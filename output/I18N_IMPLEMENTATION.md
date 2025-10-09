# Internationalization (i18n) Implementation Guide

**Version:** 1.0  
**Date:** October 3, 2025  
**Languages:** English (en) & Bahasa Indonesia (id)

---

## 🌍 Overview

Portfolio V2 supports bilingual content for **Blog Posts** and **Projects**:
- 🇬🇧 **English** (en) - Primary language
- 🇮🇩 **Bahasa Indonesia** (id) - Secondary language

**Other content** (Awards, Services, Gallery, Contact) remains **English only** (single language).

---

## 📊 Database Strategy

### **Approach: Translation Tables (Recommended)**

**Why?**
- ✅ Clean separation of translatable vs non-translatable content
- ✅ Easy to add more languages later
- ✅ Maintains referential integrity
- ✅ Keeps base tables lean

### **Schema Design:**

#### **Base Tables (Language-Agnostic):**
```sql
-- posts table (language-agnostic fields)
posts:
  - id
  - category_id
  - author_id
  - featured_image
  - published_at
  - is_featured
  - status
  - view_count
  - created_at
  - updated_at

-- projects table (language-agnostic fields)
projects:
  - id
  - category
  - featured_image
  - project_url
  - github_url
  - is_featured
  - display_order
  - status
  - created_at
  - updated_at
```

#### **Translation Tables:**
```sql
-- post_translations table
CREATE TABLE post_translations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  excerpt TEXT,
  content LONGTEXT NOT NULL,
  meta_title VARCHAR(255),
  meta_description TEXT,
  og_title VARCHAR(255),
  og_description TEXT,
  canonical_url VARCHAR(255),
  ai_summary TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_post_lang_slug (post_id, language, slug),
  INDEX idx_language (language),
  INDEX idx_slug (slug)
);

-- project_translations table
CREATE TABLE project_translations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  description TEXT,
  content LONGTEXT,
  tech_stack JSON,
  meta_title VARCHAR(255),
  meta_description TEXT,
  og_title VARCHAR(255),
  og_description TEXT,
  canonical_url VARCHAR(255),
  ai_summary TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  UNIQUE KEY unique_project_lang_slug (project_id, language, slug),
  INDEX idx_language (language),
  INDEX idx_slug (slug)
);
```

---

## 🔧 Backend Implementation (Laravel)

### **1. Migration Files**

**Create migrations:**
```bash
php artisan make:migration create_post_translations_table
php artisan make:migration create_project_translations_table
```

**Migration content:** (See schema above)

### **2. Model Relationships**

**Post Model:**
```php
// app/Models/Post.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    // Existing fields...
    
    /**
     * Get all translations for this post
     */
    public function translations()
    {
        return $this->hasMany(PostTranslation::class);
    }
    
    /**
     * Get translation for specific language
     */
    public function translation($language = 'en')
    {
        return $this->translations()->where('language', $language)->first();
    }
    
    /**
     * Get translation or fallback to English
     */
    public function translate($language = 'en')
    {
        return $this->translation($language) ?? $this->translation('en');
    }
    
    /**
     * Scope: Get posts with translations
     */
    public function scopeWithTranslation($query, $language = 'en')
    {
        return $query->with(['translations' => function ($q) use ($language) {
            $q->where('language', $language);
        }]);
    }
}
```

**PostTranslation Model:**
```php
// app/Models/PostTranslation.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSeoFields;

class PostTranslation extends Model
{
    use HasSeoFields;
    
    protected $fillable = [
        'post_id', 'language', 'title', 'slug', 'excerpt', 'content',
        'meta_title', 'meta_description', 'og_title', 'og_description',
        'canonical_url', 'ai_summary'
    ];
    
    /**
     * Belongs to post
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
    
    /**
     * Get language name
     */
    public function getLanguageNameAttribute()
    {
        return [
            'en' => 'English',
            'id' => 'Bahasa Indonesia'
        ][$this->language] ?? $this->language;
    }
}
```

**Same pattern for Project & ProjectTranslation.**

### **3. API Resources**

**PostResource:**
```php
// app/Http/Resources/PostResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        $language = $request->input('lang', 'en');
        $translation = $this->translate($language);
        
        return [
            'id' => $this->id,
            'language' => $language,
            'available_languages' => $this->translations->pluck('language'),
            
            // Translated fields
            'title' => $translation->title,
            'slug' => $translation->slug,
            'excerpt' => $translation->excerpt,
            'content' => $translation->content,
            
            // Language-agnostic fields
            'featured_image' => $this->featured_image,
            'published_at' => $this->published_at,
            'is_featured' => $this->is_featured,
            'status' => $this->status,
            'view_count' => $this->view_count,
            
            // SEO fields (translated)
            'seo' => [
                'meta_title' => $translation->meta_title,
                'meta_description' => $translation->meta_description,
                'og_title' => $translation->og_title,
                'og_description' => $translation->og_description,
                'canonical_url' => $translation->canonical_url,
                'ai_summary' => $translation->ai_summary,
                'seo_score' => $translation->seo_score,
            ],
            
            // Relations
            'category' => new CategoryResource($this->whenLoaded('category')),
            'author' => new UserResource($this->whenLoaded('author')),
        ];
    }
}
```

