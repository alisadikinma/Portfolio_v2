<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed enabled Telegram config directly (avoids relying on the seeder order).
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => '123:ABC', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '42', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_notify_manifest_needed', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_notify_generation_failed', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_notify_publish_success', 'value' => 'false', 'type' => 'text']);
    }

    private function makeIdea(array $manifest = []): ContentIdea
    {
        return ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Anthropic CEO Visits the White House',
            'status' => 'draft',
            'priority' => 'medium',
            'pending_manifest' => $manifest ?: [
                'entity' => [
                    [
                        'entity_name' => 'Dario Amodei',
                        'entity_type' => 'person',
                        'status' => 'missing',
                        'reason' => 'License CC-BY-SA not allowed',
                        'required' => true,
                    ],
                ],
            ],
        ]);
    }

    /** @test */
    public function send_manifest_alert_posts_to_telegram_api(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $idea = $this->makeIdea();

        /** @var TelegramNotificationService $svc */
        $svc = app(TelegramNotificationService::class);
        $result = $svc->sendManifestAlert($idea);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $url = $request->url();
            $payload = $request->data();
            $text = $payload['text'] ?? '';
            $chatId = (string) ($payload['chat_id'] ?? '');

            return str_contains($url, 'api.telegram.org/bot123:ABC/sendMessage')
                && $chatId === '42'
                && str_contains($text, 'Dario Amodei')
                && str_contains($text, 'Manual upload needed');
        });
    }

    /** @test */
    public function does_not_send_when_telegram_disabled(): void
    {
        Setting::where('group', 'telegram')->where('key', 'telegram_enabled')->update(['value' => 'false']);

        Http::fake();

        $idea = $this->makeIdea();

        /** @var TelegramNotificationService $svc */
        $svc = app(TelegramNotificationService::class);
        $result = $svc->sendManifestAlert($idea);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    /** @test */
    public function does_not_send_when_notification_type_disabled(): void
    {
        Setting::where('group', 'telegram')
            ->where('key', 'telegram_notify_manifest_needed')
            ->update(['value' => 'false']);

        Http::fake();

        $idea = $this->makeIdea();

        $result = app(TelegramNotificationService::class)->sendManifestAlert($idea);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    /** @test */
    public function does_not_send_when_bot_token_missing(): void
    {
        Setting::where('group', 'telegram')
            ->where('key', 'telegram_bot_token')
            ->update(['value' => null]);

        Http::fake();

        $idea = $this->makeIdea();

        $result = app(TelegramNotificationService::class)->sendManifestAlert($idea);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    /** @test */
    public function send_generation_failed_respects_its_own_toggle(): void
    {
        Setting::where('group', 'telegram')
            ->where('key', 'telegram_notify_generation_failed')
            ->update(['value' => 'false']);

        Http::fake();

        $idea = $this->makeIdea();

        $result = app(TelegramNotificationService::class)->sendGenerationFailed($idea);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    /** @test */
    public function send_publish_success_respects_its_own_toggle(): void
    {
        // publish_success toggle is 'false' by default — should NOT send
        Http::fake();

        $idea = $this->makeIdea();

        $result = app(TelegramNotificationService::class)->sendPublishSuccess($idea);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    /** @test */
    public function returns_false_on_telegram_api_error(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 401], 401),
        ]);

        $idea = $this->makeIdea();
        $result = app(TelegramNotificationService::class)->sendManifestAlert($idea);

        $this->assertFalse($result);
    }
}
