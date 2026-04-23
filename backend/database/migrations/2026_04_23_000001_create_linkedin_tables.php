<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LinkedIn admin UI schema — matches plugin Addendum 3 (2026-04-23 session 3)
 * at D:\Projects\claude-plugin\linkedin-post-writer\docs\plans\2026-04-23-plugin-architecture-full-auto.md §13.5
 *
 * Two tables:
 *   - linkedin_accounts: OAuth-connected LinkedIn accounts (v1.0 = 1 row, Ali's personal)
 *   - linkedin_posts: blog→LinkedIn conversion jobs with FSM status + validation + scheduling
 *
 * Plugin generates content (text/carousel JSON) via SSH→Claude CLI and posts to
 * backend /automation/linkedin/* callbacks. This admin UI owns everything else:
 * OAuth, publish, schedule, cancel, regenerate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('person_urn')->unique()->comment('urn:li:person:... from /v2/me');
            $table->string('display_name');
            $table->text('access_token');
            $table->text('refresh_token');
            // Expiry timestamps are always populated by LinkedInOAuthService::upsertAccount()
            // from the token-exchange response. Nullable because MySQL strict mode rejects
            // timestamp NOT NULL without a default, and we don't want CURRENT_TIMESTAMP
            // semantics (would mask missing expiry data).
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('linkedin_posts', function (Blueprint $table) {
            $table->id();

            // Blog source
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();

            // LinkedIn account used for publishing
            $table->foreignId('linkedin_account_id')
                ->nullable()
                ->constrained('linkedin_accounts')
                ->nullOnDelete();

            // Content
            $table->enum('format', ['text', 'carousel']);
            $table->text('content');
            $table->string('link_comment', 500)->nullable();
            $table->json('hashtags');
            $table->json('carousel_slides')->nullable();
            $table->string('carousel_pdf_path')->nullable();

            // Validation
            $table->unsignedTinyInteger('depth_score')->nullable()->comment('0-100 LinkedIn Depth Score');
            $table->json('validation_log')->nullable()->comment('{failures[], suggestions[]}');

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('cancel_window_ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('linkedin_post_urn')->nullable()->comment('urn:li:share:... returned by LinkedIn API');
            $table->string('linkedin_asset_urn')->nullable()->comment('Document asset URN for carousels');
            $table->string('linkedin_post_url')->nullable();

            // Status FSM (pipeline_state_log reuses the same column name as ContentIdea
            // so the generic HasStatusTransitions trait appends audit entries here)
            $table->enum('status', [
                'pending_generation', 'generating', 'validating',
                'awaiting_publish', 'published', 'cancelled',
                'failed', 'manual_review',
            ])->default('pending_generation');
            $table->json('pipeline_state_log')->nullable()->comment('Rotating 20-entry audit log');
            $table->text('last_error')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index(['post_id', 'deleted_at']);
        });

        // NOTE: uniqueness of live-row-per-post_id is enforced at the
        // application layer (controller/service) rather than DB constraint
        // because MySQL does not support partial unique indexes with WHERE
        // clauses. See LinkedInDraftController::regenerate for the
        // soft-delete + create flow that respects this invariant.
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_posts');
        Schema::dropIfExists('linkedin_accounts');
    }
};
