# Social Admin UI — Multi-Platform Monitoring + OAuth

**Status:** Design complete — full-stack admin scope locked (LinkedIn-only) — ready for implementation
**Scope (session 3 clarification, 2026-04-23):** FULL-STACK ADMIN, LINKEDIN-ONLY.

**Responsibility split:**
- **Plugin** (`D:\Projects\claude-plugin\linkedin-post-writer`): content generation ONLY. Emits JSON (text post, carousel slides, hashtags, depth score) via SSH→Claude CLI. No OAuth, no publishing, no scheduling, no MixPost, no API calls to LinkedIn.
- **This milestone (Portfolio_v2 admin UI)**: everything operational — `linkedin_accounts` + `linkedin_posts` schema + models + enum + FSM trait (refactored generic), `LinkedInOAuthService` + `LinkedInPublishService` (backend), admin `LinkedInDraftController` (7 endpoints), OAuth callback controller, admin Vue views + composable + settings card. Calls LinkedIn REST API v2 directly. Plugin's `/automation/linkedin/*` callback endpoints also owned here (stubbed until plugin integrates).

Plugin's Addendum 3 (`D:\Projects\claude-plugin\linkedin-post-writer\docs\plans\2026-04-23-plugin-architecture-full-auto.md §13`) documents the backend contract this milestone implements — schema, services, env vars, OAuth routes.

**Multi-platform scope from session 2 is DEFERRED.** Session 2's Instagram/TikTok/YouTube via MixPost Pro design is archived for reference but NOT built here. Schema is LinkedIn-specific (`linkedin_posts` not `social_posts`) matching plugin contract. When future plugins ship for other platforms, they get parallel tables + their own admin UI milestones.
**Date:** 2026-04-23
**Owner:** Ali Sadikin
**Scope:** Portfolio_v2 admin-UI side of blog → social-media auto-publish pipeline, covering LinkedIn via direct OAuth (this milestone) and Instagram/TikTok/YouTube via MixPost Pro (future milestones). Schema + UI multi-platform-ready from day one.
**Plugin reference:** `D:\Projects\claude-plugin\linkedin-post-writer\docs\plans\2026-04-23-plugin-architecture-full-auto.md` (LinkedIn-first plugin; other platforms = future plugins)
**Prior file title:** "LinkedIn Admin UI — Monitoring + OAuth Settings" (superseded by scope pivot 2026-04-23 session 2 after MixPost OSS platform-support finding)

---

## 0. Scope Pivot Note (2026-04-23 session 2)

Initial brainstorm locked MixPost OSS for LinkedIn publishing based on plugin design Decision #6. Mid-review verification against [mixpost.app/pricing](https://mixpost.app/pricing) and the [inovector/mixpost GitHub repo](https://github.com/inovector/mixpost) revealed:

- **MixPost Lite (free OSS) supports ONLY:** Facebook Pages + X (Twitter) + Mastodon
- **LinkedIn + Instagram + TikTok + YouTube + Threads + Pinterest + Bluesky + Google Business Profile** are Pro-only ($299 one-time)
- Repo's `src/Services/` folder confirms: only `FacebookService.php` + `TwitterService.php` ship in OSS

**Plugin Decision #6 is factually wrong** — MixPost OSS cannot publish to LinkedIn. This forced a re-decision. User's chosen architecture:

| Platform | Backbone | Cost | Status in this milestone |
|---|---|---|---|
| **LinkedIn** | Direct OAuth + LinkedIn REST API v2 | Free | **In scope** (this milestone) |
| **Instagram** | MixPost Pro | $299 one-time (shared) | Scaffold schema + placeholder UI |
| **TikTok** | MixPost Pro | (shared) | Scaffold schema + placeholder UI |
| **YouTube** | MixPost Pro | (shared) | Scaffold schema + placeholder UI |

This milestone ships LinkedIn end-to-end admin UI with direct OAuth, against a multi-platform schema that admits future platforms without migration. Plugin `linkedin-post-writer` stays focused on LinkedIn; future plugins (instagram-writer, etc.) follow the same pattern against shared infrastructure.

---

## Design

### 1. Context & Goal

