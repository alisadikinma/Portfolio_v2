# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Portfolio_v2 is a full-stack portfolio and blog platform using Laravel 10 (backend API) and Vue 3 (frontend SPA). Development on Windows 11 with XAMPP.

**Critical Context Files:**
- Read `README.md`, `backend/README.md`, `frontend/README.md` at start of every conversation
- Check `PROJECT_STATUS.md` for current development state and progress tracking

## Environment Architecture

### Tech Stack
**Backend:** Laravel 10 + MySQL 8 + Laravel Sanctum (JWT auth)
**Frontend:** Vue 3.5 + Vite 7 (Rolldown) + Pinia 3 + Vue Router 4.5 + Tailwind CSS 4
**Server:** XAMPP (Apache port 80, MySQL port 3306)

### Critical URLs
```
Backend API:   http://localhost/Portfolio_v2/backend/public/api
Frontend Dev:  http://localhost:5173 (Vite)
Database:      localhost:3306 (user: ali, db: portfolio_v2)
phpMyAdmin:    http://localhost/phpmyadmin
```

### Key Constraints
- **DO NOT** use `php artisan serve` - XAMPP Apache already handles backend on port 80
- Use Windows-style paths: `C:\xampp\htdocs\Portfolio_v2\`
- Backend runs on XAMPP Apache, frontend on Vite dev server

## Architecture & Patterns

### Backend Architecture (Laravel)

**API Structure:**
```
app/
├── Models/           # Eloquent models with relationships
│   ├── Post.php     # HasSeoFields trait, SoftDeletes, HasSlug
│   ├── Project.php  # HasSeoFields trait, SoftDeletes, HasSlug
│   └── Category.php # HasSeoFields trait, HasSlug
├── Http/
│   ├── Controllers/Api/  # RESTful controllers
│   ├── Requests/         # Form validation (StorePostRequest, etc.)
│   └── Resources/        # JSON transformers (PostResource, etc.)
└── Traits/
    └── HasSeoFields.php  # SEO/GEO functionality (meta tags, schema, etc.)
```

**Important Patterns:**
1. **Models use Traits:**
   - `HasSeoFields` - SEO meta tags, structured data, Open Graph
   - `SoftDeletes` - For Post/Project (trash functionality)
   - `HasSlug` (Spatie) - Auto-generate slugs from titles
   - Route key name: `slug` (not `id`)

2. **API Response Format:**
```php
// Success
return response()->json([
    'success' => true,
    'data' => $resource,
    'message' => 'Operation successful'
], 200);

// Error
return response()->json([
    'success' => false,
    'error' => ['code' => 'ERROR_CODE', 'message' => 'Error description']
], 400);
```

3. **Controller Pattern:**
   - Use Form Requests for validation
   - Use API Resources for response transformation
   - Return appropriate HTTP status codes (200, 201, 404, 422, etc.)
   - Eager load relationships to avoid N+1 queries

4. **SEO Implementation:**
   - All content models (Post, Project, Category) have extensive SEO fields
   - See `backend/SEO_IMPLEMENTATION.md` for complete guide
   - Fields: meta_title, meta_description, og_image, schema_markup, canonical_url, etc.

### Frontend Architecture (Vue 3)

**Structure:**
```
src/
├── views/          # Page components (Home.vue, Blog.vue, etc.)
├── layouts/        # Layout wrappers (DefaultLayout, AdminLayout, AuthLayout)
├── components/
│   ├── base/      # Reusable UI (BaseButton, BaseCard, BaseInput, etc.)
│   └── [feature]/ # Feature-specific components
├── composables/    # Reusable logic (usePosts, useProjects, useAuth, etc.)
├── stores/         # Pinia stores (auth.js, ui.js, theme.js)
├── services/       # API layer (api.js with axios)
└── router/         # Vue Router config
```

**Important Patterns:**
1. **Component Structure (Composition API):**
```vue
<script setup>
// Imports
import { ref, computed, onMounted } from 'vue'

// Props & Emits
const props = defineProps({ ... })
const emit = defineEmits(['update'])

// Composables
const { data, loading, error, fetch } = usePosts()

// Reactive state
const localState = ref(null)

// Computed
const computed = computed(() => ...)

// Methods
function handleAction() { ... }

// Lifecycle
onMounted(() => { ... })
</script>

<template>
  <!-- Tailwind utility classes only -->
</template>

<style scoped>
/* Minimal custom CSS, prefer Tailwind */
</style>
```

2. **API Integration:**
   - Axios instance in `services/api.js` with baseURL
   - Composables handle API calls (`usePosts`, `useProjects`, etc.)
   - Pinia stores for global state
   - Interceptors for auth tokens and error handling

3. **Routing:**
   - Slug-based routes: `/blog/:slug`, `/projects/:slug`
   - Layout wrappers for different page types
   - Protected routes use `auth:sanctum` middleware check

4. **State Management:**
   - Pinia stores use setup syntax
   - `auth.js` - User authentication & token management
   - `ui.js` - Loading states, modals, toasts
   - `theme.js` - Dark mode toggle

## Essential Commands

### Backend (Laravel)
```bash
cd C:\xampp\htdocs\Portfolio_v2\backend

# Database
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Fresh install with data
php artisan db:seed                    # Seed only

# Code generation
php artisan make:model Post -mcr       # Model + Migration + Controller (resource)
php artisan make:request StorePostRequest
php artisan make:resource PostResource

# Development
php artisan route:list                 # View all routes
php artisan tinker                     # Interactive console
composer dump-autoload                 # Reload classes

