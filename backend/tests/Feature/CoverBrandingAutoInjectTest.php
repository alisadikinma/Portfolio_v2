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

class CoverBrandingAutoInjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('content.cover_branding.enabled', true);
        Config::set('content.cover_branding.model', 'nano-banana-pro');
        Config::set('content.cover_branding.title_max_len', 70);

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockProfilePhoto(?string $value): void
    {
        $query = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $query->shouldReceive('value')->with('value')->andReturn($value);

        // creator_brand group — watermark disabled by default for these tests
        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->andReturnUsing(function ($col, $key) {
            $stub = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $stub->shouldReceive('value')->with('value')->andReturn(null);
            return $stub;
        });

        $mock = Mockery::mock('alias:' . Setting::class);
        $mock->shouldReceive('where')->with('group', 'about')->andReturn($query);
        $mock->shouldReceive('where')->with('group', 'creator_brand')->andReturn($brandQuery);
    }

    private function makeIdea(array $override = []): ContentIdea
    {
        $idea = new ContentIdea();
        $idea->title = $override['title'] ?? 'Default Title';
        $idea->generated_article = $override['generated_article'] ?? [
            'language' => 'id',
            'id' => ['title' => $override['title'] ?? 'Default Title'],
        ];
        return $idea;
    }

    /** @test */
    public function cover_always_gets_creator_face_even_without_human_keyword(): void
    {
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');
        $this->mockProfilePhoto('about/ali.png');

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Futuristic dashboard',
            'visual_direction' => 'abstract holographic UI panels, glowing neon, dark mode',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $enhancer = app(CoverBrandingEnhancer::class);
        $result = $enhancer->enhance($prompt, $this->makeIdea());

        $this->assertCount(1, $result['face_refs'], 'Cover should always have creator face prepended');
        $this->assertStringContainsString('about/ali.png', $result['face_refs'][0]);
    }

    /** @test */
    public function inline_without_needs_creator_face_flag_is_not_modified(): void
    {
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');
        $this->mockProfilePhoto('about/ali.png');

        $prompt = [
            'type' => 'inline',
            'prompt_text' => 'Abstract code editor',
            'visual_direction' => 'floating UI elements, neon accents',
            'face_refs' => [],
            'model' => 'nano-banana-2',
            // No needs_creator_face flag; no human keyword
        ];

        $enhancer = app(CoverBrandingEnhancer::class);
        $result = $enhancer->enhance($prompt, $this->makeIdea());

        $this->assertSame($prompt, $result, 'Inline without flag or keyword must pass through unchanged');
    }

    /** @test */
    public function inline_with_needs_creator_face_flag_gets_face_injected(): void
    {
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');
        $this->mockProfilePhoto('about/ali.png');

        $prompt = [
            'type' => 'inline',
            'prompt_text' => 'Tutorial scene',
            'visual_direction' => 'abstract floating UI, no people',
            'face_refs' => [],
            'needs_creator_face' => true,
            'model' => 'nano-banana-2',
        ];

        $enhancer = app(CoverBrandingEnhancer::class);
        $result = $enhancer->enhance($prompt, $this->makeIdea());

        $this->assertCount(1, $result['face_refs']);
        $this->assertStringContainsString('about/ali.png', $result['face_refs'][0]);
    }

    /** @test */
    public function inline_with_expanded_keyword_developer_gets_face_injected(): void
    {
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');
        $this->mockProfilePhoto('about/ali.png');

        $prompt = [
            'type' => 'inline',
            'prompt_text' => 'A scene',
            'visual_direction' => 'young developer at a glass desk reviewing code',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $enhancer = app(CoverBrandingEnhancer::class);
        $result = $enhancer->enhance($prompt, $this->makeIdea());

        $this->assertCount(1, $result['face_refs']);
        $this->assertStringContainsString('about/ali.png', $result['face_refs'][0]);
    }

    /** @test */
    public function auto_inject_triggers_vd_rewrite_when_vd_original_is_empty(): void
    {
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');
        $this->mockProfilePhoto('about/ali.png');

        $rewriteService = Mockery::mock(ArticleGenerationService::class);
        $rewriteService->shouldReceive('rewriteVisualDirectionForFace')
            ->once()
            ->andReturn([
                'success' => true,
                'rewritten_vd' => 'A middle-aged bald Asian man in navy blazer at a minimal glass desk',
                'error' => null,
            ]);
        $this->app->instance(ArticleGenerationService::class, $rewriteService);

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Modern dashboard',
            'visual_direction' => 'young Southeast Asian developer at a glass desk',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $enhancer = app(CoverBrandingEnhancer::class);
        $result = $enhancer->enhance($prompt, $this->makeIdea());

        $this->assertSame(
            'A middle-aged bald Asian man in navy blazer at a minimal glass desk',
            $result['visual_direction']
        );
        $this->assertSame(
            'young Southeast Asian developer at a glass desk',
            $result['visual_direction_original']
        );
    }

    /** @test */
    public function vd_rewrite_failure_is_non_blocking(): void
    {
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');
        $this->mockProfilePhoto('about/ali.png');

        Log::shouldReceive('warning')->atLeast()->once();

        $rewriteService = Mockery::mock(ArticleGenerationService::class);
        $rewriteService->shouldReceive('rewriteVisualDirectionForFace')
            ->once()
            ->andReturn([
                'success' => false,
                'rewritten_vd' => null,
                'error' => 'Claude CLI timeout',
            ]);
        $this->app->instance(ArticleGenerationService::class, $rewriteService);

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Dashboard',
            'visual_direction' => 'young developer at desk',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $enhancer = app(CoverBrandingEnhancer::class);
        $result = $enhancer->enhance($prompt, $this->makeIdea());

        // Face still prepended, VD unchanged, no exception
        $this->assertCount(1, $result['face_refs']);
        $this->assertSame('young developer at desk', $result['visual_direction']);
    }

    /** @test */
    public function vd_rewrite_is_skipped_when_visual_direction_original_already_set(): void
    {
        Storage::disk('public')->put('about/ali.png', 'fake-bytes');
        $this->mockProfilePhoto('about/ali.png');

        $rewriteService = Mockery::mock(ArticleGenerationService::class);
        $rewriteService->shouldNotReceive('rewriteVisualDirectionForFace');
        $this->app->instance(ArticleGenerationService::class, $rewriteService);

        $prompt = [
            'type' => 'cover',
            'prompt_text' => 'Dashboard',
            'visual_direction' => 'middle-aged bald Asian man at desk',
            'visual_direction_original' => 'young developer at desk',
            'face_refs' => [],
            'model' => 'nano-banana-2',
        ];

        $enhancer = app(CoverBrandingEnhancer::class);
        $result = $enhancer->enhance($prompt, $this->makeIdea());

        $this->assertCount(1, $result['face_refs']);
        $this->assertSame('middle-aged bald Asian man at desk', $result['visual_direction']);
    }
}
