<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * video_rebrand Phase E — bounded retry counter for hook/CTA asset generation.
 * PollRebrandAssets' recovery pass re-dispatches GenerateRebrandAssets when a
 * bookend keyframe/Veo clip hard-fails, capped by this counter so a persistently
 * failing GeminiGen can't burn unbounded credits.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurpose_jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('asset_retry_count')->default(0)->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('repurpose_jobs', function (Blueprint $table) {
            $table->dropColumn('asset_retry_count');
        });
    }
};
