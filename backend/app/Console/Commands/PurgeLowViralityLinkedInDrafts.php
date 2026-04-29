<?php

namespace App\Console\Commands;

use App\Models\LinkedInPost;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Soft-deletes LinkedInPost drafts whose backing ContentIdea has a
 * virality_score below the operator-configured `linkedin_virality_purge_below`
 * threshold (default 50).
 *
 * Why this exists: the scan command only filters at INGEST time. If a blog
 * post had virality_score=70 at scan time but later got re-scored down to 35
 * (e.g., social signals decayed, or the BackfillViralityScores job ran with
 * fresh weights), the existing draft would still sit in the queue forever.
 * This command catches those drift cases.
 *
 * Safety rails:
 *   - Only touches non-terminal states (skips published / cancelled / awaiting_publish).
 *     Published posts already shipped — purging them retroactively makes no sense.
 *     Awaiting_publish has been operator-approved and is in cancel-window — let it ship.
 *   - Soft-delete only (LinkedInPost uses SoftDeletes). `php artisan db:seed`
 *     and the regenerate flow remain compatible.
 *   - Skips drafts without a linked ContentIdea (manual rows).
 *   - Idempotent: re-running on the same drafts does nothing (already trashed).
 *
 * Schedule: daily 04:00 Asia/Jakarta — runs after scan-blog (03:00) so any
 * fresh ingest from the same morning gets evaluated against current scores.
 *
 * Manual ops:
 *   php artisan linkedin:purge-low-virality                    # use settings threshold
 *   php artisan linkedin:purge-low-virality --threshold=40     # override
 *   php artisan linkedin:purge-low-virality --dry-run          # log only, no writes
 */
class PurgeLowViralityLinkedInDrafts extends Command
{
    protected $signature = 'linkedin:purge-low-virality
        {--threshold= : Purge drafts below this virality_score (overrides linkedin_virality_purge_below setting)}
        {--dry-run : Log candidates without soft-deleting}';

    protected $description = 'Soft-delete LinkedIn drafts whose source idea has decayed below the virality purge threshold';

    public function handle(): int
    {
        $threshold = $this->option('threshold') !== null
            ? max(0, (int) $this->option('threshold'))
            : (int) Setting::get('linkedin_virality_purge_below', 50);

        $dryRun = (bool) $this->option('dry-run');

        if ($threshold <= 0) {
            $this->info('Purge threshold is 0 — gate disabled, nothing to do.');
            return self::SUCCESS;
        }

        // Status whitelist: only purge drafts that are still actionable.
        // Published + Cancelled = terminal good/bad, leave the audit trail.
        // AwaitingPublish = approved + in cancel window, let it run its course.
        $purgable = ['pending_generation', 'generating', 'validating', 'manual_review', 'failed'];

        $candidates = LinkedInPost::query()
            ->whereIn('status', $purgable)
            ->whereHas('post.contentIdea', function ($q) use ($threshold) {
                $q->where('virality_score', '<', $threshold);
            })
            ->with(['post.contentIdea:id,result_post_id,virality_score', 'post.translations'])
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No drafts below virality threshold {$threshold}.");
            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} draft(s) below virality threshold {$threshold}.");

        $purged = 0;
        foreach ($candidates as $draft) {
            $idea = $draft->post?->contentIdea;
            $virality = $idea?->virality_score ?? '?';
            $title = optional(
                $draft->post?->translations->firstWhere('language', 'id')
                    ?? $draft->post?->translations->firstWhere('language', 'en')
                    ?? $draft->post?->translations->first()
            )->title ?? "(post #{$draft->post_id})";

            $this->line("  - #{$draft->id} status={$draft->status} v={$virality} :: {$title}");

            if ($dryRun) {
                continue;
            }

            try {
                // Stamp last_error so admin UI surfaces a clear reason if the
                // operator filters trashed/restores the draft later. Soft-delete
                // does not transition FSM (PipelineGuard would reject most of
                // the source statuses anyway).
                $draft->update([
                    'last_error' => "Auto-purged: source idea virality_score={$virality} < threshold={$threshold}",
                ]);
                $draft->delete();
                $purged++;

                Log::info('[LinkedInPurge] draft purged', [
                    'draft_id' => $draft->id,
                    'post_id' => $draft->post_id,
                    'virality_score' => $virality,
                    'threshold' => $threshold,
                    'previous_status' => $draft->status,
                ]);
            } catch (\Throwable $e) {
                $this->error("    failed to purge #{$draft->id}: {$e->getMessage()}");
                Log::error('[LinkedInPurge] could not purge draft', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($dryRun
            ? "Dry run — would have purged {$candidates->count()}."
            : "Purged {$purged} draft(s)."
        );

        return self::SUCCESS;
    }
}
