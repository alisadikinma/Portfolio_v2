<?php

namespace Tests\Unit;

use Tests\TestCase;

class ArticleGenerationConfigTest extends TestCase
{
    public function test_use_score_phase_defaults_to_false(): void
    {
        $value = config('services.article_generation.use_score_phase');

        $this->assertFalse(
            $value,
            'ARTICLE_GEN_USE_SCORE_PHASE should default to false — Phase C default is mechanical-only.'
        );
    }

    public function test_use_score_phase_can_be_toggled_via_config(): void
    {
        config(['services.article_generation.use_score_phase' => true]);
        $this->assertTrue(config('services.article_generation.use_score_phase'));

        config(['services.article_generation.use_score_phase' => false]);
        $this->assertFalse(config('services.article_generation.use_score_phase'));
    }

    public function test_use_score_phase_sits_alongside_existing_phase_flags(): void
    {
        $generation = config('services.article_generation');

        $this->assertIsArray($generation);
        $this->assertArrayHasKey('use_images_phase', $generation);
        $this->assertArrayHasKey('use_translate_phase', $generation);
        $this->assertArrayHasKey('use_score_phase', $generation);
    }
}
