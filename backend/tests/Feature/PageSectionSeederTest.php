<?php

namespace Tests\Feature;

use App\Models\PageSection;
use Database\Seeders\PageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PF-2 phase 5B. Verifies the homepage page-section rows match the sections the Astro home
 * (web/src/pages/[lang]/index.astro) actually toggles — no ghost toggles, no missing rows.
 * The Vue-only rows `what-i-solve` and `selected-work` are deleted on seed.
 */
class PageSectionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Section types the Astro home reads (kebab-case). */
    private array $spine = [
        'hero',
        'receipts',
        'international-stages',
        'services',
        'testimonials',
        'latest-writing',
        'join-the-build',
    ];

    /** Rows only the retired Vue home rendered. */
    private array $vueOnlyGhosts = ['what-i-solve', 'selected-work'];

    public function test_seeds_every_astro_homepage_section(): void
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

    public function test_does_not_seed_vue_only_homepage_sections(): void
    {
        $this->seed(PageSectionSeeder::class);

        foreach ($this->vueOnlyGhosts as $ghost) {
            $this->assertDatabaseMissing('page_sections', [
                'page_type' => 'homepage',
                'section_type' => $ghost,
            ]);
        }
    }

    public function test_deletes_existing_vue_only_homepage_rows_on_seed(): void
    {
        // Old migrations may already have created these rows; make sure they exist before seeding.
        foreach ($this->vueOnlyGhosts as $i => $ghost) {
            PageSection::firstOrCreate(
                ['page_type' => 'homepage', 'section_type' => $ghost],
                ['is_active' => true, 'sequence' => $i],
            );
        }

        $this->seed(PageSectionSeeder::class);
        $this->seed(PageSectionSeeder::class); // idempotent re-run

        foreach ($this->vueOnlyGhosts as $ghost) {
            $this->assertDatabaseMissing('page_sections', [
                'page_type' => 'homepage',
                'section_type' => $ghost,
            ]);
        }
        // Only homepage rows are touched: a same-named row on another page stays.
        PageSection::firstOrCreate(
            ['page_type' => 'about', 'section_type' => 'selected-work'],
            ['is_active' => false, 'sequence' => 9],
        );
        $this->seed(PageSectionSeeder::class);
        $this->assertDatabaseHas('page_sections', ['page_type' => 'about', 'section_type' => 'selected-work']);
    }

    public function test_deletes_every_retired_homepage_row_the_astro_home_never_reads(): void
    {
        $retired = ['skills-reel', 'skill-vibe-coding', 'featured-projects', 'latest-blog', 'stats-cta'];
        foreach ($retired as $i => $type) {
            PageSection::firstOrCreate(
                ['page_type' => 'homepage', 'section_type' => $type],
                ['is_active' => true, 'sequence' => $i],
            );
        }

        $this->seed(PageSectionSeeder::class);

        $this->assertEqualsCanonicalizing(
            $this->spine,
            PageSection::where('page_type', 'homepage')->pluck('section_type')->all(),
        );
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
