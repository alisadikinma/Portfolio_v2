# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Portfolio_v2 is a full-stack portfolio, blog, and CMS platform using Laravel 12 (backend API) and Vue 3 (frontend SPA). Development on Windows 11.

**Critical Context Files:**
- Read `README.md`, `backend/README.md`, `frontend/README.md` at start of every conversation
- Check `PROJECT_STATUS.md` for current development state and progress tracking

## Environment Architecture

### Tech Stack
**Backend:** Laravel 12 + MySQL 8 + Laravel Sanctum 4 (JWT auth) + Filament 4.1 (admin panels)
**Frontend:** Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + Vue Router 4.5 + Tailwind CSS 4
**Packages:** Intervention Image 3.11, Spatie Sluggable 3.7, Resend (email), SortableJS, TanStack Vue Query 5.90

### Critical URLs
```
Backend API:   http://localhost/Portfolio_v2/backend/public/api
Frontend Dev:  http://localhost:5173 (Vite)
Production:    https://alisadikinma.com
Database:      localhost:3306 (user: ali, db: portfolio_v2)
phpMyAdmin:    http://localhost/phpmyadmin
```

### Key Constraints
- **DO NOT** use `php artisan serve` - XAMPP Apache already handles backend on port 80
- Project path: `D:\Projects\Portfolio_v2\`
- Backend runs on XAMPP Apache, frontend on Vite dev server

## Architecture & Patterns

### Backend Architecture (Laravel 12)

**Controller Map (17 controllers):**
```
app/Http/Controllers/Api/
├── Admin/
│   └── DashboardController.php    # Admin dashboard stats
├── AuthController.php             # Login, register, logout, me
├── AutomationController.php       # n8n/Zapier automation API
├── AwardController.php            # Awards CRUD + gallery linking
├── CategoryController.php         # Blog categories CRUD
├── ContactController.php          # Contact form + CSV export
├── GalleryController.php          # Gallery CRUD + bulk upload
├── GalleryItemController.php      # Gallery items CRUD + bulk upload
├── MenuItemController.php         # Dynamic navbar menu items
├── PageSectionController.php      # Dynamic page sections (homepage)
├── PostController.php             # Blog posts CRUD + check-duplicate
├── ProjectController.php          # Projects CRUD + import
├── ServiceController.php          # Services CRUD
├── SettingController.php          # Key-value settings by group
├── SettingsController.php         # About & Site settings (structured)
├── SitemapController.php          # XML sitemap generation
├── TestimonialController.php      # Testimonials CRUD
└── TokenController.php            # Automation API token management
```

**Model Map (16 models):**
```
app/Models/
├── Award.php              # HasSeoFields trait
├── Category.php           # HasSeoFields, HasSlug
├── Contact.php
├── Gallery.php            # award_id relationship
├── GalleryItem.php        # Belongs to Gallery
├── MenuItem.php           # Dynamic navbar
├── Newsletter.php
├── PageSection.php        # Dynamic homepage sections
├── Post.php               # HasSeoFields, SoftDeletes, HasSlug
├── PostTranslation.php    # i18n for posts
├── Project.php            # HasSeoFields, SoftDeletes, HasSlug
├── ProjectTranslation.php # i18n for projects
├── Service.php            # HasSlug
├── Setting.php            # Key-value pairs
├── Testimonial.php
└── User.php
```

**Filament Admin (partial):**
```
app/Filament/Resources/
├── Settings/
└── Testimonials/
```

**Important Patterns:**
1. **Models use Traits:** `HasSeoFields`, `SoftDeletes`, `HasSlug` (Spatie)
2. **Route key:** `slug` for public routes, `id` for admin routes
3. **API Response Format:**
```php
// Success
return response()->json(['success' => true, 'data' => $resource, 'message' => '...'], 200);
// Error
return response()->json(['success' => false, 'error' => ['code' => '...', 'message' => '...']], 400);
```
4. **Controller Pattern:** Form Requests → API Resources → Eager Loading → Proper HTTP codes
5. **SEO:** All content models have HasSeoFields trait (meta_title, meta_description, og_image, schema_markup, canonical_url, seo_score)
6. **Translations:** Posts and Projects support i18n via translation tables

### Frontend Architecture (Vue 3)

**Views (13 public + 22 admin + 1 auth):**
```
src/views/
├── Home.vue              # Hero, stats, projects, blog, testimonials, CTA
├── About.vue             # Skills, experience, education, social
├── Projects.vue          # Grid with filters, pagination
├── ProjectDetail.vue     # Full project case study
├── Awards.vue            # Awards cards with gallery modal
├── Awards-DEBUG.vue      # Debug version (remove in prod)
├── Blog.vue              # List with search, categories, pagination
├── BlogDetail.vue        # Post content, share, author, related
├── BlogCategory.vue      # Posts filtered by category
├── Gallery.vue           # Image grid with lightbox
├── Contact.vue           # Form with validation
├── NotFound.vue          # 404 page
├── auth/Login.vue
└── admin/
    ├── Dashboard.vue
    ├── PostsList.vue / PostCreate.vue / PostEdit.vue
    ├── ProjectsList.vue / ProjectCreate.vue / ProjectEdit.vue
    ├── AwardsList.vue / AwardCreate.vue / AwardEdit.vue
    ├── GalleriesList.vue
    ├── TestimonialsList.vue / TestimonialCreate.vue / TestimonialEdit.vue
    ├── ContactsList.vue
    ├── AboutSettings.vue / SettingsForm.vue
    ├── MenuItemsList.vue          # Dynamic menu management
    ├── PageSectionsManager.vue    # Homepage section editor
    ├── AutomationTokens.vue       # API token management
    ├── AutomationLogs.vue         # Automation activity logs
    └── AutomationDocs.vue         # API documentation page
