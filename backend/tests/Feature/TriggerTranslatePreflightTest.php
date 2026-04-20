<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Unit-style tests for ArticleGenerationService::triggerTranslatePreflight.
 * Mocks the concrete translate primitive (translateArticle) via partial mock
 * so we can assert preflight wrapper behavior without hitting SSH.
 */
class TriggerTranslatePreflightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeIdea(array $generatedArticle): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Preflight idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'completed',
            'auto_mode' => true,
            'generated_article' => $generatedArticle,
        ]);
    }

    /** @test */
    public function writes_translated_dict_into_target_locale_slot_on_success(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => [
                'title' => 'Judul ID',
                'content' => '<p>Isi ID</p>',
                'excerpt' => 'Ringkasan',
                'meta_title' => 'Meta',
                'meta_description' => 'Desc',
            ],
            'title' => 'Judul ID',
            'content' => '<p>Isi ID</p>',
        ]);

        $service = Mockery::mock(ArticleGenerationService::class)->makePartial();
        $service->shouldReceive('translateArticle')
            ->once()
            ->andReturn([
                'success' => true,
                'translated' => [
                    'title' => 'Title EN',
                    'content' => '<p>Content EN</p>',
                    'excerpt' => 'Summary EN',
                    'meta_title' => 'Meta EN',
                    'meta_description' => 'Desc EN',
                    'og_title' => 'OG EN',
                    'og_description' => 'OG Desc EN',
                    'ai_summary' => 'AI EN',
                ],
                'error' => null,
            ]);

        $result = $service->triggerTranslatePreflight($idea);

        $this->assertTrue($result['success']);
        $idea->refresh();
        $en = $idea->generated_article['en'] ?? null;
        $this->assertIsArray($en);
        $this->assertSame('Title EN', $en['title']);
        $this->assertSame('<p>Content EN</p>', $en['content']);
        $this->assertSame('Summary EN', $en['excerpt']);
    }

    /** @test */
    public function returns_skipped_early_when_flag_is_off(): void
    {
        Config::set('services.article_generation.use_translate_phase', false);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'x', 'content' => '<p>x</p>'],
        ]);

        $service = Mockery::mock(ArticleGenerationService::class)->makePartial();
        $service->shouldNotReceive('translateArticle');

        $result = $service->triggerTranslatePreflight($idea);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped'] ?? false);
    }

    /** @test */
    public function returns_failure_when_underlying_translate_fails(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'x', 'content' => '<p>x</p>'],
        ]);

        $service = Mockery::mock(ArticleGenerationService::class)->makePartial();
        $service->shouldReceive('translateArticle')
            ->andReturn(['success' => false, 'translated' => null, 'error' => 'SSH boom']);

        $result = $service->triggerTranslatePreflight($idea);

        $this->assertFalse($result['success']);
        $this->assertSame('SSH boom', $result['error']);

        $idea->refresh();
        $this->assertArrayNotHasKey('en', $idea->generated_article);
    }

    /** @test */
    public function uses_primary_language_to_pick_target(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);

        $idea = $this->makeIdea([
            'language' => 'en',
            'en' => ['title' => 'Title EN', 'content' => '<p>c</p>'],
            'title' => 'Title EN',
            'content' => '<p>c</p>',
        ]);

        $service = Mockery::mock(ArticleGenerationService::class)->makePartial();
        $service->shouldReceive('translateArticle')
            ->once()
            ->andReturn([
                'success' => true,
                'translated' => ['title' => 'Judul', 'content' => '<p>konten</p>'],
                'error' => null,
            ]);

        $result = $service->triggerTranslatePreflight($idea);

        $this->assertTrue($result['success']);
        $idea->refresh();
        $this->assertArrayHasKey('id', $idea->generated_article);
        $this->assertSame('Judul', $idea->generated_article['id']['title']);
    }
}
