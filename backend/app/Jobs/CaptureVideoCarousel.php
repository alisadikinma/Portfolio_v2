<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Services\TelegramNotificationService;
use App\Services\VideoCarouselCaptureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase B — capture step. Dispatched by
 * TelegramWebhookController::resolveRepurposeAction when the operator taps the
 * "video_rebrand" mode button (status = capturing). The video-mode sibling of
 * CaptureInstagramPost — yt-dlp downloads each carousel VIDEO slide instead of
 * the Playwright image scrape.
 *
 *   - success (≥1 video slide) → status captured + dispatch ExtractVideoSlides
 *   - 0 slides / error → status failed + Telegram reply (never silent)
 *
 * Artifact dir RETAINED on failure for retry/debug.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
class CaptureVideoCarousel implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Video download + per-slide poster/probe can run several minutes.
    public int $timeout = 360;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job) {
            return;
        }

        if ($job->status !== RepurposeJobStatus::Capturing->value) {
            Log::info('[CaptureVideoCarousel] skip — not in capturing', [
                'job' => $job->id,
                'status' => $job->status,
            ]);
            return;
        }

        try {
            $result = app(VideoCarouselCaptureService::class)->capture($job);
        } catch (\Throwable $e) {
            $this->failJob($job, 'capture_exception: ' . $e->getMessage());
            return;
        }

        if (!($result['success'] ?? false) || (int) ($result['count'] ?? 0) < 1) {
            $this->failJob($job, 'capture_failed: ' . ($result['error'] ?? 'no video slides captured'));
            return;
        }

        $count = (int) ($result['count'] ?? 0);
        try {
            app(TelegramNotificationService::class)
                ->sendRepurposeProgress($job, "🎬 {$count} video slide ke-download");
        } catch (\Throwable $e) {
            Log::warning('[CaptureVideoCarousel] progress notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
        }

        $job->transitionTo(
            RepurposeJobStatus::Captured,
            'video_capture_ok',
            ['last_error' => null]
        );
        ExtractVideoSlides::dispatch($job->id);
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
