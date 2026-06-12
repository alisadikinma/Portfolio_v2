<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-slide tracking for the video_rebrand repurpose mode — see
 * docs/plans/2026-06-12-ig-video-carousel-rebrand.md (Phase A).
 *
 * One row per carousel slide. `role`:
 *   - hook : Veo-generated opening clip (veo_* populated, no source video)
 *   - tool : a source video slide (yt-dlp download), re-skinned with brand chrome
 *   - cta  : Veo-generated closing clip
 * `composited_path` is the final 1080×1350 4:5 mp4 the operator downloads.
 * Strings (not native enum) for MySQL/sqlite portability — mirrors repurpose_jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repurpose_video_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repurpose_job_id')->constrained('repurpose_jobs')->cascadeOnDelete();
            $table->unsignedSmallInteger('slide_index');
            $table->string('role', 16)->default('tool'); // hook | tool | cta

            // Source (tool slides) — downloaded video + a poster frame for vision.
            $table->string('source_video_path')->nullable();
            $table->string('poster_path')->nullable();

            // Vision-extracted header content + center-band crop region (per slide).
            $table->string('header_title')->nullable();
            $table->text('header_desc')->nullable();
            $table->unsignedInteger('crop_y')->nullable();
            $table->unsignedInteger('crop_h')->nullable();

            // Face-gen keyframe (hook/cta slides) — GeminiGen image-gen with the
            // creator face ref @ 9:16, poll-based. Feeds the Veo start-frame.
            $table->string('keyframe_job_uuid')->nullable();
            $table->string('keyframe_status', 16)->nullable(); // pending | generating | done | failed
            $table->text('keyframe_url')->nullable();

            // Veo generation (hook/cta slides) — poll-based, geminigen webhook never fires.
            $table->string('veo_job_uuid')->nullable();
            $table->string('veo_status', 16)->nullable(); // pending | generating | done | failed
            $table->text('veo_url')->nullable();

            // ffmpeg composite output (all slides) + per-slide status.
            $table->string('composited_path')->nullable();
            $table->string('composited_status', 16)->nullable(); // pending | compositing | done | failed
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['repurpose_job_id', 'slide_index'], 'idx_repurpose_video_slides_job_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repurpose_video_slides');
    }
};
