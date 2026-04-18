<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->string('planned_filename', 255)->nullable()->after('insert_after_heading');
        });
    }

    public function down(): void
    {
        Schema::table('image_generation_jobs', function (Blueprint $table) {
            $table->dropColumn('planned_filename');
        });
    }
};
