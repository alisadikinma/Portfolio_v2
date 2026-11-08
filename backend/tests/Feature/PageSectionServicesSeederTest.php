<?php

namespace Tests\Feature;

use App\Models\PageSection;
use Database\Seeders\PageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PF-2 phase 3E: the Astro home page has a Services section (Konsultasi / Pelatihan / Pembicara)
 * toggled from /admin/page-sections like the other homepage sections.
 */
class PageSectionServicesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_yields_exactly_one_active_services_row(): void
    {
        $this->seed(PageSectionSeeder::class);
        $this->seed(PageSectionSeeder::class);

        $rows = PageSection::where('page_type', 'homepage')->where('section_type', 'services')->get();
        $this->assertCount(1, $rows);
        $this->assertTrue((bool) $rows->first()->is_active);
    }

    public function test_reseeding_keeps_an_admin_turning_services_off(): void
    {
        $this->seed(PageSectionSeeder::class);
        PageSection::where('page_type', 'homepage')->where('section_type', 'services')->update(['is_active' => false]);

        $this->seed(PageSectionSeeder::class);

        $this->assertFalse((bool) PageSection::where('page_type', 'homepage')->where('section_type', 'services')->value('is_active'));
    }
}
