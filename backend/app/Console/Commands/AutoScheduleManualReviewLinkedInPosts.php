<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Jobs\GenerateLinkedInCarouselImages;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Exceptions\NoAvailableSlotException;
use App\Services\LinkedInFixedSlotScheduler;
use App\Services\PipelineGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily 04:30 WIB cron — promotes LinkedIn drafts in `manual_review` to
 * `awaiting_publish`, assigning `cancel_window_ends_at` to the next
 * posting_time_rules.score >= 85 slot (14-day lookahead).
 *
 * Gated by setting `linkedin_auto_approve_enabled` (default 'false').
 * Drafts ordered by virality_score DESC, created_at ASC (highest virality
 * gets prime slot, FIFO tiebreaker).
 *
 * Loop guard: skips drafts demoted by `linkedin:process-scheduled` due to
 * kill_switch_demotion within the last 24h, so flipping auto_publish off
 * while auto_approve stays on doesn't ping-pong drafts between states.
 *
 * Carousel format: dispatches GenerateLinkedInCarouselImages for slides
 * not yet rendered, mirroring the self-heal path in
 * LinkedInDraftController::approve.
 */
class AutoScheduleManualReviewLinkedInPosts extends Command
{
    protected $signature = 'linkedin:auto-schedule
                            {--dry-run : Log planned promotions without writing state}
                            {--limit= : Cap the number of promotions per tick}
                            {--lookahead=14 : Max future days to walk when picking slots}';

    protected $description = 'Promote manual_review LinkedIn drafts to awaiting_publish on next ideal posting slot.';

    private const KILL_SWITCH_LOOP_GUARD_HOURS = 24;

    public function __construct(
        private readonly LinkedInFixedSlotScheduler $scheduler,
        private readonly PipelineGuard $guard,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Telegram human-in-the-loop scheduling (June 12, 2026) supersedes the
        // auto-schedule cron: when the operator owns slot selection via Telegram,
        // defer so the two never race over the same manual_review drafts.
        $telegramSchedule = Setting::query()
            ->where('group', 'linkedin')
            ->where('key', 'linkedin_telegram_schedule_enabled')
            ->value('value');

        if ($telegramSchedule === 'true') {
            $this->info('[linkedin:auto-schedule] deferred — linkedin_telegram_schedule_enabled is on (Telegram owns scheduling).');
            Log::info('[linkedin:auto-schedule] deferred: telegram scheduling enabled');
            return self::SUCCESS;
        }

        $enabled = Setting::query()
            ->where('group', 'linkedin')
            ->where('key', 'linkedin_auto_approve_enabled')
            ->value('value');

        if ($enabled !== 'true') {
            $this->info('[linkedin:auto-schedule] kill switch off — exiting.');
            Log::info('[linkedin:auto-schedule] skipped: linkedin_auto_approve_enabled != true');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $lookahead = (int) $this->option('lookahead');

        $candidates = $this->loadCandidates();

        $promoted = 0;
        $skipped = 0;
        $failed = 0;
        $assignedSlots = [];

        foreach ($candidates as $draft) {
            if ($limit !== null && $promoted >= $limit) {
                break;
            }

            if ($this->wasRecentlyDemotedByKillSwitch($draft)) {
                $skipped++;
                Log::debug('[linkedin:auto-schedule] skipped (kill_switch_loop_guard)', [
                    'draft_id' => $draft->id,
                ]);
                continue;
            }

            // Carousel readiness gate. A carousel draft must have every slide
            // rendered (image_status='done' + image_url present) before it
            // can be promoted to awaiting_publish — otherwise the publish-
            // time cron either ships a broken post or fails the publish gate
            // and we waste the scheduled slot. Dispatch image gen (idempotent)
            // and skip so the next tick (or manual operator action) picks
            // it up after slides finish.
            if ($draft->format === 'carousel' && ! $this->carouselSlidesReady($draft)) {
                $skipped++;
                if (! $dryRun) {
                    $this->triggerImageGenIfSlidesPending($draft);
                }
                $this->line(sprintf(
                    '  skipped #%d (carousel slides not ready)',
                    $draft->id
                ));
                Log::info('[linkedin:auto-schedule] skipped (carousel_slides_not_ready)', [
                    'draft_id' => $draft->id,
                    'slides' => $this->slideStatusBreakdown($draft),
                ]);
                continue;
            }

            // Post-May-12: use fixed-slot scheduler (5/6/7/12/17/18/19/20 WIB
            // by default). DB collision query inside scheduler dedupes against
            // already-promoted drafts. For dry-run, $assignedSlots tracks the
            // simulated promotions so multiple dry runs don't all show the
            // same slot.
            try {
                if ($dryRun && !empty($assignedSlots)) {
                    // Walk forward past simulated slots
                    $from = Carbon::now();
                    do {
                        $slot = $this->scheduler->nextAvailableSlot($from);
                        if (! in_array($slot->toIso8601String(), $assignedSlots, true)) {
                            break;
                        }
                        $from = $slot->copy()->addMinute();
                    } while (true);
                } else {
                    $slot = $this->scheduler->nextAvailableSlot();
                }
            } catch (NoAvailableSlotException $e) {
                Log::warning('[linkedin:auto-schedule] lookahead exhausted — backlog larger than slot capacity', [
                    'remaining_drafts' => $candidates->count() - $promoted - $skipped - $failed,
                    'lookahead_days' => $lookahead,
                    'reason' => $e->getMessage(),
                ]);
                break;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] would promote draft #%d (virality=%s) → %s',
                    $draft->id,
                    $draft->getAttribute('virality_score') ?? 'null',
                    $slot->toIso8601String()
                ));
                $promoted++;
                $assignedSlots[] = $slot->toIso8601String();
                continue;
            }

