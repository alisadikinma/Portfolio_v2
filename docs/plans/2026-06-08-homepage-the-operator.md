# Homepage Redesign — "The Operator" (Identity-Led Creator Hub)

**Created:** 2026-06-08
**Source:** gaspol-brainstorm session (Ali Sadikin)
**Supersedes emphasis of:** [2026-05-04-homepage-redesign-plan.md](2026-05-04-homepage-redesign-plan.md) — reuses its shipped backend (homepage API, `awards.is_featured`, `testimonials.source`, `WhatISolveTabs`) but re-centers the narrative from "Problem Solver / conversion" to "personal-brand creator hub".
**Deliverable order:** Figma mockup → approval → `gaspol-plan` → develop.

---

## Design

### Intent

One scroll = *everything about Ali*. Top→bottom, a builder/AI-peer visitor should **feel why Ali is exceptional**: 17 years, global awards, international stages (UNCTAD/Alibaba/Google SV), Demo Day #1 AI Generalist — told in a **building-in-public** voice, not a sales pitch.

### Locked Decisions

| # | Decision | Value |
|---|---|---|
| Scroll spine | **C — Identity-Led "The Operator"** | Who Ali is → what he builds → the receipts → join the build |
| Hero video | **Person-forward montage** | 12–15s loop, Ali as lead (factory floor · vibe-coding · conference stage · face+wordmark). AI-generated (NB2→VEO) w/ Ali face ref. Replaces the abstract "Genesis Triptych" brief. |
| Primary audience | **Creator / peer (AI builder community)** | Tone: behind-the-scenes building |
| Primary CTA | **Follow @alisadikinma + read blog** (+ newsletter) | Soft secondary: "Got an AI problem? WhatsApp" |
| Creator module | **Content Engine meta-flex** (in §8) | "This blog writes itself via my own AI Content Engine." OSS wall dropped → one-line nod in §2. |
| §3 discipline 3 | **AI Agent OS · MANDOR AI (aka Multica)** | "Introducing" treatment. Open-source managed-agents platform. |
| §4 Receipts | **6 tiles, NO named brand strip** | Keyence framed as benchmark (cheaper+better), NOT "replaced". |
| Language | Geo-IP ID/EN + sticky cookie | Already shipped (May 4) |

### Design Tokens (ULTRA Dark Cinema — locked, from vault `visual-identity` + CLAUDE.md)

- **Palette:** void `#050506` · elevated `#0C0C0F` · gold `#D4A843`/`#F5A623` (primary) · cyan `#06B6D4` (secondary) · indigo `#5E6AD2` (atmosphere only) · text `#EDEDEF` · muted `#8A8F98`. **No purple/pink.**
- **Type:** Space Grotesk 500–700 (display/wordmark) · Inter 300–700 (body) · JetBrains Mono (labels uppercase tracked) · Playfair italic (editorial headlines §8).
- **Effects:** glass cards (`backdrop-blur 40px saturate 180%`) · gold→cyan text gradient · aurora pulse 8s (respect reduced-motion) · all motion 150–300ms · hyperrealistic imagery only.
- **Anti-AI-slop:** no "Amazing/Cutting-edge/Revolutionary", asymmetric/data-driven layouts, intentional negative space.

### Scroll Wireframe (9 sections)

```
1 HERO            person-forward video loop · eyebrow "ai generalist · since 2008"
                  H1 "ALI SADIKIN MA" · manifesto 1-liner
                  [Follow the build]  [Read the blog →]
                  inline stats: 17 YRS · 16 COUNTRIES · 🥇 DEMO DAY #1
2 WHO I AM        manifesto + portrait · trilingual · Batam→16 countries · builder
                  one-line: "20+ open-source repos"
3 WHAT I BUILD    3-discipline tab switcher (reuse WhatISolveTabs):
                  Vibe Coding · AI Agent OS (MANDOR AI) ·Introducing· Generative Video
4 THE RECEIPTS    6-tile bento (no brand names):
                  🥇#1 Demo Day 2026 (lead) · $318K+ · 56+ products
                  17 yrs · 16 countries · ≥95% accuracy
5 INT'L STAGES    horizontal row: Alibaba Hangzhou (UNCTAD,1/48 Asia) ·
                  Google Startup Grind SV · Fenox World Cup · IDBYTE · Demo Day Bengaluru
6 SELECTED WORK   metric-led cards: INDUSIA · Sparkfluence · Marlin $5M · MySatnusa
                  [All 56 projects →]
7 TESTIMONIALS    LinkedIn-sourced carousel (shipped) · "via LinkedIn ↗"
8 LATEST WRITING  editorial feed + Content Engine meta-flex badge + mini-explainer
9 JOIN THE BUILD  follow @alisadikinma (IG·TikTok·LI·YT) + newsletter signup
                  soft 2nd: "Got an AI problem? WhatsApp"
```

