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
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to posts table
            $table->foreignId('post_id')
                  ->constrained('posts')
                  ->onDelete('cascade');
            
            // Language code (en, id)
            $table->string('language', 5);
            
            // Translated content fields
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            
            // SEO fields (per language)
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->text('ai_summary')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('language');
            $table->index('slug');
            
            // Unique constraint: one translation per post per language
            $table->unique(['post_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_translations');
    }
};
