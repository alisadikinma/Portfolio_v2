<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // STEP 1: Backup existing galleries data
        // (galleries table has: id, title, description, image, category, is_active, sort_order)
        $oldGalleries = DB::table('galleries')->get();

        // STEP 2: Remove featured_gallery_group_id from awards table FIRST (before dropping gallery_groups)
        if (Schema::hasColumn('awards', 'featured_gallery_group_id')) {
            Schema::table('awards', function (Blueprint $table) {
                $table->dropForeign(['featured_gallery_group_id']);
                $table->dropColumn('featured_gallery_group_id');
            });
        }

        // STEP 3: Drop all wrong tables (now safe to drop)
        Schema::dropIfExists('gallery_items'); // Old items table (linked to gallery_groups)
        Schema::dropIfExists('award_gallery_groups'); // Wrong pivot table
        Schema::dropIfExists('gallery_groups'); // Unnecessary intermediate table

        // STEP 4: Restructure galleries table
        Schema::table('galleries', function (Blueprint $table) {
            // Add new fields
            $table->string('company')->nullable()->after('description');
            $table->string('period', 100)->nullable()->after('company');
            $table->string('thumbnail')->nullable()->after('period');
            $table->foreignId('award_id')->nullable()->after('thumbnail')
                  ->constrained('awards')->nullOnDelete();

            // Drop old columns
            $table->dropColumn(['image', 'category']);
        });

        // STEP 5: Create NEW gallery_items table (parent: galleries)
        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('file_path', 500);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('sequence')->default(0);
            $table->timestamps();

            $table->index(['gallery_id', 'sequence']);
        });

        // STEP 6: Migrate old galleries.image → gallery_items.file_path
        foreach ($oldGalleries as $gallery) {
            if (!empty($gallery->image)) {
                DB::table('gallery_items')->insert([
                    'gallery_id' => $gallery->id,
                    'type' => 'image',
                    'file_path' => $gallery->image,
                    'title' => $gallery->title,
                    'sequence' => 0,
                    'created_at' => $gallery->created_at,
                    'updated_at' => $gallery->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: Recreate old structure
        Schema::dropIfExists('gallery_items');

        Schema::table('galleries', function (Blueprint $table) {
            // Restore old columns
            $table->string('image')->nullable();
            $table->string('category')->nullable();

            // Drop new columns
            $table->dropForeign(['award_id']);
            $table->dropColumn(['company', 'period', 'thumbnail', 'award_id']);
        });

        // Recreate old tables
        Schema::create('gallery_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('award_gallery_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_group_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['award_id', 'gallery_group_id']);
        });

        Schema::table('awards', function (Blueprint $table) {
            $table->foreignId('featured_gallery_group_id')->nullable()
                  ->constrained('gallery_groups')->nullOnDelete();
        });
    }
};
