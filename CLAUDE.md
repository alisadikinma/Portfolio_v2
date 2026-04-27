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

**Controller Map (27 controllers):**
```
app/Http/Controllers/Api/
├── Admin/
│   ├── ContentIdeaController.php  # Content Engine idea pipeline (17 routes)
│   ├── DashboardController.php    # Admin dashboard stats
│   └── LinkedInDraftController.php # LinkedIn admin drafts CRUD (7 routes: list/show/update/regenerate/approve/cancel/publish-now)
├── ActivityFeedController.php     # Public activity feed
├── AuthController.php             # Login, register, logout, me
├── AutomationController.php       # n8n/Zapier automation API
├── AwardController.php            # Awards CRUD + gallery linking
├── BlogPipelineController.php     # Blog pipeline: trending, save-draft, image webhook
├── CarouselDraftController.php    # Carousel draft save/approve/reject/schedule
├── CategoryController.php         # Blog categories CRUD
├── ChatbotController.php          # AI chatbot endpoint
├── ContactController.php          # Contact form + CSV export
├── GalleryController.php          # Gallery CRUD + bulk upload
├── GalleryItemController.php      # Gallery items CRUD + bulk upload
├── GeoController.php              # GEO: llms.txt & llms-full.txt endpoints
├── LinkedInCarouselImageWebhookController.php # GeminiGen webhook for LinkedIn slide PNGs (public, single-action)
├── LinkedInOAuthController.php    # LinkedIn OAuth 2.0 flow (connect/callback/index/test/disconnect)
├── MenuItemController.php         # Dynamic navbar menu items
├── NewsletterController.php       # Newsletter subscribe/unsubscribe
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

**Model Map (23 models):**
```
app/Models/
├── Award.php              # HasSeoFields trait
├── CarouselDraft.php      # Carousel draft from Content Engine
├── CarouselSlide.php      # Individual carousel slide
├── Category.php           # HasSeoFields, HasSlug
├── Contact.php
├── ContentIdea.php        # Content Engine idea pipeline
├── Gallery.php            # award_id relationship
├── GalleryItem.php        # Belongs to Gallery
├── ImageGenerationJob.php # GeminiGen image job tracking
├── LinkedInAccount.php    # LinkedIn OAuth tokens (encrypted access_token + refresh_token casts)
├── LinkedInPost.php       # Blog→LinkedIn conversion drafts (HasStatusTransitions, SoftDeletes, 8-state FSM)
├── MenuItem.php           # Dynamic navbar
├── Newsletter.php
├── PageSection.php        # Dynamic homepage sections
├── Post.php               # HasSeoFields, SoftDeletes, HasSlug
├── PostTranslation.php    # i18n for posts
├── Project.php            # HasSeoFields, SoftDeletes, HasSlug
├── ProjectTranslation.php # i18n for projects
├── Service.php            # HasSlug
├── Setting.php            # Key-value pairs
├── SocialAccount.php      # Social media account for carousel publishing
├── Testimonial.php
└── User.php
```

**Services:**
```
app/Services/
├── ArticleGenerationService.php # SSH/local exec to trigger Claude CLI on VPS (async pipeline phases + sync VD rewrite)
├── CarouselSlideEnhancer.php    # Brand chrome injector for LinkedIn carousel slides — placeholder replacement + face_refs/file_urls resolution + chrome instruction append
├── ContentEngineService.php     # Legacy HTTP client (kept for health check proxy)
├── ImageGenerationService.php   # GeminiGen image generation (article hero/inline path)
├── LinkedInCarouselImageService.php # GeminiGen dispatch for LinkedIn carousel slide PNGs + webhook handler (mirrors onto LinkedInPost.carousel_slides JSON)
├── LinkedInGenerationService.php # SSH-invokes plugin `/linkedin-gen` CLI, parses stdout JSON, advances FSM, dispatches GenerateLinkedInCarouselImages on carousel format
├── LinkedInOAuthService.php     # OAuth 2.0 authorize URL + token exchange + refresh + /v2/me profile fetch
├── LinkedInPublishService.php   # LinkedIn REST API v2 wrapper: publishText (POST /v2/ugcPosts) + postFirstComment (wired); publishCarousel deferred until TCPDF ships
├── PipelineGuard.php            # Generic FSM advance() with uniform logging — works with any HasStatusTransitions model
└── TrendingTopicService.php     # 4-source trend aggregation (Google Trends, TikTok, YouTube, Google News)
```

**FSM Infrastructure (April 23, 2026 refactor):** [`HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) + [`PipelineGuard`](backend/app/Services/PipelineGuard.php) are now **enum-class-generic**. Each consuming model declares its status enum via `protected function statusEnumClass(): string`. `ContentIdea` returns `ContentIdeaStatus::class`; `LinkedInPost` returns `LinkedInPostStatus::class`. Guard signature widened to `advance(Model $model, BackedEnum $next, ...)`. All existing ContentIdea callers remain type-compatible.

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

**Views (13 public + 25 admin + 1 auth = 39 total):**
```
src/views/
├── Home.vue              # Hero, stats, projects, blog, testimonials, CTA
├── About.vue             # Skills, experience, education, social
├── Work.vue              # Work/portfolio page
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
    ├── AutomationDocs.vue         # API documentation page
    ├── CarouselDraftsList.vue     # Carousel drafts management
    ├── CarouselDraftDetail.vue    # Carousel draft review/approve
    └── ContentEngine.vue          # Content idea pipeline (spreadsheet UI, 4 modals)
```

**Stores (15 Pinia stores):**
```
src/stores/
├── auth.js / auth-fixed.js  # Authentication & token management
├── automation.js             # Automation API state
├── awards.js / categories.js / contacts.js / galleries.js
├── carouselDrafts.js         # Carousel draft state
├── posts.js / projects.js / settings.js / testimonials.js
├── theme.js                  # Light/dark theme
├── ui.js                     # Loading states, modals, toasts
└── index.js                  # Store exports
```

**Composables (29 composables):**
```
src/composables/
├── useAboutSettings.js    # About page data + prefetch
├── useActivityFeed.js     # Activity feed data
├── useApi.js              # Base API wrapper
├── useAuth.js             # Auth composable
├── useAutomation.js       # Automation API
├── useAwards.js           # TanStack Query cached
├── useCarouselDrafts.js   # Carousel draft management
├── useCategories.js
├── useChatbot.js          # AI chatbot interaction
├── useContact.js
├── useContentEngine.js    # Content Engine pipeline (15+ API methods)
├── useCursorSparks.js     # Cursor spark effects
├── useGallery.js          # TanStack Query cached
├── useGlobe.js            # 3D globe visualization
├── useLocalCache.js       # Local storage caching
├── useMenuItems.js        # Dynamic menu
├── useMetaTags.js         # Dynamic SEO meta from CMS
├── useModal.js
├── useNewsletter.js       # Newsletter subscribe/unsubscribe
├── usePageSections.js     # Dynamic page sections
├── usePosts.js            # TanStack Query cached
├── useProjects.js         # TanStack Query cached
├── useScrollReveal.js     # Scroll-triggered reveal animations
├── useSettings.js / useSiteSettings.js
├── useTestimonials.js     # TanStack Query cached
├── useToast.js
├── useVideoReveal.js      # Video reveal animations
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

## Database Schema (48 migrations, 30+ tables)

Key tables: users, posts, post_translations, blog_categories, projects, project_translations, awards, award_gallery_pivot, galleries, gallery_items, services, testimonials, contacts, newsletters, settings, menu_items, page_sections, automation_logs, personal_access_tokens, cache, jobs, image_generation_jobs, carousel_drafts, carousel_slides, social_accounts, content_ideas

Recent migrations (post-initial):
- `menu_items` & `page_sections` - Dynamic content management
- `project_template_fields` - Extended project metadata
- `add_cta_fields` / `related_projects` - Project enhancements
- `whatsapp_number` on contacts
- `post_translations` / `project_translations` - i18n support
- `schema_fields` on translations - SEO extensions
- `image_generation_jobs` - GeminiGen job tracking
- `carousel_drafts` & `carousel_slides` - Content Engine carousel output
- `social_accounts` - Social media publishing accounts
- `content_ideas` - Content idea pipeline with status flow
- `update_content_ideas_article_pipeline` - Article pipeline fields (generated_article, generated_images, image_instructions)
- `add_planned_filename_to_image_generation_jobs` - Branded filename field (`alisadikinma-{seo-keyword}-{segment}.png`)

### `settings` group: `creator_brand` (April 18, 2026)

Creator brand config for image watermark + filename prefix. 5 rows seeded via `CreatorBrandSettingsSeeder`:

| key | default | purpose |
|---|---|---|
| `creator_brand_logo` | null | Brand logo file (`/uploads/branding/{timestamp}_{name}.png`) — passed to GeminiGen as `file_urls` when watermark enabled |
| `creator_brand_tagline` | `alisadikinma.com` | Text rendered below logo in watermark instruction |
| `creator_brand_slug` | `alisadikinma` | Filename prefix for all generated blog images (lowercase kebab-case) |
| `watermark_opacity` | `0.30` | Stored as string `'0.00'-'1.00'`, clamped server-side to `[0.05, 0.95]` |
| `watermark_enabled` | `false` | Opt-in toggle (`'true'` / `'false'` strings) |

Admin UI: dedicated "Creator Brand — Image Watermark" card on [AboutSettings.vue](frontend/src/views/admin/AboutSettings.vue) (below Basic Information). Has its own submit button (`handleBrandSubmit`) separate from the main About form.

API routes (all `auth:sanctum`):
```
GET    /api/admin/settings/creator-brand
PUT    /api/admin/settings/creator-brand
POST   /api/admin/settings/creator-brand  (FormData with _method=PUT for logo upload)
```

### `settings` group: `telegram` (April 20, 2026)

Telegram Bot notifications for Content Engine operational alerts. 6 rows seeded via `TelegramSettingsSeeder`:

| key | default | purpose |
|---|---|---|
| `telegram_bot_token` | null | BotFather-issued bot token (response masks to `1234****wxyz`) |
| `telegram_chat_id` | null | Admin's personal chat_id (found via `getUpdates` API) |
| `telegram_enabled` | `false` | Master opt-in toggle (all notifications no-op when false) |
| `telegram_notify_manifest_needed` | `true` | Alert on public figure / landmark reference missing |
| `telegram_notify_generation_failed` | `true` | Alert on GeminiGen failure |
| `telegram_notify_publish_success` | `false` | Celebratory alert on successful publish (off by default) |

Admin UI: "Telegram Notifications" card on [AboutSettings.vue](frontend/src/views/admin/AboutSettings.vue), below Creator Brand. Send-test-message button verifies config. Notifications dispatched via queued `App\Jobs\DispatchTelegramNotification` → `App\Services\TelegramNotificationService`.

API routes:
```
GET    /api/admin/settings/telegram            (auth:sanctum)
PUT    /api/admin/settings/telegram            (auth:sanctum)
POST   /api/admin/settings/telegram/test       (auth:sanctum — triggers sendTestMessage)
```

### `settings` group: `linkedin` (April 23, 2026)

Operator-facing LinkedIn publishing flags. 7 rows seeded via `LinkedInSettingsSeeder`:

| key | default | purpose |
|---|---|---|
| `linkedin_auto_publish` | `'false'` | Master kill-switch — when OFF, approved drafts stop at `awaiting_publish` and never fire |
| `linkedin_depth_score_threshold` | `'80'` | Min depth score for auto-publish; lower drafts → `manual_review` |
| `linkedin_cancel_window_minutes` | `'15'` | Time between approval and actual publish — allows Telegram cancel |
| `linkedin_first_comment_enabled` | `'true'` | Auto-post blog link as first comment (avoids 60% LinkedIn reach penalty on body links) |
| `linkedin_first_comment_delay_seconds` | `'30'` | Delay before POSTing first comment to `/v2/socialActions/{urn}/comments` |
| `linkedin_last_test_connection_at` | null | Timestamp of last successful Test Connection |
| `linkedin_last_test_connection_result` | null | `'ok'` / `'error: {msg}'` |

**Also extends `telegram` group** with 3 LinkedIn notify toggles (live in telegram group so `TelegramNotificationService` reads from one source per Decision #9):
- `telegram_notify_linkedin_preview` — sent when draft → awaiting_publish
- `telegram_notify_linkedin_depth_failed` — sent when draft → manual_review
- `telegram_notify_linkedin_published` — sent after successful publish

Admin UI: "LinkedIn Integration — Direct OAuth" card on [AboutSettings.vue](frontend/src/views/admin/AboutSettings.vue), below Telegram card. 3-state UX (OAuth not configured / installed-not-connected / connected). OAuth connect button redirects to LinkedIn, callback flash messages rendered via `?linkedin_oauth=success|error` query params.

Env vars (infrastructure, not operator-editable):
```env
LINKEDIN_OAUTH_CLIENT_ID=         # from LinkedIn Developer App
LINKEDIN_OAUTH_CLIENT_SECRET=
LINKEDIN_OAUTH_REDIRECT_URI=https://alisadikinma.com/api/admin/linkedin/oauth/callback
LINKEDIN_OAUTH_SCOPES=w_member_social,r_liteprofile
LINKEDIN_API_BASE_URL=https://api.linkedin.com/v2
LINKEDIN_API_VERSION=202405
```

### LinkedIn Admin UI Pipeline (April 23, 2026)

Admin side of the blog→LinkedIn auto-publish workflow. Plugin `linkedin-post-writer` (WIP, separate repo at `D:\Projects\claude-plugin\linkedin-post-writer`) generates content only — **this admin UI owns all operational concerns: OAuth, schedule, publish, FSM, cancel window**. Per plugin Addendum 3, backbone is **direct LinkedIn REST API v2** (NOT MixPost — plugin Decision #6 rejected after verification that MixPost OSS doesn't support LinkedIn, only Facebook + Twitter + Mastodon).

**New tables** (migration `2026_04_23_000001_create_linkedin_tables`):
- `linkedin_accounts` — one row per OAuth-connected account, encrypted token casts, access + refresh expiry timestamps (nullable — MySQL strict mode)
- `linkedin_posts` — 24 cols, one LIVE draft per post_id (app-level invariant enforced in regenerate; MySQL doesn't support partial unique indexes)

**FSM** — [`LinkedInPostStatus`](backend/app/Enums/LinkedInPostStatus.php) enum with 8 states:
```
pending_generation → generating → validating → awaiting_publish → published
                                                 ↓         ↓
                                           manual_review  cancelled
                                                 ↓
                                                 awaiting_publish (admin approve)
