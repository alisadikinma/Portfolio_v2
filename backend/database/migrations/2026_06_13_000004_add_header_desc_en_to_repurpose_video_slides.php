<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * video_rebrand bilingual chrome (June 13, 2026) — tool slide headers now render
 * an Indonesian-primary + English-companion description. `header_desc` holds the
 * Indonesian primary line; this adds the English companion line beneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->text('header_desc_en')->nullable()->after('header_desc');
        });
    }

    public function down(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->dropColumn('header_desc_en');
        });
    }
};
