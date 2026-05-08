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
            AboutSettingsSeeder::class,
            SiteSettingsSeeder::class,
            CtaSettingsSeeder::class, // CTA section settings
            CreatorBrandSettingsSeeder::class, // creator watermark/filename branding
            TelegramSettingsSeeder::class, // telegram bot for manifest/failure/success alerts
            LinkedInSettingsSeeder::class, // linkedin admin UI publishing flags
            PublerSettingsSeeder::class, // publer cross-post integration — api_key (encrypted) + 3 account IDs + master toggle
            BlogPromoSettingsSeeder::class, // blog.blog_promo_project_id for mid-article promo rotator
            CvSettingsSeeder::class, // /api/cv/export schema_version 2.0.0 — summary_variants, work_experience, skills_matrix, education
        ]);

        $this->command->info('✅ Database seeding completed successfully!');
    }
}
