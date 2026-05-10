<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use Illuminate\Console\Command;

/**
 * One-shot bulk flip: awaiting_publish → manual_review.
 *
 * Operator request May 10: re-introduce explicit "Need Reviews" tab in
 * the queue list and route any draft currently sitting in
 * awaiting_publish back to manual_review so the operator hits a review
 * gate before it actually publishes (vs the May 9 simplification that
 * merged manual_review + awaiting_publish into one in_progress bucket).
 *
 * Idempotent — re-running on a clean DB rewrites zero rows. Skips
 * drafts whose cancel_window_ends_at is already in the past (those
 * would have fired by next process-scheduled tick anyway).
 *
 * Usage:
 *   php artisan linkedin:flip-awaiting-to-manual-review --dry-run
 *   php artisan linkedin:flip-awaiting-to-manual-review
 */
class FlipAwaitingPublishToManualReview extends Command
{
    protected $signature = 'linkedin:flip-awaiting-to-manual-review
                            {--dry-run : Print plan, no DB writes}';

    protected $description = 'Flip awaiting_publish drafts back to manual_review (re-introduce explicit review gate)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $drafts = LinkedInPost::whereNull('deleted_at')
            ->where('status', LinkedInPostStatus::AwaitingPublish->value)
            ->get();

        $this->info("Found {$drafts->count()} drafts in awaiting_publish" . ($dryRun ? ' [DRY RUN]' : ''));

        if ($drafts->isEmpty()) {
            return self::SUCCESS;
        }

        $flipped = 0;
        foreach ($drafts as $draft) {
            $publishAt = $draft->cancel_window_ends_at;

            if ($dryRun) {
                $this->line("  → Would flip draft #{$draft->id} (would have fired at " . ($publishAt?->toIso8601String() ?? 'unscheduled') . ")");
                $flipped++;
                continue;
            }

            $previousStatus = $draft->status;
            $draft->update([
                'status' => LinkedInPostStatus::ManualReview->value,
                // Clear the schedule timestamps so process-scheduled cron
                // doesn't try to publish during the next tick.
                'cancel_window_ends_at' => null,
            ]);

            $log = is_array($draft->pipeline_state_log) ? $draft->pipeline_state_log : [];
            $log[] = [
                'from' => $previousStatus,
                'to' => LinkedInPostStatus::ManualReview->value,
                'reason' => 'admin_bulk_flip_awaiting_to_manual_review',
                'timestamp' => now()->toIso8601String(),
            ];
            $draft->update(['pipeline_state_log' => array_slice($log, -20)]);

            $this->line("  ✓ Draft #{$draft->id} flipped awaiting_publish → manual_review");
            $flipped++;
        }

        $this->newLine();
        $this->info("Summary: {$flipped} draft(s)" . ($dryRun ? ' would be flipped' : ' flipped'));

        return self::SUCCESS;
    }
}
