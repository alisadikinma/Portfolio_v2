<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding categories...');

        $categories = [
            [
                'name' => 'Web Development',
                'slug' => 'web-development',
                'description' => 'Articles about web development, programming, and coding',
                'color' => '#3b82f6', // Blue
                'meta_title' => 'Web Development Articles',
                'meta_description' => 'Read the latest articles about web development, programming languages, frameworks, and best practices.',
                'is_active' => true,
            ],
            [
                'name' => 'Design',
                'slug' => 'design',
                'description' => 'UI/UX design, graphic design, and creative inspiration',
                'color' => '#ec4899', // Pink
                'meta_title' => 'Design Articles',
                'meta_description' => 'Explore articles about UI/UX design, graphic design, and creative processes.',
                'is_active' => true,
            ],
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Latest tech news, trends, and innovations',
                'color' => '#10b981', // Green
                'meta_title' => 'Technology News',
                'meta_description' => 'Stay updated with the latest technology news, trends, and innovations.',
                'is_active' => true,
            ],
            [
                'name' => 'Tutorial',
                'slug' => 'tutorial',
                'description' => 'Step-by-step guides and tutorials',
                'color' => '#f59e0b', // Orange
                'meta_title' => 'Tutorials and Guides',
                'meta_description' => 'Learn with our comprehensive tutorials and step-by-step guides.',
                'is_active' => true,
            ],
            [
                'name' => 'Career',
                'slug' => 'career',
                'description' => 'Career advice, tips, and professional development',
                'color' => '#8b5cf6', // Purple
                'meta_title' => 'Career Development',
                'meta_description' => 'Get career advice, tips, and insights for professional growth.',
                'is_active' => true,
            ],
            [
                'name' => 'Personal',
                'slug' => 'personal',
                'description' => 'Personal thoughts, experiences, and stories',
                'color' => '#ef4444', // Red
                'meta_title' => 'Personal Blog',
                'meta_description' => 'Read personal thoughts, experiences, and stories.',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
            $this->command->info("✓ Created category: {$category['name']}");
        }

        $this->command->info('✅ Categories seeded successfully!');
    }
}
