<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RegenerateLinkedInCarouselContent;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * The admin "Regenerate all images" button (RegenerateLinkedInCarouselContent)
 * must re-author through /carousel-gen with the SAME knobs as the auto generate
 * path: resolve the visual style from the operator setting and detect
 * IG-repurpose drafts (so they get --narrative=free, not 5act). Earlier it
 * called dispatchCarouselGenEngine with 3 args, defaulting isRepurpose=false
 * and ignoring linkedin_carousel_style.
 */
class RegenerateLinkedInCarouselContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://test.alisadikinma.com']);
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

    public function test_passes_repurpose_flag_and_sketchnote_style_through(): void
    {
        $draft = $this->makeCarouselDraft();

        $gen = Mockery::mock(LinkedInGenerationService::class);
        $gen->shouldReceive('isRepurposeDraft')->once()->andReturn(true);
        $gen->shouldReceive('dispatchCarouselGenEngine')
            ->once()
            ->withArgs(function ($brief, $blogUrl, $draftId, $blogContent, $isRepurpose, $style) {
                return $isRepurpose === true && $style === 'sketchnote';
            })
            ->andReturn(null); // null → job bails before adapter/image dispatch

        (new RegenerateLinkedInCarouselContent($draft->id))
            ->handle($gen, Mockery::mock(CarouselGenOutputAdapter::class));
    }

    public function test_honors_cinematic_style_setting_and_non_repurpose(): void
    {
        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_carousel_style'],
            ['value' => 'cinematic']
        );
        $draft = $this->makeCarouselDraft();

        $gen = Mockery::mock(LinkedInGenerationService::class);
        $gen->shouldReceive('isRepurposeDraft')->once()->andReturn(false);
        $gen->shouldReceive('dispatchCarouselGenEngine')
            ->once()
            ->withArgs(function ($brief, $blogUrl, $draftId, $blogContent, $isRepurpose, $style) {
                return $isRepurpose === false && $style === 'cinematic';
            })
            ->andReturn(null);

        (new RegenerateLinkedInCarouselContent($draft->id))
            ->handle($gen, Mockery::mock(CarouselGenOutputAdapter::class));
    }
}
