<?php

namespace Tests\Unit;

use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use App\Services\PipelineGuard;
use Mockery;
use Tests\TestCase;

/**
 * Phase B — skip-linkedin-gen plan (2026-06-09).
 * Unit tests for the pure LinkedInGenerationService::buildCarouselGenPrompt().
 * Asserts the full blog article body is embedded inline (labeled PRIMARY) when
 * provided, omitted when null/empty, and the --blog-source URL flag is always
 * present. No DB / no SSH.
 */
class CarouselGenInlineContentPromptTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): LinkedInGenerationService
    {
        $guard = Mockery::mock(PipelineGuard::class);
        return new LinkedInGenerationService($guard, new CarouselGenOutputAdapter());
    }

    public function test_prompt_embeds_article_body_when_content_present(): void
    {
        $svc = $this->makeService();
        $body = 'OpenAI next model is being designed by AI. Masayoshi Son told CNBC.';

        $prompt = $svc->buildCarouselGenPrompt(
            ['hook_framework' => 'contrarian'],
            'https://alisadikinma.com/blog/ai-mendesain-model-openai',
            $body
        );

        $this->assertStringContainsString('/carousel-gen --pipeline', $prompt);
        $this->assertStringContainsString('--blog-source=', $prompt); // URL flag stays
        $this->assertStringContainsString('SOURCE ARTICLE CONTENT', $prompt);
        $this->assertStringContainsString($body, $prompt); // full body embedded
    }

    public function test_prompt_omits_content_block_when_null(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt(
            [],
            'https://alisadikinma.com/blog/x',
            null
        );

        $this->assertStringContainsString('/carousel-gen --pipeline', $prompt);
        $this->assertStringContainsString('--blog-source=', $prompt);
        $this->assertStringNotContainsString('SOURCE ARTICLE CONTENT', $prompt);
    }

    public function test_prompt_omits_content_block_when_empty_or_whitespace(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://alisadikinma.com/blog/x', "   \n  ");

        $this->assertStringNotContainsString('SOURCE ARTICLE CONTENT', $prompt);
    }

    public function test_prompt_always_emits_sketchnote_style(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://alisadikinma.com/blog/x', null);

        $this->assertStringContainsString('--style=sketchnote', $prompt);
    }

    public function test_repurpose_draft_drops_foreshadow_with_free_narrative(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://alisadikinma.com/blog/x', null, true);

        $this->assertStringContainsString('--narrative=free', $prompt);
        $this->assertStringNotContainsString('--narrative=5act', $prompt);
    }

    public function test_non_repurpose_draft_keeps_5act_narrative(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://alisadikinma.com/blog/x', null, false);

        $this->assertStringContainsString('--narrative=5act', $prompt);
        $this->assertStringNotContainsString('--narrative=free', $prompt);
    }

    public function test_sketchnote_appends_knowledge_first_infographic_directive(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://alisadikinma.com/blog/x', null);

        // The sketchnote directive's knowledge-first intent (the block was
        // reworded to "BLUE-BRAND HYBRID" — assert a stable phrase that survives).
        $this->assertStringContainsString('Maximize knowledge density', $prompt);
    }

    public function test_cinematic_style_omits_knowledge_directive_and_emits_its_flag(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://alisadikinma.com/blog/x', null, false, 'cinematic');

        $this->assertStringContainsString('--style=cinematic', $prompt);
        $this->assertStringNotContainsString('KNOWLEDGE-FIRST INFOGRAPHIC', $prompt);
    }

    // --- Source-mirrored slide count (2026-06-18) ----------------------------

    public function test_non_repurpose_uses_brief_heuristic_target_slides(): void
    {
        $svc = $this->makeService();

        // Non-repurpose: ignores any sourceSlideCount, uses the 7-default heuristic.
        $prompt = $svc->buildCarouselGenPrompt([], 'https://alisadikinma.com/blog/x', null, false, 'sketchnote', 12);

        $this->assertStringContainsString('--target-slides=7', $prompt);
    }

    public function test_repurpose_mirrors_source_slide_count(): void
    {
        $svc = $this->makeService();

        // Cursor case: 5-slide source → 5-slide carousel (not the hard 7).
        $prompt = $svc->buildCarouselGenPrompt([], 'https://www.instagram.com/p/x/', null, true, 'sketchnote', 5);

        $this->assertStringContainsString('--target-slides=5', $prompt);
    }

    public function test_repurpose_clamps_to_configured_max(): void
    {
        config(['carousel-gen.max_repurpose_slides' => 12]);
        $svc = $this->makeService();

        // 20-frame source (legacy over-grab / huge carousel) clamps to the ceiling.
        $prompt = $svc->buildCarouselGenPrompt([], 'https://www.instagram.com/p/x/', null, true, 'sketchnote', 20);

        $this->assertStringContainsString('--target-slides=12', $prompt);
    }

    public function test_repurpose_floors_at_three(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://www.instagram.com/p/x/', null, true, 'sketchnote', 1);

        $this->assertStringContainsString('--target-slides=3', $prompt);
    }

    public function test_repurpose_without_source_count_falls_back_to_heuristic(): void
    {
        $svc = $this->makeService();

        $prompt = $svc->buildCarouselGenPrompt([], 'https://www.instagram.com/p/x/', null, true, 'sketchnote', null);

        $this->assertStringContainsString('--target-slides=7', $prompt);
    }
}
