<?php

namespace Tests\Feature;

use App\Jobs\CaptureInstagramPost;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase A — inbound Telegram message branch: an Instagram URL from the allowed
 * chat creates a RepurposeJob (status=received, mode=null) and replies with two
 * signed mode buttons. Capture is NOT dispatched until a button is tapped.
 */
class TelegramRepurposeMessageTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['group' => 'telegram', 'key' => 'telegram_webhook_secret', 'value' => $this->secret, 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_repurpose_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function postWebhook(array $payload, bool $withSecret = true)
    {
        $headers = $withSecret ? ['X-Telegram-Bot-Api-Secret-Token' => $this->secret] : [];

        return $this->withHeaders($headers)
            ->postJson('/api/automation/telegram/webhook', $payload);
    }

    public function test_ig_url_from_allowed_chat_creates_job_and_sends_buttons(): void
    {
        Bus::fake();

        $resp = $this->postWebhook([
            'message' => ['chat' => ['id' => 99], 'text' => 'https://instagram.com/p/ABC123/ fokus bisnis'],
        ]);

        $resp->assertOk();
        $this->assertDatabaseHas('repurpose_jobs', [
            'source_url' => 'https://instagram.com/p/ABC123/',
            'status' => 'received',
            'mode' => null,
            'angle' => 'fokus bisnis',
            'chat_id' => '99',
        ]);

        Bus::assertNotDispatched(CaptureInstagramPost::class);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && str_contains(json_encode($request->data()), 'repurpose');
        });
    }

    public function test_reel_url_also_accepted(): void
    {
        $this->postWebhook([
            'message' => ['chat' => ['id' => 99], 'text' => 'https://www.instagram.com/reel/XYZ987/'],
        ])->assertOk();

        $this->assertDatabaseHas('repurpose_jobs', [
            'source_url' => 'https://www.instagram.com/reel/XYZ987/',
            'mode' => null,
        ]);
    }

    public function test_wrong_chat_ignored(): void
    {
        $this->postWebhook([
            'message' => ['chat' => ['id' => 12345], 'text' => 'https://instagram.com/p/ABC/'],
        ])->assertOk();

        $this->assertDatabaseCount('repurpose_jobs', 0);
    }

    public function test_toggle_off_ignored(): void
    {
        Setting::where('key', 'telegram_repurpose_enabled')->update(['value' => 'false']);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 99], 'text' => 'https://instagram.com/p/ABC/'],
        ])->assertOk();

        $this->assertDatabaseCount('repurpose_jobs', 0);
    }

    public function test_non_instagram_url_ignored(): void
    {
        $this->postWebhook([
            'message' => ['chat' => ['id' => 99], 'text' => 'https://example.com/p/ABC/ cek ini'],
        ])->assertOk();

        $this->assertDatabaseCount('repurpose_jobs', 0);
    }

    public function test_missing_secret_is_forbidden_and_creates_nothing(): void
    {
        $resp = $this->postWebhook([
            'message' => ['chat' => ['id' => 99], 'text' => 'https://instagram.com/p/ABC/'],
        ], withSecret: false);

        $resp->assertStatus(403);
        $this->assertDatabaseCount('repurpose_jobs', 0);
    }
}
