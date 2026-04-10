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
        Schema::create('image_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('uuid')->unique();
            $table->enum('type', ['hero', 'inline'])->default('hero');
            $table->text('prompt');
            $table->string('insert_after_heading')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('image_url')->nullable();
            $table->string('remote_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_generation_jobs');
    }
};
