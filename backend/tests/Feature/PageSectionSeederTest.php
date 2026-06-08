<?php

namespace Tests\Feature;

use App\Models\PageSection;
use Database\Seeders\PageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Operator redesign (Phase E). Verifies the homepage page-section rows match
 * the 9-section spine rendered by Home.vue — so there are no ghost toggles and no
 * missing rows. Runs on CI/VPS (local Mac has no PHP/MySQL).
 */
class PageSectionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** The canonical homepage spine, in render order (kebab-case). */
    private array $spine = [
        'hero',
        'who-i-am',
        'what-i-solve',
        'receipts',
        'international-stages',
        'selected-work',
        'testimonials',
        'latest-writing',
        'join-the-build',
    ];

    public function test_seeds_all_nine_operator_homepage_sections(): void
    {
        $this->seed(PageSectionSeeder::class);

        foreach ($this->spine as $sectionType) {
            $this->assertDatabaseHas('page_sections', [
                'page_type' => 'homepage',
                'section_type' => $sectionType,
                'is_active' => true,
            ]);
        }
    }

    public function test_drops_obsolete_homepage_ghost_sections(): void
    {
        // Pre-seed legacy snake_case ghosts the new Home.vue no longer renders.
        foreach (['featured_projects', 'latest_blog', 'awards', 'gallery', 'cta'] as $i => $ghost) {
            PageSection::create([
                'page_type' => 'homepage',
                'section_type' => $ghost,
                'is_active' => true,
                'sequence' => $i,
            ]);
        }

        $this->seed(PageSectionSeeder::class);

        foreach (['featured_projects', 'latest_blog', 'awards', 'gallery', 'cta'] as $ghost) {
            $this->assertDatabaseMissing('page_sections', [
                'page_type' => 'homepage',
                'section_type' => $ghost,
            ]);
        }
    }

    public function test_is_idempotent_and_preserves_admin_toggles(): void
    {
        $this->seed(PageSectionSeeder::class);

        // Admin turns one section off.
        PageSection::where('page_type', 'homepage')
            ->where('section_type', 'receipts')
            ->update(['is_active' => false]);

        // Re-seed (mirrors deploy.sh idempotent re-run).
        $this->seed(PageSectionSeeder::class);

        $this->assertDatabaseHas('page_sections', [
            'page_type' => 'homepage',
            'section_type' => 'receipts',
            'is_active' => false, // toggle preserved, not reset
        ]);

        // No duplicate rows created.
        $count = PageSection::where('page_type', 'homepage')
            ->where('section_type', 'receipts')->count();
        $this->assertSame(1, $count);
    }

    public function test_preserves_non_homepage_rows(): void
    {
        $this->seed(PageSectionSeeder::class);

        // About/projects/blog CTA rows still exist (not touched by the homepage reset).
        $this->assertDatabaseHas('page_sections', [
            'page_type' => 'about',
            'section_type' => 'cta',
        ]);
    }
}
