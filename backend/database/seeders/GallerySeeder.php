<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gallery;
use App\Models\GalleryItem;

class GallerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $galleries = [
            // Web Projects
            [
                'title' => 'E-commerce Dashboard',
                'description' => 'Modern e-commerce admin dashboard with analytics',
                'company' => 'ABC Corporation',
                'period' => '2024',
                'thumbnail' => null, // Will use placeholder
                'is_active' => true,
                'sort_order' => 1,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Dashboard View', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Products Page', 'sequence' => 2],
                    ['file_path' => 'gallery/placeholder-3.jpg', 'title' => 'Analytics', 'sequence' => 3],
                ],
            ],
            [
                'title' => 'Portfolio Website',
                'description' => 'Minimalist portfolio design with smooth animations',
                'company' => 'Personal Project',
                'period' => '2024',
                'thumbnail' => null,
                'is_active' => true,
                'sort_order' => 2,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Home Page', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Projects Page', 'sequence' => 2],
                ],
            ],
            [
                'title' => 'Task Management App',
                'description' => 'Collaborative task management with real-time updates',
                'company' => 'Internal Project',
                'period' => '2024',
                'thumbnail' => null,
                'is_active' => true,
                'sort_order' => 3,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Task Board', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Calendar View', 'sequence' => 2],
                ],
            ],

            // Mobile Projects
            [
                'title' => 'Fitness Tracker',
                'description' => 'iOS/Android fitness tracking app with workout plans',
                'company' => 'HealthTech Inc',
                'period' => '2023-2024',
                'thumbnail' => null,
                'is_active' => true,
                'sort_order' => 4,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Dashboard', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Workout Screen', 'sequence' => 2],
                ],
            ],
            [
                'title' => 'Food Delivery App',
                'description' => 'Cross-platform food delivery application',
                'company' => 'FoodHub',
                'period' => '2023',
                'thumbnail' => null,
                'is_active' => true,
                'sort_order' => 5,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Home Screen', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Menu Page', 'sequence' => 2],
                ],
            ],

            // UI/UX Designs
            [
                'title' => 'Banking App UI',
                'description' => 'Modern banking app interface design',
                'company' => 'SecureBank',
                'period' => '2024',
                'thumbnail' => null,
                'is_active' => true,
                'sort_order' => 6,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Login Screen', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Dashboard', 'sequence' => 2],
                ],
            ],
            [
                'title' => 'Social Media Redesign',
                'description' => 'Social media platform UI/UX redesign concept',
                'company' => 'Concept Project',
                'period' => '2024',
                'thumbnail' => null,
                'is_active' => true,
                'sort_order' => 7,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Feed View', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Profile Page', 'sequence' => 2],
                ],
            ],

            // Photography
            [
                'title' => 'Mountain Landscape',
                'description' => 'Beautiful mountain landscape photography',
                'company' => null,
                'period' => '2024',
                'thumbnail' => null,
                'is_active' => true,
                'sort_order' => 8,
                'items' => [
                    ['file_path' => 'gallery/placeholder-1.jpg', 'title' => 'Mountain Peak', 'sequence' => 1],
                    ['file_path' => 'gallery/placeholder-2.jpg', 'title' => 'Valley View', 'sequence' => 2],
                ],
            ],
        ];

        foreach ($galleries as $galleryData) {
            $items = $galleryData['items'];
            unset($galleryData['items']);

            $gallery = Gallery::create($galleryData);

            foreach ($items as $item) {
                GalleryItem::create([
                    'gallery_id' => $gallery->id,
                    'type' => 'image',
                    'file_path' => $item['file_path'],
                    'title' => $item['title'],
                    'sequence' => $item['sequence'],
                ]);
            }
        }

        $this->command->info('✓ Galleries seeded successfully!');
        $this->command->info('   - ' . count($galleries) . ' galleries created');
        $this->command->info('   - With gallery items for each');
        $this->command->warn('   ⚠ Note: Placeholder images used. Upload real images via admin panel.');
    }
}
