<?php

namespace App\Console\Commands;

use App\Enums\RepurposeJobStatus;
use App\Jobs\ComposeVideoCarousel;
use App\Jobs\GenerateRebrandAssets;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\GeminiGenVideoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase E — face-gen keyframe → Veo completion driver (2-poll).
 *
 * GeminiGen never fires webhooks (known incident — poll is the sole completion
 * path, see PollHookVideos). Per minute this command:
 *
 *   Pass A — keyframe poll: hook/CTA slides at keyframe_status='generating' →
 *     GET /history/{keyframe_job_uuid}. Image ready → store keyframe_url,
 *     dispatch the Veo clip (mode_image=frame), flip to veo_status='generating'.
 *   Pass B — veo poll: slides at veo_status='generating' → GET /history/{veo_job_uuid}.
 *     Video ready → finalizeVeoClip (9:16 → 4:5 1080×1350) → composited_status='done'.
 *   Pass C — completion: a GeneratingAssets job whose hook+CTA bookends are both
 *     composited → AssetsReady + dispatch ComposeVideoCarousel. Any bookend that
 *     hard-failed → the whole job fails (surfaces to the operator, never wedges).
 *   Pass D — recovery: bounded re-dispatch of stuck/failed bookends.
 */
class PollRebrandAssets extends Command
{
    protected $signature = 'repurpose:poll-rebrand-assets {--dry-run}';

    protected $description = 'Poll GeminiGen for video_rebrand keyframe→Veo completion + bounded recovery';

    /** Face-gen images land in ~30-60s, Veo clips in ~60-120s; past this a row is wedged. */
    private const STUCK_MINUTES = 15;

    /** Bounded re-dispatch of a failed bookend (keyframe ≈1 credit, Veo ≈5). */
    private const MAX_RETRIES = 2;

    private const FAILED_RETRY_COOLDOWN_MINUTES = 5;

    public function handle(GeminiGenVideoService $video): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->pollKeyframes($video, $dry);
        $this->pollVeo($video, $dry);
        $this->checkCompletion($dry);
        $this->recover($dry);

