<?php

namespace Tests\Feature;

use App\Jobs\ComposeVideoCarousel;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\GeminiGenVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase E-orchestration — PollRebrandAssets is the SOLE completion driver for the
 * face-gen keyframe → Veo clip handoff (geminigen never fires webhooks, mirroring
 * PollHookVideos). Two passes:
 *   A) keyframe 'generating' → image ready → store url + dispatch Veo
 *   B) veo 'generating'      → video ready → finalize 4:5 clip → composited done
 * then a completion check: both bookends composited → job AssetsReady + compose.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E
 */
class PollRebrandAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.geminigen.api_key', 'test-key');
    }

    public function test_keyframe_ready_stores_url_and_dispatches_veo(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'generating', 'keyframe_job_uuid' => 'kf-1',
        ]);

        Http::fake([
            '*/history/kf-1' => Http::response(['generated_image' => [['image_url' => 'https://cdn/kf-done.jpg']], 'status' => 2], 200),
        ]);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('dispatchVeoClip')->once()->with('https://cdn/kf-done.jpg', \Mockery::type('string'), '9:16', \Mockery::any())->andReturn('veo-1');
        });

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $this->assertSame('done', $hook->keyframe_status);
        $this->assertSame('https://cdn/kf-done.jpg', $hook->keyframe_url);
        $this->assertSame('generating', $hook->veo_status);
        $this->assertSame('veo-1', $hook->veo_job_uuid);
    }

    public function test_veo_ready_finalizes_and_marks_slide_done(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'veo_status' => 'generating', 'veo_job_uuid' => 'veo-1',
        ]);

        Http::fake([
            '*/history/veo-1' => Http::response(['generated_video' => [['video_url' => 'https://cdn/veo-out.mp4']], 'status' => 2], 200),
        ]);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('finalizeVeoClip')->once()->andReturn('https://alisadikinma.com/storage/repurpose/9/composited/slide_0.mp4');
        });

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $this->assertSame('done', $hook->veo_status);
        $this->assertSame('done', $hook->composited_status);
        $this->assertStringContainsString('slide_0.mp4', $hook->composited_path);
    }

    public function test_veo_failure_persists_error_class(): void
    {
        // A2: the GeminiGen error must be classified + persisted so recover() can
        // degrade the retry prompt after it blanks last_error.
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'veo_status' => 'generating', 'veo_job_uuid' => 'veo-1',
        ]);

        Http::fake([
            '*/history/veo-1' => Http::response(['error_message' => 'PUBLIC_ERROR_AUDIO_FILTERED', 'status' => 3], 200),
        ]);

        $this->mock(GeminiGenVideoService::class);

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $this->assertSame('failed', $hook->veo_status);
        $this->assertSame('audio_filtered', $hook->last_error_class);
    }

    public function test_keyframe_failure_persists_error_class(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $cta = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'cta',
            'keyframe_status' => 'generating', 'keyframe_job_uuid' => 'kf-2',
        ]);

        Http::fake([
            '*/history/kf-2' => Http::response(['error_message' => 'Blocked by safety filter', 'status' => 3], 200),
        ]);

        $this->mock(GeminiGenVideoService::class);

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $cta->refresh();
        $this->assertSame('failed', $cta->keyframe_status);
        $this->assertSame('content_policy', $cta->last_error_class);
    }

    public function test_both_bookends_composited_promotes_job_and_dispatches_compose(): void
    {
        Bus::fake();

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'a.mp4', 'veo_status' => 'done']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'cta', 'composited_status' => 'done', 'composited_path' => 'b.mp4', 'veo_status' => 'done']);

        $this->mock(GeminiGenVideoService::class);

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $job->refresh();
        $this->assertSame('assets_ready', $job->status);
        Bus::assertDispatched(ComposeVideoCarousel::class);
    }
}
