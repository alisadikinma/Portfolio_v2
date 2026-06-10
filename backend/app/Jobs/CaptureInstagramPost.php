<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Services\InstagramCaptureService;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase B — capture step. Dispatched by
 * TelegramWebhookController::resolveRepurposeAction once the operator taps a
 * mode button (status = capturing).
 *
 * Runs the Playwright capture via InstagramCaptureService:
 *   - success (>=1 slide) → status captured + dispatch ExtractSlideContent
 *   - 0 slides / login wall / error → status failed + Telegram reply (NEVER a
 *     silent fail — the operator is asked to paste/check)
 *
 * On failure the artifact dir is RETAINED for retry/debug (purge happens only
 * on success at finalize, D6).
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class CaptureInstagramPost implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 200;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job) {
            return;
        }

        // Idempotency — only run while in the capturing state. A duplicate
        // dispatch (or one that lands after the pipeline moved on) is a no-op.
        if ($job->status !== RepurposeJobStatus::Capturing->value) {
            Log::info('[CaptureInstagramPost] skip — not in capturing', [
                'job' => $job->id,
                'status' => $job->status,
            ]);
            return;
        }

        try {
            $result = app(InstagramCaptureService::class)->capture($job);
        } catch (\Throwable $e) {
            $this->failJob($job, 'capture_exception: ' . $e->getMessage());
            return;
        }

        if (!($result['success'] ?? false) || (int) ($result['count'] ?? 0) < 1) {
            $this->failJob($job, 'capture_failed: ' . ($result['error'] ?? 'no slides captured'));
            return;
        }

        $job->transitionTo(
            RepurposeJobStatus::Captured,
            'capture_ok',
            ['last_error' => null]
        );
        ExtractSlideContent::dispatch($job->id);
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
