<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuItems = [
            [
                'title' => 'Home',
                'slug' => 'home',
                'url' => '/',
                'icon' => 'home',
                'is_active' => true,
                'sequence' => 0,
            ],
            [
                'title' => 'About',
                'slug' => 'about',
                'url' => '/about',
                'icon' => 'info',
                'is_active' => true,
                'sequence' => 1,
            ],
            [
                'title' => 'Projects',
                'slug' => 'projects',
                'url' => '/projects',
                'icon' => 'briefcase',
                'is_active' => true,
                'sequence' => 2,
            ],
            [
                'title' => 'Awards',
                'slug' => 'awards',
                'url' => '/awards',
                'icon' => 'award',
                'is_active' => true,
                'sequence' => 3,
            ],
            [
                'title' => 'Blog',
                'slug' => 'blog',
                'url' => '/blog',
                'icon' => 'book',
                'is_active' => true,
                'sequence' => 4,
            ],
            [
                'title' => 'Gallery',
                'slug' => 'gallery',
                'url' => '/gallery',
                'icon' => 'images',
                'is_active' => true,
                'sequence' => 5,
            ],
            [
                'title' => 'Contact',
                'slug' => 'contact',
                'url' => '/contact',
                'icon' => 'mail',
                'is_active' => true,
                'sequence' => 6,
            ],
        ];

        foreach ($menuItems as $item) {
            MenuItem::firstOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
