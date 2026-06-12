<?php

namespace App\Console\Commands;

use App\Enums\PipelineErrorClass;
use App\Jobs\GenerateInstagramPost;
use App\Jobs\GenerateThreadsPost;
use App\Jobs\GenerateTiktokPost;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\PipelineErrorClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Caption parity for cross-post siblings (June 12, 2026 — Req 3).
 *
 * The blog→LinkedIn pipeline has two self-healing crons (`linkedin:reap-stuck`
 * + `linkedin:retry-failed`), but IG/TikTok/Threads caption generation had
 * NEITHER — so a sibling that never got picked up (queue worker down) or whose
 * caption job failed sat stuck forever. Live symptom: ~30 ThreadsPost rows
 * stuck in `pending_generation` for a month, never retried. (Note: a stuck
 * sibling ROW also blocks ScanLinkedInForCrossPost from re-creating it, since
 * that scan's idempotency is `exists()` — so the scan can't recover these.)
 *
 * This command re-dispatches three stall modes per sibling table:
 *
 *   A. status='pending_generation' (age > --pending-threshold, default 10m)
 *      — fan-out created the row but the Generate*Post job never ran (worker
 *      crash / restart). Re-dispatch; the job's `generatable` set includes
 *      pending_generation so it authors the caption.
 *
 *   B. status='generating' (age > --generating-threshold, default 15m)
 *      — job started but stalled (CLI hang, worker killed mid-run). Re-dispatch;
 *      the job's own isStaleInFlight() recovers it via FSM Failed, then the next
 *      tick generates.
 *
 *   C. status='failed' (bounded) — classified TRANSIENT / DETERMINISTIC_LLM
 *      only, auto_retry_count < MAX_RETRIES, past --failed-backoff. Bumps
 *      auto_retry_count so a permanently-broken caption can't re-queue forever.
 *      POLICY_* / PERMANENT / UNKNOWN failures are NOT retried (surface to the
 *      operator's per-platform "Regenerate caption" button).
 *
 * Skips siblings whose parent LinkedIn draft is `cancelled` (operator killed
 * the whole post). Re-dispatch targets the `social-crosspost` queue (the
 * dedicated worker pool), matching ScanLinkedInForCrossPost.
 *
 * Schedule: every 10 minutes via ScheduledCommandSeeder.
 */
class ReapStuckCrossPostCaptions extends Command
{
    protected $signature = 'crosspost:reap
        {--pending-threshold=10 : Minutes before a stuck pending_generation sibling is re-dispatched}
        {--generating-threshold=15 : Minutes before a stale generating sibling is re-dispatched}
        {--failed-backoff=5 : Minutes after a failure before the bounded retry fires}
        {--limit=50 : Max siblings re-dispatched per platform per tick}
        {--dry-run : Log candidates without re-dispatching}';

    protected $description = 'Re-dispatch stuck/failed cross-post caption siblings (IG/TikTok/Threads)';

    private const MAX_RETRIES = 2;

    /** @var array<string, array{0: class-string, 1: class-string}> */
    private const PLATFORMS = [
        'instagram' => [InstagramPost::class, GenerateInstagramPost::class],
        'tiktok' => [TiktokPost::class, GenerateTiktokPost::class],
        'threads' => [ThreadsPost::class, GenerateThreadsPost::class],
    ];

    public function __construct(private readonly PipelineErrorClassifier $classifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $pendingThreshold = max(2, (int) $this->option('pending-threshold'));
        $generatingThreshold = max(5, (int) $this->option('generating-threshold'));
        $failedBackoff = max(1, (int) $this->option('failed-backoff'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        $totalRedispatched = 0;

        foreach (self::PLATFORMS as $platformKey => [$model, $job]) {
            /** @var \Illuminate\Database\Eloquent\Collection $candidates */
            $candidates = $model::query()
                ->whereIn('status', ['pending_generation', 'generating', 'failed'])
                ->orderBy('updated_at', 'asc')
                ->limit($limit * 3) // over-fetch; many will be filtered out
                ->get();

            if ($candidates->isEmpty()) {
                continue;
            }

            // Resolve parents in one query — skip siblings whose LinkedIn draft
            // was cancelled (operator killed the whole post).
            $parentIds = $candidates->pluck('linkedin_post_id')->filter()->unique()->all();
            $parents = LinkedInPost::query()->whereIn('id', $parentIds)->get()->keyBy('id');

            $redispatched = 0;
            foreach ($candidates as $sibling) {
                if ($redispatched >= $limit) {
                    break;
                }

                $parent = $parents->get($sibling->linkedin_post_id);
                if ($parent === null || $parent->status === 'cancelled') {
                    continue;
                }

                $age = $sibling->updated_at
                    ? (int) round(abs($sibling->updated_at->diffInMinutes($now)))
                    : PHP_INT_MAX;
                $status = (string) $sibling->status;

                $isFailedRetry = false;
                $reason = null;

                if ($status === 'pending_generation') {
                    if ($age < $pendingThreshold) {
                        continue;
                    }
                    $reason = 'pending_stuck';
                } elseif ($status === 'generating') {
                    if ($age < $generatingThreshold) {
                        continue;
                    }
                    $reason = 'generating_stale';
                } else { // failed
                    if ((int) ($sibling->auto_retry_count ?? 0) >= self::MAX_RETRIES) {
                        continue;
                    }
                    if ($age < $failedBackoff) {
                        continue;
                    }
                    $class = $this->classifier->classify($sibling->last_error);
                    if (! in_array($class, [PipelineErrorClass::Transient, PipelineErrorClass::DeterministicLlm], true)) {
                        continue;
                    }
                    $isFailedRetry = true;
                    $reason = 'failed_' . $class->value;
                }

                $this->line(sprintf(
                    '  %s #%d  status=%s  age=%dm  reason=%s',
                    $platformKey,
                    $sibling->id,
                    $status,
                    $age,
                    $reason
                ));

                if ($dryRun) {
                    $redispatched++;
                    continue;
                }

                try {
                    // Only a genuine FAILURE consumes the retry budget. Stuck
                    // pending/generating are infra stalls (worker down) — don't
                    // count them or a flapping worker would exhaust the budget.
                    if ($isFailedRetry) {
                        $sibling->update([
                            'auto_retry_count' => (int) ($sibling->auto_retry_count ?? 0) + 1,
                            'last_classified_error_class' => $reason,
                        ]);
                    }

                    $job::dispatch($sibling->id)->onQueue('social-crosspost');
                    $redispatched++;

                    Log::info('[CrossPostReaper] re-dispatched stuck caption', [
                        'platform' => $platformKey,
                        'sibling_id' => $sibling->id,
                        'status' => $status,
                        'age_minutes' => $age,
                        'reason' => $reason,
                    ]);
                } catch (\Throwable $e) {
                    $this->error("    failed to re-dispatch {$platformKey} #{$sibling->id}: {$e->getMessage()}");
                    Log::error('[CrossPostReaper] re-dispatch failed', [
                        'platform' => $platformKey,
                        'sibling_id' => $sibling->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $totalRedispatched += $redispatched;
        }

        if ($totalRedispatched > 0) {
            $this->info(($dryRun ? 'Dry run — would re-dispatch ' : 'Re-dispatched ') . "{$totalRedispatched} sibling(s).");
        }

        return self::SUCCESS;
    }
}