### **4. API Endpoints**

**Updated routes:**
```php
// routes/api.php

// Public API - with language parameter
Route::get('/posts', [PostController::class, 'index']); // ?lang=en or ?lang=id
Route::get('/posts/{slug}', [PostController::class, 'show']); // ?lang=en or ?lang=id
Route::get('/projects', [ProjectController::class, 'index']); // ?lang=en or ?lang=id
Route::get('/projects/{slug}', [ProjectController::class, 'show']); // ?lang=en or ?lang=id

// Admin API - manage translations
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // Get all translations for a post
    Route::get('/posts/{post}/translations', [PostTranslationController::class, 'index']);
    
    // Create/Update translation
    Route::post('/posts/{post}/translations/{language}', [PostTranslationController::class, 'store']);
    Route::put('/posts/{post}/translations/{language}', [PostTranslationController::class, 'update']);
    
    // Delete translation
    Route::delete('/posts/{post}/translations/{language}', [PostTranslationController::class, 'destroy']);
    
    // Same for projects
    Route::get('/projects/{project}/translations', [ProjectTranslationController::class, 'index']);
    Route::post('/projects/{project}/translations/{language}', [ProjectTranslationController::class, 'store']);
    Route::put('/projects/{project}/translations/{language}', [ProjectTranslationController::class, 'update']);
    Route::delete('/projects/{project}/translations/{language}', [ProjectTranslationController::class, 'destroy']);
});
```

**Controller examples:**
```php
// app/Http/Controllers/Api/PostController.php
public function index(Request $request)
{
    $language = $request->input('lang', 'en');
    
    $posts = Post::query()
        ->withTranslation($language)
        ->where('status', 'published')
        ->latest('published_at')
        ->paginate(12);
    
    return PostResource::collection($posts);
}

public function show(Request $request, $slug)
{
    $language = $request->input('lang', 'en');
    
    // Find by translated slug
    $translation = PostTranslation::where('slug', $slug)
        ->where('language', $language)
        ->firstOrFail();
    
    $post = $translation->post()
        ->with(['category', 'author', 'translations'])
        ->firstOrFail();
    
    return new PostResource($post);
}
```

---

## ⚛️ Frontend Implementation (Vue 3)

### **1. Install Vue i18n**

```bash
npm install vue-i18n@9
```

### **2. i18n Configuration**

**Create i18n instance:**
```javascript
// src/i18n/index.js
import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import id from './locales/id.json';

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('language') || 'en',
  fallbackLocale: 'en',
  messages: {
    en,
    id
  }
});

export default i18n;
```

**Locale files:**
```json
// src/i18n/locales/en.json
{
  "nav": {
    "home": "Home",
    "projects": "Projects",
    "blog": "Blog",
    "gallery": "Gallery",
    "contact": "Contact"
  },
  "blog": {
    "title": "Blog",
    "readMore": "Read more",
    "allPosts": "All posts",
    "category": "Category",
    "publishedOn": "Published on"
  },
  "projects": {
    "title": "Projects",
    "viewProject": "View project",
    "liveDemo": "Live demo",
    "sourceCode": "Source code"
  },
  "common": {
    "loading": "Loading...",
    "error": "An error occurred",
    "noResults": "No results found"
  }
}
```

```json
// src/i18n/locales/id.json
{
  "nav": {
    "home": "Beranda",
    "projects": "Proyek",
    "blog": "Blog",
    "gallery": "Galeri",
    "contact": "Kontak"
  },
  "blog": {
    "title": "Blog",
    "readMore": "Baca selengkapnya",
    "allPosts": "Semua artikel",
    "category": "Kategori",
    "publishedOn": "Diterbitkan pada"
  },
  "projects": {
    "title": "Proyek",
    "viewProject": "Lihat proyek",
    "liveDemo": "Demo langsung",
    "sourceCode": "Kode sumber"
  },
  "common": {
    "loading": "Memuat...",
    "error": "Terjadi kesalahan",
    "noResults": "Tidak ada hasil"
  }
}
```

### **3. Language Switcher Component**

