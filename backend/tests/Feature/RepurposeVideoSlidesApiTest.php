<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase G — the admin repurpose detail exposes composited video slides (with
 * their public MP4 download URLs) for the manual-download UI. Image-mode jobs
 * (blog/carousel) return an empty video_slides array.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase G
 */
class RepurposeVideoSlidesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_ordered_video_slides_with_download_urls(): void
    {
        $user = User::factory()->create();
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted']);
        // Insert out of order to prove the response is ordered by slide_index.
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'cta', 'composited_status' => 'done', 'composited_path' => 'https://x/2.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'https://x/0.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Cursor', 'composited_status' => 'done', 'composited_path' => 'https://x/1.mp4']);

        $res = $this->actingAs($user, 'sanctum')->getJson("/api/admin/repurpose/{$job->id}");

        $res->assertOk();
        $slides = $res->json('data.video_slides');
        $this->assertCount(3, $slides);
        $this->assertSame([0, 1, 2], array_column($slides, 'slide_index'));
        $this->assertSame('hook', $slides[0]['role']);
        // composited_url carries a ?v=<updated_at> cache-buster (a re-skin overwrites
        // the same file path, so the param forces browser/Cloudflare to refetch).
        $this->assertStringStartsWith('https://x/0.mp4?v=', $slides[0]['composited_url']);
        $this->assertSame('Cursor', $slides[1]['header_title']);
    }

    public function test_image_mode_job_returns_empty_video_slides(): void
    {
        $user = User::factory()->create();
        $job = RepurposeJob::factory()->create(['mode' => 'carousel', 'status' => 'drafted']);

        $res = $this->actingAs($user, 'sanctum')->getJson("/api/admin/repurpose/{$job->id}");

        $res->assertOk();
        $this->assertSame([], $res->json('data.video_slides'));
    }
}
