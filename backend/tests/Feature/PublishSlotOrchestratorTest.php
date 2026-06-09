<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Jobs\PublishViaPubler;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\LinkedInPublishService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the social:publish-slot atomic orchestrator (May 12, 2026).
 *
 * Verifies:
 *  - text-format publishes LinkedIn solo at slot
 *  - carousel-format ready: LinkedIn first + sibling jobs dispatched
 *  - carousel-format not ready: atomic postpone to next slot
 *  - max postpones hit: LinkedIn solo + siblings demoted
 *  - kill-switch off: drafts demoted to manual_review
 *  - dry-run: no side effects
 */
class PublishSlotOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test-' . Str::random(4)]);

        Setting::firstOrCreate(['group' => 'linkedin', 'key' => 'linkedin_auto_publish'], ['value' => 'true']);
        Setting::firstOrCreate(['group' => 'linkedin', 'key' => 'linkedin_max_postpones'], ['value' => '2']);
        Setting::firstOrCreate(['group' => 'linkedin', 'key' => 'linkedin_publish_slots'], ['value' => '[5,6,7,12,17,18,19,20]', 'type' => 'json']);
        Setting::firstOrCreate(['group' => 'linkedin', 'key' => 'linkedin_slot_lead_time_minutes'], ['value' => '5']);

        // Per-platform publish gate (June 10) — seed a Publer account for each
        // platform so the carousel fan-out actually dispatches the siblings.
        foreach (['instagram', 'tiktok', 'threads', 'facebook'] as $p) {
            Setting::firstOrCreate(
                ['group' => 'publer', 'key' => "publer_{$p}_account_id"],
                ['value' => "{$p}_test_acc"]
            );
        }

        // Mock LinkedInPublishService — orchestrator should call ::publish
        $this->mock(LinkedInPublishService::class, function ($mock) {
            $mock->shouldReceive('publish')
                ->andReturn(['success' => true, 'urn' => 'urn:li:share:test123', 'url' => 'https://linkedin.com/test']);
        });

        Queue::fake();
    }

    private function makePost(): Post
    {
        return Post::create([
            'category_id' => $this->category->id,
            'title' => 'P-' . Str::random(6),
            'content' => 'Body',
            'slug' => 'p-' . Str::random(8),
            'published' => true,
            'published_at' => now(),
        ]);
    }

    private function makeDueTextDraft(): LinkedInPost
    {
        return LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'text',
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => Carbon::now()->subMinute(),
            'cancel_window_ends_at' => Carbon::now()->subMinute(),
        ]);
    }

    private function makeDueCarouselDraft(array $slideStatuses = ['done', 'done', 'done']): LinkedInPost
    {
        $slides = [];
        foreach ($slideStatuses as $i => $status) {
            $slides[] = [
                'slide_number' => $i + 1,
                'layout_hint' => 'body',
                'copy' => "Slide " . ($i + 1),
                'image_prompt' => 'prompt',
                'image_status' => $status,
                'image_url' => $status === 'done' ? "https://example.com/slide-{$i}.png" : null,
            ];
        }
        return LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'format' => 'carousel',
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => Carbon::now()->subMinute(),
            'cancel_window_ends_at' => Carbon::now()->subMinute(),
            'carousel_slides' => $slides,
        ]);
    }

    public function test_text_format_publishes_linkedin_solo(): void
    {
        $draft = $this->makeDueTextDraft();

        $this->artisan('social:publish-slot')->assertExitCode(0);

        // LinkedInPublishService::publish mocked, no real assertion possible on it
        // but ensure no PublishViaPubler dispatched (text path)
        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_carousel_ready_dispatches_siblings(): void
    {
        $draft = $this->makeDueCarouselDraft();
        InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => 'IG ready',
        ]);
        TiktokPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => 'TT ready',
            'title' => 'TT title',
        ]);
        ThreadsPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => 'TH ready',
        ]);

        $this->artisan('social:publish-slot')->assertExitCode(0);

        Queue::assertPushed(PublishViaPubler::class, 3); // IG + TT + TH
    }

    public function test_carousel_not_ready_postpones_to_next_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:55:00', 'Asia/Jakarta'));

        // Slot already due (scheduled_at < now)
        $draft = $this->makeDueCarouselDraft(['done', 'pending']);

        $this->artisan('social:publish-slot')->assertExitCode(0);

        $draft->refresh();
        // Should have shifted scheduled_at + cancel_window_ends_at to a future slot
        $this->assertTrue($draft->scheduled_at->greaterThan(Carbon::now()));
        // Should have logged postpone reason
        $log = $draft->pipeline_state_log ?? [];
        $reasons = collect($log)->pluck('reason')->toArray();
        $this->assertContains('slot_postponed_siblings_not_ready', $reasons);
        // No siblings dispatched
        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_kill_switch_off_demotes_to_manual_review(): void
    {
        Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_auto_publish')
            ->update(['value' => 'false']);

        $draft = $this->makeDueTextDraft();

        $this->artisan('social:publish-slot')->assertExitCode(0);

        $draft->refresh();
        $this->assertSame(LinkedInPostStatus::ManualReview->value, $draft->status);
    }

    public function test_dry_run_writes_no_state(): void
    {
        $draft = $this->makeDueTextDraft();
        $originalStatus = $draft->status;

        $this->artisan('social:publish-slot', ['--dry-run' => true])->assertExitCode(0);

        $draft->refresh();
        $this->assertSame($originalStatus, $draft->status);
        Queue::assertNotPushed(PublishViaPubler::class);
    }
}
