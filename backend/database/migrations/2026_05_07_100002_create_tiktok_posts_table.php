<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-post pipeline (May 7, 2026) — TikTok drafts table.
 *
 * Same shape as instagram_posts (sibling table) with two divergences:
 *   1. Title hard-caps at VARCHAR(100) — TikTok native title field limit
 *   2. New column music_suggestion VARCHAR(255) — operator hint authored by
 *      /tiktok-gen plugin using vocabulary "[genre] | [mood] | [tempo]"
 *      (B2B tech default: "lofi hip-hop | focused | slow-medium").
 *
 * Slides NOT denormalized — read live via FK linkedin_post_id. App-level
 * invariant "one live draft per post_id" enforced by Phase E controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_posts', function (Blueprint $table) {
            $table->id();

            // Source linkage
            $table->foreignId('linkedin_post_id')
                ->nullable()
                ->constrained('linkedin_posts')
                ->nullOnDelete()
                ->comment('FK to linkedin_posts owning the slide PNGs');

            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete();

            // FSM (identical to instagram_posts)
            $table->enum('status', [
                'pending_generation',
                'generating',
                'awaiting_review',
                'awaiting_manual_publish',
                'published_externally',
                'failed',
                'cancelled',
            ])->default('pending_generation');

            // Editable content
            $table->string('title', 100)->nullable()->comment('TikTok native title field — hard 100-char cap');
            $table->text('caption')->nullable()->comment('First 150 chars critical (TikTok caption is search index 2025-2026)');
            $table->json('hashtags')->nullable()->comment('5-8 entries, mix of trending + niche');
            $table->string('music_suggestion', 255)->nullable()->comment('Operator hint, vocabulary [genre] | [mood] | [tempo]');

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_url', 500)->nullable()->comment('Pasted by operator after manual publish in TikTok app');

            // Diagnostics
            $table->text('last_error')->nullable();
            $table->json('pipeline_state_log')->nullable()->comment('Rotating audit log (HasStatusTransitions trait)');

            // Authorship
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'deleted_at'], 'idx_tiktok_post_status');
            $table->index('scheduled_at', 'idx_tiktok_post_scheduled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_posts');
    }
};
