# CLAUDE.md - Backend (Laravel)

**IMPORTANT**: This is the backend-specific CLAUDE.md. Always read the root `/CLAUDE.md` first for general project context.

## Laravel Backend Specific Guidelines

### Laravel Version & Stack
- Laravel 10.x
- PHP 8.2
- MySQL (via XAMPP)
- Laravel Jetstream (authentication)
- Laravel Sanctum (API authentication)
- Livewire 3.x (dynamic components)

---

## Code Quality Standards

### PSR-12 Compliance
- Follow PSR-12 coding standards
- Use 4 spaces for indentation (not tabs)
- Opening braces on same line for classes/methods
- Use strict types: `declare(strict_types=1);`

### Type Hints
**ALWAYS** use type hints for parameters and return types:

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $user = User::create($request->validated());
    return response()->json(new UserResource($user), 201);
}
```

---

## Laravel Patterns & Conventions

### Controllers

**Resource Controllers** are preferred:
```php
php artisan make:controller PostController --resource
```

**API Controllers** should:
- Return `JsonResponse` with proper HTTP status codes
- Use API Resources for transformation
- Use Form Requests for validation
- Handle exceptions gracefully

**Example:**
```php
class PostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = Post::with('author', 'category')
            ->latest()
            ->paginate(15);
            
        return response()->json([
            'success' => true,
            'data' => PostResource::collection($posts)
        ]);
    }
    
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::create($request->validated());
        
        return response()->json([
            'success' => true,
            'data' => new PostResource($post),
            'message' => 'Post created successfully'
        ], 201);
    }
    
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $post->update($request->validated());
        
        return response()->json([
            'success' => true,
            'data' => new PostResource($post),
            'message' => 'Post updated successfully'
        ]);
    }
    
    public function destroy(Post $post): JsonResponse
    {
        $post->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully'
        ]);
    }
}
```

### Models

**Eloquent Best Practices:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;
    
    // Mass assignment protection
    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'published_at',
        'user_id',
        'category_id',
    ];
    
    // Attributes hidden from JSON
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    
    // Attribute casting
    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
    ];
    
    // Relationships
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
    
    // Scopes
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }
    
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
```

### Form Requests

**ALWAYS** use Form Requests for validation:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or implement authorization logic
    }
    
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:posts,slug',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'category_id' => 'required|exists:categories,id',
            'published_at' => 'nullable|date',
            'is_featured' => 'boolean',
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.required' => 'Post title is required',
            'slug.unique' => 'This slug is already taken',
        ];
    }
}
```

### API Resources

**Transform Eloquent models** with API Resources:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'is_featured' => $this->is_featured,
            
            // Relationships (only when loaded)
            'author' => new UserResource($this->whenLoaded('author')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'comments_count' => $this->when(
                $this->relationLoaded('comments'), 
                fn() => $this->comments->count()
            ),
        ];
    }
}
```

---

## Database & Migrations

### Migration Best Practices

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            
            // Foreign keys
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            
            // Indexes
            $table->index('published_at');
            $table->index('is_featured');
            
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

### Factories

**Use factories for testing data:**

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence();
        
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => fake()->paragraphs(5, true),
            'excerpt' => fake()->sentence(),
            'published_at' => fake()->dateTimeBetween('-1 year', '+1 month'),
            'is_featured' => fake()->boolean(20), // 20% chance
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
        ];
    }
    
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }
    
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
```

---

## Testing

### Feature Tests

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_can_list_posts(): void
    {
        Post::factory()->count(5)->create();
        
        $response = $this->getJson('/api/posts');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => ['id', 'title', 'slug', 'excerpt']
                     ]
                 ]);
    }
    
    public function test_can_create_post(): void
    {
        $user = User::factory()->create();
        
        $data = [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
            'category_id' => Category::factory()->create()->id,
        ];
        
        $response = $this->actingAs($user)
                         ->postJson('/api/posts', $data);
        
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => ['title' => 'Test Post']
                 ]);
        
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);
    }
    
    public function test_cannot_create_post_without_authentication(): void
    {
        $response = $this->postJson('/api/posts', []);
        
        $response->assertStatus(401);
    }
}
```

---

## Performance Optimization

### Eager Loading (N+1 Prevention)

**ALWAYS** eager load relationships:

```php
// ❌ BAD - N+1 query problem
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name; // Extra query for EACH post
}

// ✅ GOOD - Only 2 queries
$posts = Post::with('author')->get();
foreach ($posts as $post) {
    echo $post->author->name; // No extra queries
}

// ✅ BEST - Multiple relationships
$posts = Post::with(['author', 'category', 'comments'])
    ->latest()
    ->paginate(15);
```

### Query Optimization

```php
// Use select to fetch only needed columns
$users = User::select('id', 'name', 'email')->get();

// Use pagination for large datasets
$posts = Post::latest()->paginate(20);

// Use cursor pagination for large tables
$posts = Post::latest()->cursorPaginate(20);

// Use chunk for processing large datasets
Post::chunk(100, function ($posts) {
    foreach ($posts as $post) {
        // Process post
    }
});
```

### Caching

```php
use Illuminate\Support\Facades\Cache;

// Cache for 1 hour
$posts = Cache::remember('posts.all', 3600, function () {
    return Post::with('author')->latest()->get();
});

// Cache forever (until manually cleared)
$categories = Cache::rememberForever('categories.all', function () {
    return Category::all();
});

// Clear cache
Cache::forget('posts.all');
Cache::flush(); // Clear all cache
```

---

## Security

### Authentication & Authorization

```php
// Use Laravel Sanctum for API authentication
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Check authorization in controller
public function update(UpdatePostRequest $request, Post $post): JsonResponse
{
    $this->authorize('update', $post);
    
    $post->update($request->validated());
    
    return response()->json(['success' => true]);
}

// Or use Policy
// app/Policies/PostPolicy.php
public function update(User $user, Post $post): bool
{
    return $user->id === $post->user_id;
}
```

### Input Validation

**NEVER** trust user input:

```php
// ❌ BAD - Direct input without validation
$post = Post::create($request->all());

// ✅ GOOD - Use Form Request
public function store(StorePostRequest $request): JsonResponse
{
    $post = Post::create($request->validated());
    return response()->json(new PostResource($post), 201);
}
```

---

## Error Handling

### Global Exception Handler

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $e)
{
    if ($request->is('api/*')) {
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors()
                ]
            ], 422);
        }
        
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Resource not found.'
                ]
            ], 404);
        }
        
        // Generic error
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'SERVER_ERROR',
                'message' => 'An error occurred while processing your request.'
            ]
        ], 500);
    }
    
    return parent::render($request, $e);
}
```

---

## API Routes Structure

```php
// routes/api.php
use App\Http\Controllers\Api\{
    AuthController,
    PostController,
    CategoryController,
    CommentController
};

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Public resource routes
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post:slug}', [PostController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    Route::apiResource('posts', PostController::class)->except(['index', 'show']);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('posts.comments', CommentController::class);
});
```

---

## Important Reminders

### DO
✅ Use Form Requests for validation
✅ Use API Resources for responses
✅ Eager load relationships
✅ Use type hints everywhere
✅ Write tests for all features
✅ Follow PSR-12 standards
✅ Use Laravel conventions

### DON'T
❌ Use raw SQL queries (use Eloquent)
❌ Skip validation
❌ Return raw models (use Resources)
❌ Create N+1 queries
❌ Skip error handling
❌ Hardcode values
❌ Skip type hints

---

**This CLAUDE.md is specific to Laravel backend. For frontend guidelines, see `frontend/CLAUDE.md`.**
