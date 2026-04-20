<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the single `blog.blog_promo_project_id` row consumed by
 * BlogPromoSlotController. Null by default → controller falls through
 * to featured-project / award / generic-CTA tiers.
 *
 * firstOrCreate so production deploys don't clobber an operator-set pin.
 */
class BlogPromoSettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(
            ['key' => 'blog_promo_project_id', 'group' => 'blog'],
            ['value' => null, 'type' => 'text']
        );

        if ($this->command) {
            $this->command->info('✅ Blog promo settings seeded successfully!');
        }
    }
}
