<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->unsignedBigInteger('post_id')->nullable()->change();
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->unsignedBigInteger('post_id')->nullable(false)->change();
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });
    }
};