### §3 — MANDOR AI copy (locked)

> **AI AGENT OS · MANDOR AI** `Introducing` (aka Multica)
> **The operating system for your AI workforce.**
> Assign tasks to coding agents like you'd assign a colleague — they pick up the work, write code, report blockers, and compound skills over time. Squads, Autopilots, reusable Skills. *Your next 10 hires won't be human.*
> `open-source · 11+ agent CLIs · self-hostable`

### §4 — Receipts tiles (locked)

| Tile | Value | Sub |
|---|---|---|
| 🥇 lead | **#1 · Global AI Demo Day 2026** | beat 26 startups · 16 countries |
| 💰 | **$318K+** | documented impact |
| 📦 | **56+** | enterprise products shipped |
| ⏳ | **17** | years building |
| 🌏 | **16** | countries |
| 🎯 | **≥95%** | edge-AI inspection — better & cheaper than Keyence-class AOI |

### Data Integration Map (mostly live — minimal new backend)

| Section | Data source | Status |
|---|---|---|
| 1 Hero stats | `/api/homepage/stats` | ✅ shipped |
| 1 Hero video | new `public/videos/hero-loop.{mp4,webm}` | ⬜ produce (person-forward) |
| 2 Manifesto | `settings` group=about + static copy | ✅ |
| 3 Disciplines | `data/whatISolve.js` (+ MANDOR AI tab content) | ✅ shipped, ⬜ add MANDOR tab |
| 4 Receipts | `/api/homepage/featured` (stats+awards) + static metrics | ✅ shipped |
| 5 Int'l stages | `/api/awards` (is_featured) | ✅ shipped |
| 6 Selected work | `featured.featured_projects` | ✅ shipped |
| 7 Testimonials | `featured.featured_testimonials` (source=linkedin) | ✅ shipped |
| 8 Latest writing | `featured.latest_articles` + static badge | ✅ shipped |
| 9 CTA | `settings.social_links` + newsletter API | ✅ shipped |

**Net build cost:** frontend recompose of `Home.vue` + ~5 new section components + MANDOR tab in `whatISolve.js` + new person-forward hero video. Backend largely done.

### SEO + GEO / LLM-Friendly workstream (NEW — added 2026-06-08)

**Goal:** when someone asks ChatGPT / Gemini / Claude / Perplexity *"who is a good AI generalist/expert to learn from"* or *"who is Ali Sadikin Ma"* — OR Googles *"belajar AI agent / vibe coding course / AI video generation class"* — **alisadikinma.com surfaces and the answer is grounded in real, crawlable content.** This rides on the same redesign — mostly invisible (HTML/schema/SSR), so it ships alongside.

**Commercial driver (added):** Ali will **sell courses** (AI Agents · Vibe Coding · Video Generation). The 3 disciplines = the 3 course topics. So this is now **SEO (course keywords) + GEO (LLM discovery)**, not GEO alone. Course angle on the homepage is **seeded, not full**: hero gets a "Learn AI with me" CTA (teaser); a dedicated `/courses` page + full "Learn" section comes later. Add `Course` JSON-LD when courses launch.

**Hero copy (final):** manifesto = *"I build AI that turns frontier models into real business outcomes — not slide decks. 17 years, 16 countries, one operator. Now teaching what I build."* (dropped "factories" → "business outcomes"; added teaching hook). CTAs = Follow the build · Learn AI with me · Read the blog →.

