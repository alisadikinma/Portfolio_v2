> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# SEO/GEO Follow-ups (Neil Patel framework review)

## Goal

Close the four highest-value gaps between the current blog/GEO stack and Neil Patel's GEO+SEO findings, surfaced in the codebase review on 2026-06-09. Three gaps were real (publish-and-forget / no freshness loop; no review signal; format/word-count tuning); the fourth (AI-referral analytics) is **deferred** by operator decision. The work spans two repos: Portfolio_v2 (Laravel backend + Vue frontend) for the freshness loop and review schema, and the separate `article-content-writer` plugin for the prompt-level word-count + format tuning.

After this ships:
1. **Freshness loop** — a weekly cron flags published posts whose freshness anchor is older than 90 days, sends ONE Telegram digest, and surfaces a "Needs refresh" badge + filter in the admin Posts list. Operator marks a post reviewed (or re-runs the pipeline manually) — no automatic AI regeneration. Directly addresses Neil Patel's #1 "stop" item (publish-and-forget) and the freshness=91%-citable signal.
2. **Review signal** — the homepage entity graph gains a standalone `Organization` node carrying `aggregateRating` + `review[]` built from `testimonials.star_rating`, consumed by LLM/GEO crawlers (Neil Patel: reviews lift Google +108% AND ChatGPT +256% simultaneously). Reviews are genuinely displayed same-page (TestimonialsCarousel), so the markup is legitimate.
3. **Word-count sweet spot** — plugin outline/scoring guidance tuned to the 1,501–1,750-word citation peak.
4. **Listicle/step-by-step format bias** — plugin format-decision biased toward numbered-listicle / step-by-step structure for how-to / comparison / tools topics (Neil Patel: Lists 48% + Guides 17% = top AI-citation formats).

## Out of Scope (explicitly deferred)

