<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for `DELETE /admin/linkedin-drafts/{id}` — the Social Studio
 * "Delete" action for blog-origin drafts. Soft-delete (the model uses
 * SoftDeletes) so the row leaves every queue list but stays for audit.
 */
class LinkedInDraftDestroyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * LinkedInPostFactory expects a real Post id — PostFactory is a stub, so we
     * mint a minimal one ourselves (mirrors LinkedInDraftCheckConflictTest).
     */
    private function makePost(): Post
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::random(6),
        ]);

        $slug = 'destroy-test-' . Str::random(8);
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'Destroy Test',
            'content' => 'Destroy test source.',
            'slug' => $slug,
            'published' => false,
            'published_at' => null,
        ]);

        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'id',
            'title' => 'Destroy Test ' . Str::random(4),
            'slug' => $slug . '-id',
            'content' => '<p>Destroy test source.</p>',
            'excerpt' => 'Destroy test source.',
        ]);

        return $post;
    }

    public function test_destroy_soft_deletes_the_draft(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $draft = LinkedInPost::factory()->manualReview()->create([
            'post_id' => $this->makePost()->id,
        ]);

        $this->deleteJson("/api/admin/linkedin-drafts/{$draft->id}")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSoftDeleted('linkedin_posts', ['id' => $draft->id]);
    }

    public function test_destroy_404_on_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/admin/linkedin-drafts/999999')
            ->assertStatus(404);
    }

    public function test_destroy_requires_auth(): void
    {
        $this->deleteJson('/api/admin/linkedin-drafts/1')->assertUnauthorized();
    }
}
