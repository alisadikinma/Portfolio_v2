<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ImageGenerationJob;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\CarouselSlideEnhancer;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Bounded transient image retry (June 12, 2026, Req 2).
 *
 * A non-policy GeminiGen failure (network blip / 5xx / timeout) used to land
 * a slide in terminal 'failed' with zero retry. handleWebhook now resets the
 * slide to 'pending' + re-dispatches up to MAX_TRANSIENT_RETRIES, then marks
 * it failed_permanent.
 */
class CarouselSlideTransientRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): LinkedInCarouselImageService
    {
        $breaker = Mockery::mock(GeminiGenCircuitBreaker::class);
        $breaker->shouldReceive('state')->andReturn('closed');
        $breaker->shouldReceive('recordFailure')->andReturnNull();
        $breaker->shouldReceive('recordSuccess')->andReturnNull();

        return Mockery::mock(
            LinkedInCarouselImageService::class . '[dispatchSingleSlide]',
            [Mockery::mock(CarouselSlideEnhancer::class), $breaker]
        )->makePartial();
    }

    private function draftWithSlide(int $retryCount): LinkedInPost
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-' . Str::random(4)]);
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'P-' . Str::random(6),
            'content' => 'Body',
            'slug' => 'p-' . Str::random(8),
            'published' => true,
            'published_at' => now(),
        ]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => 'manual_review',
            'carousel_slides' => [[
                'slide_number' => 1,
                'layout_hint' => 'cover',
                'image_status' => 'generating',
                'image_job_uuid' => 'u-fail',
                'image_retry_count' => $retryCount,
            ]],
        ]);
    }

    private function jobFor(LinkedInPost $draft): void
    {
        ImageGenerationJob::create([
            'uuid' => 'u-fail',
            'type' => 'carousel_slide',
            'linkedin_post_id' => $draft->id,
            'slide_index' => 0,
            'status' => 'generating',
            'prompt' => 'slide prompt',
            'planned_filename' => 'x-li-' . $draft->id . '-slide-01-cover.png',
        ]);
    }

    public function test_transient_failure_resets_slide_to_pending_and_bumps_count(): void
    {
        $draft = $this->draftWithSlide(0);
        $this->jobFor($draft);

        $svc = $this->makeService();
        $svc->shouldReceive('dispatchSingleSlide')->once()->andReturn('new-uuid');

        $svc->handleWebhook('u-fail', 'IMAGE_GENERATION_FAILED', ['error_message' => 'network error during render']);

        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertSame('pending', $slide['image_status']);
        $this->assertSame(1, (int) $slide['image_retry_count']);
        $this->assertNull($slide['image_error']);
    }

    public function test_exhausted_transient_failure_marks_failed_permanent(): void
    {
        Bus::fake(); // markSlideFailedPermanent dispatches a Telegram event.
        $draft = $this->draftWithSlide(3); // already at MAX_TRANSIENT_RETRIES
        $this->jobFor($draft);

        $svc = $this->makeService();
        $svc->shouldNotReceive('dispatchSingleSlide');

        $svc->handleWebhook('u-fail', 'IMAGE_GENERATION_FAILED', ['error_message' => 'network error during render']);

        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertSame('failed_permanent', $slide['image_status']);
    }
}
