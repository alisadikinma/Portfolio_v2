<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\LinkedInSlotReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase H — IG publish waits for the hook video to settle.
 *
 * isReady blocks a carousel slot while its Instagram sibling's hook video is
 * still in-flight (generating/pending) so IG doesn't prematurely publish the
 * all-image fallback. done/failed/null all clear the gate (failed → fallback,
 * null → no hook video requested). IG-only — other platforms are all-image.
 */
class IgHookVideoReadinessGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraftWithIg(?string $hookStatus): LinkedInPost
    {
        $category = Category::create(['name' => 'T', 'slug' => 'c-'.uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-'.uniqid(), 'title' => 'T', 'content' => 'B']);

        $li = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'c',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://x/1.png'],
                ['slide_number' => 2, 'image_status' => 'done', 'image_url' => 'https://x/2.png'],
            ],
            'hashtags' => [],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ]);

        InstagramPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $post->id,
            'status' => 'awaiting_review',
            'caption' => 'IG caption ready',
            'hashtags' => [],
            'hook_video_status' => $hookStatus,
        ]);

        return $li->fresh(['instagramPost']);
    }

    public function test_generating_hook_video_blocks_readiness(): void
    {
        $li = $this->makeDraftWithIg('generating');

        $result = (new LinkedInSlotReadinessService())->isReady($li);

        $this->assertFalse($result['ready']);
        $this->assertContains('instagram_hook_video_generating', $result['blockers']);
    }

    public function test_pending_hook_video_blocks_readiness(): void
    {
        $li = $this->makeDraftWithIg('pending');

        $result = (new LinkedInSlotReadinessService())->isReady($li);

        $this->assertFalse($result['ready']);
        $this->assertContains('instagram_hook_video_pending', $result['blockers']);
    }

    public function test_done_hook_video_is_ready(): void
    {
        $li = $this->makeDraftWithIg('done');

        $result = (new LinkedInSlotReadinessService())->isReady($li);

        $this->assertTrue($result['ready'], json_encode($result['blockers']));
    }

    public function test_failed_hook_video_does_not_block(): void
    {
        $li = $this->makeDraftWithIg('failed');

        $result = (new LinkedInSlotReadinessService())->isReady($li);

        $this->assertNotContains('instagram_hook_video_failed', $result['blockers']);
    }

    public function test_null_hook_video_is_ready(): void
    {
        $li = $this->makeDraftWithIg(null);

        $result = (new LinkedInSlotReadinessService())->isReady($li);

        $this->assertTrue($result['ready'], json_encode($result['blockers']));
    }
}
