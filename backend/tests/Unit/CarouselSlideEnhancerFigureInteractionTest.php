<?php

namespace Tests\Unit;

use App\Services\CarouselSlideEnhancer;
use Tests\TestCase;

/**
 * Topic-aware public-figure cover (2026-06-14): when CarouselCoverFigureEnricher
 * has set `entity_face_ref` on the cover slide (a license-clean photo of the
 * figure the topic is about), the enhancer must:
 *   - attach that photo as reference image 2 (AFTER the creator = image 1)
 *   - swap the single-creator cover mandate for a TWO-subject interaction mandate
 *
 * Stubbed subclass so no DB / Settings access is needed (mirrors
 * CarouselSlideEnhancerCreatorFaceAttachmentTest).
 */
class CarouselSlideEnhancerFigureInteractionTest extends TestCase
{
    private function enhancer(?string $faceUrl = 'https://cdn.example.com/face.png'): CarouselSlideEnhancer
    {
        return new class($faceUrl) extends CarouselSlideEnhancer {
            public function __construct(private ?string $stubFaceUrl) {}
            public function getCreatorFaceUrl(): ?string { return $this->stubFaceUrl; }
            public function getCreatorBrandLogoUrl(): ?string { return 'https://cdn.example.com/logo.png'; }
            protected function resolveHandle(): string { return '@alisadikinma'; }
            protected function resolvePortfolioUrl(): string { return 'https://alisadikinma.com'; }
        };
    }

    /** @param array<string,mixed> $extra */
    private function coverSlide(array $extra = []): array
    {
        return array_merge([
            'slide_number' => 1,
            'layout_hint' => 'cover',
            'copy' => 'PERJALANAN SOUMITH CHINTALA',
            'image_prompt' => 'Creator on the left and the person matching reference image 2 on the right, coding side by side.',
            'is_cover' => true,
            'is_cta' => false,
        ], $extra);
    }

    public function test_cover_with_entity_face_ref_attaches_figure_as_reference_image_two(): void
    {
        $slide = $this->coverSlide(['entity_face_ref' => 'https://cdn.example.com/figure.png']);

        $result = $this->enhancer()->enhance($slide, 0, 7);

        // Order matters: creator = reference image 1, figure = reference image 2.
        $this->assertSame(
            ['https://cdn.example.com/face.png', 'https://cdn.example.com/figure.png'],
            $result['face_refs'],
            'cover with entity_face_ref must carry [creator, figure] in that order'
        );
    }

    public function test_cover_with_figure_uses_two_subject_mandate(): void
    {
        $slide = $this->coverSlide(['entity_face_ref' => 'https://cdn.example.com/figure.png']);

        $result = $this->enhancer()->enhance($slide, 0, 7);

        $this->assertStringContainsString('PRIMARY SUBJECTS (mandatory): render TWO real people', $result['prompt_text']);
        // The single-creator "do not generate a generic person" mandate must NOT
        // fire — it would suppress the second real face.
        $this->assertStringNotContainsString('do not generate a generic person, an avatar, or an icon', $result['prompt_text']);
    }

    public function test_figure_anchored_by_neutral_filename_not_ordinal(): void
    {
        // The 2-subject cover must bind each face to a NEUTRAL filename
        // (subject-1 / subject-2), not "reference image N". The ordinal anchor
        // rendered the figure as a random person.
        $slide = $this->coverSlide(['entity_face_ref' => 'https://cdn.example.com/figure.png']);

        $result = $this->enhancer()->enhance($slide, 0, 7);

        // Both faces bound by their neutral file handles, in the mandate AND the
        // rewritten body scene ("...matching reference image 2..." → subject-2.png).
        $this->assertStringContainsString('subject-1.png', $result['prompt_text']);
        $this->assertStringContainsString('subject-2.png', $result['prompt_text']);
        // No ordinal "reference image N" anchor survives for the two subjects.
        $this->assertStringNotContainsString('reference image 2', $result['prompt_text']);
        $this->assertStringNotContainsString('reference image 1', $result['prompt_text']);

        // The dispatcher gets ordered aliasing instructions mapping each ref URL
        // to its neutral basename.
        $this->assertSame(
            [
                ['url' => 'https://cdn.example.com/face.png', 'as' => 'subject-1.png'],
                ['url' => 'https://cdn.example.com/figure.png', 'as' => 'subject-2.png'],
            ],
            $result['face_ref_aliases'],
        );
    }