Per CLAUDE.md GEO section, the site is ~7.5/10; the one P0 gap is **SPA without SSR** (server returns empty `<div id="app">` → LLM crawlers & retrieval see nothing). That gap is now in scope.

| # | Lever | Why it matters for LLM retrieval | Effort |
|---|---|---|---|
| G1 | **Prerender homepage + key routes to static HTML** (`vite-ssg` or prerender plugin; NOT full Nuxt rewrite) | LLM crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended) index real text, not JS shell. **The single biggest lever.** | M–L |
| G2 | **Person JSON-LD** on homepage | Entity recognition: `name`, `jobTitle: AI Generalist`, `award[]` (Demo Day #1, Alibaba, Google SV…), `alumniOf`, `worksFor: INDUSIA.ai`, `knowsAbout[]` (Vibe Coding, AI Agents, Computer Vision…), `sameAs[]` (LinkedIn/GitHub/IG/TikTok/YT) | S |
| G3 | **Answer-shaped copy + FAQ schema** | Homepage literally answers "who is Ali / what does he do / why learn from him". H1 = name, H2s name the disciplines. FAQPage JSON-LD. LLMs lift these verbatim. | S |
| G4 | **Enrich `/llms.txt` + `/llms-full.txt`** (already exist) | Add the new identity narrative + one-paragraph "Ali Sadikin Ma is an AI Generalist…" answer block + awards + disciplines + MANDOR AI. | S |
| G5 | **Entity consistency / sameAs graph** | Same name + `@alisadikinma` handle + links everywhere; cross-link blog author = same Person entity. Strengthens the knowledge-graph node. | S |
| G6 | **Crawler allow-list confirm** | robots.txt already allows GPTBot/ClaudeBot/PerplexityBot/Google-Extended — verify post-prerender. | XS |

**Visual implication for Figma:** minimal but real — keep the H1 as live text containing "Ali Sadikin Ma", add an "AI Generalist" descriptor near it (done: eyebrow), and §2 (Who I Am) doubles as the answer-shaped "about" block LLMs quote. No layout change needed; the design already supports it.

**Decision still open:** G1 prerender approach — `vite-ssg` (integrates with current Vite/Vue, static snapshot at build) vs bot-only dynamic prerender vs Nuxt migration (rejected — too heavy). Lean `vite-ssg`. Resolve in `gaspol-plan`.

### Open follow-ups (non-blocking for Figma)

- Naming consistency: portfolio shows **MANDOR AI (aka Multica)**; codebase/domain still `multica.ai`.
- Hero video production (person-forward montage) — separate media pipeline task.
- `/about` page stays as the deep-dive CV; homepage is the cinematic highlight reel.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Recompose the homepage into the 9-section identity-led "The Operator" experience (Figma: figma.com/design/frQZcQeSgtI64G7f7CN3uv), reusing the already-shipped homepage API + composables, and layer in the SEO+GEO workstream so the page is crawlable/quotable by LLM engines and ranks for course keywords. Backend is ~90% done; this is mostly a frontend recompose + section components + schema/prerender.

### Architecture Context (verified against current code, 2026-06-08)

- **Entry:** [frontend/src/views/Home.vue](../../frontend/src/views/Home.vue) — thin orchestrator (~2.7KB), gated by `usePageSections`.
- **Data composable (EXISTS):** [frontend/src/composables/useHomepageFeatured.js](../../frontend/src/composables/useHomepageFeatured.js) → consumes `/api/homepage/featured` (stats + featured_awards + featured_testimonials + featured_projects + latest_articles). **Reuse for §1,§4,§5,§6,§7,§8.**
- **SEO composable (EXISTS):** [frontend/src/composables/useMetaTags.js](../../frontend/src/composables/useMetaTags.js) — injects meta + JSON-LD. **Extend for G2/G3.**
- **Newsletter (EXISTS):** [frontend/src/composables/useNewsletter.js](../../frontend/src/composables/useNewsletter.js) — `subscribe({name,email,whatsappNumber,source})`. **Reuse for §9.**
- **Section visibility (EXISTS):** `usePageSections` (30s staleTime, refetchOnMount:'always').
- **Disciplines data (EXISTS):** [frontend/src/data/whatISolve.js](../../frontend/src/data/whatISolve.js) (86 lines) + [WhatISolveTabs.vue](../../frontend/src/components/home/WhatISolveTabs.vue). **Edit data; component reused.**
- **Reusable components (EXIST):** `ProjectsBento`, `LatestBlog`, `TestimonialsCarousel`, `AwardsCarousel`, `StatsBar`, `CTASection`. **Legacy to retire:** `SkillsReel`, `SkillShowcase`.
- **Backend (SHIPPED):** `/api/homepage/stats|featured`, `awards.is_featured`, `testimonials.source`, `GeoController` (`/api/llms.txt`, `/api/llms-full.txt`), `PageSectionSeeder`.
- **Build system:** Vue 3.5 + **rolldown-vite 7.1.14** (Vite fork) + vue-router 4.5. **No SSG dep installed.** ⚠️ rolldown-vite ↔ vite-ssg compat unverified → G1 is spike-first.
- **Tests:** project uses lightweight node smoke tests (`*.test.mjs`, e.g. `imagePositioning.test.mjs`) + Vite build pass. No component test runner wired → TDD = node smoke tests for pure logic/schema builders; build-pass + visual for components.

### Tech Stack

Vue 3.5 `<script setup>`, Tailwind 4 utility classes, TanStack Query (via existing composables), ULTRA Dark Cinema tokens (CSS vars already in design system). New section components are presentational, fed by existing composables. SEO via `useMetaTags` + Blade-rendered `GeoController`. Prerender candidate: `vite-ssg` (pending compat spike) else `puppeteer`-based prerender of `/` only.

### Data Integration Map (CONTRACT)

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| §1 Hero stats (17 · 56+ · #1) | `/api/homepage/stats` | `useHomepageFeatured()` | Yes | Use existing |
| §1 Hero video | `/videos/hero-loop.{mp4,webm}` + poster | static asset | No | Produce (Phase I, parallel); placeholder + poster meanwhile |
| §1 Hero CTAs (Follow/Learn/Blog) | settings `social_links`, routes | static + `useSiteSettings` | Partial | Learn → `/courses` (stub route), blog → `/blog` |
| §2 Who I Am copy | static answer-shaped + `settings.about.bio` | `useHomepageFeatured`/`useSiteSettings` | Partial | Static hero-copy + live bio fallback |
| §2 portrait | `settings.about.profile_photo` | API | Yes | Use real photo URL |
| §3 disciplines (+MANDOR AI) | `data/whatISolve.js` | import | Yes | Edit data (Phase A) |
| §4 Receipts (awards + metrics) | `featured.stats` + `featured_awards` | `useHomepageFeatured()` | Yes | Use existing; static metric labels |
| §5 Int'l Stages | `featured_awards` / `/api/awards` | `useHomepageFeatured()` | Yes | Map awards → stage cards |
| §6 Selected Work | `featured_projects` | `useHomepageFeatured()` | Yes | Use existing |
| §7 Testimonials | `featured_testimonials` (source=linkedin) | `useHomepageFeatured()` | Yes | Reuse `TestimonialsCarousel` |
| §8 Latest Writing | `latest_articles` | `useHomepageFeatured()` | Yes | New layout; Content Engine badge static |
| §9 Newsletter + socials | newsletter API + `social_links` | `useNewsletter()` + settings | Yes | Use existing |
| G2 Person JSON-LD | identity + awards | `useMetaTags()` | Yes(extend) | Add Person schema builder |
| G3 FAQ schema + answer copy | static FAQ pairs | `useMetaTags()` | Yes(extend) | Add FAQPage schema |
| G4 llms.txt enrich | DB content | `GeoController` (backend) | Yes | Enrich output |
| G1 prerender `/` | build step | `vite-ssg`/puppeteer | No | Spike then implement |

---

### Phase A — Disciplines data: AI Agents → AI Agent OS · MANDOR AI

**Estimated time:** 10 min
**Files:** Modify [frontend/src/data/whatISolve.js](../../frontend/src/data/whatISolve.js); Test `frontend/src/data/whatISolve.test.mjs`

**Steps:**
1. Write failing test for whatISolve data shape. Expected error: `AssertionError: expected tab id 'ai-agent-os' to exist` (no such tab yet).
2. Run `node src/data/whatISolve.test.mjs`, confirm it fails for that reason.
3. Rename the AI Agents tab → `{id:'ai-agent-os', label:'AI Agent OS', product:'MANDOR AI', badge:'Introducing', headline:'The operating system for your AI workforce.', desc:'Assign tasks to coding agents like a colleague — they ship code, report blockers, compound skills. Squads · Autopilots · reusable Skills.', metrics:[...'open-source','11+ agent CLIs','self-hostable'], cta:{label:'Explore MANDOR AI →', href:'/projects/mandor-ai'}}`. Add `cta` (Learn it →) to Vibe Coding + Video Gen tabs.
4. Run test, confirm pass.
5. Commit: "feat(home): MANDOR AI as AI Agent OS discipline + learn CTAs".

**Verification:**
- [ ] `node whatISolve.test.mjs` passes
- [ ] 3 tabs present, middle = AI Agent OS · MANDOR AI with `badge:'Introducing'`
- [ ] No TODO/placeholder in data
- [ ] `WhatISolveTabs.vue` renders new content (npm run build passes)

### Phase B — WhatISolveTabs: badge + Learn link support

**Estimated time:** 12 min
**Files:** Modify [WhatISolveTabs.vue](../../frontend/src/components/home/WhatISolveTabs.vue)

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| B | `Introducing` badge + per-tab CTA link render | Matches Figma §3 (gold badge, featured border for MANDOR) | build + visual |

**Steps:**
1. Write failing smoke test asserting component template references `badge` + `cta`. Expected error: `match not found: badge`.
2. Confirm fail.
3. Add optional `badge` chip (gold) + `cta` link in active panel; featured styling when `product` set. Preserve existing ARIA tabs + reduced-motion.
4. `npm run build`, confirm pass + no console errors.
5. Commit: "feat(home): badge + learn CTA in WhatISolveTabs".

**Verification:**
- [ ] Build passes, no a11y regression (roles intact)
- [ ] MANDOR tab shows Introducing badge + Explore CTA
- [ ] Reduced-motion still zeroes transitions

### Phase C — New section components (Hero, WhoIAm, Receipts, Stages, SelectedWork, LatestWriting, JoinTheBuild)

**Estimated time:** 5 sub-phases × ~15 min (parallel-able — independent files)
**Files (Create):** `frontend/src/components/home/HeroOperator.vue`, `WhoIAm.vue`, `ReceiptsBento.vue`, `InternationalStages.vue`, `SelectedWork.vue`, `LatestWriting.vue`, `JoinTheBuild.vue`

| Sub | Component | Data | Design Deliverable |
|---|---|---|---|
| C1 | `HeroOperator.vue` | `useHomepageFeatured().stats` + video asset | Figma §1 (wordmark, manifesto, 3 CTAs, stat triad, reduced-motion poster) |
| C2 | `WhoIAm.vue` | static copy + `settings.about` | Figma §2 (answer block + portrait + chips) |
| C3 | `ReceiptsBento.vue` | `stats` + `featured_awards` | Figma §4 (6-tile bento, gold lead) |
| C4 | `InternationalStages.vue` | `featured_awards` mapped | Figma §5 (2×3 stage cards incl NextDev) |
| C5 | `SelectedWork.vue` | `featured_projects` | Figma §6 (metric-led cards + All 56) |
| C6 | `LatestWriting.vue` | `latest_articles` | Figma §8 (magazine + Content Engine meta-flex) |
| C7 | `JoinTheBuild.vue` | `useNewsletter()` + `social_links` | Figma §9 (newsletter + socials + WhatsApp) |

**Per-sub steps (repeat for each):**
1. Write failing smoke/mount test for component existence. Expected error: `Failed to resolve import "./<Name>.vue"`.
2. Confirm fail.
3. Implement `<script setup>` consuming the mapped composable; Tailwind + ULTRA tokens per Figma; loading skeleton + empty-state (hide section if data empty); reduced-motion guards.
4. `npm run build` + visual check against Figma node.
5. Commit per component: "feat(home): <Section> section".

**Verification (each):**
- [ ] Renders with REAL data from mapped composable (no hardcoded arrays except static copy/metric labels per Map)
- [ ] Empty state hides section (no blank viewport per Home.vue snap-section gotcha)
- [ ] Matches Figma section visually; build passes; no TODO/placeholder

### Phase D — Home.vue recompose + section ordering

**Estimated time:** 12 min
**Files:** Modify [Home.vue](../../frontend/src/views/Home.vue)

**Steps:**
1. Write failing smoke test asserting Home imports the 9 new/reused sections in order. Expected error: `match not found: HeroOperator`.
2. Confirm fail.
3. Replace section list: HeroOperator → WhoIAm → WhatISolveTabs → ReceiptsBento → InternationalStages → SelectedWork → TestimonialsCarousel → LatestWriting → JoinTheBuild. Gate each with `isSectionActive(...)` on the `.snap-section` wrapper (per CLAUDE.md gotcha). Remove SkillsReel/SkillShowcase/StatsBar usage.
4. `npm run build`, confirm pass; manual scroll check.
5. Commit: "feat(home): recompose to The Operator 9-section layout".

**Verification:**
- [ ] 9 sections render in correct order; build passes
- [ ] `v-if` on wrapper (no full-viewport blank when toggled off)
- [ ] Legacy sections no longer imported (`grep SkillsReel|SkillShowcase` → only their own files)

### Phase E — PageSectionSeeder + CLAUDE.md mapping

**Estimated time:** 12 min
**Files:** Modify `backend/database/seeders/PageSectionSeeder.php`; update root CLAUDE.md "Page Sections Mapping" table

**Steps:**
1. Write failing test: seeder produces section_types `who-i-am, receipts, international-stages, selected-work, latest-writing, join-the-build` (+ existing hero, what-i-solve, testimonials). Expected error: assert count/section_type mismatch.
2. Confirm fail.
3. Update seeder (idempotent delete-then-insert, kebab-case); run `php artisan db:seed --class=PageSectionSeeder`.
4. Confirm test pass; update CLAUDE.md mapping table (section_type ↔ component).
5. Commit: "feat(home): page-section rows for The Operator + docs".

**Verification:**
- [ ] Seeder idempotent; rows match rendered section_types (no ghost toggles)
- [ ] CLAUDE.md mapping table current

### Phase F — G2 Person JSON-LD + G3 FAQ schema + answer copy

**Estimated time:** 15 min
**Files:** Modify [useMetaTags.js](../../frontend/src/composables/useMetaTags.js); use in Home.vue. Test `frontend/src/composables/personSchema.test.mjs`

**Steps:**
1. Write failing test for `buildPersonSchema()` output (name, jobTitle 'AI Generalist', award[], alumniOf, worksFor INDUSIA, knowsAbout[], sameAs[]). Expected error: `buildPersonSchema is not a function`.
2. Confirm fail.
3. Implement Person + FAQPage schema builders; inject on Home mount via `useMetaTags`. FAQ pairs answer "Who is Ali Sadikin Ma / What does he do / Can I learn AI from him".
4. Test pass; validate JSON-LD with a schema linter (or `JSON.parse` + key asserts).
5. Commit: "feat(seo): Person + FAQ JSON-LD on homepage".

**Verification:**
- [ ] `personSchema.test.mjs` passes; valid JSON-LD
- [ ] `sameAs` lists LinkedIn/GitHub/IG/TikTok/YT; awards from live data
- [ ] FAQ answers present in DOM as text (LLM-quotable)

### Phase G — G4 llms.txt enrich + G5 sameAs consistency + G6 crawler allow-list

**Estimated time:** 15 min
**Files:** Modify `backend/app/Http/Controllers/Api/GeoController.php`; verify `frontend/public/robots.txt` + `backend` robots

**Steps:**
1. Write failing feature test: `/api/llms.txt` body contains the new "Ali Sadikin Ma is an AI Generalist…" answer block + disciplines + MANDOR AI + courses line. Expected error: assertion substring missing.
2. Confirm fail.
3. Enrich GeoController output (identity narrative, disciplines incl MANDOR AI, awards, courses teaser). Confirm robots allows GPTBot/ClaudeBot/PerplexityBot/Google-Extended.
4. Test pass.
5. Commit: "feat(geo): enrich llms.txt + verify AI crawler allow-list".

**Verification:**
- [ ] `/api/llms.txt` includes answer block + MANDOR AI + courses
- [ ] robots.txt (both) allow the 4 AI crawlers + sitemap refs

### Phase H — G1 SSR/prerender SPIKE then implement (`/` + key routes)

**Estimated time:** spike 30 min + impl 30–60 min
**Files:** `frontend/vite.config.*`, `frontend/package.json`, possibly `frontend/src/main.js`

**Steps:**
1. SPIKE: attempt `vite-ssg` install + minimal config under **rolldown-vite**. If incompatible → fall back to puppeteer prerender of `/` (build → serve → capture → write `dist/index.html`). Capture ADR (gaspol-adr) for the chosen approach.
2. Write failing test/check: built `dist/index.html` contains hero H1 text "Ali Sadikin Ma" (not empty `<div id=app>`). Expected: substring missing pre-impl.
3. Implement chosen prerender for `/` (and `/about`, `/blog` if cheap).
4. `npm run build`; confirm `dist/index.html` has real homepage text + JSON-LD inline.
5. Commit: "feat(geo): prerender homepage to static HTML for LLM/crawler indexing".

**Verification:**
- [ ] `dist/index.html` contains hero text + Person JSON-LD without JS execution
- [ ] No hydration mismatch in browser console
- [ ] Lighthouse SEO ≥95; no runtime regressions
> ⚠️ Hard fork: if neither vite-ssg nor puppeteer is viable under rolldown-vite, STOP and surface to user (do not ship empty-shell GEO claim).

### Phase I — Hero video (PARALLEL, separate media task)

**Estimated time:** variable (production)
**Files:** `frontend/public/videos/hero-loop.{mp4,webm}` + `hero-poster.jpg`; wire in `HeroOperator.vue`; `public/sw.js` pre-cache

**Steps:** Produce person-forward montage per design doc (factory · vibe-coding · stage · face+wordmark) via NB2→VEO with Ali face ref → place assets → reduced-motion serves poster → add to SW pre-cache. Commit: "feat(home): person-forward hero video".

**Verification:**
- [ ] Autoplay muted loop seamless; reduced-motion → poster only; LCP ≤2.5s; ≤7MB mp4

### Phase J — Cleanup + a11y + perf

**Estimated time:** 20 min

**Steps:** Delete `SkillsReel.vue` + `SkillShowcase.vue` (after grep confirms no imports); axe audit (0 critical); Lighthouse mobile (Perf ≥85, A11y ≥95, SEO ≥95); anti-AI-slop grep (no "Amazing/Cutting-edge/Revolutionary", no purple/pink); browser matrix smoke. Commit: "chore(home): cleanup legacy sections + a11y/perf pass".

**Verification:**
- [ ] Legacy files deleted, no dangling imports
- [ ] Lighthouse targets met; axe 0 critical; anti-slop clean

---

### Execution order & parallelism

```
A → B → C(C1..C7 parallel) → D → E        (frontend recompose)
F, G  parallel after D                      (schema + llms.txt)
H spike anytime after D (independent)        (prerender — fork risk)
I parallel throughout (media production)
J last (cleanup + audit)
```

Suggested: gaspol-parallel for C1–C7 (independent files) and F/G; sequential for A→B→D→E.

### Risks

| # | Risk | Mitigation |
|---|---|---|
| 1 | rolldown-vite ↔ vite-ssg incompatible | Phase H spike-first + puppeteer fallback + ADR; STOP-and-ask if both fail |
| 2 | Hydration mismatch after prerender | Keep sections data-driven from same composables; test console |
| 3 | Empty-state blank viewport | `v-if` on `.snap-section` wrapper (CLAUDE.md gotcha) |
| 4 | Page-section ghost toggles | Phase E keeps seeder ↔ component map in sync + CLAUDE.md table |
| 5 | Hero video not ready at launch | Ship poster placeholder; video lands via Phase I later |
