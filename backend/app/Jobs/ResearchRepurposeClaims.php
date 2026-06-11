<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Services\RepurposeResearchService;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase D — fact-check step. Dispatched by ExtractSlideContent
 * once claims are extracted (status extracted).
 *
 *   - success → persist `research` (verdicts + sources) + status researched +
 *     dispatch RewriteRepurposeContent
 *   - failure → status failed + Telegram reply
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class ResearchRepurposeClaims implements ShouldQueue
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
        if (!$job || $job->status !== RepurposeJobStatus::Extracted->value) {
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Researching, 'research_start');

        try {
            $result = app(RepurposeResearchService::class)->research($job);
        } catch (\Throwable $e) {
            $this->failJob($job, 'research_exception: ' . $e->getMessage());
            return;
        }

        if (!($result['success'] ?? false) || empty($result['research'])) {
            $this->failJob($job, 'research_failed: ' . ($result['error'] ?? 'no research'));
            return;
        }

        $corrected = (int) ($result['research']['corrected_count'] ?? 0);
        try {
            app(TelegramNotificationService::class)
                ->sendRepurposeProgress($job, "✅ Fact-check: {$corrected} klaim dikoreksi");
        } catch (\Throwable $e) {
            Log::warning('[ResearchRepurposeClaims] progress notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
        }

        $job->transitionTo(
            RepurposeJobStatus::Researched,
            'research_ok',
            ['research' => $result['research'], 'last_error' => null]
        );
        RewriteRepurposeContent::dispatch($job->id);
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
