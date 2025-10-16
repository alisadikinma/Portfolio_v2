<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');
        
        // Create admin user first
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProjectSeeder::class,
            PostSeeder::class,
            ServiceSeeder::class,
            GallerySeeder::class,
            AwardSeeder::class,
            AwardGallerySeeder::class,
            NewsletterSeeder::class,
            ContactSeeder::class,
            MenuItemSeeder::class,
            PageSectionSeeder::class,
        ]);

        $this->command->info('✅ Database seeding completed successfully!');
    }
}
