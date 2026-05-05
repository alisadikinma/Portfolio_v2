<?php

namespace Tests\Unit;

use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\LinkedInGenerationService;
use App\Services\PipelineGuard;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for LinkedInGenerationService::buildCarouselCaption — English-only
 * caption mode (May 6, 2026). Plugin v0.6.0+ stops emitting Indonesian text in
 * the carousel caption path; backend caption builder switched from copy_id /
 * headline_id field references to copy_en / headline_en + replaced Indonesian
 * literals (engagement question default, "Yang nggak kelihatan dari permukaan:"
 * insights label, "Swipe → untuk breakdown lengkap." CTA) with English
 * equivalents.
 *
 * Carousel SLIDES themselves stay bilingual (rendered via /carousel-gen brand
 * chrome) — only the LinkedIn POST CAPTION (the body text accompanying the
 * carousel) is now English-only.
 */
class LinkedInGenerationServiceCaptionTest extends TestCase
{
    private function svc(): LinkedInGenerationService
    {
        $guard = Mockery::mock(PipelineGuard::class);
        return new LinkedInGenerationService($guard);
    }

    /**
     * Build a draft with no post relation — forces extractSetupParagraph to
     * fall back to the slide-derived setup (which is what most tests exercise).
     */
    private function draftNoPost(string $format = 'carousel'): LinkedInPost
    {
        $draft = new LinkedInPost(['format' => $format]);
        $draft->setRelation('post', null);
        return $draft;
    }

    public function test_carousel_caption_uses_english_hook_from_copy_en(): void
    {
        $carousel = [
            'slides' => [
                [
                    'is_cover' => true,
                    'layout_hint' => 'cover',
                    'copy_id' => "Penjual ritual instan.\n\nBuku Doa, lilin merah.\n\n3 dari 5 chatbot menjual produk spiritual.",
                    'copy_en' => 'AI chatbot prescribes ritual.',
                ],
                [
                    'is_cover' => false,
                    'layout_hint' => 'human_fingerprint',
                    'copy_id' => 'Slide dua headline ID.',
                    'copy_en' => 'Slide two body line.',
                ],
            ],
        ];
        $brief = ['pull_quote' => '', 'engagement_question' => ''];

        $caption = $this->svc()->buildCarouselCaption('', $carousel, $brief, $this->draftNoPost());

        $this->assertStringContainsString('AI chatbot prescribes ritual.', $caption,
            'English hook from cover.copy_en should appear in caption');
        $this->assertStringNotContainsString('Penjual ritual instan', $caption,
            'Indonesian copy_id paragraph 1 must NOT leak into caption');
    }

    public function test_carousel_caption_drops_subtitle_block_to_avoid_duplication(): void
    {
        // Pre-fix: copy_en was used as the subtitle block (separate from hook
        // sourced from copy_id). After the EN switch, copy_en IS the hook —
        // re-emitting it as subtitle would duplicate.
        $carousel = [
            'slides' => [
                ['is_cover' => true, 'copy_en' => 'Cover hook line.'],
                ['is_cover' => false, 'layout_hint' => 'human_fingerprint', 'copy_en' => 'Body slide content here.'],
            ],
        ];
        $brief = ['pull_quote' => '', 'engagement_question' => ''];

        $caption = $this->svc()->buildCarouselCaption('', $carousel, $brief, $this->draftNoPost());

        // Hook should appear exactly ONCE — count occurrences
        $occurrences = mb_substr_count($caption, 'Cover hook line.');
        $this->assertSame(1, $occurrences,
            'Cover copy_en must appear exactly once (no separate subtitle block)');
    }

    public function test_engagement_question_english_default(): void
    {
        $carousel = ['slides' => [
            ['is_cover' => true, 'copy_en' => 'Hook.'],
        ]];
        // Plugin omitted engagement_question — backend should fall back to English literal
        $brief = ['pull_quote' => '', 'engagement_question' => ''];

        $caption = $this->svc()->buildCarouselCaption('', $carousel, $brief, $this->draftNoPost());

        $this->assertStringContainsString("What's really happening behind the scenes?", $caption);
        $this->assertStringNotContainsString('Apa yang sebenarnya terjadi di balik layar', $caption);
    }

    public function test_swipe_cta_is_english(): void
    {
        $carousel = ['slides' => [['is_cover' => true, 'copy_en' => 'Hook.']]];
        $brief = ['pull_quote' => '', 'engagement_question' => ''];

        $caption = $this->svc()->buildCarouselCaption('', $carousel, $brief, $this->draftNoPost());

        $this->assertStringContainsString('Swipe → for the full breakdown.', $caption);
        $this->assertStringNotContainsString('Swipe → untuk breakdown lengkap', $caption);
        $this->assertStringNotContainsString('untuk breakdown', $caption);
    }

