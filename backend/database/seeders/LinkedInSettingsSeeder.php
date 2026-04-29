<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the `linkedin` settings group with operator-facing publishing flags.
 *
 * Uses firstOrCreate (not updateOrCreate) so running on deploy never
 * clobbers admin-edited values. Same pattern as TelegramSettingsSeeder
 * and CreatorBrandSettingsSeeder.
 */
class LinkedInSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'linkedin_auto_publish',                 'value' => 'false', 'type' => 'text'],
            ['key' => 'linkedin_depth_score_threshold',        'value' => '80',    'type' => 'text'],
            ['key' => 'linkedin_cancel_window_minutes',        'value' => '15',    'type' => 'text'],
            ['key' => 'linkedin_first_comment_enabled',        'value' => 'true',  'type' => 'text'],
            ['key' => 'linkedin_first_comment_delay_seconds',  'value' => '30',    'type' => 'text'],
            ['key' => 'linkedin_last_test_connection_at',      'value' => null,    'type' => 'text'],
            ['key' => 'linkedin_last_test_connection_result',  'value' => null,    'type' => 'text'],
            // Virality gate (April 29, 2026):
            // - Scan only ingests blog posts whose ContentIdea.virality_score
            //   is >= linkedin_virality_min_score. Manual posts (no idea
            //   linkage) are skipped — operators can hand-create LinkedIn
            //   drafts for those if needed.
            // - Daily purge command soft-deletes non-terminal drafts whose
            //   source idea slipped below linkedin_virality_purge_below
            //   (e.g., re-scored after social signals decayed).
            // Both thresholds are operator-tunable from AboutSettings.
            ['key' => 'linkedin_virality_min_score',           'value' => '60',    'type' => 'text'],
            ['key' => 'linkedin_virality_purge_below',         'value' => '50',    'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'linkedin'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        // Per Decision #9: LinkedIn-related telegram_notify_* flags live
        // in the EXISTING `telegram` group (owned by TelegramNotificationService).
        // Add the 3 new LinkedIn notification toggles here so they share
        // the group + are editable from the existing Telegram card.
        $telegramExtensions = [
            ['key' => 'telegram_notify_linkedin_preview',       'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_linkedin_depth_failed',  'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_linkedin_published',     'value' => 'true',  'type' => 'text'],
        ];

        foreach ($telegramExtensions as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'telegram'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ LinkedIn settings seeded (9 linkedin + 3 telegram_notify_linkedin_* keys)');
        }
    }
}
