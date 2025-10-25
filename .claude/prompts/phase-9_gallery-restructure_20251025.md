# Phase 9: Gallery System Restructure

**Date:** October 25, 2025  
**Primary Agent:** orchestrator  
**Priority:** HIGH  
**Estimated Time:** 180-240 minutes

---

## 👥 Agent Delegation

**orchestrator** coordinates the following agents in sequence:

1. ✅ **database-administrator** - Database migration (45 min)
2. ✅ **laravel-specialist** - Backend API & models (90 min)
3. ✅ **vue-expert** - Frontend components (90 min)
4. ✅ **qa-expert** - Testing & verification (45 min)
5. ✅ **documentation-engineer** - Documentation (20 min)

---

## 🎯 Objective

Restructure Gallery and Awards relationship to implement proper gallery system where:
- **Gallery** = Container with multiple items
- **Gallery Items** = Individual images/videos
- **Award** → **Gallery** = One-to-Many relationship (optional)

---

## 📸 Reference Design

User provided screenshot showing correct form structure:
- **Gallery Form:** nama_galeri, company, period, description (rich text), award (optional dropdown), thumbnail, urutan_tampil, status
- **Gallery Items:** Type dropdown, Sequence, file upload, with "+ Tambah Item" button
- **Multiple items per gallery** with delete buttons

---

## ❌ Current Problems

### Database Issues:
1. **galleries** table stores single image (field `image`) - standalone, no relationships
2. **gallery_groups** + **gallery_items** tables exist but wrong structure (items linked to groups, not galleries)
3. **award_gallery_groups** pivot creates many-to-many (should be one-to-many)
4. **awards.featured_gallery_group_id** FK to gallery_groups (wrong concept)
5. Missing fields in galleries: company, period, thumbnail, award_id

### Model Issues:
- Gallery belongsToMany Award (wrong relationship)
- Award belongsToMany Gallery (wrong relationship)
- No GalleryItem model

### API Issues:
- GalleryController expects single image upload
- No bulk image upload support
- No gallery items endpoint

### Frontend Issues:
- GalleryForm.vue expects single image
- No GalleryItemForm.vue component
- No dynamic array for adding multiple items

---

## 🚀 Execution Order

### STEP 1: Database Migration (database-administrator)
**Time:** 45-60 minutes  
**Agent:** `database-administrator.md`  
**Files:** `backend/database/migrations/2025_10_25_xxxxxx_restructure_galleries_system.php`

**⚠️ CRITICAL: Read MIGRATION_STRATEGY.md first!**

**Reference:** `.claude/prompts/phase-9_MIGRATION_STRATEGY.md`

**Tasks:**
1. **BACKUP DATABASE FIRST** (mandatory)
   ```bash
   cd C:\xampp\htdocs\Portfolio_v2\backend
   mysqldump -u ali -p portfolio_v2 > backup_before_phase9.sql
   ```

2. **Read actual structure from SQL dump**
   - Tables to DROP: `gallery_groups`, `gallery_items` (old), `award_gallery_groups`
   - Column to DROP from awards: `featured_gallery_group_id`

3. **Create migration file with:**
   - Backup existing `galleries` data
   - DROP: `gallery_items`, `award_gallery_groups`, `gallery_groups` (in this order)
   - RESTRUCTURE `galleries`: add company, period, thumbnail, award_id; drop image, category
   - CREATE NEW `gallery_items` (parent: galleries, not gallery_groups)
   - MIGRATE: `galleries.image` → NEW `gallery_items.file_path`
   - REMOVE: `awards.featured_gallery_group_id` column

4. **Test migration:**
   ```bash
   php artisan migrate
   php artisan migrate:rollback  # Test rollback
   php artisan migrate           # Final run
   ```

5. **Verify results:**
   ```sql
   SHOW TABLES LIKE 'gallery_groups';  -- Should be empty
   DESC galleries;  -- Should have award_id, company, period, thumbnail
   DESC gallery_items;  -- Should have gallery_id (not gallery_group_id)
   SELECT COUNT(*) FROM gallery_items;  -- Verify data migrated
   ```

