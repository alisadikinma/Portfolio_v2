<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A4 of cross-post Publer integration (May 8, 2026) — schema finalize.
 *
 * Two changes:
 *
 *   1. DROP tiktok_posts.music_suggestion — Phase B (May 7) shipped this
 *      VARCHAR(255) column expecting operator to manually pick TikTok music
 *      via mobile-app workflow. Pivot to Publer transport revealed Publer
 *      API supports `networks.tiktok.details.auto_add_music: true` as a
 *      boolean toggle (Publer/TikTok server-side music selection). Since
 *      operator can't pre-pick a specific music_id via API anyway (TikTok
 *      doesn't expose CML to Publer at scope level), the suggestion-text
 *      column adds no value. Drop it. No production data exists.
 *
 *   2. ADD linkedin_posts.publer_media_ids JSON column — caches Publer
 *      media IDs per LinkedIn carousel slide after first /media/from-url
 *      upload. Without this cache, each cross-post platform (FB + IG +
 *      TikTok) would re-upload the same 9 PNGs to Publer = 27 upload
 *      calls. With cache: 9 uploads total, ~67% reduction. Cache is
 *      shape-of {slide_index: publer_media_id} JSON object.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiktok_posts', function (Blueprint $table) {
            $table->dropColumn('music_suggestion');
        });

        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->json('publer_media_ids')->nullable()
                ->after('linkedin_post_url')
                ->comment('Cached Publer media IDs from /media/from-url, shared across cross-post platforms');
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->dropColumn('publer_media_ids');
        });

        Schema::table('tiktok_posts', function (Blueprint $table) {
            $table->string('music_suggestion', 255)->nullable()->after('hashtags')
                ->comment('Operator hint, vocabulary [genre] | [mood] | [tempo]');
        });
    }
};
