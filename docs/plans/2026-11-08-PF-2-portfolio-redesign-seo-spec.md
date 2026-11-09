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
6. **Infra first (Ali, 2026-11-08).** Before any Astro work: PHP 8.2 → 8.4 everywhere and patch
   every composer advisory (Laravel v12.32.5 has critical CVE-2026-114509). Software phases start only
   when infra is green. See "Phase 0" below.
7. **Copy:** Claude drafts all page copy (ID + EN) from the keyword map and vault facts, in one
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
  - `worksFor` / `founder` → `{"@id": "https://indusia.ai/#org"}` — reference only, no second
    INDUSIA Organization with different data. (Confirmed 2026-11-08: indusia.ai emits `#org`,
    `#website`, `#office`, and already references `https://alisadikinma.com/#person`.)
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

Header nav highlights the in-page section in view (scrollspy, IntersectionObserver, teal underline,
`aria-current="location"`; hero = no highlight). Testimonials = the 4 live LinkedIn recommendations
(`/api/testimonials`) with photo, verbatim English excerpt, role, LinkedIn link.

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

### SEO/GEO acceptance gate (PRIORITY — Ali 2026-11-08: "SEO dan GEO menjadi focus utama")

A page phase is NOT done until its routes pass `web/scripts/seo-gate.mjs` (built in phase 1, run with
JS disabled = what crawlers and LLMs see). Per route, all must hold, ID and EN:
1. HTTP 200 (or the declared 301), `<html lang>` matches the URL language.
2. `<title>` ≤ 60 chars, contains the page's primary keyword (brief §3 map) and "Ali Sadikin Ma".
3. `<meta name="description">` 120–160 chars, contains the keyword.
4. Exactly one `<h1>`, contains the keyword; home H1 also contains the name.
5. First paragraph after the H1 answers who/what in plain third person (≥ 25 words) — LLM-quotable.
6. `<link rel="canonical">` = the language-prefixed absolute URL; hreflang `id`, `en`, `x-default` all
   present and reciprocal.
7. JSON-LD parses; contains the `Person` node `https://alisadikinma.com/#person` (or references it) with
   `worksFor`/`founder` `{"@id":"https://indusia.ai/#org"}`; page-type node present (see SEO layer);
   no `aggregateRating`/`review` on Person or Organization.
8. OG: `og:title`, `og:description`, `og:image` (absolute, 1200×630 or the portrait), `og:url` = canonical.
9. Every `<img>` has non-empty `alt` (decorative ones `alt=""` + `aria-hidden`), width/height set.
10. The page's required INDUSIA backlinks (table below) are present, dofollow (no `rel="nofollow"`/
    `sponsored`/`ugc`), with the exact anchor text.
11. Internal links: ≥ 2 contextual links to other alisadikinma.com pages (not counting nav/footer).
12. Response has no `noindex`; `robots.txt` allows the route; route is in the sitemap.

Lighthouse SEO = 100 and Accessibility ≥ 95 on home, training, speaker, one blog post (phase 5).

### Backlinks to indusia.ai (PRIORITY — every one is checked by the gate, item 10)

Dofollow, contextual, keyword anchors from the INDUSIA keyword table (`~/.claude/CLAUDE.md`). ID pages
link `https://indusia.ai/id/...`; EN pages link the unprefixed `https://indusia.ai/...` equivalent.

| alisadikinma.com page / spot | Anchor (ID) | Target (ID) |
|---|---|---|
| Home hero lead | INDUSIA.ai | https://indusia.ai/id/ |
| Home "Siapa saya" | perusahaan AI Indonesia | https://indusia.ai/id/ |
| Home Layanan → Konsultasi AI row | jasa pembuatan AI | https://indusia.ai/id/ai-development-services/ |
| Home journey, INDUSIA chapter | machine vision Indonesia · fleet management system · chatbot WhatsApp AI | /id/ai-vision/ · /id/ai-fleet-transportation/ · /id/ai-assistant/ |
| About (entity home) | INDUSIA.ai, perusahaan AI Indonesia + the 3 product anchors above | /id/ + product pages |
| Pelatihan AI (waitlist + FAQ) | jasa pembuatan AI INDUSIA | https://indusia.ai/id/ai-development-services/ |
| Pembicara AI (bio line) | founder INDUSIA.ai | https://indusia.ai/id/ |
| Blog post author box (every post) | founder INDUSIA.ai, perusahaan AI Indonesia | https://indusia.ai/id/ |
| Footer (site-wide) | Founder INDUSIA.ai | https://indusia.ai/id/ |
| JSON-LD (every page) | `worksFor` / `founder` | `{"@id":"https://indusia.ai/#org"}` |

The reverse link already exists: indusia.ai references `https://alisadikinma.com/#person`. The two sites
must keep the same facts (name, role, founding date 2026-01-01, Batam).

### Phases (built locally in order; push only with Ali's permission)

0. **Infra + security (first; ships with the phase-6 release).** Audit 2026-11-08 (Packagist advisories vs `composer.lock`,
   prod read via ssh):
   - prod website = `php8.2-fpm` 8.2.29 (candidate 8.2.35; PHP 8.2 EOL 2026-12-31); prod queue worker +
     cron = `/usr/bin/php` 8.4.12 (candidate 8.4.27) → **two PHP versions for one app**.
   - `laravel/framework` v12.32.5: CVE-2026-114509 **critical** (TokenGuard auth bypass, fixed 12.68.0),
     CRLF in email rule, signed-URL path confusion, debug-page XSS.
   - high/medium: `filament/*` 4.1.0 (MFA bypass, XSS; fixed ≥4.13.3), `guzzlehttp/guzzle` 7.10.0 (<7.15.2),
     `guzzlehttp/psr7` 2.8.0 (<2.12.3), `league/commonmark` 2.7.1 (≥2.10.2), `symfony/*` 7.3.4
     (http-foundation, mime, mailer, routing, process, html-sanitizer → 7.4 latest), `livewire/livewire`
     3.6.4 (>3.8.2), `ueberdosis/tiptap-php` 2.0.0 (≥2.1.1), `psy/psysh`, `league/flysystem`,
     `symfony/polyfill-intl-idn`.
   - Target: PHP 8.4 latest for web (fpm), CLI, queue, cron and local Docker (`docker/php/Dockerfile`);
     `composer update` within current majors (Laravel 12, Filament 4, Sanctum 4); `composer audit` clean
     or each remaining advisory written down with a reason; full PHPUnit green; manual smoke of admin
     login, Content Engine, public forms. Built and verified locally first; shipped to production together with
     the web release in phase 6 (Ali, 2026-11-08), PHP runbook executed before the Astro cutover. Rollback: re-point nginx to `php8.2-fpm.sock` (kept installed) + previous `composer.lock`.

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
