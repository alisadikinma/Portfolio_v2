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
        $this->assertStringContainsString('reference image 2', $result['prompt_text']);
        // The single-creator "do not generate a generic person" mandate must NOT
        // fire — it would suppress the second real face.
        $this->assertStringNotContainsString('do not generate a generic person, an avatar, or an icon', $result['prompt_text']);
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
}
