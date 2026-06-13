<?php

namespace Tests\Unit;

use App\Enums\RepurposeJobStatus;
use PHPUnit\Framework\TestCase;

class RepurposeJobStatusTransitionsTest extends TestCase
{
    public function test_legal_forward_transitions_allowed(): void
    {
        $this->assertTrue(RepurposeJobStatus::Received->canTransitionTo(RepurposeJobStatus::Capturing));
        $this->assertTrue(RepurposeJobStatus::Captured->canTransitionTo(RepurposeJobStatus::Extracting));
        $this->assertTrue(RepurposeJobStatus::Rewritten->canTransitionTo(RepurposeJobStatus::Finalizing));
        $this->assertTrue(RepurposeJobStatus::Finalizing->canTransitionTo(RepurposeJobStatus::Drafted));
        // Blog mode forks off `extracted` straight to finalize (skips research+rewrite).
        $this->assertTrue(RepurposeJobStatus::Extracted->canTransitionTo(RepurposeJobStatus::Finalizing));
    }

    public function test_illegal_skips_rejected(): void
    {
        $this->assertFalse(RepurposeJobStatus::Received->canTransitionTo(RepurposeJobStatus::Drafted));
        $this->assertFalse(RepurposeJobStatus::Received->canTransitionTo(RepurposeJobStatus::Finalizing));
        $this->assertFalse(RepurposeJobStatus::Captured->canTransitionTo(RepurposeJobStatus::Drafted));
    }

    public function test_drafted_is_terminal(): void
    {
        $this->assertFalse(RepurposeJobStatus::Drafted->canTransitionTo(RepurposeJobStatus::Capturing));
        $this->assertFalse(RepurposeJobStatus::Drafted->canTransitionTo(RepurposeJobStatus::Failed));
        $this->assertSame([], RepurposeJobStatus::TRANSITIONS['drafted']);
    }

    public function test_every_non_terminal_can_fail(): void
    {
        foreach (RepurposeJobStatus::cases() as $case) {
            if (in_array($case, [RepurposeJobStatus::Drafted, RepurposeJobStatus::Failed], true)) {
                continue;
            }
            $this->assertTrue(
                $case->canTransitionTo(RepurposeJobStatus::Failed),
                "{$case->value} should be able to transition to failed"
            );
        }
    }

    public function test_failed_can_retry_from_step_guard_states(): void
    {
        // Retry resumes a step by re-entering the guard state that step's job
        // accepts (capture@capturing, extract@captured, research@extracted,
        // rewrite@researched, finalize@rewritten).
        $this->assertTrue(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Capturing));
        $this->assertTrue(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Captured));
        $this->assertTrue(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Extracted));
        $this->assertTrue(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Researched));
        $this->assertTrue(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Rewritten));
        // Cannot retry into a non-guard / terminal state.
        $this->assertFalse(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Researching));
        $this->assertFalse(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Drafted));
    }
}
