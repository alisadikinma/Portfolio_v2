<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RedditPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E — reddit_posts sibling (carousel path). Mirrors threads_posts +
 * zernio_post_id/zernio_request_id, plus Reddit-specific subreddit + 300-char title.
 */
class RedditPostModelTest extends TestCase
{
    use RefreshDatabase;

    private function linkedInDraft(): LinkedInPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-'.uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-'.uniqid(), 'title' => 'T', 'content' => 'B']);

        return LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'c',
            'carousel_slides' => [],
            'hashtags' => [],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ]);
    }

    public function test_reddit_post_persists_and_relates_to_draft(): void
    {
        $draft = $this->linkedInDraft();

        $reddit = RedditPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'format' => 'carousel',
            'title' => 'AI tools that save hours',
            'caption' => 'Long Reddit body…',
            'hashtags' => ['ai', 'tools'],
            'subreddit' => 'u_alisadikinma',
            'zernio_post_id' => 'rid_1',
            'zernio_request_id' => 'req-uuid',
        ]);

        $this->assertSame('u_alisadikinma', $reddit->fresh()->subreddit);
        $this->assertSame(['ai', 'tools'], $reddit->fresh()->hashtags, 'hashtags must cast to array');
        $this->assertSame('rid_1', $reddit->fresh()->zernio_post_id);
        $this->assertSame($reddit->id, $draft->fresh()->redditPost->id, 'LinkedInPost::redditPost must resolve');
    }

    public function test_zernio_post_id_is_unique(): void
    {
        $draft = $this->linkedInDraft();
        RedditPost::create([
            'post_id' => $draft->post_id, 'status' => 'published', 'subreddit' => 'u_alisadikinma', 'zernio_post_id' => 'dup',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        RedditPost::create([
            'post_id' => $draft->post_id, 'status' => 'published', 'subreddit' => 'u_alisadikinma', 'zernio_post_id' => 'dup',
        ]);
    }
}
