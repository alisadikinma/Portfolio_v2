<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IG repurpose pipeline job state — see
 * docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md (Phase 0).
 *
 * One row per Instagram URL the operator sends via Telegram. FSM column
 * `status` is governed by App\Enums\RepurposeJobStatus + HasStatusTransitions.
 * Output anchors differ by `mode` (D1/D8/D9):
 *   - mode='blog'     → content_idea_id (enters Content Engine pipeline)
 *   - mode='carousel' → anchor_post_id + linkedin_post_id (direct carousel draft)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repurpose_jobs', function (Blueprint $table) {
            $table->id();
            $table->text('source_url');
            $table->text('angle')->nullable();
            // 'blog' | 'carousel' — set when operator taps a Telegram button (D9).
            $table->string('mode', 16)->nullable();
            $table->string('status', 32)->default('received')->index();
            $table->string('slides_path')->nullable();
            $table->json('extracted')->nullable();
            $table->json('research')->nullable();
            $table->json('rewritten')->nullable();
            $table->foreignId('content_idea_id')->nullable()->constrained('content_ideas')->nullOnDelete();
            $table->foreignId('linkedin_post_id')->nullable()->constrained('linkedin_posts')->nullOnDelete();
            $table->foreignId('anchor_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->json('pipeline_state_log')->nullable();
            $table->string('chat_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repurpose_jobs');
    }
};
