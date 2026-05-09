<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publish-all cascade flag (May 10, 2026).
 *
 * When `auto_approve_cross_posts=true`, the per-platform Generation
 * services (FB / IG / TT / Threads) skip the operator-review gate and
 * directly transition AwaitingReview → Publishing → dispatch Publer.
 * Set by the new POST /admin/linkedin-drafts/:id/publish-all endpoint
 * which orchestrates LinkedIn live + targeted scanner trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->boolean('auto_approve_cross_posts')
                ->default(false)
                ->after('cancel_window_ends_at')
                ->comment('When true, FB/IG/TT/Threads jobs auto-publish via Publer without operator review');
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->dropColumn('auto_approve_cross_posts');
        });
    }
};
