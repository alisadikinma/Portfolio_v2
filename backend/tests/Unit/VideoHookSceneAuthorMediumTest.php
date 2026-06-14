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
}
