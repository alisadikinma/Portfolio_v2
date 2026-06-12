<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoCarouselCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B — yt-dlp video carousel capture. The service execs a node wrapper
 * (ig-video-capture.cjs) that yt-dlp-downloads each carousel slide mp4 +
 * ffmpeg poster + ffprobe metadata, emitting one JSON line. The service maps
 * that into repurpose_video_slides rows (role=tool) and sets slides_path.
 *
 * Tests subclass the service to inject canned wrapper output (no real download).
 * See docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase B.
 */
class VideoCarouselCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function fakeService(array $payload): VideoCarouselCaptureService
    {
        return new class($payload) extends VideoCarouselCaptureService {
            public function __construct(private array $cannedPayload)
            {
                parent::__construct();
            }

            protected function runCapture(string $url, string $outDir): array
            {
                return ['stdout' => json_encode($this->cannedPayload), 'stderr' => '', 'exit' => 0];
            }
        };
    }

    public function test_creates_tool_slide_rows_from_capture_json(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'capturing',
            'source_url' => 'https://www.instagram.com/p/DZeWg-4FB19/',
        ]);

        $svc = $this->fakeService([
            'ok' => true,
            'count' => 3,
            'slides' => [
                ['file' => 'slide_1.mp4', 'poster' => 'slide_1.jpg', 'width' => 720, 'height' => 900, 'duration' => 8.0, 'has_audio' => true, 'crop_y' => 338, 'crop_h' => 406],
                ['file' => 'slide_2.mp4', 'poster' => 'slide_2.jpg', 'width' => 720, 'height' => 900, 'duration' => 7.5, 'has_audio' => true, 'crop_y' => 340, 'crop_h' => 406],
                ['file' => 'slide_3.mp4', 'poster' => 'slide_3.jpg', 'width' => 720, 'height' => 900, 'duration' => 9.0, 'has_audio' => false, 'crop_y' => 339, 'crop_h' => 404],
            ],
            'error' => null,
        ]);

        $result = $svc->capture($job);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['count']);

        $slides = $job->videoSlides()->get();
        $this->assertCount(3, $slides);
        // tool slides indexed 1..N (0 reserved for the Veo hook prepended in Phase E)
        $this->assertSame([1, 2, 3], $slides->pluck('slide_index')->all());
        $this->assertSame(['tool', 'tool', 'tool'], $slides->pluck('role')->all());

        $first = $slides->first();
        $this->assertStringContainsString('repurpose/' . $job->id . '/video/slide_1.mp4', $first->source_video_path);
        $this->assertStringContainsString('repurpose/' . $job->id . '/video/slide_1.jpg', $first->poster_path);
        $this->assertSame('pending', $first->composited_status);
        // center 16:9 band persisted from capture-time luminance detection
        $this->assertSame(338, $first->crop_y);
        $this->assertSame(406, $first->crop_h);

        $this->assertSame('repurpose/' . $job->id, $job->fresh()->slides_path);
    }

    public function test_fails_on_zero_slides(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'capturing',
            'source_url' => 'https://www.instagram.com/p/DZeWg-4FB19/',
        ]);

        $svc = $this->fakeService(['ok' => false, 'count' => 0, 'slides' => [], 'error' => 'no_video_items']);
        $result = $svc->capture($job);

        $this->assertFalse($result['success']);
        $this->assertSame('no_video_items', $result['error']);
        $this->assertSame(0, $job->videoSlides()->count());
    }

    public function test_rejects_non_instagram_host(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'capturing',
            'source_url' => 'https://evil.example.com/p/DZeWg-4FB19/',
        ]);

        $result = $svc = $this->fakeService(['ok' => true, 'count' => 1, 'slides' => [['file' => 'x.mp4', 'poster' => 'x.jpg']], 'error' => null])->capture($job);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_url_host', $result['error']);
        $this->assertSame(0, $job->videoSlides()->count());
    }
}
