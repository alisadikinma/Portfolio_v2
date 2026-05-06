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
├── CarouselGenOutputAdapter.php # Maps `/carousel-gen` plugin JSON envelope → `linkedin_posts.carousel_slides` shape (Phase A6 router)
├── CarouselSlideEnhancer.php    # Brand chrome injector for LinkedIn carousel slides — placeholder replacement + face_refs/file_urls resolution + chrome instruction append
├── ContentEngineService.php     # Legacy HTTP client (kept for health check proxy)
├── ImageGenerationService.php   # GeminiGen image generation (article hero/inline path)
├── LinkedInCarouselImageService.php # GeminiGen dispatch for LinkedIn carousel slide PNGs + webhook handler (mirrors onto LinkedInPost.carousel_slides JSON)
├── LinkedInGenerationService.php # SSH-invokes `/linkedin-gen` CLI, parses stdout JSON, advances FSM, dispatches GenerateLinkedInCarouselImages on carousel format. Plugin v0.5.0+: orchestrator emits `status=route_to_carousel_gen` for carousel format (brief only); service ALWAYS dispatches `/carousel-gen` engine via `applyCarouselGenAdapter()` to assemble slides via `CarouselGenOutputAdapter`. **STRICT enforcement (May 2026)**: `/carousel-gen` is the ONLY carousel path — `applyCarouselGenAdapter` throws `CarouselGenAdapterException` when `format='carousel'` but status ≠ `route_to_carousel_gen` (legacy `complete`-with-inline-slides envelopes rejected, no fallback). `persistAndRoute` strict guard refuses to persist a carousel draft when slides[] is empty.
├── LinkedInOAuthService.php     # OAuth 2.0 authorize URL + token exchange + refresh + /v2/me profile fetch
├── LinkedInPublishService.php   # LinkedIn REST API v2 wrapper: publishText (IMAGE-category share — uploads blog featured_image to LinkedIn DigitalMedia via 3-step registerUpload + bytes PUT, persists thumbnail_asset_urn for idempotent retry, falls back to NONE on upload failure) + postFirstComment (wired); publishCarousel deferred until TCPDF ships
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
| `linkedin_virality_min_score` | `'60'` | **Scan-time gate**: only ingest blog posts whose `ContentIdea.virality_score >=` this. Manual posts (no idea linkage) skipped — operator can hand-create LinkedIn drafts for those. CLI flag `--min-virality=` overrides. |
| `linkedin_virality_purge_below` | `'50'` | **Daily purge gate**: soft-delete drafts whose source idea decayed below this. Only touches non-terminal states (skips published / cancelled / awaiting_publish). Idempotent. |

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
- **[`ScanBlogForLinkedInConversion`](backend/app/Console/Commands/ScanBlogForLinkedInConversion.php)** — daily 03:00 WIB (`linkedin:scan-blog --hours=24`). Finds published posts with no live `linkedin_posts` row, creates pending row + dispatches job. **Virality gate (April 29, 2026)**: only ingests posts whose `ContentIdea.virality_score >= linkedin_virality_min_score` (default 60). Manual posts (no idea linkage) skipped. CLI: `--dry-run`, `--limit=N` (default 20), `--min-virality=N` override, `--hours=N`.
- **[`PurgeLowViralityLinkedInDrafts`](backend/app/Console/Commands/PurgeLowViralityLinkedInDrafts.php)** — daily 04:00 WIB (`linkedin:purge-low-virality`). Soft-deletes drafts whose source idea decayed below `linkedin_virality_purge_below` (default 50). Only touches non-terminal states — published / cancelled / awaiting_publish are protected. Idempotent. CLI: `--threshold=N` override, `--dry-run`.
- **[`ReapStuckLinkedInCarouselImages`](backend/app/Console/Commands/ReapStuckLinkedInCarouselImages.php)** — every 5 min (`linkedin:reap-stuck-carousel-images`). One level deeper than `linkedin:reap-stuck` — handles per-slide `image_status='pending'` (>30m) or `'generating'` (>15m) by re-dispatching `GenerateLinkedInCarouselImages` (idempotent, skips done slides). Catches GeminiGen webhook drops + queue-worker crashes between persistAndRoute and the image job. CLI: `--pending-threshold=N`, `--generating-threshold=N`, `--dry-run`.
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

- **[`CarouselSlideEnhancer`](backend/app/Services/CarouselSlideEnhancer.php)** — mirror of `CoverBrandingEnhancer` for the LinkedIn carousel pipeline. Per slide: replaces placeholder tokens (`{{CREATOR_FACE}}`, `{{BRAND_LOGO}}`, `{{HANDLE}}`, `{{PORTFOLIO_URL}}`, `{{PAGE_INDICATOR}}`, `{{SWIPE_TEXT}}`), appends brand chrome instruction paragraph (idempotent — skipped when prompt already contains both page indicator and handle), pushes creator face URL into `face_refs` for `cover`/`human_fingerprint`/`cta` layout hints **PLUS any layout (body / direct_answer) whose `image_prompt` body contains the literal `creator` token (regex `\bcreator(?:'s)?\b`, case-insensitive)** — this catches foreshadow / loop-end / B-roll-with-humans slides that the carousel-gen plugin emits as `layout_hint='body'` (the Zod enum has no foreshadow layout) but whose prose names the creator. Without this prompt-content sniff, GeminiGen rendered generic faces on those slides because `face_refs` was empty. When face URL attaches via prompt-sniff, also prepends a "PRIMARY SUBJECT (mandatory)" mandate paragraph so GeminiGen treats the face reference as the hero rather than decoration. Pushes brand logo URL into `file_urls` for every slide. Reads creator face from `Setting{group=about,key=profile_photo}`, brand logo from `Setting{group=creator_brand,key=creator_brand_logo}`, handle from `linkedin.creator_handle` setting (fallback: `creator_brand_slug` prefixed with @, fallback: `@alisadikinma`). Plugin SKILL.md Hard Rule #19 (added May 6, 2026) makes the `creator` token mandatory on every slide depicting the creator — backend treats this as the contract that triggers face attachment regardless of layout_hint.
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

**Operational behavior (post-May 2 strict /carousel-gen):**
1. `/linkedin-gen` plugin produces brief — for `format='carousel'` it short-circuits with `status='route_to_carousel_gen'` (carousel/post/validation all null)
2. `LinkedInGenerationService::applyCarouselGenAdapter` SSH-dispatches `/carousel-gen` plugin (universal engine in `ai-image-carousel-prompt-gen` v2.16+) — produces 5-10 bilingual slides with full image_prompt + chrome placeholders
3. `CarouselGenOutputAdapter::adapt` maps plugin JSON → `linkedin_posts.carousel_slides` shape, throws `CarouselGenAdapterException` on `status=failed` / empty slides
4. Status promoted `route_to_carousel_gen → complete` after slides materialize → FSM Generating → Validating → AwaitingPublish/ManualReview
5. `persistAndRoute` strict guard: refuses to persist a carousel draft when slides[] is empty (defense vs caller bugs)
6. `persistAndRoute` dispatches `GenerateLinkedInCarouselImages` job (carousel format only)
7. Job loops slides → `CarouselSlideEnhancer.enhance()` (resolves chrome placeholders) → GeminiGen multipart POST per slide → status flipped to `generating`, UUID stored
8. GeminiGen callbacks hit `POST /automation/linkedin/carousel-image-webhook` → `LinkedInCarouselImageService::handleWebhook` downloads PNG → updates `image_generation_jobs` row → mirrors onto `linkedin_posts.carousel_slides[]` JSON
9. Frontend polls every 5s while any slide is mid-flight → status pills + thumbnails update live
10. Operator can retry single failed slides (hover "Retry this slide") or re-dispatch all slides ("Regenerate All Images" action button → calls `RegenerateLinkedInCarouselContent` job → re-runs `/carousel-gen` then re-renders all slides)

**Carousel failure routing** — there is NO fallback for carousel format:
- Legacy envelope (pre-v0.5.0, status=`complete`+inline slides) → `applyCarouselGenAdapter` throws → FSM Failed with "Legacy envelopes rejected" message
- `/carousel-gen` SSH timeout / parse failure / status=failed → `CarouselGenAdapterException` → FSM Failed with operator-actionable error in `last_error`
- Sonnet output truncation (output cap exceeded) → parser returns null → forensic dump at `storage/app/carousel-gen-debug/draft-{id}-{ts}.txt` → FSM Failed
- Per-slide GeminiGen safety refusal → `LinkedInCarouselImageService::applySafetyRewriteIfNeeded` strips proper nouns and re-dispatches; idempotent via `image_prompt_pre_safety` sentinel
- Empty slides[] post-adapter → `persistAndRoute` strict guard → markFailed

### LinkedIn Carousel Engine Decoupling (April 28, 2026 — **Phase B + C SHIPPED** — engine is the only carousel path)

Strategic decoupling so carousel image authoring lives in ONE plugin (`ai-image-carousel-prompt-gen` v2.16.0+) reusable across LinkedIn + IG + TikTok, while LinkedIn-specific concerns (PDF composition, link-in-comment, Depth Score, hashtag rules) stay in `linkedin-post-writer`. Architecture choice **Opsi D — universal carousel engine + publisher-side platform concerns** (see ADR `~/.claude/gaspol-knowledge/design-decisions/adr-2026-04-28-carousel-engine-publisher-separation.md`).

**Phase A SHIPPED (forward-compat, feature-flagged OFF):**

- **Plugin layer (`ai-image-carousel-prompt-gen` v2.16.0 commits `81c175f → a799686`)** — added `/carousel-gen` pipeline mode (auto-detected via `--blog-source`, `--pipeline`, `--non-interactive` flags or no-TTY detection), Zod schema [`schema.ts`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/skills/carousel-gen/schema.ts) with discriminated union (`CompleteEnvelopeSchema` vs `FailedEnvelopeSchema`), bilingual + 5-act narrative slide types, [`non-interactive-defaults.md`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/references/non-interactive-defaults.md) (deterministic resolution rules replacing every interactive question — wardrobe/setting/brand-upload/hook-category/source-URL/output-folder), [`scripts/compile-refs.ts`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/scripts/compile-refs.ts) bundling 7 reference files into `refs-carousel-gen-pipeline.md` (~169KB, gitignored — deployed separately to VPS).
- **Backend layer (Portfolio_v2 commits `491a1a01 → 90cd9b73`)** — new [`config/carousel-gen.php`](backend/config/carousel-gen.php) (mirrors `LINKEDIN_GEN_*` SSH config pattern: driver, ssh_host/user/key, claude_path, model, refs_pipeline, timeout_seconds), new [`CarouselGenOutputAdapter`](backend/app/Services/CarouselGenOutputAdapter.php) (single `adapt(array $carouselGenJson): array` method maps plugin JSON envelope → `linkedin_posts.carousel_slides` shape, throws `CarouselGenAdapterException` on `status=failed` or empty `slides[]`, defense-in-depth forces `direct_answer_block=null` on non-`direct_answer` layouts), [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) gains `applyCarouselGenAdapter()` + `dispatchCarouselGenEngine()` methods + constructor-injected adapter — when feature flag enabled AND format=carousel, runs `/carousel-gen` SSH dispatch BEFORE FSM advance, replaces `parsed.carousel.slides` with adapter output. Adapter exceptions → `markFailed($draft, ...)` route to FSM Failed.
- **Feature flag RETIRED in v0.5.0** — Originally [`config/linkedin.php`](backend/config/linkedin.php) had a `use_carousel_gen_engine` key (env: `LINKEDIN_USE_CAROUSEL_GEN_ENGINE`) that defaulted to false. v0.5.0 removed the flag entirely — `/carousel-gen` is now the only carousel path. The `LinkedInGenerationService::applyCarouselGenAdapter` method always dispatches for carousel format. Updated tests in [`LinkedInGenerationServiceCarouselGenRouterTest`](backend/tests/Feature/LinkedInGenerationServiceCarouselGenRouterTest.php) cover: text-format pass-through, route_to_carousel_gen → status promotion to complete, legacy-complete-envelope slide replacement (backward compat), `status=failed` exception, dispatch-null exception.
- **VPS deploy (A7 manual operator step)** — `git pull` plugin cache to `a799686`, `npm install + npm run compile-refs` produces 169,621-byte bundle, symlinked at `/home/claudesn/refs-carousel-gen-pipeline.md` (mirrors `refs-linkedin-carousel.md` symlink pattern). Laravel `.env` appended with 9 new vars (`CAROUSEL_GEN_*` + `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false`), `php artisan config:cache && queue:restart`. Backup at `.env.backup-2026-04-28-A7`.

**Acceptance verified on VPS** (all green):
- `claude -p "/carousel-gen --help"` documents pipeline-mode flags (`--blog-source`, `--pipeline`, `--bilingual`, `--narrative`, `--target-slides`)
- `config('carousel-gen.driver') = 'ssh'`, `timeout_seconds=900` (matches `LINKEDIN_GEN_TIMEOUT_SECONDS`)
- `config('carousel-gen.refs_pipeline') = '/home/claudesn/refs-carousel-gen-pipeline.md'` (file exists, 169,621 bytes)
- `config('linkedin.use_carousel_gen_engine') = false` — Phase A safety preserved, ZERO production impact

**Phase B + C SHIPPED (April 28, 2026 session 3 — combined):**
- ✅ Plugin: `linkedin-post-writer` v0.5.0 BREAKING — `/linkedin-carousel` skill deleted, `/linkedin-gen` orchestrator refactored to short-circuit on carousel format with `status=route_to_carousel_gen` envelope, `compile-refs.ts` reduced to 3 bundles (carousel bundle retired), 221 plugin tests pass
- ✅ Backend: feature flag `linkedin.use_carousel_gen_engine` and env `LINKEDIN_USE_CAROUSEL_GEN_ENGINE` removed; `LinkedInGenerationService::applyCarouselGenAdapter` always dispatches for carousel format; `route_to_carousel_gen` status detected and promoted to `complete` after the adapter assembles slides; `refs_carousel` config + `LINKEDIN_GEN_REFS_CAROUSEL` env retired (3 reference bundles instead of 4); 16 backend tests pass for the v0.5.0 wiring
- ✅ VPS deploy: plugin updated to v0.5.0, 3 reference bundles compiled, retired symlinks/env vars dropped

**Phase D SHIPPED (May 2, 2026 — strict /carousel-gen enforcement + parser fix, commit `6396aadc`):**

> **CAROUSEL CONTRACT (post-May 2):** Carousel image generation MUST go through `/carousel-gen` plugin. There is NO fallback path. Any carousel draft whose envelope doesn't conform routes straight to FSM Failed.

Two production-impacting bugs surfaced via [`storage/app/carousel-gen-debug/`](backend/storage/app/carousel-gen-debug/) dump forensics on draft 43 (Apr 29) + draft 13 (May 2):

