<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoRebrandComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Phase D — ffmpeg composite of one slide: keep the center 16:9 demo, stack the
 * brand header PNG above + footer PNG below → 1080×1350 4:5, audio preserved.
 *
 * buildFilter is pure + byte-exact-tested (the POC-validated filtergraph).
 * composeSlide is exercised via Process::fake.
 * See docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase D.
 */
class VideoComposeTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_filter_is_byte_exact(): void
    {
        $slide = new RepurposeVideoSlide(['crop_y' => 339, 'crop_h' => 406]);

        $expected = '[0:v]crop=in_w:406:0:339,scale=1080:609[c];[1:v][c][2:v]vstack=inputs=3[v]';
        $this->assertSame($expected, app(VideoRebrandComposer::class)->buildFilter($slide));
    }

    public function test_build_filter_falls_back_to_centered_band_when_crop_missing(): void
    {
        // No crop detected → proportional centered 16:9 of a 1080-wide center
        // (the composer assumes source already normalized; fallback keeps a valid graph).
        $slide = new RepurposeVideoSlide(['crop_y' => null, 'crop_h' => null]);

        $filter = app(VideoRebrandComposer::class)->buildFilter($slide);
        $this->assertStringContainsString('vstack=inputs=3[v]', $filter);
        $this->assertStringContainsString('scale=1080:609[c]', $filter);
    }

    public function test_compose_slide_runs_ffmpeg_and_sets_done(): void
    {
        Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'compositing']);
        $job->update(['slides_path' => 'repurpose/' . $job->id]);
        $slide = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id,
            'slide_index' => 1,
            'role' => 'tool',
            'source_video_path' => 'repurpose/' . $job->id . '/video/slide_1.mp4',
            'crop_y' => 339,
            'crop_h' => 406,
            'composited_status' => 'pending',
        ]);

        $headerPng = storage_path('app/repurpose/' . $job->id . '/chrome/slide_1_header.png');
        $footerPng = storage_path('app/repurpose/' . $job->id . '/chrome/slide_1_footer.png');

        $out = app(VideoRebrandComposer::class)->composeSlide($slide, $headerPng, $footerPng);

        $this->assertNotNull($out);
        $fresh = $slide->fresh();
        $this->assertSame('done', $fresh->composited_status);
        $this->assertStringContainsString('repurpose/' . $job->id . '/composited/slide_1.mp4', $fresh->composited_path);

        Process::assertRan(function ($process) {
            $cmd = is_array($process->command) ? implode(' ', $process->command) : $process->command;
            return str_contains($cmd, 'crop=in_w:406:0:339')
                && str_contains($cmd, 'vstack=inputs=3')
                && str_contains($cmd, 'libx264');
        });
    }

    public function test_compose_slide_marks_failed_on_ffmpeg_error(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'compositing']);
        $job->update(['slides_path' => 'repurpose/' . $job->id]);
        $slide = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id,
            'slide_index' => 1,
            'role' => 'tool',
            'source_video_path' => 'repurpose/' . $job->id . '/video/slide_1.mp4',
            'crop_y' => 339,
            'crop_h' => 406,
            'composited_status' => 'pending',
        ]);

        $out = app(VideoRebrandComposer::class)->composeSlide(
            $slide,
            storage_path('h.png'),
            storage_path('f.png')
        );

        $this->assertNull($out);
        $this->assertSame('failed', $slide->fresh()->composited_status);
    }
}
