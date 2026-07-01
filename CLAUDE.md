# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🧠 Vault Context Link

Pre-read MANDATORY via `obsidian` MCP `read-note`:
- `20-Projects/Portfolio_v2/README.md` — current state, decisions, blockers
- `10-Identity/ali.md` — voice & style (auto-loaded global, no re-read needed)
- `30-Knowledge/content-strategy-shared.md` — kalau task menyentuh blog/article

Persist decisions: append ke vault README section "Decision Log" via `obsidian` MCP `edit-note`. JANGAN minta user re-explain context yang sudah ada di vault.

## 🕸️ graphify (code-structure brain — HOW)

This repo has a knowledge graph at `graphify-out/` (god nodes, community structure, cross-file relationships). Two-brain split: **graphify = HOW (code structure)**, the Obsidian vault = WHY (intent/decisions).

**Rules:**
- **Graph for structure, grep for literals — complementary, not either/or.** Use `graphify query "<question>"` for "where/how is X / what calls Y / which files relate" — it returns a scoped subgraph, usually far smaller than `GRAPH_REPORT.md` or raw output (local graph default `graphify-out/graph.json`). Keep using grep/Read for literal strings AND for code edited since the last `graphify update` (the graph is AST-indexed, not live — fresh edits may be stale until re-indexed).
- Cross-project questions (spanning repos, or "don't know which project") → query the federated graph: `graphify query "<question>" --graph ~/.graphify/global-graph.json`.
- If `graphify-out/wiki/index.md` exists, use it for broad navigation before raw source browsing.
- Read `graphify-out/GRAPH_REPORT.md` only for broad architecture review when query/path/explain don't surface enough.
- **After modifying code, run `graphify update .`** to keep the local graph current (AST-only, no API cost). To refresh the global brain for this project: `graphify global add graphify-out/graph.json --as Portfolio_v2`.

**Two-brain bridge (loose-coupled by design):** graphify (HOW) and the vault (WHY) are NOT edge-linked — routing happens at agent/query time per `~/CLAUDE.md`. When recording a durable decision in the vault README "Decision Log", add a `file:line` (or symbol name) backlink to the implementing code so decision↔code is a 1-hop jump. Vault recall is sequential file-read (not semantic), so keep notes lean/high-signal (R1) — this stays fine while the vault is small; vector-RAG was deliberately dropped (YAGNI), re-evaluate only if the vault exceeds ~5000 files AND keyword/graph recall proves insufficient.

## Project Overview

Portfolio_v2 is a full-stack portfolio, blog, and CMS platform using Laravel 12 (backend API) and Vue 3 (frontend SPA). Development on macOS via Docker Desktop.

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
- **DO NOT** use `php artisan serve` — the `portfolio_backend` Docker container serves the API
- Project path: `/Users/alisadikin/Drive-D/Projects/Portfolio_v2`
- Backend + MySQL run in Docker Desktop (containers `portfolio_backend` + `portfolio_mysql`); frontend on Vite dev server. **No host php/composer** — run backend commands via `docker exec portfolio_backend php artisan ...`

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
├── LinkedInGenerationService.php # SSH-invokes `/linkedin-gen` CLI, parses stdout JSON, advances FSM, dispatches GenerateLinkedInCarouselImages on carousel format. Plugin v0.5.0+: orchestrator emits `status=route_to_carousel_gen` for carousel format (brief only); service ALWAYS dispatches `/carousel-gen` engine via `applyCarouselGenAdapter()` to assemble slides via `CarouselGenOutputAdapter`. **STRICT enforcement (May 2026)**: `/carousel-gen` is the ONLY carousel path — `applyCarouselGenAdapter` throws `CarouselGenAdapterException` when `format='carousel'` but status ≠ `route_to_carousel_gen` (legacy `complete`-with-inline-slides envelopes rejected, no fallback). `persistAndRoute` strict guard refuses to persist a carousel draft when slides[] is empty. **Skip-orchestrator (June 10, 2026)**: when `linkedin_force_carousel` is ON (the default), `generate()` SKIPS the ~10-13 min `/linkedin-gen` SSH call entirely (its output was ~90% discarded under force-carousel anyway — caption rebuilt backend-side, format always overridden). `buildForcedCarouselEnvelope($draft)` synthesizes the `route_to_carousel_gen` envelope (pillar from the linked ContentIdea) and `/carousel-gen` authors the storyline from the **full blog body embedded INLINE** in its prompt (`buildCarouselGenPrompt`, no live re-fetch — the `--blog-source` URL is supplementary OG metadata). Force OFF restores the plugin path (Steps 3→4.5). `invokePlugin`/`persistAndRoute` promoted private→protected as test seams. See [docs/plans/2026-06-09-skip-linkedin-gen-force-carousel.md](docs/plans/2026-06-09-skip-linkedin-gen-force-carousel.md).
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
├── useStageGalleries.js   # Award gallery photos for International Stages cards (parallel, 60min cache)
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
| `telegram_repurpose_enabled` | `false` | IG repurpose master toggle (June 11, 2026). When `'true'`, an Instagram post URL sent to the bot starts the repurpose pipeline ([RepurposeJob](backend/app/Models/RepurposeJob.php)). Off → IG URLs silently ignored. See the IG Repurpose section + [runbook](docs/runbooks/repurpose-ig-carousel-deploy.md). |
| `telegram_video_full_enabled` | `false` | `video_full` (Video 60s) master toggle (June 16, 2026). When `'true'`, the "🎥 Video 60s" button appears on the IG-repurpose mode prompt → tapping it parks the job at `queued_local` for the MacBook worker (full-regenerate ID reel, no face-swap). Off → button hidden. See the `video_full` Recent Changes entry + [runbook](docs/runbooks/video-full-deploy.md). |

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
| `linkedin_auto_approve_enabled` | `'false'` | **Auto-schedule kill-switch (May 7, 2026)**: when `'true'`, daily 04:30 WIB cron `linkedin:auto-schedule` promotes `manual_review` drafts to `awaiting_publish`, assigning `cancel_window_ends_at` to next free `posting_time_rules.score >= 85` slot (14-day lookahead). Drafts ordered virality_score DESC, created_at ASC. Loop guard skips drafts demoted by `kill_switch_demotion` reason within 24h. |
| `linkedin_force_carousel` | `'true'` | **Default-carousel (June 9, 2026)**: when `'true'`, `LinkedInGenerationService` ALWAYS routes carousel via `/carousel-gen` regardless of the `/linkedin-gen` text/carousel decision — `forceCarouselEnvelope()` rewrites a non-failed, non-carousel envelope to `route_to_carousel_gen` so Step 5.5 dispatches the engine. Supersedes the unreliable format-mix governor (plugin ignores `format_preference`). `/carousel-gen` failure → `manual_review` (no silent text downgrade). Guards never mask a plugin `failed` or an already-carousel route. Forcing carousel = IG/TikTok/Threads/FB siblings always created (cross-post fan-out only makes those for carousel format). **June 10, 2026**: under force-ON, `generate()` now SKIPS `/linkedin-gen` entirely (not just overrides its output) — `buildForcedCarouselEnvelope()` synthesizes the route envelope and the full blog body is embedded inline in `buildCarouselGenPrompt()` for `/carousel-gen`. Set `'false'` to restore plugin-decided format. |
| `linkedin_carousel_style` | `'sketchnote'` | **Sketchnote infographic carousel (June 11, 2026)**: visual execution preset passed to `/carousel-gen` by [`buildCarouselGenPrompt`](backend/app/Services/LinkedInGenerationService.php) as `--style=<value>`. `'sketchnote'` (default) = flat hand-drawn educational infographic on cream paper (Granola/Obsidian look, knowledge-first — every body slide a self-contained mini-infographic via the `KNOWLEDGE-FIRST INFOGRAPHIC` directive; no creator-face photo, DOODLE gate replaces the photo WOW gate). `'cinematic'` restores the photorealistic creator-face carousel. Resolved at the call site (method stays pure) + seeded idempotently in `LinkedInSettingsSeeder`. Requires the plugin's `style-presets.md` bundled into the VPS `refs-carousel-gen-pipeline.md` (plugin v2.24.0+, via `compile-refs.ts`). **IG-repurpose drafts** (detected by `isRepurposeDraft()` — RepurposeJob linkage OR `ContentIdea.source='instagram'`) also get `--narrative=free` (foreshadow dropped); normal blog carousels keep `--narrative=5act`. No-redeploy revert lever. |
| `linkedin_telegram_schedule_enabled` | `'false'` | **Telegram scheduling conversation (June 12, 2026)**: when `'true'`, a genuinely-ready draft (carousel: slides rendered + captions ready; text: validated) fires ONE Telegram "kapan posting?" prompt via the `linkedin:prompt-schedule` cron — weekday/holiday-aware slot buttons ([`LinkedInFixedSlotScheduler::nextAvailableSlots`](backend/app/Services/LinkedInFixedSlotScheduler.php), holidays from static [`config/id_holidays.php`](backend/config/id_holidays.php) via [`IndonesianHolidayService`](backend/app/Services/IndonesianHolidayService.php), NO API) + free-text override (parsed by queued [`ParseAndScheduleReply`](backend/app/Jobs/ParseAndScheduleReply.php) Claude-CLI → deterministically validated future/weekday/non-holiday/±30min-conflict → [`LinkedInSchedulingService::scheduleAt`](backend/app/Services/LinkedInSchedulingService.php)). Enabling SUPERSEDES `linkedin_auto_approve_enabled` — `linkedin:auto-schedule` defers. ONE prompt, no reminder (idempotent via `linkedin_posts.schedule_prompt_sent_at`; serialized one-conversation-per-chat via `telegram_schedule_state` cache). Default OFF. |

