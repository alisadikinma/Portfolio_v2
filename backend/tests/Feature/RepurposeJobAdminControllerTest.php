<?php

namespace Tests\Feature;

use App\Jobs\ComposeVideoCarousel;
use App\Jobs\FinalizeRepurpose;
use App\Jobs\GenerateRebrandAssets;
use App\Jobs\ResearchRepurposeClaims;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase C — admin /admin/repurpose surface: list / detail / retry / thumbnail.
 * All routes auth:sanctum. Retry resumes the failed step (per-step, not a full
 * restart) by inferring the failed-from state from pipeline_state_log.
 */
class RepurposeJobAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    // ---- index ----

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/admin/repurpose')->assertUnauthorized();
    }

    public function test_index_lists_newest_first(): void
    {
        $a = RepurposeJob::factory()->create(['status' => 'drafted']);
        $b = RepurposeJob::factory()->create(['status' => 'failed']);

        $res = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/repurpose');

        $res->assertOk()->assertJson(['success' => true]);
        $ids = array_column($res->json('data'), 'id');
        $this->assertSame([$b->id, $a->id], $ids);
    }

    public function test_index_filters_by_status(): void
    {
        RepurposeJob::factory()->create(['status' => 'drafted']);
        $failed = RepurposeJob::factory()->create(['status' => 'failed']);

        $res = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/repurpose?status=failed');

        $res->assertOk();
        $ids = array_column($res->json('data'), 'id');
        $this->assertSame([$failed->id], $ids);
    }

    // ---- refetch-source ----

    public function test_refetch_source_dispatches_job(): void
    {
        Queue::fake();
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'source_url' => 'https://www.instagram.com/p/ABC123/',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/refetch-source")
            ->assertStatus(202)
            ->assertJson(['success' => true]);

        Queue::assertPushed(\App\Jobs\RefetchSourceSlides::class,
            fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_refetch_source_422_when_no_url(): void
    {
        Queue::fake();
        $job = RepurposeJob::factory()->create(['status' => 'drafted', 'source_url' => '']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/refetch-source")
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_refetch_source_requires_auth(): void
    {
        $this->postJson('/api/admin/repurpose/1/refetch-source')->assertUnauthorized();
    }

    // ---- show ----

    public function test_show_returns_full_detail(): void
    {
        $job = RepurposeJob::factory()->create([
            'status' => 'researched',
            'extracted' => ['claims' => ['a', 'b']],
            'research' => ['corrected_count' => 1],
            'pipeline_state_log' => [['from' => 'extracted', 'to' => 'researching', 'reason' => 'x', 'timestamp' => '2026-06-11T00:00:00+00:00']],
        ]);

        $res = $this->actingAs($this->admin(), 'sanctum')->getJson("/api/admin/repurpose/{$job->id}");

        $res->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.extracted.claims', ['a', 'b'])
            ->assertJsonPath('data.research.corrected_count', 1)
            ->assertJsonCount(1, 'data.pipeline_state_log');
    }

    public function test_show_404_on_missing(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/repurpose/999999')->assertNotFound();
    }

    // ---- retry ----

    public function test_retry_resumes_the_failed_step(): void
    {
        Queue::fake();
        $job = RepurposeJob::factory()->create([
            'status' => 'failed',
            'pipeline_state_log' => [
                ['from' => 'extracted', 'to' => 'researching', 'reason' => 'research_start', 'timestamp' => '2026-06-11T00:00:00+00:00'],
                ['from' => 'researching', 'to' => 'failed', 'reason' => 'research_failed', 'timestamp' => '2026-06-11T00:01:00+00:00'],
            ],
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/retry")
            ->assertOk()->assertJson(['success' => true]);

        // failed-from researching → resume at the researcher's guard state (extracted)
        $this->assertSame('extracted', $job->refresh()->status);
        Queue::assertPushed(ResearchRepurposeClaims::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_retry_blog_finalize_resumes_at_extracted(): void
    {
        Queue::fake();
        // Blog mode skips research+rewrite and enters FinalizeRepurpose at
        // `extracted`. A finalize failure must resume there (NOT `rewritten`).
        $job = RepurposeJob::factory()->create([
            'status' => 'failed',
            'mode' => 'blog',
            'pipeline_state_log' => [
                ['from' => 'extracted', 'to' => 'finalizing', 'reason' => 'finalize_blog_start', 'timestamp' => '2026-06-13T00:00:00+00:00'],
                ['from' => 'finalizing', 'to' => 'failed', 'reason' => 'finalize_exception', 'timestamp' => '2026-06-13T00:01:00+00:00'],
            ],
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/retry")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('extracted', $job->refresh()->status);
        Queue::assertPushed(FinalizeRepurpose::class, fn ($j) => $j->repurposeJobId === $job->id);
        Queue::assertNotPushed(ResearchRepurposeClaims::class);
    }

    public function test_retry_video_rebrand_asset_failure_resumes_at_extracted(): void
    {
        Queue::fake();
        // A video_rebrand asset failure must re-run GenerateRebrandAssets at its
        // `extracted` guard — NOT fall back to a full restart from capture.
        $job = RepurposeJob::factory()->create([
            'status' => 'failed',
            'mode' => 'video_rebrand',
            'asset_retry_count' => 3, // auto-recover budget already spent
            'pipeline_state_log' => [
                ['from' => 'extracted', 'to' => 'generating_assets', 'reason' => 'video_assets_start', 'timestamp' => '2026-06-13T00:00:00+00:00'],
                ['from' => 'generating_assets', 'to' => 'failed', 'reason' => 'video_assets_failed', 'timestamp' => '2026-06-13T00:01:00+00:00'],
            ],
        ]);
        // hook failed at veo, cta fully done — only the hook should be reset.
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'role' => RepurposeVideoSlide::ROLE_HOOK,
            'slide_index' => 0, 'keyframe_status' => 'done', 'veo_status' => 'failed',
            'composited_status' => 'pending', 'last_error' => 'Veo audio filter',
        ]);
        $cta = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'role' => RepurposeVideoSlide::ROLE_CTA,
            'slide_index' => 9, 'keyframe_status' => 'done', 'veo_status' => 'done',
            'composited_status' => 'done',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/retry")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('extracted', $job->refresh()->status);
        $this->assertSame(0, (int) $job->asset_retry_count); // budget reset
        Queue::assertPushed(GenerateRebrandAssets::class, fn ($j) => $j->repurposeJobId === $job->id);

        // failed hook reset for re-run; done cta preserved
        $hook->refresh();
        $this->assertNull($hook->keyframe_status);
        $this->assertNull($hook->veo_status);
        $cta->refresh();
        $this->assertSame('done', $cta->keyframe_status);
        $this->assertSame('done', $cta->composited_status);
    }

    public function test_retry_video_rebrand_compose_failure_resumes_at_assets_ready(): void
    {
        Queue::fake();
        // A compositing failure (bookends already done) must re-run the composer
        // at `assets_ready`, NOT regenerate the assets.
        $job = RepurposeJob::factory()->create([
            'status' => 'failed',
            'mode' => 'video_rebrand',
            'pipeline_state_log' => [
                ['from' => 'assets_ready', 'to' => 'compositing', 'reason' => 'compose_start', 'timestamp' => '2026-06-13T00:00:00+00:00'],
                ['from' => 'compositing', 'to' => 'failed', 'reason' => 'compose_failed', 'timestamp' => '2026-06-13T00:01:00+00:00'],
            ],
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/retry")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('assets_ready', $job->refresh()->status);
        Queue::assertPushed(ComposeVideoCarousel::class, fn ($j) => $j->repurposeJobId === $job->id);
        Queue::assertNotPushed(GenerateRebrandAssets::class);
    }

    public function test_retry_rejects_non_failed_job(): void
    {
        Queue::fake();
        $job = RepurposeJob::factory()->create(['status' => 'drafted']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/retry")
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    // ---- delete ----

    public function test_destroy_removes_the_job(): void
    {
        $job = RepurposeJob::factory()->create(['status' => 'drafted']);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/repurpose/{$job->id}")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('repurpose_jobs', ['id' => $job->id]);
    }

    public function test_destroy_404_on_missing(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/admin/repurpose/999999')
            ->assertStatus(404);
    }

    public function test_destroy_requires_auth(): void
    {
        $this->deleteJson('/api/admin/repurpose/1')->assertUnauthorized();
    }

    // ---- thumbnail ----

    public function test_slide_thumbnail_serves_private_file(): void
    {
        Storage::fake('local');
        $job = RepurposeJob::factory()->create(['slides_path' => 'repurpose/5']);
        Storage::disk('local')->put('repurpose/5/slide-01.jpg', 'JPEGBYTES');
        Storage::disk('local')->put('repurpose/5/slide-02.jpg', 'JPEGBYTES2');

        $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/admin/repurpose/{$job->id}/slide/0")
            ->assertOk();
    }

    public function test_slide_thumbnail_404_when_absent(): void
    {
        Storage::fake('local');
        $job = RepurposeJob::factory()->create(['slides_path' => 'repurpose/5']);

        $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/admin/repurpose/{$job->id}/slide/0")
            ->assertNotFound();
    }

    public function test_slide_thumbnail_requires_auth(): void
    {
        $job = RepurposeJob::factory()->create(['slides_path' => 'repurpose/5']);
        $this->getJson("/api/admin/repurpose/{$job->id}/slide/0")->assertUnauthorized();
    }
}
