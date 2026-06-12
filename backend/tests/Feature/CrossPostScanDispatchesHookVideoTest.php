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
 * Phase F — auto-dispatch the GROK hook video on IG fan-out.
 *
 * The cross-post scan only fans out a carousel once its slides are 'done', so
 * the hook slide is rendered by the time the IG sibling is created — that's the
 * single trigger point for GenerateHookVideo (the job itself re-checks + is
 * idempotent).
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

    public function test_fanout_dispatches_hook_video_for_the_ig_sibling(): void
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
        $this->assertNotNull($ig, 'IG sibling should be created on fan-out');

        Queue::assertPushed(GenerateHookVideo::class, fn ($j) => $j->instagramPostId === $ig->id);
    }
}
