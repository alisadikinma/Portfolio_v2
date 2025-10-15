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
        Schema::table('projects', function (Blueprint $table) {
            // Drop old columns first
            $table->dropColumn(['client', 'url', 'image', 'featured', 'content', 'images', 'category', 'completed_at', 'published', 'is_active', 'sort_order']);
        });
        
        Schema::table('projects', function (Blueprint $table) {
            // Add new columns with correct names
            $table->string('client_name')->nullable()->after('description');
            $table->string('project_url')->nullable()->after('client_name');
            $table->string('github_url')->nullable()->after('project_url');
            $table->string('featured_image')->nullable()->after('github_url');
            $table->boolean('is_featured')->default(false)->after('featured_image');
            $table->enum('status', ['planning', 'in_progress', 'completed'])->default('planning')->after('is_featured');
            $table->date('start_date')->nullable()->after('status');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['client_name', 'project_url', 'github_url', 'featured_image', 'is_featured', 'status', 'start_date', 'end_date']);
        });
        
        Schema::table('projects', function (Blueprint $table) {
            // Add back old columns
            $table->string('client')->nullable();
            $table->string('url')->nullable();
            $table->string('image')->nullable();
            $table->boolean('featured')->default(false);
            $table->longText('content')->nullable();
            $table->json('images')->nullable();
            $table->string('category');
            $table->date('completed_at')->nullable();
            $table->boolean('published')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
        });
    }
};
