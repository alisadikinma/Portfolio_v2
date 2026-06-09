<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\LinkedInSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — default-carousel plan (2026-06-09).
 * Verifies the linkedin_force_carousel flag is seeded (default 'true') and
 * the seeder stays idempotent.
 */
class LinkedInForceCarouselSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_force_carousel_flag_defaulting_true(): void
    {
        $this->seed(LinkedInSettingsSeeder::class);

        $this->assertSame('true', Setting::get('linkedin_force_carousel'));

        $row = Setting::where('key', 'linkedin_force_carousel')
            ->where('group', 'linkedin')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('text', $row->type);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(LinkedInSettingsSeeder::class);
        $countAfterFirst = Setting::where('group', 'linkedin')->count();

        $this->seed(LinkedInSettingsSeeder::class);
        $countAfterSecond = Setting::where('group', 'linkedin')->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_force_carousel_value_is_truthy_by_default(): void
    {
        $this->seed(LinkedInSettingsSeeder::class);

        $value = Setting::get('linkedin_force_carousel', 'false');
        $this->assertTrue(
            filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'linkedin_force_carousel should be truthy by default'
        );
    }
}
