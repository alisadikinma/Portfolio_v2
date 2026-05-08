<?php

namespace Tests\Unit;

use App\Enums\TiktokPostStatus;
use PHPUnit\Framework\TestCase;

/**
 * FSM adjacency map verification for TiktokPostStatus.
 *
 * Identical state machine to InstagramPostStatus — sibling test asserts the
 * two enums remain in sync. If the spec needs to diverge later, both this test
 * and InstagramPostStatusTransitionsTest must be updated.
 *
 * Updated May 8 for Publer-aware rename:
 *   AwaitingManualPublish → Publishing
 *   PublishedExternally   → Published
 */
class TiktokPostStatusTransitionsTest extends TestCase
{
    public function test_pending_generation_can_advance_to_generating_or_cancelled(): void
    {
        $this->assertTrue(TiktokPostStatus::PendingGeneration->canTransitionTo(TiktokPostStatus::Generating));
        $this->assertTrue(TiktokPostStatus::PendingGeneration->canTransitionTo(TiktokPostStatus::Cancelled));
        $this->assertFalse(TiktokPostStatus::PendingGeneration->canTransitionTo(TiktokPostStatus::AwaitingReview));
        $this->assertFalse(TiktokPostStatus::PendingGeneration->canTransitionTo(TiktokPostStatus::Published));
    }

    public function test_generating_can_self_transition_for_retry(): void
    {
        $this->assertTrue(TiktokPostStatus::Generating->canTransitionTo(TiktokPostStatus::Generating));
    }

    public function test_generating_advances_to_review_or_failed(): void
    {
        $this->assertTrue(TiktokPostStatus::Generating->canTransitionTo(TiktokPostStatus::AwaitingReview));
        $this->assertTrue(TiktokPostStatus::Generating->canTransitionTo(TiktokPostStatus::Failed));
        $this->assertTrue(TiktokPostStatus::Generating->canTransitionTo(TiktokPostStatus::Cancelled));
    }

    public function test_review_advances_to_publishing_or_regenerate(): void
    {
        $this->assertTrue(TiktokPostStatus::AwaitingReview->canTransitionTo(TiktokPostStatus::Publishing));
        $this->assertTrue(TiktokPostStatus::AwaitingReview->canTransitionTo(TiktokPostStatus::Generating));
        $this->assertTrue(TiktokPostStatus::AwaitingReview->canTransitionTo(TiktokPostStatus::Cancelled));
        $this->assertFalse(TiktokPostStatus::AwaitingReview->canTransitionTo(TiktokPostStatus::Published));
    }

    public function test_publishing_advances_to_published_failed_or_cancelled(): void
    {
        $this->assertTrue(TiktokPostStatus::Publishing->canTransitionTo(TiktokPostStatus::Published));
        $this->assertTrue(TiktokPostStatus::Publishing->canTransitionTo(TiktokPostStatus::Failed));
        $this->assertTrue(TiktokPostStatus::Publishing->canTransitionTo(TiktokPostStatus::Cancelled));
        $this->assertFalse(TiktokPostStatus::Publishing->canTransitionTo(TiktokPostStatus::AwaitingReview));
    }

    public function test_failed_can_be_regenerated_or_cancelled(): void
    {
        $this->assertTrue(TiktokPostStatus::Failed->canTransitionTo(TiktokPostStatus::Generating));
        $this->assertTrue(TiktokPostStatus::Failed->canTransitionTo(TiktokPostStatus::Cancelled));
    }

    public function test_cancelled_can_be_regenerated(): void
    {
        $this->assertTrue(TiktokPostStatus::Cancelled->canTransitionTo(TiktokPostStatus::Generating));
        $this->assertFalse(TiktokPostStatus::Cancelled->canTransitionTo(TiktokPostStatus::AwaitingReview));
    }

    public function test_published_is_terminal(): void
    {
        foreach (TiktokPostStatus::cases() as $next) {
            $this->assertFalse(
                TiktokPostStatus::Published->canTransitionTo($next),
                "Published must be terminal — found unexpected transition to {$next->value}"
            );
        }
    }

    public function test_feed_statuses_match_spec(): void
    {
        $this->assertSame(
            [
                TiktokPostStatus::Publishing->value,
                TiktokPostStatus::Published->value,
                TiktokPostStatus::Cancelled->value,
            ],
            TiktokPostStatus::feedStatuses()
        );
    }

    public function test_queue_statuses_match_spec(): void
    {
        $this->assertSame(
            [
                TiktokPostStatus::PendingGeneration->value,
                TiktokPostStatus::Generating->value,
                TiktokPostStatus::AwaitingReview->value,
                TiktokPostStatus::Failed->value,
            ],
            TiktokPostStatus::queueStatuses()
        );
    }

    public function test_transitions_map_covers_every_case(): void
    {
        foreach (TiktokPostStatus::cases() as $case) {
            $this->assertArrayHasKey(
                $case->value,
                TiktokPostStatus::TRANSITIONS,
                "Status {$case->value} missing from TRANSITIONS map"
            );
        }
    }

    public function test_renamed_cases_use_new_string_values(): void
    {
        $allTransitionTargets = [];
        foreach (TiktokPostStatus::TRANSITIONS as $from => $targets) {
            $allTransitionTargets = array_merge($allTransitionTargets, [$from], $targets);
        }
        $allTransitionTargets = array_unique($allTransitionTargets);

        $this->assertNotContains('awaiting_manual_publish', $allTransitionTargets, 'Old ENUM value awaiting_manual_publish must be removed (renamed to publishing)');
        $this->assertNotContains('published_externally', $allTransitionTargets, 'Old ENUM value published_externally must be removed (renamed to published)');
        $this->assertContains('publishing', $allTransitionTargets);
        $this->assertContains('published', $allTransitionTargets);
    }

    public function test_fsm_matches_instagram_sibling(): void
    {
        // Sibling FSMs MUST stay in lockstep. If spec diverges, update this assertion + add platform-specific tests.
        $this->assertSame(
            \App\Enums\InstagramPostStatus::TRANSITIONS,
            TiktokPostStatus::TRANSITIONS,
            'IG and TikTok FSM transition maps drifted apart — verify spec divergence is intentional'
        );
    }
}
