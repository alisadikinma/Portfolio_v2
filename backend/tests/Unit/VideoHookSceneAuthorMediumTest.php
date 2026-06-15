<?php

namespace Tests\Unit;

use App\Services\VideoHookSceneAuthor;
use Tests\TestCase;

/**
 * The shared scene author (reused for both the video keyframe AND the carousel
 * cover, 2026-06-14) frames its prompt per medium. 'video' (default) stays
 * byte-identical to the original 9:16 keyframe behaviour; 'carousel_cover'
 * reframes for a 4:5 still and threads the headline through so replacing a cover
 * image_prompt never loses the headline copy.
 */
class VideoHookSceneAuthorMediumTest extends TestCase
{
    /** Capture the exact prompt handed to the CLI without a real Claude call. */
    private function capturingAuthor(): VideoHookSceneAuthor
    {
        return new class extends VideoHookSceneAuthor {
            public string $captured = '';

            protected function runHookAuthor(string $prompt): array
            {
                $this->captured = $prompt;

                return ['success' => true, 'parsed' => ['figure_name' => null, 'scene_prompt' => 'a solo creator portrait'], 'output' => '', 'error' => null, 'repaired' => false];
            }
        };
    }

    public function test_default_medium_is_video_nine_by_sixteen(): void
    {
        $svc = $this->capturingAuthor();
        $svc->author('AI tools roundup');

        $this->assertStringContainsString('9:16 vertical', $svc->captured);
        $this->assertStringContainsString('animated by a video model', $svc->captured);
    }

    public function test_carousel_cover_medium_reframes_for_still_four_by_five(): void
    {
        $svc = $this->capturingAuthor();
        $svc->author('Perjalanan Soumith Chintala', true, 'carousel_cover', 'PERJALANAN SOUMITH CHINTALA');

        $this->assertStringContainsString('COVER (slide 1)', $svc->captured);
        $this->assertStringContainsString('4:5 portrait', $svc->captured);
        // Headline threaded in so a cover-prompt replacement keeps the copy.
        $this->assertStringContainsString('PERJALANAN SOUMITH CHINTALA', $svc->captured);
        // A still cover must not carry the video-only "animated" framing.
        $this->assertStringNotContainsString('animated by a video model', $svc->captured);
    }

    public function test_carousel_cover_without_headline_still_valid(): void
    {
        $svc = $this->capturingAuthor();
        $res = $svc->author('Some non-person topic', true, 'carousel_cover', null);

        $this->assertTrue($res['success']);
        $this->assertStringContainsString('4:5 portrait', $svc->captured);
    }

    public function test_carousel_cover_with_base_prompt_switches_to_subject_rewrite(): void
    {
        // When the carousel-gen cover prompt is supplied as the base, the author
        // must PRESERVE it (headline + floating cards) and change only the subject
        // — not author a fresh bare scene ("hanya subject yg berubah").
        $base = 'A Spotlight cover: the creator centered, bilingual headline "IPO OPENAI: 3 FAKTA" '
            . 'plus floating cards OpenAI S-1 FILING, OPERATING MARGIN -122%, $1T VALUATION. {{PAGE_INDICATOR}} {{HANDLE}}';

        $svc = $this->capturingAuthor();
        $svc->author('IPO OpenAI: 3 fakta yang Altman sembunyikan', true, 'carousel_cover', 'IPO OPENAI: 3 FAKTA', $base);

        // The base prompt is embedded verbatim for the model to edit in place.
        $this->assertStringContainsString($base, $svc->captured);
        // Preservation rules present; only the subject may change.
        $this->assertStringContainsString('change NOTHING except the human-subject', $svc->captured);
        $this->assertStringContainsString('Keep EVERY floating element EXACTLY', $svc->captured);
        $this->assertStringContainsString('the person matching reference image 2', $svc->captured);
        // It must NOT fall back to the fresh-scene framing.
        $this->assertStringNotContainsString('animated by a video model', $svc->captured);
    }

    public function test_carousel_cover_without_base_prompt_keeps_fresh_scene_framing(): void
    {
        // No base → original behaviour (author a fresh cover scene).
        $svc = $this->capturingAuthor();
        $svc->author('Perjalanan Soumith Chintala', true, 'carousel_cover', 'PERJALANAN', null);

        $this->assertStringContainsString('4:5 portrait', $svc->captured);
        $this->assertStringNotContainsString('change NOTHING except the human-subject', $svc->captured);
    }
}
