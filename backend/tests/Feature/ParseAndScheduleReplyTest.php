<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Jobs\ParseAndScheduleReply;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Phase F — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * The queued Claude-CLI free-text parser: parse → validate (future / weekday /
 * non-holiday) → schedule, conflict-confirm, or re-prompt.
 */
class ParseAndScheduleReplyTest extends TestCase
{
    use RefreshDatabase;

    private string $chatId = '99';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-18 00:00:00', 'Asia/Jakarta')); // Tue
        config(['services.repurpose.driver' => 'local']);

        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => $this->chatId, 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stateKey(): string
    {
        return "telegram_schedule_state:{$this->chatId}";
    }

    private function fakeCli(?string $iso): void
    {
        $json = $iso === null ? '{"datetime":null}' : '{"datetime":"' . $iso . '"}';
        Process::fake(['*' => Process::result(output: $json)]);
    }

    private function makeDraft(string $status = 'manual_review', ?Carbon $scheduledAt = null): LinkedInPost
    {
        $category = Category::create(['name' => 'T', 'slug' => 't-' . uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'D ' . uniqid(),
            'slug' => 'd-' . uniqid(),
            'content' => 'x',
            'published' => true,
            'published_at' => now(),
        ]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => $status,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    private function openConversation(int $draftId): void
    {
        Cache::put($this->stateKey(), [
            'draft_id' => $draftId,
            'step' => 'awaiting_datetime',
            'candidate_slots' => [Carbon::parse('2026-08-19 12:00:00', 'Asia/Jakarta')->toIso8601String()],
        ], now()->addMinutes(60));
    }

    public function test_valid_future_weekday_schedules(): void
    {
        $draft = $this->makeDraft();
        $this->openConversation($draft->id);
        $this->fakeCli('2026-08-19T12:00:00+07:00'); // Wed

        ParseAndScheduleReply::dispatchSync($this->chatId, $draft->id, 'besok jam 12');

        $draft->refresh();
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $draft->status);
        $this->assertSame('2026-08-19 12:00', $draft->scheduled_at->format('Y-m-d H:i'));
        $this->assertNull(Cache::get($this->stateKey()));
    }

    public function test_weekend_datetime_reprompts_without_scheduling(): void
    {
        $draft = $this->makeDraft();
        $this->openConversation($draft->id);
        $this->fakeCli('2026-08-22T12:00:00+07:00'); // Sat

        ParseAndScheduleReply::dispatchSync($this->chatId, $draft->id, 'sabtu jam 12');

        $this->assertSame('manual_review', $draft->fresh()->status);
        $this->assertNotNull(Cache::get($this->stateKey()), 'state kept for retry');
    }

    public function test_holiday_datetime_reprompts(): void
    {
        $draft = $this->makeDraft();
        $this->openConversation($draft->id);
        $this->fakeCli('2026-08-25T12:00:00+07:00'); // Maulid Nabi (Tue, future)

        ParseAndScheduleReply::dispatchSync($this->chatId, $draft->id, '25 agustus jam 12');

        $this->assertSame('manual_review', $draft->fresh()->status);
        $this->assertNotNull(Cache::get($this->stateKey()));
    }

    public function test_conflict_moves_state_to_confirm(): void
    {
        $proposed = Carbon::parse('2026-08-19 12:00:00', 'Asia/Jakarta');
        // Pre-existing scheduled draft occupying the same slot.
        $this->makeDraft('awaiting_publish', $proposed);

        $draft = $this->makeDraft();
        $this->openConversation($draft->id);
        $this->fakeCli('2026-08-19T12:00:00+07:00');

        ParseAndScheduleReply::dispatchSync($this->chatId, $draft->id, '19 agustus jam 12');

        $this->assertSame('manual_review', $draft->fresh()->status, 'not scheduled while conflict pending');
        $state = Cache::get($this->stateKey());
        $this->assertSame('awaiting_conflict_confirm', $state['step']);
        $this->assertSame($proposed->toIso8601String(), $state['proposed_slot']);
    }

    public function test_unparseable_sends_help_and_keeps_state(): void
    {
        $draft = $this->makeDraft();
        $this->openConversation($draft->id);
        $this->fakeCli(null); // {"datetime":null}

        ParseAndScheduleReply::dispatchSync($this->chatId, $draft->id, 'halo apa kabar');

        $this->assertSame('manual_review', $draft->fresh()->status);
        $this->assertNotNull(Cache::get($this->stateKey()));
    }

    public function test_stale_state_draft_mismatch_is_noop(): void
    {
        $draft = $this->makeDraft();
        $this->openConversation($draft->id);
        $this->fakeCli('2026-08-19T12:00:00+07:00');

        // Reply targets a different draft id than the open conversation.
        ParseAndScheduleReply::dispatchSync($this->chatId, $draft->id + 777, 'besok jam 12');

        $this->assertSame('manual_review', $draft->fresh()->status);
    }
}
