<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds streaming progress tracking to linkedin_posts, mirroring the
 * ContentIdea pattern (progress_percentage + current_step + progress_log)
 * so the admin LinkedInDraftDetail page can render a live progress modal
 * with phase cards + sub-step pills + terminal-style log viewer.
 *
 * Skipped vs ContentIdea: process_pid. LinkedIn pipeline runs through the
 * queue worker (systemd unit), not via inline SSH process inside an HTTP
 * request — liveness is observable via job_status / queue health rather
 * than PID. The reaper crons (linkedin:reap-stuck +
 * linkedin:reap-stuck-carousel-images) already handle the "process died
 * silently" failure mode upstream of this surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percentage')->default(0)->after('status');
            $table->string('current_step', 50)->nullable()->after('progress_percentage');
            $table->json('progress_log')->nullable()->after('current_step');
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->dropColumn(['progress_percentage', 'current_step', 'progress_log']);
        });
    }
};
