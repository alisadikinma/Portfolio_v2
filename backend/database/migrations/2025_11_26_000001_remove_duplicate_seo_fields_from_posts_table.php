<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Remove duplicate SEO fields from posts table.
     * These fields should only exist in post_translations table (per-language).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['meta_title']);
            
            // Drop duplicate SEO fields (these exist in post_translations)
            $table->dropColumn([
                'meta_title',       // Per-language -> post_translations
                'meta_description', // Per-language -> post_translations
                'meta_keywords',    // Per-language -> post_translations
                'og_title',         // Per-language -> post_translations
                'og_description',   // Per-language -> post_translations
                'canonical_url',    // Per-language -> post_translations
                'ai_summary',       // Per-language -> post_translations
            ]);
            
            // Keep global SEO fields in posts table:
            // - meta_keywords (global keywords)
            // - og_image (global image URL)
            // - schema_markup (global JSON-LD)
            // - faq_schema (global FAQ)
            // - seo_score (global score)
            // - index_follow (global boolean)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Restore columns
            $table->string('meta_title', 60)->nullable()->after('title');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->string('og_title', 60)->nullable()->after('meta_keywords');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('canonical_url')->nullable()->after('og_image');
            $table->text('ai_summary')->nullable()->after('schema_markup');
            
            // Restore index
            $table->index('meta_title');
        });
    }
};
