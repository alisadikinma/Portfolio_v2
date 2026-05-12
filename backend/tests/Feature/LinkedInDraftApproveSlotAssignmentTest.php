<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for `POST /admin/linkedin-drafts/{id}/approve` after the May 12
 * fixed-slot scheduler ship.
 *
 * Behaviour change vs pre-May-12:
 *  - scheduled_at = next available slot from linkedin_publish_slots (NOT now())
 *  - cancel_window_ends_at = same slot (NOT now() + 15 min)
 *  - Operator-provided publish_at still wins (manual override)
 */
class LinkedInDraftApproveSlotAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        // Override subpath APP_URL from local .env so feature test routes
        // resolve cleanly (per CLAUDE.md known issue, May 6 entry).
        config(['app.url' => 'http://localhost']);
        \URL::forceRootUrl('http://localhost');

        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_publish_slots'],
            ['value' => '[5,6,7,12,17,18,19,20]', 'type' => 'json']
        );
        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_slot_lead_time_minutes'],
            ['value' => '5', 'type' => 'text']
        );

        $cat = Category::create([
            'name' => 'Test', 'slug' => 'test-' . Str::random(4),
        ]);
        $this->post = Post::create([
            'category_id' => $cat->id,
            'title' => 'Slot Approve Test',
            'content' => 'Body',
            'slug' => 'slot-approve-test-' . Str::random(6),
            'published' => true,
            'published_at' => now(),
        ]);

        $this->admin = User::factory()->create();
    }

    public function test_approve_without_publish_at_assigns_next_fixed_slot(): void
    {
        Sanctum::actingAs($this->admin);
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:00:00', 'Asia/Jakarta'));

        $draft = LinkedInPost::factory()->create([
            'post_id' => $this->post->id,
            'status' => LinkedInPostStatus::ManualReview->value,
            'format' => 'text',
        ]);

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/approve");
        $response->assertOk();

        $draft->refresh();
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $draft->status);
        $this->assertSame(
            '2026-05-13 05:00:00',
            $draft->scheduled_at->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s')
        );
        // Post-May-12: cancel_window_ends_at = scheduled_at (operator can
        // cancel until slot fires).
        $this->assertEquals($draft->scheduled_at, $draft->cancel_window_ends_at);
    }

    public function test_three_concurrent_approvals_get_three_consecutive_slots_fifo(): void
    {
        Sanctum::actingAs($this->admin);
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:00:00', 'Asia/Jakarta'));

        $drafts = [];
        for ($i = 0; $i < 3; $i++) {
            $p = Post::create([
                'category_id' => $this->post->category_id,
                'title' => "Test {$i}",
                'content' => 'Body',
                'slug' => "fifo-test-{$i}-" . Str::random(4),
                'published' => true,
                'published_at' => now(),
            ]);
            $drafts[] = LinkedInPost::factory()->create([
                'post_id' => $p->id,
                'status' => LinkedInPostStatus::ManualReview->value,
                'format' => 'text',
            ]);
        }

        foreach ($drafts as $d) {
            $this->postJson("/api/admin/linkedin-drafts/{$d->id}/approve")->assertOk();
        }

        $hours = collect($drafts)
            ->map(fn ($d) => (int) $d->fresh()->scheduled_at->copy()->setTimezone('Asia/Jakarta')->format('H'))
            ->sort()
            ->values()
            ->all();

        // Slots 5, 6, 7 — three consecutive available hours
        $this->assertEquals([5, 6, 7], $hours);
    }

    public function test_explicit_publish_at_override_wins_over_slot(): void
    {
        Sanctum::actingAs($this->admin);
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:00:00', 'Asia/Jakarta'));

        $draft = LinkedInPost::factory()->create([
            'post_id' => $this->post->id,
            'status' => LinkedInPostStatus::ManualReview->value,
            'format' => 'text',
        ]);

        $manualTime = Carbon::parse('2026-05-13 14:30:00', 'Asia/Jakarta');

        $response = $this->postJson("/api/admin/linkedin-drafts/{$draft->id}/approve", [
            'publish_at' => $manualTime->toIso8601String(),
        ]);
        $response->assertOk();

        $draft->refresh();
        $this->assertSame(
            '2026-05-13 14:30:00',
            $draft->scheduled_at->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s')
        );
    }
}
