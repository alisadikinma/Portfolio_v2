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
     *   - Published : terminal. Regenerate from published creates a NEW row
     *     (old one soft-deleted first; uniqueness enforced in controller).
     *   - Self-transition on Generating allowed: plugin may re-enter generation
     *     if cron retries a stuck draft.
     */
    public const TRANSITIONS = [
        'pending_generation' => ['generating', 'cancelled'],
        'generating' => ['generating', 'validating', 'failed', 'cancelled'],
        'validating' => ['awaiting_publish', 'manual_review', 'failed', 'cancelled'],
        'manual_review' => ['awaiting_publish', 'validating', 'cancelled'],
        'awaiting_publish' => ['published', 'cancelled', 'manual_review'],
        'failed' => ['generating', 'cancelled'],
        'published' => [],
        'cancelled' => ['generating'],
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
