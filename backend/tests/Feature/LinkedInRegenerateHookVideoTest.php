<?php

namespace Tests\Feature;

use App\Jobs\GenerateHookVideo;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase I — regenerate-hook-video endpoint + show() exposure.
 *
 * POST /admin/linkedin-drafts/{id}/regenerate-hook-video resets the IG sibling's
 * hook video to pending + re-dispatches GenerateHookVideo. show() surfaces the
 * hook_video_* fields so the UI can render the Image|Video tab.
 */
class LinkedInRegenerateHookVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    private function makeCarouselWithIg(bool $withIg = true): LinkedInPost
    {
        $category = Category::create(['name' => 'T', 'slug' => 'c-'.uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-'.uniqid(), 'title' => 'T', 'content' => 'B']);

        $li = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'c',
            'carousel_slides' => [['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://x/1.png']],
            'hashtags' => [],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ]);

        if ($withIg) {
            InstagramPost::create([
                'linkedin_post_id' => $li->id,
                'post_id' => $post->id,
                'status' => 'awaiting_review',
                'caption' => 'cap',
                'hashtags' => [],
                'hook_video_status' => 'failed',
                'hook_video_error' => 'old error',
                'hook_video_retry_count' => 2,
            ]);
        }

        return $li;
    }

    public function test_regenerate_resets_and_dispatches(): void
    {
        Bus::fake();
        $li = $this->makeCarouselWithIg();

        $this->postJson("/api/admin/linkedin-drafts/{$li->id}/regenerate-hook-video")
            ->assertStatus(202)
            ->assertJson(['success' => true]);

        $ig = InstagramPost::where('linkedin_post_id', $li->id)->first();
        $this->assertSame('pending', $ig->hook_video_status);
        $this->assertNull($ig->hook_video_error);
        $this->assertSame(0, $ig->hook_video_retry_count);
        Bus::assertDispatched(GenerateHookVideo::class, fn ($j) => $j->instagramPostId === $ig->id);
    }

    public function test_404_when_no_ig_sibling(): void
    {
        Bus::fake();
        $li = $this->makeCarouselWithIg(withIg: false);

        $this->postJson("/api/admin/linkedin-drafts/{$li->id}/regenerate-hook-video")
            ->assertStatus(404);

        Bus::assertNotDispatched(GenerateHookVideo::class);
    }

    public function test_show_exposes_hook_video_fields(): void
    {
        $li = $this->makeCarouselWithIg();

        $this->getJson("/api/admin/linkedin-drafts/{$li->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.instagram_post.hook_video_status', 'failed');
    }
}
