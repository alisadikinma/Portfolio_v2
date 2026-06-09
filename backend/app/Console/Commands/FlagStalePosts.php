<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Weekly freshness-loop flagger (GEO publish-and-forget fix — Neil Patel #1).
 *
 * Finds published posts whose freshness anchor (COALESCE(content_reviewed_at,
 * published_at)) is older than --days, stamps stale_notified_at, and (Phase C)
 * sends ONE Telegram digest. Never regenerates content — the operator decides
 * whether to refresh or mark reviewed. Manual refresh stays operator-initiated.
 *
 * Re-alert suppression: a post already notified within the last 30 days is
 * skipped so an evergreen post isn't re-flagged every Monday.
 *
 *   php artisan content:flag-stale-posts                 # default 90 days
 *   php artisan content:flag-stale-posts --days=120      # custom threshold
 *   php artisan content:flag-stale-posts --dry-run       # list only, no writes
 *   php artisan content:flag-stale-posts --limit=20      # cap per run
 */
class FlagStalePosts extends Command
{
    protected $signature = 'content:flag-stale-posts
        {--days=90 : Freshness anchor age threshold in days}
        {--dry-run : List candidates without flagging}
        {--limit=50 : Max posts to flag per run}';

    protected $description = 'Flag published posts older than N days for review (freshness loop) + Telegram digest';

    public function handle(TelegramNotificationService $telegram): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        // Re-alert suppression: never-notified OR last notified >30 days ago.
        $cutoff = now()->subDays(30);

        $stale = Post::stale($days)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('stale_notified_at')
                    ->orWhere('stale_notified_at', '<', $cutoff);
            })
            ->with('translations')
            ->orderBy('published_at')
            ->limit($limit)
            ->get();

        if ($stale->isEmpty()) {
            $this->info("No stale posts older than {$days} days awaiting notification.");
            return self::SUCCESS;
        }

        $this->info("Found {$stale->count()} stale post(s) older than {$days} days.");

        $digest = [];
        foreach ($stale as $post) {
            $title = $this->titleFor($post);
            $anchor = $post->content_reviewed_at ?? $post->published_at;
            $age = $anchor ? (int) abs($anchor->diffInDays(now())) : 0;
            $this->line("  - #{$post->id} {$age}d :: {$title}");
            $digest[] = ['id' => $post->id, 'title' => $title, 'days' => $age, 'slug' => $post->slug];
        }

        if ($dryRun) {
            $this->info("Dry run — would flag {$stale->count()} post(s). No writes.");
            return self::SUCCESS;
        }

        // Surgical column update: bypasses model events + does NOT bump updated_at
        // (the freshness anchor is published_at/content_reviewed_at, never
        // stale_notified_at) so flagging can't accidentally mark a post fresh.
        DB::table('posts')
            ->whereIn('id', $stale->pluck('id')->all())
            ->update(['stale_notified_at' => now()]);

        $this->info("Flagged {$stale->count()} post(s) for review.");

        // ONE digest for the whole batch (no per-post spam). No-op when the
        // master toggle or telegram_notify_stale_content is off.
        $telegram->sendStaleContentDigest($digest);

        return self::SUCCESS;
    }

    private function titleFor(Post $post): string
    {
        $t = $post->translations->firstWhere('language', 'en')
            ?? $post->translations->firstWhere('language', 'id')
            ?? $post->translations->first();

        return $t?->title ?? "(post #{$post->id})";
    }
}
