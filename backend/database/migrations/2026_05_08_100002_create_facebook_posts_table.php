<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A3 of cross-post Publer integration (May 8, 2026).
 *
 * Facebook Page is the most flexible cross-post target — accepts BOTH
 * text-format LinkedIn posts (long caption + link unfurl preview) AND
 * carousel-format (multi-photo album from LinkedIn carousel slides).
 *
 * Schema mirrors instagram_posts (Phase A, May 7) PLUS:
 *   - format ENUM('text','carousel') discriminator (mirrors LinkedInPost.format)
 *   - link_url VARCHAR(500) NULL — populated for text format (Publer/FB
 *     auto-unfurl), NULL for carousel
 *   - 4 publer_* columns from Phase A2 (sibling tables get them via ALTER;
 *     this one declares them at create time)
 *
 * App-level invariant: one live (deleted_at IS NULL) row per post_id,
 * enforced by FacebookDraftController::regenerate (Phase E). MySQL doesn't
 * support partial unique indexes — same precedent as linkedin_posts and
 * instagram_posts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_posts', function (Blueprint $table) {
            $table->id();

            // Source linkage
            $table->foreignId('linkedin_post_id')
                ->nullable()
                ->constrained('linkedin_posts')
                ->nullOnDelete()
                ->comment('FK to linkedin_posts; carousel slides read from linkedinPost->carousel_slides, text content read from linkedinPost->content');

            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete();

            // FSM (post-rename, matches Phase A2 IG/TikTok ENUM)
            $table->enum('status', [
                'pending_generation',
                'generating',
                'awaiting_review',
                'publishing',
                'published',
                'failed',
                'cancelled',
            ])->default('pending_generation');

            // Format discriminator — text vs carousel routing
            $table->enum('format', ['text', 'carousel'])
                ->comment('Mirrors LinkedInPost.format; drives caption-authoring strategy + payload shape');

            // Editable content
            $table->string('title', 150)->nullable()->comment('First-line hook / headline preview');
            $table->text('caption')->nullable()->comment('Up to FB max ~63k chars; sweet spot 250-1500');
            $table->json('hashtags')->nullable()->comment('FB hashtag culture is light — 3-8 typical');

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_url', 500)->nullable()->comment('Captured from Publer publish confirmation');
            $table->string('link_url', 500)->nullable()->comment('Blog URL for text format — Publer/FB auto-unfurls preview');

            // Publer integration (declared at create time, vs Phase A2 ALTER for siblings)
            $table->string('publer_post_id', 100)->nullable();
            $table->string('publer_job_id', 100)->nullable();
            $table->string('publer_status', 50)->nullable()
                ->comment('Last polled: working|complete|failed');
            $table->string('publer_account_id', 100)->nullable();

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

            // Indexes
            $table->index(['format', 'status', 'deleted_at'], 'idx_facebook_post_format');
            $table->index('scheduled_at', 'idx_facebook_post_scheduled');
            $table->index(['status', 'publer_job_id'], 'idx_facebook_publer_polling');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_posts');
    }
};
