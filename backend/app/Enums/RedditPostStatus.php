<?php

namespace App\Enums;

/**
 * FSM for cross-post Reddit drafts (2026-06-16 — 4th Zernio platform).
 *
 * Mirrors ThreadsPostStatus 1:1 (same carousel-sibling shape). 7 states:
 * 5 functional + 2 terminal.
 */
enum RedditPostStatus: string
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

    public static function feedStatuses(): array
    {
        return [
            self::Publishing->value,
            self::Published->value,
            self::Cancelled->value,
        ];
    }

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
