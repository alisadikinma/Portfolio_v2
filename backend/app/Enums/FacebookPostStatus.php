<?php

namespace App\Enums;

/**
 * FSM for cross-post Facebook Page drafts (May 8, 2026 cross-post pipeline).
 *
 * Identical state machine to InstagramPostStatus + TiktokPostStatus — sibling
 * enum kept in lockstep across all 3 cross-post platforms. Two enum classes
 * (vs one shared) preserve type-safety per-platform and let
 * HasStatusTransitions::statusEnumClass() route correctly without a
 * discriminator column.
 *
 * Differences from siblings live on the model + schema:
 *   - facebook_posts has `format ENUM('text','carousel')` discriminator
 *     (FB receives both text-format and carousel-format LinkedIn posts)
 *   - facebook_posts has `link_url VARCHAR(500)` for text-format unfurl
 */
enum FacebookPostStatus: string
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
