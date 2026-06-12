<?php

namespace App\Services;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use Carbon\Carbon;

/**
 * The single scheduling write for a LinkedIn draft — sets the publish slot,
 * advances the FSM into awaiting_publish, propagates the slot to cross-post
 * siblings, and clears the Telegram schedule-prompt idempotency flag.
 *
 * Phase D — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md.
 * Extracted from LinkedInDraftController::approve so the admin approve flow,
 * the Telegram slot button, and the Telegram free-text confirm all schedule
 * through ONE code path (DRY). Caller-specific side effects (HTTP readiness
 * gates, carousel image self-heal, cross-post fan-out scan) stay with the
 * caller — this method is purely the schedule write.
 */
class LinkedInSchedulingService
{
    public function __construct(private readonly PipelineGuard $guard)
    {
    }

    /**
     * Schedule $draft to publish at $slot.
     *
     * - manual_review (or any non-terminal pre-publish state) → awaiting_publish
     *   via PipelineGuard. An already-awaiting_publish draft is a reschedule:
     *   the FSM advance is skipped (same-state transition would throw).
     * - scheduled_at AND cancel_window_ends_at both = $slot (post-May-12: the
     *   publish cron fires when cancel_window_ends_at <= now()).
     * - schedule_prompt_sent_at cleared (the Telegram prompt is resolved).
     * - $slot mirrored onto facebook/instagram/tiktok/threads siblings so the
     *   social:publish-slot orchestrator finds them at the same minute tick.
     */
    public function scheduleAt(LinkedInPost $draft, Carbon $slot, string $reason): void
    {
        if ($draft->status !== LinkedInPostStatus::AwaitingPublish->value) {
            $this->guard->advance(
                $draft,
                LinkedInPostStatus::AwaitingPublish,
                $reason,
                ['draft_id' => $draft->id, 'publish_at' => $slot->toIso8601String()]
            );
        }

        $draft->update([
            'scheduled_at' => $slot,
            'cancel_window_ends_at' => $slot,
            'schedule_prompt_sent_at' => null,
        ]);

        $draft->load(['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost']);
        foreach (['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'] as $rel) {
            $draft->$rel?->update(['scheduled_at' => $slot]);
        }
    }
}
