<?php

namespace App\Enums;

/**
 * FSM for cross-post Instagram drafts (May 7 ship + May 8 Publer-aware rename).
 *
 * 5 functional states + 2 terminal:
 *   pending_generation → generating → awaiting_review → publishing → published
 *                             ↘ failed                ↘ failed     ↘ failed
 *                                                      ↘ cancelled (DELETE Publer post)
 *
 * Lighter than LinkedInPostStatus (5 vs 8 states):
 *   - No `validating` state — plugin output validation runs inline during generating
 *   - No `awaiting_publish` auto-publish state — pivoted to Publer; FSM goes
 *     awaiting_review → publishing directly when operator clicks Approve
 *   - Terminal `published` (vs LinkedIn's `published`) — Publer confirmed publish
 *     via /job_status='complete' polling
 *
 * Rename history (May 8):
 *   awaiting_manual_publish  →  publishing  (manual workflow → Publer transport)
 *   published_externally     →  published   (operator-self-report → Publer-confirmed)
 *
 * Transition rationale:
 *   - generating → generating (self): retry path when scan cron re-picks stuck draft
 *   - awaiting_review → publishing: operator approves → backend POSTs to Publer
 *   - publishing → cancelled: operator cancels mid-flight → backend DELETEs Publer post
 *   - awaiting_review → generating: regenerate endpoint (admin path)
 *   - failed/cancelled → generating: regenerate path (admin recovery)
 *   - published: terminal (Publer confirmed; cancel post-publish would require
 *     direct platform API access which Publer may not expose)
 */
enum InstagramPostStatus: string
{
    case PendingGeneration = 'pending_generation';
    case Generating = 'generating';
    case AwaitingReview = 'awaiting_review';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public const TRANSITIONS = [
        'pending_generation' => ['generating', 'cancelled'],
        'generating' => ['generating', 'awaiting_review', 'failed', 'cancelled'],
        'awaiting_review' => ['publishing', 'generating', 'cancelled'],
        'publishing' => ['published', 'failed', 'cancelled'],
        'failed' => ['generating', 'cancelled'],
        'cancelled' => ['generating'],
        'published' => [],
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }

    /**
     * Success-feed statuses (shown on /admin/instagram-posts calendar view).
     */
    public static function feedStatuses(): array
    {
        return [
            self::Publishing->value,
            self::Published->value,
            self::Cancelled->value,
        ];
    }

    /**
     * Triage-queue statuses (shown on /admin/instagram-queue).
     */
    public static function queueStatuses(): array
    {
        return [
            self::PendingGeneration->value,
            self::Generating->value,
            self::AwaitingReview->value,
            self::Failed->value,
        ];
    }
}
