<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag awards as homepage-featured so /api/homepage/featured can
 * surface a curated 6-up grid without coupling to sort_order
 * (Phase 1, Homepage Redesign, 2026-05-04). Default false; admin UI
 * will toggle this in a follow-up phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('sort_order');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn('is_featured');
        });
    }
};