#### 1.1 Problem
Portfolio_v2 publishes blog posts at `alisadikinma.com/blog`. The `linkedin-post-writer` plugin (WIP at `D:\Projects\claude-plugin\linkedin-post-writer\`) auto-converts blogs to LinkedIn posts but needs a Portfolio_v2 admin UI for:

1. **Monitor** — lifecycle of blog→social conversion jobs across all platforms
2. **View** — converted content (text post, carousel, future video) with validation scores
3. **Manage** — posting schedule, cancel window, publish-now override
4. **Connect** — OAuth flow for LinkedIn (direct) and MixPost Pro (others)

This milestone delivers the admin UI with LinkedIn as the first fully-wired platform; other platforms are scaffolded as placeholders until their plugins + MixPost Pro ship.

#### 1.2 Goal
Deliver a vertical slice covering:
- `social_posts` table + `SocialPost` model + `SocialPostStatus` FSM enum (platform-agnostic)
- `Admin\SocialDraftController` with 7 endpoints (routes: `/admin/social-drafts/*`)
- LinkedIn OAuth flow (custom controller → LinkedIn → callback → `social_accounts` row)
- `App\Contracts\SocialPublisher` interface + `LinkedInPublisher` implementation (stub until plugin + cron ship)
- Two list views (`SocialPostsList` + `SocialQueueList`) + shared detail view + platform filter
- New "Social Publishing" card on `/admin/settings/about` with per-platform accordion (LinkedIn functional, others placeholder)
- Seeder/factory for end-to-end UI testing with mock data across all planned platforms

#### 1.3 Non-goals (deferred to future milestones)
- `SocialGenerationService` SSH → plugin CLI for any platform (Plugin Phase D3)
- 8 automation callback endpoints (Plugin Phase D4 — path: `/automation/linkedin/*`, unchanged)
- `ScanBlogForSocialConversion` cron + `GenerateLinkedInPost` job (Plugin Phase D5/D6)
- `composer require` MixPost Pro + `MixPostPublisher` implementation — defers until budget approved
- LinkedIn publish service (actual REST API v2 calls) — stubbed this milestone, implemented after plugin ships
- Telegram webhook (2-layer HMAC + cancel flow) — Plugin Phase D9
- Carousel image pipeline + TCPDF composition — Plugin Phase D10/D11
- Instagram/TikTok/YouTube plugins — future workstreams
- Manual "Convert to LinkedIn" button on `/admin/posts` — **user explicit rejection**; conversion is cron-only + regenerate endpoint

### 2. Design Decisions Log

| # | Decision | Rationale | Source |
|---|---|---|---|
| 1 | **LinkedIn = direct OAuth** (not MixPost OSS — original plan was wrong) | MixPost OSS doesn't support LinkedIn (confirmed via pricing page + repo inspection). Direct OAuth avoids $299 Pro dependency for Day-1 ship and keeps LinkedIn entirely in-house | Scope pivot 2026-04-23 session 2 |
| 2 | **Instagram/TikTok/YouTube = MixPost Pro** (deferred to future milestones) | MixPost Pro ($299 one-time) provides 8 platforms including these 3 + LinkedIn + 4 more. Cheaper than writing 3 separate OAuth + publish flows from scratch. Deferred pending plugin development + budget | User answer + MixPost pricing verification |
| 3 | **Multi-platform schema from day one** — single `social_posts` table with `platform` enum column, not `linkedin_posts` specific | Avoids future schema migration. Platform-specific fields (depth_score, carousel_slides, link_comment) become nullable and platform-conditional. `platform_metadata` JSON column holds anything else per-platform | Forward compatibility + YAGNI balance |
| 4 | **Two separate list views** — `SocialPostsList` (success feed) + `SocialQueueList` (actionable) | User preference over single-list-with-tabs. Clear mental separation: "my social posts" vs "drafts needing attention". Both views have platform filter | This brainstorm Q2 |
| 5 | **Auto cron only** — no manual convert button on `/admin/posts` | User explicit choice. Plugin cron at 03:00 WIB creates drafts. Re-conversion via `regenerate` endpoint on existing draft | This brainstorm Q3 |
| 6 | **Settings card on `AboutSettings.vue`** — "Social Publishing" card with per-platform accordion, parallel to `creator_brand` + `telegram` cards | Consistency with existing pattern. Per-platform accordion = scalable to adding platforms without redesign | This brainstorm Q4 + scope pivot |
| 7 | **Operator-facing flags in DB settings**, infrastructure in env vars | Editable without SSH/redeploy, audit trail via `settings` table. Env vars retained only for SSH keys, paths, cron schedule | Matches `creator_brand`/`telegram` precedent |
| 8 | **Refactor `HasStatusTransitions` trait + `PipelineGuard` to be generic** (enum-class agnostic) before `SocialPost` can use them | Current implementation hardcoded to `ContentIdeaStatus`/`ContentIdea` (trait line 5+19+21-22; guard line 5+7+12). Add abstract `statusEnumClass(): string` method on models + widen guard signatures to `Model $model, BackedEnum $next`. Blast radius: ContentIdea is only current user — type-compatible refactor | Code-review 2026-04-23 |
| 9 | **`telegram_notify_*_linkedin` keys live in `telegram` settings group** (not new `social_publishing` group) | Existing `telegram_notify_*` keys all in `telegram` group, read exclusively by `TelegramNotificationService`. Split groups would force the service to read two sources or miss events. Telegram card gets a new LinkedIn subsection | Code-review 2026-04-23, matches SettingsController.php:600-605 |
| 10 | **Publisher abstraction**: `App\Contracts\SocialPublisher` interface with per-platform implementations; `PublisherResolver::forPlatform($enum)` factory | Clean boundary between admin UI (platform-agnostic) and publishing backbone (platform-specific). LinkedIn gets `LinkedInDirectPublisher`, MixPost platforms get shared `MixPostPublisher` (deferred). Adding a platform = 1 new implementation + 1 enum case, no admin-UI changes | Multi-platform pivot + SOLID |
| 11 | **Plugin automation endpoints stay LinkedIn-specific** (`/automation/linkedin/*`) | Plugin is LinkedIn-specific; separating automation endpoints by platform keeps each plugin's contract independent. Backend controllers auto-pin `platform='linkedin'` when plugin posts to these routes. Future plugins (instagram-writer) get `/automation/instagram/*` | Plugin contract fidelity + scalability |
| 12 | **Contract-exact with plugin design on FSM + schema fields** — just generalized container | Plugin design §4.2 schema fields (format, content, hashtags, carousel_slides, depth_score, etc.) all preserved verbatim as LinkedIn-specific nullable columns on `social_posts`. Plugin's `linkedin-schedule` skill payload maps 1:1 to our controller | Zero drift risk for plugin integration |

---

### 3. Architecture Overview

#### 3.1 Where this fits in the blog-to-social pipeline (LinkedIn lane)

```
[03:00 WIB cron] → scan blogs → create SocialPost(platform='linkedin', status='pending_generation')
                                        ↓
                                SSH → linkedin-post-writer plugin (WIP)
                                        ↓
                        generating → validating → awaiting_publish | manual_review
                                        ↓
                                [15min cancel window]
                                        ↓
                                Publisher: LinkedInDirectPublisher (uses SocialAccount OAuth)
                                        ↓
                                        published

Future lanes (Instagram/TikTok/YouTube):
                                ...MixPostPublisher (deferred until MixPost Pro purchase)
```

**This milestone delivers (shaded)**:
```
[03:00 WIB cron]                                 ← Plugin Phase D5 (deferred)
  ↓
 ┌────────────────────────────────────┐
 │ SocialPost::create(linkedin,pending)│ ← ✅ schema + model + factory (this milestone)
 │ dispatch(GenerateLinkedInPost)     │ ← Plugin Phase D6 (deferred)
 └────────┬───────────────────────────┘
          ↓
    SSH → plugin                             ← Plugin Phase D3 (deferred)
          ↓
 ┌────────────────────────────────────┐
 │ status via generic FSM + state_log │ ← ✅ generic trait + enum (this milestone)
 └────────┬───────────────────────────┘
          ↓
    LinkedInDirectPublisher.publish()         ← stubbed this milestone (actual impl after plugin)
          ↓
          published

    ┌─────────────────────────────────────┐
    │ Operator Admin UI                    │ ← ✅ THIS MILESTONE
    │  • /admin/social-posts (feed)        │
    │  • /admin/social-queue (triage)      │
    │  • /admin/social-drafts/:id (detail) │
    │  • /admin/settings/about             │
    │    (Social Publishing card + OAuth)  │
    │  • 7 admin endpoints                 │
    │  • LinkedIn OAuth flow (full)        │
    └─────────────────────────────────────┘
```

#### 3.2 Menu structure

New admin sidebar group (replaces "LinkedIn" naming from prior draft):
```
Admin Sidebar
└── Social                              ← NEW group (expandable)
    ├── Posts      → /admin/social-posts   (success feed, multi-platform filterable)
    └── Queue      → /admin/social-queue   (actionable drafts, multi-platform)
    
    Settings lives on /admin/settings/about  → new "Social Publishing" card (per-platform accordion)
```

No dedicated `/admin/social-settings` page — all settings flow through the existing AboutSettings composite page for consistency.

---

### 4. Backend Schema + Endpoints

#### 4.1 Migration — `create_social_posts_table`

Generalizes plugin design §4.2's 24-column `linkedin_posts` schema to multi-platform by adding a `platform` enum column + `platform_metadata` JSON column. Platform-specific fields become nullable.

```php
Schema::create('social_posts', function (Blueprint $table) {
    $table->id();
    
    // Blog source
    $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
    
    // Platform identity (NEW — multi-platform)
    $table->enum('platform', ['linkedin', 'instagram', 'tiktok', 'youtube'])->index();
    $table->json('platform_metadata')->nullable();  // per-platform extras (IG caption mentions, YT category, etc.)
    
    // Generic content
    $table->enum('format', ['text', 'carousel', 'video', 'image']);  // widened for future platforms
    $table->text('content');  // main body/caption/description
    $table->json('hashtags');
    
    // LinkedIn-specific (nullable — only populated when platform='linkedin')
    $table->string('link_comment', 500)->nullable();      // LinkedIn first-comment link
    $table->json('carousel_slides')->nullable();          // LinkedIn PDF carousel slides
    $table->string('carousel_pdf_path')->nullable();
    $table->unsignedTinyInteger('depth_score')->nullable();  // LinkedIn Depth Score 0-100
    
    // Generic validation (all platforms — content differs)
    $table->json('validation_log')->nullable();  // {failures[], suggestions[]}
    
    // Scheduling (generic — works for all platforms)
    $table->string('platform_account_id')->nullable();   // SocialAccount.id for direct OAuth, or MixPost account id
    $table->string('platform_post_id')->nullable();      // Published post's id on the platform (LinkedIn URN, IG media id, etc.)
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('cancel_window_ends_at')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->string('published_url')->nullable();         // Final published URL (renamed from linkedin_post_url)
    
    // Status FSM (generic across platforms)
    $table->enum('status', [
        'pending_generation', 'generating', 'validating',
        'awaiting_publish', 'published', 'cancelled',
        'failed', 'manual_review'
    ])->default('pending_generation');
    $table->json('state_log')->nullable();
    $table->text('last_error')->nullable();
    $table->unsignedTinyInteger('retry_count')->default(0);
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['status', 'scheduled_at']);
    $table->index(['platform', 'status']);
    $table->index('post_id');
});

// Partial unique — one ACTIVE row per (post_id, platform). Soft-deleted rows don't block regenerate.
DB::statement(
    'CREATE UNIQUE INDEX social_posts_post_platform_unique
     ON social_posts (post_id, platform)
     WHERE deleted_at IS NULL'
);
```

**Notes:**
- **Platform-scoped uniqueness**: one live post per `(post_id, platform)` combo. A single blog post can have a LinkedIn row AND an Instagram row, but only one LinkedIn row at a time.
- **Partial unique index for soft-delete compatibility**: MySQL 8 supports `WHERE` clause on indexes. Regenerate flow soft-deletes old row → partial index excludes it → new row insertion succeeds.
- **Widened `format` enum**: adds `video` + `image` ahead of Instagram/TikTok/YouTube use. LinkedIn uses only `text` + `carousel` in this milestone.
- **Renamed columns** (from prior LinkedIn-specific draft):
  - `mixpost_account_id` → `platform_account_id` (direct OAuth accounts OR MixPost accounts)
  - `mixpost_post_id` → `platform_post_id`
  - `linkedin_post_url` → `published_url`

#### 4.2 Model — `App\Models\SocialPost`

```php
<?php

namespace App\Models;

use App\Enums\SocialPostStatus;
use App\Enums\SocialPlatform;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialPost extends Model
{
    use HasFactory, HasStatusTransitions, SoftDeletes;
    
    protected $fillable = [
        'post_id', 'platform', 'platform_metadata',
        'format', 'content', 'hashtags',
        'link_comment', 'carousel_slides', 'carousel_pdf_path', 'depth_score',
        'validation_log',
        'platform_account_id', 'platform_post_id',
        'scheduled_at', 'cancel_window_ends_at', 'published_at', 'published_url',
        'status', 'state_log', 'last_error', 'retry_count',
    ];
    
    protected $casts = [
        'platform' => SocialPlatform::class,
        'platform_metadata' => 'array',
        'hashtags' => 'array',
        'carousel_slides' => 'array',
        'validation_log' => 'array',
        'state_log' => 'array',
        'status' => SocialPostStatus::class,
        'scheduled_at' => 'datetime',
        'cancel_window_ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];
    
    // Required by generic HasStatusTransitions trait (post-refactor)
    protected function statusEnumClass(): string
    {
        return SocialPostStatus::class;
    }
    
    public function post() {
        return $this->belongsTo(Post::class);
    }
    
    // Scopes
    public function scopePlatform($q, SocialPlatform|string $platform) {
        return $q->where('platform', $platform instanceof SocialPlatform ? $platform->value : $platform);
    }
    public function scopeLinkedIn($q)  { return $q->where('platform', 'linkedin'); }
    public function scopePublished($q) { return $q->where('status', 'published'); }
    public function scopeScheduled($q) { return $q->where('status', 'awaiting_publish'); }
    public function scopeActionable($q) {
        return $q->whereIn('status', ['manual_review', 'failed', 'generating', 'validating']);
    }
}
```

#### 4.3 Enums — `SocialPlatform` + `SocialPostStatus`

**`App\Enums\SocialPlatform`** (NEW):
```php
enum SocialPlatform: string
{
    case LinkedIn   = 'linkedin';
    case Instagram  = 'instagram';
    case TikTok     = 'tiktok';
    case YouTube    = 'youtube';
    
    public function backbone(): string
    {
        return match($this) {
            self::LinkedIn  => 'direct_oauth',
            self::Instagram,
            self::TikTok,
            self::YouTube   => 'mixpost_pro',
        };
    }
    
    public function isWiredThisMilestone(): bool
    {
        return $this === self::LinkedIn;
    }
    
    public function displayName(): string
    {
        return match($this) {
            self::LinkedIn   => 'LinkedIn',
            self::Instagram  => 'Instagram',
            self::TikTok     => 'TikTok',
            self::YouTube    => 'YouTube',
        };
    }
}
```

**`App\Enums\SocialPostStatus`** (NEW — platform-agnostic, replaces LinkedIn-specific enum):

8 cases with strict FSM adjacency map (same pattern as `ContentIdeaStatus`). Adjacency:
```
pending_generation → [generating, cancelled]
generating → [validating, failed, cancelled]
validating → [awaiting_publish, manual_review, failed, cancelled]
manual_review → [awaiting_publish, cancelled]
awaiting_publish → [published, cancelled, manual_review]  // last = kill-switch demotion
failed → [generating, cancelled]  // regenerate
published → []  // terminal
cancelled → [generating]  // regenerate
```

**Notable transitions (reason strings mandatory):**
- `AwaitingPublish → ManualReview` = kill-switch demotion (`reason: 'kill_switch_demotion'`)
- `Cancelled/Failed → Generating` = regenerate (`reason: 'admin_regenerate'`)
- `Published` is terminal; regenerate creates NEW row (old soft-deleted first, partial-unique-index allows it)

**⚠️ Trait + Guard Refactor Required (Decision #8):** The existing `App\Traits\HasStatusTransitions` + `App\Services\PipelineGuard` are hardcoded to `ContentIdeaStatus`/`ContentIdea`. Phase 1 MUST refactor to enum-class-generic:

```php
// HasStatusTransitions.php — AFTER (generic)
use BackedEnum;
abstract protected function statusEnumClass(): string;

public function transitionTo(BackedEnum|string $next, ?string $reason = null, array $extra = []): self {
    $enumClass = $this->statusEnumClass();
    $nextEnum = is_string($next) ? $enumClass::from($next) : $next;
    $currentEnum = $enumClass::from($this->status);
    // ... rest unchanged
}

// PipelineGuard.php — AFTER (generic)
public function advance(Model $model, BackedEnum $next, string $reason, array $context = []): Model
```

Add `statusEnumClass()` to `ContentIdea` + `SocialPost`. All existing `PipelineGuard::advance(ContentIdea, ContentIdeaStatus, ...)` callers remain type-compatible. Refactor ships in Phase 1 alongside new enum + model, covered by existing `ContentIdea` tests + new `SocialPost` FSM tests. Blast radius: only `ContentIdea` currently uses the trait.

#### 4.4 Admin endpoints — `Admin\SocialDraftController`

7 endpoints (follow plugin design §4.5 shape with generalized path + `?platform=` filter support):

```php
Route::prefix('admin/social-drafts')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [SocialDraftController::class, 'index']);           // list + filter (?status= ?platform= ?format=)
    Route::get('/{id}', [SocialDraftController::class, 'show']);
    Route::put('/{id}', [SocialDraftController::class, 'update']);
    Route::post('/{id}/regenerate', [SocialDraftController::class, 'regenerate']);
    Route::post('/{id}/approve', [SocialDraftController::class, 'approve']);
    Route::post('/{id}/cancel', [SocialDraftController::class, 'cancel']);
    Route::post('/{id}/publish-now', [SocialDraftController::class, 'publishNow']);
});
```

**Plugin contract preservation** — plugin's `linkedin-schedule` skill keeps hitting `/api/automation/linkedin/*` callbacks (unchanged). Backend maps those to `social_posts` rows with `platform='linkedin'` auto-pinned. No plugin contract drift.

**Behavior contracts:**

| Endpoint | Input | Status check | Transition | Notes |
|---|---|---|---|---|
| `GET /` | `?status=` `?platform=` `?format=` `?per_page=` | — | — | Paginated 15/page, eager load `post`, sorted by `updated_at DESC`. Response Resource includes platform display name + backbone (direct_oauth/mixpost_pro) |
| `GET /{id}` | — | — | — | Eager load `post`, return validation_log + state_log + full platform_metadata |
| `PUT /{id}` | content, link_comment, hashtags, carousel_slides, platform_metadata | status ∈ {awaiting_publish, manual_review} | NO transition (Phase D1); → `validating` once plugin ships | Saves edits, returns 200 with `warnings: ["Re-validation deferred until plugin Phase D3"]`. Future "Re-validate" button wires to force-revalidate flag |
| `POST /{id}/regenerate` | — | any except `pending_generation`, `generating`, `validating` | soft-delete current row, create new with `status=pending_generation` | Dispatch `GenerateSocialPost` job (stubbed — no-op until plugin Phase D6). Partial unique index allows same (post_id, platform) re-insertion after soft-delete |
| `POST /{id}/approve` | — | status = `manual_review` | → `awaiting_publish` | Sets `scheduled_at = now()`, `cancel_window_ends_at = now() + linkedin_cancel_window_minutes` (from settings, LinkedIn-specific; other platforms use their own window setting). Countdown UI shows `cancel_window_ends_at - now()` |
| `POST /{id}/cancel` | — | any non-terminal | → `cancelled` (reason: `'admin_cancel'`) | Works at every step of pipeline |
| `POST /{id}/publish-now` | — | status = `awaiting_publish` | → `published` via `PublisherResolver::forPlatform($post->platform)->publish($post)` | Routes to `LinkedInDirectPublisher` for LinkedIn (stubbed until plugin ships), `MixPostPublisher` for others (returns 503 "MixPost Pro not purchased/configured" until future milestone) |

All mutating endpoints use `App\Services\PipelineGuard::advance(...)` with explicit human-readable `reason` strings (`'admin_approve'`, `'admin_cancel'`, `'admin_regenerate'`, `'admin_publish_now'`, `'admin_edit_resubmit'`) for state_log semantic trail.

#### 4.5 Publisher abstraction — `App\Contracts\SocialPublisher`

```php
<?php

namespace App\Contracts;

use App\Models\SocialPost;

interface SocialPublisher
{
    /**
     * Publish the given draft to the platform.
     *
     * @return array{success: bool, post_url: string|null, platform_post_id: string|null, error?: string}
     */
    public function publish(SocialPost $post): array;
    
    /** Verify the connected account is healthy (token not expired, API reachable). */
    public function testConnection(?string $accountId = null): array;
    
    /** List connected accounts available to this publisher. */
    public function listAccounts(): array;
}
```

**Implementations (this milestone):**
- `App\Services\Publishers\LinkedInDirectPublisher` — uses `SocialAccount` OAuth tokens + LinkedIn REST API v2 `/ugcPosts` endpoint. **Stubbed** in this milestone: `publish()` returns 503 "Plugin not yet ready" until plugin Phase D3-D7 lands. `testConnection()` + `listAccounts()` work fully (read SocialAccount rows where platform='linkedin' and token not expired).
- `App\Services\Publishers\MixPostPublisher` — **stub only**: all methods return 503 "MixPost Pro not installed. Future milestone." Implementation deferred until user purchases MixPost Pro + runs `composer require inovector/mixpost-pro-team`.

**Factory:**
```php
App\Services\Publishers\PublisherResolver::forPlatform(SocialPlatform $platform): SocialPublisher
```

Returns the correct implementation based on platform. Called by admin controller's `publishNow` + `testConnection` endpoints.

#### 4.6 LinkedIn OAuth flow (direct, this milestone)

Separate controller for the OAuth dance:

```php
Route::prefix('admin/oauth/linkedin')->middleware('auth:sanctum')->group(function () {
    Route::get('/start', [LinkedInOAuthController::class, 'start']);           // generate state + redirect to LinkedIn
    Route::get('/callback', [LinkedInOAuthController::class, 'callback']);     // exchange code for tokens, store in SocialAccount
    Route::delete('/disconnect', [LinkedInOAuthController::class, 'disconnect']);  // revoke + delete SocialAccount row
});
```

**Flow:**
1. User clicks "Connect LinkedIn" in Social Publishing card
2. Frontend hits `GET /api/admin/oauth/linkedin/start` → backend generates CSRF state, stores in session, returns LinkedIn OAuth URL with `client_id`, `redirect_uri`, `scope=w_member_social,r_liteprofile`, `state`
3. Browser redirects to LinkedIn → user consents → LinkedIn redirects back to `/api/admin/oauth/linkedin/callback?code=...&state=...`
4. Backend verifies state, exchanges code for access_token + refresh_token via LinkedIn's `/oauth/v2/accessToken` endpoint
5. Fetch user profile via `/v2/me` to get `id` + `name`
6. Create/update `SocialAccount` row with `platform='linkedin'`, `platform_user_id=<urn>`, `username=<name>`, `access_token`, `refresh_token`, `token_expires_at`
7. Store `SocialAccount.id` in `linkedin_primary_account_id` setting (so the publisher knows which account to use)
8. Redirect back to admin settings page with success flash

**Env vars needed:**
```env
LINKEDIN_OAUTH_CLIENT_ID=<LinkedIn Developer App client ID>
LINKEDIN_OAUTH_CLIENT_SECRET=<LinkedIn Developer App secret>
LINKEDIN_OAUTH_REDIRECT_URI=https://alisadikinma.com/api/admin/oauth/linkedin/callback
```

**Reuses existing `SocialAccount` model** (`backend/app/Models/SocialAccount.php`) which already has all needed columns: `platform, platform_user_id, username, access_token, refresh_token, token_expires_at`. Add a LinkedIn-specific scope and `isValid()` helper, but no schema migration needed.

#### 4.7 Settings groups

**Group 1: `social_publishing`** (NEW) — platform-agnostic flags, 10 rows seeded via new `SocialPublishingSettingsSeeder`:

| key | default | purpose |
|---|---|---|
| `social_master_auto_publish` | `'false'` | Master kill-switch across ALL platforms (equivalent to old `LINKEDIN_AUTO_PUBLISH`, now platform-agnostic) |
| `linkedin_primary_account_id` | null | Which `SocialAccount.id` to use for LinkedIn publishing (populated after OAuth callback) |
| `linkedin_depth_score_threshold` | `'80'` | Min score to auto-publish (60-95) — LinkedIn-specific for now |
| `linkedin_cancel_window_minutes` | `'15'` | Cancel window — LinkedIn-specific |
| `linkedin_first_comment_enabled` | `'true'` | Auto-post blog link as first comment |
| `linkedin_first_comment_delay_seconds` | `'30'` | Delay before first comment POST |
| `linkedin_last_test_connection_at` | null | Last successful test connection timestamp |
| `linkedin_last_test_connection_result` | null | `'ok'` or `'error: {msg}'` |
| `mixpost_installed` | `'false'` | Derived flag set to `'true'` when `class_exists('Inovector\\Mixpost\\Mixpost')` — used by UI to gate Instagram/TikTok/YouTube accordions |
| `mixpost_pro_account_ids` | null | JSON map `{instagram: "...", tiktok: "...", youtube: "..."}` — populated per-platform after MixPost Pro connect. Empty until future milestone |

**Group 2: `telegram`** (EXTEND EXISTING) — add 3 rows to existing `TelegramSettingsSeeder`:

| key | default | purpose |
|---|---|---|
| `telegram_notify_social_preview` | `'true'` | Send preview + cancel button (sent when any platform draft → awaiting_publish) |
| `telegram_notify_social_depth_failed` | `'true'` | Alert when draft → manual_review (validation failed) |
| `telegram_notify_social_published` | `'true'` | Success notification after successful publish |

**Rationale** (per Decision #9): All `telegram_notify_*` keys live in `telegram` group, read by `TelegramNotificationService`. LinkedIn-specific notifications become platform-agnostic `telegram_notify_social_*` naming so future platforms share the same toggles (simpler operator UX, one set of notification preferences for all social events). The 3 flags extend the existing Telegram card, not the new Social Publishing card.

**Env vars** (infrastructure, not operator-editable):
```env
# LinkedIn OAuth (direct)
LINKEDIN_OAUTH_CLIENT_ID=
LINKEDIN_OAUTH_CLIENT_SECRET=
LINKEDIN_OAUTH_REDIRECT_URI=https://alisadikinma.com/api/admin/oauth/linkedin/callback

