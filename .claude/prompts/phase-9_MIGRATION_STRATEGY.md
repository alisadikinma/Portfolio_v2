# Phase 9: Gallery System Restructure - MIGRATION STRATEGY UPDATE

**Critical:** Database structure berbeda dari asumsi awal.

---

## 🔍 ACTUAL Current Structure (from SQL dump)

```sql
-- Tables yang ada:
1. awards (featured_gallery_group_id FK)
2. award_gallery_groups (pivot)
3. gallery_groups (container)
4. gallery_items (FK ke gallery_groups)
5. galleries (standalone, no relationship)
```

**Problems:**
- Ada duplikat konsep: `gallery_groups` + `galleries`
- `gallery_items` → `gallery_groups` (wrong parent)
- `award_gallery_groups` pivot (many-to-many wrong)
- `galleries` standalone single image

---

## ✅ UPDATED Migration Strategy

### STEP 1: Database Administrator Tasks

**File:** `backend/database/migrations/2025_10_25_xxxxxx_restructure_galleries_system.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // STEP 1: Backup existing galleries data
        // (galleries table has: id, title, description, image, category, is_active, sort_order)
        $oldGalleries = DB::table('galleries')->get();
        
        // STEP 2: Drop all wrong tables
        Schema::dropIfExists('gallery_items'); // Old items table (linked to gallery_groups)
        Schema::dropIfExists('award_gallery_groups'); // Wrong pivot table
        Schema::dropIfExists('gallery_groups'); // Unnecessary intermediate table
        
        // STEP 3: Restructure galleries table
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
        
        // STEP 4: Create NEW gallery_items table (parent: galleries)
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
        
        // STEP 5: Migrate old galleries.image → gallery_items.file_path
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
        
        // STEP 6: Remove featured_gallery_group_id from awards table
        if (Schema::hasColumn('awards', 'featured_gallery_group_id')) {
            Schema::table('awards', function (Blueprint $table) {
                $table->dropForeign(['featured_gallery_group_id']);
                $table->dropColumn('featured_gallery_group_id');
            });
        }
    }

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
```

---

## 📋 Migration Checklist

**database-administrator** must verify:

### Before Migration:
- [ ] Backup database: `mysqldump portfolio_v2 > backup_before_phase9.sql`
- [ ] Check existing data count:
  ```sql
  SELECT COUNT(*) FROM galleries;
  SELECT COUNT(*) FROM gallery_groups;
  SELECT COUNT(*) FROM gallery_items;
  SELECT COUNT(*) FROM award_gallery_groups;
  ```

### During Migration:
- [ ] Create migration file
- [ ] Test migration: `php artisan migrate:rollback` (if errors)
- [ ] Run migration: `php artisan migrate`

### After Migration:
- [ ] Verify tables dropped:
  ```sql
  SHOW TABLES LIKE 'gallery_groups';  -- Should be empty
  SHOW TABLES LIKE 'award_gallery_groups';  -- Should be empty
  ```
- [ ] Verify new structure:
  ```sql
  DESC galleries;  -- Should have: company, period, thumbnail, award_id
  DESC gallery_items;  -- Should have: gallery_id, type, file_path, sequence
  ```
- [ ] Verify data migrated:
  ```sql
  SELECT COUNT(*) FROM gallery_items;  -- Should equal old galleries with images
  ```
- [ ] Verify foreign keys:
  ```sql
  SHOW CREATE TABLE galleries;  -- Should have FK to awards
  SHOW CREATE TABLE gallery_items;  -- Should have FK to galleries
  ```

---

## ⚠️ Critical Notes

1. **Data Preservation:**
   - Old `galleries.image` → NEW `gallery_items.file_path`
   - Gallery title preserved in gallery_items.title
   - Zero data loss expected

2. **Breaking Changes:**
   - DROP: `gallery_groups`, `gallery_items` (old), `award_gallery_groups`
   - REMOVE: `awards.featured_gallery_group_id`
   - ADD: `galleries.award_id` (One-to-Many)

3. **Models to Update:**
   - `Gallery.php` - Remove gallery_groups relationship
   - `Award.php` - Remove gallery_groups, add galleries hasMany
   - `GalleryItem.php` - Create new (parent: Gallery, not GalleryGroup)

4. **Controllers to Update:**
   - `GalleryController.php` - Remove gallery_groups logic
   - `AwardController.php` - Remove gallery_groups endpoints
   - Create: `GalleryItemController.php`

---

**Agent:** database-administrator  
**Estimated Time:** 45-60 minutes  
**Risk Level:** MEDIUM (drops 3 tables, restructures 2 tables)  
**Backup Required:** YES (mandatory before execution)

---

**Updated:** October 25, 2025  
**Status:** Ready for execution
