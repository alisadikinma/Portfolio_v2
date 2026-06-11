# Social Studio — merge Draft Posts + IG Repurpose into one menu

**Date:** 2026-06-11
**Status:** SHIPPED 2026-06-11 (Phases A–G via gaspol-execute) — 25 new tests green (37 backend + 18 frontend suites pass), NOT pushed (operator authorizes pushes)
**Type:** Frontend-heavy UI/UX refactor + small backend additions. No migrations, no FSM changes, no publish-engine changes.

---

## Design

### Problem

The `/admin/repurpose` ("IG Repurpose") panel is a bland job-status table:
- It shows only `status · mode · source URL slug · updated` — **the operator can't tell the topic/title** of the post.
- It **doesn't surface the grabbed IG images**, and there is **no original-vs-generated comparison** — the whole point of repurposing.
- It **overlaps conceptually with "Draft Posts"**: a repurpose *carousel* job produces a `LinkedInPost` draft, which is exactly what Draft Posts lists — so the same content lives behind two menus.

On top of that, two sibling menu labels are now stale:
- **"Draft Posts"** — too narrow once repurpose work merges in.
- **"SOSMED Posts"** — actually the *calendar* of scheduled/shipped posts; the name says nothing about that.

### Decisions (locked via brainstorm)

1. **Merge** "Draft Posts" + "IG Repurpose" into **one menu** — no separate menu.
2. **Union of one card list**: the merged list unions **two disjoint sources** (blog-origin drafts + IG-repurpose jobs), with source filter chips. No dedup.
3. **Detail for IG items = 2-pane Source ↔ Generated workspace**, reusing the existing sosmed-draft actions (no rebuild of the publish engine).
4. **Self-contained**: the operator sees original → generated → publish without bouncing to another menu. The backend publish engine (slot scheduler, cross-post, Publer) is unchanged — "self-contained" is about the UI surface only.
5. **Renames** (label-only; routes + redirects unchanged so bookmarks survive):
   - "Draft Posts" + "IG Repurpose" → **"Social Studio"**
   - "SOSMED Posts" (the calendar) → **"Content Calendar"**

Final **Social Media** sidebar section:

```
Content Engine      (ideas pipeline — unchanged)
Social Studio       (merged: draft queue + IG repurpose)     ← work happens here
Content Calendar    (was "SOSMED Posts" — scheduled & shipped) ← when/where it ships
```

### Architecture — the union list

One card list (`SocialStudio.vue`, new) unions two **disjoint** sources so nothing double-appears:

| Source | Composable | Filter applied |
|---|---|---|
| **IG** = all `RepurposeJob` rows (in-progress, failed, drafted, both modes) | `useRepurposeJobsList` | none — all jobs |
| **Blog** = `LinkedInPost` drafts | `useLinkedInDraftsList` (`scope=queue`) | **exclude repurpose-origin** drafts |

Disjoint by construction: a finalized IG-carousel job *is* a `LinkedInPost`, but it is reached **through its repurpose card** (which owns the comparison) and is **filtered out** of the blog-draft source via the existing `isRepurposeDraft(LinkedInPost)` predicate (backend adds an `exclude_repurpose=1` flag). → no client-side dedup.

**Unified card/row fields:** cover thumbnail · **topic title** · source badge (IG / Blog) · platform chips (LI·IG·TT·TH) · status pill · updated-relative.

- **Topic title** (fixes "gak tau topiknya"): `RepurposeJob.rewritten.title` → fallback `extracted.caption` first line → for blog cards, `postTitle(draft)`.
- **Filter chips:** `All · Blog · IG · Failed` (the approved mockup). Keep the existing status sort behaviour where it makes sense.
- Click → route by source: IG card → `admin-repurpose-detail`; Blog card → `admin-sosmed-draft-detail` (unchanged).

### Detail — IG card → 2-pane workspace (redesign `RepurposeJobDetail.vue`)

