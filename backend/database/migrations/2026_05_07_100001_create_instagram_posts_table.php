<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-post pipeline (May 7, 2026) — Instagram drafts table.
 *
 * Mirrors linkedin_posts FSM pattern but lighter: no auto-publish state, no
 * cancel window timer, no per-platform OAuth (manual mobile-app publish).
 *
 * Slides are NOT denormalized here — read live via FK linkedin_post_id →
 * linkedin_posts.carousel_slides[].image_url. Same slide PNGs reused across
 * LinkedIn + Instagram + TikTok.
 *
 * App-level invariant: one live (deleted_at IS NULL) draft per post_id, enforced
 * by InstagramDraftController::regenerate (Phase E). MySQL doesn't support
 * partial unique indexes filtered on deleted_at — same precedent as linkedin_posts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_posts', function (Blueprint $table) {
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

            // FSM
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
            $table->string('title', 150)->nullable()->comment('First-line hook (IG has no native title field)');
            $table->text('caption')->nullable()->comment('Up to 2200 chars, sweet spot 600-1500');
            $table->json('hashtags')->nullable()->comment('Hard-capped at 5 (IG Dec 2025 platform enforcement)');

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_url', 500)->nullable()->comment('Pasted by operator after manual publish in IG app');

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

            $table->index(['status', 'deleted_at'], 'idx_instagram_post_status');
            $table->index('scheduled_at', 'idx_instagram_post_scheduled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_posts');
    }
};
