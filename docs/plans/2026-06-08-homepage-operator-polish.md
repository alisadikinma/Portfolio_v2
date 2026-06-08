# Homepage "The Operator" — Polish Pass (5 fixes)

> Status: Design approved-pending · Created 2026-06-08 · Follows the June 8 "The Operator" homepage redesign.
> Hybrid file — `## Implementation Plan` appended by gaspol-plan later.

## Design

Five operator-requested fixes to the identity-led homepage. Decisions locked via brainstorm
(2026-06-08). Image generation uses the **geminigen MCP** (`generate_image`, image-only — no
video tool exposed; Nano Banana Pro, face-ref via `refs`). Face reference for all renders:
`https://alisadikinma.com/uploads/about/1776545803_creator-face.png` (from `settings.about.profile_photo`).

### Task 1 — Hero visual (HeroOperator.vue)
- **Decision:** Image now, video rendered by operator later.
- Generate a cinematic hero still via geminigen (16:9, 4K, `Portrait Cinematic`, face ref) →
  save to `frontend/public/videos/hero-poster.jpg`. Set `posterSrc` so the hero shows an
  intentional still immediately (and as the reduced-motion / slow-conn fallback).
- Keep `mp4Src = '/videos/hero-bg.mp4'` as the interim loop behind the poster.
- Author a tight text-to-video prompt/brief (camera move, 5–8s loop, lighting) so the operator
  can render the final clip on geminigen.ai web. When delivered, swap `webmSrc` / `mp4Src` /
  `posterSrc` to the final assets (placeholders already documented in the component).

### Task 2 — WHO I AM HTML leak (WhoIAm.vue)
- **Root cause:** `about.bio` is HTML; `{{ liveBio }}` escapes it → tags render as text.
- **Fix:** add `cleanBio` computed that sanitizes (allowlist: `p, strong, em, br`; strip
  `script/style/on*` + all other tags/attrs) and render via `v-html` with scoped styling for
  `p` spacing + `strong`/`em` emphasis. Content is admin-only (low XSS surface) but sanitize
  defensively. `showLiveBio` gate logic unchanged.

### Task 3 — Proof bento imagery (ReceiptsBento.vue)
- **Decision:** all 6 tiles get imagery.
- Generate 6 cinematic backgrounds via geminigen → `frontend/public/images/proof/`:
  - `demoday.png` (champion, face ref — Ali on stage / trophy, gold key light)
  - `impact.png` ($318K — abstract finance/impact, gold)
  - `products.png` (56+ products — product/grid motif)
  - `years.png` (17 years — factory-floor → code timeline)
  - `countries.png` (16 countries — globe/world map, cyan)
  - `accuracy.png` (≥95% — edge-AI inspection / PCB macro, cyan)
- Render each tile with a low-opacity background image + strong dark gradient overlay so the
  stat + text stay fully legible (Dark Cinema, gold/cyan accent). Reduced-motion safe (static).

### Task 4 — International stages real photos (InternationalStages.vue)
- **Decision:** keep the 6 curated cards + narrative, add a real photo per card from the
  matching award gallery; clicking opens the full gallery.
- Stage → award/gallery map (live `/api/awards/{id}/galleries`):
  | Stage | award_id | gallery_id | photos |
  |---|---|---|---|
  | Global AI Demo Day 2026 (Bengaluru) | 13 | 20 | 7 |
  | Alibaba eFounders (Hangzhou) | 10 | 12 | 11 |
  | Google Startup Grind (Silicon Valley) | 11 | 13 | 6 |
  | Telkomsel NextDev (Jakarta) | 7 | 9 | 5 |
  | Fenox Startup World Cup | 8 | 10 | 4 |
  | IDBYTE Connected (Jakarta) | 9 | 11 | 7 |
- Add `awardId` to each stage object. New composable `useStageGalleries()` fetches the 6 award
  galleries in parallel (TanStack-cached, lazy/below-fold), maps `galleries[0].items[0]` → card
  cover photo + retains full items[] for the modal.
