<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branded URL shortener for cross-post pipeline (May 10, 2026).
 *
 * Replaces full blog URLs (typically 100-130 chars for SEO-rich slugs) with
 * compact `https://alisadikinma.com/r/{code}` (33-35 chars). Saves ~70-95
 * chars per cross-post — meaningful for TikTok caption (200-500 char sweet
 * spot) and Threads preview-cut (140 char hook).
 *
 * Per-platform attribution baked in via `source_platform` column AND UTM
 * parameters appended to `target_url` before storage. Short URL stays clean
 * (`/r/abc1234`), redirect carries UTM to GA dashboard automatically.
 *
 * Idempotent: same (post_id, source_platform) tuple returns the existing
 * code on re-shorten — never creates duplicate rows for the same platform.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique()->comment('base62 code, 6-8 chars typical');
            $table->string('target_url', 2048)->comment('Full URL with UTM params already appended');
            $table->foreignId('post_id')
                ->nullable()
                ->constrained('posts')
                ->nullOnDelete()
                ->comment('Reverse lookup for blog post short links — null for ad-hoc URLs');
            $table->string('source_platform', 32)
                ->nullable()
                ->comment('linkedin / instagram / tiktok / threads / facebook / null=other');
            $table->unsignedBigInteger('hits')->default(0)->comment('Total redirects served');
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'source_platform'], 'idx_short_links_post_platform');
            $table->index('source_platform', 'idx_short_links_platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
