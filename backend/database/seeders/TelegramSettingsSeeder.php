<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class TelegramSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'telegram_bot_token',                 'value' => null,    'type' => 'text'],
            ['key' => 'telegram_chat_id',                   'value' => null,    'type' => 'text'],
            ['key' => 'telegram_enabled',                   'value' => 'false', 'type' => 'text'],
            ['key' => 'telegram_notify_manifest_needed',    'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_generation_failed',  'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_publish_success',    'value' => 'false', 'type' => 'text'],
        ];

        // firstOrCreate — NOT updateOrCreate — so running this seeder on every
        // deploy (see scripts/deploy.sh) only inserts missing rows and never
        // clobbers values the admin has saved through the UI. Mirrors the
        // CreatorBrandSettingsSeeder pattern — see that seeder's comment for
        // the regression that motivated firstOrCreate over updateOrCreate.
        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'telegram'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ Telegram notification settings seeded successfully!');
        }
    }
}
