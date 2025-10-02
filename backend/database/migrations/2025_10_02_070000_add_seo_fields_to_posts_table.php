<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // SEO Meta Tags
            $table->string('meta_title', 60)->nullable()->after('title');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            
            // Open Graph (Social Media)
            $table->string('og_title', 60)->nullable()->after('meta_keywords');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            
            // Advanced SEO
            $table->string('canonical_url')->nullable()->after('og_image');
            $table->json('schema_markup')->nullable()->after('canonical_url'); // JSON-LD structured data
            
            // GEO (Generative Engine Optimization)
            $table->text('ai_summary')->nullable()->after('schema_markup'); // Summary for AI engines
            $table->json('faq_schema')->nullable()->after('ai_summary'); // FAQ structured data
            
            // SEO Performance
            $table->integer('seo_score')->default(0)->after('faq_schema'); // 0-100
            $table->boolean('index_follow')->default(true)->after('seo_score');
            
            // Add indexes for better performance
            $table->index('meta_title');
            $table->index('seo_score');
            $table->index('index_follow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['meta_title']);
            $table->dropIndex(['seo_score']);
            $table->dropIndex(['index_follow']);
            
            $table->dropColumn([
                'meta_title',
                'meta_description',
                'meta_keywords',
                'og_title',
                'og_description',
                'og_image',
                'canonical_url',
                'schema_markup',
                'ai_summary',
                'faq_schema',
                'seo_score',
                'index_follow',
            ]);
        });
    }
};
