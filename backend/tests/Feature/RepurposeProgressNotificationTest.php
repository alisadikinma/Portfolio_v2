<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase A — sendRepurposeProgress() best-effort live progress bubble +
 * mode-tap acknowledgement. The bubble is a NEW chat message (not the
 * transient answerCallbackQuery toast) so the operator sees the pipeline
 * actually started. Master-toggle gated; callers wrap in try/catch so a
 * notify failure never blocks the FSM.
 */
class RepurposeProgressNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'cb-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['group' => 'telegram', 'key' => 'telegram_webhook_secret', 'value' => $this->secret, 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_send_repurpose_progress_emits_when_enabled(): void
    {
        $job = RepurposeJob::factory()->create();

        $ok = app(TelegramNotificationService::class)
            ->sendRepurposeProgress($job, '🔎 Ekstrak: 5 klaim ditemukan');

        $this->assertTrue($ok);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage')
            && str_contains((string) $req['text'], 'Ekstrak: 5 klaim'));
    }

    public function test_send_repurpose_progress_noop_when_disabled(): void
    {
        Setting::where('key', 'telegram_enabled')->update(['value' => 'false']);
        $job = RepurposeJob::factory()->create();

        $ok = app(TelegramNotificationService::class)
            ->sendRepurposeProgress($job, 'anything');

        $this->assertFalse($ok);
        Http::assertNothingSent();
    }

    public function test_mode_tap_sends_a_progress_ack_bubble(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'received', 'mode' => null]);
        $data = TelegramNotificationService::signCallback('carousel', 'repurpose', $job->id, $this->secret);

        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $this->secret])
            ->postJson('/api/automation/telegram/webhook', [
                'callback_query' => ['id' => 'cbq1', 'data' => $data],
            ])->assertOk();

        // Exactly one sendMessage carrying the "started" copy (separate from the
        // answerCallbackQuery toast, which hits a different endpoint).
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage')
            && str_contains((string) $req['text'], 'mulai memproses'));
    }
}