```

**Stores (14 Pinia stores):**
```
src/stores/
├── auth.js / auth-fixed.js  # Authentication & token management
├── automation.js             # Automation API state
├── awards.js / categories.js / contacts.js / galleries.js
├── posts.js / projects.js / settings.js / testimonials.js
├── theme.js                  # Light/dark theme
├── ui.js                     # Loading states, modals, toasts
└── index.js                  # Store exports
```

**Composables (20 composables):**
```
src/composables/
├── useAboutSettings.js    # About page data + prefetch
├── useApi.js              # Base API wrapper
├── useAuth.js             # Auth composable
├── useAutomation.js       # Automation API
├── useAwards.js           # TanStack Query cached
├── useCategories.js
├── useContact.js
├── useGallery.js          # TanStack Query cached
├── useLocalCache.js       # Local storage caching
├── useMenuItems.js        # Dynamic menu
├── useMetaTags.js         # Dynamic SEO meta from CMS
├── useModal.js
├── usePageSections.js     # Dynamic page sections
├── usePosts.js            # TanStack Query cached
├── useProjects.js         # TanStack Query cached
├── useSettings.js / useSiteSettings.js
├── useTestimonials.js     # TanStack Query cached
├── useToast.js
└── index.js
```

**Components:**
```
src/components/
├── admin/
│   ├── DragDropList.vue     # SortableJS drag-drop
│   ├── IconDisplay.vue      # Icon renderer
│   └── IconPicker.vue       # Icon selector
├── awards/ / blog/ / projects/ / testimonials/
├── base/ (17 components)
│   ├── BaseButton / BaseCard / BaseInput / BaseModal / BaseBadge
│   ├── BaseLoader / BaseToast / BaseLightbox / BaseGalleryModal
│   ├── BlogSkeleton / ProjectSkeleton / AwardSkeleton / ContentSkeleton
│   ├── MobileCarousel / ScrollToTop
│   └── index.js / README.md
├── CTASection.vue
├── HeroSectionWOW.vue
├── TheNavigation.vue
└── TheFooter.vue
```

## Database Schema (42 migrations, 25+ tables)

Key tables: users, posts, post_translations, blog_categories, projects, project_translations, awards, award_gallery_pivot, galleries, gallery_items, services, testimonials, contacts, newsletters, settings, menu_items, page_sections, automation_logs, personal_access_tokens, cache, jobs

Recent migrations (post-initial):
- `menu_items` & `page_sections` - Dynamic content management
- `project_template_fields` - Extended project metadata
- `add_cta_fields` / `related_projects` - Project enhancements
- `whatsapp_number` on contacts
- `post_translations` / `project_translations` - i18n support
- `schema_fields` on translations - SEO extensions

## API Routes (120+ endpoints)

### Public Routes
```
GET    /api/posts, /api/posts/{slug}
POST   /api/posts/check-duplicate
GET    /api/projects, /api/projects/{slug}
GET    /api/categories, /api/categories/{slug}
GET    /api/awards, /api/awards/{id}, /api/awards/{id}/galleries
GET    /api/galleries (or /gallery), /api/galleries/{id}, /api/galleries/{id}/items
GET    /api/testimonials, /api/testimonials/{id}
GET    /api/services, /api/services/{slug}
GET    /api/settings, /api/settings/about, /api/settings/site, /api/settings/{group}
GET    /api/menu-items, /api/page-sections
GET    /api/sitemap.xml, /api/sitemap-index.xml, /api/sitemap-posts.xml, /api/sitemap-projects.xml
GET    /api/health
POST   /api/contact (throttle: 3/15min)
```

### Admin Routes (auth:sanctum)
```
/api/admin/dashboard/stats
/api/admin/posts (CRUD)
/api/admin/projects (CRUD)
/api/admin/categories (CRUD)
/api/admin/awards (CRUD + gallery link/unlink/reorder)
/api/admin/galleries (CRUD + bulk-upload + items CRUD + items bulk-upload)
/api/admin/gallery (alias, same as galleries)
/api/admin/testimonials (CRUD)
/api/admin/services (CRUD by slug)
/api/admin/contacts (list, show, export, mark-as-read, delete)
/api/admin/settings/about (GET, PUT, POST with _method=PUT)
/api/admin/settings/site (GET, PUT, POST with _method=PUT)
/api/admin/menu-items (CRUD + reorder)
/api/admin/page-sections (list, reorder, update)
/api/admin/automation/tokens (CRUD) + /api/admin/automation/logs (list, clear)
```

### Automation Routes
```
POST   /api/automation/posts/check-duplicate (public)
GET    /api/automation/posts (auth + throttle:60/min)
POST   /api/automation/posts, /api/automation/posts/bulk
PUT    /api/automation/posts/{id}
DELETE /api/automation/posts/{id}
GET    /api/automation/categories
POST   /api/automation/upload-image, /api/automation/upload-images
POST   /api/automation/webhook/published
```

## Essential Commands

### Backend (Laravel)
```bash
cd D:\Projects\Portfolio_v2\backend
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Fresh install with data
php artisan route:list                 # View all routes
php artisan tinker                     # Interactive console
php artisan cache:clear && php artisan config:clear && php artisan route:clear
php artisan test                       # Run tests
php artisan projects:import-raw-data   # Bulk import 56 projects
```

### Frontend (Vue)
```bash
cd D:\Projects\Portfolio_v2\frontend
npm run dev           # Start Vite dev server (port 5173)
npm run build         # Production build
npm run preview       # Preview production build
```

## Code Style Conventions

### Laravel
- Controllers: `PostController.php` (singular PascalCase)
- Models: `Post.php` (singular PascalCase)
- Requests: `StorePostRequest.php`, `UpdatePostRequest.php`
- Resources: `PostResource.php`
- Routes: `/api/posts` (plural kebab-case)
- Public routes use `{slug}`, admin routes use `{id}`

### Vue
- Components: `BlogCard.vue` (PascalCase)
- Composables: `usePosts.js` (camelCase with `use` prefix)
- Stores: `auth.js` (camelCase)
- Use `<script setup>` syntax only
- Tailwind utility classes, minimal custom CSS

### Database
- Tables: plural snake_case
- Foreign keys: `category_id` (singular + _id)
- Always include timestamps

## Performance & Caching

**TanStack Query Cache Strategy:**
- Posts: 5min stale time (frequent updates)
- Projects: 60min stale time
- Awards: 60min stale time
- Testimonials: 30min stale time
- Gallery: 60min + smart invalidation on mutations
- About Settings: prefetched on router navigation

**Results:** 83% faster repeat visits, 70% fewer API calls, all pages < 500ms cached

## CORS Configuration
```
Allowed origins: localhost:5173-5175, alisadikinma.com (http/https, www/non-www)
Supports credentials: true
```

## Common Issues & Solutions

- **"Class not found"** → `composer dump-autoload`
- **HMR broken** → `npm run dev -- --force`
- **CORS errors** → Check `backend/config/cors.php` origins
- **FormData PUT** → Use `POST` with `_method=PUT` field
- **Migration fail** → `php artisan migrate:fresh --seed`

## Multi-Agent System

Located at `D:\Projects\Portfolio_v2\.claude\agents\`:
- `orchestrator.md` - Multi-agent coordinator
- `laravel-specialist.md` - Backend expert
- `vue-expert.md` - Frontend expert
- `database-administrator.md` - Database expert
- `qa-expert.md` - Testing & QA
- `documentation-engineer.md` - Documentation

## Working with This Codebase

### Before Starting:
1. Read README files (root, backend, frontend)
2. Check `PROJECT_STATUS.md` for current state
3. Review existing patterns before creating new ones

### After Changes:
1. Run tests
2. Update documentation if needed
3. Commit with conventional commits: `feat:`, `fix:`, `docs:`, etc.

## ULTRA Redesign — In Progress (March 2026)

**Brand:** Ali Sadikin Ma — AI Generalist Expert
**Design:** Dark Cinema + Gold/Cyan dual accent + Liquid Glass
**Nav:** Home | Work | Blog | About | Contact

### Design System (Active)
```
Colors:
  --bg-deep: #050506          (page base)
  --bg-elevated: #0C0C0F      (cards, sections)
  --fg-primary: #EDEDEF        (text)
  --fg-muted: #8A8F98          (secondary text)
  --accent-gold: #D4A843       (primary accent, CTAs)
  --accent-cyan: #06B6D4       (secondary accent, links)
  --accent-indigo: #5E6AD2     (aurora, tertiary)

