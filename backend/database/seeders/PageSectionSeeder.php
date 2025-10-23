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
        $sections = [
            // Homepage sections (all active)
            [
                'page_type' => 'homepage',
                'section_type' => 'hero',
                'is_active' => true,
                'sequence' => 0,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'featured_projects',
                'is_active' => true,
                'sequence' => 1,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'latest_blog',
                'is_active' => true,
                'sequence' => 2,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'testimonials',
                'is_active' => true,
                'sequence' => 3,
            ],
            [
                'page_type' => 'homepage',
                'section_type' => 'cta',
                'is_active' => true,
                'sequence' => 4,
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
