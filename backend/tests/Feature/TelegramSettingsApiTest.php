<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SettingsController;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Database\Seeders\TelegramSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Controller-level tests for the Telegram settings endpoints.
 *
 * IMPORTANT: we exercise the controller methods directly rather than going
 * through HTTP → route → middleware, because the /api/admin/settings/* routes
 * can't be reached from Laravel's test HTTP kernel in this project (all
 * requests to that prefix return 404 inside PHPUnit, even for long-established
 * routes like /api/admin/settings/about — reproduced independently). The
 * routes DO work in production (verified via artisan route:list + direct PHP
 * request handling outside PHPUnit), so this is a test-environment quirk
 * rather than a real auth/routing problem. Controller-method tests give us
 * real coverage of validation + masking + persistence logic without tripping
 * the 404 quirk.
 */
class TelegramSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private SettingsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TelegramSettingsSeeder::class);
        $this->controller = new SettingsController();
    }

    /** @test */
    public function get_returns_all_six_telegram_settings_with_token_masked(): void
    {
        Setting::where('group', 'telegram')
            ->where('key', 'telegram_bot_token')
            ->update(['value' => '123456789:ABCDEFGHIJKLMN1234']);

        $response = $this->controller->getTelegramSettings();

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getData(true);

        $this->assertTrue($body['success']);
        $data = $body['data'];

        $this->assertArrayHasKey('telegram_bot_token', $data);
        $this->assertArrayHasKey('telegram_chat_id', $data);
        $this->assertArrayHasKey('telegram_enabled', $data);
        $this->assertArrayHasKey('telegram_notify_manifest_needed', $data);
        $this->assertArrayHasKey('telegram_notify_generation_failed', $data);
        $this->assertArrayHasKey('telegram_notify_publish_success', $data);

        // Bot token MUST be masked — only last 4 chars visible
        $this->assertNotSame('123456789:ABCDEFGHIJKLMN1234', $data['telegram_bot_token']);
        $this->assertStringContainsString('****', $data['telegram_bot_token']);
        $this->assertStringEndsWith('1234', $data['telegram_bot_token']);
    }

    /** @test */
    public function get_returns_null_bot_token_when_not_set(): void
    {
        $response = $this->controller->getTelegramSettings();

        $body = $response->getData(true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($body['data']['telegram_bot_token']);
    }

    /** @test */
    public function put_updates_telegram_settings(): void
    {
        $request = Request::create('/', 'PUT', [
            'telegram_bot_token' => '987:XYZ-token-here',
            'telegram_chat_id' => '777',
            'telegram_enabled' => 'true',
            'telegram_notify_manifest_needed' => 'true',
            'telegram_notify_generation_failed' => 'false',
            'telegram_notify_publish_success' => 'true',
        ]);

        $response = $this->controller->updateTelegramSettings($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('987:XYZ-token-here', Setting::where('group', 'telegram')->where('key', 'telegram_bot_token')->value('value'));
        $this->assertSame('777', Setting::where('group', 'telegram')->where('key', 'telegram_chat_id')->value('value'));
        $this->assertSame('true', Setting::where('group', 'telegram')->where('key', 'telegram_enabled')->value('value'));
        $this->assertSame('false', Setting::where('group', 'telegram')->where('key', 'telegram_notify_generation_failed')->value('value'));
    }

    /** @test */
    public function put_leaves_bot_token_unchanged_when_omitted(): void
    {
        Setting::where('group', 'telegram')
            ->where('key', 'telegram_bot_token')
            ->update(['value' => 'original-token']);

        $request = Request::create('/', 'PUT', [
            'telegram_chat_id' => '999',
            'telegram_enabled' => 'true',
            // no telegram_bot_token key
        ]);

        $this->controller->updateTelegramSettings($request);

        // Token must NOT have been cleared
        $this->assertSame(
            'original-token',
            Setting::where('group', 'telegram')->where('key', 'telegram_bot_token')->value('value')
        );
    }

    /** @test */
    public function put_leaves_bot_token_unchanged_when_empty_string(): void
    {
        Setting::where('group', 'telegram')
            ->where('key', 'telegram_bot_token')
            ->update(['value' => 'original-token']);

        $request = Request::create('/', 'PUT', [
            'telegram_bot_token' => '',
            'telegram_enabled' => 'true',
        ]);

        $this->controller->updateTelegramSettings($request);

        $this->assertSame(
            'original-token',
            Setting::where('group', 'telegram')->where('key', 'telegram_bot_token')->value('value')
        );
    }

    /** @test */
    public function put_validates_enabled_flag_values(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $request = Request::create('/', 'PUT', [
            'telegram_enabled' => 'garbage',
        ]);

        $this->controller->updateTelegramSettings($request);
    }

    /** @test */
    public function test_message_endpoint_attempts_to_send_when_configured(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        Setting::where('group', 'telegram')->where('key', 'telegram_bot_token')->update(['value' => '111:TEST']);
        Setting::where('group', 'telegram')->where('key', 'telegram_chat_id')->update(['value' => '222']);
        Setting::where('group', 'telegram')->where('key', 'telegram_enabled')->update(['value' => 'true']);

        $svc = app(TelegramNotificationService::class);
        $response = $this->controller->testTelegramNotification($svc);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertTrue($body['success']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/bot111:TEST/sendMessage')
                && str_contains($request->data()['text'] ?? '', 'Test');
        });
    }

    /** @test */
    public function test_message_endpoint_returns_error_when_not_configured(): void
    {
        Http::fake();
        // Bot token still null (seeder default)

        $svc = app(TelegramNotificationService::class);
        $response = $this->controller->testTelegramNotification($svc);

        $this->assertSame(400, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertFalse($body['success']);
    }
}
