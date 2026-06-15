<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-platform Zernio publish state for video_rebrand repurpose jobs.
 *
 * JSON keyed by platform: { "instagram": {...}, "threads": {...} } where each
 * entry holds { status, post_id, request_id, url, scheduled_for, error, updated_at }.
 * FSM-neutral — the job stays `drafted`; publishing a video carousel to Zernio is
 * a separate concern (mirrors how composited_status lives outside the FSM).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurpose_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('repurpose_jobs', 'zernio_publish')) {
                $table->json('zernio_publish')->nullable()->after('pipeline_state_log');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repurpose_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('repurpose_jobs', 'zernio_publish')) {
                $table->dropColumn('zernio_publish');
            }
        });
    }
};
