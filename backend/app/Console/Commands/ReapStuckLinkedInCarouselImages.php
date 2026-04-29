<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLinkedInCarouselImages;
use App\Models\LinkedInPost;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Catches LinkedIn carousel slides stuck mid-render.
 *
 * The existing ReapStuckLinkedInPosts command only handles FSM-level stalls
 * (status=generating/validating). This command operates one level deeper —
 * the per-slide `image_status` lifecycle inside `linkedin_posts.carousel_slides[]`.
 *
 * Two stall modes get re-dispatched:
 *
 *   A. image_status='pending'    — backend persisted the slide but never POSTed
 *                                    to GeminiGen (queue worker died after the
 *                                    LinkedInGenerationService::persistAndRoute
 *                                    transaction but before
 *                                    GenerateLinkedInCarouselImages fired, OR
 *                                    GeminiGen dispatch errored and we never
 *                                    advanced past 'pending').
 *                                    Threshold: 30 minutes (any slide pending
 *                                    longer than this is definitionally stuck).
 *
 *   B. image_status='generating' — POST landed at GeminiGen but the webhook
 *                                    never came back (GeminiGen drop, signed
 *                                    URL expiry, public ingress flake).
 *                                    Threshold: 15 minutes (typical render
 *                                    finishes in 30-90s; 15 min is 10x worst-
 *                                    case so we don't false-positive a slow
 *                                    render).
 *
 * Action per stuck draft:
 *   - Re-dispatch GenerateLinkedInCarouselImages (idempotent — service skips
 *     slides already image_status='done').
 *
 * The job's dispatchAllSlides() resets in-flight slides to a fresh GeminiGen
 * call, so a 15+ min stuck 'generating' slot effectively re-enters the queue.
 *
 * NOT auto-failed — only re-dispatched. Repeated re-dispatch every 5 min
 * doesn't burn out (idempotent on done slides, GeminiGen rate-limits are
 * generous), and surfaces persistent failures via the existing safety-rewrite
 * + last_error handling in LinkedInCarouselImageService::handleWebhook.
 *
 * Schedule: every 5 minutes — `routes/console.php`.
 */
class ReapStuckLinkedInCarouselImages extends Command
{
    protected $signature = 'linkedin:reap-stuck-carousel-images
        {--pending-threshold=30 : Minutes before pending slides are re-dispatched}
        {--generating-threshold=15 : Minutes before generating slides are re-dispatched}
        {--dry-run : Log candidates without re-dispatching}';

    protected $description = 'Re-dispatch GenerateLinkedInCarouselImages for drafts whose slides are stuck in pending/generating';

    public function handle(): int
    {
        $pendingThreshold = max(5, (int) $this->option('pending-threshold'));
        $generatingThreshold = max(5, (int) $this->option('generating-threshold'));
        $dryRun = (bool) $this->option('dry-run');

        // Look at carousel drafts in a state where image rendering can still
        // make progress — manual_review (the typical landing post-validation)
        // and awaiting_publish (auto-publish kept running). Generating /
        // validating are FSM-level states already covered by the FSM reaper.
        $candidates = LinkedInPost::query()
            ->where('format', 'carousel')
            ->whereIn('status', ['manual_review', 'awaiting_publish'])
            ->whereNotNull('carousel_slides')
            ->get();

        if ($candidates->isEmpty()) {
            return self::SUCCESS;
        }

        $now = now();
        $reapable = [];

        foreach ($candidates as $draft) {
            $slides = $draft->carousel_slides;
            if (! is_array($slides) || count($slides) === 0) {
                continue;
            }

            $stuckPending = 0;
            $stuckGenerating = 0;

            // Use updated_at as the per-slide age proxy. We don't track per-slide
            // dispatched_at on the row (would require schema change) — relying on
            // the row's overall updated_at is conservative: any slide write
            // (webhook arrival or status flip) bumps it, so a draft whose
            // updated_at is fresh isn't reaped even if one slide is technically
            // older. False-negatives ok here; the next tick catches them.
            $ageMinutes = $draft->updated_at
                ? (int) round($draft->updated_at->diffInMinutes($now))
                : PHP_INT_MAX;

            foreach ($slides as $slide) {
                $st = $slide['image_status'] ?? null;
                if ($st === 'pending' && $ageMinutes >= $pendingThreshold) {
                    $stuckPending++;
                } elseif ($st === 'generating' && $ageMinutes >= $generatingThreshold) {
                    $stuckGenerating++;
                }
            }

            if ($stuckPending === 0 && $stuckGenerating === 0) {
                continue;
            }

            $reapable[] = [
                'draft' => $draft,
                'pending' => $stuckPending,
                'generating' => $stuckGenerating,
                'age' => $ageMinutes,
            ];
        }

        if ($reapable === []) {
            return self::SUCCESS;
        }

        $this->info('Found '.count($reapable).' carousel draft(s) with stuck slides.');

        $redispatched = 0;
        foreach ($reapable as $row) {
            /** @var LinkedInPost $draft */
            $draft = $row['draft'];
            $this->line(sprintf(
                '  #%d  age=%dm  stuck-pending=%d  stuck-generating=%d',
                $draft->id,
                $row['age'],
                $row['pending'],
                $row['generating']
            ));

            if ($dryRun) {
                continue;
            }

            try {
                GenerateLinkedInCarouselImages::dispatch($draft->id);
                $redispatched++;

                Log::info('[LinkedInCarouselReaper] re-dispatched stuck draft', [
                    'draft_id' => $draft->id,
                    'age_minutes' => $row['age'],
                    'stuck_pending' => $row['pending'],
                    'stuck_generating' => $row['generating'],
                ]);
            } catch (\Throwable $e) {
                $this->error("    failed to re-dispatch #{$draft->id}: {$e->getMessage()}");
                Log::error('[LinkedInCarouselReaper] re-dispatch failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($dryRun
            ? 'Dry run — would have re-dispatched '.count($reapable).' draft(s).'
            : "Re-dispatched {$redispatched} draft(s)."
        );

        return self::SUCCESS;
    }
}
