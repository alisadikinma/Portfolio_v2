<?php

namespace App\Console\Commands;

use App\Jobs\GenerateHookVideo;
use App\Models\InstagramPost;
use App\Services\GeminiGenVideoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GROK hook-video completion poll + recovery (June 12, 2026, Phase D+E).
 *
 * GeminiGen never fires webhooks (known incident — the blog:process-images
 * poller is the sole image completion path). Hook videos mirror that: this
 * per-minute command is the SOLE completion driver.
 *
 * Pass 1 (completion): for IG drafts with hook_video_status='generating', poll
 *   GET /history/{uuid}. Done = generated_video[0].video_url present → download,
 *   crop 2:3→4:5, strip audio, store, status=done. Explicit GROK failure →
 *   status=failed. A render that exceeds the stuck window → status=failed.
 *
 * Pass 2 (recovery): re-dispatch FAILED hook videos up to MAX_RETRIES (each GROK
 *   clip ≈ 5 credits, so retries are bounded); also re-dispatch PENDING drafts
 *   whose worker pickup was missed. The circuit breaker inside the service makes
 *   re-dispatch a no-op during a GeminiGen outage, so credits aren't burned.
 */
class PollHookVideos extends Command
{
    protected $signature = 'crosspost:poll-hook-videos {--dry-run}';

    protected $description = 'Poll GROK for IG hook-video completion + bounded recovery of failed/stuck hook videos';

    /** GROK renders in ~60-90s; past this a 'generating' row is wedged. */
    private const STUCK_GENERATING_MINUTES = 15;

    /** Bounded re-dispatch of FAILED hook videos (≈5 credits each). */
    private const MAX_RETRIES = 2;

    /** Cooldown before a FAILED hook video is retried — avoids same-tick retry
     *  of a just-completed failure and gives GROK / the circuit time to recover. */
    private const FAILED_RETRY_COOLDOWN_MINUTES = 5;

    /** A 'pending' (queued-for-retry) row older than this lost its worker pickup. */
    private const PENDING_REDISPATCH_MINUTES = 3;

    public function handle(GeminiGenVideoService $video): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->pollGenerating($video, $dry);
        $this->recover($dry);

        return self::SUCCESS;
    }

    private function pollGenerating(GeminiGenVideoService $video, bool $dry): void
    {
        $rows = InstagramPost::where('hook_video_status', 'generating')
            ->whereNotNull('hook_video_job_uuid')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $apiKey = config('services.geminigen.api_key');

        foreach ($rows as $ig) {
            try {
                $resp = Http::timeout(15)
                    ->withHeaders(['x-api-key' => $apiKey])
                    ->get("https://api.geminigen.ai/uapi/v1/history/{$ig->hook_video_job_uuid}");
            } catch (\Throwable $e) {
                $this->markStuckIfElapsed($ig, "poll exception: {$e->getMessage()}", $dry);

                continue;
            }

            if (! $resp->successful()) {
                $this->markStuckIfElapsed($ig, "poll HTTP {$resp->status()}", $dry);

                continue;
            }

            $data = $resp->json() ?? [];
            $videoUrl = $data['generated_video'][0]['video_url']
                ?? $data['media_url']
                ?? null;
            $status = (int) ($data['status'] ?? 0);
            $hasError = $status === 3
                || isset($data['error'])
                || isset($data['error_message']);

            if ($videoUrl) {
                if ($dry) {
                    $this->line("  [dry] ig {$ig->id} ready → would finalize");

                    continue;
                }
                $final = $video->finalizeHookVideo($videoUrl, $ig->id);
                if ($final !== null) {
                    $ig->update(['hook_video_status' => 'done', 'hook_video_url' => $final, 'hook_video_error' => null]);
                    $this->info("  ig {$ig->id} hook video done");
                } else {
                    $ig->update(['hook_video_status' => 'failed', 'hook_video_error' => 'Download/crop of finished GROK video failed — see logs.']);
                    $this->warn("  ig {$ig->id} finalize failed");
                }

                continue;
            }

            if ($hasError) {
                $reason = is_string($data['error_message'] ?? null) ? $data['error_message'] : 'GROK reported a render failure.';
                if (! $dry) {
                    $ig->update(['hook_video_status' => 'failed', 'hook_video_error' => $reason]);
                }
                $this->warn("  ig {$ig->id} GROK failed: {$reason}");

                continue;
            }

            // Still rendering — only fail it once the stuck window elapses.
            $this->markStuckIfElapsed($ig, 'GROK render exceeded the stuck window', $dry);
        }
    }

    private function markStuckIfElapsed(InstagramPost $ig, string $reason, bool $dry): void
    {
        $age = $ig->updated_at ? (int) $ig->updated_at->diffInMinutes(now()) : 0;
        if ($age < self::STUCK_GENERATING_MINUTES) {
            return;
        }
        if (! $dry) {
            $ig->update(['hook_video_status' => 'failed', 'hook_video_error' => "{$reason} (stuck {$age}min)"]);
        }
        $this->warn("  ig {$ig->id} stuck {$age}min → failed ({$reason})");
    }

    private function recover(bool $dry): void
    {
        // Bounded re-dispatch of failed hook videos — after a cooldown so a
        // just-marked failure isn't retried in the same tick.
        $failed = InstagramPost::where('hook_video_status', 'failed')
            ->where('hook_video_retry_count', '<', self::MAX_RETRIES)
            ->where('updated_at', '<', now()->subMinutes(self::FAILED_RETRY_COOLDOWN_MINUTES))
            ->get();

        foreach ($failed as $ig) {
            if ($dry) {
                $this->line("  [dry] ig {$ig->id} failed → would retry (#{$ig->hook_video_retry_count})");

                continue;
            }
            $ig->increment('hook_video_retry_count');
            // 'pending' = queued-for-retry; keeps the next tick from double-dispatching.
            $ig->update(['hook_video_status' => 'pending', 'hook_video_error' => null]);
            GenerateHookVideo::dispatch($ig->id);
            $this->info("  ig {$ig->id} re-dispatched (retry #{$ig->hook_video_retry_count})");
        }

        // Pending rows whose worker pickup was missed.
        $stalePending = InstagramPost::where('hook_video_status', 'pending')
            ->where('updated_at', '<', now()->subMinutes(self::PENDING_REDISPATCH_MINUTES))
            ->get();

        foreach ($stalePending as $ig) {
            if ($dry) {
                $this->line("  [dry] ig {$ig->id} stale pending → would re-dispatch");

                continue;
            }
            GenerateHookVideo::dispatch($ig->id);
            $this->info("  ig {$ig->id} stale pending re-dispatched");
        }
    }
}