Fonts:
  Display: Space Grotesk 500-700
  Body: Inter 300-700
  Labels: JetBrains Mono 400-500 (uppercase, tracking wide)
  Quotes: Playfair Display italic

Effects:
  .glass-card       — backdrop-blur(40px) saturate(180%) on dark
  .gradient-border   — animated conic-gradient rotating border
  .text-gradient     — gold→cyan gradient text
  .glow-gold        — gold box-shadow glow
  .btn-gold         — gold gradient button
  .btn-glass        — transparent glass button
  .chromatic-hover   — RGB split on hover
```

### Implementation Plans
- Design Spec: `docs/plans/2026-03-22-ultra-portfolio-redesign.md`
- Implementation Plan: `docs/plans/2026-03-22-ultra-portfolio-implementation.md`
- Phase 1A (Design Tokens): COMPLETED
- Phase 1B-1E (Aurora, Glass Cards, Nav, Cursor): PENDING
- Phase 2-6: PENDING

### New Dependencies (Planned)
- GSAP 3 + ScrollTrigger (scroll animations)
- TresJS 4 + Three.js (3D globe)
- vue-i18n 10 (multi-language)
- anthropic/sdk PHP (chatbot backend)

---

**Last Updated:** March 22, 2026
**Maintainer:** Ali Sadikin (ali.sadikincom85@gmail.com)
**Environment:** Windows 11, D:\Projects\Portfolio_v2
**PHP:** D:\xampp\php\php.exe (8.2.12) — use full path, not in system PATH
**Backend:** Laravel 12 + PHP 8.2 + MySQL 8 + Sanctum 4 + Filament 4.1
**Frontend:** Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + TanStack Query 5.90 + Tailwind 4
**Production:** https://alisadikinma.com
