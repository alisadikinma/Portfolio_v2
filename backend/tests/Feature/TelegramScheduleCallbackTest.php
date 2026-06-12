<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase F — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * Telegram kind=schedule inline-button callbacks: slot tap schedules; confirm
 * overrides a conflict; reject re-offers; forged HMAC is rejected.
 */
class TelegramScheduleCallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'cb-secret';
    private string $chatId = '99';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-18 00:00:00', 'Asia/Jakarta')); // Tue

        Setting::create(['group' => 'telegram', 'key' => 'telegram_webhook_secret', 'value' => $this->secret, 'type' => 'text']);
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

    private function makeDraft(string $status = 'manual_review'): LinkedInPost
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
            'scheduled_at' => null,
        ]);
    }

    private function tap(string $callbackData)
    {
        return $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $this->secret])
            ->postJson('/api/automation/telegram/webhook', [
                'callback_query' => ['id' => 'cbq1', 'data' => $callbackData],
            ]);
    }

    public function test_slot_tap_schedules_at_cached_candidate(): void
    {
        $draft = $this->makeDraft();
        $slotIso = Carbon::parse('2026-08-19 12:00:00', 'Asia/Jakarta')->toIso8601String();
        Cache::put($this->stateKey(), [
            'draft_id' => $draft->id,
            'step' => 'awaiting_datetime',
            'candidate_slots' => [$slotIso],
        ], now()->addMinutes(60));

        $data = TelegramNotificationService::signCallback('slot0', 'schedule', $draft->id, $this->secret);
        $this->tap($data)->assertOk();

        $draft->refresh();
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $draft->status);
        $this->assertSame('2026-08-19 12:00', $draft->scheduled_at->format('Y-m-d H:i'));
        $this->assertNull(Cache::get($this->stateKey()), 'state cleared after schedule');
    }

    public function test_forged_hmac_is_rejected_and_does_not_schedule(): void
    {
        $draft = $this->makeDraft();
        Cache::put($this->stateKey(), [
            'draft_id' => $draft->id,
            'step' => 'awaiting_datetime',
            'candidate_slots' => [Carbon::parse('2026-08-19 12:00:00', 'Asia/Jakarta')->toIso8601String()],
        ], now()->addMinutes(60));

        // Valid base shape, wrong hmac.
        $this->tap("slot0:schedule:{$draft->id}:deadbeef0000")->assertOk();

        $draft->refresh();
        $this->assertSame('manual_review', $draft->status, 'forged callback must not schedule');
        $this->assertNotNull(Cache::get($this->stateKey()), 'state untouched');
    }

    public function test_confirm_overrides_conflict_and_schedules_proposed_slot(): void
    {
        $draft = $this->makeDraft();
        $proposed = Carbon::parse('2026-08-19 12:00:00', 'Asia/Jakarta');
        Cache::put($this->stateKey(), [
            'draft_id' => $draft->id,
            'step' => 'awaiting_conflict_confirm',
            'proposed_slot' => $proposed->toIso8601String(),
            'conflict' => ['id' => 999, 'post_title' => 'Other', 'scheduled_at' => $proposed->toIso8601String(), 'minutes_apart' => 0],
        ], now()->addMinutes(60));

        $data = TelegramNotificationService::signCallback('confirm', 'schedule', $draft->id, $this->secret);
        $this->tap($data)->assertOk();

        $draft->refresh();
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $draft->status);
        $this->assertSame('2026-08-19 12:00', $draft->scheduled_at->format('Y-m-d H:i'));
        $this->assertNull(Cache::get($this->stateKey()));
    }

    public function test_reject_reoffers_slots_and_resets_state(): void
    {
        $draft = $this->makeDraft();
        Cache::put($this->stateKey(), [
            'draft_id' => $draft->id,
            'step' => 'awaiting_conflict_confirm',
            'proposed_slot' => Carbon::parse('2026-08-19 12:00:00', 'Asia/Jakarta')->toIso8601String(),
            'conflict' => ['id' => 999, 'post_title' => 'Other', 'scheduled_at' => '...', 'minutes_apart' => 0],
        ], now()->addMinutes(60));

        $data = TelegramNotificationService::signCallback('reject', 'schedule', $draft->id, $this->secret);
        $this->tap($data)->assertOk();

        $draft->refresh();
        $this->assertSame('manual_review', $draft->status, 'reject does not schedule');
        $state = Cache::get($this->stateKey());
        $this->assertSame('awaiting_datetime', $state['step']);
        $this->assertNotEmpty($state['candidate_slots']);
    }
}