        return self::SUCCESS;
    }

    private function apiKey(): string
    {
        return (string) config('services.geminigen.api_key');
    }

    /** Pass A — keyframe image ready → store url + dispatch the Veo clip. */
    private function pollKeyframes(GeminiGenVideoService $video, bool $dry): void
    {
        $slides = RepurposeVideoSlide::where('keyframe_status', 'generating')
            ->whereNotNull('keyframe_job_uuid')
            ->get();

        foreach ($slides as $slide) {
            $data = $this->poll($slide->keyframe_job_uuid);
            if ($data === null) {
                $this->markStuck($slide, 'keyframe', 'poll failed', $dry);

                continue;
            }

            $imageUrl = $data['image_url']
                ?? $data['media_url']
                ?? ($data['generated_image'][0]['image_url'] ?? null);

            if ($imageUrl) {
                if ($dry) {
                    $this->line("  [dry] slide {$slide->id} keyframe ready → would dispatch Veo");

                    continue;
                }
                $prompt = $slide->role === RepurposeVideoSlide::ROLE_CTA
                    ? GenerateRebrandAssets::VEO_PROMPT_CTA
                    : GenerateRebrandAssets::VEO_PROMPT_HOOK;

                $uuid = $video->dispatchVeoClip($imageUrl, $prompt, '9:16', $slide->id);
                if ($uuid === null) {
                    // Circuit open / dispatch rejected — keep keyframe done so the
                    // recovery pass can re-dispatch Veo without re-rendering the image.
                    $slide->update(['keyframe_status' => 'done', 'keyframe_url' => $imageUrl, 'veo_status' => 'failed', 'last_error' => 'Veo dispatch failed (service unavailable)']);
                    $this->warn("  slide {$slide->id} Veo dispatch failed");

                    continue;
                }
                $slide->update([
                    'keyframe_status' => 'done', 'keyframe_url' => $imageUrl,
                    'veo_status' => 'generating', 'veo_job_uuid' => $uuid, 'last_error' => null,
                ]);
                $this->info("  slide {$slide->id} keyframe done → Veo {$uuid}");

                continue;
            }

            if ($this->hasError($data)) {
                $reason = $this->errorReason($data);
                if (! $dry) {
                    $update = ['keyframe_status' => 'failed', 'last_error' => $reason];
                    // Safety fallback (#1): a hook keyframe refused by GeminiGen's
                    // named-public-figure upload filter → drop the figure ref so
                    // the recovery pass re-authors a CREATOR-ONLY scene. The
                    // sentinel is durable (recover() blanks last_error, not this).
                    if ($slide->role === RepurposeVideoSlide::ROLE_HOOK
                        && ! $slide->figure_dropped
                        && $this->isSafetyError($reason)) {
                        $update['figure_dropped'] = true;
                        $this->warn("  slide {$slide->id} hook keyframe refused (figure) → dropping figure ref for retry");
                    }
                    $slide->update($update);
                }
                $this->warn("  slide {$slide->id} keyframe failed");

                continue;
            }

            $this->markStuck($slide, 'keyframe', 'render exceeded stuck window', $dry);
        }
    }

    /**
     * GeminiGen's named-public-figure / unsafe-upload refusal class. Mirrors
     * ImageGenerationService::isSafetyError (same deterministic substring gate) —
     * a figure photo would fail the same way on every retry, so we drop the figure
     * ref instead of grinding the retry budget.
     */
    private function isSafetyError(?string $reason): bool
    {
        if ($reason === null || trim($reason) === '') {
            return false;
        }
        $needle = strtolower($reason);
        $patterns = [
            'public_error_prominent_people_upload',
            'public_error_prominent_people',
            'public_error_minor',
            'public_error_unsafe',
            'prominent people',
            'prominent person',
            'do not allow uploading images',
            'safety filter',
            'content policy',
        ];
        foreach ($patterns as $p) {
            if (str_contains($needle, $p)) {
                return true;
            }
        }

        return false;
    }

    /** Pass B — Veo video ready → finalize 4:5 clip → composited done. */
    private function pollVeo(GeminiGenVideoService $video, bool $dry): void
    {
        $slides = RepurposeVideoSlide::where('veo_status', 'generating')
            ->whereNotNull('veo_job_uuid')
            ->get();

        foreach ($slides as $slide) {
            $data = $this->poll($slide->veo_job_uuid);
            if ($data === null) {
                $this->markStuck($slide, 'veo', 'poll failed', $dry);

                continue;
            }

            $videoUrl = $data['generated_video'][0]['video_url'] ?? $data['media_url'] ?? null;

            if ($videoUrl) {
                if ($dry) {
                    $this->line("  [dry] slide {$slide->id} veo ready → would finalize");

                    continue;
                }
                $relOut = "repurpose/{$slide->repurpose_job_id}/composited/slide_{$slide->slide_index}.mp4";
                $final = $video->finalizeVeoClip($videoUrl, $relOut);
                if ($final !== null) {
                    $slide->update(['veo_status' => 'done', 'veo_url' => $videoUrl, 'composited_path' => $final, 'composited_status' => 'done', 'last_error' => null]);
                    $this->info("  slide {$slide->id} veo done");
                } else {
                    $slide->update(['veo_status' => 'failed', 'last_error' => 'Download/crop of finished Veo clip failed — see logs.']);
                    $this->warn("  slide {$slide->id} finalize failed");
                }

                continue;
            }

            if ($this->hasError($data)) {
                if (! $dry) {
                    $slide->update(['veo_status' => 'failed', 'last_error' => $this->errorReason($data)]);
                }
                $this->warn("  slide {$slide->id} veo failed");

                continue;
            }

            $this->markStuck($slide, 'veo', 'render exceeded stuck window', $dry);
        }
    }

    /** Pass C — promote the job once both bookends are composited (or fail it). */
    private function checkCompletion(bool $dry): void
    {
        $jobs = RepurposeJob::where('mode', 'video_rebrand')
            ->where('status', RepurposeJobStatus::GeneratingAssets->value)
            ->get();

        foreach ($jobs as $job) {
            $bookends = $job->videoSlides()
                ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
                ->get();

            if ($bookends->isEmpty()) {
                continue;
            }

            // A hard-failed keyframe/veo that exhausted retries fails the job.
            $hardFailed = $bookends->first(fn ($s) => in_array($s->keyframe_status, ['failed'], true) || in_array($s->veo_status, ['failed'], true));
            $allDone = $bookends->every(fn ($s) => $s->composited_status === 'done');

            if ($allDone) {
                if ($dry) {
                    $this->line("  [dry] job {$job->id} bookends done → would compose");

                    continue;
                }
                $job->transitionTo(RepurposeJobStatus::AssetsReady, 'video_assets_ready', ['last_error' => null]);
                ComposeVideoCarousel::dispatch($job->id);
                $this->info("  job {$job->id} → assets_ready, composing");

                continue;
            }

            if ($hardFailed && ! $dry) {
                // Only fail the job once retries are exhausted (recovery pass owns the budget).
                // Scope the error check to bookends (like $hardFailed/$allDone) —
                // a stale tool-slide last_error must not prematurely fail the job.
                $exhausted = $bookends->every(fn ($s) => $s->keyframe_status !== 'generating' && $s->veo_status !== 'generating')
                    && $bookends->contains(fn ($s) => $s->last_error !== null)
                    && (int) ($job->asset_retry_count ?? 0) >= self::MAX_RETRIES;
                if ($exhausted) {
                    $job->transitionTo(RepurposeJobStatus::Failed, 'video_assets_failed', ['last_error' => 'A hook/CTA clip failed to generate after retries — see slide errors.']);
                    $this->warn("  job {$job->id} → failed (asset generation exhausted)");
                }
            }
        }
    }

    /** Pass D — bounded re-dispatch of failed bookends after a cooldown. */
    private function recover(bool $dry): void
    {
        $jobs = RepurposeJob::where('mode', 'video_rebrand')
            ->where('status', RepurposeJobStatus::GeneratingAssets->value)
            ->where('updated_at', '<', now()->subMinutes(self::FAILED_RETRY_COOLDOWN_MINUTES))
            ->get();

        foreach ($jobs as $job) {
            $failedBookend = $job->videoSlides()
                ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
                ->where(fn ($q) => $q->where('keyframe_status', 'failed')->orWhere('veo_status', 'failed'))
                ->exists();

            if (! $failedBookend) {
                continue;
            }
            if ((int) ($job->asset_retry_count ?? 0) >= self::MAX_RETRIES) {
                continue;
            }
            if ($dry) {
                $this->line("  [dry] job {$job->id} has failed bookend → would re-dispatch assets");

                continue;
            }

            // Reset failed bookends so GenerateRebrandAssets' idempotent dispatch re-runs them.
            $job->videoSlides()
                ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
                ->where(fn ($q) => $q->where('keyframe_status', 'failed')->orWhere('veo_status', 'failed'))
                ->update(['keyframe_status' => null, 'veo_status' => null, 'last_error' => null]);

            $job->increment('asset_retry_count');
            // Bounce back to extracted so GenerateRebrandAssets' Extracted guard passes.
            $job->transitionTo(RepurposeJobStatus::Extracted, 'video_assets_retry');
            GenerateRebrandAssets::dispatch($job->id);
            $this->info("  job {$job->id} re-dispatched assets (retry #{$job->asset_retry_count})");
        }
    }

    private function poll(?string $uuid): ?array
    {
        if (empty($uuid)) {
            return null;
        }
        try {
            $resp = Http::timeout(15)
                ->withHeaders(['x-api-key' => $this->apiKey()])
                ->get("https://api.geminigen.ai/uapi/v1/history/{$uuid}");
        } catch (\Throwable $e) {
            Log::warning('[PollRebrandAssets] poll exception', ['uuid' => $uuid, 'error' => $e->getMessage()]);

            return null;
        }

        return $resp->successful() ? ($resp->json() ?? []) : null;
    }

    /**
     * GeminiGen ALWAYS returns error_code/error_message keys ("" when healthy),
     * so a real failure is status===3 OR a non-empty error string (see PollHookVideos).
     */
    private function hasError(array $data): bool
    {
        $errCode = is_string($data['error_code'] ?? null) ? trim($data['error_code']) : '';
        $errMsg = is_string($data['error_message'] ?? null) ? trim($data['error_message']) : '';

        return (int) ($data['status'] ?? 0) === 3 || $errCode !== '' || $errMsg !== '';
    }

    private function errorReason(array $data): string
    {
        $errMsg = is_string($data['error_message'] ?? null) ? trim($data['error_message']) : '';
        $errCode = is_string($data['error_code'] ?? null) ? trim($data['error_code']) : '';

        return $errMsg !== '' ? $errMsg : ($errCode !== '' ? $errCode : 'GeminiGen reported a render failure.');
    }

    private function markStuck(RepurposeVideoSlide $slide, string $stage, string $reason, bool $dry): void
    {
        $age = $slide->updated_at ? (int) $slide->updated_at->diffInMinutes(now()) : 0;
        if ($age < self::STUCK_MINUTES) {
            return;
        }
        $col = $stage === 'veo' ? 'veo_status' : 'keyframe_status';
        if (! $dry) {
            $slide->update([$col => 'failed', 'last_error' => "{$reason} (stuck {$age}min)"]);
        }
        $this->warn("  slide {$slide->id} {$stage} stuck {$age}min → failed");
    }
}
