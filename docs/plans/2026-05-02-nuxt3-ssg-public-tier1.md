# Nuxt 3 SSG Hybrid — Public Tier 1 Migration

**Date:** May 2, 2026
**Goal:** Make Ali Sadikin's public-facing portfolio pages (Tier 1) crawlable by AI training scrapers + LLM live-search bots so the site signals **author authority** when ChatGPT / Claude / Gemini are asked about Ali's expertise, projects, or background.
**Status:** Design phase complete → Ready for `/gaspol-plan`
**Supersedes:** [2026-04-11-nuxt3-ssr-llm-geo-design.md](2026-04-11-nuxt3-ssr-llm-geo-design.md) + [2026-04-11-nuxt3-ssr-implementation-plan.md](2026-04-11-nuxt3-ssr-implementation-plan.md) — original April 11 plan never executed; 3 weeks of frontend drift (LinkedIn UI, Content Engine views, vue-i18n 11, Tailwind 4) made it stale. This is a re-scoped, ~50% lighter follow-up.

---

## Design

### Executive Summary

Spin up a **second frontend project** (`frontend-nuxt/`) using **Nuxt 3 in SSG mode** (`nuxt generate`) for ~62 public Tier 1 routes (Home, About, Projects index + 56 detail, Awards, Gallery, Contact, 404). The **existing Vue 3 SPA stays as-is** but its public-route scope shrinks to admin + auth only. VPS Apache vhost routes `/admin/*` and `/login` to the SPA, everything else to the Nuxt static output.

**Why this scope:**
- Goal is *author authority* (Ali quoted as expert), not *citation per blog post*. About + Home + Project case studies are the primary evidence pages.
- Blog list/detail stays SPA — blog is updated frequently, SSG rebuild on every post adds ops complexity. Modern AI live-search bots execute JS anyway. Training scrapers can pull blog content from `/api/llms-full.txt` (already exists).
- Admin (28 views) doesn't need SSR ever (auth-gated, not LLM-relevant). Migrating admin would be 50%+ of the effort with zero LLM-friendliness benefit. Keep it Vue SPA.

**Outcome:** AI crawler hitting `https://alisadikinma.com/about` or `/projects/ai-content-engine` receives full HTML (bio, project case study, JSON-LD Person + CreativeWork schemas) — no JS execution required. GEO score moves from 7.5 → ~9 without rewriting the admin panel or migrating auth.

