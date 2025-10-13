# CLAUDE.md - Backend API

This file provides guidance to Claude Code when working with the Laravel backend API.

**Context:** Read root `/CLAUDE.md` first for general project architecture.

## Quick Reference

**Framework:** Laravel 10 | **PHP:** 8.2 | **Database:** MySQL 8 (portfolio_v2)
**Server:** XAMPP Apache (Port 80) - **DO NOT** use `php artisan serve`
**API Base:** `http://localhost/Portfolio_v2/backend/public/api`

## Architecture Overview

### Core Pattern: RESTful API with i18n Support

This backend implements full internationalization (EN/ID) with:

1. **Slug-based routing** - Public endpoints use slugs (`/posts/{slug}`), not IDs
2. **Trait-based models** - Models use `HasSeoFields`, `HasSlug`, `SoftDeletes`
3. **Translation system** - Separate translation tables (`post_translations`)
4. **Eager loading** - Always load relationships to avoid N+1 queries
5. **Resource transformation** - All responses via API Resources
6. **Validation layer** - Form Requests for all input validation

### Request Flow
```
Request → Route → Controller → FormRequest (validate)
→ Model (with traits) → Resource (transform) → JSON Response
```

## Model Trait System

### 1. HasSeoFields (`app/Traits/HasSeoFields.php`)

Provides comprehensive SEO/GEO functionality:

```php
use App\Traits\HasSeoFields;

class Post extends Model {
    use HasSeoFields;
}

// Available methods:
$post->getSeoMetaAttribute()       // ['title', 'description', 'keywords', 'canonical', 'robots']
$post->getOgMetaAttribute()        // ['title', 'description', 'image', 'type', 'url']
$post->getSchemaMarkupAttribute()  // JSON-LD structured data
$post->calculateSeoScore()         // Returns 0-100 score
$post->generateAiSummary()         // GEO-optimized summary

// Auto-calculated on save:
$post->save(); // seo_score automatically updated
```

**SEO Fields Available:**
- `meta_title`, `meta_description`, `meta_keywords`
- `og_title`, `og_description`, `og_image`
- `schema_markup` (JSON), `canonical_url`
- `ai_summary` (GEO), `faq_schema` (JSON)
- `seo_score` (auto-calculated), `index_follow` (boolean)

### 2. HasSlug (Spatie\Sluggable)

Auto-generates slugs from titles:

```php
use Spatie\Sluggable\HasSlug;

public function getSlugOptions(): SlugOptions {
    return SlugOptions::create()
        ->generateSlugsFrom('title')
        ->saveSlugsTo('slug');
}

// Slug-based routing
public function getRouteKeyName() {
    return 'slug';
}
```

### 3. SoftDeletes (for Post/Project)

Trash/restore functionality:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

$post->delete();       // Soft delete
$post->restore();      // Restore
$post->forceDelete();  // Permanent delete
Post::withTrashed()->get(); // Include trashed
```

### 4. Model Scopes

Common query scopes for filtering:

```php
// Post.php
public function scopePublished($query) {
    return $query->where('published', true)
                 ->whereNotNull('published_at')
                 ->where('published_at', '<=', now());
}

// Usage:
$posts = Post::published()->with('category')->paginate(15);
$posts = Post::published()->free()->latest()->get();
```

## Translation System

Posts and Projects support i18n via `post_translations` / `project_translations`:

```php
// Relationships
public function translations() {
    return $this->hasMany(PostTranslation::class);
}

public function translation($language = 'en') {
    return $this->translations()->where('language', $language)->first();
}

// Always eager load:
$posts = Post::with(['category', 'translations'])->get();

// In controller:
$language = $request->query('lang',
    $request->header('Accept-Language', 'en'));
