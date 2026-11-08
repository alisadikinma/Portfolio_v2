**Ticket:** PF-2

# PF-2 — alisadikinma.com rebuild: Astro public site, SEO/GEO-first, INDUSIA design system

Inputs (read first):
- Brief: `docs/plans/2026-11-08-PF-2-portfolio-redesign-seo-brief.md` (goals, page/keyword map, Ali's decisions §10a)
- Keyword data: `docs/seo/2026-11-08-keyword-research-personal-brand.md`

## Design

### Decisions locked in brainstorm (2026-11-08)

1. **PF-1 is shelved, not merged.** Branch `feat/PF-1-career-journey-3d` (3D journey home + hero video)
   contradicts the brief (hero = photo + one GSAP moment, no video, no 3D). PF-2 branches from `main`.
   PF-1 branch + worktree are kept as an archive; nothing from it is cherry-picked by default.
2. **Public site moves to Astro in server mode** (`@astrojs/node`, standalone). Same framework as
   indusia.ai (Astro 7), so tokens, fonts and illustration components are shared for real, not imitated.
3. **Laravel stays untouched as the data + admin layer**: API, admin CMS (Vue SPA under `/admin`),
   Content Engine, LinkedIn/cross-post pipelines, newsletter, scheduler. Astro is a read-mostly client
   of the existing `/api/*` endpoints.
4. **Primary keywords:** "pakar AI Indonesia" (ID), "AI consultant Indonesia" (EN). Name is always
   "Ali Sadikin Ma". "AI Generalist" leaves title/H1.
5. **Built route by route, released once.** Phases 1–5 are built and verified locally (Astro and the
   Vue SPA side by side). Production switches once, in phase 6. Visitors never see a mix of the old
   (gold) and new (teal) design. Trade-off accepted: SEO gains only land at the final release.
6. **Copy:** Claude drafts all page copy (ID + EN) from the keyword map and vault facts, in one
   `docs/copy/PF-2-<phase>.md` file per phase. Ali approves or corrects it before that phase counts
   as done.

### Why Astro server mode (not static, not Nuxt, not more prerender)

- Today the public site is a Vue SPA. Crawlers/LLMs get an empty shell except on routes Laravel
  enriches (`SpaPrerenderController`: `/`, `/faq`, blog, project detail). Those routes carry two
  templates (Blade body + Vue body) that can drift.
- Astro renders real HTML per request with near-zero JS → one template, crawler content == visitor
  content.
- Server mode (not static) because the Content Engine publishes posts automatically; a static build
  would delay every new post by a rebuild. Server mode serves fresh content immediately.
- Nuxt would reuse old Vue components but cannot share components with indusia.ai. The brief's
  core visual goal is "one family with indusia.ai", so Astro wins.

### Architecture

```
            nginx (alisadikinma.com)
   ┌──────────────┬───────────────┬──────────────────┐
   │ /api/*       │ /admin/*      │ public routes     │
   │ /storage/*   │ (Vue SPA,     │ migrated so far   │
   │ /uploads/*   │  unchanged)   │                   │
   ▼              ▼               ▼
 Laravel 12     frontend/dist   Astro (web/, node server,
 PHP-FPM        index.html      systemd, 127.0.0.1:4321)
                                   │ server-side fetch
                                   └──► Laravel /api/* (internal)
 not-yet-migrated public routes ──► Vue SPA (as today) until cutover
```

- New directory `web/` in this repo: Astro app. Own `package.json`, own build.
- Runtime: `node web/dist/server/entry.mjs` under a new systemd unit `portfolio-web.service`
  (template in `scripts/systemd/`, installed manually once like the queue worker).
- Data access: server-side `fetch` to Laravel API via an internal base URL (env `API_INTERNAL_URL`,
  e.g. `http://127.0.0.1:8080/api`) to avoid the Cloudflare round trip. Short in-process cache
  (e.g. 60 s for lists, keyed by URL) plus `Cache-Control` headers on responses.
- Forms (contact, newsletter, waitlist) are small client islands (or plain HTML forms with progressive
  enhancement) that `POST` to Laravel `/api/*`. No form logic in Astro server.
- i18n: URLs unchanged — `/{en|id}/...`. `/` redirects server-side by `Accept-Language` (302, keeps
  today's "follow the browser" behaviour); every page emits hreflang `id`, `en`, `x-default`.
- **Canonical URL = language-prefixed.** Unprefixed public paths (`/blog/{slug}`, `/projects/{slug}`,
  `/about`, `/work`, `/contact`, `/faq`, …) answer **301 → `/en/...`** (matches today's Vue redirect;
  today nginx serves some of them 200 via prerender — that duplicate goes away). FAQ moves to
  `/{lang}/faq`. Sitemaps list prefixed URLs only. *(Claude's decision after builder check.)*
- Existing URLs must keep working (blog, projects, `/r/{code}` short links, `/faq`, sitemaps,
  `llms.txt`). Laravel keeps owning sitemaps and `llms*.txt`.

### SEO / GEO layer (one place, in Astro)

- `BaseLayout.astro` owns: `<title>`, meta description, canonical, OG/Twitter, hreflang, JSON-LD.
- A TS schema builder replaces `SchemaGraphBuilder.php` + `frontend/src/utils/personSchema.js` +
  the static JSON-LD in `frontend/index.html` (today three sources that must be kept in sync):
  - `Person` "Ali Sadikin Ma" is the primary node: `jobTitle` "AI Consultant", `knowsAbout`,
    `sameAs` = all of Ali's social profiles (moved off Organization).
  - `worksFor` / `founder` → `{"@id": "https://indusia.ai/#organization"}` — reference only, no second
    INDUSIA Organization with different data. **Phase 1 must confirm the exact `@id` indusia.ai emits.**
  - Testimonial `aggregateRating` / `review` are **dropped**, not moved (SEO audit 2026-11-08):
    self-written reviews on your own Person/Organization are self-serving and Google ignores or
    penalises them. Testimonials stay as visible quotes only.
  - `Person` also gets `disambiguatingDescription` ("Indonesian AI consultant and founder of
    INDUSIA.ai, based in Batam — not the former Governor of Jakarta Ali Sadikin"), `image` (the
    indexable hero portrait), `alumniOf`/`hasOccupation` from vault facts, and `sameAs` including the
    Wikidata item once Ali creates it.
  - Career facts (vault `10-Identity/experience.md`): Satnusa `2021-01 → 2025-12`; INDUSIA.ai
    `foundingDate 2026-01-01`.
  - Page-level: `BlogPosting`, `CreativeWork`, `BreadcrumbList`, `FAQPage`, `Service`
    (training/speaker pages, `provider` = Person, `areaServed` Indonesia, no `offers`/price/schedule),
    `ProfilePage` (About = entity home, `mainEntity` = Person), `Event` (award pages).
  - FAQ rich results were retired by Google on 2026-05-07. `FAQPage` stays only because the Q&A is
    visible and useful to LLMs; no SERP feature is promised.
- Every page: H1 contains its keyword; first sentence answers "who/what" plainly (LLM-quotable);
  FAQ 4–6 Q&A where the brief asks for it; ID and EN equivalent.
- Page/title/keyword map = brief §3, unchanged.

### SEO/GEO audit additions (mockup review 2026-11-08)

1. **Indexable hero portrait.** The WebGL point cloud is invisible to crawlers. The hero keeps a real
   `<img>` (descriptive filename `ali-sadikin-ma-pakar-ai-indonesia.jpg`, alt "Ali Sadikin Ma, pakar AI
   Indonesia dan founder INDUSIA.ai", width/height set) in the DOM; the canvas sits on top. Same image
   = `og:image` = `Person.image`.
2. **Name visible in H1.** H1 = "Ali Sadikin Ma — Pakar AI Indonesia" (name line + keyword line), not a
   screen-reader-only name.
3. **Third-person opening sentence (40–60 words)** under the H1, quotable as-is by an LLM: who, what,
   where, one proof. First person stays for the rest of the copy.
4. **One page per award** (`/id/penghargaan/<slug>`, `/en/awards/<slug>`) with `Event` schema
   (name, date, location, organiser, `award` on Person), photos, and the result. Home stage cards
   become `<a>` links to these pages; the lightbox stays as an enhancement.
5. **Speaker page proof:** list of past sessions (event, city, date, topic), embedded or linked video,
   and a downloadable press kit (bio short/long, photos). Event organisers check this first.
6. **About = entity home** (`ProfilePage`): full bio, career timeline with dates, awards, links to
   every profile. All `sameAs` point back here.
7. **Freshness:** `dateModified` on every page from the API `updated_at`; visible "Diperbarui …" on
   About, training and speaker pages.
8. **Content cluster for "pelatihan AI"**: 3–5 blog posts (e.g. "pelatihan AI untuk karyawan",
   "workshop AI perusahaan") linking to the training page, via the existing Content Engine.
9. **Hero performance:** `ogl` installed from npm and bundled (no CDN at runtime); point count cut on
   low-end devices (`navigator.hardwareConcurrency <= 4` or `deviceMemory <= 4`); canvas loads after
   LCP (the `<img>` is the LCP element).
10. **Off-site (Ali, not code):** create the Wikidata item "Ali Sadikin Ma"; use one identical bio
    sentence on LinkedIn, YouTube, Instagram, TikTok and the About page.

### Design system

- Process (per `~/.claude/CLAUDE.md`): ui-ux-pro-max `--design-system --persist` with INDUSIA tokens as
  brand constraint → frontend-design reviews and decides → gsap-core writes motion.
- Source of truth: `Indusia-ai-web/src/styles/tokens.css`, copied into `web/src/styles/tokens.css`
  (copy, not a shared package — two repos, low churn; revisit if they drift).
- Teal `#3fb8c5` is the only CTA/link/active colour; gold `#d4a24d` only for highlights (numbers,
  awards). Space Grotesk / Inter / JetBrains Mono via `@fontsource` (no Google/FA CDN). Heroicons.
- Personal difference from indusia.ai: Ali's photo is the hero centre; numbers/awards in gold more
  prominent. Structure, colour, type identical.
- Motion: one GSAP moment (hero). Everything else calm. `gsap.matchMedia()` +
  `prefers-reduced-motion`. GSAP is the only animation library on the public site.
- Reuse INDUSIA illustration style (`IllusArt`) for service pages.

### Pages (end state)

Brief §3 map plus existing public pages kept at parity: home, about, work, projects (+detail),
blog (+detail, category), awards, gallery, contact, faq, newsletter unsubscribe. New: training
(`/id/pelatihan-ai`, `/en/ai-training`) and speaker (`/id/pembicara-ai`, `/en/ai-speaker`).
Removed: `awards-debug`. Awards and gallery stay what they are today: tabs inside Work
(`/{lang}/awards` and `/{lang}/gallery` 301 → `/{lang}/work?tab=…`), not separate pages.
`/newsletter/unsubscribe`, `/login` and `/admin/*` stay in the Vue SPA (token-only / no SEO value).
*(Claude's decisions after builder check.)*

### Measurement (GEO must stay measurable)

- AI-crawler logging: today `LogAiCrawler` is Laravel `web` middleware → stops seeing routes served by
  Astro. Astro middleware detects the same AI-bot user agents (one shared list, read from a Laravel
  endpoint or copied once with a test that compares them) and fire-and-forget `POST`s the hit to a new
  internal Laravel endpoint that writes `geo_crawler_hits`. Never blocks the response.
- GA4: the tag moves to `BaseLayout.astro`, same `GA4_MEASUREMENT_ID` gating (empty → no tag).
- Proof point wording on pages: "Juara 1 Global AI Demo Day 2026 (Outskill, Bengaluru)" /
  "1st Place, Global AI Demo Day 2026 (Outskill)" — source `Obsidian-Vault/10-Identity/awards.md`.

Home section order (brief §10a): Hero → Who I Am (+INDUSIA link) → Receipts → **Layanan** (new:
Konsultasi AI → WhatsApp · Pelatihan & Workshop → waitlist · Pembicara AI → WhatsApp) → What I Solve
→ International Stages → Selected Work → Testimonials → Latest Writing → Newsletter.
Sections stay toggleable from `/admin/page-sections` (Astro reads `/api/page-sections`); new
`section_type` `services` added to `PageSectionSeeder`. Nav anchors in the Astro header replace
`TheNavigation.vue` `sectionLinks` for public pages.

### Training waitlist (new backend feature)

- Table `training_waitlist`: name, company (nullable), email, whatsapp_number (nullable, E.164 when
  present), interest enum (`inhouse` | `workshop` | `public_class`), note, `source_page`, `locale`,
  `newsletter_opt_in` bool, status enum (`new` | `contacted` | `cancelled`), admin_note,
  `client_key` (unique, idempotency), timestamps.
- Public `POST /api/training-waitlist`: honeypot field, `throttle`, unique `client_key` (repeat submit
  returns the existing row, never a duplicate), button disabled while sending, on-screen confirmation.
  Newsletter row only created when `newsletter_opt_in` is checked.
- Admin (auth:sanctum): list/search/filter (interest, locale, status), update status + admin_note,
  CSV export. Vue admin view `TrainingWaitlist.vue` + sidebar entry "Waitlist Pelatihan", modelled on
  `NewsletterSubscribers.vue` / `ContactsList.vue`.
- Copy is honest: "Segera dibuka — daftar waitlist". No dates, quota, price.

### Backlinks to indusia.ai

Brief §6 as written: one contextual sentence in Who I Am / About, one on the training page
(→ `https://indusia.ai/id/ai-development-services/`, EN `/ai-development-services/`), footer
"Founder INDUSIA.ai". Dofollow.

### Phases (built locally in order; one production release in phase 6; push only with Ali's permission)

1. **Astro foundation + SEO.** Scaffold `web/`, tokens/fonts/base components, `BaseLayout` + schema
   builder, API client + cache, systemd unit, nginx block, `deploy.sh` step. Pilot route: `/faq`.
   AI-crawler logger + GA4 in Astro. Done = `curl` without JS shows H1, body, FAQ, JSON-LD with the
   corrected Person graph, and a GPTBot-UA request lands in `geo_crawler_hits`.
2. **Content parity.** Blog (index, detail, category) and projects (list, detail) in Astro, new design,
   same URLs. Crawler-output diff old vs new per route.
3. **Home + About + remaining pages.** New home order incl. `services` section, hero photo + GSAP,
   INDUSIA links, nav overlap fixed; about, work, awards, gallery, contact.
4. **New pages.** Training (waitlist table, API, admin module) and speaker (WhatsApp). FAQ + Service
   schema. Sitemap includes them.
5. **Full cutover + cleanup.** All public routes → Astro. Remove public views/components from
   `frontend/` (admin stays), Three.js/TresJS, hero video assets, Font Awesome CDN,
   `SpaPrerenderController`, `SeoHtmlComposer`, Blade SEO views. Update `llms.txt` (static + dynamic).
   Crawler check (curl, no JS) on every public route; e2e in one headed Chrome window via CDP.
6. **Deploy** after Ali's explicit OK. Live nginx config synced manually (deploy.sh does not touch it);
   one-time install of `portfolio-web.service`.

### Data Integration Map

| Component | Data source | Existing? | Notes |
|---|---|---|---|
| Blog list/detail/category | `/api/posts`, `/api/posts/{slug}`, `/api/categories/{slug}` | Yes | translations + SEO fields already in resources |
| Projects list/detail | `/api/projects`, `/api/projects/{slug}` | Yes | |
| Home sections + toggles | `/api/page-sections`, homepage/featured API | Yes | add `services` row |
| About / social links / photo | `/api/settings/about`, `/api/settings/site` | Yes | `sameAs` source |
| Testimonials (rating on Person) | `/api/testimonials` | Yes | |
| Awards, gallery | `/api/awards`, `/api/galleries` | Yes | |
| FAQ | `/api/faq` (`config/faq.php`) | Yes | |
| Contact form | `POST /api/contact` | Yes | |
| Newsletter form | `POST /api/newsletter/subscribe` | Yes | |
| Training waitlist | `POST /api/training-waitlist` + admin routes | **No — new** | table + controller + Vue admin view |
| Sitemaps, llms.txt | Laravel `SitemapController`, `GeoController` | Yes | add new pages |
| INDUSIA design tokens | `Indusia-ai-web/src/styles/tokens.css` | Yes | copied once |

### Risks

- **Astro version/adapter**: indusia.ai uses Astro 7 static; server mode with `@astrojs/node` must be
  verified against the current Astro 7 docs in phase 1 (do not assume older adapter APIs).
- **Node service on VPS**: new long-running process; needs systemd restart policy, health check in
  `deploy.sh`, and memory budget next to PHP-FPM + queue workers.
- **nginx ordering**: Astro location blocks must sit above the SPA catch-all, like today's prerender
  blocks. Live config is hand-synced — a missed sync = route still on the SPA (fails safe).
- **Cache staleness**: Astro in-process cache must be short or purgeable so admin edits show quickly.
- **SEO continuity**: URLs, canonical, and existing JSON-LD types must not regress during migration;
  compare crawler output before/after per route.
- **Big-bang release**: all public routes switch at once in phase 6 → needs a rollback path (keep the
  old nginx config + SPA build ready to restore) and a full crawler + e2e pass before switching.
