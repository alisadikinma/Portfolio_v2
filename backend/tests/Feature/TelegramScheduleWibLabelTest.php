<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every schedule time printed in Telegram must be WIB and say "WIB", even if
 * the caller hands over a UTC Carbon (DB-hydrated models are UTC).
 */
class TelegramScheduleWibLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'telegram_enabled' => 'true', 'telegram_bot_token' => 'TESTTOKEN',
            'telegram_chat_id' => '42', 'telegram_webhook_secret' => 'sekret',
        ] as $k => $v) {
            Setting::updateOrCreate(['group' => 'telegram', 'key' => $k], ['value' => $v, 'type' => 'text']);
        }
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);
    }

    private function sentText(): string
    {
        $text = '';
        Http::assertSent(function ($request) use (&$text) {
            $text = ($request['text'] ?? '').' '.($request['reply_markup'] ?? '');

            return true;
        });

        return $text;
    }

    public function test_schedule_prompt_slot_buttons_are_wib_even_for_utc_slots(): void
    {
        $draft = LinkedInPost::factory()->make(['id' => 1]);
        $utcSlot = Carbon::parse('2026-05-13 05:00:00', 'UTC'); // 12:00 WIB

        app(TelegramNotificationService::class)->sendSchedulePrompt($draft, [$utcSlot]);

        $text = $this->sentText();
        $this->assertStringContainsString('12:00 WIB', $text);
        $this->assertStringNotContainsString('05:00 WIB', $text);
    }

    public function test_schedule_conflict_time_is_wib_for_utc_input(): void
    {
        $draft = LinkedInPost::factory()->make(['id' => 1]);

        app(TelegramNotificationService::class)->sendScheduleConflict(
            $draft,
            Carbon::parse('2026-05-13 05:00:00', 'UTC'),
            ['id' => 2, 'post_title' => 'X', 'scheduled_at' => 'x', 'minutes_apart' => 5]
        );

        $this->assertStringContainsString('12:00 WIB', $this->sentText());
    }

    public function test_schedule_confirmed_time_is_wib_for_utc_input(): void
    {
        $draft = LinkedInPost::factory()->make(['id' => 1]);

        app(TelegramNotificationService::class)->sendScheduleConfirmed($draft, Carbon::parse('2026-05-13 05:00:00', 'UTC'));

        $this->assertStringContainsString('12:00 WIB', $this->sentText());
    }
}
