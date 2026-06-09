<?php

namespace Tests\Feature;

use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use App\Services\PipelineGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase C — skip-linkedin-gen plan (2026-06-09).
 *
 * Proves generate() branches on the linkedin_force_carousel setting:
 *   - ON  → /linkedin-gen (invokePlugin) is NEVER called; a synthetic
 *           route_to_carousel_gen envelope is built and applyCarouselGenAdapter
 *           receives the blog body inline as its 4th arg.
 *   - OFF → invokePlugin IS called (legacy plugin-decided path).
 *
 * Heavy seams are partial-mocked (invokePlugin/persistAndRoute promoted to
 * protected as test seams; buildBlogPayload/applyCarouselGenAdapter public).
 * The real PipelineGuard + RefreshDatabase drive the FSM advance + progress
 * writes against a persisted draft, so no SSH and no caption/translation
 * plumbing is needed.
 */
class LinkedInGenerateSkipPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @param string[] $methods */
    private function partialService(array $methods): LinkedInGenerationService
    {
        config()->set('carousel-gen.driver', 'ssh');
        $guard = app(PipelineGuard::class);
        $adapter = new CarouselGenOutputAdapter();
        $list = implode(',', $methods);

        $svc = Mockery::mock(
            LinkedInGenerationService::class . "[{$list}]",
            [$guard, $adapter]
        );
        $svc->shouldAllowMockingProtectedMethods();
        $svc->makePartial();

        return $svc;
    }

    private function assembledCarousel(): array
    {
        return [
            'format' => 'carousel',
            'status' => 'complete',
            'brief' => ['pillar' => 'ai_generalist'],
            'carousel' => ['slides' => [[
                'slide_number' => 1,
                'layout_hint' => 'cover',
                'copy' => 'x',
                'image_prompt' => 'p',
                'image_status' => 'pending',
            ]]],
            'post' => null,
            'validation' => null,
        ];
    }

    public function test_force_carousel_on_skips_linkedin_gen_and_embeds_blog_content(): void
    {
        Setting::set('linkedin_force_carousel', 'true', 'linkedin');

        $draft = LinkedInPost::factory()->create([
            'status' => 'pending_generation',
            'format' => 'carousel',
        ]);

        $captured = null;
        $svc = $this->partialService(['invokePlugin', 'buildBlogPayload', 'applyCarouselGenAdapter', 'persistAndRoute']);

        // /linkedin-gen orchestrator must NOT run under force-carousel.
        $svc->shouldReceive('invokePlugin')->never();

        $svc->shouldReceive('buildBlogPayload')->once()->andReturn([
            'url' => 'https://alisadikinma.com/blog/ai-mendesain-model-openai',
            'title' => 'AI is designing OpenAI next model',
            'content' => 'INLINE-BODY-XYZ full article paragraphs.',
        ]);

        $svc->shouldReceive('applyCarouselGenAdapter')
            ->once()
            ->andReturnUsing(function ($parsed, $url, $id, $content = null) use (&$captured) {
                $captured = compact('parsed', 'url', 'id', 'content');
                return $this->assembledCarousel();
            });

        $svc->shouldReceive('persistAndRoute')->once()->andReturn([
            'success' => true,
            'draft_id' => $draft->id,
            'status' => 'awaiting_publish',
        ]);

        $result = $svc->generate($draft);

        $this->assertTrue($result['success']);
        $this->assertNotNull($captured, 'applyCarouselGenAdapter should have been called');
        // Synthetic envelope reached the adapter (proves /linkedin-gen skipped).
        $this->assertSame('route_to_carousel_gen', $captured['parsed']['status']);
        $this->assertSame('carousel', $captured['parsed']['format']);
        $this->assertArrayHasKey('pillar', $captured['parsed']['brief']);
        // Blog body embedded inline as the 4th arg.
        $this->assertSame('INLINE-BODY-XYZ full article paragraphs.', $captured['content']);
    }

    public function test_force_carousel_off_invokes_linkedin_gen_plugin(): void
    {
        Setting::set('linkedin_force_carousel', 'false', 'linkedin');

        $draft = LinkedInPost::factory()->create([
            'status' => 'pending_generation',
            'format' => 'carousel',
        ]);

        $svc = $this->partialService(['invokePlugin', 'buildBlogPayload', 'applyCarouselGenAdapter', 'persistAndRoute']);

        $svc->shouldReceive('buildBlogPayload')->andReturn([
            'url' => 'https://alisadikinma.com/blog/x',
            'title' => 'T',
            'content' => 'BODY',
        ]);

        // Plugin path: invokePlugin runs and returns a carousel-route envelope
        // (route_to_carousel_gen skips the format-mix governor → no 2nd invoke).
        $svc->shouldReceive('invokePlugin')->once()->andReturn([
            'success' => true,
            'stdout' => json_encode([
                'format' => 'carousel',
                'status' => 'route_to_carousel_gen',
                'brief' => ['pillar' => 'ai_generalist'],
                'post' => null,
                'carousel' => null,
                'validation' => null,
            ]),
        ]);

        $svc->shouldReceive('applyCarouselGenAdapter')->once()->andReturn($this->assembledCarousel());
        $svc->shouldReceive('persistAndRoute')->once()->andReturn([
            'success' => true,
            'draft_id' => $draft->id,
            'status' => 'awaiting_publish',
        ]);

        $result = $svc->generate($draft);

        $this->assertTrue($result['success']);
        // Mockery verifies invokePlugin->once() at tearDown — the plugin ran.
    }
}
