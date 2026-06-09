<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Post::scopeStale() must flag published posts whose freshness anchor
 * (max of content_reviewed_at, published_at) is older than N days, and must
 * NOT flag fresh posts, recently-reviewed posts, or drafts.
 */
class PostStaleScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai'], ['name' => 'AI']);

        return Post::create(array_merge([
            'category_id' => $category->id,
            'slug' => 'post-' . uniqid(),
            'content' => '<p>body</p>',
            'published' => true,
            'published_at' => now()->subDays(100),
        ], $overrides));
    }

    public function test_stale_scope_flags_only_old_published_posts(): void
    {
        $old = $this->makePost(['published_at' => now()->subDays(100)]);
        $fresh = $this->makePost(['published_at' => now()->subDays(30)]);
        $reviewed = $this->makePost([
            'published_at' => now()->subDays(200),
            'content_reviewed_at' => now(),
        ]);
        $draft = $this->makePost([
            'published' => false,
            'published_at' => now()->subDays(200),
        ]);

        $ids = Post::stale(90)->pluck('id')->all();

        $this->assertContains($old->id, $ids, 'old published post is stale');
        $this->assertNotContains($fresh->id, $ids, 'recent post is not stale');
        $this->assertNotContains($reviewed->id, $ids, 'recently-reviewed post is not stale');
        $this->assertNotContains($draft->id, $ids, 'draft post is never stale');
    }

    public function test_stale_anchor_uses_content_reviewed_at_when_present(): void
    {
        // Published long ago but reviewed 100 days ago → still stale by the anchor.
        $post = $this->makePost([
            'published_at' => now()->subDays(400),
            'content_reviewed_at' => now()->subDays(100),
        ]);

        $this->assertContains($post->id, Post::stale(90)->pluck('id')->all());
    }
}
