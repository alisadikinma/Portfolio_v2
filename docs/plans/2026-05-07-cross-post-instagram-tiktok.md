# Cross-post LinkedIn Carousel to Instagram + TikTok

**Date:** 2026-05-07
**Status:** Design locked — awaiting `/gaspol-plan` to expand into Implementation Plan
**Owner:** Ali Sadikin

## Design

### Problem statement

Blog posts currently auto-fan out to LinkedIn as carousel posts (mature pipeline since April 2026). Instagram and TikTok are entirely missing from the auto-cross-post flow despite reusable visual assets (`linkedin_posts.carousel_slides[]` already produces 5–10 polished 1080×1350 PNGs per blog post via `/carousel-gen`).

Operator wants those PNGs to also reach IG and TikTok — but each platform demands different copy (caption / hashtag / title), different posting cadence, and (for TikTok) usually a topic-matched background sound. LinkedIn copy doesn't translate 1:1: TikTok captions favor sub-150-char hooks, IG captions tolerate up to 2200 chars but reward niche hashtag mix, posting time best-practices diverge sharply per platform.

### Scope (v1 — locked)

✅ **In scope**
- Reuse existing carousel slide PNGs from LinkedIn pipeline (zero new image work, zero video conversion)
- Per-platform draft generation (caption + hashtags + title + suggested time) via new `social-short-form-writer` plugin
- Two new admin sections (Instagram + TikTok) mirroring the LinkedIn Posts/Queue/Calendar UI pattern
- Per-platform manual approval gate (operator approves IG and TikTok separately, not bundled with LinkedIn)
- Telegram notification per platform when draft is approved + ready for manual publish
- Per-platform best-practice RAG (caption format, hashtag strategy, hook patterns) seeded by `gaspol-research`
- Per-platform posting time research via existing `posting_time_rules` infrastructure (already multi-platform)
- Per-blog-post folder upload to operator's **Google Drive** via Service Account (folder name = `{date}-{slug}`, contains all slide PNGs renamed sequentially) — operator opens folder on mobile, downloads to camera roll, uploads in IG/TikTok app

❌ **Out of scope (explicit YAGNI)**
- Music API integration (TikTok CML, Pixabay, Mubert, AI-generated) — operator adds music manually in app
- Video generation pipeline (FFmpeg, Remotion, Shotstack) — static carousel only on all platforms
- Instagram Reels format — IG uses static Carousel only (silent, music added manually in app)
- Meta Graph API + TikTok Login Kit OAuth flows — manual publish, no auto-publish to IG/TikTok
- Bundled approval (LinkedIn approve = IG + TikTok approve) — explicitly rejected, operator wants per-platform gates
- Slide image regeneration per platform — same PNGs used 1:1 across all 3 platforms

### Architecture overview

```
Blog post hits "published" state
   │
   ├─→ LinkedIn pipeline (existing, untouched)
   │      └─ /linkedin-gen → /carousel-gen → LinkedInCarouselImageService
   │      └─ linkedin_posts.carousel_slides[] populated with rendered PNG URLs
   │      └─ Manual review → Approve → publish (auto via OAuth — existing)
   │
   └─→ AFTER LinkedIn carousel slide rendering completes (status_log event hook)
          ├─→ GenerateInstagramPost job dispatched
          │     └─ /instagram-gen plugin via SSH (Sonnet)
          │     └─ Reads blog content + carousel slides + posting_time_rules
          │     └─ Emits {title, caption, hashtags[], suggested_time, validation}
          │     └─ Insert instagram_posts row, status=awaiting_review
          │
          └─→ GenerateTiktokPost job dispatched
                └─ /tiktok-gen plugin via SSH (Sonnet)
                └─ Same input + tiktok-specific RAG
                └─ Emits {title, caption, hashtags[], music_suggestion, suggested_time}
                └─ Insert tiktok_posts row, status=awaiting_review

Operator opens /admin/instagram-queue or /admin/tiktok-queue
   │
   ├─→ Reviews draft (caption + hashtags + slide preview + suggested time)
   ├─→ Edits if needed (inline char counter, hashtag chip editor)
   ├─→ Clicks Approve → status=awaiting_manual_publish
   │       └─ Telegram notif fires with checklist + ZIP download link + admin URL
   │
   └─→ Operator publishes in mobile app (adds music in TikTok / Music sticker in IG)
   │
   └─→ Operator returns to admin → "Mark as published" form → pastes public URL
          └─ status=published_externally, published_at=now()
```

### Per-platform FSM (5 states, simpler than LinkedIn's 8)

```
pending_generation → generating → awaiting_review → awaiting_manual_publish → published_externally
                              ↘ failed                                      ↘ cancelled
```