```

## Controller Patterns

### Standard Structure (PostController Example)

```php
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    // PUBLIC: List posts
    public function index(Request $request): JsonResponse
    {
        // 1. Get language preference
        $language = $request->query('lang',
            $request->header('Accept-Language', 'en'));
        $language = strtolower(substr($language, 0, 2));

        // 2. Build query with eager loading
        $query = Post::with(['category', 'translations'])
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        // 3. Apply filters
        if ($request->has('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->query('category'));
            });
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // 4. Sort and paginate
        $query->orderBy('published_at', 'desc');
        $perPage = min($request->query('per_page', 15), 50);
        $posts = $query->paginate($perPage);

        // 5. Return with pagination meta
        return response()->json([
            'data' => PostResource::collection($posts)
                ->additional(['lang' => $language]),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
            'links' => [
                'first' => $posts->url(1),
                'last' => $posts->url($posts->lastPage()),
                'prev' => $posts->previousPageUrl(),
                'next' => $posts->nextPageUrl(),
            ],
        ]);
    }

    // PUBLIC: Show single post by slug
    public function show(Request $request, string $slug): JsonResponse
    {
        $language = $request->query('lang',
            $request->header('Accept-Language', 'en'));

        $post = Post::with(['category', 'translations'])
            ->where('slug', $slug)
            ->where('published', true)
            ->first();

        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'error' => 'The requested post does not exist.'
            ], 404);
        }

        // Track views
        $post->incrementViews();

        return response()->json([
            'data' => (new PostResource($post))
                ->additional(['lang' => $language])
        ]);
    }

    // PROTECTED: Create post (auth:sanctum)
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language' => ['required', Rule::in(['en', 'id'])],
            'translations.*.title' => ['required', 'string'],
            'translations.*.content' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $post = Post::create($request->only([
                'category_id', 'title', 'slug', 'content',
                'excerpt', 'featured_image', 'tags',
                'is_premium', 'published', 'published_at'
            ]));

            foreach ($request->input('translations', []) as $translation) {
                $post->translations()->create($translation);
            }

            DB::commit();
            $post->load(['category', 'translations']);

            return response()->json([
                'message' => 'Post created successfully',
                'data' => new PostResource($post)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create post',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

### Response Format Standards

**Success:**
```json
{
  "data": {...},
  "message": "Operation successful"
}
```

**Error:**
```json
{
  "message": "Error description",
  "error": "Detailed error message"
}
```

**Validation Error:**
```json
{
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

## Database Patterns

### Eager Loading (Critical!)

**Always use `with()` to avoid N+1 queries:**

```php
// ❌ Bad - N+1 queries (1 + N additional queries)
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->category->name; // N queries
}

// ✅ Good - Only 2 queries total
$posts = Post::with('category')->get();

// ✅ Best - Multiple relationships
$posts = Post::with(['category', 'translations', 'author'])->get();

// ✅ Nested relationships
$posts = Post::with('category.parent')->get();

// ✅ Conditional eager loading
$posts = Post::with(['translations' => function($q) use ($lang) {
    $q->where('language', $lang);
}])->get();
```

### Migration Conventions

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('content');

    // Foreign keys with cascade
    $table->foreignId('category_id')
          ->constrained()
          ->cascadeOnDelete();

    // Indexes for frequently queried fields
    $table->index('slug');
    $table->index('published_at');
    $table->index(['category_id', 'published']);

    $table->timestamps();
    $table->softDeletes(); // For trash functionality
});
```

## Essential Commands

```bash
# Database
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Nuclear reset with data
php artisan db:seed                    # Seed only

# Code Generation
php artisan make:model Post -mcr       # Model + Migration + Controller
php artisan make:request StorePostRequest
php artisan make:resource PostResource
php artisan make:resource PostCollection

# Development
php artisan route:list                 # All routes
php artisan route:list --path=api      # API routes only
php artisan tinker                     # Interactive console
composer dump-autoload                 # Reload autoloader

# Testing
php artisan test                       # All tests
php artisan test --filter=PostTest     # Single test
php artisan test --coverage            # With coverage

# Cache Management
php artisan cache:clear                # Clear app cache
php artisan config:clear               # Clear config cache
php artisan route:clear                # Clear route cache
php artisan optimize:clear             # Clear all caches
```

## Testing Patterns

```php
// tests/Feature/PostControllerTest.php
use App\Models\{User, Post};
use Illuminate\Foundation\Testing\RefreshDatabase;

test('can list published posts', function () {
    Post::factory()->published()->count(3)->create();

    $response = $this->getJson('/api/posts');

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['*' => ['id', 'title', 'slug']],
                 'meta',
                 'links'
             ]);
});

test('can create post with translations', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/posts', [
            'title' => 'Test Post',
            'content' => 'Content here',
            'category_id' => 1,
            'translations' => [
                ['language' => 'en', 'title' => 'Test', 'content' => 'English'],
                ['language' => 'id', 'title' => 'Tes', 'content' => 'Indonesian']
            ]
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('posts', ['title' => 'Test Post']);
    $this->assertDatabaseHas('post_translations', ['language' => 'en']);
});

test('increments views on post show', function () {
    $post = Post::factory()->published()->create(['views' => 0]);

    $this->getJson("/api/posts/{$post->slug}");

    expect($post->fresh()->views)->toBe(1);
});
```

## Common Issues & Solutions

### "Class not found" after creating file
```bash
composer dump-autoload
```

### Migration fails
```bash
# Check migration order - parent tables first
# Or nuclear option:
php artisan migrate:fresh --seed
```

### Route not found
```bash
php artisan route:clear
php artisan optimize:clear
```

### Authentication not working
```php
// Ensure auth:sanctum middleware
// Check Authorization header
Authorization: Bearer {token}
```

### N+1 query detection
```bash
# Install Laravel Debugbar (dev only)
composer require barryvdh/laravel-debugbar --dev

# Check queries in browser toolbar
```

## File Organization

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/                  # All API controllers
│   ├── Resources/                # API transformers
│   └── Requests/                 # Form validation
├── Models/                       # Eloquent models
└── Traits/
    └── HasSeoFields.php          # SEO trait

database/
├── migrations/                   # Schema definitions
├── seeders/                      # Sample data
└── factories/                    # Test data

routes/
└── api.php                       # API routes

tests/
├── Feature/                      # Integration tests
└── Unit/                         # Unit tests
```

## Best Practices Checklist

**Models:**
- [ ] Use appropriate traits (HasSeoFields, HasSlug, SoftDeletes)
- [ ] Define relationships with proper type hints
- [ ] Add query scopes for common filters
- [ ] Set proper `$fillable` or `$guarded`
- [ ] Define `$casts` for special types
- [ ] Use `getRouteKeyName()` for slug routing

**Controllers:**
- [ ] Eager load all relationships
- [ ] Support language parameter (`lang` query or `Accept-Language` header)
- [ ] Use `Validator::make()` or FormRequest
- [ ] Wrap multi-table ops in `DB::transaction()`
- [ ] Return via API Resources
- [ ] Use proper HTTP status codes (200, 201, 404, 422, 500)

**Database:**
- [ ] Add indexes to foreign keys
- [ ] Add indexes to frequently queried fields
- [ ] Use `softDeletes()` for trash functionality
- [ ] Use `foreignId()->constrained()->cascadeOnDelete()`

**Testing:**
- [ ] Test all CRUD operations
- [ ] Test authentication/authorization
- [ ] Test validation rules
- [ ] Test eager loading (no N+1)
- [ ] Test translations if applicable

---

**Last Updated:** October 13, 2025
**See also:** `/CLAUDE.md` (root), `frontend/CLAUDE.md`
