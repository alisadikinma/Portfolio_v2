<?php

namespace Tests\Unit;

use App\Enums\InstagramPostStatus;
use PHPUnit\Framework\TestCase;

/**
 * FSM adjacency map verification for InstagramPostStatus.
 *
 * Pure unit test — no Laravel boot, no DB. Asserts the enum's transition
 * graph matches the design spec at
 * docs/plans/2026-05-08-cross-post-publer-integration.md § FSM.
 *
 * Updated May 8 for Publer-aware rename:
 *   AwaitingManualPublish → Publishing
 *   PublishedExternally   → Published
 */
class InstagramPostStatusTransitionsTest extends TestCase
{
    public function test_pending_generation_can_advance_to_generating_or_cancelled(): void
    {
        $this->assertTrue(InstagramPostStatus::PendingGeneration->canTransitionTo(InstagramPostStatus::Generating));
        $this->assertTrue(InstagramPostStatus::PendingGeneration->canTransitionTo(InstagramPostStatus::Cancelled));
        // Direct jumps forbidden:
        $this->assertFalse(InstagramPostStatus::PendingGeneration->canTransitionTo(InstagramPostStatus::AwaitingReview));
        $this->assertFalse(InstagramPostStatus::PendingGeneration->canTransitionTo(InstagramPostStatus::Published));
    }

    public function test_generating_can_self_transition_for_retry(): void
    {
        $this->assertTrue(InstagramPostStatus::Generating->canTransitionTo(InstagramPostStatus::Generating));
    }

    public function test_generating_advances_to_review_or_failed(): void
    {
        $this->assertTrue(InstagramPostStatus::Generating->canTransitionTo(InstagramPostStatus::AwaitingReview));
        $this->assertTrue(InstagramPostStatus::Generating->canTransitionTo(InstagramPostStatus::Failed));
        $this->assertTrue(InstagramPostStatus::Generating->canTransitionTo(InstagramPostStatus::Cancelled));
    }

    public function test_review_advances_to_publishing_or_regenerate(): void
    {
        $this->assertTrue(InstagramPostStatus::AwaitingReview->canTransitionTo(InstagramPostStatus::Publishing));
        $this->assertTrue(InstagramPostStatus::AwaitingReview->canTransitionTo(InstagramPostStatus::Generating));
        $this->assertTrue(InstagramPostStatus::AwaitingReview->canTransitionTo(InstagramPostStatus::Cancelled));
        // Cannot skip the publishing step (no direct review → published):
        $this->assertFalse(InstagramPostStatus::AwaitingReview->canTransitionTo(InstagramPostStatus::Published));
    }

    public function test_publishing_advances_to_published_failed_or_cancelled(): void
    {
        $this->assertTrue(InstagramPostStatus::Publishing->canTransitionTo(InstagramPostStatus::Published));
        $this->assertTrue(InstagramPostStatus::Publishing->canTransitionTo(InstagramPostStatus::Failed));
        $this->assertTrue(InstagramPostStatus::Publishing->canTransitionTo(InstagramPostStatus::Cancelled));
        // Cannot regress to review without going through cancel + regenerate first:
        $this->assertFalse(InstagramPostStatus::Publishing->canTransitionTo(InstagramPostStatus::AwaitingReview));
    }

    public function test_failed_can_be_regenerated_or_cancelled(): void
    {
        $this->assertTrue(InstagramPostStatus::Failed->canTransitionTo(InstagramPostStatus::Generating));
        $this->assertTrue(InstagramPostStatus::Failed->canTransitionTo(InstagramPostStatus::Cancelled));
    }

    public function test_cancelled_can_be_regenerated(): void
    {
        $this->assertTrue(InstagramPostStatus::Cancelled->canTransitionTo(InstagramPostStatus::Generating));
        // Cancelled is NOT a black hole — but cannot resurrect to AwaitingReview directly:
        $this->assertFalse(InstagramPostStatus::Cancelled->canTransitionTo(InstagramPostStatus::AwaitingReview));
    }

    public function test_published_is_terminal(): void
    {
        foreach (InstagramPostStatus::cases() as $next) {
            $this->assertFalse(
                InstagramPostStatus::Published->canTransitionTo($next),
                "Published must be terminal — found unexpected transition to {$next->value}"
            );
        }
    }

    public function test_feed_statuses_match_spec(): void
    {
        $this->assertSame(
            [
                InstagramPostStatus::Publishing->value,
                InstagramPostStatus::Published->value,
                InstagramPostStatus::Cancelled->value,
            ],
            InstagramPostStatus::feedStatuses()
        );
    }

    public function test_queue_statuses_match_spec(): void
    {
        $this->assertSame(
            [
                InstagramPostStatus::PendingGeneration->value,
                InstagramPostStatus::Generating->value,
                InstagramPostStatus::AwaitingReview->value,
                InstagramPostStatus::Failed->value,
            ],
            InstagramPostStatus::queueStatuses()
        );
    }

    public function test_transitions_map_covers_every_case(): void
    {
        // Every enum case must have an entry in TRANSITIONS (even if empty for terminal)
        foreach (InstagramPostStatus::cases() as $case) {
            $this->assertArrayHasKey(
                $case->value,
                InstagramPostStatus::TRANSITIONS,
                "Status {$case->value} missing from TRANSITIONS map"
            );
        }
    }

    public function test_renamed_cases_use_new_string_values(): void
    {
        // Defensive: ensure no lingering references to old enum values
        $allTransitionTargets = [];
        foreach (InstagramPostStatus::TRANSITIONS as $from => $targets) {
            $allTransitionTargets = array_merge($allTransitionTargets, [$from], $targets);
        }
        $allTransitionTargets = array_unique($allTransitionTargets);

        $this->assertNotContains('awaiting_manual_publish', $allTransitionTargets, 'Old ENUM value awaiting_manual_publish must be removed (renamed to publishing)');
        $this->assertNotContains('published_externally', $allTransitionTargets, 'Old ENUM value published_externally must be removed (renamed to published)');
        $this->assertContains('publishing', $allTransitionTargets);
        $this->assertContains('published', $allTransitionTargets);
    }
}
