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
        Schema::table('posts', function (Blueprint $table) {
            // Add is_active column (in addition to existing 'published')
            $table->boolean('is_active')->default(true)->after('published_at');

            // Add sort_order column
            $table->integer('sort_order')->default(0)->after('is_active');

            // Add indexes for performance
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['is_active']);
            $table->dropIndex(['sort_order']);

            // Drop columns
            $table->dropColumn(['is_active', 'sort_order']);
        });
    }
};
