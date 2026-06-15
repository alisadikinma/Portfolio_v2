<?php

namespace App\Enums;

enum LinkedInPostStatus: string
{
    case PendingGeneration = 'pending_generation';
    case Generating = 'generating';
    case Validating = 'validating';
    case AwaitingPublish = 'awaiting_publish';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case ManualReview = 'manual_review';

    /**
     * FSM adjacency map. Per plugin design + review fixes:
     *
     *   - AwaitingPublish → ManualReview : kill-switch demotion when
     *     LINKEDIN_AUTO_PUBLISH=false at fire time (reason: 'kill_switch_demotion')
     *   - Cancelled/Failed → Generating : regenerate endpoint
     *     (reason: 'admin_regenerate')
     *   - Cancelled → ManualReview : "Balik ke Social Studio" revive action
     *     (reason: 'revive_to_social_studio'). Un-cancels a draft WITHOUT
     *     regenerating — its already-rendered carousel slides are preserved and
     *     it lands back in Social Studio as an actionable (re-schedulable) draft.
     *   - Failed → PendingGeneration : bounded auto-retry cron
     *     (linkedin:retry-failed, reason: 'auto_retry_class_*'). Distinct
     *     from regenerate edge — the cron re-queues the draft and lets the
     *     service own the eventual PendingGeneration → Generating transition,
     *     and the intermediate status change naturally excludes the row from
     *     the next cron tick's `status=failed` filter (no double-dispatch).
     *   - Published : terminal. Regenerate from published creates a NEW row
     *     (old one soft-deleted first; uniqueness enforced in controller).
     *   - Self-transition on Generating allowed: plugin may re-enter generation
     *     if cron retries a stuck draft.
     *   - AwaitingPublish → Failed : publish API errors (LinkedIn 429 quota,
     *     5xx, network). Without this edge, social:publish-slot loops forever
     *     hammering a failed publish call every minute — production incident
     *     2026-05-13 burned LinkedIn daily quota in ~30 min via 3 stuck
     *     drafts × 60 ticks/hr = 180 wasted 429 calls/hr.
     */
    public const TRANSITIONS = [
        'pending_generation' => ['generating', 'cancelled'],
        'generating' => ['generating', 'validating', 'failed', 'cancelled'],
        'validating' => ['awaiting_publish', 'manual_review', 'failed', 'cancelled'],
        'manual_review' => ['awaiting_publish', 'validating', 'cancelled'],
        'awaiting_publish' => ['published', 'cancelled', 'manual_review', 'failed'],
        'failed' => ['generating', 'cancelled', 'pending_generation'],
        'published' => [],
        'cancelled' => ['generating', 'manual_review'],
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }

    /**
     * Success-feed statuses (shown on /admin/linkedin-posts).
     */
    public static function feedStatuses(): array
    {
        return [
            self::AwaitingPublish->value,
            self::Published->value,
            self::Cancelled->value,
        ];
    }

    /**
     * Triage-queue statuses (shown on /admin/linkedin-queue).
     */
    public static function queueStatuses(): array
    {
        return [
            self::PendingGeneration->value,
            self::Generating->value,
            self::Validating->value,
            self::ManualReview->value,
            self::Failed->value,
        ];
    }
}
