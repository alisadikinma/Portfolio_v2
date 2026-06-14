<?php

namespace Tests\Unit;

use App\Jobs\GenerateRebrandAssets;
use App\Services\VideoGenPromptDegrader;
use PHPUnit\Framework\TestCase;

/**
 * Part A4 — static, LLM-free per-retry prompt degradation for the Veo bookend
 * re-dispatch. The degrade map turns a recurring GeminiGen error into a tweaked
 * prompt so the SAME failure stops recurring within the 3-retry budget.
 */
class VideoGenPromptDegraderTest extends TestCase
{
    private VideoGenPromptDegrader $degrader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->degrader = new VideoGenPromptDegrader();
    }

    public function test_audio_filtered_simplifies_audio_bed(): void
    {
        $base = GenerateRebrandAssets::VEO_PROMPT_HOOK;
        $out = $this->degrader->degradeVeo($base, 'audio_filtered', 1);

        // The original multi-clause Audio: line is replaced with a barer single
        // positive-ambiance bed, and the Ambient UI-drift line is dropped (less for
        // the audio model to react to). The exact original Audio clause is gone.
        $this->assertStringNotContainsString('floating side UI icons drift', $out);
        $this->assertStringContainsString('Audio:', $out);
        $this->assertNotSame($base, $out);
    }

    public function test_transient_returns_prompt_unchanged(): void
    {
        // Infra/timeout failure is not a prompt fault → retry the SAME prompt.
        $base = GenerateRebrandAssets::VEO_PROMPT_CTA;
        $this->assertSame($base, $this->degrader->degradeVeo($base, 'transient', 1));
    }

    public function test_unknown_escalates_with_retry_number(): void
    {
        $base = GenerateRebrandAssets::VEO_PROMPT_HOOK;
        $r1 = $this->degrader->degradeVeo($base, 'unknown', 1);
        $r3 = $this->degrader->degradeVeo($base, 'unknown', 3);

        // retry 1 leaves it close to base; retry 3 strips motion toward near-still.
        $this->assertSame($base, $r1);
        $this->assertNotSame($base, $r3);
        $this->assertStringContainsString('near-still', strtolower($r3));
    }

    public function test_audio_filtered_retry3_also_reduces_motion(): void
    {
        $base = GenerateRebrandAssets::VEO_PROMPT_HOOK;
        $out = $this->degrader->degradeVeo($base, 'audio_filtered', 3);
        $this->assertStringContainsString('near-still', strtolower($out));
    }

    public function test_learned_constraints_append_point_is_empty_for_now(): void
    {
        // Part B append point must contribute nothing until the overlay ships.
        $this->assertSame('', $this->degrader->activeLearnedConstraints('veo'));
    }
}
