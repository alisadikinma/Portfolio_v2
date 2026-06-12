<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoSlideExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C — vision extract of each source tool slide's header title + desc (the
 * tool name + blurb shown in the source slide's header band), so they can be
 * re-rendered in Ali's brand chrome. The center 16:9 crop band is already set at
 * capture (luminance), so this step is title/desc only.
 *
 * Subclasses the extractor to inject canned vision JSON (no real Claude CLI).
 * See docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase C.
 */
class VideoSlideExtractTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithToolSlides(): RepurposeJob
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'extracting',
            'slides_path' => null,
        ]);
        $job->update(['slides_path' => 'repurpose/' . $job->id]);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'poster_path' => 'repurpose/' . $job->id . '/video/slide_1.jpg']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'poster_path' => 'repurpose/' . $job->id . '/video/slide_2.jpg']);

        return $job;
    }

    private function fakeExtractor(array $parsed): VideoSlideExtractor
    {
        return new class($parsed) extends VideoSlideExtractor {
            public function __construct(private array $cannedParsed)
            {
            }

            protected function runVisionParsed(string $prompt): array
            {
                return ['success' => true, 'parsed' => $this->cannedParsed, 'output' => '', 'error' => null, 'repaired' => false];
            }
        };
    }

    public function test_sets_header_title_and_desc_per_slide(): void
    {
        $job = $this->jobWithToolSlides();

        $svc = $this->fakeExtractor([
            'slides' => [
                ['n' => 1, 'title' => 'Stitch', 'desc' => 'AI design studio that builds your screen layout from plain text.'],
                ['n' => 2, 'title' => 'Cursor', 'desc' => 'AI pair-programmer in your editor.'],
            ],
        ]);

        $result = $svc->extract($job);

        $this->assertTrue($result['success']);

        $slides = $job->videoSlides()->get();
        $this->assertSame('Stitch', $slides[0]->header_title);
        $this->assertStringContainsString('design studio', $slides[0]->header_desc);
        $this->assertSame('Cursor', $slides[1]->header_title);
    }

    public function test_fails_loudly_on_vision_failure(): void
    {
        $job = $this->jobWithToolSlides();

        $svc = new class extends VideoSlideExtractor {
            protected function runVisionParsed(string $prompt): array
            {
                return ['success' => false, 'parsed' => null, 'output' => '', 'error' => 'unparseable_after_repair', 'repaired' => true];
            }
        };

        $result = $svc->extract($job);

        $this->assertFalse($result['success']);
        $this->assertSame('vision_unparseable', $result['error']);
        $this->assertNull($job->videoSlides()->first()->header_title);
    }
}
