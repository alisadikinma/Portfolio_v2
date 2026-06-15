<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — RepurposeJob helpers for the Zernio video-carousel publish path.
 */
class RepurposeZernioPublishModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_zernio_publish_json_casts_round_trip(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted']);
        $job->update(['zernio_publish' => ['instagram' => ['status' => 'published', 'post_id' => 'z-1']]]);

        $this->assertSame('published', $job->fresh()->zernioPublishState('instagram')['status']);
        $this->assertNull($job->fresh()->zernioPublishState('threads'));
    }

    public function test_composited_video_urls_are_ordered_and_done_only(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted']);
        // Out of order + one not-done to prove filtering + ordering.
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'composited_status' => 'done', 'composited_path' => 'https://x/2.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'https://x/0.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'composited_status' => 'generating', 'composited_path' => null]);

        $this->assertSame(['https://x/0.mp4', 'https://x/2.mp4'], $job->compositedVideoUrls());
    }

    public function test_ig_caption_prefers_source_caption_then_falls_back_to_topic(): void
    {
        $withCaption = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'drafted',
            'extracted' => ['caption' => '  5 AI tools you need  '],
        ]);
        $this->assertSame('5 AI tools you need', $withCaption->igCaption());

        $noCaption = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'drafted',
            'extracted' => [], 'rewritten' => ['title' => 'Fallback Topic'],
        ]);
        $this->assertSame('Fallback Topic', $noCaption->igCaption());
    }
}