- Card gains a cover photo (aspect-video, object-cover, dark gradient bottom for the location
  chip). Click → reuse existing `BaseGalleryModal` (same pattern as About.vue) with all event
  photos. **Fallback:** if a gallery fails/empty, card renders text-only as today (no broken img).

### Task 5 — Header menu → scroll to sections (TheNavigation.vue + Home.vue + router)
- **Decision:** menu = homepage section anchors; on other pages route home then scroll.
- Add stable `id`s to each Home.vue `.snap-section` wrapper: `who-i-am`, `what-i-solve`,
  `proof`, `stages`, `work`, `writing`, `contact` (hero stays top).
- Nav uses a static section set (replacing the DB `menu_items` route-links for the main bar):
  **Who I Am · What I Solve · Proof · Stages · Work · Writing · Contact**.
- Click behaviour: on homepage → `scrollIntoView({behavior:'smooth'})` (respect
  `prefers-reduced-motion` → `auto`). Off homepage → `router.push({ path: '/'+lang, hash:'#id' })`;
  router `scrollBehavior` resolves the hash with smooth scroll (verify/extend in router).
- Active-section highlight via IntersectionObserver (nice-to-have; falls back to no highlight).
- Logo (home) + LanguageSwitcher unchanged. Mobile overlay uses the same section set + closes on click.

## Data Integration Map

| Task | Component | Data source | Existing? | Notes |
|---|---|---|---|---|
| 1 | HeroOperator.vue | generated `hero-poster.jpg` (static); face ref = `settings.about.profile_photo` | poster NEW · ref existing | geminigen image; video operator-rendered later |
| 2 | WhoIAm.vue | `about.bio` via `useAboutSettings` | existing | sanitized `v-html` |
| 3 | ReceiptsBento.vue | 6 generated images (static) + `/api/homepage/featured` stats | stats existing · images NEW | geminigen |
| 4 | InternationalStages.vue | `/api/awards/{id}/galleries` | existing API | new `useStageGalleries` composable + `BaseGalleryModal` |
| 5 | TheNavigation.vue, Home.vue, router/index.js | section ids (DOM anchors) | menu_items existing (repurposed) | smooth scroll + route-home-then-scroll |

## Feasibility / risk notes
- geminigen MCP = **image only**. Hero video is operator-rendered; we ship the still + brief. (Locked.)
- 7 geminigen renders total (1 hero + 6 proof) — each ~30–90s. Generated during execution, saved to `public/`.
- Task 4 = 6 parallel cached gallery calls below the fold; graceful text-only fallback per card.
- Backend untouched (all 5 are frontend + static assets). Existing `*.test.mjs` smoke tests updated per component.
- No new deps. Dark Cinema tokens + existing `BaseGalleryModal` reused (no new design primitives → anti-AI-slop ok).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations (live `/api/awards` galleries, real
> `settings.about.bio`, geminigen-generated assets). During execution, NEVER substitute
> placeholders for real data sources without explicit user approval. If a data source
> doesn't exist yet, STOP and ask.

### Goal

Ship 5 operator-requested polish fixes to the "The Operator" homepage: a real hero still
(geminigen) + video brief, fix the WHO-I-AM HTML-tag leak, add imagery to all 6 proof tiles,
surface real award photos on the international-stages cards, and convert the header menu into
smooth scroll-to-section navigation. Frontend + static assets only — zero backend changes.

### Architecture Context (from CLAUDE.md + code read)

- Homepage = `frontend/src/views/Home.vue`, 9 `.snap-section` wrappers gated by
  `isSectionActive(section_type)`. Section types: `hero`, `who-i-am`, `what-i-solve`,
  `receipts`, `international-stages`, `selected-work`, `testimonials`, `latest-writing`,
  `join-the-build`.
- Bio source: `useAboutSettings()` → `heroBio` / `aboutSettings.bio` (live `/api/settings/about`,
  contains HTML). Profile photo: `aboutSettings.profile_photo` = `/uploads/about/1776545803_creator-face.png`.
