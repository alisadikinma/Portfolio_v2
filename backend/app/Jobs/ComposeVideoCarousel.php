<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
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

        $toolSlides = $job->videoSlides()->where('role', RepurposeVideoSlide::ROLE_TOOL)->get();
        $total = $job->videoSlides()->count();

        $failed = 0;
        foreach ($toolSlides as $slide) {
            // Skip slides already composited (idempotent re-run).
            if ($slide->composited_status === 'done' && $slide->composited_path) {
                continue;
            }

            try {
                $chromePngs = $chrome->renderSlide($slide, $slide->slide_index, $total);
                if ($chromePngs === null) {
                    throw new \RuntimeException('chrome render returned null');
                }
                $out = $composer->composeSlide($slide, $chromePngs['header'], $chromePngs['footer']);
                if ($out === null) {
                    throw new \RuntimeException('composer returned null');
                }
            } catch (\Throwable $e) {
                $failed++;
                $slide->update(['composited_status' => 'failed', 'last_error' => 'compose failed: '.$e->getMessage()]);
                Log::warning('[ComposeVideoCarousel] slide compose failed', ['slide' => $slide->id, 'error' => $e->getMessage()]);
            }
        }

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
