<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for `POST /admin/linkedin-drafts/{id}/check-conflict` — the soft
 * conflict-warning lookup that fires from the reschedule modal as the
 * operator types a new datetime.
 *
 * Per design doc §4 the check is "soft": it informs the operator that
 * another live draft is within ±30 min but never blocks the reschedule —
 * announcement + Q&A back-to-back is legitimate.
 */
class LinkedInDraftCheckConflictTest extends TestCase
{
    use RefreshDatabase;

    /**
     * LinkedInPostFactory expects a real Post id — PostFactory is a stub,
     * so we mint a minimal one ourselves (mirrors ReapStuckLinkedInPostsTest).
     */
    private function makePost(): Post
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::random(6),
        ]);

        $slug = 'conflict-test-' . Str::random(8);
        // Posts table still carries legacy `title` + `content` NOT NULL columns
        // from the original 2025-10-02 migration even though production reads
        // these from post_translations. Test SQLite enforces strictly.
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'Conflict Test',
            'content' => 'Conflict test source.',
            'slug' => $slug,
            'published' => false,
            'published_at' => null,
        ]);

        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'id',
            'title' => 'Conflict Test ' . Str::random(4),
            'slug' => $slug . '-id',
            'content' => '<p>Conflict test source.</p>',
            'excerpt' => 'Conflict test source.',
        ]);

        return $post;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Pre-mint Posts so LinkedInPostFactory has fresh post_ids
        // (the factory dedupes against existing LinkedInPost rows).
        for ($i = 0; $i < 8; $i++) {
            $this->makePost();
        }
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function returns_no_conflict_when_no_other_drafts_in_window(): void
    {
        $this->authenticate();
        $draft = LinkedInPost::factory()->awaitingPublish()->create();

        // Pick a time 5 hours away from any seeded factory time
        $proposed = now()->addHours(5)->toIso8601String();

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => $proposed,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_conflict', false)
            ->assertJsonPath('data.window_minutes', 30);
        $this->assertCount(0, $response->json('data.conflicts'));
    }

    /** @test */
    public function returns_conflict_when_other_draft_within_30_min(): void
    {
        $this->authenticate();
        $proposed = now()->addDay()->setTime(10, 0, 0);

        // The draft being rescheduled
        $draft = LinkedInPost::factory()->manualReview()->create();

        // Conflicting draft: scheduled 25 min after proposed time
        $other = LinkedInPost::factory()->awaitingPublish()->create([
            'scheduled_at' => $proposed->copy()->addMinutes(25),
        ]);

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => $proposed->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', true);

        $conflicts = $response->json('data.conflicts');
        $this->assertCount(1, $conflicts);
        $this->assertSame($other->id, $conflicts[0]['id']);
        $this->assertSame(25, $conflicts[0]['minutes_apart']);
    }

    /** @test */
    public function excludes_self_from_conflict_check(): void
    {
        $this->authenticate();
        $proposed = now()->addDay()->setTime(10, 0, 0);

        // The only draft, currently scheduled at the EXACT proposed time
        $draft = LinkedInPost::factory()->awaitingPublish()->create([
            'scheduled_at' => $proposed->copy(),
        ]);

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => $proposed->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', false);
    }

    /** @test */
    public function excludes_soft_deleted_drafts(): void
    {
        $this->authenticate();
        $proposed = now()->addDay()->setTime(10, 0, 0);

        $draft = LinkedInPost::factory()->manualReview()->create();

        // Conflicting draft, then soft-deleted (e.g., regenerate flow)
        $deleted = LinkedInPost::factory()->awaitingPublish()->create([
            'scheduled_at' => $proposed->copy()->addMinutes(15),
        ]);
        $deleted->delete(); // soft delete

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => $proposed->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', false);
    }

    /** @test */
    public function excludes_non_live_statuses(): void
    {
        $this->authenticate();
        $proposed = now()->addDay()->setTime(10, 0, 0);

        $draft = LinkedInPost::factory()->manualReview()->create();

        // A cancelled draft within window — must NOT count as conflict
        LinkedInPost::factory()->cancelled()->create([
            'scheduled_at' => $proposed->copy()->addMinutes(15),
        ]);

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => $proposed->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', false);
    }

    /** @test */
    public function at_boundary_29min_inside_window_31min_outside(): void
    {
        $this->authenticate();
        $proposed = now()->addDay()->setTime(10, 0, 0);

        $draft = LinkedInPost::factory()->manualReview()->create();

        // Inside window — 29 min apart
        $inside = LinkedInPost::factory()->awaitingPublish()->create([
            'scheduled_at' => $proposed->copy()->addMinutes(29),
        ]);

        // Outside window — 31 min apart
        LinkedInPost::factory()->awaitingPublish()->create([
            'scheduled_at' => $proposed->copy()->addMinutes(31),
        ]);

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => $proposed->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', true);

        $conflicts = $response->json('data.conflicts');
        $this->assertCount(1, $conflicts);
        $this->assertSame($inside->id, $conflicts[0]['id']);
    }

    /** @test */
    public function returns_suggested_alternatives_when_posting_time_rules_table_exists_with_optimal_rows(): void
    {
        if (! Schema::hasTable('posting_time_rules')) {
            $this->markTestSkipped('posting_time_rules table not present (P1 migration not yet shipped).');
        }

        $this->authenticate();
        $draft = LinkedInPost::factory()->manualReview()->create();

        $proposed = now()->next(\Carbon\Carbon::TUESDAY)->setTime(10, 0, 0);
        $dow = $proposed->dayOfWeek;

        // Seed 3 optimal rules for this dow
        \DB::table('posting_time_rules')->insert([
            [
                'platform' => 'linkedin', 'audience' => 'b2b_tech',
                'day_of_week' => $dow, 'hour' => 9, 'score' => 90,
                'timezone' => 'Asia/Jakarta', 'last_researched_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'platform' => 'linkedin', 'audience' => 'b2b_tech',
                'day_of_week' => $dow, 'hour' => 11, 'score' => 85,
                'timezone' => 'Asia/Jakarta', 'last_researched_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'platform' => 'linkedin', 'audience' => 'b2b_tech',
                'day_of_week' => $dow, 'hour' => 14, 'score' => 80,
                'timezone' => 'Asia/Jakarta', 'last_researched_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            // Below threshold — should NOT be returned
            [
                'platform' => 'linkedin', 'audience' => 'b2b_tech',
                'day_of_week' => $dow, 'hour' => 22, 'score' => 50,
                'timezone' => 'Asia/Jakarta', 'last_researched_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => $proposed->toIso8601String(),
        ]);

        $response->assertStatus(200);
        $alternatives = $response->json('data.suggested_alternatives');
        $this->assertCount(3, $alternatives);
        $this->assertContains('09:00', $alternatives);
        $this->assertContains('11:00', $alternatives);
        $this->assertContains('14:00', $alternatives);
        $this->assertNotContains('22:00', $alternatives);
    }

    /** @test */
    public function gracefully_returns_empty_alternatives_when_table_missing(): void
    {
        // Drop the table if it exists (P1 migration may have run)
        Schema::dropIfExists('posting_time_rules');

        $this->authenticate();
        $draft = LinkedInPost::factory()->manualReview()->create();

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.suggested_alternatives', []);
    }

    /** @test */
    public function requires_auth(): void
    {
        $draft = LinkedInPost::factory()->manualReview()->create();

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function validates_at_field_required_and_date_format(): void
    {
        $this->authenticate();
        $draft = LinkedInPost::factory()->manualReview()->create();

        // Missing `at`
        $missingResponse = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", []);
        $missingResponse->assertStatus(422)->assertJsonValidationErrors(['at']);

        // Garbage string
        $badResponse = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/check-conflict", [
            'at' => 'not-a-date',
        ]);
        $badResponse->assertStatus(422)->assertJsonValidationErrors(['at']);
    }
}
