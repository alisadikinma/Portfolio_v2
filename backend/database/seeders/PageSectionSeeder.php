<?php

namespace Database\Seeders;

use App\Models\PageSection;
use Illuminate\Database\Seeder;

class PageSectionSeeder extends Seeder
{
    /** Homepage section_types the Astro home reads (kebab-case). Keep in sync with index.astro. */
    private const HOMEPAGE_SECTIONS = [
        'hero',
        'who-i-am',
        'receipts',
        'international-stages',
        'services',
        'testimonials',
        'latest-writing',
        'join-the-build',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PF-2 phase 5B: the Astro home (web/src/pages/[lang]/index.astro) toggles exactly these
        // homepage sections. Every other homepage row is a ghost toggle that changes nothing:
        // the snake_case legacy rows, the kebab rows of the retired Vue home (what-i-solve,
        // selected-work, skills-reel, featured-projects, latest-blog, stats-cta, skill-*) and
        // anything the old migrations seeded. Scoped to page_type='homepage' — about/projects/blog
        // rows are untouched. Idempotent: a no-op once the ghosts are gone.
        PageSection::where('page_type', 'homepage')
            ->whereNotIn('section_type', self::HOMEPAGE_SECTIONS)
            ->delete();

        $sections = [
            // Homepage — sections the Astro home toggles (all active). Order here is admin display order.
            [
                'page_type' => 'homepage',
                'section_type' => 'hero',
                'is_active' => true,
                'sequence' => 0,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'who-i-am',
                'is_active' => true,
                'sequence' => 1,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'receipts',
                'is_active' => true,
                'sequence' => 3,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'international-stages',
                'is_active' => true,
                'sequence' => 4,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'testimonials',
                'is_active' => true,
                'sequence' => 6,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'latest-writing',
                'is_active' => true,
                'sequence' => 7,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'join-the-build',
                'is_active' => true,
                'sequence' => 8,
            ],
            // PF-2 Astro home: Services (Konsultasi / Pelatihan / Pembicara). Appended (sequence 9) so
            // existing rows keep their sequence; the Astro page renders in its own fixed order.
            [
                'page_type' => 'homepage',
                'section_type' => 'services',
                'is_active' => true,
                'sequence' => 9,
            ],

            // About Page sections (all inactive by default)
            [
                'page_type' => 'about',
                'section_type' => 'featured_projects',
                'is_active' => false,
                'sequence' => 0,
            ],
            [
                'page_type' => 'about',
                'section_type' => 'latest_blog',
                'is_active' => false,
                'sequence' => 1,
            ],
            [
                'page_type' => 'about',
                'section_type' => 'cta',
                'is_active' => false,
                'sequence' => 2,
            ],

            // Projects Page sections (all inactive by default)
            [
                'page_type' => 'projects',
                'section_type' => 'latest_blog',
                'is_active' => false,
                'sequence' => 0,
            ],
            [
                'page_type' => 'projects',
                'section_type' => 'cta',
                'is_active' => false,
                'sequence' => 1,
            ],

            // Blog Page sections (all inactive by default)
            [
                'page_type' => 'blog',
                'section_type' => 'featured_projects',
                'is_active' => false,
                'sequence' => 0,
            ],
            [
                'page_type' => 'blog',
                'section_type' => 'cta',
                'is_active' => false,
                'sequence' => 1,
            ],
        ];

        foreach ($sections as $section) {
            PageSection::firstOrCreate(
                ['page_type' => $section['page_type'], 'section_type' => $section['section_type']],
                $section
            );
        }
    }
}
