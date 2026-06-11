<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LinkedInPostStatus;
use App\Exceptions\NoAvailableSlotException;
use App\Jobs\PublishViaPubler;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Services\LinkedInFixedSlotScheduler;
use App\Services\LinkedInPublishService;
use App\Services\LinkedInSlotReadinessService;
use App\Services\PipelineGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * social:publish-slot — every-minute cron (May 12, 2026).
 *
 * Atomic orchestrator. When a LinkedIn slot fires (scheduled_at <= now()):
 *
 *   1. format=text  → publish LinkedIn solo via existing LinkedInPublishService.
 *                     FB+TH siblings (scanner-created) publish independently on
 *                     their own crons. No atomic gating needed.
 *
 *   2. format=carousel → atomic readiness check via LinkedInSlotReadinessService.
 *      - All ready → publish LinkedIn first (so first-comment URL is well-formed),
 *                    then dispatch PublishViaPubler for each non-null sibling
 *                    in parallel — IG/TT/TH all fire same minute tick.
 *      - Not ready + postpone_count < linkedin_max_postpones → shift LinkedIn
 *                    AND all siblings to next slot, increment postpone counter.
 *      - Not ready + postpone_count >= cap → publish LinkedIn solo, mark unready
 *                    siblings as manual_review (operator handles tail).
 *
 * Replaces linkedin:process-scheduled (which only knew LinkedIn). The legacy
 * command delegates to this orchestrator for backwards-compat through P7.
 *
 * Kill switch: linkedin_auto_publish=false → still demote past-due drafts to
 * manual_review (existing pattern preserved).
 */
class PublishSlotOrchestrator extends Command
{
    protected $signature = 'social:publish-slot
                            {--dry-run : Log decisions without publishing or postponing}
                            {--limit=10 : Cap drafts processed per tick}';

    protected $description = 'Atomic publish at slot tick — LinkedIn + carousel siblings (IG/TT/TH) fire together';

    private const PIPELINE_LOG_REASON_POSTPONE = 'slot_postponed_siblings_not_ready';

    public function handle(
        LinkedInPublishService $publisher,
        LinkedInSlotReadinessService $readiness,
        LinkedInFixedSlotScheduler $scheduler,
        PipelineGuard $guard,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) ($this->option('limit') ?: 10);

        $due = LinkedInPost::query()
            ->where('status', LinkedInPostStatus::AwaitingPublish->value)
            ->whereNotNull('cancel_window_ends_at')
            ->where('cancel_window_ends_at', '<=', now())
            ->whereNull('deleted_at')
            ->with(['account', 'facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'])
            ->limit($limit)
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("Processing {$due->count()} drafts due for publish.");

        $autoPublishSetting = Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_auto_publish')
            ->value('value');
        $autoPublish = filter_var($autoPublishSetting, FILTER_VALIDATE_BOOLEAN);

        $maxPostpones = (int) (Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_max_postpones')
            ->value('value') ?? 2);

        foreach ($due as $draft) {
            if (!$autoPublish) {
                $this->demoteToManualReview($draft, $guard, $dryRun);
                continue;
            }

            if ($draft->format === 'text') {
                $this->publishTextDraft($draft, $publisher, $guard, $dryRun);
                continue;
            }

            // Carousel path: atomic readiness + postpone-or-publish
            $check = $readiness->isReady($draft);
            if ($check['ready']) {
                $this->publishCarouselDraftAtomic($draft, $publisher, $guard, $dryRun);
            } else {
                $this->handleNotReady($draft, $check['blockers'], $scheduler, $publisher, $guard, $maxPostpones, $dryRun);
            }
        }

