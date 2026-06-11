<?php

namespace Tests\Feature;

use App\Jobs\ResearchRepurposeClaims;
use App\Models\RepurposeJob;
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

    public function test_retry_rejects_non_failed_job(): void
    {
        Queue::fake();
        $job = RepurposeJob::factory()->create(['status' => 'drafted']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/retry")
            ->assertStatus(422);

        Queue::assertNothingPushed();
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