```
TITLE: "5 Cara AI Ubah Marketing"        from @handle · Carousel · #id

┌─ SOURCE · Original IG ──────────┬─ GENERATED · Your version ─────────┐
│ link to original post           │ generated title + caption          │
│ [captured slide carousel]       │ [rendered slide carousel]          │
│   (PRIVATE → fetchSlideObjectUrl│   (PUBLIC carousel_slides[].image_ │
│    blob, existing)              │    url — direct <img>)             │
│ Original caption                │ [Approve][Schedule][Publish now]   │
│ N claims extracted              │ [Regenerate]                       │
│ Fact-check: M corrected ✓ + src │   ← useLinkedInDrafts mutations on │
│                                 │     linkedin_post_id (reuse)       │
└─────────────────────────────────┴─────────────────────────────────────┘
Pipeline timeline (existing) · Failed → Retry step (existing)
```

- **Right pane reuses the sosmed draft, zero rebuild:** the detail additionally calls `useLinkedInDraft(job.linkedin_post_id)` and renders that draft's `carousel_slides[].image_url` + caption + the same Approve / Schedule / Publish / Regenerate mutations already used by `LinkedInDraftDetail.vue` / `LinkedInQueueList.vue`.
- **Asymmetry to remember:** left-pane original slides are **private** (blob fetch via `fetchSlideObjectUrl`); right-pane generated slides are **public** storage URLs (`storage/app/public/linkedin-carousel/…`) → plain `<img :src>`.
- **In-progress IG job (no draft yet):** right pane shows pipeline progress (timeline + current step) instead of slides.
- **Blog-mode IG job:** right pane shows `rewritten` preview + "Open in Content Engine →" (`content_idea_id`); no carousel slides (blog mode produces a `ContentIdea`, not a draft).
- **Blog-source cards** keep routing to the existing `admin-sosmed-draft-detail` — untouched.

### Data Integration Map

| UI element | Data source | Exists? | Change needed |
|---|---|---|---|
| Card title (IG) | `RepurposeJob.rewritten.title` / `extracted.caption` | ✅ data present | add `title` to `RepurposeJobController::compact()` |
| Card cover (IG) | first captured slide | ✅ `/admin/repurpose/{id}/slide/0` | mark `has_cover`/`slide_count` in compact |
| Card list (Blog) | `useLinkedInDraftsList` | ✅ | add `exclude_repurpose` query flag (uses `isRepurposeDraft`) |
| Generated slides (detail) | linked `LinkedInPost.carousel_slides[].image_url` | ✅ public | fetch via `useLinkedInDraft(linkedin_post_id)` in detail |
| Right-pane actions | `useLinkedInDrafts` mutations (approve/schedule/publish/regenerate) | ✅ | reuse as-is on `linkedin_post_id` |
| Original slides (detail) | `RepurposeJob` private storage | ✅ blob | reuse `fetchSlideObjectUrl` |
| Source badge / platform chips | `mode`, `linkedin_post_id`, cross-post siblings | ✅ partial | derive in helper; surface sibling presence if cheap |

### Backend scope (small)

1. `RepurposeJobController` — `compact()` + `show()` add a derived **`title`** (rewritten → caption fallback) and a **cover hint** (`slide_count` already in `show`; add to `compact`). Optionally surface linked-draft status for the card pill.
2. `LinkedInDraftController` index — accept **`exclude_repurpose=1`** and filter via the existing `isRepurposeDraft` predicate so repurpose-origin drafts don't double-appear in the blog source.
3. Sidebar + router **label renames** only (paths + legacy redirects unchanged).

No migrations · no FSM changes · no publish-engine touch · no new publish path.

### Frontend scope

- New `SocialStudio.vue` (the union list) → becomes the target of the merged menu item.
- Redesign `RepurposeJobDetail.vue` → 2-pane Source↔Generated workspace (reuse `useLinkedInDraft` + `useLinkedInDrafts` mutations + existing slide-blob loader).
- Extend `repurposeHelpers.js` with: `derivedTitle(job)`, `sourceBadge(...)`, plus reuse of `statusTone/statusLabel`.
- Sidebar (`AdminLayout.vue`): collapse Draft Posts + IG Repurpose rows into one "Social Studio" link; rename "SOSMED Posts" → "Content Calendar".
- Router: point the merged menu at a clean path (e.g. `/admin/social-studio`) with `/admin/draft-posts` + `/admin/repurpose` redirecting to it; keep `admin-repurpose-detail` + `admin-sosmed-draft-detail`. Retire/redirect `RepurposeJobsList.vue`.
- Keep the **cockpit dark aesthetic** already in `LinkedInQueueList.vue` (neutral-950, amber/cyan accents, mono uppercase labels, hairline dividers, inline-SVG `ICON` set — no emoji slop; the IG/Blog source badges use small inline glyphs or text chips, not emoji).