**Migration Structure:**
```php
public function up(): void
{
    // Step 1: Create gallery_items table
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
    
    // Step 2: Migrate existing gallery images to gallery_items
    DB::table('galleries')->whereNotNull('image')->each(function ($gallery) {
        DB::table('gallery_items')->insert([
            'gallery_id' => $gallery->id,
            'type' => 'image',
            'file_path' => $gallery->image,
            'sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
    
    // Step 3: Add award_id to galleries
    Schema::table('galleries', function (Blueprint $table) {
        $table->string('company')->nullable()->after('description');
        $table->string('period', 100)->nullable()->after('company');
        $table->string('thumbnail')->nullable()->after('period');
        $table->foreignId('award_id')->nullable()->after('thumbnail')
              ->constrained('awards')->nullOnDelete();
    });
    
    // Step 4: Migrate award_gallery pivot data to galleries.award_id
    // (Assuming 1 gallery can only link to 1 award, take first)
    DB::table('award_gallery')->each(function ($pivot) {
        DB::table('galleries')
          ->where('id', $pivot->gallery_id)
          ->update(['award_id' => $pivot->award_id]);
    });
    
    // Step 5: Drop pivot table
    Schema::dropIfExists('award_gallery');
    
    // Step 6: Drop old columns from galleries
    Schema::table('galleries', function (Blueprint $table) {
        $table->dropColumn(['image', 'category']);
    });
}
```

**Deliverables:**
- ✅ Migration file created
- ✅ Data preserved (no loss)
- ✅ Tables restructured

---

### STEP 2: Backend API (laravel-specialist)
**Time:** 90 minutes  
**Agent:** `laravel-specialist.md`  
**Depends on:** STEP 1 complete

**Tasks:**

#### A. Models (30 min)

**Update `app/Models/Gallery.php`:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'company',
        'period',
        'thumbnail',
        'award_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['items_count'];

    /**
     * Gallery belongs to Award (optional)
     */
    public function award()
    {
        return $this->belongsTo(Award::class);
    }

    /**
     * Gallery has many items
     */
    public function items()
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sequence');
    }

    /**
     * Get total items count
     */
    public function getItemsCountAttribute()
    {
        return $this->items()->count();
    }
}
```

**Create `app/Models/GalleryItem.php`:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'type',
        'file_path',
        'title',
        'description',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    /**
     * Item belongs to Gallery
     */
    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }
}
```

**Update `app/Models/Award.php`:**
```php
// Update galleries relationship
public function galleries()
{
    return $this->hasMany(Gallery::class);
}

// Remove from fillable:
// 'featured_gallery_id'

// Remove methods:
// featuredGallery()

// Update getTotalPhotosAttribute:
public function getTotalPhotosAttribute()
{
    return $this->galleries()
                ->with('items')
                ->get()
                ->sum(fn($g) => $g->items->count());
}
```

#### B. Controllers (40 min)

**Update `app/Http/Controllers/Api/Admin/GalleryController.php`:**

Add `store()` method for bulk upload:
```php
public function store(StoreGalleryRequest $request)
{
    DB::beginTransaction();
    try {
        // Create gallery
        $gallery = Gallery::create([
            'title' => $request->title,
            'description' => $request->description,
            'company' => $request->company,
            'period' => $request->period,
            'award_id' => $request->award_id,
            'is_active' => $request->is_active ?? true,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        // Handle thumbnail
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')
                ->store('galleries/thumbnails', 'public');
            $gallery->update(['thumbnail' => $thumbnailPath]);
        }

        // Handle items (bulk upload)
        if ($request->has('items')) {
            foreach ($request->items as $itemData) {
                if (isset($itemData['file'])) {
                    $filePath = $itemData['file']
                        ->store("galleries/{$gallery->id}/items", 'public');
                    
                    GalleryItem::create([
                        'gallery_id' => $gallery->id,
                        'type' => $itemData['type'] ?? 'image',
                        'file_path' => $filePath,
                        'title' => $itemData['title'] ?? null,
                        'description' => $itemData['description'] ?? null,
                        'sequence' => $itemData['sequence'] ?? 0,
                    ]);
                }
            }
        }

        DB::commit();
        
        $gallery->load(['award', 'items']);
        
        return response()->json([
            'success' => true,
            'data' => new GalleryResource($gallery),
            'message' => 'Gallery created successfully',
        ], 201);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'error' => ['message' => $e->getMessage()],
        ], 500);
    }
}
```