failed → generating (regenerate)    cancelled → generating (regenerate)
```
Uses generic `HasStatusTransitions` trait (refactored this session). Reason strings mandatory for every transition.

**Frontend views** (all TanStack Query via [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js)):
- [`/admin/linkedin-posts`](frontend/src/views/admin/LinkedInPostsList.vue) — success feed (published / scheduled / cancelled), card grid, status filter tabs, hover actions
- [`/admin/linkedin-queue`](frontend/src/views/admin/LinkedInQueueList.vue) — triage table (manual_review default, failed, in_progress, all), inline actions + counts per tab
- [`/admin/linkedin-drafts/:id`](frontend/src/views/admin/LinkedInDraftDetail.vue) — 2-col detail with LinkedIn-style card mockup (text) / swipeable slide viewer (carousel) + validation panel + state_log timeline + inline edit mode (hashtag chip editor, char counter at 1100-1300 sweet spot, 3-5 enforced)

**Sidebar** (AdminLayout.vue): new "LinkedIn" section with Posts + Queue links between Content Engine and Testimonials.

**Plugin integration (SHIPPED April 23, 2026 session 3):** Plugin [linkedin-post-writer](https://github.com/alisadikinma/linkedin-post-writer) v0.2.0+ (Phase C4 complete — 5 content-gen skills: brief/convert/carousel/validate/gen-orchestrator) is now wired end-to-end. Plugin is content-gen-only (Addendum 3) — it emits a single JSON blob to stdout matching `OrchestratorOutputSchema`, never calls the backend. Backend owns all operational concerns:

- **[`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php)** — SSH-invokes `claude -p "/linkedin-gen <blog-JSON>" --model sonnet --append-system-prompt-file refs-linkedin-*.md` (4 compiled refs) on `claudesn@localhost`. Synchronous (~30-90s per run, 300s timeout). Parses stdout via balanced-brace scanner (tolerates trailing narration + markdown fences — see `parseOrchestratorOutput`, 8 unit tests in [`LinkedInGenerationServiceParseTest`](backend/tests/Unit/LinkedInGenerationServiceParseTest.php)). Advances FSM PendingGeneration → Generating → Validating → (AwaitingPublish|ManualReview) based on `validation.passed`. On AwaitingPublish, sets `scheduled_at` + `cancel_window_ends_at` from `linkedin_cancel_window_minutes` setting.
- **[`GenerateLinkedInPost`](backend/app/Jobs/GenerateLinkedInPost.php)** — queued wrapper, `$timeout=360s`, 2 retries (60s/300s backoff). Dispatched by regenerate endpoint + scan cron. Skips if draft no longer in a generatable state (PendingGeneration/Failed/Cancelled).
- **[`LinkedInPublishService::publishText`](backend/app/Services/LinkedInPublishService.php)** — now real. `POST /v2/ugcPosts` with `com.linkedin.ugc.ShareContent` payload, `shareMediaCategory=NONE`, `X-Restli-Protocol-Version: 2.0.0`, `LinkedIn-Version` header. Composes body = content + blank line + hashtags. Extracts URN from `X-RestLi-Id` header or body `.id`. Auto-schedules `PostLinkedInFirstComment` job (delay = `linkedin_first_comment_delay_seconds`, default 30s) when `link_comment` non-empty AND `linkedin_first_comment_enabled=true`. `publishCarousel` still returns 503 — PDF composition via TCPDF pending.
- **[`PostLinkedInFirstComment`](backend/app/Jobs/PostLinkedInFirstComment.php)** — delayed job posting the blog link as first comment via `POST /v2/socialActions/{urn}/comments`. Avoids the 60% reach penalty from body links. 3 retries (30s/2m/5m).
- **[`ProcessScheduledLinkedInPosts`](backend/app/Console/Commands/ProcessScheduledLinkedInPosts.php)** — every-minute cron (`linkedin:process-scheduled`). Three outcomes per awaiting_publish row past its cancel_window: kill-switch OFF → demote to manual_review (reason=`kill_switch_demotion`); kill-switch ON + success → published + URN stored; failure → Failed + last_error. Reads `linkedin_auto_publish` setting.
- **[`ScanBlogForLinkedInConversion`](backend/app/Console/Commands/ScanBlogForLinkedInConversion.php)** — daily 03:00 WIB (`linkedin:scan-blog --hours=24`). Finds published posts with no live `linkedin_posts` row, creates pending row + dispatches job. `--dry-run` + `--limit=N` (default 20) safety caps.
- **Regenerate endpoint** — `LinkedInDraftController::regenerate` now dispatches `GenerateLinkedInPost::dispatch($newDraft->id)` after soft-delete + create. Admin sees new row in pending_generation → generating within ~60s.

**Config** — [`config/linkedin.php`](backend/config/linkedin.php) extended with `generation` section: `driver` (ssh/local), `ssh_host/user/key`, `claude_path`, `model` (sonnet), 4 refs paths (`refs_playbook/templates/formats/carousel`), `timeout_seconds` (300). Env vars: `LINKEDIN_GEN_*` — all have sensible defaults matching article generation's SSH pattern.

**Carousel image rendering (April 27, 2026):** Slide PNGs now generated end-to-end via [`LinkedInCarouselImageService`](backend/app/Services/LinkedInCarouselImageService.php) — fired automatically by `LinkedInGenerationService::persistAndRoute` whenever a carousel draft advances past validation, and on demand via two new admin endpoints (regenerate-all + per-slide retry). See "LinkedIn Carousel Image Generation" section below for full details.

**Still deferred:**
- Carousel PDF composition via TCPDF (slide PNGs render end-to-end now; PDF assembly + LinkedIn DocumentShare upload still pending — `publishCarousel` still returns 503 until that ships)
- Telegram webhook cancel flow (existing notifications fire for preview/depth_failed/published via existing `telegram_notify_linkedin_*` flags; 2-step HMAC cancel button not built)

**Testing**: [`LinkedInPostFactory`](backend/database/factories/LinkedInPostFactory.php) + [`LinkedInPostSeeder`](backend/database/seeders/LinkedInPostSeeder.php) create 11 mock drafts across 6 FSM states. Seeder auto-creates fake "[LinkedIn Test]" blog posts when real posts are insufficient to satisfy the "one live draft per post" invariant. Run `php artisan db:seed --class=LinkedInPostSeeder`.

**Full design doc**: [docs/plans/2026-04-23-linkedin-admin-ui.md](docs/plans/2026-04-23-linkedin-admin-ui.md) — covers FSM, schema, UI views, OAuth flow, multi-platform pivot history.

### LinkedIn Carousel Image Generation (April 27, 2026)

Closes the gap where carousel drafts shipped with `copy` + `image_prompt` but no actual rendered slide PNGs (the existing pipeline persisted slides JSON without ever calling GeminiGen). Three layers shipped together:

