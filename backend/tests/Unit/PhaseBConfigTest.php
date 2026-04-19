<?php

namespace Tests\Unit;

use Tests\TestCase;

class PhaseBConfigTest extends TestCase
{
    public function test_phase_b_tier_models_and_refs_have_expected_defaults(): void
    {
        $this->assertSame(
            'opus',
            config('services.article_generation.model_research_deep'),
            'model_research_deep should default to opus — reserved for virality_score >= 70.'
        );

        $this->assertSame(
            'sonnet',
            config('services.article_generation.model_research_quick'),
            'model_research_quick should default to sonnet for quick research tier.'
        );

        $this->assertSame(
            'sonnet',
            config('services.article_generation.model_strategy_outline'),
            'model_strategy_outline should always default to sonnet (never opus).'
        );

        $refsResearch = config('services.article_generation.refs_research');
        $this->assertIsString(
            $refsResearch,
            'refs_research must resolve to a string (empty string acceptable as placeholder default).'
        );

        $refsStrategyOutline = config('services.article_generation.refs_strategy_outline');
        $this->assertIsString(
            $refsStrategyOutline,
            'refs_strategy_outline must resolve to a string (empty string acceptable as placeholder default).'
        );

        $this->assertFalse(
            config('services.article_generation.skill_split_enabled'),
            'skill_split_enabled should default to false for safe rollout during Phase B.'
        );

        $this->assertTrue(
            config('services.article_generation.deep_research_enabled'),
            'deep_research_enabled should default to true — kill-switch is off by default.'
        );
    }

    public function test_phase_b_flags_support_runtime_override(): void
    {
        config(['services.article_generation.skill_split_enabled' => true]);
        $this->assertTrue(
            config('services.article_generation.skill_split_enabled'),
            'Runtime config override must flip skill_split_enabled to true (needed for Mockery in later phases).'
        );

        config(['services.article_generation.skill_split_enabled' => false]);
        $this->assertFalse(
            config('services.article_generation.skill_split_enabled'),
            'Runtime config override must flip skill_split_enabled back to false.'
        );

        config(['services.article_generation.deep_research_enabled' => false]);
        $this->assertFalse(
            config('services.article_generation.deep_research_enabled'),
            'Runtime override must flip deep_research_enabled off (kill-switch scenario).'
        );
    }
}
