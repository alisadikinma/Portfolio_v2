<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Category;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories first
        $webCategory = Category::firstOrCreate(
            ['slug' => 'web-development'],
            [
                'name' => 'Web Development',
                'description' => 'Articles about web development, frameworks, and best practices',
                'color' => '#3B82F6',
                'sort_order' => 1,
            ]
        );

        $tutorialCategory = Category::firstOrCreate(
            ['slug' => 'tutorials'],
            [
                'name' => 'Tutorials',
                'description' => 'Step-by-step guides and tutorials',
                'color' => '#10B981',
                'sort_order' => 2,
            ]
        );

        // Create posts
        $posts = [
            [
                'category_id' => $webCategory->id,
                'title' => 'Getting Started with Vue 3',
                'slug' => 'getting-started-vue3',
                'excerpt' => 'Learn the basics of Vue 3 and build your first application',
                'content' => '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>',
                'featured_image' => 'posts/vue3-tutorial.jpg',
                'tags' => ['vue', 'javascript', 'frontend', 'tutorial'],
                'is_premium' => false,
                'published' => true,
                'published_at' => now()->subDays(5),
                'views' => 150,
                'translations' => [
                    'en' => [
                        'title' => 'Getting Started with Vue 3',
                        'slug' => 'getting-started-vue3',
                        'excerpt' => 'Learn the basics of Vue 3 and build your first application',
                        'content' => '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>',
                        'meta_title' => 'Getting Started with Vue 3 - Complete Guide',
                        'meta_description' => 'Learn Vue 3 from scratch with this comprehensive tutorial covering Composition API, components, and more.',
                    ],
                    'id' => [
                        'title' => 'Memulai dengan Vue 3',
                        'slug' => 'memulai-dengan-vue3',
                        'excerpt' => 'Pelajari dasar-dasar Vue 3 dan bangun aplikasi pertama Anda',
                        'content' => '<p>Vue 3 adalah versi terbaru dari framework JavaScript progresif. Dalam panduan komprehensif ini, kita akan menjelajahi fitur-fitur baru termasuk Composition API, peningkatan performa, dan dukungan TypeScript.</p><p>Kita akan membangun aplikasi lengkap dari awal, mencakup komponen, manajemen state, routing, dan strategi deployment. Di akhir tutorial ini, Anda akan memiliki pemahaman yang solid tentang fundamental Vue 3.</p>',
                        'meta_title' => 'Memulai dengan Vue 3 - Panduan Lengkap',
                        'meta_description' => 'Pelajari Vue 3 dari awal dengan tutorial komprehensif ini yang mencakup Composition API, komponen, dan lainnya.',
                    ],
                ],
            ],
            [
                'category_id' => $webCategory->id,
                'title' => 'Laravel 12 New Features',
                'slug' => 'laravel-12-new-features',
                'excerpt' => 'Explore the exciting new features in Laravel 12',
                'content' => '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>',
                'featured_image' => 'posts/laravel-12.jpg',
                'tags' => ['laravel', 'php', 'backend', 'framework'],
                'is_premium' => false,
                'published' => true,
                'published_at' => now()->subDays(2),
                'views' => 89,
                'translations' => [
                    'en' => [
                        'title' => 'Laravel 12 New Features',
                        'slug' => 'laravel-12-new-features',
                        'excerpt' => 'Explore the exciting new features in Laravel 12',
                        'content' => '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>',
                        'meta_title' => 'Laravel 12 New Features - Complete Overview',
                        'meta_description' => 'Discover the new features in Laravel 12 and learn how to upgrade your applications.',
                    ],
                    'id' => [
                        'title' => 'Fitur Baru Laravel 12',
                        'slug' => 'fitur-baru-laravel-12',
                        'excerpt' => 'Jelajahi fitur-fitur baru yang menarik di Laravel 12',
                        'content' => '<p>Laravel 12 membawa peningkatan signifikan ke framework. Kita akan mendalami fitur-fitur baru termasuk query builder database yang ditingkatkan, utilitas testing yang lebih baik, dan performa yang lebih bagus.</p><p>Artikel ini mencakup contoh praktis dan panduan migrasi untuk membantu Anda mengupgrade aplikasi Laravel yang ada ke versi 12.</p>',
                        'meta_title' => 'Fitur Baru Laravel 12 - Overview Lengkap',
                        'meta_description' => 'Temukan fitur-fitur baru di Laravel 12 dan pelajari cara mengupgrade aplikasi Anda.',
                    ],
                ],
            ],
        ];

        foreach ($posts as $postData) {
            $translations = $postData['translations'];
            unset($postData['translations']);

            $post = Post::create($postData);

            foreach ($translations as $language => $translationData) {
                $post->translations()->create(array_merge(
                    $translationData,
                    ['language' => $language]
                ));
            }
        }

        $this->command->info('Posts and categories seeded successfully!');
    }
}

