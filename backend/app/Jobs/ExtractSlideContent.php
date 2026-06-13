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
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase C — vision-extract step. Dispatched by CaptureInstagramPost
 * once slides land (status captured).
 *
 *   - success → persist `extracted` JSON + status extracted, then branch on mode:
 *       carousel → ResearchRepurposeClaims (rewrite seeds /carousel-gen)
 *       blog     → FinalizeRepurpose (skip research+rewrite; seed a draft
 *                  ContentIdea for the proper Content Engine pipeline)
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

        $claimCount = count((array) ($result['extracted']['claims'] ?? []));
        try {
            app(TelegramNotificationService::class)
                ->sendRepurposeProgress($job, "🔎 Ekstrak: {$claimCount} klaim ditemukan");
        } catch (\Throwable $e) {
            Log::warning('[ExtractSlideContent] progress notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
        }

        $job->transitionTo(
            RepurposeJobStatus::Extracted,
            'extract_ok',
            ['extracted' => $result['extracted'], 'last_error' => null]
        );

        // Blog mode skips the internal research+rewrite (low quality, no scoring)
        // and hands the extracted material straight to FinalizeRepurpose, which
        // seeds a draft ContentIdea for the proper Content Engine pipeline.
        // Carousel keeps research+rewrite (the rewrite is just a /carousel-gen seed).
        if ($job->mode === 'blog') {
            FinalizeRepurpose::dispatch($job->id);
        } else {
            ResearchRepurposeClaims::dispatch($job->id);
        }
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