- Awards: `GET /api/awards` + `GET /api/awards/{id}/galleries` → `{award, galleries[], total_photos}`;
  `galleries[i].items[].file_path` are full CDN URLs. Map verified (Design §Task 4).
- Gallery modal: `frontend/src/components/base/BaseGalleryModal.vue` — props `show`, `title`,
  `description`, `items`, `galleries`, `empty-message`, `@close`. Used in `About.vue:452`.
- Router `frontend/src/router/index.js:578` already has `scrollBehavior` resolving `to.hash`
  with `behavior:'smooth'`. Home route: `name:'home'`, `path:'/:lang'`.
- Nav: `frontend/src/components/TheNavigation.vue` — desktop + mobile lists currently driven by
  `useMenuItems()` DB rows mapped to `router-link`. Logo + `LanguageSwitcher` retained.
- geminigen MCP: `mcp__geminigen__generate_image` (image-only; `model`, `aspect`, `style`,
  `resolution`, `output_format`, `output_dir`, `refs`, `prompt`). Face ref = profile_photo CDN URL.
- Tests: file-content `.test.mjs` smoke checks (`node <file>.test.mjs`), `readFileSync` + `assert`.
  No DOM/jsdom. New tests follow the same shape (assert component/composable source contents).

### Tech Stack

Vue 3.5 `<script setup>` · Tailwind 4 (Dark Cinema tokens: `--accent-gold #D4A843`,
`--accent-cyan #06B6D4`, `--bg-deep`, `--bg-elevated`, `--fg-primary/muted`) · TanStack Vue Query
(60-min gallery cache) · Vite (Rolldown). No new dependencies.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Hero poster | `public/videos/hero-poster.jpg` (geminigen) | static asset | No | Generate in Phase D, set `posterSrc` |
| Hero face ref | `settings.about.profile_photo` CDN URL | n/a (geminigen `refs`) | Yes | Use existing URL |
| WHO-I-AM bio | `aboutSettings.bio` (HTML) | `useAboutSettings()` | Yes | Sanitize + `v-html` |
| Proof tile images (6) | `public/images/proof/*.png` (geminigen) | static assets | No | Generate in Phase E |
| Stage photos | `GET /api/awards/{id}/galleries` | new `useStageGalleries()` | API yes · composable no | Create composable |
| Stage modal | `items[]` from gallery | `BaseGalleryModal` | Yes | Reuse |
| Menu anchors | DOM section `id`s | router `scrollBehavior` (hash) | Yes | Add ids + scroll handler |

Phases A/B/C are asset-free (no geminigen) and mutually file-independent → parallelizable.
Phases D/E do geminigen renders (sequential I/O). Phase F = final gate.

---

### Phase A — WHO I AM: render sanitized HTML (Task 2)

**Estimated time:** 8 min

**Files:**
- Modify: `frontend/src/components/home/WhoIAm.vue`
- Test: `frontend/src/components/home/WhoIAm.test.mjs` (extend existing)

**Steps:**
1. Write failing test for the v-html fix in `WhoIAm.test.mjs`: assert source contains `v-html="cleanBio"` and a `cleanBio` computed, and that it does NOT render the bio via `{{ liveBio }}`. Expected error: `AssertionError: missing v-html cleanBio binding`.
2. Run `node src/components/home/WhoIAm.test.mjs`, confirm it fails for that reason.
3. Implement: add `cleanBio` computed that allowlist-sanitizes `liveBio` (keep `<p> <strong> <em> <br>`; strip every other tag, all attributes, and `script`/`style` blocks via regex). Replace the `{{ liveBio }}` interpolation with `<div v-html="cleanBio" class="...">`. Add scoped styles for `:deep(p)` spacing + `:deep(strong)`/`:deep(em)` emphasis matching the muted italic quote treatment.
4. Run test, confirm pass.
5. Commit: `fix(home): render WHO-I-AM bio as sanitized HTML (was leaking tags)`