**Create `app/Http/Controllers/Api/Admin/GalleryItemController.php`:**
```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryItemController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'gallery_id' => 'required|exists:galleries,id',
            'type' => 'required|in:image,video',
            'file' => 'required|file|max:5120',
            'sequence' => 'integer',
        ]);

        $filePath = $request->file('file')
            ->store("galleries/{$validated['gallery_id']}/items", 'public');

        $item = GalleryItem::create([
            'gallery_id' => $validated['gallery_id'],
            'type' => $validated['type'],
            'file_path' => $filePath,
            'sequence' => $validated['sequence'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => new GalleryItemResource($item),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = GalleryItem::findOrFail($id);

        $validated = $request->validate([
            'type' => 'in:image,video',
            'file' => 'file|max:5120',
            'sequence' => 'integer',
        ]);

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($item->file_path);
            $validated['file_path'] = $request->file('file')
                ->store("galleries/{$item->gallery_id}/items", 'public');
        }

        $item->update($validated);

        return response()->json([
            'success' => true,
            'data' => new GalleryItemResource($item),
        ]);
    }

    public function destroy($id)
    {
        $item = GalleryItem::findOrFail($id);
        Storage::disk('public')->delete($item->file_path);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted',
        ]);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:gallery_items,id',
            'items.*.sequence' => 'required|integer',
        ]);

        foreach ($validated['items'] as $itemData) {
            GalleryItem::where('id', $itemData['id'])
                ->update(['sequence' => $itemData['sequence']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Items reordered',
        ]);
    }
}
```

#### C. API Resources (20 min)

**Update `app/Http/Resources/GalleryResource.php`:**
```php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'description' => $this->description,
        'company' => $this->company,
        'period' => $this->period,
        'thumbnail' => $this->thumbnail ? url("storage/{$this->thumbnail}") : null,
        'award' => $this->whenLoaded('award', fn() => [
            'id' => $this->award->id,
            'title' => $this->award->title,
        ]),
        'items' => GalleryItemResource::collection($this->whenLoaded('items')),
        'items_count' => $this->items_count,
        'is_active' => $this->is_active,
        'sort_order' => $this->sort_order,
        'created_at' => $this->created_at->format('Y-m-d H:i'),
        'updated_at' => $this->updated_at->format('Y-m-d H:i'),
    ];
}
```

**Create `app/Http/Resources/GalleryItemResource.php`:**
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GalleryItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'gallery_id' => $this->gallery_id,
            'type' => $this->type,
            'file_path' => url("storage/{$this->file_path}"),
            'title' => $this->title,
            'description' => $this->description,
            'sequence' => $this->sequence,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