### Out of scope / YAGNI

- No early `LinkedInPost` creation for in-progress repurpose jobs (rejected merge model B) — keeps the FSM untouched.
- No tabs-as-two-lists (rejected model C).
- No changes to the publish engine, slot scheduler, cross-post fan-out, or Publer.
- "Content Calendar" gets a **label change only** this round; its internals stay as-is.

### Risks / things to verify in planning

- Union **sort/pagination** across two client-side lists (repurpose ≤100 + drafts ≤100). Likely fine; confirm a sensible merged sort (e.g. updated_at DESC) and that both lists' poll intervals coexist.
- Confirm `isRepurposeDraft` is reachable/efficient at list scope (it joins RepurposeJob linkage or `ContentIdea.source='instagram'`).
- Back-navigation origin sentinel (`linkedin:detail:origin`) currently uses `'queue'`/`'posts'`; the merged detail back-buttons must return to Social Studio.
- Published repurpose carousels also appear in Content Calendar (shipped) — acceptable; the Studio card reflects terminal status.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Collapse the "Draft Posts" and "IG Repurpose" admin menus into a single **Social Studio** card list (a union of blog-origin drafts + IG-repurpose jobs, source-filtered), redesign the repurpose detail into a 2-pane Original↔Generated workspace that reuses the existing draft actions, and relabel "SOSMED Posts" → "Content Calendar". This removes the menu overlap, surfaces the topic title + grabbed IG images + original-vs-generated comparison the operator asked for, with no migrations, no FSM changes, and no publish-engine changes.

### Architecture Context (from CLAUDE.md + code read)

- **Drafts queue:** `LinkedInDraftController::index` (`scope=queue` → `LinkedInPostStatus::queueStatuses()`), consumed by `useLinkedInDraftsList` (TanStack, 30s stale + `refetchOnMount:'always'`). List rows already eager-load `post.translations(title)`, `post.featured_image`, `post.contentIdea.virality_score`. `postTitle(draft)` helper exists in `useLinkedInDrafts.js`.
- **Repurpose jobs:** `RepurposeJobController::index/show/retry/slide`, consumed by `useRepurposeJobsList` / `useRepurposeJob` / `fetchSlideObjectUrl`. Original slides are **private** (blob fetch); job carries `rewritten{title,body,excerpt}`, `extracted{caption,claims}`, `research{verdicts,corrected_count}`, `linkedin_post_id`, `anchor_post_id`, `content_idea_id`, `pipeline_state_log`, `mode`.
- **Repurpose-origin predicate:** `LinkedInGenerationService::isRepurposeDraft(LinkedInPost)` — runs `exists()` queries (RepurposeJob `linkedin_post_id`=draft.id OR `anchor_post_id`=draft.post_id; OR `ContentIdea` `result_post_id`=draft.post_id AND `source='instagram'`). **Per-instance → do NOT loop over the list** (N+1). The `exclude_repurpose` filter must be expressed as **query-level subquery exclusions** that mirror this exact logic.
- **Generated slides (detail right pane):** the linked `LinkedInPost.show()` returns `carousel_slides[].image_url` (PUBLIC `storage/app/public/linkedin-carousel/…`) + cross-post siblings + status. Reachable via `useLinkedInDraft(linkedin_post_id)`. Mutations (approve/cancel/regenerate + schedule/publish) live in `useLinkedInDrafts.js` + `LinkedInDraftDetail.vue`.
- **Sidebar:** `AdminLayout.vue` "Social Media" section: Content Engine · Draft Posts (`/admin/draft-posts`) · SOSMED Posts (`/admin/sosmed-posts`, = `LinkedInPostsCalendar`) · IG Repurpose (`/admin/repurpose`).
- **Router:** `admin-draft-posts` (LinkedInQueueList), `admin-repurpose` (RepurposeJobsList), `admin-repurpose-detail`, `admin-sosmed-draft-detail` (LinkedInDraftDetail), `admin-sosmed-posts`. Legacy `linkedin-*` paths already redirect. Back-nav sentinel `sessionStorage['linkedin:detail:origin']` = `'queue'`/`'posts'`.

