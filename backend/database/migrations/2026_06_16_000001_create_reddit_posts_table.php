<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reddit cross-post sibling (2026-06-16) — carousel path (image gallery).
 *
 * Mirrors threads_posts but Reddit is ZERNIO-ONLY (no Publer columns) and adds
 * Reddit-specific fields: `subreddit` (snapshot at create, default u_alisadikinma)
 * + `flair_id` (forward-compat, unused for profile posts) + a 300-char title
 * (Reddit's title cap). Slide PNGs are read live from the owning LinkedInPost
 * carousel_slides — not stored here. Zernio columns are part of the create
 * since this table is born after the 2026-06-15 Zernio cutover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reddit_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('linkedin_post_id')
                ->nullable()
                ->constrained('linkedin_posts')
                ->nullOnDelete()
                ->comment('FK to linkedin_posts owning the slide PNGs');

            $table->foreignId('post_id')
                ->nullable()
                ->constrained('posts')
                ->nullOnDelete();

            $table->enum('status', [
                'pending_generation',
                'generating',
                'awaiting_review',
                'publishing',
                'published',
                'failed',
                'cancelled',
            ])->default('pending_generation');

            $table->string('format', 20)->default('carousel')
                ->comment("'carousel' image gallery — Reddit has no multi-video carousel");

            $table->string('title', 300)->nullable()
                ->comment('Reddit post title — required at publish, hard cap 300');
            $table->text('caption')->nullable()
                ->comment('Reddit body (markdown) — LinkedIn caption, cap 40k');
            $table->json('hashtags')->nullable();

            $table->string('subreddit', 100)->nullable()
                ->comment('Target subreddit, snapshot at create (default u_alisadikinma)');
            $table->string('flair_id', 100)->nullable()
                ->comment('Forward-compat — unused for profile posts');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_url', 500)->nullable();

            // Zernio transport (Reddit is Zernio-only — no Publer columns).
            $table->string('zernio_post_id', 100)->nullable()->unique();
            $table->string('zernio_request_id', 100)->nullable();

            $table->text('last_error')->nullable();
            $table->json('pipeline_state_log')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'deleted_at'], 'idx_reddit_post_status');
            $table->index('scheduled_at', 'idx_reddit_post_scheduled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reddit_posts');
    }
};
