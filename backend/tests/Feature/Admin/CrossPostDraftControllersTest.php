<?php

namespace Tests\Feature\Admin;

use App\Enums\FacebookPostStatus;
use App\Enums\InstagramPostStatus;
use App\Enums\TiktokPostStatus;
use App\Jobs\GenerateFacebookPost;
use App\Jobs\GenerateInstagramPost;
use App\Jobs\GenerateTiktokPost;
use App\Jobs\PublishViaPubler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for the 3 cross-post admin controllers (Facebook, Instagram,
 * TikTok). They share the same trait — one happy-path test per platform +
 * a small set of edge cases that exercise the trait's branching logic.
 */
class CrossPostDraftControllersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // Approve action is gated on PUBLER_PUBLISH_ENABLED until Phase H+
        // ships the real Publer transport. Tests that exercise the FSM
        // approve→publishing path must enable the flag explicitly.
        config(['social-cross-post.publer.enabled' => true]);
        $this->admin = User::factory()->create();
    }

    public function test_approve_returns_503_when_publer_disabled(): void
    {
        config(['social-cross-post.publer.enabled' => false]);
        $id = $this->seedDraft('instagram_posts', InstagramPostStatus::AwaitingReview->value);

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/instagram-drafts/{$id}/approve");

        $resp->assertStatus(503);
        Queue::assertNotPushed(PublishViaPubler::class);
    }

    /* ---------------- Instagram ---------------- */

    public function test_instagram_index_returns_paginated_list(): void
    {
        $this->seedDraft('instagram_posts', InstagramPostStatus::AwaitingReview->value);
        $this->seedDraft('instagram_posts', InstagramPostStatus::Failed->value);

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/instagram-drafts');

        $resp->assertOk();
        $resp->assertJsonStructure([
            'success',
            'data' => [['id', 'status']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertSame(2, $resp->json('meta.total'));
    }

    public function test_instagram_show_returns_draft(): void
    {
        $id = $this->seedDraft('instagram_posts');

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/instagram-drafts/{$id}");

        $resp->assertOk();
        $this->assertSame($id, $resp->json('data.id'));
    }

    public function test_instagram_update_validates_hashtag_count(): void
    {
        $id = $this->seedDraft('instagram_posts');

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/instagram-drafts/{$id}", [
                'hashtags' => ['#a', '#b', '#c', '#d', '#e', '#f'], // 6 over 5 cap
            ]);

        $resp->assertStatus(422);
    }

    public function test_instagram_approve_transitions_and_dispatches_publer(): void
    {
        $id = $this->seedDraft('instagram_posts', InstagramPostStatus::AwaitingReview->value);

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/instagram-drafts/{$id}/approve");

        $resp->assertOk();
        $this->assertSame('publishing', $resp->json('data.status'));
        Queue::assertPushed(PublishViaPubler::class);
    }

    public function test_instagram_approve_rejects_non_awaiting_status(): void
    {
        $id = $this->seedDraft('instagram_posts', InstagramPostStatus::Failed->value);

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/instagram-drafts/{$id}/approve");

        $resp->assertStatus(422);
        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_instagram_cancel_transitions_to_cancelled(): void
    {
        $id = $this->seedDraft('instagram_posts', InstagramPostStatus::AwaitingReview->value);

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/instagram-drafts/{$id}/cancel");

        $resp->assertOk();
        $this->assertSame('cancelled', $resp->json('data.status'));
    }

    public function test_instagram_regenerate_softdeletes_and_creates_new(): void
    {
        $id = $this->seedDraft('instagram_posts', InstagramPostStatus::Failed->value);

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/instagram-drafts/{$id}/regenerate");

        $resp->assertStatus(201);
        $this->assertSame('pending_generation', $resp->json('data.status'));
        $this->assertNotSame($id, $resp->json('data.id'));
        Queue::assertPushed(GenerateInstagramPost::class);
    }

    /* ---------------- TikTok ---------------- */

    public function test_tiktok_update_validates_hashtag_count_5_to_8(): void
    {
        $id = $this->seedDraft('tiktok_posts');

        // 4 hashtags fails (below floor 5)
        $resp1 = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/tiktok-drafts/{$id}", [
                'hashtags' => ['#a', '#b', '#c', '#d'],
            ]);
        $resp1->assertStatus(422);

        // 6 hashtags pass
        $resp2 = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/tiktok-drafts/{$id}", [
                'hashtags' => ['#a', '#b', '#c', '#d', '#e', '#f'],
            ]);
        $resp2->assertOk();
    }

    public function test_tiktok_approve_dispatches_publer_with_tiktok_class(): void
    {
        $id = $this->seedDraft('tiktok_posts', TiktokPostStatus::AwaitingReview->value);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/tiktok-drafts/{$id}/approve")
            ->assertOk();

        Queue::assertPushed(PublishViaPubler::class, function ($job) {
            // P4 signature change (May 12): job carries short platform string
            // ('tiktok'|'instagram'|'threads'|'facebook'), not FQN modelClass.
            return $job->platform === 'tiktok';
        });
    }

    /* ---------------- Facebook ---------------- */

    public function test_facebook_index_filters_by_format(): void
    {
        $this->seedDraft('facebook_posts', FacebookPostStatus::AwaitingReview->value, 'text');
        $this->seedDraft('facebook_posts', FacebookPostStatus::AwaitingReview->value, 'carousel');

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/facebook-drafts?format=text');

        $resp->assertOk();
        $this->assertSame(1, $resp->json('meta.total'));
    }

    public function test_facebook_update_accepts_link_url(): void
    {
        $id = $this->seedDraft('facebook_posts', FacebookPostStatus::AwaitingReview->value, 'text');

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/facebook-drafts/{$id}", [
                'link_url' => 'https://alisadikinma.com/blog/test',
                'caption' => 'Short FB caption.',
                'hashtags' => ['#brand'],
            ]);

        $resp->assertOk();
        $this->assertSame('https://alisadikinma.com/blog/test', $resp->json('data.link_url'));
    }

    /* ---------------- Auth ---------------- */

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/admin/instagram-drafts')->assertStatus(401);
    }

    public function test_calendar_endpoint_returns_pinned_rows(): void
    {
        $this->seedDraft('instagram_posts', InstagramPostStatus::Publishing->value, null, [
            'scheduled_at' => now()->addDays(2),
        ]);

        $resp = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/instagram-drafts/calendar?from='
                . now()->toDateString() . '&to=' . now()->addDays(7)->toDateString());

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertNotNull($resp->json('data.0.pin_at'));
    }

    /* ---------------- Helpers ---------------- */

    private function seedDraft(
        string $table,
        ?string $status = null,
        ?string $format = null,
        array $extra = [],
    ): int {
        $categoryId = DB::table('categories')->value('id');
        if ($categoryId === null) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => 'General',
                'slug' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $postId = DB::table('posts')->insertGetId([
            'category_id' => $categoryId,
            'title' => 'Test',
            'slug' => 'test-' . uniqid(),
            'content' => 'body',
            'published' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkedinPostId = DB::table('linkedin_posts')->insertGetId([
            'post_id' => $postId,
            'format' => 'carousel',
            'content' => '',
            'hashtags' => json_encode([]),
            'status' => 'awaiting_publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = array_merge([
            'post_id' => $postId,
            'linkedin_post_id' => $linkedinPostId,
            'status' => $status ?? 'pending_generation',
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra);

        if ($table === 'facebook_posts' && $format !== null) {
            $row['format'] = $format;
        }
        if ($table === 'facebook_posts' && !isset($row['format'])) {
            $row['format'] = 'carousel';
        }

        return DB::table($table)->insertGetId($row);
    }
}
