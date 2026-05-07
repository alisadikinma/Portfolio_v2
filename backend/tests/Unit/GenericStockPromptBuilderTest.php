<?php

namespace Tests\Unit;

use App\Services\LinkedInCarouselImageService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase E verification: ensure the tier-2 generic-stock prompt builder is
 * layout-aware and produces a prompt that won't trigger NB2 content policy
 * (no proper nouns, no specific persons/brands/landmarks).
 */
class GenericStockPromptBuilderTest extends TestCase
{
    private function buildPrompt(string $layoutHint, string $copy = ''): string
    {
        $service = app(LinkedInCarouselImageService::class);
        $reflection = new ReflectionMethod($service, 'buildGenericStockPrompt');
        $reflection->setAccessible(true);
        return $reflection->invoke($service, $layoutHint, $copy);
    }

    public function test_cover_layout_uses_hero_composition(): void
    {
        $prompt = $this->buildPrompt('cover', 'Some scene context');
        $this->assertStringContainsString('Hero composition', $prompt);
        $this->assertStringContainsString('rule of thirds', $prompt);
    }

    public function test_cta_layout_leaves_breathing_room_for_text(): void
    {
        $prompt = $this->buildPrompt('cta');
        $this->assertStringContainsString('breathing room for text overlay', $prompt);
    }

    public function test_data_point_layout_uses_infographic_style(): void
    {
        $prompt = $this->buildPrompt('data_point');
        $this->assertStringContainsString('infographic-style', $prompt);
    }

    public function test_human_fingerprint_layout_uses_abstract_silhouette(): void
    {
        $prompt = $this->buildPrompt('human_fingerprint');
        $this->assertStringContainsString('abstract human silhouette', $prompt);
    }

    public function test_body_layout_uses_abstract_silhouette(): void
    {
        $prompt = $this->buildPrompt('body');
        $this->assertStringContainsString('abstract human silhouette', $prompt);
    }

    public function test_unknown_layout_falls_back_to_base_template(): void
    {
        $prompt = $this->buildPrompt('totally_made_up_layout');
        $this->assertStringContainsString('Professional studio photograph', $prompt);
        $this->assertStringContainsString('no recognizable people', $prompt);
    }

    public function test_every_layout_forbids_recognizable_entities(): void
    {
        foreach (['cover', 'cta', 'data_point', 'human_fingerprint', 'body', 'unknown'] as $layout) {
            $prompt = $this->buildPrompt($layout);
            $this->assertStringContainsString(
                'no recognizable people, no brands, no logos',
                $prompt,
                "Layout '{$layout}' must forbid recognizable entities to be NB2-safe"
            );
        }
    }

    public function test_proper_noun_bigrams_in_copy_are_redacted(): void
    {
        // Cover layout interpolates copy into the prompt; bigram redaction
        // strips two-word proper nouns ("Sam Altman", "Mark Zuckerberg") to
        // "..." before reaching GeminiGen. Single-token uppercase tokens
        // are intentionally NOT redacted (too many false positives — "AI",
        // "Tuesday", "January" all look like proper nouns to a naive regex).
        // Single-brand leakage is acceptable at tier 2 since the prompt
        // already declares "no brands, no logos" universally.
        $prompt = $this->buildPrompt('cover', 'Sam Altman announces new product at conference');
        $this->assertStringNotContainsString('Sam Altman', $prompt);
        $this->assertStringContainsString('...', $prompt);
    }

    public function test_aspect_ratio_is_4_to_5_for_linkedin_carousel(): void
    {
        $prompt = $this->buildPrompt('cover');
        $this->assertStringContainsString('4:5 aspect ratio', $prompt);
    }
}
