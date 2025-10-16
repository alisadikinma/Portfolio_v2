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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);                  // "Home", "About", "Blog", etc
            $table->string('slug', 100)->unique();        // route identifier
            $table->string('url', 255);                    // "/", "/about", "/projects", etc
            $table->string('icon', 100)->nullable();      // "home", "info", "briefcase", etc (for navbar icons)
            $table->boolean('is_active')->default(true);  // show/hide in navbar
            $table->integer('sequence')->default(0);      // sort order (0=first)
            $table->timestamps();

            // Indexes for performance
            $table->index('is_active');
            $table->index('sequence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
