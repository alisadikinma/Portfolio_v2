<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\PublerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase G — IG mixed video+image carousel.
 *
 * The buildInstagram prepend path (GROK hook video as media item 0, type=video)
 * still exists but is gated behind the publer_ig_mixed_video_enabled kill-switch,
 * which now defaults OFF: Publer support confirmed (2026-06-12) + a live probe
 * verified that Publer cannot publish a mixed video+image IG carousel (it only
 * does full-image OR full-video). So the default behaviour is the all-image
 * carousel; the prepend path only fires when the kill-switch is explicitly ON.
 */
class PublerInstagramMixedMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://alisadikinma.com']);
        Setting::create(['group' => 'publer', 'key' => 'publer_instagram_account_id', 'value' => 'ig_acc_123']);
    }

    private function makeSibling(array $igAttrs = []): InstagramPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-'.uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-'.uniqid(), 'title' => 'T', 'content' => 'B']);

        $li = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'c',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/slide-01.png'],
                ['slide_number' => 2, 'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/slide-02.png'],
                ['slide_number' => 3, 'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/slide-03.png'],
            ],
            'hashtags' => [],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ]);

        $ig = InstagramPost::create(array_merge([
            'linkedin_post_id' => $li->id,
            'post_id' => $post->id,
            'status' => 'awaiting_review',
            'caption' => 'cap',
            'hashtags' => [],
        ], $igAttrs));
        $ig->load('linkedinPost');

        return $ig;
    }

    public function test_prepends_hook_video_when_enabled_done_and_app_hosted(): void
    {
        // Kill-switch must be EXPLICITLY on — the prepend path is dormant by default.
        Setting::create(['group' => 'publer', 'key' => 'publer_ig_mixed_video_enabled', 'value' => 'true']);

        $ig = $this->makeSibling([
            'hook_video_status' => 'done',
            'hook_video_url' => 'https://alisadikinma.com/storage/linkedin-carousel/grok-hook-1.mp4',
        ]);

        $spec = (new PublerPayloadBuilder())->buildInstagram($ig);

        $this->assertCount(4, $spec['media_urls']);
        $this->assertSame('https://alisadikinma.com/storage/linkedin-carousel/grok-hook-1.mp4', $spec['media_urls'][0]);
        $this->assertSame(['video', 'image', 'image', 'image'], $spec['media_types']);
    }

    public function test_default_is_all_image_when_setting_absent(): void
    {
        // No publer_ig_mixed_video_enabled row → default OFF → all-image even
        // though the hook video is done + app-hosted (Publer can't do mixed).
        $ig = $this->makeSibling([
            'hook_video_status' => 'done',
            'hook_video_url' => 'https://alisadikinma.com/storage/linkedin-carousel/grok-hook-1.mp4',
        ]);

        $spec = (new PublerPayloadBuilder())->buildInstagram($ig);

        $this->assertCount(3, $spec['media_urls']);
        $this->assertSame(['image', 'image', 'image'], $spec['media_types']);
        $this->assertStringNotContainsString('.mp4', json_encode($spec['media_urls']));
    }

    public function test_all_image_when_hook_not_done(): void
    {
        $ig = $this->makeSibling(); // no hook_video_status

        $spec = (new PublerPayloadBuilder())->buildInstagram($ig);

        $this->assertCount(3, $spec['media_urls']);
        $this->assertSame(['image', 'image', 'image'], $spec['media_types']);
        $this->assertStringNotContainsString('.mp4', json_encode($spec['media_urls']));
    }

    public function test_kill_switch_off_forces_all_image(): void
    {
        Setting::create(['group' => 'publer', 'key' => 'publer_ig_mixed_video_enabled', 'value' => 'false']);

        $ig = $this->makeSibling([
            'hook_video_status' => 'done',
            'hook_video_url' => 'https://alisadikinma.com/storage/linkedin-carousel/grok-hook-1.mp4',
        ]);

        $spec = (new PublerPayloadBuilder())->buildInstagram($ig);

        $this->assertCount(3, $spec['media_urls']);
        $this->assertSame(['image', 'image', 'image'], $spec['media_types']);
    }

    public function test_non_app_hosted_hook_video_is_skipped(): void
    {
        $ig = $this->makeSibling([
            'hook_video_status' => 'done',
            'hook_video_url' => 'https://edge-files.geminigen.ai/raw.mp4', // not app-hosted
        ]);

        $spec = (new PublerPayloadBuilder())->buildInstagram($ig);

        $this->assertCount(3, $spec['media_urls']);
        $this->assertSame(['image', 'image', 'image'], $spec['media_types']);
    }
}
