<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A2 of cross-post Publer integration (May 8, 2026).
 *
 * Two changes per existing table (instagram_posts + tiktok_posts):
 *
 *   1. ENUM rename (BREAKING — coordinate with Phase B2 PHP enum case rename
 *      in the same commit):
 *        awaiting_manual_publish  →  publishing
 *        published_externally     →  published
 *      Rationale: original FSM design assumed manual operator publish via
 *      mobile app. Pivot to Publer transport means backend POSTs to Publer
 *      with state='scheduled', then polls /job_status. The new state names
 *      reflect the actual lifecycle (publishing = in-flight at Publer,
 *      published = Publer confirmed).
 *
 *   2. Additive Publer integration columns:
 *        publer_post_id      VARCHAR(100) NULL — Publer post ID after creation
 *        publer_job_id       VARCHAR(100) NULL — Publer async job ID for polling
 *        publer_status       VARCHAR(50)  NULL — last polled: working|complete|failed
 *        publer_account_id   VARCHAR(100) NULL — which Publer connected account
 *      Plus index on (status, publer_job_id) for the PollPublerJobs cron.
 *
 * No production rows exist on these tables yet (Phase A shipped May 7, 21h
 * ago, no admin UI yet). The defensive UPDATE statements are belt-and-
 * suspenders for any test fixtures.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            // Step 1a: defensive UPDATE for any rows using old ENUM values
            // (none expected in production; covers test fixtures).
            DB::statement("UPDATE instagram_posts SET status='publishing' WHERE status='awaiting_manual_publish'");
            DB::statement("UPDATE instagram_posts SET status='published' WHERE status='published_externally'");
            DB::statement("UPDATE tiktok_posts SET status='publishing' WHERE status='awaiting_manual_publish'");
            DB::statement("UPDATE tiktok_posts SET status='published' WHERE status='published_externally'");

            // Step 1b: rename ENUM values via MODIFY COLUMN. MySQL ENUMs
            // need raw SQL (Laravel's Schema builder can't redefine enums
            // cleanly without doctrine/dbal).
            DB::statement(
                "ALTER TABLE instagram_posts MODIFY COLUMN status "
                . "ENUM('pending_generation','generating','awaiting_review',"
                . "'publishing','published','failed','cancelled') "
                . "NOT NULL DEFAULT 'pending_generation'"
            );
            DB::statement(
                "ALTER TABLE tiktok_posts MODIFY COLUMN status "
                . "ENUM('pending_generation','generating','awaiting_review',"
                . "'publishing','published','failed','cancelled') "
                . "NOT NULL DEFAULT 'pending_generation'"
            );
        }

        // Step 2: additive Publer columns + index for instagram_posts.
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->string('publer_post_id', 100)->nullable()->after('external_url');
            $table->string('publer_job_id', 100)->nullable()->after('publer_post_id');
            $table->string('publer_status', 50)->nullable()->after('publer_job_id')
                ->comment('Last polled: working|complete|failed');
            $table->string('publer_account_id', 100)->nullable()->after('publer_status');

            $table->index(['status', 'publer_job_id'], 'idx_instagram_publer_polling');
        });

        // Step 3: same for tiktok_posts.
        Schema::table('tiktok_posts', function (Blueprint $table) {
            $table->string('publer_post_id', 100)->nullable()->after('external_url');
            $table->string('publer_job_id', 100)->nullable()->after('publer_post_id');
            $table->string('publer_status', 50)->nullable()->after('publer_job_id')
                ->comment('Last polled: working|complete|failed');
            $table->string('publer_account_id', 100)->nullable()->after('publer_status');

            $table->index(['status', 'publer_job_id'], 'idx_tiktok_publer_polling');
        });
    }

    public function down(): void
    {
        // Reverse publer columns + indexes first (Schema::table is safe).
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropIndex('idx_instagram_publer_polling');
            $table->dropColumn(['publer_post_id', 'publer_job_id', 'publer_status', 'publer_account_id']);
        });

        Schema::table('tiktok_posts', function (Blueprint $table) {
            $table->dropIndex('idx_tiktok_publer_polling');
            $table->dropColumn(['publer_post_id', 'publer_job_id', 'publer_status', 'publer_account_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            // Restore old ENUM values + UPDATE rows back.
            DB::statement(
                "ALTER TABLE instagram_posts MODIFY COLUMN status "
                . "ENUM('pending_generation','generating','awaiting_review',"
                . "'awaiting_manual_publish','published_externally','failed','cancelled') "
                . "NOT NULL DEFAULT 'pending_generation'"
            );
            DB::statement(
                "ALTER TABLE tiktok_posts MODIFY COLUMN status "
                . "ENUM('pending_generation','generating','awaiting_review',"
                . "'awaiting_manual_publish','published_externally','failed','cancelled') "
                . "NOT NULL DEFAULT 'pending_generation'"
            );

            DB::statement("UPDATE instagram_posts SET status='awaiting_manual_publish' WHERE status='publishing'");
            DB::statement("UPDATE instagram_posts SET status='published_externally' WHERE status='published'");
            DB::statement("UPDATE tiktok_posts SET status='awaiting_manual_publish' WHERE status='publishing'");
            DB::statement("UPDATE tiktok_posts SET status='published_externally' WHERE status='published'");
        }
    }
};
