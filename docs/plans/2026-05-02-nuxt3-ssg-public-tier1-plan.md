# Nuxt 3 SSG Hybrid — Public Tier 1 Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations with the existing Laravel backend (`http://localhost/Portfolio_v2/backend/public/api` dev / `https://alisadikinma.com/api` production), porting from `frontend/src/components/`, and existing CI/CD at `.github/workflows/deploy.yml`. NEVER substitute placeholders. If a data source doesn't exist yet, STOP and ask the user.

**Companion design document:** [`2026-05-02-nuxt3-ssg-public-tier1.md`](2026-05-02-nuxt3-ssg-public-tier1.md) — read first for scope, architecture, decisions, and rationale. Skipping it means re-deriving choices already locked in via brainstorm.

**Plan created:** May 2, 2026
**Estimated effort:** 9 working days = 2 weeks
**Phases:** 6 (A-F)
**Total tasks:** ~50

---

## Goal

Stand up a second frontend project (`frontend-nuxt/`) using Nuxt 3 SSG mode that prerenders ~62 public Tier 1 routes (Home, About, Projects index + 56 detail, Awards, Gallery, Contact, 404) to static HTML, deployed alongside the existing Vue 3 SPA via Apache vhost split (`/admin/*` and `/login` → SPA, everything else → Nuxt SSG output). Outcome: AI crawlers (GPTBot, ClaudeBot, PerplexityBot, plus training scrapers) receive full HTML body when hitting public pages, signaling **author authority** so Ali is quoted accurately when ChatGPT/Claude/Gemini are asked about AI generalist work, projects, or expertise. Admin Vue SPA stays untouched — zero risk of regression to recent LinkedIn UI / Content Engine work.

## Architecture Context (from CLAUDE.md)

**Existing pieces this plan reuses:**

- **Backend API** (Laravel 12) — all Tier 1 endpoints already exist and are public:
  - `GET /api/settings/site` → SettingsController
  - `GET /api/settings/about` → SettingsController
  - `GET /api/projects` (+ `/api/projects/{slug}`) → ProjectController
  - `GET /api/awards` → AwardController
  - `GET /api/galleries` → GalleryController
  - `GET /api/posts` (Home teaser only — top 3) → PostController
  - `POST /api/contact` (throttle:3/15min) → ContactController
  - Public, no auth, CORS configured for `alisadikinma.com`
- **Tailwind 4 config** — `frontend/tailwind.config.js` (copy verbatim to `frontend-nuxt/`)
- **Design tokens** — `frontend/src/assets/styles/main.css` (Dark Cinema gold/cyan palette, fonts, glass effects)
- **Component library** — 17 base components in `frontend/src/components/base/` (BaseButton, BaseCard, BaseLightbox, BaseLoader, GlassCard, GradientBorderCard, etc.) — copy-port subset for Tier 1
- **Composables (29 total)** — only ~5 needed for Tier 1 public: `useGlobe.js`, `useCursorSparks.js`, `useScrollReveal.js`, `useVideoReveal.js`, plus replace `useMetaTags.js` with Nuxt's built-in `useSeoMeta`
- **Service worker** — `frontend/public/sw.js` (media cache; copy verbatim to `frontend-nuxt/public/`)
- **CI/CD** — `.github/workflows/deploy.yml` → `scripts/deploy.sh` (existing build pipeline; extend with parallel Nuxt job)

**Existing pieces this plan does NOT touch:**

- Vue 3 SPA admin (28 views: ContentEngine, LinkedInDraftDetail, LinkedInQueueList, etc.)
- Auth flow (Sanctum + localStorage JWT — admin owns this)
- Backend Laravel (zero schema/route changes)
- Content Engine pipeline (article-prep → article-write → article-score)
- LinkedIn carousel pipeline (/carousel-gen + LinkedInCarouselImageService)

**Existing CLAUDE.md GEO state (for verification baseline):**

- Score 7.5/10 (root CLAUDE.md "GEO" section)
- llms.txt + sitemap.xml + JSON-LD via HasSeoFields trait already shipped
- Remaining gap: SPA HTML body — **THIS plan closes it**

## Tech Stack

```
Framework:    Nuxt 3.x (latest stable May 2026, target 3.13+)
Output mode:  SSG via `nuxt generate` (no Node runtime at production)
Vue:          3.5 (same as SPA)
State:        @pinia/nuxt 0.5+ (mostly idle in SSG public)
Styling:      @nuxtjs/tailwindcss 6+ (Tailwind 4 config copy)
HTTP:         $fetch (built-in, build-time fetch from Laravel API)
i18n:         @nuxtjs/i18n 9+ (vue-i18n 11 compat)
3D Globe:     TresJS via <ClientOnly> wrapper
Animation:    GSAP 3 (npm direct, no module needed)
Image:        @nuxt/image (optional — registers backend storage as external domain)
SEO:          @nuxtjs/seo (umbrella: sitemap + robots + schema-org + og-image)
Testing:      Vitest + @nuxt/test-utils (unit/component) + Playwright (E2E, reuse SPA setup)
Auth:         NONE (admin SPA owns auth)
Test runner:  Existing Playwright config at frontend/playwright.config.js can be reused for cross-app E2E
```

---

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Home: site settings | `GET /api/settings/site` | `$fetch` build-time | Yes (SettingsController) | Use directly |
| Home: featured projects | `GET /api/projects?featured=1` | `$fetch` build-time | Yes (ProjectController) | Use directly |
| Home: latest blog teasers | `GET /api/posts?per_page=3` | `$fetch` build-time | Yes (PostController) | Use directly (top 3 only) |
| About: bio + skills + experience | `GET /api/settings/about` | `$fetch` build-time | Yes (SettingsController) | Use directly |
| Projects index | `GET /api/projects?per_page=100` | `$fetch` build-time | Yes (ProjectController) | Use directly |
| Projects detail (×56) | `GET /api/projects/{slug}` per slug | `$fetch` per slug in `nitro.prerender.routes` callback | Yes (ProjectController.show) | Use directly |
| Awards | `GET /api/awards` | `$fetch` build-time | Yes (AwardController) | Use directly |
| Gallery | `GET /api/galleries` | `$fetch` build-time | Yes (GalleryController) | Use directly |
| Contact form POST | `POST /api/contact` | client `$fetch` runtime | Yes (ContactController) | Use directly |
| Person JSON-LD (About) | `useSeoMeta` + script tag | derived from `/api/settings/about` | Generate | Build from existing `HasSeoFields` payload |
| CreativeWork JSON-LD (Project) | `useSeoMeta` + script tag | derived from `/api/projects/{slug}` | Generate | Build from existing `HasSeoFields` payload — `schema_markup` column already on table |
| Sitemap.xml | `@nuxtjs/sitemap` module + prerender list | auto-generated | Generate | Module reads prerendered route list |
| robots.txt | static `public/robots.txt` | n/a | Copy | Copy from `frontend/public/robots.txt` |
| llms.txt | static `public/llms.txt` | n/a | Copy | Copy from `frontend/public/llms.txt` |
| Service worker | static `public/sw.js` | n/a | Copy | Copy from `frontend/public/sw.js` (scope `/`) |
| Tailwind config | `tailwind.config.js` | n/a | Copy | Copy from `frontend/tailwind.config.js` |
| Design tokens CSS | `assets/styles/main.css` | imported in app.vue | Copy | Copy from `frontend/src/assets/styles/main.css` |
| Locale JSONs (en/id) | `i18n/locales/*.json` | `@nuxtjs/i18n` | Copy if exists, else create | Verify path in frontend; current SPA uses vue-i18n 11 — locate locale files via grep |
| 3D globe component | `composables/useGlobe.js` + TresJS components | wrap `<ClientOnly>` | Yes | Port + wrap |
| Cursor sparks effect | `composables/useCursorSparks.js` | composable | Yes | Port as-is |
| Scroll reveal animations | `composables/useScrollReveal.js` | composable | Yes | Port as-is |
| Theme toggle | `stores/theme.js` (Pinia) | `@pinia/nuxt` store | Yes | Port if Tier 1 needs dark/light toggle in nav |
| Brand assets (logo, favicon) | `frontend/public/*.{ico,png,svg}` | n/a | Copy | Copy verbatim |

