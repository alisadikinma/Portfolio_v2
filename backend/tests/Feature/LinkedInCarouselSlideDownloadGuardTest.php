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
use Mockery;
use Tests\TestCase;

/**
 * B2 (draft-149 cross-post fix) — a carousel slide must NEVER reach 'done'
 * carrying a remote URL. downloadAndStore() falls back to returning the remote
 * URL on any failure (non-2xx, tiny body, storage error). Marking the slide
 * 'done' with that URL leaks a short-lived octet-stream GeminiGen edge URL into
 * carousel_slides[].image_url — which LinkedIn tolerates (it fetches bytes) but
 * Publer's /media/from-url cannot ingest (the production cross-post failure).
 * The completion path now holds such a slide as 'failed' so the reaper retries.
 *
 * Two slides are used so a single slide completing never makes the carousel
 * "all done" — keeps the cross-post fan-out (and its protected dispatch seam)
 * out of scope here.
 */
class LinkedInCarouselSlideDownloadGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(string $downloadReturns): LinkedInCarouselImageService
    {
        $breaker = Mockery::mock(GeminiGenCircuitBreaker::class);
        $breaker->shouldReceive('recordSuccess')->andReturnNull();
        $breaker->shouldReceive('recordFailure')->andReturnNull();
        $breaker->shouldReceive('state')->andReturn('closed');

        $svc = Mockery::mock(
            LinkedInCarouselImageService::class . '[downloadAndStore]',
            [Mockery::mock(CarouselSlideEnhancer::class), $breaker]
        )->makePartial();
        $svc->shouldReceive('downloadAndStore')->andReturn($downloadReturns);

        return $svc;
    }

    private function draftWithPendingSlide(): LinkedInPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-' . uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'guard-post-' . uniqid(),
            'title' => 'Guard Post',
            'content' => 'x',
        ]);

        $draft = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => 'manual_review',
            'content' => 'x',
            'hashtags' => [],
            'pipeline_state_log' => [],
            'carousel_slides' => [
                ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'generating', 'image_job_uuid' => 'uuid-1'],
                ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'generating', 'image_job_uuid' => 'uuid-2'],
            ],
        ]);

        ImageGenerationJob::create([
            'uuid' => 'uuid-1',
            'type' => 'carousel_slide',
            'linkedin_post_id' => $draft->id,
            'slide_index' => 0,
            'status' => 'generating',
            'prompt' => 'slide 1 prompt',
            'planned_filename' => 'x-li-' . $draft->id . '-slide-01-cover.png',
        ]);

        return $draft;
    }

    public function test_remote_url_holds_slide_as_failed_not_done(): void
    {
        $remote = 'https://edge-files.geminigen.ai/bucket/gen/slide.png?Signature=a&Expires=1';
        $svc = $this->makeService($remote);
        $draft = $this->draftWithPendingSlide();

        $svc->handleWebhook('uuid-1', 'IMAGE_GENERATION_COMPLETED', ['media_url' => $remote]);

        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertSame('failed', $slide['image_status']);
        $this->assertNotSame($remote, $slide['image_url'] ?? null);

        $this->assertSame('failed', ImageGenerationJob::where('uuid', 'uuid-1')->value('status'));
    }

    public function test_local_url_marks_slide_done(): void
    {
        $local = 'http://localhost/storage/linkedin-carousel/x-li-slide-01-cover.png';
        $svc = $this->makeService($local);
        $draft = $this->draftWithPendingSlide();

        $svc->handleWebhook('uuid-1', 'IMAGE_GENERATION_COMPLETED', ['media_url' => 'https://edge-files.geminigen.ai/gen.png']);

        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertSame('done', $slide['image_status']);
        $this->assertSame($local, $slide['image_url']);

        $this->assertSame('completed', ImageGenerationJob::where('uuid', 'uuid-1')->value('status'));
    }
}
