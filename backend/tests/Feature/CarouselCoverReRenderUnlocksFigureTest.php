<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\CarouselCoverFigureEnricher;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A single-slide "Re-render image" on the COVER must be able to attach the
 * topic-aware public figure even when the cover was previously resolved as
 * creator-only (carousel_slides[cover].figure_enriched === true). Without this,
 * the only way to gain the figure is a full, token-heavy "Regenerate All Images".
 *
 * The CarouselCoverFigureEnricher is stubbed to a no-op so the test isolates the
 * lock-reset gate (no SSH author / Wikidata in CI).
 */
class CarouselCoverReRenderUnlocksFigureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('services.geminigen.linkedin_carousel_model', 'nano-banana-pro');
        Config::set('services.article_generation.use_safety_rewrite', false);

        // Stub the enricher — it must NOT re-set figure_enriched, so the test can
        // assert the reset gate ran. (Real enricher needs SSH + Wikidata.)
        $this->app->instance(CarouselCoverFigureEnricher::class, new class extends CarouselCoverFigureEnricher
        {
            public function __construct()
            {
            }

            public function enrich(LinkedInPost $draft): bool
            {
                return false;
            }
        });

        // GeminiGen dispatch always "succeeds" with a UUID so dispatchSingleSlide
        // completes and flips the slide to 'generating'.
        Http::fake([
            '*generate_image*' => Http::response(['uuid' => 'fake-uuid-123', 'status' => 1], 200),
        ]);
    }

    private function makeDraft(): LinkedInPost
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-' . uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'test-post-' . uniqid(),
            'title' => 'Test Post',
            'content' => 'Test content body.',
        ]);

        return LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'Test caption.',
            'status' => LinkedInPostStatus::ManualReview->value,
            'pipeline_state_log' => [],
            'hashtags' => [],
            'carousel_slides' => [
                [
                    'slide_number' => 1,
                    'layout_hint' => 'cover',
                    'is_cover' => true,
                    'copy_id' => 'Cover headline',
                    'image_prompt' => 'A clean modern photograph.',
                    'image_status' => 'done',
                    'figure_enriched' => true, // locked creator-only by a prior run
                ],
                [
                    'slide_number' => 2,
                    'layout_hint' => 'body',
                    'copy_id' => 'Body copy',
                    'image_prompt' => 'A workspace photograph.',
                    'image_status' => 'done',
                ],
            ],
        ]);
    }

    /** @test */
    public function re_rendering_the_cover_clears_the_figure_enriched_lock(): void
    {
        $draft = $this->makeDraft();

        app(LinkedInCarouselImageService::class)->dispatchSingleSlide($draft, 0);

        $draft->refresh();
        $cover = $draft->carousel_slides[0];

        $this->assertArrayNotHasKey('figure_enriched', $cover, 'cover figure_enriched lock must be cleared so the enricher re-runs');
        $this->assertSame('generating', $cover['image_status']);
    }

    /** @test */
    public function re_rendering_an_already_enriched_cover_keeps_the_figure_and_does_not_reset(): void
    {
        // A cover that already carries a resolved figure must NOT have its lock
        // cleared — re-render uses the existing interaction prompt + figure ref,
        // no redundant (slow) re-author.
        $draft = $this->makeDraft();
        $slides = $draft->carousel_slides;
        $slides[0]['entity_face_ref'] = 'https://cdn/altman.png';
        $slides[0]['figure_name'] = 'Sam Altman';
        $draft->update(['carousel_slides' => $slides]);

        app(LinkedInCarouselImageService::class)->dispatchSingleSlide($draft, 0);

        $draft->refresh();
        $cover = $draft->carousel_slides[0];
        $this->assertTrue($cover['figure_enriched'], 'enriched cover lock must be preserved');
        $this->assertSame('https://cdn/altman.png', $cover['entity_face_ref']);
        $this->assertSame('generating', $cover['image_status']);
    }

    /** @test */
    public function re_rendering_a_body_slide_leaves_the_cover_lock_intact(): void
    {
        $draft = $this->makeDraft();

        app(LinkedInCarouselImageService::class)->dispatchSingleSlide($draft, 1);

        $draft->refresh();

        $this->assertTrue($draft->carousel_slides[0]['figure_enriched'], 'body re-render must not touch the cover lock');
        $this->assertSame('generating', $draft->carousel_slides[1]['image_status']);
    }
}
