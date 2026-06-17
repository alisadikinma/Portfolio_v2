<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ImageGenerationJob;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\CarouselPersonStripRenderer;
use App\Services\CarouselSlideEnhancer;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Phase F/G wiring — on slide-completion the webhook composites real person
 * photos into the slide's reserved band when person_photo_refs are present, and
 * leaves a plain slide untouched otherwise.
 */
class CarouselPersonPhotoWiringTest extends TestCase
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

    /** A fake strip renderer that writes the out file + reports success. */
    private function bindFakeRenderer(bool $succeed = true): void
    {
        app()->instance(CarouselPersonStripRenderer::class, new class($succeed) extends CarouselPersonStripRenderer {
            public function __construct(private bool $ok)
            {
            }

            public function render(string $baseAbs, array $faces, array $band, string $outAbs): bool
            {
                if ($this->ok) {
                    @mkdir(dirname($outAbs), 0775, true);
                    file_put_contents($outAbs, 'composited-png-bytes');

                    return true;
                }

                return false;
            }
        });
    }

    private function draft(array $slide0Extra): LinkedInPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-' . uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-' . uniqid(), 'title' => 'P', 'content' => 'x']);

        $draft = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => 'manual_review',
            'content' => 'x',
            'hashtags' => [],
            'pipeline_state_log' => [],
            'carousel_slides' => [
                array_merge([
                    'slide_number' => 1, 'layout_hint' => 'body',
                    'image_status' => 'generating', 'image_job_uuid' => 'uuid-1',
                ], $slide0Extra),
                ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'generating', 'image_job_uuid' => 'uuid-2'],
            ],
        ]);

        ImageGenerationJob::create([
            'uuid' => 'uuid-1', 'type' => 'carousel_slide',
            'linkedin_post_id' => $draft->id, 'slide_index' => 0, 'status' => 'generating',
            'prompt' => 'slide 1', 'planned_filename' => 'x-li-' . $draft->id . '-slide-01.png',
        ]);

        return $draft;
    }

    public function test_webhook_composites_person_strip_when_refs_present(): void
    {
        Storage::fake('public');
        // The rendered slide PNG the webhook "downloaded".
        Storage::disk('public')->put('linkedin-carousel/base-01.png', 'base-bytes');
        // A real cropped face cut-out the enricher had stored.
        Storage::disk('public')->put('repurpose-faces/9/0/face-01.png', 'face-bytes');

        $localBaseUrl = url('/storage/linkedin-carousel/base-01.png');
        $faceUrl = url('/storage/repurpose-faces/9/0/face-01.png');

        $this->bindFakeRenderer(true);
        $svc = $this->makeService($localBaseUrl);
        $draft = $this->draft([
            'needs_real_faces' => true,
            'face_layout' => 'photo_band_top',
            'person_photo_refs' => [['url' => $faceUrl, 'name' => 'Ashish Vaswani', 'role' => 'lead author']],
        ]);

        $svc->handleWebhook('uuid-1', 'IMAGE_GENERATION_COMPLETED', ['media_url' => 'https://edge/gen.png']);

        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertSame('done', $slide['image_status']);
        $this->assertStringContainsString('/storage/linkedin-carousel/person-strip/', $slide['image_url']);
        // The composited file exists on disk.
        $rel = str_replace(url('/storage/'), '', $slide['image_url']);
        Storage::disk('public')->assertExists($rel);
    }

    public function test_webhook_leaves_plain_slide_when_no_refs(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('linkedin-carousel/base-01.png', 'base-bytes');
        $localBaseUrl = url('/storage/linkedin-carousel/base-01.png');

        $this->bindFakeRenderer(true);
        $svc = $this->makeService($localBaseUrl);
        $draft = $this->draft([]); // no person_photo_refs

        $svc->handleWebhook('uuid-1', 'IMAGE_GENERATION_COMPLETED', ['media_url' => 'https://edge/gen.png']);

        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertSame('done', $slide['image_status']);
        $this->assertSame($localBaseUrl, $slide['image_url']); // unchanged, no person-strip
    }

    public function test_webhook_keeps_plain_slide_when_composite_fails(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('linkedin-carousel/base-01.png', 'base-bytes');
        Storage::disk('public')->put('repurpose-faces/9/0/face-01.png', 'face-bytes');
        $localBaseUrl = url('/storage/linkedin-carousel/base-01.png');
        $faceUrl = url('/storage/repurpose-faces/9/0/face-01.png');

        $this->bindFakeRenderer(false); // composite fails → graceful degrade
        $svc = $this->makeService($localBaseUrl);
        $draft = $this->draft([
            'needs_real_faces' => true, 'face_layout' => 'photo_band_top',
            'person_photo_refs' => [['url' => $faceUrl, 'name' => 'X Y']],
        ]);

        $svc->handleWebhook('uuid-1', 'IMAGE_GENERATION_COMPLETED', ['media_url' => 'https://edge/gen.png']);

        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertSame('done', $slide['image_status']);
        $this->assertSame($localBaseUrl, $slide['image_url']); // fell back to plain slide
    }
}
