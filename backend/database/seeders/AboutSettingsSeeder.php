<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class AboutSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $aboutSettings = [
            [
                'key' => 'name',
                'group' => 'about',
                'value' => 'Ali Sadikin',
                'type' => 'text'
            ],
            [
                'key' => 'title',
                'group' => 'about',
                'value' => 'Full-Stack Developer & Digital Solutions Architect',
                'type' => 'text'
            ],
            [
                'key' => 'bio',
                'group' => 'about',
                'value' => '<p>I\'m a passionate full-stack developer with over 16 years of experience building innovative web applications and digital solutions. I specialize in modern JavaScript frameworks like Vue.js and React, backend development with Laravel and Node.js, and creating seamless user experiences.</p><p>My journey in software development has been driven by a constant desire to solve complex problems with elegant solutions. I believe in writing clean, maintainable code and staying at the forefront of technological advancements.</p>',
                'type' => 'textarea'
            ],
            [
                'key' => 'profile_photo',
                'group' => 'about',
                'value' => null,
                'type' => 'image'
            ],
            [
                'key' => 'skills',
                'group' => 'about',
                'value' => json_encode([
                    'Vue.js', 'React', 'Laravel', 'PHP', 'Node.js', 
                    'TypeScript', 'MySQL', 'MongoDB', 'Tailwind CSS',
                    'Docker', 'AWS', 'Git', 'REST API', 'GraphQL'
                ]),
                'type' => 'json'
            ],
            [
                'key' => 'experience',
                'group' => 'about',
                'value' => json_encode([
                    [
                        'position' => 'Senior Full-Stack Developer',
                        'company' => 'Tech Innovations Inc.',
                        'period' => '2020 - Present',
                        'description' => 'Leading development of enterprise web applications using Vue.js and Laravel. Mentoring junior developers and architecting scalable solutions.'
                    ],
                    [
                        'position' => 'Full-Stack Developer',
                        'company' => 'Digital Solutions Co.',
                        'period' => '2017 - 2020',
                        'description' => 'Developed and maintained multiple client projects using modern web technologies. Implemented CI/CD pipelines and improved deployment processes.'
                    ],
                    [
                        'position' => 'Frontend Developer',
                        'company' => 'Creative Web Agency',
                        'period' => '2015 - 2017',
                        'description' => 'Created responsive and interactive user interfaces using Vue.js and React. Collaborated with designers to implement pixel-perfect designs.'
                    ]
                ]),
                'type' => 'json'
            ],
            [
                'key' => 'education',
                'group' => 'about',
                'value' => json_encode([
                    [
                        'degree' => 'Bachelor of Computer Science',
                        'institution' => 'University of Technology',
                        'period' => '2011 - 2015',
                        'description' => 'Specialized in Software Engineering and Web Technologies'
                    ],
                    [
                        'degree' => 'Certified AWS Solutions Architect',
                        'institution' => 'Amazon Web Services',
                        'period' => '2021',
                        'description' => 'Professional certification for cloud architecture and deployment'
                    ]
                ]),
                'type' => 'json'
            ],
            [
                'key' => 'social_links',
                'group' => 'about',
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
                    ]
                ]),
                'type' => 'json'
            ]
        ];

        foreach ($aboutSettings as $setting) {
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

        $this->command->info('✅ About settings seeded successfully!');
    }
}
