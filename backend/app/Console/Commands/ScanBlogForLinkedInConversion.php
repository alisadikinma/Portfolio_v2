<?php

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
use App\Models\Post;
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
        {--limit=20 : Max drafts to create per run (safety cap)}';

    protected $description = 'Scan recent blog posts and dispatch LinkedIn conversion jobs for those without a live draft';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $candidates = Post::query()
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subHours($hours))
            ->whereDoesntHave('linkedinPosts')
            ->with('translations')
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No un-converted posts in the last {$hours}h.");
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

            $this->line("  - #{$post->id}: {$title}");

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
