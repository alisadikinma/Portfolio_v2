<?php

namespace App\Jobs;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use App\Services\LinkedInGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around LinkedInGenerationService::generate().
 *
 * Dispatched by:
 *   - LinkedInDraftController::regenerate (admin retry)
 *   - ScanBlogForLinkedInConversion (daily 03:00 WIB cron — new blog posts)
 *
 * The underlying service runs synchronous SSH to the plugin CLI (~30-90s).
 * Retries 2x with 60s/300s backoff. After exhaustion the draft stays in
 * Failed status, awaiting admin regenerate.
 */
class GenerateLinkedInPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [60, 300];

    // Timeout sits above the service's SSH timeout so the job doesn't get
    // killed mid-Sonnet. Service default raised to 600s after a production
    // run on blog #24 measured 369s wall — see config/linkedin.php for the
    // analysis. Job gets 60s extra to handle parsing + persistence after
    // SSH returns.
    public int $timeout = 660;

    public function __construct(public int $draftId)
    {
    }

    public function handle(LinkedInGenerationService $service): void
    {
        $draft = LinkedInPost::find($this->draftId);
        if ($draft === null) {
            Log::info('[GenerateLinkedInPost] Draft not found, skipping', [
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        // Skip if draft is no longer in a generatable state (e.g. admin cancelled)
        $generatable = [
            LinkedInPostStatus::PendingGeneration->value,
            LinkedInPostStatus::Failed->value,
            LinkedInPostStatus::Cancelled->value,
        ];
        if (!in_array($draft->status, $generatable, true)) {
            Log::info('[GenerateLinkedInPost] Draft no longer generatable, skipping', [
                'draft_id' => $draft->id,
                'status' => $draft->status,
            ]);
            return;
        }

        $result = $service->generate($draft);

        Log::info('[GenerateLinkedInPost] Generation complete', [
            'draft_id' => $draft->id,
            'success' => $result['success'] ?? false,
            'status' => $result['status'] ?? null,
            'depth_score' => $result['depth_score'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        // Throw on failure so the queue retries per $backoff. After $tries
        // exhaustion, the draft remains in Failed status (markFailed already
        // ran) and the queue worker moves on.
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException(
                'LinkedIn generation failed: ' . ($result['error'] ?? 'unknown')
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[GenerateLinkedInPost] Job exhausted retries', [
            'draft_id' => $this->draftId,
            'error' => $e->getMessage(),
        ]);
    }
}
