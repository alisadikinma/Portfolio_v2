<?php

namespace Tests\Unit;

use App\Enums\RepurposeJobStatus;
use PHPUnit\Framework\TestCase;

/**
 * FSM additions for the video_rebrand mode (3rd repurpose mode).
 * video_rebrand branches OFF `extracted` (skips researching/rewriting — it only
 * re-skins chrome, it does not rewrite claims):
 *   extracted → generating_assets → assets_ready → compositing → composed → finalizing → drafted
 * See docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase A.
 */
class RepurposeJobStatusVideoTransitionsTest extends TestCase
{
    public function test_video_branch_forks_off_extracted(): void
    {
        // blog/carousel keep the researching branch...
        $this->assertTrue(RepurposeJobStatus::Extracted->canTransitionTo(RepurposeJobStatus::Researching));
        // ...video_rebrand forks to asset generation instead.
        $this->assertTrue(RepurposeJobStatus::Extracted->canTransitionTo(RepurposeJobStatus::GeneratingAssets));
    }

    public function test_video_forward_chain(): void
    {
        $this->assertTrue(RepurposeJobStatus::GeneratingAssets->canTransitionTo(RepurposeJobStatus::AssetsReady));
        $this->assertTrue(RepurposeJobStatus::AssetsReady->canTransitionTo(RepurposeJobStatus::Compositing));
        $this->assertTrue(RepurposeJobStatus::Compositing->canTransitionTo(RepurposeJobStatus::Composed));
        // rejoins the shared finalize tail
        $this->assertTrue(RepurposeJobStatus::Composed->canTransitionTo(RepurposeJobStatus::Finalizing));
        $this->assertTrue(RepurposeJobStatus::Finalizing->canTransitionTo(RepurposeJobStatus::Drafted));
    }

    public function test_generating_assets_can_bounce_back_to_extracted_for_recovery(): void
    {
        // PollRebrandAssets::recover() bounces a stuck job generating_assets →
        // extracted so GenerateRebrandAssets re-runs the failed bookends. Without
        // this edge the recovery transition throws and crashes the whole cron.
        $this->assertTrue(RepurposeJobStatus::GeneratingAssets->canTransitionTo(RepurposeJobStatus::Extracted));
    }

    public function test_video_states_can_fail(): void
    {
        $this->assertTrue(RepurposeJobStatus::GeneratingAssets->canTransitionTo(RepurposeJobStatus::Failed));
        $this->assertTrue(RepurposeJobStatus::AssetsReady->canTransitionTo(RepurposeJobStatus::Failed));
        $this->assertTrue(RepurposeJobStatus::Compositing->canTransitionTo(RepurposeJobStatus::Failed));
        $this->assertTrue(RepurposeJobStatus::Composed->canTransitionTo(RepurposeJobStatus::Failed));
    }

    public function test_failed_retries_resume_at_video_guard_states(): void
    {
        // Retry resumes a step at the guard state its job accepts:
        // GenerateRebrandAssets@extracted, ComposeVideoCarousel@assets_ready,
        // FinalizeRepurpose(video)@composed.
        $this->assertTrue(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::AssetsReady));
        $this->assertTrue(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Composed));
        // in-progress states are NOT retry entrypoints (no job guards on them as entry)
        $this->assertFalse(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::GeneratingAssets));
        $this->assertFalse(RepurposeJobStatus::Failed->canTransitionTo(RepurposeJobStatus::Compositing));
    }

    public function test_illegal_video_skips_rejected(): void
    {
        $this->assertFalse(RepurposeJobStatus::Extracted->canTransitionTo(RepurposeJobStatus::Compositing));
        $this->assertFalse(RepurposeJobStatus::GeneratingAssets->canTransitionTo(RepurposeJobStatus::Composed));
        $this->assertFalse(RepurposeJobStatus::AssetsReady->canTransitionTo(RepurposeJobStatus::Drafted));
    }

    public function test_existing_blog_carousel_chain_unchanged(): void
    {
        // regression: the original linear chain still holds
        $this->assertTrue(RepurposeJobStatus::Received->canTransitionTo(RepurposeJobStatus::Capturing));
        $this->assertTrue(RepurposeJobStatus::Researched->canTransitionTo(RepurposeJobStatus::Rewriting));
        $this->assertFalse(RepurposeJobStatus::Received->canTransitionTo(RepurposeJobStatus::Drafted));
    }
}
