<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B (Zernio integration) — add Zernio publish tracking columns to the
 * three cross-post sibling tables, mirroring the existing `publer_post_id`.
 *
 *   zernio_post_id    — Zernio post `_id` returned by createPost (or the
 *                       existingPostId from a 409 content-dedup hit).
 *   zernio_request_id — stable client-generated UUID sent as `x-request-id`
 *                       so a queue retry returns the original post (HTTP 200)
 *                       instead of double-posting. Persisted on first attempt.
 */
return new class extends Migration
{
    private array $tables = ['instagram_posts', 'tiktok_posts', 'threads_posts'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'zernio_post_id')) {
                    $blueprint->string('zernio_post_id')->nullable()->after('publer_post_id');
                    $blueprint->index('zernio_post_id', "idx_{$table}_zernio_post");
                }
                if (! Schema::hasColumn($table, 'zernio_request_id')) {
                    $blueprint->string('zernio_request_id')->nullable()->after('zernio_post_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'zernio_post_id')) {
                    $blueprint->dropIndex("idx_{$table}_zernio_post");
                    $blueprint->dropColumn('zernio_post_id');
                }
                if (Schema::hasColumn($table, 'zernio_request_id')) {
                    $blueprint->dropColumn('zernio_request_id');
                }
            });
        }
    }
};
