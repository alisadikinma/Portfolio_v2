<?php

namespace Tests\Unit;

use App\Enums\FacebookPostStatus;
use App\Enums\InstagramPostStatus;
use App\Enums\TiktokPostStatus;
use PHPUnit\Framework\TestCase;

/**
 * FSM adjacency map verification for FacebookPostStatus.
 *
 * FB FSM is identical to IG + TikTok siblings — all 3 cross-post platforms
 * share the same lifecycle. This test asserts the lockstep stays intact.
 */
class FacebookPostStatusTransitionsTest extends TestCase
{
    public function test_pending_generation_can_advance_to_generating_or_cancelled(): void
    {
        $this->assertTrue(FacebookPostStatus::PendingGeneration->canTransitionTo(FacebookPostStatus::Generating));
        $this->assertTrue(FacebookPostStatus::PendingGeneration->canTransitionTo(FacebookPostStatus::Cancelled));
        $this->assertFalse(FacebookPostStatus::PendingGeneration->canTransitionTo(FacebookPostStatus::AwaitingReview));
        $this->assertFalse(FacebookPostStatus::PendingGeneration->canTransitionTo(FacebookPostStatus::Published));
    }

    public function test_publishing_advances_to_published_failed_or_cancelled(): void
    {
        $this->assertTrue(FacebookPostStatus::Publishing->canTransitionTo(FacebookPostStatus::Published));
        $this->assertTrue(FacebookPostStatus::Publishing->canTransitionTo(FacebookPostStatus::Failed));
        $this->assertTrue(FacebookPostStatus::Publishing->canTransitionTo(FacebookPostStatus::Cancelled));
        $this->assertFalse(FacebookPostStatus::Publishing->canTransitionTo(FacebookPostStatus::AwaitingReview));
    }

    public function test_published_is_terminal(): void
    {
        foreach (FacebookPostStatus::cases() as $next) {
            $this->assertFalse(
                FacebookPostStatus::Published->canTransitionTo($next),
                "Published must be terminal — found unexpected transition to {$next->value}"
            );
        }
    }

    public function test_feed_statuses_match_spec(): void
    {
        $this->assertSame(
            [
                FacebookPostStatus::Publishing->value,
                FacebookPostStatus::Published->value,
                FacebookPostStatus::Cancelled->value,
            ],
            FacebookPostStatus::feedStatuses()
        );
    }

    public function test_queue_statuses_match_spec(): void
    {
        $this->assertSame(
            [
                FacebookPostStatus::PendingGeneration->value,
                FacebookPostStatus::Generating->value,
                FacebookPostStatus::AwaitingReview->value,
                FacebookPostStatus::Failed->value,
            ],
            FacebookPostStatus::queueStatuses()
        );
    }

    public function test_transitions_map_covers_every_case(): void
    {
        foreach (FacebookPostStatus::cases() as $case) {
            $this->assertArrayHasKey(
                $case->value,
                FacebookPostStatus::TRANSITIONS,
                "Status {$case->value} missing from TRANSITIONS map"
            );
        }
    }

    public function test_fsm_matches_instagram_sibling(): void
    {
        // All 3 cross-post platforms (FB + IG + TikTok) MUST share same FSM.
        // If spec diverges later, update assertion + add platform-specific tests.
        $this->assertSame(
            InstagramPostStatus::TRANSITIONS,
            FacebookPostStatus::TRANSITIONS,
            'FB and IG FSM transition maps drifted apart — verify spec divergence is intentional'
        );
    }

    public function test_fsm_matches_tiktok_sibling(): void
    {
        $this->assertSame(
            TiktokPostStatus::TRANSITIONS,
            FacebookPostStatus::TRANSITIONS,
            'FB and TikTok FSM transition maps drifted apart — verify spec divergence is intentional'
        );
    }
}
