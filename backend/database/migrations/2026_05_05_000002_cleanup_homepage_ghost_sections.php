<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up cleanup to 2026_05_05_000001_sync_homepage_page_sections.
 *
 * Bug surfaced via /gaspol-debug after the May 4 deploy: admin UI
 * `/admin/page-sections` showed apparent duplicates because the legacy
 * snake_case rows (Latest Blog Posts / Featured Projects / Awards /
 * Call to Action / Gallery on homepage) were never deleted when the
 * kebab-case rows started carrying the actual rendering. Operator
 * confusion = ghost toggles. Plus `featured-projects` (kebab) was
 * created INACTIVE by the previous migration — should have been active.
 *
 * Operations on `homepage` page_type only:
 *
 *   DELETE 5 snake_case ghost rows that no Home.vue v-if reads:
 *     - awards          (seq=0)   — never read; legacy seeder leftover
 *     - gallery         (seq=2)   — never read
 *     - latest_blog     (seq=5)   — superseded by `latest-blog` (kebab)
 *     - featured_projects (seq=11) — superseded by `featured-projects` (kebab)
 *     - cta             (seq=15)  — Home.vue uses `stats-cta`, not `cta`
 *
 *   UPDATE: activate featured-projects (kebab) so ProjectsBento renders.
 *
 * Idempotent — safe to re-run. Down() is a no-op for the deletions
 * because the legacy rows had no canonical schema and re-inserting them
 * would just put orphan data back. The featured-projects activation IS
 * reversible (down sets it back to inactive).
 *
 * Other page_types (about, projects, blog, gallery) keep their snake_case
 * rows since those views may still reference them (out-of-scope for
 * this homepage cleanup).
 */
return new class extends Migration {
    public function up(): void
    {
        $deleted = DB::table('page_sections')
            ->where('page_type', 'homepage')
            ->whereIn('section_type', [
                'awards',
                'gallery',
                'latest_blog',
                'featured_projects',
                'cta',
            ])
            ->delete();

        DB::table('page_sections')
            ->where('page_type', 'homepage')
            ->where('section_type', 'featured-projects')
            ->update([
                'is_active' => 1,
                'updated_at' => Carbon::now(),
            ]);

        // Surface the count in the migration log so operator can confirm.
        if (function_exists('logger')) {
            logger()->info('[migration] cleanup_homepage_ghost_sections', [
                'deleted_rows' => $deleted,
            ]);
        }
    }

    public function down(): void
    {
        // Set featured-projects back to inactive — the only reversible
        // operation. Ghost row deletions are not restored (they had no
        // canonical content; restoring would just re-create orphans).
        DB::table('page_sections')
            ->where('page_type', 'homepage')
            ->where('section_type', 'featured-projects')
            ->update([
                'is_active' => 0,
                'updated_at' => Carbon::now(),
            ]);
    }
};
