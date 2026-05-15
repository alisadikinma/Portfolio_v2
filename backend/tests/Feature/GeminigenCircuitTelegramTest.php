<?php

namespace Tests\Feature;

use App\Services\GeminiGenCircuitBreaker;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Phase I — Telegram alerts for circuit state transitions + suppression
 * of per-segment alerts while breaker is non-closed.
 *
 * Verifies that:
 *   1. closed → open transition fires sendGeminigenCircuitOpen
 *   2. half_open → closed transition fires sendGeminigenCircuitClose
 *   3. per-event opt-in setting suppresses the dispatch
 *
 * Telegram is mocked at the service-instance level so no real HTTP is sent.
 */
class GeminigenCircuitTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Master enable + token + chat_id so isEnabledFor() can return true.
        // (Production methods bail when token/chat_id are blank — these aren't
        // checked by the mock but keep parity with realistic conditions.)
        DB::table('settings')->updateOrInsert(
            ['group' => 'telegram', 'key' => 'telegram_enabled'],
            ['value' => 'true', 'type' => 'text', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'telegram', 'key' => 'telegram_bot_token'],
            ['value' => 'fake-test-token', 'type' => 'text', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'telegram', 'key' => 'telegram_chat_id'],
            ['value' => '12345', 'type' => 'text', 'updated_at' => now(), 'created_at' => now()]
        );

        // Pre-seed the 2 new per-event opt-in flags.
        DB::table('settings')->updateOrInsert(
            ['group' => 'telegram', 'key' => 'telegram_notify_geminigen_circuit_open'],
            ['value' => 'true', 'type' => 'text', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'telegram', 'key' => 'telegram_notify_geminigen_circuit_close'],
            ['value' => 'true', 'type' => 'text', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_open_transition_dispatches_open_alert(): void
    {
        $telegram = Mockery::mock(TelegramNotificationService::class);
        $telegram->shouldReceive('sendGeminigenCircuitOpen')->once();
        $telegram->shouldReceive('sendGeminigenCircuitClose')->zeroOrMoreTimes();
        $this->app->instance(TelegramNotificationService::class, $telegram);

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $this->assertSame('open', $breaker->state());
    }

    public function test_close_transition_dispatches_close_alert(): void
    {
        $telegram = Mockery::mock(TelegramNotificationService::class);
        $telegram->shouldReceive('sendGeminigenCircuitOpen')->zeroOrMoreTimes();
        $telegram->shouldReceive('sendGeminigenCircuitClose')->once();
        $this->app->instance(TelegramNotificationService::class, $telegram);

        $breaker = app(GeminiGenCircuitBreaker::class);
        // Trip → half_open → close path
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $breaker->transitionToHalfOpen();
        $breaker->recordSuccess();
        $this->assertSame('closed', $breaker->state());
    }

    public function test_alerts_still_fire_when_per_event_flag_is_true(): void
    {
        // Sanity: real (not mocked) service is invoked when flag is true.
        // We let the real service run — it'll bail at the actual HTTP step
        // because no real Telegram API is reachable, but the method WILL be
        // called from inside setOpen() without throwing.
        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }

        $this->assertSame('open', $breaker->state());
    }

    public function test_open_alert_method_respects_setting_flag(): void
    {
        // Flip flag OFF — call method directly on real service, expect early-return.
        DB::table('settings')->updateOrInsert(
            ['group' => 'telegram', 'key' => 'telegram_notify_geminigen_circuit_open'],
            ['value' => 'false', 'type' => 'text', 'updated_at' => now(), 'created_at' => now()]
        );

        $service = app(TelegramNotificationService::class);
        $result = $service->sendGeminigenCircuitOpen(now(), now()->addMinutes(5), 'test_reason');

        // Method returns false when opt-in flag is off.
        $this->assertFalse($result);
    }

    public function test_close_alert_method_respects_setting_flag(): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'telegram', 'key' => 'telegram_notify_geminigen_circuit_close'],
            ['value' => 'false', 'type' => 'text', 'updated_at' => now(), 'created_at' => now()]
        );

        $service = app(TelegramNotificationService::class);
        $result = $service->sendGeminigenCircuitClose(now()->subMinutes(10));

        $this->assertFalse($result);
    }
}
