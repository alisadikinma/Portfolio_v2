# Homepage "The Operator" — Follow-up Pass 3 (Track Record · MANDOR visual · dead CTAs · hero video)

**Date:** 2026-06-08
**Author:** brainstorm (gaspol-brainstorm)
**Scope:** Frontend only. Four operator-reported items from the live homepage review.
**Predecessors:** `2026-06-08-homepage-the-operator.md`, `2026-06-08-homepage-operator-polish.md`

---

## Design

### Decisions locked (via AskUserQuestion)

| # | Item | Decision |
|---|------|----------|
| 1 | Hero video | **DEFERRED** — operator is building a video-gen MCP, will supply the asset later. No code change now; interim `hero-bg.mp4` loop + warrior `hero-poster.jpg` stay. |
| 2 | "Learn AI with me" + all dead CTAs | Redirect **all** dead course/MANDOR CTAs → newsletter/waitlist (`#join-the-build`). |
| 3 | MANDOR AI visual | **Generate a branded kanban-board mockup** (geminigen, image-only) to replace the OpenClaw orchestrator render. |
| 4 | Career history | **Reframe `InternationalStages` → "Track Record"**: drop IDBYTE, add 3 career chapters as a distinct band. |

---

### Item 1 — Hero video (NEW DIRECTION — produce in next session)

**Decision (2026-06-08):** **Drop the warrior concept.** The hero video must make a viewer
**instantly understand Ali is an AI expert** just by watching — no caption needed.

**Toolchain (geminigen REMOVED):** `indusia-image-gen` (default nano-banana-2) → keyframe(s);
`indusia-video-gen` → image-to-video loop. Both require a fresh session to register the MCP servers.

**North-star:** in the first 2 seconds, a stranger thinks *"this person commands AI."* The clickable
CTA stays as the crisp HTML buttons (Follow the build / Learn AI / Read the blog); the video's job is
attention + authority + choreographing the eye toward the gold button (motion CTA, **no baked text** —
legibility + i18n). Left third stays dark/low-motion for wordmark + manifesto legibility.

**Candidate concepts (pick in next session):**

| Concept | What the viewer sees | Why it reads "AI expert" |
|---------|----------------------|--------------------------|
| **A · Operator at the console (REC)** | Ali (real, from `settings.about.profile_photo` face ref) in a dark cinematic studio, a wall of screens streaming live AI work — agents running, code generating, image/video rendering, dashboards. Slow push-in; screens flicker; he glances to camera. | He visibly *commands* multiple AI systems at once — authority + scale. |
| **B · AI materializing around him** | Ali centered; holographic AI artifacts (neural mesh, generated images, agent task-boards, video frames) assemble/orbit around him. | He is the source the AI outputs flow from. |
| **C · Output montage** | Quick cinematic cuts of his real outputs (Sparkfluence, INDUSIA inspection, gen-video, carousels) with Ali as the through-line. | Proof-by-portfolio — receipts in motion. |

**Recommended:** Concept A (real face = strongest expert signal; single strong keyframe → indusia-video-gen loop). Confirm concept in next session, then: keyframe → 8s loop → wire `webmSrc`/`mp4Src` in `HeroOperator.vue` (poster already wired). Specs: 1920×1080, 16:9, 8s seamless, muted, webm+mp4, <4MB.

