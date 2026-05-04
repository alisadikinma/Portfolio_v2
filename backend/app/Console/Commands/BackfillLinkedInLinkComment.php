<?php

namespace App\Console\Commands;

use App\Models\LinkedInPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-shot backfill for legacy LinkedInPost rows whose `link_comment` is a
 * non-URL string (typically a stale pull_quote / insight sentence emitted
 * by the plugin before resolveLinkComment shipped in commit c64e9c31 on
 * Apr 29, 2026). Affects drafts created before that date that were also
 * published before the new defense-in-depth guard in
 * LinkedInPublishService::publish landed.
 *
 * What this fixes: published comments on LinkedIn that should have been
 * "Full article: https://alisadikinma.com/blog/{slug}" but were posted as
 * a random insight sentence — defeating the link-in-comment automation.
 * This command does NOT re-post the comment (LinkedIn comment-edit isn't
 * wired). It only repairs the DB so future republish/regenerate use the
 * canonical URL.
 *
 *   php artisan linkedin:backfill-link-comment              # apply
 *   php artisan linkedin:backfill-link-comment --dry-run    # preview only
 */
class BackfillLinkedInLinkComment extends Command
{
    protected $signature = 'linkedin:backfill-link-comment
        {--dry-run : List candidates without rewriting}';

    protected $description = 'Rewrite legacy non-URL LinkedInPost.link_comment values to "Full article: {blogUrl}"';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = LinkedInPost::query()
            ->withTrashed()
            ->whereNotNull('link_comment')
            ->where('link_comment', '!=', '')
            ->where('link_comment', 'not like', '%http%')
            ->with('post:id,slug')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No legacy non-URL link_comment rows found.');
            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} draft(s) with non-URL link_comment.");

        $rewritten = 0;
        $skipped = 0;
        foreach ($candidates as $draft) {
            $slug = (string) ($draft->post?->slug ?? '');
            if ($slug === '') {
                $this->line("  - #{$draft->id} SKIPPED (no post.slug, post_id={$draft->post_id})");
                $skipped++;
                continue;
            }

            $oldPreview = mb_substr((string) $draft->link_comment, 0, 80);
            $this->line("  - #{$draft->id} status={$draft->status} :: \"{$oldPreview}\" → blog/{$slug}");

            if ($dryRun) {
                continue;
            }

            try {
                $changed = $draft->ensureLinkCommentHasUrl();
                if ($changed) {
                    $rewritten++;
                    Log::info('[LinkedInBackfill] link_comment rewritten', [
                        'draft_id' => $draft->id,
                        'post_id' => $draft->post_id,
                    ]);
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $this->error("    failed to rewrite #{$draft->id}: {$e->getMessage()}");
                Log::error('[LinkedInBackfill] rewrite failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->info($dryRun
            ? "Dry run — would have rewritten {$candidates->count()} draft(s)."
            : "Rewrote {$rewritten} draft(s), skipped {$skipped}."
        );

        return self::SUCCESS;
    }
}
