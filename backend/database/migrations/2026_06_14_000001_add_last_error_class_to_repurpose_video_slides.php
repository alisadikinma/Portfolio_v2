<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part A2 — persist the classified GeminiGen error so PollRebrandAssets::recover
 * can degrade the retry prompt AFTER it blanks last_error. Nullable (legacy rows +
 * healthy slides carry NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->string('last_error_class', 32)->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->dropColumn('last_error_class');
        });
    }
};
