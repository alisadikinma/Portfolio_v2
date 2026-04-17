<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE content_ideas MODIFY COLUMN status ENUM('draft','researching','article_ready','generating_images','images_ready','completed','archived','failed') DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE content_ideas MODIFY COLUMN status ENUM('draft','researching','article_ready','generating_images','images_ready','completed','archived') DEFAULT 'draft'");
    }
};
