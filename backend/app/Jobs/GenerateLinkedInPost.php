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

    /**
     * How long a draft can sit in generating/validating before we consider
     * its in-flight attempt dead. Threshold sits above the 10-min SSH
     * budget (LINKEDIN_GEN_TIMEOUT_SECONDS=600s) + 60s job-margin so
     * legitimate slow runs are NOT clobbered. Mirror of the reaper cron
     * threshold so both recovery paths agree on what "stuck" means.
     */
    private const STALE_THRESHOLD_MINUTES = 20;

    public function handle(LinkedInGenerationService $service): void
    {
        $draft = LinkedInPost::find($this->draftId);
        if ($draft === null) {
            Log::info('[GenerateLinkedInPost] Draft not found, skipping', [
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        $generatable = [
            LinkedInPostStatus::PendingGeneration->value,
            LinkedInPostStatus::Failed->value,
            LinkedInPostStatus::Cancelled->value,
        ];
        $inFlight = [
            LinkedInPostStatus::Generating->value,
            LinkedInPostStatus::Validating->value,
        ];

        if (!in_array($draft->status, $generatable, true)) {
            // In-flight states need stale-aware handling. The original code
            // here was a silent-return for any non-generatable status, which
            // turned every SSH timeout / queue worker crash into a permanent
            // stuck row (see ReapStuckLinkedInPosts for context).
            if (in_array($draft->status, $inFlight, true)) {
                if ($this->isStaleInFlight($draft)) {
                    Log::warning('[GenerateLinkedInPost] Stale in-flight draft, recovering via FSM Failed', [
                        'draft_id' => $draft->id,
                        'status' => $draft->status,
                        'updated_at' => $draft->updated_at?->toIso8601String(),
                    ]);

                    // Flip to Failed first so the service's own "already in
                    // progress" check (LinkedInGenerationService::generate
                    // line 50) doesn't reject us. Failed → Generating is the
                    // FSM-blessed retry edge — same path the admin "Restart
                    // from blog" button uses.
                    if (!$this->markFailedForStaleRetry($draft)) {
                        return; // markFailedForStaleRetry already logged
                    }
                    $draft->refresh();
                } else {
                    Log::warning('[GenerateLinkedInPost] Draft still in-flight (not yet stale), retry deferred', [
                        'draft_id' => $draft->id,
                        'status' => $draft->status,
                        'updated_at' => $draft->updated_at?->toIso8601String(),
                        'stale_threshold_minutes' => self::STALE_THRESHOLD_MINUTES,
                    ]);
                    return;
                }
            } else {
                // Terminal states (published) or the awaiting/manual states
                // that legitimately should not be re-dispatched. Skip silently.
                Log::info('[GenerateLinkedInPost] Draft no longer generatable, skipping', [
                    'draft_id' => $draft->id,
                    'status' => $draft->status,
                ]);
                return;
            }
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

    /**
     * Has this draft been in-flight without any DB activity for longer
     * than the SSH+job timeout budget? Anything past STALE_THRESHOLD_MINUTES
     * past `updated_at` cannot be a live attempt — the SSH process
     * has either timed out, the worker died, or the PHP request crashed
     * mid-write.
     *
     * For `generating` rows nothing else writes to the row between
     * guard.advance(Generating) and the next FSM transition (markFailed
     * or persistAndRoute). For `validating` the window is tiny (sync
     * inside persistAndRoute), so any age past threshold is unambiguous.
     */
    private function isStaleInFlight(LinkedInPost $draft): bool
    {
        $updatedAt = $draft->updated_at;
        if ($updatedAt === null) {
            return false;
        }
        return $updatedAt->lessThan(now()->subMinutes(self::STALE_THRESHOLD_MINUTES));
    }

    /**
     * Force a stale in-flight draft to Failed so the service can re-enter
     * Generating cleanly. Returns false if the transition itself failed,
     * in which case we skip processing this tick (next reaper tick or
     * next queue retry will pick it up — never clobber).
     */
    private function markFailedForStaleRetry(LinkedInPost $draft): bool
    {
        try {
            app(\App\Services\PipelineGuard::class)->advance(
                $draft,
                LinkedInPostStatus::Failed,
                'stale_retry_recovery',
                [
                    'previous_status' => $draft->status,
                    'stale_threshold_minutes' => self::STALE_THRESHOLD_MINUTES,
                ]
            );
            $draft->update([
                'last_error' => 'Recovered from stale in-flight state by queue retry',
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('[GenerateLinkedInPost] Could not mark stale draft Failed', [
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
