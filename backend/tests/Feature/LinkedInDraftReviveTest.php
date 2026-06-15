<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /admin/linkedin-drafts/{id}/revive — "Balik ke Social Studio".
 *
 * Un-cancels a CANCELLED draft back to manual_review WITHOUT regenerating
 * (rendered carousel slides preserved), so it becomes an actionable draft in
 * Social Studio again. The FSM rejects revive from any non-cancelled status.
 */
class LinkedInDraftReviveTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Category', 'slug' => 'cat-'.Str::random(6),
        ]);

        return Post::create([
            'category_id' => $category->id,
            'title' => 'Revive Test',
            'content' => 'Revive source.',
            'slug' => 'revive-test-'.Str::random(8),
            'published' => false,
        ]);
    }

    public function test_revive_moves_cancelled_draft_to_manual_review(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $slides = [['slide_number' => 1, 'image_url' => 'https://alisadikinma.com/storage/x.png', 'image_status' => 'done']];
        $draft = LinkedInPost::factory()->cancelled()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'carousel',
            'carousel_slides' => $slides,
        ]);

        $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/revive")
            ->assertOk()
            ->assertJson(['success' => true]);

        $fresh = $draft->fresh();
        $this->assertSame('manual_review', $fresh->status);
        // Rendered slides preserved (no regenerate).
        $this->assertSame($slides, $fresh->carousel_slides);
    }

    public function test_revive_rejected_when_not_cancelled(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $draft = LinkedInPost::factory()->manualReview()->create([
            'post_id' => $this->makePost()->id,
        ]);

        $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/revive")
            ->assertStatus(409);

        $this->assertSame('manual_review', $draft->fresh()->status);
    }

    public function test_revive_404_on_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/admin/linkedin-drafts/999999/revive')->assertStatus(404);
    }

    public function test_revive_requires_auth(): void
    {
        $this->postJson('/api/admin/linkedin-drafts/1/revive')->assertUnauthorized();
    }
}