```

#### D. Routes (10 min)

Update `routes/api.php`:
```php
// Admin galleries
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::apiResource('galleries', GalleryController::class);
    Route::post('galleries/{id}/items', [GalleryController::class, 'addItems']);
    
    Route::post('gallery-items', [GalleryItemController::class, 'store']);
    Route::put('gallery-items/{id}', [GalleryItemController::class, 'update']);
    Route::delete('gallery-items/{id}', [GalleryItemController::class, 'destroy']);
    Route::post('gallery-items/reorder', [GalleryItemController::class, 'reorder']);
});
```

**Deliverables:**
- ✅ Gallery.php updated
- ✅ GalleryItem.php created
- ✅ Award.php updated
- ✅ GalleryController.php handles bulk upload
- ✅ GalleryItemController.php created
- ✅ API Resources created/updated
- ✅ Routes configured

---

### STEP 3: Frontend Components (vue-expert)
**Time:** 90 minutes  
**Agent:** `vue-expert.md`  
**Depends on:** STEP 2 complete

**Tasks:**

#### A. Update GalleryForm.vue (40 min)

Location: `frontend/src/views/admin/GalleryForm.vue`

Add fields:
- Company (text input)
- Period (text input)
- Award (single select dropdown, optional)
- Thumbnail (ImageUploader)
- Description (RichTextEditor)
- Items section (use GalleryItemsSection component)

Remove:
- Old single image upload

**Template structure:**
```vue
<template>
  <form @submit.prevent="handleSubmit">
    <!-- Title -->
    <BaseInput v-model="form.title" label="Nama Galeri" required />
    
    <!-- Company -->
    <BaseInput v-model="form.company" label="Company" />
    
    <!-- Period -->
    <BaseInput v-model="form.period" label="Period" placeholder="2024 / Q1-2024" />
    
    <!-- Description -->
    <RichTextEditor v-model="form.description" label="Deskripsi" />
    
    <!-- Award (optional) -->
    <select v-model="form.award_id">
      <option :value="null">Tidak terkait dengan award</option>
      <option v-for="award in awards" :key="award.id" :value="award.id">
        {{ award.title }}
      </option>
    </select>
    
    <!-- Thumbnail -->
    <ImageUploader 
      v-model="form.thumbnail"
      label="Thumbnail Galeri"
      accept="image/*"
      :max-size="2048"
    />
    
    <!-- Gallery Items -->
    <GalleryItemsSection v-model="form.items" />
    
    <!-- Sort Order -->
    <BaseInput v-model.number="form.sort_order" type="number" label="Urutan Tampil" />
    
    <!-- Status -->
    <label>
      <input type="checkbox" v-model="form.is_active" />
      Active
    </label>
    
    <!-- Submit -->
    <BaseButton type="submit" :loading="loading">
      {{ isEdit ? 'Update' : 'Simpan' }} Gallery
    </BaseButton>
  </form>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import RichTextEditor from '@/components/blog/RichTextEditor.vue'
import ImageUploader from '@/components/blog/ImageUploader.vue'
import GalleryItemsSection from '@/components/gallery/GalleryItemsSection.vue'
import { useGalleriesStore } from '@/stores/galleries'

const router = useRouter()
const galleriesStore = useGalleriesStore()

const form = ref({
  title: '',
  description: '',
  company: '',
  period: '',
  award_id: null,
  thumbnail: null,
  items: [],
  is_active: true,
  sort_order: 0,
})

const awards = ref([])
const loading = ref(false)

onMounted(async () => {
  // Fetch awards for dropdown
  const response = await fetch('/api/awards')
  awards.value = await response.json()
})

async function handleSubmit() {
  loading.value = true
  try {
    const formData = new FormData()
    
    // Basic fields
    formData.append('title', form.value.title)
    formData.append('description', form.value.description || '')
    formData.append('company', form.value.company || '')
    formData.append('period', form.value.period || '')
    formData.append('is_active', form.value.is_active)
    formData.append('sort_order', form.value.sort_order)
    
    // Award (optional)
    if (form.value.award_id) {
      formData.append('award_id', form.value.award_id)
    }
    
    // Thumbnail
    if (form.value.thumbnail) {
      formData.append('thumbnail', form.value.thumbnail)
    }
    
    // Items
    form.value.items.forEach((item, index) => {
      formData.append(`items[${index}][type]`, item.type)
      formData.append(`items[${index}][file]`, item.file)
      formData.append(`items[${index}][sequence]`, item.sequence)
    })
    
    await galleriesStore.createGallery(formData)
    router.push('/admin/galleries')
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}
</script>
```

#### B. Create GalleryItemsSection.vue (30 min)

Location: `frontend/src/components/gallery/GalleryItemsSection.vue`

```vue
<template>
  <div class="space-y-4">
    <h3 class="text-lg font-semibold">Gallery Items</h3>
    
    <div v-for="(item, index) in modelValue" :key="index" 
         class="border border-gray-300 rounded-lg p-4 space-y-3 bg-gray-50">
      
      <div class="flex items-start justify-between">
        <span class="font-medium">Item #{{ index + 1 }}</span>
        <button 
          type="button"
          @click="removeItem(index)"
          class="text-red-600 hover:text-red-800"
        >
          🗑️ Hapus
        </button>
      </div>
      
      <!-- Type select -->
      <div>
        <label class="block text-sm font-medium mb-1">Type</label>
        <select 
          v-model="item.type"
          class="w-full border rounded px-3 py-2"
        >
          <option value="image">Image</option>
          <option value="video">Video</option>
        </select>
      </div>
      
      <!-- File upload -->
      <ImageUploader 
        v-model="item.file"
        :label="`File`"
        :accept="item.type === 'image' ? 'image/*' : 'video/*'"
        :max-size="5120"
      />
      
      <!-- Sequence -->
      <div>
        <label class="block text-sm font-medium mb-1">Urutan</label>
        <input 
          v-model.number="item.sequence"
          type="number"
          class="w-full border rounded px-3 py-2"
          placeholder="0"
        />
      </div>
      
      <!-- Preview button (if file exists) -->
      <button 
        v-if="item.file"
        type="button"
        @click="previewItem(item)"
        class="text-blue-600 hover:text-blue-800"
      >
        👁️ Preview
      </button>
    </div>
    
    <!-- Add item button -->
    <button 
      type="button"
      @click="addItem"
      :disabled="modelValue.length >= 20"
      class="w-full py-2 px-4 bg-green-600 text-white rounded hover:bg-green-700 disabled:bg-gray-400"
    >
      + Tambah Item ({{ modelValue.length }}/20)
    </button>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'
import ImageUploader from '@/components/blog/ImageUploader.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue'])

