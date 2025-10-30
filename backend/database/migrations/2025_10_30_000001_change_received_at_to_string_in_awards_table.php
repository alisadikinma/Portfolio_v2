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
        Schema::table('awards', function (Blueprint $table) {
            // Change received_at from date to string(100) to support flexible formats
            // Like "January 2025", "Q1 2025", "2025-01-15", etc.
            $table->string('received_at', 100)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            // Rollback to date type
            $table->date('received_at')->change();
        });
    }
};
