<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Services\RepurposeRewriteService;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * IG repurpose Phase E — rewrite step. Dispatched by ResearchRepurposeClaims
 * once claims are verified (status researched).
 *
 *   - success → persist `rewritten` + status rewritten + dispatch FinalizeRepurpose
 *   - failure → status failed + Telegram reply
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class RewriteRepurposeContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job || $job->status !== RepurposeJobStatus::Researched->value) {
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Rewriting, 'rewrite_start');

        try {
            $result = app(RepurposeRewriteService::class)->rewrite($job);
        } catch (\Throwable $e) {
            $this->failJob($job, 'rewrite_exception: ' . $e->getMessage());
            return;
        }

        if (!($result['success'] ?? false) || empty($result['rewritten'])) {
            $this->failJob($job, 'rewrite_failed: ' . ($result['error'] ?? 'no rewrite'));
            return;
        }

        $job->transitionTo(
            RepurposeJobStatus::Rewritten,
            'rewrite_ok',
            ['rewritten' => $result['rewritten'], 'last_error' => null]
        );
        FinalizeRepurpose::dispatch($job->id);
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