```vue
<!-- src/components/LanguageSwitcher.vue -->
<template>
  <div class="relative">
    <button
      @click="isOpen = !isOpen"
      class="flex items-center gap-2 px-3 py-2 rounded-lg
             text-gray-700 dark:text-gray-300
             hover:bg-gray-100 dark:hover:bg-gray-800
             transition-colors"
      aria-label="Change language"
    >
      <GlobeIcon class="w-5 h-5" />
      <span class="hidden sm:inline">{{ currentLanguage.name }}</span>
      <ChevronDownIcon class="w-4 h-4" />
    </button>

    <!-- Dropdown -->
    <Transition name="dropdown">
      <div
        v-if="isOpen"
        class="absolute right-0 mt-2 w-48 rounded-lg
               bg-white dark:bg-gray-800
               border border-gray-200 dark:border-gray-700
               shadow-lg z-50"
      >
        <button
          v-for="lang in languages"
          :key="lang.code"
          @click="changeLanguage(lang.code)"
          class="w-full px-4 py-2 text-left flex items-center gap-3
                 hover:bg-gray-100 dark:hover:bg-gray-700
                 transition-colors first:rounded-t-lg last:rounded-b-lg"
          :class="{ 'bg-primary-50 dark:bg-primary-900/20': locale === lang.code }"
        >
          <span class="text-2xl">{{ lang.flag }}</span>
          <span class="font-medium">{{ lang.name }}</span>
          <CheckIcon
            v-if="locale === lang.code"
            class="w-4 h-4 ml-auto text-primary-600"
          />
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { GlobeIcon, ChevronDownIcon, CheckIcon } from '@heroicons/vue/24/outline';

const { locale } = useI18n();
const isOpen = ref(false);

const languages = [
  { code: 'en', name: 'English', flag: '🇬🇧' },
  { code: 'id', name: 'Bahasa Indonesia', flag: '🇮🇩' }
];

const currentLanguage = computed(() => {
  return languages.find(lang => lang.code === locale.value) || languages[0];
});

const changeLanguage = (langCode) => {
  locale.value = langCode;
  localStorage.setItem('language', langCode);
  isOpen.value = false;
  
  // Reload current page with new language
  window.location.reload();
};
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 150ms ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
```

### **4. API Composable with Language**

```javascript
// src/composables/useApi.js
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

export function useApi() {
  const { locale } = useI18n();
  const baseURL = 'http://localhost/Portfolio_v2/backend/public/api';
  
  const fetchPosts = async (params = {}) => {
    const url = new URL(`${baseURL}/posts`);
    url.searchParams.append('lang', locale.value);
    
    Object.entries(params).forEach(([key, value]) => {
      url.searchParams.append(key, value);
    });
    
    const response = await fetch(url);
    return response.json();
  };
  
  const fetchPost = async (slug) => {
    const url = new URL(`${baseURL}/posts/${slug}`);
    url.searchParams.append('lang', locale.value);
    
    const response = await fetch(url);
    return response.json();
  };
  
  const fetchProjects = async (params = {}) => {
    const url = new URL(`${baseURL}/projects`);
    url.searchParams.append('lang', locale.value);
    
    Object.entries(params).forEach(([key, value]) => {
      url.searchParams.append(key, value);
    });
    
    const response = await fetch(url);
    return response.json();
  };
  
  const fetchProject = async (slug) => {
    const url = new URL(`${baseURL}/projects/${slug}`);
    url.searchParams.append('lang', locale.value);
    
    const response = await fetch(url);
    return response.json();
  };
  
  return {
    fetchPosts,
    fetchPost,
    fetchProjects,
    fetchProject
  };
}
```

### **5. Usage in Components**

```vue
<!-- src/views/Blog.vue -->
<template>
  <div>
    <h1>{{ $t('blog.title') }}</h1>
    
    <!-- Posts Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <BlogCard
        v-for="post in posts"
        :key="post.id"
        :post="post"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/composables/useApi';
import BlogCard from '@/components/BlogCard.vue';

const { locale } = useI18n();
const { fetchPosts } = useApi();
const posts = ref([]);

const loadPosts = async () => {
  const response = await fetchPosts();
  posts.value = response.data;
};

onMounted(loadPosts);

// Reload when language changes
watch(locale, loadPosts);
</script>
```

---

## 🎨 Admin Panel - Translation Editor

### **Translation Editor Component:**

