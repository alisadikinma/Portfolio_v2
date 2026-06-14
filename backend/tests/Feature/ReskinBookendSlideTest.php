<?php

namespace Tests\Feature;

use App\Jobs\ReskinBookendSlide;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\GeminiGenVideoService;
use App\Services\VideoBookendOverlayApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * video_rebrand credit-free bookend re-skin — re-applies the brand overlay
 * (hook title/logo, CTA ask) onto the ALREADY-RENDERED Veo/GROK clip by
 * re-finalizing the stored veo_url. No keyframe, no i2v render, no Veo credits.
 *
 * "jangan regenerate all lagi.. cukup hook & cta tambahkan re-skin saja"
 */
class ReskinBookendSlideTest extends TestCase
{
    use RefreshDatabase;

    private function bookend(string $role, array $overrides = []): array
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted']);
        $slide = RepurposeVideoSlide::create(array_merge([
            'repurpose_job_id' => $job->id,
            'slide_index' => $role === 'cta' ? 8 : 0,
            'role' => $role,
            'keyframe_status' => 'done',
            'veo_status' => 'done',
            'veo_url' => 'https://cdn/raw-veo-output.mp4',
            'composited_status' => 'done',
            'composited_path' => 'https://alisadikinma.com/storage/repurpose/1/composited/slide_0_old.mp4',
        ], $overrides));

        return [$job, $slide];
    }

    public function test_reskin_refinalizes_from_veo_url_and_applies_overlay_no_render(): void
    {
        Bus::fake(); // assert NO GenerateRebrandAssets / keyframe job is queued
        [$job, $slide] = $this->bookend('hook');

        // finalizeVeoClip re-downloads the ALREADY-PAID veo_url — no dispatchVeoClip /
        // dispatchKeyframe is called, so no new credits are spent.
        $this->mock(GeminiGenVideoService::class, function ($m) use ($slide) {
            $m->shouldReceive('finalizeVeoClip')
                ->once()
                ->with('https://cdn/raw-veo-output.mp4', "repurpose/{$slide->repurpose_job_id}/composited/slide_0.mp4")
                ->andReturn('https://alisadikinma.com/storage/repurpose/1/composited/slide_0.mp4');
            $m->shouldNotReceive('dispatchVeoClip');
            $m->shouldNotReceive('dispatchGrokClip');
            $m->shouldNotReceive('dispatchKeyframe');
        });
        $this->mock(VideoBookendOverlayApplier::class, function ($m) {
            $m->shouldReceive('apply')->once()
                ->andReturn('https://alisadikinma.com/storage/repurpose/1/composited/slide_0_title.mp4');
        });

        (new ReskinBookendSlide($job->id, 0))->handle(
            app(GeminiGenVideoService::class),
            app(VideoBookendOverlayApplier::class),
        );

        $slide->refresh();
        $this->assertSame('done', $slide->composited_status);
        $this->assertStringContainsString('slide_0_title.mp4', $slide->composited_path);
    }

    public function test_reskin_keeps_plain_base_when_overlay_returns_null(): void
    {
        // No title/logo (or render miss) → applier returns null → the plain base ships.
        [$job, $slide] = $this->bookend('hook');

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('finalizeVeoClip')->once()
                ->andReturn('https://alisadikinma.com/storage/repurpose/1/composited/slide_0.mp4');
        });
        $this->mock(VideoBookendOverlayApplier::class, function ($m) {
            $m->shouldReceive('apply')->once()->andReturn(null);
        });

        (new ReskinBookendSlide($job->id, 0))->handle(
            app(GeminiGenVideoService::class),
            app(VideoBookendOverlayApplier::class),
        );

        $slide->refresh();
        $this->assertSame('done', $slide->composited_status);
        $this->assertStringContainsString('slide_0.mp4', $slide->composited_path);
        $this->assertStringNotContainsString('_title', $slide->composited_path);
    }

    public function test_reskin_fails_when_no_rendered_clip(): void
    {
        [$job, $slide] = $this->bookend('cta', ['veo_url' => null]);

        // No clip to re-skin → never touches the video service.
        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldNotReceive('finalizeVeoClip');
        });

        (new ReskinBookendSlide($job->id, 8))->handle(
            app(GeminiGenVideoService::class),
            app(VideoBookendOverlayApplier::class),
        );

        $slide->refresh();
        $this->assertSame('failed', $slide->composited_status);
    }

    public function test_reskin_noop_on_tool_slide(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted']);
        $tool = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 3, 'role' => 'tool',
            'composited_status' => 'done', 'composited_path' => 'x.mp4',
        ]);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldNotReceive('finalizeVeoClip');
        });

        (new ReskinBookendSlide($job->id, 3))->handle(
            app(GeminiGenVideoService::class),
            app(VideoBookendOverlayApplier::class),
        );

        $tool->refresh();
        // Untouched — tool slides re-skin via ComposeToolSlides, not this job.
        $this->assertSame('done', $tool->composited_status);
    }
}
