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
        Schema::create('award_gallery', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('award_id')
                  ->constrained('awards')
                  ->cascadeOnDelete();

            $table->foreignId('gallery_id')
                  ->constrained('galleries')
                  ->cascadeOnDelete();

            // Sort order for galleries within an award
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Unique constraint to prevent duplicate relationships
            $table->unique(['award_id', 'gallery_id']);

            // Indexes for performance
            $table->index('award_id');
            $table->index('gallery_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('award_gallery');
    }
};
