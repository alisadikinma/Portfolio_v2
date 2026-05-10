<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `link_comment` column to instagram_posts, tiktok_posts, threads_posts —
 * mirror of linkedin_posts.link_comment introduced in 2026_04_23_000001.
 *
 * Stores the formatted "Full article: {short_url}" string at generate-time
 * via persistAndRoute. Read at publish-time:
 *   - LinkedIn: PostLinkedInFirstComment job (already wired)
 *   - Instagram: PublishViaPubler.comments[] field (Phase H+ Publer real impl)
 *   - Threads: PublishViaPubler.comments[] field (Phase H+)
 *   - TikTok: NOT used in first-comment (Publer API limitation) — caption body
 *     carries URL via plugin input. Column populated for visibility / parity only.
 *
 * Width 500 chars matches linkedin_posts.link_comment.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->string('link_comment', 500)
                ->nullable()
                ->after('text_only_caption')
                ->comment('Branded short URL formatted "Full article: {short_url}" — read by Publer comments[] field at publish.');
        });

        Schema::table('tiktok_posts', function (Blueprint $table) {
            $table->string('link_comment', 500)
                ->nullable()
                ->after('caption')
                ->comment('Branded short URL parity with other platforms; TikTok caption body carries URL since Publer API does not support TT first-comment.');
        });

        Schema::table('threads_posts', function (Blueprint $table) {
            $table->string('link_comment', 500)
                ->nullable()
                ->after('caption')
                ->comment('Branded short URL formatted "Full article: {short_url}" — read by Publer comments[] field at publish.');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropColumn('link_comment');
        });
        Schema::table('tiktok_posts', function (Blueprint $table) {
            $table->dropColumn('link_comment');
        });
        Schema::table('threads_posts', function (Blueprint $table) {
            $table->dropColumn('link_comment');
        });
    }
};