### Tech Stack

Vue 3.5 `<script setup>` + TanStack Vue Query + Tailwind 4 (cockpit dark: neutral-950, amber/cyan, mono uppercase labels, inline-SVG `ICON` set — no emoji). Laravel 12 + Sanctum. **No PHP on dev Mac** → backend tests authored for CI / Docker (`serversideup/php:8.2-cli` sqlite); frontend = pure helpers tested with `node --test` `.mjs` + `npm run build` for component compile.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Blog source rows | `LinkedInPost` queue, repurpose-excluded | `useLinkedInDraftsList({scope:'queue',exclude_repurpose:1})` | Partial | Add `exclude_repurpose` to controller + composable param |
| IG source rows | all `RepurposeJob` | `useRepurposeJobsList({per_page:100})` | Yes | Use existing; bump per_page |
| IG card title | `rewritten.title`→`extracted.caption` | `RepurposeJobController::compact()` | No | Add derived `title` + `slide_count`/`has_cover` to compact |
| Blog card title | `post.translations.title` | `postTitle(draft)` | Yes | Reuse |
| IG card cover | first captured slide (private) | `fetchSlideObjectUrl(jobId, 0)` | Yes | Reuse (lazy per card) |
| Blog card cover | `post.featured_image` | list payload | Yes | Reuse |
| Unified card shape / filter / sort | — | `socialStudioHelpers.js` (new, pure) | No | Create + node-test |
| Detail right pane (generated) | linked draft `carousel_slides[].image_url` + caption + status | `useLinkedInDraft(linkedin_post_id)` | Yes | Reuse |
| Detail right pane actions | approve/cancel/regenerate/schedule/publish | `useLinkedInDrafts.js` mutations | Yes | Reuse on `linkedin_post_id` |
| Detail left pane (source) | captured slides + caption + claims + verdicts | existing `RepurposeJobDetail` data | Yes | Keep |
| Menu / route names | — | `AdminLayout.vue` + `router/index.js` | Yes | Rename/merge labels + add route + redirects |

---

### Phase A — Backend: `exclude_repurpose` filter on draft list

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php`
- Test: `backend/tests/Feature/LinkedInDraftListExcludeRepurposeTest.php`

**Steps:**
1. Write failing test for `exclude_repurpose=1` on `GET /admin/linkedin-drafts?scope=queue`. Expected error: `Failed asserting that … contains/excludes …` (the filter param does not exist yet, so repurpose-origin drafts still appear). Cases: (a) draft referenced by `RepurposeJob.linkedin_post_id` → excluded; (b) draft whose `post_id` = a `RepurposeJob.anchor_post_id` → excluded; (c) draft whose post links `ContentIdea{source:'instagram'}` → excluded; (d) **a normal draft with `post_id = null` → KEPT** (guards the `NULL NOT IN` trap); (e) `exclude_repurpose` absent → all included.
2. Run test in Docker (`serversideup/php:8.2-cli` sqlite), confirm it fails for the expected reason.
3. Implement: add `'exclude_repurpose' => ['nullable','boolean']` to `$validated`; when `$request->boolean('exclude_repurpose')`, apply query-level exclusions mirroring `isRepurposeDraft` — `whereNotIn('id', RepurposeJob::whereNotNull('linkedin_post_id')->select('linkedin_post_id'))` AND a nested `where(fn $q => $q->whereNull('post_id')->orWhere(fn $q2 => $q2->whereNotIn('post_id', RepurposeJob::whereNotNull('anchor_post_id')->select('anchor_post_id'))->whereNotIn('post_id', ContentIdea::where('source','instagram')->whereNotNull('result_post_id')->select('result_post_id'))))`. No per-row `isRepurposeDraft` call.
4. Run tests, confirm all pass.
5. Commit: `feat(linkedin): exclude_repurpose query filter on draft list`

**Verification:**
- [ ] All 5 test cases pass in Docker sqlite
- [ ] Null-`post_id` draft is KEPT (no `NULL NOT IN` regression)
- [ ] No per-row `isRepurposeDraft()` invocation (grep the diff)
- [ ] `php -l` clean; existing `LinkedInDraft*` feature tests still green
- [ ] No placeholder/TODO in new code

---

### Phase B — Backend: derived `title` + cover hint on repurpose list

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php`
- Test: `backend/tests/Feature/RepurposeJobAdminControllerTest.php` (extend) or new `RepurposeJobListTitleTest.php`

