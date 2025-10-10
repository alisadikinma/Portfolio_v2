<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gallery;

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
                'image' => 'gallery/ecommerce-dashboard.jpg',
                'category' => 'web',
                'order' => 1,
            ],
            [
                'title' => 'Portfolio Website',
                'description' => 'Minimalist portfolio design with smooth animations',
                'image' => 'gallery/portfolio-website.jpg',
                'category' => 'web',
                'order' => 2,
            ],
            [
                'title' => 'Task Management App',
                'description' => 'Collaborative task management with real-time updates',
                'image' => 'gallery/task-management.jpg',
                'category' => 'web',
                'order' => 3,
            ],

            // Mobile Projects
            [
                'title' => 'Fitness Tracker',
                'description' => 'iOS/Android fitness tracking app with workout plans',
                'image' => 'gallery/fitness-tracker.jpg',
                'category' => 'mobile',
                'order' => 1,
            ],
            [
                'title' => 'Food Delivery App',
                'description' => 'Cross-platform food delivery application',
                'image' => 'gallery/food-delivery.jpg',
                'category' => 'mobile',
                'order' => 2,
            ],

            // UI/UX Designs
            [
                'title' => 'Banking App UI',
                'description' => 'Modern banking app interface design',
                'image' => 'gallery/banking-app-ui.jpg',
                'category' => 'design',
                'order' => 1,
            ],
            [
                'title' => 'Social Media Redesign',
                'description' => 'Social media platform UI/UX redesign concept',
                'image' => 'gallery/social-media-redesign.jpg',
                'category' => 'design',
                'order' => 2,
            ],
            [
                'title' => 'Dashboard Components',
                'description' => 'Reusable dashboard component library',
                'image' => 'gallery/dashboard-components.jpg',
                'category' => 'design',
                'order' => 3,
            ],

            // Photography
            [
                'title' => 'Mountain Landscape',
                'description' => 'Beautiful mountain landscape photography',
                'image' => 'gallery/mountain-landscape.jpg',
                'category' => 'photography',
                'order' => 1,
            ],
            [
                'title' => 'Urban Architecture',
                'description' => 'Modern urban architecture photography',
                'image' => 'gallery/urban-architecture.jpg',
                'category' => 'photography',
                'order' => 2,
            ],

            // Logos & Branding
            [
                'title' => 'Tech Startup Logo',
                'description' => 'Minimalist logo design for tech startup',
                'image' => 'gallery/tech-startup-logo.jpg',
                'category' => 'branding',
                'order' => 1,
            ],
            [
                'title' => 'Coffee Shop Branding',
                'description' => 'Complete branding package for coffee shop',
                'image' => 'gallery/coffee-shop-branding.jpg',
                'category' => 'branding',
                'order' => 2,
            ],
        ];

        foreach ($galleries as $gallery) {
            Gallery::create($gallery);
        }

        $this->command->info(' Gallery items seeded successfully!');
        $this->command->info('   - ' . count($galleries) . ' gallery items created');
        $this->command->info('   - Categories: web, mobile, design, photography, branding');
    }
}
