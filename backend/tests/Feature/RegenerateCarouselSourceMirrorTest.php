<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateLinkedInCarouselImages;
use App\Jobs\RegenerateLinkedInCarouselContent;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use App\Services\RepurposeCarouselBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Verifies that RegenerateLinkedInCarouselContent takes the source-mirror
 * path (RepurposeCarouselBuilder) for IG-repurpose drafts when the
 * repurpose_source_mirror_regenerate flag is on, and falls back to
 * /carousel-gen for non-repurpose drafts, empty tool lists, or flag-off.
 */
class RegenerateCarouselSourceMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://test.alisadikinma.com']);
        // Ensure button-path flag is ON by default for every test.
        config(['linkedin.repurpose_source_mirror_regenerate' => true]);
    }

    private function makeCarouselDraft(): LinkedInPost
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . Str::random(5)]);
        $post = Post::create([
            'category_id' => $cat->id,
            'title' => 'P',
            'content' => 'Body',
            'slug' => 'post-' . Str::random(8),
            'published' => true,
            'published_at' => now(),
        ]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
        ]);
    }

    /** Build N canned slides in the shape RepurposeCarouselBuilder::slide() returns. */
    private function cannedSlides(int $count): array
    {
        $slides = [];
        for ($i = 1; $i <= $count; $i++) {
            $isFirst = $i === 1;
            $isLast = $i === $count;
            $slides[] = [
                'slide_number' => $i,
                'layout_hint' => $isFirst ? 'cover' : ($isLast ? 'cta' : 'body'),
                'copy_id' => "Slide {$i} teks Indonesia",
                'copy_en' => "Slide {$i} English text",
                'image_prompt' => "Cinematic prompt for slide {$i}",
                'image_status' => 'pending',
                'image_url' => null,
                'is_cover' => $isFirst,
                'is_cta' => $isLast,
            ];
        }
        return $slides;
    }

    public function test_repurpose_with_parseable_list_uses_source_mirror_and_skips_carousel_gen(): void
    {
        Queue::fake();
        $draft = $this->makeCarouselDraft();
        $canned = $this->cannedSlides(4); // cover + 2 tools + cta

        $gen = Mockery::mock(LinkedInGenerationService::class);
        $gen->shouldReceive('isRepurposeDraft')->once()->andReturn(true);
        $gen->shouldNotReceive('dispatchCarouselGenEngine');

        $builder = $this->mock(RepurposeCarouselBuilder::class);
        $builder->shouldReceive('buildForDraftId')
            ->once()
            ->with($draft->id)
            ->andReturn($canned);

        (new RegenerateLinkedInCarouselContent($draft->id))
            ->handle($gen, Mockery::mock(CarouselGenOutputAdapter::class));

        $fresh = $draft->fresh();
        $this->assertEquals($canned, $fresh->carousel_slides);
        $this->assertNull($fresh->slide_asset_urns);
        $this->assertNull($fresh->last_error);

        Queue::assertPushed(GenerateLinkedInCarouselImages::class, function ($job) use ($draft) {
            return $job->draftId === $draft->id;
        });
    }

    public function test_no_tool_list_falls_back_to_carousel_gen(): void
    {
        Queue::fake();
        $draft = $this->makeCarouselDraft();

        $gen = Mockery::mock(LinkedInGenerationService::class);
        $gen->shouldReceive('isRepurposeDraft')->once()->andReturn(true);
        $gen->shouldReceive('sourceSlideCount')->andReturn(null);
        // builder returns empty → must fall through to /carousel-gen
        $gen->shouldReceive('dispatchCarouselGenEngine')->once()->andReturn(null);

        $builder = $this->mock(RepurposeCarouselBuilder::class);
        $builder->shouldReceive('buildForDraftId')
            ->once()
            ->with($draft->id)
            ->andReturn([]);

        (new RegenerateLinkedInCarouselContent($draft->id))
            ->handle($gen, Mockery::mock(CarouselGenOutputAdapter::class));

        // envelope null → job bails before dispatching image render
        Queue::assertNotPushed(GenerateLinkedInCarouselImages::class);
    }

    public function test_flag_off_uses_carousel_gen(): void
    {
        config(['linkedin.repurpose_source_mirror_regenerate' => false]);
        Queue::fake();
        $draft = $this->makeCarouselDraft();

        $gen = Mockery::mock(LinkedInGenerationService::class);
        $gen->shouldReceive('isRepurposeDraft')->once()->andReturn(true);
        $gen->shouldReceive('sourceSlideCount')->andReturn(null);
        $gen->shouldReceive('dispatchCarouselGenEngine')->once()->andReturn(null);

        $builder = $this->mock(RepurposeCarouselBuilder::class);
        $builder->shouldNotReceive('buildForDraftId');

        (new RegenerateLinkedInCarouselContent($draft->id))
            ->handle($gen, Mockery::mock(CarouselGenOutputAdapter::class));
    }

    public function test_non_repurpose_never_calls_source_mirror(): void
    {
        Queue::fake();
        $draft = $this->makeCarouselDraft();

        $gen = Mockery::mock(LinkedInGenerationService::class);
        $gen->shouldReceive('isRepurposeDraft')->once()->andReturn(false);
        $gen->shouldReceive('dispatchCarouselGenEngine')->once()->andReturn(null);

        $builder = $this->mock(RepurposeCarouselBuilder::class);
        $builder->shouldNotReceive('buildForDraftId');

        (new RegenerateLinkedInCarouselContent($draft->id))
            ->handle($gen, Mockery::mock(CarouselGenOutputAdapter::class));
    }

    public function test_job_declares_without_overlapping_middleware(): void
    {
        $mw = (new RegenerateLinkedInCarouselContent(42))->middleware();

        $this->assertNotEmpty($mw, 'middleware() must return at least one entry');
        $this->assertContainsOnlyInstancesOf(
            \Illuminate\Queue\Middleware\WithoutOverlapping::class,
            $mw
        );
    }

    public function test_slide_count_follows_source_tool_count(): void
    {
        Queue::fake();
        $draft = $this->makeCarouselDraft();
        $canned = $this->cannedSlides(12); // cover + 10 tools + cta

        $gen = Mockery::mock(LinkedInGenerationService::class);
        $gen->shouldReceive('isRepurposeDraft')->once()->andReturn(true);
        $gen->shouldNotReceive('dispatchCarouselGenEngine');

        $builder = $this->mock(RepurposeCarouselBuilder::class);
        $builder->shouldReceive('buildForDraftId')
            ->once()
            ->with($draft->id)
            ->andReturn($canned);

        (new RegenerateLinkedInCarouselContent($draft->id))
            ->handle($gen, Mockery::mock(CarouselGenOutputAdapter::class));

        // No 7-cap: all 12 slides persisted
        $this->assertCount(12, $draft->fresh()->carousel_slides);
    }
}
