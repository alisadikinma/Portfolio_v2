<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite test-compat ONLY (MySQL no-op).
 *
 * The status-enum-widening migrations (2026_04_12_100000, 2026_04_17_110000,
 * 2026_04_20_000003) use raw `ALTER TABLE ... MODIFY COLUMN ... ENUM(...)`,
 * which is MySQL-only. On sqlite they no-op, leaving content_ideas.status stuck
 * on the original 6-value enum from the create migration (no article_ready,
 * generating_images, images_ready, awaiting_manual_upload, failed). That CHECK
 * constraint rejects legitimate inserts/transitions in the CI sqlite suite
 * (e.g. FinalizeRepurpose creating an article_ready idea).
 *
 * Relax the column to a plain string on sqlite so the full application status
 * set is insertable in tests. Production MySQL is untouched (driver guard) —
 * it keeps its authoritative enum from the earlier MODIFY migrations.
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md (Phase F)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return; // prod MySQL keeps its enum
        }
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // No-op — test-compat shim, nothing to reverse meaningfully.
    }
};
