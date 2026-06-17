<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facebook Publer→Zernio cutover (2026-06-16).
 *
 * Adds the two Zernio transport columns to facebook_posts (mirror of the
 * 2026-06-15 add-zernio migration for instagram/tiktok/threads). Facebook was
 * Publer-only before this; the publer_* columns stay as dead weight for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('facebook_posts', 'zernio_post_id')) {
                $table->string('zernio_post_id', 100)->nullable()->unique()->after('publer_post_id');
            }
            if (! Schema::hasColumn('facebook_posts', 'zernio_request_id')) {
                $table->string('zernio_request_id', 100)->nullable()->after('zernio_post_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            foreach (['zernio_post_id', 'zernio_request_id'] as $col) {
                if (Schema::hasColumn('facebook_posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
