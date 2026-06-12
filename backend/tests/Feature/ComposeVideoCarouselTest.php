<?php

namespace Tests\Feature;

use App\Jobs\ComposeVideoCarousel;
use App\Jobs\FinalizeRepurpose;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoChromeRenderer;
use App\Services\VideoRebrandComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Phase F — ComposeVideoCarousel re-skins the source `tool` slides into Ali's
 * brand chrome (header+footer PNG → ffmpeg vstack). Hook/CTA bookends are already
 * final clips (composited at the Veo finalize step), so only tool slides composite
 * here. All done → Composed → FinalizeRepurpose; any failure → Failed.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase F
 */
class ComposeVideoCarouselTest extends TestCase
{
    use RefreshDatabase;

    private function jobReadyToCompose(): RepurposeJob
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'assets_ready']);
        // bookends already final
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'h.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'source_video_path' => 's1.mp4', 'crop_y' => 338, 'crop_h' => 406]);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'cta', 'composited_status' => 'done', 'composited_path' => 'c.mp4']);

        return $job;
    }

    public function test_composes_tool_slides_then_advances_to_composed_and_finalizes(): void
    {
        Bus::fake();
        $job = $this->jobReadyToCompose();

        $chrome = $this->mock(VideoChromeRenderer::class, function ($m) {
            $m->shouldReceive('renderSlide')->once()->andReturn(['header' => 'hdr.png', 'footer' => 'ftr.png']);
        });
        $composer = $this->mock(VideoRebrandComposer::class, function ($m) {
            $m->shouldReceive('composeSlide')->once()->andReturnUsing(function ($slide) {
                $slide->update(['composited_status' => 'done', 'composited_path' => 'tool.mp4']);
                return 'tool.mp4';
            });
        });

        (new ComposeVideoCarousel($job->id))->handle($chrome, $composer);

        $job->refresh();
        $this->assertSame('composed', $job->status);
        Bus::assertDispatched(FinalizeRepurpose::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_compose_failure_fails_job(): void
    {
        Bus::fake();
        $job = $this->jobReadyToCompose();

        $chrome = $this->mock(VideoChromeRenderer::class, function ($m) {
            $m->shouldReceive('renderSlide')->once()->andReturn(null); // chrome render fails
        });
        $composer = $this->mock(VideoRebrandComposer::class, function ($m) {
            $m->shouldReceive('composeSlide')->never();
        });

        (new ComposeVideoCarousel($job->id))->handle($chrome, $composer);

        $job->refresh();
        $this->assertSame('failed', $job->status);
        Bus::assertNotDispatched(FinalizeRepurpose::class);
    }

    public function test_noop_when_not_assets_ready(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'composed']);

        $chrome = $this->mock(VideoChromeRenderer::class, fn ($m) => $m->shouldReceive('renderSlide')->never());
        $composer = $this->mock(VideoRebrandComposer::class, fn ($m) => $m->shouldReceive('composeSlide')->never());

        (new ComposeVideoCarousel($job->id))->handle($chrome, $composer);

        $this->assertSame('composed', $job->refresh()->status);
    }
}
