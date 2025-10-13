<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Profile Settings
            [
                'key' => 'profile.name',
                'value' => 'John Doe',
                'group' => 'profile',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'profile.email',
                'value' => 'john.doe@example.com',
                'group' => 'profile',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'profile.phone',
                'value' => '+1 (555) 123-4567',
                'group' => 'profile',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'profile.bio',
                'value' => '<p>Passionate <strong>Full-Stack Developer</strong> with 10+ years of experience building scalable web applications. Specialized in Laravel, React, and cloud technologies.</p>',
                'group' => 'profile',
                'type' => 'textarea',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'profile.avatar',
                'value' => 'images/profile/avatar.jpg',
                'group' => 'profile',
                'type' => 'image',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'profile.social.github',
                'value' => 'https://github.com/johndoe',
                'group' => 'profile',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'profile.social.linkedin',
                'value' => 'https://linkedin.com/in/johndoe',
                'group' => 'profile',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'profile.social.twitter',
                'value' => 'https://twitter.com/johndoe',
                'group' => 'profile',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // About Settings
            [
                'key' => 'about.title',
                'value' => 'About Me',
                'group' => 'about',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'about.content',
                'value' => '<h2>Building Digital Experiences</h2><p>I specialize in creating <strong>innovative solutions</strong> that combine cutting-edge technology with user-centered design. My approach focuses on delivering high-quality, maintainable code while ensuring exceptional user experiences.</p><p>With expertise spanning across <em>backend development, frontend frameworks, and cloud infrastructure</em>, I help businesses transform their digital presence.</p>',
                'group' => 'about',
                'type' => 'textarea',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'about.skills',
                'value' => json_encode([
                    ['name' => 'Laravel', 'level' => 95],
                    ['name' => 'React', 'level' => 90],
                    ['name' => 'TypeScript', 'level' => 88],
                    ['name' => 'Node.js', 'level' => 85],
                    ['name' => 'AWS', 'level' => 82],
                    ['name' => 'Docker', 'level' => 80],
                    ['name' => 'MySQL/PostgreSQL', 'level' => 92],
                    ['name' => 'GraphQL', 'level' => 78],
                ]),
                'group' => 'about',
                'type' => 'json',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'about.experience',
                'value' => json_encode([
                    [
                        'title' => 'Senior Full-Stack Developer',
                        'company' => 'Tech Innovations Corp',
                        'period' => '2020 - Present',
                        'description' => 'Lead development of enterprise web applications using Laravel and React'
                    ],
                    [
                        'title' => 'Full-Stack Developer',
                        'company' => 'Digital Solutions Ltd',
                        'period' => '2017 - 2020',
                        'description' => 'Developed and maintained multiple client projects using modern web technologies'
                    ],
                    [
                        'title' => 'Junior Developer',
                        'company' => 'StartUp Ventures',
                        'period' => '2015 - 2017',
                        'description' => 'Contributed to development of web applications and learned industry best practices'
                    ],
                ]),
                'group' => 'about',
                'type' => 'json',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'about.years_experience',
                'value' => '10',
                'group' => 'about',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'about.projects_completed',
                'value' => '150',
                'group' => 'about',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Hero Settings
            [
                'key' => 'hero.heading',
                'value' => 'Hi, I\'m John Doe',
                'group' => 'hero',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'hero.subheading',
                'value' => 'Full-Stack Developer & Cloud Architect',
                'group' => 'hero',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'hero.description',
                'value' => '<p>I build <strong>scalable web applications</strong> and <em>cloud-native solutions</em> that drive business growth.</p>',
                'group' => 'hero',
                'type' => 'textarea',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'hero.cta_text',
                'value' => 'View My Work',
                'group' => 'hero',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'hero.cta_link',
                'value' => '/projects',
                'group' => 'hero',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'hero.secondary_cta_text',
                'value' => 'Contact Me',
                'group' => 'hero',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'hero.secondary_cta_link',
                'value' => '/contact',
                'group' => 'hero',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'hero.background_image',
                'value' => 'images/hero/background.jpg',
                'group' => 'hero',
                'type' => 'image',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }
}
