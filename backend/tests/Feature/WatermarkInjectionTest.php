<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Setting;
use App\Services\ArticleGenerationService;
use App\Services\CoverBrandingEnhancer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class WatermarkInjectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('content.cover_branding.enabled', true);
        Config::set('content.cover_branding.model', 'nano-banana-pro');
        Config::set('content.cover_branding.title_max_len', 70);

        Storage::fake('public');

        // Stub ArticleGenerationService so VD rewrite during face inject never fires external CLI
        $rewriteStub = Mockery::mock(ArticleGenerationService::class);
        $rewriteStub->shouldReceive('rewriteVisualDirectionForFace')
            ->andReturn(['success' => false, 'rewritten_vd' => null, 'error' => 'stubbed']);
        $this->app->instance(ArticleGenerationService::class, $rewriteStub);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Full Setting mock chain: about.profile_photo, creator_brand.watermark_enabled,
     * creator_brand.creator_brand_logo, creator_brand.creator_brand_tagline,
     * creator_brand.watermark_opacity.
     */
    private function mockSettings(array $overrides = []): void
    {
        $defaults = [
            'profile_photo' => null,
            'watermark_enabled' => 'true',
            'creator_brand_logo' => 'creator-brand.png',
            'creator_brand_tagline' => 'alisadikinma.com',
            'watermark_opacity' => '0.30',
        ];
        $values = array_merge($defaults, $overrides);

        $aboutQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $aboutQuery->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $aboutQuery->shouldReceive('value')->with('value')->andReturn($values['profile_photo']);

        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->with('key', Mockery::on(fn ($k) => in_array($k, [
            'watermark_enabled', 'creator_brand_logo', 'creator_brand_tagline', 'watermark_opacity',
        ], true)))->andReturnUsing(function ($col, $key) use (&$brandQuery, $values) {
            $specific = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $specific->shouldReceive('value')->with('value')->andReturn($values[$key] ?? null);
            return $specific;
        });

        $mock = Mockery::mock('alias:' . Setting::class);
        $mock->shouldReceive('where')->with('group', 'about')->andReturn($aboutQuery);
        $mock->shouldReceive('where')->with('group', 'creator_brand')->andReturn($brandQuery);
    }

    private function makeIdea(): ContentIdea
    {
        $idea = new ContentIdea();
        $idea->title = 'Test Article';
        $idea->generated_article = [
            'language' => 'id',
            'id' => ['title' => 'Test Article'],
        ];
        return $idea;
    }

    /** @test */
    public function watermark_appended_on_cover_and_inline_when_enabled(): void
    {
        Storage::disk('public')->put('creator-brand.png', 'fake-logo-bytes');
        $this->mockSettings();

        $enhancer = app(CoverBrandingEnhancer::class);

        $coverPrompt = [
            'type' => 'cover',
            'prompt_text' => 'A modern dashboard',
            'visual_direction' => 'abstract panels',
            'face_refs' => [],
            'file_urls' => [],
            'model' => 'nano-banana-2',
        ];
        $cover = $enhancer->enhance($coverPrompt, $this->makeIdea());

        $inlinePrompt = [
            'type' => 'inline',
            'prompt_text' => 'Terminal output scene',
            'visual_direction' => 'abstract code snippet',
            'face_refs' => [],
            'file_urls' => [],
            'model' => 'nano-banana-2',
        ];
        $inline = $enhancer->enhance($inlinePrompt, $this->makeIdea());

        // Cover
        $this->assertStringContainsString('30% opacity', $cover['prompt_text']);
        $this->assertStringContainsString('alisadikinma.com', $cover['prompt_text']);
        $this->assertNotEmpty($cover['file_urls']);
        $this->assertTrue(collect($cover['file_urls'])->contains(fn ($u) => str_contains($u, 'creator-brand.png')));

        // Inline
        $this->assertStringContainsString('30% opacity', $inline['prompt_text']);
        $this->assertStringContainsString('alisadikinma.com', $inline['prompt_text']);
        $this->assertNotEmpty($inline['file_urls']);
        $this->assertTrue(collect($inline['file_urls'])->contains(fn ($u) => str_contains($u, 'creator-brand.png')));
    }

    /** @test */
    public function watermark_skipped_when_disabled(): void
    {
        $this->mockSettings(['watermark_enabled' => 'false']);

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A modern dashboard',
            'visual_direction' => 'abstract panels',
            'face_refs' => [],
            'file_urls' => [],
            'model' => 'nano-banana-2',
        ];
        $result = app(CoverBrandingEnhancer::class)->enhance($prompt, $this->makeIdea());

        $this->assertStringNotContainsString('30% opacity', $result['prompt_text']);
        $this->assertStringNotContainsString('alisadikinma.com', $result['prompt_text']);
        $this->assertSame([], $result['file_urls']);
    }

    /** @test */
    public function watermark_skipped_when_logo_missing(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();

        $this->mockSettings(['creator_brand_logo' => null]);

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'A scene',
            'visual_direction' => 'abstract',
            'face_refs' => [],
            'file_urls' => [],
            'model' => 'nano-banana-2',
        ];
        $result = app(CoverBrandingEnhancer::class)->enhance($prompt, $this->makeIdea());

        $this->assertStringNotContainsString('30% opacity', $result['prompt_text']);
        $this->assertSame([], $result['file_urls']);
    }
}
