<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use Tests\TestCase;

/**
 * Phase B.3: ArticleGenerationService::resolveResearchTier() + resolveResearchModel()
 *
 * Pure resolver logic — no DB roundtrip required for ContentIdea (unsaved models
 * still expose fillable attributes). Extends Laravel's TestCase so config()
 * helper is bootstrapped for model resolution.
 */
class ArticleGenerationTierResolverTest extends TestCase
{
    private ArticleGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ArticleGenerationService();
    }

    // ==================================================================
    // resolveResearchTier(ContentIdea $idea): string
    // ==================================================================

    public function test_override_quick_forces_quick_regardless_of_score(): void
    {
        $idea = new ContentIdea([
            'research_tier_override' => 'quick',
            'virality_score' => 95, // high score would normally pick deep
        ]);

        $this->assertSame('quick', $this->service->resolveResearchTier($idea));
    }

    public function test_override_deep_forces_deep_regardless_of_score(): void
    {
        $idea = new ContentIdea([
            'research_tier_override' => 'deep',
            'virality_score' => 10, // low score would normally pick quick
        ]);

        $this->assertSame('deep', $this->service->resolveResearchTier($idea));
    }

    public function test_auto_with_high_virality_picks_deep(): void
    {
        $idea = new ContentIdea([
            'research_tier_override' => 'auto',
            'virality_score' => 70, // exactly at the boundary
        ]);

        $this->assertSame('deep', $this->service->resolveResearchTier($idea));

        $idea2 = new ContentIdea([
            'research_tier_override' => 'auto',
            'virality_score' => 85,
        ]);
        $this->assertSame('deep', $this->service->resolveResearchTier($idea2));
    }

    public function test_auto_with_low_virality_picks_quick(): void
    {
        $idea = new ContentIdea([
            'research_tier_override' => 'auto',
            'virality_score' => 69, // one below boundary
        ]);

        $this->assertSame('quick', $this->service->resolveResearchTier($idea));
    }

    public function test_auto_with_null_virality_picks_quick_safe_default(): void
    {
        $idea = new ContentIdea([
            'research_tier_override' => 'auto',
            'virality_score' => null,
        ]);

        $this->assertSame('quick', $this->service->resolveResearchTier($idea));
    }

    public function test_kill_switch_forces_quick_even_on_high_score(): void
    {
        config(['services.article_generation.deep_research_enabled' => false]);

        $idea = new ContentIdea([
            'research_tier_override' => 'auto',
            'virality_score' => 95, // high score — but kill switch overrides
        ]);

        $this->assertSame('quick', $this->service->resolveResearchTier($idea));
    }

    // ==================================================================
    // resolveResearchModel(string $tier): string
    // ==================================================================

    public function test_resolve_model_deep_returns_configured_opus(): void
    {
        config(['services.article_generation.model_research_deep' => 'opus']);

        $this->assertSame('opus', $this->service->resolveResearchModel('deep'));
    }

    public function test_resolve_model_quick_returns_configured_sonnet(): void
    {
        config(['services.article_generation.model_research_quick' => 'sonnet']);

        $this->assertSame('sonnet', $this->service->resolveResearchModel('quick'));
    }

    public function test_resolve_model_rejects_invalid_tier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("resolveResearchModel expects 'quick' or 'deep'");

        $this->service->resolveResearchModel('auto');
    }
}
