<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * social-short-form-writer plugin v0.3.0 introduced an OPTIONAL
 * `text_only_caption` field on /instagram-gen output — a Bahasa Indonesia
 * condensed FB-text variant for cross-post reuse (≤1000 chars, body URL OK,
 * 300-700 char sweet spot).
 *
 * `FacebookGenerationService::generateText` now reads this column first
 * and falls back to truncated LinkedIn content only when absent. Replaces
 * the prior "always reuse LinkedIn EN content" path that would leak English
 * into the now-Indonesian FB strategy.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->string('text_only_caption', 1000)
                ->nullable()
                ->after('caption')
                ->comment('Bahasa Indonesia condensed FB-text variant for FB cross-post reuse (plugin v0.3.0+). Body URL allowed.');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropColumn('text_only_caption');
        });
    }
};
