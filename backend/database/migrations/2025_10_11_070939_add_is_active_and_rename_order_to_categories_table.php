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
        Schema::table('categories', function (Blueprint $table) {
            // Add is_active column
            $table->boolean('is_active')->default(true)->after('color');

            // Rename 'order' to 'sort_order'
            $table->renameColumn('order', 'sort_order');

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
        Schema::table('categories', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['is_active']);
            $table->dropIndex(['sort_order']);

            // Rename back to original column name
            $table->renameColumn('sort_order', 'order');

            // Drop is_active column
            $table->dropColumn('is_active');
        });
    }
};
