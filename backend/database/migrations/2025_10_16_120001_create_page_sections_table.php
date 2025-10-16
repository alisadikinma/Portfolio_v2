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
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page_type', 50);              // "homepage"
            $table->string('section_type', 100);          // "hero", "featured_projects", "latest_blog", "testimonials", "cta"
            $table->boolean('is_active')->default(true); // show/hide section
            $table->integer('sequence')->default(0);     // sort order (0=first)
            $table->timestamps();

            // Unique constraint for page+section combo
            $table->unique(['page_type', 'section_type']);

            // Indexes for performance
            $table->index('page_type');
            $table->index('is_active');
            $table->index('sequence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
