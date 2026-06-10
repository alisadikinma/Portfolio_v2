<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily 03:00 WIB scan. Finds blog posts published in the last 24 hours
 * with no live LinkedInPost row, creates a pending draft per post, and
 * dispatches GenerateLinkedInPost to run the plugin content pipeline.
 *
 * Respects the "one live draft per post" invariant — `Post::whereDoesntHave('linkedinPosts')`
 * uses the default query (which excludes soft-deleted rows, matching our
 * LinkedInPost uses SoftDeletes). Regenerate flows already soft-delete the
 * old row before creating a new one.
 */
class ScanBlogForLinkedInConversion extends Command
{
    protected $signature = 'linkedin:scan-blog
        {--hours=24 : Lookback window in hours}
        {--dry-run : Log candidates without creating drafts}
        {--limit=20 : Max drafts to create per run (safety cap)}
        {--min-virality= : Override linkedin_virality_min_score setting (0 disables the gate)}
        {--post-id= : Target a single post by id — bypasses the lookback window, keeps the virality + one-live-draft idempotency gates}';

    protected $description = 'Scan recent blog posts and dispatch LinkedIn conversion jobs for those without a live draft';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $postId = $this->option('post-id');

        // Virality gate (April 29, 2026): only ingest blog posts whose
        // backing ContentIdea has a virality_score >= threshold. Posts
        // without a linked ContentIdea (manual blog uploads) are skipped
        // — operators can hand-create a LinkedInPost row if they want one
        // for those. CLI flag overrides the setting for ad-hoc backfills.
        $minVirality = $this->option('min-virality') !== null
            ? max(0, (int) $this->option('min-virality'))
            : (int) Setting::get('linkedin_virality_min_score', 60);

        $query = Post::query()
            ->where('published', true)
            ->whereNotNull('published_at')
            ->whereDoesntHave('linkedinPosts');

        // Targeted mode (--post-id): bypass the lookback window and aim at a
        // single post. All other gates (published, published_at present,
        // one-live-draft, virality) still apply.
        if ($postId !== null) {
            $query->where('id', (int) $postId);
            $this->info("[LinkedInScan] targeted post #{$postId}");
        } else {
            $query->where('published_at', '>=', now()->subHours($hours));
        }

        if ($minVirality > 0) {
            $query->whereHas('contentIdea', function ($q) use ($minVirality) {
                $q->where('virality_score', '>=', $minVirality);
            });
            $this->info("Virality gate active: requiring contentIdea.virality_score >= {$minVirality}");
        } else {
            $this->info('Virality gate disabled (min-virality=0).');
        }

        $candidates = $query
            ->with(['translations', 'contentIdea:id,result_post_id,virality_score'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No un-converted posts in the last {$hours}h passing the virality gate.");
            return 0;
        }

        $this->info("Found {$candidates->count()} un-converted posts.");

        $created = 0;
        foreach ($candidates as $post) {
            $title = optional(
                $post->translations->firstWhere('language', 'id')
                    ?? $post->translations->firstWhere('language', 'en')
                    ?? $post->translations->first()
            )->title ?? "(post #{$post->id})";

            $virality = $post->contentIdea?->virality_score;
            $viralityChip = $virality !== null ? " v={$virality}" : '';
            $this->line("  - #{$post->id}{$viralityChip}: {$title}");

            if ($dryRun) {
                continue;
            }

            try {
                $draft = LinkedInPost::create([
                    'post_id' => $post->id,
                    'format' => 'text', // plugin brief may upgrade to carousel; default text
                    'content' => '',
                    'hashtags' => [],
                    'status' => LinkedInPostStatus::PendingGeneration->value,
                    'pipeline_state_log' => [[
                        'from' => 'scan',
                        'to' => 'pending_generation',
                        'reason' => 'cron_scan_detected_new_post',
                        'timestamp' => now()->toIso8601String(),
                    ]],
                ]);

                GenerateLinkedInPost::dispatch($draft->id);
                $created++;

                Log::info('[LinkedInScan] Draft created + dispatched', [
                    'post_id' => $post->id,
                    'draft_id' => $draft->id,
                ]);
            } catch (\Throwable $e) {
                $this->error("    failed to create draft: {$e->getMessage()}");
                Log::error('[LinkedInScan] Failed to create draft', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($dryRun ? "Dry run — would have created {$candidates->count()} drafts." : "Created {$created} draft(s).");
        return 0;
    }
}