Notable differences vs LinkedIn FSM:
- No `validating` state — plugin output validation happens inline during `generating`
- No `awaiting_publish` — there is no auto-publish step (manual app workflow)
- New terminal `published_externally` (vs LinkedIn's `published`) — semantically distinct: we never confirmed publish via API, only operator self-reported
- No cancel-window timer — `awaiting_manual_publish` persists until operator acts (no auto-cascade)

Reuses existing `HasStatusTransitions` trait + `PipelineGuard` (now enum-class-generic per CLAUDE.md April 23 refactor).

### Schema (2 new tables, ~13 cols each)

```sql
CREATE TABLE instagram_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  linkedin_post_id BIGINT UNSIGNED NULL,
  post_id BIGINT UNSIGNED NOT NULL,
  status ENUM(...) NOT NULL DEFAULT 'pending_generation',
  title VARCHAR(150) NULL,                    -- IG "first-line hook" (no native title field)
  caption TEXT NULL,                          -- max 2200 chars
  hashtags JSON NULL,                         -- ["#hashtag1", ...]
  scheduled_at TIMESTAMP NULL,                -- when operator should publish
  published_at TIMESTAMP NULL,
  external_url VARCHAR(500) NULL,             -- pasted by operator after manual publish
  last_error TEXT NULL,
  pipeline_state_log JSON NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (linkedin_post_id) REFERENCES linkedin_posts(id) ON DELETE SET NULL,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  -- App-level invariant: one live (deleted_at IS NULL) draft per post_id; enforced in regenerate handler
  INDEX idx_instagram_post_status (status, deleted_at),
  INDEX idx_instagram_post_scheduled (scheduled_at)
);

CREATE TABLE tiktok_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  linkedin_post_id BIGINT UNSIGNED NULL,
  post_id BIGINT UNSIGNED NOT NULL,
  status ENUM(...) NOT NULL DEFAULT 'pending_generation',
  title VARCHAR(100) NULL,                    -- TikTok video/post title field, hard 100-char cap
  caption TEXT NULL,                          -- max 2200 chars
  hashtags JSON NULL,
  music_suggestion VARCHAR(255) NULL,         -- text hint for operator (mood/genre/sound name)
  scheduled_at TIMESTAMP NULL,
  published_at TIMESTAMP NULL,
  external_url VARCHAR(500) NULL,
  last_error TEXT NULL,
  pipeline_state_log JSON NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (linkedin_post_id) REFERENCES linkedin_posts(id) ON DELETE SET NULL,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  INDEX idx_tiktok_post_status (status, deleted_at),
  INDEX idx_tiktok_post_scheduled (scheduled_at)
);
```

Slides themselves are NOT denormalized into these tables — they're read live from `linkedin_posts.carousel_slides[].image_url` via the FK. If LinkedIn carousel is regenerated, IG and TikTok drafts naturally reference the new slides.

### Plugin: `social-short-form-writer` (new repo)

Location: `D:\Projects\claude-plugin\social-short-form-writer`

Mirrors `linkedin-post-writer` repo conventions (compile-refs.ts, skills/ folder, docs/rag/ folder).

```
docs/rag/
  social-base/                          ← shared (mobile-first hook, attention curve, anti-AI-slop)
    01-mobile-first-hook.md
    02-attention-curve.md
    03-hashtag-fundamentals.md
  instagram-playbook/                   ← IG-specific (caption format, alt text, niche hashtag strategy)
    01-caption-format.md
    02-hashtag-mix.md
    03-hook-patterns.md
    04-carousel-storytelling.md
  tiktok-playbook/                      ← TikTok-specific (3-second hook, sound integration, hashtag mix)
    01-three-second-hook.md
    02-hashtag-mix.md
    03-hook-patterns.md
    04-photo-mode-storytelling.md
    05-music-suggestion-language.md     ← guides plugin on how to phrase music_suggestion field
skills/
  instagram-gen/
    SKILL.md                            ← generation prompt + output schema
    schema.ts                           ← Zod schema for output validation
  tiktok-gen/
    SKILL.md
    schema.ts
scripts/
  compile-refs.ts                       ← bundles RAG into 2 compiled refs:
                                        ←   refs-instagram.md (~80KB)
                                        ←   refs-tiktok.md (~80KB)
package.json
README.md
CHANGELOG.md
```

Plugin output schema (both skills) — constraints encoded from `docs/research/2026-05-07-ig-tiktok-best-practice/` (sourced post-December-2025 platform changes):

```typescript
// Instagram envelope
{
  status: 'complete' | 'failed',
  platform: 'instagram',
  title: string | null,                    // first-line hook, < 125 chars (mobile preview cutoff)
  caption: string,                         // 100-2200 chars, sweet spot 600-1500
  hashtags: string[],                      // HARD MAX 5 (Dec 2025 IG platform enforcement — excess silently removed)
  suggested_time: string,                  // ISO 8601 — Tue-Thu 07:00-09:00 WIB primary
  reasoning: string,
  validation: { passed, score?, issues[] }
}

// TikTok envelope
{
  status: 'complete' | 'failed',
  platform: 'tiktok',
  title: string | null,                    // hard 100-char cap (TikTok native)
  caption: string,                         // 100-2200 chars; FIRST 150 CHARS critical (TikTok caption is now a search index 2025-2026)
  hashtags: string[],                      // 5-8 entries, mix of trending + niche
  music_suggestion: string,                // operator hint, vocabulary: '[genre] | [mood] | [tempo]' — never song titles (stale in 2 weeks)
                                            // B2B tech default: 'lofi hip-hop | focused | slow-medium'
  suggested_time: string,                  // Tue-Thu 07:00-09:00 OR 20:00-22:00 WIB
  reasoning: string,
  validation: { passed, score?, issues[] }
}
```

**Platform-specific hard rules (encoded as Zod refinements in `schema.ts`):**

| Constraint | Instagram | TikTok | Source |
|---|---|---|---|
| Hashtag count | Exactly 3–5 (HARD CAP — refuse output >5) | 5–8 | `02-instagram-hashtag-strategy.md` (Dec 2025 platform change) |
| Title length | ≤ 125 chars (preview cutoff) | ≤ 100 chars (native field cap) | Research INDEX |
| First-150-chars caption optimization | Helpful (preview window) | **Mandatory** — search-index keyword placement | `05-tiktok-caption-format.md` |
| Music suggestion field | Absent | Required, vocabulary-constrained | `08-tiktok-music-suggestion-language.md` |
| Hook compression vs LinkedIn | LI ~800 → IG ≤ 125 | LI ~800 → TikTok slide-1 ≤ 8 words | `INDEX.md` cross-platform compression rule |

Output emitted to stdout, parsed via balanced-brace scanner in backend (lifted from `LinkedInGenerationService::parseOrchestratorOutput` per CLAUDE.md May 2 fix — tolerates Sonnet preamble + fenced JSON).

### Backend services

```
app/Enums/
  InstagramPostStatus.php                 ← 7 cases mirroring FSM
  TiktokPostStatus.php                    ← same
app/Models/
  InstagramPost.php                       ← uses HasStatusTransitions trait, returns InstagramPostStatus::class
  TiktokPost.php                          ← same with TiktokPostStatus
app/Services/
  InstagramGenerationService.php          ← SSH-invokes /instagram-gen, parses JSON, advances FSM
  TiktokGenerationService.php             ← same pattern
  CrossPostBundleService.php              ← creates ZIP of slide PNGs for download endpoint
app/Jobs/
  GenerateInstagramPost.php               ← queued, 360s timeout
  GenerateTiktokPost.php                  ← same
app/Http/Controllers/Api/Admin/
  InstagramDraftController.php            ← 7 endpoints (list/show/update/regenerate/approve/cancel/mark-published)
  TiktokDraftController.php               ← same
  CrossPostDownloadController.php         ← single-action, returns ZIP of slides
app/Console/Commands/
  ProcessLinkedInCarouselDoneEvents.php   ← every 2 min — finds LinkedIn drafts where slides just finished rendering, dispatches IG + TikTok generation jobs
  PurgeLowViralityInstagramDrafts.php     ← daily 04:30 WIB
  PurgeLowViralityTiktokDrafts.php        ← daily 05:00 WIB
```

**Trigger mechanism:** event-driven via existing `pipeline_state_log` on `linkedin_posts`. New cron `ProcessLinkedInCarouselDoneEvents` polls every 2 min for drafts that:
1. `format='carousel'`
2. All `carousel_slides[]` entries have `image_status='done'`
3. No matching live row in `instagram_posts` or `tiktok_posts` (use UNIQUE invariant check)
4. Source `ContentIdea.virality_score >= linkedin_virality_min_score` (reuse existing virality gate)

For drafts matching, dispatch both jobs in parallel (queue worker handles concurrency). Idempotent — if jobs already exist, skip.

Alternative considered: Laravel event/listener pattern (`LinkedInCarouselSlidesRendered` event). Rejected because the cron-poll path is more robust to worker crashes mid-event-fire and matches existing `linkedin:scan-blog` precedent.

### Posting time integration

**Existing infrastructure works as-is.** Per CLAUDE.md May 6 entry, `posting_time_rules` table already supports `platform=instagram` and `platform=tiktok` enum values, and `ResearchPostingTimeRules` artisan command is platform-parameterized.

Operator post-deploy steps (one-time):
```bash
php artisan posting-rules:research --platform=instagram --audience=b2b_tech
php artisan posting-rules:research --platform=tiktok --audience=b2b_tech
```

Each command runs ~5 min (Sonnet + WebSearch + balanced-brace JSON parser per CLAUDE.md May 6) and seeds 168 rules per platform (24 hours × 7 days).

Quarterly cron entry for LinkedIn (`0 3 1 */3 *`) gets extended to also fire for IG + TikTok in `routes/console.php`:

```php
Schedule::command('posting-rules:research --platform=linkedin')
    ->cron('0 3 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
Schedule::command('posting-rules:research --platform=instagram')
    ->cron('0 4 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
Schedule::command('posting-rules:research --platform=tiktok')
    ->cron('0 5 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
```

Plugin's `suggested_time` field derives from these rules at generation time:
1. Query top 5 optimal slots (best_score >= 80) in next 7 days for `(platform, audience=b2b_tech)`
2. Filter to slots > 2 hours from now
3. Pick first → ISO 8601 string

Backend persists to `instagram_posts.scheduled_at` / `tiktok_posts.scheduled_at`.

### Admin UI surface

3 new routes per platform (mirror LinkedIn template):

```
/admin/instagram-posts          ← Calendar view (mirror LinkedInPostsCalendar.vue)
/admin/instagram-queue          ← Triage table (mirror LinkedInQueueList.vue)
/admin/instagram-drafts/:id     ← Detail page

/admin/tiktok-posts             ← same
/admin/tiktok-queue
/admin/tiktok-drafts/:id
```

Sidebar (`AdminLayout.vue`) gains 2 new sections under existing LinkedIn section:
```
- LinkedIn (existing)
  - Posts
  - Queue
- Instagram (NEW)
  - Posts (calendar)
  - Queue
- TikTok (NEW)
  - Posts (calendar)
  - Queue
```

Detail page key UI elements:

| Element | Behavior |
|---|---|
| Status hero panel | Same component as `LinkedInDraftDetail.vue` — color-coded chip, mood gradient, primary CTA per status |
| Slide preview strip | Reads `linkedin_posts.carousel_slides[].image_url` via FK, renders 1st 5 thumbnails |
| Title editor | Single-line input, char counter (IG no native title — shows as "first-line hook"; TikTok hard 100-char cap) |
| Caption editor | Multi-line textarea, char counter, IG max 2200 / TikTok max 2200 |
| Hashtag chip editor | Click to add/remove, autosuggest from blog meta_keywords + recent platform-specific tags |
| Music suggestion field (TikTok only) | Read-only text hint from plugin output ("Try upbeat tech beats — search 'tech innovation' or 'startup vibe'") |
| Suggested time picker | Auto-populated from posting_time_rules, operator can override via datetime picker |
| Approve button | `POST /admin/{platform}-drafts/{id}/approve` → status=awaiting_manual_publish + Telegram notif fires |
| Download slides ZIP | Dedicated download button → `GET /admin/{platform}-drafts/{id}/download-slides` |
| Mark as published form | Pastable public URL field + "Mark as published" button → status=published_externally |
| State log timeline | Same component as LinkedIn (humanized via `transitionSummary()`) |

Reuses TanStack Query composable pattern from `useLinkedInDrafts.js`:
```
frontend/src/composables/
  useInstagramDrafts.js                   ← 30s staleTime, refetchOnMount:'always'
  useTiktokDrafts.js                      ← same
```

### Telegram notification design

Two new opt-in toggles extend existing `telegram` settings group (per CLAUDE.md April 20 entry):

```
telegram_notify_instagram_ready          ← fires when IG draft → awaiting_manual_publish
telegram_notify_tiktok_ready             ← same for TikTok
```

Notification body template (Instagram example):

```
📸 Instagram draft ready for manual publish

Title: {title}
Scheduled: {scheduled_at} WIB
Caption preview: {caption_first_120_chars}…

📁 Slides on Drive: {gdrive_folder_url}
   (Open on mobile → Save All to camera roll)

Quick checklist:
1. Open Drive folder above on phone
2. Save all PNGs to camera roll (sequence preserved by filename)
3. Open IG app → New post → Carousel → select all in order
4. Paste caption from admin: {caption}
5. Add hashtags: {hashtags_first_5}…
6. Tap Music sticker → search topic, add 30s clip
7. Set time to {scheduled_at} or publish immediately
8. Return to admin: {admin_url} → Mark as published

Cancel: tap link to mark cancelled (no nag follow-up)
{cancel_url}
```

TikTok variant uses the same shape but checklist mentions TikTok Drafts + music_suggestion text.

When `gdrive_folder_url` is null (Drive disabled OR upload pending), the `📁 Slides on Drive:` line is replaced with `📁 Slides: pending Drive upload — retry via admin button if delayed.`

Notification dispatched via existing `App\Jobs\DispatchTelegramNotification` queued job (per CLAUDE.md April 20 entry — already wires through `App\Services\TelegramNotificationService`).

### Cross-post asset distribution — Google Drive (NOT ZIP)

Slides are uploaded to operator's Google Drive in a per-blog-post folder. Folder URL surfaces to operator via Telegram notif + admin UI link.

**Upload trigger:** Once per blog post — fired by `ScanLinkedInForCrossPost` cron after creating IG + TikTok pending rows. Idempotent — already-uploaded posts skipped via `linkedin_posts.gdrive_folder_id` sentinel.

**Folder structure:**
```
Portfolio Cross-Post/                               ← top-level parent (created once, shared with SA)
├── 2026-04-25-vibe-coding-surveillance/            ← per-post folder = {YYYY-MM-DD}-{slug}
│   ├── 01-cover.png
│   ├── 02-hook.png
│   ├── 03-act1.png
│   ├── …
│   └── 09-cta.png
├── 2026-04-29-llm-agent-evaluation/
│   └── …
└── …
```

**Auth:** Service Account (no operator OAuth flow). One-time GCP setup:
1. Create GCP project + enable Drive API
2. Create Service Account, download JSON key
3. Operator creates top-level "Portfolio Cross-Post" folder in their Drive UI
4. Operator shares it with SA email (Editor permission)
5. Operator pastes parent folder ID into `.env` as `GOOGLE_DRIVE_PARENT_FOLDER_ID`

**Folder permission:** Each per-post folder is set to `anyone with link → reader` so operator can open from mobile without Drive app authentication friction.

**Filename convention:** `{NN}-{role}.png` matches existing `LinkedInCarouselImageService` filename pattern (`alisadikinma-li-{draft_id}-slide-{NN}-{role}.png`) but sequential order enforced for camera-roll upload UX. Cover is always 01.

**New columns on `linkedin_posts`** (migration `2026_05_07_000003`):
- `gdrive_folder_id VARCHAR(100)` — Google's folder ID for idempotency lookup
- `gdrive_folder_url VARCHAR(500)` — shareable URL surfaced to operator
- `gdrive_uploaded_at TIMESTAMP` — last successful upload
- `gdrive_upload_error TEXT` — last failure for operator visibility

**Manual retry:** `POST /admin/linkedin-drafts/{id}/reupload-to-drive` re-runs upload service (force-refresh after Drive folder accidentally deleted, etc.)

**Disabled fallback:** When `GOOGLE_DRIVE_ENABLED=false`, IG + TikTok generation continues normally; Telegram notif simply omits the Drive folder line. Operator can still find PNGs locally on VPS at `storage/app/public/linkedin-carousel/`.

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Slide PNGs | `linkedin_posts.carousel_slides[].image_url` | ✅ existing | Reused 1:1, no regen, no per-platform variants |
| Blog content | `posts.translations.content` (EN preferred) | ✅ existing | Plugin reads via existing `/automation/posts/{id}` endpoint |
| ContentIdea pillar | `content_ideas.pillar` | ✅ existing | Plugin uses for tone calibration + virality gate |
| Posting time | `posting_time_rules` (platform=instagram/tiktok) | ✅ schema exists, ❌ data missing | Operator runs `posting-rules:research` once post-deploy to seed |
| Plugin RAG | `docs/rag/{instagram,tiktok}-playbook/` (new repo) | ❌ new | Seeded by `gaspol-research` (running in parallel) |
| Telegram service | `App\Services\TelegramNotificationService` | ✅ existing | Extend with 2 new notification types |
| Settings group `telegram` | `settings` table | ✅ existing | Add 2 keys: `telegram_notify_instagram_ready`, `telegram_notify_tiktok_ready` |
| FSM infrastructure | `HasStatusTransitions` + `PipelineGuard` | ✅ existing | Already enum-class-generic (April 23 refactor) — works for any BackedEnum |
| Calendar view | `LinkedInPostsCalendar.vue` | ✅ template | Copy + parameterize per platform |
| Composable pattern | `useLinkedInDrafts.js` | ✅ template | Copy + rename |
| Admin layout | `AdminLayout.vue` sidebar | ✅ existing | Append 2 nav sections |
| Cron infrastructure | `routes/console.php` + systemd worker + crontab | ✅ existing | Already documented in CLAUDE.md "VPS Background Process Setup" |
| Queue infrastructure | `portfolio-queue.service` (claudesn) | ✅ existing | All new jobs queue here |

### Anti-patterns explicitly avoided

- ❌ Mocking music API integration that we'll never use (TikTok CML, Pixabay) — operator does it manually
- ❌ Building video pipeline (FFmpeg/Remotion/Shotstack) — static carousel only
- ❌ Auto-publish cascade from LinkedIn approve — operator wants per-platform gates
- ❌ Bundled FSM (single status across 3 platforms) — each platform fails/succeeds independently
- ❌ Adding placeholder OAuth flows that won't work — manual publish only, no Meta/TikTok OAuth in v1
- ❌ Storing slide PNGs in `instagram_posts` / `tiktok_posts` rows — read live via FK to `linkedin_posts.carousel_slides[]`
- ❌ Premature unification (single `social_posts` table for all 3 platforms) — LinkedIn schema has 24 cols of platform-specific data, would dilute the IG/TikTok tables

### Implementation feasibility

| Item | Status |
|---|---|
| Reuse carousel slide PNGs | ✅ existing infra, zero new image work |
| FSM trait + guard | ✅ already enum-class-generic |
| Plugin pattern | ✅ proven 3x (article-content-writer, linkedin-post-writer, ai-image-carousel-prompt-gen) |
| SSH bridge to Claude CLI | ✅ existing `ARTICLE_GEN_*` and `LINKEDIN_GEN_*` env-var pattern reused |
| Empty MCP config flag | ✅ existing `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config` (April 29 leak fix) |
| Posting time rules table | ✅ multi-platform schema since May 6 |
| Telegram service | ✅ existing |
| Queue worker | ✅ existing systemd unit |
| RAG content (IG + TikTok) | ⚠️ pending — `gaspol-research` will produce, ~30 min |
| Operator manual workflow | ⚠️ ~3-5 min per post (download ZIP, upload to phone, paste caption, add music, publish) — expected trade-off |

### Estimated scope

| Phase | Effort | Dependencies |
|---|---|---|
| `gaspol-research` (IG + TikTok best practice → RAG seed) | ~30 min | None |
| Plugin authoring (`social-short-form-writer` v0.1) | ~3 days | Research done |
| Backend (services + jobs + 2 migrations + 2 controllers + 2 enums) | ~3 days | Plugin compiled-refs deployed to VPS |
| Frontend (6 new views + 2 composables + sidebar update) | ~3 days | Backend endpoints stable |
| Posting time research seed (per platform) | ~10 min operator | Backend deployed |
| End-to-end smoke test (1 blog post → 3 platforms) | ~2 hr | Everything wired |
| **Total v1** | **~9–10 working days** | |

### Deferred to Phase 2 (future iteration)

**API-based draft/schedule path** — investigated but rejected for v1 due to OAuth + app review overhead:
- TikTok Content Posting API has `INBOX/UPLOAD` mode (`POST /v2/post/publish/inbox/photo/init/`) that uploads carousels directly to user's TikTok app Drafts folder
- Instagram Graph API supports `scheduled_publish_time` parameter on container creation (min 10 min, max 75 days ahead)
- Both paths skip the manual ZIP-download dance, but require:
  - TikTok Login Kit OAuth + Content Posting API approval (~1-2 weeks app review)
  - Meta Graph API + Instagram Business account verification + `instagram_content_publish` scope (~2-4 weeks)
- Deferred to Phase 2 once v1 manual workflow validates IG + TikTok engagement is worth the OAuth investment
- Add ~3-4 days dev time on top of v1 scope

### Open questions for `/gaspol-plan`

These are design-locked but plan needs to detail HOW:

1. Exact cron timing for `ProcessLinkedInCarouselDoneEvents` (every 2 min vs 5 min — trade-off latency vs DB load)
2. Hashtag autosuggest source for chip editor (recent platform tags? blog meta_keywords? both?)
3. Char counter behavior at limit (hard block vs soft warning)
4. ZIP filename collision handling on regenerate (overwrite vs version suffix)
5. Operator role permission scope (does any auth user have access, or new `cross-post:manage` permission?)
6. Calendar view should show all 3 platforms unified, or 1 per platform? (Recommendation: 1 per platform for now, unified Phase 2)
7. Test fixtures — fake LinkedIn carousel with 9 slides for unit/feature tests
8. Migration ordering — instagram_posts and tiktok_posts independent, can ship in any order

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Wire automated cross-post fan-out from LinkedIn carousel → Instagram + TikTok drafts (text generation + scheduling only, manual operator publish via mobile app). Reuse existing carousel slide PNGs 1:1 (zero new image work). Ship platform-specific copy + posting-time intelligence via new `social-short-form-writer` plugin and 2 new admin sections that mirror the LinkedIn UI surface.

### Architecture Context (from CLAUDE.md)

Templates to mirror — do NOT reinvent:

| Need | Existing reference | Notes |
|---|---|---|
| Plugin repo layout | `D:\Projects\claude-plugin\linkedin-post-writer` | compile-refs.ts pattern (April 23, May 5 entries) |
| SSH bridge service | [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) | Including `parseOrchestratorOutput` balanced-brace scanner + May 2 fence-strip removal |
| Queued generation job | [`GenerateLinkedInPost`](backend/app/Jobs/GenerateLinkedInPost.php) | 360s timeout, 2 retries, idempotent skip-when-not-generatable |
| FSM trait | [`HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) | Already enum-class-generic — works for any BackedEnum (April 23 refactor) |
| FSM guard | [`PipelineGuard::advance`](backend/app/Services/PipelineGuard.php) | Uniform logging, rotating `pipeline_state_log` |
| Status enum pattern | [`LinkedInPostStatus`](backend/app/Enums/LinkedInPostStatus.php) | TRANSITIONS adjacency map |
| Admin controller | [`LinkedInDraftController`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) | 7-endpoint pattern (list/show/update/regenerate/approve/cancel/publish) |
| Posting time | `posting_time_rules` table + [`ResearchPostingTimeRules`](backend/app/Console/Commands/ResearchPostingTimeRules.php) | Multi-platform schema since May 6 |
| Frontend composable | [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) | 30s staleTime + refetchOnMount:'always' |
| Calendar view | [`LinkedInPostsCalendar.vue`](frontend/src/views/admin/LinkedInPostsCalendar.vue) | Native Date math, no date-fns dep |
| Queue view | [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue) | Status filter pills, status-mood gradient |
| Detail view | [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) | Hero panel + hashtag chip editor + state log timeline |
| Helpers module | [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js) | STATUS_META + transitionSummary + 18 inline SVG icons |
| Telegram service | `App\Services\TelegramNotificationService` + `App\Jobs\DispatchTelegramNotification` | Add new notification types via group `telegram` settings |
| ZIP streaming | None existing — net new | Use Laravel's `response()->streamDownload()` + `ZipArchive` PHP ext |

Empty MCP config gotcha (April 29 entry): every `claude -p` call from queue worker MUST pass `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config` to prevent MCP server leak. Helper method [`buildMcpFlags()`](backend/app/Services/LinkedInGenerationService.php) is the reference.

SSH key gotcha: queue-worker context (claudesn user) needs `/home/claudesn/.ssh/id_ed25519`, NOT `/var/www/.ssh/id_ed25519` (HTTP/www-data path).

### Tech Stack (existing — no new deps)

- Backend: Laravel 12 + PHP 8.2 + MySQL 8 + Sanctum 4 (existing stack)
- Plugin: Node.js 20 + TypeScript + Zod + tsx (existing pattern from `linkedin-post-writer`)
- Frontend: Vue 3.5 + Pinia 3 + TanStack Vue Query 5.90 + Tailwind 4 (existing)
- Model routing: Sonnet 4.6 for plugin gen calls (per Model Selection Policy added May 7 — content pipeline phases default Sonnet)

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Slide PNG URLs | `linkedin_posts.carousel_slides[].image_url` | Eloquent FK on `linkedin_post_id` | ✅ | Use directly via `$instagramPost->linkedinPost->carousel_slides` |
| Blog title + content | `posts.translations.content` (EN preferred) | Existing `Post::translation('en')` accessor | ✅ | Use directly |
| ContentIdea pillar/virality | `content_ideas.pillar` + `virality_score` | `Post::contentIdea()` BelongsTo | ✅ | Use directly |
| Posting time slots | `posting_time_rules` (platform=instagram/tiktok) | `PostingTimeRule::forPlatform()` scope | ✅ schema, ❌ data | Operator runs `posting-rules:research --platform=instagram` + `--platform=tiktok` once post-deploy |
| FSM trait | `HasStatusTransitions` | `transitionTo($status, $reason)` | ✅ | Wire to new IG + TikTok models, return new enum class via `statusEnumClass()` |
| FSM guard | `PipelineGuard::advance` | Static method | ✅ | Call from services on every status change |
| Plugin SSH bridge | New `InstagramGenerationService` + `TiktokGenerationService` | Net-new, copy from `LinkedInGenerationService` | ❌ | Create real services, NOT placeholders |
| Plugin compiled refs | `/home/claudesn/refs-instagram.md` + `refs-tiktok.md` | New env vars `SOCIAL_GEN_REFS_INSTAGRAM` + `SOCIAL_GEN_REFS_TIKTOK` | ❌ | Plugin scaffolding produces these via `npm run compile-refs`; operator deploys to VPS |
| Plugin RAG content | `docs/rag/{instagram,tiktok}-playbook/` (plugin repo) | File-based markdown | ❌ blocked on research | Background `gaspol-research` agent producing `docs/research/2026-05-07-ig-tiktok-best-practice/` — plan dependency for Phase F only |
| Telegram service | `TelegramNotificationService` | Existing methods | ✅ | Extend with `sendInstagramReady()` + `sendTiktokReady()` methods |
| Settings keys | `settings` table group=`telegram` + new group=`social_cross_post` | Existing `Setting::firstOrCreate()` | ✅ | Add 2 new toggles via new `SocialCrossPostSettingsSeeder` |
| Queue worker | `portfolio-queue.service` (claudesn) | Existing systemd unit | ✅ | All new jobs queue here |
| Empty MCP flag | `/home/claudesn/empty-mcp.json` | Existing file (April 29) | ✅ | Reuse via `buildMcpFlags()` helper pattern |
| SSH key (queue context) | `/home/claudesn/.ssh/id_ed25519` | Existing | ✅ | Configure in env via `SOCIAL_GEN_SSH_KEY` |
| Slide distribution to operator | `CrossPostDriveUploadService` (queued via `UploadCrossPostSlidesToDrive` job) | Net-new | ❌ | Google Drive Service Account auth, `google/apiclient` composer dep, per-blog-post folder, idempotent. Replaces ZIP approach (operator decision May 7) |
| Sidebar nav | [`AdminLayout.vue`](frontend/src/layouts/AdminLayout.vue) | Vue template | ✅ | Append 2 new sections under existing LinkedIn entry |
| Composable cache pattern | `useLinkedInDrafts.js` | TanStack Query keys | ✅ template | Copy + rename `linkedin` → `instagram`/`tiktok` |
| Calendar/Queue/Detail views | LinkedIn templates | Vue SFCs | ✅ template | Parameterize platform via prop OR copy + rename |
| Helper module | `linkedinHelpers.js` | STATUS_META + transitionSummary | ✅ template | New `socialPlatformHelpers.js` with `INSTAGRAM_STATUS_META` + `TIKTOK_STATUS_META` |

### Phase ordering rationale

Phases A → B → C → D → E are the **critical path** (each blocks the next).
Phases F → H run in parallel with C–E (plugin work doesn't block backend).
Phases I → J → K → L → M are **frontend-parallel** with backend (mock the API responses for early UI iteration if backend lags).
Phase N runs anytime after backend ships.
Phases O + P run last (smoke test + docs).

Recommended `gaspol-parallel` waves:
- Wave 1 (sequential): A → B → C
- Wave 2 (parallel): D + F + H (3 subagents)
- Wave 3 (parallel): E + I + J + K (4 subagents — but watch SSH bridge contention)
- Wave 4 (parallel): G + L + M
- Wave 5 (sequential): N → O → P

---

### Phase A: DB schema — migrations for instagram_posts + tiktok_posts

**Estimated time:** 15 minutes

**Files:**
- Create: `backend/database/migrations/2026_05_07_000001_create_instagram_posts_table.php`
- Create: `backend/database/migrations/2026_05_07_000002_create_tiktok_posts_table.php`
- Test: `backend/tests/Feature/Migrations/CrossPostSchemaTest.php`

**Steps:**
1. Write failing test for instagram_posts schema. Expected error: `PDOException: SQLSTATE[42S02] Base table 'instagram_posts' doesn't exist`
2. Run test, confirm it fails for the expected reason
3. Implement migration `2026_05_07_000001_create_instagram_posts_table` per Design § Schema (13 cols + 2 FKs + 2 indexes)
4. Implement migration `2026_05_07_000002_create_tiktok_posts_table` per Design § Schema (14 cols incl. `music_suggestion`, same indexes)
5. Run `php artisan migrate` against test DB, run schema test → confirm pass
6. Commit: `feat(cross-post): add instagram_posts + tiktok_posts schema`

**Verification:**
- [ ] `php artisan migrate:fresh --seed` runs cleanly through both new migrations
- [ ] Both tables have `Schema::hasTable()` returning true
- [ ] FK constraints verified via `SHOW CREATE TABLE instagram_posts` (linkedin_post_id ON DELETE SET NULL, post_id ON DELETE CASCADE)
- [ ] Indexes present: `idx_*_post_status`, `idx_*_post_scheduled`
- [ ] Migration rollback works (`php artisan migrate:rollback --step=2`)

---

### Phase B: Status enums + Eloquent models

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/app/Enums/InstagramPostStatus.php`
- Create: `backend/app/Enums/TiktokPostStatus.php`
- Create: `backend/app/Models/InstagramPost.php`
- Create: `backend/app/Models/TiktokPost.php`
- Test: `backend/tests/Unit/InstagramPostStatusTransitionsTest.php`
- Test: `backend/tests/Unit/TiktokPostStatusTransitionsTest.php`

**Steps:**
1. Write failing test asserting `InstagramPostStatus::PendingGeneration->canTransitionTo(InstagramPostStatus::Generating)` returns true. Expected error: `Error: Class "App\Enums\InstagramPostStatus" not found`
2. Run test, confirm it fails for the expected reason
3. Implement `InstagramPostStatus` enum mirroring `LinkedInPostStatus.php` structure: 7 cases (`PendingGeneration`, `Generating`, `AwaitingReview`, `AwaitingManualPublish`, `PublishedExternally`, `Failed`, `Cancelled`) + `TRANSITIONS` adjacency map per Design § FSM
4. Implement `TiktokPostStatus` enum (identical shape, different case namespace)
5. Implement `InstagramPost` model: `$fillable` (all editable cols), `$casts['hashtags' => 'array', 'pipeline_state_log' => 'array']`, SoftDeletes, HasStatusTransitions trait, `statusEnumClass()` returns `InstagramPostStatus::class`, BelongsTo relations to `Post`, `LinkedInPost`, `User`
6. Implement `TiktokPost` model (same pattern + `music_suggestion` in fillable)
7. Run all tests, confirm pass
8. Commit: `feat(cross-post): add status enums + Eloquent models with FSM wiring`

**Verification:**
- [ ] `php -l` clean on all 4 new files
- [ ] `tinker`: `InstagramPost::factory()->make()->status` returns `InstagramPostStatus::PendingGeneration` (default)
- [ ] `transitionTo()` happy path works: pending → generating → awaiting_review → awaiting_manual_publish → published_externally
- [ ] Illegal transition throws `InvalidStateTransitionException` (e.g., pending → published_externally)
- [ ] `pipeline_state_log` JSON appends on every transition

---

### Phase C: Backend services + queued jobs (parallel-safe with Phase F)

**Estimated time:** 18 minutes

**Files:**
- Create: `backend/app/Services/InstagramGenerationService.php`
- Create: `backend/app/Services/TiktokGenerationService.php`
- Create: `backend/app/Jobs/GenerateInstagramPost.php`
- Create: `backend/app/Jobs/GenerateTiktokPost.php`
- Create: `backend/config/social-cross-post.php`
- Modify: `backend/.env.example` (add `SOCIAL_GEN_*` keys)
- Test: `backend/tests/Unit/InstagramGenerationServiceParseTest.php`
- Test: `backend/tests/Feature/GenerateInstagramPostJobTest.php`

**Steps:**
1. Write failing test for `InstagramGenerationService::parseOrchestratorOutput` happy path with Sonnet preamble + fenced JSON. Expected error: `Error: Class "App\Services\InstagramGenerationService" not found`
2. Run test, confirm it fails for the expected reason
3. Implement `InstagramGenerationService::parseOrchestratorOutput` by lifting [`LinkedInGenerationService::parseOrchestratorOutput`](backend/app/Services/LinkedInGenerationService.php) — balanced-brace scanner, NO fence-strip regex (May 2 fix)
4. Implement `InstagramGenerationService::generate(InstagramPost $draft)`: SSH dispatch via `dispatchPlugin('/instagram-gen', $blogPayload)`, parse stdout, advance FSM via `PipelineGuard::advance`. Reuse `buildMcpFlags()` helper pattern (April 29 fix) + `--append-system-prompt-file=$refsPath`
5. Implement `TiktokGenerationService::generate` (same pattern, different skill name + refs path)
6. Implement `config/social-cross-post.php` with `driver`, `ssh_host`, `ssh_user`, `ssh_key`, `claude_path`, `model`, `refs_instagram`, `refs_tiktok`, `timeout_seconds=300`, `empty_mcp_config=/home/claudesn/empty-mcp.json`
7. Implement `GenerateInstagramPost` queued job: 360s timeout, 2 retries (60s/300s backoff), skip if status not in `[PendingGeneration, Failed, Cancelled]`
8. Implement `GenerateTiktokPost` (same pattern)
9. Add `.env.example` keys: `SOCIAL_GEN_DRIVER=ssh`, `SOCIAL_GEN_SSH_HOST=localhost`, `SOCIAL_GEN_SSH_USER=claudesn`, `SOCIAL_GEN_SSH_KEY=/home/claudesn/.ssh/id_ed25519`, `SOCIAL_GEN_CLAUDE_PATH=claude`, `SOCIAL_GEN_MODEL=sonnet`, `SOCIAL_GEN_TIMEOUT_SECONDS=300`, `SOCIAL_GEN_REFS_INSTAGRAM=/home/claudesn/refs-instagram.md`, `SOCIAL_GEN_REFS_TIKTOK=/home/claudesn/refs-tiktok.md`
10. Write 6 parser tests covering: clean JSON, Sonnet preamble + fenced JSON (regression for May 2 bug), trailing narration, empty stdout, malformed JSON, status=failed envelope
11. Write feature test for `GenerateInstagramPost` job: dispatches with mocked SSH driver, asserts FSM advances PendingGeneration → Generating → AwaitingReview on `validation.passed=true`, → Failed on `passed=false`
12. Run all tests, confirm pass
13. Commit: `feat(cross-post): backend services + queued jobs for IG + TikTok plugin gen`

**Verification:**
- [ ] All 6 parser tests pass (regression coverage for fence-strip bug)
- [ ] FSM advances correctly on happy path
- [ ] FSM routes to Failed on plugin `status=failed`
- [ ] FSM stays at Failed when retried beyond 2 attempts (no infinite retry loop)
- [ ] `buildMcpFlags()` correctly emits `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config`
- [ ] No `dd()` / `dump()` / TODO comments in new code

---

### Phase D: Trigger cron — `social-cross-post:scan-linkedin-done`

**Estimated time:** 10 minutes

**Files:**
- Create: `backend/app/Console/Commands/ScanLinkedInForCrossPost.php`
- Modify: `backend/routes/console.php` (add schedule entry)
- Test: `backend/tests/Feature/ScanLinkedInForCrossPostTest.php`

**Steps:**
1. Write failing test asserting cron creates IG + TikTok pending rows when LinkedIn carousel slides all `image_status=done`. Expected error: `Error: Class "App\Console\Commands\ScanLinkedInForCrossPost" not found`
2. Run test, confirm it fails for the expected reason
3. Implement `ScanLinkedInForCrossPost` artisan command (signature `social-cross-post:scan {--dry-run} {--limit=20}`):
   - Query `LinkedInPost::where('format', 'carousel')->whereNotNull('carousel_slides')` filtered by:
     - All `carousel_slides[]` entries have `image_status='done'` (use whereJsonContains where possible, else collection filter)
     - No live (non-trashed) row in `instagram_posts.where('post_id', $li->post_id)` — same for tiktok_posts
     - Source `ContentIdea.virality_score >= linkedin_virality_min_score` (reuse existing virality gate from `ScanBlogForLinkedInConversion`)
   - For each match: create `instagram_posts` + `tiktok_posts` pending rows + dispatch both jobs
   - Honor `--dry-run` flag (print plan, no DB writes)
   - Honor `--limit` flag
4. Add schedule entry in `routes/console.php`: `Schedule::command('social-cross-post:scan')->everyTwoMinutes()->withoutOverlapping(5);`
5. Write 4 feature tests: happy path (creates 2 rows), idempotent (running twice doesn't double-create), virality gate (skips low-score), all-done invariant (skips when any slide still generating)
6. Run all tests, confirm pass
7. Commit: `feat(cross-post): cron scanner that fans LinkedIn carousels out to IG + TikTok pending rows`

**Verification:**
- [ ] Cron is idempotent (running 5x in a row produces same DB state as 1x)
- [ ] Virality gate respected (drafts with `virality_score < 60` not ingested)
- [ ] Slides-not-ready guard works (carousel with any `image_status != 'done'` is skipped)
- [ ] `--dry-run` produces no DB mutations
- [ ] Schedule entry verified via `php artisan schedule:list`

---

### Phase E: Admin Controllers + Routes (instagram-drafts + tiktok-drafts)

**Estimated time:** 18 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/InstagramDraftController.php`
- Create: `backend/app/Http/Controllers/Api/Admin/TiktokDraftController.php`
- Modify: `backend/routes/api.php` (add 14 endpoints + 2 ZIP routes)
- Test: `backend/tests/Feature/Admin/InstagramDraftControllerTest.php`
- Test: `backend/tests/Feature/Admin/TiktokDraftControllerTest.php`

**Steps:**
1. Write failing test for `GET /admin/instagram-drafts` returning paginated list with status filter. Expected error: `Symfony\Component\Routing\Exception\RouteNotFoundException`
2. Run test, confirm it fails for the expected reason
3. Implement `InstagramDraftController` mirroring [`LinkedInDraftController`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) — 7 actions: `index()` (filter by status/scope/per_page), `show()` (eager-load `linkedinPost.carouselSlides` + `post.translations`), `update()` (caption/hashtags/title/scheduled_at, no FSM transition), `regenerate()` (soft-delete + create new pending row + dispatch job, 409 on duplicate live), `approve()` (awaiting_review → awaiting_manual_publish, dispatch Telegram notif), `cancel()` (any non-terminal → cancelled), `markPublished()` (awaiting_manual_publish → published_externally, capture external_url from request body)
4. Implement `TiktokDraftController` (same shape + `music_suggestion` in update validator)
5. Add 14 routes to `routes/api.php` under `auth:sanctum` middleware group, prefixed `admin/instagram-drafts/` and `admin/tiktok-drafts/`
6. Add 2 ZIP download routes (deferred to Phase F): `GET admin/{platform}-drafts/{id}/download-slides`
7. Write 14 controller tests — at minimum: list with filter, show with eager loads, update happy path, regenerate happy path, regenerate 409 on duplicate, approve happy path, approve invalid state, cancel happy path, mark-published with URL, mark-published without URL → 422
8. Run all tests, confirm pass
9. Commit: `feat(cross-post): admin controllers + 14 endpoints for IG + TikTok drafts`

**Verification:**
- [ ] All 14 routes verified via `php artisan route:list | grep -E 'instagram-drafts|tiktok-drafts'`
- [ ] All endpoints require `auth:sanctum` (returns 401 JSON when bearer missing — verifies May 6 401-JSON fix is present)
- [ ] FSM transitions hit `PipelineGuard::advance` on every state change (audit log populated)
- [ ] Approve action queues Telegram dispatch (assert via `Bus::fake() && Bus::assertDispatched(DispatchTelegramNotification::class)`)
- [ ] Mark-published validates `external_url` is a valid URL (Laravel `url` rule)

---

### Phase F: Plugin scaffolding — `social-short-form-writer` (BLOCKED on background research)

**Estimated time:** 25 minutes

**Files (in NEW separate repo at `D:\Projects\claude-plugin\social-short-form-writer`):**
- Create: `package.json` (TypeScript + Zod + tsx + standard plugin deps)
- Create: `scripts/compile-refs.ts` (mirror `linkedin-post-writer/scripts/compile-refs.ts`)
- Create: `skills/instagram-gen/SKILL.md` + `skills/instagram-gen/schema.ts`
- Create: `skills/tiktok-gen/SKILL.md` + `skills/tiktok-gen/schema.ts`
- Create: `docs/rag/social-base/` + `docs/rag/instagram-playbook/` + `docs/rag/tiktok-playbook/` (8+ markdown files seeded from research output at `Portfolio_v2/docs/research/2026-05-07-ig-tiktok-best-practice/`)
- Create: `tests/SchemaParseTest.spec.ts` (Zod schema validation tests)
- Create: `README.md` + `CHANGELOG.md` + `.gitignore`

**Steps:**
1. Verify research output exists at `D:\Projects\Portfolio_v2\docs\research\2026-05-07-ig-tiktok-best-practice\INDEX.md` — if missing, BLOCK and ask user to wait for background agent. Expected error if research missing: human-readable BLOCK message, NOT silent placeholder
2. Write failing Zod schema test for `InstagramOutputEnvelope` — must enforce `caption.length <= 2200`, `hashtags.length` 3–30, `status: 'complete' | 'failed'`. Expected error: `Cannot find module './schema'`
3. Run test, confirm it fails for the expected reason
4. Initialize npm repo: `npm init -y && npm install zod tsx typescript @types/node vitest --save-dev`
5. Implement `skills/instagram-gen/schema.ts` — discriminated union `CompleteEnvelope | FailedEnvelope` per Design § Plugin output schema
6. Implement `skills/tiktok-gen/schema.ts` (same shape + `music_suggestion: z.string().optional()`)
7. Implement `scripts/compile-refs.ts` — bundles `docs/rag/social-base/*` + `docs/rag/{instagram,tiktok}-playbook/*` into 2 output bundles `refs-instagram.md` + `refs-tiktok.md` (~80KB each)
8. Author `skills/instagram-gen/SKILL.md` — generation prompt with hard rules:
   - English authoring (matches LinkedIn pipeline May 6 EN directive)
   - Caption length 100–2200 chars sweet spot ~600–1500
   - 8–12 hashtags (per research output — confirm exact count from INDEX.md)
   - First-line hook on slide cover
   - NO link in caption (LinkedIn-style "link in comments" via separate first comment is N/A here — IG carousel has no comment-from-author preferred)
   - Use bilingual fallback: if blog has Indonesian original, use English translation
9. Author `skills/tiktok-gen/SKILL.md` (same pattern + 3-second hook discipline + music_suggestion language guide)
10. Seed 8 RAG markdown files in `docs/rag/{instagram,tiktok}-playbook/` from research output (copy-paste + light editing for plugin voice)
11. Run `npm run compile-refs` — confirm 2 output files generated, sizes 50KB–100KB each, gitignored
12. Run Zod schema tests, confirm pass
13. Commit (in plugin repo): `feat: v0.1.0 social-short-form-writer with /instagram-gen + /tiktok-gen skills`
14. Document VPS deploy step in plugin `README.md`: `git pull && npm install && npm run compile-refs && symlink to /home/claudesn/refs-{instagram,tiktok}.md`

**Verification:**
- [ ] Plugin tests pass (`npm test`)
- [ ] `npm run compile-refs` produces both output files non-empty
- [ ] Zod schemas reject all known bad envelopes (caption too long, hashtags empty, missing status field)
- [ ] Plugin RAG content cites research sources (no fabricated stats — verify against research output)
- [ ] No reliance on training memory for 2025-2026 algorithm specs (everything sourced from research)

---

### Phase G: Telegram notification extension (parallel-safe with G–M)

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Services/TelegramNotificationService.php`
- Create: `backend/database/seeders/SocialCrossPostSettingsSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php` (call new seeder)
- Test: `backend/tests/Feature/SocialTelegramNotificationTest.php`

**Steps:**
1. Write failing test asserting `telegram_notify_instagram_ready` setting exists after seeder run. Expected error: `Failed asserting that null matches expected 'true'.`
2. Run test, confirm it fails for the expected reason
3. Implement `SocialCrossPostSettingsSeeder` adding 2 keys to `telegram` group via `firstOrCreate`: `telegram_notify_instagram_ready = 'true'`, `telegram_notify_tiktok_ready = 'true'`
4. Wire seeder into `DatabaseSeeder::run`
5. Add `sendInstagramReady(InstagramPost $draft)` + `sendTiktokReady(TiktokPost $draft)` methods on `TelegramNotificationService`. Each: builds checklist message per Design § Telegram template, dispatches `DispatchTelegramNotification` job. Honors per-platform toggle (no-op if setting=`'false'`)
6. Run tests, confirm pass
7. Commit: `feat(cross-post): Telegram notification types for IG + TikTok manual publish`

**Verification:**
- [ ] Seeder is idempotent (running twice doesn't error or duplicate)
- [ ] Both notification methods are no-op when `telegram_enabled='false'` (master kill switch from existing infra)
- [ ] Both methods are no-op when per-platform toggle is `'false'`
- [ ] Notification body contains: title, scheduled_at, caption preview (first 120 chars), download_url, admin_url, cancel_url

---

### Phase H: Google Drive upload — `CrossPostDriveUploadService`

**Estimated time:** 25 minutes (larger than ZIP variant — adds Google Drive API integration + idempotency tracking)

**Why Drive instead of ZIP** (operator decision May 7):
- Operator publishes from mobile (iOS/Android) — Drive app handles cross-device transfer better than email/ZIP
- Per-blog-post folder = easier maintenance (find old assets by title later)
- One upload per blog post (folder shared between IG + TikTok), not duplicated per platform
- No bandwidth cost on backend each download (Drive serves from CDN)
- Service Account auth = zero operator OAuth flow

**Files:**
- Create: `backend/app/Services/GoogleDriveClient.php` (thin wrapper over `google/apiclient`)
- Create: `backend/app/Services/CrossPostDriveUploadService.php`
- Create: `backend/app/Jobs/UploadCrossPostSlidesToDrive.php` (queued — Drive uploads can be slow)
- Modify: `backend/composer.json` (add `google/apiclient: ^2.15`)
- Create: `backend/database/migrations/2026_05_07_000003_add_drive_folder_fields_to_linkedin_posts.php`
- Create: `backend/config/google-drive.php`
- Modify: `backend/.env.example` (add `GOOGLE_DRIVE_*` keys)
- Test: `backend/tests/Unit/CrossPostDriveUploadServiceTest.php`
- Test: `backend/tests/Feature/UploadCrossPostSlidesToDriveJobTest.php`
- Storage: `/var/www/Portfolio_v2/storage/app/private/google-service-account.json` (mode 600, NOT in git, deployed manually to VPS)

**Steps:**
1. Write failing test for `CrossPostDriveUploadService::uploadSlidesForLinkedInPost($linkedinPostId)` returning a folder URL string. Expected error: `Error: Class "App\Services\CrossPostDriveUploadService" not found`
2. Run test, confirm it fails for the expected reason
3. Run `composer require google/apiclient ^2.15` (note: this pulls ~50MB of transitive deps; acceptable cost — mature library, used at scale)
4. Implement migration `2026_05_07_000003_add_drive_folder_fields_to_linkedin_posts`:
   - Add nullable `gdrive_folder_id VARCHAR(100)` (Google's folder ID)
   - Add nullable `gdrive_folder_url VARCHAR(500)` (shareable link)
   - Add nullable `gdrive_uploaded_at TIMESTAMP`
   - Add nullable `gdrive_upload_error TEXT`
   - These cols on `linkedin_posts` (not on instagram_posts/tiktok_posts) because slides are LinkedIn-owned and reused 1:1
5. Implement `config/google-drive.php`: `service_account_path`, `parent_folder_id` (the "Portfolio Cross-Post" parent folder ID, shared with SA), `app_name`, `enabled` (master toggle, default false)
6. Add `.env.example` keys: `GOOGLE_DRIVE_ENABLED=false`, `GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH=/var/www/Portfolio_v2/storage/app/private/google-service-account.json`, `GOOGLE_DRIVE_PARENT_FOLDER_ID=` (operator fills after one-time GCP setup)
7. Implement `GoogleDriveClient`: thin wrapper exposing `createFolder(string $name, ?string $parentId): Google\Service\Drive\DriveFile`, `uploadFile(string $localPath, string $remoteName, string $parentFolderId): DriveFile`, `findFolderByName(string $name, string $parentId): ?DriveFile` (idempotency lookup), `makePubliclySharable(string $fileOrFolderId): string` returning shareable URL. Uses `Google\Client` with Service Account auth (`useApplicationDefaultCredentials()` after setting `GOOGLE_APPLICATION_CREDENTIALS` env from config)
8. Implement `CrossPostDriveUploadService::uploadSlidesForLinkedInPost`:
   - Resolve `LinkedInPost` + eager-load `post.translations`
   - Idempotency check: if `gdrive_folder_id` already set AND folder still exists in Drive, return existing URL (no re-upload)
   - Build folder name: `"{YYYY-MM-DD}-{slug}"` where slug is `Str::slug($post->translation->title, '-')` truncated to 60 chars (Drive folder name max 1024, but operator-friendly to keep short). Example: `"2026-04-25-vibe-coding-surveillance"`
   - Look up existing folder by name under parent (handles case where DB row was wiped but Drive folder remains): `findFolderByName($folderName, $parentFolderId)`. If found, reuse ID. Else create new.
   - For each slide in `linkedin_posts.carousel_slides[]` where `image_status='done'`:
     - Skip if file already exists in folder by name (idempotent re-run)
     - Download PNG from `storage/app/public/linkedin-carousel/{filename}` (local file system) to temp
     - Build remote name: `{NN}-{role}.png` (e.g. `01-cover.png`, `02-hook.png`, `09-cta.png`)
     - Upload via `GoogleDriveClient::uploadFile`
   - Make folder publicly readable (`anyone with link → reader` permission) → capture shareable URL
   - Persist `gdrive_folder_id`, `gdrive_folder_url`, `gdrive_uploaded_at` on `LinkedInPost`
   - Return folder URL
9. Implement `UploadCrossPostSlidesToDrive` queued job: 360s timeout, 2 retries (60s/300s backoff), idempotent — calls service. On failure, persist `gdrive_upload_error` on LinkedInPost, do NOT block IG/TikTok generation (graceful degrade — operator can manually re-trigger via admin button)
10. Modify `ScanLinkedInForCrossPost` (Phase D): after creating IG + TikTok pending rows, dispatch `UploadCrossPostSlidesToDrive::dispatch($linkedinPostId)` if `gdrive_folder_id IS NULL` AND `config('google-drive.enabled')`. Idempotency: skip when already in flight (check `gdrive_uploaded_at` + `gdrive_upload_error` recency)
11. Add admin endpoint `POST /api/admin/linkedin-drafts/{id}/reupload-to-drive` for manual retry from LinkedIn detail page (single-action controller method on existing `LinkedInDraftController`)
12. Modify Telegram notif templates (Phase G) to read `linkedinPost.gdrive_folder_url` instead of building ZIP download URL. Fall back to "(Drive upload pending — try again in a moment)" if URL missing
13. Write 8 tests:
    - Happy path: creates folder + uploads N PNGs + returns URL
    - Idempotent re-run: second call returns same URL, no new uploads
    - Missing parent folder ID: throws clear error pointing to env var
    - Service account JSON missing: throws clear error
    - Drive disabled (`enabled=false`): service no-ops + returns null + Telegram notif gracefully omits Drive section
    - Folder name collision: existing folder reused (lookup by name under parent)
    - File-level idempotency: re-upload skips PNGs already present in folder
    - Sanitization: title with special chars produces clean folder name
14. Run all tests, confirm pass
15. Commit: `feat(cross-post): Google Drive upload of carousel slides per-blog-post folder`
16. Operator one-time setup (document in `README.md` under Cross-Post section):
    - Create GCP project, enable Drive API
    - Create Service Account, generate JSON key, download
    - SCP key to VPS at `/var/www/Portfolio_v2/storage/app/private/google-service-account.json`, `chmod 600`, `chown www-data:www-data` (HTTP context) — note: queue worker runs as claudesn, so also need read access there
    - Actually use `chmod 640 + chgrp claudesn` so both www-data (owner) and claudesn (group) can read
    - In Drive UI, create top-level folder "Portfolio Cross-Post"
    - Share it with SA email (`Editor` access)
    - Get folder ID from Drive URL → put in `.env` as `GOOGLE_DRIVE_PARENT_FOLDER_ID`
    - Set `GOOGLE_DRIVE_ENABLED=true` + `php artisan config:cache && systemctl restart portfolio-queue.service`

**Verification:**
- [ ] All 8 service tests pass
- [ ] Folder structure on Drive: `Portfolio Cross-Post/{YYYY-MM-DD}-{slug}/01-cover.png ... 09-cta.png`
- [ ] Idempotency proven: running `social-cross-post:scan` 3 times produces same Drive state (no duplicate folders, no duplicate files)
- [ ] Drive folder is publicly readable via shareable link (no Google login required for operator on mobile)
- [ ] Disabled flag respected (Drive integration is optional — IG + TikTok still generate, operator can manually upload PNGs from `storage/app/public/linkedin-carousel/`)
- [ ] Service Account JSON file permissions: 640, owned by www-data, group claudesn (both contexts can read)
- [ ] No Service Account JSON anywhere in git (verify via `git ls-files | grep -i service-account`)
- [ ] Telegram notification carries the folder URL when present, gracefully omits when absent

---

### Phase I: Frontend composables — useInstagramDrafts + useTiktokDrafts

**Estimated time:** 15 minutes

**Files:**
- Create: `frontend/src/composables/useInstagramDrafts.js`
- Create: `frontend/src/composables/useTiktokDrafts.js`
- Test: `frontend/src/composables/useInstagramDrafts.test.mjs` (Node smoke test mirroring [`linkedinHelpers.test.mjs`](frontend/src/views/admin/linkedinHelpers.test.mjs))

**Steps:**
1. Write failing Node smoke test asserting `useInstagramDraftsList()` query key shape `['instagram-drafts', { status, scope, page }]`. Expected error: `Cannot find module 'useInstagramDrafts'`
2. Run test, confirm it fails for the expected reason
3. Implement `useInstagramDrafts.js` mirroring [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) — 30s staleTime, refetchOnMount:'always'. Export composables: `useInstagramDraftsList(filters)`, `useInstagramDraft(id)` (with poll-while-in-progress logic mirroring LinkedIn's polling for FSM in-progress states), `useUpdateInstagramDraft()`, `useRegenerateInstagramDraft()`, `useApproveInstagramDraft()`, `useCancelInstagramDraft()`, `useMarkPublishedInstagram()` (POST mark-published with external_url payload)
4. Implement `useTiktokDrafts.js` (same pattern)
5. Run tests, confirm pass
6. Commit: `feat(cross-post): TanStack Query composables for IG + TikTok drafts`

**Verification:**
- [ ] Query keys are platform-namespaced (no collision with linkedin queries)
- [ ] Mutations correctly invalidate parent list query on success
- [ ] Poll-while-in-progress fires every 5s when status ∈ {pending_generation, generating}, stops on terminal states
- [ ] Vite production build clean (no module resolution errors)

---

### Phase J: Frontend Calendar views per platform

**Estimated time:** 18 minutes

**Files:**
- Create: `frontend/src/views/admin/InstagramPostsCalendar.vue`
- Create: `frontend/src/views/admin/TiktokPostsCalendar.vue`
- Modify: `frontend/src/router/index.js` (add 2 routes)

**Steps:**
1. Write failing test asserting calendar renders 7×6 month grid with cells. Expected error: `Cannot find module 'InstagramPostsCalendar'`
2. Run test, confirm it fails for the expected reason
3. Copy [`LinkedInPostsCalendar.vue`](frontend/src/views/admin/LinkedInPostsCalendar.vue) → `InstagramPostsCalendar.vue`. Replace API endpoint with `/admin/instagram-drafts/calendar` (note: Phase E adds calendar endpoint as part of `index()` action with `?from=&to=&view=calendar` query params — verify in Phase E or add explicit endpoint)
4. Same for TikTok → `TiktokPostsCalendar.vue`
5. Wire `posting_time_rules` heatmap by passing `?platform=instagram` (same composable `usePostingRules('instagram')`) — `posting_time_rules` table already supports this per CLAUDE.md May 6
6. Add routes to `router/index.js`: `/admin/instagram-posts` and `/admin/tiktok-posts`
7. Run smoke test, confirm pass
8. Commit: `feat(cross-post): calendar views per platform with native heatmap`

**Verification:**
- [ ] Both routes resolve, render without errors
- [ ] Heatmap shows distinct color tints per platform (depends on data — operator must run Phase M's research command first; pre-data shows uniform background, expected)
- [ ] Day cells are clickable → opens slide-in side panel with platform-specific drafts
- [ ] Calendar uses TanStack Query 30s staleTime + refetchOnMount:'always' (per existing pattern)

---

### Phase K: Frontend Queue views per platform

**Estimated time:** 15 minutes

**Files:**
- Create: `frontend/src/views/admin/InstagramQueueList.vue`
- Create: `frontend/src/views/admin/TiktokQueueList.vue`
- Create: `frontend/src/views/admin/socialPlatformHelpers.js` (shared helpers — STATUS_META per platform, transitionSummary, format/time utilities)
- Modify: `frontend/src/router/index.js`

**Steps:**
1. Write failing test for `socialPlatformHelpers.STATUS_META.awaiting_manual_publish` returning a status-meta object with `label`, `tone`, `description`. Expected error: `Cannot find module 'socialPlatformHelpers'`
2. Run test, confirm it fails for the expected reason
3. Implement `socialPlatformHelpers.js` — copy structure from [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js), add 2 new STATUS_META maps (INSTAGRAM_STATUS_META, TIKTOK_STATUS_META — but values are identical because FSM is shared, so likely 1 SOCIAL_STATUS_META). Reuse 18 inline SVG icons.
4. Copy [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue) → `InstagramQueueList.vue`. Replace endpoint, drop depth_score column (IG/TikTok don't have depth scoring), add `caption_preview` column instead.
5. Same for TikTok
6. Add routes: `/admin/instagram-queue`, `/admin/tiktok-queue`
7. Run tests, confirm pass
8. Commit: `feat(cross-post): queue list views per platform`

**Verification:**
- [ ] Status filter pills (manual_review, awaiting_manual_publish, in_progress, failed, all) render and filter correctly
- [ ] Inline approve action button works (calls `useApproveInstagramDraft()` mutation)
- [ ] Tab counts update reactively
- [ ] Generating progress % helper works (mirror May 6 `generatingProgress()` from `linkedinHelpers.js` — IG/TikTok baseline ~60s for plugin gen, no slide rendering wait)

---

### Phase L: Frontend Detail views per platform

**Estimated time:** 22 minutes (largest UI phase — most operator interaction surface)

**Files:**
- Create: `frontend/src/views/admin/InstagramDraftDetail.vue`
- Create: `frontend/src/views/admin/TiktokDraftDetail.vue`
- Modify: `frontend/src/router/index.js`

**Steps:**
1. Write failing test for detail view rendering hero panel + caption editor + hashtag chip editor + mark-published form. Expected error: `Cannot find module 'InstagramDraftDetail'`
2. Run test, confirm it fails for the expected reason
3. Copy [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) → `InstagramDraftDetail.vue`. Modifications:
   - Hero panel: status meta from socialPlatformHelpers (no Depth Score chip)
   - Slide preview strip: read `draft.linkedin_post.carousel_slides` (eager-loaded by show endpoint), render 1st 5 thumbnails
   - Title editor: single-line input (IG: no native title, treat as "first-line hook" — UI label says "Hook line")
   - Caption editor: char counter, max 2200, soft-warning over 1500 (best practice from research)
   - Hashtag chip editor: **HARD CAP at 5 chips for IG** (per Dec 2025 platform enforcement — see research). Disable add-button at 5, show inline message "Instagram caps at 5 hashtags since Dec 2025". Autosuggest from `post.contentIdea.meta_keywords`
   - Music suggestion field: ABSENT (IG-specific — not applicable)
   - LinkedIn-style mockup: replace with IG-style preview (square card + caption below + hashtags + slide carousel mockup)
   - Approve button: `useApproveInstagramDraft()` mutation
   - Download slides ZIP button: `<a :href="downloadUrl">` (browser handles streaming response)
   - Mark-as-published form: textbox for external_url + button → `useMarkPublishedInstagram()` mutation
4. Same for TikTok → `TiktokDraftDetail.vue`. Modifications vs IG:
   - Title editor: hard cap 100 chars (TikTok native title field limit)
   - Music suggestion field PRESENT: read-only text hint ("Try upbeat tech beats — search 'tech innovation' or 'startup vibe'")
   - TikTok-style mockup: vertical card + caption + hashtags + Photo Mode swipe indicator
5. Add routes: `/admin/instagram-drafts/:id`, `/admin/tiktok-drafts/:id`
6. Run smoke test, confirm pass
7. Commit: `feat(cross-post): detail views per platform with editors + mockup + mark-published flow`

**Verification:**
- [ ] Hero panel reflects current FSM status accurately (no stale error display per May 6 LinkedIn fix — reuse `pipeline_state_log` 24h freshness gate)
- [ ] Char counters work (IG 2200, TikTok 2200, TikTok title 100)
- [ ] Hashtag chip editor: add chip, remove chip, paste comma-separated list parses correctly
- [ ] Approve button gated when status ≠ awaiting_review (disabled with tooltip)
- [ ] Mark-published form gated when status ≠ awaiting_manual_publish
- [ ] external_url validation (must be `https://` and contain platform domain — `instagram.com` for IG, `tiktok.com` for TikTok) — soft warning, not hard block
- [ ] Vite production build clean

---

### Phase M: Sidebar nav update + posting time research extension

**Estimated time:** 10 minutes

**Files:**
- Modify: `frontend/src/layouts/AdminLayout.vue` (add 2 nav sections)
- Modify: `backend/routes/console.php` (add 2 schedule entries for IG + TikTok posting-rules:research)

**Steps:**
1. Write failing test asserting AdminLayout renders "Instagram" and "TikTok" nav sections. Expected error: assertion fails — sections not yet present
2. Run test, confirm it fails for the expected reason
3. Add 2 nav sections in `AdminLayout.vue` under existing LinkedIn section:
   - "Instagram" → links: Posts (calendar), Queue
   - "TikTok" → links: Posts (calendar), Queue
4. Add 2 schedule entries to `routes/console.php`:
   ```php
   Schedule::command('posting-rules:research --platform=instagram')
       ->cron('0 4 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
   Schedule::command('posting-rules:research --platform=tiktok')
       ->cron('0 5 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
   ```
5. Run tests, confirm pass
6. Commit: `feat(cross-post): sidebar nav + quarterly posting-time research for IG + TikTok`

**Verification:**
- [ ] Sidebar renders 3 social sections in order (LinkedIn, Instagram, TikTok)
- [ ] Schedule entries verified via `php artisan schedule:list | grep posting-rules`
- [ ] Schedule offsets stagger from LinkedIn (3:00) → Instagram (4:00) → TikTok (5:00) WIB to avoid concurrent SSH bridge load on quarterly cron night

---

### Phase N: Purge cron commands

**Estimated time:** 8 minutes

**Files:**
- Create: `backend/app/Console/Commands/PurgeLowViralityInstagramDrafts.php`
- Create: `backend/app/Console/Commands/PurgeLowViralityTiktokDrafts.php`
- Modify: `backend/routes/console.php` (add 2 schedule entries)
- Test: `backend/tests/Feature/PurgeLowViralityInstagramDraftsTest.php`

**Steps:**
1. Write failing test asserting purge command soft-deletes drafts with `virality_score < 50` AND status not in terminal set. Expected error: `Error: Class "App\Console\Commands\PurgeLowViralityInstagramDrafts" not found`
2. Run test, confirm it fails for the expected reason
3. Implement `PurgeLowViralityInstagramDrafts` mirroring [`PurgeLowViralityLinkedInDrafts`](backend/app/Console/Commands/PurgeLowViralityLinkedInDrafts.php). Same safety rails:
   - Only touches non-terminal states (skips published_externally, cancelled)
   - Idempotent
   - Requires linked ContentIdea (skip orphans)
   - CLI flags: `--threshold=N`, `--dry-run`
4. Same for TikTok
5. Add schedule entries: `social-cross-post:purge-instagram-drafts` daily 04:30 WIB, `social-cross-post:purge-tiktok-drafts` daily 05:00 WIB
6. Write 4 tests per command: happy path, dry-run, terminal state protection, orphan skip
7. Run all tests, confirm pass
8. Commit: `feat(cross-post): daily purge of low-virality IG + TikTok drafts`

**Verification:**
- [ ] Idempotent (running 2x in a row produces same DB state)
- [ ] Terminal states protected (published_externally + cancelled never touched)
- [ ] Schedule entries verified via `php artisan schedule:list`

---

### Phase O: End-to-end smoke test

**Estimated time:** 30 minutes (manual + automated)

**Files:**
- Create: `backend/tests/Feature/CrossPostE2ETest.php`
- Document: VPS smoke runbook in plan file

**Steps:**
1. Write failing E2E test that simulates: (a) LinkedIn carousel completes → (b) `social-cross-post:scan` runs → (c) IG + TikTok pending rows exist → (d) jobs execute (with mocked SSH) → (e) status reaches AwaitingReview. Expected error: at least one assertion fails because pipeline isn't wired end-to-end
2. Run test, confirm it fails for the expected reason
3. Wire each step until pipeline passes (this is integration verification, not new feature work — fix any wiring gaps discovered)
4. Run test, confirm pass
5. Manual VPS smoke (after deploy):
   - Pick an existing blog post with completed LinkedIn carousel
   - Run `php artisan social-cross-post:scan --dry-run` — verify it would create rows
   - Run without `--dry-run` — verify rows created + jobs dispatched
   - Watch queue: `journalctl -u portfolio-queue.service -f` — verify no SSH errors
   - Wait ~90s for plugin gen
   - Open `/admin/instagram-queue` — verify IG draft visible
   - Open `/admin/tiktok-queue` — verify TikTok draft visible
   - Click into IG detail, click Approve → verify Telegram notif arrives
   - Click "Mark as published" with fake URL — verify status moves to published_externally
6. Commit: `test(cross-post): E2E test + manual smoke runbook documented`

**Verification:**
- [ ] E2E test passes
- [ ] Manual smoke completes through both platforms
- [ ] Telegram notification arrives within 5s of approve
- [ ] No SSH key errors in queue worker logs (April 29 SSH key fix is in place)
- [ ] No MCP server leak (verify via `ps -u claudesn -o pid,etime,cmd | grep node | wc -l` stays stable across 5 sequential runs)

---

### Phase P: Documentation — CLAUDE.md update

**Estimated time:** 12 minutes

**Files:**
- Modify: `CLAUDE.md` (add new section "Cross-Post to Instagram + TikTok (May 7, 2026)" after LinkedIn sections)
- Modify: this plan file (mark phases complete in checkboxes)

**Steps:**
1. Write failing assertion (manual): CLAUDE.md "Last Updated" line not yet bumped to 2026-05-07. Expected error: not yet edited
2. Run grep, confirm line still says May 7 from earlier today (router fix entry) — needs new entry appended
3. Append new section to CLAUDE.md mirroring depth of "LinkedIn Carousel Image Generation (April 27, 2026)" section. Cover:
   - Schema (instagram_posts + tiktok_posts)
   - FSM (5 states)
   - Plugin (`social-short-form-writer` v0.1, location, RAG sources)
   - Backend services + jobs + cron
   - Admin endpoints (14 + 2 ZIP + automation: cross-post-pull-pending if needed)
   - Frontend views + composables + sidebar nav
   - Telegram notif extension
   - Posting time integration
   - Manual operator workflow
   - Phase 2 deferred (TikTok Inbox API + IG scheduled_publish_time)
4. Update "Last Updated" line at bottom of CLAUDE.md with tonight's commit summary
5. Mark all phase checkboxes [x] in this plan file
6. Run `git diff CLAUDE.md docs/plans/2026-05-07-cross-post-instagram-tiktok.md` — review for completeness
7. Commit: `docs: add cross-post IG + TikTok section to CLAUDE.md`

**Verification:**
- [ ] CLAUDE.md grep finds "Cross-Post to Instagram + TikTok" section
- [ ] All schema, routes, env vars from Data Integration Map are documented
- [ ] "Last Updated" line bumped
- [ ] All plan phase checkboxes marked complete

---

### Risk register

| Risk | Likelihood | Mitigation |
|---|---|---|
| Background research agent fails / produces low-quality RAG | Medium | Phase F BLOCKS on research INDEX.md existence + manual review of cited sources before plugin authoring. If research unusable, run inline WebSearch fallback (~15 min add) |
| Plugin Sonnet output truncation (similar to May 2 carousel-gen issue) | Low | Default `target_caption_length` ~1200 chars (well under output token cap). Tighten if observed in production logs |
| Operator forgets to run `posting-rules:research --platform=instagram` post-deploy | Medium | Calendar UI shows "AI rules: not yet researched · run command on VPS" pill when `posting_time_rules` table has 0 rows for platform — proactive nag |
| MCP leak resurfaces under new SSH bridge service | Low | Reuse existing `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config` flag pattern from April 29 fix |
| ZIP download too large for mobile data plan | Low | Slides are ~150-300KB each PNG → 9 slides ≈ 2-3 MB ZIP. Reasonable on 4G. Document in Telegram notif checklist |
| External URL validation false-positives (operator pastes shortened bit.ly link) | Low | Soft warning only, not hard block — let operator override |
| Concurrent SSH bridge load (LinkedIn + IG + TikTok all firing for same post) | Medium | LinkedIn pipeline fires first (existing scan), THEN cross-post scanner fires 2 min later — natural staggering. Plus queue worker is single-process serial executor, so no actual concurrency at SSH layer |
| IG hashtag cap changes again post-launch | Medium | Cap is encoded in 1 place (Zod schema in `schema.ts`) + 1 place in frontend chip editor. Single grep target. Plugin schema test catches drift — research file `02-instagram-hashtag-strategy.md` is the source of truth, refresh when platform updates |
| Google Drive API quota (10k uploads/day per project) | Low | At ~50 blog posts/month upper bound, ~9 slides each = 450 uploads/month — well under quota. If volume grows 10x, switch to resumable upload sessions or shard across multiple SAs |
| Service Account JSON key leak | Critical-if-occurs | Mode 640, group claudesn, NOT in git, deployed via SCP only. Rotate quarterly via GCP UI. Limit SA scope to Drive only (no other GCP APIs enabled on the SA) |
| Operator forgets to whitelist parent folder for SA | Medium | Drive client throws clear permission error → captured in `gdrive_upload_error` column → admin UI surfaces in red banner pointing to setup guide in README |
| Drive folder accidentally deleted by operator (orphans the link in Telegram) | Low | `reupload-to-drive` admin endpoint re-creates folder + re-uploads. `findFolderByName` lookup handles case where DB has stale folder ID after delete — service falls back to recreate |

### Execution Handoff

Plan saved to [docs/plans/2026-05-07-cross-post-instagram-tiktok.md](docs/plans/2026-05-07-cross-post-instagram-tiktok.md).

**Three options:**

**Option 1: Execute in this session**
> Use `/gaspol-execute` with this plan path. Sequential mode, per-phase checkpoints, TDD hard gate enforced.

**Option 2: Parallel execution**
> Use `/gaspol-parallel` mode `plan-phases`. Phase A → B → C run sequential first, THEN dispatch waves 2–4 in parallel (Wave 2: D + F + H, Wave 3: E + I + J + K, Wave 4: G + L + M, Wave 5: N → O → P).

**Option 3: Separate session**
> Save plan, return tomorrow with fresh context. The plan file has everything needed (Architecture Context, Data Integration Map, per-phase TDD steps, verification criteria, risk register).

---

*Plan created 2026-05-07. Background research agent dispatched at same time — completion notification will surface independently. Phase F BLOCKS on research output presence at `docs/research/2026-05-07-ig-tiktok-best-practice/INDEX.md`.*
