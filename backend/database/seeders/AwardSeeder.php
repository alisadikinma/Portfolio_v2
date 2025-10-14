<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AwardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $awards = [
            [
                'title' => 'Best Web Developer Award 2024',
                'description' => 'Recognized for outstanding achievement in modern web development, creating innovative and user-centric solutions using cutting-edge technologies.',
                'organization' => 'International Web Development Association',
                'credential_id' => 'IWDA-2024-001',
                'credential_url' => 'https://example.com/credentials/iwda-2024-001',
                'image' => 'awards/web-developer-2024.jpg',
                'received_at' => '2024-06-15',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Excellence in Full-Stack Development',
                'description' => 'Awarded for demonstrating exceptional skills in both frontend and backend technologies, with a focus on scalable architecture and clean code practices.',
                'organization' => 'Tech Excellence Foundation',
                'credential_id' => 'TEF-FULLSTACK-2023',
                'credential_url' => 'https://example.com/credentials/tef-fullstack-2023',
                'image' => 'awards/fullstack-excellence.jpg',
                'received_at' => '2023-11-20',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Innovation in Laravel Development',
                'description' => 'Honored for creating robust and maintainable Laravel applications with advanced features and best practices implementation.',
                'organization' => 'Laravel Community Excellence Awards',
                'credential_id' => 'LCEA-2023-042',
                'credential_url' => 'https://example.com/credentials/lcea-2023-042',
                'image' => 'awards/laravel-innovation.jpg',
                'received_at' => '2023-09-10',
                'sort_order' =>3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Outstanding Vue.js Implementation',
                'description' => 'Recognized for building performant and elegant single-page applications using Vue 3 with modern composition API patterns.',
                'organization' => 'Frontend Developers Guild',
                'credential_id' => 'FDG-VUE-2023',
                'credential_url' => 'https://example.com/credentials/fdg-vue-2023',
                'image' => 'awards/vue-outstanding.jpg',
                'received_at' => '2023-07-05',
                'sort_order' =>4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Best UI/UX Design Implementation',
                'description' => 'Awarded for exceptional attention to user experience design, accessibility standards, and creating intuitive interfaces that delight users.',
                'organization' => 'Design & Development Summit',
                'credential_id' => 'DDS-UIUX-2022',
                'credential_url' => 'https://example.com/credentials/dds-uiux-2022',
                'image' => 'awards/uiux-best.jpg',
                'received_at' => '2022-12-18',
                'sort_order' =>5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Database Architecture Excellence',
                'description' => 'Recognized for designing and implementing efficient database schemas with optimal query performance and data integrity.',
                'organization' => 'Database Professionals Association',
                'credential_id' => 'DPA-ARCH-2022',
                'credential_url' => 'https://example.com/credentials/dpa-arch-2022',
                'image' => 'awards/database-excellence.jpg',
                'received_at' => '2022-08-22',
                'sort_order' =>6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        \DB::table('awards')->insert($awards);
        $this->command->info('✅ Successfully seeded ' . count($awards) . ' awards.');
    }
}
