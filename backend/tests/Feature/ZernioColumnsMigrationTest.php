<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase B — zernio_post_id + zernio_request_id columns on the three cross-post
 * sibling tables (mirror of the existing publer_post_id column).
 */
class ZernioColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public static function siblingTables(): array
    {
        return [
            ['instagram_posts'],
            ['tiktok_posts'],
            ['threads_posts'],
            ['facebook_posts'], // 2026-06-16 — Publer→Zernio cutover
            ['reddit_posts'],   // 2026-06-16 — 4th Zernio platform
        ];
    }

    /**
     * @dataProvider siblingTables
     */
    public function test_sibling_table_has_zernio_columns(string $table): void
    {
        $this->assertTrue(
            Schema::hasColumn($table, 'zernio_post_id'),
            "{$table} is missing zernio_post_id"
        );
        $this->assertTrue(
            Schema::hasColumn($table, 'zernio_request_id'),
            "{$table} is missing zernio_request_id"
        );
    }
}
