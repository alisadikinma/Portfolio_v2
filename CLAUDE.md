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
├── views/              # Page components (Home.vue, Blog.vue, etc.)
│   ├── admin/         # Admin pages (Dashboard.vue, PostCreate.vue, PostEdit.vue)
│   └── auth/          # Auth pages (Login.vue)
├── layouts/            # Layout wrappers (DefaultLayout, AdminLayout, AuthLayout)
├── components/
│   ├── base/          # Reusable UI (BaseButton, BaseCard, BaseInput, etc.)
│   └── blog/          # Blog-specific components
│       ├── RichTextEditor.vue  # CKEditor 5 integration
│       ├── ImageUploader.vue   # Drag & drop image upload
│       ├── CategorySelect.vue  # Headless UI category selector
│       └── BlogPostForm.vue    # Integrated post form
├── composables/        # Reusable logic (usePosts, useProjects, useAuth, useCategories)
├── stores/            # Pinia stores (auth.js, posts.js, categories.js, projects.js, ui.js)
├── services/          # API layer (api.js with axios)
└── router/            # Vue Router config
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
   - `posts.js` - Blog posts CRUD operations & pagination
   - `categories.js` - Categories management
   - `projects.js` - Projects CRUD operations & pagination
   - `ui.js` - Loading states, modals, toasts

5. **Blog Components (Phase 3 - Completed):**
   - **RichTextEditor** - CKEditor 5 via CDN with full toolbar, code blocks, dark mode
   - **ImageUploader** - Drag & drop with preview, validation (5MB max)
   - **CategorySelect** - Headless UI Listbox with API integration
   - **BlogPostForm** - Integrated form with validation, auto-slug, SEO fields

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

## Recent Updates

### Homepage Enhancement - New Positioning Fields (November 3, 2025) ✅

**Project Template & About Page Enhancement**
- ✅ Added 8 case study fields to projects table:
  - `domain` - Project domain/category (AI Automation, Web Development, etc.)
  - `impact_statement` - Brief business impact summary (max 255 chars)
  - `context` - Project background and why it was needed
  - `role` - Your specific role and responsibilities
  - `problem` - Business challenge or problem addressed
  - `solution` - Technical solution implemented
  - `integration` - Systems/technologies integrated
  - `result` - Measurable outcomes and achievements

- ✅ Added 7 hero/about settings to settings table:
  - `hero_tagline` - Main positioning statement
  - `availability_note` - Current availability status
  - `trust_strip` - Trust indicators (JSON array)
  - `mission` - Mission statement
  - `what_i_do` - Services/capabilities (JSON array)
  - `approach` - Methodology and philosophy
  - `collaboration_modes` - How to work together (JSON array)

**Backend Updates:**
- Migration: `2025_11_03_072451_add_project_template_fields_to_projects_table.php`
- Seeder: `HeroAboutEnhancementSeeder.php`
- Updated Project model with new fillable fields and scopes
- Enhanced StoreProjectRequest and UpdateProjectRequest validation
- Updated ProjectResource to include case study fields
- Enhanced UpdateAboutSettingsRequest for new settings

**Frontend Updates:**
- Admin About Settings form with 7 new fields
- Admin Project forms with case study fields
- Enhanced Home.vue hero section
- Restructured About.vue with mission, approach, collaboration sections
- Updated Projects.vue with domain filtering
- Enhanced ProjectDetail.vue with case study template

**API Endpoints Enhanced:**
- GET /api/projects - Returns projects with domain and impact
- GET /api/projects/{slug} - Returns full case study details
- POST /api/admin/projects - Accepts case study fields
- GET /api/settings/about - Returns hero/about settings
- POST /api/admin/settings/about - Updates settings

**Performance:** All fields nullable, zero breaking changes, 100% backward compatible

---

### 🎉 Project Complete - October 25, 2025

**Status:** ✅ 100% COMPLETE - PRODUCTION READY

---

### Phase 9: Gallery System Restructure (October 25, 2025) ✅

**Problem:** Wrong database structure
- gallery_groups table (incorrect parent)
- award_gallery_groups many-to-many (wrong relationship)
- Missing fields in galleries (company, period, thumbnail, award_id)

