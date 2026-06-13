<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * video_rebrand Phase C (#1 topic-aware hook) — durable sentinel so the
 * keyframe→Veo safety fallback survives PollRebrandAssets::recover() blanking
 * last_error. When a HOOK keyframe is refused by GeminiGen's named-public-figure
 * upload filter (PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD), this flag is set so the
 * re-dispatch authors a CREATOR-ONLY scene (drops the figure ref) — the job
 * degrades, never fails, on figure refusal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->boolean('figure_dropped')->default(false)->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->dropColumn('figure_dropped');
        });
    }
};
