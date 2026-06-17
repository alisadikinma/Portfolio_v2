<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase H — scan creates the Reddit sibling (carousel-only, content reused:
 * body = LinkedIn caption, title = hook line / TikTok title; subreddit snapshot).
 * Reddit needs no Generate*Post job (content is reused), so it lands awaiting_review.
 */
class CrossPostScanRedditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function carouselSlides(): array
    {
        return [
            ['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://x/1.png'],
            ['slide_number' => 2, 'image_status' => 'done', 'image_url' => 'https://x/2.png'],
        ];
    }

    public function test_carousel_scan_creates_reddit_sibling_with_reused_content(): void
    {
        $li = $this->seedLinkedInPost('carousel', "Smarter AI agents in 2026\nFull breakdown inside.");

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])->assertExitCode(0);

        $this->assertDatabaseHas('reddit_posts', [
            'linkedin_post_id' => $li,
            'format' => 'carousel',
            'status' => 'awaiting_review',
            'subreddit' => 'u_alisadikinma',
        ]);
        $row = DB::table('reddit_posts')->where('linkedin_post_id', $li)->first();
        $this->assertNotEmpty($row->title, 'Reddit title must be derived (hook line)');
        $this->assertSame('Smarter AI agents in 2026', $row->title, 'title = first hook line of the caption');
        $this->assertStringContainsString('Smarter AI agents', $row->caption, 'body = LinkedIn caption');
    }

    public function test_text_format_creates_no_reddit_sibling(): void
    {
        $this->seedLinkedInPost('text', 'A text-only LinkedIn post');

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])->assertExitCode(0);

        $this->assertDatabaseCount('reddit_posts', 0);
    }

    public function test_reddit_creation_is_idempotent(): void
    {
        $li = $this->seedLinkedInPost('carousel', 'Idempotency check post\nbody');

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])->assertExitCode(0);
        $this->assertDatabaseCount('reddit_posts', 1);

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])->assertExitCode(0);
        $this->assertDatabaseCount('reddit_posts', 1);
    }

    private function seedLinkedInPost(string $format, string $content): int
    {
        $categoryId = DB::table('categories')->value('id')
            ?? DB::table('categories')->insertGetId(['name' => 'General', 'slug' => 'general', 'created_at' => now(), 'updated_at' => now()]);

        $postId = DB::table('posts')->insertGetId([
            'category_id' => $categoryId, 'title' => 'Reddit scan post', 'slug' => 'reddit-scan-'.uniqid(),
            'content' => 'Body.', 'published' => true, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('linkedin_posts')->insertGetId([
            'post_id' => $postId,
            'format' => $format,
            'content' => $content,
            'hashtags' => json_encode(['#test']),
            'carousel_slides' => $format === 'carousel' ? json_encode($this->carouselSlides()) : null,
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
    }
}
