<?php

namespace App\Enums;

/**
 * FSM for cross-post TikTok drafts (May 7 ship + May 8 Publer-aware rename).
 *
 * Identical state machine to InstagramPostStatus — sibling enum kept in
 * lockstep. Two enum classes (vs one shared) preserve type-safety per-platform
 * and let HasStatusTransitions::statusEnumClass() route correctly without a
 * discriminator column.
 *
 * Rename history (May 8): awaiting_manual_publish → publishing,
 * published_externally → published. See InstagramPostStatus docblock for
 * full rationale.
 */
enum TiktokPostStatus: string
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