function addItem() {
  if (props.modelValue.length >= 20) return
  
  const items = [...props.modelValue]
  items.push({
    type: 'image',
    file: null,
    title: '',
    description: '',
    sequence: items.length
  })
  emit('update:modelValue', items)
}

function removeItem(index) {
  const items = [...props.modelValue]
  items.splice(index, 1)
  // Reorder sequences
  items.forEach((item, i) => item.sequence = i)
  emit('update:modelValue', items)
}

function previewItem(item) {
  if (!item.file) return
  const url = URL.createObjectURL(item.file)
  window.open(url, '_blank')
}
</script>
```

#### C. Update GalleryList.vue (20 min)

Location: `frontend/src/views/admin/GalleryList.vue`

Update table columns to show:
- Thumbnail (image preview)
- Title
- Company
- Period
- Award name (if linked)
- Items count
- Status
- Actions

```vue
<template>
  <table>
    <thead>
      <tr>
        <th>Thumbnail</th>
        <th>Title</th>
        <th>Company</th>
        <th>Period</th>
        <th>Award</th>
        <th>Items</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="gallery in galleries" :key="gallery.id">
        <td>
          <img 
            v-if="gallery.thumbnail" 
            :src="gallery.thumbnail" 
            class="w-16 h-16 object-cover rounded"
          />
        </td>
        <td>{{ gallery.title }}</td>
        <td>{{ gallery.company || '-' }}</td>
        <td>{{ gallery.period || '-' }}</td>
        <td>{{ gallery.award?.title || '-' }}</td>
        <td>{{ gallery.items_count }} items</td>
        <td>
          <span :class="gallery.is_active ? 'text-green-600' : 'text-gray-400'">
            {{ gallery.is_active ? 'Active' : 'Inactive' }}
          </span>
        </td>
        <td>
          <button @click="editGallery(gallery.id)">Edit</button>
          <button @click="deleteGallery(gallery.id)">Delete</button>
        </td>
      </tr>
    </tbody>
  </table>
