<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add schema_markup and faq_schema fields to post_translations table.
     */
    public function up(): void
    {
        Schema::table('post_translations', function (Blueprint $table) {
            $table->json('schema_markup')->nullable()->after('ai_summary');
            $table->json('faq_schema')->nullable()->after('schema_markup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_translations', function (Blueprint $table) {
            $table->dropColumn(['schema_markup', 'faq_schema']);
        });
    }
};