**Also extends `telegram` group** with 4 LinkedIn notify toggles (live in telegram group so `TelegramNotificationService` reads from one source per Decision #9):
- `telegram_notify_linkedin_preview` — sent when draft → awaiting_publish
- `telegram_notify_linkedin_depth_failed` — sent when draft → manual_review
- `telegram_notify_linkedin_published` — sent after successful publish
- `telegram_notify_linkedin_backlog_exhausted` — forward-compat scaffolding for `linkedin:auto-schedule` 14-day-lookahead exhaustion alert (default OFF; v1 only logs WARNING — Telegram dispatch deferred until `TelegramNotificationService` gets a system-level `sendLinkedInBacklogExhausted()` method, since existing `DispatchTelegramNotification` job is keyed to `ContentIdea`)

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
- [`/admin/sosmed-posts`](frontend/src/views/admin/LinkedInPostsCalendar.vue) — Content Calendar (scheduled / published / cancelled), renamed from `/admin/linkedin-posts` (June 2026). Old URL redirects here.
- [`/admin/social-studio`](frontend/src/views/admin/SocialStudio.vue) — merged triage surface: LinkedIn drafts (manual_review / failed / in_progress) + IG repurpose jobs. Replaces separate `/admin/linkedin-queue` + `/admin/draft-posts` (both redirect here).
- [`/admin/sosmed-drafts/:id`](frontend/src/views/admin/LinkedInDraftDetail.vue) — 2-col detail with LinkedIn-style card mockup (text) / swipeable slide viewer (carousel) + validation panel + state_log timeline + inline edit mode (hashtag chip editor, char counter at 1100-1300 sweet spot, 3-5 enforced). Old `/admin/linkedin-drafts/:id` redirects here.
- [`/admin/repurpose/:id`](frontend/src/views/admin/RepurposeJobDetail.vue) — IG repurpose job detail (carousel/blog/video_rebrand modes)

**Sidebar** (AdminLayout.vue): new "LinkedIn" section with Posts + Queue links between Content Engine and Testimonials.

**Plugin integration (SHIPPED April 23, 2026 session 3):** Plugin [linkedin-post-writer](https://github.com/alisadikinma/linkedin-post-writer) v0.2.0+ (Phase C4 complete — 5 content-gen skills: brief/convert/carousel/validate/gen-orchestrator) is now wired end-to-end. Plugin is content-gen-only (Addendum 3) — it emits a single JSON blob to stdout matching `OrchestratorOutputSchema`, never calls the backend. Backend owns all operational concerns:

- **[`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php)** — SSH-invokes `claude -p "/linkedin-gen <blog-JSON>" --model sonnet --append-system-prompt-file refs-linkedin-*.md` (4 compiled refs) on `claudesn@localhost`. Synchronous (~30-90s per run, 300s timeout). Parses stdout via balanced-brace scanner (tolerates trailing narration + markdown fences — see `parseOrchestratorOutput`, 8 unit tests in [`LinkedInGenerationServiceParseTest`](backend/tests/Unit/LinkedInGenerationServiceParseTest.php)). Advances FSM PendingGeneration → Generating → Validating → (AwaitingPublish|ManualReview) based on `validation.passed`. On AwaitingPublish, sets `scheduled_at` + `cancel_window_ends_at` from `linkedin_cancel_window_minutes` setting.
- **[`GenerateLinkedInPost`](backend/app/Jobs/GenerateLinkedInPost.php)** — queued wrapper, `$timeout=360s`, 2 retries (60s/300s backoff). Dispatched by regenerate endpoint + scan cron. Skips if draft no longer in a generatable state (PendingGeneration/Failed/Cancelled).
- **[`LinkedInPublishService::publishText`](backend/app/Services/LinkedInPublishService.php)** — now real. `POST /v2/ugcPosts` with `com.linkedin.ugc.ShareContent` payload, `shareMediaCategory=NONE`, `X-Restli-Protocol-Version: 2.0.0`, `LinkedIn-Version` header. Composes body = content + blank line + hashtags. Extracts URN from `X-RestLi-Id` header or body `.id`. Auto-schedules `PostLinkedInFirstComment` job (delay = `linkedin_first_comment_delay_seconds`, default 30s) when `link_comment` non-empty AND `linkedin_first_comment_enabled=true`. `publishCarousel` still returns 503 — PDF composition via TCPDF pending.
- **[`PostLinkedInFirstComment`](backend/app/Jobs/PostLinkedInFirstComment.php)** — delayed job posting the blog link as first comment via `POST /v2/socialActions/{urn}/comments`. Avoids the 60% reach penalty from body links. 3 retries (30s/2m/5m).
- **[`ProcessScheduledLinkedInPosts`](backend/app/Console/Commands/ProcessScheduledLinkedInPosts.php)** — every-minute cron (`linkedin:process-scheduled`). Three outcomes per awaiting_publish row past its cancel_window: kill-switch OFF → demote to manual_review (reason=`kill_switch_demotion`); kill-switch ON + success → published + URN stored; failure → Failed + last_error. Reads `linkedin_auto_publish` setting.
- **[`ScanBlogForLinkedInConversion`](backend/app/Console/Commands/ScanBlogForLinkedInConversion.php)** — **event-driven since June 10, 2026** (was daily 03:00 WIB cron — retired). Finds published posts with no live `linkedin_posts` row, creates pending row + dispatches job. **Virality gate (April 29, 2026)**: only ingests posts whose `ContentIdea.virality_score >= linkedin_virality_min_score` (default 60). Manual posts (no idea linkage) skipped. CLI: `--dry-run`, `--limit=N` (default 20), `--min-virality=N` override, `--hours=N`, **`--post-id=N` (targeted mode — bypasses the lookback window, keeps virality + one-live-draft gates)**. **Now triggered automatically**: [`ContentIdeaController::approveAndPublish`](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php) queues `linkedin:scan-blog --post-id={post}` (non-fatal try/catch) right after a Post is published, so drafts land in `/admin/social-studio` within seconds — no manual scan. The old manual "Scan blog now" button + its `POST /admin/linkedin-drafts/scan-blog-now` endpoint are removed; `ScheduledCommandSeeder` ghost-deletes the `linkedin:scan-blog` cron row on re-seed. SSH catch-up for old posts: `php artisan linkedin:scan-blog --hours=720`.
- **[`PurgeLowViralityLinkedInDrafts`](backend/app/Console/Commands/PurgeLowViralityLinkedInDrafts.php)** — daily 04:00 WIB (`linkedin:purge-low-virality`). Soft-deletes drafts whose source idea decayed below `linkedin_virality_purge_below` (default 50). Only touches non-terminal states — published / cancelled / awaiting_publish are protected. Idempotent. CLI: `--threshold=N` override, `--dry-run`.
- **[`ReapStuckLinkedInCarouselImages`](backend/app/Console/Commands/ReapStuckLinkedInCarouselImages.php)** — every 5 min (`linkedin:reap-stuck-carousel-images`). One level deeper than `linkedin:reap-stuck` — handles per-slide `image_status='pending'` (>30m) or `'generating'` (>15m) by re-dispatching `GenerateLinkedInCarouselImages` (idempotent, skips done slides). Catches GeminiGen webhook drops + queue-worker crashes between persistAndRoute and the image job. CLI: `--pending-threshold=N`, `--generating-threshold=N`, `--dry-run`.
- **[`AutoScheduleManualReviewLinkedInPosts`](backend/app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php)** — daily 04:30 WIB (`linkedin:auto-schedule`). Promotes drafts in `manual_review` to `awaiting_publish`, assigning `cancel_window_ends_at` to next free `posting_time_rules.score >= 85` slot via [`LinkedInAutoSchedulerService::nextAvailableSlot`](backend/app/Services/LinkedInAutoSchedulerService.php) (14-day lookahead, 30-min lead-time guard, `LINKEDIN_AUDIENCE='b2b_tech'`). Drafts ordered virality_score DESC + created_at ASC (highest virality wins prime slot, FIFO tiebreaker). Loop guard scans `pipeline_state_log[]` last 24h for `kill_switch_demotion` reason — skips ping-pong with `linkedin:process-scheduled` when `auto_publish=false`. Carousel format dispatches `GenerateLinkedInCarouselImages` for non-`done` slides, mirroring the self-heal path in `LinkedInDraftController::approve`. Conflict detection extracted into shared [`LinkedInScheduleConflictService`](backend/app/Services/LinkedInScheduleConflictService.php) reused by `checkConflict` endpoint. Gated by setting `linkedin_auto_approve_enabled` (default OFF). CLI: `--dry-run`, `--limit=N`, `--lookahead=N` (default 14). Per [docs/plans/2026-05-07-linkedin-auto-schedule-manual-review.md](docs/plans/2026-05-07-linkedin-auto-schedule-manual-review.md).
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
10. Operator can retry single failed slides (hover "Retry this slide") or re-dispatch all slides ("Regenerate All Images" action button → calls `RegenerateLinkedInCarouselContent` job → for **IG-repurpose** carousels: `RepurposeCarouselBuilder::buildForDraftId` authors 1 slide per source tool then renders; for **blog** carousels: re-runs `/carousel-gen` then re-renders. Fallback to `/carousel-gen` when no parseable tool list. Gated by `config('linkedin.repurpose_source_mirror_regenerate')` (default `true`). `WithoutOverlapping($draftId)` prevents duplicate runs.)

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
/api/admin/galleries (CRUD + bulk-upload + items CRUD + items bulk-upload + items reorder: PUT /{galleryId}/items/reorder — writes `sequence`, controls homepage stage cover)
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
POST   /api/admin/content-engine/ideas/{id}/translate-article # Pre-publish: translate primary → secondary language (async — 202 + queued TranslateContentIdea job; FE polls generated_article.translation_status; FSM-gated)
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

### Admin Scheduler Routes (auth:sanctum, 3 endpoints) — May 9, 2026
```
GET    /api/admin/scheduler                  # List all 18 scheduled commands grouped by category, with computed next_run_at
PUT    /api/admin/scheduler/{cmd}            # Update enabled / cron_expression / description / timezone / without_overlapping_minutes / run_in_background (placeholder rows return 403)
POST   /api/admin/scheduler/{cmd}/run        # Queue Artisan::queue($signature, $arguments) — 202 Accepted (placeholder + disabled rows return 403)
```

### Admin GeminiGen Circuit Routes (auth:sanctum, 1 endpoint) — May 15, 2026
```
GET    /api/admin/geminigen/circuit-status   # Read-only circuit breaker state: {state, opened_at, next_probe_at, last_probe_result, failure_count_in_window}
```

### Automation Routes
```
POST   /api/automation/posts/check-duplicate (public)
POST   /api/automation/blog/image-webhook (public, GeminiGen callback)
POST   /api/automation/geminigen/webhook (public, stateless relay for geminigen-api-client plugin — manual auth via ?token= query)
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
# No host php — prefix each with: docker exec portfolio_backend
docker exec portfolio_backend php artisan migrate                    # Run migrations
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
cd /Users/alisadikin/Drive-D/Projects/Portfolio_v2/frontend
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

**The Operator redesign (June 8, 2026):** homepage recomposed into a fixed 9-section identity-led spine. All homepage `section_type`s are kebab-case; `PageSectionSeeder` deletes the obsolete snake_case homepage ghosts (`featured_projects`, `latest_blog`, `awards`, `gallery`, `cta`) on seed. `Home.vue::isSectionActive` is **default-on for rows absent from DB** (so the spine renders before the seeder runs), and respects `is_active` for rows that exist.

| page_type | section_type | Rendered by | Component |
|---|---|---|---|
| `homepage` | `hero` | [Home.vue](frontend/src/views/Home.vue) | `HeroOperator` (cinematic "JARVIS operator" hero video + ALI SADIKIN MA wordmark + manifesto + 3 CTAs + stat triad; **`public/videos/hero-loop.webm` (2.92MB) + `.mp4` (3.40MB) 8s loop** — keyframe via indusia-image-gen (face ref) → VEO image-to-video via indusia-video-gen, Veo watermark stripped w/ ffmpeg `delogo`; central JARVIS arc-reactor HUD orchestrating 3 labeled live screens VIBE CODING · AI AGENT OS · VIDEO GEN, operator in trendy blazer+tee; **`hero-poster.jpg` (keyframe) wired as `posterSrc`** = immediate render + reduced-motion/slow-conn fallback; `sw.js` pre-caches loop+poster (cache v2). Replaced `CinematicHero` June 8.) |
| `homepage` | `who-i-am` | Home.vue | `WhoIAm` (answer-shaped LLM-quotable about block + real portrait from `settings.about.profile_photo` + identity chips; **live `settings.about.bio` rendered via sanitized `v-html`** — `cleanBio` allowlist `p/strong/em/br`, strips attrs+script — fixed raw-tag leak) |
| `homepage` | `what-i-solve` | Home.vue | `WhatISolveTabs` (3-discipline tabbed switcher: Vibe Coding · AI Agent OS **MANDOR AI** `Introducing` · Generative Video; right panel renders `active.imageSrc` `<img>` when set — **AI Agent OS tab shows a branded MANDOR kanban-board mockup `public/images/whatisolve/mandor-board.jpg`** (indusia-image-gen), else autoplay muted `videoSrc` per tab; all 3 CTAs scroll to `#join-the-build`) |
| `homepage` | `receipts` | Home.vue | `ReceiptsBento` (6-tile proof bento, gold lead = #1 Global AI Demo Day 2026; live 56+/17yr from stats, static $318K+/16/≥95%; **each tile has a geminigen background `public/images/proof/*.jpg` + dark legibility overlay** — demoday from face ref) |
| `homepage` | `international-stages` | Home.vue | `InternationalStages` — **reframed to "Track Record"** (two bands): a **17-year CAREER band** (3 curated chapters from vault `experience.md`: Singapore MNC IT 2008–2015 · Marlin 2016–2019 · Sat Nusapersada 2023–2025, accent gold) ABOVE the **5 STAGE cards** (Bengaluru/Hangzhou-UNCTAD/Silicon Valley/NextDev/Fenox — **IDBYTE dropped**, accent cyan). Stage photos via [`useStageGalleries`](frontend/src/composables/useStageGalleries.js) (`/api/awards/{id}/galleries`); **career photos via new [`useExperienceGalleries`](frontend/src/composables/useExperienceGalleries.js)** (gallery_id based: Singapore→14, Marlin→9-13, Satnusa→16; `/api/galleries/{id}` `file_url`). Shared `BaseGalleryModal` driven by both; text-only fallback per card. Section `id` stays `international-stages` (nav anchor intact) |
| `homepage` | `selected-work` | Home.vue | `SelectedWork` (live `featured.featured_projects` cards → `/projects/{slug}`; footer "All N projects") |
| `homepage` | `testimonials` | Home.vue | `TestimonialsCarousel` (LinkedIn-sourced quotes, 8s auto-rotate, pause-on-hover, dots nav, keyboard ←/→) |
| `homepage` | `latest-writing` | Home.vue | `LatestWriting` (live `featured.latest_articles` feed + Content Engine meta-flex "this blog writes itself") |
| `homepage` | `join-the-build` | Home.vue | `JoinTheBuild` (follow @alisadikinma IG·TikTok·LI·YT + live newsletter signup name/email/WA E.164 + soft WhatsApp CTA) |
| ~~`homepage` `skills-reel`~~ | ~~Home.vue~~ | ~~`SkillsReel`~~ | RETIRED June 8 — dropped from The Operator spine |
| ~~`homepage` `featured-projects`~~ | ~~Home.vue~~ | ~~`ProjectsBento`~~ | RETIRED June 8 — superseded by `selected-work` / `SelectedWork` |
| ~~`homepage` `latest-blog`~~ | ~~Home.vue~~ | ~~`LatestBlog`~~ | RETIRED June 8 — superseded by `latest-writing` / `LatestWriting` |
| ~~`homepage` `stats-cta`~~ | ~~Home.vue~~ | ~~`StatsBar`+`CTASection`~~ | RETIRED June 8 — superseded by `receipts` + `join-the-build` |
| `about` | `cta` | [About.vue](frontend/src/views/About.vue) | `CTASection` (root variant, WhatsApp + social) |
| `projects` | `cta` | [Projects.vue](frontend/src/views/Projects.vue) | `CTASection` (root variant) |
| `gallery` | `cta` | [Gallery.vue](frontend/src/views/Gallery.vue) | `CTASection` (root variant) |
| `blog` | `cta` | [BlogDetail.vue](frontend/src/views/BlogDetail.vue) | `CTASection` (root variant) — **article detail, NOT list** |

**Naming convention:** `section_type` is kebab-case. The retired homepage components (`SkillsReel`, `SkillShowcase`, `ProjectsBento`, `LatestBlog`, `StatsBar`, `CTASection` home-variant, `CinematicHero`) still exist on disk until the Phase J cleanup. Don't revive snake_case; always use kebab-case for new sections and keep `PageSectionSeeder` in sync.

**Home.vue snap-section gotcha:** `.snap-section` wrapper has `min-height: 100dvh` — putting `v-if="isSectionActive(...)"` on the inner component while keeping the wrapper always-rendered leaves a full-viewport blank space when the section is toggled off. Always put the `v-if` on the `<div class="snap-section">` wrapper itself so the whole section collapses.

**Header nav = section anchors (June 8, 2026):** [TheNavigation.vue](frontend/src/components/TheNavigation.vue) no longer renders DB `menu_items` route-links — it renders a static `sectionLinks` set (Who I Am · What I Solve · Receipts · Awards · My Projects · Blogs · Contact) mapped to the homepage section `id`s (`who-i-am`/`what-i-solve`/`receipts`/`international-stages`/`selected-work`/`latest-writing`/`join-the-build`). Each `.snap-section` wrapper in `Home.vue` carries a matching `id` + `scroll-mt-24`. Click → on homepage `scrollIntoView` (reduced-motion aware), off-homepage `router.push({name:'home', hash})` (router `scrollBehavior` resolves the hash). IntersectionObserver drives an active-link highlight (cosmetic, graceful no-op). When adding/removing a homepage section, keep `sectionLinks` ↔ the section `id`s in sync.

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

Located at `/Users/alisadikin/Drive-D/Projects/Portfolio_v2/.claude/agents/`:
- `orchestrator.md` - Multi-agent coordinator
- `laravel-specialist.md` - Backend expert
- `vue-expert.md` - Frontend expert
- `database-administrator.md` - Database expert
- `qa-expert.md` - Testing & QA
- `documentation-engineer.md` - Documentation

## Model Selection Policy (Cost-Aware Routing)

Match the model to the actual cognitive effort of the work. Don't pay Opus rates for a config edit; don't pay Haiku quality for an architecture decision. Default to the cheapest model that can finish the task correctly on the first attempt.

| Tier | Model | Model ID | When to use |
|---|---|---|---|
| **Heavy** | Opus 4.7 | `claude-opus-4-7` | Multi-file architecture, FSM/state-machine design, ambiguous specs, complex debugging (no obvious cause), security review, plan authoring (`/gaspol-plan`), brainstorm with deep tradeoffs (`/gaspol-brainstorm`), reviewing AI-generated long-form output (article scoring with reasoning), cross-file refactor across 5+ files, root-cause investigation with mixed signals |
| **Standard** | Sonnet 4.6 | `claude-sonnet-4-6` | Normal feature implementation following an approved plan, single-feature CRUD, writing tests for existing code, refactor inside one module, standard PR review, content generation pipeline phases (article-prep / write / score / images / translate — already configured in `ARTICLE_GEN_MODEL_*`), code review when behavior is well-specified, documentation drafting from existing context |
| **Light** | Haiku 4.5 | `claude-haiku-4-5-20251001` | Simple file lookup (`Glob`, `Grep`), single-line config edits, formatting fixes, status checks, listing/searching, dependency lookups, "where is X defined" questions, parsing structured output, log scraping, simple translations of short strings, bumping version numbers, syntax-error fixes |

### Routing rules

**For subagents** (this is the lever Claude actually controls — `Agent` tool's `model:` parameter):
- `general-purpose` agent doing broad codebase exploration → **Haiku** (volume-heavy, low-judgment)
- `Explore` agent for "find files matching pattern" → **Haiku**
- `code-reviewer` / `spec-reviewer` / `plan-verifier` (gaspol-dev review subagents) → **Sonnet** by default; **Opus** when reviewing security-sensitive code, OAuth/auth flows, FSM transitions, or migrations affecting >100k rows
- `laravel-specialist` / `vue-expert` doing planned implementation work → **Sonnet**
- `laravel-specialist` / `vue-expert` doing greenfield architecture → **Opus**
- `gaspol-dev:code-simplifier` → **Sonnet** (refactor pattern is well-defined)
- `pitch-deck-designer-agent` / `linkedin-writer` / `article-writer` / `carousel-prompt-generator` → keep their defaults (these plugins set their own model in the skill's `--model` flag — don't override)
- Unknown agent type → start **Sonnet**, escalate to **Opus** only if first run shows judgment failure

**For the main session** (user's `/model` choice — this section documents the bias, the user picks):
- If task description matches Heavy criteria → suggest `/model opus` before starting
- If user is in default mode and task is clearly Light, proactively delegate to a Haiku subagent rather than burning the main-session model
- Never silently downgrade — if the work needs Opus and user is on Sonnet, surface it: "this needs deeper reasoning than Sonnet typically gives — switch to Opus, or accept that the first pass may need iteration?"

### Cost guardrails

- **Don't escalate after a single bad output.** Sonnet missing context once is normal — give it the missing context, don't reach for Opus reflexively.
- **Don't downgrade mid-task.** If you started a complex task on Opus, finish it on Opus. Switching mid-execution loses context (cache miss + reasoning drift).
- **Pipeline phases are NOT a place to escalate without measurement.** `ARTICLE_GEN_MODEL_*`, `LINKEDIN_GEN_MODEL`, `CAROUSEL_GEN_MODEL` defaults to Sonnet because production runs validated quality there. Changing to Opus is a 4-5x cost multiplier — only flip after observing repeated failure of the same class on Sonnet (e.g., the May 2 carousel-gen Sonnet truncation issue is documented as an Opus-flip candidate but not yet justified by frequency).
- **Cached prompts amortize across the tier.** Subagent dispatch always resets cache; main-session model stays warm. Prefer the main session for the deepest 1-2 reasoning steps, delegate breadth-search work to fresh Haiku/Sonnet subagents.

### Anti-patterns

- ❌ "Use Opus for everything to be safe" — burns budget, doesn't measurably improve outputs for Tier-2/3 work
- ❌ "Use Haiku for the planning step to save money" — planning is the highest-leverage step; cheaping out here amplifies downstream cost via wrong implementation
- ❌ Changing pipeline model env vars (`ARTICLE_GEN_MODEL_*` etc.) without observing failure pattern in production logs first
- ❌ Routing to `code-reviewer` agent without specifying model (defaults to Sonnet — fine for most reviews, but security/migration/FSM reviews should explicitly pass `model: "opus"`)

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

**Overall Score: 8.5/10** — All P0-P2 issues resolved. **P0 "SPA without SSR" now FULLY CLOSED** (homepage + all blog surfaces June 9, 2026; **projects detail June 25, 2026** — `SpaPrerenderController::projectDetail` + `SchemaGraphBuilder::creativeWork` + crawlable `seo/project.blade.php` body, 1h cache purged via `Project::boot`) via the Laravel SSR-enrichment layer ([`SpaPrerenderController`](backend/app/Http/Controllers/SpaPrerenderController.php) + [`SeoHtmlComposer`](backend/app/Services/Seo/SeoHtmlComposer.php) + [`SchemaGraphBuilder`](backend/app/Services/Seo/SchemaGraphBuilder.php)) — see the Last Updated changelog + [docs/runbooks/seo-geo-ssr-deploy.md](docs/runbooks/seo-geo-ssr-deploy.md). **GEO 5-Pillar audit (June 25, 2026)** vs Helena Liu's GEO Toolkit closed 3 gaps: projects SSR (Pillar 1), a dedicated `/faq` page (Pillar 2 — `config/faq.php` single source → SSR `FAQPage` + `/api/faq` + Vue `FaqView`), and Pillar 5 measurement (GA4 tag via `VITE_GA4_MEASUREMENT_ID` + AI-bot crawl logger `LogAiCrawler` → `geo_crawler_hits` → `GET /api/admin/geo/crawler-hits`, since GA4 can't see JS-blind crawls). See [docs/plans/2026-06-25-geo-hardening-three-gaps.md](docs/plans/2026-06-25-geo-hardening-three-gaps.md) + [docs/runbooks/geo-ai-traffic-ga4.md](docs/runbooks/geo-ai-traffic-ga4.md).

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
| ~~**P0**~~ | ~~**SPA without SSR**~~ — homepage + blog + **projects detail** now server-enriched with head + JSON-LD + crawlable body via Laravel `SpaPrerenderController` (Vue hydrates on top). | ✅ FULLY FIXED — blog June 9 2026, projects June 25 2026 |
| ~~**P2**~~ | ~~**FAQ only on blog**~~ — dedicated `/faq` page now emits `FAQPage` JSON-LD + crawlable `<dl>` (`config/faq.php` single source). | ✅ FIXED June 25, 2026 — per-project/about FAQ deferred |
| **P5** | **No AI-traffic measurement** — GA4 tag + AI-bot crawl logger now shipped; GA4 "AI Traffic" channel group is a manual dashboard step. | ✅ code shipped June 25, 2026 (operator: set `VITE_GA4_MEASUREMENT_ID` + GA4 channel group) |
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
CAROUSEL_GEN_DRIVER=ssh                                     # 'ssh' (production) or 'local' (Docker dev)
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

## Admin Scheduler (May 9, 2026)

DB-driven cron management — operator toggles, retimes, and run-now any of 14 production schedules from `/admin/settings → Scheduler tab` without redeploying. Replaces 128 hardcoded `Schedule::command()` lines in [routes/console.php](backend/routes/console.php) with one [`DynamicScheduleRegistrar::register()`](backend/app/Services/DynamicScheduleRegistrar.php) call (file now 18 lines). Forward-compat placeholder slots seeded for Instagram (scan + publish), Facebook (publish), TikTok (publish) — flip-on the day commands ship.

**Schema** — single new table via [`2026_05_09_000001_create_scheduled_commands_table`](backend/database/migrations/2026_05_09_000001_create_scheduled_commands_table.php):

| col | type | purpose |
|---|---|---|
| `signature` | string UNIQUE | `'linkedin:scan-blog'` |
| `arguments` | JSON nullable | `['--hours=24']` — concatenated to signature at register time |
| `category` | enum indexed | `content_engine` / `linkedin` / `instagram` / `facebook` / `tiktok` / `newsletter` / `system` |
| `cron_expression` | string(64) | `'0 5,6,12,15,17,18,19,20 * * *'` (consolidates the 8x/day `content:auto-pipeline` foreach) |
| `timezone` | string default `'Asia/Jakarta'` | per-row override |
| `enabled` | bool indexed | toggle off → registrar skips on next tick |
| `is_placeholder` | bool default false | UI lock + 403 on update/run for IG/FB/TikTok scaffolding |
| `without_overlapping_minutes` / `run_in_background` | nullable int / bool | passed to `withoutOverlapping()` / `runInBackground()` if set |
| `last_run_at`, `last_finished_at`, `last_status`, `last_duration_ms`, `last_error` | audit columns | populated by Laravel `ScheduledTask*` event listeners in [`AppServiceProvider::boot()`](backend/app/Providers/AppServiceProvider.php) — zero coupling to command code |
| `sort_order` | unsigned smallint | display ordering within category |

**Disable behavior:** "Stop new runs only" — toggle OFF prevents next register, never scans queue or kills in-flight jobs. Up to 60s lag before next `schedule:run` tick respects new state.

**Boot safety:** registrar wraps DB read in `try/catch` + `Schema::hasTable` guard. On failure (table missing pre-migration, MySQL down during boot) logs `warning` + silently no-ops so kernel finishes bootstrapping. **Production-critical**: silent no-op without logging would hide ALL cron failures behind an empty schedule list — `Log::warning` ensures `laravel.log` surfaces the issue.

**Audit hook signature parsing** — [`App\Support\ScheduledTaskSignatureExtractor::extract($command)`](backend/app/Support/ScheduledTaskSignatureExtractor.php) handles `'php /path/to/artisan linkedin:scan-blog --hours=24'` → `'linkedin:scan-blog'` (strips binary path + args, returns null on malformed input). Listeners early-return on null match → no DB writes for unrelated commands like `php artisan migrate`.

**Cron preview** — uses `dragonmantank/cron-expression` (already a Laravel transitive dep) for `Cron\CronExpression::isValidExpression()` validation + `getNextRunDate(now()->setTimezone($tz))` to compute `next_run_at` server-side per row.

**Run Now** — `Artisan::queue($signature, $args)` enqueues to `portfolio-queue.service` systemd worker (existing infra, no new daemon). Optimistic UI flips status pill to `running` immediately; audit hook updates real status when worker picks up.

**Seeded inventory (18 rows total)** via [`ScheduledCommandSeeder`](backend/database/seeders/ScheduledCommandSeeder.php):
- 5 Content Engine: `content:process-scheduled`, `blog:process-images`, `content:process-pending-translations`, `content:auto-pipeline` (single cron `0 5,6,12,15,17,18,19,20 * * *`), `content:pull-trending-daily`
- 8 LinkedIn: `linkedin:process-scheduled`, `linkedin:scan-blog --hours=24`, `linkedin:reap-stuck`, `linkedin:retry-failed`, `linkedin:reap-stuck-carousel-images`, `linkedin:purge-low-virality`, `linkedin:auto-schedule`, `linkedin:prompt-schedule` (every minute — Telegram schedule prompt, gated by `linkedin_telegram_schedule_enabled`)
- 1 Newsletter: `newsletter:send-weekly`
- 1 System: `posting-rules:research --platform=linkedin`
- 4 Placeholders: `placeholder:instagram-scan`, `placeholder:instagram-publish`, `placeholder:facebook-publish`, `placeholder:tiktok-publish` (`enabled=false`, `is_placeholder=true`)

Idempotent via `firstOrCreate(['signature' => ...], $row)` — re-seeding on existing DB yields zero new rows. Wired into `DatabaseSeeder::run()` so `php artisan migrate --seed` (and deploy.sh step 4) populate atomically.

**UI** — new tab "Scheduler" added as 7th tab in [`/admin/settings`](frontend/src/views/admin/SettingsForm.vue) tab strip (synced via `?tab=scheduler` query param). New view [`SchedulerSettings.vue`](frontend/src/views/admin/SchedulerSettings.vue) renders grouped tables per category. Per row: name + signature monospace, schedule preset dropdown (10 presets covering 95% of seeded values + Custom… escape hatch with raw cron field) + `cron_human_readable` preview, enabled toggle (emerald/gray rocker), last-run status pill (success / failed / running animate-pulse / never) + relative time + duration_ms, next-run relative time, `▶ Run Now` button. Placeholder rows visually distinct (amber tint + 🔒 lock badge) with all controls disabled. New composable [`useScheduler.js`](frontend/src/composables/useScheduler.js) with TanStack Query mirrors `useLinkedInDrafts.js` pattern (30s `staleTime` + `refetchOnMount: 'always'`, `useUpdateScheduledCommand` + `useRunScheduledCommand` mutations with `invalidateQueries` on success).

**Files (12):** 7 backend (1 migration + 1 model + 1 factory + 1 seeder + 1 service + 1 helper + 1 controller + 1 resource — counting service/helper/controller/resource as 4, total = migration+model+factory+seeder+service+helper+controller+resource = 8) + 6 test files (1 unit per phase A+D, 1 feature per phase B+C+D+E = 6) + 2 frontend (composable + view) + 2 modified (routes/api.php, routes/console.php, AppServiceProvider.php, SettingsForm.vue, DatabaseSeeder.php). **Total tests authored: 27 / 93 assertions, all green** across 6 test files.

**Operator post-deploy step:** none — `deploy.sh` step 4 runs idempotent seeders automatically. After first deploy the admin can navigate to `/admin/settings?tab=scheduler` and see all 18 rows live.

**Anti-patterns enforced:**
- ❌ NO closures in `routes/console.php` (verified before cutover) — every entry is an Artisan command, dynamic registration covers them all
- ❌ NO multi-row composite key for `content:auto-pipeline` 8x/day — collapsed to single cron `0 5,6,12,15,17,18,19,20 * * *`
- ❌ NO silent fallback to hardcoded schedule on registrar failure — `Log::warning` then no-op (operational visibility > convenience)
- ❌ NO Slack/Discord notify on `last_status='failed'` v1 — leverage existing Telegram infra in follow-up phase
- ❌ NO per-user permissions on which schedule can be toggled — admin = all (single-operator project)
- ❌ NO confirm modal on disable for high-impact schedules (e.g., `linkedin:process-scheduled`) — flagged as Phase H follow-up

**Design + plan:**
- [docs/plans/2026-05-09-admin-scheduler-tab.md](docs/plans/2026-05-09-admin-scheduler-tab.md) — design + 7-phase TDD plan + 5-row risk register

---

## Recent Changes (newest first)

> Condensed log — one line per change. Deep detail lives in the linked `docs/plans/` + `docs/runbooks/` docs and git history. Commits are local until the operator authorizes a push.

- **2026-07-01** — **GeminiGen → indusia client SSOT (submit via CLI bridge, flagged OFF).** Stops the backend hand-rolling GeminiGen/snapgen HTTP submits in two drifted spots — image + video now shell the `indusiagen-api-client` CLI ([geminigen_image.py / geminigen_video.py](https://github.com/alisadikinma/indusiagen-api-client), the SSOT for the wire protocol) via new [`GeminiGenClientBridge`](backend/app/Services/GeminiGenClientBridge.php) (SUBMIT-only: `submit($endpoint,$fields,$refs,$model)` → uuid; `local` driver = Illuminate Process **argv array, no shell**, `ssh` = `escapeshellarg` per token; **API key NEVER on argv** — client reads its own VPS `.env`; gpt-image-2 URL-refs auto-materialized to temp local files). Wave 1 (images): [`ImageGenerationService::queue`](backend/app/Services/ImageGenerationService.php) + [`LinkedInCarouselImageService::dispatchOne`](backend/app/Services/LinkedInCarouselImageService.php) branch to the bridge behind `geminigen.use_indusia_images`; carousel model now operator-set via `linkedin_carousel_image_model` setting (default `nano-banana-pro`; `gpt-image-2` = literal-typography, Premium-plan only, gets `--mode medium`). Wave 2 (video): all 4 [`GeminiGenVideoService`](backend/app/Services/GeminiGenVideoService.php) dispatchers (hook/keyframe/veo/grok) branch behind `geminigen.use_indusia_video` — per-family CLI subcommand (grok `2:3`/`--mode custom`, veo `--mode frame`, keyframe → image CLI). **Both flags default OFF** — old PHP HTTP path is the fallback, deploy = no-op until env flip. Vendor rebrand `api.geminigen.ai → api.snapgen.ai` centralized in new [`config/geminigen.php`](backend/config/geminigen.php) `base_url` (6 seams: 3 services + 3 poll crons off the dead domain). **Poll stays backend GET** (`{base_url}/history/{uuid}` — model-agnostic, no drift; the client's `check` subcommand is a documented stub → bridge is submit-only). Env: `GEMINIGEN_BASE_URL`, `GEMINIGEN_CLIENT_{DRIVER,PATH,REPO,TIMEOUT,SSH_*}`, `GEMINIGEN_USE_INDUSIA_{IMAGES,VIDEO}`. Tests: config 4 + bridge 6 + ImageGenIndusiaSubmit 2 + CarouselIndusiaSubmit 3 + VideoIndusiaSubmit 4 + touched-suite regression (video svc 2, polls 17) all green (11 batched fails are the pre-existing `alias:Setting` Mockery leak, proven identical at base `88479ec7`). **SHIPPED + LIVE (2026-07-01):** pushed, deployed, VPS client installed (read-only deploy key + `~/.config/geminigen/config.json` key — client has no dotenv loader), all step-0 probes green (image / gpt-image-2 Premium / grok+ref 2:3 / veo frame), **both flags flipped ON** — Wave 1 verified prod-green end-to-end (draft 188 slide 0 `failed → done`: service → CLI bridge → job row → poll `GET /history` → downloaded), Wave 2 (video) ON, organic-verify on next hook/veo run. Key rotated to a fresh snapgen key (old one hit `HTTP 402` out-of-credits, which had also broken the live HTTP image path since ~09:30 — rotation fixed both). CLI `--aspect` enum has NO `4:5` (carousel uses `3:4`). → [runbook](docs/runbooks/geminigen-indusia-client-deploy.md). [plan](docs/plans/2026-07-01-geminigen-indusia-client-ssot.md)
- **2026-06-25** — **GEO hardening vs Helena Liu's 5-Pillar GEO Toolkit — closed the 3 real gaps.** Audit found Pillars 1/3-signal/4 essentially done (9 AI-bot robots allow-list, static+dynamic llms.txt, 10 schema types, blog/home SSR, 7-platform cross-post); gaps were projects-invisible-to-JS-crawlers (P1), FAQ-only-on-blog (P2), zero measurement (P5). **(P1) Projects detail SSR** — [`SpaPrerenderController::projectDetail`](backend/app/Http/Controllers/SpaPrerenderController.php) clones `blogDetail`: `CreativeWork` JSON-LD ([`SchemaGraphBuilder::creativeWork`](backend/app/Services/Seo/SchemaGraphBuilder.php)) + crawlable `<article>` body ([`seo/project.blade.php`](backend/resources/views/seo/project.blade.php)) + breadcrumbs + hreflang, 1h cache purged via `Project::boot` → `purgeForProject`; web.php OG-only closures replaced (no nginx change — `/projects/*` already routes to PHP-FPM). **(P2) Dedicated `/faq`** — single source [`config/faq.php`](backend/config/faq.php) (11 curated answer-first Q&A) feeds SSR `FAQPage`+crawlable `<dl>` ([`SpaPrerenderController::faq`](backend/app/Http/Controllers/SpaPrerenderController.php) + [`seo/faq.blade.php`](backend/resources/views/seo/faq.blade.php)), public `GET /api/faq` ([`FaqController`](backend/app/Http/Controllers/Api/FaqController.php)), and Vue [`FaqView.vue`](frontend/src/views/FaqView.vue) — **needs one-time nginx widening for `/faq`** ([runbook](docs/runbooks/seo-geo-ssr-deploy.md)). **(P5) Measurement** — GA4 gtag in [`main.js`](frontend/src/main.js) gated by `VITE_GA4_MEASUREMENT_ID` (empty → no tag/network) + [`LogAiCrawler`](backend/app/Http/Middleware/LogAiCrawler.php) middleware (atomic `upsert` into `geo_crawler_hits`, fail-open) logging the AI-bot crawls GA4 can't see, read via `GET /api/admin/geo/crawler-hits` ([`GeoCrawlerStatsController`](backend/app/Http/Controllers/Api/Admin/GeoCrawlerStatsController.php)); GA4 "AI Traffic" channel group is a manual dashboard step ([runbook](docs/runbooks/geo-ai-traffic-ga4.md)). Per-project/about FAQ deferred (YAGNI). 5 test files added (LogAiCrawler, ProjectDetailSsr, FaqSsr, FaqApi, SchemaGraphBuilderCreativeWork); code-reviewed (noscript-FAQ + upsert-race + MariaDB-orderBy fixes applied). NOT pushed — operator: run migrate + `php artisan test`, set `VITE_GA4_MEASUREMENT_ID`, widen nginx for `/faq`. [plan](docs/plans/2026-06-25-geo-hardening-three-gaps.md)
- **2026-06-22** — **Fix: IG-repurpose BLOG-mode carousel draft invisible in Social Studio (stale `exclude_repurpose` arm #3).** A `source='instagram'` ContentIdea finalized via Content Engine → published → `linkedin:scan-blog` correctly created a `manual_review` carousel draft, but it showed in NEITHER Social Studio column (idea 817 → post 125 → draft 177). IG column (`exclude_settled=1`) hides the blog-mode repurpose job the moment it hands off (`content_idea_id` set); LinkedIn column (`exclude_repurpose=1`) arm #3 in [LinkedInDraftController::index](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) dropped any draft whose `post_id` linked a `ContentIdea{source:'instagram'}`. Arm #3 was added 2026-06-11 to keep sources disjoint, but the 2026-06-13 blog→Content-Engine handoff made blog-mode jobs settle (hidden from IG) immediately — so arm #3 only ever hid blog-handoff drafts that have NO IG-column twin (carousel/video modes never create a `source=instagram` ContentIdea; they're caught by arms #1 `linkedin_post_id` / #2 `anchor_post_id`). **Removed arm #3** → blog-handoff carousel drafts now appear in the Social Studio LinkedIn queue for triage. Test `LinkedInDraftListExcludeRepurposeTest` inverted (now asserts KEEP); unused `ContentIdea` import dropped. 261 LinkedIn/Repurpose tests green. NOT pushed.
- **2026-06-22** — **Zernio schedule timezone fix (UTC → WIB) + carousel cover headline centered.** (1) Scheduled Zernio posts sent `timezone = config('app.timezone')` = **UTC** ([config/app.php](backend/config/app.php), no `APP_TIMEZONE` override) → Zernio fired/displayed schedules 7h off. New config `social-cross-post.zernio.timezone` (env `ZERNIO_TIMEZONE`, default `Asia/Jakarta`); [PublishViaZernio::applyScheduling](backend/app/Jobs/PublishViaZernio.php) + [PublishRepurposeViaZernio](backend/app/Jobs/PublishRepurposeViaZernio.php) now emit `scheduledFor` in WIB (`+07:00`, same absolute instant via `setTimezone`) + `timezone=Asia/Jakarta` — robust whether Zernio honors the ISO offset or the timezone field. `publishNow` immediate path unchanged (already works when no future schedule). (2) Carousel **cover headline text was dragged to the bottom** — the canonical text-overlay phrase in plugin `/carousel-gen` (`ai-image-carousel-prompt-gen/references/prompt-formulas.md`) read "positioned starting from the vertical center extending **downward**"; rewritten to center the text block on the canvas middle third (7 occurrences = rule def + 6 slide templates, single-source). Recompiled `refs-carousel-gen-pipeline.md` (306KB, gitignored). Tests: PublishViaZernio 11/11 (+1 WIB tz regression assertion), repurpose 8/8 green. **Deploy pending:** Portfolio commit+push (CI auto-deploy) + manual VPS bundle copy to `/home/claudesn/refs-carousel-gen-pipeline.md`. NOT pushed.
- **2026-06-18** — **IG-repurpose carousel: slide count now follows the SOURCE + founder/figure faces finally REACHABLE (two root causes, `/gaspol-debug`).** Two long-standing prod bugs — every repurpose carousel stuck at 7 slides + founder/public-figure faces never rendering (all sketchnote) — traced to TWO defects, both PROVEN on prod before any fix. **(1) Capture over-grab + under-capture** ([scripts/playwright/ig-capture.cjs](scripts/playwright/ig-capture.cjs)): IG lazy-loads carousel slides behind hover-gated "Next" clicks, but the script did a STATIC whole-page `<img>` scrape and UNIONED JSON-LD+og+DOM → it captured ~2 real slides + a pile of srcset thumbnails + the profile avatar + the "more posts" suggested grid (job 33 = **20 noisy frames** for a 5-slide post; JSON-LD now returns 0 anonymously). Rewritten to TRAVERSE the carousel — hover the media, click "Next" through every slide, collect ONLY `naturalWidth≥600` IG-CDN images (cleanly drops ~150px avatars + ~480px grid cards) until the arrow disappears (loop-back guard). Live-verified on all 4 real source URLs: **5 / 8 / 12 / 7 slides, zero junk** (was 20+). **(2) Enricher read the WRONG disk — the real "no faces" blocker** ([`CarouselPersonPhotoEnricher::resolveSourceSlidePaths`](backend/app/Services/CarouselPersonPhotoEnricher.php)): `Storage::disk('local')` root is `storage/app/private` under Laravel 11/12, but capture writes to `storage/app/repurpose/{id}` → `files()` returned `[]` → the enricher silently no-op'd → **no faces EVER, even with last session's group-crop fix (correct but unreachable — it only ran in my hand-fed test with absolute paths).** Now reads `storage_path('app/'.$relDir)` with a native scan, mirroring `VideoSlideExtractor` (which is why video_rebrand always worked). The People-Spotlight rule + VPS bundle are correctly deployed; `/carousel-gen` flags faces only on posts that warrant a "SIAPA X?" profile slide (news roundups correctly get none). **(3) Slide count follows source**: new [`LinkedInGenerationService::sourceSlideCount()`](backend/app/Services/LinkedInGenerationService.php) + `resolveTargetSlides()` thread the captured count into `/carousel-gen --target-slides` for repurpose drafts (replaces the hard-7 `inferTargetSlides`), clamped to a Sonnet-safe ceiling `config('carousel-gen.max_repurpose_slides', 12)` — a single bilingual envelope truncates past ~9 slides (the reason for the legacy 7-cap; raise w/ `CAROUSEL_GEN_MODEL`→opus). Wired through `applyCarouselGenAdapter`→`dispatchCarouselGenEngine`→`buildCarouselGenPrompt` + the regenerate-button job. Tests: 65 green across 7 suites (+10 new); capture rewrite live-verified (non-deterministic surface). NOT pushed. [eval](docs/evals/repurpose-person-photo.md)

- **2026-06-17** — **people_spotlight fix #2 — GROUP-photo fallback for UNLABELLED people (the real "no CURSOR faces" blocker) + stale-bundle root cause.** Draft 172 still showed no founder faces after a regenerate. `/gaspol-debug` on prod found TWO compounding causes. **Cause #1 (fixed):** the gitignored VPS `/carousel-gen` bundle `refs-carousel-gen-pipeline.md` was never recompiled after the v3.0.6 marketplace auto-reinstall — it ran v3.0.5 knowledge (zero `needs_real_faces` / "People Spotlight"), so `/carousel-gen` flagged every slide `needs_real_faces=false` and the enricher never fired. Recompiled from the v3.0.6 cache (`npx tsx scripts/compile-refs.ts` → 306KB, now carries the rule). A version bump auto-reinstalls the plugin cache but a **real-file** bundle does NOT auto-update — it must be recompiled, and a stale one fails SILENTLY (old behaviour, no error). **Cause #2 (fixed — code):** even with the contract, [`SourceFaceLocator::locate()`](backend/app/Services/SourceFaceLocator.php) matches faces BY NAME against visible labels, but a founders GROUP photo ("4 MIT dropouts") shows them **together, unlabelled** → vision won't ID by appearance → 0 matches → empty band. New [`SourceFaceLocator::locateGroup($paths,$people,$topic)`](backend/app/Services/SourceFaceLocator.php) fallback: finds the ONE slide that is the group portrait (by topic text + headcount) and returns EVERY face bbox left-to-right, capped to headcount, **without name attribution** (showing the real faces IS the human touch; guessing whom is which would be wrong). [`CarouselPersonPhotoEnricher`](backend/app/Services/CarouselPersonPhotoEnricher.php) invokes it whenever name-matching yields fewer faces than `people` (prefers whichever finds more — named single subjects keep the label path). **Proven live on job 33** (raw vision over the 20 captured slides as the claudesn worker): `claude -p` reads the images fine; the founders ARE there (slide 4 = 4-face group photo, unlabelled); name-locate → 0, group-locate → slide 4 + 4 boxes `x≈[0.07,0.27,0.46,0.67]`. The "always 7 slides not 10" symptom is unrelated + not a bug: this Cursor post is a narrative news caption (no numbered tool list) so the source-mirror one-tool-per-slide builder correctly no-ops and `/carousel-gen` authors a 7-slide narrative. Tests: +9 (`SourceFaceLocatorTest` group + enricher group-fallback), 22/22 green. **Still needs the plugin VPS deploy + a Regenerate to verify the composite (eval E4/E5).** NOT pushed. [eval E5](docs/evals/repurpose-person-photo.md)
- **2026-06-17** — **people_spotlight — REAL founder/people photos on profile carousel slides (plugin-brain + backend-fulfilment).** Fixes profile body slides ("SIAPA `<Name>`?" / who-is — e.g. draft 172 "SIAPA CURSOR? 4 MIT Dropouts", draft 161 "SIAPA ASHISH VASWANI?") rendering as icon-only doodles with NO human face. **The intelligence lives in the plugin** (`ai-image-carousel-prompt-gen` v3.0.6+, separate repo, so EVERY consumer gets it — not just Portfolio): new optional `CarouselSlideSchema` fields `needs_real_faces` + `people[{name,role?}]` + `face_layout` (superRefine: needs_real_faces ⇒ non-empty people + face_layout≠none) + a **People Spotlight** authoring rule (`creator-bible.md` single source of truth, `style-presets.md §0` sketchnote exception, SKILL Hard Rule #22) that DETECTS a person-profile slide, emits the contract, reserves a photo band in `image_prompt`, and forbids doodling/inventing the person or baking their name as on-image text. **Backend FULFILS the contract** (repurpose-only — only IG-repurpose drafts carry captured source slides; blog→carousel is a no-op): [`CarouselGenOutputAdapter`](backend/app/Services/CarouselGenOutputAdapter.php) passes the contract through; [`CarouselPersonPhotoEnricher`](backend/app/Services/CarouselPersonPhotoEnricher.php) (wired beside the cover enricher in [`LinkedInCarouselImageService::dispatchAllSlides`](backend/app/Services/LinkedInCarouselImageService.php)) resolves each named person's face in the captured source slides via [`SourceFaceLocator`](backend/app/Services/SourceFaceLocator.php) (vision, NAME-driven — NO app-side intent detection, the plugin already decided), Intervention-crops the padded bbox to a unique public cut-out, attaches `person_photo_refs` + forces re-render; on slide completion `handleWebhook` composites the real cut-outs into the reserved band as framed pinned-polaroids via [`CarouselPersonStripRenderer`](backend/app/Services/CarouselPersonStripRenderer.php) + [`scripts/repurpose/carousel-person-strip.cjs`](scripts/repurpose/carousel-person-strip.cjs) (Playwright, base+faces as data: URIs, one screenshot). Idempotent (`person_photos_enriched`, cleared on single-slide re-render), fail-safe (any miss → plain slide), `SharedDir::ensure` + unique filenames (cross-user + immutable-CDN safe). New `services.instagram_capture.person_strip_script_path`. Tests: plugin 71 (13 new) + 31 new backend + 5 node, all green. **Deferred operator steps (NOT done):** push the plugin repo + VPS recompile `refs-carousel-gen-pipeline.md` (gitignored) + deploy `carousel-person-strip.cjs`; then "Regenerate All Images" on 172/161 to verify (eval E4). [plan](docs/plans/2026-06-17-repurpose-founder-photo-human-touch.md) · [eval](docs/evals/repurpose-person-photo.md). NOT pushed.
- **2026-06-16** — **Reddit (4th Zernio platform) + Facebook & YouTube (3rd Zernio workspace key `fbyt`) cross-post expansion.** Zernio now publishes 6 platforms across 3 keys: `igtt` (IG+TikTok) · `threads` (Threads **+Reddit**) · **`fbyt` (Facebook+YouTube)** — [`ZernioClient::forPlatform`](backend/app/Services/ZernioClient.php) routes via `match`. Feasibility (Zernio docs): none do video-carousel / mixed media; Reddit = gallery+single-video (subreddit req, title ≤300), FB = ≤10-image+video (Pages only), **YouTube = video-only** (single 60s → Short, `containsSyntheticMedia`). Routing: image carousel → FB multi-image + Reddit gallery (not YT); `video_full` 60s → FB/Reddit/**YouTube**; `video_rebrand` → none. **Reddit** carousel path: `reddit_posts` table + [`RedditPost`](backend/app/Models/RedditPost.php)/[`RedditPostStatus`](backend/app/Enums/RedditPostStatus.php) (Zernio-only mirror of Threads) + `LinkedInPost::redditPost`; [`buildReddit`](backend/app/Services/ZernioPayloadBuilder.php), `PublishViaZernio` reddit branch (reuses Threads key), [`createReddit`](backend/app/Console/Commands/ScanLinkedInForCrossPost.php) (carousel-only, content REUSED → `awaiting_review`, no Generate job), wired into [`PublishSlotOrchestrator`](backend/app/Console/Commands/PublishSlotOrchestrator.php) (+`off` guard) + approve cascade. **Facebook** Publer→Zernio cutover: `facebook_posts.zernio_*` migration, [`buildFacebook`](backend/app/Services/ZernioPayloadBuilder.php) (≤10 img, repurpose-safe firstComment from `link_url`), facebook publish branch; [`PublisherResolver`](backend/app/Support/PublisherResolver.php) defaults FB→zernio (Publer-FB kept as selectable fallback). **YouTube** video_full-only (NO sibling table — `repurpose_jobs.zernio_publish` JSON): [`buildVideoFull`](backend/app/Services/ZernioPayloadBuilder.php) per-platform `platformSpecificData` (YT title/categoryId=28/synthetic-media + Reddit subreddit/title); [`VideoFullController::publishZernio`](backend/app/Http/Controllers/Api/Admin/VideoFullController.php) `in:` widened. **Settings** `zernio` group +8 keys ([`ZernioSettingsSeeder`](backend/database/seeders/ZernioSettingsSeeder.php)): `zernio_api_key_fbyt` + 3 account-ids + `zernio_reddit_subreddit=u_alisadikinma` + 3 selectors (**reddit=`off`**, fb/yt=`zernio`); `PublisherResolver::for` gains an `off` value. UI: [`SettingsForm.vue`](frontend/src/views/admin/SettingsForm.vue) Zernio tab (3rd key + 3 platform rows + Off option), [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) Reddit + re-shown Facebook tabs (YT absent), [`/admin/video-full`](frontend/src/views/admin/VideoFullDetail.vue) 7-target picker. Enabled on deploy (FB no fallback; Reddit `off` until live-probe). ~70 new tests green. **Pre-deploy BLOCKING**: connect FB Page + YT channel in the fbyt workspace + rotate pasted keys — [runbook](docs/runbooks/zernio-reddit-fb-yt-deploy.md). [plan](docs/plans/2026-06-16-reddit-zernio-crosspost-plan.md) · [design](docs/plans/2026-06-16-reddit-zernio-crosspost.md). NOT pushed.

- **2026-06-16** — **`video_full` (Video 60s) — 4th IG-repurpose mode: a source talking-head reel → Ali's ≈60s Indonesian reel (full-regenerate, no face-swap).** Dual-runtime by design: the **VPS** (Laravel) owns the job FSM + Telegram trigger + admin UI + Zernio publish; the **MacBook** runs the heavy pipeline (yt-dlp → whisper.cpp → ffmpeg scene/segment classifier → translate → Ali keyframe → Veo i2v w/ GROK failover → RVC/ElevenLabs voice-change → ffmpeg compose) as a Node daemon ([video-full-worker/](video-full-worker/), `node index.js`). **FSM**: 4 new [`RepurposeJobStatus`](backend/app/Enums/RepurposeJobStatus.php) states `queued_local → claimed_local → processing_local → uploaded` (then `→ drafted`); crash/re-queue edges `claimed/processing → queued_local`; per-segment regen edge `uploaded → processing_local`; all reachable from `failed`. **Schema**: migration `2026_06_16_000010` adds `repurpose_jobs.worker_*` cols (claimed_at/heartbeat_at/progress/step) + `video_full_segments` table ([`VideoFullSegment`](backend/app/Models/VideoFullSegment.php)). **Bridge API** (worker side, Sanctum token w/ ability `video-full:work` + `throttle:240,1`): [`VideoFullWorkerController`](backend/app/Http/Controllers/Api/VideoFullWorkerController.php) — `GET /worker/video-full/claim` (atomic `lockForUpdate` single-worker claim), `PUT /{id}/progress` (heartbeat + claimed→processing), `POST /{id}/segments` (idempotent manifest upsert), `POST /{id}/assets` (final MP4 → `uploaded`, or per-segment preview), `PUT /{id}/fail` (worker crash → Failed/retryable, no-op once out of in-flight states). **Admin** ([`VideoFullController`](backend/app/Http/Controllers/Api/Admin/VideoFullController.php), `auth:sanctum`): `GET /admin/video-full` (list), `GET /{id}` (detail + `worker_online`), `POST /{id}/regenerate-segment/{n}` (mark pending → bounce to processing_local), `POST /{id}/publish-zernio` (final reel → LI/IG/TikTok/Threads via [`ZernioPayloadBuilder::buildVideoFull`](backend/app/Services/ZernioPayloadBuilder.php) single-MP4 mediaItem + [`PublishRepurposeViaZernio`](backend/app/Jobs/PublishRepurposeViaZernio.php) mode-aware dispatch). **Trigger**: Telegram "🎥 Video 60s" button ([`TelegramNotificationService::sendRepurposeModePrompt`](backend/app/Services/TelegramNotificationService.php), [`TelegramWebhookController`](backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php)) — parks the job at `queued_local` (NO VPS capture dispatch), gated by setting `telegram_video_full_enabled` (default `false`). **Retry**: [`RepurposeRetryService`](backend/app/Services/RepurposeRetryService.php) has a `video_full` branch — re-queues to `queued_local` (the MacBook worker re-claims; NO VPS job dispatched). **Admin UI**: [`/admin/video-full`](frontend/src/views/admin/VideoFullList.vue) + [detail](frontend/src/views/admin/VideoFullDetail.vue) (worker-online pill, segment timeline, per-segment ↻ regenerate, final-video player, Zernio publish) via [`useVideoFull.js`](frontend/src/composables/useVideoFull.js). **Env**: `VIDEO_FULL_BRIDGE_URL`, `VIDEO_FULL_WORKER_TOKEN`, `GEMINIGEN_API_KEY`, `WHISPER_MODEL`, `RVC_*`/`ELEVENLABS_*` (MacBook only — see worker README). Tests: 25 backend (FSM/bridge/Telegram/Zernio + retry guard + fail endpoint) + 39 worker node + 4 FE vitest, all green. Additive — zero impact on blog/carousel/video_rebrand. [plan](docs/plans/2026-06-16-video-full-rebrand.md) · [runbook](docs/runbooks/video-full-deploy.md) · [worker README](video-full-worker/README.md) · [ADR](~/.claude/gaspol-knowledge/design-decisions/adr-2026-06-16-video-full-worker-runtime.md). NOT pushed.

- **2026-06-16** — **Fix: Retry button on a `drafted` blog repurpose job with `content_idea_id=NULL` was silently blocked.** Root cause (prod jobs 21 + 27): `FinalizeRepurpose::finalizeBlog` ran correctly and created ContentIdeas, but those ideas were later hard-deleted via `/admin/content-engine`. MySQL `ON DELETE SET NULL` FK cascade auto-nullified `repurpose_jobs.content_idea_id` — job stuck in `drafted` with no linked idea and no "Start Research" entry in Content Engine. `RepurposeRetryService::retry()` only allowed `status=failed` jobs, so the Retry button returned 422 forever. Fix: new recovery branch BEFORE the `failed` guard — when `status=drafted AND mode=blog AND content_idea_id=null` → `forceStatus(Extracted, 'retry_lost_content_idea')` + `FinalizeRepurpose::dispatch($job->id)`. `finalizeBlog` re-reads `$job->extracted` (already persisted) and re-creates the ContentIdea, identical to the original finalize call. A drafted blog job whose idea is STILL linked is NOT affected (422 unchanged — operator uses Content Engine). Tests: `test_retry_drafted_blog_with_lost_content_idea_reruns_finalize` + `test_retry_drafted_blog_with_linked_idea_still_rejects` (2 new in `RepurposeJobAdminControllerTest`). **After deploy: click Retry on jobs 21 + 27 to recover.** NOT pushed.
- **2026-06-16** — **"Regenerate all images" on IG-repurpose carousels now source-mirrors: 1 slide per source tool, slide count follows source caption (e.g. 10 tools → 12 slides), no 7-cap.** Root cause: `RegenerateLinkedInCarouselContent` called `dispatchCarouselGenEngine` directly and passed `$brief=[]` → `inferTargetSlides([])` → default 7 cap, no source-mirror branch reachable. Fix: new branch in [`RegenerateLinkedInCarouselContent::run`](backend/app/Jobs/RegenerateLinkedInCarouselContent.php) — when `isRepurposeDraft=true` AND `config('linkedin.repurpose_source_mirror_regenerate', true)` (new flag, default ON), calls `RepurposeCarouselBuilder::buildForDraftId($draftId)` → persists slides → dispatches `GenerateLinkedInCarouselImages`, then returns early (skips `/carousel-gen`). Falls back to `/carousel-gen` when builder returns `[]` (no numbered list in caption). **Two-flag separation**: auto-pipeline flag `repurpose_source_mirror` (env `LINKEDIN_REPURPOSE_SOURCE_MIRROR`, default **false**) stays OFF (360s budget can't hold sequential authoring); button-path flag `repurpose_source_mirror_regenerate` (env `LINKEDIN_REPURPOSE_SOURCE_MIRROR_REGENERATE`, default **true**) is ON (20-min job budget is safe). `WithoutOverlapping($draftId)->dontRelease()->expireAfter(1320)` added as Phase C. Tests: `LinkedInConfigFlagTest` (2 unit) + `RegenerateCarouselSourceMirrorTest` (6 feature: mirror-path, empty-list-fallback, flag-off, non-repurpose, slide-count-no-cap, WithoutOverlapping). NOT pushed — prod verify on draft 162 pending after deploy. [plan](docs/plans/2026-06-16-source-mirror-on-regenerate-button.md)
- **2026-06-16** — **video_carousel is now a first-class sosmed draft: calendar → /sosmed-drafts (not Social Studio) + per-platform IG/Threads captions.** Two reported bugs + a latent caption inconsistency. **(Bug 1 — route)** [`detailTarget`](frontend/src/views/admin/linkedinHelpers.js) dropped its `video_carousel`→`admin-repurpose-detail` deep-link; a video anchor now opens the LinkedIn draft detail (`admin-sosmed-draft-detail`) like any LinkedIn row (dead branch removed from [`LinkedInPostsCalendar`](frontend/src/views/admin/LinkedInPostsCalendar.vue) `openDetail`). **(Bug 2 — captions)** the anchor showed the branded `rewritten['caption']` but [`PublishRepurposeViaZernio`](backend/app/Jobs/PublishRepurposeViaZernio.php) shipped `igCaption()` (raw source) — see-≠-ship. New per-platform model layer on [`RepurposeJob`](backend/app/Models/RepurposeJob.php): `captionFor($platform)` (`rewritten["caption_$platform"]` → `rewritten['caption']` → `igCaption()`) + `setCaption()` (Threads hard-capped 500); [`FinalizeRepurpose`](backend/app/Jobs/FinalizeRepurpose.php) seeds `caption_instagram`+`caption_threads` at finalize; `PublishRepurposeViaZernio` now publishes `captionFor($this->platform)`. **(Full management on the draft detail)** [`LinkedInDraftController::show`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) enriches a video_carousel anchor with a `repurpose` block {id, composited_videos[], zernio_publish, caption_instagram, caption_threads}; new `PUT /admin/repurpose/{id}/captions` ([`RepurposeJobController::updateCaptions`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php), video_rebrand-gated); [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) gains a `video_carousel` branch — clip previews + separate IG/Threads caption editors (Save → captions API) + **Approve & Publish + Schedule** (reusing `POST /repurpose/{job}/publish-zernio`) + per-platform Zernio status chips + "Open in Social Studio" link (production-only). Composables: `useUpdateRepurposeCaptions` + reused `usePublishRepurposeZernio`. Tests: 16 new (captionFor 3, finalize-captions 1, publish-caption 1, caption-API 4, show-enrichment 2, detailTarget updated) + 34-suite regression green; frontend build clean. [plan](docs/plans/2026-06-16-video-carousel-draft-detail-captions.md). NOT pushed — Phase G (deploy + backfill job 26 captions) pending.
- **2026-06-16** — **Fix: "Schedule for later" on a video_rebrand job scheduled to Zernio but NEVER appeared on the Content Calendar (pre-feature orphan = no anchor).** Root cause (prod job 26, gaspol-debug): the job was finalized **2026-06-14**, before the calendar-anchor feature (`c6e2154d`) shipped, so its `finalizeVideoRebrand` ran the old path with `linkedin_post_id=NULL`. [`RepurposeJob::mirrorAnchorScheduled`](backend/app/Models/RepurposeJob.php) then **silently early-returned** on `videoAnchor()===null` → Zernio scheduling succeeded but no `LinkedInPost` calendar row was created (the Calendar only renders LinkedInPost rows). Fix: new [`VideoCarouselAnchorService::ensureFor`](backend/app/Services/VideoCarouselAnchorService.php) (idempotent — returns the existing anchor, else creates the minimal Post+translation+`video_carousel` `manual_review` LinkedInPost and links `linkedin_post_id`+`anchor_post_id`, no status transition); `mirrorAnchorScheduled`/`mirrorAnchorPublishedIfComplete` now call `ensureFor` (gated `mode==='video_rebrand'`) so a pre-feature orphan **self-heals onto the calendar** when scheduled/published instead of no-opping; [`FinalizeRepurpose::finalizeVideoRebrand`](backend/app/Jobs/FinalizeRepurpose.php) refactored to reuse the SAME factory (DRY — anchor creation now lives in one place). 2 pre-feature orphans on prod (jobs 26, 19); job 26 still needs a one-shot `mirrorAnchorScheduled` backfill (its schedule already fired, won't re-trigger). New jobs unaffected (already get an anchor at finalize). Tests: `VideoCarouselAnchorLazyCreateTest` (3: lazy-create-on-schedule, idempotent, non-video no-op) + 21 existing anchor/finalize/calendar suites green (24 total). NOT pushed.
- **2026-06-16** — **Fix: Zernio IG carousel "Image N: Image not found" — `ZernioImageNormalizer` returned a PHANTOM normalized URL when the write silently failed (perms).** Draft 163 published to Threads + TikTok fine but IG failed `400 "Instagram Image 2: Image not found at the provided URL"`. Root cause (proven on prod — both expected normalized files `sha1(rel).png` absent on disk yet handed to Zernio): [`ZernioImageNormalizer::normalizeForInstagram`](backend/app/Services/ZernioImageNormalizer.php) called `Storage::disk('public')->put(...)` **without checking the return value** — `Storage::put()` returns `false` (NOT an exception) on a permission-denied write, so the fail-open `catch` never fired, it logged "padded slide to IG ratio" as success, and returned `/storage/zernio-normalized/{hash}.png` for a file that was never written → Zernio 404'd it. The write failed because the dir was `www-data:www-data` **0755** but the `social-crosspost` queue worker runs as **claudesn** (group member, no group-write) — same cross-user class as the [`SharedDir`](backend/app/Support/SharedDir.php) video_rebrand fix, never applied here. Fix: (1) `SharedDir::ensure(dirname($outAbs))` forces the output dir 0775 so the worker can write; (2) capture the `put()` result + verify `$disk->exists($outRelative)` — on failure log a warning and **fail-open to the original URL** (worst case = the TRUE pre-existing IG ratio rejection, never a phantom 404). Threads/TikTok were never affected (they get RAW slide URLs, no normalization). **Ops recovery done on prod**: `chmod g+w` the normalized dir + `crosspost:backfill-zernio-urls` (Threads + TikTok had actually published — "Threads failed" was a false alarm from a null `external_url` = no "Open on Threads" link). IG sibling 33 still `failed` — a re-publish now succeeds (dir writable). Tests: `ZernioImageNormalizerWriteGuardTest` (2: happy-path persists + write-failure fails-open) + 3 existing + 18 Zernio builder/client/config green. NOT pushed.
- **2026-06-16** — **IG video carousel (`video_rebrand`) now enters the Content Calendar + leaves Social Studio + blank list thumbnail fixed.** Three things: **(Point 2 — thumbnail)** Social Studio list cover for a `video_rebrand` job fell back to `linkedinPost.carousel_slides[]` (which video jobs lack) → blank; [`RepurposeJobController::generatedCoverUrl`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php) now uses the first clip's hook `keyframe_url` (the still that seeds the first video), `index()` eager-loads `videoSlides` to avoid N+1. **(Point 1 — calendar hand-off)** `finalizeVideoRebrand` ([FinalizeRepurpose](backend/app/Jobs/FinalizeRepurpose.php)) now creates a **display-only `LinkedInPost` anchor** with a new `format='video_carousel'` ([`LinkedInPost::FORMAT_VIDEO_CAROUSEL`](backend/app/Models/LinkedInPost.php), DB `format` enum→string via migration `2026_06_15_000003`) + a minimal anchor Post/translation (post_id is NOT NULL) → the job appears in the Content Calendar (LinkedIn tab) and **leaves Social Studio** (`index()` `exclude_settled` now settles a `video_rebrand` job the moment `linkedin_post_id` is set, mode-aware vs carousel). **The anchor NEVER publishes to LinkedIn** — it publishes to IG + Threads via the existing Zernio path; every LinkedIn publisher is guarded by [`LinkedInPost::scopeExcludeVideoCarousel`](backend/app/Models/LinkedInPost.php) ([`PublishSlotOrchestrator`](backend/app/Console/Commands/PublishSlotOrchestrator.php), [`linkedin:auto-schedule`](backend/app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php), [`linkedin:prompt-schedule`](backend/app/Console/Commands/PromptScheduleReadyDrafts.php), defensive bail in [`GenerateLinkedInPost`](backend/app/Jobs/GenerateLinkedInPost.php)); the cross-post scan ignores it (format not in text/carousel). **(mirror)** scheduling/publishing via `publishZernio` mirrors onto the anchor (`RepurposeJob::mirrorAnchorScheduled` → awaiting_publish + scheduled_at; `mirrorAnchorPublishedIfComplete` in [`PublishRepurposeViaZernio`](backend/app/Jobs/PublishRepurposeViaZernio.php) → published once ALL platforms ship). Calendar card shows a 🎬 badge + deep-links to `/admin/repurpose/{id}` (Zernio publish UI) via pure `detailTarget()` in [linkedinHelpers.js](frontend/src/views/admin/linkedinHelpers.js). **LinkedIn has NO video-carousel format** (platform fact) — confirmed, hence the IG+Threads-only routing. 38 new tests green (legacy `ContentIdeaFactory`/`post_id=999999` suite fails pre-exist). [plan](docs/plans/2026-06-15-video-carousel-content-calendar.md)
- **2026-06-16** — **Trending topics: best-5 daily pick + enriched virality scoring.** `PullTrendingDaily` now selects ONLY the top N topics by `virality_score` DESC (default 5, env `TRENDING_DAILY_PICK_LIMIT`) after the virality threshold gate — previously imported ALL passing topics with no cap. `TopicScoringService::buildViralityPrompt` overhauled: Sonnet now receives full audience persona (Indonesian AI professionals 22-40, LinkedIn/TikTok), Ali's 5 content pillars (Vibe Coding, AI Agents, AI Automation, AI Image/Video Gen, LLMs), a STEPPS→score derivation formula (base 20 + 16/trigger + pillar/recency/carousel-fit adjustments), 4 calibration anchor examples, and heat/age metadata per topic. `carousel_fit` boolean added to the prompt output schema, merged onto each topic, and persisted in `content_ideas.virality_breakdown`. Cache bumped to `v2` to bust stale entries. No migrations. [`config/content.php`](backend/config/content.php) · [`PullTrendingDaily`](backend/app/Console/Commands/PullTrendingDaily.php) · [`TopicScoringService`](backend/app/Services/TopicScoringService.php). NOT pushed.
- **2026-06-16** — **"Balik ke Social Studio" — revive a CANCELLED carousel draft from the calendar.** A cancelled carousel in the Content Calendar day-detail was terminal (FSM only allowed `cancelled → generating` = full regenerate). New revive action un-cancels it WITHOUT regenerating: new FSM edge `cancelled → manual_review` in [`LinkedInPostStatus`](backend/app/Enums/LinkedInPostStatus.php) (rendered slides preserved) + [`LinkedInDraftController::revive`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) (`POST /admin/linkedin-drafts/{id}/revive`, mirrors `cancel`, 409 via `illegalTransition` from any non-cancelled status). Safe re the one-live-draft invariant — `cancel` never soft-deletes (the cancelled row stays the single live draft; scan idempotency treats it as live), so reviving introduces no duplicate. Frontend: [`useReviveLinkedInDraft`](frontend/src/composables/useLinkedInDrafts.js) (invalidates lists + `social-calendar`) + a cyan "↩ Balik ke Social Studio" button on cancelled cards in the [`LinkedInPostsCalendar`](frontend/src/views/admin/LinkedInPostsCalendar.vue) day-detail panel (LinkedIn-only; restructured the post loop so the revive button isn't nested in the card button). Tests: `LinkedInDraftReviveTest` (4) green; calendar spec mock extended (its 3 pre-existing fails unrelated). **Follow-up fix (2026-06-16):** revive ALSO clears the stale `scheduled_at` + `cancel_window_ends_at` — `cancel` never cleared them and `calendar()` pins on those timestamps, so a revived draft was staying ON the Content Calendar (still on its old slot) instead of leaving for Social Studio. Now `revive` `forceFill`s both to null after the FSM advance → the draft drops off the calendar (no date anchor) and returns to Social Studio as an unscheduled `manual_review` draft, re-schedulable from scratch (matches the docblock promise). +1 test `test_revive_clears_schedule_so_it_leaves_the_calendar` (5 green). NOT pushed.
- **2026-06-15** — **Fix: "Regenerate video" (IG GROK hook) re-used the STALE asset — Cloudflare immutable-cache + reused filename.** Draft 165: after re-rendering the cover 4× (→ `cover-v4.png`), regenerating the hook video kept animating an OLD cover. Root cause (proven on prod, NOT a keyframe-selection bug — `GenerateHookVideo` correctly reads the current `carousel_slides[0].image_url`): [`GeminiGenVideoService`](backend/app/Services/GeminiGenVideoService.php) wrote BOTH the GROK keyframe (`grok-frame-{ig}.jpg`) and the finished video (`grok-hook-{ig}.mp4`) to **deterministic filenames** under `/storage`, which Cloudflare serves `cache-control: public, max-age=2592000, immutable`. So regenerate overwrote the origin bytes but the unchanged URL kept serving the previously-cached copy — verified live: origin `grok-hook-32.mp4` mtime 11:00 but CDN `cf-cache-status: HIT, last-modified 04:45` (served the 4.7h-old video); GROK likewise fetched the stale immutable `grok-frame-32.jpg`. Fix: new `GeminiGenVideoService::carouselAssetPath($prefix,$ig,$ext)` appends `bin2hex(random_bytes(8))` so every render gets a brand-new URL → guaranteed cache MISS (mirrors the slide renderer's `-vN` collision suffix, which already dodged this; the GROK path never got it). Both `prepareFrame` + `finalizeHookVideo` use it. **Lesson:** any regenerable `/storage` asset MUST use a unique filename — overwriting a fixed name is invisible behind the immutable CDN cache. **Existing draft 165: operator re-clicks "Regenerate video" AFTER deploy** (new unique URL picks up cover-v4); or one-off purge the two CF URLs. Tests: `GeminiGenVideoAssetPathTest` (2) + 16/16 hook-video suites green. NOT pushed.

- **2026-06-15** — **Zernio post-publish bug trio (IG video cover, carousel-only first-comment, async Open-on links).** Three fixes on the live Zernio path, all in/around [`ZernioPayloadBuilder`](backend/app/Services/ZernioPayloadBuilder.php): (1) **IG video cover** — `buildInstagram` now DROPS the static cover image (slide 1) when a GROK hook video is ready (`array_slice($images, 1)`) — the video IS the animated cover, so it leads the carousel in the cover's place instead of publishing video + redundant still. (2) **Carousel-only first comment** — `buildInstagram` suppresses `platformSpecificData.firstComment` (the "Full article: …/r/…" link) when the parent [`LinkedInPost::isRepurpose()`](backend/app/Models/LinkedInPost.php) is true; that link belongs ONLY to blog / blog+carousel posts (an IG-repurpose carousel anchors an unpublished Post whose `/blog/{slug}` 404s), defensive even if a stale `link_comment` lingers. (3) **"Open on IG/TikTok/Threads" links never lit up** — Zernio's createPost is ASYNC so IG/TikTok return no `platformPostUrl` at publish time → `external_url` stored null → the frontend `publishedExternalLinks` (already reads `external_url`, serialized in `LinkedInDraftController::show`) had no data. New [`BackfillZernioPostUrls`](backend/app/Console/Commands/BackfillZernioPostUrls.php) cron (`crosspost:backfill-zernio-urls`, every 2 min via [`ScheduledCommandSeeder`](backend/database/seeders/ScheduledCommandSeeder.php)) polls `GET /posts/{zernio_post_id}` for published siblings missing `external_url` and backfills the live URL once Zernio finishes — bounded (last 7d), per-platform workspace key, getPost-fail → log+skip+retry-next-tick (same "cron is sole completion driver" pattern as GROK/Veo polls — Zernio gives no webhook). Tests: builder +3 (cover-drop, no-hook keeps all, repurpose suppresses first-comment) + new `BackfillZernioPostUrlsTest` (4) + existing hook-video test inverted (was assert-cover-kept); 16/16 green (9 `CrossPostSchemaTest` fails pre-existing/environmental). NOT pushed.
- **2026-06-15** — **Fix Zernio IG + TikTok cross-post rejections + replace "Publish to Publer" UI with "Open on <platform>" links.** Diagnosed two platform-content 400s on draft 153: **IG** rejected a slide at `0.75:1` (outside IG carousel's 0.75–1.91 ratio window); **TikTok** rejected a 241-char caption (photo-slideshow title caps at 90). Fixes: new [`ZernioImageNormalizer`](backend/app/Services/ZernioImageNormalizer.php) (pure ratio gate + letterbox-to-compliant-canvas via Intervention, corner-pixel pad color, cached under `storage/app/public/zernio-normalized/`, **fail-open** → original URL on any error) wired into [`ZernioPayloadBuilder::buildInstagram`](backend/app/Services/ZernioPayloadBuilder.php) (every IG image slide normalized before send; builder now constructor-injects the normalizer); `buildTiktok` hard-caps the title to 90 chars (`capTiktokTitle`, mirrors the Threads 500-cap). Frontend [LinkedInDraftDetail.vue](frontend/src/views/admin/LinkedInDraftDetail.vue): removed the stale "Publish to Publer" manual-republish row + its dead script cluster (publisher is Zernio now), added an **"Open on Instagram/TikTok/Threads"** external-link button per cross-post sibling that actually published (`status=published` + `external_url`), beside "Open on LinkedIn". Tests: normalizer 3 (pure logic + fail-open) + builder 2 (TikTok cap, IG-runs-through-normalizer); 243 Zernio/Repurpose green. NOT pushed.
- **2026-06-15** — **video_rebrand carousel → Zernio publish (IG + Threads), Approve + Schedule.** Gives a `video_rebrand` repurpose job a real publish path — the reason for the publisher switch (manual API probe live-validated Zernio publishes a 9-clip ALL-VIDEO carousel: IG ✅ instagram.com/p/DZmocjYkXuB + Threads ✅ threads.com/@alisadikinma/post/DZmo0aLERm1; TikTok ❌ 400 "single video file only" → excluded). New: migration `2026_06_15_000002` adds FSM-neutral `repurpose_jobs.zernio_publish` JSON (per-platform `{status,post_id,request_id,url,error,updated_at}`; job stays `drafted`); [`RepurposeJob`](backend/app/Models/RepurposeJob.php) `compositedVideoUrls()`/`igCaption()`/`zernioPublishState()`; [`ZernioPayloadBuilder::buildRepurposeVideoCarousel`](backend/app/Services/ZernioPayloadBuilder.php) (video mediaItems from composited public URLs, IG/Threads, cap 10, Threads 500-char cap); [`PublishRepurposeViaZernio`](backend/app/Jobs/PublishRepurposeViaZernio.php) job (`social-crosspost` queue; master-switch + account gates; idempotency via `zernio_publish[platform].post_id` + persisted `request_id`; publishNow vs FUTURE-only `scheduledFor`; row-locked JSON merge so IG/Threads don't clobber); [`RepurposeJobController::publishZernio`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php) → `POST /admin/repurpose/{id}/publish-zernio` (validates `platforms[] in:instagram,threads` + future `scheduled_at`; 503 master-off, 422 not-composited/past-schedule/no-account-configured, 202 per-platform dispatch). Frontend: [RepurposeJobDetail.vue](frontend/src/views/admin/RepurposeJobDetail.vue) gains **"Approve & Publish (IG + Threads)"** + **"Schedule for later"** (datetime modal) on composited video_rebrand jobs + per-platform publish-status chips (poll keeps ticking while `publishing`), `usePublishRepurposeZernio` in [useRepurposeJobs.js](frontend/src/composables/useRepurposeJobs.js). 19 tests green (model 3 + builder 4 + job 5 + api 7). Still gated by `ZERNIO_PUBLISH_ENABLED` (default OFF) + rotate compromised keys before live use. [plan](docs/plans/2026-06-15-repurpose-video-zernio-publish.md). NOT pushed.
- **2026-06-15** — **Scheduled cross-post publishing now routes through Zernio too (Option 1 — keep the local slot brain).** The Zernio Phase G work only routed the 4 *immediate* publish sites; the **scheduled** path — `social:publish-slot` cron ([`PublishSlotOrchestrator`](backend/app/Console/Commands/PublishSlotOrchestrator.php)) → at slot tick → `PostizPublishDispatcher` → `PublishViaPubler` — still bypassed `PublisherResolver`, so timed IG/TT/TH posts never reached Zernio. Fix: the orchestrator's carousel sibling loop is now publisher-aware — `PublisherResolver::for($platform)==='zernio'` → gate on `ZernioPayloadBuilder::isPlatformEnabled` + `PublisherResolver::dispatchPublish` (Zernio); else the existing Postiz/Publer dispatcher path (Facebook always Publer). The local fixed-slot scheduler stays the single scheduling brain (readiness/postpone gating intact) — Zernio just publishes at the tick. Guard: [`PublishViaZernio::applyScheduling`](backend/app/Jobs/PublishViaZernio.php) now only sets `scheduledFor` when `scheduled_at` is in the **future** (`isFuture()`), so the now-past slot-tick `scheduled_at` → `publishNow` (a past `scheduledFor` would be rejected by Zernio). Covers the Telegram-schedule path too (same orchestrator). Tests: +3 (orchestrator Zernio-dispatch, Zernio-unconfigured-skip-no-Publer-fallback, past-schedule→publishNow); 16/16 affected green. NOT pushed.
- **2026-06-15** — **Zernio settings: Verify auto-fills account IDs.** `Verify` already discovers each workspace's accounts; [SettingsForm.vue](frontend/src/views/admin/SettingsForm.vue) now populates the matching per-platform `zernio_{platform}_account_id` field by platform (operator just clicks Save) instead of hand-copying 24-char hex IDs. Fixed the misleading `acc_...` placeholder (Zernio IDs are bare hex, no prefix). Pushed `51a862ee`.
- **2026-06-15** — **Figure cover: preserve floating cards + bilingual headline — "only the subject changes".** After the face-binding fix worked, the figure cover was missing the floating topic cards + the headline a normal cover has. Root cause (gaspol-review-confirmed, NOT the face fix): [`CarouselCoverFigureEnricher::enrich()`](backend/app/Services/CarouselCoverFigureEnricher.php) OVERWROTE the whole carousel-gen cover `image_prompt` with a bare interaction scene from [`VideoHookSceneAuthor`](backend/app/Services/VideoHookSceneAuthor.php), discarding the floating elements + headline the plugin had authored. Fix (operator chose "preserve cover, change subject only"): the enricher now passes the original carousel-gen cover prompt as a `$basePrompt` to `author(...)`; `VideoHookSceneAuthor::buildCoverSubjectRewritePrompt` runs a SUBJECT-REWRITE — the model edits the base in place, changing ONLY the human subject (single creator → creator + figure interaction) and preserving the headline, every floating card, the composition, and all `{{PLACEHOLDER}}` tokens verbatim (figure still nameless = "reference image 2", later anchored to `subject-2.<ext>` by the enhancer). No base → original fresh-scene behaviour (video keyframe path unchanged). Plus 2 gaspol-review hardening fixes on the prior commit: `str_ireplace`→`strtr` (single-pass, kills the "reference image 10" corruption hazard) and on neutral-ref host failure `dispatchOne` now FAILS the slide for retry instead of silently dispatching the celeb-name mismatch. Tests: +3 (`VideoHookSceneAuthorMediumTest` rewrite mode), 33/33 carousel-cover suites green. **Existing figure covers need a fresh "Regenerate All Images" (re-authors the rich cover, then preserves it).** Standard → vault [[30-Knowledge/image-gen-shared]] §8.
- **2026-06-15** — **Fix: 2-subject carousel cover — figure (ref image 2) rendered as a random person.** Root cause (operator-diagnosed): with ≥2 face references, nano-banana-pro binds each face by its FILE HANDLE, NOT by a "reference image N" ordinal. The creator (ref 1) bound because it also had a rich textual description; the figure (ref 2) was anchored ONLY as "the person matching reference image 2" (no description, name deliberately withheld) → zero usable anchor → the model invented a generic face (the OpenAI/Altman cover rendered a random Asian man for the figure). Fix: [`CarouselSlideEnhancer`](backend/app/Services/CarouselSlideEnhancer.php) now re-anchors the 2-subject figure cover to NEUTRAL per-render filenames — the mandate + the authored body scene reference `subject-1.png` / `subject-2.jpg` (ext from the source URL) instead of "reference image 1/2", and it emits `face_ref_aliases`; [`LinkedInCarouselImageService::hostNeutralRefs`](backend/app/Services/LinkedInCarouselImageService.php) re-hosts each ref under that exact neutral basename (`storage/.../linkedin-carousel/refs/{draft}/{slide}/subject-N.ext`) so what GeminiGen sees matches the prompt. **The public figure's NAME never appears — not in the prompt, not in the filename** (operator rule: the original `Q…_sam-altman.jpg` basename both leaked the name and gave only an ordinal anchor; the figure's photo is still uploaded, just under a neutral handle). Single-creator covers (1 ref) untouched; on any re-host failure it falls back to the original refs (degraded, not broken). Applies to both "Regenerate All Images" and single cover "Re-render". **Existing wrong covers need a re-render to pick up the new prompt.** Same fix is pending for the video-hook keyframe path (`VideoHookSceneAuthor` + `GeminiGenVideoService::dispatchKeyframe` — identical 2-ref shape). Tests: 4 new in [`CarouselSlideEnhancerFigureInteractionTest`](backend/tests/Unit/CarouselSlideEnhancerFigureInteractionTest.php), 20/20 enhancer green. Standard → vault [[30-Knowledge/image-gen-shared]] §8. Pushed `8c81fd9c` (figure binding verified live — creator + Sam Altman render precisely; floating/headline follow-up above).
- **2026-06-15** — **Zernio added as the PRIMARY cross-post publisher for IG/TikTok/Threads (Publer kept as fallback).** New parallel adapter mirroring the Publer trio — [`ZernioClient`](backend/app/Services/ZernioClient.php) (REST `zernio.com/api/v1`, plain `Bearer`, **two-workspace key routing** via `forPlatform()`: IG/TikTok→`zernio_api_key_igtt`, Threads→`zernio_api_key_threads`; createPost/listAccounts/getPost; 409→idempotent-duplicate, 401/403/4xx→`ZernioApiException`, 5xx→retry), [`ZernioPayloadBuilder`](backend/app/Services/ZernioPayloadBuilder.php) (Zernio `mediaItems`/`platforms` shape; **public CDN slide URLs passed directly — NO media upload/poll**, unlike Publer; IG = native mixed video+image carousel with hook video item 0 + `platformSpecificData.firstComment`; TikTok image-only ≤35; Threads image-only ≤10 + 500-char cap; replicates Publer's app-hosted-URL + local-mirror recovery), [`PublishViaZernio`](backend/app/Jobs/PublishViaZernio.php) (`social-crosspost` queue, publishNow|scheduledFor, stable `x-request-id` persisted before call + `zernio_post_id` guard = two-layer idempotency). Routing: [`PublisherResolver`](backend/app/Support/PublisherResolver.php) (`for`/`isPlatformEnabled`/`publishedIdColumn`/`dispatchPublish`) selects publisher per platform via setting `crosspost_publisher_{platform}` (default `zernio`); wired into ALL 4 publish sites (auto fan-out [`BaseSocialGenerationService`](backend/app/Services/BaseSocialGenerationService.php), Approve [`HandlesCrossPostDraftActions`](backend/app/Http/Controllers/Api/Admin/Concerns/HandlesCrossPostDraftActions.php), bulk + single re-publish [`LinkedInDraftController`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php)) — gates + published-id column now publisher-aware; Publer/Postiz adapter classes untouched. Schema: migration `2026_06_15_000001` adds `zernio_post_id` + `zernio_request_id` to `instagram_posts`/`tiktok_posts`/`threads_posts`. New `settings` group `zernio` (8 keys: 2 encrypted workspace API keys + 3 account ids + 3 publisher selectors) via [`ZernioSettingsSeeder`](backend/database/seeders/ZernioSettingsSeeder.php). Admin: `GET/PUT /api/admin/settings/zernio` + `POST /api/admin/settings/zernio/verify` (masked keys, encrypted writes, selector-validated) → new **Zernio** sub-tab beside LinkedIn/Publer in [SettingsForm.vue](frontend/src/views/admin/SettingsForm.vue) (2 keys + per-workspace Verify + 3 manual account-id inputs + 3 publisher selectors). Env: `ZERNIO_*` in `config/social-cross-post.php` (no media-poll keys). 38 tests green. **Pending (Phase J, needs rotated live keys):** verify GROK hook video is 4:5 for the IG carousel first-item ratio; confirm `listAccounts` accountId shape; confirm content-hash 409 doesn't trip legit re-publish. [plan](docs/plans/2026-06-15-zernio-publisher-integration.md). NOT pushed.
- **2026-06-15** — **Fix: video_rebrand hook re-skin ALWAYS failed (job #26) — ffmpeg "Permission denied", not "clip expired".** Root cause = cross-user filesystem perms: the initial keyframe/Veo/compose render writes `storage/app/public/repurpose/{id}/composited/` as **www-data** (php-fpm), but the credit-free hook/CTA re-skin ([`ReskinBookendSlide`](backend/app/Jobs/ReskinBookendSlide.php) → [`GeminiGenVideoService::finalizeVeoClip`](backend/app/Services/GeminiGenVideoService.php)) runs in the **claudesn** queue worker. The dir was created mode **0755** (php-fpm umask 022 silently downgraded `mkdir(0775)` → no group-write), so claudesn (∈ www-data group) couldn't write its output → ffmpeg failed opening the file. The DB `last_error` "could not re-fetch the rendered clip (may have expired — use Re-render)" was MISLEADING (download + crop were fine — proven: clip HTTP 200, expires Jun 21) and pushed the operator toward a credit-burning Re-render. Job 19 worked only because its dir happened to come out 0775. Fix: new [`App\Support\SharedDir::ensure()`](backend/app/Support/SharedDir.php) = mkdir + explicit `@chmod(0775)` (chmod ignores umask) so shared dirs stay group-writable whoever creates them first; wired into `finalizeVeoClip`/`prepareFrame`/`finalizeHookVideo`, [`VideoRebrandComposer`](backend/app/Services/VideoRebrandComposer.php), [`VideoChromeRenderer`](backend/app/Services/VideoChromeRenderer.php) (×3). `ReskinBookendSlide` error message neutralized (no false "expired" steer). **Already-broken job #26 unblocked via one-off `chmod -R g+w` on prod** (operator just re-clicks Re-skin — no Re-render). Tests: `SharedDirTest` (2) + 49 video_rebrand suite green.
- **2026-06-15** — **CI hygiene: `tests` workflow no longer double-runs on `main` + is green again.** Every push to main fired TWO workflows ([deploy.yml](.github/workflows/deploy.yml) `Deploy to VPS` + [tests.yml](.github/workflows/tests.yml) `tests`) — that's the "2 runs per commit", and the `tests` run was perpetually red. Fix: (1) `tests.yml` now triggers on `pull_request` + `push: branches-ignore:[main]` only — main is the deploy branch, code is gated at PR time, so a main push = deploy only (one run). (2) Made the IG-repurpose filter green by fixing the real failures it caught: [`PostFactory`](backend/database/factories/PostFactory.php) + [`CategoryFactory`](backend/database/factories/CategoryFactory.php) were EMPTY → every `Post::factory()` (incl. `LinkedInPostFactory`'s no-existing-post fallback) threw `NOT NULL: posts.category_id` on the sqlite CI DB (the long-documented "Post::factory() needs explicit category_id" gotcha — now fixed at the factory level so it can only help). [`ReapRepurposeArtifactsTest`](backend/tests/Feature/ReapRepurposeArtifactsTest.php) was missing the `RefreshDatabase` trait (`no such table: repurpose_jobs`). Filter now 191/191 green; bonus: the 5 `CrossPostScanWidenedGateTest` legacy `category_id` failures are fixed too. NOT pushed.
- **2026-06-15** — **Fix: 2-subject public-figure carousel cover rendered the creator as a generic bald-glasses everyman, not Ali** (draft 165, Ali + Sundar Pichai). Root cause (NOT a prompt/order bug — prompt correctly named creator=ref-image-1 / figure=ref-image-2 with an anti-blend TWO-subject mandate, and both face URLs resolved 200): [`LinkedInCarouselImageService`](backend/app/Services/LinkedInCarouselImageService.php) flat-merges `face_refs` + `file_urls` into ONE GeminiGen `file_urls` bucket, so the **brand logo — itself a bald-with-glasses cartoon FACE — landed as a third identity reference**. On a single-creator slide that's harmless, but on a 2-subject cover the generic bald-glasses logo competed with Ali's real (Asian) face for the "creator" subject and blended → Ali's likeness drifted to a generic Western bald-glasses man; Sundar (a singular, distinct, famous face with no competing ref) bound cleanly. Fix: [`CarouselSlideEnhancer`](backend/app/Services/CarouselSlideEnhancer.php) now DROPS the brand-logo reference on a 2-subject cover (`layout_hint==='cover' && entity_face_ref!==''`) — the brand icon still renders from the `appendBrandChrome` text instruction. Targeted (single-creator covers + all other slides unchanged). **To fix already-rendered drafts: operator clicks "Re-render image" on slide 1** (single cover re-render goes through the same enhancer → logo now dropped; `figure_enriched=true` is preserved so the figure/scene stays). Tests: 2 new in [`CarouselSlideEnhancerFigureInteractionTest`](backend/tests/Unit/CarouselSlideEnhancerFigureInteractionTest.php). NOT pushed.
- **2026-06-15** — **Carousel image→video (GROK IG hook) is now MANUAL-TRIGGER ONLY** (operator request). [`ScanLinkedInForCrossPost::createInstagram`](backend/app/Console/Commands/ScanLinkedInForCrossPost.php) no longer auto-dispatches `GenerateHookVideo` on IG fan-out — it still creates the IG sibling row (enough for the draft-detail Image|Video toggle, since `hasHookVideo` only needs `format=carousel` + an `instagram_post`), so the operator starts the clip on demand from the "Video" tab → `POST /admin/linkedin-drafts/{id}/regenerate-hook-video`. `PollHookVideos` only RECOVERS rows already pending/failed, so nothing auto-starts a hook video anymore. Inverted [`CrossPostScanDispatchesHookVideoTest`](backend/tests/Feature/CrossPostScanDispatchesHookVideoTest.php) to assert sibling-created-but-no-auto-dispatch (`Queue::assertNotPushed`). NOT pushed.
- **2026-06-15** — **video_rebrand bookend HOOK + CTA → bilingual (Indonesian primary + English companion)**. The tool slides were already bilingual (`header_desc`/`header_desc_en`), but the two Veo bookend overlays were single-language: the hook title overlay rendered only the captured source headline (English, e.g. "AI Tools That Save Hours") and the CTA ask card was hardcoded English ("Found this useful? / Follow @… for more AI Tools"). Now both lead Indonesian with an English companion line. **Hook**: new [`RepurposeHookTitleResolver`](backend/app/Services/RepurposeHookTitleResolver.php) (one light text-only CLI call via `RunsRepurposeClaudeCli`) localizes the source headline → `{id, en}`, cached onto `repurpose_jobs.extracted` (`source_hook_title_id`/`source_hook_title_en`) so it runs ONCE per job (every later re-skin is a cache hit, no CLI); graceful — a CLI miss keeps the original as the ID line with no companion and is NOT cached (so a re-skin retries). [`VideoBookendOverlayApplier::applyHookTitle`](backend/app/Services/VideoBookendOverlayApplier.php) now resolves the pair and passes the EN companion as a new `$subtitle` arg to [`VideoChromeRenderer::renderHookTitle`](backend/app/Services/VideoChromeRenderer.php) → [`video-chrome.cjs`](scripts/repurpose/video-chrome.cjs) `--mode hook` renders ID h1 + smaller gold `.hsub` companion (omitted when empty/identical). **CTA**: [`buildCtaOverlayHtml`](scripts/repurpose/video-chrome.cjs) static-rewritten bilingual ("Bermanfaat? / Ikuti @… untuk lebih banyak AI Tools" + EN companion) — still exactly ONE command, no Save/Comment stacking, no comment→DM promise. No migration (free-form JSON keys). **To fix an already-finalized job (e.g. #26): operator clicks Re-skin on the hook + CTA slides** (`ReskinBookendSlide`, credit-free) after deploy. Config: `services.repurpose.model_hook_translate` (`REPURPOSE_MODEL_HOOK_TRANSLATE`, default sonnet). Tests: resolver 4 (PHP) + node hook 3 + node cta 1 new; full repurpose/video suite green (PollRebrandAssets 20, ComposeTool/Video, ReskinBookend, VideoHookTitleOverlay). NOT pushed.
- **2026-06-15** — **Repurpose carousel: source-mirrored 1-tool-per-slide (bilingual)**. IG-repurpose IMAGE carousels now follow the SOURCE's tool list — one Tool/Skill/Plugin per slide — instead of re-narrating into a flat 7-slide story (which crammed 2–3 tools/slide because [`inferTargetSlides`](backend/app/Services/LinkedInGenerationService.php) hard-caps at 7). Key insight: the reliable tool list is the post CAPTION's numbered list (`extracted.caption`) + fact-checked `claims`, NOT the captured frames (the IG capture over-grabs the profile grid — job 25 had 28 frames, only 1 real tool slide). New [`RepurposeCarouselBuilder`](backend/app/Services/RepurposeCarouselBuilder.php): `parseToolList` (caption→tools), `authorSlide` (per-tool bilingual `copy_id`+`copy_en`+`image_prompt` via a light CLI call — opsi A, visuals deferred to the carousel-gen bundle like [`VideoHookSceneAuthor`](backend/app/Services/VideoHookSceneAuthor.php)), `buildSlides` (cover + 1 slide/tool + cta; per-slide author failure degrades to a deterministic non-empty fallback). Per-slide INDEPENDENT authoring → no Sonnet truncation (the reason for the old 7-cap). Hook + CTA + every body slide are bilingual ID-primary + EN. Wired via a branch in [`applyCarouselGenAdapter`](backend/app/Services/LinkedInGenerationService.php) (`$isRepurpose` + parseable list → mirror; else fall through to `/carousel-gen` unchanged; original blog→carousel never touches it) — keeps `persistAndRoute` (FSM + caption + image dispatch + cross-post) intact. Slide-author model + refs default to sonnet + `carousel-gen.refs_pipeline` (no VPS config change). Tests: parser 6 + builder 8 + router 4 new. **AUTO-PIPELINE STILL GATED OFF** by `config('linkedin.repurpose_source_mirror')` (env `LINKEDIN_REPURPOSE_SOURCE_MIRROR`, default false) — Phase E on prod measured >10 min for 10 tools, far past the 360s auto-pipeline queue budget. **BLOCKER before enabling: parallelize per-slide authoring** (Process pool). **BUTTON PATH NOW ON** via a separate flag `config('linkedin.repurpose_source_mirror_regenerate')` (env `LINKEDIN_REPURPOSE_SOURCE_MIRROR_REGENERATE`, default **true**) — the "Regenerate all images" job has a 20-min budget so sequential authoring fits. See 2026-06-16 entry + [plan](docs/plans/2026-06-16-source-mirror-on-regenerate-button.md). Pushed (gated). [original plan](docs/plans/2026-06-15-carousel-one-tool-per-slide.md)
- **2026-06-15** — **Fix: public-figure carousel cover never fired**. [`CarouselCoverFigureEnricher::topic()`](backend/app/Services/CarouselCoverFigureEnricher.php) read `$slide['copy']` but carousel-gen slides store text in `copy_id`/`copy_en` → topic always empty → `markResolved('empty_topic')` with no figure, for every draft (the test fixtures also used `copy`, so it shipped green). Now reads `copy_id ?? copy_en ?? copy`; fixtures switched to the real shape. Also loosens the IG-repurpose gate for Tools/Plugins/Skills topics (`isToolsSkillsPluginsTopic`, operator rule) so repurpose carousels naming a figure get the interaction; non-tools repurpose stays creator-only. Existing drafts carry `figure_enriched=true` → need "Regenerate All Images" (not cover-only re-render) to pick it up. Tests: 7 enricher + 17 related green. Pushed (`dba5fec3`).
- **2026-06-15** — **Fix: title-less video_rebrand cards in Social Studio**. `RepurposeJobController::derivedTitle()` only checked `rewritten.title`→`extracted.caption`, but video_rebrand jobs skip the rewrite + have no caption → "Untitled repurpose". Now falls back to the model's `displayTopic()` (tool-slide header titles → source host → job #id). Tests: `RepurposeJobListTitleTest` 12 passed. Pushed.
- **2026-06-14** — **video_rebrand credit-free bookend RE-SKIN (hook/CTA)**. New cheap path to pick up a changed title/logo/CTA-overlay treatment WITHOUT re-rendering Veo (no keyframe, no i2v, ~0 credits): re-download the bookend's stored `veo_url` (the already-paid provider output) via [`GeminiGenVideoService::finalizeVeoClip`](backend/app/Services/GeminiGenVideoService.php) → re-crop to the plain 4:5 base → re-apply the brand overlay. New [`ReskinBookendSlide`](backend/app/Jobs/ReskinBookendSlide.php) job (FSM-neutral, like `ComposeToolSlides` — only mutates the one slide's `composited_status`/`composited_path`) + endpoint `POST /admin/repurpose/{id}/slides/{n}/reskin` ([`RepurposeJobController::reskinSlide`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php) — 422 `NO_RENDERED_CLIP` when `veo_url` absent / `NOT_A_BOOKEND` on a tool slide). DRY: extracted the hook-title + CTA-ask overlay orchestration out of `PollRebrandAssets` into the shared [`VideoBookendOverlayApplier`](backend/app/Services/VideoBookendOverlayApplier.php) (`apply(slide)` role-branches hook→title/logo, cta→Follow ask), reused by both the first finalize and the re-skin. Frontend: green **Re-skin** button on each hook/CTA slide in [RepurposeJobDetail.vue](frontend/src/views/admin/RepurposeJobDetail.vue) (`useReskinRepurposeSlide`), tool slides keep their free Regenerate. The widened detail poll already tracks `composited_status` so the download link refreshes live. 43 tests green (8 new re-skin: 4 job + 4 endpoint). Committed, NOT pushed.
- **2026-06-14** — **video_rebrand hook title + brand logo + single-command CTA**. (1) **CTA = ONE command** — the CTA ask overlay ([`video-chrome.cjs`](scripts/repurpose/video-chrome.cjs) `--mode cta`) dropped the stacked Save/Comment rows; now just "Follow @alisadikinma for more AI Tools" (per the carousel/video CTA standard — exactly one engagement command). (2) **Hook TITLE from the IG source** — the original creator's cover headline is captured by [`VideoSlideExtractor`](backend/app/Services/VideoSlideExtractor.php) onto `repurpose_jobs.extracted.source_hook_title` BEFORE its source_hook row is dropped; [`RepurposeJob::videoHookTitle()`](backend/app/Models/RepurposeJob.php) resolves it (fallback: first IG caption line). (3) **Hook brand LOGO auto-detected** — [`VideoHookSceneAuthor`](backend/app/Services/VideoHookSceneAuthor.php) now also returns `brand_name` (dominant topic brand, e.g. "Google"); [`GenerateRebrandAssets::resolveHookBrandLogo`](backend/app/Jobs/GenerateRebrandAssets.php) resolves a license-clean logo via [`EntityReferenceService`](backend/app/Services/EntityReferenceService.php) (`findOrFetch($brand, 'logo')`) → `extracted.hook_brand_logo` (best-effort: corporate logos that fail the CC-license gate → no logo, hook ships title-only). (4) **Overlay wiring** — new `video-chrome.cjs` `--mode hook` renders a transparent 1080×1350 title+logo card (bottom-third over a scrim, logo chip above the headline); [`VideoChromeRenderer::renderHookTitle`](backend/app/Services/VideoChromeRenderer.php) + generalized [`VideoRebrandComposer::overlayClip`](backend/app/Services/VideoRebrandComposer.php) (`overlayCta` now delegates) composite it onto the hook clip in [`PollRebrandAssets`](backend/app/Console/Commands/PollRebrandAssets.php) on hook finalize (mirrors the CTA overlay; non-fatal). 41 tests green (PHP + node). Committed.
- **2026-06-14** — **video_rebrand bookend i2v: Veo-first → GROK-failover** (root-cause fix for the recurring `AUDIO_FILTERED` + figure-hook walls). Veo 3.x has TWO hard Google limits on bookend clips: it ALWAYS generates audio (no off-param) so its audio safety filter trips nondeterministically (`PUBLIC_ERROR_AUDIO_FILTERED`), and it refuses to animate a recognizable celebrity in the source frame (`PUBLIC_ERROR_PROMINENT_PEOPLE_FILTER_FAILED`, triggered by the FACE not the prompt words). **GROK (xAI grok-video) clears both** (audio stripped on download; xAI ≠ Google) and is the existing IG-hook generator. **Policy (Ali): Veo stays DEFAULT (quality); GROK = failover only** — (a) **public figure on the keyframe → GROK immediately** ([`GenerateRebrandAssets::buildHookKeyframe`](backend/app/Jobs/GenerateRebrandAssets.php) sets `video_provider='grok'`, Veo never tried); (b) **non-figure Veo failure (audio/timeout) → retry Veo 3× FIRST**, then [`PollRebrandAssets::recoverJob`](backend/app/Console/Commands/PollRebrandAssets.php) fails over to GROK (provider flip + budget reset → GROK gets its own 3 retries; job fails only when BOTH exhaust). New: `repurpose_video_slides.video_provider` col (migration `2026_06_14_000002`), [`GeminiGenVideoService::dispatchGrokClip`](backend/app/Services/GeminiGenVideoService.php) (GROK only accepts `aspect_ratio=2:3`, 9:16→HTTP 400; 2:3 cropped to 4:5 by the shared `finalizeVeoClip`), `GenerateRebrandAssets::GROK_PROMPT_HOOK/CTA` (motion-only, NO audio clause), `PollRebrandAssets` dispatch-by-provider + `checkCompletion` defers failing a job while a Veo→GROK failover is still possible, manual regen resets `video_provider→veo`. A Veo-stage prominent-people refusal now **keeps the figure** (failover to GROK) instead of dropping it. Verified live on job #19 (Ali+Sundar): Veo failed audio+figure, GROK `status=2` clean. 49 tests green. WHY/standard → vault `30-Knowledge/video-pipeline-shared.md` §0.1. Committed, NOT pushed.
- **2026-06-14** — **video_rebrand error-aware retry + observability (Part A)**: fixes the asset-retry loop in [`PollRebrandAssets`](backend/app/Console/Commands/PollRebrandAssets.php). (1) **Veo-only retry** — `recoverJob()` routes each failed bookend by which STAGE broke: a veo-only failure (keyframe `done` + url present) re-dispatches the Veo clip DIRECTLY from the stored `keyframe_url` (no keyframe re-render, no SSH hook re-author, no Extracted bounce); keyframe-broken still full-resets + bounces. Fixes the bug where a Veo flake threw away a good keyframe. (2) **Error-aware prompt** — new [`VideoGenErrorClassifier`](backend/app/Services/VideoGenErrorClassifier.php) (static, LLM-free; classifies `error_code`+`error_message` → audio_filtered / prominent_people / content_policy / transient / unknown) drives [`VideoGenPromptDegrader`](backend/app/Services/VideoGenPromptDegrader.php): audio_filtered → barer audio bed, retry-3 → near-still motion, transient → unchanged, content_policy → static safe keyframe. New `repurpose_video_slides.last_error_class` column persists the class through `recover()`'s blank. (3) **Exhaustion → Telegram** — `checkCompletion()` exhaustion (was a SILENT Failed transition) now fires `sendRepurposeAssetsFailed()` with a one-tap HMAC **inline Retry button** (`kind='repurpose'` → shared [`RepurposeRetryService`](backend/app/Services/RepurposeRetryService.php), extracted from `RepurposeJobController::retry`) + admin deep-link. (4) **Informative notifs** — `RepurposeJob::displayTopic()` + `TelegramNotificationService::repurposeHeader()` prepend `job #id · "topic"` to every repurpose Telegram message. **Part B (auto-learning rule loop — DB-backed learned-constraints overlay, ExpeL/Voyager-style on a relational substrate)** is designed but deferred. ~30 new tests green. [plan](docs/plans/2026-06-14-video-rebrand-retry-and-autolearning.md). Committed, NOT pushed.
- **2026-06-14** — **Admin video_rebrand slide regeneration (batch + per-slide)**: [`/admin/repurpose/{id}`](frontend/src/views/admin/RepurposeJobDetail.vue) gains a **"Regenerate all"** button (header) + a per-slide **Re-skin/Re-render** button on every slide — productizes the manual reset/re-dispatch. Backend: [`RepurposeJobController::regenerateAllSlides`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php) (`POST /admin/repurpose/{id}/regenerate-slides`) resets all bookends (kf/veo null, comp pending) + all tools (comp pending), `forceStatus('extracted')`, dispatches `GenerateRebrandAssets`; `regenerateSlide` (`POST /admin/repurpose/{id}/slides/{n}/regenerate`) branches by role — **tool** → `ComposeToolSlides` (free ffmpeg re-skin, FSM-neutral, runs on a `drafted` job) / **hook|cta** → reset bookend + `forceStatus('extracted')` + `GenerateRebrandAssets` (Veo re-render, burns credits). New [`HasStatusTransitions::forceStatus()`](backend/app/Traits/HasStatusTransitions.php) — audited FSM-bypass (`forced=>true` in pipeline_state_log) for operator re-runs from terminal `drafted` (no legal forward edge). [`GenerateRebrandAssets`](backend/app/Jobs/GenerateRebrandAssets.php) now **skips the ~1-3min hook SSH-author when the hook keyframe is already `done`** (single-CTA / re-run regen). Frontend [`useRepurposeJobs`](frontend/src/composables/useRepurposeJobs.js): `useRegenerateAllRepurposeSlides` + `useRegenerateRepurposeSlide` mutations + the detail poll widened to keep ticking while any slide is mid-flight (a free tool re-skin leaves the job `drafted`/terminal, so status-only polling would miss it). Confirm dialogs warn on Veo-credit actions. 21 tests green (8 new regenerate + 5 compose + 8 controller/asset regression).
- **2026-06-14** — **Fix: video_rebrand tool-slide header pagination counted bookends (1–9 for 7 tools)**: [`VideoRebrandComposer::composeJobToolSlides`](backend/app/Services/VideoRebrandComposer.php) passed `$total = $job->videoSlides()->count()` (all 9 = hook + 7 tools + cta) to [`VideoChromeRenderer::renderSlide`](backend/app/Services/VideoChromeRenderer.php), so the header chip row rendered 1–9 even though only the 7 `tool` slides get header chrome (the hook/CTA Veo bookends are full-bleed clips, no chrome). Fix: `$total = $tools->count()` (tool count only) + pass the slide's **1-based position among tools** (`$position + 1` from the ordered, `->values()`-reindexed tool collection) instead of the raw `slide_index` — robust to dropped source bookends / renumbered indices, matching renderSlide's documented contract. Both compose paths share this method (early `ComposeToolSlides` + the `assets_ready` gate `ComposeVideoCarousel`), so one fix covers both. New test `test_pagination_counts_tool_slides_only_not_bookends` asserts `renderSlide` receives `total=2` (not 4) + 1-based positions for a hook+2tools+cta job. 13 compose tests green.

- **2026-06-14** — **Topic-aware public-figure on the carousel COVER (dynamic, any figure)**: corrected standard — v3 Spotlight Portrait (creator-fronted, no figure) is the default ONLY for IG-source (repurpose) carousels; for ORIGINAL content (blog→carousel) the cover/HOOK is TOPIC-AWARE — topic about a public figure ("Perjalanan Soumith Chintala", "IPO OpenAI: yang Altman sembunyikan") → cover shows the creator INTERACTING with that figure (coding/coffee/whiteboard — scene fits the topic) for human-touch curiosity. Fully dynamic, ZERO hardcoded names: new [`CarouselCoverFigureEnricher`](backend/app/Services/CarouselCoverFigureEnricher.php) runs once per cover (idempotent via `carousel_slides[cover].figure_enriched`, fail-safe — any miss/CLI-fail leaves the plugin creator cover untouched), gates IG-source via [`LinkedInPost::isRepurpose()`](backend/app/Models/LinkedInPost.php). Figure + interaction SCENE both authored by [`VideoHookSceneAuthor`](backend/app/Services/VideoHookSceneAuthor.php) (generalized with `medium='carousel_cover'` + `headline` param — reuses the SAME `/carousel-gen` bundle, video path byte-identical); figure's license-clean photo resolves via [`EntityReferenceService`](backend/app/Services/EntityReferenceService.php) (Wikidata/Commons notability+license gate, same as blog covers), name NEVER in prompt (only "reference image 2"). [`CarouselSlideEnhancer`](backend/app/Services/CarouselSlideEnhancer.php) attaches figure as face_refs[1] (after creator=image 1) + swaps the single-creator cover mandate for a TWO-subject interaction mandate when `entity_face_ref` set. Wired into `LinkedInCarouselImageService::dispatchAllSlides` + `dispatchSingleSlide` (both "Regenerate All Images" + single cover "Re-render" pick it up). Figure belongs at IMAGE gen (cover/keyframe), NOT video (video only animates). 14 new tests green; vault [[10-Identity/visual-identity]] §Spotlight scope + [[30-Knowledge/image-gen-shared]] §0 updated. NOT deployed — needs VPS to exercise the SSH author + Wikidata.
- **2026-06-14** — **video_rebrand hook/CTA = REUSE `/carousel-gen` knowledge (single source of truth), stop hardcoding rules**: root-caused why hook/CTA looked generic — [`services.repurpose.refs_hook`](backend/config/services.php) was empty (`REPURPOSE_REFS_HOOK` never set on the VPS) so [`RunsRepurposeClaudeCli`](backend/app/Services/Concerns/RunsRepurposeClaudeCli.php) appended NO `--append-system-prompt-file` → [`VideoHookSceneAuthor`](backend/app/Services/VideoHookSceneAuthor.php) ran with ZERO plugin knowledge (the 291KB `/carousel-gen` bundle — hook-visual-library + hook-science + creator-bible + v3 visual — was already on the VPS, just never wired). Fixes: (1) `refsBundle()` now **defaults `refs_hook` to `carousel-gen.refs_pipeline`** at runtime so it can never be silently empty (env is an optional override); (2) **slimmed `buildPrompt`** to DEFER all creative/visual rules to the bundle (removed the hardcoded Spotlight-Portrait/≥3-floating-UI rules — that duplication both drifted AND revealed the whole tool list, killing curiosity) — the prompt now only frames the task + video deltas: 9:16, **curiosity gap (never reveal the list)**, figure interaction when one fits (CEO as face ref); (3) static `KEYFRAME_PROMPT_HOOK` fallback de-revealed (no tool cards). Cross-project standard + write-back rule captured in the vault ([[30-Knowledge/image-gen-shared]] §0 + [[10-Identity/visual-identity]]) and enforced via `~/CLAUDE.md` (the `ai-image-carousel-prompt-gen` plugin is the single source of truth for ALL image gen in any project). Still pending: CTA authored via the bundle (1 varying command baked into the prompt) + drop the ffmpeg ask-overlay. 10 hook/asset tests green.
- **2026-06-13** — **video_rebrand quality pass v2** (operator feedback on job #19, 3 changes): (1) **Parallel tool slides** — [`ComposeToolSlides`](backend/app/Jobs/ComposeToolSlides.php) re-skins the source tool slides in PARALLEL with the Veo hook/CTA render ([`GenerateRebrandAssets`](backend/app/Jobs/GenerateRebrandAssets.php) dispatches it; shared idempotent loop [`VideoRebrandComposer::composeJobToolSlides`](backend/app/Services/VideoRebrandComposer.php) also used by the `assets_ready` gate `ComposeVideoCarousel`), so a slow/failed hook no longer blocks the rest — each tool slide is individually downloadable the moment it composites. (2) **Bilingual + restyled tool-slide chrome** — header drops the top-left creator avatar (brand now only in footer), description is Indonesian-primary (`header_desc`) + English-companion (NEW `header_desc_en` col, migration `2026_06_13_000004`; [`VideoSlideExtractor`](backend/app/Services/VideoSlideExtractor.php) vision prompt emits `desc_id`/`desc_en`); footer adds IG/TikTok/LinkedIn glyphs + @handle, a globe + site, gold "Geser (Swipe) →" pill ([`video-chrome.cjs`](scripts/repurpose/video-chrome.cjs) header/footer extracted into exported pure builders). (3) **Hook/CTA keyframes → v3 Spotlight Portrait** at 9:16 (mirrors `/carousel-gen` cover/CTA per vault [visual-identity](Obsidian-Vault/10-Identity/visual-identity.md)): solid signature-blue `#0F59B6` base, subject off-center, THREE+ floating topic UI elements convey the topic (not costume), fixed signature outfit, cool-neutral key + gold rim, scroll-stopping — encoded in both the static fallback ([`GenerateRebrandAssets::KEYFRAME_PROMPT_*`](backend/app/Jobs/GenerateRebrandAssets.php)) and the topic-aware [`VideoHookSceneAuthor`](backend/app/Services/VideoHookSceneAuthor.php). CTA = deepened-navy + gold glow + "join me" gesture. ~30 tests green (node layout + extractor bilingual + compose/asset/poll). [plan](docs/plans/2026-06-13-video-rebrand-quality-pass.md)
- **2026-06-13** — **Fix: manual retry for fully-`failed` video_rebrand jobs**: the auto-recover only fires while status=`generating_assets`; once [`PollRebrandAssets::checkCompletion`](backend/app/Console/Commands/PollRebrandAssets.php) exhausts `MAX_RETRIES` and fails the job, the operator's only recourse is the admin Retry button — which was broken for video mode. [`RepurposeJobController::retry`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php) had NO `RETRY_MAP` entries for the video asset states, so a failed-from `generating_assets`/`assets_ready`/`compositing`/`composed` fell back to `'capturing'` = a full restart from IG capture (re-downloads the whole video) AND never reset `asset_retry_count`, so even if it bounced back `recover()` skipped it (retry ≥ 3). Three fixes: (1) added the 4 video asset states to `RETRY_MAP` → resume at the exact failed step's guard (`generating_assets`→`GenerateRebrandAssets@extracted`, `assets_ready`/`compositing`→`ComposeVideoCarousel@assets_ready`, `composed`→`FinalizeRepurpose@composed`); (2) zero `asset_retry_count` on a video asset re-run so the bounded auto-recover safety-net is live again; (3) reset the failed/orphaned bookends (`keyframe/veo`→null, `composited`→pending, **done bookends preserved**) so `GenerateRebrandAssets` re-runs them immediately instead of waiting for the 5-min `recover()` pass (its `dispatchKeyframe` skips `kf='done'`, so a veo-failed bookend would otherwise stall). Segments stay independent — a re-run only regenerates what broke, an already-rendered CTA survives. 28 repurpose-admin/FSM tests green.
- **2026-06-13** — **Fix: `repurpose:poll-rebrand-assets` recovery crash** (video_rebrand stuck-asset trap): [`PollRebrandAssets::recover()`](backend/app/Console/Commands/PollRebrandAssets.php) bounced a stuck job `generating_assets → extracted`, but that FSM edge didn't exist → `InvalidStateTransitionException` crashed the WHOLE cron (exit 1) every minute, so it never re-dispatched AND the crash starved nothing downstream of it but spammed failures. Three fixes: (1) added the `generating_assets → extracted` recovery edge to [`RepurposeJobStatus`](backend/app/Enums/RepurposeJobStatus.php); (2) per-job `try/catch` in `recover()` so one bad job never crashes the cron; (3) recover now also re-dispatches **orphaned-NULL** keyframes (a worker death mid-`GenerateRebrandAssets` commits the status transition but never dispatches → bookend stuck `null` forever; the 5-min cooldown prevents racing an in-flight author). Root-caused from prod jobs #15/#19 stuck with null hook/CTA keyframes. 10 tests green.
- **2026-06-13** — **video_rebrand Veo audio-filter fix**: hook/CTA Veo clips were failing 100% with `PUBLIC_ERROR_AUDIO_FILTERED` ("Audio generation failed"). Root cause = the `Audio:` line in [`GenerateRebrandAssets::VEO_PROMPT_HOOK`/`_CTA`](backend/app/Jobs/GenerateRebrandAssets.php) was **over-negated near-silence** (`quiet room tone only, no music, no dialogue, no subtitles, no audience sounds`) — Veo 3 ALWAYS generates audio (no API param to disable it, confirmed in GeminiGen video-gen/veo docs + Google dev forum), and an all-negation command leaves the audio model no valid target → degenerate track → whole render fails. Keyframe→I2V flow was already correct (image-first; that was NOT the bug). Fix (per snubroot Veo-3 Prompting Guide v4.0 "Audio Hallucination Fixes" — match audio complexity to visual complexity, one positive ambiance + ≤1 negation): a **concrete positive ambient bed** (`soft neutral room tone, gentle ambient hum, calm atmosphere, no music, no spoken words`). Audio is stripped on download anyway — only needs to PASS. Also bumped [`PollRebrandAssets::MAX_RETRIES`](backend/app/Console/Commands/PollRebrandAssets.php) 2→3 for partly-nondeterministic Veo 3.1 trips (r/Bard reports of recent audio regressions). Still veo-3.1-fast (no model/param change). 18 asset tests green.
- **2026-06-13** — **Social Studio drops blog jobs on hand-off**: `?exclude_settled=1` (the Social Studio IG list filter) now hides a blog-mode repurpose job the moment it hands off to Content Engine (`content_idea_id` set by `finalizeBlog`, status `drafted`) — NOT only once the article finally publishes. The blog work lives entirely in `/admin/content-engine` after hand-off, so it's no longer double-listed. `whereNull('content_idea_id')` in [`RepurposeJobController::index`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php); carousel (linkedinPost-queue rule) + video_rebrand (manual-download) modes never set `content_idea_id`, so they're unaffected. Row retained for audit (hard-delete via the trash button if truly unwanted). Test `test_excludes_blog_job_once_handed_off_to_content_engine`.
- **2026-06-13** — **Social Studio per-row Delete**: manual cleanup of stale/test rows. New `DELETE /admin/repurpose/{id}` ([`RepurposeJobController::destroy`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php) — hard delete + private slide-dir purge + child video-slide rows; RepurposeJob has no SoftDeletes) and `DELETE /admin/linkedin-drafts/{id}` ([`LinkedInDraftController::destroy`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) — soft delete). Frontend: [`SocialStudio.vue`](frontend/src/views/admin/SocialStudio.vue) Actions column gains a trash button (window.confirm guard, per-row spinner) routing IG cards → `useDeleteRepurposeJob` / blog cards → `useDeleteLinkedInDraft`. Linked ContentIdea/Post left intact (own delete surfaces). 6 backend tests.
- **2026-06-13** — **IG-repurpose blog mode → Content Engine handoff** (quality fix): blog mode no longer runs the low-quality internal single-CLI rewrite (no scoring/gates → "jelek banget"). It now **forks off `extracted`** (skips research+rewrite, like video_rebrand), and [`FinalizeRepurpose`](backend/app/Jobs/FinalizeRepurpose.php) seeds a **`draft` ContentIdea** with an `instructions` brief built from the IG material (caption + slide lines + claims → `buildBlogBrief`/`deriveBlogTitle`/`extractedSlideLines`) — NO `generated_article`. Operator clicks **Start Research** in `/admin/content-engine` to run the proper pipeline (article-prep → write → 5-gate score → images → publish → auto carousel + cross-post). [`ExtractSlideContent`](backend/app/Jobs/ExtractSlideContent.php) branches blog→`FinalizeRepurpose` / carousel→`ResearchRepurposeClaims`; FSM gains `extracted → finalizing` ([`RepurposeJobStatus`](backend/app/Enums/RepurposeJobStatus.php)); retry resumes a failed blog finalize at `extracted` ([`RepurposeJobController::retry`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php)); Telegram drafted copy → "Start Research". **Carousel & video_rebrand modes unchanged** (carousel's rewrite is only a /carousel-gen seed). 44 repurpose tests green. **Follow-up (same day):** `finalizeBlog` also seeds `virality_score = 75` (`REPURPOSE_VIRALITY_SCORE`, manual IG curation = high intent) + `source_data.pub_date` — the virality_score is FUNCTIONALLY REQUIRED, not cosmetic: [`ScanBlogForLinkedInConversion`](backend/app/Console/Commands/ScanBlogForLinkedInConversion.php) applies the `contentIdea.virality_score >= linkedin_virality_min_score` (default 60) gate in ALL modes incl `--post-id`, so a null-score repurpose idea would publish its blog but get SKIPPED for carousel + cross-post (silently breaking "Blog + Carousel"); pub_date drives the Content Engine "Published" column.
- **2026-06-13** — **`video_rebrand` quality pass** (4 fixes on the shipped mode): (A) chrome bugs — brand logo inlined as `data:` URI in `video-chrome.cjs` (Playwright `setContent` opaque origin silently blocked the `file://` logo) + `handle()` placeholder-hardened (literal `@creator-brand` → `@alisadikinma`) via the new shared [`App\Support\CreatorHandle`](backend/app/Support/CreatorHandle.php); (B) **source-slide drop** — `VideoSlideExtractor` vision-classifies each slide `content|source_hook|source_cta`; `ExtractVideoSlides` deletes the source creator's own hook/cta bookends + renumbers survivors contiguous 1..K (all-dropped guard); (C) **topic-aware hook** — `dispatchKeyframe` widened to multi-ref, [`VideoHookSceneAuthor`](backend/app/Services/VideoHookSceneAuthor.php) authors a topic scene (Spotlight Portrait standard) optionally with a public figure as a face REFERENCE (name only fetches the photo via `EntityReferenceService`, never enters the prompt), with a `figure_dropped` safety fallback on `PROMINENT_PEOPLE_UPLOAD` (degrades to creator-only, never fails) — migration `2026_06_13_000003`; (D) **CTA ask** — `video-chrome.cjs --mode cta` ask-card overlay (Follow/Save/Comment, NO comment→DM promise) composited onto the CTA clip via `VideoRebrandComposer::overlayCta` + the same ask in the caption (`FinalizeRepurpose`, surfaced in admin detail `caption`). 19 new tests (15 PHP + 4 node). [plan](docs/plans/2026-06-13-video-rebrand-quality-pass.md) · [eval](docs/evals/video-rebrand-quality.md)
- **2026-06-13** — **IG VIDEO-carousel rebrand** (`video_rebrand`, 3rd repurpose mode): yt-dlp downloads a source video carousel headless → vision-extracts each tool slide's header title → face-gen keyframe (9:16) → Veo image-to-video hook/CTA clips → ffmpeg re-skins tool slides into Ali's brand chrome (header+footer vstack, audio preserved) → composited 4:5 MP4s. **v1 = manual download** (per-slide + download-all in Social Studio detail; operator posts the carousel in the IG app — Postiz auto-publish deferred). New FSM states `generating_assets→assets_ready→compositing→composed` (fork off `extracted`); `repurpose_video_slides` table; `repurpose:poll-rebrand-assets` cron (sole keyframe→Veo completion driver, geminigen never webhooks); Telegram 3rd "🎬 Video rebrand" button. 43 backend + 6 frontend tests. [plan](docs/plans/2026-06-12-ig-video-carousel-rebrand.md) · [runbook](docs/runbooks/repurpose-video-rebrand-deploy.md)
- **2026-06-12** — Social Studio status-sync (list pill mirrors carousel render state) + Telegram human-in-the-loop scheduling: weekday/holiday-aware slot prompt (`linkedin:prompt-schedule`), free-text reply parsed by `ParseAndScheduleReply`, `linkedin_telegram_schedule_enabled` setting (supersedes auto-schedule). Migration `2026_06_12_000005` adds `linkedin_posts.schedule_prompt_sent_at`. [plan](docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md)
- **2026-06-12** — Publer cross-post hardening: IG mixed video+image carousel **abandoned** (Publer can't do it — `publer_ig_mixed_video_enabled` default OFF → all-image), silent-mislabel guard (`PublerClient` list-failure detection), publishes routed to `social-crosspost` queue, poll-timeout reclassified transient (retry).
- **2026-06-12** — GROK hook video for IG carousel (xAI image-to-video via GeminiGen, poll-driven). Generation + UI kept, but Publer mixed-publish path gated OFF (see above). [plan](docs/plans/2026-06-12-grok-hook-video-ig-carousel.md)
- **2026-06-12** — Reliability bundle: caption-readiness gate on Approve/Schedule, `crosspost:reap` caption reaper, bounded transient image retry on carousel slides.
- **2026-06-12** — Social Studio: hide settled IG-repurpose jobs (published OR scheduled) via `?exclude_settled=1`; repurpose detail UX pass; aligned Source↔Generated slide comparison; retain captured IG slides.
- **2026-06-12** — Repurpose carousels: suppress the blog "Full article" first-comment across all platforms (no public article to link) via `RepurposeJob::isRepurposePost()`.
- **2026-06-11** — IG-repurpose LLM hardening: 900s CLI budget + one-shot repair-retry (`RunsRepurposeClaudeCli::runRepurposeParsed`). [plan](docs/plans/2026-06-11-repurpose-llm-hardening.md)
- **2026-06-11** — **Social Studio** merge: Draft Posts + IG Repurpose into one menu; "SOSMED Posts" → "Content Calendar". `LinkedInDraftController::index` gains `exclude_repurpose=1`. [plan](docs/plans/2026-06-11-social-studio-merge.md)
- **2026-06-11** — Carousel → **sketchnote** style (knowledge-first infographic, cream paper). `linkedin_carousel_style` setting (default `sketchnote`), `--style`/`--narrative` threaded to `/carousel-gen`. IG-repurpose drops foreshadow. [plan](docs/plans/2026-06-11-carousel-sketchnote-infographic.md)
- **2026-06-11** — **Telegram → Instagram repurpose → carousel** feature (Phases 0–I): `RepurposeJobStatus` FSM, `repurpose_jobs` table, capture→vision-extract→fact-check→rewrite→finalize pipeline, admin `/admin/repurpose` panel + per-step Telegram progress. Gated by `telegram_repurpose_enabled`. [plan](docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md) · [runbook](docs/runbooks/repurpose-ig-carousel-deploy.md)
- **2026-06-11** — LinkedIn draft detail: live backend-activity visibility (re-author/render progress bar, stale-error suppression).
- **2026-06-10** — Autonomous content factory re-enabled (3 Content Engine crons had been disabled) + **weekday-only LinkedIn cadence** (`linkedin_publish_weekdays_only`, slots `[5,12,18]` WIB).
- **2026-06-10** — **Event-driven LinkedIn draft ingest**: every Content Engine publish queues `linkedin:scan-blog --post-id=N`; manual "Scan blog now" button + daily cron retired. [plan](docs/plans/2026-06-10-event-driven-linkedin-draft-ingest.md)
- **2026-06-10** — Publer publish path rewritten against live-validated bulk API (`/posts/schedule/publish`, pre-uploaded media, per-platform networks key) + per-platform config gate `PublerPayloadBuilder::isPlatformEnabled()`.
- **2026-06-10** — Skip `/linkedin-gen` under force-carousel + embed full blog body inline in `/carousel-gen` prompt (`buildForcedCarouselEnvelope`). [plan](docs/plans/2026-06-09-skip-linkedin-gen-force-carousel.md)
- **2026-06-09** — **Default carousel for all 4 platforms + parallel cross-post fan-out** (`linkedin_force_carousel` default `true`, `portfolio-crosspost@{1..N}` worker pool, early fan-out on all-slides-done). [plan](docs/plans/2026-06-09-default-carousel-all-platforms-parallel.md) · [runbook](docs/runbooks/crosspost-parallel-carousel-deploy.md)
- **2026-06-09** — **SEO/GEO SSR-enrichment** for homepage + blog (`SpaPrerenderController` + `SeoHtmlComposer` + `SchemaGraphBuilder`): JSON-LD entity graph, crawlable `<article>`, hreflang spliced into the SPA shell; 1h HTML cache w/ purge-on-edit. Closes P0 "SPA without SSR". Requires one-time nginx widening. [runbook](docs/runbooks/seo-geo-ssr-deploy.md)
- **2026-06-09** — SEO/GEO follow-ups: stale-content freshness loop (`content:flag-stale-posts`), homepage review schema (`aggregateRating`), hard image-completion gate (`segmentsResolvedForAdvance`). [plan](docs/plans/2026-06-09-seo-geo-neilpatel-followups.md)
- **2026-06-08** — **Homepage redesign "The Operator"** (9-section identity-led spine, kebab-case `section_type`s) + follow-up polish (Track Record, MANDOR board, cinematic JARVIS hero video). See *Page Sections Mapping*. [plan](docs/plans/2026-06-08-homepage-the-operator.md)
- **2026-05-15** — **GeminiGen circuit breaker** (Hystrix-style, Cache-backed): closed→open→half_open, `geminigen:circuit-probe` cron + Firecrawl status-page accelerator + Telegram alerts + `GET /admin/geminigen/circuit-status`. [plan](docs/plans/2026-05-15-geminigen-circuit-breaker.md)
- **2026-05-14** — GeminiGen stateless webhook relay for `geminigen-api-client` plugin (`POST /api/automation/geminigen/webhook`, RSA-signed). [runbook](docs/runbooks/geminigen-relay-setup.md)
- **2026-05-12** — Fixed-slot scheduler + format-mix governor + real `PublishViaPubler` + atomic `social:publish-slot` orchestrator + admin calendar. [plan](docs/plans/2026-05-12-linkedin-fixed-slots-and-cross-post-sync.md)
- **2026-05-09** — **Admin Scheduler tab** — DB-driven cron via `scheduled_commands` + `DynamicScheduleRegistrar` (replaces hardcoded `routes/console.php`). [plan](docs/plans/2026-05-09-admin-scheduler-tab.md)
- **2026-05-07** — LinkedIn `linkedin:auto-schedule` cron (`manual_review` → `awaiting_publish` into scored slots), `linkedin_auto_approve_enabled`. [plan](docs/plans/2026-05-07-linkedin-auto-schedule-manual-review.md)
- **2026-05-07** — Model Selection Policy section (cost-aware Opus/Sonnet/Haiku routing). See *Model Selection Policy*.
- **2026-05-06** — LinkedIn calendar view + AI-researched `posting_time_rules` + soft conflict warning. [plan](docs/plans/2026-05-06-linkedin-calendar-and-ai-time-rules.md)
- **2026-05-05** — **Newsletter system** end-to-end (4 capture forms, weekly Friday digest cron, SMTP via admin panel, token unsubscribe). [plan](docs/plans/2026-05-05-newsletter-system.md) · [runbook](docs/runbooks/newsletter-deploy.md)
- **2026-05-05** — CV Master Markdown API (`GET /api/cv/master.md`, token `cv:read`) + perf/cache refactor (ETag/304, WebP+LQIP variants, Cloudflare edge).
- **2026-05-02** — Strict `/carousel-gen` enforcement + parser fix (no fallback for carousel format; carousel image generation MUST go through `/carousel-gen`).
- **2026-04** — Foundations: LinkedIn Admin UI + OAuth + FSM (`LinkedInPostStatus`), carousel image generation pipeline, carousel engine decoupling (`/carousel-gen` universal engine), named-entity covers (Wikidata), Creator Brand system, Pipeline State Machine, segment retry, translate-before-publish gate, MCP-leak fix (empty-mcp), SSH-key-per-context. See sections above + `docs/plans/2026-04-*`.

---

**Maintainer:** Ali Sadikin (ali.sadikincom85@gmail.com)
**Environment:** macOS + Docker Desktop, /Users/alisadikin/Drive-D/Projects/Portfolio_v2
**PHP:** 8.2 inside the `portfolio_backend` container — no host php; run `docker exec portfolio_backend php ...` (e.g. `docker exec portfolio_backend php artisan test`)
**Backend:** Laravel 12 + PHP 8.2 + MySQL 8 + Sanctum 4 + Filament 4.1
**Frontend:** Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + TanStack Query 5.90 + Tailwind 4
**Production:** https://alisadikinma.com
