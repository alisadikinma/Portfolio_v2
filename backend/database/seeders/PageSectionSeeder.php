<?php

namespace Database\Seeders;

use App\Models\PageSection;
use Illuminate\Database\Seeder;

class PageSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // The Operator redesign (2026-06-08): homepage renders a fixed 9-section
        // spine (kebab-case). Drop the legacy snake_case ghosts the new Home.vue
        // no longer renders so they don't linger as ghost toggles in the admin.
        // Scoped to page_type='homepage' — about/projects/blog rows untouched.
        // Idempotent: a no-op once the ghosts are gone.
        PageSection::where('page_type', 'homepage')
            ->whereIn('section_type', ['featured_projects', 'latest_blog', 'awards', 'gallery', 'cta'])
            ->delete();

        $sections = [
            // Homepage — The Operator 9-section spine (all active), in render order.
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
                'section_type' => 'what-i-solve',
                'is_active' => true,
                'sequence' => 2,
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
                'section_type' => 'selected-work',
                'is_active' => true,
                'sequence' => 5,
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
