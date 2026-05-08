# Cross-post LinkedIn Carousel → Facebook + Instagram + TikTok via Publer

**Date:** 2026-05-08
**Status:** Design locked — awaiting `/gaspol-plan` to expand into Implementation Plan
**Owner:** Ali Sadikin
**Supersedes:** [docs/plans/2026-05-07-cross-post-instagram-tiktok.md](2026-05-07-cross-post-instagram-tiktok.md) (manual-workflow + Drive-upload approach — REJECTED in favor of Publer-as-transport)

## Design

### Pivot rationale

The May 7 design used a manual-workflow path: backend authors caption per platform → uploads slide PNGs to Google Drive → Telegram nudges operator → operator publishes manually in IG/TikTok mobile apps. Operator opted to delegate publishing to **Publer** ([https://app.publer.com](https://app.publer.com)) — third-party SaaS scheduler that's already an approved Meta + TikTok partner, has a clean REST API (`https://app.publer.com/api/v1`), and connects to all 3 target platforms (FB Page + IG + TikTok).

Publer becomes the publishing transport. The custom backend pipeline + admin UI + caption plugin still exist (Phase A/B already shipped, Phase C-P largely unchanged) — but the publish step is now `POST /posts/schedule` to Publer API instead of operator manual workflow. This keeps approval review in our admin (consistent with existing LinkedIn workflow) while skipping the OAuth + App Review burden for IG + TikTok.

### Scope (v1 — locked)

**Platform routing matrix** — LinkedIn output formats route to specific cross-post platforms (operator-locked May 8):

| LinkedIn output format | → LinkedIn (existing) | → Facebook Page | → Instagram | → TikTok |
|---|---|---|---|---|
| **text** | ✅ existing pipeline | ✅ NEW (long caption + link unfurl) | ❌ skip | ❌ skip |
| **carousel** | ✅ existing pipeline | ✅ NEW (multi-photo album) | ✅ NEW (static carousel) | ✅ NEW (Photo Mode + auto music) |

Rationale:
- FB Page is the most flexible target — accepts BOTH text format (long caption + link unfurl preview, FB algorithm rewards in-feed unfurls) AND carousel format (FB multi-photo album works well for B2B brand awareness)
- IG + TikTok are visual-only — text-format LinkedIn posts have no slides, would degrade to plain text + minimal engagement, so we skip those
- LinkedIn pipeline produces both formats based on `LinkedInPost.format` enum (`text` vs `carousel`)
- `facebook_posts` schema needs a `format` discriminator column to handle both paths

✅ **In scope (3 platforms with format-aware routing)**
- **Facebook Page** ("Alisadikinma.com") — auto-publish via Publer for BOTH formats:
  - text format: caption (cloned/adapted from LinkedIn text content) + blog URL (FB unfurls link preview)
  - carousel format: caption + slide PNGs as multi-photo album
- **Instagram** ("Ali Sadikin Ma") — auto-publish carousel-format only via Publer (static feed carousel, no music — IG carousel music not exposed via API)
- **TikTok** ("Ali Sadikin Al") — auto-publish carousel-format only via Publer Photo Mode **with auto-add music** (`networks.tiktok.details.auto_add_music: true` — Publer/TikTok server-side music selection, no operator-picked sound ID)
- Reuse existing carousel slide PNGs from LinkedIn `/carousel-gen` pipeline (zero new image work) — when format=carousel
- Reuse existing LinkedIn text content for FB text-format posts (clone + adapt)
- Per-platform caption authoring — see "Caption authoring strategy" below (different per format + platform)
- Per-platform admin UI (queue + calendar + detail) — mirror LinkedIn template
- Per-platform manual approval gate (operator approves each separately, NOT bundled with LinkedIn approval)
- Telegram notification on publish success/failure
- Posting time research integration (`posting_time_rules` table + `ResearchPostingTimeRules` artisan, both already multi-platform)
- Encrypted Publer API key in `settings(group=publer)` — operator-rotatable
- Account auto-discovery via Publer `GET /accounts` → dropdown picker in admin settings

❌ **Out of scope (explicit YAGNI)**
- Direct OAuth to Meta/TikTok (Publer handles partnership burden)
- Music API integration (Publer doesn't expose music; operator-acceptable per prior brainstorm — reach signal still good without trending sound)
- Video generation pipeline (static carousel only)
- Manual ZIP/Drive workflow (Publer ingests slide PNGs via URL — simpler)
- Per-platform image variants (same PNGs across all 3)
- Bundled approval (LinkedIn approve = FB+IG+TikTok approve) — explicitly rejected
- Auto-publish cascade without review — operator wants per-platform gate

### Caption authoring strategy (LOCKED)

Format-aware routing per platform:

- **Instagram (carousel format only)**: dedicated `/instagram-gen` skill in new plugin, RAG-backed by 10 research files at `docs/research/2026-05-07-ig-tiktok-best-practice/`. Hard rules: 5-hashtag cap (Dec 2025 IG platform enforcement), 100–2200 char caption, ≤125-char first-line hook.

- **TikTok (carousel format only)**: dedicated `/tiktok-gen` skill, same RAG. Hard rules: ≤100-char title, first 150 chars critical (TikTok caption-as-search-index 2025-2026), 5-8 hashtags, link in caption acceptable. **Music handled by Publer** (`auto_add_music=true`) — no operator music selection needed, no `music_suggestion` field stored on row.

- **Facebook (text + carousel formats)**:
  - **text format**: clone `LinkedInPost.content` directly + append blog URL (FB unfurls preview automatically). No new authoring needed — LinkedIn text content is already polished (~1100-1300 chars, English, Depth Score gated ≥80). Operator can edit inline in admin if too long for FB's lower-engagement window.
  - **carousel format**: REUSES `/instagram-gen` output (same as IG carousel). `FacebookGenerationService` invokes `/instagram-gen` for carousel-format posts and maps to `facebook_posts` schema. Acceptable because (a) FB Page audience overlaps IG audience for B2B tech, (b) FB permits more chars than IG so IG output fits in FB cap, (c) FB hashtag culture is already anemic so IG's 5-cap works for FB, (d) saves ~1 day plugin work.
  - **Decision flow inside `FacebookGenerationService::generate(FacebookPost $draft)`**:
    ```
    if $draft->format === 'text':
        return adaptLinkedInTextForFb($draft->linkedinPost->content, $draft->linkedinPost->post)
    else (carousel):
        return invokeInstagramGenPlugin($draft->linkedinPost)  # reuse /instagram-gen output
    ```

### Approval gate (LOCKED)

**Our admin is the gate**. Publer is just the transport. Operator reviews caption + hashtags + scheduled time in `/admin/{facebook|instagram|tiktok}-queue`, edits if needed, clicks Approve. Backend then POSTs to Publer with `state: "scheduled"` and explicit `scheduled_at`. Publer auto-publishes at scheduled time.

This keeps consistency with existing LinkedIn workflow and preserves our investment in the admin UI design from Phase L. Operator has one review surface (our admin), not multiple (Publer UI + ours).

### Scheduling strategy (LOCKED)

Backend derives `scheduled_at` from `posting_time_rules` table at caption-generation time:
1. Query top-3 optimal slots in next 7 days for `(platform, audience='b2b_tech')`
2. Filter to slots > 2 hours from now
3. Pick first → ISO 8601 string → persist to row
4. Operator can override via datetime picker in admin detail page

Posting time research extends to all 3 platforms post-deploy:
```bash
php artisan posting-rules:research --platform=facebook
php artisan posting-rules:research --platform=instagram
php artisan posting-rules:research --platform=tiktok
```

Quarterly cron extends accordingly in `routes/console.php`.

### Account discovery (LOCKED)

One-time setup flow:
1. Operator pastes Publer API key in admin settings (`/admin/about` "Publer Integration" card)
2. Backend calls `GET /api/v1/accounts` → returns list of all connected accounts
3. Admin UI shows 3 dropdowns (one per platform): "Default Facebook account", "Default Instagram account", "Default TikTok account"
4. Operator picks each (likely just one option per platform — auto-select if list length === 1)
5. Account IDs persist to `settings(group=publer)`
6. "Refresh accounts" button re-fetches list (for when operator adds accounts in Publer later)

### Cancel behavior (LOCKED)

When operator cancels a draft in our admin (FSM transition to `cancelled`):
- Backend calls `DELETE /api/v1/posts/{publer_post_id}` to remove from Publer's queue
- Idempotent — 404 from Publer (post already published or never created) is treated as success
- Cascade ensures single source of truth (our admin)
- FSM rule: `publishing → cancelled` allowed (DELETE Publer post in same flow)
- After `published`: cancel is forbidden (FSM blocks; would need to delete from FB/IG/TikTok directly which Publer may not expose)

### Architecture overview

```
Blog post hits "published" state
   │
   ├─→ LinkedIn pipeline (existing, untouched)
   │      └─ /linkedin-gen → /carousel-gen → LinkedInCarouselImageService
   │      └─ linkedin_posts.carousel_slides[] populated with rendered PNG URLs
   │      └─ Manual review → Approve → publish (auto via OAuth — existing)
   │
   └─→ AFTER LinkedIn pipeline completes (text format ready OR carousel slides rendered)
          ├─→ ScanLinkedInForCrossPost cron (every 2 min) — FORMAT-AWARE ROUTING:
          │     ├─→ For each LinkedIn post in `published`/`awaiting_publish` state:
          │     │     IF format='text' THEN:
          │     │       • Create facebook_posts row (format='text')
          │     │       • SKIP instagram_posts + tiktok_posts (no slides)
          │     │     IF format='carousel' AND all slides image_status='done' THEN:
          │     │       • Create facebook_posts row (format='carousel')
          │     │       • Create instagram_posts row
          │     │       • Create tiktok_posts row
          │     │
          │     └─→ Common gates per row:
          │         • virality_score >= linkedin_virality_min_score (reuse gate)
          │         • no live row already in target platform table
          │
          ├─→ For TEXT-format facebook_posts:
          │     └─ GenerateFacebookPost queued job
          │         └─ Reads $linkedin_post->content directly (no plugin call)
          │         └─ Adapts caption (lightweight Sonnet trim if >2000 chars OR keep as-is)
          │         └─ Sets link_url = blog URL → Publer unfurls preview at publish time
          │         └─ FSM → AwaitingReview
          │
          ├─→ For CAROUSEL-format facebook_posts:
          │     └─ GenerateFacebookPost queued job
          │         └─ SSH /instagram-gen plugin (REUSED — same output)
          │         └─ Maps to facebook_posts schema with format='carousel'
          │         └─ FSM → AwaitingReview
          │
          ├─→ For instagram_posts (always carousel):
          │     └─ GenerateInstagramPost queued job
          │         └─ SSH /instagram-gen plugin (Sonnet, ~30-60s)
          │         └─ Returns { title, caption, hashtags[], suggested_time, validation }
          │         └─ Persists to instagram_posts row, FSM → AwaitingReview
          │
          └─→ For tiktok_posts (always carousel):
                └─ GenerateTiktokPost queued job
                    └─ SSH /tiktok-gen plugin (Sonnet, ~30-60s)
                    └─ Returns { title, caption, hashtags[], suggested_time, validation }
                    └─ NO music_suggestion (Publer auto_add_music handles it)
                    └─ Persists to tiktok_posts row, FSM → AwaitingReview

Operator opens /admin/{facebook|instagram|tiktok}-queue
   │
   ├─→ Reviews draft (caption + hashtags + slide preview + suggested time)
   ├─→ Edits inline if needed (chip editor, char counter, datetime picker)
   ├─→ Clicks Approve
   │     └─ Backend dispatches PublishViaPubler queued job
   │
   └─→ PublishViaPubler job:
          1. PublerClient::ensureMediaIds(slide_urls):
             - For each PNG URL: POST /media/from-url → poll job_status → media_id
             - (Cached on linkedin_posts.publer_media_ids JSON to avoid re-upload across platforms)
          2. PublerClient::createPost({
               state: 'scheduled',
               networks: {
                 facebook|instagram|tiktok: {
                   type: 'text' OR 'carousel' (per row format),
                   text: caption,
                   media: media_ids[] (carousel only — skipped for FB text format),
                   link: link_url (FB text format only — Publer auto-unfurls),
                   details: { auto_add_music: true } (TikTok carousel only)
                 }
               },
               accounts: [{ id: $publer_account_id, scheduled_at }]
             })
          3. Persist publer_post_id + publer_job_id on row
          4. FSM: AwaitingReview → Publishing

PollPublerJobs cron (every 1 min)
   ├─→ Scans rows in Publishing state
   ├─→ GET /job_status/{publer_job_id}
   ├─→ status='complete' → fetch publish details → external_url + published_at populated
   │     └─ FSM: Publishing → Published
   │     └─ Telegram notif "Published to {platform}: {url}"
   │
   └─→ status='failed' → FSM: Publishing → Failed (last_error from Publer)
         └─ Telegram notif "Publish failed: {error}"
```

### FSM (5 functional states + 2 terminal — final v1)

```
pending_generation → generating → awaiting_review → publishing → published
                            ↘ failed              ↘ failed     ↘ failed
                                                   ↘ cancelled (DELETE Publer post)
                                                   
failed → generating | cancelled (regenerate path)
cancelled → generating (regenerate)
published: terminal
```

Adjacency map:
```
pending_generation: → generating, cancelled
generating: → generating (retry), awaiting_review, failed, cancelled
awaiting_review: → publishing, generating (regen), cancelled
publishing: → published, failed, cancelled (DELETE Publer cascade)
failed: → generating, cancelled
cancelled: → generating
published: [terminal]
```

**Difference from May 7 v1 (already shipped Phase B):**
- Renamed `awaiting_manual_publish` → `publishing` (Publer in-flight, NOT operator manual)
- Renamed `published_externally` → `published` (Publer confirmed, semantic = full publish)

### Schema (3 tables — Phase A revisited)

**Already shipped Phase A (May 7):** `instagram_posts` + `tiktok_posts` (16 cols + 17 cols respectively).

**Schema delta needed for Publer pivot:**

1. **Phase A2 — additive migration on existing 2 tables** (rename ENUM values + add Publer columns):
   ```sql
   ALTER TABLE instagram_posts
     MODIFY COLUMN status ENUM(
       'pending_generation', 'generating', 'awaiting_review',
       'publishing', 'published', 'failed', 'cancelled'
     ) NOT NULL DEFAULT 'pending_generation',
     ADD COLUMN publer_post_id VARCHAR(100) NULL AFTER external_url,
     ADD COLUMN publer_job_id VARCHAR(100) NULL,
     ADD COLUMN publer_status VARCHAR(50) NULL COMMENT 'last polled: working|complete|failed',
     ADD COLUMN publer_account_id VARCHAR(100) NULL,
     ADD INDEX idx_instagram_publer_polling (status, publer_job_id);
   -- mirror on tiktok_posts
   ```

2. **Phase A3 — new migration for `facebook_posts`** (mirrors instagram_posts schema + adds `format` discriminator since FB receives BOTH text and carousel):
   ```sql
   CREATE TABLE facebook_posts (
     -- All 13 base cols from instagram_posts + 4 publer_* cols + standard timestamps
     -- PLUS: format ENUM('text', 'carousel') NOT NULL — discriminator (mirrors LinkedInPost.format)
     -- title VARCHAR(150) — FB headline preview cap
     -- link_url VARCHAR(500) NULL — populated for text format (FB unfurls), NULL for carousel
     -- linkedin_post_id FK still required (text format reads linkedin_post.content; carousel format reads linkedin_post.carousel_slides[])
   );
   -- Index on format helps queries like "list all FB carousel drafts"
   ```

3. **Phase A4 — additive migration on `tiktok_posts`** to drop `music_suggestion` (no longer needed — Publer handles music server-side via `auto_add_music: true`):
   ```sql
   ALTER TABLE tiktok_posts DROP COLUMN music_suggestion;
   ```
   Phase B already shipped this column (May 7); now we remove it. No production data exists yet (table is brand new), so no risk.

### Plugin: `social-short-form-writer` (mostly unchanged from prior plan)

```
docs/rag/
  social-base/                     ← shared (mobile-first hook, attention curve)
  instagram-playbook/              ← IG-specific RAG (5-hashtag cap, etc.) — ALSO consumed by FB
  tiktok-playbook/                 ← TikTok-specific RAG
skills/
  instagram-gen/                   ← /instagram-gen — output reused by FB
  tiktok-gen/                      ← /tiktok-gen — TikTok-specific
scripts/
  compile-refs.ts                  ← bundles 3 RAG dirs into 2 files: refs-instagram.md + refs-tiktok.md
                                     (FB consumes refs-instagram.md via the shared skill)
```

**RAG content already exists** at `docs/research/2026-05-07-ig-tiktok-best-practice/` (10 markdown files + INDEX) from prior research agent. Plugin authoring becomes mostly a copy-paste-and-edit-for-plugin-voice job.

### Backend services (Publer-aware)

```
app/Enums/
  FacebookPostStatus.php             ← 7 cases identical to InstagramPostStatus (post-rename)
  InstagramPostStatus.php            ← Phase B2: rename 2 cases (awaiting_manual_publish→publishing, published_externally→published)
  TiktokPostStatus.php               ← Phase B2: same rename
app/Models/
  FacebookPost.php                   ← mirror InstagramPost
  InstagramPost.php                  ← Phase B already shipped, status enum reference still valid
  TiktokPost.php                     ← Phase B already shipped
app/Services/
  PublerClient.php                   ← thin REST wrapper: bearer auth, JSON, rate-limit awareness
                                       Methods: me(), listAccounts(), uploadMediaFromUrl(url),
                                                pollMediaJob(jobId), createPost(payload), pollJob(jobId),
                                                deletePost(postId)
  PublerSyncService.php              ← high-level orchestration:
                                       publishFacebook(FacebookPost), publishInstagram(InstagramPost),
                                       publishTiktok(TiktokPost), cancelDraft($model)
                                       buildPostPayload($model, $platform)
                                       ensureMediaIdsCached($linkedinPost) — caches Publer media_ids on
                                                                            linkedin_posts.publer_media_ids
                                                                            so 1 LinkedIn carousel = 1 upload,
                                                                            shared across 3 platforms
  InstagramGenerationService.php     ← caption authoring (SSH → /instagram-gen plugin)
  TiktokGenerationService.php        ← caption authoring (SSH → /tiktok-gen plugin)
  FacebookGenerationService.php     ← caption authoring (SSH → /instagram-gen plugin REUSED + map output)
app/Jobs/
  GenerateInstagramPost.php          ← queued, dispatches SSH plugin call
  GenerateTiktokPost.php             ← same
  GenerateFacebookPost.php           ← same (calls /instagram-gen, maps to facebook_posts)
  PublishViaPubler.php               ← polymorphic queued job:
                                       handle($modelClass, $modelId) → PublerSyncService::publish*
                                       Idempotent: skip if model already in publishing/published
app/Console/Commands/
  ScanLinkedInForCrossPost.php       ← every 2 min — creates 3 pending rows (FB + IG + TT) per LinkedIn post
                                       Dispatches 3 generation jobs in parallel
  PollPublerJobs.php                 ← every 1 min — scans publishing rows, polls Publer, advances FSM
  PurgeLowVirality{Facebook,Instagram,Tiktok}Drafts.php
                                     ← daily, mirrors LinkedIn purge cron
```

**Key design decision: cache Publer media uploads at LinkedIn-post level**

When LinkedIn carousel completes with 9 slides, the 3 platform jobs each need the same 9 PNGs available in Publer. Without caching, we'd upload 9 PNGs × 3 platforms = 27 Publer uploads per blog post. Wasteful.

Better: cache `publer_media_ids JSON` on the `linkedin_posts` row after first upload. Subsequent platforms read from cache. Total: 9 uploads per blog post, regardless of platform count. ~67% reduction in Publer media upload calls.

**New column on `linkedin_posts`** (additive migration, separate from cross-post tables):
```sql
ALTER TABLE linkedin_posts
  ADD COLUMN publer_media_ids JSON NULL COMMENT 'Cached Publer media IDs from /media/from-url, shared across cross-post platforms';
```

### Settings group `publer` (new — operator-editable via admin)

```
publer_api_key                       ← encrypted via Crypt::encryptString (mail_password precedent)
                                       Masked as ***SET*** in API responses
publer_enabled                       ← master toggle, default 'false'
publer_facebook_account_id           ← operator picks from /accounts dropdown
publer_instagram_account_id          ← same
publer_tiktok_account_id             ← same
publer_last_account_sync_at          ← timestamp of last GET /accounts call
```

**Admin UI** ([AboutSettings.vue](frontend/src/views/admin/AboutSettings.vue)) gains "Publer Integration" card between LinkedIn and Newsletter cards:
- API key input (preserves on empty submit, ***SET*** placeholder when configured)
- Enable toggle
- 3 account dropdowns (lazy-loaded from `GET /admin/settings/publer/sync-accounts`)
- Test Connection button (pings `GET /api/v1/users` via PublerClient::me)
- Refresh Accounts button (re-fetches /accounts list)

API endpoints:
```
GET    /api/admin/settings/publer                  (auth:sanctum)
PUT    /api/admin/settings/publer                  (auth:sanctum)
POST   /api/admin/settings/publer/test             (auth:sanctum, sync ping)
POST   /api/admin/settings/publer/sync-accounts    (auth:sanctum, returns dropdown options)
```

### Admin UI surface (3 platforms × 3 views = 9 routes)

```
/admin/facebook-posts        ← Calendar view
/admin/facebook-queue        ← Triage table
/admin/facebook-drafts/:id   ← Detail page

/admin/instagram-posts       ← same shape
/admin/instagram-queue
/admin/instagram-drafts/:id

/admin/tiktok-posts          ← same shape (only divergence: music_suggestion in detail)
/admin/tiktok-queue
/admin/tiktok-drafts/:id
```

Sidebar gains 3 new sections under existing LinkedIn:
```
- LinkedIn (existing)
- Facebook (NEW)
  - Posts (calendar) | Queue
- Instagram (NEW)
  - Posts (calendar) | Queue
- TikTok (NEW)
  - Posts (calendar) | Queue
```

Detail page key UI elements (per platform):

| Element | Behavior | FB | IG | TikTok |
|---|---|---|---|---|
| Hero panel | FSM-aware status chip | ✅ | ✅ | ✅ |
| Slide preview strip | Reads linkedin_post.carousel_slides[] | ✅ | ✅ | ✅ |
| Title editor | char counter | 150 cap | 150 cap (hook line) | 100 cap |
| Caption editor | char counter | up to 63k (FB) | up to 2200 | up to 2200 |
| Hashtag chip editor | hard cap | 8 (soft) | **5 (HARD — Dec 2025)** | 8 (mix trending+niche) |
| Music suggestion | read-only text | ❌ | ❌ | ❌ (Publer auto_add_music handles) |
| Auto-add music toggle | boolean override | ❌ | ❌ | ✅ default ON |
| Format indicator | text vs carousel chip | ✅ both | n/a (always carousel) | n/a |
| Suggested time | datetime picker | from posting_time_rules | same | same |
| Approve button | dispatches PublishViaPubler | ✅ | ✅ | ✅ |
| Publer post link | shows after publishing | ✅ deep link | ✅ deep link | ✅ deep link |
| External URL | populated when published | ✅ | ✅ | ✅ |
| Cancel | DELETEs Publer post | ✅ | ✅ | ✅ |

### Telegram notification design (simplified vs prior plan)

Two notification types per platform — no checklist (Publer handles publish):

```
✅ Published to Instagram

Title: {title}
Published at: {published_at} WIB
URL: {external_url}

Source: {blog_url}
```

```
❌ Publish failed on Instagram

Error: {last_error}
Title: {title}

Retry: {admin_url} → click Regenerate
```

Three new notification toggles in `telegram` settings group:
```
telegram_notify_facebook_published / _failed
telegram_notify_instagram_published / _failed
telegram_notify_tiktok_published / _failed
```

### Data Integration Map (final)

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Slide PNG URLs | `linkedin_posts.carousel_slides[].image_url` | ✅ existing | Publicly accessible via `https://alisadikinma.com/storage/linkedin-carousel/` — Publer downloads via `/media/from-url` |
| Publer media_ids cache | `linkedin_posts.publer_media_ids` JSON | ❌ new | Additive migration on linkedin_posts; saves 67% media upload calls (3 platforms reuse) |
| Blog content | `posts.translations.content` (EN preferred) | ✅ | Read by plugin via existing `/automation/posts/{id}` |
| ContentIdea pillar/virality | `content_ideas.pillar` + `virality_score` | ✅ | Reuse virality gate from LinkedIn scan |
| Posting time | `posting_time_rules` (platform=facebook/instagram/tiktok) | ✅ schema, ❌ data | Operator runs research command for 3 platforms post-deploy |
| FSM trait | `HasStatusTransitions` (enum-class-generic since April 23) | ✅ | Wire to all 3 cross-post models |
| Plugin /instagram-gen | new repo `social-short-form-writer` | ❌ | Reused by both IG and FB pipelines |
| Plugin /tiktok-gen | same repo | ❌ | TikTok-only (caption-as-search-index, music_suggestion) |
| Plugin RAG content | `docs/research/2026-05-07-ig-tiktok-best-practice/` | ✅ already on disk | Seeded by prior research agent |
| Publer API key | `settings(group=publer, key=publer_api_key)` | ❌ new | Encrypted via `Crypt::encryptString` |
| Publer account IDs | `settings(group=publer, key=publer_{fb|ig|tt}_account_id)` | ❌ new | Auto-discovered via /accounts |
| PublerClient | new service | ❌ | Thin REST wrapper, no Composer dep needed (use Laravel HTTP client) |
| Telegram service | `TelegramNotificationService` | ✅ | Add 6 notification methods (3 platforms × {published, failed}) |
| Queue worker | `portfolio-queue.service` (claudesn) | ✅ | All new jobs queue here |
| Cron scheduler | `routes/console.php` + host crontab | ✅ | 2 new entries: PollPublerJobs (every minute), 3 new purge crons |
| Empty MCP config | `/home/claudesn/empty-mcp.json` | ✅ | Reuse existing flag pattern (April 29 leak fix) |
| SSH key (queue context) | `/home/claudesn/.ssh/id_ed25519` | ✅ | Used by /instagram-gen + /tiktok-gen SSH dispatches |

### Phase-by-phase mapping (vs prior May 7 plan)

| Prior phase | Status as of May 8 | Disposition |
|---|---|---|
| A — Schema (instagram_posts, tiktok_posts) | ✅ shipped May 7 | Keep |
| B — Enums + models with FSM | ✅ shipped May 7 | Keep, but Phase B2 needed for ENUM rename |
| C — Plugin services + jobs (manual workflow) | ❌ halted May 8 | Replan as Publer-aware (caption services + new PublishViaPubler job) |
| D — ScanLinkedInForCrossPost cron | Not started | Unchanged scope (now creates 3 rows instead of 2) |
| E — Admin controllers + routes | Not started | 3× platform controllers (FB + IG + TT), drop "mark-published" endpoint |
| F — Plugin scaffolding | Not started | Unchanged (2 skills, FB reuses /instagram-gen) |
| G — Telegram notif | Not started | Simplified (no checklist) |
| **H — Drive upload** | Not started | **DROPPED** (Publer ingests via URL) |
| **NEW: A2 — additive migration (publer cols + ENUM rename)** | New | Small, ~30 min |
| **NEW: A3 — facebook_posts table** | New | Mirrors instagram_posts schema, ~15 min |
| **NEW: B2 — FSM enum case rename + transitions update** | New | ~30 min, breaks existing tests, fix in same commit |
| **NEW: H' — PublerClient + PublerSyncService + PollPublerJobs cron** | New | ~3 days |
| **NEW: H'' — Settings UI + account discovery flow** | New | ~1 day |
| **NEW: Phase A4 — additive migration linkedin_posts.publer_media_ids JSON** | New | 5 min |
| I, J, K — Frontend composables/calendar/queue | Not started | 3× platforms instead of 2; templates parameterized |
| L — Detail views | Not started | Drop mark-published form, add publer_post_id deep-link |
| M — Sidebar + posting time | Not started | 3 sidebar entries; 3 schedule entries for posting-rules:research |
| N — Purge crons | Not started | 3 commands (FB + IG + TT) |
| O — E2E smoke | Not started | 1 blog → 4 platforms (LinkedIn + FB + IG + TT) |
| P — CLAUDE.md docs | Not started | Document all the above |

### Estimated scope

| Item | Effort |
|---|---|
| Phase A2 — additive migration (publer cols + ENUM rename, 2 existing tables) | ~30 min |
| Phase A3 — facebook_posts table | ~15 min |
| Phase A4 — linkedin_posts.publer_media_ids cache column | ~10 min |
| Phase B2 — FSM enum case rename + adjacency update + test fixes | ~45 min |
| Phase F — plugin scaffolding (`social-short-form-writer`, 2 skills) | ~3 days |
| Phase C — caption services + 3 generation jobs | ~1.5 days |
| Phase H' — PublerClient + PublerSyncService + PollPublerJobs cron + tests | ~3 days |
| Phase H'' — admin settings UI + account discovery flow | ~1 day |
| Phase D — ScanLinkedInForCrossPost cron (creates 3 rows) | ~0.5 day |
| Phase E — 3 admin controllers (FB + IG + TT) + 3×7 endpoints + ZIP DROPPED | ~2 days |
| Phase G — Telegram notif extension (6 methods + 6 settings) | ~0.5 day |
| Phase I — Frontend composables (3 platforms) | ~1 day |
| Phase J — Calendar views (3 platforms) | ~1 day |
| Phase K — Queue views (3 platforms) | ~1 day |
| Phase L — Detail views (3 platforms) | ~2 days |
| Phase M — Sidebar nav + posting-rules:research scheduling extension | ~0.5 day |
| Phase N — Purge crons (3 platforms) | ~0.5 day |
| Phase O — E2E smoke + manual VPS verification | ~0.5 day |
| Phase P — CLAUDE.md docs | ~0.5 day |
| Operator one-time Publer setup (API key + account picker) | ~5 min |
| **Total v1** | **~17–19 working days** |

(Up from prior plan ~9-10 days due to: 3rd platform (FB) + Publer integration replacing manual workflow + 2 amendment phases for shipped Phase A/B.)

### Anti-patterns explicitly avoided

- ❌ Storing Publer API key in `.env` (use encrypted setting for operator rotation)
- ❌ Multipart upload to Publer (use `/media/from-url` — Publer downloads from our public storage)
- ❌ Per-platform media upload (cache `publer_media_ids` on linkedin_posts row, share across 3 platforms)
- ❌ Bundled approval (operator wants per-platform gates)
- ❌ Auto-publish without review (operator wants admin gate before Publer dispatch)
- ❌ Webhook-driven publish confirmation (Publer doesn't expose webhooks — must poll)
- ❌ Storing slide PNGs in cross-post tables (read live via FK to linkedin_posts.carousel_slides)
- ❌ Premature unification of 4 platforms into 1 social_posts table (LinkedIn schema diverges enough; cross-post 3 platforms can stay 3 sibling tables)

### Risk register

| Risk | Likelihood | Mitigation |
|---|---|---|
| Publer API rate limit (100 req/2 min) hit during scan + publish bursts | Low | At expected volume (~10-50 posts/month × 3 platforms = ~150 publish calls/month), well under limit. Plus media upload caching reduces by 67% |
| Publer changes API contract | Medium | Pin to v1 explicitly, version-aware error handling, weekly e2e smoke caught quickly |
| Publer post fails after PublishViaPubler dispatched | Medium | FSM Failed state with last_error from Publer body; admin Regenerate button re-runs with fresh dispatch |
| Operator's Publer trial/subscription expires mid-month | Low | PublerClient catches 402/403, surfaces to admin UI as red banner. Operator gets daily reminder cron for expiry approaching |
| Sonnet output truncation in /instagram-gen plugin | Medium | Tighten plugin caption length invariant (<1500 chars per platform) — same mitigation as carousel-gen May 2 issue |
| API key leak in Git/logs | Critical-if-occurs | Encrypted at-rest in DB, masked in API responses (***SET***), never logged in PublerClient (use redaction wrapper). Operator rotates after this chat (key was shared in transcript) |
| FSM rename breaks existing data (Phase B already shipped May 7) | Low | Only existing rows are test fixtures + dev DB. Migration ALTERS ENUM + UPDATEs old values to new. Production has no rows yet |
| Publer media_ids cache poisoning if PNG URL changes | Low | Cache is per-linkedin_post; if operator regenerates LinkedIn carousel, new linkedin_posts row → new cache. No stale cross-references |
| FB caption from /instagram-gen too IG-flavored | Medium | Acceptable per design — operator can edit inline before approve. If quality drops, add /facebook-gen skill in v2 |

### Implementation feasibility flags

- ✅ Reuses existing infra: queue worker, SSH bridge, posting_time_rules, Telegram service, HasStatusTransitions trait
- ✅ Plugin pattern proven 4× (article + linkedin + carousel-gen + the upcoming social-short-form-writer)
- ✅ Publer API simple REST + bearer auth — no SDK needed (Laravel HTTP client suffices)
- ✅ Slide PNGs already publicly accessible (no auth-token URL workaround needed)
- ⚠️ Need to verify: Does Publer's `state: 'scheduled'` ACTUALLY publish at scheduled_at? Or does it stay as draft? — verify in v1 smoke test
- ⚠️ Need to verify: Does `accounts[i].scheduled_at` accept future ISO timestamps cleanly, or has a max horizon (LinkedIn's 75-day max)? — test with 30-day-out timestamp
- ⚠️ Operator action: rotate API key after this brainstorm (key in chat history)
- ⚠️ Operator action: ensure all 3 Publer-connected accounts (FB Page, IG, TikTok) have full publishing permissions enabled in Publer

### Open questions for `/gaspol-plan`

1. Publer media upload retry strategy — if `/media/from-url` job fails (job_status='failed'), do we re-upload or fail the cross-post draft entirely?
2. PollPublerJobs poll cadence — every minute (chosen above) or backed off (1 min for first 5 min, then 5 min)? Trade-off: latency to status update vs cron frequency
3. Approve action UX when Publer disabled (`publer_enabled='false'`) — should approve fail with clear error, or queue locally for retry once enabled?
4. Multi-account picker — operator may add more IG/TikTok accounts later. Is per-draft account override needed, or just default-account-only?
5. Plugin output validation gate — when `validation.passed=false` from plugin, does the row go to `failed` (per LinkedIn precedent) or stay `awaiting_review` for operator override?
6. Publer account list refresh cadence — manual (operator clicks button) or auto (daily cron)?

---

*This file is a hybrid Design + Plan document. `/gaspol-plan` will append `## Implementation Plan` section below this line with step-by-step phases mapped to the table above (A2, A3, A4, B2, C, D, E, F, G, H', H'', I, J, K, L, M, N, O, P).*