**Steps:**
1. Write failing test for `compact()` output. Expected error: `Undefined array key "title"`/assertion failure — `index` rows have no `title`/`slide_count`. Cases: title = `rewritten.title` when present; falls back to first line of `extracted.caption`; `null` when neither; `slide_count` present; `has_cover` true when `slide_count > 0`.
2. Run test in Docker, confirm fail.
3. Implement: add private `derivedTitle(RepurposeJob): ?string` (rewritten.title → first non-empty line of extracted.caption trimmed to ~120 chars → null). Add `title`, `slide_count` (reuse `slideFiles($j)->count()`), `has_cover` to `compact()`. Add `title` to `show()` payload too.
4. Run tests, confirm pass.
5. Commit: `feat(repurpose): derive title + cover hint for list cards`

**Verification:**
- [ ] Title fallback chain covered by tests (rewritten → caption → null)
- [ ] `slide_count`/`has_cover` present on list rows
- [ ] `php -l` clean; existing `RepurposeJobAdminControllerTest` (10 cases) still green
- [ ] No N+1 beyond the existing `slideFiles` disk read per row (acceptable, already done in `show`)

---

### Phase C — Frontend: unified card helper (pure, node-tested)

**Estimated time:** 12 min

**Files:**
- Create: `frontend/src/views/admin/socialStudioHelpers.js`
- Test: `frontend/src/views/admin/socialStudioHelpers.test.mjs`

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| C | pure adapter `toCard` + filter + merge-sort | n/a (pure logic) | node --test |

**Steps:**
1. Write failing test for `socialStudioHelpers`. Expected error: `Cannot find module './socialStudioHelpers.js'`. Cases: `toCard(igJob)` → `{kind:'ig', id, title, sourceBadge:'ig', status, updatedAt, route:{name:'admin-repurpose-detail',params:{id}}}`; `toCard(blogDraft)` (reusing `postTitle`) → `{kind:'blog', route:{name:'admin-sosmed-draft-detail'}}`; `derivedTitle` fallback for IG when backend `title` null; `matchesFilter(card,'ig'|'blog'|'failed'|'all')`; `mergeAndSort(igCards, blogCards)` → single array sorted by `updatedAt` DESC.
2. Run `node --test src/views/admin/socialStudioHelpers.test.mjs`, confirm fail.
3. Implement pure functions. Reuse `statusLabel`/`statusTone` from `repurposeHelpers.js` for IG and `effectiveStatusMeta` shape mapping for blog (import or re-derive a minimal `{short,mood}`); keep platform-chip derivation minimal (LI always; IG/TT/TH from job mode = carousel ⇒ siblings expected).
4. Run tests, confirm pass.
5. Commit: `feat(social-studio): unified card adapter + filter/merge helpers`

**Verification:**
- [ ] `node --test` green (≥8 assertions)
- [ ] `toCard` shapes identical keys for both kinds
- [ ] No framework imports in the helper (pure, tree-test-safe)
- [ ] No placeholder/TODO

---

### Phase D — Frontend: `SocialStudio.vue` union list

**Estimated time:** 15 min

