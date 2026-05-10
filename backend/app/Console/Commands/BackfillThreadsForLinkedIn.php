<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Enums\ThreadsPostStatus;
use App\Jobs\GenerateThreadsPost;
use App\Models\LinkedInPost;
use App\Models\ThreadsPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retroactive Threads sibling creation for existing LinkedIn drafts.
 *
 * `/threads-gen` plugin shipped May 10, 2026. LinkedIn drafts created BEFORE
 * that ship date never received a Threads cross-post sibling because
 * ScanLinkedInForCrossPost::createThreads() didn't exist yet. This command
 * walks LinkedIn drafts (any non-terminal status, including published — Threads
 * can post even after LinkedIn already shipped, drives independent reach),
 * finds those without a ThreadsPost row, and creates + dispatches.
 *
 * Idempotent — re-running on a clean state is no-op (skips drafts that already
 * have a ThreadsPost sibling).
 *
 * Usage:
 *   php artisan linkedin:backfill-threads                   # process all
 *   php artisan linkedin:backfill-threads --limit=20        # cap
 *   php artisan linkedin:backfill-threads --dry-run         # preview only
 *   php artisan linkedin:backfill-threads --include-published    # include published LinkedIn (default: skip)
 */
class BackfillThreadsForLinkedIn extends Command
{
    protected $signature = 'linkedin:backfill-threads
                            {--limit= : Cap number of LinkedIn drafts processed}
                            {--dry-run : Print plan, no DB writes / job dispatch}
                            {--include-published : Also fan out from already-published LinkedIn drafts (default skips)}';

    protected $description = 'Create Threads cross-post siblings for existing LinkedIn drafts that pre-date /threads-gen plugin';

    public function handle(): int
    {
        $limit = $this->option('limit');
        $limit = $limit !== null ? (int) $limit : null;
        $dryRun = (bool) $this->option('dry-run');
        $includePublished = (bool) $this->option('include-published');

        $eligibleStatuses = $includePublished
            ? null  // any status
            : array_diff(
                array_map(fn($s) => $s->value, LinkedInPostStatus::cases()),
                [LinkedInPostStatus::Cancelled->value, LinkedInPostStatus::Failed->value]
            );

        $query = LinkedInPost::with('threadsPost')->whereNull('deleted_at');
        if ($eligibleStatuses !== null) {
            $query->whereIn('status', $eligibleStatuses);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }
        $drafts = $query->get()->filter(fn($d) => $d->threadsPost === null);

        $this->info("Found {$drafts->count()} LinkedIn drafts without Threads sibling" .
            ($includePublished ? ' (including published)' : ' (excluding cancelled/failed)') .
            ($dryRun ? ' [DRY RUN]' : '')
        );

        $created = 0;
        foreach ($drafts as $li) {
            $format = $li->format ?? 'text';

            if ($dryRun) {
                $this->line("  → Would create Threads sibling for LinkedIn #{$li->id} (format={$format}, post #{$li->post_id})");
                $created++;
                continue;
            }

            $threadsDraft = ThreadsPost::create([
                'linkedin_post_id' => $li->id,
                'post_id' => $li->post_id,
                'format' => $format,
                'status' => ThreadsPostStatus::PendingGeneration->value,
                'pipeline_state_log' => [[
                    'from' => 'backfill',
                    'to' => 'pending_generation',
                    'reason' => 'admin_backfill_threads',
                    'timestamp' => now()->toIso8601String(),
                ]],
            ]);

            GenerateThreadsPost::dispatch($threadsDraft->id);

            Log::info('[BackfillThreads] Threads sibling created + dispatched', [
                'linkedin_post_id' => $li->id,
                'threads_post_id' => $threadsDraft->id,
                'format' => $format,
            ]);

            $this->line("  ✓ LinkedIn #{$li->id} → Threads #{$threadsDraft->id} ({$format}) dispatched");
            $created++;
        }

        $this->newLine();
        $this->info("Summary: {$created} Threads sibling(s) " . ($dryRun ? 'would be' : '') . ' created');
        return self::SUCCESS;
    }
}
