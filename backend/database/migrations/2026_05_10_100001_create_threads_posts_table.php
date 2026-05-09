<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-post pipeline (May 10, 2026) — Threads drafts table.
 *
 * Mirrors instagram_posts schema (incl. May 8 publer_* columns) — Threads is
 * Meta-owned and shares Publer transport infra. Tier-2 platform per user
 * decision: text format reuses LinkedIn caption; carousel reuses /instagram-gen
 * plugin output (4:5 photos identical between IG and Threads carousel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('linkedin_post_id')
                ->nullable()
                ->constrained('linkedin_posts')
                ->nullOnDelete()
                ->comment('FK to linkedin_posts owning the slide PNGs');

            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete();

            $table->enum('status', [
                'pending_generation',
                'generating',
                'awaiting_review',
                'publishing',
                'published',
                'failed',
                'cancelled',
            ])->default('pending_generation');

            $table->string('format', 20)->default('text')
                ->comment("'text' | 'carousel' — mirrors LinkedIn format");

            $table->string('title', 200)->nullable()
                ->comment('First-line hook (Threads has no native title field, used for queue display)');
            $table->text('caption')->nullable()
                ->comment('Up to 500 chars sweet spot, hard cap 1000');
            $table->json('hashtags')->nullable()
                ->comment('Hard-capped at 3 (Threads minimal hashtag culture)');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_url', 500)->nullable();

            // Publer transport (May 8 pattern)
            $table->string('publer_post_id', 100)->nullable();
            $table->string('publer_job_id', 100)->nullable();
            $table->string('publer_status', 50)->nullable()
                ->comment('working | complete | failed — last polled');
            $table->string('publer_account_id', 100)->nullable();

            $table->text('last_error')->nullable();
            $table->json('pipeline_state_log')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'deleted_at'], 'idx_threads_post_status');
            $table->index('scheduled_at', 'idx_threads_post_scheduled');
            $table->index(['status', 'publer_job_id'], 'idx_threads_publer_polling');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads_posts');
    }
};
