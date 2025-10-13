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
            AwardSeeder::class,
            ServiceSeeder::class,
            GallerySeeder::class,
            NewsletterSeeder::class,
            ContactSeeder::class,
        ]);

        $this->command->info('✅ Database seeding completed successfully!');
    }
}
