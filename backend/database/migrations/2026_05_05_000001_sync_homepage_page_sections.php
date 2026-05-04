<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time data migration: align `page_sections` rows with the May 5
 * homepage redesign cleanup.
 *
 *   - Insert kebab-case rows for `what-i-solve` and `skills-reel` if
 *     missing (views read kebab-case; `PageSectionSeeder` carries
 *     legacy snake_case ghosts that are not rendered).
 *   - Insert kebab-case `testimonials` row if missing (the seeder has
 *     it but only as snake_case `testimonials` was kept consistent).
 *   - Deactivate the 4 retired SkillShowcase rows whose content has
 *     been merged into `what-i-solve` so they stop occupying snap-page
 *     real estate. Rows are kept (not deleted) so the audit trail of
 *     historical activations survives and operators can re-toggle if
 *     needed.
 *
 * Idempotent — safe to re-run. Down() restores the deactivated rows
 * and removes the kebab-case inserts.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = Carbon::now();

        $ensureRow = function (string $sectionType, int $sequence, string $title) use ($now): void {
            $exists = DB::table('page_sections')
                ->where('page_type', 'homepage')
                ->where('section_type', $sectionType)
                ->exists();

            if (!$exists) {
                DB::table('page_sections')->insert([
                    'page_type' => 'homepage',
                    'section_type' => $sectionType,
                    'title' => $title,
                    'is_active' => 1,
                    'sequence' => $sequence,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        };

        $ensureRow('skills-reel', 2, 'Skills Reel — Kinetic Marquee');
        $ensureRow('what-i-solve', 3, 'What I Solve — Four Disciplines');
        $ensureRow('featured-projects', 7, 'Featured Projects');
        $ensureRow('latest-blog', 8, 'Latest Blog');
        $ensureRow('testimonials', 9, 'Testimonials — LinkedIn Quotes');
        $ensureRow('stats-cta', 10, 'Stats + CTA');

        // Deactivate retired SkillShowcase rows whose content lives in
        // `what-i-solve` now. Only touches rows that currently exist.
        DB::table('page_sections')
            ->where('page_type', 'homepage')
            ->whereIn('section_type', [
                'skill-vibe-coding',
                'skill-ai-automation',
                'skill-ai-agents',
                'skill-ai-video',
            ])
            ->update([
                'is_active' => 0,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = Carbon::now();

        // Re-activate retired skill rows so the previous state is restored.
        DB::table('page_sections')
            ->where('page_type', 'homepage')
            ->whereIn('section_type', [
                'skill-vibe-coding',
                'skill-ai-automation',
                'skill-ai-agents',
                'skill-ai-video',
            ])
            ->update([
                'is_active' => 1,
                'updated_at' => $now,
            ]);

        DB::table('page_sections')
            ->where('page_type', 'homepage')
            ->whereIn('section_type', [
                'skills-reel',
                'what-i-solve',
                'featured-projects',
                'latest-blog',
                'testimonials',
                'stats-cta',
            ])
            ->delete();
    }
};
