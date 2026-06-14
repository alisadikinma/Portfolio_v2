<?php

namespace Tests\Feature;

use App\Jobs\ComposeToolSlides;
use App\Jobs\GenerateRebrandAssets;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Admin slide-regeneration endpoints for video_rebrand jobs:
 *   POST /admin/repurpose/{id}/regenerate-slides        (batch — all)
 *   POST /admin/repurpose/{id}/slides/{n}/regenerate     (single — by slide_index)
 *
 * A `tool` slide re-skins via ComposeToolSlides (free, FSM-neutral). A `hook`/`cta`
 * bookend resets its keyframe + clip and forces the job to `extracted` →
 * GenerateRebrandAssets (Veo re-render). Batch resets everything + forces extracted.
 */
class RepurposeRegenerateSlidesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** A drafted video_rebrand job with hook(0) + 2 tools(1,2) + cta(3), all done. */
    private function draftedVideoJob(): RepurposeJob
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'keyframe_status' => 'done', 'veo_status' => 'done', 'composited_status' => 'done', 'composited_path' => 'h.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'source_video_path' => 's1.mp4', 'composited_status' => 'done', 'composited_path' => 't1.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'source_video_path' => 's2.mp4', 'composited_status' => 'done', 'composited_path' => 't2.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 3, 'role' => 'cta', 'keyframe_status' => 'done', 'veo_status' => 'done', 'composited_status' => 'done', 'composited_path' => 'c.mp4']);

        return $job;
    }

    // ---- auth ----

    public function test_regenerate_all_requires_auth(): void
    {
        $this->postJson('/api/admin/repurpose/1/regenerate-slides')->assertUnauthorized();
    }

    public function test_regenerate_single_requires_auth(): void
    {
        $this->postJson('/api/admin/repurpose/1/slides/0/regenerate')->assertUnauthorized();
    }

    // ---- batch (all) ----

    public function test_regenerate_all_resets_every_slide_forces_extracted_and_dispatches_assets(): void
    {
        Queue::fake();
        $job = $this->draftedVideoJob();

        $res = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/regenerate-slides");

        $res->assertStatus(202)->assertJson(['success' => true, 'data' => ['status' => 'extracted']]);
        $this->assertSame('extracted', $job->refresh()->status);

        // Bookends fully reset (keyframe + clip will re-render).
        foreach ($job->videoSlides()->whereIn('role', ['hook', 'cta'])->get() as $b) {
            $this->assertNull($b->keyframe_status);
            $this->assertNull($b->veo_status);
            $this->assertSame('pending', $b->composited_status);
        }
        // Tools reset to pending (free re-skin).
        foreach ($job->videoSlides()->where('role', 'tool')->get() as $t) {
            $this->assertSame('pending', $t->composited_status);
        }

        Queue::assertPushed(GenerateRebrandAssets::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_regenerate_all_rejects_non_video_rebrand(): void
    {
        Queue::fake();
        $job = RepurposeJob::factory()->create(['mode' => 'carousel', 'status' => 'drafted']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/regenerate-slides")
            ->assertStatus(422)
            ->assertJson(['error' => ['code' => 'NOT_VIDEO_REBRAND']]);

        Queue::assertNotPushed(GenerateRebrandAssets::class);
    }

    // ---- single (by slide_index) ----

    public function test_regenerate_single_tool_reskins_via_compose_without_touching_fsm(): void
    {
        Queue::fake();
        $job = $this->draftedVideoJob();

        $res = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/slides/1/regenerate");

        $res->assertStatus(202)->assertJson(['success' => true]);

        // Only the targeted tool reset; the job FSM is untouched (stays drafted).
        $this->assertSame('drafted', $job->refresh()->status);
        $this->assertSame('pending', $job->videoSlides()->where('slide_index', 1)->first()->composited_status);
        $this->assertSame('done', $job->videoSlides()->where('slide_index', 2)->first()->composited_status);

        Queue::assertPushed(ComposeToolSlides::class, fn ($j) => $j->repurposeJobId === $job->id);
        Queue::assertNotPushed(GenerateRebrandAssets::class);
    }

    public function test_regenerate_single_bookend_resets_and_dispatches_assets(): void
    {
        Queue::fake();
        $job = $this->draftedVideoJob();

        $res = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/slides/0/regenerate");

        $res->assertStatus(202);
        $this->assertSame('extracted', $job->refresh()->status);

        $hook = $job->videoSlides()->where('slide_index', 0)->first();
        $this->assertNull($hook->keyframe_status);
        $this->assertNull($hook->veo_status);
        $this->assertSame('pending', $hook->composited_status);

        // Other slides untouched.
        $this->assertSame('done', $job->videoSlides()->where('slide_index', 2)->first()->composited_status);

        Queue::assertPushed(GenerateRebrandAssets::class, fn ($j) => $j->repurposeJobId === $job->id);
        Queue::assertNotPushed(ComposeToolSlides::class);
    }

    public function test_regenerate_single_404_for_unknown_slide_index(): void
    {
        Queue::fake();
        $job = $this->draftedVideoJob();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/slides/99/regenerate")
            ->assertNotFound();

        Queue::assertNotPushed(GenerateRebrandAssets::class);
        Queue::assertNotPushed(ComposeToolSlides::class);
    }

    public function test_regenerate_single_rejects_non_video_rebrand(): void
    {
        Queue::fake();
        $job = RepurposeJob::factory()->create(['mode' => 'carousel', 'status' => 'drafted']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/slides/0/regenerate")
            ->assertStatus(422)
            ->assertJson(['error' => ['code' => 'NOT_VIDEO_REBRAND']]);
    }
}
