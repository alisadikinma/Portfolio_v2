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
        Schema::table('services', function (Blueprint $table) {
            // Rename 'active' to 'is_active' and 'order' to 'sort_order'
            $table->renameColumn('active', 'is_active');
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
        Schema::table('services', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['is_active']);
            $table->dropIndex(['sort_order']);

            // Rename back to original column names
            $table->renameColumn('is_active', 'active');
            $table->renameColumn('sort_order', 'order');
        });
    }
};
