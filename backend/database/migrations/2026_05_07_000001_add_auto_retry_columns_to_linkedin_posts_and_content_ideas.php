<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B of docs/plans/2026-05-07-pipeline-error-classifier-and-bounded-retry.md
 *
 * Adds two columns to both `linkedin_posts` and `content_ideas`:
 *   - auto_retry_count TINYINT UNSIGNED DEFAULT 0
 *   - last_classified_error_class VARCHAR(32) NULL
 *
 * Used by RetryFailedLinkedInPosts + RetryFailedContentIdeas crons (Phase
 * C/D) to bound retries at 2 per record + record which `PipelineErrorClass`
 * the last failure was classified as. Visible in admin UI as "auto-retried
 * Nx" chip + tooltip showing the class.
 *
 * Both columns nullable/defaulted — non-breaking for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->unsignedTinyInteger('auto_retry_count')
                ->default(0)
                ->after('last_error');
            $table->string('last_classified_error_class', 32)
                ->nullable()
                ->after('auto_retry_count');
        });

        Schema::table('content_ideas', function (Blueprint $table) {
            $table->unsignedTinyInteger('auto_retry_count')
                ->default(0);
            $table->string('last_classified_error_class', 32)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->dropColumn(['auto_retry_count', 'last_classified_error_class']);
        });

        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn(['auto_retry_count', 'last_classified_error_class']);
        });
    }
};