**Files:**
- Create: `frontend/src/views/admin/SocialStudio.vue`
- Modify: `frontend/src/composables/useLinkedInDrafts.js` (thread `exclude_repurpose` into the list params), `frontend/src/composables/useRepurposeJobs.js` (allow `per_page:100`)

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| D | union list view | reuse cockpit aesthetic (neutral-950, amber/cyan, mono labels, inline ICON; IG/Blog badge = small glyph/text chip, no emoji) | npm run build + helper tests |

**Steps:**
1. Write failing test (extend `socialStudioHelpers.test.mjs`) for the filter-count + merge used by the view's computed (`countsBySource`, applied filter result ordering). Expected error: assertion fail / missing export `countsBySource`.
2. Run `node --test`, confirm fail.
3. Implement `countsBySource`/filter glue in the helper; then build `SocialStudio.vue`: fetch `useRepurposeJobsList({per_page:100})` + `useLinkedInDraftsList({scope:'queue',exclude_repurpose:1,per_page:100})`, map both via `toCard`, `mergeAndSort`, render filter chips (All/Blog/IG/Failed) + card rows (cover, title, source badge, platform chips, status pill, updated). IG cover = lazy `fetchSlideObjectUrl(id,0)` with object-URL revoke on unmount; blog cover = `featured_image`. On card click: `sessionStorage.setItem('linkedin:detail:origin','studio')` then `router.push(card.route)`.
4. Run `node --test` (green) + `npm run build` (clean compile).
5. Commit: `feat(social-studio): union card list view (blog drafts + IG repurpose)`

**Verification:**
- [ ] `npm run build` clean (new chunk emits)
- [ ] Helper tests green incl new count/filter cases
- [ ] IG covers revoke object URLs on unmount (no leak)
- [ ] Card click sets origin sentinel `'studio'`
- [ ] No emoji in card chrome (inline SVG/text only); no placeholder/TODO

---

### Phase E — Frontend: `RepurposeJobDetail.vue` → 2-pane workspace

**Estimated time:** 15 min

**Files:**
- Modify: `frontend/src/views/admin/RepurposeJobDetail.vue`
- Modify: `frontend/src/views/admin/repurposeHelpers.js` (add pure `rightPaneMode(job)` → `'generated'|'in_progress'|'blog'`)
- Test: `frontend/src/views/admin/repurposeHelpers.test.mjs` (extend)

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| E | 2-pane Source↔Generated detail | left=private slides+claims+fact-check, right=public generated slides+actions; reduced-motion safe; reuse cockpit tokens | npm run build + helper tests |

**Steps:**
1. Write failing test for `rightPaneMode(job)`. Expected error: missing export `rightPaneMode`. Cases: `mode='carousel'` + `linkedin_post_id` set → `'generated'`; `mode='carousel'` + no `linkedin_post_id` (still processing) → `'in_progress'`; `mode='blog'` → `'blog'`.
2. Run `node --test`, confirm fail.
3. Implement `rightPaneMode`; then refactor the detail into 2 columns. Left = existing captured-slide carousel + original caption + extracted claims + fact-check verdicts (unchanged data). Right per `rightPaneMode`: `'generated'` → `useLinkedInDraft(job.linkedin_post_id)`, render `carousel_slides[].image_url` (public `<img>`) + generated caption + Approve/Cancel/Regenerate from `useLinkedInDrafts` mutations (schedule/publish via the same actions the draft detail uses) on `linkedin_post_id`; `'in_progress'` → pipeline timeline + current step (reuse existing timeline block); `'blog'` → `rewritten` preview + `router-link` to Content Engine via `content_idea_id`. Prominent header **title** (`job.title` from Phase B). Back button → `admin-social-studio`. Keep Failed→Retry block.
4. Run `node --test` (green) + `npm run build` (clean).
5. Commit: `feat(repurpose): 2-pane source↔generated workspace detail`

**Verification:**
- [ ] `rightPaneMode` cases green in `node --test`
- [ ] `npm run build` clean
- [ ] Right pane reuses `useLinkedInDraft` + `useLinkedInDrafts` mutations (no duplicated publish logic — grep diff)
- [ ] Private (blob) left vs public (image_url) right rendered correctly
- [ ] Back button targets `admin-social-studio`; reduced-motion respected
- [ ] No placeholder/TODO