# Plugin generation (LinkedIn-specific — matches plugin design §4.4)
LINKEDIN_GEN_DRIVER=ssh
LINKEDIN_GEN_SSH_HOST=localhost
LINKEDIN_GEN_SSH_USER=claudesn
LINKEDIN_GEN_SSH_KEY=/var/www/.ssh/id_ed25519
LINKEDIN_GEN_CLAUDE_PATH=claude
LINKEDIN_GEN_REFS_PLAYBOOK=/home/claudesn/refs-linkedin-playbook.md
LINKEDIN_GEN_REFS_TEMPLATES=/home/claudesn/refs-linkedin-templates.md
LINKEDIN_GEN_REFS_FORMATS=/home/claudesn/refs-linkedin-formats.md
LINKEDIN_GEN_REFS_CAROUSEL=/home/claudesn/refs-linkedin-carousel.md
LINKEDIN_GEN_MODEL_BRIEF=sonnet
LINKEDIN_GEN_MODEL_CONVERT=sonnet
LINKEDIN_GEN_MODEL_CAROUSEL=sonnet
LINKEDIN_GEN_MODEL_VALIDATE=sonnet
LINKEDIN_CRON_SCHEDULE="0 3 * * *"
LINKEDIN_PDF_TEMP_DIR=/tmp/linkedin-pdfs