**Interim (unchanged):** `posterSrc=/videos/hero-poster.jpg` + `mp4Src=/videos/hero-bg.mp4` keep the page alive until the new loop ships. (The warrior poster will be replaced by the new concept's keyframe.)

---

### Item 2 — Dead CTAs → waitlist

**Problem:** `/courses` and `/projects/mandor-ai` are **not registered** in `src/router/index.js`. Four CTAs dead-link:
1. `HeroOperator.vue` — "Learn AI with me" → `/courses`
2. `whatISolve.js` vibe-coding — "Learn it →" → `/courses#vibe-coding`
3. `whatISolve.js` ai-agent-os — "Explore MANDOR AI →" → `/projects/mandor-ai`
4. `whatISolve.js` ai-video — "Learn it →" → `/courses#video-generation`

**Fix:** all four scroll to the existing `#join-the-build` section (newsletter signup), reduced-motion aware — same pattern as `TheNavigation.vue` section-anchor nav.

- **HeroOperator.vue:** swap the `<RouterLink to="/courses">` for a button that `scrollIntoView`s `#join-the-build` on homepage (it always is). Keep visual identical.
- **WhatISolveTabs.vue:** the CTA is `<RouterLink :to="active.cta.to">`. Change `cta` shape in `whatISolve.js` from `{label, to}` (route) to a scroll target. Cleanest: add `cta.anchor = 'join-the-build'` and render the CTA as a scroll-button when `anchor` present, RouterLink when `to` present. All 3 tabs set `anchor: 'join-the-build'`; drop the dead `to`.
- CTA **labels** updated to match intent: "Learn it →" / "Explore MANDOR AI →" → keep "Explore MANDOR AI →" but it now joins the waitlist; the two "Learn it →" stay. (Copy unchanged is fine — destination is a waitlist for exactly these topics.)

No new routes, no dead links left.

### Item 3 — MANDOR AI branded board mockup

**Problem:** the ai-agent-os tab renders `/videos/ai-agents.mp4` — the OpenClaw orchestrator diagram. Operator wants a **kanban-board** visual (assign issues to AI agents) representing MANDOR AI, no OpenClaw branding.

**Plan:**
1. **Generate** via `indusia-image-gen` (default nano-banana-2; geminigen REMOVED), 16:9 to match the tab's `aspect-video` frame. Spec: a dark-cinema Linear/Kanban board UI — columns (Backlog / In Progress / In Review / Done), issue cards each with a small **AI-agent avatar** (robot/glyph, not human photos), left sidebar reading "Agents · Runtimes · Skills", subtle gold/cyan accent matching the design system, **"MANDOR AI"** wordmark top-left. No OpenClaw, no real logos. Save to `public/images/whatisolve/mandor-board.jpg` (+ optimized).
2. **Render path:** `WhatISolveTabs.vue` currently only does `<video>`. Add an optional `imageSrc` on the tab object → when present, render `<img>` (lazy, object-cover) instead of `<video>`; video stays the fallback for the other two tabs. Minimal, additive.
3. `whatISolve.js` ai-agent-os: add `imageSrc: '/images/whatisolve/mandor-board.jpg'` (keep `videoSrc` as deprecated fallback or remove).

### Item 4 — `InternationalStages` → "Track Record" (career + stages)

**Operator override (divergence):** I advised against replacing IDBYTE because Stages = speaking/competition events, not employment. Operator chose to replace it anyway to surface 17-year career proof. Honored via a **section reframe** (not a raw card swap) so a manufacturing job never sits under an "invited to the world's stages" headline.

**Design — one section, two labeled bands:**

```
TRACK RECORD                                    (eyebrow, mono, cyan)
16 countries. 17 years. One operator.           (h2)
From the factory floor to the world's stages — one continuous arc.   (sub)

── Career band (3 chapter cards, 3-col) ────────────────────────
[🇸🇬 Singapore · 8y]   [🇮🇩 Startup · 4y]   [🇮🇩 Manufacturing · 5y]
 Multinational IT       Co-Founder & CEO     Head of Digital Transf.

── Stages band (5 stage cards, existing) ──────────────────────
[🥇 Demo Day] [🇨🇳 Hangzhou] [🇺🇸 Silicon V.] [🇮🇩 NextDev] [🇺🇸 Fenox]
```

- **Drop** the `stage-idbyte` object (Top 8 Finalist — weakest, operator-chosen).
- **Add** a `careerChapters` array (static curated, like `stages` — text-only cards, no `awardId` → no gallery, the existing `coverFor()`→null path already renders text-only).
- **Section id stays `international-stages`** so `TheNavigation.vue` section-anchor + `Home.vue` wrapper keep working. Nav label is currently "Awards" → still points here. (Minor: label says "Awards" for a now-broader section — flag to operator; optionally rename nav label to "Track Record" in a follow-up, out of scope unless wanted.)
- Career cards reuse the stage card shell with a distinct accent (gold for career vs cyan for stages) + a small "career" / role label to differentiate visually.

**Career chapter data (verified — `10-Identity/experience.md`, `ali.md`):**

| Chapter | Years | Role / Company | Proof line |
|---------|-------|----------------|------------|
| 🇸🇬 Singapore — Multinational IT | 2008–2016 (~8y) | Software Consultant → Engineer → Solution Support (exSYS · DHL Supply Chain · Thales/Gemalto · MPA Singapore) | Java/J2EE/Oracle, Asia-Pacific enterprise delivery for MNC clients. |
| 🇮🇩 Startup — Co-Founder & CEO | 2016–2019 (~4y) | Marlin Booking | Digitized Indonesian ports (Ministry of Transportation), $5M valuation → UN-UNCTAD × Alibaba eFounders (1 of 48 in Asia). |
| 🇮🇩 Manufacturing — Head of Digital Transformation 4.0 | ~2019–2024 (5y) | PT Sat Nusapersada (Satnusa), Batam | Led 31, 56+ enterprise AI/IoT products, $318K+ documented impact, MySatnusa Super App — seeded INDUSIA.ai. |

---

## Data Integration Map

| Element | Data Source | Existing? | Notes |
|---------|-------------|-----------|-------|
| Hero video loop | `public/videos/hero-loop.*` | ❌ pending operator MCP | No-op this pass |
| "Learn AI with me" + tab CTAs | static / scroll `#join-the-build` | ✅ JoinTheBuild section exists | No backend |
| MANDOR board image | `indusia-image-gen`-generated `public/images/whatisolve/mandor-board.jpg` | ❌ next session (MCP not loaded) | Static asset; geminigen removed |
| WhatISolveTabs `imageSrc` | `src/data/whatISolve.js` | ✅ data file exists | Additive field + `<img>` render path |
| Career chapters | static array in `InternationalStages.vue` (facts from vault `experience.md`) | ✅ vault verified | No API — curated narrative, like `stages` |
| Stage cards (5) | `/api/awards/{id}/galleries` via `useStageGalleries` | ✅ unchanged | IDBYTE removed |

## Feasibility / placeholder check
- ✅ All career facts verified against vault (no placeholders).
- ✅ MANDOR image is a real generated asset (not a stub).
- ✅ CTAs scroll to a real, existing section.
- ⚠️ Hero video deferred by operator — explicitly out of scope, no fake asset wired.

## Out of scope
- Hero video production (operator-owned MCP).
- Building real `/courses` + `/projects/mandor-ai` pages (waitlist redirect is the interim).
- Renaming nav label "Awards" → "Track Record" (flag only).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. NEVER substitute placeholders
> for real data sources without explicit user approval. If a data source doesn't exist
> yet, STOP and ask. Git policy: commit per phase, **never push** (operator authorizes pushes).

### Goal

Ship 3 frontend-only fixes to the live homepage: (B) all dead course/MANDOR CTAs scroll to the
newsletter waitlist, (C) the MANDOR AI tab shows a branded kanban-board mockup instead of the
OpenClaw orchestrator video, (D) `InternationalStages` is reframed into a "Track Record" section
that adds Ali's 17-year career (3 chapters) alongside his speaking stages, with IDBYTE dropped.
Hero video (A) is deferred to the operator's video-gen MCP — no code change this pass.

### Architecture Context (from CLAUDE.md + code)

- Homepage spine = `Home.vue` with `.snap-section` wrappers each carrying an `id`; nav (`TheNavigation.vue`) scrolls to those ids. `#join-the-build` = newsletter section (`JoinTheBuild.vue`). `#international-stages` = the section being reframed.
- `WhatISolveTabs.vue` reads tab data from `src/data/whatISolve.js`; renders the right column as `<video :src="active.videoSrc">` with an error→gradient fallback. CTA = `<RouterLink :to="active.cta.to">`.
- `InternationalStages.vue` renders a static `stages[]` array; `coverFor(stage)`→null path already renders text-only cards (no `awardId` ⇒ no gallery). Real photos come from `useStageGalleries`.
- Reduced-motion pattern: `window.matchMedia('(prefers-reduced-motion: reduce)')` (see HeroOperator.vue) and `behavior: prefersReduced ? 'auto' : 'smooth'` for scroll (see TheNavigation.vue).
- Design tokens: `--accent-gold #D4A843`, `--accent-cyan #06B6D4`, `--bg-deep #050506`, `--bg-elevated #0C0C0F`; fonts Space Grotesk / Inter / JetBrains Mono.

### Tech Stack

Vue 3.5 `<script setup>` · Tailwind 4 · Vite (rolldown) · smoke tests = file-content assertions in `*.test.mjs` run via `node <file>.test.mjs` (missing/unmatched source string ⇒ RED). Build check: `npm run build`. geminigen MCP (`nano-banana-2`) for the image asset.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Hero "Learn AI with me" → waitlist | scroll to `#join-the-build` | n/a (DOM scroll) | Yes (section) | Use existing anchor |
| Tab CTAs → waitlist | `whatISolve.js` `cta.anchor` | n/a (DOM scroll) | data file Yes | Add `anchor`, drop dead `to` |
| MANDOR board visual | `public/images/whatisolve/mandor-board.jpg` | geminigen | No | Generate in Phase B |
| Tab image render | `whatISolve.js` `imageSrc` | n/a | No | Additive field + `<img>` path |
| Career chapters | static array in `InternationalStages.vue` | n/a (curated, vault-verified) | No | Create from `experience.md` facts |
| Stage cards (5) | `/api/awards/{id}/galleries` | `useStageGalleries` | Yes | Unchanged; remove IDBYTE |

---

### Phase B: Dead CTAs → newsletter waitlist

**Estimated time:** 12 min

**Files:**
- Modify: `frontend/src/components/home/HeroOperator.vue`
- Modify: `frontend/src/components/home/WhatISolveTabs.vue`
- Modify: `frontend/src/data/whatISolve.js`
- Test: `frontend/src/components/home/HeroOperator.test.mjs`, `WhatISolveTabs.test.mjs`, `frontend/src/data/whatISolve.test.mjs`

**Steps:**
1. Write failing test in `whatISolve.test.mjs`: assert the data file contains `anchor: 'join-the-build'` and NO `/courses` or `/projects/mandor-ai` strings. Expected error: `AssertionError [ERR_ASSERTION]: data must route CTAs to join-the-build` (source still has `/courses`).
2. Run `node src/data/whatISolve.test.mjs`, confirm it fails for that reason.
3. In `whatISolve.js`, change each tab's `cta` from `{ label, to: '/courses#...' | '/projects/mandor-ai' }` to `{ label, anchor: 'join-the-build' }` (keep labels: "Learn it →" ×2, "Explore MANDOR AI →").
4. In `WhatISolveTabs.vue`, replace the CTA `<RouterLink :to>` with a `<button>` that calls a `scrollToAnchor(active.cta.anchor)` helper (uses `document.getElementById(anchor)?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth' })`); add a `reducedMotion` matchMedia ref + cleanup. Keep the visual classes identical.
5. Write failing test in `HeroOperator.test.mjs`: assert source contains `join-the-build` and NOT `to="/courses"`. Run, see fail.
6. In `HeroOperator.vue`, replace the `<RouterLink to="/courses">` "Learn AI with me" with a `<button>` calling the same scroll helper (add matchMedia reducedMotion already present? it has `reducedMotion` ref — reuse it). Keep button styling identical.
7. Add `WhatISolveTabs.test.mjs` assertion: source contains `scrollIntoView` (CTA now scrolls). Run all 3 test files, confirm pass.
8. `npm run build`, confirm clean.
9. Commit: `fix(home): route dead course/MANDOR CTAs to newsletter waitlist (#join-the-build)`

**Verification:**
- [ ] `node src/data/whatISolve.test.mjs && node src/components/home/HeroOperator.test.mjs && node src/components/home/WhatISolveTabs.test.mjs` all pass
- [ ] No occurrence of `/courses` or `/projects/mandor-ai` in `whatISolve.js`, `HeroOperator.vue`
- [ ] Clicking any of the 4 CTAs scrolls to the newsletter section (reduced-motion → instant)
- [ ] `npm run build` clean; no placeholder/TODO comments

---

### Phase C: MANDOR AI branded board mockup

**Estimated time:** 12 min (+ image gen)

**Files:**
- Create: `frontend/public/images/whatisolve/mandor-board.jpg` (geminigen)
- Modify: `frontend/src/data/whatISolve.js` (add `imageSrc` to ai-agent-os)
- Modify: `frontend/src/components/home/WhatISolveTabs.vue` (`<img>` render path)
- Test: `frontend/src/data/whatISolve.test.mjs`, `WhatISolveTabs.test.mjs`

**Steps:**
1. Generate the board image via geminigen (`nano-banana-2`, 16:9). Prompt spec: dark-cinema Linear/Kanban board UI — columns Backlog / In Progress / In Review / Done; issue cards each with a small glowing AI-agent avatar (robot glyph, NOT human faces); left sidebar labelled "Agents · Runtimes · Skills"; "MANDOR AI" wordmark top-left; gold (#D4A843) + cyan (#06B6D4) accents on #050506; NO "OpenClaw", NO real third-party logos. Save/downscale to `public/images/whatisolve/mandor-board.jpg`.
2. Write failing test in `whatISolve.test.mjs`: assert ai-agent-os tab has `imageSrc` containing `mandor-board`. Expected error: `AssertionError`. Run, see fail.
3. In `whatISolve.js`, add `imageSrc: '/images/whatisolve/mandor-board.jpg'` to the `ai-agent-os` tab (leave `videoSrc` as a fallback or remove — prefer remove to avoid double-loading).
4. Write failing test in `WhatISolveTabs.test.mjs`: assert source contains both an `<img` render path and `active.imageSrc`. Run, see fail.
5. In `WhatISolveTabs.vue`, in the right column add `v-if="active.imageSrc"` `<img :src="active.imageSrc" loading="lazy" decoding="async" class="h-full w-full object-cover">`; the existing `<video>` becomes `v-else-if="active.videoSrc && !videoBroken[active.id]"`; gradient fallback `v-else`.
6. Run both test files, confirm pass. Verify the file exists: `test -f public/images/whatisolve/mandor-board.jpg`.
7. `npm run build`, confirm clean.
8. Commit: `feat(home): MANDOR AI tab shows branded kanban-board mockup (drop OpenClaw render)`

**Verification:**
- [ ] `public/images/whatisolve/mandor-board.jpg` exists and is a real generated board (no OpenClaw text/logo)
- [ ] `node src/data/whatISolve.test.mjs && node src/components/home/WhatISolveTabs.test.mjs` pass
- [ ] MANDOR AI tab renders the image; Vibe Coding + Gen Video tabs still render their videos
- [ ] `npm run build` clean

---

### Phase D: `InternationalStages` → "Track Record" (career band + drop IDBYTE)

**Estimated time:** 15 min

**Files:**
- Modify: `frontend/src/components/home/InternationalStages.vue`
- Test: `frontend/src/components/home/InternationalStages.test.mjs`

**Steps:**
1. Write failing tests in `InternationalStages.test.mjs`: (a) source contains `Track Record` (new eyebrow/heading); (b) source contains `Sat Nusapersada` and `Marlin` and `exSYS` (career chapters present); (c) source does NOT contain `IDBYTE` (dropped). Expected error: `AssertionError`. Run, see fail.
2. In `InternationalStages.vue`, remove the `stage-idbyte` object from `stages[]`.
3. Add a static `careerChapters` array (3 objects — see Design "Career chapter data"): Singapore MNC IT 2008–2016 (exSYS · DHL · Thales/Gemalto · MPA), Marlin Booking 2016–2019, PT Sat Nusapersada ~2019–2024. Each: `{ id, flag, location, years, role, org, note, accent:'gold' }`. Facts verbatim from the Design table (vault-verified — NO invented numbers).
4. Update the header: eyebrow `track record`, h2 `16 countries. 17 years. One operator.`, sub `From the factory floor to the world's stages — one continuous arc.`
5. Add a "Career" band ABOVE the existing stage grid: sub-label "The 17-year arc" + a 3-col grid of career cards (reuse the stage card shell markup; gold accent; no cover image, no gallery click). Keep the existing stage grid below under a "Global stages" sub-label.
6. Keep the `<BaseGalleryModal>` + `useStageGalleries` wiring unchanged (stages still pull photos; career cards are text-only and not clickable).
7. Run `node src/components/home/InternationalStages.test.mjs`, confirm all pass.
8. `npm run build`, confirm clean.
9. Commit: `feat(home): reframe Stages → Track Record (add 17yr career band, drop IDBYTE)`

**Verification:**
- [ ] `node src/components/home/InternationalStages.test.mjs` passes (Track Record heading, 3 career chapters present, IDBYTE absent)
- [ ] Section id stays `international-stages` (nav anchor unbroken)
- [ ] Career cards show verified facts only (Satnusa 56+/$318K, Marlin $5M/UN-UNCTAD, SG MNC employers) — no placeholders
- [ ] Stage cards (5) still pull photos via `useStageGalleries`; gallery modal still opens
- [ ] `npm run build` clean

---

### Phase ordering / parallelism

- **B → C are sequential** (both touch `whatISolve.js` + `WhatISolveTabs.vue` — would conflict in parallel).
- **D is independent** (only `InternationalStages.vue`) — safe to run in parallel with B or C if desired, but serial B→C→D is simplest for a pass this small.
- **A (hero video)** = no-op; revisit when the operator's MCP delivers `hero-loop.*`.

### Post-implementation
- Update root `CLAUDE.md` "Last Updated" + Page Sections Mapping note (Stages → Track Record reframe; WhatISolveTabs `imageSrc`).
- Commit only — do not push (operator authorizes).

---

## Session Handoff — 2026-06-08 (paused for MCP reload)

**Why paused:** `indusia-image-gen` + `indusia-video-gen` MCP servers aren't registered in the
current session (geminigen removed). Operator restarting Claude Code to load them.

**Status: ALL COMPLETE (2026-06-08 session 2 — MCP registered).**
- ✅ **Phase B DONE + committed** (`c517123e`) — all 4 dead CTAs scroll to `#join-the-build`.
- ✅ **Phase C DONE** (`d908ca33`) — MANDOR kanban board (`indusia-image-gen` nano-banana-pro) + `imageSrc` render path in WhatISolveTabs.
- ✅ **Phase D DONE** (`2d39c692`) — Track Record reframe + 3 career chapters (vault-corrected dates), IDBYTE dropped. **Plus** career-card real photos via new `useExperienceGalleries` (`41707ac0`+`4242de28`).
- ✅ **Hero video DONE** (`88e8e721`) — final concept = trendy blazer+tee operator + central JARVIS HUD orchestrating 3 labeled screens (VIBE CODING · AI AGENT OS · VIDEO GEN). Keyframe (indusia-image-gen) → VEO loop (indusia-video-gen) → ffmpeg delogo (strip Veo watermark) → webm 2.92MB/mp4 3.40MB wired + sw.js v2 pre-cache. **Diverged from plan's Concept A** (single operator facing camera) per operator's live direction: added JARVIS core + 3 labeled discipline screens + costume change.

**Resume steps (new session):**
1. Read this plan file. Re-invoke `gaspol-execute` for `2026-06-08-homepage-followups-track-record-mandor-ctas.md`.
2. Confirm `indusia-image-gen` + `indusia-video-gen` are callable (ToolSearch).
3. **Phase D** (no MCP) → **Phase C** (generate MANDOR board via indusia-image-gen, wire imageSrc) → **Hero video** (confirm Concept A, keyframe → loop → wire HeroOperator).
4. Per phase: TDD smoke test → implement → `npm run build` → commit (no push).
5. Final: `gaspol-sync-docs` → update root CLAUDE.md "Last Updated".

**Face ref for hero + any human imagery:** `settings.about.profile_photo` (admin-managed). Career facts: vault `10-Identity/experience.md` (already transcribed into the Design "Career chapter data" table above).