**Solution:**
```php
// Database Migration
- Dropped 3 tables: gallery_groups, award_gallery_groups, old gallery_items
- Restructured galleries: Added company, period, thumbnail, award_id
- Created NEW gallery_items table (parent: galleries, not groups)
- Fixed relationships: Award → Gallery (hasMany), Gallery → Items (hasMany)

// Controllers
- GalleryController - Completely refactored
- GalleryItemController - NEW (CRUD + bulk upload 20 files max)

// Routes (21 endpoints)
GET    /api/galleries                                    # Public list
GET    /api/galleries/{id}                               # Public detail
GET    /api/galleries/{galleryId}/items                  # Public items
POST   /api/admin/galleries                              # Admin create
PUT    /api/admin/galleries/{id}                         # Admin update
DELETE /api/admin/galleries/{id}                         # Admin delete
POST   /api/admin/galleries/{galleryId}/items            # Add item
POST   /api/admin/galleries/{galleryId}/items/bulk-upload # Bulk upload
PUT    /api/admin/galleries/{galleryId}/items/{id}       # Update item
DELETE /api/admin/galleries/{galleryId}/items/{id}       # Delete item

// Frontend
- Pinia galleries.js updated with FormData support
- Gallery items actions added (fetch, add, update, delete, bulk upload)
```

---

### Service API Implementation (October 25, 2025) ✅

**New Files Created:**
```php
// Backend
- ServiceController.php      # Full CRUD (index, show, store, update, destroy)
- StoreServiceRequest.php    # Validation for create
- UpdateServiceRequest.php   # Validation for update
- ServiceResource.php         # JSON API responses

// Routes (7 endpoints)
GET    /api/services           # Public list (with filters, search, pagination)
GET    /api/services/{slug}    # Public detail by slug
POST   /api/admin/services     # Admin create
PUT    /api/admin/services/{slug}  # Admin update
DELETE /api/admin/services/{slug}  # Admin delete

// Features
- Auto-slug generation from title
- Active/inactive filtering
- Search by title, description, content
- Order management (auto-increment if not provided)
- Pagination with meta data
```

---

### Testing & Documentation (October 25, 2025) ✅

**Test Files Created:**
```php
// Feature Tests (54+ test cases total)
- ServiceApiTest.php (17 tests)
  - CRUD operations, search, filtering, pagination
  - Validation, authentication, auto-slug generation

- GalleryApiTest.php (20 tests)
  - CRUD, filters, relationships, cascade deletes
  - Gallery items CRUD, bulk upload (max 20 files)
  - Sequence ordering, validation limits

// Model Factories
- ServiceFactory.php (with active/inactive states)
- GalleryFactory.php (with active/inactive & award relationship)
- GalleryItemFactory.php (with image/video types & sequence)
```

**Documentation Files Created:**
```markdown
1. API_ENDPOINTS.md (900+ lines)
   - All 100+ endpoints documented with examples
   - Request/response formats, validation rules
   - Query parameters, authentication requirements
   - Rate limiting, error responses

2. SECURITY_AUDIT.md
   - Security Score: 95/100 ✅ PRODUCTION READY
   - Authentication & Authorization verified
   - Input validation comprehensive
   - Rate limiting implemented
   - File upload security enforced
   - SQL injection, XSS, CSRF protection verified
   - Production recommendations documented

3. DEPLOYMENT_CHECKLIST.md
   - 16-section comprehensive guide
   - Pre-deployment checklist
   - Server configuration examples
   - Security hardening steps
   - Performance optimization
   - Monitoring & logging setup
   - Backup strategy
   - Rollback plan

4. COMPLETION_SUMMARY.md
   - Complete project overview
   - All features documented
   - Development timeline
   - Tech stack details
   - Achievement summary

5. PROJECT_STATUS.md (Updated to 100%)
   - All 4 development sessions documented
   - Phase 9 complete
   - Service API complete
   - Testing & documentation complete
```

---

## Final Statistics

| Metric | Count | Status |
|--------|-------|--------|
| **Backend API Endpoints** | 100+ | ✅ Complete |
| **Controllers** | 15 | ✅ Complete |
| **Models** | 12+ | ✅ Complete |
| **Database Tables** | 18 | ✅ Complete |
| **New Project Fields** | 8 (case study) | ✅ Complete |
| **New Settings Fields** | 7 (hero/about) | ✅ Complete |
| **Test Cases** | 54+ | ✅ Passing |
| **Frontend Pages** | 15+ | ✅ Complete |
| **Vue Components** | 50+ | ✅ Complete |
| **Pinia Stores** | 5 | ✅ Complete |
| **Documentation Files** | 10+ | ✅ Complete |
| **Security Score** | 95/100 | ✅ Production Ready |
| **Overall Progress** | **100%** | ✅ **COMPLETE** |

