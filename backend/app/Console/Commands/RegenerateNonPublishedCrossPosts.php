<?php

namespace App\Console\Commands;

use App\Enums\InstagramPostStatus;
use App\Enums\LinkedInPostStatus;
use App\Enums\ThreadsPostStatus;
use App\Enums\TiktokPostStatus;
use App\Jobs\GenerateInstagramPost;
use App\Jobs\GenerateLinkedInPost;
use App\Jobs\GenerateThreadsPost;
use App\Jobs\GenerateTiktokPost;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\ArticleGenerationService;
use App\Services\LinkedInGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-regenerate non-published cross-post drafts to pick up post-ship config:
 *   - English-only LinkedIn directive (plugin v0.6.0)
 *   - Bahasa Indonesia IG/TikTok/Threads (plugin v0.3.0)
 *   - Branded shortener URLs in caption + link_comment field
 *
 * "Non-published" = status NOT IN (published / cancelled / failed). FSM-safe:
 *   resets to pending_generation via raw update, then re-dispatches the
 *   platform's Generate*Post queued job.
 *
 * For LinkedIn drafts whose source post has no EN translation yet, runs
 * article-translate sync preflight FIRST (blocks until EN row exists in
 * post_translations) — otherwise plugin fallback to ID source = legacy bug.
 *
 * Usage:
 *   php artisan cross-post:regen-non-published                       # all platforms
 *   php artisan cross-post:regen-non-published --platform=linkedin   # one platform
 *   php artisan cross-post:regen-non-published --limit=10 --dry-run  # safe preview
 *   php artisan cross-post:regen-non-published --skip-translate      # don't auto-translate
 */
class RegenerateNonPublishedCrossPosts extends Command
{
    protected $signature = 'cross-post:regen-non-published
                            {--platform=all : all|linkedin|instagram|tiktok|threads}
                            {--limit= : Cap drafts per platform (default unlimited)}
                            {--dry-run : Print plan, no DB writes / job dispatch}
                            {--skip-translate : Do not auto-trigger article-translate for missing EN}';

    protected $description = 'Regenerate non-published cross-post drafts to pick up latest plugin/config (English LinkedIn, Bahasa Indonesia IG/TikTok/Threads, shortener URLs)';

