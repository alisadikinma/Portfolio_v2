<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigest;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWeeklyNewsletter extends Command
{
    protected $signature = 'newsletter:send-weekly
        {--dry-run : Render preview HTML without sending}
        {--force : Send even when 0 posts in last 7 days}
        {--limit= : Override default 5 posts max}
        {--triggered-by=cron : Source label for audit row (cron|manual|test)}
        {--user-id= : Admin user id when triggered manually}';

    protected $description = 'Send weekly digest to all newsletter subscribers (skips when no new posts)';

    public function handle(): int
    {
        $startedAt = now();
        $triggeredBy = in_array($this->option('triggered-by'), ['cron', 'manual', 'test'], true)
            ? $this->option('triggered-by')
            : 'cron';

        $limit = (int) ($this->option('limit') ?? 5);
        if ($limit < 1) {
            $limit = 5;
        }

        $posts = Post::published()
            ->whereBetween('published_at', [now()->subWeek(), now()])
            ->with(['category', 'translations'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        if ($posts->isEmpty() && !$this->option('force')) {
            NewsletterSend::create([
                'sent_at' => $startedAt,
                'subscriber_count' => 0,
                'posts_count' => 0,
                'post_ids' => [],
                'status' => 'skipped',
                'triggered_by' => $triggeredBy,
                'created_by_user_id' => $this->option('user-id'),
                'duration_seconds' => 0,
            ]);

            $this->info('Skipped: no posts published in the last 7 days. Use --force to send anyway.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            // Don't depend on factory — production composer install --no-dev
            // sometimes drops dev autoload paths. Hand-build a throwaway model.
            $fakeSub = new Newsletter([
                'name' => 'Preview Recipient',
                'email' => 'preview@example.com',
                'whatsapp_number' => '+628000000000',
            ]);
            $fakeSub->unsubscribe_token = str_repeat('x', 32);

            $rendered = (new WeeklyDigest($posts, $fakeSub))->render();
            $this->info('--- DRY RUN: rendered HTML preview below ---');
            $this->line($rendered);
            $this->info('--- END DRY RUN (no sends, no DB writes) ---');
            return self::SUCCESS;
        }

        $count = 0;
        try {
            Newsletter::query()
                ->whereNotNull('email')
                ->chunkById(100, function ($subs) use ($posts, &$count) {
                    foreach ($subs as $sub) {
                        Mail::to($sub->email)->queue(new WeeklyDigest($posts, $sub));
                        $count++;
                    }
                });
        } catch (Throwable $e) {
            $duration = $startedAt->diffInSeconds(now());
            NewsletterSend::create([
                'sent_at' => $startedAt,
                'subscriber_count' => $count,
                'posts_count' => $posts->count(),
                'post_ids' => $posts->pluck('id')->toArray(),
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 65000),
                'triggered_by' => $triggeredBy,
                'created_by_user_id' => $this->option('user-id'),
                'duration_seconds' => $duration,
            ]);

            Log::error('newsletter:send-weekly failed', [
                'error' => $e->getMessage(),
                'subscribers_queued' => $count,
            ]);

            $this->error("Send failed after queuing $count subscribers: " . $e->getMessage());
            return self::FAILURE;
        }

        $duration = $startedAt->diffInSeconds(now());
        NewsletterSend::create([
            'sent_at' => $startedAt,
            'subscriber_count' => $count,
            'posts_count' => $posts->count(),
            'post_ids' => $posts->pluck('id')->toArray(),
            'status' => 'sent',
            'triggered_by' => $triggeredBy,
            'created_by_user_id' => $this->option('user-id'),
            'duration_seconds' => $duration,
        ]);

        $this->info("Queued $count subscribers · {$posts->count()} posts (duration {$duration}s)");
        return self::SUCCESS;
    }
}
