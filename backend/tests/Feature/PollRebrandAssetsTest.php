<?php

namespace Tests\Feature;

use App\Jobs\ComposeVideoCarousel;
use App\Jobs\GenerateRebrandAssets;
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

    public function test_recover_veo_only_failure_preserves_keyframe_and_redispatches_veo(): void
    {
        // A3 (the bug fix): keyframe was 'done', only Veo failed → keep keyframe_url,
        // reset veo only, re-dispatch Veo DIRECTLY (no keyframe re-render, no
        // GenerateRebrandAssets, no Extracted bounce).
        Bus::fake();

        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'generating_assets',
            'asset_retry_count' => 0,
            'updated_at' => now()->subMinutes(10), // past the cooldown
        ]);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'keyframe_url' => 'https://cdn/kf-hook.jpg',
            'veo_status' => 'failed', 'last_error' => 'PUBLIC_ERROR_AUDIO_FILTERED', 'last_error_class' => 'audio_filtered',
        ]);
        // CTA already fully done so the job has a single failed (veo-only) bookend.
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'cta',
            'keyframe_status' => 'done', 'keyframe_url' => 'https://cdn/kf-cta.jpg',
            'veo_status' => 'done', 'composited_status' => 'done', 'composited_path' => 'cta.mp4',
        ]);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('dispatchVeoClip')
                ->once()
                ->with('https://cdn/kf-hook.jpg', \Mockery::type('string'), '9:16', \Mockery::any())
                ->andReturn('veo-retry-1');
        });

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $job->refresh();
        // keyframe preserved, Veo re-dispatched
        $this->assertSame('done', $hook->keyframe_status);
        $this->assertSame('https://cdn/kf-hook.jpg', $hook->keyframe_url);
        $this->assertSame('generating', $hook->veo_status);
        $this->assertSame('veo-retry-1', $hook->veo_job_uuid);
        // job stays in generating_assets (no Extracted bounce), retry budget spent
        $this->assertSame('generating_assets', $job->status);
        $this->assertSame(1, $job->asset_retry_count);
        // the GenerateRebrandAssets re-render path was NOT taken
        Bus::assertNotDispatched(GenerateRebrandAssets::class);
    }

    public function test_recover_keyframe_failure_full_resets_and_bounces_to_extracted(): void
    {
        // A keyframe-broken bookend still needs the full GenerateRebrandAssets path.
        Bus::fake();

        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'generating_assets',
            'asset_retry_count' => 0,
            'updated_at' => now()->subMinutes(10),
        ]);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'failed', 'last_error' => 'Blocked by safety filter', 'last_error_class' => 'content_policy',
        ]);
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'cta',
            'keyframe_status' => 'done', 'keyframe_url' => 'https://cdn/kf-cta.jpg',
            'veo_status' => 'done', 'composited_status' => 'done', 'composited_path' => 'cta.mp4',
        ]);

        $this->mock(GeminiGenVideoService::class);

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $job->refresh();
        $this->assertNull($hook->keyframe_status); // full reset for re-render
        $this->assertSame('extracted', $job->status); // bounced for GenerateRebrandAssets guard
        $this->assertSame(1, $job->asset_retry_count);
        Bus::assertDispatched(GenerateRebrandAssets::class);
    }

    public function test_recover_respects_max_retries(): void
    {
        Bus::fake();

        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'generating_assets',
            'asset_retry_count' => 3, // already at MAX_RETRIES
            'updated_at' => now()->subMinutes(10),
        ]);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'keyframe_url' => 'https://cdn/kf.jpg', 'veo_status' => 'failed',
        ]);

        $this->mock(GeminiGenVideoService::class); // no dispatchVeoClip expected

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $hook->refresh();
        $this->assertSame('failed', $hook->veo_status); // untouched — budget exhausted
        Bus::assertNotDispatched(GenerateRebrandAssets::class);
    }

    public function test_exhaustion_fails_job_and_sends_telegram_alert(): void
    {
        // A5: once retries are spent and a bookend is hard-failed, checkCompletion
        // fails the job AND fires the operator "take action" Telegram alert (was
        // a silent transition before).
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'generating_assets',
            'asset_retry_count' => 3, // MAX_RETRIES
        ]);
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'failed', 'last_error' => 'PUBLIC_ERROR_AUDIO_FILTERED',
        ]);
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'cta',
            'composited_status' => 'done', 'composited_path' => 'b.mp4', 'veo_status' => 'done',
        ]);

        $this->mock(GeminiGenVideoService::class);
        $telegram = $this->mock(\App\Services\TelegramNotificationService::class);
        $telegram->shouldReceive('sendRepurposeAssetsFailed')->once()
            ->with(\Mockery::on(fn ($j) => $j->id === $job->id), \Mockery::type('string'))
            ->andReturn(true);

        $this->artisan('repurpose:poll-rebrand-assets')->assertSuccessful();

        $this->assertSame('failed', $job->refresh()->status);
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
