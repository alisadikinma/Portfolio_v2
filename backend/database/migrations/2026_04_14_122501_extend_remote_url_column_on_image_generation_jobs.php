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
        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->text('remote_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->string('remote_url', 255)->nullable()->change();
        });
    }
};
