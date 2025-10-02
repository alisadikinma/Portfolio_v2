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
        Schema::table('categories', function (Blueprint $table) {
            // SEO Meta Tags
            $table->string('meta_title', 60)->nullable()->after('name');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            
            // Open Graph
            $table->string('og_title', 60)->nullable()->after('meta_keywords');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            
            // Category-specific SEO
            $table->string('canonical_url')->nullable()->after('og_image');
            $table->json('schema_markup')->nullable()->after('canonical_url');
            $table->boolean('index_follow')->default(true)->after('schema_markup');
            
            // Add indexes
            $table->index('meta_title');
            $table->index('index_follow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['meta_title']);
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
                'index_follow',
            ]);
        });
    }
};
