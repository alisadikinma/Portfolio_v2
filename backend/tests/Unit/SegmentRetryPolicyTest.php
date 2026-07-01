<?php

namespace Tests\Unit;

use App\Services\ImageGenerationService;
use Tests\TestCase;

/**
 * Pure-logic coverage for the image-completion gate + retry policy (GEO
 * publish-and-forget fix). The advance predicate is the single source both gate
 * sites use (webhook + poller); the backoff + cap are env-tunable.
 */
class SegmentRetryPolicyTest extends TestCase
{
    public function test_all_done_segments_can_advance(): void
    {
        $prompts = [['status' => 'done'], ['status' => 'done'], ['status' => 'done']];
        $this->assertTrue(ImageGenerationService::segmentsResolvedForAdvance($prompts));
    }

    public function test_done_plus_skipped_can_advance(): void
    {
        // skipped is an explicit human decision → advance-eligible.
        $prompts = [['status' => 'done'], ['status' => 'skipped'], ['status' => 'done']];
        $this->assertTrue(ImageGenerationService::segmentsResolvedForAdvance($prompts));
    }

    public function test_any_failed_segment_blocks_advance(): void
    {
        $prompts = [['status' => 'done'], ['status' => 'done'], ['status' => 'failed']];
        $this->assertFalse(ImageGenerationService::segmentsResolvedForAdvance($prompts));
    }

    public function test_failed_with_terminal_at_still_blocks(): void
    {
        // Terminal failure is STILL a failure — must not advance (the old bug).
        $prompts = [['status' => 'done'], ['status' => 'failed', 'terminal_at' => '2026-06-09T00:00:00+00:00']];
        $this->assertFalse(ImageGenerationService::segmentsResolvedForAdvance($prompts));
    }

    public function test_all_skipped_no_done_does_not_advance(): void
    {
        // Need at least one rendered image.
        $prompts = [['status' => 'skipped'], ['status' => 'skipped']];
        $this->assertFalse(ImageGenerationService::segmentsResolvedForAdvance($prompts));
    }

    public function test_empty_prompts_does_not_advance(): void
    {
        $this->assertFalse(ImageGenerationService::segmentsResolvedForAdvance([]));
    }

    public function test_retry_backoff_is_exponential_and_capped(): void
    {
        $this->assertSame(60, ImageGenerationService::retryBackoffSeconds(1));
        $this->assertSame(120, ImageGenerationService::retryBackoffSeconds(2));
        $this->assertSame(240, ImageGenerationService::retryBackoffSeconds(3));
        $this->assertSame(480, ImageGenerationService::retryBackoffSeconds(4));
        // Capped at 15 min.
        $this->assertSame(900, ImageGenerationService::retryBackoffSeconds(5));
        $this->assertSame(900, ImageGenerationService::retryBackoffSeconds(6));
    }

    public function test_max_segment_attempts_reads_config_default_3(): void
    {
        // Default caps at 3 — align with the admin UI "Attempt N/3" badge so
        // auto-retry stops at 3 instead of grinding to 6 (the "4/3" bug).
        config(['services.article_generation.image_segment_max_attempts' => null]);
        $svc = app(ImageGenerationService::class);
        $this->assertSame(3, $svc->maxSegmentAttempts());

        config(['services.article_generation.image_segment_max_attempts' => 6]);
        $this->assertSame(6, $svc->maxSegmentAttempts());
    }
}
