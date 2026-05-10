<?php

namespace App\Jobs;

use App\Enums\ThreadsPostStatus;
use App\Models\ThreadsPost;
use App\Services\ThreadsGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around ThreadsGenerationService::generate(). Post-May 10
 * Tier-1 upgrade: SSH-invokes Claude CLI with /threads-gen plugin
 * (~30-90s typical). Older "sub-second pure-reuse" comment was stale.
 */
class GenerateThreadsPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [60, 300];
    // 360s = matches IG/TikTok job timeout post-Tier-1 upgrade. Plugin
    // SSH-invoke + Sonnet output cap can spike past 60s; lower timeout
    // would mark legitimate slow runs as failed.
    public int $timeout = 360;

    private const STALE_THRESHOLD_MINUTES = 15;

    public function __construct(public int $draftId)
    {
    }

    public function handle(ThreadsGenerationService $service): void
    {
        $draft = ThreadsPost::find($this->draftId);
        if ($draft === null) {
            Log::info('[GenerateThreadsPost] Draft not found, skipping', [
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        $generatable = [
            ThreadsPostStatus::PendingGeneration->value,
            ThreadsPostStatus::Failed->value,
            ThreadsPostStatus::Cancelled->value,
        ];
        $inFlight = [ThreadsPostStatus::Generating->value];

        if (!in_array($draft->status, $generatable, true)) {
            if (in_array($draft->status, $inFlight, true)) {
                if ($this->isStaleInFlight($draft)) {
                    if (!$this->markFailedForStaleRetry($draft)) {
                        return;
                    }
                    $draft->refresh();
                } else {
                    return;
                }
            } else {
                return;
            }
        }

        $result = $service->generate($draft);

        Log::info('[GenerateThreadsPost] Generation complete', [
            'draft_id' => $draft->id,
            'format' => $draft->format,
            'success' => $result['success'] ?? false,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException(
                'Threads generation failed: ' . ($result['error'] ?? 'unknown')
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[GenerateThreadsPost] Job exhausted retries', [
            'draft_id' => $this->draftId,
            'error' => $e->getMessage(),
        ]);
    }

    private function isStaleInFlight(ThreadsPost $draft): bool
    {
        $updatedAt = $draft->updated_at;
        if ($updatedAt === null) {
            return false;
        }
        return $updatedAt->lessThan(now()->subMinutes(self::STALE_THRESHOLD_MINUTES));
    }

    private function markFailedForStaleRetry(ThreadsPost $draft): bool
    {
        try {
            app(\App\Services\PipelineGuard::class)->advance(
                $draft,
                ThreadsPostStatus::Failed,
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
            Log::error('[GenerateThreadsPost] Could not mark stale draft Failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