        return self::SUCCESS;
    }

    private function publishTextDraft(
        LinkedInPost $draft,
        LinkedInPublishService $publisher,
        PipelineGuard $guard,
        bool $dryRun
    ): void {
        if ($dryRun) {
            $this->line("  [dry-run] text #{$draft->id} → would publish LinkedIn solo");
            return;
        }
        $this->actuallyPublishLinkedIn($draft, $publisher, $guard);
    }

    private function publishCarouselDraftAtomic(
        LinkedInPost $draft,
        LinkedInPublishService $publisher,
        PipelineGuard $guard,
        bool $dryRun
    ): void {
        $siblingCount = collect(['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'])
            ->filter(fn ($rel) => $draft->$rel !== null)
            ->count();

        if ($dryRun) {
            $this->line("  [dry-run] carousel #{$draft->id} → would publish LinkedIn + {$siblingCount} sibling(s)");
            return;
        }

        // LinkedIn first so first-comment URL works when sibling fires
        $linkedinOk = $this->actuallyPublishLinkedIn($draft, $publisher, $guard);

        if (!$linkedinOk) {
            // LinkedIn failed — DON'T dispatch siblings (would orphan)
            return;
        }

        // Parallel sibling dispatch — all enqueue same tick, queue workers pick up.
        // Per-platform gate: skip platforms with no Publer account selected
        // (operator directive — "cek dulu settingan ke sosmed mana saja").
        foreach (['instagram', 'tiktok', 'threads', 'facebook'] as $platform) {
            $rel = $platform . 'Post';
            $sibling = $draft->$rel;
            if ($sibling === null) {
                continue;
            }
            if (!\App\Services\PublerPayloadBuilder::isPlatformEnabled($platform)) {
                $this->line("  ⊘ {$platform} not configured in Publer settings — skipped");
                continue;
            }
            PublishViaPubler::dispatch($platform, $sibling->id);
            $this->line("  → dispatched {$platform} sibling #{$sibling->id}");
        }
    }

    private function handleNotReady(
        LinkedInPost $draft,
        array $blockers,
        LinkedInFixedSlotScheduler $scheduler,
        LinkedInPublishService $publisher,
        PipelineGuard $guard,
        int $maxPostpones,
        bool $dryRun
    ): void {
        $log = $draft->pipeline_state_log ?? [];
        $postponeCount = collect($log)
            ->where('reason', self::PIPELINE_LOG_REASON_POSTPONE)
            ->count();

        if ($postponeCount >= $maxPostpones) {
            // Max postpones hit — ship LinkedIn solo, drop unready siblings to manual_review
            $this->warn("  #{$draft->id} max postpones reached ({$postponeCount}) — LinkedIn solo + manual_review siblings");
            if ($dryRun) {
                return;
            }
            $this->actuallyPublishLinkedIn($draft, $publisher, $guard);
            $this->dropUnreadySiblingsToManualReview($draft, $blockers);
            // Telegram alert deferred — DispatchTelegramNotification is keyed
            // to ContentIdea; system-level slot alerts need new service method
            // (see P7 follow-up). For now, WARNING log surfaces the event.
            Log::warning('[social:publish-slot] siblings dropped to manual_review (max postpones)', [
                'draft_id' => $draft->id,
                'blockers' => $blockers,
            ]);
            return;
        }

        // Postpone: shift LinkedIn + siblings to next available slot
        try {
            $nextSlot = $scheduler->nextAvailableSlot();
        } catch (NoAvailableSlotException $e) {
            $this->error("  #{$draft->id} postpone failed: {$e->getMessage()}");
            return;
        }

        $this->warn("  #{$draft->id} postponed to {$nextSlot->toIso8601String()} — blockers: " . implode(', ', $blockers));
        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($draft, $nextSlot, $blockers, $postponeCount) {
            $log = $draft->pipeline_state_log ?? [];
            $log[] = [
                'from' => $draft->status,
                'to' => $draft->status,
                'reason' => self::PIPELINE_LOG_REASON_POSTPONE,
                'timestamp' => now()->toIso8601String(),
                'context' => [
                    'new_slot' => $nextSlot->toIso8601String(),
                    'blockers' => $blockers,
                    'postpone_count' => $postponeCount + 1,
                ],
            ];
            if (count($log) > 20) {
                $log = array_slice($log, -20);
            }
            $draft->pipeline_state_log = $log;
            $draft->scheduled_at = $nextSlot;
            $draft->cancel_window_ends_at = $nextSlot;
            $draft->save();

            // Propagate to siblings
            foreach (['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'] as $rel) {
                $draft->$rel?->update([
                    'scheduled_at' => $nextSlot,
                ]);
            }
        });

        Log::warning('[social:publish-slot] postponed', [
            'draft_id' => $draft->id,
            'new_slot' => $nextSlot->toIso8601String(),
            'blockers' => $blockers,
        ]);
    }

    private function actuallyPublishLinkedIn(
        LinkedInPost $draft,
        LinkedInPublishService $publisher,
        PipelineGuard $guard
    ): bool {
        try {
            $result = $publisher->publish($draft);
            if ($result['success'] ?? false) {
                $urn = $result['post_urn'] ?? $result['urn'] ?? '';
                $this->info("  ✓ LinkedIn #{$draft->id} published — URN {$urn}");
                // CRITICAL: advance awaiting_publish → published here. Without
                // this the draft stays awaiting_publish with cancel_window in
                // the past, so the every-minute cron re-publishes it next tick
                // → LinkedIn 422 "duplicate" → draft marked Failed even though
                // the post is live (production incident: draft 148, 2026-06-11).
                // Mirrors LinkedInDraftController::publishNow's success path.
                try {
                    $guard->advance($draft, LinkedInPostStatus::Published, 'published_in_orchestrator', [
                        'post_urn' => $urn ?: null,
                    ]);
                    $draft->update([
                        'published_at' => now(),
                        'linkedin_post_urn' => $urn ?: null,
                        'linkedin_post_url' => $result['post_url'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    // Publish already succeeded on LinkedIn — never undo that.
                    // Log so the operator can reconcile the FSM state manually.
                    Log::warning('[social:publish-slot] FSM advance to published threw (post is live)', [
                        'draft_id' => $draft->id,
                        'post_urn' => $urn,
                        'error' => $e->getMessage(),
                    ]);
                }
                return true;
            }
            $this->error("  ✗ LinkedIn #{$draft->id} publish failed: " . ($result['error'] ?? 'unknown'));
            $draft->update(['last_error' => $result['error'] ?? 'unknown publish failure']);
            try {
                $guard->advance($draft, LinkedInPostStatus::Failed, 'publish_failed_in_orchestrator', [
                    'error' => $result['error'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[social:publish-slot] FSM advance to failed threw', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
            return false;
        } catch (\Throwable $e) {
            $this->error("  ✗ LinkedIn #{$draft->id} threw: {$e->getMessage()}");
            $draft->update(['last_error' => $e->getMessage()]);
            return false;
        }
    }

    private function demoteToManualReview(LinkedInPost $draft, PipelineGuard $guard, bool $dryRun): void
    {
        $this->line("  kill-switch off → #{$draft->id} demoted to manual_review");
        if ($dryRun) {
            return;
        }
        try {
            $guard->advance($draft, LinkedInPostStatus::ManualReview, 'kill_switch_demotion', [
                'cancel_window_ends_at' => $draft->cancel_window_ends_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[social:publish-slot] demotion FSM threw', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dropUnreadySiblingsToManualReview(LinkedInPost $draft, array $blockers): void
    {
        foreach (['instagram', 'tiktok', 'threads'] as $platform) {
            $rel = $platform . 'Post';
            $sibling = $draft->$rel;
            if ($sibling === null) {
                continue;
            }
            // Only demote when this platform's row was actually a blocker
            $platformBlocked = collect($blockers)->contains(fn ($b) => str_starts_with($b, $platform));
            if (! $platformBlocked) {
                continue;
            }
            $sibling->update([
                'status' => 'manual_review',
                'last_error' => 'slot_missed_max_postpones — see LinkedIn parent #' . $draft->id,
            ]);
        }
    }
}