</template>
```

#### D. Update stores/galleries.js (10 min)

Location: `frontend/src/stores/galleries.js`

```js
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useGalleriesStore = defineStore('galleries', {
  state: () => ({
    galleries: [],
    loading: false,
  }),

  actions: {
    async createGallery(formData) {
      this.loading = true
      try {
        const response = await api.post('/admin/galleries', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        this.galleries.push(response.data.data)
        return response.data
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchGalleries() {
      this.loading = true
      try {
        const response = await api.get('/admin/galleries')
        this.galleries = response.data.data
      } catch (error) {
        console.error(error)
      } finally {
        this.loading = false
      }
    },

    async deleteGallery(id) {
      await api.delete(`/admin/galleries/${id}`)
      this.galleries = this.galleries.filter(g => g.id !== id)
    }
  }
})
```

**Deliverables:**
- ✅ GalleryForm.vue updated (company, period, award, thumbnail, items)
- ✅ GalleryItemsSection.vue created (dynamic items)
- ✅ GalleryList.vue updated (new columns)
- ✅ stores/galleries.js updated (FormData handling)

---

### STEP 4: QA Testing (qa-expert)
**Time:** 45 minutes  
**Agent:** `qa-expert.md`  
**Depends on:** STEP 3 complete

**Test Cases:**

#### 1. Create Gallery with Items (15 min)
- Navigate to `/admin/galleries/create`
- Fill: title, company, period, description
- Upload thumbnail
- Select award (optional)
- Add 10 items (images)
- Submit
- ✅ Verify: gallery created with 10 items
- 📸 Screenshot: form filled + success message

#### 2. Bulk Upload (10 min)
- Create new gallery
- Upload 20 images at once
- ✅ Verify: all 20 items saved with correct sequence
- 📸 Screenshot: 20 items in gallery list

#### 3. Award Linking (10 min)
- Create gallery
- Link to award
- ✅ Verify: award name shows on gallery list
- Navigate to award detail (if award detail page exists)
- ✅ Verify: gallery listed under award
- 📸 Screenshot: linked relationship

#### 4. Update Gallery (5 min)
- Edit existing gallery
- Change company, period
- Add 3 more items
- ✅ Verify: changes saved, old items preserved
- 📸 Screenshot: updated gallery

#### 5. Delete Gallery (5 min)
- Delete gallery
- ✅ Verify: gallery deleted from list
- Check database: `SELECT * FROM gallery_items WHERE gallery_id = [deleted_id]`
- ✅ Verify: items cascade deleted (should be empty)
- 📸 Screenshot: delete confirmation

**Deliverables:**
- ✅ All test cases passed
- ✅ 5+ screenshots collected
- ✅ No console errors
- ✅ Database integrity verified

---

### STEP 5: Documentation (documentation-engineer)
**Time:** 20 minutes  
**Agent:** `documentation-engineer.md`  
**Depends on:** STEP 4 complete

**Tasks:**

#### A. Create API_ENDPOINTS.md (if not exists) or update (10 min)

Document gallery endpoints:

```markdown
## Galleries API

### Create Gallery (with items)
**POST** `/api/admin/galleries`

**Request:** `multipart/form-data`
- `title`: string (required)
- `description`: text (optional)
- `company`: string (optional, max 255)
- `period`: string (optional, max 100)
- `award_id`: integer (optional, foreign key to awards)
- `thumbnail`: file (required, image, max 2MB)
- `is_active`: boolean (default: true)
- `sort_order`: integer (default: 0)
- `items[0][type]`: enum (image, video)
- `items[0][file]`: file (max 5MB)
- `items[0][sequence]`: integer
- `items[1][...]`: ...

**Response:** `201 Created`
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Company Event 2024",
    "company": "PT ABC",
    "period": "Q1-2024",
    "thumbnail": "http://localhost/storage/galleries/thumbnails/abc.jpg",
    "award": {
      "id": 5,
      "title": "Best Company Award"
    },
    "items": [
      {
        "id": 1,
        "type": "image",
        "file_path": "http://localhost/storage/galleries/1/items/img1.jpg",
        "sequence": 0
      },
      {
        "id": 2,
        "type": "image",
        "file_path": "http://localhost/storage/galleries/1/items/img2.jpg",
        "sequence": 1
      }
    ],
    "items_count": 2,
    "is_active": true,
    "sort_order": 0
  },
  "message": "Gallery created successfully"
}
```

### List Galleries
**GET** `/api/admin/galleries`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Company Event 2024",
      "thumbnail": "...",
      "company": "PT ABC",
      "period": "Q1-2024",
      "award": { "id": 5, "title": "..." },
      "items_count": 10
    }
  ]
}
```

### Delete Gallery (cascade delete items)
**DELETE** `/api/admin/galleries/{id}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Gallery deleted successfully"
}
```
```

#### B. Update README.md (5 min)

Add to Recent Updates section:

```markdown
### Phase 9 - Gallery System Restructure (Oct 25, 2025) ✅

**Gallery Improvements:**
- ✅ Gallery as container for multiple items (not single image)
- ✅ Gallery Items: separate table for images/videos
- ✅ Award → Gallery: One-to-Many relationship (optional)
- ✅ Bulk upload: up to 20 items per gallery
- ✅ New fields: company, period, thumbnail
- ✅ Migration: preserved existing data during restructure
```

#### C. Update PROJECT_STATUS.md (5 min)

Mark Phase 9 complete:

```markdown
## Phase 9: Gallery System Restructure ✅ (Oct 25, 2025)

**Completed:**
- [x] Database migration (galleries + gallery_items)
- [x] Models updated (Gallery, GalleryItem, Award)
- [x] Backend API (GalleryController, GalleryItemController)
- [x] Frontend components (GalleryForm, GalleryItemsSection, GalleryList)
- [x] Pinia store (FormData handling)
- [x] QA testing (all test cases passed)
- [x] Documentation updated

**Breaking Changes:**
- Dropped `award_gallery` pivot table
- `galleries.image` removed, replaced with `gallery_items` table
- Award ↔ Gallery relationship changed from Many-to-Many to One-to-Many

**Estimated Time:** 180-240 minutes
**Actual Time:** [QA to fill after completion]
```

**Deliverables:**
- ✅ API_ENDPOINTS.md updated
- ✅ README.md updated (Recent Updates)
- ✅ PROJECT_STATUS.md updated (Phase 9 marked complete)

---

## 📊 Success Checklist (orchestrator verifies)

**orchestrator** must verify all items before marking Phase 9 complete:

### Database:
- [ ] `award_gallery` pivot table dropped
- [ ] `galleries` table restructured (company, period, thumbnail, award_id added; image, category removed)
- [ ] `gallery_items` table created
- [ ] Foreign keys correct (gallery.award_id → awards.id, gallery_items.gallery_id → galleries.id)
- [ ] Data migrated (no loss)

### Backend:
- [ ] Gallery.php updated (award() belongsTo, items() hasMany)
- [ ] GalleryItem.php created
- [ ] Award.php updated (galleries() hasMany)
- [ ] GalleryController handles bulk upload (store method)
- [ ] GalleryItemController CRUD complete
- [ ] GalleryResource includes items, award
- [ ] GalleryItemResource created
- [ ] Routes configured in api.php

### Frontend:
- [ ] GalleryForm has all new fields (company, period, award dropdown, thumbnail, description)
- [ ] GalleryItemsSection allows adding up to 20 items
- [ ] GalleryList shows thumbnail, company, period, award, items_count
- [ ] stores/galleries.js handles FormData

### QA:
- [ ] Can create gallery with 10+ items ✅
- [ ] Can upload 20 images in bulk ✅
- [ ] Can link gallery to award (optional) ✅
- [ ] Can update without breaking items ✅
- [ ] Delete cascades to items ✅
- [ ] All screenshots captured (5+) ✅

### Documentation:
- [ ] API endpoints documented
- [ ] README updated
- [ ] PROJECT_STATUS updated

---

## ⚠️ Important Notes

### Data Preservation:
- Migration MUST backup existing data
- Existing `galleries.image` migrated to `gallery_items.file_path`
- Existing `award_gallery` pivot migrated to `galleries.award_id`
- Zero data loss acceptable

### File Storage:
- Gallery thumbnails: `storage/app/public/galleries/thumbnails/`
- Gallery items: `storage/app/public/galleries/{gallery_id}/items/`
- Max sizes: thumbnail 2MB, items 5MB each

### Validation:
- Thumbnail: required on create
- Items: minimum 1 item, maximum 20 items per gallery
- Company/Period: optional fields
- Award: optional relationship

---

## 🎓 Learning Points

This task demonstrates:
1. **Database refactoring** - Many-to-many → One-to-many
2. **Data migration** - Preserving data during schema changes
3. **Nested data handling** - Gallery + Items
4. **Bulk uploads** - Multiple files in single request
5. **Dynamic forms** - Vue array manipulation
6. **Cascade deletes** - Foreign key constraints
7. **Multi-agent coordination** - 5 agents working in sequence

---

**Total Estimated Time:** 180-240 minutes  
**Complexity:** HIGH (database restructure + nested data)  
**Risk:** MEDIUM (requires data migration)

**Status:** Ready for execution by orchestrator  
**Last Updated:** October 25, 2025