**Plugin layer** ([linkedin-post-writer](https://github.com/alisadikinma/linkedin-post-writer) v0.3.0+) — new spec doc [`docs/rag/linkedin-playbook/07-carousel-image-standards.md`](https://github.com/alisadikinma/linkedin-post-writer/blob/main/docs/rag/linkedin-playbook/07-carousel-image-standards.md) compiled into `refs-linkedin-carousel.md` via `scripts/compile-refs.ts`. Mirrors `ai-image-carousel-prompt-gen` standards (global-config + creator-bible + prompt-formulas) trimmed to LinkedIn 4:5 only. `linkedin-carousel/SKILL.md` Step 4 rewritten to enforce: bilingual headline (Indonesian main `#FFFFFF` + English subtitle `#F5A623`), accent keywords (2-4 in `#F5A623` within main headline), `SWIPE (GESER) >` indicator below headline (omitted on CTA), page number top-left, brand icon + @alisadikinma watermark center thirty percent opacity stack on every slide, social block (IG/TikTok/LinkedIn icons + handle + portfolio URL) on CTA only, visual hook (absurdist/surreal/vividly literal scene) on cover only, WOW 8-element gate, hyperrealistic anti-AI-look rules, mobile dead zones (top 150px / bottom 200px / 75px margins), 5-paragraph prompt structure, three mandatory text-overlay phrases ("remaining text in white" / "positioned starting from the vertical center... not crammed at the very bottom" / "subtitle must not be white"). Plugin authors prose only — no URLs, no filenames, no raw percentages.

**Backend layer** — three new services + one job + one webhook controller + one migration:

- **[`CarouselSlideEnhancer`](backend/app/Services/CarouselSlideEnhancer.php)** — mirror of `CoverBrandingEnhancer` for the LinkedIn carousel pipeline. Per slide: replaces placeholder tokens (`{{CREATOR_FACE}}`, `{{BRAND_LOGO}}`, `{{HANDLE}}`, `{{PORTFOLIO_URL}}`, `{{PAGE_INDICATOR}}`, `{{SWIPE_TEXT}}`), appends brand chrome instruction paragraph (idempotent — skipped when prompt already contains both page indicator and handle), pushes creator face URL into `face_refs` for `cover`/`human_fingerprint`/`cta` layout hints, pushes brand logo URL into `file_urls` for every slide. Reads creator face from `Setting{group=about,key=profile_photo}`, brand logo from `Setting{group=creator_brand,key=creator_brand_logo}`, handle from `linkedin.creator_handle` setting (fallback: `creator_brand_slug` prefixed with @, fallback: `@alisadikinma`).
- **[`LinkedInCarouselImageService`](backend/app/Services/LinkedInCarouselImageService.php)** — orchestrator. `dispatchAllSlides()` loops `carousel_slides[]`, skips slides already `image_status='done'`, calls enhancer + GeminiGen `/generate_image` (4:5 aspect, photorealistic, multipart with `webhook_url` set to the carousel webhook endpoint). On success: creates `ImageGenerationJob` row with `type='carousel_slide'`, `linkedin_post_id`, `slide_index`, `slide_image_role`, `planned_filename={brand}-li-{draft_id}-slide-{NN}-{role}.png` (collision-aware -v2/-v3 suffix). Mutates `carousel_slides[i]` in-place with `image_status='generating'` + `image_job_uuid`. `handleWebhook()` matches by UUID + type='carousel_slide', downloads remote PNG to `storage/app/public/linkedin-carousel/`, mirrors status onto the LinkedInPost row inside `DB::transaction` + `lockForUpdate()` (concurrent webhooks safe).
- **[`GenerateLinkedInCarouselImages`](backend/app/Jobs/GenerateLinkedInCarouselImages.php)** — queued wrapper, 360s timeout, 1 try (idempotent service handles retries via per-slide endpoint). Bails when format ≠ carousel.
- **[`LinkedInCarouselImageWebhookController`](backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php)** — public single-action controller for `POST /api/automation/linkedin/carousel-image-webhook`. Routes the same GeminiGen payload shape ({event, uuid, data}) as `BlogPipelineController::imageWebhook` but to `LinkedInCarouselImageService::handleWebhook` (different mirroring path).
- **Migration `2026_04_27_000001_add_linkedin_carousel_fields_to_image_generation_jobs`** — widens `type` ENUM to include `'carousel_slide'`, adds `linkedin_post_id` (FK cascade), `slide_index` (uint16), `slide_image_role` (string 32), plus two indexes (`idx_imgjobs_li_status` + `idx_imgjobs_li_slide`). `ImageGenerationJob` model gains the columns + a `linkedinPost()` BelongsTo.
- **`LinkedInGenerationService::persistAndRoute`** — when `format=carousel`, dispatches `GenerateLinkedInCarouselImages::dispatch($draft->id)` AFTER FSM advance (regardless of AwaitingPublish vs ManualReview, because operators want to see slide PNGs during manual review too). Failures logged but don't block the FSM transition — admin can manually trigger `regenerate-images` from the UI.

**Frontend layer** — composable + UI:

- **[`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js)** — `useLinkedInDraft()` poll logic extended: now polls every 5s when ANY slide has `image_status='generating'` or `'pending'` (in addition to the existing 3s poll for FSM in-progress states). Two new mutations: `useRegenerateAllCarouselImages()` (POST `/regenerate-images`) and `useRegenerateSlideImage()` (POST `/slides/{slideIndex}/regenerate-image`).
- **[`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue)** — main slide viewer gains 4-state visual: rendered image (when `done`), animated spinner + "Rendering with GeminiGen…" copy preview (when `generating`), red error block + "Retry this slide" button (when `failed`), neutral copy preview (when `pending`/no-status). Status pill overlay top-right. Below the viewer: image generation summary line (`✓ N · ↻ N generating · ✕ N failed / total`). Thumbnail strip now shows actual rendered slide images (when ready) plus per-thumbnail status dot (emerald/cyan-pulse/red/grey). New action button "🖼 Regenerate all images" appears in the right-column actions panel for carousel drafts.

**API additions** — 3 new admin/automation routes:

```
POST   /api/admin/linkedin-drafts/{id}/regenerate-images                            (auth:sanctum)
POST   /api/admin/linkedin-drafts/{id}/slides/{slideIndex}/regenerate-image          (auth:sanctum)
POST   /api/automation/linkedin/carousel-image-webhook                              (public, GeminiGen callback)
```

**Slide JSON schema (`linkedin_posts.carousel_slides[]`)** — plugin-authored fields untouched; backend adds at runtime:

```json
{
  "slide_number": 1,
  "layout_hint": "cover",
  "copy": "...",
  "image_prompt": "<plugin-authored cinematic brief>",
  "is_cover": true,
  "is_cta": false,
  "image_status": "generating|done|failed|pending",   // backend-managed
  "image_url": "https://alisadikinma.com/storage/linkedin-carousel/...png",  // backend-managed
  "image_job_uuid": "...",                            // backend-managed (GeminiGen UUID)
  "image_error": null                                 // backend-managed (failure reason)
}
```

**Filename pattern** — `{creator_brand_slug}-li-{draft_id}-slide-{NN}-{role}.png` (e.g., `alisadikinma-li-28-slide-01-cover.png`, `alisadikinma-li-28-slide-09-cta.png`). Stored at `storage/app/public/linkedin-carousel/`.

**Operational behavior:**
1. Plugin generates carousel JSON → `LinkedInGenerationService` parses → FSM Generating → Validating → AwaitingPublish/ManualReview
2. Same `persistAndRoute` step dispatches `GenerateLinkedInCarouselImages` job
3. Job loops slides → `CarouselSlideEnhancer.enhance()` → GeminiGen multipart POST per slide → status flipped to `generating`, UUID stored
4. GeminiGen callbacks hit `/automation/linkedin/carousel-image-webhook` → service downloads PNG → updates ImageGenerationJob row → mirrors onto LinkedInPost.carousel_slides[]
5. Frontend polls every 5s while any slide is mid-flight → status pills + thumbnails update live
6. Operator can retry single failed slides (hover "Retry this slide" button on viewer) or re-dispatch all slides ("🖼 Regenerate all images" action button)

### Named Entity Cover Generation (April 20-21, 2026) — UPDATED

Fixes a major bug where blog covers about public figures (Dario Amodei, Elon Musk) or famous landmarks (White House, Capitol) silently used Ali's face instead of the real subject.

**Plugin-side detection (v2.7.1+):** `/article-images` Phase 3.5b detects named entities (persons / landmarks / logos / products) using a 3-tier structural gate — title, H2/H3 headings, AND lede (first 2 paragraphs). News articles routinely put role+org in the title ("Anthropic CEO visits White House") and the actual person's name in the lede sentence — earlier title-only gate missed these cases. Plus role-resolution rule: when title contains role+org without person name, scan lede for `[Title] [Person Name]` pattern OR use training knowledge to map `Anthropic CEO → Dario Amodei`.

**Plugin-side SEO field audit (v2.7.2):** Hard pre-save gate in `/article-write` that lists 7 required SEO fields (`meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `ai_summary`, `faq_schema`) with each field's common skip pattern. Sonnet was silently omitting `meta_keywords` from save-article payloads, cascading to backend fallback emitting literal `"AI & Tech"` for every post.

**Backend auto-resolve fallback (April 21):** [`CoverBrandingEnhancer::autoResolvePersonFromTitle`](backend/app/Services/CoverBrandingEnhancer.php) — when `entity_refs[]` is empty AND segment is cover, attempts inline person resolution via 2-pass strategy: (1) scan title for proper-noun bigrams ("Dario Amodei", "Sam Altman") + try Wikidata directly, (2) detect role+org pattern, scan article body lede for "[role] [Name]" pattern. Synchronous direct PHP call to `EntityReferenceService::findOrFetch()` — no SSH round-trip, 1-3s cache hit, 5-10s fresh fetch. Catches: (a) articles authored before plugin v2.7.1 shipped, (b) plugin runs where role-resolution missed, (c) operators clicking Generate AI without first refreshing prompts.

**Backend flow:** [`EntityReferenceService::findOrFetch`](backend/app/Services/EntityReferenceService.php) queries Wikidata SPARQL (notability gate: sitelinks ≥ 5, P18 image exists) + Commons MediaWiki API (license whitelist: CC0, Public Domain, PD-USGov, CC-BY-4.0 — rejects CC-BY-SA share-alike). Valid hits download to `storage/app/public/entity-refs/{type}/{qid}_{slug}.{ext}` and persist in the new `entity_references` table for zero-roundtrip cache reuse on subsequent articles.

**Cover branding gate:** [`CoverBrandingEnhancer::enhance`](backend/app/Services/CoverBrandingEnhancer.php) — when any entry in `image_prompts[i].entity_refs[]` has `entity_type='person'` on a cover, SKIP prepending Ali's creator face + SKIP VD auto-rewrite (plugin VD already names the person). Landmark/logo/product alone still inject creator face (Ali visits the landmark). All entity URLs merge into GeminiGen `file_urls`. Watermark + title overlay + branded filename unchanged.

**Manifest + manual upload:** Plugin flags unfetchable entities (license fail, notability fail) in `manifest.entity[]` alongside existing `manifest.brand[]`. Backend progress endpoint persists to `content_ideas.pending_manifest`, flips status to `awaiting_manual_upload`, and dispatches `DispatchTelegramNotification` job. Admin resolves via `POST /admin/content-engine/ideas/{id}/upload-entity-reference` (creates `source=user_upload` EntityReference row + patches segments) or `skip-entity-reference` (removes entity, falls back to creator face).

**New status:** `content_ideas.status` enum expanded to include `awaiting_manual_upload` between `generating_images` and `images_ready`.

**UI:** [`EntityUploadSlot.vue`](frontend/src/components/admin/EntityUploadSlot.vue) renders per-entity chips (green fetched, red missing, amber skipped) in Gate 2 Image Config modal.

API routes:
```
GET    /api/automation/entity-refs/lookup?name=...&type=...   (auth:sanctum, plugin)
POST   /api/admin/content-engine/ideas/{id}/upload-entity-reference   (auth:sanctum)
POST   /api/admin/content-engine/ideas/{id}/skip-entity-reference     (auth:sanctum)
```

Tests: 30+ across 10 phases covering model, service, gate, dispatch, manifest, upload/skip, lookup, E2E. See design doc at `docs/plans/2026-04-20-named-entity-aware-cover-generation.md`.

## Critical Schema Notes

**`posts` table has NO `title`, `content`, or `excerpt` columns.**
These fields live in `post_translations` table. The Post model uses
`translations()` hasMany relationship. Always query `PostTranslation`
for title/content, never `Post` directly.

**Spatie HasSlug:** Post model uses `doNotGenerateSlugsOnUpdate()` —
slug is pre-set by controllers since there's no `title` column to generate from.

## API Routes (140+ endpoints)

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
GET    /api/llms.txt, /api/llms-full.txt
GET    /api/health
GET    /api/activity-feed
POST   /api/contact (throttle: 3/15min)
POST   /api/chatbot/ask (throttle: 10/min)
POST   /api/newsletter/subscribe (throttle: 5/60min)
DELETE /api/newsletter/unsubscribe
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
/api/admin/carousel-drafts (list, show, approve, reject, schedule, slide status)
/api/admin/content-engine/* (see Content Engine section below)
/api/admin/linkedin-drafts/* (see LinkedIn Admin Routes section below)
/api/admin/linkedin/* (OAuth: connect, callback, account list, test, disconnect)
/api/admin/settings/linkedin (GET, PUT — operator-facing publishing flags)
```

### Admin Content Engine Routes (auth:sanctum, 19 endpoints)
```
GET    /api/admin/content-engine/health              # CLI system health check
GET    /api/admin/content-engine/workflows            # List workflows from DB
GET    /api/admin/content-engine/workflows/{id}       # Workflow status
GET    /api/admin/content-engine/ideas                # List ideas (filter: pillar, status)
POST   /api/admin/content-engine/ideas                # Create idea
PUT    /api/admin/content-engine/ideas/{id}           # Update idea
DELETE /api/admin/content-engine/ideas/{id}           # Delete idea
POST   /api/admin/content-engine/ideas/{id}/archive   # Archive idea
POST   /api/admin/content-engine/ideas/{id}/restore   # Restore archived idea
POST   /api/admin/content-engine/ideas/{id}/revert    # Revert to draft
GET    /api/admin/content-engine/trending             # Pull trending topics
POST   /api/admin/content-engine/trending/import      # Import trending as ideas
POST   /api/admin/content-engine/ideas/{id}/research          # Gate 1: Start article generation (SSH → Claude CLI)
GET    /api/admin/content-engine/ideas/{id}/research          # Get research status
GET    /api/admin/content-engine/ideas/{id}/progress          # Real-time progress (percentage + log)
POST   /api/admin/content-engine/ideas/{id}/approve-article   # Gate 1: Approve article text
POST   /api/admin/content-engine/ideas/{id}/generate-images   # Gate 2: Start image generation
POST   /api/admin/content-engine/ideas/{id}/generate-segment-image  # Gate 2: Dispatch or replace a single segment/variation
POST   /api/admin/content-engine/ideas/{id}/retry-segment/{i} # Gate 2: Manual retry of a failed segment (FSM-gated)
POST   /api/admin/content-engine/ideas/{id}/skip-segment/{i}  # Gate 2: Skip a failed segment and continue pipeline
POST   /api/admin/content-engine/ideas/{id}/rewrite-vd        # Gate 2: Rewrite VD to match face reference (sync Sonnet)
POST   /api/admin/content-engine/ideas/{id}/regenerate-image-prompts # Gate 2: Refresh plugin-authored prompts (subset supported)
PUT    /api/admin/content-engine/ideas/{id}/update-image-concept # Gate 2: Edit per-section image_concept
POST   /api/admin/content-engine/ideas/{id}/upload-entity-reference # Gate 2: Manual reference upload when Wikidata fails
POST   /api/admin/content-engine/ideas/{id}/skip-entity-reference   # Gate 2: Drop a manifest-flagged entity
POST   /api/admin/content-engine/ideas/{id}/translate-article # Pre-publish: translate primary → secondary language (sync, FSM-gated)
POST   /api/admin/content-engine/ideas/{id}/publish           # Gate 2: Approve images & publish
```

### Admin LinkedIn Routes (auth:sanctum, 14 endpoints + 1 public webhook)
```
# Draft CRUD (matches plugin design §4.5)
GET    /api/admin/linkedin-drafts              # List (filter: ?status, ?format, ?scope=feed|queue, ?per_page)
GET    /api/admin/linkedin-drafts/{id}         # Show with eager-loaded post.translations + state_log
PUT    /api/admin/linkedin-drafts/{id}         # Edit content + link_comment + hashtags + carousel_slides (saves, no FSM transition — re-validate via plugin Phase D3)
POST   /api/admin/linkedin-drafts/{id}/regenerate    # Soft-delete current + create new pending_generation row (409 if duplicate live)
POST   /api/admin/linkedin-drafts/{id}/approve       # manual_review → awaiting_publish (sets scheduled_at + cancel_window_ends_at per linkedin_cancel_window_minutes)
POST   /api/admin/linkedin-drafts/{id}/cancel        # Any non-terminal → cancelled
POST   /api/admin/linkedin-drafts/{id}/publish-now   # awaiting_publish → published (via LinkedInPublishService; 503 until OAuth configured + plugin content-gen wired)

# Carousel image generation (April 27, 2026)
POST   /api/admin/linkedin-drafts/{id}/regenerate-images                          # Re-dispatch GeminiGen for every non-done slide
POST   /api/admin/linkedin-drafts/{id}/slides/{slideIndex}/regenerate-image       # Re-dispatch single slide (per-slide retry button)
POST   /api/automation/linkedin/carousel-image-webhook                            # PUBLIC — GeminiGen callback (mirrors slide status onto carousel_slides JSON)

# OAuth (direct LinkedIn, not MixPost — plugin Addendum 3)
GET    /api/admin/linkedin/connect             # Generate LinkedIn authorize URL + CSRF state token
GET    /api/admin/linkedin/oauth/callback      # PUBLIC (no auth) — LinkedIn redirects here; exchanges code for tokens
GET    /api/admin/linkedin/account             # List connected accounts + oauth_configured flag
POST   /api/admin/linkedin/account/{id}/test   # Ping /v2/me to verify token still works
DELETE /api/admin/linkedin/account/{id}        # Disconnect (delete row; LinkedIn has no revoke endpoint)
```

### Automation Routes
```
POST   /api/automation/posts/check-duplicate (public)
POST   /api/automation/blog/image-webhook (public, GeminiGen callback)
POST   /api/automation/carousel/save-draft (public, Content Engine callback)
GET    /api/automation/posts (auth + throttle:60/min)
POST   /api/automation/posts, /api/automation/posts/bulk
PUT    /api/automation/posts/{id}
DELETE /api/automation/posts/{id}
GET    /api/automation/categories
POST   /api/automation/upload-image, /api/automation/upload-images
POST   /api/automation/webhook/published
GET    /api/automation/blog/trending-topic
POST   /api/automation/blog/save-draft
GET    /api/automation/blog/image-status/{postId}
GET    /api/automation/content-ideas/pending
PUT    /api/automation/content-ideas/{id}/complete
GET    /api/automation/carousel/accounts, /drafts, /drafts/{id}
```

## Blog & Content Pipeline Architecture

### Content Idea Pipeline (Active)

**Status Flow (FSM — see [ContentIdeaStatus enum](backend/app/Enums/ContentIdeaStatus.php)):**
```
draft → researching → article_ready → generating_images → images_ready → completed → archived
                 ↘                ↘                    ↘             ↘
                   failed          awaiting_manual_upload            failed
```
All states strict-transition via [`HasStatusTransitions::transitionTo`](backend/app/Traits/HasStatusTransitions.php) — illegal transitions throw `InvalidStateTransitionException` and append a `pipeline_state_log[]` audit entry (last 20 kept). See the new "Pipeline State Machine" section below.

**2-Gate Approval System:**
- **Gate 1 (Article):** Idea → Start Research → Review Generated Article → Approve Article Text
- **Gate 2 (Images):** Generate Images → Review Images → Approve & Publish to Blog

**CLI-Based Triggering:** Content generation triggered via Claude Code CLI (not RemoteTrigger).
The `ContentIdeaController` orchestrates the full pipeline through the admin UI (`ContentEngine.vue`).

**Key Components:**
- `ContentIdea` model — tracks ideas with status, progress_percentage, current_step, progress_log, generated_article, generated_images
- `ContentIdeaController` — 18 admin endpoints for full pipeline management (incl. progress tracking)
- `ArticleGenerationService` — SSH/local exec to trigger Claude Code CLI on VPS
- `ContentEngineService` — Legacy HTTP client (kept for backward compatibility)
- `TrendingTopicService` — aggregates trends from Google News (RSS, working) + Google Trends (dailytrends JSON API, working). TikTok/YouTube scrapers exist but disabled in UI (brittle). Instagram not implemented.
- `useContentEngine.js` — Vue composable with 16+ API methods (incl. getProgress)

**Automation Endpoints (for CLI plugin callbacks):**
- `GET /api/automation/content-ideas/pending` — get next idea in `researching` status
- `PUT /api/automation/content-ideas/{id}/progress` — progress callback (step, percentage, message)
- `PUT /api/automation/content-ideas/{id}/complete` — mark as `article_ready` with generated article
- `PUT /api/automation/content-ideas/{id}/save-image-prompts` — Gate 2 split flow: /article-images persists authored prompts
- `GET /api/automation/posts/{id}/for-translation` — Finalize: /article-translate reads primary-language Post
- `PUT /api/automation/posts/{id}/save-translation` — Finalize: /article-translate persists post_translations.{en} row
- `POST /api/automation/posts/{id}/translation-complete` — Finalize: flips `translation_pending=false`
- `PUT /api/automation/posts/{id}/progress` — Finalize: /article-translate progress callback

**Admin Endpoints (Gate 2 split flow):**
- `PUT /api/admin/content-engine/ideas/{id}/update-image-concept` — user edits per-section image_concept
- `POST /api/admin/content-engine/ideas/{id}/regenerate-image-prompts` — triggers /article-images (all or `{sections:[...]}` filtered)
- `POST /api/admin/content-engine/ideas/{id}/rewrite-vd` — sync Sonnet rewrite of VD to match face reference

### Blog Pipeline (Legacy Endpoints)

**Endpoints:**
- `GET /api/automation/blog/trending-topic` — 4-source trend aggregation
- `POST /api/automation/blog/save-draft` — saves article + queues image generation
- `POST /api/automation/blog/image-webhook` — GeminiGen callback (public, no auth)
- `GET /api/automation/blog/image-status/{postId}` — poll image job status
- `php artisan blog:process-images` — fallback: polls GeminiGen for pending jobs

**Image Generation:** GeminiGen API, fire-and-forget with webhook.
Use `generated_image[0].image_url` (R2 signed URL), NOT `file_download_url` (requires extra auth).
Store full URLs (`url('/storage/...')`), not relative paths.

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
php artisan article:simulate {ideaId}  # Simulate article generation progress (local testing)
php artisan content-engine:resync-stuck-variations [--dry-run] [--idea=N]  # Backfill drifted UI variation status from authoritative ImageGenerationJob rows
```

### Frontend (Vue)
```bash
cd D:\Projects\Portfolio_v2\frontend
npm run dev           # Start Vite dev server (port 5173)
npm run build         # Production build
npm run preview       # Preview production build
```

## Deployment (Auto CI/CD — NO Manual Deploy)

**Production deploys are fully automated via GitHub Actions. Never SSH-deploy manually.**

Every `git push origin main` triggers [.github/workflows/deploy.yml](.github/workflows/deploy.yml), which SSHs into the VPS and runs [scripts/deploy.sh](scripts/deploy.sh):

1. `git fetch + reset --hard origin/main`
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force`
4. Idempotent seeders (e.g. `CreatorBrandSettingsSeeder`)
5. Laravel cache clear + recache (config/route/view)
6. `npm ci + npm run build` (frontend)
7. Fix `storage/` + `bootstrap/cache/` permissions
8. `php artisan queue:restart`
9. Health check via `curl https://alisadikinma.com/api/health`

**Workflow:**
```bash
git add <files>
git commit -m "..."
git push origin main   # ← auto-deploys, watch at https://github.com/alisadikinma/Portfolio_v2/actions
```

**Manual trigger** (rare — e.g. retry after infra fix): Actions tab → *Deploy to VPS* → Run workflow.

**Bypass build steps** (debug only): `DEPLOY_SKIP_FRONTEND=1` or `DEPLOY_SKIP_COMPOSER=1` on the VPS when running `scripts/deploy.sh` directly.

**Required GitHub secrets** (already configured — see [.github/workflows/README.md](.github/workflows/README.md)): `VPS_SSH_HOST`, `VPS_SSH_USER`, `VPS_SSH_KEY`, `VPS_SSH_PORT`, `VPS_PROJECT_PATH`.

**Concurrency:** `deploy-production` group with `cancel-in-progress: false` — pushes during an active deploy queue rather than interrupt mid-migration.

**DO NOT:**
- Manually `ssh` and run `git pull` / `deploy.sh` (creates drift vs. workflow state)
- `--force` push to `main` (breaks concurrent deploy assumptions)
- Edit files directly on the VPS (next deploy will `git reset --hard` over them)

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

## Page Sections Mapping (admin ↔ view)

`/admin/page-sections` rows are keyed by `(page_type, section_type)`. The table below lists which view reads which `section_type` — if a row exists in DB but isn't listed here, toggling it is a **ghost toggle** (no render effect). Always keep this in sync when adding/removing sections from views.

| page_type | section_type | Rendered by | Component |
|---|---|---|---|
| `homepage` | `hero` | [Home.vue](frontend/src/views/Home.vue) | `CinematicHero` |
| `homepage` | `skills-reel` | Home.vue | `SkillsReel` |
| `homepage` | `skill-vibe-coding` | Home.vue | `SkillShowcase` |
| `homepage` | `skill-ai-automation` | Home.vue | `SkillShowcase` |
| `homepage` | `skill-ai-agents` | Home.vue | `SkillShowcase` |
| `homepage` | `skill-ai-video` | Home.vue | `SkillShowcase` |
| `homepage` | `featured-projects` | Home.vue | `ProjectsBento` |
| `homepage` | `latest-blog` | Home.vue | `LatestBlog` (asymmetric 1-large + 2-stacked with thumbnails) |
| `homepage` | `stats-cta` | Home.vue | `StatsBar` + `CTASection` (home variant) |
| `about` | `cta` | [About.vue](frontend/src/views/About.vue) | `CTASection` (root variant, WhatsApp + social) |
| `projects` | `cta` | [Projects.vue](frontend/src/views/Projects.vue) | `CTASection` (root variant) |
| `gallery` | `cta` | [Gallery.vue](frontend/src/views/Gallery.vue) | `CTASection` (root variant) |
| `blog` | `cta` | [BlogDetail.vue](frontend/src/views/BlogDetail.vue) | `CTASection` (root variant) — **article detail, NOT list** |

**Naming convention:** `section_type` is kebab-case. Legacy snake_case rows (`featured_projects`, `latest_blog`, `cta` on homepage) exist in production DB as orphan ghosts from the original seeder — views do not read them. Don't revive snake_case; always use kebab-case for new sections and keep `PageSectionSeeder` in sync.

**Home.vue snap-section gotcha:** `.snap-section` wrapper has `min-height: 100dvh` — putting `v-if="isSectionActive(...)"` on the inner component while keeping the wrapper always-rendered leaves a full-viewport blank space when the section is toggled off. Always put the `v-if` on the `<div class="snap-section">` wrapper itself so the whole section collapses.

## Performance & Caching

**TanStack Query Cache Strategy:**
- Posts: 5min stale time (frequent updates)
- Projects: 60min stale time
- Awards: 60min stale time
- Testimonials: 30min stale time
- Gallery: 60min + smart invalidation on mutations
- About Settings: prefetched on router navigation
- Page Sections: **30s staleTime + `refetchOnMount: 'always'`** — operators toggle visibility in `/admin/page-sections` and expect effect within next navigation, not 10 minutes. Override of global default (`refetchOnMount: false`).

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

### After Changes (MANDATORY):
1. Run tests
2. **Update CLAUDE.md** — every change that touches architecture, routes, schema, composables, page-section mapping, new env vars, or pipeline stages MUST be reflected in the relevant CLAUDE.md section (root / backend / frontend) before commit. Skipping this leaves next session (or next contributor) debugging stale docs. Also update the "Last Updated" line at the bottom of root CLAUDE.md.
3. Commit with conventional commits: `feat:`, `fix:`, `docs:`, etc.

### Git Push Policy (STRICT)
**Default after any fix = commit ONLY. Never push autonomously.**

- After completing a fix or feature, stop at `git commit`. Do not run `git push` unless the user explicitly asks (e.g. "push", "deploy", "naikin ke prod", "push main").
- Push triggers VPS auto-deploy via GitHub Actions CI/CD (see `.github/workflows/`) — pushing without permission deploys straight to production.
- When the user does ask to push, a single `git push origin main` is sufficient; CI/CD handles SSH + `deploy.sh` on the VPS.
- If the harness blocks the push (main-branch guard), surface it to the user with the exact command to run manually — do not try to work around the block.
- Never force-push to `main`.

## GEO (Generative Engine Optimization) / LLM-Friendly

### Current State (Fixed April 10, 2026)

**Overall Score: 7.5/10** — All P0-P2 issues resolved. Remaining gap: SPA without SSR (P0 deferred — needs architecture decision).

### What's Implemented

**Backend GEO Infrastructure:**
- `GeoController.php` → `/api/llms.txt` (concise) + `/api/llms-full.txt` (comprehensive)
- `SitemapController.php` → 4 XML sitemaps at `/api/sitemap*.xml` + `apiUrl()` helper for https
- `HasSeoFields` trait → Person, BlogPosting, CreativeWork JSON-LD schemas
- `PostResource` / `ProjectResource` → Rich SEO metadata (meta_title, og_image, schema_markup, faq_schema, ai_summary, canonical_url)
- `robots.txt` (backend) → Crawl rules, AI bot allow-list (GPTBot, ClaudeBot, PerplexityBot), rate limiting

**Frontend SEO Infrastructure:**
- `useMetaTags.js` → Dynamic meta tags, JSON-LD injection, BreadcrumbList schema, ArticleSchema, ProjectSchema
- `index.html` → Static OG/Twitter meta tags + `<link rel="alternate" href="/llms.txt">` + `<link rel="sitemap">`
- `frontend/public/llms.txt` → Static root-level LLM profile (+ link to dynamic API version)
- `frontend/public/sitemap.xml` → Root sitemap index pointing to API sitemaps
- `robots.txt` (frontend) → Production URLs, AI crawler allow-list, sitemap references
- Semantic HTML: `<article>`, `<section>`, `<main>`, `<aside>`, `<header>`, `<footer>`, `<figure>`, `<figcaption>`, `<time>`
- BreadcrumbList JSON-LD on BlogDetail and ProjectDetail pages
- BlogPosting JSON-LD on blog posts, CreativeWork JSON-LD on projects
- Proper H1→H2→H3 hierarchy, alt text on all images, slug-based URLs

### Remaining Issue

| Priority | Issue | Status |
|----------|-------|--------|
| **P0** | **SPA without SSR** — Server returns `<div id="app"></div>`. JS-required for content. | Deferred — needs `vite-plugin-prerender` or Nuxt migration |
| **P3** | No `.well-known/ai-plugin.json` | Optional |

### Fixed Issues (April 10, 2026)

- **robots.txt** — Both frontend/backend now use `https://alisadikinma.com/api/sitemap*.xml`
- **Root /llms.txt** — Static file in `frontend/public/llms.txt` with profile + API links
- **Root /sitemap.xml** — Static sitemap index in `frontend/public/sitemap.xml`
- **sitemap-index.xml** — Fixed `http://` → `https://` via `apiUrl()` helper
- **Per-page meta tags** — BlogDetail + ProjectDetail inject dynamic OG/Twitter/canonical
- **`<time datetime>`** — Blog.vue, BlogDetail.vue use semantic `<time>` elements
- **`<figure>/<figcaption>`** — BlogDetail featured image + ProjectDetail hero image
- **BreadcrumbList** — JSON-LD on BlogDetail (Home→Blog→Category→Post) and ProjectDetail (Home→Projects→Project)
- **Article schema** — BlogPosting JSON-LD injected on blog post pages
- **Project schema** — CreativeWork JSON-LD injected on project detail pages
- **AI crawler allow-list** — GPTBot, ClaudeBot, PerplexityBot, GoogleOther explicitly allowed in robots.txt
- **index.html** — Added `<link rel="alternate">` for llms.txt and `<link rel="sitemap">` for sitemap

### llms.txt Specification

The site follows the [llms.txt standard](https://llmstxt.org/):
- **`/llms.txt`** — Static root file: name, expertise, links to API endpoints
- **`/api/llms.txt`** — Dynamic concise: name, title, bio, top 20 projects, 10 recent posts, contact
- **`/api/llms-full.txt`** — Dynamic full dump: skills, all awards, all projects, all blog posts with excerpts
- API endpoints return `text/plain; charset=utf-8`, data pulled live from database

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

## Content Pipeline (CLI-Based)

Article content generation uses **Claude Code CLI + plugins** on VPS, NOT HTTP microservice calls.
Carousel/video content handled by Sparkfluence platform (separate project).

### Architecture — Split Pipeline (v2.0.0)
```
Admin Panel (/admin/content-engine)
       │
  Add ideas (manual or Pull Trending)
  Status: draft → researching → article_ready → generating_images → images_ready → completed
       │
  Gate 1: Configure (languages + instructions) → Start Research
  Gate 2: Configure image gen (instructions + reference uploads) → Approve
       │
  ┌────▼─────────────────────────────────────────────────────────────┐
  │  Laravel Backend (ArticleGenerationService)                      │
  │    SSH → claudesn@localhost (VPS) with file-based prompt         │
  │                                                                  │
  │  Split Pipeline (4 CLI calls, uniform Sonnet):                   │
  │                                                                  │
  │  Step 1-3: claude -p "/article-prep ..."                         │
  │    --model sonnet                                                │
  │    --append-system-prompt-file refs-prep.md                      │
  │    → Progress: 5%, 15%, 25%, 35%                                 │
  │    → save-prep → continue-pipeline                               │
  │    → ~2-3 min                                                    │
  │                                                                  │
  │  Step 4: claude -p "/article-write ..."                          │
  │    --model sonnet                                                │
  │    --append-system-prompt-file refs-write.md                     │
  │    → Progress: 50%, 70%, 78%, 82%, 85%                           │
  │    → save-article → continue-pipeline                            │
  │    → ~3-4 min                                                    │
  │                                                                  │
  │  Step 5: claude -p "/article-score ..."                          │
  │    --model sonnet                                                │
  │    --append-system-prompt-file refs-score.md                     │
  │    → Progress: 90%, 94%, 97%, 100%                               │
  │    → completion callback (5 gates + combined 100-point)          │
  │    → ~1 min                                                      │
  │                                                                  │
  │  [Gate 1 user approval of article text]                          │
  │                                                                  │
  │  Gate 2: claude -p "/article-images ..."                         │
  │    --model sonnet                                                │
  │    --append-system-prompt-file refs-images.md                    │
  │    → Reads outline.sections[].image_concept blueprint            │
  │    → Expands each into 300-500 word cinematic prompt             │
  │      (8-element WOW + 5-paragraph structure)                     │
  │    → save-image-prompts → continue-pipeline(phase=images)        │
  │    → Backend → ImageGenerationService → GeminiGen                │
  │    → Gated by ARTICLE_GEN_USE_IMAGES_PHASE (default false)       │
  │    → ~1-2 min                                                    │
  │                                                                  │
  │  [Gate 2 user approval of images → Approve & Publish]            │
  │                                                                  │
  │  Finalize: Backend creates Post + primary post_translations row  │
  │    Then optionally: claude -p "/article-translate ..."           │
  │      --model sonnet                                              │
  │      --append-system-prompt-file refs-translate.md               │
  │      → Reads post primary translation (ID)                       │
  │      → Translates title + content + meta + alt text → EN         │
  │      → save-translation → post_translations.en row created       │
  │      → translation-complete → translation_pending=false          │
  │      → Gated by ARTICLE_GEN_USE_TRANSLATE_PHASE (default false)  │
  │      → On failure: ProcessPendingTranslations cron retries       │
  │        every 5min up to 3 attempts                               │
  │      → ~30-60 sec                                                │
  │                                                                  │
  │  Fallback: claude -p "/article-gen ..." (single-session, all     │
  │    steps in one call — used when refs not configured)             │
  │                                                                  │
  │  Total: ~8-11 min (vs ~15 min single-session)                    │
  └──────────────┬───────────────────────────────────────────────────┘
                 │
  Frontend polls GET /ideas/{id}/progress every 3 seconds
  Progress Modal: progress bar + step indicators + streaming log
```

### Admin UI: Content Engine Page (`ContentEngine.vue`)
- **Spreadsheet-style idea management** with filters (pillar, status, priority, search)
- **Bulk selection** — checkbox column + sticky bulk bar with 4 actions (Start Research, Archive, Revert to Draft, Delete) using chunked `Promise.all` (3 concurrent for SSH-heavy Start Research, 10 for others)
- **Published column** — relative "Xh ago / Xd ago" with absolute datetime tooltip, sourced from `content_ideas.source_data.pub_date` JSON
- **Status-aware Play ▶ icon** per row — single icon button replaces 7 status-specific text buttons, tooltip changes per status (Start Research / View Progress / Preview Article / Finalize / View / Restore)
- **Pull Trending** — 2 working sources (Google News + Google Trends); YouTube + TikTok disabled with "Coming soon" badge; Instagram removed (no backend)
- **2-gate approval pipeline** — nothing auto-generates without user confirmation
- **5 modals**: Trending Preview (wide 4-col grid, pagination), Config (language + instructions), **Progress Modal** (progress bar + step indicators + streaming log), Article Preview, Image Config (with reference upload)
- **Real-time progress tracking** — polls every 3 seconds during `researching` / `generating_images` status

### Content Idea Status Flow
```
draft → researching → article_ready → generating_images → images_ready → completed
                            ↑                                    ↑
                      Gate 1: approve text              Gate 2: approve images
```

### Automation Endpoints (for CLI plugin callbacks)
```
GET  /api/automation/content-ideas/pending              → Get next idea to generate
GET  /api/automation/content-ideas/{id}                  → Get full idea data (for article-write/score/images to read prep data)
PUT  /api/automation/content-ideas/{id}/progress         → Report step progress (percentage + message)
PUT  /api/automation/content-ideas/{id}/save-prep        → Save prep data (research + strategy + outline)
PUT  /api/automation/content-ideas/{id}/save-article     → Save article data (merge with prep data)
PUT  /api/automation/content-ideas/{id}/save-image-prompts → Gate 2: persist /article-images authored prompts
POST /api/automation/content-ideas/{id}/continue-pipeline → Trigger next phase (prep→write→score, or phase=images → GeminiGen)
PUT  /api/automation/content-ideas/{id}/complete          → Save completed article + all 5 scores
POST /api/automation/blog/save-draft                     → Direct blog post save (fallback)
```

### Admin Endpoints (Gate 2 split flow)
```
PUT  /api/admin/content-engine/ideas/{id}/update-image-concept      → Edit per-section image_concept
POST /api/admin/content-engine/ideas/{id}/regenerate-image-prompts  → Trigger /article-images (all or {sections:[...]})
POST /api/admin/content-engine/ideas/{id}/rewrite-vd               → Sync Sonnet: rewrite VD to match face reference
```

### Shared Image Positioning Helper

`frontend/src/utils/imagePositioning.js` — pure ES module used by both
`ArticlePreview.vue` (Step 1) and `ArticleFinalize.vue` (Step 3) so body
images render at identical positions in both views. Resolves placement
from plugin's `insert_after_heading` + `suggested_position` hints with
even-distribute fallback. Smoke test at `imagePositioning.test.mjs`.

### Variation Cleanup Timing

Image variants (`image_prompts[].variations[]`, max 3 per segment) are
preserved through Step 2 and Step 3 so users can navigate back and
reselect. Compaction to the selected variant and deletion of non-selected
files runs server-side **only** at final Publish time, inside
`ContentIdeaController::approveAndPublish` (after Post + PostTranslation
are written). The `/cleanup-variation-images` route still exists but is
no longer called from the frontend.

### Variation Replace UX (April 21, 2026)

Each done variation thumbnail in [`ImageGeneration.vue`](frontend/src/views/admin/ImageGeneration.vue) shows a hover-revealed amber **refresh icon** in the top-right corner. Clicking it confirms with the operator then dispatches a new GeminiGen run targeting THAT specific slot — slot's `status` flips to `generating`, image swaps when webhook fires (~30s). Implemented via new optional `replace_variation_index` param on [`generateSegmentImage`](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php) which overrides the retry-in-place heuristic and targets the exact slot regardless of current status. Replaces the previous "Regen Prompt" button (removed Apr 21) — now redundant since `CoverBrandingEnhancer::autoResolvePersonFromTitle` runs at every dispatch.

### Sync Stuck Variations (April 21, 2026)

Two parallel fixes for variations stuck `generating` in admin UI even though the underlying `ImageGenerationJob` resolved:

1. **Live sync paths** — `ProcessPendingImages::syncToContentIdea` ([line 158](backend/app/Console/Commands/ProcessPendingImages.php#L158)) + `ImageGenerationService::findIdeaIdForJobUuid` ([line 434](backend/app/Services/ImageGenerationService.php#L434)) status whitelists now include `'completed'`. Previously excluded `completed` ideas, so late webhook deliveries (operator-triggered retries after first variation auto-advanced the FSM) couldn't sync. Mirror-only update — never reverses FSM transition.
2. **Historical drift backfill** — new artisan command `php artisan content-engine:resync-stuck-variations [--dry-run] [--idea=N]` walks every idea in `(article_ready, generating_images, images_ready, completed)` status, compares each variation's UI status against the authoritative `image_generation_jobs` row, and overwrites mismatches. Cosmetic only — never re-dispatches jobs.

Same family fix lifted the `regenerateImagePrompts` whitelist to also accept `completed` ([ContentIdeaController:1758](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L1758)) — operators routinely need to fix wrong covers post-publish without manually reverting status.

### Face-Aware Visual Direction Rewrite (Gate 2)

When a face reference is uploaded in the Image Config modal, clicking "Apply & Generate"
auto-rewrites the segment's Visual Direction via a synchronous Claude Sonnet call before
dispatching to GeminiGen. This prevents demographic contradiction between VD text
(e.g. "young woman") and the actual reference image (e.g. bald older man).

- Trigger: `ImageGeneration.vue::handleConfigApply` — detects new/changed `face_refs`
- Backend: `ArticleGenerationService::rewriteVisualDirectionForFace` (sync SSH, ~10-20s)
- Endpoint: `POST /admin/content-engine/ideas/{id}/rewrite-vd`
- Persistence: overwrites `image_prompts[i].visual_direction`, preserves original as `visual_direction_original`
- Fallback: on rewrite failure, error toast + generate with original VD (no hard block)
- Chips: face/style ref thumbnails + notes icon visible on segment cards below Style/Model/Ratio pills

### Auto Creator-Face + Watermark + Branded Filenames (April 18, 2026)

Backend-owned brand policy applied automatically at every GeminiGen dispatch via [CoverBrandingEnhancer](backend/app/Services/CoverBrandingEnhancer.php) + [ImageGenerationService](backend/app/Services/ImageGenerationService.php). Plugin stays creative-focused — never prescribes logos, filenames, or brand overlays.

**Cover auto-inject (always)** — every cover image gets the creator's profile photo prepended to `face_refs` regardless of keyword match. Then sync VD rewrite fires (same pipeline as manual Gate 2 upload) so the VD describes the actual person. Idempotent via `visual_direction_original` sentinel.

**Inline auto-inject (conditional)** — inline images get creator face when `image_prompts[i].needs_creator_face === true` (plugin-authored flag from `/article-images`) OR when the expanded `HUMAN_KEYWORDS` list matches the VD/prompt text. Expanded list includes: `developer, engineer, designer, marketer, user, student, entrepreneur, coder, programmer, executive` (EN) + `pengembang, perancang, mahasiswa, wirausaha` (ID).

**Watermark prompt-injection (cover + inline)** — when `creator_brand` settings have `watermark_enabled='true'` AND `creator_brand_logo` resolves, `CoverBrandingEnhancer::appendWatermark()` appends a centered-watermark instruction string (reads `watermark_opacity` + `creator_brand_tagline` from DB) to `prompt_text` AND pushes the brand logo URL into `file_urls[]`. Applies to every image type for brand consistency. Graceful no-op + warning log when logo missing.

**Branded filenames** — every `ImageGenerationJob` dispatched from `triggerForIdea()` gets a `planned_filename` column set to `{creator_brand_slug}-{slug(research.keyword || title)}-{cover|body-N}.png`. The webhook's `downloadAndStore($remoteUrl, $job->planned_filename)` saves the file under that name. Collisions get `-v2`, `-v3` suffix. Frontend lightbox download uses the identical pattern via `ImageGeneration.vue::computeBrandedFilename`. Example: `alisadikinma-vibe-coding-tools-2026-cover.png`.

**Per-type captions (new field `image_prompts[i].caption`)** — authored by `/article-images` plugin. Cover = article title (exact or light SEO paraphrase). Inline = 5-12 words of supporting context (MUST NOT duplicate title or H2 heading). Backend `insertInlineImage()` sources figcaption from `caption` → `concept` → `insert_after_heading` fallback chain. Editable inline in Content Engine UI via caption input under Visual Direction (auto-saved).

**Tests:** 19 backend tests across 4 suites — `CreatorBrandSettingsTest` (5), `BrandedFilenameTest` (4), `CoverBrandingAutoInjectTest` (7), `WatermarkInjectionTest` (3). All green.

### meta_keywords Synthesis (April 21, 2026)

Backend-side fallback for `post_translations.meta_keywords` when plugin omits the field. Lives in [`ContentPublishService::buildSeoDefaults`](backend/app/Services/ContentPublishService.php) + two private helpers (`extractKeywordTerms`, `resolveBroadTopic`).

**Resolution order** (most specific → fallback):
1. `langData.meta_keywords` (plugin-authored per-language, ideal)
2. `article.meta_keywords` (plugin-authored top-level)
3. **Synthesized**: broad topic anchor + body-lede entity tokens (when target_keyword exists but plugin skipped meta_keywords)
4. Generic chain: `niche + pillar + tags` (fires only when target_keyword also missing — yields `"AI & Tech"` literal)

**Synthesis strategy follows web SEO best practice (Bing/Yandex/AI-crawlers era):** 5-7 SHORT entity tokens (1-3 words each), NEVER long-tail phrases. Long phrases hurt scannability and signal keyword stuffing.

- **Broad topic anchor** (first slot) — mapped from `idea.pillar`: `vibe_coding → "AI Coding"`, `ai_agents → "AI Agents"`, `ai_video_image → "AI Media"`, etc. Falls back to `idea.niche` parsed (drops "& Tech" suffix → "AI"). Gives the list one discoverability anchor users actually type.
- **Body-lede entity extraction** (`extractKeywordTerms`) — scans first 1200 chars of stripped article body (NOT title; titles use English/Indonesian title-case which makes every word match the proper-noun regex). Three passes:
  - Capitalized bigrams matching person-name pattern (`[Capital][Capital]` both not stopwords) → "Elon Musk", "Sam Altman", "Dario Amodei"
  - Brand-style mixed-case (`OpenAI`, `xAI`, `ChatGPT`, `iPhone`, `GitHub`)
  - ALL-CAPS acronyms 2-5 chars (`AI`, `IPO`, `SPAC`, `NASA`)
- **Stopword list**: ~80 tokens covering EN articles/connectors/verb-frames + ID equivalents + news-prose fillers (`Lawsuit`, `Trial`, `News`, `Update`, `Story`).

Example output for post #11 (Musk vs Altman lawsuit):
- Before: `"AI & Tech"` (literal column default)
- After: `"AI, Elon Musk, Sam Altman, Public Benefit, OpenAI, PBC"`

Backfill artisan-style script ran for 24 historical PostTranslation rows during this session — see git log `db48428d`, `851220ce`. Cap at 7 keywords total, idempotent + side-effect free.

### Pipeline State Machine (April 21, 2026)

Foundation for all Content Engine status movement. Before this, status writes were scattered `update(['status' => ...])` calls across 8+ files with no centralized validation — late webhooks routinely reversed FSM direction (`completed` → `generating_images` → `completed`) and orphaned ideas got stuck.

**Core pieces:**
- [`App\Enums\ContentIdeaStatus`](backend/app/Enums/ContentIdeaStatus.php) — 9 cases (`Draft`, `Researching`, `ArticleReady`, `AwaitingManualUpload`, `GeneratingImages`, `ImagesReady`, `Completed`, `Failed`, `Archived`) + `TRANSITIONS` adjacency map + `canTransitionTo()` check
- [`App\Traits\HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) — atomic `transitionTo(next, reason, extra)` method on `ContentIdea`. Throws `App\Exceptions\InvalidStateTransitionException` on illegal transitions. Mirrors status onto in-memory attributes so `$idea->status` reflects the new value without `refresh()`. Appends `{from, to, reason, timestamp}` to `pipeline_state_log` JSON column (rotating window, 20 entries).
- [`App\Services\PipelineGuard::advance`](backend/app/Services/PipelineGuard.php) — thin wrapper with uniform logging (`[PipelineGuard] idea #N reason: from → to`). Preferred entry point for any orchestrator code that wants a stack-traced log on failure.
- [`App\Services\AutoPipelineOrchestrator`](backend/app/Services/AutoPipelineOrchestrator.php) — tick-driven pipeline advancer invoked by `ProcessScheduledIdeas` / `ProcessPendingImages`. Calls `PipelineGuard::advance` for every forward move.

**Migration:** `2026_04_21_add_state_machine_fields_to_content_ideas.php` adds `pipeline_state_log` JSON + segment retry bookkeeping columns on `content_ideas`.

**Tests (6 suites, ~20 tests):** `ContentIdeaStatusTransitionsTest`, `FsmEnforcementRegressionTest`, `InvalidStateTransitionHandlerTest`, `PipelineGuardTest`, `AdvanceRuleSkippedAndCoverCriticalTest`, `SegmentRetryMachineTest`. All green.

### Segment Retry Pipeline (April 21, 2026)

Fixes stuck generation when one segment of a multi-segment article fails (e.g., GeminiGen quota hit for 1 of 4 body images).

**Per-segment status fields** — each `image_prompts[i]` row tracks `status` (`pending` / `generating` / `done` / `failed` / `skipped`), `retry_count`, `last_error`, `selected_variation`, `variations[]`. Aggregation up to idea-level `status` handled by [`ImageGenerationService::advanceIdea`](backend/app/Services/ImageGenerationService.php).

**Retry modes:**
- **Auto retry** — webhook failure on a segment triggers `RetryImageSegmentJob` dispatch (delayed 60s, exponential backoff, max 2 auto attempts per segment). See [`backend/app/Jobs/RetryImageSegmentJob.php`](backend/app/Jobs/RetryImageSegmentJob.php) + [`SegmentFailureAutoRetryTest`](backend/tests/Feature/SegmentFailureAutoRetryTest.php).
- **Manual retry** — `POST /admin/content-engine/ideas/{id}/retry-segment/{i}` resets the segment's `status=pending`, clears `last_error`, and redispatches via `ImageGenerationService::dispatchSegment()`. FSM-gated on `generating_images` / `images_ready`.
- **Skip segment** — `POST /admin/content-engine/ideas/{id}/skip-segment/{i}` marks segment `skipped`, removes the `<figure>` placeholder from article body at publish time. Used for irrecoverable failures (persistent content policy block, etc).
- **Replace variation** — new optional `replace_variation_index` param on `generate-segment-image` redispatches targeting a specific variation slot regardless of current status. Used by the amber refresh hover icon on done thumbnails.

**Telegram notifications** — `DispatchTelegramNotification` job fires on: `segment_failed` (after auto-retry exhaustion), `manifest_entity_needed` (awaiting_manual_upload), `auto_translate_exhausted`, `generation_complete`. Toggleable per-event via `telegram_notify_*` settings.

### Translate-Before-Publish Gate (April 21, 2026)

Blocks publish until a secondary-language translation exists on the primary Post. Before this, articles could ship monolingual and never get translated (the cron retry path sometimes lost them).

**Flow (gated by `ARTICLE_GEN_USE_TRANSLATE_PHASE=true`):**
1. Operator clicks "Publish" on Gate 2 → backend creates Post + primary `post_translations` row
2. `AutoPipelineOrchestrator::shouldBlockPublishForTranslate` checks `translation_pending=true`
3. If missing: sync SSH preflight to `/article-translate` (cache-locked `auto_pipeline:translate_preflight:{id}`, 30-60s)
4. On success: `post_translations.en` row written, `translation_pending=false`, publish proceeds
5. On failure: increments `translation_attempts_auto`, max 3 tries over 15 min (5 min per cron tick)
6. Exhaustion: dispatches Telegram `auto_translate_exhausted` alert + publishes monolingual (pipeline unblocks)

**Manual trigger** — `POST /admin/content-engine/ideas/{id}/translate-article` runs the sync translate preflight on demand (idempotent, FSM-safe).

**Dual-storage mirror (April 23, 2026):** Translations live in two sinks with **different content shapes** — `post_translations.{locale}.content` stores the rendered blog version (with `<figure>` image blocks baked in for public render), while `content_ideas.generated_article.{locale}.content` stores the raw authored body (no figures; Finalize re-injects images at render time from `image_prompts[]` positions). Before this fix, the automation path (plugin `/article-translate` → `PUT /automation/posts/{id}/save-translation` → [`ContentIdeaController::saveTranslation`](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L1358)) wrote ONLY to `post_translations`. Result: blog showed translated EN, but Finalize (which reads `generated_article.en.content`) showed "Belum diterjemahkan" for completed ideas and auto-re-triggered Claude on mount. `saveTranslation` now mirrors translated fields into `generated_article.{locale}` with two safety rails: (1) **never write the primary-language slot** — `generated_article.{primary}.content` is the authored source of truth and must not be clobbered by the rendered version; (2) **strip `<figure>` blocks** via [`App\Support\HtmlFigureStripper`](backend/app/Support/HtmlFigureStripper.php) before the write so Finalize doesn't double-render images. Idempotent + safe for orphan posts (no idea match → skip, endpoint still 200s).

**Historical backfill:** `php artisan content-engine:sync-translation-mirrors [--dry-run] [--idea=N]` — walks every idea with `result_post_id`, conservatively fills missing/duplicate non-primary-language entries from `post_translations`. Three guardrails: skips the primary-language slot (never overwrites authored content), skips entries that already contain legitimate non-duplicate content (written by admin `translateArticle` path), and strips `<figure>` blocks before writing. Cosmetic only — never re-translates, never hits Claude.

**Tests:** `AutoPipelineTranslateGateTest` (blocks/unblocks publish correctly), `TriggerTranslatePreflightTest` (manual endpoint + cache lock behavior), `SaveTranslationMirrorsToIdeaTest` (9 tests: mirror-write, orphan-safe noop, backfill live + dry-run, figure stripping on both paths, primary-slot protection, skips legitimate existing content).

### Plugin: article-content-writer (v2.7.2)
```
Location: D:\Projects\claude-plugin\article-content-writer\
Status:   Integrated with Portfolio backend (split pipeline mode)
Version:  2.7.2 (April 21, 2026)

Skills (9):
  article-gen       All-in-one 5-step pipeline (interactive + pipeline fallback)
  article-prep      Pipeline-only Steps 1-3 (Sonnet) — research, strategy, outline
  article-write     Pipeline-only Step 4 (Sonnet) — write, polish (image prompts deferred to Gate 2)
  article-score     Pipeline-only Step 5 (Sonnet) — 5 gates + combined 100-point
  article-images    Pipeline-only Gate 2 (Sonnet) — expand outline image_concept into 300-500w cinematic prompts
  article-translate Pipeline-only Finalize (Sonnet) — Indonesian → English translation of published Post
  article-brief     Brainstorm + outline planning
  article-validate  Score existing article against 5 gates
  article-seo       Standalone SEO + GEO analysis

Agent:
  article-writer    Self-contained subagent for batch production

Scoring (5 gates, combined 100-point):
  Quality Gate      10-point (min 7/10, weight x3 = 30 pts)
  Virality Score    5-point  (min 3/5,  weight x4 = 20 pts)
  SEO Score         6-point  (min 4/6,  weight x2.5 = 15 pts)
  AI Humanization   20-point deduction (weight x1 = 20 pts)
  GEO/AEO Score     5-point  (weight x3 = 15 pts)
  Combined minimum: 70/100 to publish

Hard Rules: 20 (incl. AI Humanization 107-word system, GEO formatting)

Compiled reference files (injected via --append-system-prompt-file):
  refs-prep.md      ~59 KB — global-config, frameworks, hooks, arcs, templates
  refs-write.md     ~49 KB — global-config (trimmed), style-guide, retention, SEO (no image bloat)
  refs-score.md     ~52 KB — virality, quality-gate, SEO, style-guide
  refs-images.md    ~38 KB — global-config (§11+§16 only), image-prompt-guide, cinematography-lut
  refs-translate.md  ~7 KB — translation-guidelines (HTML preservation, tone, SEO meta, bucket brigades)
```

### Article Generation Environment Variables
```env
# Core config
ARTICLE_GEN_DRIVER=ssh              # 'ssh' (production) or 'local' (development)
ARTICLE_GEN_SSH_HOST=localhost       # VPS (localhost for same-server SSH)
ARTICLE_GEN_SSH_USER=claudesn       # SSH user with Claude CLI auth
ARTICLE_GEN_SSH_KEY=/var/www/.ssh/id_ed25519  # SSH private key
ARTICLE_GEN_CLAUDE_PATH=claude      # Path to claude CLI binary on VPS
ARTICLE_GEN_API_URL=https://alisadikinma.com/api  # Callback URL for plugin
ARTICLE_GEN_API_TOKEN=your-token    # Bearer token (from admin Automation Tokens)

# Split pipeline — per-phase model + compiled reference files
ARTICLE_GEN_REFS_PREP=/home/claudesn/refs-prep.md
ARTICLE_GEN_REFS_WRITE=/home/claudesn/refs-write.md
ARTICLE_GEN_REFS_SCORE=/home/claudesn/refs-score.md
ARTICLE_GEN_REFS_IMAGES=/home/claudesn/refs-images.md
ARTICLE_GEN_REFS_TRANSLATE=/home/claudesn/refs-translate.md
ARTICLE_GEN_MODEL_PREP=sonnet       # Sonnet for research/strategy/outline
ARTICLE_GEN_MODEL_WRITE=sonnet      # Sonnet for writing (uniform across phases)
ARTICLE_GEN_MODEL_SCORE=sonnet      # Sonnet for scoring/evaluation
ARTICLE_GEN_MODEL_IMAGES=sonnet     # Sonnet for cinematic image prompt authoring
ARTICLE_GEN_MODEL_TRANSLATE=sonnet  # Sonnet for ID→EN translation
ARTICLE_GEN_MODEL_VD_REWRITE=sonnet # Sonnet for face-aware Visual Direction rewrite (sync, Gate 2)

# Feature flags (default false for safe rollout)
ARTICLE_GEN_USE_IMAGES_PHASE=false
ARTICLE_GEN_USE_TRANSLATE_PHASE=false
```

### LinkedIn Plugin Integration Environment Variables
```env
# OAuth (register at linkedin.com/developers)
LINKEDIN_OAUTH_CLIENT_ID=
LINKEDIN_OAUTH_CLIENT_SECRET=
LINKEDIN_OAUTH_REDIRECT_URI=https://alisadikinma.com/api/admin/linkedin/oauth/callback
LINKEDIN_OAUTH_SCOPES=w_member_social,r_liteprofile
LINKEDIN_API_BASE_URL=https://api.linkedin.com/v2
LINKEDIN_API_VERSION=202405

# Plugin generation bridge (SSH → Claude CLI on VPS — same pattern as ARTICLE_GEN_*)
LINKEDIN_GEN_DRIVER=ssh                                     # 'ssh' or 'local'
LINKEDIN_GEN_SSH_HOST=localhost
LINKEDIN_GEN_SSH_USER=claudesn
LINKEDIN_GEN_SSH_KEY=/var/www/.ssh/id_ed25519
LINKEDIN_GEN_CLAUDE_PATH=claude
LINKEDIN_GEN_MODEL=sonnet
LINKEDIN_GEN_TIMEOUT_SECONDS=300                            # Matches nginx fastcgi_read_timeout
# 4 compiled refs from plugin's compile-refs.ts — place on VPS at claudesn home
LINKEDIN_GEN_REFS_PLAYBOOK=/home/claudesn/refs-linkedin-playbook.md
LINKEDIN_GEN_REFS_TEMPLATES=/home/claudesn/refs-linkedin-templates.md
LINKEDIN_GEN_REFS_FORMATS=/home/claudesn/refs-linkedin-formats.md
LINKEDIN_GEN_REFS_CAROUSEL=/home/claudesn/refs-linkedin-carousel.md

# Operator defaults (overridden by settings table — linkedin_* keys)
LINKEDIN_AUTO_PUBLISH=false                                 # Master kill switch — OFF = stop at awaiting_publish
LINKEDIN_DEPTH_SCORE_THRESHOLD=80
LINKEDIN_CANCEL_WINDOW_MINUTES=15
LINKEDIN_FIRST_COMMENT_ENABLED=true
LINKEDIN_FIRST_COMMENT_DELAY_SECONDS=30
```

### Service Worker (Media Caching)
`frontend/public/sw.js` — Caches videos and images via Cache API. Pre-caches hero videos on install, cache-first strategy for all media.

---

**Last Updated:** April 27, 2026 — **LinkedIn carousel image rendering SHIPPED end-to-end:** closes the gap where carousel drafts persisted slide JSON (copy + plugin-authored image_prompt) but never actually rendered the slide PNGs. Three layers shipped together: (1) **Plugin layer** — new spec doc [`07-carousel-image-standards.md`](https://github.com/alisadikinma/linkedin-post-writer/blob/main/docs/rag/linkedin-playbook/07-carousel-image-standards.md) compiled into `refs-linkedin-carousel.md`, mirrors `ai-image-carousel-prompt-gen` standards (bilingual headline ID main `#FFFFFF` + EN subtitle `#F5A623`, accent keywords 2-4 in `#F5A623`, brand chrome on every slide, `SWIPE (GESER) >` indicator, page number top-left, brand icon + @alisadikinma watermark center thirty percent opacity, social block on CTA, visual hook on cover, WOW 8-element gate, hyperrealistic anti-AI-look, mobile dead zones, 5-paragraph prompt structure). `linkedin-carousel/SKILL.md` Step 4 rewritten to enforce these. (2) **Backend layer** — new [`CarouselSlideEnhancer`](backend/app/Services/CarouselSlideEnhancer.php) (placeholder replacement + face/logo URL resolution + chrome instruction append, idempotent), [`LinkedInCarouselImageService`](backend/app/Services/LinkedInCarouselImageService.php) (orchestrator: enhance → GeminiGen multipart → ImageGenerationJob row + slide status mirror), [`GenerateLinkedInCarouselImages`](backend/app/Jobs/GenerateLinkedInCarouselImages.php) queued job (auto-dispatched from `LinkedInGenerationService::persistAndRoute` for carousel format, regardless of AwaitingPublish/ManualReview), [`LinkedInCarouselImageWebhookController`](backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php) public callback at `POST /automation/linkedin/carousel-image-webhook`. New migration widens `image_generation_jobs.type` ENUM with `carousel_slide`, adds `linkedin_post_id` (FK cascade) + `slide_index` + `slide_image_role` columns + 2 indexes. Filename pattern `{brand_slug}-li-{draft_id}-slide-{NN}-{role}.png` (e.g., `alisadikinma-li-28-slide-01-cover.png`), stored at `storage/app/public/linkedin-carousel/`. (3) **Frontend layer** — [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) extends polling to fire every 5s when any slide has `image_status='generating'/'pending'`; new mutations `useRegenerateAllCarouselImages` + `useRegenerateSlideImage`. [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) main slide viewer gains 4-state visual (rendered image / animated spinner with copy preview / red error block + "Retry this slide" / pending placeholder), status pill overlay top-right, image generation summary line (✓ N · ↻ N generating · ✕ N failed / total), thumbnail strip shows actual rendered images + per-slide status dot, action panel gains "🖼 Regenerate all images" button for carousels. 3 new admin/automation routes: `POST /admin/linkedin-drafts/{id}/regenerate-images`, `POST /admin/linkedin-drafts/{id}/slides/{slideIndex}/regenerate-image`, `POST /automation/linkedin/carousel-image-webhook`. Carousel PDF composition + LinkedIn DocumentShare upload still deferred (`publishCarousel` still 503) — slide PNGs ready for manual download or downstream PDF assembly. **Earlier (April 23, session 3):** **LinkedIn plugin integration SHIPPED end-to-end:** plugin [linkedin-post-writer](https://github.com/alisadikinma/linkedin-post-writer) v0.2.0+ now wired to Portfolio backend. New [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) SSH-invokes `claude -p "/linkedin-gen"` with 4 compiled refs, parses stdout JSON (balanced-brace scanner, 8 unit tests), advances FSM PendingGeneration → Generating → Validating → AwaitingPublish|ManualReview based on plugin's `validation.passed`. New [`GenerateLinkedInPost`](backend/app/Jobs/GenerateLinkedInPost.php) queued job (2 retries, 360s timeout). [`LinkedInPublishService::publishText`](backend/app/Services/LinkedInPublishService.php) now real — `POST /v2/ugcPosts` with `ShareContent` payload, extracts URN from `X-RestLi-Id` header, auto-schedules delayed [`PostLinkedInFirstComment`](backend/app/Jobs/PostLinkedInFirstComment.php) (`POST /v2/socialActions/{urn}/comments`) to avoid 60% body-link reach penalty. New cron commands: [`linkedin:process-scheduled`](backend/app/Console/Commands/ProcessScheduledLinkedInPosts.php) (every-minute, publishes awaiting_publish past cancel_window — kill switch OFF → demote to manual_review, ON + success → published, failure → Failed + last_error) + [`linkedin:scan-blog`](backend/app/Console/Commands/ScanBlogForLinkedInConversion.php) (daily 03:00 WIB, creates pending rows for blog posts without live drafts + dispatches job). [`LinkedInDraftController::regenerate`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) now dispatches the job (was stub). [`config/linkedin.php`](backend/config/linkedin.php) extended with `generation` section (SSH config + 4 refs paths + 300s timeout). Carousel publish still deferred until TCPDF PDF composition ships (carousel drafts route to manual_review). Earlier session 2: **LinkedIn Admin UI shipped** (full-stack): new `linkedin_accounts` + `linkedin_posts` tables (24 cols, 8-state FSM), [`LinkedInPostStatus`](backend/app/Enums/LinkedInPostStatus.php) enum, [`LinkedInAccount`](backend/app/Models/LinkedInAccount.php) + [`LinkedInPost`](backend/app/Models/LinkedInPost.php) models, [`LinkedInOAuthService`](backend/app/Services/LinkedInOAuthService.php) (authorize URL + token exchange + refresh + /v2/me profile fetch) + [`LinkedInPublishService`](backend/app/Services/LinkedInPublishService.php) (stubs until plugin content-gen wired), [`LinkedInOAuthController`](backend/app/Http/Controllers/Api/LinkedInOAuthController.php) (5 endpoints) + [`Admin\LinkedInDraftController`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) (7 endpoints). **FSM infrastructure refactored to be enum-class-generic**: [`HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) + [`PipelineGuard`](backend/app/Services/PipelineGuard.php) now accept any BackedEnum via `statusEnumClass()` abstract method on models; ContentIdea tests still pass. New `linkedin` settings group (7 keys) + extends `telegram` group with 3 linkedin notify toggles. Frontend: [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) TanStack Query composable + 3 views ([`LinkedInPostsList`](frontend/src/views/admin/LinkedInPostsList.vue) success feed + [`LinkedInQueueList`](frontend/src/views/admin/LinkedInQueueList.vue) triage + [`LinkedInDraftDetail`](frontend/src/views/admin/LinkedInDraftDetail.vue) detail with edit mode + LinkedIn-style card mockup + carousel slide viewer + state timeline). Sidebar gains "LinkedIn" section. AboutSettings gets "LinkedIn Integration — Direct OAuth" card (3-state UX: OAuth not configured / installed-not-connected / connected). Plugin pivoted from MixPost to direct LinkedIn OAuth after verification that MixPost OSS supports only Facebook + Twitter + Mastodon (not LinkedIn). Plugin owns content generation only; admin UI owns OAuth, publish, schedule, FSM, cancel window. Full design: [docs/plans/2026-04-23-linkedin-admin-ui.md](docs/plans/2026-04-23-linkedin-admin-ui.md). Earlier same day (session 1): Translation dual-storage mirror fix: automation-path `saveTranslation` now mirrors translated fields into `content_ideas.generated_article.{locale}` so the admin Finalize tab matches what the blog renders, WITH two safety rails preventing data corruption — (1) never writes the primary-language slot (authored raw content is sacrosanct), (2) strips `<figure>` blocks via new `App\Support\HtmlFigureStripper` since `post_translations` stores rendered content while `generated_article` stores raw body (Finalize re-injects figures at render time, so mirroring without the strip would cause double-rendering). New artisan backfill `content-engine:sync-translation-mirrors` for historical ideas applies the same three guardrails: skip primary slot, skip already-legit content, strip figures. Earlier (April 21): Page Sections wiring fixes: `<LatestBlog>` mounted on Home (was never rendered despite component existing); `<CTASection>` moved from Blog list → BlogDetail (admin "Blog Page → Call to Action" controls article detail, not index); `LatestBlog` side cards now render `featured_image` thumbnails (40/60 horizontal split) matching the large card; `usePageSections` cache staleTime 10min → 30s + `refetchOnMount:'always'` so admin toggles surface on public pages within next navigation. Added "Page Sections Mapping" reference table in this CLAUDE.md to prevent ghost-toggle confusion. Earlier same day: Pipeline State Machine foundation (`ContentIdeaStatus` enum + `HasStatusTransitions` trait + `PipelineGuard`, strict adjacency map with audit log), Segment Retry Pipeline (auto retry job + manual retry/skip endpoints + replace-variation), Translate-Before-Publish Gate (sync SSH preflight, 3 auto retries, Telegram exhaustion alert), meta_keywords body-lede synthesis with broad-topic anchor (web SEO best practice — short entity tokens), backend auto-resolve person entity via Wikidata at every cover dispatch (no manual Regen Prompt needed), Replace-variation hover icon on done thumbnails, `completed` status added to syncToContentIdea + findIdeaIdForJobUuid + regenerateImagePrompts whitelists, `content-engine:resync-stuck-variations` artisan backfill, plugin v2.7.2 with hard pre-save SEO field audit + lede + role-resolution. Earlier (April 18): Creator Brand system — auto-inject cover face + VD rewrite, prompt-injection watermark, branded filenames `alisadikinma-{keyword}-{segment}.png`, per-type image captions, `creator_brand` Settings group + AboutSettings card.
**Maintainer:** Ali Sadikin (ali.sadikincom85@gmail.com)
**Environment:** Windows 11, D:\Projects\Portfolio_v2
**PHP:** D:\xampp\php\php.exe (8.2.12) — use full path, not in system PATH
**Backend:** Laravel 12 + PHP 8.2 + MySQL 8 + Sanctum 4 + Filament 4.1
**Frontend:** Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + TanStack Query 5.90 + Tailwind 4
**Production:** https://alisadikinma.com
