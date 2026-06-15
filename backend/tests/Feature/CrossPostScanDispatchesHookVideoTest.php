<?php

namespace Tests\Feature;

use App\Jobs\GenerateHookVideo;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Hook video (image→video for IG carousel slide 1) is MANUAL-ONLY as of
 * 2026-06-15 (operator request). The cross-post scan still creates the IG
 * sibling (so the Image|Video toggle + manual "Regenerate video" trigger are
 * available) but must NOT auto-dispatch GenerateHookVideo — the operator starts
 * it on demand from the draft detail "Video" tab.
 */
class CrossPostScanDispatchesHookVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function doneSlides(): array
    {
        return [
            ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'done', 'image_url' => 'https://x/1.png', 'image_job_uuid' => 'u1'],
            ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'done', 'image_url' => 'https://x/2.png', 'image_job_uuid' => 'u2'],
        ];
    }

    public function test_fanout_creates_ig_sibling_but_does_not_auto_dispatch_hook_video(): void
    {
        // Build the draft manually (the LinkedInPost factory inserts a Post with
        // no category_id → NOT NULL violation on sqlite; passes on CI MySQL).
        $category = Category::create(['name' => 'T', 'slug' => 'c-'.uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'p-'.uniqid(),
            'title' => 'T',
            'content' => 'Body.',
        ]);
        $draft = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => 'validating',
            'content' => 'Caption.',
            'carousel_slides' => $this->doneSlides(),
            'pipeline_state_log' => [],
            'hashtags' => [],
            'updated_at' => now(),
        ]);

        Artisan::call('social-cross-post:scan', ['--min-virality' => 0]);

        $ig = InstagramPost::where('linkedin_post_id', $draft->id)->first();
        $this->assertNotNull($ig, 'IG sibling should still be created on fan-out (keeps the manual Image|Video trigger available)');

        // Hook video is manual-only — the scan must NOT auto-dispatch it.
        Queue::assertNotPushed(GenerateHookVideo::class);
    }
}
