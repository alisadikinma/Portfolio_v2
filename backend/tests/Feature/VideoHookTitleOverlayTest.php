<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\GeminiGenVideoService;
use App\Services\VideoChromeRenderer;
use App\Services\VideoRebrandComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * video_rebrand hook overlay — the finalized hook clip gets the cover headline
 * (from the original IG hook, via RepurposeJob::videoHookTitle) + the auto-resolved
 * topic brand logo composited on top by PollRebrandAssets (mirroring the CTA ask).
 */
class VideoHookTitleOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_hook_title_prefers_source_hook_then_caption(): void
    {
        $fromHook = RepurposeJob::factory()->make(['extracted' => ['source_hook_title' => 'Cover headline', 'caption' => "first line\nsecond"]]);
        $this->assertSame('Cover headline', $fromHook->videoHookTitle());

        $fromCaption = RepurposeJob::factory()->make(['extracted' => ['caption' => "  \nReal first line\nmore"]]);
        $this->assertSame('Real first line', $fromCaption->videoHookTitle());

        $empty = RepurposeJob::factory()->make(['extracted' => []]);
        $this->assertSame('', $empty->videoHookTitle());
    }

    public function test_hook_finalize_applies_title_and_logo_overlay(): void
    {
        Bus::fake();

        $logo = 'https://alisadikinma.com/storage/entity-refs/logo/Q95_google.png';
        // Pre-cached bilingual hook title → RepurposeHookTitleResolver short-circuits
        // (no CLI), so the test exercises only the overlay path.
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'generating_assets',
            'extracted' => [
                'source_hook_title' => '7 Google AI Tools',
                'source_hook_title_id' => '7 Tools AI Google',
                'source_hook_title_en' => '7 Google AI Tools',
                'hook_brand_logo' => $logo,
            ],
        ]);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'veo_status' => 'generating', 'veo_job_uuid' => 'veo-1',
        ]);

        Http::fake(['*/history/veo-1' => Http::response(['generated_video' => [['video_url' => 'https://cdn/veo-out.mp4']], 'status' => 2], 200)]);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('finalizeVeoClip')->once()->andReturn('https://alisadikinma.com/storage/repurpose/9/composited/slide_0.mp4');
        });
        $this->mock(VideoChromeRenderer::class, function ($m) use ($logo) {
            $m->shouldReceive('renderHookTitle')->once()
                ->with(\Mockery::any(), '7 Tools AI Google', $logo, '7 Google AI Tools')
                ->andReturn('/abs/hook_title.png');
        });
        $this->mock(VideoRebrandComposer::class, function ($m) {
            $m->shouldReceive('overlayClip')->once()
                ->with(\Mockery::any(), '/abs/hook_title.png', 'title')
                ->andReturn('https://alisadikinma.com/storage/repurpose/9/composited/slide_0_title.mp4');
        });

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $this->assertSame('done', $hook->composited_status);
        $this->assertStringContainsString('slide_0_title.mp4', $hook->composited_path);
    }

    public function test_hook_finalize_no_title_keeps_plain_clip(): void
    {
        Bus::fake();

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets', 'extracted' => []]);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'veo_status' => 'generating', 'veo_job_uuid' => 'veo-1',
        ]);

        Http::fake(['*/history/veo-1' => Http::response(['generated_video' => [['video_url' => 'https://cdn/veo-out.mp4']], 'status' => 2], 200)]);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('finalizeVeoClip')->once()->andReturn('https://alisadikinma.com/storage/repurpose/9/composited/slide_0.mp4');
        });
        // No title → renderHookTitle must NOT be called; plain finalized clip ships.
        $this->mock(VideoChromeRenderer::class, function ($m) {
            $m->shouldNotReceive('renderHookTitle');
        });

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $this->assertSame('done', $hook->composited_status);
        $this->assertStringContainsString('slide_0.mp4', $hook->composited_path);
        $this->assertStringNotContainsString('_title', $hook->composited_path);
    }
}
