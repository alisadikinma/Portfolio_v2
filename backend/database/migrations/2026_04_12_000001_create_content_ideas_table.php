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
        Schema::create('content_ideas', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->enum('source', ['manual','google_trends','youtube','tiktok','google_news','instagram'])->default('manual');
            $table->enum('pillar', ['vibe_coding','ai_automation','ai_agents','ai_video_image','general'])->default('general');
            $table->enum('priority', ['low','medium','high'])->default('medium');
            $table->json('tags')->nullable();
            $table->json('languages')->nullable();
            $table->json('output_types')->nullable();
            $table->text('instructions')->nullable();
            $table->string('niche', 100)->default('AI & Tech');
            $table->enum('status', ['draft','researching','researched','generating','completed','archived'])->default('draft');
            $table->json('research_data')->nullable();
            $table->json('workflows')->nullable();
            $table->json('source_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_ideas');
    }
};
