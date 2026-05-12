<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LinkedInPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill: mirror LinkedIn `scheduled_at` onto cross-post sibling
 * rows for drafts that pre-date the P5 propagation logic.
 *
 * Idempotent — running multiple times yields zero new writes once siblings
 * are in sync.
 *
 *   php artisan linkedin:sync-sibling-slots [--dry-run]
 */
class SyncSiblingSlots extends Command
{
    protected $signature = 'linkedin:sync-sibling-slots
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Backfill scheduled_at from LinkedIn drafts onto their cross-post siblings (one-shot)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $drafts = LinkedInPost::query()
            ->whereNotNull('scheduled_at')
            ->whereNull('deleted_at')
            ->with(['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'])
            ->get();

        $synced = 0;
        $skipped = 0;

        foreach ($drafts as $draft) {
            foreach (['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'] as $rel) {
                $sibling = $draft->$rel;
                if ($sibling === null) {
                    continue;
                }
                if ($sibling->scheduled_at?->equalTo($draft->scheduled_at)) {
                    $skipped++;
                    continue;
                }
                $this->line(sprintf(
                    '  draft #%d → %s sibling #%d: %s → %s',
                    $draft->id,
                    $rel,
                    $sibling->id,
                    $sibling->scheduled_at?->toIso8601String() ?? 'null',
                    $draft->scheduled_at->toIso8601String()
                ));
                if (! $dryRun) {
                    $sibling->update(['scheduled_at' => $draft->scheduled_at]);
                }
                $synced++;
            }
        }

        $verb = $dryRun ? 'would sync' : 'synced';
        $this->info(sprintf(
            '[linkedin:sync-sibling-slots] %s %d siblings, skipped %d already-aligned',
            $verb,
            $synced,
            $skipped
        ));

        return self::SUCCESS;
    }
}
