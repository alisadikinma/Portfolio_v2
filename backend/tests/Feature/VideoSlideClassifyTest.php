<?php

namespace Tests\Feature;

use App\Jobs\ExtractVideoSlides;
use App\Jobs\GenerateRebrandAssets;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoSlideExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase B (#2 source-slide bug) — the vision pass classifies each source video
 * slide as content | source_hook | source_cta. ExtractVideoSlides drops the
 * non-content (the source creator's own hook/cta) tool rows and renumbers the
 * survivors to a contiguous 1..K, recomputing the stepper total.
 *
 * Subclasses the extractor to inject canned vision JSON (no real Claude CLI).
 * See docs/plans/2026-06-13-video-rebrand-quality-pass.md Phase B.
 */
class VideoSlideClassifyTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithTools(int $count): RepurposeJob
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'captured']);
        $job->update(['slides_path' => 'repurpose/' . $job->id]);
        for ($i = 1; $i <= $count; $i++) {
            RepurposeVideoSlide::create([
                'repurpose_job_id' => $job->id,
                'slide_index' => $i,
                'role' => 'tool',
                'poster_path' => 'repurpose/' . $job->id . '/video/slide_' . $i . '.jpg',
                'source_video_path' => 'repurpose/' . $job->id . '/video/slide_' . $i . '.mp4',
            ]);
        }

        return $job;
    }

    /** @param array<int,array<string,mixed>> $slides canned vision slide entries */
    private function bindFakeExtractor(array $slides): void
    {
        $extractor = new class($slides) extends VideoSlideExtractor {
            public function __construct(private array $cannedSlides)
            {
            }

            protected function runVisionParsed(string $prompt): array
            {
                return ['success' => true, 'parsed' => ['slides' => $this->cannedSlides], 'output' => '', 'error' => null, 'repaired' => false];
            }
        };
        $this->app->instance(VideoSlideExtractor::class, $extractor);
    }

    public function test_extract_returns_dropped_non_content_indexes(): void
    {
        $job = $this->jobWithTools(3);

        $svc = new class extends VideoSlideExtractor {
            protected function runVisionParsed(string $prompt): array
            {
                return ['success' => true, 'parsed' => ['slides' => [
                    ['n' => 1, 'kind' => 'source_hook', 'title' => 'Save This', 'desc' => 'Swipe for more'],
                    ['n' => 2, 'kind' => 'content', 'title' => 'Stitch', 'desc' => 'AI design studio'],
                    ['n' => 3, 'kind' => 'content', 'title' => 'Cursor', 'desc' => 'AI pair programmer'],
                ]], 'output' => '', 'error' => null, 'repaired' => false];
            }
        };

        $result = $svc->extract($job);

        $this->assertTrue($result['success']);
        $this->assertSame([1], $result['dropped']);
    }

    public function test_job_drops_source_slides_and_renumbers_contiguously(): void
    {
        Bus::fake([GenerateRebrandAssets::class]);
        Http::fake();

        $job = $this->jobWithTools(3);
        // slide 1 = source creator's own hook → drop; 2 & 3 = real tools → keep.
        $this->bindFakeExtractor([
            ['n' => 1, 'kind' => 'source_hook', 'title' => 'Follow Me', 'desc' => 'Swipe for more'],
            ['n' => 2, 'kind' => 'content', 'title' => 'Stitch', 'desc' => 'AI design studio'],
            ['n' => 3, 'kind' => 'content', 'title' => 'Cursor', 'desc' => 'AI pair programmer'],
        ]);

        (new ExtractVideoSlides($job->id))->handle();

        $tools = $job->videoSlides()->where('role', 'tool')->orderBy('slide_index')->get();
        $this->assertCount(2, $tools, 'dropped source_hook slide should be deleted');
        $this->assertSame([1, 2], $tools->pluck('slide_index')->all(), 'survivors must renumber to contiguous 1..K');
        $this->assertSame(['Stitch', 'Cursor'], $tools->pluck('header_title')->all(), 'survivors keep their content in original order');
        // cta will land at maxToolIndex + 1 = 3
        $this->assertSame(2, (int) $job->videoSlides()->where('role', 'tool')->max('slide_index'));

        $this->assertSame('extracted', $job->refresh()->status);
        Bus::assertDispatched(GenerateRebrandAssets::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_all_dropped_guard_keeps_every_slide(): void
    {
        Bus::fake([GenerateRebrandAssets::class]);
        Http::fake();

        $job = $this->jobWithTools(2);
        // Over-aggressive classification: both flagged non-content.
        $this->bindFakeExtractor([
            ['n' => 1, 'kind' => 'source_hook', 'title' => 'Intro', 'desc' => 'hi'],
            ['n' => 2, 'kind' => 'source_cta', 'title' => 'Outro', 'desc' => 'follow'],
        ]);

        (new ExtractVideoSlides($job->id))->handle();

        $tools = $job->videoSlides()->where('role', 'tool')->orderBy('slide_index')->get();
        $this->assertCount(2, $tools, 'never ship an empty carousel — keep all when all classify non-content');
        $this->assertSame([1, 2], $tools->pluck('slide_index')->all());
        $this->assertSame('extracted', $job->refresh()->status);
    }
}