# Telegram webhook (Addendum 2 D14a)
TELEGRAM_WEBHOOK_SECRET=<random-32-char>
TELEGRAM_CALLBACK_HMAC_KEY=<random-32-char>
```

Settings API (mirror `creator_brand` + `telegram` exactly):
```
GET    /api/admin/settings/social-publishing
PUT    /api/admin/settings/social-publishing
POST   /api/admin/settings/social-publishing/test-connection  # body: {platform: 'linkedin'}
```

---

### 5. Frontend — Views

#### 5.1 `SocialPostsList.vue` (success feed, `/admin/social-posts`)

**Purpose:** browseable visual log of posts that shipped or are about to ship, across all platforms.

**Layout:** card grid, 3 cols desktop / 1 col mobile. Uses existing `.glass-card` utility (dark cinema theme).

**Filter bar** (top, glass pill style):
- Status tabs: `All | Published (default) | Scheduled | Cancelled`
- Platform filter dropdown: `All platforms | LinkedIn | Instagram (coming soon) | TikTok (coming soon) | YouTube (coming soon)`
- Disabled platforms (coming soon) show in dropdown but selecting them filters to 0 results + info banner "Instagram publishing requires MixPost Pro (future milestone)"

**Card anatomy** (~340×300):
```
┌──────────────────────────────────┐
│ [●Published]  [LinkedIn]  [text] │  ← status badge + platform badge + format chip
├──────────────────────────────────┤
│                                  │
│ "Content preview first 120       │  ← text body / carousel cover / video thumb
│  chars or media thumbnail..."    │
│                                  │
├──────────────────────────────────┤
│ From: <post.title>               │
│ Depth: 87  •  2h ago             │  ← score (LinkedIn only) + relative time
│                                  │
│ [Open ↗]  (on hover)             │
└──────────────────────────────────┘
```

Platform badge colors (match each brand's recognizable hue, but muted for dark cinema):
- LinkedIn: cyan tint
- Instagram: magenta tint (placeholder for future)
- TikTok: coral tint
- YouTube: red tint

Hover actions depend on status + platform:
- published → `[Open on <Platform> ↗]` (link to published_url)
- awaiting_publish → `[Publish Now]` `[Cancel]`
- cancelled → `[Regenerate]`

**Sort:** `scheduled_at DESC` for scheduled tab, `published_at DESC` for published tab.

**TanStack Query config:** `staleTime: 30_000`, `refetchOnMount: 'always'` (matches operator-edited-view convention from root CLAUDE.md).

#### 5.2 `SocialQueueList.vue` (triage, `/admin/social-queue`)

**Purpose:** operator worklist. Dense table for scanning failure rows across all platforms.

**Filter bar:**
- Status tabs: `All | Manual Review (default, badge count) | Failed | In Progress`
- Platform filter dropdown: same as Posts list

**Table columns:**

| Blog source | Platform | Status | Format | Depth | Issue | Retries | Updated | Actions |
|---|---|---|---|---|---|---|---|---|
| Post title + `→` link | chip | badge | chip | `87` or `—` (non-LinkedIn rows show `—`) | First failure or `last_error` trunc(60) | `1/3` | `15m ago` | kebab |

**Row click** → `/admin/social-drafts/:id`

**Inline actions** (kebab): `View`, `Regenerate`, `Approve` (manual_review only), `Cancel`

**Empty state:**
- `manual_review` empty: "No drafts need attention"
- `failed` empty: "No failures in the last 7 days"
- `All` empty: "No drafts yet. Publish a blog post to kick off the first conversion."

#### 5.3 `SocialDraftDetail.vue` (`/admin/social-drafts/:id`)

**Layout:** 2-column grid at ≥1024px, stacked on mobile.

**Left column — Content preview (platform-specific rendering)**:

**For `platform='linkedin'`, `format='text'`:**
- LinkedIn-style card mockup: avatar + name header, `content` body with preserved line breaks, hashtag chips
- "First comment" bubble (if `link_comment` set): blue background, indented, rendered as LinkedIn's comment UI

**For `platform='linkedin'`, `format='carousel'`:**
- Horizontal swipeable slide viewer, per-slide image + copy overlay, slide counter `3/10`, layout_hint chip

**For `platform='instagram'` (future — placeholder in this milestone):**
- Instagram-style card mockup: square image + caption; hashtags rendered in the Instagram-typical "first comment" placement
- Placeholder badge: "Instagram publishing deferred — requires MixPost Pro"

**For `platform='tiktok'`/`platform='youtube'` (future):**
- Video thumbnail + description preview
- Same "deferred" placeholder badge

**Right column — Metadata + actions:**

1. **Status timeline** (rendered from `state_log`, rotating 20 entries, with `reason` strings):
   ```
   14:32  pending_generation → generating      (admin_regenerate)
   14:35  generating → validating              (plugin_completed)
   14:36  validating → manual_review           (depth_score_below_threshold: 72<80)
   14:50  manual_review → awaiting_publish     (admin_approve)
   15:05  awaiting_publish → published         (publisher_success)
   ```

2. **Metadata panel:**
   - Blog source (link)
   - Platform + backbone (LinkedIn via direct OAuth / Instagram via MixPost Pro)
   - Format (text/carousel/video/image)
   - Depth score ring 0-100 (LinkedIn only — hidden for other platforms)
   - Scheduled at + countdown (`cancel_window_ends_at - now()` rendered as "Publishes in 12m 34s")
   - Platform post ID (link to platform admin if MixPost, or to LinkedIn URL)
   - Published URL (external link, if published)

3. **Validation panel:**
   - Pass/Fail summary
   - Failures + suggestions lists (from validation_log)

4. **Action bar** (sticky bottom on mobile, inline on desktop):
   - Status-aware buttons per §4.4 behavior contracts
   - `Publish Now` button is conditionally disabled for platforms where `Publisher` is not wired (shows tooltip "Instagram publishing deferred — requires MixPost Pro")

**Edit mode** (triggered by `[Edit]`):
- `content` textarea with char counter (LinkedIn: visual guide at 1100-1300; other platforms: platform-appropriate limits)
- `link_comment` input (LinkedIn only)
- Hashtag chip editor — chips deletable, enforces platform-specific count (LinkedIn: 3-5; Instagram: up to 30; etc.)
- For carousel (LinkedIn): slide-by-slide editor
- Platform-specific `platform_metadata` editor (JSON form generated from platform schema)
- Save → `PUT /api/admin/social-drafts/:id` → saves content, returns 200 with `warnings[]`. Does NOT transition status during Phase D1 (see §4.4 PUT behavior). Re-validate button deferred

#### 5.4 Composable — `useSocialDrafts.js`

**Reference implementation**: `frontend/src/composables/usePageSections.js` — same TanStack Query pattern + cache strategy. `useCarouselDrafts.js` is NOT a suitable reference (plain axios, no TanStack Query).

Exports:
```js
useSocialDraftsList({ status, platform, format, page })
useSocialDraft(id)
useUpdateSocialDraft()
useApproveSocialDraft()
useCancelSocialDraft()
usePublishSocialDraftNow()
useRegenerateSocialDraft()
```

Cache strategy:
- List queries: `staleTime: 30_000` + `refetchOnMount: 'always'`
- Detail query: same + manual invalidation after mutations via `queryClient.invalidateQueries({ queryKey: ['social-drafts'] })`
- Progress polling: detail view enables `refetchInterval: 3_000` when status ∈ {generating, validating, pending_generation}

Query keys: `['social-drafts']` list, `['social-drafts', id]` detail, `['social-drafts', { platform }]` platform-filtered list.

---

### 6. Frontend — Settings Card

#### 6.1 "Social Publishing" card on `AboutSettings.vue`

**Insertion point**: immediately after the closing `</BaseCard>` of the existing Telegram card — around line 442 of current `AboutSettings.vue`. LinkedIn card must go between Telegram card and `Hero & About Enhancement Card`.

Own submit button (`handleSocialPublishingSubmit`) separate from main About form, follows `handleBrandSubmit`/`handleTelegramSubmit` pattern.

**Card structure: master toggle + per-platform accordion**

```
┌─────────────────────────────────────────────────────┐
│ Social Publishing                                    │
├─────────────────────────────────────────────────────┤
│ [ ] Master auto-publish  (kill-switch — all platforms)│
│     Overrides per-platform settings when OFF.        │
├─────────────────────────────────────────────────────┤
│ ▼ LinkedIn  [● Connected: @alisadikinma]             │
│   Backbone: Direct OAuth                             │
│   [Disconnect]  [Test Connection]                    │
│   Last test: 2026-04-23 14:32 — OK                   │
│                                                      │
│   Publishing controls:                               │
│     Depth Score threshold:  [====|===]  80           │
│     Cancel window minutes:  [15]                     │
│                                                      │
│   First comment automation:                          │
│     [✓] Auto-post blog link as first comment         │
│     Delay seconds: [30]                              │
├─────────────────────────────────────────────────────┤
│ ▸ Instagram  [○ Not connected]                       │
│   Backbone: MixPost Pro                              │
│   ⓘ Requires MixPost Pro ($299 one-time)             │
│   ⓘ Future milestone — plugin not yet written        │
│   [Learn about MixPost Pro ↗]                        │
├─────────────────────────────────────────────────────┤
│ ▸ TikTok  [○ Not connected]    (Future milestone)    │
├─────────────────────────────────────────────────────┤
│ ▸ YouTube  [○ Not connected]   (Future milestone)    │
├─────────────────────────────────────────────────────┤
│ [Save Social Publishing Settings]                    │
└─────────────────────────────────────────────────────┘
```

**LinkedIn accordion states** (3 states driven by `GET /api/admin/settings/social-publishing`):

1. **Not connected** (`linkedin_primary_account_id: null`):
   ```
   ▼ LinkedIn  [○ Not connected]
     Backbone: Direct OAuth
     [Connect LinkedIn Account]  → kicks off OAuth flow (§4.6)
   ```

2. **Connected** (`linkedin_primary_account_id` set + token not expired):
   ```
   ▼ LinkedIn  [● Connected: @alisadikinma]
     Backbone: Direct OAuth
     Account: Ali Sadikin (urn:li:person:abc123)
     Connected: 2026-04-23 14:00
     Token valid until: 2027-04-23
     [Disconnect]  [Test Connection]
     Last test: 2026-04-23 14:32 — OK
     ... (publishing controls) ...
   ```

3. **Token expired** (`linkedin_primary_account_id` set but `token_expires_at < now`):
   ```
   ▼ LinkedIn  [⚠ Token expired]
     Backbone: Direct OAuth
     Account: Ali Sadikin (reconnect needed)
     [Reconnect LinkedIn Account]  → OAuth flow with refresh_token if available, else full re-auth
   ```

**Instagram/TikTok/YouTube accordions** (this milestone — always "Not connected" + disabled):
- Show platform logo + "Not connected" chip
- Backbone: MixPost Pro
- Info banner: "Requires MixPost Pro ($299 one-time). Future milestone — plugin not yet written."
- Link to [MixPost Pro pricing](https://mixpost.app/pricing) (opens in new tab)
- No interactive elements (all disabled until future milestone when `mixpost_installed` setting flips to `true` and that platform's plugin ships)

**MixPost install detection**: backend endpoint returns `mixpost_installed: class_exists('Inovector\\Mixpost\\Mixpost')`. Graceful fallback when package absent.

**Phase D1 reachability note**: LinkedIn "Connected" state is fully reachable via the OAuth flow implemented in this milestone (LinkedIn Developer App required — user must create one at linkedin.com/developers and paste `LINKEDIN_OAUTH_CLIENT_ID` + `LINKEDIN_OAUTH_CLIENT_SECRET` into `.env`). Instagram/TikTok/YouTube "Connected" state is unreachable in this milestone — implementers should still build the disabled UI state + ensure it gracefully transitions when future milestones arrive.

#### 6.2 Telegram card extension (existing card gets new subsection)

3 new platform-agnostic social notification flags extend the existing **Telegram Notifications card** in `AboutSettings.vue` (not the new Social Publishing card).

Add a new subsection heading within the existing Telegram card:

```
── Telegram Notifications ──────────────