# Testing
php artisan test                       # Run tests
php artisan test --filter=PostTest     # Single test

# Cache (clear when config changes)
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Frontend (Vue)
```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend

npm run dev           # Start Vite dev server (port 5173)
npm run build         # Production build
npm run preview       # Preview production build

# Force refresh (if HMR not working)
npm run dev -- --force
```

## Code Style Conventions

### Laravel
- **Controllers:** `PostController.php` (singular, PascalCase)
- **Models:** `Post.php` (singular, PascalCase)
- **Requests:** `StorePostRequest.php`, `UpdatePostRequest.php`
- **Resources:** `PostResource.php`, `PostCollection.php`
- **Routes:** `/api/posts` (plural, kebab-case)
- **Route Parameters:** Use `{slug}` not `{id}` for public routes

### Vue
- **Components:** `BlogCard.vue`, `ProjectList.vue` (PascalCase)
- **Pages/Views:** `Home.vue`, `BlogDetail.vue` (PascalCase)
- **Composables:** `usePosts.js`, `useAuth.js` (camelCase with `use` prefix)
- **Stores:** `auth.js`, `ui.js` (camelCase)
- Use `<script setup>` syntax (not Options API)
- Props in template: kebab-case `<BaseButton button-type="primary" />`

### Database
- **Tables:** plural snake_case (`posts`, `blog_categories`)
- **Foreign keys:** `category_id`, `user_id` (singular + _id)
- **Timestamps:** Always include `created_at`, `updated_at`
- **Indexes:** Add for foreign keys and frequently queried fields

## Testing Requirements

### TDD Workflow (Mandatory)
1. **Write test FIRST** - Create failing test
2. **Run test** - Confirm it fails
3. **Implement** - Write minimal code to pass
4. **Refactor** - Clean up while keeping tests green

### Backend Tests
```php
// tests/Feature/PostTest.php
test('can create post', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/posts', [
            'title' => 'Test Post',
            'content' => 'Content here',
            'category_id' => 1,
        ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['success', 'data', 'message']);

    $this->assertDatabaseHas('posts', ['title' => 'Test Post']);
});
```

### Frontend Tests (Playwright)
- Use Playwright MCP for browser automation
- Test CRUD operations, forms, navigation
- Verify responsive design
- Check for console errors

## Critical Patterns to Follow

### 1. Eager Loading (Avoid N+1)
```php
// ❌ Bad (N+1 query)
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->category->name; // N queries
}

// ✅ Good
$posts = Post::with('category')->get(); // 2 queries
```

### 2. Form Validation
```php
// ❌ Bad (validation in controller)
public function store(Request $request) {
    $request->validate([...]);
}

// ✅ Good (use Form Request)
public function store(StorePostRequest $request) {
    $validated = $request->validated();
}
```

### 3. API Resources
```php
// ❌ Bad (return model directly)
return $post;

// ✅ Good (use API Resource)
return new PostResource($post);
```

### 4. SEO Fields Usage
```php
// All content models have HasSeoFields trait
$post->meta_title          // SEO title
$post->meta_description    // SEO description
$post->og_image            // Open Graph image
$post->schema_markup       // JSON-LD structured data
$post->canonical_url       // Canonical URL
$post->seo_score          // Auto-calculated score (0-100)
```

## Common Issues & Solutions

### "Class not found" error
```bash
composer dump-autoload
```

### Frontend not updating (HMR broken)
```bash
npm run dev -- --force
```

### CORS errors
Check `backend/config/cors.php`:
```php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:5173'],
```

### Migration fails
```bash
php artisan migrate:fresh --seed  # Nuclear option
```

## Documentation Standards

**Update documentation when:**
- Adding/changing API endpoints
- Adding new models or major features
- Changing environment variables
- Modifying architectural patterns
- Adding new dependencies

**Files to update:**
- `PROJECT_STATUS.md` - Track completion status
- `README.md` - User-facing changes
- `backend/README.md` or `frontend/README.md` - Technical changes
- This `CLAUDE.md` - Architectural decisions

## Multi-Agent System

This project uses Claude Code's subagent system located in `.claude/agents/`:
- `orchestrator.md` - Multi-agent coordinator
- `laravel-specialist.md` - Backend expert
- `vue-expert.md` - Frontend expert
- `database-administrator.md` - Database expert
- `qa-expert.md` - Testing & QA
- `documentation-engineer.md` - Documentation

Development prompts in `.claude/prompts/` for phased implementation.

## Working with This Codebase

### Before Starting Work:
1. Read all three README files (root, backend, frontend)
2. Check `PROJECT_STATUS.md` for current state
3. Review sibling files for existing patterns
4. Verify changes won't break conventions

### After Making Changes:
1. **Run tests** - Ensure nothing broke
2. **Update documentation** - Keep READMEs current
3. **Update PROJECT_STATUS.md** - Track progress
4. **Commit with conventional commits** - `feat:`, `fix:`, `docs:`, etc.

### Development Philosophy:
- **Quality over speed** - You're working with an experienced developer (16+ years)
- **No hand-holding** - Execute tasks efficiently, assume architectural knowledge
- **Follow established patterns** - Check existing code before creating new patterns
- **TDD always** - Tests first, implementation second
- **QA verification mandatory** - Every task must conclude with verification

---

**Last Updated:** October 13, 2025
**Maintainer:** Ali Sadikin (ali.sadikincom85@gmail.com)
**Status:** In Development (28% Complete - see PROJECT_STATUS.md)
