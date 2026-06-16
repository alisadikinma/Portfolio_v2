<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\User;
use App\Models\VideoFullSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase E — VPS↔MacBook bridge API. Worker routes are gated by the
 * `video-full:work` token ability; admin routes by plain auth:sanctum.
 *
 * @see docs/plans/2026-06-16-video-full-rebrand.md Phase E
 */
class VideoFullBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function workerActing(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['video-full:work']);

        return $user;
    }

    private function job(string $status = 'queued_local'): RepurposeJob
    {
        return RepurposeJob::create([
            'source_url' => 'https://www.instagram.com/p/DZmqSoRKOQ9/',
            'mode' => RepurposeJob::MODE_VIDEO_FULL,
            'status' => $status,
        ]);
    }

    public function test_claim_requires_the_video_full_work_ability(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['some:other']);
        $this->getJson('/api/worker/video-full/claim')->assertForbidden();
    }

    public function test_claim_picks_oldest_queued_job_and_advances_status(): void
    {
        $this->workerActing();
        $job = $this->job();

        $res = $this->getJson('/api/worker/video-full/claim');

        $res->assertOk()->assertJsonPath('job.id', $job->id);
        $this->assertSame('claimed_local', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->worker_claimed_at);
    }

    public function test_claim_returns_null_when_no_queued_job(): void
    {
        $this->workerActing();
        $this->getJson('/api/worker/video-full/claim')->assertOk()->assertJsonPath('job', null);
    }

    public function test_progress_advances_claimed_to_processing_and_heartbeats(): void
    {
        $this->workerActing();
        $job = $this->job('claimed_local');

        $this->putJson("/api/worker/video-full/{$job->id}/progress", ['progress' => 40, 'step' => 'animate'])
            ->assertOk()->assertJsonPath('ok', true);

        $job->refresh();
        $this->assertSame('processing_local', $job->status);
        $this->assertSame(40, $job->worker_progress);
        $this->assertNotNull($job->worker_heartbeat_at);
    }

    public function test_segments_upsert_is_idempotent_on_index(): void
    {
        $this->workerActing();
        $job = $this->job('processing_local');

        $payload = ['segments' => [
            ['segment_index' => 0, 'type' => 'to_camera', 'start_sec' => 0, 'end_sec' => 4, 'text_id' => 'Halo'],
            ['segment_index' => 1, 'type' => 'b_roll', 'start_sec' => 4, 'end_sec' => 9, 'strategy' => 'reuse_source'],
        ]];
        $this->postJson("/api/worker/video-full/{$job->id}/segments", $payload)->assertOk();
        $this->postJson("/api/worker/video-full/{$job->id}/segments", $payload)->assertOk();

        $this->assertSame(2, VideoFullSegment::where('repurpose_job_id', $job->id)->count());
    }

    public function test_segments_rejects_an_invalid_type(): void
    {
        $this->workerActing();
        $job = $this->job('processing_local');
        $this->postJson("/api/worker/video-full/{$job->id}/segments", [
            'segments' => [['segment_index' => 0, 'type' => 'hacker']],
        ])->assertStatus(422);
    }

    public function test_assets_final_video_upload_advances_to_uploaded(): void
    {
        Storage::fake('public');
        $this->workerActing();
        $job = $this->job('processing_local');

        $this->postJson("/api/worker/video-full/{$job->id}/assets", [
            'final_video' => UploadedFile::fake()->create('final.mp4', 2000, 'video/mp4'),
        ])->assertOk();

        $job->refresh();
        $this->assertSame('uploaded', $job->status);
        $this->assertStringContainsString('video-full/'.$job->id, (string) $job->final_video_url);
        $this->assertSame(100, $job->worker_progress);
    }

    public function test_admin_regenerate_segment_marks_pending_and_bounces_to_processing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $job = $this->job('uploaded');
        VideoFullSegment::create(['repurpose_job_id' => $job->id, 'segment_index' => 2, 'type' => 'b_roll', 'status' => 'done']);

        $this->postJson("/api/admin/video-full/{$job->id}/regenerate-segment/2")
            ->assertOk()->assertJsonPath('job_status', 'processing_local');

        $this->assertSame('pending', VideoFullSegment::where('repurpose_job_id', $job->id)->where('segment_index', 2)->first()->status);
        $this->assertSame('processing_local', $job->fresh()->status);
    }
}
