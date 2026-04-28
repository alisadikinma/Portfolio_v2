<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds linkedin_posts.thumbnail_asset_urn so text-format posts can publish
     * with a 16:9 thumbnail (shareMediaCategory=IMAGE) instead of the existing
     * NONE-category text-only post. The URN is the LinkedIn DigitalMedia asset
     * URN returned from registerUpload + PUT bytes — persisting it makes
     * publish-now retries idempotent (no double upload).
     *
     * Distinct from `linkedin_asset_urn` which is reserved for carousel
     * document assets (TCPDF flow, deferred).
     */
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->string('thumbnail_asset_urn', 191)
                ->nullable()
                ->after('linkedin_asset_urn');
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->dropColumn('thumbnail_asset_urn');
        });
    }
};
