<?php

namespace App\Jobs;

use App\Models\InstagramPost;
use App\Services\GeminiGenVideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * GROK hook-video job (June 12, 2026) — Instagram mixed carousel.
 *
 * Turns an IG draft's hook slide into a short GROK image-to-video animation.
 * Dispatched (Phase F) once the parent LinkedInPost carousel's hook slide has
 * rendered AND the IG sibling exists; also re-dispatched by the Phase E reaper
 * and the admin "Regenerate hook video" action.
 *
 * Idempotent: skips when hook_video_status is already generating/done. On a
 * dispatch failure it sets status=failed so the reaper can retry (bounded);
 * circuit-open is surfaced the same way and self-heals once the breaker closes.
 *
 * Runs on the social-crosspost pool. 1 try (the service + reaper own retries),
 * 360s timeout (frame download + ffmpeg pad + the GROK multipart handshake).
 */
class GenerateHookVideo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;

    public int $tries = 1;

    public function __construct(public readonly int $instagramPostId)
    {
        $this->onQueue('social-crosspost');
    }

    public function handle(GeminiGenVideoService $service): void
    {
        $ig = InstagramPost::find($this->instagramPostId);
        if (! $ig) {
            Log::warning('[GenerateHookVideo] IG draft not found', ['ig' => $this->instagramPostId]);

            return;
        }

        // Idempotency — work in flight or already complete.
        if (in_array($ig->hook_video_status, ['generating', 'done'], true)) {
            Log::info('[GenerateHookVideo] skip — already '.$ig->hook_video_status, ['ig' => $ig->id]);

            return;
        }

        $li = $ig->linkedinPost;
        if (! $li || $li->format !== 'carousel') {
            Log::info('[GenerateHookVideo] bail — parent not a carousel', ['ig' => $ig->id]);

            return;
        }

        $slides = $li->carousel_slides ?? [];
        $hook = is_array($slides) ? ($slides[0] ?? null) : null;
        $hookUrl = is_array($hook) ? ($hook['image_url'] ?? null) : null;
        $hookDone = is_array($hook) && ($hook['image_status'] ?? null) === 'done';

        if (! $hookDone || empty($hookUrl)) {
            Log::info('[GenerateHookVideo] bail — hook slide not rendered', ['ig' => $ig->id]);

            return;
        }

        $frameUrl = $service->prepareFrame($hookUrl, $ig->id);
        if ($frameUrl === null) {
            $ig->update([
                'hook_video_status' => 'failed',
                'hook_video_error' => 'Frame preparation (flatten + pad) failed — see logs.',
            ]);

            return;
        }

        $uuid = $service->dispatchHookVideo($ig, $frameUrl);
        if ($uuid === null) {
            // Circuit-open or dispatch failure — mark failed; the reaper retries
            // (and skips again while the circuit is open, succeeding once closed).
            $ig->update([
                'hook_video_status' => 'failed',
                'hook_video_error' => 'GROK dispatch failed (service unavailable or rejected) — see logs.',
            ]);

            return;
        }

        $ig->update([
            'hook_video_status' => 'generating',
            'hook_video_job_uuid' => $uuid,
            'hook_video_error' => null,
        ]);

        Log::info('[GenerateHookVideo] dispatched', ['ig' => $ig->id, 'uuid' => $uuid]);
    }
}
