<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $siteSettings = [
            [
                'key' => 'site_name',
                'group' => 'site',
                'value' => 'Portfolio V2',
                'type' => 'text'
            ],
            [
                'key' => 'site_description',
                'group' => 'site',
                'value' => 'A modern portfolio showcasing creative projects, insightful blog posts, and professional work.',
                'type' => 'textarea'
            ],
            [
                'key' => 'site_logo',
                'group' => 'site',
                'value' => null,
                'type' => 'image'
            ],
            [
                'key' => 'contact_email',
                'group' => 'site',
                'value' => 'contact@example.com',
                'type' => 'text'
            ],
            [
                'key' => 'contact_phone',
                'group' => 'site',
                'value' => '+1 (555) 123-4567',
                'type' => 'text'
            ],
            [
                'key' => 'social_media',
                'group' => 'site',
                'value' => json_encode([
                    [
                        'platform' => 'github',
                        'url' => 'https://github.com/alisadikinma'
                    ],
                    [
                        'platform' => 'linkedin',
                        'url' => 'https://linkedin.com/in/alisadikin'
                    ],
                    [
                        'platform' => 'twitter',
                        'url' => 'https://twitter.com/alisadikin'
                    ],
                    [
                        'platform' => 'instagram',
                        'url' => 'https://instagram.com/alisadikin'
                    ]
                ]),
                'type' => 'json'
            ],
            [
                'key' => 'meta_tags',
                'group' => 'site',
                'value' => json_encode([
                    [
                        'name' => 'keywords',
                        'content' => 'portfolio, web development, full-stack developer, Laravel, Vue.js'
                    ],
                    [
                        'name' => 'author',
                        'content' => 'Ali Sadikin'
                    ],
                    [
                        'name' => 'robots',
                        'content' => 'index, follow'
                    ]
                ]),
                'type' => 'json'
            ],
            [
                'key' => 'analytics_code',
                'group' => 'site',
                'value' => '',
                'type' => 'textarea'
            ]
        ];

        foreach ($siteSettings as $setting) {
            Setting::updateOrCreate(
                [
                    'key' => $setting['key'],
                    'group' => $setting['group']
                ],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type']
                ]
            );
        }

        $this->command->info('✅ Site settings seeded successfully!');
    }
}
