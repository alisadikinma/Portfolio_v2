<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase G — a video_carousel anchor must NEVER be fanned out into FB/IG/TikTok/Threads
 * image siblings by the carousel cross-post scan. Its media is composited MP4s that
 * publish via Zernio (PublishRepurposeViaZernio), not the image-carousel sibling path.
 * The scan only handles format in ['text','carousel'], so video_carousel is disqualified.
 */
class VideoCarouselNotCrossPostScannedTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_ignores_video_carousel(): void
    {
        $cat = Category::firstOrCreate(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Vid', 'content' => '<p>b</p>']);

        // Shaped like a fan-out-ready carousel (awaiting_publish + done slides) EXCEPT
        // the format — proving the format gate, not some other precondition, excludes it.
        LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'status' => 'awaiting_publish',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://x/1.png'],
            ],
        ]);

        $this->artisan('social-cross-post:scan')->assertExitCode(0);

        $this->assertSame(0, FacebookPost::count());
        $this->assertSame(0, InstagramPost::count());
        $this->assertSame(0, TiktokPost::count());
        $this->assertSame(0, ThreadsPost::count());
    }
}