            try {
                $this->promoteDraft($draft, $slot);
                $promoted++;
                $assignedSlots[] = $slot->toIso8601String();
            } catch (\Throwable $e) {
                $failed++;
                Log::error('[linkedin:auto-schedule] promotion failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            '[linkedin:auto-schedule] processed: %d promoted, %d skipped, %d failed (lookahead=%d, dry-run=%s)',
            $promoted,
            $skipped,
            $failed,
            $lookahead,
            $dryRun ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }

    /**
     * Loads manual_review drafts ordered by virality_score DESC then created_at ASC.
     * Uses a left join so manual drafts (no ContentIdea linkage) still appear,
     * sorted last via COALESCE(virality_score, 0).
     */
    private function loadCandidates()
    {
        return LinkedInPost::query()
            ->select('linkedin_posts.*')
            ->selectRaw('COALESCE(content_ideas.virality_score, 0) as virality_score')
            ->leftJoin('posts', 'linkedin_posts.post_id', '=', 'posts.id')
            ->leftJoin('content_ideas', 'posts.id', '=', 'content_ideas.result_post_id')
            ->where('linkedin_posts.format', '!=', LinkedInPost::FORMAT_VIDEO_CAROUSEL) // Zernio-only IG-video anchors
            ->where('linkedin_posts.status', LinkedInPostStatus::ManualReview->value)
            ->whereNull('linkedin_posts.deleted_at')
            ->orderByDesc('virality_score')
            ->orderBy('linkedin_posts.created_at')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Inspects pipeline_state_log for a recent kill_switch_demotion entry.
     * Prevents the auto_publish=off + auto_approve=on ping-pong loop.
     */
    private function wasRecentlyDemotedByKillSwitch(LinkedInPost $draft): bool
    {
        $log = $draft->pipeline_state_log ?? [];
        if (empty($log)) {
            return false;
        }

        $cutoff = Carbon::now()->subHours(self::KILL_SWITCH_LOOP_GUARD_HOURS);

        // Walk in reverse — most recent entries come last.
        foreach (array_reverse($log) as $entry) {
            $reason = (string) ($entry['reason'] ?? '');
            $to = (string) ($entry['to'] ?? '');
            $timestampStr = $entry['timestamp'] ?? null;

            if (! $timestampStr) {
                continue;
            }

            $timestamp = Carbon::parse($timestampStr);
            if ($timestamp->lt($cutoff)) {
                // Older than the guard window — and since we're walking newest-first,
                // anything else is also older. Done scanning.
                break;
            }

            if ($to === LinkedInPostStatus::ManualReview->value
                && stripos($reason, 'kill_switch') !== false) {
                return true;
            }
        }

        return false;
    }

    private function promoteDraft(LinkedInPost $draft, Carbon $slot): void
    {
        DB::transaction(function () use ($draft, $slot): void {
            // Post-May-12: scheduled_at = slot (when publish fires), same as
            // cancel_window_ends_at. linkedin:process-scheduled cron triggers
            // on cancel_window_ends_at <= now() — both fields aligned.
            $draft->update([
                'scheduled_at' => $slot,
                'cancel_window_ends_at' => $slot,
            ]);

            $this->guard->advance(
                $draft,
                LinkedInPostStatus::AwaitingPublish,
                'auto_schedule:no_gate',
                [
                    'cancel_window_ends_at' => $slot->toIso8601String(),
                    'virality_score' => $draft->getAttribute('virality_score'),
                ]
            );
        });

        if ($draft->format === 'carousel') {
            $this->triggerImageGenIfSlidesPending($draft);
        }
    }

    /**
     * Carousel readiness gate: every slide must have image_status='done'
     * AND a non-empty image_url. If even one slide is pending/generating/
     * failed, the draft is NOT ready for promotion to awaiting_publish.
     */
    private function carouselSlidesReady(LinkedInPost $draft): bool
    {
        $slides = $draft->carousel_slides ?? [];
        if (empty($slides)) {
            return false;
        }
        foreach ($slides as $slide) {
            $status = $slide['image_status'] ?? null;
            $url = $slide['image_url'] ?? null;
            if ($status !== 'done' || empty($url)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Compact summary of slide statuses for log lines + Telegram dispatch.
     * Returns ['done'=>3, 'failed'=>6, 'generating'=>1, ...].
     */
    private function slideStatusBreakdown(LinkedInPost $draft): array
    {
        $breakdown = ['total' => 0];
        foreach ($draft->carousel_slides ?? [] as $slide) {
            $status = (string) ($slide['image_status'] ?? 'pending');
            $breakdown[$status] = ($breakdown[$status] ?? 0) + 1;
            $breakdown['total']++;
        }
        return $breakdown;
    }

    /**
     * Mirrors the self-heal path in LinkedInDraftController::approve.
     * Idempotent — GenerateLinkedInCarouselImages skips slides already 'done'.
     */
    private function triggerImageGenIfSlidesPending(LinkedInPost $draft): void
    {
        $slides = $draft->carousel_slides ?? [];
        $needsImages = false;
        foreach ($slides as $slide) {
            $status = $slide['image_status'] ?? null;
            $url = $slide['image_url'] ?? null;
            if ($status !== 'done' || empty($url)) {
                $needsImages = true;
                break;
            }
        }

        if (! $needsImages) {
            return;
        }

        try {
            GenerateLinkedInCarouselImages::dispatch($draft->id);
            Log::info('[linkedin:auto-schedule] dispatched carousel images', [
                'draft_id' => $draft->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[linkedin:auto-schedule] image dispatch failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