Content Engine:
  [✓] Manifest needed
  [✓] Generation failed
  [✓] Publish success
  [✓] Segment failed
  [✓] Cover critical
  [✓] Translate failed

Social Publishing:                          ← NEW subsection
  [✓] Preview + cancel button   (any platform → awaiting_publish)
  [✓] Validation failed         (any platform → manual_review)
  [✓] Publish success           (any platform published successfully)
```

Backend impact:
- `TelegramSettingsSeeder` gets 3 new default rows
- `SettingsController::updateTelegramSettings()` validation rules expand to accept the 3 new keys
- `SettingsController::getTelegramSettings()` returns the 3 keys (clients fall back to default `true` if missing during migration window)
- `TelegramNotificationService` reads from `telegram` group — single source of truth maintained across platforms

---

### 7. Testing Strategy

#### 7.1 Seeder/factory for mock test data

`SocialPostFactory`:
- Random blog post FK (pick from existing `posts`)
- Random `platform` weighted: 70% LinkedIn, 10% Instagram, 10% TikTok, 10% YouTube (so LinkedIn is dominant in mock data, matching real usage plan)
- Random `format` based on platform:
  - LinkedIn: 60% text / 40% carousel
  - Instagram: 50% image / 30% carousel / 20% video
  - TikTok: 100% video
  - YouTube: 100% video
- Realistic `content` via Faker's `realText()` trimmed to platform-appropriate length
- `depth_score` only populated when `platform='linkedin'`
- State-specific defaults (e.g., `published` state requires `published_at` + `published_url`; `awaiting_publish` requires `scheduled_at` + `cancel_window_ends_at`)

`SocialPostSeeder`:
- Creates 15 mock drafts (expanded from 10 to cover multi-platform):
  - 4 `published` LinkedIn (fake URLs)
  - 2 `awaiting_publish` LinkedIn (scheduled 5-45 min from now)
  - 2 `manual_review` LinkedIn (with validation_log failures)
  - 1 `failed` LinkedIn (with last_error)
  - 1 `cancelled` LinkedIn
  - 1 `generating` LinkedIn (for progress-polling UX test)
  - 1 `published` Instagram (demonstrates multi-platform feed)
  - 1 `awaiting_publish` Instagram (disabled Publish Now button UX)
  - 1 `manual_review` TikTok (shows triage queue handling non-LinkedIn)
  - 1 `published` YouTube (shows video thumbnail rendering)

Covers all 8 FSM states + all 4 platforms. Validates UI renders correctly when non-LinkedIn rows lack `depth_score` + `carousel_slides`.

**Dev workflow:**
```bash
php artisan migrate
php artisan db:seed --class=SocialPostSeeder
# /admin/social-posts — see feed (70% LinkedIn, 30% mixed)
# /admin/social-queue — see triage with non-LinkedIn rows
# /admin/settings/about — LinkedIn OAuth flow works; others show "Not connected" disabled
```

Zero placeholder implementations. Stubs return explicit 503 with clear messages (e.g., `MixPostPublisher::publish()` returns `["success" => false, "error" => "MixPost Pro not installed. Future milestone."]`). No silent no-ops.

#### 7.2 Feature tests

- `SocialDraftControllerTest` — 7 endpoint tests × platform filter variations (auth, filter, FSM, PipelineGuard)
- `SocialPostFsmTest` — enum adjacency rules (all 64 transition combos + happy paths, platform-agnostic)
- `SocialPublishingSettingsControllerTest` — GET/PUT/test-connection endpoints, MixPost detection logic
- `LinkedInOAuthControllerTest` — start/callback/disconnect flows with mocked LinkedIn API
- `LinkedInDirectPublisherTest` — `publish()` stub returns 503, `listAccounts()` reads SocialAccount rows correctly, `testConnection()` validates token expiry
- `MixPostPublisherTest` — all methods return 503 with expected error messages
- `PublisherResolverTest` — returns correct impl per platform enum
- `SocialPostFactoryTest` — platform-specific defaults correctness, null-safe for LinkedIn-specific fields

#### 7.3 Frontend tests (manual walkthrough)

No Playwright/Cypress in Portfolio_v2. Manual smoke test checklist in implementation plan.

---

### 8. Pattern Reuse

Following proven Portfolio_v2 patterns (minimal new infrastructure):

| Pattern | From | Applied to |
|---|---|---|
| FSM via `HasStatusTransitions` trait + strict adjacency map | `ContentIdeaStatus` | `SocialPostStatus` (after trait refactored to be generic — Decision #8) |
| `PipelineGuard::advance` for uniform transition logging | `AutoPipelineOrchestrator` | Every admin controller mutating endpoint (after guard refactored to be generic — Decision #8) |
| Settings group as DB-backed card | `creator_brand`, `telegram` | `social_publishing` group (same seeder + card pattern) |
| 30s staleTime + `refetchOnMount: 'always'` for operator-edited views | `usePageSections.js` | `useSocialDrafts.js` |
| `.glass-card` + dark cinema tokens | Root CLAUDE.md `--bg-deep`, `--accent-gold` | All new views (zero new tokens) |
| Draft review/approve UI pattern | `CarouselDraftsList.vue` (structure only — NOT composable; it uses Pinia not TanStack Query) | `SocialPostsList.vue`, `SocialQueueList.vue`, `SocialDraftDetail.vue` (reference for sidebar+grid layout) |
| Soft delete for "cancel" semantics | `Post` model | `SocialPost` (cancelled rows restorable, partial unique index allows re-insertion) |
| Status-based action button mapping | `ContentEngine.vue` | Detail view action bar |
| Seeder + factory for UI testing | `CarouselDraftFactory`, `ImageGenerationJobFactory` | `SocialPostFactory` |
| SocialAccount model for OAuth tokens | Existing `social_accounts` table (unused by carousel path) | LinkedIn direct OAuth stores tokens here |

---

### 9. Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| List views data | `GET /api/admin/social-drafts` → `SocialDraftController@index` | ❌ New | Paginated 15/page, filter by status + platform + format, eager load `post` |
| Detail view data | `GET /api/admin/social-drafts/:id` | ❌ New | Eager load `post`, return validation_log + state_log + platform_metadata |
| Blog source link (From: ...) | `SocialPost::post()` → `posts` + `post_translations` | ✅ | Standard eager load |
| Status timeline | `state_log` JSON column | ✅ (trait auto-writes) | `HasStatusTransitions` appends on every transition (post-refactor, generic) |
| Countdown for scheduled_at | Client-side Vue computed + `setInterval(1000)` | ✅ (pattern) | Similar to ContentEngine progress modal |
| Settings form (social_publishing group) | `GET/PUT /api/admin/settings/social-publishing` | ❌ New | Mirror `creator_brand` + `telegram` implementation |
| MixPost install detection | `class_exists('Inovector\\Mixpost\\Mixpost')` check | ✅ (PHP builtin) | Graceful when package absent |
| LinkedIn OAuth flow | `LinkedInOAuthController@start/callback/disconnect` + `SocialAccount` model | ⚠️ Partial — model exists, controller is NEW | Reuses existing `social_accounts` table, zero migration |
| LinkedIn test connection | `LinkedInDirectPublisher::testConnection()` → validates SocialAccount token expiry + pings `/v2/me` | ❌ New | Real API call — works without plugin; stores result to `linkedin_last_test_connection_*` settings |
| LinkedIn publish (actual) | `LinkedInDirectPublisher::publish()` → `/v2/ugcPosts` | ❌ Stubbed (returns 503) | Full implementation deferred until plugin Phase D3-D7 |
| MixPost publishers | `MixPostPublisher::*` | ❌ Stubbed (all return 503) | Deferred until MixPost Pro purchase |
| Publisher resolver | `PublisherResolver::forPlatform($enum)` factory | ❌ New | Maps SocialPlatform enum → Publisher impl |
| Mock test data (seeder) | `SocialPostFactory` + `SocialPostSeeder` | ❌ New | Covers all 8 FSM states × all 4 platforms |
| Route auth | `auth:sanctum` middleware | ✅ | Standard pattern |
| TanStack Query wrapper | `useSocialDrafts.js` — reference `usePageSections.js` pattern | ✅ (reference exists) | Adapt endpoints + query keys |
| Dark cinema theme | Root CLAUDE.md tokens | ✅ | Zero new tokens |
| Sidebar menu | Existing admin sidebar + route meta | ✅ | Add `social` menu group, 2 child items |
| FSM transition enforcement | `HasStatusTransitions::transitionTo()` + `PipelineGuard::advance()` (after Decision #8 refactor) | ⚠️ Refactor needed | Generic post-refactor; existing ContentIdea usage remains type-compatible |
| Soft-delete on regenerate | `SoftDeletes` trait on `SocialPost` + partial unique index | ✅ (Laravel builtin + MySQL partial index) | Audit trail of previous drafts preserved |

**Implementation feasibility: 100% feasible.** LinkedIn OAuth + admin UI are fully buildable this milestone. Instagram/TikTok/YouTube scaffolding is placeholder UI (disabled accordions) + schema columns (nullable) — zero fake functionality. Every stub returns explicit 503 with clear message so operator always knows true state.

---

### 10. Risk Register

| # | Risk | Severity | Mitigation |
|---|---|---|---|
| 1 | **Drift between admin-UI contract and plugin callback contract** | HIGH | Controller method signatures + DB schema copied from plugin design where unchanged. Plugin `linkedin-schedule` skill hits `/automation/linkedin/*` (unchanged, platform-pinned). Admin endpoints never overlap with automation endpoints |
| 2 | **Plugin design needs to be updated** (MixPost OSS was wrong) | MED | Coordinate: after this milestone ships, update plugin design doc Decision #6 to reference "LinkedIn via direct OAuth from Portfolio_v2 backend" instead of "MixPost OSS". Plugin's `linkedin-schedule` skill calls backend's schedule endpoint which routes to `LinkedInDirectPublisher` — plugin unaware of publisher backbone, no plugin code changes |
| 3 | **LinkedIn OAuth token refresh failures** (access_token expires, refresh flow broken) | MED | `LinkedInDirectPublisher::testConnection()` checks token expiry pre-publish. On expired: transition to `manual_review` with reason `'linkedin_token_expired'` + Telegram alert. Operator re-connects via Settings card. Full refresh_token rotation handled in publisher impl (standard OAuth 2.0 flow) |
| 4 | **LinkedIn Developer App rate limits** | MED | LinkedIn OAuth flow: 100 requests/day per app (conservative). Our usage: ~1 publish/day. Headroom ample. Publisher retries on 429 with exponential backoff |
| 5 | **Multi-platform schema too speculative for future platforms** (IG/TikTok/YouTube fields unknown) | LOW | `platform_metadata` JSON column absorbs future platform-specific fields without migration. Core fields (content, hashtags, published_url, status, platform_post_id) apply uniformly |
| 6 | **Operator confused by disabled Instagram/TikTok/YouTube accordions** | LOW | Each disabled accordion has clear info banner explaining MixPost Pro dependency + future-milestone status. Tooltips on disabled actions |
| 7 | **Regenerate race condition** (user clicks regenerate twice) | LOW | Partial unique index on `(post_id, platform) WHERE deleted_at IS NULL` + DB transaction wraps soft-delete + create. Second click errors with "A regeneration is already in progress" |
| 8 | **SocialAccount token leakage in API responses** | LOW | Existing `SocialAccount` model has `$hidden = ['access_token', 'refresh_token']` — API resources automatically exclude. Explicit Resource class for OAuth status responses uses only public fields (username, platform_user_id, token_expires_at) |

---

### 11. Success Criteria (admin-UI milestone ship gate)

All must pass before handing to `gaspol-plan` → `gaspol-execute`:

- [ ] Migration runs clean on fresh DB + migration:fresh --seed
- [ ] Seeder populates 15 mock drafts across 8 status states × 4 platforms
- [ ] `HasStatusTransitions` + `PipelineGuard` refactored to generic — existing ContentIdea tests still pass
- [ ] `/admin/social-posts` renders cards for all platforms, platform filter works, status filter tabs work
- [ ] `/admin/social-queue` renders table for all platforms, filter tabs work, non-LinkedIn rows gracefully handle null depth_score
- [ ] `/admin/social-drafts/:id` renders detail for all platforms with correct action buttons (Publish Now disabled for non-LinkedIn platforms with clear tooltip)
- [ ] Edit mode works for LinkedIn (content + hashtags + carousel slides + link_comment); Instagram/TikTok/YouTube edit mode shows appropriate platform-specific fields (even if read-only-ish for now)
- [ ] `/admin/settings/about` shows Social Publishing card with LinkedIn accordion functional (full OAuth flow works end-to-end with LinkedIn Developer App credentials in .env)
- [ ] LinkedIn OAuth callback successfully stores tokens in `social_accounts` table + updates `linkedin_primary_account_id` setting
- [ ] `Test Connection` button works for LinkedIn (calls `/v2/me`, returns success/error correctly)
- [ ] Instagram/TikTok/YouTube accordions render as disabled with info banners
- [ ] Telegram card gains "Social Publishing" subsection with 3 new toggles that persist to `telegram` settings group
- [ ] All 7 admin `social-drafts/*` endpoints pass feature tests
- [ ] LinkedIn OAuth 3 endpoints pass feature tests (start/callback/disconnect) with mocked LinkedIn API
- [ ] `PublisherResolver` factory returns correct impl per platform
- [ ] `LinkedInDirectPublisher::publish()` stub returns explicit 503 (not silent success)
- [ ] `MixPostPublisher::*` stubs return explicit 503
- [ ] Status badges use correct colors; platform badges use platform-appropriate hues (muted for dark cinema theme)
- [ ] TanStack Query config matches project convention (30s + refetchOnMount:'always')
- [ ] Root CLAUDE.md updated with Social Admin UI section (Page Sections + API Routes + Settings groups + Publisher abstraction)
- [ ] Plugin design doc (Decision #6) has a note/addendum referencing this scope pivot (informational — plugin code doesn't change)
- [ ] Design doc + implementation plan committed to `docs/plans/`

---

### 12. Next Actions

1. Hand to `gaspol-plan` → appends `## Implementation Plan` section with step-by-step TDD phases
2. Implementation phases (preview):
   - **Phase 1 — Generic FSM refactor** (refactor `HasStatusTransitions` + `PipelineGuard` to be enum-class-generic; ContentIdea regression tests)
   - **Phase 2 — Backend foundation** (migration + model + enums + factory + seeder + feature tests)
   - **Phase 3 — Admin endpoints** (SocialDraftController + 7 routes + feature tests)
   - **Phase 4 — Publisher abstraction** (SocialPublisher interface + LinkedInDirectPublisher stub + MixPostPublisher stub + PublisherResolver + tests)
   - **Phase 5 — LinkedIn OAuth flow** (LinkedInOAuthController + 3 endpoints + feature tests with mocked LinkedIn API + LinkedIn Developer App setup docs)
   - **Phase 6 — Settings group + endpoint** (seeder + controller + MixPost detection + test-connection stub)
   - **Phase 7 — Frontend composable + list views** (useSocialDrafts + both list pages + platform filter + sidebar menu)
   - **Phase 8 — Detail view + edit mode** (shared detail page with platform-specific content preview + edit)
   - **Phase 9 — Settings card on AboutSettings** (Social Publishing card with LinkedIn accordion + disabled accordions for other platforms + Telegram card extension)
   - **Phase 10 — Manual smoke test + CLAUDE.md sync**

---

## Appendix A — Cross-references

- Plugin design doc: `D:\Projects\claude-plugin\linkedin-post-writer\docs\plans\2026-04-23-plugin-architecture-full-auto.md` (696 lines). **Note**: plugin's Decision #6 ("MixPost OSS") is superseded by this milestone's Decision #1 (LinkedIn via direct OAuth). Plugin automation endpoints unchanged.
- Plugin implementation plan: `D:\Projects\claude-plugin\linkedin-post-writer\docs\plans\2026-04-23-plugin-architecture-full-auto-plan.md` (1036 lines). Phase D1/D2/D12/D13 conceptually map to this milestone's Phase 2/3/7/8.
- Portfolio_v2 FSM pattern reference: `backend/app/Enums/ContentIdeaStatus.php` + `backend/app/Traits/HasStatusTransitions.php` (to be refactored)
- Reference TanStack Query composable: `frontend/src/composables/usePageSections.js`
- Settings card pattern: `frontend/src/views/admin/AboutSettings.vue` § Creator Brand + § Telegram Notifications
- SocialAccount model (reused for LinkedIn tokens): `backend/app/Models/SocialAccount.php`
- MixPost pricing (supports findings in §0): https://mixpost.app/pricing
- MixPost OSS repo (supports findings in §0): https://github.com/inovector/mixpost
- LinkedIn REST API v2 docs: https://learn.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/ugc-post-api

---

## Appendix B — Open Questions

| # | Question | Decision owner | When to resolve |
|---|---|---|---|
| 1 | Regenerate behavior: soft-delete + new row confirmed — but should `regenerate` accept a `{content_override: ...}` payload for operator-provided content instead of re-triggering plugin? | Tech decision, Phase 3 | Execution — default NO (always re-triggers plugin for consistency); allow override only if operator edits then calls PUT then regenerate |
| 2 | LinkedIn OAuth: user needs to manually create a LinkedIn Developer App at linkedin.com/developers. Should setup docs be included in implementation plan or deferred to Phase 10? | Documentation | Phase 10 — add to LINKEDIN_SETUP.md in repo |
| 3 | Instagram/TikTok/YouTube: when MixPost Pro is purchased in future milestone, should each platform's OAuth go through MixPost admin UI (`/mixpost` route), or should we build custom OAuth wrappers that proxy to MixPost API? | Future decision | Not this milestone — MixPost admin route is simpler, defer decision to when Pro is purchased |
| 4 | Sidebar menu: "Social" group collapsible with icon? Use existing collapsible sidebar component if one exists | UX polish | Phase 7 — check existing sidebar pattern first |
| 5 | Platform badges on cards: dedicated color per platform (LinkedIn cyan, IG magenta, TikTok coral, YouTube red) OR uniform gold? User may have design preference | UX polish | Phase 7 — default colored, easy to flatten to uniform if preferred |
| 6 | When regenerating a `published` LinkedIn post, should link to old LinkedIn URL stay visible in history? | Product decision | Phase 3 — default YES (soft-delete preserves it), expose via `?with_trashed=1` filter on list endpoint |
| 7 | `LinkedInDirectPublisher::publish()` — when plugin ships, does the plugin call our Publisher directly, or does the plugin call `/automation/linkedin/{id}/schedule` which THEN invokes Publisher? | Plugin contract | Not this milestone — deferred to plugin Phase D7 integration |
| 8 | Should we add a "dry-run" mode where Publisher logs what it would post but doesn't actually hit LinkedIn API? Useful for testing in staging | Enhancement | Phase 4 — include as env flag `LINKEDIN_DRY_RUN=true` |

---

**Design complete + scope-pivoted for multi-platform.** Ready for `/gaspol-plan` to append `## Implementation Plan` section. Code-review fixes (HasStatusTransitions refactor, telegram group placement, partial unique index, TanStack Query reference correction, approve endpoint semantics, PUT stub behavior, Phase D1 UI reachability) are all integrated into this revision.

---

## Design Phase 2 — Progress Modal (2026-04-23 session 3)

Extension of Phase 1 (LinkedIn admin UI). Adds step-by-step conversion progress visualization matching the Content Engine Article Generation Progress modal pattern (screenshot at `/admin/content-engine` → in-progress idea → modal).

### Decisions locked (this session)

| # | Decision | Rationale |
|---|---|---|
| 13 | **4 phase cards** (BRIEF + CONTENT-merged + VALIDATE + SCHEDULE) | Matches Content Engine `grid-cols-4` layout exactly. CONVERT + CAROUSEL merged into one "CONTENT" card with format badge + conditional `Slides (N/10)` pill greyed for text posts |
| 14 | **Both triggers** — modal from Queue list + inline timeline on detail view | Consistent with Content Engine UX. Modal opens on "View Progress" click from Queue list for in-progress drafts; inline progress panel on detail page when `status ∈ {pending_generation, generating, validating}` |
| 15 | **No simulator command** — plugin wires callbacks when ready | Saves ~50 LoC. Dev testing via manual `tinker` insert into `progress_log` JSON column until plugin Phase D3 lands. Acceptable since UI renders the 3 card states (cold-start / active / completed) deterministically from data |
| 16 | **Reuse `/progress` callback pattern from Content Engine** | `PUT /api/automation/linkedin/{id}/progress` with `{step, percentage, message}` — mirrors `PUT /api/automation/content-ideas/{id}/progress` exactly. Plugin's `linkedin-schedule` skill (plus each prior skill) emits sub-step progress via this endpoint during its execution |
| 17 | **`progress_log` separate from `pipeline_state_log`** | Two distinct streams: `pipeline_state_log` = FSM audit (20-entry rotating), `progress_log` = plugin skill sub-step stream (100-entry rotating). Same split as ContentIdea |

### Schema additions

New follow-up migration `2026_04_23_000002_add_progress_tracking_to_linkedin_posts` adds 3 columns to existing `linkedin_posts`, **matching ContentIdea's progress columns exactly** for schema consistency (`add_progress_tracking_to_content_ideas.php:11-14`):

```php
$table->unsignedTinyInteger('progress_percentage')->default(0)->after('retry_count')
    ->comment('0-100, plugin-authoritative');
$table->string('current_step', 50)->nullable()->after('progress_percentage')
    ->comment('Plugin-emitted step key; see LinkedInPost::PROGRESS_STEPS for whitelist');
$table->json('progress_log')->nullable()->after('current_step')
    ->comment('Rotating 100-entry stream of {step, percentage, message, timestamp}');
```

Column parity with ContentIdea (size 50, default 0 on progress_percentage). All non-breaking for existing rows — `progress_percentage=0` semantically matches "not yet started" for pending_generation rows. No index needed (never filtered on, only read per-draft in detail/modal).

`LinkedInPost` model gets a `PROGRESS_STEPS` constant listing the whitelist:

```php
public const PROGRESS_STEPS = [
    'initializing', 'input_collection',
    'linkedin_brief', 'linkedin_brief_complete',
    'linkedin_convert', 'linkedin_convert_complete',
    'linkedin_carousel', 'linkedin_carousel_complete',
    'linkedin_validate', 'linkedin_validate_complete',
    'linkedin_schedule', 'completed', 'failed',
];
```

**Deliberately skipped: `process_pid` column** — ContentIdea has one (for tracking Claude CLI PID on VPS). For LinkedIn we derive "process alive" from `now() - updated_at < 60s` on the frontend. Simpler, no VPS PID plumbing needed. If heartbeat polling proves insufficient in production, revisit.

### Plugin callback contract — `PUT /automation/linkedin/{id}/progress`

Token-gated automation endpoint (same middleware as existing `/automation/content-ideas/{id}/progress`). Body schema:

```json
{
  "step": "linkedin_convert",
  "percentage": 42,
  "message": "Text drafted: 1247 chars, hook formula = PAS"
}
```

Step name whitelist (snake_case, matches plugin skill names):
```
input_collection, linkedin_brief, linkedin_brief_complete,
linkedin_convert, linkedin_convert_complete,
linkedin_carousel, linkedin_carousel_complete,
linkedin_validate, linkedin_validate_complete,
linkedin_schedule, completed, failed
```

Handler:
1. Validate `step` against `LinkedInPost::PROGRESS_STEPS` whitelist (unknown step → 422 with `errors.step` field)
2. `clamp($percentage, 0, 100)`
3. `$draft->update(['current_step' => $step, 'progress_percentage' => $pct])`
4. Append `{step, percentage, message, timestamp: now()->toIso8601String()}` to `progress_log`, trim to last 100
5. Return **200 OK** with `{success: true, data: $logEntry}` (matches existing Content Engine pattern at `backend/routes/api.php:436` — plugin uses the echoed entry for confirmation logging on its side)

No FSM transition happens here. This endpoint is pure progress data. FSM transitions fire separately via `PUT .../save-brief`, `/save-post`, `/save-validation`, `/schedule` as already documented in plugin design §4.5.

**Authorship split** (fixes session-3 review ambiguity):
- **Backend writes `initializing`** when cron creates `LinkedInPost::create(status=pending_generation)` — first `progress_log` entry: `{step: 'initializing', percentage: 0, message: 'Generation queued'}`. Mirrors `ContentIdeaController:518-525` pattern.
- **Backend writes `input_collection`** when dispatching `GenerateLinkedInPost` job — second entry: `{step: 'input_collection', percentage: 5, message: 'Loaded blog post: {title}'}`. This guarantees the modal shows at least 2 log entries immediately after cron fires, before the plugin even starts — no confusing empty terminal.
- **Plugin writes everything else** (`linkedin_brief` → `completed` via `PUT /progress` endpoint). Plugin never emits `initializing` or `input_collection` — those are backend-owned.

### Phase definition — 4-card grid

```
┌─────────────┬─────────────┬─────────────┬─────────────┐
│  BRIEF      │  CONTENT    │  VALIDATE   │  SCHEDULE   │
│  [Sonnet]   │  [Sonnet]   │  [Sonnet]   │  [Sonnet]   │
│  ✓ Done     │  ⌛ 42%     │  ○ Wait     │  ○ Wait     │
│             │  [carousel] │             │             │
├─────────────┼─────────────┼─────────────┼─────────────┤
│  ● Input    │  ● Hook     │  ○ AI slop  │  ○ Dispatch │
│  ● Format   │  ● Body     │  ○ Bait     │  ○ Preview  │
│  ● Hook     │  ● Link cmt │  ○ Hashtag  │  ○ Sched_at │
│  ● Pillar   │  ● Hashtags │  ○ Link     │  ○ Cancel   │
│  ● Quote    │  ⌛ Slides  │  ○ Depth    │             │
│             │    (7/10)   │  ○ Hard-fail│             │
├─────────────┼─────────────┼─────────────┼─────────────┤
│ /linkedin-  │ /linkedin-  │ /linkedin-  │ /linkedin-  │
│ brief       │ convert +   │ validate    │ schedule    │
│ 0-20%       │ carousel    │ 70-90%      │ 90-100%     │
│             │ 20-65%      │             │             │
└─────────────┴─────────────┴─────────────┴─────────────┘
```

**Phase → step mapping** (drives `stepIsDone()`, `stepIsActive()`, `phaseStatus()` helpers):

| Phase | Pct range | Steps in this phase |
|---|---|---|
| BRIEF | 0-20% | `input_collection`, `linkedin_brief`, `linkedin_brief_complete` |
| CONTENT | 20-65% | `linkedin_convert`, `linkedin_convert_complete`, `linkedin_carousel`, `linkedin_carousel_complete` |
| VALIDATE | 65-90% | `linkedin_validate`, `linkedin_validate_complete` |
| SCHEDULE | 90-100% | `linkedin_schedule`, `completed` |
| (failure) | — | `failed` → bar turns red, log entry shows error |

**CONTENT card format adaptation**:
- Header shows format badge: `[text]` amber or `[carousel]` cyan, reading from `draft.format`
- `Slides (N/10)` sub-step pill is conditional:
  - Text format: greyed out (`opacity-40`) with label `Slides (N/A)`, never receives check mark
  - Carousel format: active pill, shows `Slides (7/10)` counter pulled from `draft.carousel_slides.length`
- Skill footer **adapts to format** (matches Slides pill greying principle):
  - Text format: `/linkedin-convert · 20-65%` only
  - Carousel format: `/linkedin-convert + /linkedin-carousel · 20-65%`
- Active skill determined by current_step prefix (`linkedin_convert_*` vs `linkedin_carousel_*`)
- **Text-format progress bar jump is expected**: for text posts, `linkedin_convert_complete` fires at 45% then `linkedin_validate` starts at 65% — the bar visually jumps 45%→65% with no intermediate callback. This is by design (no artificial steps in the dead band). Plugin implementers: do NOT fill this gap with fake progress entries.

### Shared Vue component

New `frontend/src/components/admin/LinkedInProgressPanel.vue`:

```
Props:
  draftId: Number (required — component runs its own useLinkedInDraft(id) query
                   for independent 3s polling, does NOT receive draft object
                   from parent to avoid staleness from parent's 30s list cache)
  compact: Boolean (default false — removes the outer card chrome for inline use)

Slots: none (self-contained)

Emits:
  regenerate: Number (draftId) — fired when user clicks Retry/Regenerate on failed state;
                                 parent handles the mutation via useRegenerateLinkedInDraft
  close: void — fired by explicit close action (modal only listens to this)

Internal state: runs its own useLinkedInDraft(draftId) — guarantees 3s polling even
                when parent's list has 30s staleTime
```

**Staleness fix rationale**: If the panel received a `draft: Object` prop from a list query, changes to `progress_log` would only flow when the parent refetches. Queue list uses `staleTime: 30_000` → the modal progress bar would freeze for 30s at a time despite the 3s poll convention. Running `useLinkedInDraft(draftId)` internally gives the panel its own polling loop, scoped to when it's mounted. Disabled automatically when parent unmounts (closes modal).

Markup structure (mirrors `ContentEngine.vue:625-728`):
1. Header strip: step label (from `formatStepName(current_step)`) + pct mono-font — use `progress_percentage ?? 0` to null-coalesce (column is unsigned tinyint default 0, but defensive for legacy rows pre-migration)
2. Progress bar (gradient gold→amber, red on `current_step === 'failed'`) — width uses `(draft.progress_percentage ?? 0) + '%'`
3. 4-card grid (responsive: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`)
4. Generation Log terminal (`bg-neutral-950`, auto-scroll via `ref="logContainer"` + `nextTick` + `scrollTop = scrollHeight`)
5. Status footer: process-alive pulse dot (computed: `now() - updated_at < 60s`) + "Process running" / "Completed" / "Process stopped" label + **Regenerate button when `current_step === 'failed'`** (emits `regenerate` event; parent modal/detail view handles via `useRegenerateLinkedInDraft` mutation then closes/navigates)

### Integration points

**LinkedInProgressModal.vue** (new wrapper):
- Props: `draftId: Number | null` (null = closed, number = open)
- Fixed overlay (`fixed inset-0 z-50 bg-black/50`), click-outside closes
- Inner card: `max-w-6xl w-full mx-4 p-6 max-h-[90vh] flex flex-col`
- Renders `<LinkedInProgressPanel :draftId="draftId" />` when draftId is non-null
- Listens to panel's `@regenerate` — calls `useRegenerateLinkedInDraft()` then emits close + navigates parent to new draft detail via `$router.push`
- Emits `close` event (or uses `v-model:draftId` two-way binding)

**LinkedInQueueList.vue** (edit existing):
- Add "View Progress" kebab action for rows where `status ∈ {pending_generation, generating, validating, failed}` (include `failed` so operators can see the log + regenerate from the modal)
- Action opens `LinkedInProgressModal` with current draft ID
- Modal state: `const activeProgressDraftId = ref(null)`, open = set id, close = null
- Mount `<LinkedInProgressModal v-model:draftId="activeProgressDraftId" />` once at the end of the template

**LinkedInDraftDetail.vue** (edit existing):
- Conditionally render `<LinkedInProgressPanel :draftId="Number(route.params.id)" :compact="true" @regenerate="handleRegenerate" />` above the content preview column
- Condition: `draft.status ∈ {pending_generation, generating, validating}` (exclude `failed` from inline — detail view's own action bar already has Regenerate button, avoid duplicate CTA)
- When status completes (awaiting_publish / published / manual_review), panel disappears — existing state_log timeline on metadata panel already covers historical view

### Data Integration Map — Design Phase 2

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Schema: 3 new progress columns | New migration `2026_04_23_000002_*` | ❌ New | Additive, non-breaking |
| Plugin callback endpoint | `PUT /api/automation/linkedin/{id}/progress` | ❌ New | Mirror Content Engine `PUT /api/automation/content-ideas/{id}/progress` |
| Real-time polling | `useLinkedInDraft(id)` with `refetchInterval: 3_000` | ✅ | Already in existing composable at `frontend/src/composables/useLinkedInDrafts.js:72-76` — pauses automatically on terminal states. Panel runs this query internally, not through prop-passed draft object (staleness fix) |
| Phase card markup | Copy from `ContentEngine.vue:640-674` | ✅ (reference) | Swap `articleGenerationPhases` → `linkedinPipelinePhases` |
| Log streaming terminal | Copy from `ContentEngine.vue:677-695` | ✅ (reference) | Same auto-scroll ref pattern |
| Phase helper functions | `stepIsDone`, `stepIsActive`, `phaseStatus`, `phaseHeaderColor`, `phaseModelBadge`, `phaseStatusColor` | ✅ (reference) | Adapt from ContentEngine.vue, parameterize over `linkedinPipelinePhases` |
| Format-specific carousel greying | `draft.format === 'text'` check on Slides pill | ❌ New | 1-line computed property |
| Modal trigger from Queue list | "View Progress" button — kebab menu extension | ❌ New | ~5 LoC in LinkedInQueueList.vue |
| Inline panel on detail view | Mount panel when status in-progress | ❌ New | 1 `v-if` + 1 component mount in LinkedInDraftDetail.vue |
| Failed state rendering | `current_step === 'failed'` → red progress bar + red log entries | ✅ (reference) | Same pattern as Content Engine |

### Implementation feasibility

**100% feasible — no placeholders.** Every building block exists:
- Schema additions are 3 nullable columns (trivial migration)
- Backend controller method mirrors existing Content Engine progress handler
- Frontend component is 70% markup copy from `ContentEngine.vue:613-728` with phase definitions swapped
- Composable polling already implemented and active for in-progress statuses

**What waits on plugin**: actual `progress_log` entries + `progress_percentage` updates arrive only after plugin calls `PUT /automation/linkedin/{id}/progress`. Until plugin Phase D3 ships, the UI renders deterministically:
- Cold-start draft (pending_generation, no progress data): "Waiting for plugin heartbeat..." placeholder in log area, progress bar at 0%
- In-progress with plugin data: live updates every 3s
- Completed draft: final state preserved, panel auto-hides after status → terminal

### Success criteria (shipped when all pass)

**Backend:**
- [ ] Migration runs cleanly on fresh DB + existing production DB (schema parity with ContentIdea progress columns)
- [ ] `LinkedInPost::PROGRESS_STEPS` constant contains the 13 whitelisted step names
- [ ] `PUT /api/automation/linkedin/{id}/progress` handler accepts valid step + percentage + message, returns **200 with `{success, data: $logEntry}`** (Content Engine parity)
- [ ] Feature test: step NOT in `PROGRESS_STEPS` → **422 with `errors.step` field** (new test, not inherited from Content Engine)
- [ ] Feature test: inserting 101 `progress_log` entries trims to exactly 100 (rotating, `assertJsonCount(100, 'data.progress_log')`)
- [ ] Feature test: percentage outside 0-100 → clamped, not rejected
- [ ] Backend writes `initializing` + `input_collection` log entries when cron creates the draft AND dispatches the generation job (2 entries before plugin emits anything)

**Frontend:**
- [ ] `LinkedInProgressPanel.vue` accepts `draftId: Number` prop (not `draft: Object`) and runs its own `useLinkedInDraft(draftId)` query — verifiable by Vue Devtools showing independent query instance when mounted
- [ ] Panel renders 4 phase cards with correct sub-step pills + format-adaptive Slides pill
- [ ] CONTENT card skill footer adapts: `/linkedin-convert` for text, `/linkedin-convert + /linkedin-carousel` for carousel
- [ ] Modal opens from Queue list "View Progress" kebab action for in-progress rows (including `failed`)
- [ ] Inline panel renders on detail view when `status ∈ {pending_generation, generating, validating}` (excludes `failed` to avoid duplicate Regenerate CTA with detail's action bar)
- [ ] Panel emits `regenerate` event when Retry button clicked on failed state; parent handles mutation
- [ ] Progress bar turns red on `current_step === 'failed'`
- [ ] Log auto-scrolls to latest entry on new `progress_log` item
- [ ] Poll interval 3s; pauses when status terminal (verified by existing composable behavior at `useLinkedInDrafts.js:72-76`)
- [ ] Progress bar width uses `(progress_percentage ?? 0) + '%'` null-safe expression

**Docs:**
- [ ] Root CLAUDE.md "API Routes" section's Admin Routes list includes `/api/admin/social-drafts/*` → updated to reference the new `/automation/linkedin/{id}/progress` endpoint under Automation Routes
- [ ] Root CLAUDE.md "LinkedIn Admin UI Pipeline" section extended with Progress Modal subsection referencing component paths + callback contract

### Appendix D — Plugin emit checklist (informational, for plugin implementers)

When plugin skills execute, they should emit `PUT /automation/linkedin/{id}/progress` calls roughly as follows:

```
# /linkedin-brief (0-20%)
step=input_collection,        pct=5,   msg="Loaded blog post: {title}"
step=linkedin_brief,          pct=10,  msg="Analyzing blog structure: {N} H2 sections detected"
step=linkedin_brief,          pct=15,  msg="Format decision: {text|carousel} (listicle heuristic: {yes|no})"
step=linkedin_brief_complete, pct=20,  msg="Hook formula: {PAS|AIDA|...}, pillar: {AI Generalist|...}"

# /linkedin-convert (20-45%)
step=linkedin_convert,        pct=25,  msg="Drafting hook line..."
step=linkedin_convert,        pct=35,  msg="Body expansion: {char_count} chars so far"
step=linkedin_convert,        pct=42,  msg="Char gate check: {char_count} chars (sweet spot 1100-1300)"
step=linkedin_convert_complete, pct=45, msg="Text post finalized, hashtags: {list}"

# /linkedin-carousel (45-65%) — SKIP if format=text
step=linkedin_carousel,       pct=50,  msg="Generating 8 slides..."
step=linkedin_carousel,       pct=58,  msg="Slide 4/8: Direct Answer Block authored"
step=linkedin_carousel_complete, pct=65, msg="Carousel JSON complete: 8 slides with image prompts"

# /linkedin-validate (65-90%)
step=linkedin_validate,       pct=70,  msg="AI-Slop scan: {N} violations"
step=linkedin_validate,       pct=78,  msg="Hashtag count: {N} (rule: 3-5)"
step=linkedin_validate,       pct=85,  msg="Depth Score: {score} — {pass|fail} threshold {80}"
step=linkedin_validate_complete, pct=90, msg="Validation complete, decision: {awaiting_publish|manual_review}"

# /linkedin-schedule (90-100%)
step=linkedin_schedule,       pct=95,  msg="Posting to backend: POST /schedule"
step=completed,               pct=100, msg="Scheduled at {time}, cancel window ends {time}"

# On any failure
step=failed,                  pct=current_pct (not reset), msg="Plugin error: {message}"
```

Plugin emit frequency: at phase entry, major sub-step, and phase complete. Not every internal LLM token — aim for ~3-5 entries per skill.

---

**Design Phase 2 complete — session-3 review fixes integrated.** Ready for `/gaspol-plan` to append implementation plan covering: migration → backend job dispatch with initial log entries → controller method (200 response + whitelist) → `PROGRESS_STEPS` constant on model → shared Vue component (draftId prop, internal query, regenerate emit) → modal wrapper (v-model:draftId) → integration in Queue list + Detail view → CLAUDE.md sync.

**Session-3 review resolution summary:**
- Critical 1 (schema mismatch): Fixed — migration now uses `VARCHAR(50)` + `default(0)` matching ContentIdea exactly
- Critical 2 (input_collection authorship): Fixed — backend writes `initializing` + `input_collection` on cron dispatch; plugin never emits those two
- Important 1 (204 vs 200): Fixed — 200 with `{success, data: $logEntry}` matches Content Engine `routes/api.php:436`
- Important 2 (text-format bar jump): Documented as expected behavior; plugin implementers instructed not to fill the 45%→65% dead band
- Important 3 (Retry/Regenerate gap): Fixed — panel emits `regenerate` event on failed state, modal + detail view handle mutation
- Important 4 (modal staleness bug): Fixed — panel accepts `draftId: Number`, runs own `useLinkedInDraft()` query internally for independent 3s polling
- Important 5 (skill footer adaptation): Fixed — CONTENT card skill footer format-adapts like the Slides pill
- Minor 1 (whitelist constant): Added `LinkedInPost::PROGRESS_STEPS`
- Minor 2 (null coalescing): Specified `(progress_percentage ?? 0)` in markup spec
- Minor 3 (composable line references): Added `useLinkedInDrafts.js:72-76` in Data Integration Map
- Minor 4 (CLAUDE.md section specificity): Success criteria now specify which sections extend
