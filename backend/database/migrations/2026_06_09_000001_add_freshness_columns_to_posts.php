<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freshness-loop columns for the GEO publish-and-forget fix (Neil Patel #1).
 *   - content_reviewed_at: when the operator last marked the post reviewed.
 *     Becomes the freshness anchor (COALESCE(content_reviewed_at, published_at)).
 *   - stale_notified_at: when the weekly digest last alerted on this post,
 *     used to suppress re-alert spam (30-day window).
 * Both nullable, no backfill — legacy posts anchor on published_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('content_reviewed_at')->nullable()->after('published_at');
            $table->timestamp('stale_notified_at')->nullable()->after('content_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['content_reviewed_at', 'stale_notified_at']);
        });
    }
};
