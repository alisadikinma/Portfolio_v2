<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D — LinkedInDraftController::show enriches a video_carousel anchor with
 * a `repurpose` block (composited MP4s + Zernio state + IG/Threads captions) so
 * the draft detail can be the full management surface. Normal drafts omit it.
 */
class VideoCarouselDraftShowEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create();
    }

    public function test_show_emits_repurpose_block_for_video_carousel(): void
    {
        $cat = Category::firstOrCreate(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $anchor = LinkedInPost::factory()->create([
            'post_id' => $post->id, 'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL, 'status' => 'awaiting_publish',
        ]);
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'drafted', 'linkedin_post_id' => $anchor->id,
            'rewritten' => ['caption_instagram' => 'IG cap', 'caption_threads' => 'TH cap'],
            'zernio_publish' => ['instagram' => ['status' => 'scheduled', 'post_id' => 'z1']],
        ]);
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'composited_status' => 'done', 'composited_path' => 'https://x/0.mp4',
        ]);

        $block = $this->actingAs($this->actor(), 'sanctum')
            ->getJson("/api/admin/linkedin-drafts/{$anchor->id}")
            ->assertOk()
            ->json('data.repurpose');

        $this->assertSame($job->id, $block['id']);
        $this->assertSame(['https://x/0.mp4'], $block['composited_videos']);
        $this->assertSame('IG cap', $block['caption_instagram']);
        $this->assertSame('TH cap', $block['caption_threads']);
        $this->assertSame('scheduled', $block['zernio_publish']['instagram']['status']);
    }

    public function test_show_omits_repurpose_block_for_normal_carousel(): void
    {
        $cat = Category::firstOrCreate(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Normal', 'content' => '<p>b</p>']);
        $anchor = LinkedInPost::factory()->create([
            'post_id' => $post->id, 'format' => 'carousel', 'status' => 'manual_review',
        ]);

        $data = $this->actingAs($this->actor(), 'sanctum')
            ->getJson("/api/admin/linkedin-drafts/{$anchor->id}")
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('repurpose', $data);
    }
}
