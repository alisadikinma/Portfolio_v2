# Portfolio v2 — Full-Stack Portfolio, Blog & CMS Platform

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?logo=vue.js)](https://vuejs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql)](https://mysql.com)
[![Filament](https://img.shields.io/badge/Filament-4.1-FDAE4B?logo=laravel)](https://filamentphp.com)
[![License](https://img.shields.io/badge/License-Proprietary-red)](https://github.com)

> **Production-Ready** | **Security-Hardened** | **Performance-Optimized**

Modern, scalable full-stack portfolio and CMS featuring RESTful API architecture, Vue 3 SPA frontend, comprehensive admin panel, automation API (n8n/Zapier), CLI-based AI content pipeline (Claude Code plugins), dynamic content management, and i18n support.

---

## Executive Summary

| Aspect | Details |
|--------|---------|
| **Status** | Production Ready |
| **Production URL** | https://alisadikinma.com |
| **API Endpoints** | 140+ documented endpoints |
| **Performance** | <500ms cached loads (83% improvement) + Cloudflare edge caching (60-80% bandwidth saved on /storage/*) |
| **Security Score** | 95/100 |
| **AI Content Plugin** | article-content-writer v2.7.2 (Wikidata + lede + role-resolution + hard SEO audit) |
| **Image Pipeline** | WebP variants (320/640/1024/1920w) + LQIP blur via Intervention Image — `php artisan images:generate-variants` |
| **HTTP Caching** | ETag/304 on all JSON GET responses (~50 byte revalidation vs full payload) |
| **CDN** | Cloudflare proxy (orange cloud) — `/storage/*` + `/uploads/*` cached at edge, `/api/*` bypassed |
| **Last Updated** | May 5, 2026 |

---

## System Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                         │
│  Vue 3.5 + Rolldown-Vite 7.1 + TanStack Query 5.90    │
│  + Pinia 3 + Tailwind CSS 4 + Headless UI              │
│  Port: 5173 (Dev) | Static (Prod)                      │
├─────────────────────────────────────────────────────────┤
│                         │ Axios + Bearer Token          │
├─────────────────────────────────────────────────────────┤
│                    API LAYER                            │
│  Laravel 12 + Sanctum 4 + Filament 4.1                 │
│  Port: 80 (XAMPP Apache) | /api/*                      │
├─────────────────────────────────────────────────────────┤
│                         │ Eloquent ORM                  │
├─────────────────────────────────────────────────────────┤
│                    DATA LAYER                           │
│  MySQL 8 | 25+ Tables | 42 Migrations                  │
│  Port: 3306 (XAMPP)                                    │
├─────────────────────────────────────────────────────────┤
│                AUTOMATION LAYER                         │
│  n8n / Zapier / Make.com via REST API + Webhooks       │
├─────────────────────────────────────────────────────────┤
│              AI CONTENT PIPELINE (v2.7.2)                │
│  Claude Code CLI + article-content-writer plugin       │
│  Split: prep → write → score → images → translate      │
│  All Sonnet, system prompt injection per phase          │
│  5 gates + combined 100-point scoring, ~8-11 min/article│
│  Named-entity covers: Wikidata + Commons + lede gate    │
│  Backend auto-resolve person on every dispatch (no SSH) │
│  FSM-guarded: ContentIdeaStatus enum + PipelineGuard    │
│  Segment retry + skip + translate-before-publish gate   │
└─────────────────────────────────────────────────────────┘
```

### Technology Stack

**Backend:** Laravel 12 (PHP 8.2), Laravel Sanctum 4, Filament 4.1, Intervention Image 3.11, Spatie Sluggable 3.7, Resend (email)

**Frontend:** Vue 3.5, Rolldown-Vite 7.1, Pinia 3, Vue Router 4.5, TanStack Vue Query 5.90, Tailwind CSS 4, Headless UI 1.7, Heroicons 2.2, CKEditor 5, SortableJS

**Database:** MySQL 8 with 25+ tables, i18n translation tables, SEO fields, pivot tables

**Environment:** Windows 11, XAMPP (Apache:80, MySQL:3306), Node.js 18+

---

## Critical URLs

| Service | URL | Port |
|---------|-----|------|
| Frontend Dev | http://localhost:5173 | 5173 |
| Backend API | http://localhost/Portfolio_v2/backend/public/api | 80 |
| phpMyAdmin | http://localhost/phpmyadmin | 80 |
| Production | https://alisadikinma.com | 443 |

**Important:** Backend runs on XAMPP Apache — do **NOT** use `php artisan serve`.

---

## Quick Start (15 min)

### Prerequisites
- PHP 8.2+, Composer 2.x, Node.js 18+, npm 9+, XAMPP (Apache + MySQL)

### Setup

```bash
# 1. Start XAMPP (Apache + MySQL)

# 2. Backend
cd D:\Projects\Portfolio_v2\backend
composer install
copy .env.example .env
php artisan key:generate
# Edit .env with database credentials
php artisan migrate --seed
php artisan storage:link

# 3. Frontend
cd ..\frontend
npm install
copy .env.example .env
npm run dev

# 4. Access
# Frontend: http://localhost:5173
# API: http://localhost/Portfolio_v2/backend/public/api/health
```

### Create Admin Account
```bash
cd backend
php artisan tinker
>>> User::create(['name'=>'Admin','email'=>'admin@test.com','password'=>bcrypt('password')]);
```

---

## Project Structure

### Backend (25 Controllers, 21 Models, 4 Services)

```
backend/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── Admin/
│   │   │   ├── ContentIdeaController.php # Content Engine pipeline (17 routes)
│   │   │   └── DashboardController.php
│   │   ├── ActivityFeedController.php    # Public activity feed
│   │   ├── AuthController.php
│   │   ├── AutomationController.php      # n8n/Zapier API
│   │   ├── AwardController.php
│   │   ├── BlogPipelineController.php    # Blog pipeline automation
│   │   ├── CarouselDraftController.php   # Carousel draft management
│   │   ├── CategoryController.php
│   │   ├── ChatbotController.php         # AI chatbot
│   │   ├── ContactController.php
│   │   ├── GalleryController.php
│   │   ├── GalleryItemController.php     # Gallery items CRUD
│   │   ├── GeoController.php            # GEO: llms.txt endpoints
│   │   ├── MenuItemController.php        # Dynamic navbar
│   │   ├── NewsletterController.php      # Newsletter subscribe
│   │   ├── PageSectionController.php     # Homepage sections
│   │   ├── PostController.php
│   │   ├── ProjectController.php
│   │   ├── ServiceController.php
│   │   ├── SettingController.php         # Key-value settings
│   │   ├── SettingsController.php        # About & Site settings
│   │   ├── SitemapController.php         # XML sitemap
│   │   ├── TestimonialController.php
│   │   └── TokenController.php           # API token management
│   ├── Services/
│   │   ├── ArticleGenerationService.php  # SSH/local exec → Claude CLI on VPS
│   │   ├── ContentEngineService.php      # Legacy health check proxy
│   │   ├── ImageGenerationService.php    # GeminiGen integration
│   │   └── TrendingTopicService.php      # Trend aggregation
│   ├── Filament/                         # Filament 4.1 admin panels
│   ├── Models/ (21 models)
│   │   ├── Award, CarouselDraft, CarouselSlide, Category, Contact
│   │   ├── ContentIdea, Gallery, GalleryItem, ImageGenerationJob
│   │   ├── MenuItem, Newsletter, PageSection
│   │   ├── Post, PostTranslation, Project, ProjectTranslation
│   │   ├── Service, Setting, SocialAccount, Testimonial, User
│   └── Traits/ (HasSeoFields)
├── database/migrations/ (48 migrations)
├── routes/api.php (140+ endpoints)
└── storage/app/uploads/
```

### Frontend (39 views, 47+ components)

```
frontend/src/
├── views/
│   ├── Home, About, Work, Projects, ProjectDetail, Awards
│   ├── Blog, BlogDetail, BlogCategory, Gallery, Contact, NotFound
│   ├── auth/Login.vue
│   └── admin/ (25 views)
│       ├── Dashboard, Posts(List/Create/Edit), Projects(List/Create/Edit)
│       ├── Awards(List/Create/Edit), Galleries, Testimonials(List/Create/Edit)
│       ├── Contacts, AboutSettings, SettingsForm
│       ├── MenuItemsList, PageSectionsManager
│       ├── Automation(Tokens/Logs/Docs)
│       ├── CarouselDrafts(List/Detail)
│       └── ContentEngine              # Content idea pipeline UI
├── stores/ (15 Pinia stores)
├── composables/ (29 composables)
├── components/ (47+ components)
│   ├── base/ (17 components: Button, Card, Modal, Input, Lightbox, Skeletons...)
│   ├── admin/ (DragDropList, IconPicker, IconDisplay)
│   ├── blog/ (RichTextEditor, ImageUploader, CategorySelect, BlogPostForm)
│   ├── awards/, projects/, testimonials/
│   ├── HeroSectionWOW, CTASection, TheNavigation, TheFooter
├── public/sw.js                        # Service Worker for media caching
└── router/index.js (48 routes)
```

---

## API Overview (140+ Endpoints)

### Public Routes
```
Auth:         POST /api/auth/login, /api/auth/register
Posts:        GET /api/posts, /api/posts/{slug}
Projects:     GET /api/projects, /api/projects/{slug}
Categories:   GET /api/categories, /api/categories/{slug}
Awards:       GET /api/awards, /api/awards/{id}, /api/awards/{id}/galleries
Gallery:      GET /api/galleries, /api/galleries/{id}, /api/galleries/{id}/items
Testimonials: GET /api/testimonials, /api/testimonials/{id}
Services:     GET /api/services, /api/services/{slug}
Settings:     GET /api/settings, /api/settings/about, /api/settings/site
Menu/Pages:   GET /api/menu-items, /api/page-sections
SEO:          GET /api/sitemap.xml, /api/sitemap-index.xml, /api/llms.txt, /api/llms-full.txt
Contact:      POST /api/contact (throttle: 3/15min)
Chatbot:      POST /api/chatbot/ask (throttle: 10/min)
Newsletter:   POST /api/newsletter/subscribe, DELETE /api/newsletter/unsubscribe
Activity:     GET /api/activity-feed
Health:       GET /api/health
```

### Admin Routes (auth:sanctum)
```
Dashboard:    GET /api/admin/dashboard/stats
Posts:        CRUD /api/admin/posts
Projects:     CRUD /api/admin/projects
Categories:   CRUD /api/admin/categories
Awards:       CRUD /api/admin/awards (+gallery link/unlink/reorder)
Galleries:    CRUD /api/admin/galleries (+items CRUD, bulk-upload)
Testimonials: CRUD /api/admin/testimonials
Services:     CRUD /api/admin/services
Contacts:     /api/admin/contacts (list, show, export, mark-read, delete)
Settings:     /api/admin/settings/about, /api/admin/settings/site (GET+PUT)
Menu Items:   CRUD /api/admin/menu-items (+reorder)
Page Sections:/api/admin/page-sections (list, update, reorder)
Automation:   /api/admin/automation/tokens, /api/admin/automation/logs
Carousels:    /api/admin/carousel-drafts (list, show, approve, reject, schedule)
Content Eng:  /api/admin/content-engine/* (18 endpoints: ideas CRUD, trending, pipeline gates, progress)
```

### Automation API (n8n/Zapier/Make.com)
```
Posts:        CRUD /api/automation/posts (+bulk create)
Categories:   GET /api/automation/categories
Images:       POST /api/automation/upload-image(s)
Webhook:      POST /api/automation/webhook/published
Duplicate:    POST /api/automation/posts/check-duplicate (public)
Blog:         GET /api/automation/blog/trending-topic, POST /save-draft, POST /image-webhook
Content:      GET /api/automation/content-ideas/pending, PUT /{id}/progress, PUT /{id}/complete
Carousels:    GET /api/automation/carousel/accounts, /drafts
```

### Response Format
```json
// Success
{ "success": true, "data": {...}, "message": "..." }
// Error
{ "success": false, "error": { "code": "...", "message": "..." } }
// Pagination
{ "data": [...], "meta": { "current_page": 1, "total": 50 }, "links": {...} }
```

---

## Key Features

### Content Management
- Blog posts with rich text editor (CKEditor 5), categories, SEO fields
- Projects portfolio with case studies, tech stack, CTA fields
- Awards & recognition with gallery linking
- Gallery system with nested items and bulk upload
- Testimonials with 5-star ratings
- Dynamic navbar menu items (admin-managed)
- Dynamic homepage sections (admin-managed)
- Contact form with CSV export

### SEO & i18n
- HasSeoFields trait: meta_title, meta_description, og_image, schema_markup, canonical_url
- XML sitemap generation (posts, projects) with per-post `lastmod`
- Post & Project translations via translation tables
- GEO optimization fields (ai_summary, tech_stack_details)
- **SSR-enrichment for homepage + blog (June 2026):** Laravel `SpaPrerenderController` splices per-page `<head>`, a JSON-LD entity graph (BlogPosting / BreadcrumbList / FAQPage / WebSite / ItemList / CollectionPage), hreflang, and a crawlable `<article>` body into the built Vue SPA shell so non-JS search/LLM crawlers (ChatGPT, Perplexity, Claude, Google AI) index + cite real content; Vue still hydrates on top (progressive enhancement). 1h HTML cache with purge-on-edit. `llms.txt`/`llms-full.txt` carry freshness + `ai_summary`. Requires a one-time nginx widening — see [docs/runbooks/seo-geo-ssr-deploy.md](./docs/runbooks/seo-geo-ssr-deploy.md).

### Automation
- n8n/Zapier/Make.com integration via REST API
- API token management (create/revoke)
- Activity logging
- Webhook triggers on publish
- Bulk post creation
- Duplicate detection

### AI Content Pipeline (v2.7.2, Split Pipeline)
- AI-powered article pipeline: Ideas → Research → Article → Images → Translate → Publish
- 2-gate approval system (article text review, then image review)
- Trending topic aggregation: Google Trends + Google News (TikTok/YouTube scrapers disabled, Instagram removed)
- Spreadsheet-style admin UI for idea management (bulk actions, status-aware Play ▶ icon per row)
- **Split pipeline — all Sonnet:** prep → write → score → images → translate (uniform model, no Opus)
- **System prompt injection** via `--append-system-prompt-file` — zero Read tool calls, refs pre-compiled
- `ArticleGenerationService` with `triggerPrep()` / `triggerWrite()` / `triggerScore()` / `triggerImages()` / `triggerTranslate()` + `triggerGeneration()` fallback
- **5 scoring gates:** Quality (7/10) + Virality (3/5) + SEO (4/6) + AI Humanization (20pt) + GEO (5pt)
- **Combined 100-point weighted scoring** (min 70 to publish, 5 bands)
- 20 hard rules (incl. 107-word AI replacement system, 36 AI pattern categories, GEO/AEO formatting)
- 12 content templates with auto-selection
- **Real-time progress tracking**: progress callbacks at each step, progress modal with bar + step indicators + streaming log
- Auto-continuation via `continue-pipeline` endpoint (prep→write→score chained automatically)
- **~8-11 minutes** per article (split pipeline, down from ~15 min single-session)

### Content Pipeline Hardening (April 21, 2026)
- **Pipeline State Machine:** `ContentIdeaStatus` enum + `HasStatusTransitions` trait + `PipelineGuard` service. Strict adjacency map; illegal transitions throw `InvalidStateTransitionException`. Rolling `pipeline_state_log[]` JSON audit column (last 20 entries with from/to/reason/timestamp).
- **Segment Retry Pipeline:** per-segment status (`pending/generating/done/failed/skipped`), auto-retry job with exponential backoff (2 attempts), manual retry/skip admin endpoints, replace-variation targeting specific slots.
- **Translate-Before-Publish Gate:** sync SSH preflight blocks publish until secondary-language translation exists; 3 auto retries over 15 min; Telegram `auto_translate_exhausted` alert on exhaustion + falls through to monolingual publish.
- **Resync artisan:** `content-engine:resync-stuck-variations` backfills UI drift from authoritative `image_generation_jobs` rows (dry-run + per-idea flags supported).
- **Named-entity covers:** Wikidata SPARQL + Commons MediaWiki with license whitelist (CC0/PD/CC-BY-4.0, rejects SA); plugin 3-tier detection (title + headings + lede); backend `autoResolvePersonFromTitle` runs inline at every cover dispatch (no SSH round-trip, 1-3s cache hit).
- **meta_keywords synthesis:** 4-tier resolution with body-lede entity extraction (capitalized bigrams + mixed-case brands + ALL-CAPS acronyms), broad-topic anchor from pillar, ~80-token stopword list. 5-7 short entity tokens (web SEO best practice).
- **Telegram notifications:** `creator_brand` + `telegram` settings groups, queued `DispatchTelegramNotification` job, per-event toggles (`manifest_needed`, `generation_failed`, `publish_success`, `auto_translate_exhausted`).

### Creator Brand System (April 18, 2026)
- **Auto creator-face injection** on every cover image (no keyword gate) + on inline when `needs_creator_face: true` or human keywords match
- **Sync VD rewrite** fires on auto-inject so the prompt describes the actual person in the profile photo (no demographic contradiction)
- **DB-driven watermark** via new `creator_brand` Settings group (logo + tagline + opacity + enabled) — prompt-injected on every image type
- **Branded filenames**: `{brand-slug}-{seo-keyword}-{cover|body-N}.png` (e.g. `alisadikinma-vibe-coding-tools-cover.png`) — same pattern in storage + lightbox download
- **Per-type captions**: cover = article title, inline = 5-12 words of supporting context (plugin-authored, user-editable)
- **Dedicated admin UI card** on AboutSettings with logo uploader, tagline input, slug validator, opacity slider, enable toggle
- **Backward compatible**: `watermark_enabled` opt-in default off, legacy blog pipeline unchanged

### Performance
- **Cloudflare edge caching** — `/storage/*` + `/uploads/*` cached globally at 300+ POPs (Cache Everything, 1 month TTL), 60-80% bandwidth reduction on origin VPS
- **ETag/304 revalidation** — all JSON GET responses get a weak ETag; browser revalidates with `If-None-Match` and gets `304 Not Modified` (~80 byte response) instead of full payload (~95% bandwidth saved per revalidation)
- **WebP variant pipeline** — Intervention Image generates 4 widths (320/640/1024/1920w) + LQIP blur placeholder per image; frontend will render via `<picture><source srcset>` (Phase D)
- TanStack Query in-memory caching (5-60min stale times per resource)
- 83% faster repeat visits, 70% fewer API calls
- All pages <500ms on cached loads
- Prefetch critical data on router navigation
- Service Worker for media files (videos + images) — cache-first strategy

---

## Database (30+ Tables, 48 Migrations)

Core tables: users, posts, post_translations, categories, projects, project_translations, awards, award_gallery (pivot), galleries, gallery_items, services, testimonials, contacts, newsletters, settings, menu_items, page_sections, automation_logs, personal_access_tokens, cache, jobs, image_generation_jobs, carousel_drafts, carousel_slides, social_accounts, content_ideas

Key patterns:
- SEO fields on posts, projects, categories (via migration additions)
- Translation tables for i18n (post_translations, project_translations)
- Pivot table for award-gallery relationships
- is_active + sort_order on most content tables
- Credential fields on awards (issuer, credential_id, credential_url)

---

## Essential Commands

```bash
# Backend
cd D:\Projects\Portfolio_v2\backend
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Fresh install
php artisan route:list                 # View routes
php artisan tinker                     # Console
php artisan cache:clear && php artisan config:clear && php artisan route:clear
php artisan test                       # Run tests
php artisan projects:import-raw-data   # Bulk import 56 projects
php artisan article:simulate {id}     # Simulate article generation (local testing)

# Frontend
cd D:\Projects\Portfolio_v2\frontend
npm run dev           # Dev server (port 5173)
npm run build         # Production build
npm run preview       # Preview build
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Class not found" | `composer dump-autoload` |
| HMR broken | `npm run dev -- --force` |
| CORS errors | Check `backend/config/cors.php`, run `php artisan config:clear` |
| DB connection failed | Verify XAMPP MySQL running, check `.env` credentials |
| Storage images 404 | `php artisan storage:link` |
| Unauthenticated | Check `Authorization: Bearer {token}` header |
| Migration failed | `php artisan migrate:fresh --seed` |
| Port in use | `netstat -ano \| findstr :5173` then `taskkill /PID {id} /F` |
| FormData PUT fails | Use `POST` with `_method=PUT` field |

---

## Deployment (Production)

```bash
# Backend
composer install --optimize-autoloader --no-dev
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan migrate --force

# Frontend
npm ci && npm run build
# Deploy dist/ to CDN or static hosting

# Environment
APP_ENV=production, APP_DEBUG=false
SANCTUM_STATEFUL_DOMAINS=alisadikinma.com
```

Production runs on VPS with Nginx + SSL (Let's Encrypt) at alisadikinma.com.

---

## Documentation Index

| Document | Description |
|----------|-------------|
| [README.md](./README.md) | This file — complete overview |
| [CLAUDE.md](./CLAUDE.md) | Claude Code development instructions |
| [backend/README.md](./backend/README.md) | Backend technical details |
| [frontend/README.md](./frontend/README.md) | Frontend architecture |
| [backend/N8N_INTEGRATION_GUIDE.md](./backend/N8N_INTEGRATION_GUIDE.md) | n8n automation guide |
| [backend/SEO_SITEMAP_ROBOTS.md](./backend/SEO_SITEMAP_ROBOTS.md) | SEO implementation |

---

## Project Statistics

| Category | Value |
|----------|-------|
| Backend Controllers | 25 |
| Backend Models | 21 |
| Backend Services | 4 |
| API Endpoints | 140+ |
| Database Tables | 30+ |
| Database Migrations | 48 |
| Frontend Views | 39 |
| Frontend Components | 47+ |
| Pinia Stores | 15 |
| Composables | 29 |
| Vue Routes | 48 |
| Security Score | 95/100 |
| Cache Hit Rate | 83% |
| Cached Load Time | <500ms |

### Technology Versions
```json
{
  "backend": { "php": "8.2", "laravel": "12.x", "sanctum": "4.x", "filament": "4.1", "mysql": "8.x" },
  "frontend": { "vue": "3.5", "vite": "7.1 (rolldown)", "pinia": "3.x", "tanstack-query": "5.90", "tailwind": "4.x" }
}
```

---

## License

**Copyright 2025-2026 Ali Sadikin.** All rights reserved. Proprietary and confidential.

Contact: ali.sadikincom85@gmail.com | Location: Batam, Indonesia

---

**Last Updated:** May 5, 2026 | **Version:** 2.4.0 | **Status:** Production Ready
