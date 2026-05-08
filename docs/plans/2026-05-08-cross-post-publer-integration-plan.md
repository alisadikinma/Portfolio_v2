# Implementation Plan — Cross-post LinkedIn → FB + IG + TikTok via Publer

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

**Companion design doc**: [2026-05-08-cross-post-publer-integration.md](2026-05-08-cross-post-publer-integration.md) — read the `## Design` section first; this plan is additive.

**Status as of May 8, 2026:**
- Phase A (instagram_posts + tiktok_posts schemas) — ✅ shipped May 7
- Phase B (status enums + Eloquent models with FSM) — ✅ shipped May 7
- This plan starts from **Phase A2** (additive migrations + ENUM rename) and **Phase B2** (FSM enum case rename — BREAKING with A2, must coordinate)

---

## Goal

Wire automated cross-post fan-out from LinkedIn (text + carousel formats) → Facebook Page + Instagram + TikTok via Publer's REST API. Reuse existing carousel slide PNGs 1:1, reuse LinkedIn text content for FB text-format posts. Plugin authors platform-specific captions (`/instagram-gen` reused by FB carousel, `/tiktok-gen` for TikTok). Operator approves per-platform in our admin (consistent with LinkedIn workflow). Backend POSTs to Publer with `state: 'scheduled'`, polls job_status, advances FSM. Drop manual mobile-app workflow + Drive uploads (replaced by Publer).

---

## Architecture Context (from CLAUDE.md + already-shipped Phase A/B)

Templates to mirror:

| Need | Existing reference | Notes |
|---|---|---|
| Plugin repo layout | `D:\Projects\claude-plugin\linkedin-post-writer` | compile-refs.ts pattern (April 23 ship) |
| SSH bridge service | [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) | parseOrchestratorOutput + buildMcpFlags + executeSSH (May 2 fix) |
| Queued generation job | [`GenerateLinkedInPost`](backend/app/Jobs/GenerateLinkedInPost.php) | 360s timeout, idempotent skip-when-not-generatable, stale-retry recovery |
| FSM trait | [`HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) | Already enum-class-generic — works for any BackedEnum |
| FSM guard | [`PipelineGuard::advance`](backend/app/Services/PipelineGuard.php) | Uniform logging |
| Admin controller | [`LinkedInDraftController`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) | 7-endpoint pattern |
| Posting time | `posting_time_rules` table + [`ResearchPostingTimeRules`](backend/app/Console/Commands/ResearchPostingTimeRules.php) | Multi-platform schema since May 6 |
| Frontend composable | [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) | 30s staleTime + refetchOnMount:'always' |
| Calendar/Queue/Detail views | LinkedIn templates | Copy + parameterize per platform |
| Helpers module | [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js) | STATUS_META + transitionSummary + 18 SVG icons |
| Telegram service | `App\Services\TelegramNotificationService` + `App\Jobs\DispatchTelegramNotification` | Settings group `telegram` toggles |
| Settings encryption | `mail_password` precedent (May 5 newsletter) | `Crypt::encryptString` + `***SET***` masking in API responses |
| Empty MCP flag | `/home/claudesn/empty-mcp.json` | Reuse via helper for plugin SSH calls (April 29 leak fix) |
| SSH key (queue ctx) | `/home/claudesn/.ssh/id_ed25519` | Required for queue worker SSH dispatch |

Already-shipped foundation:
- [`backend/database/migrations/2026_05_07_100001_create_instagram_posts_table.php`](backend/database/migrations/2026_05_07_100001_create_instagram_posts_table.php)
- [`backend/database/migrations/2026_05_07_100002_create_tiktok_posts_table.php`](backend/database/migrations/2026_05_07_100002_create_tiktok_posts_table.php)
- [`backend/app/Enums/InstagramPostStatus.php`](backend/app/Enums/InstagramPostStatus.php) — needs B2 rename
- [`backend/app/Enums/TiktokPostStatus.php`](backend/app/Enums/TiktokPostStatus.php) — needs B2 rename
- [`backend/app/Models/InstagramPost.php`](backend/app/Models/InstagramPost.php)
- [`backend/app/Models/TiktokPost.php`](backend/app/Models/TiktokPost.php)
- [`backend/tests/Unit/InstagramPostStatusTransitionsTest.php`](backend/tests/Unit/InstagramPostStatusTransitionsTest.php) — needs B2 update
- [`backend/tests/Unit/TiktokPostStatusTransitionsTest.php`](backend/tests/Unit/TiktokPostStatusTransitionsTest.php) — needs B2 update
- [`backend/tests/Feature/Migrations/CrossPostSchemaTest.php`](backend/tests/Feature/Migrations/CrossPostSchemaTest.php) — needs A2/A3/A4 expansion

---

## Tech Stack (no new deps required)

- Backend: Laravel 12 + PHP 8.2 + MySQL 8 + Sanctum 4
- Plugin: Node.js 20 + TypeScript + Zod + tsx (mirror linkedin-post-writer)
- Frontend: Vue 3.5 + Pinia 3 + TanStack Vue Query 5.90 + Tailwind 4
- HTTP client: Laravel `Http::withHeaders()->post()` (no SDK needed for Publer)
- Model routing: Sonnet 4.6 for plugin gen calls (per Model Selection Policy)

---

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Slide PNG URLs | `linkedin_posts.carousel_slides[].image_url` | Eloquent FK | ✅ | Use directly via `$xPost->linkedinPost->carousel_slides` |
| LinkedIn text content | `linkedin_posts.content` | Eloquent FK | ✅ | Read directly for FB text format |
| Blog title/content | `posts.translations.content` (EN preferred) | `Post::translation('en')` accessor | ✅ | Read directly |
| ContentIdea pillar/virality | `content_ideas.pillar` + `virality_score` | `Post::contentIdea()` BelongsTo | ✅ | Reuse virality gate from `linkedin_virality_min_score` |
| Posting time slots | `posting_time_rules` (platform=facebook/instagram/tiktok) | `PostingTimeRule::forPlatform()` scope | ✅ schema, ❌ data | Operator runs research command 3x post-deploy |
| FSM trait | `HasStatusTransitions` | `transitionTo()` | ✅ | Wire to all 3 cross-post models |
| FSM guard | `PipelineGuard::advance` | static method | ✅ | Call from services on every status change |
| Plugin /instagram-gen | new repo `social-short-form-writer` | SSH dispatch | ❌ new | Phase F creates; reused by IG + FB carousel |
| Plugin /tiktok-gen | same repo | SSH dispatch | ❌ new | Phase F creates |
| Plugin RAG content | `docs/research/2026-05-07-ig-tiktok-best-practice/` | file-based markdown | ✅ already on disk | Seeded by prior research agent |
| Compiled plugin refs | `/home/claudesn/refs-instagram.md`, `refs-tiktok.md` | `--append-system-prompt-file` | ❌ new | Plugin compile-refs.ts produces; operator deploys to VPS |
| Publer API key | `settings(group=publer, key=publer_api_key)` | `Setting::firstOrCreate()` + `Crypt::encryptString` | ❌ new | Phase H'' encrypted setting |
| Publer account IDs | `settings(group=publer, key=publer_{fb,ig,tt}_account_id)` | same | ❌ new | Auto-discovered via `GET /accounts` |
| PublerClient | `App\Services\PublerClient` | constructor-injected | ❌ new | Thin REST wrapper using Laravel HTTP client |
| Empty MCP flag | `/home/claudesn/empty-mcp.json` | reuse via helper | ✅ | Required for plugin SSH calls (April 29 leak fix) |
| SSH key (queue ctx) | `/home/claudesn/.ssh/id_ed25519` | env `SOCIAL_GEN_SSH_KEY` | ✅ | Same key as LinkedIn pipeline |
| Queue worker | `portfolio-queue.service` (claudesn) | systemd | ✅ | All new jobs queue here |
| Telegram service | `TelegramNotificationService` | DI | ✅ | Phase G adds 6 new methods |
| Sidebar nav | [`AdminLayout.vue`](frontend/src/layouts/AdminLayout.vue) | template | ✅ | Phase M appends 3 sections |
| Composable pattern | `useLinkedInDrafts.js` | TanStack Query | ✅ template | Phase I copies + renames |
| Calendar template | `LinkedInPostsCalendar.vue` | Vue SFC | ✅ template | Phase J copies × 3 |
| Helpers module | `linkedinHelpers.js` | STATUS_META + transitionSummary | ✅ template | Phase K creates `socialPlatformHelpers.js` |

---

## Phase ordering & wave plan

Recommended `/gaspol-parallel plan-phases` waves:

- **Wave 1 (sequential — schema + FSM foundation):** A2 → A3 → A4 → B2
- **Wave 2 (parallel — 3 subagents):** C, F, H'
- **Wave 3 (parallel — 4 subagents):** D, E, G, H''
- **Wave 4 (parallel — 3 subagents):** I, J, K
- **Wave 5 (parallel — 3 subagents):** L, M, N
- **Wave 6 (sequential):** O → P

Critical-path dependencies:
- A2 must precede B2 (DB ENUM rename before PHP enum rename — BREAKING coordination)
- A4 must precede C+H' (publer_media_ids cache column needed for cross-platform reuse)
- F must precede C (plugin must exist before generation services can SSH-call it)
- H' must precede E + Wave 3 (controllers + Wave 4 frontend rely on PublerClient)
- All of Wave 2-3 must complete before Wave 4-5 (frontend needs API endpoints stable)

---

## Phase A2: Additive migration — Publer columns + ENUM rename on shipped tables

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/database/migrations/2026_05_08_100001_add_publer_fields_and_rename_enum_on_cross_post_tables.php`
- Modify: `backend/tests/Feature/Migrations/CrossPostSchemaTest.php` (add publer_* column assertions + new ENUM value asserts)

**Steps:**
1. Write failing test for `instagram_posts` having `publer_post_id` column. Expected error: `Failed asserting that false is true.` (Schema::hasColumn returns false because column not yet added)
2. Run test, confirm it fails for the expected reason
3. Implement migration `up()`:
   - `ALTER TABLE instagram_posts MODIFY COLUMN status ENUM('pending_generation','generating','awaiting_review','publishing','published','failed','cancelled') NOT NULL DEFAULT 'pending_generation'`
   - First `UPDATE instagram_posts SET status='publishing' WHERE status='awaiting_manual_publish'` (no production data exists yet but defensive)
   - First `UPDATE instagram_posts SET status='published' WHERE status='published_externally'`
   - Add nullable cols: `publer_post_id VARCHAR(100)`, `publer_job_id VARCHAR(100)`, `publer_status VARCHAR(50)`, `publer_account_id VARCHAR(100)`
   - Add index `idx_instagram_publer_polling (status, publer_job_id)`
   - Repeat all of above for `tiktok_posts`
4. Implement migration `down()`: reverse — drop columns + drop index + restore old ENUM values + UPDATE rows back
5. Run `php artisan migrate` → run schema test → confirm pass
6. Add 4 new asserts in `CrossPostSchemaTest`: each table has 4 publer_* columns + new ENUM allows `publishing` + `published`
7. Suggested commit: `feat(cross-post): add Publer integration columns + rename FSM enum values`

**Verification:**
- [ ] `php artisan migrate` runs cleanly
- [ ] `php artisan migrate:rollback --step=1` reverses cleanly (can re-roll forward)
- [ ] Schema test passes with 4 new column asserts per table
- [ ] `DB::select("SHOW INDEX FROM instagram_posts")` returns the new `idx_instagram_publer_polling` index
- [ ] No PHP fatal errors loading models with new schema

---

## Phase A3: New `facebook_posts` table with format discriminator

**Estimated time:** 15 minutes

**Files:**
- Create: `backend/database/migrations/2026_05_08_100002_create_facebook_posts_table.php`
- Modify: `backend/tests/Feature/Migrations/CrossPostSchemaTest.php` (add facebook_posts assertions)

**Steps:**
1. Write failing test asserting `Schema::hasTable('facebook_posts')` returns true. Expected error: `Failed asserting that false is true.`
2. Run test, confirm it fails for the expected reason
3. Implement migration mirroring `instagram_posts` schema PLUS:
   - `format ENUM('text','carousel') NOT NULL` AFTER `status` — discriminator
   - `link_url VARCHAR(500) NULL` AFTER `external_url` — populated for text format (FB unfurls), NULL for carousel
   - All 4 publer_* columns from A2
   - Index `idx_facebook_post_format (format, status, deleted_at)` — query "all FB carousel drafts in queue"
   - Index `idx_facebook_post_publer_polling (status, publer_job_id)`
4. Implement migration `down()`: `Schema::dropIfExists('facebook_posts')`
5. Run `php artisan migrate` → run schema test → confirm pass
6. Add full set of asserts in CrossPostSchemaTest mirroring instagram_posts pattern + format/link_url asserts
7. Suggested commit: `feat(cross-post): add facebook_posts table with format discriminator`

**Verification:**
- [ ] `Schema::hasTable('facebook_posts')` true
- [ ] All 18 columns present (16 base + format + link_url + 4 publer_*)
- [ ] FK constraints: `linkedin_post_id` ON DELETE SET NULL, `post_id` ON DELETE CASCADE
- [ ] `idx_facebook_post_format` index present
- [ ] Migration rollback works
- [ ] Schema test passes with FB-specific asserts

---

## Phase A4: Drop `music_suggestion` from tiktok_posts + add `publer_media_ids` to linkedin_posts

**Estimated time:** 8 minutes

**Files:**
- Create: `backend/database/migrations/2026_05_08_100003_finalize_cross_post_schema.php`
- Modify: `backend/tests/Feature/Migrations/CrossPostSchemaTest.php`

**Steps:**
1. Write failing test asserting `linkedin_posts` has `publer_media_ids` column. Expected error: `Failed asserting that false is true.`
2. Run test, confirm it fails for the expected reason
3. Implement migration:
   - `ALTER TABLE tiktok_posts DROP COLUMN music_suggestion` (no prod data exists yet)
   - `ALTER TABLE linkedin_posts ADD COLUMN publer_media_ids JSON NULL COMMENT 'Cached Publer media IDs from /media/from-url, shared across cross-post platforms'`
4. Implement `down()`: re-add `music_suggestion VARCHAR(255) NULL` to tiktok_posts; drop `publer_media_ids` from linkedin_posts
5. Run migrate → tests pass
6. Suggested commit: `feat(cross-post): drop music_suggestion (Publer handles), add linkedin_posts.publer_media_ids cache`

**Verification:**
- [ ] `Schema::hasColumn('tiktok_posts', 'music_suggestion')` returns false
- [ ] `Schema::hasColumn('linkedin_posts', 'publer_media_ids')` returns true
- [ ] Migration rollback restores both columns
- [ ] Existing tests still pass (no breakage to LinkedIn pipeline)

---

## Phase B2: FSM enum case rename + transition map update + test fixes

**Estimated time:** 25 minutes

**Files:**
- Modify: `backend/app/Enums/InstagramPostStatus.php`
- Modify: `backend/app/Enums/TiktokPostStatus.php`
- Modify: `backend/tests/Unit/InstagramPostStatusTransitionsTest.php`
- Modify: `backend/tests/Unit/TiktokPostStatusTransitionsTest.php`

**Steps:**
1. Write failing test asserting `InstagramPostStatus::Publishing` exists as enum case. Expected error: `Error: Undefined constant App\Enums\InstagramPostStatus::Publishing`
2. Run test, confirm it fails for the expected reason
3. Modify `InstagramPostStatus.php`:
   - Rename case `AwaitingManualPublish = 'awaiting_manual_publish'` → `Publishing = 'publishing'`
   - Rename case `PublishedExternally = 'published_externally'` → `Published = 'published'`
   - Update `TRANSITIONS` map: replace string keys + values to match new enum values
   - New adjacency map per design § FSM
4. Repeat all of step 3 for `TiktokPostStatus.php`
5. Update `InstagramPostStatusTransitionsTest`:
   - Replace test method names referring to "manual_publish" with "publishing"
   - Update enum references in assertions (e.g., `AwaitingManualPublish` → `Publishing`, `PublishedExternally` → `Published`)
   - Update `feedStatuses()` + `queueStatuses()` assertions to match design
6. Same updates for `TiktokPostStatusTransitionsTest`
7. Run `php artisan test --filter='InstagramPostStatusTransitionsTest|TiktokPostStatusTransitionsTest'` → all 23 tests pass
8. Suggested commit: `refactor(cross-post): rename FSM cases to match Publer-aware lifecycle (BREAKING with A2)`

**Verification:**
- [ ] All 23 unit tests pass with new enum case names
- [ ] `feedStatuses()` returns `['publishing', 'published', 'cancelled']` (replacing old `awaiting_manual_publish`/`published_externally`)
- [ ] FSM lockstep test (`test_fsm_matches_instagram_sibling`) still passes — IG and TikTok TRANSITIONS arrays remain identical
- [ ] `InstagramPostStatus::Generating->canTransitionTo(InstagramPostStatus::AwaitingReview)` returns true
- [ ] `InstagramPostStatus::AwaitingReview->canTransitionTo(InstagramPostStatus::Publishing)` returns true (NEW transition)
- [ ] `InstagramPostStatus::Publishing->canTransitionTo(InstagramPostStatus::Published)` returns true (NEW)

---

## Phase C: Caption services + 3 generation jobs

**Estimated time:** 90 minutes (5 sub-files)

**Files:**
- Create: `backend/app/Models/FacebookPost.php`
- Create: `backend/app/Enums/FacebookPostStatus.php`
- Create: `backend/app/Services/InstagramGenerationService.php`
- Create: `backend/app/Services/TiktokGenerationService.php`
- Create: `backend/app/Services/FacebookGenerationService.php`
- Create: `backend/app/Jobs/GenerateInstagramPost.php`
- Create: `backend/app/Jobs/GenerateTiktokPost.php`
- Create: `backend/app/Jobs/GenerateFacebookPost.php`
- Create: `backend/config/social-cross-post.php`
- Modify: `backend/.env.example`
- Test: `backend/tests/Unit/InstagramGenerationServiceParseTest.php`
- Test: `backend/tests/Feature/GenerateInstagramPostJobTest.php`
- Test: `backend/tests/Feature/GenerateFacebookPostFormatRoutingTest.php`

**Steps:**
1. Write failing test for `InstagramGenerationService::parseOrchestratorOutput` happy path + Sonnet preamble + fenced JSON. Expected error: `Error: Class "App\Services\InstagramGenerationService" not found`
2. Run test, confirm it fails for the expected reason
3. Create `FacebookPostStatus` enum (mirror InstagramPostStatus post-rename, identical 7 cases + same transitions)
4. Create `FacebookPost` model (mirror InstagramPost — fillable + casts + statusEnumClass + relations + scopes; ADD `format` to fillable, `format` casts to string, `link_url` in fillable)
5. Implement `InstagramGenerationService` lifting `parseOrchestratorOutput` from [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) verbatim (balanced-brace scanner, NO fence-strip regex per May 2 fix)
6. Implement `InstagramGenerationService::generate(InstagramPost $draft)`:
   - SSH dispatch via `dispatchPlugin('/instagram-gen', $blogPayload)` using config `social-cross-post.generation.refs_instagram`
   - Reuse `buildMcpFlags()` helper pattern (April 29 fix — `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config`)
   - Parse stdout, advance FSM via `PipelineGuard::advance($draft, InstagramPostStatus::Generating, 'generation_start')` then `→AwaitingReview` on success or `→Failed` on parse failure
   - Persist title + caption + hashtags + suggested_time (queried from `posting_time_rules` for `(platform=instagram, audience='b2b_tech')`)
7. Implement `TiktokGenerationService` (same pattern, `--append-system-prompt-file=$refsTiktok`, plugin skill `/tiktok-gen`)
8. Implement `FacebookGenerationService::generate(FacebookPost $draft)` with format-aware branching:
   - If `$draft->format === 'text'`: read `$draft->linkedinPost->content` directly, lightweight Sonnet trim if >2000 chars (use existing `ArticleGenerationService::rewriteVisualDirectionForSafety` pattern for sync Sonnet call), set `$draft->link_url` to blog URL
   - If `$draft->format === 'carousel'`: SSH-invoke `/instagram-gen` plugin (REUSED), map output to FacebookPost fields
9. Implement `config/social-cross-post.php` per design § Settings (driver, ssh_host, ssh_user, ssh_key, claude_path, model='sonnet', refs_instagram, refs_tiktok, empty_mcp_config, timeout_seconds=300)
10. Add `.env.example` keys: `SOCIAL_GEN_DRIVER=ssh`, `SOCIAL_GEN_SSH_HOST=localhost`, `SOCIAL_GEN_SSH_USER=claudesn`, `SOCIAL_GEN_SSH_KEY=/home/claudesn/.ssh/id_ed25519`, `SOCIAL_GEN_CLAUDE_PATH=claude`, `SOCIAL_GEN_MODEL=sonnet`, `SOCIAL_GEN_REFS_INSTAGRAM=/home/claudesn/refs-instagram.md`, `SOCIAL_GEN_REFS_TIKTOK=/home/claudesn/refs-tiktok.md`, `SOCIAL_GEN_EMPTY_MCP_CONFIG=/home/claudesn/empty-mcp.json`, `SOCIAL_GEN_TIMEOUT_SECONDS=300`
11. Implement `GenerateInstagramPost` queued job: 360s timeout, 2 retries (60s/300s backoff), idempotent skip-when-not-generatable (mirror `GenerateLinkedInPost` stale-retry recovery pattern)
12. Implement `GenerateTiktokPost` (same pattern)
13. Implement `GenerateFacebookPost` (same pattern but loads FacebookPost model)
14. Write 6 parser tests covering: clean JSON, Sonnet preamble + fenced JSON, trailing narration, empty stdout, malformed JSON, status=failed envelope
15. Write feature test for `GenerateInstagramPost`: dispatches with mocked SSH driver, asserts FSM advances `pending_generation → generating → awaiting_review` on `validation.passed=true`, → failed on `passed=false`
16. Write `GenerateFacebookPostFormatRoutingTest`: text-format draft uses LinkedIn content directly (no SSH call), carousel-format draft fires SSH to /instagram-gen
17. Run all tests, confirm pass
18. Suggested commit: `feat(cross-post): caption generation services + 3 queued jobs (FB dual-format, IG/TikTok plugin)`

**Verification:**
- [ ] All parser tests pass (regression coverage for May 2 fence-strip bug)
- [ ] FSM advances correctly on happy path for all 3 platforms
- [ ] FacebookGenerationService correctly branches on format (text uses linkedin_post content, carousel uses /instagram-gen)
- [ ] `buildMcpFlags()` correctly emits `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config`
- [ ] No `dd()` / `dump()` / TODO comments
- [ ] FacebookPost model has format/link_url in fillable
- [ ] FacebookPostStatus enum mirrors InstagramPostStatus

---

## Phase D: ScanLinkedInForCrossPost cron with format-aware routing

**Estimated time:** 18 minutes

**Files:**
- Create: `backend/app/Console/Commands/ScanLinkedInForCrossPost.php`
- Modify: `backend/routes/console.php` (add schedule entry)
- Test: `backend/tests/Feature/ScanLinkedInForCrossPostTest.php`

**Steps:**
1. Write failing test asserting cron creates ONLY `facebook_posts` row when LinkedIn post format='text'. Expected error: `Error: Class "App\Console\Commands\ScanLinkedInForCrossPost" not found`
2. Run test, confirm it fails for the expected reason
3. Implement command (signature `social-cross-post:scan {--dry-run} {--limit=20}`):
   - Query `LinkedInPost::whereIn('status', ['awaiting_publish', 'published'])` filtered by:
     - For carousel: all `carousel_slides[]` entries have `image_status='done'`
     - Source `ContentIdea.virality_score >= linkedin_virality_min_score` (reuse setting)
     - No live (non-trashed) FB row in `facebook_posts.where('post_id', $li->post_id)` etc
   - Per LinkedIn post matched, branch on `$li->format`:
     - `text`: create only `facebook_posts` row with `format='text'`
     - `carousel`: create 3 rows (FB with format='carousel', IG, TikTok)
   - Dispatch corresponding generation jobs after each insert
   - Honor --dry-run + --limit
4. Add schedule entry: `Schedule::command('social-cross-post:scan')->everyTwoMinutes()->withoutOverlapping(5);`
5. Write 6 feature tests: text-format creates FB only, carousel-format creates 3 rows, idempotent (no duplicates on re-run), virality gate skips low-score, slides-not-ready guard skips when any image_status≠done, --dry-run produces no DB writes
6. Run tests
7. Suggested commit: `feat(cross-post): scanner cron with format-aware fan-out routing`

**Verification:**
- [ ] Cron is idempotent (running 5x produces same DB state as 1x)
- [ ] format='text' route creates 1 row (FB only)
- [ ] format='carousel' route creates 3 rows (FB + IG + TT)
- [ ] Virality gate respected
- [ ] --dry-run produces no DB mutations
- [ ] Schedule entry verified via `php artisan schedule:list | grep social-cross-post`

---

## Phase E: Admin controllers + routes (3 platforms × 7 endpoints)

**Estimated time:** 60 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/FacebookDraftController.php`
- Create: `backend/app/Http/Controllers/Api/Admin/InstagramDraftController.php`
- Create: `backend/app/Http/Controllers/Api/Admin/TiktokDraftController.php`
- Modify: `backend/routes/api.php` (add 21 endpoints — 7 per platform)
- Test: `backend/tests/Feature/Admin/FacebookDraftControllerTest.php`
- Test: `backend/tests/Feature/Admin/InstagramDraftControllerTest.php`
- Test: `backend/tests/Feature/Admin/TiktokDraftControllerTest.php`

**Steps:**
1. Write failing test for `GET /admin/instagram-drafts` returning paginated list. Expected error: `Symfony\Component\Routing\Exception\RouteNotFoundException`
2. Run test, confirm it fails for the expected reason
3. Implement `InstagramDraftController` mirroring [`LinkedInDraftController`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) — 7 actions:
   - `index()` (filter by status/scope/per_page, eager-load `linkedinPost`)
   - `show()` (eager-load linkedinPost + post.translations)
   - `update()` (validate caption ≤2200, hashtags array max 5 for IG)
   - `regenerate()` (soft-delete + create pending row + dispatch GenerateInstagramPost, 409 on duplicate live)
   - `approve()` (awaiting_review → publishing, dispatch `PublishViaPubler::dispatch(InstagramPost::class, $id)`)
   - `cancel()` (any non-terminal → cancelled, if publer_post_id present call `PublerClient::deletePost`)
   - `calendar()` (mirror LinkedInDraftController calendar endpoint shape — `?from=&to=&status=`)
4. Implement `TiktokDraftController` (same pattern, hashtag validation 5-8 range)
5. Implement `FacebookDraftController` (same pattern + accept `format` field readonly + `link_url` field for text format only)
6. Add 21 routes to `routes/api.php` under `auth:sanctum` middleware group
7. Write 21+ tests: per-controller list/show/update/regenerate/approve/cancel happy paths + edge cases (FB format-text rejects carousel-shape update, IG hashtag exceeds 5 returns 422)
8. Run tests
9. Suggested commit: `feat(cross-post): admin controllers for FB + IG + TikTok drafts (21 endpoints)`

**Verification:**
- [ ] All 21 routes verified via `php artisan route:list`
- [ ] All endpoints require `auth:sanctum` (returns 401 JSON when bearer missing — verifies May 6 401 JSON fix)
- [ ] FSM transitions hit `PipelineGuard::advance` (audit log populated)
- [ ] Approve action queues `PublishViaPubler` (assert via `Queue::fake()` + `Queue::assertPushed`)
- [ ] IG hashtag validation enforces ≤5
- [ ] FB controller accepts text + carousel formats correctly

---

## Phase F: Plugin scaffolding `social-short-form-writer`

**Estimated time:** 3 hours (most of one day)

**Files (NEW separate repo at `D:\Projects\claude-plugin\social-short-form-writer`):**
- `package.json` (TypeScript + Zod + tsx + vitest)
- `scripts/compile-refs.ts` (mirror linkedin-post-writer compile-refs)
- `skills/instagram-gen/SKILL.md` + `schema.ts`
- `skills/tiktok-gen/SKILL.md` + `schema.ts`
- `docs/rag/social-base/*.md` (4-6 shared markdown files)
- `docs/rag/instagram-playbook/*.md` (4 files seeded from research)
- `docs/rag/tiktok-playbook/*.md` (5 files seeded from research)
- `tests/SchemaParseTest.spec.ts` (Zod validation)
- `README.md` + `CHANGELOG.md` + `.gitignore`

**Steps:**
1. Write failing Zod schema test asserting `InstagramOutputEnvelope` rejects 6+ hashtags (Dec 2025 hardcap). Expected error: `Cannot find module './schema'`
2. Run test, confirm it fails for the expected reason
3. Verify research output exists at `D:\Projects\Portfolio_v2\docs\research\2026-05-07-ig-tiktok-best-practice\INDEX.md` — if missing, BLOCK with human-readable error
4. Initialize npm repo: `npm init -y && npm install zod tsx typescript @types/node vitest --save-dev`
5. Implement `skills/instagram-gen/schema.ts`: discriminated union `CompleteEnvelope | FailedEnvelope` with Zod refinements: `hashtags.length >= 3 && hashtags.length <= 5` (HARD), `caption.length <= 2200`, `title.length <= 125`
6. Implement `skills/tiktok-gen/schema.ts`: same shape but `hashtags.length 5-8`, `title.length <= 100`, NO `music_suggestion` field (Publer handles)
7. Implement `scripts/compile-refs.ts`: bundles `docs/rag/social-base/*` + `docs/rag/{instagram,tiktok}-playbook/*` into 2 output bundles `refs-instagram.md` + `refs-tiktok.md`
8. Author `skills/instagram-gen/SKILL.md`: hard rules — English authoring, 5-hashtag CAP, first-line hook ≤125 chars, NO link in caption (acceptable since FB carousel uses this output and FB allows link in caption — but IG canonical workflow doesn't)
9. Author `skills/tiktok-gen/SKILL.md`: hard rules — first 150 chars critical (search index), 5-8 hashtags, ≤100 char title, link in caption acceptable
10. Seed 8+ RAG markdown files in `docs/rag/{social-base,instagram-playbook,tiktok-playbook}/` from research output (copy-paste + edit for plugin voice)
11. Run `npm run compile-refs` — confirm 2 output files generated, sizes 50-100KB each, gitignored
12. Run Zod schema tests, confirm pass
13. Suggested commit (in plugin repo): `feat: v0.1.0 social-short-form-writer with /instagram-gen + /tiktok-gen skills`
14. Document VPS deploy step in plugin `README.md`: `git pull && npm install && npm run compile-refs && symlink to /home/claudesn/refs-{instagram,tiktok}.md`

**Verification:**
- [ ] Plugin tests pass (`npm test`)
- [ ] `npm run compile-refs` produces both output files non-empty
- [ ] Zod schemas reject all known bad envelopes (IG with 6 hashtags, caption >2200, missing required fields)
- [ ] Plugin RAG content cites research sources (no fabricated stats)
- [ ] No reliance on training memory for 2025-2026 algorithm specs

---

## Phase G: Telegram notification extension

**Estimated time:** 25 minutes

**Files:**
- Modify: `backend/app/Services/TelegramNotificationService.php`
- Create: `backend/database/seeders/SocialCrossPostSettingsSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Test: `backend/tests/Feature/SocialTelegramNotificationTest.php`

**Steps:**
1. Write failing test asserting `telegram_notify_instagram_published` setting exists after seeder run. Expected error: `Failed asserting that null matches expected 'true'.`
2. Run test, confirm it fails for the expected reason
3. Implement `SocialCrossPostSettingsSeeder` adding 6 keys to `telegram` group via `firstOrCreate`:
   - `telegram_notify_facebook_published` = 'true'
   - `telegram_notify_facebook_failed` = 'true'
   - `telegram_notify_instagram_published` = 'true'
   - `telegram_notify_instagram_failed` = 'true'
   - `telegram_notify_tiktok_published` = 'true'
   - `telegram_notify_tiktok_failed` = 'true'
4. Wire seeder into `DatabaseSeeder::run`
5. Add 6 methods on `TelegramNotificationService`: `sendFacebookPublished($draft)`, `sendFacebookFailed($draft)`, etc. Each builds message per design § Telegram template, dispatches `DispatchTelegramNotification` job. Honors per-event toggle.
6. Run tests
7. Suggested commit: `feat(cross-post): Telegram notification types for FB + IG + TikTok publish events`

**Verification:**
- [ ] Seeder is idempotent
- [ ] All 6 methods are no-op when `telegram_enabled='false'` (master kill switch)
- [ ] Each method is no-op when corresponding per-event toggle is `'false'`
- [ ] Notification body contains: title, scheduled_at, caption preview, external_url (success) or last_error (failure)

---

## Phase H': PublerClient + PublerSyncService + PollPublerJobs cron

**Estimated time:** 4 hours (most of one day)

**Files:**
- Create: `backend/app/Services/PublerClient.php`
- Create: `backend/app/Services/PublerSyncService.php`
- Create: `backend/app/Jobs/PublishViaPubler.php`
- Create: `backend/app/Console/Commands/PollPublerJobs.php`
- Modify: `backend/routes/console.php` (add schedule entry)
- Modify: `backend/config/social-cross-post.php` (add `publer` section)
- Modify: `backend/.env.example` (add `PUBLER_*` keys)
- Test: `backend/tests/Unit/PublerClientTest.php` (mock HTTP)
- Test: `backend/tests/Feature/PublishViaPublerJobTest.php`
- Test: `backend/tests/Feature/PollPublerJobsCommandTest.php`

**Steps:**
1. Write failing test asserting `PublerClient::me()` returns array with user data. Expected error: `Error: Class "App\Services\PublerClient" not found`
2. Run test, confirm it fails for the expected reason
3. Implement `PublerClient` using Laravel `Http::withHeaders(['Authorization' => "Bearer-API {$apiKey}"])`. Methods:
   - `me()` → GET /users (test connection)
   - `listAccounts()` → GET /accounts
   - `uploadMediaFromUrl($url)` → POST /media/from-url, returns job_id
   - `pollMediaJob($jobId)` → GET /job_status/{job_id}, returns ['status' => ..., 'media_id' => ...]
   - `createPost($payload)` → POST /posts/schedule, returns job_id
   - `pollJob($jobId)` → GET /job_status/{job_id}
   - `deletePost($postId)` → DELETE /posts/{id}, idempotent (404 = success)
   - `getApiKey()` private — reads from `Setting::get('publer_api_key')` decrypted via `Crypt::decryptString`
   - Error mapping: 401 → invalidate-api-key signal, 429 → rate-limit-hit, 5xx → retry signal
4. Implement `PublerSyncService` orchestrator:
   - `publishFacebook(FacebookPost $draft)`: calls `buildPostPayload('facebook', $draft)`, ensureMediaIdsCached if carousel, createPost, persist publer_post_id + publer_job_id, advance FSM to Publishing
   - `publishInstagram(InstagramPost $draft)`: same pattern
   - `publishTiktok(TiktokPost $draft)`: same + adds `details.auto_add_music: true` to payload
   - `cancelDraft($model)`: deletePost via Publer + advance FSM to Cancelled
   - `buildPostPayload($platform, $draft)`: returns full Publer JSON shape per design § Architecture
   - `ensureMediaIdsCached($linkedinPost)`: checks `publer_media_ids` JSON cache; uploads via `/media/from-url` if empty; persists IDs
5. Implement `PublishViaPubler` polymorphic queued job: takes `$modelClass + $modelId`, calls `PublerSyncService::publish*` based on class. Idempotent skip if model not in awaiting_review.
6. Implement `PollPublerJobs` artisan command (signature `social-cross-post:poll-publer {--dry-run}`):
   - Scans rows in `publishing` state with `publer_job_id` IS NOT NULL
   - For each: GET /job_status/{publer_job_id}
   - status='complete' → fetch publish details → external_url + published_at populated → FSM Publishing → Published → fire Telegram notif
   - status='failed' → FSM Publishing → Failed (last_error from Publer) → fire Telegram failure notif
7. Add schedule entry: `Schedule::command('social-cross-post:poll-publer')->everyMinute()->withoutOverlapping(2);`
8. Add config to `social-cross-post.php`: `publer` section with `base_url`, `api_path` ('/api/v1'), `rate_limit_per_2min` (100), `max_retries` (3)
9. Add .env: `PUBLER_BASE_URL=https://app.publer.com`, `PUBLER_API_PATH=/api/v1`
10. Write 12+ tests: HTTP mocked happy paths for all PublerClient methods, error mapping (401/429/5xx), PublishViaPubler dispatch happy path, PollPublerJobs status transitions
11. Run tests
12. Suggested commit: `feat(cross-post): Publer integration — client + sync service + status poller cron`

**Verification:**
- [ ] PublerClient correctly auths with `Bearer-API` prefix
- [ ] Rate-limit aware (treats 429 as retry signal)
- [ ] PublerSyncService correctly assembles payload per platform with format/auto_add_music branching
- [ ] PublishViaPubler is idempotent (re-dispatching same draft when not in awaiting_review = no-op)
- [ ] PollPublerJobs polls only `publishing` rows, never reverses FSM (e.g. published → publishing reversal blocked)
- [ ] Media IDs cached on linkedin_posts.publer_media_ids — 2nd platform on same LinkedIn carousel reuses without re-upload

---

## Phase H'': Settings UI + account discovery flow

**Estimated time:** 2 hours

**Files:**
- Modify: `backend/app/Http/Controllers/Api/SettingsController.php`
- Modify: `backend/app/Providers/MailConfigOverrideProvider.php` (model — boot Publer config from settings, mirror pattern)
- Create: `backend/app/Providers/PublerConfigProvider.php`
- Modify: `backend/bootstrap/providers.php` (register new provider)
- Create: `backend/database/seeders/PublerSettingsSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Modify: `backend/routes/api.php` (4 new admin endpoints)
- Modify: `frontend/src/views/admin/AboutSettings.vue` (add Publer card)
- Test: `backend/tests/Feature/Admin/PublerSettingsControllerTest.php`

**Steps:**
1. Write failing test asserting `GET /api/admin/settings/publer` returns shape with `publer_api_key_configured: false` initially. Expected error: `Symfony\Component\Routing\Exception\RouteNotFoundException`
2. Run test, confirm it fails for the expected reason
3. Implement `PublerSettingsSeeder` — 6 keys via `firstOrCreate`: `publer_api_key=null`, `publer_enabled='false'`, `publer_facebook_account_id=null`, `publer_instagram_account_id=null`, `publer_tiktok_account_id=null`, `publer_last_account_sync_at=null`
4. Wire seeder into DatabaseSeeder
5. Extend `SettingsController` with 4 methods:
   - `getPublerSettings()` — returns settings, masks `publer_api_key` as `***SET***` if configured
   - `updatePublerSettings(Request $r)` — validates + encrypts api_key via `Crypt::encryptString` if non-empty, preserves on empty submit (mirror mail_password pattern)
   - `testPublerConnection()` — calls `PublerClient::me()` synchronously, returns success/error JSON
   - `syncPublerAccounts()` — calls `PublerClient::listAccounts()`, persists `publer_last_account_sync_at`, returns dropdown options
6. Add 4 routes to `routes/api.php` under `auth:sanctum`:
   - `GET /api/admin/settings/publer`
   - `PUT /api/admin/settings/publer`
   - `POST /api/admin/settings/publer/test`
   - `POST /api/admin/settings/publer/sync-accounts`
7. Add "Publer Integration" card to `AboutSettings.vue` between LinkedIn and Newsletter cards (per CLAUDE.md May 5 newsletter card precedent):
   - API key input (password type, preserves on empty submit)
   - Master enable toggle
   - 3 account dropdowns (lazy-loaded via Refresh Accounts button)
   - "Test Connection" button + result feedback
   - "Refresh Accounts" button
8. Write 8+ feature tests: GET masks api_key, PUT encrypts new key, PUT preserves existing key on empty submit, test endpoint pings Publer (mocked), sync-accounts persists timestamp, validation rejects malformed inputs
9. Run tests
10. Suggested commit: `feat(cross-post): Publer settings UI + account discovery flow`

**Design Deliverable:** Card layout follows existing AboutSettings.vue pattern (LinkedIn card precedent April 23, Newsletter SMTP card May 5). No new design tokens needed.

**Verification:**
- [ ] API key encrypted at rest (verify via DB inspection — value is base64-ish, not plaintext)
- [ ] API key masked as `***SET***` in API responses + `publer_api_key_configured: true` flag
- [ ] Empty submit preserves existing key (no clobber)
- [ ] Test Connection button works end-to-end against real Publer API (smoke test required)
- [ ] Refresh Accounts populates 3 dropdowns
- [ ] PublerConfigProvider boots config from settings (silent failure on DB unavailable, mirroring `MailConfigOverrideProvider`)

---

## Phase I: Frontend composables (3 platforms)

**Estimated time:** 60 minutes

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| I | 3 TanStack Query composables | n/a (logic only, no rendering) | Tests + types |

**Files:**
- Create: `frontend/src/composables/useFacebookDrafts.js`
- Create: `frontend/src/composables/useInstagramDrafts.js`
- Create: `frontend/src/composables/useTiktokDrafts.js`
- Test: `frontend/src/composables/useInstagramDrafts.test.mjs` (Node smoke test)

**Steps:**
1. Write failing Node smoke test asserting `useInstagramDraftsList()` query key shape `['instagram-drafts', { status, scope, page }]`. Expected error: `Cannot find module './useInstagramDrafts'`
2. Run test, confirm it fails for the expected reason
3. Implement `useInstagramDrafts.js` mirroring [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js):
   - `useInstagramDraftsList(filters)` — 30s staleTime, refetchOnMount:'always'
   - `useInstagramDraft(id)` — poll every 5s when status ∈ {pending_generation, generating, publishing}
   - 7 mutations: useUpdate, useRegenerate, useApprove, useCancel, useCalendar, etc.
4. Implement `useFacebookDrafts.js` (same pattern + format-aware caption editor logic — text vs carousel branches in mutations)
5. Implement `useTiktokDrafts.js` (same pattern)
6. Run smoke test
7. Suggested commit: `feat(cross-post): TanStack Query composables for FB + IG + TikTok drafts`

**Verification:**
- [ ] Query keys are platform-namespaced (no collision)
- [ ] Mutations correctly invalidate parent list query on success
- [ ] Poll-while-publishing fires every 5s when status='publishing', stops on terminal states
- [ ] Vite production build clean

---

## Phase J: Calendar views per platform

**Estimated time:** 60 minutes

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| J | 3 Vue calendar views | Mirror `LinkedInPostsCalendar.vue` aesthetic (no new tokens) | Smoke test + Vite build |

**Files:**
- Create: `frontend/src/views/admin/FacebookPostsCalendar.vue`
- Create: `frontend/src/views/admin/InstagramPostsCalendar.vue`
- Create: `frontend/src/views/admin/TiktokPostsCalendar.vue`
- Modify: `frontend/src/router/index.js` (3 new routes)

**Steps:**
1. Write failing smoke test asserting calendar renders 7×6 month grid. Expected error: `Cannot find module './FacebookPostsCalendar.vue'`
2. Run test, confirm it fails for the expected reason
3. Copy [`LinkedInPostsCalendar.vue`](frontend/src/views/admin/LinkedInPostsCalendar.vue) → 3 platform variants (FB, IG, TT)
4. Replace API endpoint per platform: `/admin/{platform}-drafts/calendar`
5. Wire `posting_time_rules` heatmap via `usePostingRules('{platform}')` composable (existing, multi-platform per May 6)
6. FB calendar: filter chip for format=text|carousel|all
7. Add 3 routes to `router/index.js`: `/admin/facebook-posts`, `/admin/instagram-posts`, `/admin/tiktok-posts`
8. Run smoke + Vite build
9. Suggested commit: `feat(cross-post): calendar views per platform with heatmap`

**Verification:**
- [ ] All 3 routes resolve, render without errors
- [ ] Heatmap shows distinct color tints per platform
- [ ] Day cells clickable → opens slide-in side panel with platform-specific drafts
- [ ] FB calendar's format filter works (filters by `format` field)

---

## Phase K: Queue views + shared helpers

**Estimated time:** 60 minutes

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| K | 3 queue views + shared helper module | Status pills + transition humanizer (mirror linkedinHelpers) | Helper unit test + smoke |

**Files:**
- Create: `frontend/src/views/admin/socialPlatformHelpers.js`
- Create: `frontend/src/views/admin/FacebookQueueList.vue`
- Create: `frontend/src/views/admin/InstagramQueueList.vue`
- Create: `frontend/src/views/admin/TiktokQueueList.vue`
- Modify: `frontend/src/router/index.js`
- Test: `frontend/src/views/admin/socialPlatformHelpers.test.mjs`

**Steps:**
1. Write failing test for `socialPlatformHelpers.STATUS_META.publishing` returning meta object with `label: 'PUBLISHING'`, `tone: 'cyan'`, `description: ...`. Expected error: `Cannot find module './socialPlatformHelpers'`
2. Run test, confirm it fails for the expected reason
3. Implement `socialPlatformHelpers.js`: copy structure from [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js), adapt STATUS_META to 7 cross-post FSM states. Reuse 18 inline SVG icons.
4. Add `generatingProgress(draft)` helper with format-aware baselines (text=20s, carousel=60s)
5. Copy [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue) → 3 variants. Drop depth_score column. Add format column (FB only).
6. Add 3 routes
7. Run helper tests + smoke
8. Suggested commit: `feat(cross-post): queue views + shared social platform helpers`

**Verification:**
- [ ] Status filter pills render per platform queue
- [ ] Inline approve action calls platform-specific composable
- [ ] FB queue shows format chip (text vs carousel)
- [ ] Generating progress % helper works with format baselines

---

## Phase L: Detail views per platform

**Estimated time:** 90 minutes (largest UI phase)

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| L | 3 detail views with editors + mockups | Hero panel + chip editor + mockup preview (mirror LinkedInDraftDetail) | Smoke + Vite + visual eyeball |

**Files:**
- Create: `frontend/src/views/admin/FacebookDraftDetail.vue`
- Create: `frontend/src/views/admin/InstagramDraftDetail.vue`
- Create: `frontend/src/views/admin/TiktokDraftDetail.vue`
- Modify: `frontend/src/router/index.js`

**Steps:**
1. Write failing test asserting detail view renders hero panel + caption editor + approve button. Expected error: `Cannot find module './FacebookDraftDetail.vue'`
2. Run test, confirm it fails for the expected reason
3. Copy [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) → 3 platform variants
4. Per-platform modifications:
   - **FB:** format-aware UI — text format shows just caption + link_url field; carousel format shows slide preview + caption
   - **IG:** caption max 2200 char counter; hashtag chip editor with HARD CAP 5 (Dec 2025); slide preview from `linkedinPost.carousel_slides[]`
   - **TikTok:** title 100-char counter; caption with first-150-char highlighting (green when keyword in first 150); slide preview; auto_add_music toggle (default ON)
5. All 3: drop "Mark as published" form (replaced by Publer auto-publish + poller); add `publer_post_id` deep-link to Publer dashboard when set
6. All 3: stale-error suppression (24h gate from `pipeline_state_log` per May 6 LinkedIn fix)
7. Add 3 routes
8. Run smoke + Vite build
9. Suggested commit: `feat(cross-post): detail views per platform with format-aware editors`

**Verification:**
- [ ] FB detail toggles UI based on format (text vs carousel)
- [ ] IG hashtag chip editor disables add at 5 chips with inline message
- [ ] TikTok caption shows first-150-char visual indicator
- [ ] Approve button gated when status ≠ awaiting_review
- [ ] Vite production build clean

---

## Phase M: Sidebar nav + posting-time research extension

**Estimated time:** 12 minutes

**Files:**
- Modify: `frontend/src/layouts/AdminLayout.vue`
- Modify: `backend/routes/console.php`

**Steps:**
1. Write failing test asserting AdminLayout renders 3 new nav sections "Facebook", "Instagram", "TikTok". Expected error: assertion fails — sections not present
2. Run test, confirm it fails for the expected reason
3. Add 3 nav sections in `AdminLayout.vue` under existing LinkedIn:
   - Facebook → Posts (calendar) + Queue
   - Instagram → Posts (calendar) + Queue
   - TikTok → Posts (calendar) + Queue
4. Add 3 schedule entries to `routes/console.php`:
   ```
   Schedule::command('posting-rules:research --platform=facebook')->cron('0 4 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
   Schedule::command('posting-rules:research --platform=instagram')->cron('0 5 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
   Schedule::command('posting-rules:research --platform=tiktok')->cron('0 6 1 */3 *')->timezone('Asia/Jakarta')->withoutOverlapping(15);
   ```
5. Run smoke
6. Suggested commit: `feat(cross-post): sidebar nav + quarterly posting-time research for FB+IG+TikTok`

**Verification:**
- [ ] Sidebar renders 4 social sections in order (LinkedIn, Facebook, Instagram, TikTok)
- [ ] Schedule entries verified via `php artisan schedule:list | grep posting-rules`
- [ ] Schedule offsets stagger (LI 03:00, FB 04:00, IG 05:00, TT 06:00 WIB)

---

## Phase N: Purge crons (3 platforms)

**Estimated time:** 18 minutes

**Files:**
- Create: `backend/app/Console/Commands/PurgeLowViralityFacebookDrafts.php`
- Create: `backend/app/Console/Commands/PurgeLowViralityInstagramDrafts.php`
- Create: `backend/app/Console/Commands/PurgeLowViralityTiktokDrafts.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/PurgeLowViralityFacebookDraftsTest.php` (extends pattern to 3)

**Steps:**
1. Write failing test asserting purge command soft-deletes drafts with `virality_score < 50` AND status not in terminal set. Expected error: `Error: Class "App\Console\Commands\PurgeLowViralityFacebookDrafts" not found`
2. Run test, confirm it fails for the expected reason
3. Implement 3 purge commands mirroring [`PurgeLowViralityLinkedInDrafts`](backend/app/Console/Commands/PurgeLowViralityLinkedInDrafts.php) — same safety rails (terminal state protection, idempotent, requires linked ContentIdea, --threshold + --dry-run flags)
4. Add 3 schedule entries: `Schedule::command('social-cross-post:purge-facebook')->dailyAt('04:30')->timezone('Asia/Jakarta');` (similar for IG at 04:45, TT at 05:00 — staggered)
5. Write 4+ tests per command: happy path, dry-run, terminal protection, orphan skip
6. Run tests
7. Suggested commit: `feat(cross-post): daily purge of low-virality FB + IG + TikTok drafts`

**Verification:**
- [ ] Idempotent (running 2x = same DB state)
- [ ] Terminal states protected (published + cancelled never touched)
- [ ] Schedule entries verified via `php artisan schedule:list`

---

## Phase O: End-to-end smoke test

**Estimated time:** 60 minutes (manual + automated)

**Files:**
- Create: `backend/tests/Feature/CrossPostPublerE2ETest.php`
- Document: VPS smoke runbook in plan file

**Steps:**
1. Write failing E2E test simulating: (a) LinkedIn carousel completes → (b) `social-cross-post:scan` runs → (c) 3 pending rows exist → (d) jobs execute (mocked SSH + mocked Publer) → (e) PublishViaPubler dispatches → (f) PollPublerJobs polls + advances → (g) status='published' on all 3. Expected error: at least one assertion fails until pipeline wired
2. Run test, confirm it fails for the expected reason
3. Wire each step until pipeline passes (integration verification, fix any wiring gaps)
4. Run test, confirm pass
5. Manual VPS smoke (after deploy):
   - Pick existing LinkedIn carousel post in `published` state
   - Run `php artisan social-cross-post:scan --dry-run` → preview
   - Run without --dry-run → verify 3 rows + 3 jobs dispatched
   - Watch queue: `journalctl -u portfolio-queue.service -f` → no SSH errors
   - Wait ~90s for plugin gen
   - Open `/admin/instagram-queue` → IG draft visible with caption
   - Click into IG detail → click Approve → row → publishing
   - Watch `social-cross-post:poll-publer` log → eventually published
   - Check Telegram → notif arrived
   - Check Publer dashboard + actual IG profile → post live
6. Suggested commit: `test(cross-post): E2E test + VPS smoke runbook documented`

**Verification:**
- [ ] E2E test passes
- [ ] Manual smoke completes for at least 1 platform end-to-end
- [ ] Telegram notification arrives within 5s of publish
- [ ] Real post visible on actual IG profile (or wherever test platform points)
- [ ] No MCP server leak (verify `ps -u claudesn | grep node | wc -l` stable across 5 sequential runs)

---

## Phase P: CLAUDE.md documentation update

**Estimated time:** 25 minutes

**Files:**
- Modify: `CLAUDE.md` (root project)

**Steps:**
1. Write failing assertion (manual): grep CLAUDE.md for "Cross-Post via Publer" section → not found yet
2. Run grep, confirm absent
3. Append new section "Cross-Post to Facebook + Instagram + TikTok via Publer (May 8, 2026)" mirroring depth of "LinkedIn Carousel Image Generation (April 27, 2026)" section. Cover:
   - Schema (3 tables: facebook_posts + instagram_posts + tiktok_posts + linkedin_posts.publer_media_ids cache)
   - FSM (7 states post-rename: pending_generation → generating → awaiting_review → publishing → published)
   - Plugin (`social-short-form-writer` v0.1, location, RAG sources, /instagram-gen reused for FB carousel)
   - Backend services + jobs + cron (PublerClient + PublerSyncService + PollPublerJobs + ScanLinkedInForCrossPost)
   - Admin endpoints (21 + 4 settings + ZIP DROPPED)
   - Frontend views + composables + sidebar nav
   - Telegram notif extension (6 new types)
   - Posting time integration (`posting-rules:research --platform={fb,ig,tt}`)
   - Format-aware routing (text → FB only, carousel → FB+IG+TT)
   - Music handling (TikTok auto_add_music, IG/FB no music)
   - Settings group `publer` (encrypted api_key, account IDs)
   - Phase 2 deferred items (if any)
4. Update "Last Updated" line at bottom of CLAUDE.md with this session summary
5. Mark all phase checkboxes [x] in this plan file
6. Suggested commit: `docs: add cross-post Publer section to CLAUDE.md`

**Verification:**
- [ ] CLAUDE.md grep finds new section
- [ ] All schema, routes, env vars, settings keys documented
- [ ] "Last Updated" line bumped
- [ ] All plan phase checkboxes marked complete

---

## Risk Register (cross-references design § Risk Register)

See [companion design doc § Risk Register](2026-05-08-cross-post-publer-integration.md) — risks unchanged from design phase. Key reminders during execution:

- 🚨 **API key already exposed in chat history** — operator must rotate after this session before production deploy
- ⚠️ Publer rate limit (100 req / 2 min) is generous for our volume but PollPublerJobs running every minute against many publishing rows could approach it — back off with `withoutOverlapping` if observed
- ⚠️ FB caption from /instagram-gen reuse may be too IG-flavored — operator can edit inline; if quality consistently bad, add `/facebook-gen` skill in v2

---

## Execution Handoff

**Option 1: Execute in this session**
> Use `/gaspol-execute` with this plan path. Sequential mode, per-phase checkpoints, TDD hard gate enforced.

**Option 2: Parallel execution (RECOMMENDED for this plan)**
> Use `/gaspol-parallel plan-phases`. 19 phases organize into 6 waves:
>
> - Wave 1 (sequential): A2 → A3 → A4 → B2
> - Wave 2 (parallel ×3): C, F, H'
> - Wave 3 (parallel ×4): D, E, G, H''
> - Wave 4 (parallel ×3): I, J, K
> - Wave 5 (parallel ×3): L, M, N
> - Wave 6 (sequential): O → P
>
> Watch out for: subagent fabrication risk (3 documented incidents in CLAUDE.md May 5+6 + this session's Phase A laravel-specialist). Recommend `Trust-but-verify` checkpoint after each subagent: `git status` + `Glob` + spot-check files exist before accepting "complete" report.

**Option 3: Separate session**
> Save plan, return tomorrow with fresh context. Plan file is self-contained — Architecture Context, Data Integration Map, per-phase TDD steps, verification criteria, risk register all included.

**Operator one-time setup steps (post-deploy):**
1. Rotate Publer API key in Publer dashboard (compromised in this chat history)
2. Enter new key in `/admin/about` "Publer Integration" card
3. Click "Test Connection" → confirm green
4. Click "Refresh Accounts" → 3 dropdowns populate
5. Pick default account per platform from dropdowns
6. Toggle `publer_enabled` to ON
7. Run `php artisan posting-rules:research --platform=facebook` (then `--platform=instagram`, `--platform=tiktok`) — once each, ~5 min each
8. Send test post via admin UI → verify end-to-end against actual FB/IG/TikTok

---

*Plan written 2026-05-08. Builds atop already-shipped Phase A (May 7 schema migrations) + Phase B (May 7 enums + models). Companion design doc covers full architecture rationale.*
