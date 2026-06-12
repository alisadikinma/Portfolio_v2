<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Services\TelegramNotificationService;
use App\Services\VideoSlideExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase C — vision-extract step. Dispatched by CaptureVideoCarousel
 * once the source video slides land (status captured). Recovers each tool slide's
 * header title + description, then forks to asset generation (Veo hook/CTA).
 *
 *   - success → status extracted + dispatch GenerateRebrandAssets
 *   - failure → status failed + Telegram reply (no half-state)
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
class ExtractVideoSlides implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Covers one CLI attempt + one repair retry (2x services.repurpose.timeout).
    public int $timeout = 1920;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job || $job->status !== RepurposeJobStatus::Captured->value) {
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Extracting, 'video_extract_start');

        try {
            $result = app(VideoSlideExtractor::class)->extract($job);
        } catch (\Throwable $e) {
            $this->failJob($job, 'extract_exception: ' . $e->getMessage());
            return;
        }

        if (!($result['success'] ?? false)) {
            $this->failJob($job, 'extract_failed: ' . ($result['error'] ?? 'no extraction'));
            return;
        }

        try {
            app(TelegramNotificationService::class)
                ->sendRepurposeProgress($job, '🔎 Judul tiap slide ke-ekstrak');
        } catch (\Throwable $e) {
            Log::warning('[ExtractVideoSlides] progress notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
        }

        // End at the shared `extracted` state; GenerateRebrandAssets guards on it
        // and forks Extracted → GeneratingAssets (the video_rebrand branch).
        $job->transitionTo(RepurposeJobStatus::Extracted, 'video_extract_ok', ['last_error' => null]);
        GenerateRebrandAssets::dispatch($job->id);
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