---

## Quick Reference Links

**📚 Essential Documentation:**
- [PROJECT_STATUS.md](./PROJECT_STATUS.md) - Development tracking (100% complete)
- [COMPLETION_SUMMARY.md](./COMPLETION_SUMMARY.md) - Complete project summary
- [API_ENDPOINTS.md](./API_ENDPOINTS.md) - API documentation (900+ lines, 100+ endpoints)
- [SECURITY_AUDIT.md](./SECURITY_AUDIT.md) - Security report (95/100 score)
- [DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md) - Production deployment guide
- [README.md](./README.md) - Project overview & setup instructions

**🔧 Development:**
- [backend/README.md](./backend/README.md) - Backend-specific documentation
- [frontend/README.md](./frontend/README.md) - Frontend-specific documentation
- [backend/SEO_IMPLEMENTATION.md](./backend/SEO_IMPLEMENTATION.md) - SEO features guide

---

**Last Updated:** November 3, 2025
**Maintainer:** Ali Sadikin (ali.sadikincom85@gmail.com)
**Status:** ✅ 100% COMPLETE - PRODUCTION READY
**Security Score:** 95/100
**Test Coverage:** 54+ test cases passing
**Performance:** ⚡ All pages < 500ms cached (83% faster)
**Environment:** Windows 11 + XAMPP (Apache:80, MySQL:3306)

---

### Recent Performance Improvements (October 30, 2025)

**Phase 1: Initial TanStack Query Implementation**
- ✅ Installed `@tanstack/vue-query@5.90.5` for intelligent caching
- ✅ Configured QueryClient with optimized cache policies (5-60min stale time)
- ✅ Migrated `usePosts` and `useProjects` composables to TanStack Query
- ✅ Created 4 loading skeleton components (Blog, Project, Award, Content)
- ✅ **83% faster repeat visits** (0.3s from 1.8s)
- ✅ **70% reduction in API calls** through smart caching
- ✅ Zero "no data" flashes - instant content from cache
- ✅ 100% backward compatible - no breaking changes

**Phase 2: Extended TanStack Query to All Pages (October 30, 2025)** ✅
- ✅ **Backend:** Added `limit` parameter support to 4 controllers:
  - PostController.php - Skip pagination when `?limit=N` present
  - AwardController.php - Fast queries for homepage sections
  - TestimonialController.php - Optimized list endpoint
  - GalleryController.php - Quick gallery previews
- ✅ **Frontend:** Migrated 3 additional composables to TanStack Query:
  - `useAwards.js` - 1hr cache (60min stale time)
  - `useTestimonials.js` - 30min cache (30min stale time)
  - `useGallery.js` - 1hr cache with mutation invalidation
- ✅ **Cache Strategy:**
  - Awards: 1 hour (rarely changes)
  - Testimonials: 30 minutes (moderate updates)
  - Gallery: 1 hour + smart invalidation on uploads/deletes
  - Posts: 5 minutes (frequent updates)
  - Projects: 60 minutes (stable content)
- ✅ **Performance Results:**
  - All pages now < 500ms on cached loads
  - Homepage: 5.75s → 0.3s (cached)
  - Awards: 8s → 0.4s (cached)
  - Testimonials: 6s → 0.3s (cached)
- ✅ **Backward Compatibility:** 100% - no component changes required

**Gallery System Enhancements (October 26-30):**
- ✅ Fixed award gallery linking issues
- ✅ Updated thumbnail path handling for public access in GalleryController and GalleryResource
- ✅ Enhanced gallery selection component for award relationships
- ✅ Improved bulk upload data handling for galleries
- ✅ Added comprehensive feature tests for Gallery and Service APIs

**Maintenance Period (November 2025):**
- System stable and running in production-ready state
- All core features complete and tested
- Minor UI refinements and bug fixes ongoing
- Documentation kept current with environment updates

**Note:** These are post-completion refinements to improve stability and user experience. Core functionality remains unchanged.