**Verification:**
- [ ] `node src/components/home/WhoIAm.test.mjs` passes
- [ ] `cleanBio` strips `<script>`/attributes but keeps `p/strong/em/br`
- [ ] No `{{ liveBio }}` raw-HTML escape path remains; no TODO/placeholder
- [ ] `npm run build` compiles (final gate Phase F)

---

### Phase B — Header menu → scroll-to-section nav (Task 5)

**Estimated time:** 18 min

**Files:**
- Modify: `frontend/src/views/Home.vue` (add `id` to each `.snap-section` wrapper)
- Modify: `frontend/src/components/TheNavigation.vue` (static section links + scroll handler)
- Verify: `frontend/src/router/index.js` (hash scroll already present — no edit expected)
- Test: `frontend/src/components/TheNavigation.test.mjs` (new) + `frontend/src/views/Home.test.mjs` (new or extend)

**Steps:**
1. Write failing test `TheNavigation.test.mjs`: assert source defines a section-link set containing `who-i-am`, `what-i-solve`, `receipts`, `international-stages`, `selected-work`, `latest-writing`, `join-the-build`, and a `scrollToSection`/`goToSection` handler using `scrollIntoView` and a `router.push` hash fallback. Expected error: `Error: ENOENT ... TheNavigation.test.mjs` then after creating, `AssertionError: missing section link set`.
2. Run `node src/components/TheNavigation.test.mjs`, confirm fail.
3. Implement Home.vue: add matching `id="<section_type>"` (use `who-i-am`, `what-i-solve`, `receipts`, `international-stages`, `selected-work`, `latest-writing`, `join-the-build`) to each `.snap-section` wrapper div. Add `scroll-margin-top` (via a utility/class) so the fixed nav doesn't overlap anchored headings.
4. Implement TheNavigation.vue: add static `sectionLinks` array (label + id) — Who I Am · What I Solve · Proof(`receipts`) · Stages(`international-stages`) · Work(`selected-work`) · Writing(`latest-writing`) · Contact(`join-the-build`). Render these in BOTH desktop bar and mobile overlay as `<button>`/`<a href="#id">` (not router-link). Handler `goToSection(id)`: if on home route → `document.getElementById(id)?.scrollIntoView({behavior: reducedMotion ? 'auto':'smooth'})`; else → `router.push({ name:'home', params:{lang}, hash:'#'+id })` (router scrollBehavior completes the scroll). Close mobile menu on click. Keep logo + LanguageSwitcher.
5. Add `Home.test.mjs` assertion (new or extended): each of the 7 anchor ids appears in `Home.vue`. Run both tests, confirm pass.
6. Commit: `feat(home): header menu scrolls to homepage sections (route-home-then-scroll off-home)`

**Verification:**
- [ ] `node src/components/TheNavigation.test.mjs` and `Home.test.mjs` pass
- [ ] 7 section `id`s present in Home.vue; nav renders 7 section links (desktop + mobile)
- [ ] On-home click smooth-scrolls; off-home click pushes home + hash (manual: build/preview)
- [ ] `prefers-reduced-motion` → `auto` scroll; mobile menu closes on click
- [ ] No leftover dead `useMenuItems` route-links in the main bar; no TODO/placeholder

---

### Phase C — International stages: real award photos (Task 4)

**Estimated time:** 22 min

**Files:**
- Create: `frontend/src/composables/useStageGalleries.js`
- Modify: `frontend/src/components/home/InternationalStages.vue`
- Test: `frontend/src/composables/useStageGalleries.test.mjs` (new) + extend `InternationalStages.test.mjs`

