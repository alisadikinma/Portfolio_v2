<?php

namespace Tests\Feature;

use App\Enums\RepurposeJobStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\RepurposeJob;
use App\Models\VideoFullSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D — video_full FSM lifecycle + video_full_segments. The MacBook-local
 * worker drives the job through queued_local → claimed → processing → uploaded →
 * drafted; the VPS only tracks state.
 *
 * @see docs/plans/2026-06-16-video-full-rebrand.md Phase D
 */
class VideoFullFsmTest extends TestCase
{
    use RefreshDatabase;

    private function videoFullJob(string $status = 'queued_local'): RepurposeJob
    {
        return RepurposeJob::create([
            'source_url' => 'https://www.instagram.com/p/DZmqSoRKOQ9/',
            'mode' => RepurposeJob::MODE_VIDEO_FULL,
            'status' => $status,
        ]);
    }

    public function test_walks_the_local_worker_lifecycle(): void
    {
        $job = $this->videoFullJob();
        $job->transitionTo(RepurposeJobStatus::ClaimedLocal, 'worker claimed');
        $this->assertSame('claimed_local', $job->status);
        $job->transitionTo(RepurposeJobStatus::ProcessingLocal, 'worker started');
        $this->assertSame('processing_local', $job->status);
        $job->transitionTo(RepurposeJobStatus::Uploaded, 'assets uploaded');
        $this->assertSame('uploaded', $job->status);
        $job->transitionTo(RepurposeJobStatus::Drafted, 'ready for review');
        $this->assertSame('drafted', $job->fresh()->status);
    }

    public function test_rejects_illegal_video_full_transition(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        $this->videoFullJob()->transitionTo(RepurposeJobStatus::Uploaded, 'skip');
    }

    public function test_allows_per_segment_regenerate_edge(): void
    {
        $job = $this->videoFullJob('uploaded');
        $job->transitionTo(RepurposeJobStatus::ProcessingLocal, 'regenerate segment 3');
        $this->assertSame('processing_local', $job->status);
    }

    public function test_allows_worker_crash_requeue_and_failed_retry(): void
    {
        $job = $this->videoFullJob('processing_local');
        $job->transitionTo(RepurposeJobStatus::QueuedLocal, 'worker dropped');
        $this->assertSame('queued_local', $job->status);

        $failed = $this->videoFullJob('failed');
        $failed->transitionTo(RepurposeJobStatus::QueuedLocal, 'admin retry');
        $this->assertSame('queued_local', $failed->status);
    }

    public function test_does_not_regress_video_rebrand_branch(): void
    {
        $job = RepurposeJob::create([
            'source_url' => 'https://www.instagram.com/p/x/',
            'mode' => RepurposeJob::MODE_VIDEO_REBRAND,
            'status' => 'extracted',
        ]);
        $job->transitionTo(RepurposeJobStatus::GeneratingAssets, 'rebrand');
        $this->assertSame('generating_assets', $job->status);
    }

    public function test_owns_video_full_segments_ordered_by_index(): void
    {
        $job = $this->videoFullJob();
        VideoFullSegment::create(['repurpose_job_id' => $job->id, 'segment_index' => 1, 'type' => 'b_roll', 'strategy' => 'reuse_source']);
        VideoFullSegment::create(['repurpose_job_id' => $job->id, 'segment_index' => 0, 'type' => 'to_camera', 'strategy' => 'veo_talking']);

        $segs = $job->videoFullSegments()->get();
        $this->assertCount(2, $segs);
        $this->assertSame(0, $segs->first()->segment_index);
        $this->assertSame('to_camera', $segs->first()->type);
    }

    public function test_persists_worker_lifecycle_fields(): void
    {
        $job = $this->videoFullJob();
        $job->update(['worker_progress' => 45, 'worker_step' => 'animate', 'worker_heartbeat_at' => now()]);
        $this->assertSame(45, $job->fresh()->worker_progress);
        $this->assertSame('animate', $job->fresh()->worker_step);
    }
}
