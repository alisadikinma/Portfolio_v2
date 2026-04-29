<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds linkedin_posts.slide_asset_urns (JSON) so carousel-format posts can
     * publish via the LinkedIn multi-image fallback path (shareMediaCategory=
     * IMAGE + media[] array of asset URNs, one per slide). This is the MVP
     * carousel publish path until TCPDF document composition ships.
     *
     * Stored as JSON so partial uploads survive across publish retries — if
     * 7 of 9 slide uploads succeed before a network blip, the next retry
     * skips the 7 already-persisted URNs and only re-uploads slides 8 + 9.
     *
     * Shape: indexed JSON object keyed by slide index (string keys for JSON
     * compatibility):
     *   { "0": "urn:li:digitalmediaAsset:...", "1": "urn:li:digitalmediaAsset:...", ... }
     *
     * Distinct from `linkedin_asset_urn` (single document asset for the
     * eventual TCPDF carousel flow) and `thumbnail_asset_urn` (single image
     * asset for text-format posts).
     */
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->json('slide_asset_urns')
                ->nullable()
                ->after('thumbnail_asset_urn');
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->dropColumn('slide_asset_urns');
        });
    }
};
