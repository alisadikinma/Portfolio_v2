<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\TiktokPost;
use App\Services\LinkedInSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * The single scheduling write reused by admin approve, the Telegram slot
 * button, and the Telegram free-text confirm.
 */
class LinkedInSchedulingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-' . uniqid()]);

        return Post::create([
            'category_id' => $category->id,
            'title' => 'Scheduling Test ' . uniqid(),
            'slug' => 'scheduling-test-' . uniqid(),
            'content' => 'Test content',
            'published' => true,
            'published_at' => now(),
        ]);
    }

    public function test_schedule_at_advances_fsm_sets_timestamps_propagates_siblings_clears_flag(): void
    {
        $post = $this->makePost();

        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => LinkedInPostStatus::ManualReview->value,
            'schedule_prompt_sent_at' => now(),
            'scheduled_at' => null,
            'cancel_window_ends_at' => null,
        ]);

        $tiktok = TiktokPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $post->id,
            'status' => 'pending_generation',
            'scheduled_at' => null,
        ]);

        $slot = Carbon::parse('2026-08-18 12:00:00', 'Asia/Jakarta');

        app(LinkedInSchedulingService::class)->scheduleAt($draft, $slot, 'test_reason');

        // Eloquent serializes a WIB Carbon as wall-clock + rehydrates in app
        // tz (existing approve() behaviour) — assert the wall-clock round-trips.
        $wall = $slot->format('Y-m-d H:i');
        $draft->refresh();
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $draft->status);
        $this->assertSame($wall, $draft->scheduled_at->format('Y-m-d H:i'), 'scheduled_at = slot');
        $this->assertSame($wall, $draft->cancel_window_ends_at->format('Y-m-d H:i'), 'cancel_window_ends_at = slot');
        $this->assertNull($draft->schedule_prompt_sent_at, 'prompt flag cleared');

        $tiktok->refresh();
        $this->assertSame($wall, $tiktok->scheduled_at->format('Y-m-d H:i'), 'sibling scheduled_at propagated');
    }

    public function test_schedule_at_on_already_awaiting_publish_reschedules_without_fsm_error(): void
    {
        $post = $this->makePost();

        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'text',
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => Carbon::parse('2026-08-18 05:00:00', 'Asia/Jakarta'),
        ]);

        $newSlot = Carbon::parse('2026-08-19 12:00:00', 'Asia/Jakarta');

        // No InvalidStateTransitionException — same-state advance is skipped.
        app(LinkedInSchedulingService::class)->scheduleAt($draft, $newSlot, 'reschedule');

        $draft->refresh();
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $draft->status);
        $this->assertSame($newSlot->format('Y-m-d H:i'), $draft->scheduled_at->format('Y-m-d H:i'));
    }
}
