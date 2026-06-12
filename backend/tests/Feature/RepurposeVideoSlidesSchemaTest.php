<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — repurpose_video_slides table + RepurposeVideoSlide model + the
 * RepurposeJob::videoSlides relation. See
 * docs/plans/2026-06-12-ig-video-carousel-rebrand.md.
 */
class RepurposeVideoSlidesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_slides_persist_and_relation_is_ordered(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'extracted']);

        // insert out of order to prove the relation sorts by slide_index
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 3, 'role' => 'cta']);

        $roles = $job->videoSlides()->get()->pluck('role')->all();
        $this->assertSame(['hook', 'tool', 'tool', 'cta'], $roles);
    }

    public function test_casts_and_fields_round_trip(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'extracted']);

        $slide = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id,
            'slide_index' => 1,
            'role' => 'tool',
            'source_video_path' => 'repurpose/9/video/slide1.mp4',
            'poster_path' => 'repurpose/9/video/slide1.jpg',
            'header_title' => 'Stitch',
            'header_desc' => 'Build apps with AI',
            'crop_y' => 339,
            'crop_h' => 406,
            'composited_status' => 'pending',
        ]);

        $fresh = $slide->fresh();
        $this->assertIsInt($fresh->crop_y);
        $this->assertSame(406, $fresh->crop_h);
        $this->assertSame('Stitch', $fresh->header_title);
    }

    public function test_cascade_delete_with_parent_job(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'extracted']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool']);

        $this->assertSame(2, RepurposeVideoSlide::where('repurpose_job_id', $job->id)->count());

        $job->delete();

        $this->assertSame(0, RepurposeVideoSlide::where('repurpose_job_id', $job->id)->count());
    }
}
