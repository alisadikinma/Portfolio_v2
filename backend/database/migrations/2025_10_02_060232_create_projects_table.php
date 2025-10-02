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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->json('images')->nullable(); // Multiple images for gallery
            $table->string('category'); // web, mobile, ai, iot, automation
            $table->json('technologies')->nullable(); // Array of technologies used
            $table->string('client')->nullable();
            $table->string('url')->nullable();
            $table->date('completed_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('published')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
