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
}
