<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add meta_keywords field to post_translations table.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('post_translations', 'meta_keywords')) {
            Schema::table('post_translations', function (Blueprint $table) {
                $table->string('meta_keywords')->nullable()->after('meta_description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('post_translations', 'meta_keywords')) {
            Schema::table('post_translations', function (Blueprint $table) {
                $table->dropColumn('meta_keywords');
            });
        }
    }
};
