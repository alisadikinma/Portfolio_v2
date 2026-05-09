<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seed operator-facing Publer integration settings (May 8, 2026).
 *
 * 6 keys live in the `settings` table (group=publer). Operator manages all
 * via the "Publer Integration" card on /admin/about.
 *
 *   publer_api_key                 nullable, encrypted via Crypt::encryptString
 *                                  at write time (see SettingsController::
 *                                  updatePublerSettings); masked as ***SET***
 *                                  in API responses
 *   publer_enabled                 master kill switch ('true' / 'false')
 *   publer_facebook_account_id     selected from /accounts dropdown (UI-hidden May 10, 2026 — direct Graph API path planned)
 *   publer_instagram_account_id    same
 *   publer_tiktok_account_id       same
 *   publer_threads_account_id      same (added May 10, 2026)
 *   publer_last_account_sync_at    timestamp of last successful sync-accounts call
 *
 * Defaults intentionally null — operator must enter API key + click Test
 * Connection + Refresh Accounts before publishing flow can fire.
 *
 * firstOrCreate (NOT updateOrCreate) so re-running on every deploy never
 * clobbers operator-edited values. Same pattern as MailSettingsSeeder +
 * TelegramSettingsSeeder.
 */
class PublerSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'publer_api_key',                 'value' => null,    'type' => 'text'],
            ['key' => 'publer_enabled',                 'value' => 'false', 'type' => 'text'],
            ['key' => 'publer_facebook_account_id',     'value' => null,    'type' => 'text'],
            ['key' => 'publer_instagram_account_id',    'value' => null,    'type' => 'text'],
            ['key' => 'publer_tiktok_account_id',       'value' => null,    'type' => 'text'],
            ['key' => 'publer_threads_account_id',      'value' => null,    'type' => 'text'],
            ['key' => 'publer_last_account_sync_at',    'value' => null,    'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'publer'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ Publer integration settings seeded.');
        }
    }
}
