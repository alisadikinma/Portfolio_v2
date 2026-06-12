<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GROK hook video — bounded retry bookkeeping (June 12, 2026, Phase E).
 *
 * The crosspost:poll-hook-videos reaper re-dispatches a FAILED hook video up to
 * a small cap (each GROK clip costs ~5 credits, so retries must be bounded).
 * Distinct from auto_retry_count (that one bounds caption re-generation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instagram_posts')) {
            return;
        }
        Schema::table('instagram_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('instagram_posts', 'hook_video_retry_count')) {
                $table->unsignedTinyInteger('hook_video_retry_count')
                    ->default(0)
                    ->after('hook_video_error')
                    ->comment('Bounded GROK hook-video re-dispatch count (reaper cap)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('instagram_posts')) {
            return;
        }
        Schema::table('instagram_posts', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_posts', 'hook_video_retry_count')) {
                $table->dropColumn('hook_video_retry_count');
            }
        });
    }
};
