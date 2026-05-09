<?php

namespace App\Console\Commands;

use App\Enums\FacebookPostStatus;
use App\Enums\InstagramPostStatus;
use App\Enums\LinkedInPostStatus;
use App\Enums\TiktokPostStatus;
use App\Enums\ThreadsPostStatus;
use App\Jobs\GenerateFacebookPost;
use App\Jobs\GenerateInstagramPost;
use App\Jobs\GenerateThreadsPost;
use App\Jobs\GenerateTiktokPost;
use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cross-post fan-out scanner. Every 2 minutes finds LinkedIn drafts ready to
 * propagate to Facebook + (optionally) Instagram + TikTok.
 *
 * Format-aware routing:
 *   format='text'     → create 1 row in `facebook_posts` (FB only — IG/TT
 *                        don't do text-only posts in our pipeline)
 *   format='carousel' → create 3 rows: FB(format=carousel) + IG + TT
 *
 * Source qualification gates (BOTH must pass):
 *   1. status ∈ {awaiting_publish, published} — LinkedIn pipeline finished
 *      validation, content is final
 *   2. For carousel: every `carousel_slides[i].image_status === 'done'` —
 *      slide PNGs rendered (otherwise IG/FB carousel would have no images)
 *   3. ContentIdea.virality_score >= linkedin_virality_min_score (reused
 *      setting; manual posts without contentIdea are skipped)
 *
 * Idempotency: scanner skips LinkedIn posts that already have a live
 * (non-trashed) row in any of the 3 cross-post tables matched by `post_id`.
 * Re-running 5x produces the same DB state as 1x.
 *
 * Schedule: registered in routes/console.php as
 *   `social-cross-post:scan` every 2 minutes with `withoutOverlapping(5)`.
 *
 * CLI flags:
 *   --dry-run  : log candidates + planned actions, no DB writes
 *   --limit=N  : cap drafts created per run (safety)
 *   --hours=N  : lookback window (default 168 = 7 days; matches manual
 *                Approve patterns where blog→LinkedIn cycle can take days)
 */
class ScanLinkedInForCrossPost extends Command
{
    protected $signature = 'social-cross-post:scan
        {--dry-run : Log candidates without creating rows or dispatching jobs}
        {--limit=20 : Max LinkedIn posts to fan out per run (safety cap)}
        {--hours=168 : Lookback window in hours (default 7 days)}
        {--min-virality= : Override linkedin_virality_min_score (0 disables gate)}
        {--draft-id= : Scan only the specified LinkedIn draft id (used by /publish-all cascade)}';

    protected $description = 'Fan out finished LinkedIn drafts to Facebook + Instagram + TikTok cross-post tables';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $hours = max(1, (int) $this->option('hours'));

        $minVirality = $this->option('min-virality') !== null
            ? max(0, (int) $this->option('min-virality'))
            : (int) Setting::get('linkedin_virality_min_score', 60);

        $eligibleStatuses = [
            LinkedInPostStatus::AwaitingPublish->value,
            LinkedInPostStatus::Published->value,
        ];

        $draftIdFilter = $this->option('draft-id');

        $query = LinkedInPost::query()
            ->whereIn('status', $eligibleStatuses)
            ->with(['post.contentIdea:id,result_post_id,virality_score']);

        if ($draftIdFilter !== null && $draftIdFilter !== '') {
            // Targeted scan from /publish-all cascade — bypass time window
            // and virality gate. The operator already opted in by clicking
            // the cascade button, fan-out is the explicit intent.
            $query->where('id', (int) $draftIdFilter);
            $this->info("Targeted scan: draft id={$draftIdFilter} (window + virality gates bypassed)");
        } else {
            $query->where('updated_at', '>=', now()->subHours($hours));
        }

        if ($minVirality > 0 && ($draftIdFilter === null || $draftIdFilter === '')) {
            $query->whereHas('post.contentIdea', function ($q) use ($minVirality) {
                $q->where('virality_score', '>=', $minVirality);
            });
            $this->info("Virality gate active: contentIdea.virality_score >= {$minVirality}");
        } else {
            $this->info('Virality gate disabled (min-virality=0)');
        }

        $candidates = $query
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No eligible LinkedIn drafts found in the last {$hours}h");
            return 0;
        }

        $this->info("Found {$candidates->count()} candidate LinkedIn drafts");

        $stats = ['fb_text' => 0, 'fb_carousel' => 0, 'ig' => 0, 'tt' => 0, 'skipped' => 0];

        foreach ($candidates as $linkedinPost) {
            $reason = $this->disqualify($linkedinPost);
            if ($reason !== null) {
                $this->line("  - skip LI#{$linkedinPost->id} (post #{$linkedinPost->post_id}): {$reason}");
                $stats['skipped']++;
                continue;
            }

            $format = (string) $linkedinPost->format;
            $this->line("  + LI#{$linkedinPost->id} (post #{$linkedinPost->post_id}) format={$format}");

            if ($dryRun) {
                continue;
            }

            $platforms = [];
            try {
                if ($format === 'text') {
                    $this->createFacebook($linkedinPost, 'text');
                    $stats['fb_text']++;
                    $platforms[] = 'facebook';
                    $this->createThreads($linkedinPost, 'text');
                    $stats['threads_text'] = ($stats['threads_text'] ?? 0) + 1;
                    $platforms[] = 'threads';
                } elseif ($format === 'carousel') {
                    $this->createFacebook($linkedinPost, 'carousel');
                    $stats['fb_carousel']++;
                    $platforms[] = 'facebook';
                    $this->createInstagram($linkedinPost);
                    $stats['ig']++;
                    $platforms[] = 'instagram';
                    $this->createTiktok($linkedinPost);
                    $stats['tt']++;
                    $platforms[] = 'tiktok';
                    $this->createThreads($linkedinPost, 'carousel');
                    $stats['threads_carousel'] = ($stats['threads_carousel'] ?? 0) + 1;
                    $platforms[] = 'threads';
                }

                // Phase G — Telegram fanout alert. Dormant by default
                // (telegram_notify_crosspost_fanout setting absent → no-op).
                if (!empty($platforms)) {
                    app(TelegramNotificationService::class)
                        ->sendCrossPostFanout($linkedinPost->id, $linkedinPost->post_id, $platforms);
                }
            } catch (\Throwable $e) {
                $this->error("    failed: {$e->getMessage()}");
                Log::error('[CrossPostScan] Failed to fan out LinkedIn draft', [
                    'linkedin_post_id' => $linkedinPost->id,
                    'post_id' => $linkedinPost->post_id,
                    'format' => $format,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            '%s — FB(text)=%d FB(carousel)=%d IG=%d TT=%d skipped=%d',
            $dryRun ? 'DRY RUN summary' : 'Created',
            $stats['fb_text'],
            $stats['fb_carousel'],
            $stats['ig'],
            $stats['tt'],
            $stats['skipped']
        ));

        return 0;
    }

    /**
     * Returns a human-readable disqualification reason, or null when the
     * LinkedIn post passes all gates and should be fanned out.
     */
    private function disqualify(LinkedInPost $linkedinPost): ?string
    {
        $format = (string) $linkedinPost->format;
        if (!in_array($format, ['text', 'carousel'], true)) {
            return "unknown format='{$format}'";
        }

        if ($linkedinPost->post === null) {
            return 'parent post missing';
        }

        // Carousel: every slide must have image_status='done'. Without this
        // gate, IG/FB carousel rows would dispatch generation against
        // missing/half-rendered images and the plugin would fail.
        if ($format === 'carousel') {
            $slides = is_array($linkedinPost->carousel_slides ?? null)
                ? $linkedinPost->carousel_slides
                : [];
            if (empty($slides)) {
                return 'carousel has no slides';
            }
            foreach ($slides as $idx => $slide) {
                $imageStatus = is_array($slide) ? ($slide['image_status'] ?? null) : null;
                if ($imageStatus !== 'done') {
                    return "carousel slide #{$idx} not ready (image_status=" . ($imageStatus ?? 'null') . ')';
                }
            }
        }

        if ($this->hasLiveFacebookRow($linkedinPost)) {
            return 'facebook_posts row already exists (idempotent skip)';
        }
        if ($this->hasLiveThreadsRow($linkedinPost)) {
            return 'threads_posts row already exists (idempotent skip)';
        }
        if ($format === 'carousel') {
            if ($this->hasLiveInstagramRow($linkedinPost)) {
                return 'instagram_posts row already exists (idempotent skip)';
            }
            if ($this->hasLiveTiktokRow($linkedinPost)) {
                return 'tiktok_posts row already exists (idempotent skip)';
            }
        }

        return null;
    }

    private function hasLiveFacebookRow(LinkedInPost $li): bool
    {
        return FacebookPost::where('post_id', $li->post_id)->exists();
    }

    private function hasLiveInstagramRow(LinkedInPost $li): bool
    {
        return InstagramPost::where('post_id', $li->post_id)->exists();
    }

    private function hasLiveTiktokRow(LinkedInPost $li): bool
    {
        return TiktokPost::where('post_id', $li->post_id)->exists();
    }

    private function hasLiveThreadsRow(LinkedInPost $li): bool
    {
        return ThreadsPost::where('post_id', $li->post_id)->exists();
    }

    private function createFacebook(LinkedInPost $li, string $format): void
    {
        $draft = FacebookPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'format' => $format,
            'status' => FacebookPostStatus::PendingGeneration->value,
            'pipeline_state_log' => [[
                'from' => 'scan',
                'to' => 'pending_generation',
                'reason' => 'cross_post_scan_fanout',
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

        GenerateFacebookPost::dispatch($draft->id);
        Log::info('[CrossPostScan] FB draft created + dispatched', [
            'linkedin_post_id' => $li->id,
            'facebook_post_id' => $draft->id,
            'format' => $format,
        ]);
    }

    private function createInstagram(LinkedInPost $li): void
    {
        $draft = InstagramPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'status' => InstagramPostStatus::PendingGeneration->value,
            'pipeline_state_log' => [[
                'from' => 'scan',
                'to' => 'pending_generation',
                'reason' => 'cross_post_scan_fanout',
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

        GenerateInstagramPost::dispatch($draft->id);
        Log::info('[CrossPostScan] IG draft created + dispatched', [
            'linkedin_post_id' => $li->id,
            'instagram_post_id' => $draft->id,
        ]);
    }

    private function createTiktok(LinkedInPost $li): void
    {
        $draft = TiktokPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'status' => TiktokPostStatus::PendingGeneration->value,
            'pipeline_state_log' => [[
                'from' => 'scan',
                'to' => 'pending_generation',
                'reason' => 'cross_post_scan_fanout',
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

        GenerateTiktokPost::dispatch($draft->id);
        Log::info('[CrossPostScan] TT draft created + dispatched', [
            'linkedin_post_id' => $li->id,
            'tiktok_post_id' => $draft->id,
        ]);
    }

    private function createThreads(LinkedInPost $li, string $format): void
    {
        $draft = ThreadsPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'format' => $format,
            'status' => ThreadsPostStatus::PendingGeneration->value,
            'pipeline_state_log' => [[
                'from' => 'scan',
                'to' => 'pending_generation',
                'reason' => 'cross_post_scan_fanout',
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

        GenerateThreadsPost::dispatch($draft->id);
        Log::info('[CrossPostScan] Threads draft created + dispatched', [
            'linkedin_post_id' => $li->id,
            'threads_post_id' => $draft->id,
            'format' => $format,
        ]);
    }
}
