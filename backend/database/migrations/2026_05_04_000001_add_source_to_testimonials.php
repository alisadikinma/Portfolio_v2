<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `source` + `source_url` to testimonials so the homepage can
 * filter to LinkedIn-only social proof (Phase 1, Homepage Redesign,
 * 2026-05-04). The repo uses `testimonial_text` (not `content`) — the
 * new columns are placed after that field to keep migration semantics
 * close to the original-spec intent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->enum('source', ['linkedin', 'direct', 'video'])
                  ->default('direct')
                  ->after('testimonial_text');
            $table->string('source_url', 500)
                  ->nullable()
                  ->after('source');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'source_url']);
        });
    }
};
