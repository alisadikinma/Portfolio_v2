<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Services\SlideVisionExtractor;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * IG repurpose Phase C — vision-extract step. Dispatched by CaptureInstagramPost
 * once slides land (status captured).
 *
 *   - success → persist `extracted` JSON + status extracted + dispatch
 *     ResearchRepurposeClaims
 *   - failure → status failed + Telegram reply (no half-state)
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class ExtractSlideContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job || $job->status !== RepurposeJobStatus::Captured->value) {
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Extracting, 'extract_start');

        try {
            $result = app(SlideVisionExtractor::class)->extract($job);
        } catch (\Throwable $e) {
            $this->failJob($job, 'extract_exception: ' . $e->getMessage());
            return;
        }

        if (!($result['success'] ?? false) || empty($result['extracted'])) {
            $this->failJob($job, 'extract_failed: ' . ($result['error'] ?? 'no extraction'));
            return;
        }

        $job->transitionTo(
            RepurposeJobStatus::Extracted,
            'extract_ok',
            ['extracted' => $result['extracted'], 'last_error' => null]
        );
        ResearchRepurposeClaims::dispatch($job->id);
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
