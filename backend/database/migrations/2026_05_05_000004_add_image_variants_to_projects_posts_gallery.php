<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C1 of docs/plans/2026-05-05-portfolio-perf-cache-refactor.md
 *
 * Adds a single JSON column `image_variants` to projects, posts,
 * gallery_items. Stored shape (per row):
 *
 * {
 *   "320w":  "/storage/projects/49_dlp-form-request-cybersecurity-320w.webp",
 *   "640w":  "/storage/projects/49_dlp-form-request-cybersecurity-640w.webp",
 *   "1024w": "/storage/projects/49_dlp-form-request-cybersecurity-1024w.webp",
 *   "1920w": "/storage/projects/49_dlp-form-request-cybersecurity-1920w.webp",
 *   "lqip":  "data:image/jpeg;base64,/9j/4AAQ...K"
 * }
 *
 * NULL until ImageVariantService generates variants (queued at upload time
 * via HasImageVariants trait, or backfilled via images:generate-variants
 * artisan). Frontend BaseImage component falls back to the original src
 * when this column is null, so the migration is non-breaking — old rows
 * simply render via plain <img>.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['projects', 'posts', 'gallery_items'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'image_variants')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->json('image_variants')->nullable()->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['projects', 'posts', 'gallery_items'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'image_variants')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('image_variants');
                });
            }
        }
    }
};
