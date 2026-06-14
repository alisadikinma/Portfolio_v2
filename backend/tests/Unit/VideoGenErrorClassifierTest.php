<?php

namespace Tests\Unit;

use App\Services\VideoGenErrorClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Part A1 — deterministic GeminiGen asset-error → class map (LLM-free).
 * Drives the error-aware retry degradation in PollRebrandAssets.
 */
class VideoGenErrorClassifierTest extends TestCase
{
    private function classify(?string $reason): string
    {
        return (new VideoGenErrorClassifier())->classify($reason);
    }

    public function test_audio_filtered_class(): void
    {
        $this->assertSame('audio_filtered', $this->classify('PUBLIC_ERROR_AUDIO_FILTERED'));
        $this->assertSame('audio_filtered', $this->classify('Audio generation failed for this clip'));
    }

    public function test_prominent_people_class(): void
    {
        $this->assertSame('prominent_people', $this->classify('PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'));
        $this->assertSame('prominent_people', $this->classify('We do not allow uploading images of prominent people'));
    }

    public function test_content_policy_class(): void
    {
        $this->assertSame('content_policy', $this->classify('Blocked by safety filter'));
        $this->assertSame('content_policy', $this->classify('violates content policy'));
    }

    public function test_transient_class(): void
    {
        $this->assertSame('transient', $this->classify('poll failed'));
        $this->assertSame('transient', $this->classify('render exceeded stuck window (stuck 16min)'));
        $this->assertSame('transient', $this->classify('Download/crop of finished Veo clip failed — see logs.'));
        $this->assertSame('transient', $this->classify('cURL error 28: Operation timed out'));
    }

    public function test_null_or_empty_is_transient(): void
    {
        // No error string = not a prompt fault → retry as-is (transient).
        $this->assertSame('transient', $this->classify(null));
        $this->assertSame('transient', $this->classify(''));
        $this->assertSame('transient', $this->classify('   '));
    }

    public function test_unrecognized_is_unknown(): void
    {
        $this->assertSame('unknown', $this->classify('some brand new failure nobody has seen'));
    }

    public function test_prominent_people_takes_precedence_over_content_policy(): void
    {
        // A refusal mentioning both → the more specific figure class wins (it has a
        // dedicated figure-drop recovery the generic content_policy lacks).
        $this->assertSame('prominent_people', $this->classify('content policy: prominent people not allowed'));
    }
}