```vue
<!-- src/admin/components/TranslationEditor.vue -->
<template>
  <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
    <h3 class="text-lg font-semibold mb-4">Translations</h3>
    
    <!-- Language Tabs -->
    <div class="flex gap-2 mb-6">
      <button
        v-for="lang in languages"
        :key="lang.code"
        @click="currentLang = lang.code"
        class="px-4 py-2 rounded-lg font-medium transition-colors"
        :class="currentLang === lang.code
          ? 'bg-primary-600 text-white'
          : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
        "
      >
        {{ lang.flag }} {{ lang.name }}
        <span
          v-if="hasTranslation(lang.code)"
          class="ml-2 text-xs"
        >✓</span>
      </button>
    </div>
    
    <!-- Translation Form for Current Language -->
    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">
          Title ({{ currentLangName }})
          <span class="text-red-500">*</span>
        </label>
        <input
          v-model="translations[currentLang].title"
          type="text"
          class="w-full rounded-lg border-gray-300 focus:border-primary-500"
          required
        />
      </div>
      
      <div>
        <label class="block text-sm font-medium mb-1">
          Slug ({{ currentLangName }})
          <span class="text-red-500">*</span>
        </label>
        <input
          v-model="translations[currentLang].slug"
          type="text"
          class="w-full rounded-lg border-gray-300 focus:border-primary-500"
          required
        />
      </div>
      
      <div>
        <label class="block text-sm font-medium mb-1">
          Excerpt ({{ currentLangName }})
        </label>
        <textarea
          v-model="translations[currentLang].excerpt"
          rows="3"
          class="w-full rounded-lg border-gray-300 focus:border-primary-500"
        ></textarea>
      </div>
      
      <div>
        <label class="block text-sm font-medium mb-1">
          Content ({{ currentLangName }})
          <span class="text-red-500">*</span>
        </label>
        <RichTextEditor
          v-model="translations[currentLang].content"
        />
      </div>
      
      <!-- SEO Fields -->
      <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
        <h4 class="font-semibold mb-3">SEO ({{ currentLangName }})</h4>
        
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium mb-1">Meta Title</label>
            <input
              v-model="translations[currentLang].meta_title"
              type="text"
              class="w-full rounded-lg border-gray-300"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Meta Description</label>
            <textarea
              v-model="translations[currentLang].meta_description"
              rows="2"
              class="w-full rounded-lg border-gray-300"
            ></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  }
});

const emit = defineEmits(['update:modelValue']);

const languages = [
  { code: 'en', name: 'English', flag: '🇬🇧' },
  { code: 'id', name: 'Bahasa Indonesia', flag: '🇮🇩' }
];

const currentLang = ref('en');
const translations = ref({
  en: props.modelValue.en || {},
  id: props.modelValue.id || {}
});

const currentLangName = computed(() => {
  return languages.find(l => l.code === currentLang.value)?.name || '';
});

const hasTranslation = (langCode) => {
  return translations.value[langCode]?.title && translations.value[langCode]?.content;
};

watch(translations, (newVal) => {
  emit('update:modelValue', newVal);
}, { deep: true });
</script>
```

---

## ✅ Implementation Checklist

### Backend:
- [ ] Create migration: `post_translations` table
- [ ] Create migration: `project_translations` table
- [ ] Create `PostTranslation` model with `HasSeoFields` trait
- [ ] Create `ProjectTranslation` model with `HasSeoFields` trait
- [ ] Update `Post` model with translation relationships
- [ ] Update `Project` model with translation relationships
- [ ] Update `PostResource` to handle language parameter
- [ ] Update `ProjectResource` to handle language parameter
- [ ] Create `PostTranslationController`
- [ ] Create `ProjectTranslationController`
- [ ] Update API routes with language support
- [ ] Create seeders with bilingual data

### Frontend:
- [ ] Install `vue-i18n@9`
- [ ] Create i18n config and locale files
- [ ] Create `LanguageSwitcher.vue` component
- [ ] Update `useApi` composable with language support
- [ ] Add language switcher to navigation
- [ ] Update blog/project pages to support language switching
- [ ] Create `TranslationEditor.vue` for admin
- [ ] Integrate translation editor in admin forms
- [ ] Test language switching
- [ ] Test SEO with different languages

### Testing:
- [ ] Test API returns correct language
- [ ] Test language fallback (id → en if not found)
- [ ] Test admin CRUD for translations
- [ ] Test SEO fields per language
- [ ] Test language switcher UI
- [ ] Test URL slugs per language
- [ ] Test performance with translations

---

## 🎯 Best Practices

1. **Always provide English (en) as fallback**
2. **Use translation tables** (not column duplication)
3. **Slug must be unique per language** (not per post)
4. **SEO fields are language-specific**
5. **Images/media are shared** across languages
6. **Admin must fill both languages** (or at least English)
7. **Cache API responses per language**
8. **Use `?lang=` parameter** (don't use subdomain/path)

---

## 📝 Notes

- **Only Blog & Projects** are translated
- **Awards, Services, Gallery, Contact** remain English-only
- **UI labels** (buttons, nav, etc.) use vue-i18n
- **Content** (posts, projects) use database translations
- **Default language:** English (en)
- **Fallback:** Always fall back to English if translation missing

---

**End of i18n Implementation Guide**
