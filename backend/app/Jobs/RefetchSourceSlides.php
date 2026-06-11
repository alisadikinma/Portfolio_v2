<?php

namespace App\Jobs;

use App\Models\RepurposeJob;
use App\Services\InstagramCaptureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-download a repurpose job's source Instagram slides on demand.
 *
 * Captured slides are PRIVATE and get reaped a week after publish
 * (ReapRepurposeArtifacts), so an older drafted job loses its source. This job
 * re-runs the same Playwright capture into the job's slides_path so the
 * Social Studio source↔generated comparison can be restored.
 *
 * REVIEW-ONLY: it never advances the FSM (the job is already drafted/published).
 * Capture failure is logged, not surfaced as a pipeline failure — the IG post
 * may have been deleted/changed, which is not a pipeline error.
 *
 * @see App\Http\Controllers\Api\Admin\RepurposeJobController::refetchSource
 */
class RefetchSourceSlides implements ShouldQueue
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
        if (!$job || (string) $job->source_url === '') {
            return;
        }

        try {
            $result = app(InstagramCaptureService::class)->capture($job);
        } catch (\Throwable $e) {
            Log::warning('[RefetchSourceSlides] capture threw', ['job' => $job->id, 'error' => $e->getMessage()]);
            return;
        }

        if (!($result['success'] ?? false) || (int) ($result['count'] ?? 0) < 1) {
            Log::warning('[RefetchSourceSlides] re-fetch produced no slides', [
                'job' => $job->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
            return;
        }

        Log::info('[RefetchSourceSlides] re-fetched source', ['job' => $job->id, 'count' => $result['count']]);
    }
}
