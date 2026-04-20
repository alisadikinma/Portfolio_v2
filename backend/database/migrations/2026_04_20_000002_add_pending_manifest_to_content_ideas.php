<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            // Blocking manifest payload from /article-images Gate 2 when brand
            // refs or entity refs need manual upload. Cleared when all slots
            // resolved via upload/skip admin actions.
            $table->json('pending_manifest')->nullable()->after('progress_log');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn('pending_manifest');
        });
    }
};
