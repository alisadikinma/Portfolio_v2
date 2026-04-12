<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand status enum for 2-gate article pipeline
        DB::statement("ALTER TABLE content_ideas MODIFY COLUMN status ENUM('draft','researching','article_ready','generating_images','images_ready','completed','archived') DEFAULT 'draft'");

        // 2. Add result tracking fields
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->unsignedBigInteger('result_post_id')->nullable()->after('workflows');
            $table->json('generated_article')->nullable()->after('result_post_id');
            $table->json('generated_images')->nullable()->after('generated_article');
            $table->text('image_instructions')->nullable()->after('generated_images');
            $table->json('image_references')->nullable()->after('image_instructions');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE content_ideas MODIFY COLUMN status ENUM('draft','researching','researched','generating','completed','archived') DEFAULT 'draft'");

        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn(['result_post_id', 'generated_article', 'generated_images', 'image_instructions', 'image_references']);
        });
    }
};
