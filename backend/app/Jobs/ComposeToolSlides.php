<?php

namespace App\Jobs;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoChromeRenderer;
use App\Services\VideoRebrandComposer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase E (parallel) — re-skin the source `tool` slides into brand
 * chrome EARLY, in parallel with the Veo hook/CTA bookend generation.
 *
 * Dispatched by GenerateRebrandAssets right after the bookend keyframes go out.
 * A tool slide is just the source video re-stacked with header/footer chrome — it
 * has NO dependency on the hook or CTA clips. Composing the tool slides only at
 * `assets_ready` (after BOTH bookends finish) needlessly let a slow/failed hook
 * block all 7 tool slides. This job decouples them: each tool slide becomes
 * individually downloadable in Social Studio the moment it composites, regardless
 * of the bookends' fate.
 *
 * Idempotent + FSM-neutral: it never transitions the job (the assets_ready gate,
 * ComposeVideoCarousel, still owns Compositing → Composed → Finalize and mops up
 * any straggler). composeJobToolSlides persists per-slide status + download URL.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E/F
 */
class ComposeToolSlides implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(VideoChromeRenderer $chrome, VideoRebrandComposer $composer): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (! $job || $job->mode !== 'video_rebrand') {
            return;
        }

        // Nothing to do until the source tool slides exist (set by ExtractVideoSlides).
        if (! $job->videoSlides()->where('role', RepurposeVideoSlide::ROLE_TOOL)->exists()) {
            return;
        }

        $failed = $composer->composeJobToolSlides($job, $chrome);

        if ($failed > 0) {
            // Failures are NOT fatal here — the assets_ready gate (ComposeVideoCarousel)
            // retries any non-done tool slide and owns the job-level fail decision.
            // This early pass just gets the good slides downloadable ASAP.
            Log::warning('[ComposeToolSlides] some tool slides failed (gate will retry)', [
                'job' => $job->id,
                'failed' => $failed,
            ]);
        }
    }
}