**Contract:** every "Use directly" / "Copy" entry above is a real integration the executor wires in Phase B–D. NO placeholder fetches. If any endpoint returns a shape the executor doesn't expect, STOP and ask — do not stub.

---

## Phase A: Project Setup & Configuration (1 day)

**Estimated time:** 4-6 hours
**Goal:** Initialize `frontend-nuxt/` with Nuxt 3, dependencies installed, config wired for SSG mode + prerender + module list. Smoke test passes (`npm run dev` shows blank Nuxt page on port 3001).

### A1: Create frontend-nuxt scaffold + dependencies (45 min)

**Files:**
- Create: `frontend-nuxt/package.json`
- Create: `frontend-nuxt/nuxt.config.ts`
- Create: `frontend-nuxt/app.vue`
- Create: `frontend-nuxt/tsconfig.json`
- Create: `frontend-nuxt/.gitignore`
- Modify: root `.gitignore` (add `frontend-nuxt/.nuxt`, `frontend-nuxt/.output`, `frontend-nuxt/node_modules`)

**Steps:**
1. Write failing test: create `frontend-nuxt/tests/smoke.spec.ts` asserting `import { defineNuxtConfig } from 'nuxt/config'` resolves. Expected error: `Cannot find module 'nuxt/config'` (deps not installed yet).
2. Run `npx vitest run tests/smoke.spec.ts` from `frontend-nuxt/`, confirm fail.
3. Init Nuxt project: `cd frontend-nuxt && npx nuxi@latest init . --no-git-init --packageManager npm --gitInit false` (interactive — answer "yes" to overwrite, "no" to git init since we're inside an existing repo).
4. Install dependencies:
   ```bash
   npm install -D @nuxtjs/tailwindcss @pinia/nuxt @nuxt/image
   npm install -D @nuxtjs/i18n @nuxtjs/seo
   npm install -D @nuxt/test-utils vitest happy-dom
   npm install gsap
   ```
   (Skip `@tresjs/nuxt` for now — add in Phase C only if Home keeps the globe.)
5. Run smoke test again, confirm pass.
6. Commit: `feat: initialize frontend-nuxt scaffold with Nuxt 3 + deps`.

**Verification:**
- [ ] `frontend-nuxt/package.json` lists Nuxt 3.x in dependencies
- [ ] `npm install` in `frontend-nuxt/` completes with zero errors
- [ ] `npm run build` (default Nuxt) completes (will produce blank `.output/`)
- [ ] No placeholder/TODO comments in new config files
- [ ] Root `.gitignore` correctly excludes `frontend-nuxt/.nuxt`, `.output`, `node_modules`

### A2: Configure Nuxt for SSG + module list (45 min)

**Files:**
- Modify: `frontend-nuxt/nuxt.config.ts`
- Create: `frontend-nuxt/.env.example`
- Modify: `frontend-nuxt/app.vue` (replace default with NuxtPage placeholder)

**Steps:**
1. Write failing test: `frontend-nuxt/tests/config.spec.ts` asserts `nuxtConfig.ssr === true`, `nuxtConfig.modules.includes('@nuxtjs/tailwindcss')`, `nuxtConfig.runtimeConfig.public.apiBase` is defined. Expected error: `expected undefined to be 'http://...' ` (config not written yet).
2. Run vitest, confirm fail.
3. Write `nuxt.config.ts` with:
   ```typescript
   export default defineNuxtConfig({
     ssr: true,
     modules: [
       '@nuxtjs/tailwindcss',
       '@pinia/nuxt',
       '@nuxtjs/i18n',
       '@nuxt/image',
       '@nuxtjs/seo'
     ],
     runtimeConfig: {
       public: {
         apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost/Portfolio_v2/backend/public/api',
         siteUrl: process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:3001'
       }
     },
     nitro: {
       prerender: {
         crawlLinks: true,
         routes: ['/'],
         ignore: ['/admin/**', '/login', '/blog', '/blog/**', '/api/**']
       }
     },
     image: {
       domains: ['alisadikinma.com', 'localhost']
     },
     site: {
       url: process.env.NUXT_PUBLIC_SITE_URL || 'https://alisadikinma.com',
       name: 'Ali Sadikin Ma — AI Generalist Expert'
     },
     devServer: { port: 3001 }
   })
   ```
4. Write `.env.example` documenting all 2 public env vars.
5. Run vitest, confirm pass.
6. Run `npm run dev` — confirm dev server boots on port 3001 with no errors.
7. Commit: `feat: configure Nuxt 3 SSG mode + module list + dev port 3001`.

**Verification:**
- [ ] `npm run dev` boots without errors on port 3001 (does not collide with frontend SPA on 5173)
- [ ] `curl http://localhost:3001` returns HTML (not 404)
- [ ] `tsc --noEmit` passes for `nuxt.config.ts`
- [ ] All listed modules resolve in `node_modules/` (no peer-dep warnings)

### A3: Copy Tailwind config + design tokens (30 min)

**Files:**
- Create: `frontend-nuxt/tailwind.config.js` (copy from `frontend/tailwind.config.js`)
- Create: `frontend-nuxt/assets/css/main.css` (copy from `frontend/src/assets/styles/main.css`)
- Modify: `frontend-nuxt/nuxt.config.ts` (add `css: ['~/assets/css/main.css']`)

**Steps:**
1. Write failing test: `frontend-nuxt/tests/styles.spec.ts` asserts compiled HTML at `/` includes Tailwind utility classes (e.g., `class="container mx-auto"`). Expected error: HTML not yet styled.
2. Run vitest, confirm fail.
3. Copy `frontend/tailwind.config.js` → `frontend-nuxt/tailwind.config.js`. Update `content:` paths to point at `frontend-nuxt/` directories: `['./components/**/*.{vue,js,ts}', './pages/**/*.vue', './app.vue']`.
4. Copy `frontend/src/assets/styles/main.css` → `frontend-nuxt/assets/css/main.css`. Verify no `@import` paths break (likely fine — Tailwind 4 directives are self-contained).
5. Edit `nuxt.config.ts` to register CSS: add `css: ['~/assets/css/main.css']`.
6. Update `app.vue` with a marker class for the test:
   ```vue
   <template><div class="container mx-auto p-8"><NuxtPage /></div></template>
   ```
7. Run `npm run dev` and visit `http://localhost:3001` — Tailwind classes should compile.
8. Run vitest, confirm pass.
9. Commit: `feat: copy Tailwind 4 config + design tokens to frontend-nuxt`.

**Verification:**
- [ ] `npm run dev` compiles Tailwind (no PostCSS errors in console)
- [ ] Browser DevTools shows compiled utility classes
- [ ] `assets/css/main.css` design tokens (`--accent-gold`, `--accent-cyan`, etc.) accessible via CSS vars
- [ ] No font 404s in network tab (fonts copied or loaded from same source as SPA)

### A4: Copy locale JSONs + configure i18n (1 hr)

**Files:**
- Create: `frontend-nuxt/i18n/locales/en.json` (copy from frontend SPA)
- Create: `frontend-nuxt/i18n/locales/id.json` (copy from frontend SPA)
- Modify: `frontend-nuxt/nuxt.config.ts` (add i18n config)

**Steps:**
1. Locate existing locale files: `find frontend/src -name "*.json" -path "*locale*"` or grep for `vue-i18n` usage in `frontend/src/main.js` to find the import path.
2. Write failing test: `frontend-nuxt/tests/i18n.spec.ts` asserts `useI18n().t('common.home')` returns localized string for `en` and `id` locales. Expected error: i18n not configured.
3. Run vitest, confirm fail.
4. Copy locale JSONs to `frontend-nuxt/i18n/locales/{en,id}.json`. If frontend doesn't have separate JSON files (i18n inline in components), document this and create minimal stub locales for Tier 1 keys (nav, footer, contact form labels, error messages).
5. Configure i18n in `nuxt.config.ts`:
   ```typescript
   i18n: {
     locales: [
       { code: 'en', file: 'en.json', name: 'English' },
       { code: 'id', file: 'id.json', name: 'Indonesia' }
     ],
     defaultLocale: 'en',
     strategy: 'no_prefix',  // Don't prefix URLs with /en or /id
     detectBrowserLanguage: { useCookie: true, cookieKey: 'i18n_redirected' }
   }
   ```
6. Run vitest, confirm pass.
7. Commit: `feat: configure i18n for en/id locales`.

**Verification:**
- [ ] `useI18n().t('...')` works in pages
- [ ] Locale switch via cookie persists across reload
- [ ] No prefix in URL (per `no_prefix` strategy — same UX as SPA)

---

## Phase B: Page Scaffolding + Build-Time Data Fetch (2 days)

**Estimated time:** 8-10 hours
**Goal:** All 8 page types created, each fetches from Laravel API at build time, `nuxt generate` emits HTML for ~62 routes (1 home + 1 about + 1 projects index + 56 projects detail + 1 awards + 1 gallery + 1 contact + 1 404).

### B1: Page scaffold — static pages first (1 hr)

**Files:**
- Create: `frontend-nuxt/pages/index.vue`
- Create: `frontend-nuxt/pages/about.vue`
- Create: `frontend-nuxt/pages/contact.vue`
- Create: `frontend-nuxt/pages/[...catchall].vue` (404)

**Steps:**
1. Write failing test: `frontend-nuxt/tests/pages-static.spec.ts` asserts each route returns HTML containing `<h1>` after `nuxt generate`. Expected error: pages don't exist.
2. Run vitest, confirm fail.
3. Create minimal `pages/index.vue`:
   ```vue
   <template>
     <main>
       <h1>Ali Sadikin Ma</h1>
       <p>AI Generalist Expert</p>
     </main>
   </template>
   <script setup>
   useSeoMeta({ title: 'Home', description: 'AI Generalist Expert portfolio' })
   </script>
   ```
4. Create `pages/about.vue` with placeholder `<h1>About</h1>` (real content in Phase C port).
5. Create `pages/contact.vue` with placeholder `<h1>Contact</h1>`.
6. Create `pages/[...catchall].vue` for 404:
   ```vue
   <template><main><h1>404 — Not Found</h1></main></template>
   <script setup>
   setResponseStatus(404)
   useSeoMeta({ title: '404 Not Found' })
   </script>
   ```
7. Run `npm run generate` — should emit `dist/index.html`, `dist/about/index.html`, etc.
8. Run vitest, confirm pass.
9. Commit: `feat: scaffold static page placeholders with useSeoMeta`.

**Verification:**
- [ ] `npm run generate` produces `.output/public/index.html` with `<h1>` content
- [ ] All 4 static routes accessible via `npm run preview`
- [ ] `<title>` tag rendered in HTML head

### B2: Page scaffold — list pages with build-time fetch (1.5 hr)

**Files:**
- Create: `frontend-nuxt/pages/projects/index.vue`
- Create: `frontend-nuxt/pages/awards.vue`
- Create: `frontend-nuxt/pages/gallery.vue`

**Steps:**
1. Write failing test: `frontend-nuxt/tests/pages-list.spec.ts` asserts `<curl http://localhost:3001/projects | grep -c "data-project-id"` returns ≥1 (matches existence of project cards). Expected error: pages don't fetch.
2. Run vitest with backend running, confirm fail.
3. Create `pages/projects/index.vue`:
   ```vue
   <template>
     <main>
       <h1>Projects</h1>
       <ul>
         <li v-for="p in data?.data" :key="p.id" :data-project-id="p.id">
           <NuxtLink :to="`/projects/${p.slug}`">{{ p.title }}</NuxtLink>
         </li>
       </ul>
     </main>
   </template>
   <script setup>
   const config = useRuntimeConfig()
   const { data } = await useFetch(`${config.public.apiBase}/projects`, {
     query: { per_page: 100 },
     key: 'projects-index'
   })
   useSeoMeta({ title: 'Projects', description: 'Portfolio of AI/automation work by Ali Sadikin' })
   </script>
   ```
4. Create `pages/awards.vue` with same pattern fetching `/api/awards`.
5. Create `pages/gallery.vue` with same pattern fetching `/api/galleries`.
6. Run `npm run dev` — visit each page, confirm data renders (with backend running on XAMPP).
7. Run `npm run generate` — confirm dist HTML contains expected list markup.
8. Run vitest, confirm pass.
9. Commit: `feat: list pages with build-time fetch (projects/awards/gallery)`.

**Verification:**
- [ ] Backend XAMPP running, `npm run generate` succeeds
- [ ] Generated `dist/projects/index.html` contains 56+ `<a href="/projects/{slug}">` links
- [ ] No `loading...` markers in static HTML (data baked at build)
- [ ] Network tab on `npm run preview` shows zero API calls (all data inline)

### B3: Project detail dynamic route + nitro.prerender callback (2 hr)

**Files:**
- Create: `frontend-nuxt/pages/projects/[slug].vue`
- Modify: `frontend-nuxt/nuxt.config.ts` (add prerender hook)

**Steps:**
1. Write failing test: `frontend-nuxt/tests/projects-detail.spec.ts` asserts `nuxt generate` produces `.output/public/projects/{slug}/index.html` for at least 10 known slugs. Expected error: dynamic route not generating.
2. Run vitest, confirm fail.
3. Create `pages/projects/[slug].vue`:
   ```vue
   <template>
     <article v-if="data?.data">
       <h1>{{ data.data.title }}</h1>
       <div v-html="data.data.content" />
     </article>
   </template>
   <script setup>
   const route = useRoute()
   const config = useRuntimeConfig()
   const { data, error } = await useFetch(`${config.public.apiBase}/projects/${route.params.slug}`)
   if (error.value) throw createError({ statusCode: 404, statusMessage: 'Project not found' })
   useSeoMeta({
     title: data.value?.data?.meta_title || data.value?.data?.title,
     description: data.value?.data?.meta_description,
     ogImage: data.value?.data?.og_image
   })
   </script>
   ```
4. Add prerender hook in `nuxt.config.ts`:
   ```typescript
   hooks: {
     async 'nitro:config'(nitroConfig) {
       const apiBase = process.env.NUXT_PUBLIC_API_BASE || 'http://localhost/Portfolio_v2/backend/public/api'
       try {
         const res = await $fetch(`${apiBase}/projects?per_page=100`)
         const slugs = (res.data || []).map(p => `/projects/${p.slug}`)
         nitroConfig.prerender.routes.push(...slugs)
         console.log(`[Nuxt prerender] Added ${slugs.length} project routes`)
       } catch (err) {
         console.error('[Nuxt prerender] Failed to fetch project slugs:', err.message)
         throw err  // Fail build — don't ship stale or empty
       }
     }
   }
   ```
5. Run `npm run generate` — confirm log line `Added 56 project routes` and `.output/public/projects/{slug}/index.html` exists for sample slugs.
6. Run vitest, confirm pass.
7. Commit: `feat: project detail SSG with nitro.prerender slug callback`.

**Verification:**
- [ ] Build log shows `Added N project routes` where N matches `SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL`
- [ ] `.output/public/projects/{slug}/index.html` exists for at least 10 sample slugs
- [ ] Each project HTML contains the project's actual `<h1>title</h1>` (not placeholder)
- [ ] Build fails loudly if backend is unreachable (no silent empty output)

### B4: Home page with multi-source fetch + JSON-LD Person (1.5 hr)

**Files:**
- Modify: `frontend-nuxt/pages/index.vue`

**Steps:**
1. Write failing test: assert Home HTML contains both `featured project` data AND `<script type="application/ld+json">` with `"@type":"Person"`. Expected error: not yet wired.
2. Run vitest, confirm fail.
3. Update `pages/index.vue` to fetch site settings + featured projects + 3 latest posts:
   ```vue
   <script setup>
   const config = useRuntimeConfig()
   const [{ data: site }, { data: featured }, { data: latest }] = await Promise.all([
     useFetch(`${config.public.apiBase}/settings/site`, { key: 'site' }),
     useFetch(`${config.public.apiBase}/projects`, { query: { featured: 1 }, key: 'featured' }),
     useFetch(`${config.public.apiBase}/posts`, { query: { per_page: 3 }, key: 'latest-posts' })
   ])

   useHead({
     script: [{
       type: 'application/ld+json',
       innerHTML: JSON.stringify({
         '@context': 'https://schema.org',
         '@type': 'Person',
         name: 'Ali Sadikin Ma',
         jobTitle: 'AI Generalist Expert',
         url: 'https://alisadikinma.com',
         sameAs: site.value?.data?.social_links || []
       })
     }]
   })
   </script>
   ```
4. Run `npm run generate`, inspect HTML, confirm Person schema present.
5. Run vitest, confirm pass.
6. Commit: `feat: home page with site/featured/latest fetch + Person JSON-LD`.

**Verification:**
- [ ] Home HTML contains valid Person JSON-LD (validate via [validator.schema.org](https://validator.schema.org/))
- [ ] Featured projects render as actual cards (not placeholder)
- [ ] 3 latest blog teasers visible

### B5: Contact form client-side POST (1 hr)

**Files:**
- Modify: `frontend-nuxt/pages/contact.vue`

**Steps:**
1. Write failing test: Playwright e2e asserts form submission POSTs to `/api/contact`. Expected error: form not wired.
2. Run Playwright, confirm fail.
3. Implement contact form with `$fetch` client-side POST:
   ```vue
   <script setup>
   const config = useRuntimeConfig()
   const form = reactive({ name: '', email: '', message: '' })
   const submitting = ref(false)
   const success = ref(false)
   const error = ref(null)

   async function submit() {
     submitting.value = true
     error.value = null
     try {
       await $fetch(`${config.public.apiBase}/contact`, { method: 'POST', body: form })
       success.value = true
       form.name = form.email = form.message = ''
     } catch (err) {
       error.value = err.data?.message || 'Submission failed'
     } finally {
       submitting.value = false
     }
   }
   </script>
   ```
4. Run Playwright, confirm pass.
5. Commit: `feat: contact form with client-side POST to backend`.

**Verification:**
- [ ] Form POSTs to `https://alisadikinma.com/api/contact` (production) or localhost (dev)
- [ ] Backend `contacts` table has new row after submission
- [ ] Throttle behavior preserved (3/15min — backend enforces)
- [ ] Success/error states visible in UI

---

## Phase C: Component Port + Visual Parity (3 days)

**Estimated time:** 12-14 hours
**Goal:** ~15 Tier 1 components ported from `frontend/src/components/` to `frontend-nuxt/components/`. Layout (TheNavigation + TheFooter) wired in `default` layout. Visual match with current SPA. 3D globe + animations work post-hydration.

### C1: Default layout + navigation/footer port (2 hr)

**Files:**
- Create: `frontend-nuxt/layouts/default.vue`
- Create: `frontend-nuxt/components/TheNavigation.vue` (port from `frontend/src/components/TheNavigation.vue`)
- Create: `frontend-nuxt/components/TheFooter.vue` (port from `frontend/src/components/TheFooter.vue`)

**Steps:**
1. Write failing test: assert generated HTML for `/` contains `<nav>` element and `<footer>` element. Expected error: layout missing.
2. Run vitest, confirm fail.
3. Copy `TheNavigation.vue` from `frontend/src/components/`. Replace `useRouter`/`useRoute` imports — they're auto-imported in Nuxt.
4. Copy `TheFooter.vue` similarly.
5. Replace any `RouterLink` with `<NuxtLink to="...">`.
6. Strip out admin-only nav items (only public Tier 1 nav: Home, About, Projects, Awards, Gallery, Blog, Contact). Note Blog still links to SPA route — Apache routes `/blog` to SPA dist.
7. Create `layouts/default.vue`:
   ```vue
   <template>
     <div class="min-h-screen flex flex-col">
       <TheNavigation />
       <main class="flex-1"><slot /></main>
       <TheFooter />
     </div>
   </template>
   ```
8. Verify pages render with layout (default applied automatically when `layouts/default.vue` exists).
9. Run vitest, confirm pass.
10. Commit: `feat: port TheNavigation + TheFooter, wire default layout`.

**Verification:**
- [ ] All Tier 1 pages render with nav + footer
- [ ] Logo + nav links visible
- [ ] Mobile menu toggle works (client-side hydration)
- [ ] Active link highlighting works (NuxtLink `active-class`)

### C2: Base UI components port (3 hr)

**Files:**
- Create: `frontend-nuxt/components/base/BaseButton.vue`
- Create: `frontend-nuxt/components/base/BaseCard.vue`
- Create: `frontend-nuxt/components/base/BaseLoader.vue`
- Create: `frontend-nuxt/components/base/BaseLightbox.vue`
- Create: `frontend-nuxt/components/base/BaseGalleryModal.vue`
- Create: `frontend-nuxt/components/base/GlassCard.vue`
- Create: `frontend-nuxt/components/base/GradientBorderCard.vue`
- Create: `frontend-nuxt/components/base/MobileCarousel.vue`
- Create: `frontend-nuxt/components/base/ScrollToTop.vue`
- Skip skeletons (BlogSkeleton, AwardSkeleton, etc.) — not needed in SSG (data already loaded)

**Steps:**
1. Write failing component test: `tests/components/BaseButton.spec.ts` asserts button renders with `variant=primary` and emits click. Expected error: component missing.
2. Run vitest, confirm fail.
3. Copy each component from `frontend/src/components/base/`. Update imports — drop relative `@/...` paths in favor of Nuxt auto-import where possible.
4. For each component: visual smoke test by adding it to a temporary `pages/_dev/components.vue` page and viewing in browser.
5. Run all component tests, confirm pass.
6. Commit: `feat: port 9 base UI components`.

**Verification:**
- [ ] Each component renders without console errors
- [ ] Tailwind classes still apply
- [ ] Lightbox opens/closes (client interaction)
- [ ] No SSR hydration warnings in browser console

### C3: Hero + CTA + feature components port (3 hr)

**Files:**
- Create: `frontend-nuxt/components/HeroSectionWOW.vue` (or `CinematicHero` — check current)
- Create: `frontend-nuxt/components/CTASection.vue`
- Create: `frontend-nuxt/components/projects/ProjectCard.vue`
- Create: `frontend-nuxt/components/awards/AwardCard.vue`
- Create: `frontend-nuxt/components/awards/AwardGalleryModal.vue`

**Steps:**
1. Write failing test: assert ProjectCard renders title + image + link. Expected error: missing.
2. Run vitest, confirm fail.
3. Copy each component from `frontend/src/components/`. Replace any TanStack Query imports — for static pages we just pass props, no fetch hooks needed.
4. CTASection has variants (`home`/`root`) per CLAUDE.md page-section mapping — preserve `variant` prop.
5. Update image paths: any `<img src="/storage/...">` should resolve relative to backend domain. Use full URL `https://alisadikinma.com/storage/...` OR configure `@nuxt/image` provider for backend storage.
6. Run vitest + visual smoke test, confirm pass.
7. Commit: `feat: port hero + CTA + project/award feature components`.

**Verification:**
- [ ] ProjectCard image URLs resolve (no broken images)
- [ ] CTASection variants render correctly (home variant on Home, root variant on About/Projects/Gallery)
- [ ] AwardGalleryModal opens with award images

### C4: Composables port + integration (2 hr)

**Files:**
- Create: `frontend-nuxt/composables/useScrollReveal.js`
- Create: `frontend-nuxt/composables/useCursorSparks.js`
- Create: `frontend-nuxt/composables/useVideoReveal.js`
- Create: `frontend-nuxt/composables/useGlobe.js` (only if 3D globe is part of Home)

**Steps:**
1. Write failing test: `tests/composables/useScrollReveal.spec.ts` asserts composable returns reactive ref. Expected error: missing.
2. Run vitest, confirm fail.
3. Copy each composable from `frontend/src/composables/`. Nuxt auto-imports composables in `composables/` — no manual export changes needed.
4. Wrap any `window` / `document` access in `if (process.client) { ... }` or use `onMounted` lifecycle (already standard).
5. For `useGlobe.js`: ensure all WebGL/Three.js calls happen inside `onMounted` only.
6. Run vitest, confirm pass.
7. Commit: `feat: port animation/effect composables (scroll/cursor/video/globe)`.

**Verification:**
- [ ] Composables auto-import in pages without explicit `import`
- [ ] No `window is not defined` errors during `nuxt generate`
- [ ] Animations fire on client after hydration

### C5: 3D globe with ClientOnly wrapper (2 hr)

**Files:**
- Create: `frontend-nuxt/components/HomeGlobe.client.vue` (note `.client.vue` suffix → auto-skips SSR)
- Modify: `frontend-nuxt/pages/index.vue` (mount globe)

**Steps:**
1. Write failing test: assert globe component renders only on client (HTML at build time has empty placeholder, browser DOM after hydration has canvas). Expected error: not wired.
2. Run vitest + Playwright, confirm fail.
3. Add TresJS dependency: `npm install @tresjs/core three`
4. Copy globe component from `frontend/src/components/` (likely under `home/` or similar). Rename to `HomeGlobe.client.vue` so Nuxt skips SSR.
5. In `pages/index.vue`, mount with `<ClientOnly fallback-tag="div" fallback="Loading globe..."><HomeGlobe /></ClientOnly>`.
6. Verify `nuxt generate` completes without WebGL errors.
7. Run Playwright, confirm globe canvas appears in browser DOM after page load.
8. Commit: `feat: 3D globe with ClientOnly + .client.vue suffix`.

**Verification:**
- [ ] `nuxt generate` succeeds (no WebGL/canvas errors during build)
- [ ] Generated Home HTML has placeholder where globe will mount
- [ ] Browser DOM after hydration has `<canvas>` element from globe
- [ ] Lighthouse Performance score not crashing due to globe

### C6: Style verification — visual parity (1 hr)

**Files:**
- Create: `frontend-nuxt/tests/visual.spec.ts` (Playwright screenshot comparison)

**Steps:**
1. Write Playwright test: navigate to each Tier 1 route on `npm run preview` (port 3001), take screenshot, compare to baseline screenshot of SPA at `localhost:5173`. Expected error: visual diff > 5% on initial run (acceptable since Tailwind classes match).
2. Run Playwright, document any divergence.
3. Fix style issues case-by-case (likely missing fonts, CSS imports, or design token names).
4. Re-run Playwright, confirm visual diff < 2% per page.
5. Commit: `feat: visual parity verification across Tier 1 routes`.

**Verification:**
- [ ] Home/About/Projects/Awards/Gallery/Contact look identical to SPA versions
- [ ] No missing fonts (DevTools network: zero font 404s)
- [ ] No layout shift (CLS) issues

---

## Phase D: SEO + JSON-LD + Sitemap (1 day)

**Estimated time:** 4-6 hours
**Goal:** Sitemap.xml regenerates at build with all 62 routes, JSON-LD schemas (Person, CreativeWork) per page, llms.txt + robots.txt accessible, OG images dynamic.

### D1: @nuxtjs/seo umbrella module configuration (1 hr)

**Files:**
- Modify: `frontend-nuxt/nuxt.config.ts` (configure @nuxtjs/seo)
- Create: `frontend-nuxt/public/robots.txt` (copy from `frontend/public/robots.txt`)

**Steps:**
1. Write failing test: assert `https://localhost:3001/sitemap.xml` returns valid XML with ≥62 `<url>` entries. Expected error: sitemap not configured.
2. Run vitest, confirm fail.
3. Configure `@nuxtjs/seo` in `nuxt.config.ts`:
   ```typescript
   site: {
     url: 'https://alisadikinma.com',
     name: 'Ali Sadikin Ma — AI Generalist Expert',
     description: 'AI Generalist Expert specializing in AI agents, automation, and generative systems',
     defaultLocale: 'en'
   },
   sitemap: {
     // Module auto-includes all prerendered routes
     exclude: ['/admin/**', '/login']
   },
   robots: {
     allow: '/',
     disallow: ['/admin/', '/login'],
     sitemap: 'https://alisadikinma.com/sitemap.xml'
   }
   ```
4. Copy `frontend/public/robots.txt` to `frontend-nuxt/public/robots.txt` — verify AI crawler allow-list (GPTBot, ClaudeBot, PerplexityBot) preserved.
5. Run `npm run generate`, inspect `.output/public/sitemap.xml`.
6. Run vitest, confirm pass.
7. Commit: `feat: configure @nuxtjs/seo for sitemap + robots`.

**Verification:**
- [ ] `.output/public/sitemap.xml` has ≥62 `<url>` entries
- [ ] All Tier 1 routes listed
- [ ] `/admin/**` excluded
- [ ] `lastmod` dates accurate

### D2: llms.txt + brand assets copy (30 min)

**Files:**
- Create: `frontend-nuxt/public/llms.txt` (copy from `frontend/public/llms.txt`)
- Create: `frontend-nuxt/public/sw.js` (copy from `frontend/public/sw.js`)
- Copy: favicon, brand logos from `frontend/public/`

**Steps:**
1. Write failing test: assert `curl http://localhost:3001/llms.txt` returns 200 with content. Expected error: file missing.
2. Copy `frontend/public/llms.txt` → `frontend-nuxt/public/llms.txt`.
3. Copy `frontend/public/sw.js` verbatim. Note: SW scope is `/` so it caches all media.
4. Copy all brand asset files (`*.ico`, `*.png`, `*.svg`) from `frontend/public/` to `frontend-nuxt/public/`.
5. Run vitest, confirm pass.
6. Commit: `feat: copy llms.txt + service worker + brand assets`.

**Verification:**
- [ ] `curl http://localhost:3001/llms.txt` returns same content as SPA
- [ ] Service worker registers in browser DevTools Application tab
- [ ] Favicon loads on every page

### D3: Per-page JSON-LD enrichment (2 hr)

**Files:**
- Modify: `frontend-nuxt/pages/index.vue` (Person schema — done in Phase B4, verify)
- Modify: `frontend-nuxt/pages/about.vue` (Person schema with full bio)
- Modify: `frontend-nuxt/pages/projects/[slug].vue` (CreativeWork schema)
- Modify: `frontend-nuxt/pages/projects/index.vue` (ItemList schema)

**Steps:**
1. Write failing test: assert each page's HTML contains `<script type="application/ld+json">` with appropriate `@type`. Expected error: schemas missing.
2. Run vitest, confirm fail.
3. About page Person schema (richer than Home):
   ```js
   useHead({
     script: [{
       type: 'application/ld+json',
       innerHTML: JSON.stringify({
         '@context': 'https://schema.org',
         '@type': 'Person',
         name: 'Ali Sadikin Ma',
         jobTitle: 'AI Generalist Expert',
         description: aboutData.value?.bio,
         url: 'https://alisadikinma.com',
         sameAs: aboutData.value?.social_links || [],
         knowsAbout: ['AI Agents', 'AI Automation', 'Generative AI', 'Full-stack Development'],
         alumniOf: aboutData.value?.education,
         workExample: featuredProjects.value
       })
     }]
   })
   ```
4. Project detail CreativeWork schema (use `data.data.schema_markup` if backend already populates it via HasSeoFields trait):
   ```js
   useHead({
     script: data.value?.data?.schema_markup ? [{
       type: 'application/ld+json',
       innerHTML: JSON.stringify(data.value.data.schema_markup)
     }] : []
   })
   ```
5. Projects index ItemList schema with all project links.
6. Run vitest, confirm pass.
7. Validate at [validator.schema.org](https://validator.schema.org/) for sample URLs.
8. Commit: `feat: per-page JSON-LD (Person/CreativeWork/ItemList)`.

**Verification:**
- [ ] About page validates as schema.org Person
- [ ] At least 3 project detail pages validate as schema.org CreativeWork
- [ ] Projects index validates as schema.org ItemList
- [ ] No JSON parse errors in DevTools

### D4: Open Graph images per page (1 hr)

**Files:**
- Modify: each page's `useSeoMeta` block

**Steps:**
1. Write failing test: assert each page HTML has `<meta property="og:image">` with valid URL. Expected error: ogImage missing.
2. Run vitest, confirm fail.
3. Add `ogImage` to `useSeoMeta` per page:
   - Home/About: brand cover image (`https://alisadikinma.com/storage/branding/og-default.png`)
   - Projects detail: `data.value?.data?.og_image` from backend
   - Awards/Gallery: brand cover or first item image
4. Run vitest, confirm pass.
5. Commit: `feat: per-page Open Graph images`.

**Verification:**
- [ ] Each Tier 1 page has unique `og:image` where applicable
- [ ] Test with [opengraph.xyz](https://www.opengraph.xyz/) on production URL after deploy

---

## Phase E: Apache vhost + Deploy Integration (1 day)

**Estimated time:** 4-6 hours
**Goal:** GitHub Actions builds Nuxt + SPA in parallel; deploys both to VPS atomically; Apache vhost routes correctly to each.

### E1: GitHub Actions workflow extension (2 hr)

**Files:**
- Modify: `.github/workflows/deploy.yml`

**Steps:**
1. Write failing test: GH Actions dry-run via `act` (or just lint workflow YAML) verifies new `build-nuxt` job. Expected error: job not added.
2. Run actionlint, confirm fail or YAML invalid.
3. Add parallel `build-public-nuxt` job to deploy.yml:
   ```yaml
   build-public-nuxt:
     runs-on: ubuntu-latest
     steps:
       - uses: actions/checkout@v4
       - uses: actions/setup-node@v4
         with:
           node-version: '20'
           cache: 'npm'
           cache-dependency-path: frontend-nuxt/package-lock.json
       - name: Install + Build Nuxt SSG
         working-directory: ./frontend-nuxt
         env:
           NUXT_PUBLIC_API_BASE: https://alisadikinma.com/api
           NUXT_PUBLIC_SITE_URL: https://alisadikinma.com
         run: |
           npm ci
           npm run generate
       - name: Upload Nuxt artifact
         uses: actions/upload-artifact@v4
         with:
           name: nuxt-public-dist
           path: frontend-nuxt/.output/public
   ```
4. Modify existing deploy job to download Nuxt artifact and rsync to VPS:
   ```yaml
   - name: Download Nuxt artifact
     uses: actions/download-artifact@v4
     with:
       name: nuxt-public-dist
       path: ./nuxt-dist
   - name: Sync Nuxt to VPS (atomic)
     uses: appleboy/scp-action@v0.1.7
     with:
       host: ${{ secrets.VPS_SSH_HOST }}
       username: ${{ secrets.VPS_SSH_USER }}
       key: ${{ secrets.VPS_SSH_KEY }}
       port: ${{ secrets.VPS_SSH_PORT }}
       source: "./nuxt-dist/*"
       target: "/var/www/Portfolio_v2/dist-public-staging/"
   ```
5. Add `workflow_dispatch:` trigger to enable manual rebuild button.
6. Run actionlint, confirm pass.
7. Commit: `ci: add Nuxt SSG build job + artifact deploy`.

**Verification:**
- [ ] Workflow YAML valid (actionlint passes)
- [ ] Parallel build executes successfully on next push
- [ ] Artifact size reasonable (<50MB)
- [ ] `workflow_dispatch` button visible in GitHub Actions UI

### E2: scripts/deploy.sh atomic swap (1 hr)

**Files:**
- Modify: `scripts/deploy.sh`

**Steps:**
1. Write failing test: bash script lint via shellcheck. Expected: clean.
2. Add atomic swap step after Nuxt artifact landed at staging path:
   ```bash
   # Atomic swap: only flip live folder if staging build is non-empty
   if [ -f "/var/www/Portfolio_v2/dist-public-staging/index.html" ]; then
     echo "→ Swapping Nuxt dist (atomic)"
     # Backup current
     [ -d "/var/www/Portfolio_v2/dist-public" ] && \
       mv /var/www/Portfolio_v2/dist-public /var/www/Portfolio_v2/dist-public-backup-$(date +%s)
     # Promote staging
     mv /var/www/Portfolio_v2/dist-public-staging /var/www/Portfolio_v2/dist-public
     # Cleanup old backups (keep last 3)
     ls -dt /var/www/Portfolio_v2/dist-public-backup-* 2>/dev/null | tail -n +4 | xargs rm -rf
   else
     echo "✗ Staging build empty — skipping Nuxt deploy"
     exit 1
   fi
   ```
3. Run shellcheck, confirm pass.
4. Commit: `ci(deploy): atomic Nuxt dist swap with backup retention`.

**Verification:**
- [ ] shellcheck passes
- [ ] Failed Nuxt build doesn't wipe live `dist-public`
- [ ] Last 3 backups retained for rollback

### E3: Apache vhost edit (operator step, documented) (1 hr)

**Files:**
- Create: `docs/ops/2026-05-02-apache-vhost-nuxt-split.md` (operator runbook)

**Steps:**
1. Document required vhost change in operator runbook:
   ```apache
   # /etc/apache2/sites-available/alisadikinma.conf
   <VirtualHost *:443>
     ServerName alisadikinma.com
     # NEW DocumentRoot — Nuxt SSG default
     DocumentRoot /var/www/Portfolio_v2/dist-public

     # Admin SPA — fall through to existing Vue dist
     RewriteEngine On
     RewriteRule ^/admin/?(.*)$ /var/www/Portfolio_v2/frontend/dist/index.html [L]
     RewriteRule ^/login$ /var/www/Portfolio_v2/frontend/dist/index.html [L]

     # Backend API — unchanged
     Alias /api /var/www/Portfolio_v2/backend/public
     <Directory /var/www/Portfolio_v2/backend/public>
       Require all granted
       AllowOverride All
     </Directory>

     # Storage — unchanged
     Alias /storage /var/www/Portfolio_v2/backend/storage/app/public

     # Nuxt fallthrough for any non-matched route
     <Directory /var/www/Portfolio_v2/dist-public>
       Require all granted
       FallbackResource /404/index.html
     </Directory>
   </VirtualHost>
   ```
2. Document rollback: how to revert vhost to old DocumentRoot if issues.
3. Document operator pre-flight test: `apachectl configtest && apachectl -S` before reload.
4. Commit: `docs(ops): Apache vhost split runbook for Nuxt + SPA`.

**Verification:**
- [ ] Runbook covers backup of old vhost
- [ ] `apachectl configtest` passes locally
- [ ] Test on staging before production

### E4: Smoke test integration (1 hr)

**Files:**
- Create: `frontend-nuxt/tests/deploy-smoke.spec.ts`

**Steps:**
1. Write Playwright smoke test that runs against production URL post-deploy:
   ```typescript
   test('Nuxt SSG: home page returns full HTML to GPTBot', async ({ request }) => {
     const res = await request.get('https://alisadikinma.com/', {
       headers: { 'User-Agent': 'GPTBot/1.0' }
     })
     const html = await res.text()
     expect(html).toContain('<h1')
     expect(html).not.toContain('<div id="app"></div>')
     expect(html).toContain('Person')  // JSON-LD
   })

   test('Admin SPA still loads at /admin', async ({ request }) => {
     const res = await request.get('https://alisadikinma.com/admin')
     expect(res.status()).toBeLessThan(400)
   })

   test('Backend API still reachable at /api/posts', async ({ request }) => {
     const res = await request.get('https://alisadikinma.com/api/posts')
     expect(res.status()).toBe(200)
   })
   ```
2. Add as post-deploy step in `.github/workflows/deploy.yml` to fire after VPS deploy completes.
3. Commit: `test: post-deploy smoke tests for Nuxt + SPA + API split`.

**Verification:**
- [ ] All 3 smoke tests pass against production after deploy
- [ ] Failure of any test blocks deploy promotion (fail-fast)

---

## Phase F: Verification + LLM Crawl Audit (1 day)

**Estimated time:** 4-6 hours
**Goal:** Confirm GEO score moved from 7.5 → ≥9, AI crawlers see full HTML, performance metrics on target.

### F1: GEO score audit per CLAUDE.md (2 hr)

**Files:**
- Create: `docs/audits/2026-05-XX-geo-score-post-nuxt.md`

**Steps:**
1. Write checklist test based on root CLAUDE.md GEO section criteria:
   - SSR HTML body (was P0 deferred — now ✅)
   - Per-page meta tags ✅ (via useSeoMeta)
   - JSON-LD Person/CreativeWork ✅
   - Sitemap accessible ✅
   - llms.txt accessible ✅
   - robots.txt allows AI bots ✅
   - Semantic HTML (article, time, figure) — verify per page
   - BreadcrumbList — add if missing
2. Run audit, score each criterion.
3. Document score in audit file.
4. Update root CLAUDE.md GEO section with new score + remaining gaps.
5. Commit: `docs: GEO score audit post-Nuxt migration`.

**Verification:**
- [ ] GEO score documented at ≥9/10
- [ ] Root CLAUDE.md GEO section updated
- [ ] Audit findings tracked for future improvement

### F2: AI crawler simulation (1 hr)

**Files:**
- Create: `scripts/audit-llm-crawl.sh`

**Steps:**
1. Write bash script simulating major AI crawlers:
   ```bash
   #!/bin/bash
   set -e
   BASE="https://alisadikinma.com"
   ROUTES=("/" "/about" "/projects" "/projects/some-real-slug" "/awards" "/gallery")
   AGENTS=("GPTBot/1.0" "ClaudeBot/1.0" "PerplexityBot/1.0" "Mozilla/5.0 GoogleOther")

   for agent in "${AGENTS[@]}"; do
     echo "=== Crawler: $agent ==="
     for route in "${ROUTES[@]}"; do
       html=$(curl -sA "$agent" "$BASE$route")
       if echo "$html" | grep -q '<div id="app"></div>'; then
         echo "  ✗ $route returned SPA shell"
         exit 1
       elif echo "$html" | grep -q '<h1'; then
         echo "  ✓ $route full HTML ($(echo "$html" | wc -c) bytes)"
       fi
     done
   done
   echo "✓ All crawlers see full HTML"
   ```
2. Run script, confirm all routes pass.
3. Commit: `audit: LLM crawler simulation script`.

**Verification:**
- [ ] All 4 user-agent variants get full HTML
- [ ] Zero `<div id="app"></div>` returns
- [ ] Bytes per route reasonable (>5KB indicates real content)

### F3: Performance audit (1 hr)

**Files:**
- Modify: `docs/audits/2026-05-XX-geo-score-post-nuxt.md` (add perf section)

**Steps:**
1. Write Playwright Lighthouse test (or use Lighthouse CI):
   ```typescript
   test('Lighthouse: Home page mobile ≥ 90', async ({ page }) => {
     await page.goto('https://alisadikinma.com/')
     // Run Lighthouse via lighthouse-cli or chrome-launcher
     const score = await runLighthouse('https://alisadikinma.com/', { mobile: true })
     expect(score.performance).toBeGreaterThanOrEqual(90)
   })
   ```
2. Run TTFB measurement:
   ```bash
   for route in / /about /projects /projects/sample-slug; do
     time=$(curl -w "%{time_starttransfer}" -o /dev/null -s "https://alisadikinma.com$route")
     echo "$route: ${time}s"
   done
   ```
3. Document results in audit file.
4. Commit: `audit: Lighthouse + TTFB benchmarks for Tier 1`.

**Verification:**
- [ ] Home Lighthouse Performance ≥ 90 (mobile)
- [ ] TTFB < 200ms for static routes
- [ ] CLS < 0.1
- [ ] LCP < 2.5s

### F4: Update root CLAUDE.md (1 hr)

**Files:**
- Modify: `CLAUDE.md` (root)

**Steps:**
1. Update "Frontend Architecture" section to mention split:
   - `frontend/` — Vue 3 SPA for admin + auth + blog
   - `frontend-nuxt/` — Nuxt 3 SSG for public Tier 1
2. Update "GEO" section: P0 SPA-without-SSR resolved; new score; what's still deferred (Blog SSR if needed).
3. Update "Deployment" section: GitHub Actions now builds both frontends; Apache vhost split documented at `docs/ops/2026-05-02-apache-vhost-nuxt-split.md`.
4. Update "Last Updated" line at bottom with this milestone.
5. Commit: `docs(claude): update root CLAUDE.md for Nuxt split + GEO improvement`.

**Verification:**
- [ ] CLAUDE.md GEO section accurately reflects post-migration state
- [ ] Frontend split clearly documented (which app owns what)
- [ ] Last Updated line includes today's date + summary

---

## Risks Summary (per design doc)

| Risk | Severity | Mitigation in Plan |
|---|---|---|
| Apache vhost edit breaks routes | High | Phase E3 documented runbook + `apachectl configtest` pre-flight |
| Build fails on backend unreachable | Medium | Phase B3 prerender hook fails loudly |
| Hydration mismatch | Medium | TanStack Query NOT used in public Nuxt; `useFetch` SSR-safe by design |
| 3D globe SSR break | Low | Phase C5 `.client.vue` suffix + `<ClientOnly>` |
| Service worker scope conflict | Medium | Public SW scope `/`; SPA SW scope `/admin/*` (verify in Phase D2) |
| Atomic deploy fails | Medium | Phase E2 staging dir + non-empty check + 3-backup retention |

---

## Execution Handoff

**Option 1: Execute in this session**
> Ready to start Phase A? I'll use `gaspol-execute` to implement with per-phase checkpoints + TDD hard-gate enforcement. Each phase gets a commit.

**Option 2: Parallel execution**
> Phases A–C are sequential (each depends on previous). Phases D + E can run in parallel after C completes (D = SEO/JSON-LD, E = CI/CD/Apache — independent surfaces). Phase F runs after both. `gaspol-parallel` mode `plan-phases` orchestrates.

**Option 3: Save for separate session**
> Plan saved at `docs/plans/2026-05-02-nuxt3-ssg-public-tier1-plan.md`. Next session: load this file + design doc, resume from Phase A.

---

**Plan created by:** Claude Code + gaspol-plan
**Companion design:** [`2026-05-02-nuxt3-ssg-public-tier1.md`](2026-05-02-nuxt3-ssg-public-tier1.md)
**Last updated:** May 2, 2026
