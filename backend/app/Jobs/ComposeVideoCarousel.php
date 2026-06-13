<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Services\TelegramNotificationService;
use App\Services\VideoChromeRenderer;
use App\Services\VideoRebrandComposer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase F — composite the source tool slides into Ali's brand
 * chrome. Dispatched by PollRebrandAssets once both Veo bookends are composited
 * (status assets_ready). Per tool slide: render header+footer PNG (VideoChromeRenderer)
 * → ffmpeg vstack the center 16:9 crop between them (VideoRebrandComposer) → 4:5 mp4.
 *
 *   assets_ready → compositing → composed → FinalizeRepurpose (video branch)
 *
 * Hook/CTA slides are ALREADY final 4:5 clips (composited at the Veo finalize
 * step) — only the `tool` slides need chrome composition here.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase F
 */
class ComposeVideoCarousel implements ShouldQueue
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
        if (! $job || $job->status !== RepurposeJobStatus::AssetsReady->value) {
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Compositing, 'video_compose_start', ['last_error' => null]);

        // Mop up any tool slide not already re-skinned by the early ComposeToolSlides
        // pass (idempotent — done slides are skipped). This is the assets_ready gate:
        // by here the bookends are done, so once every tool slide is composited the
        // carousel is whole and we advance to finalize.
        $failed = $composer->composeJobToolSlides($job, $chrome);

        if ($failed > 0) {
            $job->transitionTo(RepurposeJobStatus::Failed, 'video_compose_failed', ['last_error' => "{$failed} tool slide(s) failed to composite — see slide errors."]);
            try {
                app(TelegramNotificationService::class)->sendRepurposeFailed($job, "Compositing gagal di {$failed} slide.");
            } catch (\Throwable $e) {
                Log::warning('[ComposeVideoCarousel] fail notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
            }

            return;
        }

        $job->transitionTo(RepurposeJobStatus::Composed, 'video_compose_ok', ['last_error' => null]);
        FinalizeRepurpose::dispatch($job->id);
    }
}
