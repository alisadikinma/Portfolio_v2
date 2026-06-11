<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite test-compat ONLY (MySQL no-op).
 *
 * Production `posts` has NO `title` / `content` columns — article text lives in
 * `post_translations` (see root CLAUDE.md "Critical Schema Notes"). But the
 * create-posts migration (2025_10_02_060233) declares `title` + `content` as
 * NOT NULL, so the CI sqlite schema keeps them required. ContentPublishService::
 * publish() correctly omits them (matching prod), which makes its real Post
 * insert violate the sqlite NOT NULL constraint and breaks any test that drives
 * the real publish path (e.g. ApproveAndPublishQueuesLinkedInScanTest).
 *
 * Relax both columns to nullable on sqlite so the test schema tolerates what
 * prod already does. Production MySQL is untouched (driver guard) — it has no
 * such columns to relax.
 *
 * @see docs/plans/2026-06-11-repurpose-telegram-progress-admin-panel.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return; // prod MySQL has no posts.title/content columns
        }
        Schema::table('posts', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->longText('content')->nullable()->change();
            // publish() inserts seo_score explicitly NULL; prod is NOT NULL
            // DEFAULT 0 (MySQL non-strict coerces), sqlite rejects. Relax it.
            $table->integer('seo_score')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No-op — test-compat shim, nothing to reverse meaningfully.
    }
};