1. **Parser bug** — [`LinkedInGenerationService::parseOrchestratorOutput`](backend/app/Services/LinkedInGenerationService.php) silently swallowed entire JSON when Sonnet emitted preamble narration + ```json fenced JSON. The regex `preg_replace('/\s*```.*$/s', '')` matched the LEFTMOST `\s*```` (the OPENING ```json fence) and `.*$` greedily consumed everything to EOF, leaving only preamble. `strpos('{')` then returned false → parser null → forensic dump written. **Fix**: drop the fence-strip regex entirely; the balanced-brace scanner already tolerates leading preamble (no `{` chars in narration) and trailing fences/narration (stops at matched depth=0 `}`).
2. **Legacy fallback removed** — `applyCarouselGenAdapter` now throws `CarouselGenAdapterException` when `format='carousel'` but `status !== 'route_to_carousel_gen'`. Pre-v0.5.0 envelopes with inline `status='complete'` + `format='carousel'` slides are explicitly rejected (test `test_rejects_legacy_complete_carousel_envelope` asserts `dispatchCarouselGenEngine` is NOT called). [`persistAndRoute`](backend/app/Services/LinkedInGenerationService.php) gains a strict guard: refuses to persist a carousel draft when slides[] is empty (defends against future caller bugs that bypass the adapter).

Tests: 22/22 LinkedIn tests pass. New regression tests `test_parses_sonnet_preamble_with_fenced_json` + `test_parses_pure_fenced_json_no_preamble` cover the production failure modes; legacy compat test replaced with rejection assertion.

**Open issue (NOT fixed by Phase D)** — Sonnet output truncation: draft 13 dump (May 2, 6KB) showed Sonnet emitted "Completing slide 8 and slide 9... Paste this after `\"it's the best`" continuation prose instead of valid JSON. Likely cause: 9 bilingual slides exceeding model output token cap during /carousel-gen pipeline mode. The new strict envelope handler correctly routes this to FSM Failed (not silent fallback). Mitigations to evaluate as separate work:
- Reduce default `target_slides` 9 → 7 in [`LinkedInGenerationService::inferTargetSlides`](backend/app/Services/LinkedInGenerationService.php) (cheapest)
- Plugin-level: tighten per-slide `image_prompt` length invariant in `/carousel-gen` skill from ~500-700 chars → ~350-450 chars
- Switch `CAROUSEL_GEN_MODEL` from sonnet → opus (4-5x cost, higher output cap)

**Full design + ADR + deploy guide:**
- Master plan: [docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md](docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md)
- A7 operator manual: [docs/plans/2026-04-28-A7-vps-deploy-guide.md](docs/plans/2026-04-28-A7-vps-deploy-guide.md)
- ADR: `~/.claude/gaspol-knowledge/design-decisions/adr-2026-04-28-carousel-engine-publisher-separation.md`

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

### CV Export Routes (Sanctum bearer + ability:cv:read + throttle:30,1)
```
GET    /api/cv/export                  # JSON Resume schema (basics + 56 projects + 5 awards + top 5 thought_leadership)
GET    /api/cv/master.md               # LLM-optimized markdown rendering of the same data
GET    /api/cv/master.md?compact=1     # Compact mode — drops Problem/Outcome lines (~5k tokens vs ~10k default)
```

The two endpoints share data sources but serve different consumer shapes:
- `/cv/export` — JSON Resume v1.0.0 envelope for tools that parse JSON (jobhunter scoring engines, ATS systems).
- `/cv/master.md` — single dense markdown blob for direct LLM prompt embedding (jobhunter `cv-tailor`, `job-score` skills). English-only with silent ID translation fallback. ETag-revalidated via existing `App\Http\Middleware\ApiETag` (304 on `If-None-Match` round-trip — ~80 byte revalidation cost vs ~10k full body).

Token mint: `User::find(1)->createToken('jobhunter-cv-export', ['cv:read'])->plainTextToken`. Same token works for both endpoints.

Implementation files:
- [`CvExportController`](backend/app/Http/Controllers/Api/CvExportController.php) — both `export()` (JSON) and `master()` (markdown) actions.
- [`CvMasterMarkdownService`](backend/app/Services/CvMasterMarkdownService.php) — markdown rendering, reuses `CvProjectResource::relevance_hint` heuristic so both exports surface identical industry tags.
- [`config/cv.php`](backend/config/cv.php) — hand-curated `skill_domains` array (5 domains: ai_automation / vibe_coding / ai_agents / manufacturing / enterprise) joined at render time with live project counts.
- [`resources/views/cv/master.blade.php`](backend/resources/views/cv/master.blade.php) — Blade template assembling the dense markdown.

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
POST   /api/admin/linkedin-drafts/{id}/regenerate-images                          # Re-dispatch /carousel-gen + every slide (~5-7 min, FE label "Regenerate All Images")
POST   /api/admin/linkedin-drafts/{id}/regenerate-caption                         # Re-synth caption + hashtags from current slides (~1s, sync, carousel-only — Apr 29)
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

## VPS Background Process Setup (Required for Pipelines)

**`deploy.sh` only signals workers — it doesn't START them.** Step 8 runs `php artisan queue:restart`, which is a graceful-reload SIGNAL to running workers. If no worker is running (fresh VPS, after reboot, after process crash), every queued job sits in `jobs` MySQL table forever — `LinkedInGenerationService`, `GenerateLinkedInCarouselImages`, `RegenerateLinkedInCarouselContent`, the entire Content Engine pipeline silently stalls.

Same for `routes/console.php` schedules — Laravel doesn't fire them internally. The host crontab must run `php artisan schedule:run` once per minute for `linkedin:process-scheduled`, `linkedin:reap-stuck-carousel-images`, etc. to ever execute.

**One-time install per VPS** (manual operator step, NOT in deploy.sh):

1. **Queue worker** — see [scripts/systemd/portfolio-queue.service](scripts/systemd/portfolio-queue.service):
   ```bash
   sudo cp /var/www/Portfolio_v2/scripts/systemd/portfolio-queue.service /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable --now portfolio-queue.service
   sudo systemctl status portfolio-queue.service
   ```
   `Restart=always` + `--max-time=3600` keeps it alive forever, recycling hourly to release memory. After every deploy, `queue:restart` signals graceful reload — systemd brings it back with new code.

2. **Scheduler** — see [scripts/systemd/portfolio-scheduler.crontab](scripts/systemd/portfolio-scheduler.crontab):
   ```bash
   crontab -u claudesn -e
   # Add the single line:
   * * * * * cd /var/www/Portfolio_v2/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
   ```

**Verify any time:**
```bash
sudo systemctl is-active portfolio-queue.service     # → active
crontab -u claudesn -l | grep schedule:run           # → returns the line
mysql -u ali -p portfolio_v2 -e "SELECT COUNT(*) FROM jobs;"  # not growing unbounded
```

Full ops doc + troubleshooting at [scripts/systemd/README.md](scripts/systemd/README.md). Symptom of missing setup: 20+ "in-progress" rows piling up in admin UI with `updated_at` timestamps far in the past.

**ALSO check there's no rogue cron-fired `queue:work` running as `www-data`:**
```bash
sudo crontab -u www-data -l 2>/dev/null   # → "no crontab for www-data" expected
ps -u www-data -o pid,etime,cmd | grep "queue:work" | grep -v grep   # → empty expected
```

Production incident **April 29, 2026 (session 8)**: a forgotten www-data crontab from the original VPS bootstrap was firing `php artisan queue:work --stop-when-empty --max-time=50 --tries=2` every minute alongside the new claudesn systemd worker. The www-data worker would race with claudesn picking up `RegenerateLinkedInCarouselContent` and `GenerateLinkedInPost` jobs in the WRONG user context — www-data cannot read `/home/claudesn/.ssh/id_ed25519` (mode 600), so /carousel-gen + /linkedin-gen invocations failed `SSH prompt write failed: Warning: Identity file ... not accessible: Permission denied`. Plus `--max-time=50` killed the worker mid-job (carousel-gen needs 3-7 min), causing `MaxAttemptsExceededException` on the next pickup attempt. Plus `--stop-when-empty` meant 60 ephemeral workers per hour vs one persistent systemd unit — wasteful and harder to reason about.

If you find this crontab, remove it: `sudo crontab -u www-data -r`. The systemd unit + claudesn schedule:run cover both responsibilities.

### Empty MCP Config (Required for Pipeline Runs)

Every `claude -p "..."` invocation from `LinkedInGenerationService` and `ArticleGenerationService` is a **fresh, one-shot CLI process** — there's no shared session between calls. Each invocation boots claude from scratch, including loading **all MCP servers** from the user's `~/.claude.json`.

**Why this matters:** `obsidian-mcp` (and likely other MCP servers) leak their child node process when the parent claude exits. Production incident **April 29, 2026**: 140 leaked obsidian-mcp processes consuming 8.7GB RSS over 4 days → carousel-gen hung past 880s SSH timeout, queue piled up to 26 stuck "in-progress" rows.

**Fix:** every pipeline `claude` call now passes `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config`. Empty config = `{"mcpServers": {}}` = zero servers loaded. Pipeline runs don't need MCP — all context flows through `--append-system-prompt-file` (compiled refs) and the prompt itself.

**One-time VPS setup:**
```bash
echo '{"mcpServers": {}}' > /home/claudesn/empty-mcp.json
chown claudesn:claudesn /home/claudesn/empty-mcp.json
```

Without this file, the `--mcp-config` flag fails silently and claude exits early. File MUST exist before the deploy lands.

**Override via env** (if you ever need MCP servers for debugging a specific pipeline):
```env
LINKEDIN_GEN_EMPTY_MCP_CONFIG=          # empty string disables the override (uses default ~/.claude.json)
ARTICLE_GEN_EMPTY_MCP_CONFIG=           # same
```

**Cleanup script** (run if leak recurs):
```bash
pkill -9 -u claudesn -f "node.*obsidian-mcp"
free -h  # confirm memory freed
```

### SSH Key Path: Two Contexts (HTTP vs Queue Worker)

`*_GEN_SSH_KEY` env vars must point to a path readable by **whichever user actually runs the SSH call**, and that user differs by service:

| Service | Dispatched from | Effective UID | Key path |
|---|---|---|---|
| `ARTICLE_GEN_*` | HTTP (admin clicks → controller → sync SSH inside the request) | `www-data` (PHP-FPM) | `/var/www/.ssh/id_ed25519` (owner: www-data, mode 600) |
| `LINKEDIN_GEN_*` | Queue (admin click → `GenerateLinkedInPost::dispatch` → worker) | `claudesn` (queue worker) | `/home/claudesn/.ssh/id_ed25519` (owner: claudesn, mode 600) |
| `CAROUSEL_GEN_*` | Queue (regenerate-images → `RegenerateLinkedInCarouselContent` → worker) | `claudesn` (queue worker) | `/home/claudesn/.ssh/id_ed25519` |

**Why this matters:** mode-600 keys grant ONLY-owner read. claudesn is in the www-data group but mode 600 gives the group zero read access, so the queue worker (claudesn) cannot use a www-data-owned key file even though it's group-readable in theory. Production incident **April 29, 2026 (session 7)**: regenerate-images on draft 19 returned `Re-author failed: /carousel-gen returned no usable output. See logs.` with `SSH prompt write failed: Warning: Identity file /var/www/.ssh/id_ed25519 not accessible: Permission denied` in `laravel.log`.

**One-time VPS setup** (after first install of queue worker):
```bash
sudo cp /var/www/.ssh/id_ed25519 /home/claudesn/.ssh/id_ed25519
sudo chown claudesn:claudesn /home/claudesn/.ssh/id_ed25519
sudo chmod 600 /home/claudesn/.ssh/id_ed25519
# Verify:
sudo -u claudesn ssh -i /home/claudesn/.ssh/id_ed25519 -o BatchMode=yes claudesn@localhost 'whoami'
# → claudesn
```

The same private key works in both locations because the corresponding pubkey already lives in `/home/claudesn/.ssh/authorized_keys` (that's how `www-data → claudesn@localhost` SSH worked all along).

**Then in `.env`:**
```env
ARTICLE_GEN_SSH_KEY=/var/www/.ssh/id_ed25519           # HTTP context
LINKEDIN_GEN_SSH_KEY=/home/claudesn/.ssh/id_ed25519    # Queue worker context
CAROUSEL_GEN_SSH_KEY=/home/claudesn/.ssh/id_ed25519    # Queue worker context
```

After updating `.env`, run `php artisan config:cache && systemctl restart portfolio-queue.service` so the worker picks up the new path.

**Future improvement (not yet shipped):** detect process UID at runtime and switch from `ssh` driver to `local` driver when the worker user matches the SSH target — eliminates the SSH round-trip entirely for queue-dispatched calls (claudesn → claudesn@localhost is wasted hop).

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
| `homepage` | `what-i-solve` | Home.vue | `WhatISolveTabs` (Phase 3 expanded May 5 — 4-discipline tabbed switcher with autoplay loop muted video per tab; absorbed Vibe Coding + AI Automation + AI Agents + AI Video Gen content from the 4 retired SkillShowcase sections) |
| ~~`skill-vibe-coding`~~ | ~~Home.vue~~ | ~~`SkillShowcase`~~ | RETIRED May 5 — content merged into `what-i-solve` |
| ~~`skill-ai-automation`~~ | ~~Home.vue~~ | ~~`SkillShowcase`~~ | RETIRED May 5 — content merged into `what-i-solve` |
| ~~`skill-ai-agents`~~ | ~~Home.vue~~ | ~~`SkillShowcase`~~ | RETIRED May 5 — content merged into `what-i-solve` |
| ~~`skill-ai-video`~~ | ~~Home.vue~~ | ~~`SkillShowcase`~~ | RETIRED May 5 — content merged into `what-i-solve` |
| `homepage` | `featured-projects` | Home.vue | `ProjectsBento` |
| `homepage` | `latest-blog` | Home.vue | `LatestBlog` (1 hero + 3 stacked secondary; per_page=4) |
| `homepage` | `testimonials` | Home.vue | `TestimonialsCarousel` (LinkedIn-sourced quotes, 8s auto-rotate, pause-on-hover, dots nav, keyboard ←/→) |
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
- **Gallery bulk-upload silently 422 / "Uploading…" stuck** → check **PHP-FPM `upload_max_filesize`** on VPS (NOT CLI). PHP silently truncates files >limit, validator then sees empty/corrupt `images[]` and returns 422 with no specific message. Production VPS uses `php8.2-fpm` with override at [/etc/php/8.2/fpm/conf.d/99-portfolio.ini](scripts/systemd/) (50M per file / 200M post / 120s execution / 256M memory). Laravel validator capped at 30MB per file (gallery + items + bulk endpoints), frontend hint says "Max 30MB". **Don't trust `php -i` from CLI** — CLI defaults differ from FPM (e.g., CLI showed 2M while FPM was 10M before fix). Always check `/etc/php/8.2/fpm/php.ini` + conf.d overrides.

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

**Safety-aware prompt rewrite (April 28, 2026):** When GeminiGen returns a policy refusal (e.g., `PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD` for prompts naming public figures, minors, brands, or unsafe content), the same prompt would fail deterministically on every retry — segments used to grind through 3 attempts then sit stuck at terminal failure with operator looking at "Failed to generate / Retry" with no recovery path. [`ImageGenerationService::handleSegmentFailure`](backend/app/Services/ImageGenerationService.php) now detects the refusal class via [`isSafetyError()`](backend/app/Services/ImageGenerationService.php) (substring match against known codes + free-text patterns: `prominent people`, `minors`, `unsafe content`, `sexual content`, `safety filter`, `content policy`, `do not allow uploading images`) and synchronously calls [`ArticleGenerationService::rewriteVisualDirectionForSafety`](backend/app/Services/ArticleGenerationService.php) — text-only Sonnet call (~10-15s, no image input) that strips proper nouns (persons/brands/landmarks) and replaces them with generic descriptors that preserve scene/lighting/mood. Mutates `image_prompts[i].visual_direction` + `prompt_text` + `prompt`, drops `face_refs` + `entity_refs` (the most common GeminiGen trigger is a public-figure entity_ref), preserves the original under `visual_direction_pre_safety` for audit. Failure history entry tags `safety_detected: true` + `rewritten_for_safety: true`. The existing `RetryImageSegmentJob` then picks up the sanitized prompt automatically — no changes to `retrySegment()`. Gated by `ARTICLE_GEN_USE_SAFETY_REWRITE` (default `true`). Falls back gracefully — if the rewriter fails, retry still dispatches with the original prompt (next attempt re-detects and retries the rewrite). Tests: `SegmentFailureSafetyRewriteTest` (5 cases — detector positive/negative, rewrite-and-dispatch, flag-off skip, transient-failure skip, rewriter-failure graceful fallback).

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

# Safety-aware retry rewrite — defaults TRUE (this is a fix, not an experiment).
# Set to false only to disable the auto-rewrite when GeminiGen returns
# PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD or similar safety refusals.
ARTICLE_GEN_USE_SAFETY_REWRITE=true
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
# RETIRED in linkedin-post-writer v0.5.0:
#   LINKEDIN_GEN_REFS_CAROUSEL  — carousel design specs moved to /carousel-gen plugin

# Operator defaults (overridden by settings table — linkedin_* keys)
LINKEDIN_AUTO_PUBLISH=false                                 # Master kill switch — OFF = stop at awaiting_publish
LINKEDIN_DEPTH_SCORE_THRESHOLD=80
LINKEDIN_CANCEL_WINDOW_MINUTES=15
LINKEDIN_FIRST_COMMENT_ENABLED=true
LINKEDIN_FIRST_COMMENT_DELAY_SECONDS=30

# Carousel Gen Engine — only carousel path post linkedin-post-writer v0.5.0.
# /linkedin-gen orchestrator emits status=route_to_carousel_gen for carousel
# format; backend ALWAYS dispatches /carousel-gen and assembles slides via
# CarouselGenOutputAdapter. (Feature flag LINKEDIN_USE_CAROUSEL_GEN_ENGINE
# was retired in v0.5.0 — no flag-gating now.)
CAROUSEL_GEN_DRIVER=ssh                                     # 'ssh' (production) or 'local' (XAMPP dev)
CAROUSEL_GEN_SSH_HOST=localhost
CAROUSEL_GEN_SSH_USER=claudesn
CAROUSEL_GEN_SSH_KEY=/var/www/.ssh/id_ed25519
CAROUSEL_GEN_CLAUDE_PATH=claude
CAROUSEL_GEN_MODEL=sonnet
CAROUSEL_GEN_REFS_PIPELINE=/home/claudesn/refs-carousel-gen-pipeline.md  # ~169KB compiled bundle
CAROUSEL_GEN_TIMEOUT_SECONDS=900                            # Matches LINKEDIN_GEN_TIMEOUT_SECONDS
```

### Service Worker (Media Caching)
`frontend/public/sw.js` — Caches videos and images via Cache API. Pre-caches hero videos on install, cache-first strategy for all media.

## Newsletter System (May 5, 2026)

End-to-end weekly digest pipeline: lead capture (name + email + WhatsApp E.164) on 4 frontend touchpoints, branded HTML email via SMTP, admin panel for CRUD + send-history + send-now + preview, token-based public unsubscribe, Friday 09:00 WIB cron, Hostinger SMTP managed via admin UI (no `.env` edits).

**Schema (2 migrations):**
- `2026_05_05_000001_add_lead_fields_to_newsletters` — adds `name`, `whatsapp_number` (UNIQUE), `unsubscribe_token` (CHAR(32) UNIQUE), `consent_given_at`, `source` to existing `newsletters` table. All nullable for safe backfill of legacy email-only rows. Existing `is_subscribed`/`subscribed_at`/`unsubscribed_at` columns kept as dead weight (backwards compat — never queried; hard-delete on unsubscribe per GDPR right-to-erasure).
- `2026_05_05_000002_create_newsletter_sends_table` — audit log: `sent_at`, `subscriber_count`, `posts_count`, `post_ids JSON`, `status ENUM(sent|failed|skipped|partial)`, `error_message`, `triggered_by ENUM(cron|manual|test)`, `created_by_user_id` FK users nullOnDelete, `test_recipient`, `duration_seconds`.

**`settings` group: `mail`** — 8 keys seeded by [`MailSettingsSeeder`](backend/database/seeders/MailSettingsSeeder.php), `firstOrCreate` so admin UI edits never get clobbered:

| key | default | encrypted? | purpose |
|---|---|---|---|
| `mail_mailer` | `'smtp'` | no | Driver (smtp/log/sendmail/array) |
| `mail_host` | `'smtp.hostinger.com'` | no | SMTP server hostname |
| `mail_port` | `'465'` | no | SMTP port (465 SSL / 587 TLS / 25 plain) |
| `mail_username` | `'aiagent@alisadikinma.com'` | no | Mailbox address for SMTP auth |
| `mail_password` | null | YES (Crypt::encryptString) | Mailbox password — set via UI, encrypted at rest, never returned in API (masked as `***SET***` + `mail_password_configured: true` flag) |
| `mail_encryption` | `'ssl'` | no | ssl / tls / none (none → empty string per Symfony Mailer convention) |
| `mail_from_address` | `'aiagent@alisadikinma.com'` | no | Default From address |
| `mail_from_name` | `'Ali Sadikin'` | no | Default From name |

[`App\Providers\MailConfigOverrideProvider`](backend/app/Providers/MailConfigOverrideProvider.php) reads these at boot and overrides `config('mail.*')` so Laravel `.env` mail keys are no longer authoritative. Silent failure on DB-unavailable / decrypt-fail (Laravel `.env` defaults stay in effect — artisan still boots on fresh schemas). Registered in [`bootstrap/providers.php`](backend/bootstrap/providers.php).

Admin UI: dedicated "Email — SMTP Settings" card on [AboutSettings.vue](frontend/src/views/admin/AboutSettings.vue) (between Telegram and LinkedIn cards). Form fields: host, port, username, password (preserves on empty submit — `***SET***` placeholder when configured), encryption dropdown, from address, from name. "📤 Send test email to me" button (disabled until password configured) — sends synchronously and re-applies config from DB before send so admin doesn't have to wait for `queue:restart` to verify.

API routes (all `auth:sanctum`):
```
GET    /api/admin/settings/mail
PUT    /api/admin/settings/mail
POST   /api/admin/settings/mail/test
```

**Public + admin endpoints (12 total):**

```
# Public (existing widened + 1 new)
POST   /api/newsletter/subscribe                 (throttle:5,60 — now requires name + WhatsApp E.164 strict /^\+[1-9]\d{6,14}$/)
DELETE /api/newsletter/unsubscribe               (legacy by-email path, kept for backwards compat)
POST   /api/newsletter/unsubscribe-by-token      (NEW — public, no auth, token from email link)

# Admin (auth:sanctum, all NEW)
GET    /api/admin/newsletter                     (list + search + source filter + paginate)
DELETE /api/admin/newsletter/{id}                (hard delete subscriber)
GET    /api/admin/newsletter/export              (streamed CSV: name, email, whatsapp, source, subscribed_at)
GET    /api/admin/newsletter/digest-preview      (rendered HTML of next Friday's digest with throwaway subscriber)
POST   /api/admin/newsletter/send-test           (sync send to {recipient} or auth user — Mail::send, not queued)
POST   /api/admin/newsletter/send-now            (Artisan::queue dispatches newsletter:send-weekly with --triggered-by=manual)
GET    /api/admin/newsletter/sends                (paginated audit log + status filter)
GET    /api/admin/newsletter/sends/{id}          (single send detail with post titles resolved from post_ids[])
```

**Mailable + email template:**
- [`App\Mail\WeeklyDigest`](backend/app/Mail/WeeklyDigest.php) — Queueable, accepts `Collection<Post> $posts + Newsletter $subscriber`. Subject: `"Friday Digest · {N} reads from this week"`. From/Reply-To default to `aiagent@alisadikinma.com` (overridable via mail.from.* + mail.reply_to.* settings).
- [`weekly-digest.blade.php`](backend/resources/views/emails/weekly-digest.blade.php) — Dark Cinema HTML, **600px max-width, table-based layout, ALL CSS INLINE** (Resend/Hostinger/Outlook-safe). Header (brand mark + "FRIDAY DIGEST" mono eyebrow), greeting (`Hi {{ $subscriber->name ?? 'there' }}`), 3-5 post cards (featured_image + category eyebrow gold + title 22px white bold + 180-char excerpt + gold CTA `Read this essay →` linking to `/blog/{slug}?utm_source=newsletter&utm_medium=email&utm_campaign=weekly-{Y-W}`), personal touch ("Reply to this email — I read every one."), footer with token-based unsubscribe link.
- [`weekly-digest-text.blade.php`](backend/resources/views/emails/weekly-digest-text.blade.php) — plain text fallback.

**Cron command:**
- [`SendWeeklyNewsletter`](backend/app/Console/Commands/SendWeeklyNewsletter.php) signature: `newsletter:send-weekly {--dry-run} {--force} {--limit=} {--triggered-by=cron} {--user-id=}`. Flow: query `Post::published()->whereBetween('published_at', [now()->subWeek(), now()])->limit(5)` → if empty AND NOT --force, insert `status=skipped` audit row + exit 0 (no spam) → if --dry-run, print rendered HTML and exit without DB write → else `Newsletter::chunkById(100)` and `Mail::to($sub)->queue(new WeeklyDigest($posts, $sub))` → insert `status=sent` audit row with stats. Exception path inserts `status=failed`.
- Schedule entry in [`routes/console.php`](backend/routes/console.php): `Schedule::command('newsletter:send-weekly')->fridays()->at('09:00')->timezone('Asia/Jakarta')->withoutOverlapping(60)`. Reuses existing `portfolio-queue.service` systemd worker + `portfolio-scheduler` host crontab — no new infra.

**One-shot command:** [`BackfillNewsletterTokens`](backend/app/Console/Commands/BackfillNewsletterTokens.php) — `newsletter:backfill-tokens [--dry-run]`. Idempotent. Generates `unsubscribe_token` for any pre-migration newsletter row where token IS NULL.

**Frontend touchpoints (4 forms, all 3 fields now):**
1. `Blog.vue` lines 307-371 — inline section, source `'blog_inline'`
2. `NewsletterInlineCard.vue` ("Enjoying this?"), source `'inline_card'`
3. `NewsletterFloatingBanner.vue` ("Before you go —"), source `'floating_banner'`
4. `NewsletterFooterBar.vue` ("Liked what you read?") — **modal-on-click pattern**: bar shows brand line + Subscribe button; click opens [`NewsletterModal.vue`](frontend/src/components/blog/NewsletterModal.vue) with full 3-field form (Teleport-to-body, ESC + backdrop close, auto-focus first input). Compact 1-input bar can't fit 3 fields gracefully — modal pattern keeps the high-intent footer touchpoint without UX compromise.

`useNewsletter()` composable widened — `subscribe(payload)` accepts `{name, email, whatsappNumber, source}` object. Backwards-compat shim: bare string still treated as email + `console.warn`. New `unsubscribeByToken(token)` method for the public unsubscribe page. Snake_case body conversion (`whatsappNumber` → `whatsapp_number`) handled inside composable.

WhatsApp client-side validation: `<input type="tel">` with `pattern="^\+[1-9]\d{6,14}$"` + `@blur="validateWa"` JS regex check + red-border feedback + inline help text "Format internasional, mulai dengan +".

**Public unsubscribe page** — [`/newsletter/unsubscribe?token=X`](frontend/src/views/NewsletterUnsubscribe.vue). Token-based, no email re-typing. Three states: idle (Confirm button), success (+ Resubscribe link), invalid_token / error (polite error). Calls `clearNewsletterState()` on success so subscribe forms reappear on this device.

**Admin view** — [`/admin/newsletter`](frontend/src/views/admin/NewsletterSubscribers.vue) with TanStack Query composable [`useNewsletterAdmin.js`](frontend/src/composables/useNewsletterAdmin.js) (mirrors `useLinkedInDrafts.js` 30s staleTime + refetchOnMount:'always' pattern). Two tabs: **Subscribers** (search + source filter + paginated table + delete + Compose Digest panel with Preview/Send-test/Send-now actions) + **Send History** (paginated audit table with status chips + status filter). Compose Digest panel: "Preview" opens iframe modal with rendered HTML email; "Send test" defaults to admin's own email; "Send NOW to all N subscribers" opens confirm modal with explicit checkbox confirm before queueing.

Sidebar nav entry "Newsletter" between LinkedIn (Queue) and Contact in [AdminLayout.vue](frontend/src/layouts/AdminLayout.vue).

**Anti-patterns enforced (per design doc):**
- ❌ NO open/click tracking via Resend/SMTP webhook (out of scope v1)
- ❌ NO welcome email on subscribe (only weekly digest in v1)
- ❌ NO multi-language email (English only — add per-locale variants if subscriber base spans languages)
- ❌ NO `is_subscribed=false` soft-pause (legacy columns dead weight; hard-delete on unsubscribe per GDPR)
- ❌ NO synchronous send loop (always queue; systemd worker handles retries via `--tries=3 --backoff=60,300,900`)
- ❌ NO storing WhatsApp without UNIQUE constraint (defense vs phone spam)

**Operator runbook:** [docs/runbooks/newsletter-deploy.md](docs/runbooks/newsletter-deploy.md) — covers SMTP config via admin panel (NOT `.env`), Hostinger sending limits (~100/hour shared plan), first-Friday cron observation, rollback path.

**Design + plan:**
- [docs/plans/2026-05-05-newsletter-system.md](docs/plans/2026-05-05-newsletter-system.md) — design doc with locked decisions (3 fields required upfront, E.164 strict, Friday 09:00 skip-if-empty, hard-delete unsubscribe)
- [docs/plans/2026-05-05-newsletter-system-plan.md](docs/plans/2026-05-05-newsletter-system-plan.md) — 17-phase implementation plan with TDD steps + verification criteria

---

**Last Updated:** May 6, 2026 — **API admin polish (B2 401-JSON + C playground + D usage badges) + plugin v0.6.0 EN directive.** Closes 4 follow-ups from the `/admin/automation/tokens` ship earlier today: (1) **B2 — `api/*` routes now return 401 JSON** instead of 302 redirect to `/login` HTML page when Sanctum auth fails. New `AuthenticationException` render handler in [`bootstrap/app.php`](backend/bootstrap/app.php) checks `$request->expectsJson() || $request->is('api/*')` and emits `{success:false, error:{code:'UNAUTHENTICATED', message}}` — fixes the API consumer surprise where jobhunter / automation webhooks would see HTML instead of structured error. Verified: both `Accept: application/json` AND no-Accept-header now return 401 JSON. (2) **B1 — `?compact=1` mode confirmed working**, the dev-env 3.5% reduction was an artifact of dev DB having only 5 projects with `problem`=EMPTY / `outcome`=EMPTY fields. Existing test [`compact_mode_reduces_body_size_by_at_least_40_percent`](backend/tests/Feature/CvMasterMarkdownApiTest.php) PASSES on factory data with populated fields (production has 56 projects per CLAUDE.md so 40%+ target holds). No code change needed — Blade `!$compact` flag at lines 59-64 of [`master.blade.php`](backend/resources/views/cv/master.blade.php) correctly gates Problem/Outcome lines. (3) **B3 — localhost footer URL is dev-env only**, `.env` `APP_URL=http://localhost/Portfolio_v2/backend/public` in dev; production VPS has `APP_URL=https://alisadikinma.com`, service uses config so auto-corrects post-deploy. No code change. (4) **C — API Playground widget shipped** on `/admin/automation/tokens` page below the table. Form: Token dropdown (filtered to current user's existing tokens) + Method (GET/POST/PUT/DELETE) + Endpoint input with `<datalist>` autocomplete suggestions filtered by selected token's abilities (`/api/health`, `/api/cv/master.md`, `/api/cv/master.md?compact=1`, `/api/cv/export`, `/api/automation/posts`, `/api/categories`). Uses raw `fetch()` (NOT axios `api` instance) so the bearer being tested is what actually goes on the wire — no admin-session interceptor fallback that would silently mask 401s. Plain-text token caching strategy: when minted via the modal, plainText is cached in `sessionStorage[admin:tokens:plaintext-cache]` keyed by token id (cleared on tab close, never persisted to disk). For tokens minted in a previous session OR via `tinker`, an amber warning panel surfaces a "paste plain-text token" input. Sanctum doesn't store plain text server-side by design — caching client-side for the active session is the only path. Response panel: status badge (color-coded by 2xx/3xx/4xx/5xx tier) + duration ms + size + headers (sorted) + body (first 50 lines truncated, full body in copy-to-clipboard buffer). Clear button resets state. (5) **D — Usage stats column rewrite.** Existing `requests_count` field (sourced from `automation_logs` table) is now category-gated: only shown for `automation` tokens (CV API doesn't have a logging middleware so the count was always 0 — misleading). CV tokens show `—` with tooltip explaining the gap. `last_used_at` column now renders relative time (`2h ago` / `5d ago` / `Never`) with a color-coded activity badge: `<24h` emerald (active), `<7d` blue (recent), `<30d` amber (stale), `≥30d` red (dormant), `null` gray. Sanctum auto-updates `last_used_at` on every authenticated request — authoritative usage signal for CV API without needing new logging infrastructure. Tooltip on hover shows full datetime for precise audit. **Plus E — plugin `linkedin-post-writer` v0.6.0** (separate repo, commit `185fa0f`): minimal additive ship — (a) `linkedin-convert/SKILL.md` gains explicit "Authoring Language: ENGLISH" hard rule at the top (formalizes what RAG §05 already declares as `primary_language='en'`, makes the directive impossible to miss when Sonnet processes the skill file, allows Indonesian *terms* as cultural shorthand but enforces English grammar + connective tissue), (b) `OrchestratorOutputSchema` gains optional `caption_language: z.enum(['en','id']).optional()` field for backend telemetry. All 221 plugin tests pass. No breaking changes — backward compat preserved (field is optional, legacy v0.5.x envelopes parse fine). Backend already feeds this skill the EN translation of source blog (Portfolio_v2 May 6 ship in commit `4982b22d`'s upstream `856d59d8`) so plugin naturally authors English given English input. **Files changed Portfolio_v2 (3)**: [`bootstrap/app.php`](backend/bootstrap/app.php) (+15 lines AuthenticationException handler), [AutomationTokens.vue](frontend/src/views/admin/AutomationTokens.vue) (+200 lines API Playground section + relativeTime/usageBadgeClass helpers + plainText caching + watch hook for fresh-mint cache), CLAUDE.md (this entry). Vite build clean (~1.7s). **Files changed plugin (3)**: package.json (v0.5.0 → v0.6.0), linkedin-convert/SKILL.md (+ EN hard rule paragraph), linkedin-gen/schema.ts (+ optional caption_language field). **Earlier (May 6 same day):** **API token admin UI generalized for category-based surfaces (Automation + CV API).** Operator review of `/api/cv/master.md` smoke tests revealed the existing `/admin/automation/tokens` UI couldn't mint or display CV-API tokens — the `TokenController::index` method filtered by `name LIKE 'api-%'` and `store` validated abilities against a hardcoded whitelist (`post:read/write/delete, category:read`) plus a name regex `^api-`. Generalized into a category registry pattern: [`TokenController::CATEGORIES`](backend/app/Http/Controllers/Api/TokenController.php) constant declares `automation` (`api-` prefix, 4 post abilities, n8n/Zapier surface) + `cv` (`cv-` prefix, `cv:read` ability, jobhunter surface). New `categoryForName()` helper resolves a token's category from its stored name prefix; `'other'` for legacy/auth tokens (kept invisible in UI). New `GET /api/admin/automation/categories` endpoint returns the static config (slug + prefix + label + abilities + description) so the frontend renders the create-modal dropdown without hardcoding values that drift from the backend whitelist. `index()` widened to OR-match across all registered prefixes; optional `?category=cv` query param narrows. `store()` accepts `category` field (defaults `automation` for backward compat), validates abilities against per-category whitelist via `Rule::in($category['abilities'])`, auto-prefixes the user-supplied name (`jobhunter-prod` → `cv-jobhunter-prod`). 422 responses return structured JSON errors when `Accept: application/json` sent (Laravel validator default). Adding a new category = one row in `CATEGORIES` constant + a routes entry if needed; no schema change, no new endpoints. Frontend: [AutomationTokens.vue](frontend/src/views/admin/AutomationTokens.vue) extended with category tabs at top (`All` / `Automation` / `CV API` with per-tab counts via new `tokensByCategory` Pinia getter), Create Token modal gains Category dropdown (driven by live `/categories` endpoint with hardcoded fallback for degraded conditions) that reactively switches the prefix addon + ability checkbox set + name placeholder, category badges per row (purple for automation, emerald for CV), `openCreateModal()` pre-selects the active tab's category for one-click flow. New [`useAutomation.fetchCategories()`](frontend/src/stores/automation.js) action with cache (categories are static config, fetch once per session), `fetchTokens(category?)` accepts optional filter param, new `tokensByCategory` getter buckets tokens for tab counts. Routes: 1 new `GET /api/admin/automation/categories` (auth:sanctum, no breaking changes to existing `/tokens`, `/tokens/{id}`, `/logs`). Smoke verified end-to-end: minted `cv-jobhunter-prod` via API → curl `/api/cv/master.md` with new token → HTTP 200 / 5169 bytes; cross-category abilities (post:write on cv category) → 422 JSON with operator-actionable message; invalid category slug → 422 JSON with valid-options listed; `?category=cv` filter returns only CV tokens. **Backward compat**: existing `api-*` tokens stay visible (default category=`automation`), legacy POST without `category` field still works (defaults to automation). No migration. Vite build clean (~2.5s). **Earlier (May 6 same day):** **LinkedIn admin UX polish + EN caption end-to-end (3-issue ship from operator review).** Three operator-driven changes wired in one Portfolio_v2 commit, each independently shippable: (1) **Queue list Depth column dropped** ([LinkedInQueueList.vue](frontend/src/views/admin/LinkedInQueueList.vue)) — carousels post-plugin-v0.5.0 skip `/linkedin-validate`'s depth rubric so the score was either null or a legacy 100 stub; column was noise. Detail page + Posts list keep depth display for TEXT-format inspection. Grid template trimmed 7→6 cols, sortable header + row cell removed, `'depth'` case dropped from `sortValue()`, `depthTone()` helper retained (still scores Virality column). (2) **Generating progress % indicator** in Queue list — new `generatingProgress(draft)` helper in [linkedinHelpers.js](frontend/src/views/admin/linkedinHelpers.js) reads `pipeline_state_log[]` for the latest transition into `generating`/`validating` and computes elapsed-vs-baseline %. **Format-aware baselines**: text 60s / carousel 360s (carousel pipeline = `/linkedin-gen` short-circuit ~15s + `/carousel-gen` SSH ~3-7 min observed P50). Hard cap at 95% during generating so operator never sees a misleading "100%" on a still-running run; validating ramps 95→99 over 8s window with 99 cap. Synthetic % is honest about lack of real plugin progress callback (labeled `~`) — answers "is anything happening?" without overpromising precision. Defensive: returns `null` for non-generating statuses, missing `pipeline_state_log`, malformed timestamps (no `NaN%` reaches UI). 10-case Node smoke test at [linkedinHelpers.test.mjs](frontend/src/views/admin/linkedinHelpers.test.mjs) covers out-of-scope statuses, missing logs, format baselines, hard caps, validating ramp + cap, malformed input. UI wiring: 1s `setInterval` ticker in `LinkedInQueueList.vue` set in `onMounted` + cleared in `onBeforeUnmount` (mirrors detail-page cancel-window pattern, no orphan timers on route change). Status pill template uses comma-operator pattern `(tick, generatingProgress(draft)) !== null` to subscribe Vue reactivity to `tick.value` while still returning the helper's value — single helper call per render, no O(n×renders) explosion. Suffix renders as `~N%` with `opacity-70` Tailwind utility inside the existing pill (no new tokens, no new layout primitives). (3) **English-only caption end-to-end** for both TEXT and CAROUSEL formats — operator scope: caption (the LinkedIn post body that accompanies content) is English; carousel SLIDES themselves stay bilingual (ID main headline `#FFFFFF` + EN subtitle `#F5A623` baked into rendered slide images via `CarouselSlideEnhancer` brand chrome, untouched). Backend changes: [`LinkedInGenerationService::buildCarouselCaption`](backend/app/Services/LinkedInGenerationService.php) made public for testability (matches `parseOrchestratorOutput` precedent), hook source flipped `cover.copy_id` paragraphs → `cover.copy_en` single-line (with defensive `splitParagraphs` if multi-paragraph EN ever ships), entire subtitle/coverStat machinery DELETED — copy_en is now the single hook source so re-emitting it as subtitle would duplicate. 3 Indonesian literals → English: `'Apa yang sebenarnya terjadi di balik layar?'` → `"What's really happening behind the scenes?"` (engagement default), `'Yang nggak kelihatan dari permukaan:'` → `"What you can't see from the surface:"` (insights label — discovered during impl, not in original plan), `'Swipe → untuk breakdown lengkap.'` → `'Swipe → for the full breakdown.'` (CTA). `extractSetupParagraph` prefers EN translation (`where('language', 'en')->first() ?? where('language', 'id')->first() ?? first()`), slide-fallback prefers `copy_en` over `copy_id` with legacy `copy` chain. `extractInsightsFromSlides` source preference flipped `headline_id` → `headline_en` first. **Plus Phase 5 (scoped down)**: `buildBlogPayload` (the SSH bridge that ships blog content to `/linkedin-gen` plugin) flipped translation preference ID → EN — operator clarified blog posts already have both translations stored via the article-translate pipeline (April 21); just feed plugin the EN one. Plugin's RAG already declares `primary_language = "en"`, so given EN input, plugin authors EN output naturally — no plugin v0.6.0 release needed. Original plan called for plugin work + manual VPS deploy; reality was a 1-method backend swap (made `buildBlogPayload` public + flipped `firstWhere('language', 'id')` to `'en'` first with `id` then `first()` as fallback chain). `synthesizeHashtagsFromBlog` flipped to EN-primary `meta_keywords` for consistency with caption + payload preferences. Drafts created BEFORE deploy keep their existing language; operator clicks Regenerate to re-author in English. Tests: 9-case [`LinkedInGenerationServiceCaptionTest`](backend/tests/Unit/LinkedInGenerationServiceCaptionTest.php) (6 caption + 3 buildBlogPayload). All 24 LinkedInGeneration tests pass / 53 assertions (9 new caption + 10 existing parser + 5 existing carousel-gen-router). Test infra: extends `Tests\TestCase` (Laravel boot needed for `config()` helper), uses `setRelation()` on real Eloquent models (no DB), defensive `relationLoaded` guard added to `buildBlogPayload` so unit tests skip the `loadMissing` resolver call while production behavior is preserved (callers don't always eager-load). Code-reviewer pass on Phase 4 surfaced 2 Important docblock issues — orphan stale class-level block describing pre-EN 7-block caption structure was deleted, `regenerateCaption` docblock updated to "6-block English-only synthesizer". Files changed (5): 3 frontend (LinkedInQueueList.vue + linkedinHelpers.js + linkedinHelpers.test.mjs new) + 2 backend (LinkedInGenerationService.php + LinkedInGenerationServiceCaptionTest.php new). NO plugin repo changes. NO operator VPS deploy required (no plugin redeploy, no env var changes, no migrations). Vite production build clean (~4s incremental). Brainstorm + plan + per-phase checkpoints at [docs/plans/2026-05-06-linkedin-en-caption-and-queue-polish.md](docs/plans/2026-05-06-linkedin-en-caption-and-queue-polish.md). **Earlier (May 5):** **CV Master Markdown API shipped — `GET /api/cv/master.md` LLM-optimized rendering for jobhunter platform.** Companion endpoint to existing `/api/cv/export` (JSON Resume) — same Sanctum bearer + `cv:read` ability + `throttle:30,1`, but emits a single dense markdown document (~10k tokens default, ~5k via `?compact=1`) for direct embedding into jobhunter's `cv-tailor` and `job-score` LLM prompts. English only with silent Indonesian fallback (no `[ID]` prefix tags). Reuses `CvProjectResource::relevance_hint` heuristic so JSON + markdown exports surface identical industry tags. Sections: identity (`Setting{group=about}`: name/title/bio/social_links/location) + ## Summary + ## Skills Matrix (5 hand-curated domains in new `config/cv.php`, joined with live project counts via `CvProjectResource` reuse) + ## Selected Projects (full inventory, sort_order ASC, EN translation preferred + ID fallback, role + year_range + industry + tech_stack + Problem + Outcome + relevance) + ## Awards & Recognition (`is_featured DESC, id DESC`) + ## Thought Leadership (top 5 published posts) + footer (Generated YYYY-MM-DD + self URL). ETag-revalidated via existing `ApiETag` middleware (Phase A, May 2026) — second request with `If-None-Match` returns 304 + empty body. Compact mode gates Problem/Outcome lines behind `!$compact` Blade flag. New files: [`CvMasterMarkdownService`](backend/app/Services/CvMasterMarkdownService.php) (174 LoC: settings hydration, `aggregateSkillDomains()` reusing CvProjectResource hints, `buildProjectRow()` with EN-pref translation override + year-range formatting + word-truncated summary, `loadAwardRows()` + `loadThoughtLeadershipRows()` mirroring CvExportController query patterns), [`config/cv.php`](backend/config/cv.php) (5 skill_domains: ai_automation/vibe_coding/ai_agents/manufacturing/enterprise, keys match relevance_hint heuristic), [`resources/views/cv/master.blade.php`](backend/resources/views/cv/master.blade.php) (Blade template using `{!! !!}` for trusted server-sanitized text since markdown output doesn't need HTML escaping; bio + descriptions go through `normalizeMarkdownText()` strip_tags + entity_decode first), [`tests/Feature/CvMasterMarkdownApiTest.php`](backend/tests/Feature/CvMasterMarkdownApiTest.php) (10 tests: 401/403/200 auth gates, identity rendering, skills matrix counts, project sort_order + EN/ID fallback, awards is_featured ordering, top-5 thought leadership, footer timestamp + self URL, compact mode 40%+ reduction, ETag round-trip 304), [`tests/Unit/CvMasterMarkdownServiceTest.php`](backend/tests/Unit/CvMasterMarkdownServiceTest.php) (2 tests: skeleton render + config shape). Modified: [`CvExportController`](backend/app/Http/Controllers/Api/CvExportController.php) gains `master()` action via method-level DI, [`routes/api.php`](backend/routes/api.php) adds one line under existing `cv:read` middleware group. Token mint pattern unchanged from Phase 10 (`User::find(1)->createToken('jobhunter-cv-export', ['cv:read'])->plainTextToken`). Tests: 25 pass / 148 assertions (10 new feature + 2 new unit + 13 existing CvExport regression — all green). Design + 8-phase TDD plan in [docs/plans/2026-05-05-cv-master-markdown-api.md](docs/plans/2026-05-05-cv-master-markdown-api.md). **Earlier same day** — **Newsletter system shipped end-to-end + SMTP via admin panel (no `.env` hardcoding).** Full pipeline live: 4 frontend touchpoints (Blog inline + InlineCard + FloatingBanner + FooterBar with modal-on-click) collect name + email + WhatsApp E.164 strict, 2 schema migrations (lead fields on `newsletters` + `newsletter_sends` audit table, both nullable for safe legacy backfill), `Newsletter` model auto-generates 32-char `unsubscribe_token` via `creating` hook, `NewsletterController::subscribe` widened with name/email/WhatsApp validation + dedup-by-email-or-WA + 409 DUPLICATE response, new `POST /newsletter/unsubscribe-by-token` public endpoint with token-based confirmation flow (no email re-typing — friction-free GDPR right-to-erasure), public `/newsletter/unsubscribe?token=X` Vue page (idle/success/invalid_token states + `clearNewsletterState()` re-enables forms on this device). Email pipeline: new `App\Mail\WeeklyDigest` Queueable Mailable + `weekly-digest{,-text}.blade.php` Dark Cinema HTML (600px max-width, table-based layout, ALL CSS INLINE per Resend/Hostinger/Outlook compatibility, gold #D4A843 accent + cyan #06B6D4 links, post cards with `featured_image` + category eyebrow + title + 180-char excerpt + gold "Read this essay →" CTA + UTM tracking `?utm_source=newsletter&utm_medium=email&utm_campaign=weekly-{Y-W}`, "Reply to this email — I read every one" personal touch, footer with token-based unsubscribe + LinkedIn + Portfolio links). New `SendWeeklyNewsletter` artisan command (`newsletter:send-weekly {--dry-run} {--force} {--limit=} {--triggered-by=cron|manual|test} {--user-id=}`) with skip-if-empty path inserting `status=skipped` audit row (no spam when blog quiet) + dry-run path printing rendered HTML without DB write + real send via `Newsletter::chunkById(100)` → `Mail::to($sub)->queue(new WeeklyDigest($posts, $sub))` + exception path inserting `status=failed` with truncated error_message + success row with subscriber_count + posts_count + post_ids[] + duration_seconds. Schedule entry in `routes/console.php`: `Schedule::command('newsletter:send-weekly')->fridays()->at('09:00')->timezone('Asia/Jakarta')->withoutOverlapping(60)` — reuses existing `portfolio-queue.service` systemd worker + `portfolio-scheduler` host crontab, NO new infra. New `BackfillNewsletterTokens` one-shot artisan (`newsletter:backfill-tokens [--dry-run]`, idempotent). New `NewsletterAdminController` with 8 endpoints (list + delete + CSV streamed export + digest-preview rendered HTML + send-test sync + send-now async via `Artisan::queue` + paginated send-history + single-send detail with post_ids[] resolved to titles). New admin Vue view `/admin/newsletter` (`NewsletterSubscribers.vue`) with TanStack Query composable `useNewsletterAdmin.js` (mirrors `useLinkedInDrafts.js` 30s staleTime + refetchOnMount:'always' pattern) — 2 tabs Subscribers + Send History, search + source filter + paginated table + delete-with-confirm-modal + Compose Digest panel (Preview iframe modal, Send-test default-to-auth-user-email, Send-now confirm-checkbox modal). Sidebar nav entry "Newsletter" between LinkedIn (Queue) and Contact in `AdminLayout.vue`. SMTP config moved out of `.env` to admin panel: new `MailSettingsSeeder` (8 keys, group `mail`, Hostinger defaults pre-filled `aiagent@alisadikinma.com` / `smtp.hostinger.com` / 465 / SSL, password null), new `App\Providers\MailConfigOverrideProvider` reads settings at boot + overrides `config('mail.*')` (silent failure on DB-unavailable / decrypt-fail so artisan still boots on fresh schemas), password encrypted via `Crypt::encryptString` before DB write + masked as `***SET***` in API responses with `mail_password_configured: true` flag, registered in `bootstrap/providers.php`. `SettingsController` extended with `getMailSettings` + `updateMailSettings` (empty password preserves existing) + `testMailConnection` (re-applies config from DB before sync send so admin doesn't wait for `queue:restart` to verify). 3 new admin routes `GET/PUT /api/admin/settings/mail` + `POST /api/admin/settings/mail/test`. New "Email — SMTP Settings" card on `AboutSettings.vue` between Telegram and LinkedIn cards (host/port/username/password/encryption-dropdown/from-address/from-name fields + "📤 Send test email to me" button disabled until password configured). `useNewsletter()` composable widened — `subscribe(payload)` accepts `{name,email,whatsappNumber,source}` object with backwards-compat shim for legacy bare-string callers (`console.warn` deprecation), new `unsubscribeByToken(token)` method, snake_case body conversion (`whatsappNumber` → `whatsapp_number`) handled inside composable. WhatsApp validation: client `<input type="tel">` + `pattern="^\+[1-9]\d{6,14}$"` + `@blur="validateWa"` JS regex check + red-border feedback + "Format internasional, mulai dengan +" help text; server re-validates with strict regex (defense-in-depth). `whatsapp_number` UNIQUE constraint at DB level (defense vs phone spam). `is_subscribed`/`subscribed_at`/`unsubscribed_at` legacy columns kept untouched as dead weight (backwards compat — never queried; hard-delete on unsubscribe per GDPR). Cleanup: dead `handleSubscribe()` placeholder removed from `CTASection.vue` (lines 45-56, never rendered anyway since template only had WhatsApp + Get in Touch buttons). Tests via `php -l` syntax + curl smoke + tinker render verification (RefreshDatabase tests blocked by env-specific MySQL tablespace per CLAUDE.md known issue, not blocking ship). Anti-patterns enforced: no open/click tracking v1, no welcome email v1, no multi-language email v1, no soft-pause flag (hard-delete only), no synchronous send loop (always queue), no WA storage without UNIQUE constraint. Operator runbook at `docs/runbooks/newsletter-deploy.md` covers admin-panel SMTP config (NOT `.env`), Hostinger sending limits (~100/hour shared plan), first-Friday cron observation, rollback path. Design + 17-phase plan + runbook all under `docs/plans/2026-05-05-newsletter-system*.md`. **Files (32):** 11 backend new + 7 backend modified + 4 frontend new + 10 frontend modified + 3 docs new + this CLAUDE.md update. **Important meta-note:** Wave 2 of execution dispatched 2 background subagents (laravel-specialist + vue-expert) for parallel work — both fabricated their entire reports (claimed 6 commits + 12 files that never existed). Caught via `git status` + `git cat-file -t` verification before proceeding. All work in this commit was implemented inline by the operator with verified file existence + syntax checks + smoke tests against the running XAMPP backend. Trust-but-verify pattern from Agent tool docs proven essential. **Earlier same day:** **Performance + cache strategy refactor (Phases A + C + D + E shipped, Phase B dropped).** **Phase D session (this commit): Frontend BaseImage component + 9-site `<img>` replacement.** New [`BaseImage.vue`](frontend/src/components/base/BaseImage.vue) consumes the `image_variants` JSON the backend now exposes (Phase C). Renders `<picture><source type="image/webp" srcset="320w...1920w" sizes="...">` + `<img>` fallback with browser-native `loading="lazy"` (or `eager` for hero) + `decoding="async"` + `fetchpriority` + LQIP base64 blur background that fades to opacity:1 over 280ms once the full image's `load` event fires. `aspectRatio` prop pre-reserves layout space (zero CLS). Graceful fallback when `image_variants` is null/empty — renders plain `<img src>` so partial backfill is non-breaking. `prefers-reduced-motion` zeroes the fade transition. Wired into 9 sites: [`ProjectsBento`](frontend/src/components/home/ProjectsBento.vue) (homepage projects grid, idx===0 eager+high priority), [`LatestBlog`](frontend/src/components/home/LatestBlog.vue) (homepage 1 hero + 3 secondary), [`BlogHeroCard`](frontend/src/components/blog/BlogHeroCard.vue) (eager+high — list page hero), [`BlogSmallCard`](frontend/src/components/blog/BlogSmallCard.vue) + [`BlogTallCard`](frontend/src/components/blog/BlogTallCard.vue) + [`BlogWideCard`](frontend/src/components/blog/BlogWideCard.vue) (blog list grid variants), [`BlogDetail`](frontend/src/views/BlogDetail.vue) (article hero + related posts grid), [`ProjectDetail`](frontend/src/views/ProjectDetail.vue) (related projects strip — case study screenshots use a separate `getImageUrl(slug, size, format)` helper not via `image_variants`, intentionally not migrated), [`Projects.vue`](frontend/src/views/Projects.vue) (project listing 4:3 cards), [`Gallery.vue`](frontend/src/views/Gallery.vue) (gallery thumbnails). `sizes` attribute calibrated per breakpoint (e.g. hero `100vw`, 3-col grid `(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw`, related-projects strip `280px`). Decorative blur backgrounds (BlogDetail header bg-image at low opacity) intentionally left as plain `<img>` — wasteful to load 4 variants for a heavily blurred background. Vite production build clean (21.38s, all chunks emit cleanly). Combined effect post-backfill: mobile viewports download ~30-50KB vs ~540KB per project thumbnail (60-80% byte reduction). Phase D commit closes the perf+cache refactor end-to-end. **Phase B (Service Worker upgrade) DROPPED from scope** — browser HTTP cache + CF edge cache + ETag/304 already cover 95% of revisit perf for non-PWA portfolio site. Revisit if real offline/PWA story emerges. **Earlier same day** — **Performance + cache strategy refactor (Phase A + C + E shipped).** Three-pronged perf hardening per [docs/plans/2026-05-05-portfolio-perf-cache-refactor.md](docs/plans/2026-05-05-portfolio-perf-cache-refactor.md). **Phase A (commit `9162cc3d`): cache strategy refactor.** Operator reported new page-section rows (`what-i-solve`, `testimonials`) didn't appear for users with warm browser sessions even after clearing browser cache — incognito worked. Root cause: 24-hour `localStorage['PORTFOLIO_QUERY_CACHE']` persistence in [main.js](frontend/src/main.js) restored TanStack queries via `setQueryData()`, which marks `dataUpdatedAt = now` so `refetchOnMount:'always'` on [usePageSections](frontend/src/composables/usePageSections.js) skipped the network refresh; data appeared "fresh" to TanStack but was 24h stale. Fix: dropped the persist-and-restore strategy (60 LOC removed from main.js — kept a one-time legacy key cleanup block, safe to remove ~June 2026). Replaced with HTTP-semantic revalidation via new [`App\Http\Middleware\ApiETag`](backend/app/Http/Middleware/ApiETag.php) — weak ETag (`W/"..."`) on every 2xx JSON GET response, RFC 7232 §2.3.2 weak comparison, wildcard support, comma-list support. Weak prefix because Apache `mod_deflate` strips strong ETags on gzipped responses (and md5-over-content is content-equivalent not byte-equivalent semantics anyway). Skips non-GET, non-2xx, empty, streamed/binary, and >1MB payloads. Registered in api group at [bootstrap/app.php](backend/bootstrap/app.php). [`usePageSections::fetchActiveSections`](frontend/src/composables/usePageSections.js) early-return on cache hit removed — refetch always fires, TanStack serves cached data instantly while background-fetching fresh state. [`updateSection`](frontend/src/composables/usePageSections.js) + [`reorderSections`](frontend/src/composables/usePageSections.js) Layer 3 PORTFOLIO_QUERY_CACHE bookkeeping deleted (no longer relevant). Tests: 10/10 [`ApiETagMiddlewareTest`](backend/tests/Feature/ApiETagMiddlewareTest.php) green; manual round-trip on Apache verified `/api/categories` → `ETag: W/"852d52b73ef15823a5e0f9822f612b0a"` first hit, second request with `If-None-Match` → `HTTP/1.1 304 Not Modified` (~80 byte response vs 1696 byte full payload, ~95% bandwidth saved per revalidation). **Phase C (commit `9af535a8`): WebP variant pipeline + LQIP placeholders.** Backend infrastructure for responsive image delivery — generates 4 WebP widths (320/640/1024/1920w) + base64-encoded LQIP blur placeholder per Project / Post / GalleryItem image. New schema: nullable JSON column `image_variants` on `projects`, `posts`, `gallery_items` (migration `2026_05_05_000004_add_image_variants_to_projects_posts_gallery`, applies cleanly via deploy.sh step 3). Stored shape: `{"320w": "/storage/...-320w.webp", "640w": "...", "1024w": "...", "1920w": "...", "lqip": "data:image/jpeg;base64,..."}`. New [`App\Services\ImageVariantService`](backend/app/Services/ImageVariantService.php) — Imagick when available else GD (Imagick faster + better quality), idempotent (skips variants newer than source), no upscale (skips widths >= source width), ~1KB LQIP via 24w JPEG q30 base64. WebP-only for v1 — AVIF deferred (encoding 5-10x slower, marginal browser-support delta in 2026). Static [`ImageVariantService::normalizePath`](backend/app/Services/ImageVariantService.php) handles legacy absolute URLs (`https://alisadikinma.com/storage/...`) + `/storage/` prefix + bare relative paths — 10/10 unit tests pass [`ImageVariantServiceNormalizePathTest`](backend/tests/Unit/ImageVariantServiceNormalizePathTest.php). New [`HasImageVariants`](backend/app/Traits/HasImageVariants.php) trait registers a saved() listener — when source column changes (`Project.image`, `Post.featured_image`, `GalleryItem.file_path`), dispatches [`GenerateImageVariantsJob`](backend/app/Jobs/GenerateImageVariantsJob.php) (180s timeout, 2 tries, queued). Job uses `saveQuietly()` to avoid re-firing the saved() listener and creating an infinite dispatch loop. Each model declares its source column via `imageVariantSource(): string`. Trait wired into [Project](backend/app/Models/Project.php), [Post](backend/app/Models/Post.php), [GalleryItem](backend/app/Models/GalleryItem.php) with `image_variants` added to `$fillable` + `$casts['array']`. New artisan command `php artisan images:generate-variants [--model=Project|Post|GalleryItem|all] [--limit=N] [--dry-run]` ([`GenerateImageVariants`](backend/app/Console/Commands/GenerateImageVariants.php)) for one-shot backfill of historical inventory — uses `chunkById(50)` for memory safety. API resources expose `image_variants` field: [ProjectResource](backend/app/Http/Resources/ProjectResource.php) + [PostResource](backend/app/Http/Resources/PostResource.php) + [GalleryItemResource](backend/app/Http/Resources/GalleryItemResource.php). Null-safe — frontend BaseImage component (Phase D) gracefully falls back to plain `<img src>` when variants are null, so partial backfill is non-breaking. **Operator action post-deploy**: SSH to VPS → `cd /var/www/Portfolio_v2/backend && php artisan images:generate-variants --dry-run` (preview), then re-run without `--dry-run` to backfill. Queue worker (`portfolio-queue.service`) processes ~30-60s per image. **Phase E (operator-driven, no commit): Cloudflare proxy + edge caching.** Domain `alisadikinma.com` moved from Hostinger DNS parking to Cloudflare. Nameservers updated `ns1/ns2.dns-parking.com` → `doug.ns.cloudflare.com` + `liv.ns.cloudflare.com` at Hostinger registrar. CF zone activated, status banner "Your domain is now protected by Cloudflare". DNS records audit: 18 records imported, mail-related CNAMEs (autoconfig, autodiscover, hostingermail-a/b/c._domainkey) all set to DNS only (grey cloud) — proxying mail records breaks autoconfig + DKIM. Backend services (ffmpeg, sparkfluence, sparkfluence-api, labelstudio, jobs) DNS only — websocket / large upload risk under CF default 100s timeout + 100MB body limit. Apex + www Proxied (orange cloud). DNSSEC verified disabled at Hostinger. n8n A record dropped (unused). Verified end-to-end: `curl https://alisadikinma.com/` returns `Server: cloudflare` + `cf-ray: ...-SIN` (Singapore POP); image at `/storage/projects/thumbnail/49_*.png` 1st request `cf-cache-status: MISS`, 2nd request `cf-cache-status: HIT` + `Age: 4` — edge caching active globally. API endpoints `cf-cache-status: DYNAMIC` (bypass per `Cache-Control: no-cache, private` from origin). New runbook [docs/ops/cloudflare-setup.md](docs/ops/cloudflare-setup.md) documents the full CF setup including SSL/TLS Full strict, 3 page rules (`/storage/*` + `/uploads/*` Cache Everything 1 month TTL, `/api/*` Bypass), Brotli, HTTP/3, Auto Minify (Rocket Loader OFF — breaks Vue hydration). E2-E8 advanced settings deferred to operator's pace — basic edge caching already serving. **Phase B (Service Worker upgrade) DROPPED from scope** — browser HTTP cache + CF edge cache cover 95% of revisit perf for non-PWA portfolio site; SW adds complexity (cache invalidation hard, debugging painful) without commensurate benefit. Revisit if real offline/PWA story emerges. **Phase D (frontend BaseImage component) NEXT** — replace `<img>` in 8 sites (ProjectCard, BlogCard, AwardCard, BlogDetail hero, ProjectDetail hero, Gallery, LatestBlog, ProjectsBento) with new `BaseImage.vue` consuming `image_variants` via `<picture><source srcset>` + LQIP blur fade-in + `loading="lazy"` + `fetchpriority="high"` for hero. **Files changed Phase A+C (19)**: 3 frontend (main.js + usePageSections.js + 1 plan doc) + 14 backend (1 middleware + 1 service + 1 trait + 1 job + 1 artisan + 1 migration + 3 model + 3 resource + 1 bootstrap + 2 test files) + 1 ops runbook. **Earlier (May 5):** **LinkedIn link_comment defense-in-depth + legacy backfill.** Operator review of LinkedIn post #13 (post_id=14, "Vibe Coding Surveillance") surfaced the bug: blog post DOES exist + IS published, but the auto-posted first comment was the plugin's stale pull_quote ("Infrastruktur pengawasan massal bisa dibangun tanpa pembangunnya memahami konsekuensinya.") instead of the canonical blog URL — defeating the whole link-in-comment automation that exists to dodge LinkedIn's 60% reach penalty on body links. Root cause: draft #13 was generated **2026-04-25**, four days before commit `c64e9c31` (Apr 29) added [`LinkedInGenerationService::resolveLinkComment`](backend/app/Services/LinkedInGenerationService.php) — pre-fix the legacy code path stored whatever the plugin emitted in `post.link_comment`, which for some carousel runs was a brief.pull_quote string (no URL). Draft was published Apr 5 (4 days **after** the fix landed), but `resolveLinkComment` only fires at generation/persistAndRoute time — there was no second-chance check at publish time. **Fix #1 (defense-in-depth)**: new public model method [`LinkedInPost::ensureLinkCommentHasUrl()`](backend/app/Models/LinkedInPost.php) — idempotent, no-op when `link_comment` already contains `http(s)://`, otherwise rewrites to `"Full article: {APP_URL}/blog/{slug}"`. [`LinkedInPublishService::publish`](backend/app/Services/LinkedInPublishService.php) now calls it AFTER token-refresh AND BEFORE format dispatch (covers both `publishText` + `publishCarousel` paths in one chokepoint). Logged at INFO level when it fires so audit trail captures every legacy rewrite. **Fix #2 (one-shot backfill)**: new artisan command `php artisan linkedin:backfill-link-comment [--dry-run]` ([`BackfillLinkedInLinkComment`](backend/app/Console/Commands/BackfillLinkedInLinkComment.php)) — finds drafts where `link_comment NOT LIKE '%http%'` (with-trashed for audit completeness), rewrites via the same `ensureLinkCommentHasUrl()` method (single source of truth). Skips drafts with no `post.slug`. Does NOT re-post comments to LinkedIn (LinkedIn comment-edit API not wired) — only fixes DB so future republish/regenerate use the canonical URL. Backfill is run-once + idempotent (re-running on a clean DB rewrites zero rows). Tests authored: 6-case `LinkedInPostEnsureLinkCommentTest` (already-has-URL no-op, legacy rewrite, empty rewrite, dangling FK skip, empty APP_URL skip, case-insensitive HTTPS detection) — local PHPUnit blocked by stale MySQL tablespace (env-specific, affects all RefreshDatabase tests including pre-existing HomepageApiTest), syntax verified via `php -l` clean across all 4 changed files. **Files changed (4)**: 1 model + 1 service + 1 new artisan command + 1 new test. **Operator action after deploy**: run `php artisan linkedin:backfill-link-comment --dry-run` on VPS first to preview affected rows, then re-run without `--dry-run` to apply. **Earlier (May 4):** **Homepage Redesign Plan: Phase 1 + 10 + 11 + 3 shipped (parallel via /gaspol-parallel + /gaspol-review).** Plan source: [docs/plans/2026-05-04-homepage-redesign-plan.md](docs/plans/2026-05-04-homepage-redesign-plan.md) — 12-phase executable plan. Phase 1 (Foundation): new [`SetLocaleByGeoIP`](backend/app/Http/Middleware/SetLocaleByGeoIP.php) middleware (alias `set.locale.by.geoip` in [`bootstrap/app.php`](backend/bootstrap/app.php)) — 3-step locale resolution (cookie `lang_preference` → CF-IPCountry header → ip-api.com fallback, fail-open to `en`); 1yr lax cookie set via `$response->headers->setCookie()` directly (API middleware group lacks `AddQueuedCookiesToResponse`). Two new public endpoints: `GET /api/homepage/stats` (years_experience from config, `awards_count` filtered to `is_active=true`, `projects_count` filtered to `published=true`, enterprise_brands from config) + `GET /api/homepage/featured` (stats + 4 curated lists: featured_awards `is_featured DESC, id DESC` limit 6, featured_testimonials filtered `source=linkedin` limit 4, featured_projects `sort_order` limit 5, latest_articles `published_at DESC` limit 5). 2 schema migrations: `testimonials.source` enum(`linkedin,direct,video`) + `testimonials.source_url` (after `testimonial_text` real column name); `awards.is_featured` bool (after `sort_order`). New configs [`config/homepage.php`](backend/config/homepage.php) + `app.years_experience` + `services.geoip.{fallback,timeout}` env-overridable. Tests: 7 pass / 49 assertions in [`HomepageApiTest`](backend/tests/Feature/HomepageApiTest.php). Phase 10 (CV Master Export API): new `GET /api/cv/export` token-protected endpoint (Sanctum bearer + `ability:cv:read` + `throttle:30,1`) returning JSON Resume schema (basics + 56 projects + 5 awards + top 5 thought_leadership) for jobhunter platform consumption. New [`CvExportController`](backend/app/Http/Controllers/Api/CvExportController.php) + 3 resources [`CvProjectResource`](backend/app/Http/Resources/Cv/CvProjectResource.php) (with `relevance_hint` heuristic — `mb_strtolower(haystack, 'UTF-8')` for unicode-safe matching, mapping table: AI/agent → ai_automation+ai_agents; vibe coding → vibe_coding; manufacturing → manufacturing+enterprise; logistics → logistics+enterprise; gov → gov_tech+enterprise; banking → fintech), [`CvAwardResource`](backend/app/Http/Resources/Cv/CvAwardResource.php) (Carbon parser handles year-only "2019" → "2019-01-01" + null + ISO + invalid), [`CvThoughtResource`](backend/app/Http/Resources/Cv/CvThoughtResource.php) (post_translations id-primary fallback chain). Sanctum `ability` + `abilities` middleware aliases registered in `bootstrap/app.php`. Real settings keys discovered: `name` (not `full_name`), `title` (not `headline`), `social_links` (not `social_media`); missing fields `email/phone/city` return `null` (no fake values). Tests: 10 pass / 51 assertions in [`CvExportApiTest`](backend/tests/Feature/CvExportApiTest.php). Token mint via `User::find(1)->createToken('jobhunter-cv-export', ['cv:read'])->plainTextToken`. Phase 11 (Work Experience ↔ Gallery Linking on About): [`SettingsController::getAboutSettings()`](backend/app/Http/Controllers/Api/SettingsController.php) hydrates `experience[i].galleries[]` from `gallery_ids[]` in single batched eager-load (`Gallery::withCount('items')->with('items', limit 4)` — `withCount` required so `items_count` reflects REAL total, not preview-limited collection count). Three storage URL conventions handled via `resolveAssetUrl`: absolute http(s) → as-is, `/uploads/*` + `/storage/*` → prepend APP_URL, bare keys → `Storage::url()` fallback. Dangling gallery IDs filtered silently. Original `gallery_ids` preserved on output for admin picker round-trip. [`About.vue`](frontend/src/views/About.vue) renders inline thumbnail strip per experience card (4 thumbs 80×80 desktop / 3 thumbs 60×60 mobile via matchMedia with `onBeforeUnmount` cleanup) + `+M` overflow chip. Click any thumb or chip → [`BaseGalleryModal`](frontend/src/components/base/BaseGalleryModal.vue) with FLATTENED items across all linked galleries. Gold border + scale 1.05 hover, gated behind `prefers-reduced-motion`. Section auto-hides when no galleries linked (silent absence). Phase 3 (What I Solve Tabbed Switcher): new [`whatISolve.js`](frontend/src/data/whatISolve.js) static content (3 tabs: Vibe Coding default + AI Agents + Video Generation, each with id/label/icon/headline/3 metrics/visual/CTA) + new [`WhatISolveTabs.vue`](frontend/src/components/home/WhatISolveTabs.vue) component (~290 LoC) with 120ms fade-out + 200ms fade-in `<Transition mode="out-in">`, gold underline scaleX hover (180ms ease-out + transform-origin: left), full WAI-ARIA tabs pattern (role=tablist/tab/tabpanel + aria-selected + roving tabindex + ArrowLeft/Right cycling with wraparound + Home/End + Enter/Space activation), mobile horizontal-scroll with `snap-x snap-mandatory`, image fallback to gold/cyan radial gradient placeholder when `/images/showcases/*.png` 404s (graceful — operator uploads later), `prefers-reduced-motion` zeroes transitions, gated by `isSectionActive('what-i-solve')` per existing usePageSections pattern. Mounted on [`Home.vue`](frontend/src/views/Home.vue) between SkillsReel + first SkillShowcase (old skill components coexist until Phase 8 cleanup). Page Sections Mapping table updated with `(homepage, what-i-solve)` row → `WhatISolveTabs`. Vite production build clean (~26.5s, new chunk 5.56 kB / 1.97 kB gzip). **/gaspol-review verdict: Ready to Merge** — 0 Critical, 4 Important all fixed in-line: items_count via `withCount` aggregate (was preview-limited count showing "Gallery (4)" + "+0" for >4-item galleries), About.vue matchMedia listener cleanup on `onBeforeUnmount`, `mb_strtolower` UTF-8 in relevance_hint heuristic, Award/Project counters filter by `is_active`/`published` to match what featured() actually surfaces. Combined: 17 backend tests / 100 assertions all green. Files: 7 new backend (HomepageController + CvExportController + 4 resources + middleware + 2 migrations + 2 test files + 2 configs) + 6 modified backend (Models + Resources + bootstrap + routes + SettingsController + 2 configs) + 1 modified frontend (About.vue) + 2 new frontend (WhatISolveTabs + whatISolve.js) + 1 modified frontend (Home.vue) + 1 modified docs (CLAUDE.md). Single bundled commit `11142e5e`. **Hero video Phase 9 (parallel track):** Genesis Triptych concept iterated through 5-keyframe → 3-keyframe → 3-keyframe with founder face. Two NB2 renders (KF-01) rejected by operator as "jelek sekali, no meaningful at all" — pillars rendered as flat 2D containers with literal code/UI content. Tool-agnostic [`docs/plans/hero-video/end-goal-brief.md`](docs/plans/hero-video/end-goal-brief.md) authored to hand off to alternative AI (Midjourney v7, FLUX 1.1 Pro, Imagen 4, Veo 3.1, Kling 2.5, Sora 2, Runway Gen-4) — defines 11 sections covering 3-act narrative + visual identity + 3-keyframe specs + motion + output specs + 10-item hard "do not" list + reference photo URL for KF-3 face identity lock. **Earlier same day** — **Carousel image generation root-cause fixes (5-fix bundle).** Operator hit "Last Error: Could not parse orchestrator JSON from stdout" + 0/9 slides rendered on draft 17. Verification (`/gaspol-verify`) found two distinct issues conflated in the UI: (a) the displayed `last_error` was stale from May 1 (pre the May 2 parser fix at commit `6396aadc`), (b) `/carousel-gen` SSH dispatch is still failing intermittently due to **Sonnet output token cap exhaustion** on 9-slide bilingual carousels — same open issue flagged in CLAUDE.md May 2 entry "Open issue (NOT fixed by Phase D)". Fresh forensic dump on VPS (`storage/app/carousel-gen-debug/draft-5-20260504080626.txt`, 41KB) confirmed pattern: Sonnet emits per-slide JSON chunks separated by ` ```json ` fences with continuation prose ("Continuing slide 5 image_prompt, then slides 6-9:") instead of one envelope; balanced-brace scanner correctly bails. Five mitigations bundled: **Fix #1** — default `target_slides` reduced 9 → 7 in [`LinkedInGenerationService::inferTargetSlides`](backend/app/Services/LinkedInGenerationService.php) (cuts ~22% of output tokens; per-framework defaults: AIDA=6, before_after/contrarian/default=7). [`RegenerateLinkedInCarouselContent.php`](backend/app/Jobs/RegenerateLinkedInCarouselContent.php) doc comment refreshed. **Fix #2** — plugin-side `image_prompt` Zod cap reduced 2500 → 1800 chars in [`schema.ts:53`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/skills/carousel-gen/schema.ts) of `ai-image-carousel-prompt-gen` plugin + explicit hard-cap section appended to [`carousel-gen/SKILL.md`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/skills/carousel-gen/SKILL.md) Step 4 with rationale. **Operator action required**: bump plugin version, `npm run compile-refs`, deploy `refs-carousel-gen-pipeline.md` to VPS at `/home/claudesn/refs-carousel-gen-pipeline.md` for the schema/SKILL changes to take effect at runtime. **Fix #3 (operator escape hatch)** — new admin action `POST /api/admin/linkedin-drafts/{id}/rerender-images` ([`LinkedInDraftController::rerenderImagesOnly`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) + [`useRerenderImagesOnly()`](frontend/src/composables/useLinkedInDrafts.js) composable + new "Re-render Images Only" cyan button in [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) actions panel). Bypasses `/carousel-gen` entirely — uses existing `carousel_slides[].image_prompt` JSON in DB and re-dispatches `GenerateLinkedInCarouselImages` directly. ~2-3 min vs ~5-7 min for full re-author. Validates each slide carries an `image_prompt` before queuing (else 422 with operator-actionable error). Same FSM-status guard as `regenerateAllImages` (blocks during pending_generation/generating/validating). Mirrors what the carousel-image reaper does on stuck slides, exposed as an explicit operator button. **Fix #4** — stale `last_error` UI suppression in [`LinkedInDraftDetail.vue::showLastError`](frontend/src/views/admin/LinkedInDraftDetail.vue) computed: now reads `pipeline_state_log` (already exposed via `LinkedInPost.$casts['array']`) and hides errors when the latest state transition is older than 24 hours. Status gate (`{failed, manual_review}`) preserved. Error stays in DB for debugging — only the UI suppresses. Draft 17's May 1 error stops haunting the May 4 UI on next load. **Fix #5** — [`config/carousel-gen.php`](backend/config/carousel-gen.php) `model` key inline doc explaining sonnet (~1x cost, default) vs opus (~4-5x cost, higher output token cap) tradeoff. No default change — operator flips `CAROUSEL_GEN_MODEL=opus` in `.env` if mitigations 1+2+3 don't suffice. Verification: PHP syntax clean across 5 backend files (`php -l`); 15/15 LinkedIn generation tests pass in 1.81s; Vite production build clean in 11.36s, `LinkedInDraftDetail` bundle 52.01 kB / gzip 13.02 kB. **Files changed Portfolio_v2 (8):** 1 service + 1 job + 1 controller + 1 route + 1 config (backend); 1 composable + 1 view (frontend) + this CLAUDE.md. **Files changed plugin `ai-image-carousel-prompt-gen` (3):** schema.ts + SKILL.md + CLAUDE.md. **Earlier (May 2):** **Strict /carousel-gen enforcement + parser fix (commit `6396aadc`).** Two production-impacting bugs surfaced via [`storage/app/carousel-gen-debug/`](backend/storage/app/carousel-gen-debug/) dump forensics on draft 43 (Apr 29) + draft 13 (May 2). **(1) Parser bug** — [`LinkedInGenerationService::parseOrchestratorOutput`](backend/app/Services/LinkedInGenerationService.php) silently swallowed entire JSON when Sonnet emitted preamble narration ("All facts verified… let me assemble the JSON…") + ```json fenced JSON. The regex `preg_replace('/\s*```.*$/s', '')` matched the LEFTMOST `\s*```` (the OPENING ```json fence) and `.*$` greedily consumed everything to EOF, leaving only preamble. `strpos('{')` then returned false → parser null → forensic dump was written. **Fix**: drop the fence-strip regex entirely; the balanced-brace scanner already tolerates leading preamble (no `{` chars in narration) and trailing fences/narration (stops at matched depth=0 `}`). New regression tests `test_parses_sonnet_preamble_with_fenced_json` + `test_parses_pure_fenced_json_no_preamble`. **(2) Legacy fallback REMOVED** — `applyCarouselGenAdapter` now throws `CarouselGenAdapterException` when `format='carousel'` but `status !== 'route_to_carousel_gen'`. Pre-v0.5.0 envelopes with inline `status='complete' + format='carousel'` slides are explicitly rejected (test `test_rejects_legacy_complete_carousel_envelope` asserts `dispatchCarouselGenEngine` is NOT called for legacy envelopes — they go straight to FSM Failed). [`persistAndRoute`](backend/app/Services/LinkedInGenerationService.php) gains a strict guard: refuses to persist a carousel draft when slides[] is empty (defends against future caller bugs that bypass the adapter). **Carousel contract post-May 2**: `/carousel-gen` is the ONLY carousel image generation path. There is NO fallback. Files changed (3): `LinkedInGenerationService.php` + 2 test files. 22/22 LinkedIn tests pass. **Open issue (NOT fixed by this commit)** — Sonnet output truncation: draft 13 dump (May 2, 6KB) showed Sonnet emitted "Completing slide 8 and slide 9… Paste this after `\"it's the best`" continuation prose instead of valid JSON. Likely cause: 9 bilingual slides exceeding model output token cap during /carousel-gen pipeline mode. The new strict envelope handler correctly routes this to FSM Failed (not silent fallback). Mitigations to evaluate as separate work: reduce default `target_slides` 9 → 7 in `inferTargetSlides` (cheapest), tighten plugin-level `image_prompt` length invariant ~500-700 chars → ~350-450 chars, or switch `CAROUSEL_GEN_MODEL` sonnet → opus (4-5x cost, higher output cap). **Earlier (April 29, 2026 session 6):** **MCP server leak fix in pipeline runs.** Production incident: 26 stuck LinkedIn carousel drafts traced to **140 leaked obsidian-mcp processes consuming 8.7GB RSS** on VPS (memory pressure → carousel-gen hung past 880s SSH timeout). Root cause: every `claude -p "..."` invocation from `LinkedInGenerationService` and `ArticleGenerationService` is a fresh one-shot CLI process that boots claude from scratch including ALL configured MCP servers from user's `~/.claude.json`. `obsidian-mcp` (and likely others) leak their child node process when the parent claude exits — accumulating ~60MB RSS each over 4 days of pipeline runs. Fix: every pipeline `claude` call now passes `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config` — empty MCP config = zero servers loaded, no leak vector. Pipeline runs don't need MCP servers anyway (all context flows through `--append-system-prompt-file` compiled refs + the prompt itself). Implementation: new `buildMcpFlags()` helper on both [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) and [`ArticleGenerationService`](backend/app/Services/ArticleGenerationService.php), injected into all SSH/local exec wrappers (linkedin-gen + carousel-gen + 5 article phases = 4 invocation paths per service). Two new env-overridable config keys: `linkedin.generation.empty_mcp_config` (env `LINKEDIN_GEN_EMPTY_MCP_CONFIG`) + `services.article_generation.empty_mcp_config` (env `ARTICLE_GEN_EMPTY_MCP_CONFIG`), both default `/home/claudesn/empty-mcp.json`. **One-time VPS setup**: `echo '{"mcpServers": {}}' > /home/claudesn/empty-mcp.json` (already done during incident response). New "Empty MCP Config (Required for Pipeline Runs)" subsection in this CLAUDE.md under VPS Background Process Setup so future contributors find it. Tests: 13/13 LinkedIn tests pass. PHP syntax clean across all 4 modified files. Cleanup script: `pkill -9 -u claudesn -f "node.*obsidian-mcp"`. **Earlier same day (session 5):** **LinkedIn queue UX + virality gate + background-process infra (4 issues from operator review).** Fixes 4 concerns: (1) **Depth score 100/100 always** — Depth Score is the `/linkedin-validate` 0-100 rubric scoped to format=text; for carousel post-plugin-v0.5.0 the carousel branch is `z.unknown()` and validation never runs, so legacy v0.4.x stub-100 values were misleading. Frontend hides depth_score for carousel rows in [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) (hero chip + Details panel both gated by new `showDepthScore` computed), [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue) Depth column, and [`LinkedInPostsList.vue`](frontend/src/views/admin/LinkedInPostsList.vue) footer chip. Title attr explains "Depth score does not apply to carousels" so operator understands the dash. (2) **Approve enabled while slides un-rendered** — synthetic status `carousel_render_pending` (label "AWAITING RENDER") is cosmetic; FSM was still `manual_review` so the green Approve button + Schedule for later + Publish now were all enabled. Clicking would start the cancel_window timer immediately; if GeminiGen rendering didn't finish in 15 min, `linkedin:process-scheduled` cron fires `publishCarousel` which fails the per-slide validation gate ("validate every slide has image_status=done"). New `slidesReadyForPublish` + `slidesPendingMessage` computeds in [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) gate **all 4 publish actions** (Approve, Schedule for later, Publish now, inline scheduler submit) — disabled with explanatory tooltip + visible cyan banner above the action cluster ("Approval gated · Wait for all slides to finish rendering (X of Y done)"). Quick-approve button in [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue) gets the same gate via new `carouselReadyForApprove(draft)` helper. Misleading sentence "Click Approve to start GeminiGen rendering" in [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js) `STATUS_META.carousel_render_pending` rewritten — Approve doesn't trigger rendering (auto-dispatched in `persistAndRoute`), it unlocks once renders finish. (3) **Virality filter at scan time + auto-purge** — [`ScanBlogForLinkedInConversion`](backend/app/Console/Commands/ScanBlogForLinkedInConversion.php) gains `whereHas('contentIdea')` filter requiring `virality_score >= linkedin_virality_min_score` (default 60). Manual blog posts (no ContentIdea linkage) are explicitly skipped — operator can hand-create LinkedIn drafts for those. New CLI flag `--min-virality=N` overrides. Output line per candidate now prints `v=X` chip. New artisan command [`PurgeLowViralityLinkedInDrafts`](backend/app/Console/Commands/PurgeLowViralityLinkedInDrafts.php) (`linkedin:purge-low-virality`) — daily 04:00 WIB, soft-deletes drafts whose source idea decayed below `linkedin_virality_purge_below` (default 50). Three safety rails: only touches non-terminal states (skips published / cancelled / awaiting_publish), idempotent, requires linked ContentIdea. CLI flags: `--threshold=N`, `--dry-run`. Both thresholds added to `linkedin` settings group via [`LinkedInSettingsSeeder`](backend/database/seeders/LinkedInSettingsSeeder.php) (now 9 linkedin keys, idempotent firstOrCreate). (4) **26 stuck in-progress, no cron processing** — root cause: `deploy.sh` only signals workers (`queue:restart`), never STARTS them. With `QUEUE_CONNECTION=database`, every dispatched job sat in `jobs` MySQL table forever. Same for scheduler — `routes/console.php` schedules require host crontab to fire `php artisan schedule:run` per minute. Both pieces of infra were undocumented. Three artifacts ship: (a) [`scripts/systemd/portfolio-queue.service`](scripts/systemd/portfolio-queue.service) — systemd unit running `queue:work --queue=default --sleep=3 --tries=3 --max-time=3600 --backoff=60,300,900`, `Restart=always`, `User=claudesn`, journald-piped logs; (b) [`scripts/systemd/portfolio-scheduler.crontab`](scripts/systemd/portfolio-scheduler.crontab) — single-line cron entry (`* * * * * cd /var/www/Portfolio_v2/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1`); (c) [`scripts/systemd/README.md`](scripts/systemd/README.md) — install steps + verification commands + troubleshooting recipe (the exact one-time setup the operator needs to run on VPS). New "VPS Background Process Setup (Required for Pipelines)" section in this CLAUDE.md right after Deployment so future contributors find it. Plus a new artisan command [`ReapStuckLinkedInCarouselImages`](backend/app/Console/Commands/ReapStuckLinkedInCarouselImages.php) (`linkedin:reap-stuck-carousel-images`) — every 5 min cron, one level deeper than `linkedin:reap-stuck`. Detects per-slide `image_status='pending'` >30m or `'generating'` >15m using `updated_at` as age proxy, re-dispatches `GenerateLinkedInCarouselImages` (idempotent — service skips done slides). Doesn't auto-fail; surfaces persistent failures via existing safety-rewrite + last_error in `LinkedInCarouselImageService::handleWebhook`. Two new schedule entries in [`routes/console.php`](backend/routes/console.php): `linkedin:reap-stuck-carousel-images` every 5 min, `linkedin:purge-low-virality` daily 04:00 Asia/Jakarta. **Files changed (10):** 3 frontend Vue + 1 frontend helper, 2 new backend artisan commands, 1 schedule, 1 seeder, 1 scan command updated, 3 ops files (systemd unit + crontab snippet + README). No migrations needed (uses existing `settings` table). No tests affected (safety-railed cron commands log but don't mutate without --dry-run). **Earlier same day (session 4):** **LinkedIn carousel publish unblocked via multi-image MVP path.** Operator hit the deferred-publish wall on draft 34 ("Carousel publish deferred — PDF composition (TCPDF) + document upload pending"). Shipped the MVP carousel publish path: LinkedIn `ugcPosts` with `shareMediaCategory=IMAGE` + `media[]` array of one asset URN per slide — visually a swipeable gallery in feed, ~70-80% of true PDF document carousel engagement, but ships TODAY without TCPDF dependency. New migration [`2026_04_29_000002_add_slide_asset_urns_to_linkedin_posts`](backend/database/migrations/2026_04_29_000002_add_slide_asset_urns_to_linkedin_posts.php) adds nullable JSON column `slide_asset_urns` keyed by string slide index — partial uploads survive across publish retries (7-of-9 succeeds before network blip → next retry skips persisted URNs and resumes from slide 8). [`LinkedInPost`](backend/app/Models/LinkedInPost.php) gets the column in `$fillable` + `$casts['array']`. [`LinkedInPublishService::publishCarousel`](backend/app/Services/LinkedInPublishService.php) replaces the 503 stub with the real flow: (1) validate every slide has `image_status=done` + non-empty `image_url` (else descriptive error pointing to which slide failed), (2) cap at 9 slides (LinkedIn ugcPost media[] hard limit — carousels >9 fail with clear instruction to reduce or wait for PDF support), (3) loop slides, idempotent upload via existing public `registerAndUploadImage()` helper (already proven on text-format thumbnail flow) — skips slides whose URN is already persisted, persists each new URN incrementally before continuing, persists partial state on upload failure before bailing, (4) compose ugcPost payload with `shareMediaCategory=IMAGE` + media[] in slide_number order (cover first), (5) POST `/v2/ugcPosts` with same headers/timeout pattern as publishText (45s timeout vs 30s — multiple uploads), (6) extract URN from `X-RestLi-Id` header, (7) auto-schedule `PostLinkedInFirstComment` job when `link_comment` present (same +30s delay pattern). New private helper `carouselFailure()` returns the uniform 4-key failure array + optionally persists `last_error` on upload failures (operator sees actionable message in admin UI). PDF document path (TCPDF + `shareMediaCategory=DOCUMENT` + `linkedin_asset_urn` column) remains future work — the multi-image path uses `slide_asset_urns` (plural, JSON, per-slide) which is intentionally distinct from `linkedin_asset_urn` (singular, scalar, reserved for the eventual document asset). Tests: 13 pass (8 unit `LinkedInGenerationServiceParseTest` + 5 feature `LinkedInGenerationServiceCarouselGenRouterTest`). PHP syntax clean. Earlier same day session 3 — **LinkedIn carousel caption builder rewrite (2026 best-practice spec).** Operator review of the auto-generated caption flagged it as "terlalu pendek dan kurang informatif" (~470 chars, hook + pull-quote + Swipe CTA only). Cross-referenced 2026 LinkedIn engagement data: carousel sweet spot is **800–1500 chars** with story-driven structure (carousel format hits 6.60% engagement vs 2% for text — longest-dwell format on the platform), **dwell time 61s+ correlates with 15.6% engagement vs 1.2% for sub-3s posts**, **first 210 chars** is the preview cutoff (60–70% of readers drop at "See more"), **3–5 hashtags** is optimal mix (1–2 industry + 1–2 niche + 0–1 branded), **question-based CTAs** drive comments (the algorithm's strongest engagement signal). [`LinkedInGenerationService::buildCarouselCaption`](backend/app/Services/LinkedInGenerationService.php) refactored to produce a 7-block caption: (1) **Hook** from `cover.copy_id` (within first 210 chars — preview window), (2) **Punchline subtitle** from `cover.copy_en` (when distinct), (3) **Setup paragraph** ≤280 chars from blog excerpt or first non-cover/non-CTA slide via new helper [`extractSetupParagraph`](backend/app/Services/LinkedInGenerationService.php), (4) **Pull-quote / data point** from `brief.pull_quote` (when distinct from setup), (5) **3 insight bullets** (`→` prefix) from sharpest body slides via new helper [`extractInsightsFromSlides`](backend/app/Services/LinkedInGenerationService.php) — prefers `headline_id` over `copy_id`, caps each at 110 chars (mobile single-line), splits at first sentence terminator, (6) **Engagement question** from `brief.engagement_question` (fallback "Apa yang sebenarnya terjadi di balik layar?") + Swipe CTA, (7) **"Full article: link in comments ↓"**. Plugin caption trust threshold raised from ≥200 chars to ≥800 chars (legacy v0.4.x rich captions still pass through; thin/empty captions trigger backend rebuild). Final length capped at 1900 chars (engagement sweet spot). Builder signature widened to `($pluginCaption, $carousel, $brief, $draft)` — `$carousel` gives access to all slides for body insight extraction, `$draft` reaches into `post.translations` for blog excerpt. Hashtag pipeline (`resolveHashtags` → `synthesizeHashtagsFromBlog`) unchanged — already produces 3–5 hashtags from plugin/brief/blog `meta_keywords` with brand-default padding. Tests: 13 pass (8 unit `LinkedInGenerationServiceParseTest` + 5 feature `LinkedInGenerationServiceCarouselGenRouterTest`). PHP syntax clean. Sources: [Best LinkedIn Post Length 2026](https://connectsafely.ai/articles/ideal-linkedin-post-length-engagement-guide-2026), [LinkedIn Hashtags 2026](https://connectsafely.ai/articles/linkedin-hashtags), [LinkedIn Algorithm 2026](https://meet-lea.com/en/blog/linkedin-algorithm-explained), [LinkedIn Carousel 2026](https://expandi.io/blog/linkedin-carousel/). Earlier same day session 2 — **LinkedIn admin UI redesign — operator clarity + visual polish.** Operator reported the existing screens were "membingungkan sekali" (very confusing) — status pill said GENERATING while a stale red "LAST ERROR" banner persisted from a previous attempt; thumbnail caption fired during generation; back button always landed on Manual Review regardless of the tab the operator came from; action panel was empty during in-progress states. Full redesign across three views — [LinkedInQueueList.vue](frontend/src/views/admin/LinkedInQueueList.vue), [LinkedInPostsList.vue](frontend/src/views/admin/LinkedInPostsList.vue), [LinkedInDraftDetail.vue](frontend/src/views/admin/LinkedInDraftDetail.vue) — backed by a new shared helper module [linkedinHelpers.js](frontend/src/views/admin/linkedinHelpers.js) (status metadata + mood-class fragments + transition humanizer + reason humanizer + format/time utilities + 18 inline SVG icon paths). Concrete improvements: (1) **Status hero panel** replaces the small pill + scattered metadata at top of detail page — large color-coded chip with mood gradient rail, one-sentence operator copy ("The /linkedin-gen plugin is running. Typical runs finish in 30-90 seconds."), live countdown for awaiting_publish that ticks every second, primary CTA(s) sized to the current status (Approve for manual_review, Publish now for awaiting_publish, Open on LinkedIn for published, Regenerate for failed/cancelled, Cancel run for in-progress), animated shimmer bar at bottom while in_progress. (2) **Stale-error suppression** — `last_error` now only renders when current status ∈ {failed, manual_review}; previously it persisted as a separate red banner even after a successful retry, contradicting the active status pill. (3) **Thumbnail caption gating** — "Will upload to LinkedIn on publish" caption only fires when format=text AND post.featured_image AND no thumbnail_asset_urn AND status ∈ {manual_review, awaiting_publish}; previously it fired during generation/validation creating mixed messaging. (4) **Smart back navigation** — list views (`Queue`, `Posts`) write `linkedin:detail:origin` to sessionStorage on row click, detail page reads it onMount and routes its back button to the correct list; tab state persisted via separate `QUEUE_TAB_KEY` + `FEED_TAB_KEY` so operator returns to the same tab they came from. (5) **Humanized timeline** — pipeline_state_log entries no longer rendered as raw enum strings (`pending_generation → generating`); each transition resolves to a human sentence via `transitionSummary()` ("Detected in blog scan", "Validation gate passed", "Auto-publish disabled — held for review", etc.) with status-mood-colored dots on a vertical rail. (6) **Anti-AI-slop emoji removal** — every ✓/✕/↻/✎/🚀/🖼/↗ replaced with inline 24x24 stroke SVG icons from a 18-glyph set in `ICON` (arrowLeft, refresh, check, x, pencil, send, image, externalLink, alertCircle, alertTriangle, clock, inbox, loader, search, chevronLeft, chevronRight, linkedin, sparkle); all SVGs use `stroke="currentColor"` strokeWidth=1.5/2.0 for consistency. (7) **Visual taste pass** — segmented-control tab rail (vs default browser pills), divide-y table rows (vs heavy card boxes), mood-driven gradient rails, `min-h` skeleton loaders matching final layout, polished empty states with friendly icons + actionable copy ("Inbox zero" / "Quiet on the wire" / "Nothing broken"), micro-interactions (active:scale-[0.98] for buttons, group-hover translate-x for back arrow, hover-lift -translate-y-[1px] for cards), depth score color tiers (emerald ≥80, amber 70-79, red <70). (8) **Live ticker** — countdown-to-publish updates every second via a `setInterval` ref instead of static formatted string (cleaned up in onBeforeUnmount). Vue 3 + Tailwind 4 utility-first; no new dependencies. Vite production build clean (~20s, LinkedInDraftDetail bundle 34.67 kB / gzip 8.79 kB). Earlier same day — **LinkedIn pipeline UX + self-heal fixes (4 issues from operator review):** (1) **Queue tab classification** — [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue) now fetches the full queue (`scope=queue`, no server-side status filter) and reclassifies client-side. Carousel drafts at `status='manual_review'` with no rendered slides no longer pollute the Manual Review tab — `carouselImageState()` inspects each slide's `image_status[]` and routes drafts with all-`failed`/null slides to **Failed**, slides with any `pending`/`generating` to **In Progress**, and only truly-reviewable drafts (≥1 done slide) stay in **Manual Review**. Issue column now explains the bucket choice ("Rendering 9 of 9 slides…", "All 9 slides rejected — see detail", "5 done · 4 failed (review and retry)"). (2) **Approve self-heal for missing images** — [`LinkedInDraftController::approve`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L205) now auto-dispatches `GenerateLinkedInCarouselImages::dispatch($draft->id)` when format=carousel and any slide is non-`done`. Closes the gap where a draft whose original Scenario-C dispatch silently failed (logged but swallowed in `persistAndRoute` try/catch) would queue a publish that produced nothing. Job is idempotent — already-`done` slides are skipped. Wrapped in try/catch so dispatch failure never blocks the FSM transition. (3) **Manual retry safety-rewrite hook** — [`LinkedInCarouselImageService::applySafetyRewriteIfNeeded`](backend/app/Services/LinkedInCarouselImageService.php) is a new public method that reads a slide's existing `image_error`, runs the safety detector + Sonnet rewrite if matched, returns true when the prompt was sanitized. [`LinkedInDraftController::regenerateSlideImage`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L412) (the per-slide "Retry this slide" button) now calls this BEFORE `dispatchSingleSlide` so manual retries on safety-class failures actually have a chance to succeed instead of redispatching the same rejected prompt. Refactored existing `maybeAutoRetryOnSafety` to share the rewrite-only helper `rewriteSlidePromptIfSafetyError` (DRY — auto-path and manual-path both go through the same idempotent core, sentinel `image_prompt_pre_safety` shared). (4) **TEXT post 16:9 thumbnail end-to-end** — text-format LinkedIn posts no longer publish as a wall of text. New nullable column `linkedin_posts.thumbnail_asset_urn` (migration `2026_04_29_000001`). [`LinkedInPublishService::publishText`](backend/app/Services/LinkedInPublishService.php) now resolves a thumbnail URN before building the payload — uploads `posts.featured_image` (already 16:9, CDN-served, operator-approved) to LinkedIn DigitalMedia via new public `registerAndUploadImage()` helper (3-step: `POST /v2/assets?action=registerUpload` with `feedshare-image` recipe → fetch source bytes → PUT to upload URL). Persists `thumbnail_asset_urn` for idempotent retry (no double-upload on publish-now retries). When a URN exists, payload uses `shareMediaCategory=IMAGE` + `media[]` array with the asset; falls back to `shareMediaCategory=NONE` (text-only) if upload fails — failure never blocks publish. Frontend mockup at [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue#L341) now renders the thumbnail above the body in TEXT-format previews; amber footer caption "Will upload to LinkedIn on publish (no asset URN yet)" disappears once the URN is persisted. (5) **Cosmetic** — duplicated bilingual ID/EN copy block below the carousel slide viewer was removed (the same headline + subtitle is already baked into the rendered slide image). In-frame fallback text for `pending`/`generating` states preserved — those are the only text the operator sees pre-render. **Impact:** existing manual_review drafts with no images will reclassify automatically on next admin nav; clicking Approve on those drafts will trigger image generation; clicking "Retry this slide" on safety-failed slides now applies the sanitization first; future text-format publishes will include thumbnails. **Files changed (7):** 1 migration, 1 model, 2 services, 1 controller, 2 Vue views. **CI/CD migration:** `php artisan migrate --force` runs in deploy.sh step 3 — kolom `thumbnail_asset_urn` ditambah otomatis. Earlier (April 28, session 4) — **LinkedIn Carousel Engine Decoupling Phase B + C SHIPPED — `/linkedin-carousel` skill DELETED (v0.5.0 BREAKING):** plugin [linkedin-post-writer](https://github.com/alisadikinma/linkedin-post-writer) bumped to v0.5.0. `/linkedin-carousel` skill removed (`skills/linkedin-carousel/` deleted), `/linkedin-gen` orchestrator refactored to short-circuit on carousel format with `status=route_to_carousel_gen` envelope (carousel/post/validation all null, backend handles from there), `compile-refs.ts` reduced from 4 → 3 bundles (carousel bundle retired), legacy `06-carousel-design.md` + `07-carousel-image-standards.md` raw RAG files removed (equivalent specs live in `/carousel-gen` plugin's references). Plugin tests: 221 pass. Plugin schema imports updated — `linkedin-validate/schema.ts` carousel branch is now `z.unknown()` (the universal `/carousel-gen` engine in `ai-image-carousel-prompt-gen` plugin owns its own schema). Backend: feature flag `linkedin.use_carousel_gen_engine` and env `LINKEDIN_USE_CAROUSEL_GEN_ENGINE` REMOVED — `LinkedInGenerationService::applyCarouselGenAdapter` always dispatches for carousel format, detects new `route_to_carousel_gen` status and promotes to `complete` after slides materialize. Backward compat: legacy envelopes with `status=complete` + inline carousel slides still work (slides replaced by adapter output). `LINKEDIN_GEN_REFS_CAROUSEL` env + `linkedin.generation.refs_carousel` config retired (3 reference bundles only). Backend tests: 16 pass for v0.5.0 wiring (router test rewritten — flag-off pass-through test removed since flag is gone, route-to-carousel-gen + status promotion test added, legacy-complete-envelope backward-compat test added). VPS deploy pending operator: `git pull` plugin to v0.5.0, `npm run compile-refs` (now produces 3 files), remove `/home/claudesn/refs-linkedin-carousel.md` symlink, drop `LINKEDIN_USE_CAROUSEL_GEN_ENGINE` + `LINKEDIN_GEN_REFS_CAROUSEL` from Laravel `.env`, `php artisan config:cache && queue:restart`. Also defense-in-depth carousel fixes (commit `30d200bf`): safety auto-retry in [`LinkedInCarouselImageService::handleWebhook`](backend/app/Services/LinkedInCarouselImageService.php) (detects GeminiGen safety refusal, calls `ArticleGenerationService::rewriteVisualDirectionForSafety`, redispatches with sanitized prompt — idempotent via `image_prompt_pre_safety` sentinel; demotes `human_fingerprint` layout to `body` since it's the most common safety trigger), bilingual font hierarchy chrome rules in [`CarouselSlideEnhancer::appendBrandChrome`](backend/app/Services/CarouselSlideEnhancer.php) (Indonesian dominant uppercase white bold ~95px with amber #F5A623 accent words 2-4, English subtitle white sentence-case regular ~38px not italic not amber, ~2.5x size ratio — derived from canonical reference covers), admin UI cleanup in [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) (removed HUMAN_FINGERPRINT/BODY/CTA layout_hint section labels, fixed text styles to match reference). **Earlier same day (sessions 2-3):** **LinkedIn Carousel Engine Decoupling Phase A SHIPPED end-to-end (forward-compat, feature-flagged OFF):** strategic decoupling so carousel image authoring lives in ONE plugin (`ai-image-carousel-prompt-gen` v2.16.0+) reusable across LinkedIn + IG + TikTok, while LinkedIn-specific concerns (PDF composition, link-in-comment, Depth Score) stay in `linkedin-post-writer`. Architecture choice **Opsi D — universal carousel engine + publisher-side platform concerns** after multi-platform research showed LinkedIn + IG Feed are 90% spec-identical (same 4:5 aspect ratio, same 5-10 slide range, same hook-first rule); platform-exclusive bits are publisher-side. Phase A: 7 sub-phases shipped across 2 repos in ~11 commits. Plugin layer (commits `81c175f → a799686`): added `/carousel-gen` pipeline mode (auto-detected via `--blog-source`/`--pipeline`/`--non-interactive` flags or no-TTY), Zod [`schema.ts`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/skills/carousel-gen/schema.ts) discriminated union (Complete vs Failed envelopes) with bilingual + 5-act narrative slide types + 4 superRefine invariants (bilingual XOR single-language, direct_answer_block layout coupling, etc.), [`non-interactive-defaults.md`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/references/non-interactive-defaults.md) deterministic resolution rules replacing every interactive question, [`scripts/compile-refs.ts`](https://github.com/alisadikinma/ai-image-carousel-prompt-gen/blob/main/scripts/compile-refs.ts) bundling 7 reference files into 169KB `refs-carousel-gen-pipeline.md` (gitignored — deployed separately to VPS). Backend layer (commits `491a1a01 → 90cd9b73`): new [`config/carousel-gen.php`](backend/config/carousel-gen.php) (mirrors `LINKEDIN_GEN_*` SSH config: driver/ssh_host/user/key/claude_path/model/refs_pipeline/timeout_seconds, default 600, production .env=900 to match LINKEDIN_GEN tuning), new [`CarouselGenOutputAdapter`](backend/app/Services/CarouselGenOutputAdapter.php) (single `adapt(array): array` method maps plugin JSON → `linkedin_posts.carousel_slides` shape, throws `CarouselGenAdapterException` on `status=failed` or empty `slides[]`, defense-in-depth forces `direct_answer_block=null` on non-`direct_answer` layouts), [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) gains `applyCarouselGenAdapter()` + `dispatchCarouselGenEngine()` methods + constructor-injected adapter — when feature flag enabled AND format=carousel, runs `/carousel-gen` SSH dispatch BEFORE FSM advance, replaces `parsed.carousel.slides` with adapter output. Adapter exceptions → `markFailed($draft, ...)` route to FSM Failed. Feature flag [`config/linkedin.php`](backend/config/linkedin.php) gains `use_carousel_gen_engine` key (env: `LINKEDIN_USE_CAROUSEL_GEN_ENGINE`, default false). Tests: [`LinkedInGenerationServiceCarouselGenRouterTest`](backend/tests/Feature/LinkedInGenerationServiceCarouselGenRouterTest.php) 5 feature tests (flag-off pass-through, text-format pass-through, carousel-format slide replacement, status=failed exception, dispatch-null exception); 25 carousel-gen related tests passing with 318 assertions; PHPUnit not Pest (project convention). VPS deploy (A7 manual operator step executed): `git pull` plugin cache `2.16.0/` to commit `a799686`, `npm install + npm run compile-refs` produces 169,621-byte bundle, symlinked at `/home/claudesn/refs-carousel-gen-pipeline.md` (mirrors `refs-linkedin-carousel.md` symlink pattern). Laravel `.env` appended with 9 new vars (`CAROUSEL_GEN_*` + `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false`), `php artisan config:cache && queue:restart`. Backup at `.env.backup-2026-04-28-A7`. Acceptance verified all green: `claude -p "/carousel-gen --help"` documents pipeline-mode flags, `config('carousel-gen.refs_pipeline')` resolves to deployed file, `config('linkedin.use_carousel_gen_engine') = false` — Phase A safety preserved, ZERO production impact. Local Laravel `.env` mirrored with `CAROUSEL_GEN_DRIVER=local` + Windows path to compiled refs. Phase B (cutover) and Phase C (cleanup) deferred to separate sessions per plan time-gate (B3 = 7-day monitoring window). Master plan: [docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md](docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md), A7 operator guide: [docs/plans/2026-04-28-A7-vps-deploy-guide.md](docs/plans/2026-04-28-A7-vps-deploy-guide.md). **Earlier same day (session 1):** **Safety-aware prompt rewrite on GeminiGen refusal:** when GeminiGen returns a policy refusal like `PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD` (caused by named public figures, minors, brand logos, or unsafe content in the prompt), the segment used to retry the same prompt 3 times then hit terminal failure with operator stuck. New flow: [`ImageGenerationService::isSafetyError()`](backend/app/Services/ImageGenerationService.php) detects the refusal class (substring match against `PUBLIC_ERROR_*`, `prominent people`, `minors`, `unsafe content`, `sexual content`, `safety filter`, `content policy`); when matched, [`handleSegmentFailure`](backend/app/Services/ImageGenerationService.php) calls [`ArticleGenerationService::rewriteVisualDirectionForSafety`](backend/app/Services/ArticleGenerationService.php) — text-only sync Sonnet call (~10-15s) that strips proper nouns (persons/brands/landmarks) and substitutes generic descriptors that preserve scene/lighting/mood. Mutates `image_prompts[i].visual_direction` + `prompt_text` + `prompt`, preserves the rejected version under `visual_direction_pre_safety`, drops `face_refs` + `entity_refs` (the most common safety trigger is a public-figure entity_ref). Existing `RetryImageSegmentJob` then picks up the sanitized prompt automatically — no changes to retrySegment(). Failure history entries tagged `safety_detected: true` + `rewritten_for_safety: true`. Gated by `ARTICLE_GEN_USE_SAFETY_REWRITE` (default `true` — this is a fix, on by default). Graceful fallback: if rewriter fails, retry still dispatches with original prompt and next attempt re-detects + retries the rewrite. New tests: `SegmentFailureSafetyRewriteTest` (5 cases). Also fixed pre-existing SQLite incompatibility in `2026_04_27_000001_add_linkedin_carousel_fields_to_image_generation_jobs` migration — wrapped raw `ALTER TABLE MODIFY COLUMN` in MySQL driver check so feature tests can run on SQLite (no functional change for production MySQL). **Earlier (April 27, 2026):** **LinkedIn carousel image rendering SHIPPED end-to-end:** closes the gap where carousel drafts persisted slide JSON (copy + plugin-authored image_prompt) but never actually rendered the slide PNGs. Three layers shipped together: (1) **Plugin layer** — new spec doc [`07-carousel-image-standards.md`](https://github.com/alisadikinma/linkedin-post-writer/blob/main/docs/rag/linkedin-playbook/07-carousel-image-standards.md) compiled into `refs-linkedin-carousel.md`, mirrors `ai-image-carousel-prompt-gen` standards (bilingual headline ID main `#FFFFFF` + EN subtitle `#F5A623`, accent keywords 2-4 in `#F5A623`, brand chrome on every slide, `SWIPE (GESER) >` indicator, page number top-left, brand icon + @alisadikinma watermark center thirty percent opacity, social block on CTA, visual hook on cover, WOW 8-element gate, hyperrealistic anti-AI-look, mobile dead zones, 5-paragraph prompt structure). `linkedin-carousel/SKILL.md` Step 4 rewritten to enforce these. (2) **Backend layer** — new [`CarouselSlideEnhancer`](backend/app/Services/CarouselSlideEnhancer.php) (placeholder replacement + face/logo URL resolution + chrome instruction append, idempotent), [`LinkedInCarouselImageService`](backend/app/Services/LinkedInCarouselImageService.php) (orchestrator: enhance → GeminiGen multipart → ImageGenerationJob row + slide status mirror), [`GenerateLinkedInCarouselImages`](backend/app/Jobs/GenerateLinkedInCarouselImages.php) queued job (auto-dispatched from `LinkedInGenerationService::persistAndRoute` for carousel format, regardless of AwaitingPublish/ManualReview), [`LinkedInCarouselImageWebhookController`](backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php) public callback at `POST /automation/linkedin/carousel-image-webhook`. New migration widens `image_generation_jobs.type` ENUM with `carousel_slide`, adds `linkedin_post_id` (FK cascade) + `slide_index` + `slide_image_role` columns + 2 indexes. Filename pattern `{brand_slug}-li-{draft_id}-slide-{NN}-{role}.png` (e.g., `alisadikinma-li-28-slide-01-cover.png`), stored at `storage/app/public/linkedin-carousel/`. (3) **Frontend layer** — [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) extends polling to fire every 5s when any slide has `image_status='generating'/'pending'`; new mutations `useRegenerateAllCarouselImages` + `useRegenerateSlideImage`. [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) main slide viewer gains 4-state visual (rendered image / animated spinner with copy preview / red error block + "Retry this slide" / pending placeholder), status pill overlay top-right, image generation summary line (✓ N · ↻ N generating · ✕ N failed / total), thumbnail strip shows actual rendered images + per-slide status dot, action panel gains "🖼 Regenerate all images" button for carousels. 3 new admin/automation routes: `POST /admin/linkedin-drafts/{id}/regenerate-images`, `POST /admin/linkedin-drafts/{id}/slides/{slideIndex}/regenerate-image`, `POST /automation/linkedin/carousel-image-webhook`. Carousel PDF composition + LinkedIn DocumentShare upload still deferred (`publishCarousel` still 503) — slide PNGs ready for manual download or downstream PDF assembly. **Earlier (April 23, session 3):** **LinkedIn plugin integration SHIPPED end-to-end:** plugin [linkedin-post-writer](https://github.com/alisadikinma/linkedin-post-writer) v0.2.0+ now wired to Portfolio backend. New [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) SSH-invokes `claude -p "/linkedin-gen"` with 4 compiled refs, parses stdout JSON (balanced-brace scanner, 8 unit tests), advances FSM PendingGeneration → Generating → Validating → AwaitingPublish|ManualReview based on plugin's `validation.passed`. New [`GenerateLinkedInPost`](backend/app/Jobs/GenerateLinkedInPost.php) queued job (2 retries, 360s timeout). [`LinkedInPublishService::publishText`](backend/app/Services/LinkedInPublishService.php) now real — `POST /v2/ugcPosts` with `ShareContent` payload, extracts URN from `X-RestLi-Id` header, auto-schedules delayed [`PostLinkedInFirstComment`](backend/app/Jobs/PostLinkedInFirstComment.php) (`POST /v2/socialActions/{urn}/comments`) to avoid 60% body-link reach penalty. New cron commands: [`linkedin:process-scheduled`](backend/app/Console/Commands/ProcessScheduledLinkedInPosts.php) (every-minute, publishes awaiting_publish past cancel_window — kill switch OFF → demote to manual_review, ON + success → published, failure → Failed + last_error) + [`linkedin:scan-blog`](backend/app/Console/Commands/ScanBlogForLinkedInConversion.php) (daily 03:00 WIB, creates pending rows for blog posts without live drafts + dispatches job). [`LinkedInDraftController::regenerate`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) now dispatches the job (was stub). [`config/linkedin.php`](backend/config/linkedin.php) extended with `generation` section (SSH config + 4 refs paths + 300s timeout). Carousel publish still deferred until TCPDF PDF composition ships (carousel drafts route to manual_review). Earlier session 2: **LinkedIn Admin UI shipped** (full-stack): new `linkedin_accounts` + `linkedin_posts` tables (24 cols, 8-state FSM), [`LinkedInPostStatus`](backend/app/Enums/LinkedInPostStatus.php) enum, [`LinkedInAccount`](backend/app/Models/LinkedInAccount.php) + [`LinkedInPost`](backend/app/Models/LinkedInPost.php) models, [`LinkedInOAuthService`](backend/app/Services/LinkedInOAuthService.php) (authorize URL + token exchange + refresh + /v2/me profile fetch) + [`LinkedInPublishService`](backend/app/Services/LinkedInPublishService.php) (stubs until plugin content-gen wired), [`LinkedInOAuthController`](backend/app/Http/Controllers/Api/LinkedInOAuthController.php) (5 endpoints) + [`Admin\LinkedInDraftController`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) (7 endpoints). **FSM infrastructure refactored to be enum-class-generic**: [`HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) + [`PipelineGuard`](backend/app/Services/PipelineGuard.php) now accept any BackedEnum via `statusEnumClass()` abstract method on models; ContentIdea tests still pass. New `linkedin` settings group (7 keys) + extends `telegram` group with 3 linkedin notify toggles. Frontend: [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) TanStack Query composable + 3 views ([`LinkedInPostsList`](frontend/src/views/admin/LinkedInPostsList.vue) success feed + [`LinkedInQueueList`](frontend/src/views/admin/LinkedInQueueList.vue) triage + [`LinkedInDraftDetail`](frontend/src/views/admin/LinkedInDraftDetail.vue) detail with edit mode + LinkedIn-style card mockup + carousel slide viewer + state timeline). Sidebar gains "LinkedIn" section. AboutSettings gets "LinkedIn Integration — Direct OAuth" card (3-state UX: OAuth not configured / installed-not-connected / connected). Plugin pivoted from MixPost to direct LinkedIn OAuth after verification that MixPost OSS supports only Facebook + Twitter + Mastodon (not LinkedIn). Plugin owns content generation only; admin UI owns OAuth, publish, schedule, FSM, cancel window. Full design: [docs/plans/2026-04-23-linkedin-admin-ui.md](docs/plans/2026-04-23-linkedin-admin-ui.md). Earlier same day (session 1): Translation dual-storage mirror fix: automation-path `saveTranslation` now mirrors translated fields into `content_ideas.generated_article.{locale}` so the admin Finalize tab matches what the blog renders, WITH two safety rails preventing data corruption — (1) never writes the primary-language slot (authored raw content is sacrosanct), (2) strips `<figure>` blocks via new `App\Support\HtmlFigureStripper` since `post_translations` stores rendered content while `generated_article` stores raw body (Finalize re-injects figures at render time, so mirroring without the strip would cause double-rendering). New artisan backfill `content-engine:sync-translation-mirrors` for historical ideas applies the same three guardrails: skip primary slot, skip already-legit content, strip figures. Earlier (April 21): Page Sections wiring fixes: `<LatestBlog>` mounted on Home (was never rendered despite component existing); `<CTASection>` moved from Blog list → BlogDetail (admin "Blog Page → Call to Action" controls article detail, not index); `LatestBlog` side cards now render `featured_image` thumbnails (40/60 horizontal split) matching the large card; `usePageSections` cache staleTime 10min → 30s + `refetchOnMount:'always'` so admin toggles surface on public pages within next navigation. Added "Page Sections Mapping" reference table in this CLAUDE.md to prevent ghost-toggle confusion. Earlier same day: Pipeline State Machine foundation (`ContentIdeaStatus` enum + `HasStatusTransitions` trait + `PipelineGuard`, strict adjacency map with audit log), Segment Retry Pipeline (auto retry job + manual retry/skip endpoints + replace-variation), Translate-Before-Publish Gate (sync SSH preflight, 3 auto retries, Telegram exhaustion alert), meta_keywords body-lede synthesis with broad-topic anchor (web SEO best practice — short entity tokens), backend auto-resolve person entity via Wikidata at every cover dispatch (no manual Regen Prompt needed), Replace-variation hover icon on done thumbnails, `completed` status added to syncToContentIdea + findIdeaIdForJobUuid + regenerateImagePrompts whitelists, `content-engine:resync-stuck-variations` artisan backfill, plugin v2.7.2 with hard pre-save SEO field audit + lede + role-resolution. Earlier (April 18): Creator Brand system — auto-inject cover face + VD rewrite, prompt-injection watermark, branded filenames `alisadikinma-{keyword}-{segment}.png`, per-type image captions, `creator_brand` Settings group + AboutSettings card.
**Maintainer:** Ali Sadikin (ali.sadikincom85@gmail.com)
**Environment:** Windows 11, D:\Projects\Portfolio_v2
**PHP:** D:\xampp\php\php.exe (8.2.12) — use full path, not in system PATH
**Backend:** Laravel 12 + PHP 8.2 + MySQL 8 + Sanctum 4 + Filament 4.1
**Frontend:** Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + TanStack Query 5.90 + Tailwind 4
**Production:** https://alisadikinma.com
