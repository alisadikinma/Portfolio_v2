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
use Illuminate\Support\Facades\Cache;
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

        $publishReadyStatuses = [
            LinkedInPostStatus::AwaitingPublish->value,
            LinkedInPostStatus::Published->value,
        ];
        $terminalStatuses = [
            LinkedInPostStatus::Cancelled->value,
            LinkedInPostStatus::Failed->value,
        ];

        $draftIdFilter = $this->option('draft-id');
        $targeted = $draftIdFilter !== null && $draftIdFilter !== '';

        $query = LinkedInPost::query()
            ->with(['post.contentIdea:id,result_post_id,virality_score']);

        if ($targeted) {
            // Targeted scan — from the /publish-all cascade OR the event-driven
            // all-slides-done dispatch in LinkedInCarouselImageService. Bypass
            // status + window + virality gates: the caller explicitly opted in.
            // disqualify() still enforces format + all-slides-done + idempotency,
            // so a carousel in manual_review/validating with rendered slides
            // fans out, while an unrendered one is still skipped.
            $query->where('id', (int) $draftIdFilter);
            $this->info("Targeted scan: draft id={$draftIdFilter} (status + window + virality gates bypassed)");
        } else {
            // Reaper gate (June 9, 2026 — widened for default-carousel). Pick up:
            //   (a) any draft at awaiting_publish/published (text + carousel,
            //       the original behavior), OR
            //   (b) any CAROUSEL draft in a non-terminal state — so it fans out
            //       the moment its slides render (disqualify() enforces
            //       all-slides-done), without waiting for the LinkedIn FSM to
            //       reach awaiting_publish.
            // Text drafts still require awaiting_publish/published — they have
            // no slides to gate on, so operator/scheduler readiness stays the
            // trigger (avoids fanning out text siblings mid-generation).
            $query->where(function ($q) use ($publishReadyStatuses, $terminalStatuses) {
                $q->whereIn('status', $publishReadyStatuses)
                    ->orWhere(function ($q2) use ($terminalStatuses) {
                        $q2->where('format', 'carousel')
                            ->whereNotIn('status', $terminalStatuses);
                    });
            });
            $query->where('updated_at', '>=', now()->subHours($hours));

            if ($minVirality > 0) {
                $query->whereHas('post.contentIdea', function ($q) use ($minVirality) {
                    $q->where('virality_score', '>=', $minVirality);
                });
                $this->info("Virality gate active: contentIdea.virality_score >= {$minVirality}");
            } else {
                $this->info('Virality gate disabled (min-virality=0)');
            }
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
            // Per-draft mutex (June 9, 2026): the cross-post tables have NO DB
            // unique constraint on post_id (MySQL can't do partial unique
            // indexes on deleted_at — see the create_*_posts migrations), so
            // disqualify()'s check-then-create is a TOCTOU window. The new
            // event-driven fan-out (LinkedInCarouselImageService) raises how
            // often a targeted --draft-id scan overlaps the every-2-min reaper
            // scan (cron vs default worker = separate processes). A non-blocking
            // lock keyed by draft id serializes concurrent scans of the SAME
            // draft so two can't both pass disqualify() and double-create.
            $lock = Cache::lock("crosspost-fanout:{$linkedinPost->id}", 60);
            if (! $lock->get()) {
                $this->line("  - skip LI#{$linkedinPost->id}: fan-out lock held by a concurrent scan");
                $stats['skipped']++;
                continue;
            }

            $reason = $this->disqualify($linkedinPost);
            if ($reason !== null) {
                $this->line("  - skip LI#{$linkedinPost->id} (post #{$linkedinPost->post_id}): {$reason}");
                $stats['skipped']++;
                $lock->release();
                continue;
            }

            $format = (string) $linkedinPost->format;
            $this->line("  + LI#{$linkedinPost->id} (post #{$linkedinPost->post_id}) format={$format}");

            if ($dryRun) {
                $lock->release();
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

                // P5 (May 12) — propagate LinkedIn slot to newly-created
                // siblings so social:publish-slot orchestrator finds them at
                // the same minute tick on carousel-atomic-publish.
                if ($linkedinPost->scheduled_at !== null) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($linkedinPost) {
                        $linkedinPost->refresh()->load(['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost']);
                        foreach (['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'] as $rel) {
                            $linkedinPost->$rel?->update(['scheduled_at' => $linkedinPost->scheduled_at]);
                        }
                    });
                }
            } catch (\Throwable $e) {
                $this->error("    failed: {$e->getMessage()}");
                Log::error('[CrossPostScan] Failed to fan out LinkedIn draft', [
                    'linkedin_post_id' => $linkedinPost->id,
                    'post_id' => $linkedinPost->post_id,
                    'format' => $format,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                $lock->release();
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

    // Idempotency guard is keyed on linkedin_post_id (the specific draft being
    // fanned out), NOT post_id. Keying on post_id meant a regenerated draft
    // (e.g. text→carousel, which soft-deletes the old draft and creates a new
    // linkedin_posts row) inherited the OLD draft's siblings as a "live row"
    // and got disqualified — so IG/TikTok/Threads captions never generated for
    // the new draft. Per-draft keying scopes the guard to the current draft.
    private function hasLiveFacebookRow(LinkedInPost $li): bool
    {
        return FacebookPost::where('linkedin_post_id', $li->id)->exists();
    }

    private function hasLiveInstagramRow(LinkedInPost $li): bool
    {
        return InstagramPost::where('linkedin_post_id', $li->id)->exists();
    }

    private function hasLiveTiktokRow(LinkedInPost $li): bool
    {
        return TiktokPost::where('linkedin_post_id', $li->id)->exists();
    }

    private function hasLiveThreadsRow(LinkedInPost $li): bool
    {
        return ThreadsPost::where('linkedin_post_id', $li->id)->exists();
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

        GenerateFacebookPost::dispatch($draft->id)->onQueue('social-crosspost');
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

        GenerateInstagramPost::dispatch($draft->id)->onQueue('social-crosspost');
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

        GenerateTiktokPost::dispatch($draft->id)->onQueue('social-crosspost');
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

        GenerateThreadsPost::dispatch($draft->id)->onQueue('social-crosspost');
        Log::info('[CrossPostScan] Threads draft created + dispatched', [
            'linkedin_post_id' => $li->id,
            'threads_post_id' => $draft->id,
            'format' => $format,
        ]);
    }
}
