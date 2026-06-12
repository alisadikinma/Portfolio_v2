<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bounded auto-retry bookkeeping for cross-post caption siblings
 * (June 12, 2026 — Req 3 caption reaper). Mirrors the linkedin_posts pattern
 * from 2026_05_07_000001:
 *   - auto_retry_count          TINYINT UNSIGNED DEFAULT 0
 *   - last_classified_error_class VARCHAR nullable
 *
 * The new `crosspost:reap` command uses auto_retry_count to cap how many
 * times a FAILED Instagram/TikTok/Threads caption is re-dispatched before
 * giving up (so a permanently-broken caption doesn't re-queue forever).
 */
return new class extends Migration
{
    private const TABLES = ['instagram_posts', 'tiktok_posts', 'threads_posts'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'auto_retry_count')) {
                    $table->unsignedTinyInteger('auto_retry_count')
                        ->default(0)
                        ->after('last_error');
                }
                if (! Schema::hasColumn($tableName, 'last_classified_error_class')) {
                    $table->string('last_classified_error_class', 64)
                        ->nullable()
                        ->after('auto_retry_count');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $cols = array_values(array_filter(
                    ['auto_retry_count', 'last_classified_error_class'],
                    fn ($c) => Schema::hasColumn($tableName, $c)
                ));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
