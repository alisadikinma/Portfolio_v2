<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentIdeaPublishedDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL subpath trips getJson('/api/...') in tests — see BlogPromoSlotTest for context.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeCompletedIdeaWithPost(string $publishedAt): array
    {
        $category = Category::firstOrCreate(['slug' => 'tech'], ['name' => 'Tech']);

        $post = Post::create([
            'title' => 'Sample Post',
            'slug' => 'sample-post-' . uniqid(),
            'content' => 'body',
            'excerpt' => '',
            'published' => true,
            'published_at' => $publishedAt,
            'category_id' => $category->id,
        ]);

        $idea = ContentIdea::create([
            'title' => 'Sample Idea',
            'status' => 'completed',
            'result_post_id' => $post->id,
            'source' => 'manual',
            'pillar' => 'general',
        ]);

        return compact('idea', 'post');
    }

    /** @test */
    public function content_idea_has_post_belongs_to_relation(): void
    {
        ['idea' => $idea, 'post' => $post] = $this->makeCompletedIdeaWithPost('2026-04-20 10:00:00');

        $relatedPost = $idea->post;

        $this->assertNotNull($relatedPost, 'Expected ContentIdea::post to return Post instance');
        $this->assertEquals($post->id, $relatedPost->id);
    }

    /** @test */
    public function admin_ideas_index_includes_result_post_published_at_field(): void
    {
        ['idea' => $idea, 'post' => $post] = $this->makeCompletedIdeaWithPost('2026-04-20 10:00:00');

        $response = $this->getJson('/api/admin/content-engine/ideas');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $idea->id);
        $this->assertNotNull($item, 'Idea should appear in index response');
        $this->assertArrayHasKey(
            'result_post_published_at',
            $item,
            'Index response must include result_post_published_at field'
        );
        $this->assertStringContainsString(
            '2026-04-20',
            $item['result_post_published_at'] ?? '',
            'result_post_published_at should reflect post.published_at'
        );
    }

    /** @test */
    public function result_post_published_at_is_null_when_result_post_id_is_null(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Draft Idea',
            'status' => 'draft',
            'source' => 'manual',
            'pillar' => 'general',
        ]);

        $response = $this->getJson('/api/admin/content-engine/ideas');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $idea->id);
        $this->assertArrayHasKey('result_post_published_at', $item, 'field must exist even when null');
        $this->assertNull($item['result_post_published_at'], 'draft idea has null result_post_published_at');
    }
}