    public function test_insights_label_is_english(): void
    {
        // Two body slides with sharp headlines → triggers the insights bullet
        // block, which has its own label literal.
        $carousel = ['slides' => [
            ['is_cover' => true, 'copy_en' => 'Cover hook.'],
            ['is_cover' => false, 'layout_hint' => 'human_fingerprint',
             'headline_en' => 'First insight headline.', 'copy_en' => 'First insight body line.'],
            ['is_cover' => false, 'layout_hint' => 'human_fingerprint',
             'headline_en' => 'Second insight headline.', 'copy_en' => 'Second insight body line.'],
        ]];
        $brief = ['pull_quote' => '', 'engagement_question' => ''];

        $caption = $this->svc()->buildCarouselCaption('', $carousel, $brief, $this->draftNoPost());

        $this->assertStringContainsString("What you can't see from the surface:", $caption);
        $this->assertStringNotContainsString('Yang nggak kelihatan dari permukaan', $caption);
        // English insights themselves should appear
        $this->assertStringContainsString('First insight headline.', $caption);
    }

    public function test_setup_falls_back_to_slide_copy_en_when_no_post_translation(): void
    {
        // No post relation → must fall back to non-cover slide content for setup.
        // Pre-fix: setup pulled from copy_id (Indonesian). Post-fix: copy_en.
        $carousel = ['slides' => [
            ['is_cover' => true, 'copy_en' => 'Hook.'],
            [
                'is_cover' => false,
                'layout_hint' => 'human_fingerprint',
                'copy_id' => 'Paragraf bahasa Indonesia di sini yang panjangnya cukup untuk lewat 60 karakter sehingga lolos dari filter setup paragraph.',
                'copy_en' => 'English body paragraph here that is long enough to pass the 60 character minimum filter for setup.',
            ],
        ]];
        $brief = ['pull_quote' => '', 'engagement_question' => ''];

        $caption = $this->svc()->buildCarouselCaption('', $carousel, $brief, $this->draftNoPost());

        $this->assertStringContainsString('English body paragraph', $caption,
            'Setup paragraph must be sourced from slide.copy_en when no EN post translation exists');
        $this->assertStringNotContainsString('Paragraf bahasa Indonesia', $caption,
            'Indonesian copy_id must NOT be used for setup paragraph');
    }

    /**
     * Build a post with translations[language=>{title,content}] for buildBlogPayload tests.
     * Uses real Eloquent models with setRelation (no DB) — same pattern as draftNoPost().
     */
    private function postWithTranslations(array $byLanguage, string $slug = 'test-post'): Post
    {
        $translations = collect();
        foreach ($byLanguage as $lang => $body) {
            $t = new PostTranslation([
                'language' => $lang,
                'title' => $body['title'] ?? "{$lang} title",
                'content' => $body['content'] ?? str_repeat("{$lang} body paragraph. ", 20),
            ]);
            $translations->push($t);
        }
        $post = new Post(['slug' => $slug]);
        $post->setRelation('translations', $translations);
        return $post;
    }

    public function test_build_blog_payload_prefers_english_translation_when_available(): void
    {
        $post = $this->postWithTranslations([
            'id' => ['title' => 'Judul Indonesia', 'content' => str_repeat('Konten bahasa Indonesia di sini. ', 10)],
            'en' => ['title' => 'English Title', 'content' => str_repeat('English content body here. ', 10)],
        ]);
        $draft = new LinkedInPost(['format' => 'text']);
        $draft->setRelation('post', $post);

        $payload = $this->svc()->buildBlogPayload($draft);

        $this->assertIsArray($payload);
        $this->assertSame('English Title', $payload['title']);
        $this->assertStringContainsString('English content', $payload['content']);
        $this->assertStringNotContainsString('Konten bahasa Indonesia', $payload['content']);
    }

    public function test_build_blog_payload_falls_back_to_indonesian_when_english_missing(): void
    {
        $post = $this->postWithTranslations([
            'id' => ['title' => 'Judul Indonesia', 'content' => str_repeat('Konten bahasa Indonesia. ', 10)],
        ]);
        $draft = new LinkedInPost(['format' => 'text']);
        $draft->setRelation('post', $post);

        $payload = $this->svc()->buildBlogPayload($draft);

        $this->assertIsArray($payload);
        $this->assertSame('Judul Indonesia', $payload['title']);
        $this->assertStringContainsString('Konten bahasa Indonesia', $payload['content']);
    }

    public function test_build_blog_payload_returns_null_when_no_translations(): void
    {
        $post = new Post(['slug' => 'orphan']);
        $post->setRelation('translations', collect());
        $draft = new LinkedInPost(['format' => 'text']);
        $draft->setRelation('post', $post);

        $payload = $this->svc()->buildBlogPayload($draft);

        $this->assertNull($payload);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
