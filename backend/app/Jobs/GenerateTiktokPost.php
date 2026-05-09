<?php

namespace App\Jobs;

use App\Enums\TiktokPostStatus;
use App\Models\TiktokPost;
use App\Services\TiktokGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around TiktokGenerationService::generate(). Mirrors
 * GenerateInstagramPost — same retry policy, same stale-recovery pattern.
 */
class GenerateTiktokPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [60, 300];
    public int $timeout = 360;

    private const STALE_THRESHOLD_MINUTES = 15;

    public function __construct(public int $draftId)
    {
    }

    public function handle(TiktokGenerationService $service): void
    {
        $draft = TiktokPost::find($this->draftId);
        if ($draft === null) {
            Log::info('[GenerateTiktokPost] Draft not found, skipping', [
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        $generatable = [
            TiktokPostStatus::PendingGeneration->value,
            TiktokPostStatus::Failed->value,
            TiktokPostStatus::Cancelled->value,
        ];
        $inFlight = [TiktokPostStatus::Generating->value];

        if (!in_array($draft->status, $generatable, true)) {
            if (in_array($draft->status, $inFlight, true)) {
                if ($this->isStaleInFlight($draft)) {
                    Log::warning('[GenerateTiktokPost] Stale in-flight draft, recovering via FSM Failed', [
                        'draft_id' => $draft->id,
                        'status' => $draft->status,
                    ]);
                    if (!$this->markFailedForStaleRetry($draft)) {
                        return;
                    }
                    $draft->refresh();
                } else {
                    Log::warning('[GenerateTiktokPost] Draft still in-flight, retry deferred', [
                        'draft_id' => $draft->id,
                        'status' => $draft->status,
                    ]);
                    return;
                }
            } else {
                Log::info('[GenerateTiktokPost] Draft no longer generatable, skipping', [
                    'draft_id' => $draft->id,
                    'status' => $draft->status,
                ]);
                return;
            }
        }

        $result = $service->generate($draft);

        Log::info('[GenerateTiktokPost] Generation complete', [
            'draft_id' => $draft->id,
            'success' => $result['success'] ?? false,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException(
                'TikTok generation failed: ' . ($result['error'] ?? 'unknown')
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[GenerateTiktokPost] Job exhausted retries', [
            'draft_id' => $this->draftId,
            'error' => $e->getMessage(),
        ]);
    }

    private function isStaleInFlight(TiktokPost $draft): bool
    {
        $updatedAt = $draft->updated_at;
        if ($updatedAt === null) {
            return false;
        }
        return $updatedAt->lessThan(now()->subMinutes(self::STALE_THRESHOLD_MINUTES));
    }

    private function markFailedForStaleRetry(TiktokPost $draft): bool
    {
        try {
            app(\App\Services\PipelineGuard::class)->advance(
                $draft,
                TiktokPostStatus::Failed,
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
            Log::error('[GenerateTiktokPost] Could not mark stale draft Failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
