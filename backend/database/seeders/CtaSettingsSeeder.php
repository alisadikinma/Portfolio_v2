<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class CtaSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ctaSettings = [
            // Main Heading - HOOK! Shorter, more impactful
            [
                'key' => 'cta.heading',
                'value' => 'Seen Enough? Let\'s Automate Your Business.',
                'group' => 'cta',
                'type' => 'text',
            ],

            // Subtext - Concise value prop
            [
                'key' => 'cta.subtext',
                'value' => 'This AI-powered site? Built in 3 days. 300+ hours saved. Your business could be next.',
                'group' => 'cta',
                'type' => 'text',
            ],

            // CTA Question - Social proof angle
            [
                'key' => 'cta.question',
                'value' => 'Ready to see what AI can do for YOU?',
                'group' => 'cta',
                'type' => 'text',
            ],

            // Urgency text
            [
                'key' => 'cta.urgency',
                'value' => 'FREE 30-min consultation. Limited slots.',
                'group' => 'cta',
                'type' => 'text',
            ],

            // Primary button
            [
                'key' => 'cta.primary_button_text',
                'value' => 'Get Free Consultation',
                'group' => 'cta',
                'type' => 'text',
            ],

            // Secondary button
            [
                'key' => 'cta.secondary_button_text',
                'value' => 'Schedule a Call',
                'group' => 'cta',
                'type' => 'text',
            ],

            // Stats badges (JSON)
            [
                'key' => 'cta.stats',
                'value' => json_encode([
                    [
                        'icon' => '⚡',
                        'label' => '300+ hours saved',
                    ],
                    [
                        'icon' => '🤖',
                        'label' => '100% AI-built',
                    ],
                    [
                        'icon' => '💰',
                        'label' => '95% cost cut',
                    ],
                ]),
                'group' => 'cta',
                'type' => 'json',
            ],
        ];

        foreach ($ctaSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✅ CTA settings seeded successfully!');
    }
}
