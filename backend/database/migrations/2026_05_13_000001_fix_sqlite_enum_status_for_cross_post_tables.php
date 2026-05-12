<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P4 SQLite fix (2026-05-13).
 *
 * On MySQL, migration 2026_05_08_100001 already ran
 * ALTER TABLE ... MODIFY COLUMN status ENUM(…new values…). But that
 * migration wraps the MODIFY COLUMN in `if (mysql)` — SQLite keeps the
 * original CREATE TABLE schema which only allows the old values
 * ('awaiting_manual_publish', 'published_externally') and lacks
 * the new ones ('publishing', 'published').
 *
 * In-memory SQLite used by unit tests therefore rejects DB::table()
 * UPDATE with status='published' even though it works in production.
 *
 * This migration runs ONLY on SQLite (in-memory test DB). It recreates
 * instagram_posts and tiktok_posts with the correct updated status
 * CHECK constraint. On MySQL it is a no-op.
 *
 * The threads_posts and facebook_posts tables were created after the
 * rename so they already have the correct values ('publishing',
 * 'published') in their original CREATE TABLE statements.
 */
return new class extends Migration
{
    // New values using single-quoted strings (SQLite CHECK schema format from Laravel)
    private const IG_NEW_VALUES = "'pending_generation', 'generating', 'awaiting_review', 'publishing', 'published', 'failed', 'cancelled'";
    private const TT_NEW_VALUES = "'pending_generation', 'generating', 'awaiting_review', 'publishing', 'published', 'failed', 'cancelled'";

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        // Recreate instagram_posts with updated CHECK constraint.
        $this->patchStatusCheck('instagram_posts', self::IG_NEW_VALUES);
        $this->patchStatusCheck('tiktok_posts', self::TT_NEW_VALUES);
    }

    public function down(): void
    {
        // No-op — RefreshDatabase re-runs all migrations from scratch on each test.
    }

    /**
     * Patch the status CHECK constraint on a SQLite table.
     *
     * Uses the SQLite create-tmp → copy → drop-old → rename-tmp pattern
     * since SQLite does not support ALTER TABLE ... MODIFY COLUMN.
     *
     * @param  string  $table      Table name (e.g. 'instagram_posts')
     * @param  string  $newValues  Comma-separated double-quoted values
     *                             e.g. '"pending","published","failed"'
     */
    private function patchStatusCheck(string $table, string $newValues): void
    {
        $pdo = DB::connection()->getPdo();

        // Read current CREATE TABLE SQL from sqlite_master.
        $row = $pdo->query(
            "SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'"
        )->fetch(\PDO::FETCH_ASSOC);

        if (! $row) {
            return; // Table not yet created — nothing to fix.
        }

        $origSql = $row['sql'];

        // Replace the status CHECK value list.
        // Laravel generates (for enum columns on SQLite):
        //   "status" varchar check ("status" in ('val1', 'val2', ...)) not null default 'pending_generation'
        // The exact format may vary — we match: "status" varchar... check ("status" in (...))
        $newSql = preg_replace(
            '/"status"\s+varchar(?:\(\d+\))?\s+check\s*\(\s*"status"\s+in\s*\([^)]+\)\s*\)/i',
            '"status" varchar check ("status" in (' . $newValues . '))',
            $origSql
        );

        if ($newSql === null || $newSql === $origSql) {
            // Pattern didn't match — table may already be correct or uses
            // unexpected schema format. Skip rather than corrupt.
            return;
        }

        $tmpTable = $table . '_p4tmp';

        // Create temporary table with the patched schema.
        $tmpSql = preg_replace(
            '/CREATE TABLE "?' . preg_quote($table, '/') . '"?/i',
            "CREATE TABLE \"{$tmpTable}\"",
            $newSql
        );

        $pdo->exec($tmpSql);

        // Copy all rows.
        $columns = $this->getColumnNames($table, $pdo);
        $colList = implode(', ', array_map(fn ($c) => '"' . $c . '"', $columns));

        $pdo->exec("INSERT INTO \"{$tmpTable}\" ({$colList}) SELECT {$colList} FROM \"{$table}\"");

        // Drop original and rename tmp.
        $pdo->exec("DROP TABLE \"{$table}\"");
        $pdo->exec("ALTER TABLE \"{$tmpTable}\" RENAME TO \"{$table}\"");
    }

    /** @return string[] Column names from PRAGMA table_info */
    private function getColumnNames(string $table, \PDO $pdo): array
    {
        $stmt = $pdo->query("PRAGMA table_info(\"{$table}\")");
        $cols = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $cols[] = $row['name'];
        }
        return $cols;
    }
};