- **AI-referral analytics (review item #3)** — DEFERRED by operator. No analytics infra exists (no GA4/gtag/Plausible). Will be a separate follow-up; not built here. Do not add analytics in this plan.
- **Auto re-running the article pipeline on stale posts** — operator chose flag-for-review, NOT auto-regeneration. The cron must never dispatch `/article-prep`/`/article-write`. Manual refresh stays the existing operator-initiated path.
- **Rewriting static `frontend/index.html` Person/Org block** — keep blast radius small; the new Organization+aggregateRating node is injected by the SSR layer (dynamic, because ratings change), not baked into the static shell.

## Architecture Context (from CLAUDE.md + verified in code)

**Schema / SSR layer (item #2):**
- [`SchemaGraphBuilder`](../../backend/app/Services/Seo/SchemaGraphBuilder.php) — DB-free JSON-LD builders. Methods: `person()`, `organization()` (currently minimal: `@type`+name+url, no `@id`, no rating), `webSite()`, `blogPosting()`, `breadcrumbList()`, `faqPage()`, `itemList()`, `collectionPage()`. Consts `SITE_URL`, `ORG_NAME`, `ORG_URL`.
- [`SpaPrerenderController::home()`](../../backend/app/Http/Controllers/SpaPrerenderController.php) (line ~41) — injects only `webSite()` today; Person+FAQ are static in `index.html` so it deliberately does NOT re-inject them. Already caches composed HTML 1h via `Cache::remember` keyed `seo_html:home:{lang}`.
- [`SeoHtmlComposer::compose()`](../../backend/app/Services/Seo/SeoHtmlComposer.php) — splices a `jsonLd` array into `<head>` before `</head>` (substring splice, graceful no-op on missing anchor).
- Static `frontend/index.html` bakes: 1 Person (with nested worksFor=INDUSIA.ai + 2 alumniOf Organizations), 1 FAQPage. No standalone Organization `@id`, no aggregateRating.
- [`Testimonial`](../../backend/app/Models/Testimonial.php) — `star_rating` (tinyInteger 1–5, default 5, indexed), `is_active`, `source` (linkedin/direct/video), `client_name`, `testimonial_text` (longText HTML), `company_name`, `job_title`. Homepage `TestimonialsCarousel` renders `source=linkedin` testimonials.
- Home cache purge: [`Post::boot()`](../../backend/app/Models/Post.php) saved/deleted → `SpaPrerenderController::purgeForPost()` forgets `seo_html:home:*`. Testimonials are NOT yet wired to purge home — must add (rating changes must bust the home cache).

**Freshness loop (item #1):**
- DB-driven scheduler: [`scheduled_commands`](../../backend/database/migrations/2026_05_09_000001_create_scheduled_commands_table.php) table + [`DynamicScheduleRegistrar`](../../backend/app/Services/DynamicScheduleRegistrar.php) + [`ScheduledCommandSeeder`](../../backend/database/seeders/ScheduledCommandSeeder.php) (idempotent `firstOrCreate`). New crons are added as seeder rows (category enum: `content_engine`/`linkedin`/`system`/...), NOT `routes/console.php`. Audit columns auto-populated by `ScheduledTask*` listeners in `AppServiceProvider`.
- [`Post`](../../backend/app/Models/Post.php) — `published` scope, `published_at`, `updated_at`, `translations()` hasMany (title/content live in `post_translations`). SoftDeletes.
- [`TelegramNotificationService`](../../backend/app/Services/TelegramNotificationService.php) — system-level alert precedent: `sendGeminigenCircuitOpen()` (NOT ContentIdea-keyed). New digest method follows this shape. Toggles seeded by [`TelegramSettingsSeeder`](../../backend/database/seeders/TelegramSettingsSeeder.php) (group `telegram`, idempotent).
- Admin Posts: [`PostsList.vue`](../../frontend/src/views/admin/PostsList.vue) is where operator manages blog. Admin composables use TanStack Query 30s staleTime + `refetchOnMount:'always'` (see `useScheduler.js`, `useNewsletterAdmin.js`).
- Admin post routes live under `/api/admin/posts` (PostController) — new stale endpoints attach there or under content-engine.

**Plugin (items #4, #5)** — separate repo `D:\Projects\claude-plugin\article-content-writer` (= `/Users/alisadikin/Drive-D/Projects/claude-plugin/article-content-writer`):
- [`references/seo-rules-engine.md`](../../../claude-plugin/article-content-writer/references/seo-rules-engine.md) — §8 GEO/AEO rules, §9 GEO Score 5 metrics. Already has answer-first, citability, FAQ, entity clarity, freshness.
- [`references/frameworks-library.md`](../../../claude-plugin/article-content-writer/references/frameworks-library.md) — narrative frameworks (StoryBrand/AIDA/PAS/etc.) + a "Step-by-step buyer journey → AIDA" mapping table. No explicit listicle-vs-narrative FORMAT decision.
- [`references/quality-gate.md`](../../../claude-plugin/article-content-writer/references/quality-gate.md) — Gate 9 "Benefit-First + Actionable Depth" already requires 150–250 words per numbered point (What/How/Example/Outcome).
- [`skills/article-strategy-outline/SKILL.md`](../../../claude-plugin/article-content-writer/skills/article-strategy-outline/SKILL.md) + [`skills/article-prep/SKILL.md`](../../../claude-plugin/article-content-writer/skills/article-prep/SKILL.md) — "Template determines: target word count". No 1501–1750 band.
- [`skills/article-write/SKILL.md`](../../../claude-plugin/article-content-writer/skills/article-write/SKILL.md) — example output `word_count: 2100`.
- Compiled refs (`references/compiled/refs-*.md`) are the runtime artifacts; operator runs the compile script + deploys to VPS after a source ref change. Plugin has its own git history + version bump.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2, MySQL 8. PHPUnit (project convention — NOT Pest). **No PHP runtime on the dev Mac** (per CLAUDE.md) → tests are authored TDD-first, `php -l` syntax-checked locally, and run on CI / VPS. Each backend phase's "RED" is verified structurally (test references a not-yet-existing symbol) + on CI.
- **Frontend:** Vue 3.5 `<script setup>`, Pinia, TanStack Vue Query, Tailwind 4. Node `.test.mjs` smoke tests + `npm run build`.
- **Plugin:** Markdown reference edits (no unit-test runtime for refs). "RED/GREEN" = grep-assertion before/after edit; plugin's own `npm test` (TS schema/orchestrator suite) must stay green; operator recompiles refs + deploys.

## Data Integration Map

| Feature | Data Source | Hook/API/Method | Exists? | Action |
|---|---|---|---|---|
| AggregateRating values (home graph) | `testimonials` where `is_active=true` (count + AVG `star_rating`) | `Testimonial::query()` in `SpaPrerenderController::home()` | Yes (table+col) | Query in controller, pass to builder |
| Review[] items (home graph) | top-N `testimonials` (client_name, testimonial_text, star_rating) | same query, limited + ordered | Yes | Use existing columns |
| Organization+rating JSON-LD node | computed from above | new `SchemaGraphBuilder::organizationWithRating(array $rating, array $reviews)` | No | Create real method |
| Home cache bust on testimonial change | `Testimonial::boot()` saved/deleted | `SpaPrerenderController::purgeForPost()`-style `Cache::forget('seo_html:home:*')` | No (post-only today) | Add `Testimonial::boot()` + `purgeHome()` |
| Stale-post detection | `posts.published_at`, new `content_reviewed_at`, new `stale_notified_at` | new `Post::scopeStale()` | Partial (cols new) | Migration + scope |
| Stale flagging cron | Post::stale() query | new `content:flag-stale-posts` artisan | No | Create real command |
| Stale Telegram digest | stale posts collection | new `TelegramNotificationService::sendStaleContentDigest()` | No | Create (mirror `sendGeminigenCircuitOpen`) |
| Telegram toggle | `settings` group `telegram` key `telegram_notify_stale_content` | `TelegramSettingsSeeder` | No | Add seeded row |
| Cron registration | `scheduled_commands` row | `ScheduledCommandSeeder` | No | Add idempotent row |
| Stale list (admin) | `Post::stale()` | new `GET /api/admin/content-engine/stale-posts` | No | Create endpoint |
| Mark-reviewed action | sets `posts.content_reviewed_at=now()` | new `POST /api/admin/content-engine/posts/{id}/mark-reviewed` | No | Create endpoint |
| Admin stale badge/filter | stale endpoint | new `useStalePosts.js` composable + PostsList.vue | No | Create (TanStack pattern) |
| Word-count band | plugin refs | edit `article-strategy-outline` + `quality-gate.md` | n/a (docs) | Edit + grep-verify |
| Format bias | plugin refs | edit `frameworks-library.md` + `seo-rules-engine.md` + `article-write` | n/a (docs) | Edit + grep-verify |

---

## Phase A — Review/AggregateRating schema (item #2, backend)

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Services/Seo/SchemaGraphBuilder.php`
- Modify: `backend/app/Http/Controllers/SpaPrerenderController.php`
- Modify: `backend/app/Models/Testimonial.php`
- Test: `backend/tests/Unit/SchemaGraphBuilderRatingTest.php`
- Test: `backend/tests/Feature/SpaPrerenderHomeRatingTest.php`

**Steps:**
1. Write failing test for `SchemaGraphBuilder::organizationWithRating()`. Expected error: `Error: Call to undefined method App\Services\Seo\SchemaGraphBuilder::organizationWithRating()`. Assert returned node has `@type=Organization`, stable `@id` (e.g. `https://alisadikinma.com/#organization`), `aggregateRating` with `ratingValue`/`reviewCount`/`bestRating=5`/`worstRating=1`, and `review[]` each with `@type=Review`, `author.@type=Person`+name, `reviewBody`, `reviewRating.ratingValue`. Assert: empty input → returns a no-rating Organization node (no `aggregateRating` key, no fabricated values).
2. Run test, confirm it fails for the expected reason (method undefined). [CI / `php -l` local]
3. Implement `organizationWithRating(array $rating, array $reviews): array` — pure, DB-free; strips HTML from `reviewBody` (testimonial_text is HTML) via `strip_tags` + truncate ~280 chars; omits `aggregateRating` entirely when `reviewCount===0` (never emit zero/fake ratings).
4. Write failing test `SpaPrerenderHomeRatingTest`: seed 3 active testimonials (ratings 5,4,5), GET `/`, assert response `<head>` contains a JSON-LD `Organization` with `aggregateRating.ratingValue` ≈ 4.7 and `reviewCount=3`. Expected error pre-impl: assertion fails (no Organization node in home output). Use the SSR fixture-shell pattern from existing `SpaPrerenderTest` (`SEO_SPA_SHELL_PATH`).
5. Implement in `SpaPrerenderController::home()`: query `Testimonial::where('is_active',true)` → compute `['ratingValue'=>round(avg,1),'reviewCount'=>count]` + top-5 reviews (latest by `sort_order`/`id`), add `$this->schema->organizationWithRating($rating,$reviews)` to the injected `jsonLd` array. Guard: if zero active testimonials, inject plain `organization()` (unchanged behavior).
6. Add `Testimonial::boot()` `saved`/`deleted` → `SpaPrerenderController::purgeHome()` (new static that forgets `seo_html:home:*` for each supported lang). Mirrors `Post::boot()` purge.
7. Run tests, confirm pass [CI]. `php -l` all 3 modified files locally.
8. Commit: `feat(seo): aggregateRating + review[] on homepage Organization node from testimonials`

**Verification:**
- [ ] `php -l` clean on all 3 modified files (local)
- [ ] `organizationWithRating()` returns real computed values from passed data; empty input → no `aggregateRating` key (no fabrication)
- [ ] Home SSR output contains one `Organization` node with `aggregateRating` matching seeded testimonials (CI)
- [ ] Editing a testimonial busts `seo_html:home:*` cache (CI assertion or manual `Cache::has` check)
- [ ] No placeholder/TODO comments in new code
- [ ] reviewBody is HTML-stripped + length-capped

---

## Phase B — Stale-post detection (item #1, backend core)

**Estimated time:** 12 min

**Files:**
- Create: `backend/database/migrations/2026_06_09_000001_add_freshness_columns_to_posts.php`
- Modify: `backend/app/Models/Post.php`
- Create: `backend/app/Console/Commands/FlagStalePosts.php`
- Test: `backend/tests/Unit/PostStaleScopeTest.php`
- Test: `backend/tests/Feature/FlagStalePostsCommandTest.php`

**Steps:**
1. Write failing test `PostStaleScopeTest` for `Post::scopeStale($q, 90)`. Expected error: `BadMethodCallException: Call to undefined method ... scopeStale()` (or `stale()`). Assert: a published post with anchor (`max(published_at, content_reviewed_at)`) 100 days ago IS returned; one 30 days ago is NOT; one with `content_reviewed_at`=now (but published 200d ago) is NOT; an unpublished/draft post 200d ago is NOT.
2. Run test, confirm fail (scope undefined). [CI]
3. Migration: add nullable `content_reviewed_at` (timestamp) + `stale_notified_at` (timestamp) to `posts`. Both nullable, no backfill (legacy posts → anchor falls back to `published_at`).
4. Implement `Post::scopeStale(Builder $q, int $days=90)` — `published()` AND `COALESCE(content_reviewed_at, published_at) < now()-days`. Add both columns to `$fillable` + `$casts` (datetime).
5. Write failing test `FlagStalePostsCommandTest`: seed 2 stale + 1 fresh post; run `content:flag-stale-posts --days=90`; assert exit 0, the 2 stale posts get `stale_notified_at` set, fresh post untouched, and `--dry-run` mutates nothing. Expected error pre-impl: command not registered.
6. Implement `FlagStalePosts` command signature `content:flag-stale-posts {--days=90} {--dry-run} {--limit=50}`. Logic: `Post::stale($days)->where(fn re-alert suppression: stale_notified_at IS NULL OR stale_notified_at < now()-30 days)->limit()->get()`. On non-dry-run: set `stale_notified_at=now()` on each (does NOT touch `updated_at` semantics for detection — use `saveQuietly` or direct update to avoid bumping freshness anchor falsely). Output a count + list. Telegram dispatch is Phase C (this phase just flags + logs).
7. Run tests, confirm pass [CI]. `php -l` clean.
8. Commit: `feat(content): stale-post detection scope + content:flag-stale-posts command`

**Verification:**
- [ ] `php -l` clean (migration, model, command)
- [ ] `scopeStale` correctly includes/excludes per anchor + published state (CI)
- [ ] Command flags only un-notified stale posts; `--dry-run` is side-effect-free (CI)
- [ ] `stale_notified_at` update does NOT alter the freshness anchor (no false "fresh" flip)
- [ ] No placeholder/TODO comments

---

## Phase C — Stale digest (Telegram) + cron registration (item #1, ops)

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Services/TelegramNotificationService.php`
- Modify: `backend/app/Console/Commands/FlagStalePosts.php`
- Modify: `backend/database/seeders/TelegramSettingsSeeder.php`
- Modify: `backend/database/seeders/ScheduledCommandSeeder.php`
- Test: `backend/tests/Feature/StaleContentTelegramTest.php`

**Steps:**
1. Write failing test `StaleContentTelegramTest`: fake HTTP, enable `telegram_enabled` + `telegram_notify_stale_content`, run `content:flag-stale-posts` with 2 stale posts, assert exactly ONE Telegram sendMessage call containing both post titles (digest, not per-post spam). Assert: when `telegram_notify_stale_content=false`, zero sends. Expected error pre-impl: `Error: Call to undefined method ...::sendStaleContentDigest()`.
2. Run test, confirm fail. [CI]
3. Implement `TelegramNotificationService::sendStaleContentDigest(array $posts): void` — system-level (mirror `sendGeminigenCircuitOpen`), gated by `telegram_enabled` + `telegram_notify_stale_content`, composes one message (count + up to N titles + "review in /admin/posts?filter=stale"). Returns early/no-op when disabled or empty.
4. Wire `FlagStalePosts` to call `sendStaleContentDigest($flagged)` once after flagging (skip on `--dry-run`).
5. Add `telegram_notify_stale_content` (default `'true'`) to `TelegramSettingsSeeder` via `firstOrCreate` (idempotent).
6. Add `ScheduledCommandSeeder` row: signature `content:flag-stale-posts`, arguments `['--days=90']`, category `content_engine`, cron `0 6 * * 1` (Mon 06:00 WIB), timezone `Asia/Jakarta`, `enabled=true`, `without_overlapping_minutes=30`. Idempotent `firstOrCreate`.
7. Run tests, confirm pass [CI]. `php -l` clean.
8. Commit: `feat(content): weekly stale-content Telegram digest + DB scheduler row`

**Verification:**
- [ ] `php -l` clean on all modified files
- [ ] Exactly one digest message for N stale posts; zero when toggle off (CI)
- [ ] Seeders idempotent (re-seed yields no dupes) — assert `firstOrCreate` used
- [ ] Scheduler row registers via `DynamicScheduleRegistrar` (appears in `/admin/settings?tab=scheduler` after seed)
- [ ] No placeholder/TODO comments

---

## Phase D — Admin stale surface (item #1, endpoints + frontend)

**Estimated time:** 14 min

**Files:**
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (or `PostController` — pick the one owning admin post reads; see step 0)
- Test: `backend/tests/Feature/StalePostsEndpointTest.php`
- Create: `frontend/src/composables/useStalePosts.js`
- Modify: `frontend/src/views/admin/PostsList.vue`
- Test: `frontend/src/views/admin/postsStale.test.mjs`

**Steps:**
0. (Read, not code) Confirm which controller owns `GET /api/admin/posts` and attach the two new routes there for consistency (`grep -n "admin/posts" backend/routes/api.php`). Use that controller; do NOT create a new one if a natural home exists.
1. Write failing test `StalePostsEndpointTest`: auth:sanctum user, seed 2 stale + 1 fresh, `GET /api/admin/content-engine/stale-posts` → 200 with the 2 stale (id, title, published_at, days_stale); `POST /api/admin/content-engine/posts/{id}/mark-reviewed` → sets `content_reviewed_at=now()` and the post no longer appears stale. Assert 401 without auth. Expected error pre-impl: 404 route not found.
2. Run test, confirm fail. [CI]
3. Implement the two endpoints (FSM-free; mark-reviewed just stamps `content_reviewed_at`). Reuse `Post::stale()` scope + eager-load `translations` (avoid N+1). Response shape mirrors existing admin list resources.
4. Write failing Node smoke test `postsStale.test.mjs`: import the composable module, assert it exports `useStalePosts` + `useMarkReviewed` and the query key/endpoint strings match the backend routes. (Pure structural smoke — no DOM.) Expected error: module not found.
5. Implement `useStalePosts.js` (TanStack Query, 30s staleTime + `refetchOnMount:'always'`, mirror `useScheduler.js`): `useStalePosts()` query + `useMarkReviewed()` mutation invalidating the stale + posts queries.
6. Wire `PostsList.vue`: a "Needs refresh (N)" filter chip / badge sourced from `useStalePosts`, a per-row amber "Stale · 142d" pill when the post id is in the stale set, and a "Mark reviewed" row action calling the mutation (optimistic). No new design tokens — reuse existing amber/pill utilities (match SchedulerSettings placeholder-row styling).
7. Run frontend smoke test + `npm run build`; run backend test [CI]. `php -l` clean.
8. Commit: `feat(admin): stale-posts endpoint + mark-reviewed + PostsList needs-refresh badge`

**Verification:**
- [ ] Backend: endpoints return real `Post::stale()` data, mark-reviewed clears staleness, 401 unauth (CI)
- [ ] `npm run build` clean; smoke test green
- [ ] Badge/filter driven by real composable data (no hardcoded counts)
- [ ] Design: amber pill reuses existing tokens (no hardcoded hex), has loading/empty states, mark-reviewed has disabled/pending state
- [ ] No placeholder/TODO comments

---

## Phase E — Word-count band 1,500–2,000 + image-count floor 5–7 (item #4, plugin)

**Estimated time:** 10 min — **docs/config kind** (gaspol-execute auto-review SKIP)

**Decisions locked (operator, 2026-06-09):**
- **Word band = 1,500–2,000 words** (compromise: Neil Patel cites 1,501–1,750 as the AI-citation visibility peak; the NotebookLM research found high-performers average ~2,000+. Band spans both — advisory, not a hard cap.)
- **Image count = 5–7 total** (1 cover + 4–6 inline), ≈ 1 image per 250–300 words. (Research-backed ideal for this length is denser at ~1 per 150–175 words → 8–10; operator chose the **moderate** 5–7 to balance GeminiGen cost against the Phase G all-images-must-succeed gate. Caption + alt-text mandatory on every image — captioned figures aid AI passage extraction.)

**Files (in `article-content-writer` repo):**
- Modify: `skills/article-strategy-outline/SKILL.md` (word band + image floor + decoupling guardrail)
- Modify: `skills/article-prep/SKILL.md` (if it carries the same "Template determines word count" line)
- Modify: `references/quality-gate.md` (note the citation-peak band + image floor in the depth gate)

**Image-count coupling (verified 2026-06-09 — MUST preserve):** image count is **section-driven, not word-driven**. `article-strategy-outline` plans `image_count = 1 cover + inline per section where image_concept != null` (`outline.image_count`); backend `ImageGenerationService` (line ~225) purely loops the plugin-authored `image_prompts[]` — there is NO word→image formula. The 5–7 number is a **floor/target the outline must MEET by ensuring enough sections carry an `image_concept`**, NOT a word-derived formula bolted onto the backend. Lowering the word band could tempt the AI to hit it by **deleting sections** → fewer images AND fewer answer-first passages (a GEO regression). Phase E must DECOUPLE these: reach the word band by tightening prose density per section, never by cutting sections; and ensure the planned `image_count` lands in 5–7 by assigning `image_concept` to the cover + each substantive H2.

**Steps:**
1. Grep-assert RED: `grep -ni "1,500\|2,000\|citation peak\|image floor\|5–7\|5-7" skills/article-strategy-outline/SKILL.md` → confirm ABSENT (no current guidance). This is the "failing check".
2. Edit the outline skill: where target word count is set per template, add explicit guidance — "Default target band **1,500–2,000 words** (AI-citation visibility band; peak ~1,500–1,750, high-performers ~2,000). Go longer ONLY when topic depth genuinely requires it; never pad with fluff — depth over length. Listicles/guides may run longer if each numbered point earns its 150–250 words (Gate 9)." Keep it advisory, not a hard cap.
3. Add the **image-count target** in the same outline step: "Plan **5–7 images total** = 1 cover + 4–6 inline (≈ 1 image per 250–300 words). Assign an `image_concept` to the cover and to each substantive H2 section until `image_count` lands in 5–7. Every image MUST have a caption + alt text (captioned figures aid AI passage extraction). Do not exceed 7 unless a genuinely visual topic warrants it — each image is a generation cost and ALL must render before the article compiles (Phase G gate)."
4. **Add the decoupling guardrail** right next to the band: "Hit the WORD band by tightening prose density per section — **do NOT reduce the planned section count or `image_count` to fit the word target.** Section count (and therefore image count + the number of answer-first extractable passages) is set by the template/topic, independent of the word band. More H2 sections = more citable passages; keep them." Confirm `image_count` is still computed from sections (now targeting the 5–7 floor), not back-derived from word count.
5. Mirror the word-band + image-floor one-liner in `article-prep` if it owns the word-count instruction; add a short note in `quality-gate.md` near the depth/Gate-9 section referencing the band + the 5–7 image floor.
6. Grep-assert GREEN: `grep -n "1,500\|2,000" skills/article-strategy-outline/SKILL.md` → present, AND `grep -ni "image_count\|5–7\|5-7\|section count" skills/article-strategy-outline/SKILL.md` shows the image-floor + decoupling note.
7. Run plugin test suite to confirm no TS/schema regression: `npm test` (refs are markdown; suite must stay green).
8. Commit (in plugin repo): `feat(refs): word-count band 1500-2000 + 5-7 image floor + image-count decoupling guardrail`

**Verification:**
- [ ] Grep confirms the 1,500–2,000 band text present in the outline skill (+ prep if applicable)
- [ ] Image-count target present: 5–7 total (1 cover + 4–6 inline), ≈1 per 250–300 words, caption+alt mandatory
- [ ] Decoupling guardrail present: word band hit via prose density, NOT by cutting sections/`image_count`
- [ ] `outline.image_count` remains section-derived, now targeting the 5–7 floor (a 1,500-word and a 2,000-word article on a 5-section topic still plan the same image count)
- [ ] Guidance is advisory (no hard cap that would truncate legit long-form)
- [ ] Plugin `npm test` green (no schema/orchestrator regression)
- [ ] No contradiction with existing Gate 9 depth rules

---

## Phase F — Listicle/step-by-step format bias, backed by a researched RAG file (item #5, plugin)

**Estimated time:** ~20 min active (deep research runs async, 15–30 min in background) — **docs/research kind** (gaspol-execute auto-review SKIP)

**Approach change (operator request):** the format-decision guidance is NOT written from memory. It is grounded in a fresh deep-research pass synthesized via **NotebookLM** into a **new source-cited RAG file**, which then becomes the canonical reference that `frameworks-library.md` + `article-write` point to.

**Research provenance (executed during planning, 2026-06-09):**
- NotebookLM notebook `ac75d86d-d085-45a2-b436-03ae6dc53adc` — "GEO Format Research — AI Citation by Content Structure (2024-2026)". **68+ sources imported** incl. primary papers (Princeton GEO, arXiv GEO), the "AI search loves listicles: 25,000 URLs" citation study, Evertune research, Yext/Pixelmojo GEO guides, Gemini SEA market-share stats.
- Research #1 — deep web research (query `/tmp/geo_format_research_query.txt`): AI-citation-by-format, answer-first/passage extractability, word-count sweet spot, freshness, entity/stats, third-party signals, platform diversification.
- **Research #2 — deep web research (query `/tmp/geo_image_density_query.txt`): IDEAL IMAGE COUNT per article + image-to-text ratio for a 1,500–1,750-word piece (cover+inline), engagement/share data, diminishing-returns upper bound, AI/GEO angle on captioned figures.** This produces a concrete, source-cited **image-count rule** that becomes part of the RAG file AND wires into the outline's `image_count` planning.
- Synthesis: `notebooklm generate report --format briefing-doc` (or `--format custom` with a RAG-shaping prompt) → downloaded markdown.

**Files (in `article-content-writer` repo):**
- **Create:** `references/geo-format-citation-research.md` — NEW RAG file (synthesized + source-cited from NotebookLM). The single source of truth for format/structure decisions.
- Modify: `references/frameworks-library.md` (add a FORMAT-decision layer above the narrative-framework choice, pointing to the new RAG file)
- Modify: `references/seo-rules-engine.md` (cross-reference the RAG file under §8 GEO rules)
- Modify: `skills/article-write/SKILL.md` (enforce numbered-listicle structure when format=listicle/guide)
- Modify: the refs-compile config/script so the new RAG file is bundled into the relevant `refs-*.md` (e.g. `refs-prep.md`/`refs-write.md`) that gets injected at pipeline time.

**Steps:**
1. **(done during planning)** Kick off NotebookLM deep research → import sources → confirm ≥10 sources `ready`.
2. Generate synthesis report from the notebook: `notebooklm generate report --format briefing-doc -n ac75d86d... --wait` (or a `--format custom` prompt that asks for: a format-citation-rate table, answer-first/passage rules, word-count band, freshness cadence, entity/stats rules, each claim tagged with source+year). Download to a temp file.
3. **Human-in-the-loop quality gate (Iron Law / guardrail):** review the NotebookLM output for hallucinated stats or unsourced percentages. Drop any claim NotebookLM cannot attribute to a named source+year. NotebookLM is grounded on imported sources but still verify the citations resolve — do NOT ship a number we can't trace. Show the operator the draft RAG file before committing.
4. Author `references/geo-format-citation-research.md` from the verified synthesis: structured for a writer-AI to consume — (a) Format → citation-rate table with sources, (b) "when to use which format" decision rules (how-to/comparison/tools → numbered listicle/step-by-step; opinion/story/announcement → narrative), (c) answer-first + passage-citability rules, (d) word-count band + freshness cadence, (e) a Sources section listing every cited study + URL + year. Every quantified claim carries an inline `(Source, Year)`.
5. Grep-assert RED on `frameworks-library.md`: `grep -ni "format decision\|geo-format-citation-research" references/frameworks-library.md` → confirm ABSENT.
6. Edit `frameworks-library.md`: add a short "Format Decision (run BEFORE framework choice)" block that DEFERS to `geo-format-citation-research.md` for the rates/rationale — for how-to/comparison/tools/"best X"/troubleshooting topics default to numbered listicle or step-by-step; narrative frameworks (StoryBrand/PAS) layer ON TOP for intro + transitions, not replacing the scannable body.
7. Cross-reference the RAG file in `seo-rules-engine.md` §8 (one bullet, ties format to §8.2 passage citability).
8. Edit `article-write/SKILL.md`: when format=listicle/guide, each numbered H2/H3 leads with the answer-first 40–60-word stat paragraph (§8.1) AND is a standalone passage.
9. Wire the new RAG file into the refs-compile script so it ships in the injected bundle (otherwise the research never reaches the pipeline at runtime).
10. Grep-assert GREEN + run plugin `npm test` (green).
11. Commit (in plugin repo): `feat(refs): researched GEO format-citation RAG + format-decision bias`

**Verification:**
- [ ] `references/geo-format-citation-research.md` exists, every quantified claim has an inline `(Source, Year)` + a Sources section with resolvable URLs
- [ ] No unsourced/hallucinated statistic survived the quality gate (operator-reviewed before commit)
- [ ] `frameworks-library.md` + `seo-rules-engine.md` reference the RAG file; format-decision block present
- [ ] The RAG file is included by the refs-compile script (bundled into the runtime `refs-*.md`)
- [ ] article-write enforces answer-first per numbered item (consistency with §8.1/§8.2)
- [ ] Plugin `npm test` green

---

## Phase G — Hard image-completion gate before compile/publish (correctness fix, backend)

**Estimated time:** 16 min — **bugfix/new-feature kind** (gaspol-execute auto-review REVIEW)

**The bug (verified 2026-06-09):** the Content Engine advances an idea to `images_ready` (→ publishable, → blog compiled with `<figure>` insertion) when every segment is `done` OR `failed`, as long as ≥1 is `done`:
- [`ImageGenerationService.php:478`](../../backend/app/Services/ImageGenerationService.php#L478) — `every(['done','failed'])` → `transitionTo(ImagesReady)`.
- [`ProcessPendingImages.php:249`](../../backend/app/Console/Commands/ProcessPendingImages.php#L249) — advance rule counts terminally-`failed` as advance-eligible.
- [`ContentIdeaController::approveAndPublish`](../../backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php) — publish allowed from `images_ready` (no per-segment failure check).

Result: a blog with 3/4 images can auto-advance and publish with a broken/missing image. **Operator requirement: never proceed to compile/publish while ANY image failed — retry until all succeed.**

**Design decision (FLAG TO USER BEFORE CODING — Iron Law fork):** "retry forever" is unsafe for *deterministic* failures (safety refusal that survives sanitization, persistent quota, GeminiGen circuit-open) — a tight infinite loop would burn quota and never converge. Proposed responsible interpretation: **persistent retry with exponential backoff, gated by the existing GeminiGen circuit breaker; the idea NEVER advances to `images_ready` while any segment is non-`done`; after M total attempts (env-tunable, default e.g. 6) OR a safety-hard-fail OR prolonged circuit-open → ESCALATE to operator (Telegram + admin badge) and HOLD at `generating_images` — never auto-skip, never auto-advance.** Operator then resolves via existing retry / replace-variation / manual-upload / deliberate-skip endpoints. This delivers "don't ship broken images" without an unbounded loop. **Confirm this interpretation (or stricter) before implementing.** `skipped` remains advance-eligible because it is an explicit human decision, not an automatic proceed-with-failure.

**Files:**
- Modify: `backend/app/Services/ImageGenerationService.php` (gate at ~478 + retry loop in `handleSegmentFailure`)
- Modify: `backend/app/Console/Commands/ProcessPendingImages.php` (advance rule ~249 + re-dispatch failed segments)
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (`approveAndPublish` defense-in-depth block)
- Modify: `backend/app/Jobs/RetryImageSegmentJob.php` + `config` (raise/replace the hard max-2 cap with the backoff-until-M + circuit-aware policy)
- Modify: `backend/app/Services/TelegramNotificationService.php` (escalation alert on hold)
- Test: `backend/tests/Feature/ImageCompletionGateTest.php`
- Test: `backend/tests/Unit/SegmentRetryPolicyTest.php`

**Steps:**
1. Write failing test `ImageCompletionGateTest::test_failed_segment_blocks_images_ready`: idea in `generating_images` with segments [done, done, failed]; run the webhook-completion path; assert idea status STAYS `generating_images` (NOT `images_ready`). Expected pre-fix: assertion fails (current code flips to `images_ready`).
2. Run test, confirm fail (demonstrates the bug). [CI]
3. Fix the gate at `ImageGenerationService:478`: advance to `images_ready` only when `every(status IN ['done','skipped'])` AND `anyDone`. A `failed` segment blocks. Mirror the same change at `ProcessPendingImages:249`.
4. Write failing test `test_all_done_advances` + `test_skipped_counts_as_resolved`: [done,done,done] → `images_ready`; [done,skipped,done] → `images_ready`. Confirm gate still advances on legit-complete.
5. Write failing test `SegmentRetryPolicyTest`: a failed non-safety segment re-dispatches with backoff while attempts < M; at attempts == M it stops re-dispatching and triggers escalation (assert Telegram fake called once + idea still `generating_images`, NOT advanced, NOT skipped). Safety-class failure → routes to safety-rewrite path (existing), not infinite plain retry. Circuit-open → no dispatch (existing `assertCircuitClosed`), retry deferred.
6. Implement the retry policy in `handleSegmentFailure` + `RetryImageSegmentJob`: replace the hard max-2 terminal cap with persistent backoff up to `IMAGE_SEGMENT_MAX_ATTEMPTS` (env, default 6); on exhaustion set a segment flag (e.g. `needs_operator`) + dispatch `TelegramNotificationService::sendImageGenerationStalled($idea, $failedSegments)`; never flip the idea forward. Reuse the GeminiGen circuit breaker guard already in `queue()`.
7. Add defense-in-depth in `approveAndPublish`: if any segment status is `failed`/`needs_operator` (not `done`/`skipped`), return 422 with an operator-actionable message — block compile even if status was force-set. Test `test_publish_blocked_when_segment_failed`.
8. Add `TelegramNotificationService::sendImageGenerationStalled()` (system-level, mirror `sendGeminigenCircuitOpen`) + a `telegram_notify_image_stalled` toggle in `TelegramSettingsSeeder`.
9. Run all tests, confirm pass [CI]. `php -l` clean.
10. Commit: `fix(content): hard-gate compile/publish on full image completion + persistent retry`

**Verification:**
- [ ] A `failed` segment blocks `images_ready` at BOTH gate sites (CI)
- [ ] Legit-complete ([done,done,done] / [done,skipped]) still advances (CI)
- [ ] Failed segment re-dispatches with backoff up to M, then escalates (Telegram) + HOLDS — never auto-advances, never auto-skips (CI)
- [ ] `approveAndPublish` returns 422 when any segment unresolved (CI)
- [ ] Circuit-open defers retry (no quota burn during outage)
- [ ] `php -l` clean; no placeholder/TODO comments

## Operator Post-Deploy Steps

1. **Portfolio_v2 (Phases A–D):** standard `git push origin main` → CI `deploy.sh` runs `migrate --force` (adds `content_reviewed_at`/`stale_notified_at`) + idempotent seeders (Telegram toggle + scheduler row). No manual step.
2. **Verify cron:** `/admin/settings?tab=scheduler` → confirm `content:flag-stale-posts` row exists + enabled. Optionally `php artisan content:flag-stale-posts --dry-run` on VPS to preview the first stale batch.
3. **Telegram:** ensure `telegram_enabled=true` + `telegram_notify_stale_content=true` in `/admin/settings` (toggle defaults on, but bot token/chat_id must already be configured).
4. **Plugin (Phases E–F):** in `article-content-writer` repo — `git pull` to the new commits, `npm run compile-refs` (regenerate `refs-*.md` bundles), deploy/symlink the refreshed bundles to the VPS (`/home/claudesn/refs-prep.md` etc.), `php artisan config:cache` not needed (refs are file-based, picked up next pipeline run). Bump plugin version per its convention.
5. **#3 analytics** remains a deferred follow-up — track separately.

## Execution Handoff

- **Phases A–D** (Portfolio_v2) are mostly sequential within the freshness loop (B→C→D depend in order); **A is independent** of B–D. **E and F** (plugin) are independent of everything and of each other.
- Parallel-friendly grouping if desired: {A}, {B→C→D}, {E}, {F}. But default is sequential per-phase checkpoints via gaspol-execute.
