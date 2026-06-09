<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin freshness endpoints (Phase D):
 *   GET  /api/admin/content-engine/stale-posts   → real Post::stale() data
 *   POST /api/admin/content-engine/posts/{id}/mark-reviewed → clears staleness
 * Both require auth:sanctum.
 */
class StalePostsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = [], string $title = 'Post'): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai'], ['name' => 'AI']);
        $post = Post::create(array_merge([
            'category_id' => $category->id,
            'slug' => 'post-' . uniqid(),
            'content' => '<p>body</p>',
            'published' => true,
            'published_at' => now()->subDays(120),
        ], $overrides));
        $post->translations()->create([
            'language' => 'en',
            'title' => $title,
            'slug' => $post->slug,
            'content' => '<p>body</p>',
        ]);

        return $post;
    }

    public function test_stale_posts_requires_auth(): void
    {
        $this->getJson('/api/admin/content-engine/stale-posts')->assertStatus(401);
    }

    public function test_stale_posts_returns_only_stale(): void
    {
        $user = User::factory()->create();
        $stale1 = $this->makePost(['published_at' => now()->subDays(120)], 'Stale One');
        $stale2 = $this->makePost(['published_at' => now()->subDays(95)], 'Stale Two');
        $fresh = $this->makePost(['published_at' => now()->subDays(10)], 'Fresh');

        $res = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/content-engine/stale-posts?days=90');

        $res->assertStatus(200);
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($stale1->id, $ids);
        $this->assertContains($stale2->id, $ids);
        $this->assertNotContains($fresh->id, $ids);

        // Compact shape carries title + days_stale.
        $first = collect($res->json('data'))->firstWhere('id', $stale1->id);
        $this->assertSame('Stale One', $first['title']);
        $this->assertGreaterThanOrEqual(90, $first['days_stale']);
    }

    public function test_mark_reviewed_clears_staleness(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost(['published_at' => now()->subDays(120)], 'Needs Review');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/content-engine/posts/{$post->id}/mark-reviewed")
            ->assertStatus(200);

        $this->assertNotNull($post->fresh()->content_reviewed_at);

        // Post no longer surfaces as stale.
        $res = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/content-engine/stale-posts?days=90');
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertNotContains($post->id, $ids);
    }

    public function test_mark_reviewed_requires_auth(): void
    {
        $post = $this->makePost();
        $this->postJson("/api/admin/content-engine/posts/{$post->id}/mark-reviewed")
            ->assertStatus(401);
    }
}
