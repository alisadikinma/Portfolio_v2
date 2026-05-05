<?php

namespace Tests\Unit;

use App\Services\CarouselSlideEnhancer;
use Tests\TestCase;

/**
 * Regression tests for the rule introduced May 6, 2026:
 *
 *   "Step manapun klo ada muka creator pastikan attach reference image."
 *
 * The bug: the carousel-gen plugin authors slides under 5 layout_hint enums
 * (cover / body / human_fingerprint / direct_answer / cta) but the SKILL.md
 * slide-role taxonomy includes Foreshadow, Loop-end, B-roll-with-humans —
 * Sonnet routinely emits those as `body` layouts whose prompts depict the
 * creator. The previous FACE_REQUIRED_LAYOUTS allow-list (cover /
 * human_fingerprint / cta) dropped face_refs on body slides, so GeminiGen
 * rendered a generic face instead of the creator's likeness.
 *
 * Fix: detect creator references in the prompt body and auto-attach the
 * creator face URL regardless of layout_hint. Hook + CTA + Cover continue
 * to attach unconditionally (layout-mandated).
 *
 * These tests use a stubbed enhancer subclass so they don't need DB access
 * — the enhancer's two URL resolvers are the only points that touch the
 * Settings table, and they're public so we can override them cleanly.
 */
class CarouselSlideEnhancerCreatorFaceAttachmentTest extends TestCase
{
    private function enhancer(?string $faceUrl = 'https://cdn.example.com/face.png', ?string $logoUrl = 'https://cdn.example.com/logo.png'): CarouselSlideEnhancer
    {
        return new class($faceUrl, $logoUrl) extends CarouselSlideEnhancer {
            public function __construct(private ?string $stubFaceUrl, private ?string $stubLogoUrl) {}
            public function getCreatorFaceUrl(): ?string { return $this->stubFaceUrl; }
            public function getCreatorBrandLogoUrl(): ?string { return $this->stubLogoUrl; }
            protected function resolveHandle(): string { return '@alisadikinma'; }
            protected function resolvePortfolioUrl(): string { return 'https://alisadikinma.com'; }
        };
    }

    private function slide(string $layoutHint, string $prompt, bool $isCover = false, bool $isCta = false): array
    {
        return [
            'slide_number' => 2,
            'layout_hint' => $layoutHint,
            'copy' => 'irrelevant',
            'image_prompt' => $prompt,
            'is_cover' => $isCover,
            'is_cta' => $isCta,
        ];
    }

    public function test_body_layout_attaches_face_when_prompt_mentions_creator(): void
    {
        // The exact failure mode from the May 6 incident: slide 2 of 7, layout
        // body, prompt describes the creator's face — should attach face_refs.
        $slide = $this->slide(
            'body',
            "Creator in a dark minimal workspace, palm raised toward camera in a 'stop' gesture. Creator's urgent face lit by cold phone-screen key. Background: dark exposed brick wall."
        );

        $result = $this->enhancer()->enhance($slide, 1, 7);

        $this->assertContains('https://cdn.example.com/face.png', $result['face_refs'],
            'body slide describing the creator must have face_refs attached (foreshadow/B-roll-with-humans rule)');
    }

    public function test_body_layout_skips_face_when_prompt_has_no_creator_reference(): void
    {
        // Pure B-roll with no humans (per SKILL.md hard rule #4) — no face.
        $slide = $this->slide(
            'body',
            'Wide shot of a server rack at night, blue LED indicators pulsing rhythmically. Cold blue hardware lighting, no human figures in frame. Macro lens detail of the cabling.'
        );

        $result = $this->enhancer()->enhance($slide, 1, 7);

        $this->assertEmpty($result['face_refs'],
            'pure B-roll body slide without creator references should NOT attach face_refs');
    }

    public function test_direct_answer_layout_attaches_face_when_prompt_mentions_creator(): void
    {
        $slide = $this->slide(
            'direct_answer',
            'The creator stands at a whiteboard pointing at a chart, creator face partially in profile, warm desk lamp lighting. Tight medium shot.'
        );

        $result = $this->enhancer()->enhance($slide, 3, 7);

        $this->assertContains('https://cdn.example.com/face.png', $result['face_refs']);
    }

