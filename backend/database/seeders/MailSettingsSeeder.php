<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class MailSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Defaults: Hostinger SMTP config for aiagent@alisadikinma.com.
        // Password intentionally NULL — operator sets via /admin/about
        // "Email — SMTP Settings" card. Stored encrypted via Crypt at write
        // time (see SettingsController::updateMailSettings).
        $settings = [
            ['key' => 'mail_mailer',        'value' => 'smtp',                       'type' => 'text'],
            ['key' => 'mail_host',          'value' => 'smtp.hostinger.com',         'type' => 'text'],
            ['key' => 'mail_port',          'value' => '465',                        'type' => 'text'],
            ['key' => 'mail_username',      'value' => 'aiagent@alisadikinma.com',   'type' => 'text'],
            ['key' => 'mail_password',      'value' => null,                         'type' => 'text'],
            ['key' => 'mail_encryption',    'value' => 'ssl',                        'type' => 'text'],
            ['key' => 'mail_from_address',  'value' => 'aiagent@alisadikinma.com',   'type' => 'text'],
            ['key' => 'mail_from_name',     'value' => 'Ali Sadikin',                'type' => 'text'],
        ];

        // firstOrCreate (NOT updateOrCreate) so re-running on every deploy
        // never clobbers operator-edited values via UI. Same pattern as
        // CreatorBrandSettingsSeeder + TelegramSettingsSeeder.
        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'mail'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ Mail SMTP settings seeded successfully!');
        }
    }
}
