<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * video_full (4th repurpose mode) — VPS-side schema. The MacBook-local worker
 * runs the heavy pipeline; the VPS tracks the worker lifecycle (new columns on
 * repurpose_jobs) and the per-segment timeline (video_full_segments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurpose_jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('worker_progress')->default(0)->after('asset_retry_count');
            $table->string('worker_step', 32)->nullable()->after('worker_progress');
            $table->timestamp('worker_claimed_at')->nullable()->after('worker_step');
            $table->timestamp('worker_heartbeat_at')->nullable()->after('worker_claimed_at');
            $table->string('final_video_url')->nullable()->after('worker_heartbeat_at');
        });

        Schema::create('video_full_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repurpose_job_id')->constrained('repurpose_jobs')->cascadeOnDelete();
            $table->unsignedSmallInteger('segment_index');
            $table->string('type', 16);                 // to_camera | b_roll | split_screen
            $table->float('start_sec')->default(0);
            $table->float('end_sec')->default(0);
            $table->text('source_text_en')->nullable();
            $table->text('text_id')->nullable();
            $table->string('strategy', 32)->nullable(); // veo_talking | reuse_source | remotion_recreate | vstack_broll_top_ali_bottom | drop
            $table->string('status', 16)->default('pending'); // pending|processing|done|failed|dropped
            $table->string('provider', 8)->nullable();  // veo | grok (talking segments)
            $table->string('preview_url')->nullable();
            $table->string('clip_path')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['repurpose_job_id', 'segment_index'], 'idx_vfs_job_order');
            $table->index(['repurpose_job_id', 'status'], 'idx_vfs_job_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_full_segments');
        Schema::table('repurpose_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'worker_progress', 'worker_step', 'worker_claimed_at',
                'worker_heartbeat_at', 'final_video_url',
            ]);
        });
    }
};
