<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Enums\PipelineErrorClass;
use App\Jobs\DispatchPipelineTelegramEvent;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
use App\Services\PipelineErrorClassifier;
use App\Services\PipelineGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase C of docs/plans/2026-05-07-pipeline-error-classifier-and-bounded-retry.md
 *
 * Bounded auto-retry for `linkedin_posts` rows in FSM=Failed state. The
 * existing reap-stuck cron only marks rows as Failed (recovery from a stuck
 * generating/validating state); this cron decides whether to attempt a
 * retry based on the classified error class.
 *
 * Policy:
 *   - TRANSIENT  → retry up to 2x (5min backoff for #1, 30min backoff for #2)
 *   - DETERMINISTIC_LLM → retry 1x (30min backoff — give LLM service time)
 *   - POLICY_*   → no retry (operator decision; auto would refuse same way)
 *   - PERMANENT  → no retry (won't succeed without prompt change)
 *   - UNKNOWN    → no retry (surface to operator)
 *
 * Cap: max 2 auto-retries per record. After exhaustion, fires a one-shot
 * `linkedin_auto_retry_exhausted` Telegram notification (idempotent via
 * pipeline_state_log inspection — caller checks the most recent transition
 * reason to avoid spamming).
 */
class RetryFailedLinkedInPosts extends Command
{
    protected $signature = 'linkedin:retry-failed
        {--dry-run : Log decisions without mutating}
        {--limit=20 : Max rows considered per tick}';

    protected $description = 'Bounded auto-retry for failed LinkedIn drafts based on error class';

    private const MAX_RETRIES = 2;

    public function __construct(
        private readonly PipelineErrorClassifier $classifier,
        private readonly PipelineGuard $guard,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $candidates = LinkedInPost::query()
            ->where('status', LinkedInPostStatus::Failed->value)
            ->where('auto_retry_count', '<', self::MAX_RETRIES)
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        $exhaustionCandidates = LinkedInPost::query()
            ->where('status', LinkedInPostStatus::Failed->value)
            ->where('auto_retry_count', '>=', self::MAX_RETRIES)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty() && $exhaustionCandidates->isEmpty()) {
            return self::SUCCESS;
        }

        $retried = 0;
        $skipped = 0;
        foreach ($candidates as $draft) {
            $action = $this->decideAction($draft, $this->classifier->classify($draft->last_error));
            if ($action === null) {
                $skipped++;
                continue;
            }

            $this->line("  #{$draft->id} class={$action['class']->value} retry=" . ($draft->auto_retry_count + 1) . "/" . self::MAX_RETRIES);

            if ($dryRun) {
                continue;
            }

            try {
                $this->guard->advance(
                    $draft,
                    LinkedInPostStatus::PendingGeneration,
                    'auto_retry_class_' . $action['class']->value,
                    [
                        'auto_retry_count' => $draft->auto_retry_count + 1,
                        'last_classified_error_class' => $action['class']->value,
                    ]
                );
                GenerateLinkedInPost::dispatch($draft->id);
                $retried++;
            } catch (\Throwable $e) {
                Log::error('[LinkedInRetry] Could not advance draft', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Exhaustion notification — fired ONCE per draft via idempotency check
        // against the most recent pipeline_state_log entry's reason field.
        foreach ($exhaustionCandidates as $draft) {
            if ($this->alreadyNotifiedExhaustion($draft)) {
                continue;
            }

            if ($dryRun) {
                $this->line("  #{$draft->id} would notify exhaustion");
                continue;
            }

            try {
                DispatchPipelineTelegramEvent::dispatch('linkedin_auto_retry_exhausted', [
                    'draft_id' => $draft->id,
                    'last_error' => $draft->last_error,
                    'auto_retry_count' => $draft->auto_retry_count,
                ]);

                // Stamp the log so we don't notify again. Direct append (not
                // PipelineGuard) because no FSM transition occurs here — we're
                // just recording the notification dispatch.
                $log = $draft->pipeline_state_log ?? [];
                $log[] = [
                    'from' => $draft->status,
                    'to' => $draft->status,
                    'reason' => 'auto_retry_exhausted_notified',
                    'timestamp' => now()->toIso8601String(),
                ];
                $draft->update(['pipeline_state_log' => array_slice($log, -20)]);
            } catch (\Throwable $e) {
                Log::error('[LinkedInRetry] Exhaustion notify dispatch failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Retried={$retried} skipped={$skipped} considered={$candidates->count()}");
        return self::SUCCESS;
    }

    /**
     * Decide whether to retry a failed draft. Returns null when no retry
     * (POLICY_*, PERMANENT, UNKNOWN, or backoff window not yet elapsed).
     *
     * @return array{class: PipelineErrorClass, backoff_ok: bool}|null
     */
    private function decideAction(LinkedInPost $draft, PipelineErrorClass $class): ?array
    {
        // Only TRANSIENT and DETERMINISTIC_LLM classes are auto-retried.
        if (!in_array($class, [PipelineErrorClass::Transient, PipelineErrorClass::DeterministicLlm], true)) {
            return null;
        }

        $retryNumber = $draft->auto_retry_count + 1;

        // DETERMINISTIC_LLM only gets one retry — same prompt twice = same
        // truncation/parse failure. One retry covers transient LLM downtime.
        if ($class === PipelineErrorClass::DeterministicLlm && $retryNumber > 1) {
            return null;
        }

        $backoffMin = match (true) {
            $class === PipelineErrorClass::Transient && $retryNumber === 1 => 5,
            $class === PipelineErrorClass::Transient && $retryNumber === 2 => 30,
            $class === PipelineErrorClass::DeterministicLlm => 30,
            default => 60,
        };

        $eligibleAt = $draft->updated_at?->copy()->addMinutes($backoffMin);
        if ($eligibleAt && $eligibleAt->isFuture()) {
            return null;
        }

        return ['class' => $class, 'backoff_ok' => true];
    }

    private function alreadyNotifiedExhaustion(LinkedInPost $draft): bool
    {
        $log = $draft->pipeline_state_log ?? [];
        if (empty($log)) {
            return false;
        }
        foreach (array_reverse($log) as $entry) {
            if (($entry['reason'] ?? null) === 'auto_retry_exhausted_notified') {
                return true;
            }
        }
        return false;
    }
}