**Steps:**
1. Write failing test `useStageGalleries.test.mjs`: assert the file exports `useStageGalleries`, references `/awards/` + `/galleries`, fetches in parallel (`Promise.all`), and maps `galleries[0].items[0]`. Expected error: `Error: ENOENT useStageGalleries.js`.
2. Run `node src/composables/useStageGalleries.test.mjs`, confirm fail.
3. Implement `useStageGalleries.js`: accept an array of `awardId`s; `Promise.all` over `api.get('/awards/'+id+'/galleries')`; return reactive map `{ [awardId]: { cover, items, title } }` where `cover = galleries[0].items[0].file_path`, `items = galleries[0].items`. Wrap each call in try/catch → `null` on failure (graceful). Loading flag. (Cache via TanStack `useQuery` keyed `['stage-gallery', id]`, staleTime 60min — matches Awards cache policy.)
4. Modify `InternationalStages.test.mjs`: add assertions that each stage has an `awardId`, the component imports `useStageGalleries`, renders a cover `<img>` with `v-if` guard (fallback text-only), and wires `BaseGalleryModal`. Keep the existing 6-stage + UNCTAD + funded + no-placeholder checks intact.
5. Run `InternationalStages.test.mjs`, confirm new assertions fail.
6. Implement `InternationalStages.vue`: add `awardId` to each stage object (13/10/11/7/8/9 per map). Call `useStageGalleries([...awardIds])` on setup. Add a cover photo block at the top of each card (`aspect-video`, `object-cover`, rounded, dark bottom gradient for the location chip) shown only when a cover resolves; text-only fallback otherwise. Card click → open `BaseGalleryModal` (`:show`, `:title`=event, `:items`=that stage's items, `@close`). Add `galleryOpen` ref + `activeStage`. Reduced-motion safe (no new motion). Keep all curated narrative text.
7. Run both tests, confirm pass.
8. Commit: `feat(home): real award photos on international-stages cards (+gallery modal)`

**Verification:**
- [ ] both `.test.mjs` pass; existing curated-fact checks still green
- [ ] 6 stages carry `awardId`; covers render from live `/api/awards/{id}/galleries`
- [ ] card without a resolved cover degrades to text-only (no broken `<img>`)
- [ ] click opens `BaseGalleryModal` with that event's photos; closes cleanly
- [ ] no TODO/placeholder; `npm run build` compiles (Phase F)

---

### Phase D — Hero still via geminigen + video brief (Task 1)

**Estimated time:** 15 min (+ ~1–2 min render wait)

**Files:**
- Generate: `frontend/public/videos/hero-poster.jpg` (geminigen `output_dir`)
- Modify: `frontend/src/components/home/HeroOperator.vue` (set `posterSrc`)
- Create: `docs/plans/hero-video/operator-render-brief.md` (text-to-video prompt for operator)
- Test: extend `frontend/src/components/home/HeroOperator.test.mjs`

**Steps:**
1. Write failing test in `HeroOperator.test.mjs`: assert `posterSrc` is set to `/videos/hero-poster.jpg` (not empty string). Expected error: `AssertionError: posterSrc still empty`.
2. Run `node src/components/home/HeroOperator.test.mjs`, confirm fail.
3. Generate the still: `mcp__geminigen__generate_image` with `refs=['https://alisadikinma.com/uploads/about/1776545803_creator-face.png']`, `model='nano-banana-pro'`, `aspect='16:9'`, `style='Portrait Cinematic'`, `resolution='4K'`, `output_format='jpeg'`, `output_dir` = absolute path to `frontend/public/videos/`, prompt: cinematic editorial wide shot of the referenced bald man with glasses in a dark navy suit, confident operator energy, dark cinematic studio, warm gold rim-light + subtle cyan accent, deep shadows, shallow depth of field, premium AI-founder mood, negative space lower-left for text. Confirm file lands; rename to `hero-poster.jpg` if needed.
4. Set `posterSrc = '/videos/hero-poster.jpg'` in HeroOperator.vue (keep `mp4Src='/videos/hero-bg.mp4'` interim loop; `webmSrc=''`).
5. Write `operator-render-brief.md`: 5–8s seamless loop, slow push-in / subtle parallax, same lighting, no hard cuts, export webm+mp4 + poster — for the operator to render on geminigen.ai web and drop into `public/videos/`.
6. Run test, confirm pass.
7. Commit: `feat(home): cinematic hero poster (geminigen) + operator video-render brief`

**Verification:**
- [ ] `frontend/public/videos/hero-poster.jpg` exists (non-zero size)
- [ ] `HeroOperator.test.mjs` passes; `posterSrc` wired; poster shows for reduced-motion + as video poster
- [ ] `operator-render-brief.md` written; no TODO/placeholder in component

---

### Phase E — Proof bento: 6 generated backgrounds (Task 3)

**Estimated time:** 18 min (+ ~6–10 min total render)

**Files:**
- Generate: `frontend/public/images/proof/{demoday,impact,products,years,countries,accuracy}.png`
- Modify: `frontend/src/components/home/ReceiptsBento.vue`
- Test: extend `frontend/src/components/home/ReceiptsBento.test.mjs`

**Steps:**
1. Write failing test in `ReceiptsBento.test.mjs`: assert each tile object carries an `image` field referencing `/images/proof/`, and the template renders a background `<img>`/bg-layer with a dark overlay for legibility. Expected error: `AssertionError: tiles missing image field`.
2. Run `node src/components/home/ReceiptsBento.test.mjs`, confirm fail.
3. Generate 6 images via `mcp__geminigen__generate_image` (`model='nano-banana-pro'`, `aspect='1:1'`, `resolution='2K'`, `output_format='png'`, `output_dir`=`frontend/public/images/proof/`). `demoday.png` uses the face ref (Ali on a global stage, trophy/spotlight, gold). Others atmospheric, dark, Dark-Cinema palette, low detail for overlay legibility: `impact` (gold upward impact/finance abstract), `products` (floating product UI panels, gold/cyan), `years` (factory floor dissolving into flowing code, warm→cyan), `countries` (dark globe with glowing connection arcs, cyan), `accuracy` (PCB macro under a precision scan beam, cyan).
4. Modify ReceiptsBento.vue: add `image` to each tile; render an absolutely-positioned background `<img class="object-cover opacity-...">` + a strong dark gradient overlay layer beneath the content so the stat/title/sub stay fully legible (lead tile keeps its gold radial). Keep stats live (`useHomepageFeatured`). Reduced-motion unaffected (static images).
5. Run test, confirm pass.
6. Commit: `feat(home): cinematic backgrounds on all 6 proof tiles (geminigen)`

**Verification:**
- [ ] 6 files exist in `frontend/public/images/proof/` (non-zero)
- [ ] `ReceiptsBento.test.mjs` passes; every tile has an image + overlay
- [ ] stat text remains legible over imagery (manual: preview); live stats intact
- [ ] no TODO/placeholder

---

### Phase F — Final gate

**Estimated time:** 6 min

**Steps:**
1. Run all home smoke tests: `for f in src/components/home/*.test.mjs src/components/TheNavigation.test.mjs src/composables/useStageGalleries.test.mjs src/views/Home.test.mjs; do node "$f"; done` — all green.
2. `npm run build` — clean production build, no errors.
3. (Optional) `npm run preview` — eyeball hero poster, WHO-I-AM bio formatting, proof imagery legibility, stage photos + modal, menu scroll on home + from /blog.
4. Update root `CLAUDE.md` "Last Updated" + page-section/component notes (sync-docs).
5. Commit: `docs: sync CLAUDE.md for homepage Operator polish pass`. Do NOT push (project git policy — operator authorizes pushes).

**Verification:**
- [ ] All `.test.mjs` pass
- [ ] `npm run build` clean
- [ ] CLAUDE.md updated; commits made; nothing pushed

---

### Execution handoff

- **In this session:** start Phase A (gaspol-execute, per-phase checkpoints + TDD gate).
- **Parallel:** Phases A, B, C are file-independent and asset-free → `gaspol-parallel` (plan-phases) can run them concurrently; D + E (geminigen renders) run after / sequentially; F last.
- **Separate session:** this file has everything needed to resume.
