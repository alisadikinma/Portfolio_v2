<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\CreatorBrandSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreatorBrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seeder_creates_five_creator_brand_settings(): void
    {
        $this->seed(CreatorBrandSettingsSeeder::class);

        $this->assertSame(5, Setting::where('group', 'creator_brand')->count());

        $rows = Setting::where('group', 'creator_brand')->pluck('value', 'key')->toArray();

        $this->assertArrayHasKey('creator_brand_logo', $rows);
        $this->assertArrayHasKey('creator_brand_tagline', $rows);
        $this->assertArrayHasKey('creator_brand_slug', $rows);
        $this->assertArrayHasKey('watermark_opacity', $rows);
        $this->assertArrayHasKey('watermark_enabled', $rows);
    }

    /** @test */
    public function seeder_uses_expected_defaults(): void
    {
        $this->seed(CreatorBrandSettingsSeeder::class);

        $this->assertNull(Setting::where('group', 'creator_brand')->where('key', 'creator_brand_logo')->value('value'));
        $this->assertSame('alisadikinma.com', Setting::where('group', 'creator_brand')->where('key', 'creator_brand_tagline')->value('value'));
        $this->assertSame('alisadikinma', Setting::where('group', 'creator_brand')->where('key', 'creator_brand_slug')->value('value'));
        $this->assertSame('0.30', Setting::where('group', 'creator_brand')->where('key', 'watermark_opacity')->value('value'));
        $this->assertSame('false', Setting::where('group', 'creator_brand')->where('key', 'watermark_enabled')->value('value'));
    }

    /** @test */
    public function seeder_is_idempotent(): void
    {
        $this->seed(CreatorBrandSettingsSeeder::class);
        $this->seed(CreatorBrandSettingsSeeder::class);

        $this->assertSame(5, Setting::where('group', 'creator_brand')->count());
    }

    /** @test */
    public function seeder_preserves_user_saved_values_on_rerun(): void
    {
        // Simulates: fresh install → seeder → user saves custom values via
        // admin UI → deploy runs seeder again. User values MUST survive.
        // Regression guard: seeder previously used updateOrCreate which
        // clobbered watermark settings on every production deploy.
        $this->seed(CreatorBrandSettingsSeeder::class);

        Setting::where('group', 'creator_brand')->where('key', 'watermark_enabled')->update(['value' => 'true']);
        Setting::where('group', 'creator_brand')->where('key', 'watermark_opacity')->update(['value' => '0.75']);
        Setting::where('group', 'creator_brand')->where('key', 'creator_brand_tagline')->update(['value' => 'custom tagline']);
        Setting::where('group', 'creator_brand')->where('key', 'creator_brand_slug')->update(['value' => 'customslug']);
        Setting::where('group', 'creator_brand')->where('key', 'creator_brand_logo')->update(['value' => '/uploads/branding/user-logo.png']);

        $this->seed(CreatorBrandSettingsSeeder::class);

        $this->assertSame('true', Setting::where('group', 'creator_brand')->where('key', 'watermark_enabled')->value('value'));
        $this->assertSame('0.75', Setting::where('group', 'creator_brand')->where('key', 'watermark_opacity')->value('value'));
        $this->assertSame('custom tagline', Setting::where('group', 'creator_brand')->where('key', 'creator_brand_tagline')->value('value'));
        $this->assertSame('customslug', Setting::where('group', 'creator_brand')->where('key', 'creator_brand_slug')->value('value'));
        $this->assertSame('/uploads/branding/user-logo.png', Setting::where('group', 'creator_brand')->where('key', 'creator_brand_logo')->value('value'));
    }

    /** @test */
    public function image_generation_jobs_table_has_planned_filename_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('image_generation_jobs', 'planned_filename'),
            'Expected image_generation_jobs.planned_filename column to exist after migrations run'
        );
    }

    /** @test */
    public function image_generation_job_fillable_includes_planned_filename(): void
    {
        $job = new \App\Models\ImageGenerationJob();
        $this->assertContains(
            'planned_filename',
            $job->getFillable(),
            'Expected planned_filename to be mass-assignable on ImageGenerationJob'
        );
    }
}
