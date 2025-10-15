<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            [
                'title' => 'E-commerce Platform',
                'slug' => 'ecommerce-platform',
                'description' => 'A modern, scalable e-commerce platform built with Laravel and React',
                'content' => 'A comprehensive e-commerce solution featuring product management, shopping cart, payment integration, and admin dashboard. Built with Laravel 12 backend and React 18 frontend.',
                'image' => 'projects/ecommerce.jpg',
                'images' => ['projects/ecommerce/1.jpg', 'projects/ecommerce/2.jpg', 'projects/ecommerce/3.jpg'],
                'category' => 'web',
                'technologies' => ['Laravel', 'React', 'MySQL', 'Redis', 'Stripe'],
                'client' => 'ABC Corporation',
                'url' => 'https://example.com',
                'completed_at' => '2024-06-30',
                'featured' => true,
                'published' => true,
                'sort_order' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'E-commerce Platform',
                        'slug' => 'ecommerce-platform',
                        'description' => 'A modern, scalable e-commerce platform built with Laravel and React',
                        'content' => 'A comprehensive e-commerce solution featuring product management, shopping cart, payment integration, and admin dashboard. Built with Laravel 12 backend and React 18 frontend.',
                        'meta_title' => 'E-commerce Platform - Modern Shopping Solution',
                        'meta_description' => 'A modern, scalable e-commerce platform built with Laravel and React for ABC Corporation',
                        'og_title' => 'E-commerce Platform',
                        'og_description' => 'Modern shopping solution with Laravel and React',
                    ],
                    'id' => [
                        'title' => 'Platform E-commerce',
                        'slug' => 'platform-ecommerce',
                        'description' => 'Platform e-commerce modern dan scalable yang dibangun dengan Laravel dan React',
                        'content' => 'Solusi e-commerce komprehensif dengan manajemen produk, keranjang belanja, integrasi pembayaran, dan dashboard admin. Dibangun dengan backend Laravel 12 dan frontend React 18.',
                        'meta_title' => 'Platform E-commerce - Solusi Belanja Modern',
                        'meta_description' => 'Platform e-commerce modern dan scalable yang dibangun dengan Laravel dan React untuk ABC Corporation',
                        'og_title' => 'Platform E-commerce',
                        'og_description' => 'Solusi belanja modern dengan Laravel dan React',
                    ],
                ],
            ],
            [
                'title' => 'Task Management App',
                'slug' => 'task-management-app',
                'description' => 'Collaborative task management tool for teams',
                'content' => 'A powerful task management application with real-time collaboration, project tracking, and team communication features.',
                'image' => 'projects/task-app.jpg',
                'images' => ['projects/task-app/1.jpg', 'projects/task-app/2.jpg'],
                'category' => 'web',
                'technologies' => ['Laravel', 'Vue.js', 'WebSockets', 'PostgreSQL'],
                'client' => 'Internal Project',
                'url' => 'https://tasks.example.com',
                'completed_at' => null,
                'featured' => false,
                'published' => true,
                'sort_order' => 2,
                'translations' => [
                    'en' => [
                        'title' => 'Task Management Application',
                        'slug' => 'task-management-app',
                        'description' => 'Collaborative task management tool for teams',
                        'content' => 'A powerful task management application with real-time collaboration, project tracking, and team communication features.',
                        'meta_title' => 'Task Management App - Team Collaboration Tool',
                        'meta_description' => 'Collaborative task management tool for teams with real-time updates',
                    ],
                    'id' => [
                        'title' => 'Aplikasi Manajemen Tugas',
                        'slug' => 'aplikasi-manajemen-tugas',
                        'description' => 'Tool manajemen tugas kolaboratif untuk tim',
                        'content' => 'Aplikasi manajemen tugas yang powerful dengan kolaborasi real-time, pelacakan proyek, dan fitur komunikasi tim.',
                        'meta_title' => 'Aplikasi Manajemen Tugas - Tool Kolaborasi Tim',
                        'meta_description' => 'Tool manajemen tugas kolaboratif untuk tim dengan pembaruan real-time',
                    ],
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $translations = $projectData['translations'];
            unset($projectData['translations']);

            $project = Project::create($projectData);

            foreach ($translations as $language => $translationData) {
                $project->translations()->create(array_merge(
                    $translationData,
                    ['language' => $language]
                ));
            }
        }

        $this->command->info('Projects seeded successfully!');
    }
}
