<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class CreatorBrandSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'creator_brand_logo',    'value' => null,               'type' => 'image'],
            ['key' => 'creator_brand_tagline', 'value' => 'alisadikinma.com', 'type' => 'text'],
            ['key' => 'creator_brand_slug',    'value' => 'alisadikinma',     'type' => 'text'],
            ['key' => 'watermark_opacity',     'value' => '0.20',             'type' => 'text'],
            ['key' => 'watermark_enabled',     'value' => 'false',            'type' => 'text'],
        ];

        // firstOrCreate — NOT updateOrCreate — so running this seeder on every
        // deploy (see scripts/deploy.sh) only inserts missing rows and never
        // clobbers values the user has saved through the admin UI. Using
        // updateOrCreate here caused watermark_enabled, tagline, slug, and
        // opacity to reset to defaults on every production deploy.
        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'creator_brand'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ Creator brand settings seeded successfully!');
        }
    }
}