    public function handle(ArticleGenerationService $articleGen, LinkedInGenerationService $linkedInGen): int
    {
        $platform = strtolower((string) $this->option('platform'));
        $limit = $this->option('limit');
        $limit = $limit !== null ? (int) $limit : null;
        $dryRun = (bool) $this->option('dry-run');
        $skipTranslate = (bool) $this->option('skip-translate');

        $valid = ['all', 'linkedin', 'instagram', 'tiktok', 'threads'];
        if (!in_array($platform, $valid, true)) {
            $this->error("Invalid --platform={$platform}. Valid: " . implode(', ', $valid));
            return self::INVALID;
        }

        $this->info("Regen mode: {$platform}" . ($dryRun ? ' [DRY RUN]' : ''));
        $this->newLine();

        $totals = ['linkedin' => 0, 'instagram' => 0, 'tiktok' => 0, 'threads' => 0];
        $skipped = ['linkedin' => 0, 'instagram' => 0, 'tiktok' => 0, 'threads' => 0];

        if ($platform === 'all' || $platform === 'linkedin') {
            [$totals['linkedin'], $skipped['linkedin']] = $this->processLinkedIn(
                $limit, $dryRun, $skipTranslate, $articleGen, $linkedInGen
            );
        }
        if ($platform === 'all' || $platform === 'instagram') {
            $totals['instagram'] = $this->processInstagram($limit, $dryRun);
        }
        if ($platform === 'all' || $platform === 'tiktok') {
            $totals['tiktok'] = $this->processTikTok($limit, $dryRun);
        }
        if ($platform === 'all' || $platform === 'threads') {
            $totals['threads'] = $this->processThreads($limit, $dryRun);
        }

        $this->newLine();
        $this->info('=== Summary ===');
        foreach ($totals as $p => $count) {
            if ($count > 0 || ($skipped[$p] ?? 0) > 0) {
                $line = "  {$p}: queued {$count}";
                if (($skipped[$p] ?? 0) > 0) {
                    $line .= ", skipped {$skipped[$p]} (no EN translation, run article-translate manually)";
                }
                $this->line($line);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int} [queued, skipped]
     *
     * Format-aware routing:
     *  - format=text     → full pipeline (GenerateLinkedInPost) since the
     *                      post body IS the caption — needs /linkedin-gen
     *                      to re-author.
     *  - format=carousel → SYNC caption-only refresh (regenerateCaption)
     *                      that re-synth content + hashtags + link_comment
     *                      from existing slides via ShortLinkService.
     *                      Skips the expensive /carousel-gen + image render
     *                      (~5-7 min) because slide PNGs are already done.
     */
    private function processLinkedIn(
        ?int $limit,
        bool $dryRun,
        bool $skipTranslate,
        ArticleGenerationService $articleGen,
        LinkedInGenerationService $linkedInGen
    ): array {
        $nonTerminal = array_diff(
            array_map(fn($s) => $s->value, LinkedInPostStatus::cases()),
            [LinkedInPostStatus::Published->value, LinkedInPostStatus::Cancelled->value, LinkedInPostStatus::Failed->value]
        );

        $query = LinkedInPost::with('post.translations')
            ->whereNull('deleted_at')
            ->whereIn('status', $nonTerminal);
        if ($limit !== null) {
            $query->limit($limit);
        }
        $drafts = $query->get();

        $this->info("LinkedIn: found {$drafts->count()} non-published drafts");
        $queued = 0;
        $skipped = 0;

        foreach ($drafts as $draft) {
            $post = $draft->post;
            if ($post === null) {
                $this->warn("  ✗ Draft #{$draft->id}: post missing, skip");
                continue;
            }

            // Carousel = sync caption-only refresh, no full pipeline.
            // Skips expensive /carousel-gen + image render (~5-7 min) since
            // slide PNGs are already done — only caption + hashtags + link_comment
            // need to be re-synth from existing slides.
            if ($draft->format === 'carousel') {
                if ($dryRun) {
                    $this->line("  → Would re-synth caption for carousel draft #{$draft->id} (post '{$post->slug}', SYNC ~1s, no full regen)");
                    $queued++;
                    continue;
                }
                try {
                    $result = $linkedInGen->regenerateCaption($draft);
                    if ($result['success'] ?? false) {
                        $captionLen = mb_strlen((string) ($result['content'] ?? ''));
                        $this->line("  ✓ Carousel draft #{$draft->id} caption refreshed (sync, {$captionLen} chars, no slide re-render)");
                        $queued++;
                    } else {
                        $this->warn("  ✗ Carousel draft #{$draft->id} caption refresh failed: " . ($result['error'] ?? 'unknown'));
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $this->warn("  ✗ Carousel draft #{$draft->id} threw: {$e->getMessage()}");
                    $skipped++;
                }
                continue;
            }

            // Text format: full pipeline (post body IS the caption).
            $hasEnTranslation = $post->translations->contains('language', 'en');
            if (!$hasEnTranslation) {
                if ($skipTranslate) {
                    $this->warn("  ⊘ Draft #{$draft->id} (post #{$post->id} '{$post->slug}'): no EN translation, --skip-translate set");
                    $skipped++;
                    continue;
                }
                $this->line("  ⟳ Draft #{$draft->id}: triggering article-translate for post #{$post->id} (no EN translation)");
                if (!$dryRun) {
                    try {
                        $articleGen->triggerTranslate(
                            $post->id,
                            "regen-non-published-{$post->id}",
                            'en'
                        );
                    } catch (\Throwable $e) {
                        $this->warn("    article-translate failed: {$e->getMessage()}, skipping draft");
                        $skipped++;
                        continue;
                    }
                }
            }

            if ($dryRun) {
                $this->line("  → Would regen text LinkedIn draft #{$draft->id} (post '{$post->slug}', status={$draft->status}, FULL pipeline)");
                $queued++;
                continue;
            }

            DB::transaction(function () use ($draft) {
                $draft->update([
                    'status' => LinkedInPostStatus::PendingGeneration->value,
                    'content' => '',
                    'link_comment' => null,
                    'hashtags' => [],
                    'last_error' => null,
                ]);
                $log = is_array($draft->pipeline_state_log) ? $draft->pipeline_state_log : [];
                $log[] = [
                    'from' => $draft->getOriginal('status'),
                    'to' => LinkedInPostStatus::PendingGeneration->value,
                    'reason' => 'admin_bulk_regen_non_published',
                    'timestamp' => now()->toIso8601String(),
                ];
                $draft->update(['pipeline_state_log' => array_slice($log, -20)]);
            });
            GenerateLinkedInPost::dispatch($draft->id);
            $this->line("  ✓ Text LinkedIn draft #{$draft->id} reset + dispatched (full pipeline)");
            $queued++;
        }

        return [$queued, $skipped];
    }

    private function processInstagram(?int $limit, bool $dryRun): int
    {
        $nonTerminal = array_diff(
            array_map(fn($s) => $s->value, InstagramPostStatus::cases()),
            [InstagramPostStatus::Published->value, InstagramPostStatus::Cancelled->value, InstagramPostStatus::Failed->value]
        );

        $query = InstagramPost::with('post')->whereNull('deleted_at')->whereIn('status', $nonTerminal);
        if ($limit !== null) $query->limit($limit);
        $drafts = $query->get();

        $this->info("Instagram: found {$drafts->count()} non-published drafts");
        $queued = 0;
        foreach ($drafts as $draft) {
            if ($dryRun) {
                $this->line("  → Would regen IG draft #{$draft->id} (status={$draft->status})");
                $queued++;
                continue;
            }
            $draft->update([
                'status' => InstagramPostStatus::PendingGeneration->value,
                'caption' => null,
                'text_only_caption' => null,
                'link_comment' => null,
                'hashtags' => [],
                'last_error' => null,
            ]);
            GenerateInstagramPost::dispatch($draft->id);
            $this->line("  ✓ IG draft #{$draft->id} reset + dispatched");
            $queued++;
        }
        return $queued;
    }

    private function processTikTok(?int $limit, bool $dryRun): int
    {
        $nonTerminal = array_diff(
            array_map(fn($s) => $s->value, TiktokPostStatus::cases()),
            [TiktokPostStatus::Published->value, TiktokPostStatus::Cancelled->value, TiktokPostStatus::Failed->value]
        );

        $query = TiktokPost::with('post')->whereNull('deleted_at')->whereIn('status', $nonTerminal);
        if ($limit !== null) $query->limit($limit);
        $drafts = $query->get();

        $this->info("TikTok: found {$drafts->count()} non-published drafts");
        $queued = 0;
        foreach ($drafts as $draft) {
            if ($dryRun) {
                $this->line("  → Would regen TikTok draft #{$draft->id} (status={$draft->status})");
                $queued++;
                continue;
            }
            $draft->update([
                'status' => TiktokPostStatus::PendingGeneration->value,
                'caption' => null,
                'link_comment' => null,
                'hashtags' => [],
                'last_error' => null,
            ]);
            GenerateTiktokPost::dispatch($draft->id);
            $this->line("  ✓ TikTok draft #{$draft->id} reset + dispatched");
            $queued++;
        }
        return $queued;
    }

    private function processThreads(?int $limit, bool $dryRun): int
    {
        $nonTerminal = array_diff(
            array_map(fn($s) => $s->value, ThreadsPostStatus::cases()),
            [ThreadsPostStatus::Published->value, ThreadsPostStatus::Cancelled->value, ThreadsPostStatus::Failed->value]
        );

        $query = ThreadsPost::with('post')->whereNull('deleted_at')->whereIn('status', $nonTerminal);
        if ($limit !== null) $query->limit($limit);
        $drafts = $query->get();

        $this->info("Threads: found {$drafts->count()} non-published drafts");
        $queued = 0;
        foreach ($drafts as $draft) {
            if ($dryRun) {
                $this->line("  → Would regen Threads draft #{$draft->id} (status={$draft->status})");
                $queued++;
                continue;
            }
            $draft->update([
                'status' => ThreadsPostStatus::PendingGeneration->value,
                'caption' => null,
                'link_comment' => null,
                'hashtags' => [],
                'last_error' => null,
            ]);
            GenerateThreadsPost::dispatch($draft->id);
            $this->line("  ✓ Threads draft #{$draft->id} reset + dispatched");
            $queued++;
        }
        return $queued;
    }
}
