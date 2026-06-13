<?php

namespace Tests\Feature;

use App\Jobs\GenerateRebrandAssets;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use App\Services\GeminiGenVideoService;
use App\Services\VideoHookSceneAuthor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E-orchestration — GenerateRebrandAssets forks Extracted → GeneratingAssets,
 * synthesizes the hook (index 0) + CTA (index N+1) slide rows around the source
 * tool slides, and dispatches a face-gen keyframe per hook/CTA. Keyframe → Veo is
 * driven later by the PollRebrandAssets cron (2-poll).
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E
 */
class GenerateRebrandAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithToolSlides(): RepurposeJob
    {
        Setting::create(['group' => 'about', 'key' => 'profile_photo', 'value' => 'https://cdn/face.jpg']);

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'extracted']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Stitch']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'header_title' => 'Cursor']);

        return $job;
    }

    public function test_creates_hook_and_cta_slides_and_dispatches_keyframes(): void
    {
        $job = $this->jobWithToolSlides();

        // Hook authoring is exercised in HookAuthorAndFallbackTest; here keep it
        // deterministic (creator-only) so this test stays focused on bookend rows.
        $this->mock(VideoHookSceneAuthor::class, function ($m) {
            $m->shouldReceive('author')->andReturn(['success' => true, 'figure_name' => null, 'scene_prompt' => 'creator-only hook scene', 'error' => null]);
        });

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('dispatchKeyframe')->twice()->andReturn('kf-hook', 'kf-cta');
        });

        (new GenerateRebrandAssets($job->id))->handle(app(GeminiGenVideoService::class));

        $job->refresh();
        $this->assertSame('generating_assets', $job->status);

        $hook = $job->videoSlides()->where('role', RepurposeVideoSlide::ROLE_HOOK)->first();
        $cta = $job->videoSlides()->where('role', RepurposeVideoSlide::ROLE_CTA)->first();

        $this->assertNotNull($hook);
        $this->assertSame(0, $hook->slide_index);
        $this->assertSame('generating', $hook->keyframe_status);
        $this->assertSame('kf-hook', $hook->keyframe_job_uuid);

        $this->assertNotNull($cta);
        // CTA sits after the last tool slide (tools were 1,2 → cta index 3).
        $this->assertSame(3, $cta->slide_index);
        $this->assertSame('generating', $cta->keyframe_status);
        $this->assertSame('kf-cta', $cta->keyframe_job_uuid);
    }

    public function test_fails_loudly_when_creator_face_missing(): void
    {
        // No profile_photo setting → getCreatorFaceUrl returns null.
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'extracted']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool']);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('dispatchKeyframe')->never();
        });

        (new GenerateRebrandAssets($job->id))->handle(app(GeminiGenVideoService::class));

        $job->refresh();
        $this->assertSame('failed', $job->status);
    }

    public function test_noop_when_not_in_extracted_state(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'compositing']);

        $this->mock(GeminiGenVideoService::class, function ($m) {
            $m->shouldReceive('dispatchKeyframe')->never();
        });

        (new GenerateRebrandAssets($job->id))->handle(app(GeminiGenVideoService::class));

        $job->refresh();
        $this->assertSame('compositing', $job->status);
    }
}
