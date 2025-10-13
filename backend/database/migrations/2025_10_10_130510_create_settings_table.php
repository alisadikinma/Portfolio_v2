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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'profile.name', 'hero.title'
            $table->longText('value')->nullable(); // Supports JSON, HTML, text
            $table->string('group', 100); // e.g., 'profile', 'about', 'hero'
            $table->string('type', 50)->default('text'); // 'text', 'textarea', 'image', 'boolean', 'json'
            $table->timestamps();

            // Index for performance
            $table->index('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
