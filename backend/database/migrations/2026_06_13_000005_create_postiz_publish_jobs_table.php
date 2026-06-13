<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postiz cross-post publish jobs — decoupled side-table for the local-node
 * pull model. See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase A).
 *
 * One live job per (platform, sibling_post_id). The VPS marks a job
 * `ready_to_publish` at slot tick; a local Node poller atomically CLAIMS it
 * under a lease, publishes via Postiz `/public/v1`, and callbacks the result.
 *
 * Async-aware state machine:
 *   ready_to_publish → claimed (local leased it)
 *                    → accepted (poller got OK from POST /posts; Temporal owns
 *                      it now — watchdog HANDS OFF, no Publer fallback past here)
 *                    → published (state=PUBLISHED, releaseURL stored)
 *                    OR failed (state=ERROR / enqueue failed)
 *
 * Strings (not native enum) for MySQL/sqlite portability — mirrors repurpose_jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postiz_publish_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32)->index();        // instagram|tiktok|threads|facebook|linkedin|medium
            $table->unsignedBigInteger('sibling_post_id');
            $table->string('sibling_type');                 // FQCN of the sibling model (IG/TT/TH/FB/Post)
            $table->string('status', 24)->default('ready_to_publish')->index();

            // Job-lease (anti-double-publish core mechanism).
            $table->string('claimed_by')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('publish_lease_until')->nullable()->index();

            $table->timestamp('slot_due_at');               // when it became due — watchdog deadline anchor

            // Postiz correlation + result.
            $table->string('postiz_integration_id')->nullable(); // resolved at create from postiz_channels
            $table->string('postiz_post_id')->nullable();        // Postiz Post.id (async hand-off guard)
            $table->string('permalink')->nullable();             // Postiz releaseURL

            $table->text('last_error')->nullable();
            $table->timestamp('fallback_fired_at')->nullable();  // Publer fallback fired once-guard

            $table->timestamps();

            $table->unique(['platform', 'sibling_post_id'], 'uniq_postiz_job_platform_sibling');
            $table->index(['status', 'slot_due_at'], 'idx_postiz_job_status_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postiz_publish_jobs');
    }
};
