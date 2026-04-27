<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend image_generation_jobs to support LinkedIn carousel slide rendering.
 *
 * Reuses the existing table (single source of truth for all GeminiGen jobs)
 * rather than creating a parallel linkedin_carousel_image_jobs table. Three
 * additions:
 *
 *   linkedin_post_id   FK to linkedin_posts (nullable — content-engine jobs
 *                      have null here, just like LinkedIn jobs have null
 *                      post_id)
 *   slide_index        which slide in the carousel (0-based)
 *   slide_image_role   layout_hint of the slide ('cover', 'body',
 *                      'human_fingerprint', 'direct_answer', 'cta') — kept
 *                      separate from `type` so the existing 'hero'/'inline'
 *                      enum stays untouched for content-engine code paths
 *
 * `type` enum is widened to include 'carousel_slide' so LinkedIn jobs can
 * be filtered/identified without crawling FK joins.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: widen the `type` enum to include 'carousel_slide'.
        // MySQL ENUMs require a raw ALTER since Laravel's schema builder
        // can't redefine an enum cleanly without doctrine/dbal.
        DB::statement(
            "ALTER TABLE image_generation_jobs MODIFY COLUMN `type` "
            . "ENUM('hero', 'inline', 'carousel_slide') NOT NULL DEFAULT 'hero'"
        );

        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('linkedin_post_id')->nullable()->after('post_id');
            $table->unsignedSmallInteger('slide_index')->nullable()->after('linkedin_post_id');
            $table->string('slide_image_role', 32)->nullable()->after('slide_index');

            $table->foreign('linkedin_post_id')
                ->references('id')->on('linkedin_posts')
                ->cascadeOnDelete();

            $table->index(['linkedin_post_id', 'status'], 'idx_imgjobs_li_status');
            $table->index(['linkedin_post_id', 'slide_index'], 'idx_imgjobs_li_slide');
        });
    }

    public function down(): void
    {
        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_imgjobs_li_slide');
            $table->dropIndex('idx_imgjobs_li_status');
            $table->dropForeign(['linkedin_post_id']);
            $table->dropColumn(['linkedin_post_id', 'slide_index', 'slide_image_role']);
        });

        DB::statement(
            "ALTER TABLE image_generation_jobs MODIFY COLUMN `type` "
            . "ENUM('hero', 'inline') NOT NULL DEFAULT 'hero'"
        );
    }
};
