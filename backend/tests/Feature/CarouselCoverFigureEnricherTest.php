<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Services\CarouselCoverFigureEnricher;
use App\Services\EntityReferenceService;
use App\Services\VideoHookSceneAuthor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Topic-aware public-figure cover (2026-06-14): for ORIGINAL blog→carousel
 * drafts whose topic is about a public figure, the cover is re-authored as an
 * Ali ↔ figure interaction (figure = license-clean photo, name never in prompt).
 * IG-source (repurpose) carousels are excluded (v3 creator-fronted). Idempotent
 * + fail-safe: authors at most once, any miss/failure leaves the plugin cover.
 */
class CarouselCoverFigureEnricherTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int,array<string,mixed>> $slides */
    private function carouselDraft(array $slides): LinkedInPost
    {
        // Post::factory() needs an explicit category_id (NOT NULL) — build the
        // post chain ourselves rather than lean on the LinkedInPost factory
        // fallback (which creates a category-less Post).
        $post = Post::factory()->create(['category_id' => Category::create(['name' => 'AI & Tech'])->id]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'carousel_slides' => $slides,
        ]);
    }

    private function defaultSlides(): array
    {
        // Real adapter shape: carousel-gen writes copy_id / copy_en, NOT a plain
        // `copy` key (the old fixtures used `copy`, which is why the field-name
        // bug shipped green). Topic here is a person bio, no tools keyword.
        return [
            ['slide_number' => 1, 'layout_hint' => 'cover', 'is_cover' => true, 'copy_id' => 'PERJALANAN SOUMITH CHINTALA', 'image_prompt' => 'plugin creator cover', 'image_status' => 'done', 'image_url' => 'https://cdn/old-cover.png'],
            ['slide_number' => 2, 'layout_hint' => 'body', 'copy_id' => 'VIT Hyderabad menuju New York University', 'image_prompt' => 'sketchnote journey'],
        ];
    }

    /** Repurpose-eligible topic: mentions Tools / Plugins / Skills (AI niche). */
    private function toolsTopicSlides(): array
    {
        return [
            ['slide_number' => 1, 'layout_hint' => 'cover', 'is_cover' => true, 'copy_id' => '7 Plugin Claude yang Soumith Chintala pakai tiap hari', 'image_prompt' => 'plugin creator cover', 'image_status' => 'done', 'image_url' => 'https://cdn/old-cover.png'],
            ['slide_number' => 2, 'layout_hint' => 'body', 'copy_id' => 'Tools coding AI yang bikin kerja makin cepat', 'image_prompt' => 'sketchnote'],
        ];
    }

    private function fakeAuthor(array $return): void
    {
        $this->mock(VideoHookSceneAuthor::class, function ($m) use ($return) {
            $m->shouldReceive('author')->andReturn($return);
        });
    }

    private function authorNeverCalled(): void
    {
        $this->mock(VideoHookSceneAuthor::class, fn ($m) => $m->shouldReceive('author')->never());
    }

    public function test_injects_figure_interaction_on_cover_for_person_topic(): void
    {
        $this->fakeAuthor([
            'success' => true,
            'figure_name' => 'Soumith Chintala',
            'scene_prompt' => 'Creator on the left and the person matching reference image 2 on the right, coding side by side.',
            'error' => null,
        ]);
        $this->mock(EntityReferenceService::class, function ($m) {
            $m->shouldReceive('findOrFetch')->with('Soumith Chintala', 'person')
                ->andReturn(['url' => 'https://cdn/soumith.png', 'entity_type' => 'person']);
        });

        $draft = $this->carouselDraft($this->defaultSlides());
        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertTrue($injected);
        $cover = $draft->fresh()->carousel_slides[0];
        $this->assertSame('https://cdn/soumith.png', $cover['entity_face_ref']);
        $this->assertStringContainsString('reference image 2', $cover['image_prompt']);
        $this->assertSame('Soumith Chintala', $cover['figure_name']);
        $this->assertTrue($cover['figure_enriched']);
        // Force re-render of the previously-done cover.
        $this->assertSame('pending', $cover['image_status']);
        $this->assertNull($cover['image_url']);
    }

    public function test_uses_post_title_as_topic_when_slide_copy_is_empty(): void
    {
        // Real sketchnote draft shape (e.g. prod draft 153): copy_id / copy_en
        // are EMPTY — all headline text is baked into image_prompt. Topic must
        // come from the linked blog Post title, which names the figure.
        $this->fakeAuthor([
            'success' => true,
            'figure_name' => 'Sam Altman',
            'scene_prompt' => 'Creator and the person matching reference image 2 reviewing an S-1 filing.',
            'error' => null,
        ]);
        $this->mock(EntityReferenceService::class, function ($m) {
            $m->shouldReceive('findOrFetch')->with('Sam Altman', 'person')
                ->andReturn(['url' => 'https://cdn/altman.png', 'entity_type' => 'person']);
        });

        $post = Post::factory()->create(['category_id' => Category::create(['name' => 'AI & Tech'])->id]);
        $post->translations()->create([
            'language' => 'id',
            'title' => 'IPO OpenAI: 3 Fakta yang Altman sembunyikan',
            'slug' => 'ipo-openai-altman-' . uniqid(),
            'content' => 'Body.',
        ]);
        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'carousel_slides' => [
                ['slide_number' => 1, 'layout_hint' => 'cover', 'is_cover' => true, 'copy_id' => '', 'copy_en' => '', 'image_prompt' => 'Spotlight Portrait on blue gradient', 'image_status' => 'done', 'image_url' => 'https://cdn/old.png'],
                ['slide_number' => 2, 'layout_hint' => 'body', 'copy_id' => '', 'copy_en' => '', 'image_prompt' => 'sketchnote infographic'],
            ],
        ]);

        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertTrue($injected, 'empty slide copy must fall back to the figure-naming Post title');
        $cover = $draft->fresh()->carousel_slides[0];
        $this->assertSame('https://cdn/altman.png', $cover['entity_face_ref']);
        $this->assertStringContainsString('reference image 2', $cover['image_prompt']);
    }

    public function test_skips_ig_source_repurpose_when_topic_not_tools(): void
    {
        // Repurpose carousel whose topic is NOT tools/plugins/skills → stays
        // creator-only (the default v3 Spotlight). Author never runs.
        $this->authorNeverCalled();
        $draft = $this->carouselDraft($this->defaultSlides());
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id, 'mode' => 'carousel', 'status' => 'drafted']);

        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertFalse($injected);
        $cover = $draft->fresh()->carousel_slides[0];
        $this->assertArrayNotHasKey('entity_face_ref', $cover);
        $this->assertTrue($cover['figure_enriched'], 'IG-source cover is marked resolved so the author never re-runs');
    }

    public function test_injects_figure_on_repurpose_when_topic_is_tools(): void
    {
        // Operator rule (2026-06-15): repurpose carousels ABOUT tools/plugins/
        // skills DO get the figure interaction. Topic mentions "Plugin Claude" +
        // "Tools coding AI" → gate loosens, author runs, figure attaches.
        $this->fakeAuthor([
            'success' => true,
            'figure_name' => 'Soumith Chintala',
            'scene_prompt' => 'Creator and the person matching reference image 2 pairing at a laptop.',
            'error' => null,
        ]);
        $this->mock(EntityReferenceService::class, function ($m) {
            $m->shouldReceive('findOrFetch')->with('Soumith Chintala', 'person')
                ->andReturn(['url' => 'https://cdn/soumith.png', 'entity_type' => 'person']);
        });

        $draft = $this->carouselDraft($this->toolsTopicSlides());
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id, 'mode' => 'carousel', 'status' => 'drafted']);

        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertTrue($injected, 'repurpose + tools topic must loosen the gate and inject the figure');
        $cover = $draft->fresh()->carousel_slides[0];
        $this->assertSame('https://cdn/soumith.png', $cover['entity_face_ref']);
        $this->assertStringContainsString('reference image 2', $cover['image_prompt']);
    }

    public function test_no_figure_for_non_person_topic_leaves_creator_cover(): void
    {
        $this->fakeAuthor(['success' => true, 'figure_name' => null, 'scene_prompt' => 'solo creator', 'error' => null]);
        $this->mock(EntityReferenceService::class, fn ($m) => $m->shouldReceive('findOrFetch')->never());

        $draft = $this->carouselDraft($this->defaultSlides());
        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertFalse($injected);
        $cover = $draft->fresh()->carousel_slides[0];
        $this->assertArrayNotHasKey('entity_face_ref', $cover);
        $this->assertTrue($cover['figure_enriched']);
        $this->assertSame('plugin creator cover', $cover['image_prompt'], 'plugin cover prompt untouched');
    }

    public function test_unresolved_figure_falls_back_to_creator_cover(): void
    {
        $this->fakeAuthor([
            'success' => true,
            'figure_name' => 'Obscure Nobody',
            'scene_prompt' => 'creator + reference image 2',
            'error' => null,
        ]);
        $this->mock(EntityReferenceService::class, function ($m) {
            $m->shouldReceive('findOrFetch')->andReturn(null); // notability/license miss
        });

        $draft = $this->carouselDraft($this->defaultSlides());
        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertFalse($injected);
        $cover = $draft->fresh()->carousel_slides[0];
        $this->assertArrayNotHasKey('entity_face_ref', $cover);
        $this->assertTrue($cover['figure_enriched']);
    }

    public function test_is_idempotent_does_not_reauthor_when_already_enriched(): void
    {
        $this->authorNeverCalled();
        $slides = $this->defaultSlides();
        $slides[0]['figure_enriched'] = true;
        $draft = $this->carouselDraft($slides);

        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertFalse($injected);
    }

    public function test_author_failure_is_not_marked_so_it_retries(): void
    {
        $this->fakeAuthor(['success' => false, 'figure_name' => null, 'scene_prompt' => '', 'error' => 'cli_timeout']);
        $this->mock(EntityReferenceService::class, fn ($m) => $m->shouldReceive('findOrFetch')->never());

        $draft = $this->carouselDraft($this->defaultSlides());
        $injected = app(CarouselCoverFigureEnricher::class)->enrich($draft);

        $this->assertFalse($injected);
        $cover = $draft->fresh()->carousel_slides[0];
        $this->assertArrayNotHasKey('figure_enriched', $cover, 'transient author failure must NOT mark the cover resolved (retry next dispatch)');
    }
}
