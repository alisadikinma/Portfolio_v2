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
            ['key' => 'watermark_opacity',     'value' => '0.30',             'type' => 'text'],
            ['key' => 'watermark_enabled',     'value' => 'false',            'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key'], 'group' => 'creator_brand'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ Creator brand settings seeded successfully!');
        }
    }
}
