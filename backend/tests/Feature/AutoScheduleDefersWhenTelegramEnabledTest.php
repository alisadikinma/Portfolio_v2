<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E/G — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * When the Telegram human-in-the-loop scheduling flow owns scheduling, the
 * auto-schedule cron must defer (no-op) so the two never race.
 */
class AutoScheduleDefersWhenTelegramEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_schedule_defers_when_telegram_schedule_enabled(): void
    {
        Setting::updateOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_auto_approve_enabled'],
            ['value' => 'true', 'type' => 'text']
        );
        Setting::updateOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_telegram_schedule_enabled'],
            ['value' => 'true', 'type' => 'text']
        );

        $this->artisan('linkedin:auto-schedule')
            ->expectsOutputToContain('deferred')
            ->assertExitCode(0);
    }

    public function test_auto_schedule_runs_normally_when_telegram_schedule_disabled(): void
    {
        Setting::updateOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_auto_approve_enabled'],
            ['value' => 'false', 'type' => 'text']
        );
        Setting::updateOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_telegram_schedule_enabled'],
            ['value' => 'false', 'type' => 'text']
        );

        // auto_approve off → existing kill-switch path (not the new defer path).
        $this->artisan('linkedin:auto-schedule')
            ->expectsOutputToContain('kill switch off')
            ->assertExitCode(0);
    }
}