**Effort:** ~9 working days = 2 weeks (vs original plan's 5-6 weeks).
**Risk:** Low — admin SPA untouched, reversible (Apache routing flip).

---

### Goal & Scope

#### Primary Goal
When someone asks ChatGPT / Claude / Gemini / Perplexity "who is Ali Sadikin?" or "AI generalist Indonesia?" or "show me AI automation portfolios", the AI can quote Ali's bio, project case studies, and expertise areas with accurate detail because the public pages are crawlable as full HTML.

#### Secondary Goal
Foundation for future: if Tier 2 (Blog SSR with ISR) becomes valuable later, Nuxt's `routeRules` lets us flip `prerender: true` per-route without restructure. Today's investment compounds.

#### In Scope (Tier 1 — ~62 routes)
| Route | Source | Purpose |
|---|---|---|
| `/` (Home) | `/api/settings/site` + `/api/projects?featured=1` + `/api/posts?per_page=3` | Landing, hero, featured work, latest blog teasers |
| `/about` | `/api/settings/about` | Bio, skills, experience, education — primary author authority page |
| `/projects` | `/api/projects?per_page=100` | Portfolio grid index |
| `/projects/[slug]` | `/api/projects/{slug}` per slug, ~56 routes | Case study detail — primary evidence of expertise |
| `/awards` | `/api/awards` | Recognition signals |
| `/gallery` | `/api/galleries` | Visual portfolio |
| `/contact` | static (POST → `/api/contact` on submit) | Contact form |
| `/404` | static | Catch-all not-found |

#### Out of Scope (stays in existing Vue SPA)
- `/admin/*` — 28 views, auth-gated, not LLM-relevant
- `/login` — auth flow
- `/blog`, `/blog/[slug]`, `/blog/category/[slug]` — frequent updates, rebuild cost too high; LLM training pipelines fed via existing `/api/llms-full.txt`
- All authenticated mutation flows
- Carousel/LinkedIn admin UIs

---

### Architecture

#### Folder Layout
```
D:\Projects\Portfolio_v2\
├── frontend\              # EXISTING Vue 3 SPA — scope shrinks to admin + auth + blog
│   └── ... (untouched in this migration)
├── frontend-nuxt\         # NEW Nuxt 3 SSG project for public Tier 1
│   ├── nuxt.config.ts
│   ├── app.vue
│   ├── pages\
│   │   ├── index.vue               # Home
│   │   ├── about.vue
│   │   ├── projects\
│   │   │   ├── index.vue
│   │   │   └── [slug].vue
│   │   ├── awards.vue
│   │   ├── gallery.vue
│   │   ├── contact.vue
│   │   └── [...catchall].vue       # 404
│   ├── components\        # Ported subset from frontend/src/components/
│   ├── composables\       # Build-time fetchers (lighter than SPA composables)
│   ├── layouts\
│   │   └── default.vue
│   ├── public\
│   │   ├── llms.txt       # Symlink or copy from frontend/public/llms.txt
│   │   ├── robots.txt
│   │   ├── sitemap.xml
│   │   └── sw.js
│   ├── server\
│   │   └── routes\
│   │       └── sitemap.xml.ts      # Optional: dynamic sitemap from build-time data
│   ├── assets\
│   │   └── styles\
│   │       └── main.css            # Copy of frontend/src/assets/styles
│   ├── tailwind.config.js          # Copy from frontend/
│   └── package.json
├── backend\               # Laravel (unchanged)
├── docs\
│   └── plans\
│       └── 2026-05-02-nuxt3-ssg-public-tier1.md   # this file
└── scripts\
    └── deploy.sh          # Will be extended to build BOTH frontends
```

#### VPS Apache Routing
Two static dist folders, single document root via Apache vhost rules. Existing vhost edited (operator step, not in `deploy.sh`):

```apache
DocumentRoot /var/www/Portfolio_v2/dist-public

# Admin SPA + auth → Vue SPA dist
RewriteRule ^/admin/?(.*)$ /var/www/Portfolio_v2/frontend/dist/index.html [L]
RewriteRule ^/login$ /var/www/Portfolio_v2/frontend/dist/index.html [L]

# Backend API → Laravel (unchanged)
RewriteRule ^/api/(.*)$ /var/www/Portfolio_v2/backend/public/index.php [L,QSA]

# Storage (uploaded files) — unchanged
Alias /storage /var/www/Portfolio_v2/backend/storage/app/public

# Everything else → Nuxt SSG output (default DocumentRoot)
# Nuxt generate emits dist-public/{path}/index.html for every prerendered route
# Apache will serve those directly; for unmatched paths fall through to Nuxt 404
```

**Alternative considered:** subdomain split (`admin.alisadikinma.com` for SPA). Rejected — extra DNS + cert setup + breaks existing bookmarks/auth-cookie domain.

#### Tech Stack (frontend-nuxt only)

| Layer | Choice | Notes |
|---|---|---|
| Framework | **Nuxt 3.x** (latest stable as of May 2026) | SSG mode via `nuxt generate` — emits static HTML, no Node runtime needed at production |
| Vue | 3.5 | Same as current SPA |
| Build | Nitro (built-in) | No Vite config needed |
| State | `@pinia/nuxt` | Mostly unused in public SSG — data fetched at build, no client-side mutation |
| Styling | `@nuxtjs/tailwindcss` 6+ | Tailwind 4 config copy from frontend/ |
| HTTP (build-time) | `$fetch` (built-in) | Replaces axios for build-time fetch from Laravel API |
| HTTP (client-side) | `useFetch` / `$fetch` | Only for contact form POST + maybe newsletter |
| i18n | `@nuxtjs/i18n` 9+ | Supports vue-i18n 11; copy locale JSONs from frontend/src/locales/ |
| 3D Globe | `@tresjs/nuxt` (or manual `<ClientOnly>` wrap) | Hydrate client-only — no SSR for WebGL |
| Animation | GSAP (npm) | Works as-is, lifecycle hooks SSR-safe |
| Image | `@nuxt/image` | Optional — handles backend storage CDN URLs as external domain |
| SEO | `@nuxtjs/seo` (umbrella module) | sitemap, robots, schema, OG |
| Auth | **None** | Public site doesn't need auth; admin SPA owns it |
| TanStack Query | **NOT included** | Data baked at build time → same JSON for all users → no client cache layer needed |

---

### Data Integration Map

| Page | API Source | Fetch When | Cache Strategy | Rebuild Trigger |
|---|---|---|---|---|
| `/` | `/api/settings/site`, `/api/projects?featured=1`, `/api/posts?per_page=3` | Build time (Nuxt `useFetch` in SSG context) | 24h, file content cached forever until rebuild | Manual or webhook on settings/projects/posts save |
| `/about` | `/api/settings/about` | Build time | 24h | Webhook on AboutSettings save |
| `/projects` | `/api/projects?per_page=100` | Build time | 24h | Webhook on Project save |
| `/projects/[slug]` | `/api/projects/{slug}` per slug — generated via `nitro.prerender.routes` callback | Build time, one fetch per slug | 24h | Webhook on Project save |
| `/awards` | `/api/awards` | Build time | 24h | Webhook on Award save |
| `/gallery` | `/api/galleries` (+ items) | Build time | 24h | Webhook on Gallery save |
| `/contact` | static page; POST to `/api/contact` on submit | Build time (page) + runtime (POST) | n/a | n/a |
| `/404` | static | Build time | n/a | n/a |

**Build-time slug generation:** `nuxt.config.ts` declares `nitro.prerender.routes` as a function that fetches `/api/projects?per_page=100`, extracts all slugs, returns `['/projects/{slug-1}', '/projects/{slug-2}', ...]`. Nuxt then prerenders each.

```typescript
// nuxt.config.ts (sketch)
export default defineNuxtConfig({
  ssr: true,
  nitro: {
    prerender: {
      crawlLinks: true,
      routes: ['/'],
      ignore: ['/admin/**', '/login', '/blog', '/blog/**', '/api/**']
    }
  },
  hooks: {
    async 'nitro:config'(nitroConfig) {
      const projects = await $fetch(`${apiBase}/projects?per_page=100`)
      const projectRoutes = projects.data.map(p => `/projects/${p.slug}`)
      nitroConfig.prerender.routes.push(...projectRoutes)
    }
  }
})
```

**Critical: API base URL during build** — CI/CD runner needs to fetch from the production Laravel API. Two options:
1. Build runner hits `https://alisadikinma.com/api/*` (production HTTPS) — needs runner egress to production domain (works on `ubuntu-latest` GitHub Actions)
2. Tunnel: backend on VPS exposes API via SSH tunnel to runner — more secure but more setup

**Recommendation:** Option 1 for simplicity. Backend already public, no auth needed for these endpoints.

---

### Build & Deploy

#### CI/CD Flow (GitHub Actions extension)

Current `.github/workflows/deploy.yml` builds only frontend SPA. Extension:

```yaml
# Adds parallel job
build-public-nuxt:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: actions/setup-node@v4
      with:
        node-version: '20'
    - name: Build Nuxt SSG
      working-directory: ./frontend-nuxt
      env:
        NUXT_PUBLIC_API_BASE: https://alisadikinma.com/api
      run: |
        npm ci
        npm run generate   # nuxt generate → .output/public

    - name: Deploy to VPS
      uses: appleboy/scp-action@v0.1.7
      with:
        host: ${{ secrets.VPS_SSH_HOST }}
        username: ${{ secrets.VPS_SSH_USER }}
        key: ${{ secrets.VPS_SSH_KEY }}
        port: ${{ secrets.VPS_SSH_PORT }}
        source: "frontend-nuxt/.output/public/*"
        target: "/var/www/Portfolio_v2/dist-public/"
        strip_components: 3   # drop frontend-nuxt/.output/public
```

`scripts/deploy.sh` extension (the existing one runs after SSH push):
```bash
# After existing steps
echo "→ Reloading Apache to pick up Nuxt static files"
sudo systemctl reload apache2  # or just no-op since static files don't need reload
```

No queue:restart needed — Nuxt SSG output is pure static.

#### Rebuild Triggers

**Phase 1 (manual, ships first):**
- GitHub Actions `workflow_dispatch` button — operator clicks "Run workflow" → rebuilds Nuxt + redeploys
- Use case: Ali updates About text, edits a project, awards new entry → manually triggers rebuild

**Phase 2 (auto, post-MVP):**
- Backend admin save event on `Project`, `Award`, `Gallery`, `Setting{group=about|site}` → POST webhook to GitHub `repository_dispatch` event
- New artisan job: `App\Jobs\TriggerNuxtRebuild` queued on those model `saved` events
- Debounce: 5-minute window so 10 rapid saves trigger 1 rebuild (not 10)
- Setting key in `settings` group `nuxt_rebuild`: `webhook_url`, `enabled`, `last_triggered_at`

**Out of scope for this migration** — Phase 2 is a follow-up.

---

### Tech Decisions (with rationale)

| Decision | Choice | Rationale |
|---|---|---|
| Framework | Nuxt 3 SSG | Vue ecosystem (zero learning curve), built-in prerender via `nuxt generate`, `routeRules` future-proof for ISR/SSR per-route |
| Output mode | `nuxt generate` (full SSG) | Compatible with existing static-deploy CI/CD; no Node runtime on VPS needed |
| Public/Admin split | Two separate frontends | Admin works (28 views, recent LinkedIn UI) — risk of regression too high to migrate; SSG only matters for public |
| TanStack Query | Drop in public Nuxt | Build-time data baked into HTML — same payload for everyone, no per-user cache |
| Auth | None in public Nuxt | Vue SPA owns admin auth; public has no protected routes |
| i18n | `@nuxtjs/i18n` | Existing `vue-i18n 11` locale files port directly; module handles SSG hreflang automatically |
| Components | Copy-port from `frontend/src/components/` | Vue 3 components plug into Nuxt 3 unchanged; shared base components (`BaseButton`, `BaseCard`) → copy-paste, no shared package overhead |
| Tailwind | Tailwind 4 via `@nuxtjs/tailwindcss` | Copy `tailwind.config.js` from `frontend/`; PostCSS pipeline same |
| 3D globe (TresJS) | `<ClientOnly>` wrapper | WebGL can't render in SSG; client hydrate is fine — globe shows after page load |
| Service Worker | Copy from `frontend/public/sw.js` | Media cache strategy unchanged; Nuxt preserves `public/` files verbatim |
| Image optimization | `@nuxt/image` (optional) | Backend `https://alisadikinma.com/storage/...` registered as external domain; lazy-load + responsive srcset |
| API base URL | Production HTTPS during build | GitHub Actions runner has internet egress; backend API public; no SSH tunnel complexity |
| Rebuild trigger MVP | Manual `workflow_dispatch` | Auto-webhook from backend deferred to Phase 2 — ship visible value first |

---

### Component Reuse Strategy

Components to **copy-port** from `frontend/src/components/` to `frontend-nuxt/components/`:

**Tier 1 must-have (~15 components):**
- `base/BaseButton.vue`, `base/BaseCard.vue`, `base/BaseLoader.vue`, `base/BaseLightbox.vue`, `base/BaseGalleryModal.vue`
- `TheNavigation.vue`, `TheFooter.vue`
- `CTASection.vue`
- `HeroSectionWOW.vue` or `CinematicHero` (whichever is current)
- `awards/AwardCard.vue`, `awards/AwardGalleryModal.vue`
- `projects/ProjectCard.vue`, `projects/ProjectGrid.vue`
- `gallery/GalleryGrid.vue`

**Tier 2 maybe (optional):**
- 3D globe component (TresJS) — only if Home keeps the globe
- Cursor sparks effect — decorative, optional
- ScrollReveal animations — needed for visual parity

**Process per component:**
1. Copy file
2. Update import paths (`@/composables/...` → check Nuxt auto-import resolves it)
3. Replace `useRouter` from `vue-router` → Nuxt's auto-imported `useRouter` (same API)
4. Replace `useRoute` similarly
5. Wrap any `window`/`document` access in `if (process.client)` or `<ClientOnly>`
6. Replace TanStack Query usage (`useQuery`) with Nuxt's `useFetch` for build-time fetch — most public components don't need cache anyway
7. Smoke test in dev (`npm run dev`)

**Composables ported (~5):**
- `useMetaTags.js` → replaced by Nuxt `useHead` / `useSeoMeta` (built-in, more powerful)
- `useScrollReveal.js` → port as-is (client-side animation)
- `useCursorSparks.js` → port as-is
- `useGlobe.js` → port as-is, wrap in ClientOnly
- About/Project/Award fetchers → replace with Nuxt `useFetch` direct in pages

**Stores ported (~0):**
- Public Nuxt mostly stateless. Theme toggle (`theme.js`) → migrate as Pinia store via `@pinia/nuxt`
- UI store (modals, toasts) → migrate if Tier 1 pages need toasts (contact form success); else skip

---

### Risks & Mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| API call at build time fails (backend down during CI) | Medium | Retry 3× with backoff; fail build with clear error; don't deploy stale |
| 3D globe (TresJS) breaks in SSG context | Low | Wrap in `<ClientOnly>` from day 1; test in dev with `nuxt generate && nuxt preview` before deploy |
| Hydration mismatch between Nuxt build-time HTML and client hydrate | Medium | Same `$fetch` call runs at build + hydrates client with same data → no mismatch by design. Test catch-all via Vue dev warnings |
| Apache vhost edit breaks existing routes | High | Pre-flight test on staging vhost (or local Apache); deploy in window with rollback plan; keep old vhost backup |
| GSAP / scroll triggers fire before DOM ready in SSG-hydrated page | Low | Use `onMounted` lifecycle (already standard); `<ClientOnly>` for any `IntersectionObserver`-heavy components |
| Admin SPA + Nuxt deploy desync (one builds, other fails) | Medium | Two parallel CI jobs — fail-fast, atomic deploy via temp folder rename, single-rollback action |
| Build time grows too long (62 routes × API fetch each) | Low | Project list cached as JSON for the build; per-slug fetch parallelized (Nitro does this automatically); estimate ~60s build time |
| Service worker conflicts (two scopes: `/admin/*` SPA SW vs Nuxt root SW) | Medium | Public Nuxt SW scope `/`; SPA SW remains scope `/admin/*`; explicit `scope:` in registration to prevent overlap |
| Tailwind 4 config drift (frontend updated, frontend-nuxt stuck) | Low | Phase 2: extract shared `tailwind.config.js` to repo root, both projects extend it |
| LLM crawler still reads SPA admin routes (`/admin/login`) and gets shell HTML | Low | `noindex` meta on admin routes (already done?); explicitly disallow `/admin/` in `robots.txt` (already done — verify) |

---

### Implementation Phases (high-level, gaspol-plan will detail)

**Phase A — Project setup (1 day)**
- Initialize `frontend-nuxt/` with Nuxt 3 + dependencies
- `nuxt.config.ts` with SSG mode + prerender + module list
- Tailwind 4 config copy from `frontend/`
- Smoke test: `npm run dev` shows blank Nuxt page on port 3000

**Phase B — Page scaffolding + data layer (2 days)**
- `pages/index.vue`, `about.vue`, `projects/index.vue`, `projects/[slug].vue`, `awards.vue`, `gallery.vue`, `contact.vue`, `[...catchall].vue`
- `useFetch` build-time data wiring per page
- `nitro.prerender.routes` callback for project slugs
- Test: `nuxt generate` emits HTML for all 62 routes

**Phase C — Component port + visual parity (3 days)**
- Port ~15 Tier 1 components from `frontend/src/components/`
- Layout: `layouts/default.vue` with TheNavigation + TheFooter
- Style match: design tokens, fonts, glass effects, gradient borders
- 3D globe + scroll reveals + animations work post-hydration

**Phase D — SEO + JSON-LD + sitemap (1 day)**
- Install `@nuxtjs/seo` umbrella
- Per-page `useSeoMeta` (Person schema on About, CreativeWork on Projects)
- Sitemap regenerates at build time including all 62 routes
- robots.txt + llms.txt copy/symlink from `frontend/public/`

**Phase E — Apache vhost + deploy integration (1 day)**
- VPS vhost edit (operator step, documented in this plan)
- GitHub Actions parallel job for `frontend-nuxt`
- Atomic deploy via temp folder rename
- Smoke test: `curl https://alisadikinma.com/about | grep -i '<h1\|<article'` returns content
- Smoke test: `curl https://alisadikinma.com/admin` still loads SPA

**Phase F — Verification + LLM crawl test (1 day)**
- `curl -A "GPTBot" https://alisadikinma.com/about` returns full HTML
- Validate JSON-LD via [validator.schema.org](https://validator.schema.org/)
- Check sitemap.xml at root, llms.txt accessible
- Performance: TTFB < 200ms (static file), Lighthouse > 90
- Compare GEO score before/after (target: 7.5 → 9+)

**Total: ~9 working days = 2 weeks calendar.**

---

### Success Criteria

| Metric | Before (Vue SPA) | After (Nuxt SSG) | How measured |
|---|---|---|---|
| Public route HTML body | `<div id="app"></div>` | Full content | `curl -A "GPTBot" https://alisadikinma.com/about` |
| GEO score | 7.5/10 | ≥ 9/10 | Manual audit per CLAUDE.md GEO section |
| TTFB (Home) | ~300ms (JS-rendered) | < 200ms (static file) | `curl -w "%{time_starttransfer}"` |
| Lighthouse (mobile) | ~75 | ≥ 90 | Lighthouse CI |
| Build time (frontend-nuxt) | n/a | < 5 min | GitHub Actions runner log |
| Deploy frequency | Push-triggered | Push OR manual rebuild for content updates | `workflow_dispatch` button visible |
| Admin SPA functionality | 100% working | 100% working (untouched) | Smoke test: log in, edit project, generate carousel |

---

### Open Questions / Future Work

**Phase 2 (post-MVP, not in this migration):**
1. Auto-rebuild webhook: Backend save → GitHub `repository_dispatch` → rebuild Nuxt
2. Blog SSG (Tier 2): if Phase 1 GEO bump validates the approach, extend `nitro.prerender.routes` callback to include blog post slugs (with debounced rebuild)
3. ISR (Tier 3): blog detail with `routeRules: { '/blog/**': { isr: 600 } }` — per-route revalidation if Nuxt hosted on Node-capable runtime
4. Image optimization via `@nuxt/image` with WebP + responsive srcset for project gallery thumbnails
5. AI plugin / OpenAPI spec at `.well-known/ai-plugin.json` for ChatGPT custom GPT integration

**Decisions deferred to gaspol-plan:**
- Exact dependency versions (Nuxt 3.x.y, vue-i18n 11.x, etc.)
- File-by-file component port checklist
- Per-phase commit boundaries
- Test cases (unit + e2e + smoke)
- Rollback procedure step-by-step

---

**Brainstorm by:** Claude Code + gaspol-brainstorm
**Brainstorm session:** May 2, 2026
**Implementation plan:** [`2026-05-02-nuxt3-ssg-public-tier1-plan.md`](2026-05-02-nuxt3-ssg-public-tier1-plan.md) — file-level task breakdown for the 6 phases (A-F), Data Integration Map, TDD steps, verification criteria. Split into separate file (design 405 lines + plan ~700 lines = combined would exceed the 500-line append threshold).
