<?php

namespace App\Jobs;

use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use App\Services\InstagramGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around InstagramGenerationService::generate().
 *
 * Dispatched by:
 *   - InstagramDraftController::regenerate (Phase E admin retry)
 *   - ScanLinkedInForCrossPost (Phase D every-2-min cron, format=carousel branch)
 *
 * Underlying service runs synchronous SSH to the plugin CLI (~30-60s).
 * Retries 2x with 60s/300s backoff. After exhaustion the draft stays in
 * Failed status awaiting admin regenerate.
 */
class GenerateInstagramPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [60, 300];

    // Sits above SOCIAL_GEN_TIMEOUT_SECONDS (300s default) to handle parsing
    // + persistence after SSH returns.
    public int $timeout = 360;

    public function __construct(public int $draftId)
    {
    }

    /**
     * Stale threshold above the SSH+job timeout budget so legitimate slow
     * runs are NOT clobbered. Mirror of LinkedIn pipeline pattern.
     */
    private const STALE_THRESHOLD_MINUTES = 15;

    public function handle(InstagramGenerationService $service): void
    {
        $draft = InstagramPost::find($this->draftId);
        if ($draft === null) {
            Log::info('[GenerateInstagramPost] Draft not found, skipping', [
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        $generatable = [
            InstagramPostStatus::PendingGeneration->value,
            InstagramPostStatus::Failed->value,
            InstagramPostStatus::Cancelled->value,
        ];
        $inFlight = [
            InstagramPostStatus::Generating->value,
        ];

        if (!in_array($draft->status, $generatable, true)) {
            if (in_array($draft->status, $inFlight, true)) {
                if ($this->isStaleInFlight($draft)) {
                    Log::warning('[GenerateInstagramPost] Stale in-flight draft, recovering via FSM Failed', [
                        'draft_id' => $draft->id,
                        'status' => $draft->status,
                        'updated_at' => $draft->updated_at?->toIso8601String(),
                    ]);
                    if (!$this->markFailedForStaleRetry($draft)) {
                        return;
                    }
                    $draft->refresh();
                } else {
                    Log::warning('[GenerateInstagramPost] Draft still in-flight (not yet stale), retry deferred', [
                        'draft_id' => $draft->id,
                        'status' => $draft->status,
                    ]);
                    return;
                }
            } else {
                Log::info('[GenerateInstagramPost] Draft no longer generatable, skipping', [
                    'draft_id' => $draft->id,
                    'status' => $draft->status,
                ]);
                return;
            }
        }

        $result = $service->generate($draft);

        Log::info('[GenerateInstagramPost] Generation complete', [
            'draft_id' => $draft->id,
            'success' => $result['success'] ?? false,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException(
                'Instagram generation failed: ' . ($result['error'] ?? 'unknown')
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[GenerateInstagramPost] Job exhausted retries', [
            'draft_id' => $this->draftId,
            'error' => $e->getMessage(),
        ]);
    }

    private function isStaleInFlight(InstagramPost $draft): bool
    {
        $updatedAt = $draft->updated_at;
        if ($updatedAt === null) {
            return false;
        }
        return $updatedAt->lessThan(now()->subMinutes(self::STALE_THRESHOLD_MINUTES));
    }

    private function markFailedForStaleRetry(InstagramPost $draft): bool
    {
        try {
            app(\App\Services\PipelineGuard::class)->advance(
                $draft,
                InstagramPostStatus::Failed,
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
            Log::error('[GenerateInstagramPost] Could not mark stale draft Failed', [
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