    public function test_cover_always_attaches_face_even_without_creator_token_in_prompt(): void
    {
        // Layout-mandated path — face attaches regardless of prompt content.
        $slide = $this->slide(
            'cover',
            'Abstract neural network nodes glowing amber on dark canvas, no human figures. Editorial cinematic lighting.',
            isCover: true
        );

        $result = $this->enhancer()->enhance($slide, 0, 7);

        $this->assertContains('https://cdn.example.com/face.png', $result['face_refs'],
            'cover layout must attach face_refs unconditionally (FACE_REQUIRED_LAYOUTS)');
    }

    public function test_cta_always_attaches_face(): void
    {
        $slide = $this->slide(
            'cta',
            'Closing screen with social handles and portfolio URL.',
            isCta: true
        );

        $result = $this->enhancer()->enhance($slide, 6, 7);

        $this->assertContains('https://cdn.example.com/face.png', $result['face_refs']);
    }

    public function test_creator_placeholder_substitution_triggers_face_attachment(): void
    {
        // Plugin opted in via {{CREATOR_FACE}} — replacePlaceholders converts
        // it to the literal marker, which our regex matches.
        $slide = $this->slide(
            'body',
            'Tight medium shot of {{CREATOR_FACE}} reading a phone screen, urgent expression, dim ambient lighting.'
        );

        $result = $this->enhancer()->enhance($slide, 4, 7);

        $this->assertContains('https://cdn.example.com/face.png', $result['face_refs'],
            '{{CREATOR_FACE}} placeholder substitution must result in face_refs attachment');
    }

    public function test_face_attachment_is_idempotent_no_duplicate_urls(): void
    {
        // Layout-mandated AND prompt mentions creator — must not produce dupes.
        $slide = $this->slide(
            'human_fingerprint',
            "The creator's face is the focal point of this scene, lit by warm key light."
        );

        $result = $this->enhancer()->enhance($slide, 2, 7);

        $faceCount = count(array_filter($result['face_refs'],
            fn($url) => $url === 'https://cdn.example.com/face.png'));

        $this->assertSame(1, $faceCount,
            'face URL must appear exactly once even when both layout-mandate and prompt-sniff paths trigger');
    }

    public function test_no_face_attachment_when_url_unresolvable(): void
    {
        // Settings missing — log a warning but don't blow up.
        $slide = $this->slide(
            'body',
            'Creator pointing at the camera, urgent expression.'
        );

        $result = $this->enhancer($faceUrl = null)->enhance($slide, 1, 7);

        $this->assertEmpty($result['face_refs'],
            'unresolvable face URL must produce empty face_refs (warning logged separately)');
    }

    public function test_body_layout_with_creator_prompt_prepends_primary_subject_mandate(): void
    {
        // Without the mandate paragraph, GeminiGen sometimes treats face_refs
        // as decorative and renders a generic figure anyway.
        $slide = $this->slide(
            'body',
            'Creator stands beside a server rack, hands held up in a defensive gesture, urgent expression.'
        );

        $result = $this->enhancer()->enhance($slide, 1, 7);

        $this->assertStringContainsString(
            'PRIMARY SUBJECT',
            $result['prompt_text'],
            'body slide depicting creator must receive PRIMARY SUBJECT mandate prose so GeminiGen treats face_refs as the hero, not decoration'
        );
        $this->assertStringContainsString(
            'face reference image',
            $result['prompt_text']
        );
    }

    public function test_body_layout_without_creator_prompt_does_not_get_mandate(): void
    {
        // Pure B-roll — no creator token, no mandate prepended.
        $slide = $this->slide(
            'body',
            'Wide shot of a server rack at night, blue LED indicators pulsing.'
        );

        $result = $this->enhancer()->enhance($slide, 1, 7);

        $this->assertStringNotContainsString(
            'PRIMARY SUBJECT (mandatory): the creator depicted in this scene',
            $result['prompt_text'],
            'pure B-roll body slide must NOT receive creator mandate (would force a generic face into a no-humans scene)'
        );
    }
}
