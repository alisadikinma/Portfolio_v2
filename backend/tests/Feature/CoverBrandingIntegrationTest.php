<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Setting;
use App\Services\CoverBrandingEnhancer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Verifies the exact array shape produced by ContentIdeaController::generateSegmentImage
 * before it calls CoverBrandingEnhancer::enhance, and that the enhanced output
 * carries the expected mutations (or pass-through) depending on segment type.
 *
 * Route-level testing would require live DB + auth middleware which is not
 * available in the test env (SQLite migration incompatibility). This "contract
 * test" locks in the data shape the controller builds so the enhancer wiring
 * stays correct across refactors.
 */
class CoverBrandingIntegrationTest extends TestCase
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

    private function mockSetting(?string $value): void
    {
        $query = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $query->shouldReceive('value')->with('value')->andReturn($value);

        // creator_brand group — watermark disabled; all keys resolve to null
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

    /**
     * Reproduces the exact $forEnhance array shape the controller constructs
     * at the dispatch point (ContentIdeaController::generateSegmentImage).
     */
    private function buildControllerShape(array $segmentMeta, array $requestData): array
    {
        return array_merge($segmentMeta, [
            'prompt_text' => $requestData['prompt'],
            'face_refs' => array_merge($requestData['face_refs'] ?? [], $requestData['brand_ref_urls'] ?? []),
            'model' => $requestData['model'],
        ]);
    }

    /** @test */
    public function controller_shape_for_cover_segment_triggers_full_enhancement()
    {
        Storage::disk('public')->put('about/ali.png', 'fake');
        $this->mockSetting('about/ali.png');

        $idea = new ContentIdea();
        $idea->title = 'Demo';
        $idea->generated_article = [
            'language' => 'id',
            'id' => ['title' => 'Cara AI Mengubah Dunia'],
            'image_prompts' => [],
        ];

        // Segment 0 (cover) — plugin authored type=cover + visual_direction
        $segmentMeta = [
            'type' => 'cover',
            'visual_direction' => 'portrait of a founder at a modern desk',
            'concept' => 'Hero shot',
            'status' => 'pending',
        ];

        $requestData = [
            'prompt' => 'A founder at a workstation, cinematic',
            'face_refs' => ['https://example.com/user-uploaded.jpg'],
            'brand_ref_urls' => [],
            'model' => 'nano-banana-2',
        ];

        $forEnhance = $this->buildControllerShape($segmentMeta, $requestData);
        $enhanced = (new CoverBrandingEnhancer())->enhance($forEnhance, $idea);

        // Title must be appended
        $this->assertStringContainsString('Cara AI Mengubah Dunia', $enhanced['prompt_text']);
        $this->assertStringContainsString('thumbnail-style title text', $enhanced['prompt_text']);

        // Creator face prepended before user-uploaded ref
        $this->assertCount(2, $enhanced['face_refs']);
        $this->assertStringContainsString('about/ali.png', $enhanced['face_refs'][0]);
        $this->assertSame('https://example.com/user-uploaded.jpg', $enhanced['face_refs'][1]);

        // Model forced to nano-banana-pro despite input nano-banana-2
        $this->assertSame('nano-banana-pro', $enhanced['model']);
    }

    /** @test */
    public function controller_shape_for_inline_segment_without_human_passes_through_unchanged()
    {
        $this->mockSetting('about/ali.png');

        $idea = new ContentIdea();
        $idea->title = 'Demo';
        $idea->generated_article = ['language' => 'id', 'id' => ['title' => 'Demo']];

        $segmentMeta = [
            'type' => 'inline',
            // No human keyword in VD — inline must pass through unchanged
            'visual_direction' => 'abstract glowing monitors in dark studio',
            'status' => 'pending',
        ];

        $requestData = [
            'prompt' => 'Abstract code visualization',
            'face_refs' => [],
            'brand_ref_urls' => [],
            'model' => 'imagen-4',
        ];

        $forEnhance = $this->buildControllerShape($segmentMeta, $requestData);
        $enhanced = (new CoverBrandingEnhancer())->enhance($forEnhance, $idea);

        // Inline without flag/keyword: unchanged — model respected, no title injection, no face ref
        $this->assertSame('imagen-4', $enhanced['model']);
        $this->assertSame('Abstract code visualization', $enhanced['prompt_text']);
        $this->assertSame([], $enhanced['face_refs']);
    }

    /** @test */
    public function cover_without_human_keyword_still_gets_title_and_face_auto_inject()
    {
        // NEW behavior (Phase 3): cover ALWAYS gets creator face, regardless of keywords.
        Storage::disk('public')->put('about/ali.png', 'fake');
        $this->mockSetting('about/ali.png');

        $idea = new ContentIdea();
        $idea->title = 'Demo';
        $idea->generated_article = ['language' => 'id', 'id' => ['title' => 'Dashboard Trick']];

        $segmentMeta = [
            'type' => 'cover',
            'visual_direction' => 'abstract glowing UI dashboard, cinematic',
            'status' => 'pending',
        ];

        $requestData = [
            'prompt' => 'A modern dashboard interface',
            'face_refs' => [],
            'brand_ref_urls' => [],
            'model' => 'nano-banana-2',
        ];

        $forEnhance = $this->buildControllerShape($segmentMeta, $requestData);
        $enhanced = (new CoverBrandingEnhancer())->enhance($forEnhance, $idea);

        // Title injected
        $this->assertStringContainsString('Dashboard Trick', $enhanced['prompt_text']);
        // Cover always auto-injects face — no longer keyword-gated
        $this->assertCount(1, $enhanced['face_refs']);
        $this->assertStringContainsString('about/ali.png', $enhanced['face_refs'][0]);
        // Model still forced for cover type
        $this->assertSame('nano-banana-pro', $enhanced['model']);
    }
}
