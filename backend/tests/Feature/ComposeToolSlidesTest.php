<?php

namespace Tests\Feature;

use App\Jobs\ComposeToolSlides;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoChromeRenderer;
use App\Services\VideoRebrandComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E (parallel) — ComposeToolSlides re-skins the source `tool` slides EARLY,
 * in parallel with the Veo bookend render, so a slow/failed hook never blocks the
 * rest of the carousel. It is FSM-neutral (never transitions the job) and
 * idempotent (skips slides already composited). The assets_ready gate
 * (ComposeVideoCarousel) still owns the advance to finalize.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E
 */
class ComposeToolSlidesTest extends TestCase
{
    use RefreshDatabase;

    private function videoJob(string $status = 'generating_assets'): RepurposeJob
    {
        return RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => $status]);
    }

    public function test_composes_pending_tool_slides_without_touching_fsm(): void
    {
        $job = $this->videoJob();
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'keyframe_status' => 'generating']);
        $t1 = RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'source_video_path' => 's1.mp4', 'crop_y' => 338, 'crop_h' => 406]);
        $t2 = RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'source_video_path' => 's2.mp4', 'crop_y' => 338, 'crop_h' => 406]);

        $chrome = $this->mock(VideoChromeRenderer::class, function ($m) {
            $m->shouldReceive('renderSlide')->twice()->andReturn(['header' => 'h.png', 'footer' => 'f.png']);
        });
        // Real composeJobToolSlides loop; only composeSlide is mocked (it persists status).
        $composer = $this->partialMock(VideoRebrandComposer::class, function ($m) {
            $m->shouldReceive('composeSlide')->twice()->andReturnUsing(function ($slide) {
                $slide->update(['composited_status' => 'done', 'composited_path' => 'tool.mp4']);
                return 'tool.mp4';
            });
        });

        (new ComposeToolSlides($job->id))->handle($chrome, $composer);

        $this->assertSame('done', $t1->refresh()->composited_status);
        $this->assertSame('done', $t2->refresh()->composited_status);
        // FSM untouched — the assets_ready gate still owns the advance.
        $this->assertSame('generating_assets', $job->refresh()->status);
    }

    public function test_skips_tool_slides_already_done(): void
    {
        $job = $this->videoJob();
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'source_video_path' => 's1.mp4', 'composited_status' => 'done', 'composited_path' => 'done.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'source_video_path' => 's2.mp4', 'crop_y' => 338, 'crop_h' => 406]);

        $chrome = $this->mock(VideoChromeRenderer::class, function ($m) {
            $m->shouldReceive('renderSlide')->once()->andReturn(['header' => 'h.png', 'footer' => 'f.png']);
        });
        $composer = $this->partialMock(VideoRebrandComposer::class, function ($m) {
            $m->shouldReceive('composeSlide')->once()->andReturnUsing(function ($slide) {
                $slide->update(['composited_status' => 'done', 'composited_path' => 'tool.mp4']);
                return 'tool.mp4';
            });
        });

        (new ComposeToolSlides($job->id))->handle($chrome, $composer);
        // assertion is the once()/once() Mockery expectations above.
        $this->assertTrue(true);
    }

    public function test_noop_when_no_tool_slides(): void
    {
        $job = $this->videoJob();
        // only bookends, no tool slides yet
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook']);

        $chrome = $this->mock(VideoChromeRenderer::class);
        $composer = $this->mock(VideoRebrandComposer::class, fn ($m) => $m->shouldReceive('composeJobToolSlides')->never());

        (new ComposeToolSlides($job->id))->handle($chrome, $composer);
        $this->assertSame('generating_assets', $job->refresh()->status);
    }

    public function test_noop_when_not_video_mode(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'carousel', 'status' => 'researching']);

        $chrome = $this->mock(VideoChromeRenderer::class);
        $composer = $this->mock(VideoRebrandComposer::class, fn ($m) => $m->shouldReceive('composeJobToolSlides')->never());

        (new ComposeToolSlides($job->id))->handle($chrome, $composer);
        $this->assertSame('researching', $job->refresh()->status);
    }
}
