<?php

namespace Tests\Feature;

use App\Models\LinkedInPost;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Database\Seeders\LinkedInSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * The schedule master toggle + the schedule_prompt_sent_at column + cast.
 */
class LinkedInScheduleSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_adds_telegram_schedule_master_toggle_default_off(): void
    {
        $this->seed(LinkedInSettingsSeeder::class);

        $this->assertDatabaseHas('settings', [
            'group' => 'linkedin',
            'key' => 'linkedin_telegram_schedule_enabled',
            'value' => 'false',
        ]);
    }

    public function test_seeder_is_idempotent_on_the_new_toggle(): void
    {
        $this->seed(LinkedInSettingsSeeder::class);
        $this->seed(LinkedInSettingsSeeder::class);

        $this->assertSame(
            1,
            Setting::where('group', 'linkedin')
                ->where('key', 'linkedin_telegram_schedule_enabled')
                ->count()
        );
    }

    public function test_schedule_prompt_sent_at_casts_to_carbon(): void
    {
        $draft = new LinkedInPost(['schedule_prompt_sent_at' => '2026-06-12 10:00:00']);

        $this->assertInstanceOf(CarbonInterface::class, $draft->schedule_prompt_sent_at);
    }
}
