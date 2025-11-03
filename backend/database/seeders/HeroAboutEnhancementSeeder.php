<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class HeroAboutEnhancementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder adds new settings for enhanced hero section and about page
     * to support the new positioning as "AI Automation Architect & Senior Tech Consultant"
     */
    public function run(): void
    {
        // Hero Section Settings (group: 'about')
        $heroSettings = [
            [
                'key' => 'hero_tagline',
                'value' => 'AI Automation Architect & Senior Tech Consultant',
                'group' => 'about',
                'type' => 'text'
            ],
            [
                'key' => 'availability_note',
                'value' => 'Available for consulting and freelance projects',
                'group' => 'about',
                'type' => 'text'
            ],
            [
                'key' => 'trust_strip',
                'value' => json_encode([
                    'years_experience' => '17+',
                    'projects_delivered' => '56+',
                    'clients_served' => '25+',
                    'success_rate' => '95%'
                ]),
                'group' => 'about',
                'type' => 'json'
            ],
        ];

        // About Page Settings (group: 'about')
        $aboutSettings = [
            [
                'key' => 'mission',
                'value' => 'Empowering businesses through intelligent automation and cutting-edge AI solutions that drive measurable results',
                'group' => 'about',
                'type' => 'textarea'
            ],
            [
                'key' => 'what_i_do',
                'value' => json_encode([
                    [
                        'title' => 'AI Automation Architecture',
                        'description' => 'Design and implement intelligent automation solutions that streamline business processes, reduce operational costs, and improve efficiency using cutting-edge AI technologies.',
                        'icon' => 'robot'
                    ],
                    [
                        'title' => 'Full-Stack Development',
                        'description' => 'Build scalable, high-performance web applications using modern frameworks like Laravel, Vue.js, and React. From concept to deployment, delivering robust solutions.',
                        'icon' => 'code'
                    ],
                    [
                        'title' => 'Technical Consulting',
                        'description' => 'Provide strategic guidance on digital transformation, system architecture, and technology selection to help businesses achieve their goals effectively.',
                        'icon' => 'consulting'
                    ]
                ]),
                'group' => 'about',
                'type' => 'json'
            ],
            [
                'key' => 'approach',
                'value' => 'I believe in combining technical excellence with business impact. My approach starts with understanding your unique challenges, then crafting solutions that not only work technically but also deliver measurable ROI. With 17+ years of experience, I\'ve learned that the best solutions are those that balance innovation with practicality, ensuring sustainable long-term success.',
                'group' => 'about',
                'type' => 'textarea'
            ],
            [
                'key' => 'collaboration_modes',
                'value' => json_encode([
                    [
                        'mode' => 'Project-Based',
                        'description' => 'Fixed-scope projects with clear deliverables, timelines, and milestones. Perfect for well-defined initiatives with specific outcomes.',
                        'icon' => 'project'
                    ],
                    [
                        'mode' => 'Retainer',
                        'description' => 'Ongoing support and development with dedicated hours per month. Ideal for continuous improvement and long-term partnerships.',
                        'icon' => 'calendar'
                    ],
                    [
                        'mode' => 'Consulting',
                        'description' => 'Strategic advisory and technical guidance on architecture, technology choices, and digital transformation initiatives.',
                        'icon' => 'lightbulb'
                    ]
                ]),
                'group' => 'about',
                'type' => 'json'
            ],
        ];

        // Merge all settings
        $allSettings = array_merge($heroSettings, $aboutSettings);

        // Insert or update settings (idempotent operation)
        foreach ($allSettings as $setting) {
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

        $this->command->info('Hero and About enhancement settings seeded successfully!');
        $this->command->info('Total settings created/updated: ' . count($allSettings));
        $this->command->info('- Hero settings: ' . count($heroSettings));
        $this->command->info('- About settings: ' . count($aboutSettings));
    }
}
