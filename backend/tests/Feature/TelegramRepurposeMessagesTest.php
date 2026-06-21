<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase H — IG repurpose Telegram message payload shapes. Master-toggle gated;
 * each method POSTs to bot sendMessage with chat_id from settings.
 */
class TelegramRepurposeMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedTelegram(string $enabled = 'true'): void
    {
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => $enabled, 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_webhook_secret', 'value' => 'sek', 'type' => 'text']);
    }

    public function test_mode_prompt_sends_two_signed_buttons(): void
    {
        $this->seedTelegram();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $job = RepurposeJob::factory()->create(['source_url' => 'https://instagram.com/p/X/']);

        $ok = app(TelegramNotificationService::class)->sendRepurposeModePrompt($job);

        $this->assertTrue($ok);
        Http::assertSent(function ($req) use ($job) {
            $markup = json_decode($req['reply_markup'] ?? '{}', true);
            $buttons = $markup['inline_keyboard'][0] ?? [];
            return str_contains($req->url(), '/sendMessage')
                && count($buttons) === 2
                && str_starts_with($buttons[0]['callback_data'], "blog:repurpose:{$job->id}:")
                && str_starts_with($buttons[1]['callback_data'], "carousel:repurpose:{$job->id}:");
        });
    }

    public function test_failed_message_includes_reason(): void
    {
        $this->seedTelegram();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $job = RepurposeJob::factory()->create();

        app(TelegramNotificationService::class)->sendRepurposeFailed($job, 'capture_failed: login_wall');

        // Reason is Markdown-escaped so underscores can't unbalance the entity
        // parser (Telegram 400). The escaped substring confirms the reason text
        // is present AND safely encoded.
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage')
            && str_contains($req['text'], 'login\\_wall'));
    }

    public function test_drafted_message_carousel_links_draft_posts(): void
    {
        $this->seedTelegram();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $job = RepurposeJob::factory()->create(['mode' => 'carousel']);

        app(TelegramNotificationService::class)->sendRepurposeDrafted($job, 42, 3);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage')
            && str_contains($req['text'], '/admin/sosmed-drafts/42')
            && str_contains($req['text'], '3 klaim'));
    }

    public function test_drafted_message_blog_links_content_engine(): void
    {
        $this->seedTelegram();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $job = RepurposeJob::factory()->create(['mode' => 'blog']);

        app(TelegramNotificationService::class)->sendRepurposeDrafted($job, null, 0);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage')
            && str_contains($req['text'], '/admin/content-engine'));
    }

    public function test_master_toggle_off_suppresses_all(): void
    {
        $this->seedTelegram('false');
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $job = RepurposeJob::factory()->create(['mode' => 'blog']);

        $this->assertFalse(app(TelegramNotificationService::class)->sendRepurposeFailed($job, 'x'));
        $this->assertFalse(app(TelegramNotificationService::class)->sendRepurposeDrafted($job, null, 0));
        Http::assertNothingSent();
    }
}
