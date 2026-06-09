<?php

namespace Tests\Feature;

use App\Models\ImageGenerationJob;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Services\CarouselSlideEnhancer;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase C — default-carousel plan (2026-06-09).
 * When the webhook marking the LAST slide 'done' fires, the service must
 * enqueue the targeted social-cross-post:scan. Partial completion must not.
 * Already-fanned-out drafts must not re-dispatch.
 */
class CarouselWebhookFanoutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Partial mock that stubs the HTTP download + the dispatch seam, with a
     * permissive circuit breaker. dispatchCrossPostScan expectation is set by
     * each test.
     */
    private function makePartialService(): LinkedInCarouselImageService
    {
        $breaker = Mockery::mock(GeminiGenCircuitBreaker::class);
        $breaker->shouldReceive('recordSuccess')->andReturnNull();
        $breaker->shouldReceive('recordFailure')->andReturnNull();

        $svc = Mockery::mock(
            LinkedInCarouselImageService::class . '[downloadAndStore,dispatchCrossPostScan]',
            [Mockery::mock(CarouselSlideEnhancer::class), $breaker]
        )->makePartial();
        $svc->shouldReceive('downloadAndStore')->andReturn('https://cdn.example.com/slide.png');

        return $svc;
    }

    private function carouselDraft(array $slides): LinkedInPost
    {
        return LinkedInPost::factory()->create([
            'format' => 'carousel',
            'status' => 'manual_review',
            'carousel_slides' => $slides,
        ]);
    }

    public function test_dispatches_fanout_when_last_slide_completes(): void
    {
        $draft = $this->carouselDraft([
            ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'done', 'image_url' => 'https://x/1.png', 'image_job_uuid' => 'uuid-done'],
            ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'generating', 'image_job_uuid' => 'uuid-pending'],
        ]);

        ImageGenerationJob::create([
            'uuid' => 'uuid-pending',
            'type' => 'carousel_slide',
            'linkedin_post_id' => $draft->id,
            'slide_index' => 1,
            'status' => 'generating',
            'planned_filename' => 'x-li-' . $draft->id . '-slide-02-cta.png',
        ]);

        $svc = $this->makePartialService();
        $svc->shouldReceive('dispatchCrossPostScan')->once()->with($draft->id);

        $ok = $svc->handleWebhook('uuid-pending', 'IMAGE_GENERATION_COMPLETED', ['media_url' => 'https://remote/x.png']);

        $this->assertTrue($ok);
    }

    public function test_does_not_dispatch_on_partial_completion(): void
    {
        $draft = $this->carouselDraft([
            ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'generating', 'image_job_uuid' => 'uuid-a'],
            ['slide_number' => 2, 'layout_hint' => 'body', 'image_status' => 'generating', 'image_job_uuid' => 'uuid-b'],
        ]);

        ImageGenerationJob::create([
            'uuid' => 'uuid-a',
            'type' => 'carousel_slide',
            'linkedin_post_id' => $draft->id,
            'slide_index' => 0,
            'status' => 'generating',
            'planned_filename' => 'x-li-' . $draft->id . '-slide-01-cover.png',
        ]);

        $svc = $this->makePartialService();
        $svc->shouldNotReceive('dispatchCrossPostScan');

        // Completing slide 0 leaves slide 1 still generating → no fan-out.
        $svc->handleWebhook('uuid-a', 'IMAGE_GENERATION_COMPLETED', ['media_url' => 'https://remote/a.png']);
    }

    public function test_does_not_redispatch_when_sibling_already_exists(): void
    {
        $draft = $this->carouselDraft([
            ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'done', 'image_url' => 'https://x/1.png', 'image_job_uuid' => 'uuid-1'],
            ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'generating', 'image_job_uuid' => 'uuid-2'],
        ]);

        ImageGenerationJob::create([
            'uuid' => 'uuid-2',
            'type' => 'carousel_slide',
            'linkedin_post_id' => $draft->id,
            'slide_index' => 1,
            'status' => 'generating',
            'planned_filename' => 'x-li-' . $draft->id . '-slide-02-cta.png',
        ]);

        // A sibling already exists → fan-out already happened, do not repeat.
        InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'pending_generation',
        ]);

        $svc = $this->makePartialService();
        $svc->shouldNotReceive('dispatchCrossPostScan');

        $svc->handleWebhook('uuid-2', 'IMAGE_GENERATION_COMPLETED', ['media_url' => 'https://remote/2.png']);
    }
}