    public function test_figure_filename_carries_no_identity(): void
    {
        // A figure file whose name leaks the person (Q…_sam-altman.jpg) must be
        // aliased to a neutral handle — the name must reach neither the prompt
        // nor the filename GeminiGen sees.
        $slide = $this->coverSlide([
            'entity_face_ref' => 'https://alisadikinma.com/storage/entity-refs/person/Q7407093_sam-altman.jpg',
        ]);

        $result = $this->enhancer()->enhance($slide, 0, 7);

        $this->assertStringNotContainsStringIgnoringCase('sam-altman', $result['prompt_text']);
        $this->assertSame('subject-2.jpg', $result['face_ref_aliases'][1]['as']);
        $this->assertStringContainsString('subject-2.jpg', $result['prompt_text']);
    }

    public function test_single_creator_cover_emits_no_aliases(): void
    {
        $result = $this->enhancer()->enhance($this->coverSlide(), 0, 7);

        $this->assertSame([], $result['face_ref_aliases'],
            'single-creator cover (1 ref) must not trigger neutral aliasing');
    }

    public function test_cover_without_entity_face_ref_stays_single_creator(): void
    {
        $slide = $this->coverSlide(); // no entity_face_ref

        $result = $this->enhancer()->enhance($slide, 0, 7);

        $this->assertSame(['https://cdn.example.com/face.png'], $result['face_refs'],
            'cover without a figure must carry only the creator face');
        $this->assertStringNotContainsString('TWO real people', $result['prompt_text']);
    }

    public function test_figure_not_attached_when_creator_face_unresolvable(): void
    {
        // Defensive: a figure with no creator (image 1) would break the scene's
        // "reference image 2" indexing — skip it rather than render a lone figure.
        $slide = $this->coverSlide(['entity_face_ref' => 'https://cdn.example.com/figure.png']);

        $result = $this->enhancer($faceUrl = null)->enhance($slide, 0, 7);

        $this->assertEmpty($result['face_refs'],
            'no creator face → attach neither creator nor figure');
    }

    public function test_figure_appears_exactly_once_no_duplicates(): void
    {
        $slide = $this->coverSlide(['entity_face_ref' => 'https://cdn.example.com/figure.png']);

        $result = $this->enhancer()->enhance($slide, 0, 7);

        $figureCount = count(array_filter($result['face_refs'], fn ($u) => $u === 'https://cdn.example.com/figure.png'));
        $this->assertSame(1, $figureCount);
    }

    /**
     * Root cause of draft 165 (2026-06-15): the brand logo is a bald-with-
     * glasses FACE icon. GeminiGen has no param to mark a reference as "logo,
     * not face", so it competes as a third identity reference and blends into
     * the creator's likeness on a 2-subject cover (creator's exact face drifts
     * toward a generic bald-glasses everyman). The logo must NOT be sent as a
     * reference on a 2-subject public-figure cover — the brand icon still
     * renders from the brand-chrome text instruction.
     */
    public function test_brand_logo_dropped_on_two_subject_cover(): void
    {
        $slide = $this->coverSlide(['entity_face_ref' => 'https://cdn.example.com/figure.png']);

        $result = $this->enhancer()->enhance($slide, 0, 7);

        $this->assertNotContains('https://cdn.example.com/logo.png', $result['file_urls'],
            '2-subject cover must NOT send the bald-glasses brand logo as a competing face reference');
        $this->assertSame([], $result['file_urls']);
        // The two real faces are untouched — only the logo is dropped.
        $this->assertSame(
            ['https://cdn.example.com/face.png', 'https://cdn.example.com/figure.png'],
            $result['face_refs']
        );
    }

    public function test_brand_logo_kept_on_single_creator_cover(): void
    {
        $slide = $this->coverSlide(); // no entity_face_ref → single creator

        $result = $this->enhancer()->enhance($slide, 0, 7);

        $this->assertContains('https://cdn.example.com/logo.png', $result['file_urls'],
            'single-creator cover keeps the brand logo reference (no competing second face)');
    }
}