---

### Phase F — Sidebar + router: merge menu, rename, redirects, back-nav

**Estimated time:** 12 min

**Files:**
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/layouts/AdminLayout.vue`
- Modify: `frontend/src/views/admin/LinkedInDraftDetail.vue` (map back-origin `'studio'` → `admin-social-studio`)
- Test: `frontend/src/router/socialStudioRoutes.test.mjs` (source-assertion smoke)

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| F | menu merge + renames + redirects | one "Social Studio" link; "SOSMED Posts"→"Content Calendar"; keep section icons | npm run build + route smoke |

**Steps:**
1. Write failing test asserting router source contains a `social-studio` route → `SocialStudio.vue`, and `/admin/draft-posts` + `/admin/repurpose` redirect to `admin-social-studio`, and AdminLayout no longer renders the literal `IG Repurpose` / `Draft Posts` labels but does render `Social Studio` + `Content Calendar`. Expected error: assertions fail (routes/labels not present yet).
2. Run `node --test`, confirm fail.
3. Implement: router — add `{ path:'/admin/social-studio', name:'admin-social-studio', component: SocialStudio.vue }`; convert `/admin/draft-posts` + `/admin/repurpose` to `redirect:{name:'admin-social-studio'}`; keep `admin-repurpose-detail` + `admin-sosmed-draft-detail`; update `meta.title`s. AdminLayout — replace the Draft Posts + IG Repurpose `<router-link>`s with a single "Social Studio" link (`/admin/social-studio`); rename "SOSMED Posts" label → "Content Calendar". LinkedInDraftDetail — extend back-origin map so `'studio'` → `admin-social-studio` (keep `'queue'`/`'posts'` working).
4. Run `node --test` (green) + `npm run build` (clean); grep repo for stale `IG Repurpose`/`Draft Posts` label refs.
5. Commit: `feat(admin): merge menu into Social Studio; rename SOSMED Posts → Content Calendar`

**Verification:**
- [ ] Route smoke test green; `/admin/draft-posts` + `/admin/repurpose` redirect to Social Studio
- [ ] Sidebar shows one Social Studio link + Content Calendar (no Draft Posts / IG Repurpose labels)
- [ ] Blog-draft detail back button returns to Social Studio when opened from it
- [ ] `npm run build` clean; no dangling imports to retired `RepurposeJobsList.vue` (component file may remain unrouted)
- [ ] No placeholder/TODO

---

### Phase G — Docs sync

**Estimated time:** 6 min

**Files:** Modify: root `CLAUDE.md` (Page Sections / admin routes + "Last Updated" changelog), this plan's status.

**Steps:**
1. Update CLAUDE.md: rename menu/route references (Draft Posts + IG Repurpose → Social Studio union; SOSMED Posts → Content Calendar), note `exclude_repurpose` flag + `RepurposeJobController` title/cover additions, 2-pane detail.
2. Commit: `docs: sync CLAUDE.md — Social Studio menu merge`

**Verification:**
- [ ] CLAUDE.md reflects new menu names, routes, `exclude_repurpose` flag, 2-pane detail
- [ ] "Last Updated" changelog entry added

---

### Dependency / parallelism

- **A** and **B** are independent backend phases (can run in parallel).
- **C** depends on nothing (pure helper) — can run alongside A/B.
- **D** depends on A (exclude flag) + B (title) + C (helper).
- **E** depends on B (title) + existing `useLinkedInDraft`.
- **F** depends on D (SocialStudio.vue exists).
- **G** last.
Suggested order for a single session: A → B → C → D → E → F → G. For `gaspol-parallel`: wave 1 = {A, B, C}; wave 2 = {D, E}; wave 3 = {F}; then G.

### Execution handoff

- **In-session:** start Phase A with `gaspol-execute` (per-phase checkpoints + TDD hard gate).
- **Parallel:** `gaspol-parallel` plan-phases — wave 1 {A,B,C} are file-disjoint.
- **NOT pushed** until operator authorizes (project git policy). Backend tests run on CI / Docker; frontend `node --test` + `npm run build` locally.
