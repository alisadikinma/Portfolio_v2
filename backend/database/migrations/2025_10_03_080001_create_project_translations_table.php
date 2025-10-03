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
        Schema::create('project_translations', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to projects table
            $table->foreignId('project_id')
                  ->constrained('projects')
                  ->onDelete('cascade');
            
            // Language code (en, id)
            $table->string('language', 5);
            
            // Translated content fields
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            
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
            
            // Unique constraint: one translation per project per language
            $table->unique(['project_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_translations');
    }
};
