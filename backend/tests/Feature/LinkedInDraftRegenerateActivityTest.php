<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\RepurposeJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for the `regenerate_activity` block surfaced by
 * `GET /admin/linkedin-drafts/{id}` (2026-06-11 live backend-activity pass).
 *
 * The detail page (LinkedInDraftDetail.vue) needs to know, while the draft
 * sits in manual_review during the ~3-7 min /carousel-gen re-author, that
 * work IS in flight + WHEN it started — so it can show a live phase + elapsed
 * timer + suppress the stale last_error. The signal is derived (no migration):
 *   - started_at  = value of the dispatch lock `linkedin_regenerate_lock:{id}`
 *                   (the controller stores now()->toIso8601String() there)
 *   - active      = lock present OR any slide image_status === 'reauthoring'
 */
class LinkedInDraftRegenerateActivityTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::random(6),
        ]);

        $slug = 'reactivity-test-' . Str::random(8);
        // Posts table carries legacy title/content NOT NULL columns; sqlite enforces.
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'Reactivity Test',
            'content' => 'Reactivity source.',
            'slug' => $slug,
            'published' => false,
            'published_at' => null,
        ]);

        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'id',
            'title' => 'Reactivity Test ' . Str::random(4),
            'slug' => $slug . '-id',
            'content' => '<p>Reactivity source.</p>',
            'excerpt' => 'Reactivity source.',
        ]);

        return $post;
    }

    protected function setUp(): void
    {
        parent::setUp();
        for ($i = 0; $i < 4; $i++) {
            $this->makePost();
        }
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    private function carouselDraft(array $slideStatuses): LinkedInPost
    {
        $slides = [];
        foreach ($slideStatuses as $i => $status) {
            $slides[] = [
                'slide_number' => $i + 1,
                'layout_hint' => 'body',
                'copy' => 'Slide ' . ($i + 1),
                'image_prompt' => 'prompt',
                'image_status' => $status,
                'image_url' => $status === 'done' ? "https://example.com/s{$i}.png" : '',
            ];
        }

        return LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'carousel',
            'carousel_slides' => $slides,
        ]);
    }

    /** @test */
    public function show_includes_regenerate_activity_active_when_lock_present(): void
    {
        $draft = $this->carouselDraft(['done', 'done', 'done']);
        $startedAt = now()->subMinutes(2)->toIso8601String();
        Cache::put("linkedin_regenerate_lock:{$draft->id}", $startedAt, 960);

        $response = $this->getJson("/api/admin/linkedin-drafts/{$draft->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            // Existing payload intact (regression guard)
            ->assertJsonPath('data.id', $draft->id)
            // New activity block
            ->assertJsonPath('data.regenerate_activity.active', true)
            ->assertJsonPath('data.regenerate_activity.phase', 're_authoring')
            ->assertJsonPath('data.regenerate_activity.started_at', $startedAt);
    }

    /** @test */
    public function show_marks_regenerate_activity_active_when_slides_reauthoring_without_lock(): void
    {
        // tinker / auto dispatch path: slides flipped to reauthoring but no lock.
        $draft = $this->carouselDraft(['reauthoring', 'reauthoring']);

        $response = $this->getJson("/api/admin/linkedin-drafts/{$draft->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.regenerate_activity.active', true)
            ->assertJsonPath('data.regenerate_activity.phase', 're_authoring')
            ->assertJsonPath('data.regenerate_activity.started_at', null);
    }

    /** @test */
    public function show_regenerate_activity_inactive_when_no_lock_and_no_reauthoring(): void
    {
        $draft = $this->carouselDraft(['done', 'done', 'done']);

        $response = $this->getJson("/api/admin/linkedin-drafts/{$draft->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.regenerate_activity.active', false)
            ->assertJsonPath('data.regenerate_activity.phase', null)
            ->assertJsonPath('data.regenerate_activity.started_at', null);
    }

    /** @test */
    public function regenerate_repoints_linked_repurpose_job_to_the_new_draft(): void
    {
        Bus::fake(); // GenerateLinkedInPost is dispatched by regenerate

        $draft = LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'carousel',
            'status' => 'failed',
            'carousel_slides' => [],
        ]);
        $job = RepurposeJob::factory()->create([
            'mode' => 'carousel',
            'status' => 'drafted',
            'linkedin_post_id' => $draft->id,
            'anchor_post_id' => $draft->post_id,
        ]);

        $res = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/regenerate");
        $res->assertStatus(201);
        $newId = $res->json('data.id');

        $this->assertNotSame($draft->id, $newId);
        $this->assertSoftDeleted('linkedin_posts', ['id' => $draft->id]);
        // The repurpose linkage now points at the live draft, not the dead one.
        $this->assertSame($newId, $job->fresh()->linkedin_post_id);
    }

    /** @test */
    public function list_exposes_render_progress_for_carousel_drafts(): void
    {
        $draft = $this->carouselDraft(['done', 'generating', 'pending']);

        $res = $this->getJson('/api/admin/linkedin-drafts?per_page=100');
        $res->assertStatus(200);

        $row = collect($res->json('data'))->firstWhere('id', $draft->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['render_done']);
        $this->assertSame(3, $row['render_total']);
    }
}
