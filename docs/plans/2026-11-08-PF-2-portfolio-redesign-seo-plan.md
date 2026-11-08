> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.
> **Progress ledger — HARD PER-PHASE GATE:** `.gaspol/progress/PROGRESS-<TICKET>.md` (TICKET = this plan's ticket, from the header below; created by `gaspol-plan` at plan-write time — see Plan Document Header step 7). After EACH phase and **BEFORE** starting the next, STOP and do BOTH: (a) tick that phase's `## Checklist` line, (b) append a `## Log` line ending with the handoff cursor. This is **blocking**, like a test gate: no next phase until both are written. **Never batch all updates at the end** — a crash mid-run must leave a truthful state, not a stale one. Update ONLY this file — never the shared `.gaspol/progress.md` (that keeps concurrent plans from clobbering each other). **Backward compatibility:** older ledgers at `.gaspol/progress/<slug>.md` or the shared `.gaspol/progress.md` are STILL READ when a plan's header names them — only NEW plans write the `PROGRESS-<TICKET>.md` path.
> **Self-contained:** this plan is the COMPLETE spec. It must be executable by an agent with **no other context** — a fresh post-`/compact` session, or an external executor in a separate process that cannot see this chat or read a linked PDF. Every file path, contract, config key, and convention it needs is written here **verbatim**, not merely referenced. If it lives only in the conversation, copy it in.

**Ticket:** PF-2
**Ledger:** .gaspol/progress/PROGRESS-PF-2.md
**Spec:** docs/plans/2026-11-08-PF-2-portfolio-redesign-seo-spec.md
**Format:** waves-v1

**Verification commands:**
- php/static: ./vendor/bin/phpstan analyse --memory-limit=1G
- php/lint: ./vendor/bin/pint --test
- php/unit: ./vendor/bin/phpunit
- node/lint: cd web && npm run check
- node/unit: cd web && npm test
- node/build: cd web && npm run build

## Goal

Rebuild the public alisadikinma.com as an Astro 7 server-rendered site (SEO/GEO first, INDUSIA.ai
design family, approved mockup in `design-system/alisadikinma/mockups/`), keep Laravel as API + admin,
add the training waitlist (public form + admin module) and the speaker page, and — FIRST — bring the
infrastructure up to date (PHP 8.4 everywhere, every composer security advisory patched, including the
critical Laravel CVE-2026-114509). **SEO/GEO and dofollow backlinks to indusia.ai are the primary success
criteria** (Ali, 2026-11-08): every page phase must pass the SEO gate (spec §"SEO/GEO acceptance gate")
before it counts as done.

## Architecture Context

Paths are relative to the worktree root
`/Users/alisadikin/Drive-D/Projects/Portfolio_v2/.claude/worktrees/pf-2-portfolio-redesign-seo`
(branch `feat/PF-2-portfolio-redesign-seo`). Never `cd` to the main checkout.

**Running PHP/tests (no host php).** Laravel tests use sqlite `:memory:` (`backend/phpunit.xml`), so they
run in a throwaway container built from THIS worktree, with its own vendor volume (never the main
checkout's `portfolio_v2_backend_vendor`, because phase 0B changes `composer.lock`):

```bash
WT=/Users/alisadikin/Drive-D/Projects/Portfolio_v2/.claude/worktrees/pf-2-portfolio-redesign-seo
docker build -t pf2-backend -f "$WT/docker/php/Dockerfile" "$WT"            # rebuild after Dockerfile changes
PF2() { docker run --rm -v "$WT/backend:/var/www/backend" -v pf2_vendor:/var/www/backend/vendor \
          -w /var/www/backend --entrypoint "" pf2-backend "$@"; }
PF2 composer install --no-interaction        # first time / after composer.lock changes
PF2 php artisan test --filter=SomeTest       # = "php/unit" for this plan
PF2 ./vendor/bin/pint --test <files>         # = "php/lint", only on files this plan touched
```

`web/` (Astro) runs on the host: `cd web && npm test` (vitest), `npm run check` (astro check),
`npm run build`. Node on host must be ≥ 22 (prod has v22.19.0).

**Production facts (read 2026-11-08 via ssh-prod-vps, read-only):** Ubuntu 24.04.3; nginx →
`php8.2-fpm.sock` (PHP 8.2.29, candidate 8.2.35); `/usr/bin/php` CLI = 8.4.12 (candidate 8.4.27) used by
`portfolio-queue.service`, `portfolio-crosspost@.service` and the `schedule:run` crontab; Node v22.19.0;
deployed commit `38b7af83`, `laravel/framework` v12.32.5. Deploy = push to `main` → GitHub Actions →
`scripts/deploy.sh` on the VPS. **Pushing deploys production: push ONLY with Ali's explicit OK.**

**Laravel endpoints reused (all exist):**
- `GET /api/posts` (paginated `{data, meta, links}`; `?lang=id|en`, `?page`, `?per_page`, `?category`),
  `GET /api/posts/{slug}?lang=` — fields: `id, slug, featured_image, image_variants, tags, published_at,
  reading_time, title, excerpt, content, seo, category, available_translations, current_language,
  translations, related_posts, created_at, updated_at`. `PostResource`/`ProjectResource` read `?lang`.
- `GET /api/categories`, `/api/categories/{slug}`; `GET /api/projects`, `/api/projects/{slug}`.
- `GET /api/homepage/featured`, `/api/homepage/stats` — locale from cookie `lang_preference=id|en`
  (middleware `set.locale.by.geoip`); Astro sends that cookie header.
- `GET /api/awards`, `/api/awards/{id}`, `/api/awards/{id}/galleries` (`{award, galleries[{id,title,
  thumbnail,items[{image,sequence}]}]}`), `GET /api/galleries/{id}` (`data.items[].file_url|image`,
  `data.thumbnail`).
- `GET /api/testimonials` (`client_name, company_name, job_title, testimonial_text, client_photo,
  source, linkedin_url, sort_order`), `GET /api/faq` (`data[{question, answer}]`, source
  `backend/config/faq.php`), `GET /api/page-sections`, `GET /api/settings/about`, `/api/settings/site`.
- `POST /api/contact` (throttle 3,15), `POST /api/newsletter/subscribe` (throttle 5,60; requires
  `name`, `email`, `whatsapp_number` E.164 `^\+[1-9]\d{6,14}$`, `source`).
- Sitemaps `GET /api/sitemap*.xml` (`SitemapController`), `GET /api/llms.txt`, `/api/llms-full.txt`
  (`GeoController`), `GET /r/{code}` short links (web route, keep).
- AI crawler logging today: `backend/app/Http/Middleware/LogAiCrawler.php` (`BOT_MAP` const: GPTBot,
  OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-Web, PerplexityBot, Google-Extended,
  Applebot-Extended), appended to the `web` group in `backend/bootstrap/app.php:41`, atomic upsert into
  `geo_crawler_hits(date, bot, hits)` unique(date,bot); read via `GET /api/admin/geo/crawler-hits`.

**Admin (Vue SPA, stays):** `frontend/src/router/index.js`, `frontend/src/layouts/AdminLayout.vue`
(sidebar "Newsletter" entry at line ~171), patterns to copy: `frontend/src/views/admin/NewsletterSubscribers.vue`,
`frontend/src/composables/useNewsletterAdmin.js` (TanStack Query, 30 s staleTime, refetchOnMount
'always'), backend `backend/app/Http/Controllers/Api/Admin/NewsletterAdminController.php`
(list/search/export CSV). API response format: `{success:true,data,message}` /
`{success:false,error:{code,message}}`.

**nginx (template `scripts/nginx/portfolio-8080.conf`, live config hand-synced):** `/api/` → PHP-FPM;
`^~ /storage/`, `^~ /uploads/` static; regex `~* \.(css|js|png|…)$` serves from `frontend/dist`
(⇒ Astro assets need a `^~ /_astro/` block, prefix-`^~` wins over regex); prerender blocks for `/`,
`/faq`, blog, projects, `/r/…`; SPA catch-all `location /`.

**INDUSIA design source:** `/Users/alisadikin/Drive-D/Projects/Indusia-ai-web` — Astro 7.0.6,
`src/styles/tokens.css`, `src/styles/base.css`, `src/lib/seo.ts`, `src/layouts/Base.astro`, fonts via
`@fontsource/*`. Its JSON-LD uses `https://indusia.ai/#org`, `#website`, `#office`, and already
references `https://alisadikinma.com/#person`.

**Approved mockup (visual + behaviour contract):** `design-system/alisadikinma/MASTER.md`,
`design-system/alisadikinma/mockups/{index,pelatihan-ai,pembicara-ai}.html`, `site.css`,
`hero-particles.js`, `gallery.js`, `stages-data.js`. Port these; do not redesign.

**Facts locked (vault, Ali-confirmed):** name "Ali Sadikin Ma"; 1st Place Global AI Demo Day 2026
(Outskill, Bengaluru, 26 AI startups from 16 countries — "16 negara" is NOT a personal stat); 56+
digital products built while leading digital transformation at Satnusa (mix of AI, IoT, mobile apps —
NOT "56 AI products", NOT INDUSIA products); 17 years; 5 awards/fellowships; Google Startup Grind 2018 = attended only ("peserta", never
"tampil"); career: Singapore 2008–2015 (gallery 14), Marlin Booking 2016–2019 (galleries 9,11,13,10,12
incl. IDBYTE; cover `career-marlin.jpg`), gap 2019–2021 left empty, Satnusa 2021-01 → 2025-12
(gallery 16), INDUSIA.ai founded 2026-01-01 (Batam). Covers = gallery `thumbnail` for 14 and 16.

## Tech Stack

- Astro `^7.0.6` + `@astrojs/node` (mode `standalone`, `output: 'server'`) — verify the adapter API against
  current Astro 7 docs in phase 1A before writing config.
- TypeScript, vitest `^4`, `@astrojs/check`; Astro container API for component render tests.
- `@fontsource/space-grotesk`, `@fontsource/inter`, `@fontsource/jetbrains-mono` (no Google CDN).
- `gsap ^3.15` (only animation lib), `ogl ^1.0.11` (bundled from npm, no CDN).
- Laravel 12 (patched), PHP 8.4, PHPUnit via `php artisan test`, sqlite memory.
- Vue 3 admin unchanged except the new waitlist module.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Blog index/detail/category | posts + categories | `GET /api/posts`, `/api/posts/{slug}`, `/api/categories/{slug}` | Yes | Use existing via `web/src/lib/api.ts` |
| Projects list/detail | projects | `GET /api/projects`, `/api/projects/{slug}` | Yes | Use existing |
| Home latest writing / featured | homepage aggregation | `GET /api/homepage/featured` (cookie locale) | Yes | Use existing |
| Home section toggles | page_sections | `GET /api/page-sections` | Yes | Use existing; add `services` row (3E) |
| Career journey galleries | galleries 14, 9, 11, 13, 10, 12, 16 | `GET /api/galleries/{id}` | Yes | Use existing |
| Stages bento + award pages | awards 13, 10, 11, 7, 8, 9 + galleries | `GET /api/awards`, `/api/awards/{id}/galleries` | Yes | Use existing; add `slug` + `GET /api/awards/slug/{slug}` (3G) |
| Testimonials | testimonials (4 LinkedIn rows) | `GET /api/testimonials` | Yes | Use existing |
| FAQ page | `config/faq.php` | `GET /api/faq` | Yes | Use existing |
| About / social links / photo | settings `about`, `site` | `GET /api/settings/about`, `/api/settings/site` | Yes | Use existing (`sameAs`) |
| Contact form | contacts | `POST /api/contact` | Yes | Use existing |
| Newsletter form | newsletters | `POST /api/newsletter/subscribe` | Yes | Use existing |
| Training waitlist (public) | `training_waitlist` | `POST /api/training-waitlist` | **No** | Create real table + endpoint (4A, 4B) |
| Training waitlist (admin) | `training_waitlist` | `GET/PATCH /api/admin/training-waitlist`, `GET …/export` | **No** | Create real endpoints + Vue view (4C, 4D) |
| AI crawler hits from Astro | `geo_crawler_hits` | `POST /api/internal/crawler-hit` | **No** | Create real endpoint (1I) |
| AI bot list (shared) | `LogAiCrawler::BOT_MAP` | `backend/config/ai_bots.php` | **No** | Extract to config (1I); Astro copy checked by test (1J) |
| Sitemaps, llms.txt | `SitemapController`, `GeoController` | `/api/sitemap*.xml`, `/api/llms*.txt` | Yes | Extend with new pages (4G) |
| INDUSIA design tokens | Indusia-ai-web `tokens.css` | file copy | Yes | Copy once (1B) |
| INDUSIA backlinks | spec backlink table | static links in components | n/a | Implement per page; checked by SEO gate |

## Phases

### Phase 0A: PHP 8.4 in local Docker

**Deps:** none
**Complexity:** light — one Dockerfile, no design decision.
**Estimated time:** 15 minutes

**Files:**
- Modify: `docker/php/Dockerfile`
- Create: `scripts/check-php-runtime.sh`

**Steps:**
1. Write failing test for PHP runtime version: `scripts/check-php-runtime.sh` runs `php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'` in the given command context and exits 1 unless it prints `8.4`. Expected error: `check-php-runtime: expected 8.4, got 8.2`
2. Build `pf2-backend` from the current Dockerfile, run the script through `PF2`, confirm it fails with `got 8.2`
3. Change `FROM php:8.2-fpm` to `FROM php:8.4-fpm`; keep every extension (`pdo_mysql mbstring exif pcntl bcmath gd zip intl`) and the gd freetype/jpeg configure
4. Rebuild `pf2-backend`, run the script, confirm `8.4`; run `PF2 php -m` and confirm all 8 extensions are listed
5. `PF2 composer install`, then `PF2 php artisan test` once — record pass/fail counts in the ledger `## Pre-flight` as the PHP 8.4 baseline (legacy failures are expected: CLAUDE.md notes ~186 old fails; record the exact number, do not fix them here)
6. Commit: "build(PF-2): local Docker on PHP 8.4"

**Verification:**
- [ ] `scripts/check-php-runtime.sh` exits 0 inside `pf2-backend`
- [ ] `PF2 php -m` lists pdo_mysql, mbstring, exif, pcntl, bcmath, gd, zip, intl
- [ ] Baseline test counts on PHP 8.4 written in ledger Pre-flight

### Phase 0B: Patch every composer security advisory

**Deps:** 0A
**Complexity:** complex — framework + auth/security packages; a regression here breaks admin login and pipelines.
**Estimated time:** 30 minutes

**Files:**
- Modify: `backend/composer.json`
- Modify: `backend/composer.lock`
- Create: `backend/phpstan.neon`
- Create: `backend/phpstan-baseline.neon`
- Create: `backend/tests/Feature/Security/TokenGuardNonStringCredentialTest.php`
- Test: `backend/tests/Feature/Security/TokenGuardNonStringCredentialTest.php`

**Steps:**
1. Write failing test for CVE-2026-114509 (non-string API credential must not authenticate): send `Authorization: Bearer` + JSON/array token forms (`api_token[]=x`, `{"api_token":["x"]}`) to an `auth:sanctum` route (`GET /api/admin/dashboard/stats`) and to any route guarded by the `token` driver if one exists; assert 401. Expected error: assertion fails only if the bypass is reachable — if it already returns 401 on v12.32.5, keep the test as a regression guard and note "not reachable via sanctum" in the ledger
2. Run `PF2 composer audit --format=plain`, save output to the ledger (before-state)
3. `PF2 composer update --with-all-dependencies` constrained to current majors: `laravel/framework:^12.68`, `filament/filament:^4.13.3`, `livewire/livewire:^3.8.3`, `guzzlehttp/guzzle:^7.15.2`, `guzzlehttp/psr7:^2.12.3`, `league/commonmark:^2.10.2`, `ueberdosis/tiptap-php:^2.1.1`, `psy/psysh:^0.12.19`, `symfony/*` to the latest 7.x; bump `composer.json` constraints where they pin below these
4. `PF2 composer audit` — must report no advisories; any that remain need a written reason in ledger `## Utang terbuka` (e.g. fixed only in a new major)
5. `PF2 php artisan test` — failures must not exceed the 0A baseline; every NEW failure is investigated and fixed (use gaspol-debug), not skipped
6. `PF2 php artisan route:list >/dev/null` and `PF2 php artisan filament:upgrade` (if Filament asks) — both exit 0
7. Add PHPStan (Ali, 2026-11-08): `PF2 composer require --dev larastan/larastan`, `backend/phpstan.neon` (include larastan extension, `level: 5`, paths `app`, `routes`, `database`), generate `phpstan-baseline.neon` for the legacy errors so only NEW errors fail; `PF2 ./vendor/bin/phpstan analyse --memory-limit=1G` exits 0. From here every phase that touches PHP runs it
8. Commit: "fix(PF-2): patch composer security advisories (Laravel CVE-2026-114509 et al.)"

**Verification:**
- [ ] `composer audit` clean, or each remaining advisory listed with a reason in the ledger
- [ ] `composer.lock` has `laravel/framework` ≥ v12.68.0 and `filament/filament` ≥ v4.13.3
- [ ] Test failures ≤ 0A baseline; no new failure
- [ ] Security: non-string credential test returns 401
- [ ] `phpstan analyse` exits 0 with the baseline in place

### Phase 0D: Fix LinkedIn schedule timezone (posts publish 7 h late)

**Deps:** 0B
**Complexity:** complex — production data semantics for every scheduled social post + a data repair.
**Estimated time:** 30 minutes

**Files:**
- Create: `backend/app/Models/Concerns/StoresDatesInAppTimezone.php`
- Modify: `backend/app/Models/LinkedInPost.php`
- Modify: `backend/app/Models/FacebookPost.php`
- Modify: `backend/app/Models/InstagramPost.php`
- Modify: `backend/app/Models/TiktokPost.php`
- Modify: `backend/app/Models/ThreadsPost.php`
- Modify: `backend/app/Models/RedditPost.php`
- Create: `backend/app/Console/Commands/RepairWibScheduleTimes.php`
- Test: `backend/tests/Feature/ScheduleTimezoneStorageTest.php`
- Test: `backend/tests/Feature/RepairWibScheduleTimesTest.php`

Found 2026-11-08 by the Carbon 3.10→3.15 update (3 `LinkedInDraftApproveSlotAssignmentTest` cases only
passed through a Carbon mock-timezone quirk). `app.timezone` = UTC and the publish cron compares
`cancel_window_ends_at <= now()` in UTC, but `LinkedInSchedulingService::scheduleAt` (and other writers
passing an `Asia/Jakarta` Carbon: `PublishSlotOrchestrator` `$nextSlot`,
`AutoScheduleManualReviewLinkedInPosts` `$slot`, `RepurposeJob` `$when`) store the WIB wall-clock
because Eloquent's `fromDateTime()` formats without converting the zone. Production proof (read-only):
post 190 raw `scheduled_at 2026-07-06 12:00:00`, `published_at 12:00:19` UTC = 19:00 WIB for a 12:00 WIB
slot; post 186 raw 22:00 = 05:00 WIB was correct (UTC writer). Ali chose (2026-11-08): fix the code in
PF-2 and repair pending rows at release; published rows are not touched.

**Steps:**
1. Write failing test for storage zone: `scheduleAt($draft, Carbon::parse('2026-05-13 12:00', 'Asia/Jakarta'), 'test')` stores raw `scheduled_at` and `cancel_window_ends_at` = `2026-05-13 05:00:00` and the IG/FB/TikTok/Threads siblings the same; a UTC Carbon is stored unchanged. Expected error: `Failed asserting that '2026-05-13 12:00:00' matches expected '2026-05-13 05:00:00'`
2. Run, confirm failure
3. Implement trait `StoresDatesInAppTimezone` overriding `fromDateTime($value)`: a `DateTimeInterface` value is converted (`copy()->setTimezone(config('app.timezone'))`) before formatting; strings/null keep today's behaviour. Use it on the 6 post models. Do NOT change any writer's call site — one fix point
4. Run the new test + `LinkedInDraftApproveSlotAssignmentTest` (the 3 cases must now pass on Carbon 3.15 for the right reason) + every LinkedIn/cross-post/scheduler test; full suite: failures back to the 167 baseline, 0 new
5. Write failing test for the repair command, then implement `linkedin:repair-wib-schedules {--dry-run}`: for `awaiting_publish` LinkedIn drafts with `scheduled_at` in the future, read the LAST `pipeline_state_log` entry carrying `publish_at` (ISO-8601 with offset); if `publish_at` as UTC ≠ raw `scheduled_at` by exactly 7 h, set `scheduled_at` + `cancel_window_ends_at` (and siblings) to the UTC instant; rows without a logged `publish_at`, or differing by any other amount, are only listed (never changed). `--dry-run` prints `id | raw | intended WIB | action` and writes nothing; non-dry-run prints the same and a total. Tests: 7 h row fixed, correct row untouched, no-log row listed only, published row ignored, dry-run writes nothing
6. PHPStan + pint on touched files; commit: "fix(PF-2): store schedule timestamps in UTC (posts were publishing 7 h late)"
7. Add the repair command (dry-run first, then real run, Ali watching) to `docs/runbooks/pf2-php84-upgrade.md` release steps (Phase 0C) — production is NOT touched in this phase

**Verification:**
- [ ] Raw DB value for a 12:00 WIB slot is `05:00:00` (test)
- [ ] `LinkedInDraftApproveSlotAssignmentTest` green on Carbon 3.15 without changing its assertions
- [ ] Repair command dry-run writes nothing; real run only changes rows whose logged `publish_at` proves the 7 h shift

### Phase 0C: Production PHP 8.4 runbook + one-runtime check

**Deps:** 0B, 0D
**Complexity:** complex — production infrastructure change; wrong step takes the site down.
**Estimated time:** 20 minutes (writing); execution only with Ali's OK

**Files:**
- Create: `docs/runbooks/pf2-php84-upgrade.md`
- Modify: `scripts/check-php-runtime.sh`
- Modify: `scripts/nginx/portfolio-8080.conf`
- Modify: `CLAUDE.md`

**Steps:**
1. Write failing test for one PHP runtime per app: extend `scripts/check-php-runtime.sh --compare` to read the FPM socket from an nginx conf (`grep -o 'php[0-9.]*-fpm.sock'`) and compare with `php -v`; run it against `scripts/nginx/portfolio-8080.conf` + local CLI 8.4. Expected error: `check-php-runtime: fpm 8.2 != cli 8.4`
2. Change every `php8.2-fpm.sock` in `scripts/nginx/portfolio-8080.conf` to `php8.4-fpm.sock`; rerun, confirm pass
3. Write the runbook (read-only checks first, then changes, each with its verify + rollback):
   a. Read: `php8.2 -m` vs `php8.4 -m`; list `/etc/php/8.2/fpm/conf.d/99-portfolio.ini` and pool `www.conf` overrides (upload 50M / post 200M / 120s / 256M).
   b. `apt-get install` `php8.4-fpm` + every extension present in 8.2 but missing in 8.4; `apt-get install --only-upgrade php8.4-cli php8.4-*` to 8.4.27; copy `99-portfolio.ini` and pool user/group/listen owner to `/etc/php/8.4/fpm/`.
   c. `php-fpm8.4 -t`; `systemctl enable --now php8.4-fpm`.
   d. nginx: switch socket to `php8.4-fpm.sock`, `nginx -t`, `systemctl reload nginx`.
   e. `systemctl restart portfolio-queue portfolio-crosspost@*`; `php artisan queue:restart`.
   f. Verify: `curl -s https://alisadikinma.com/api/health`, admin login, one Content Engine page, `php -i | grep upload_max` via a temporary FPM check route is NOT allowed — use `php-fpm8.4 -i` instead.
   g. Rollback: re-point nginx to `php8.2-fpm.sock` (php8.2-fpm stays installed until PF-2 phase 6A is green), reload nginx.
   h. Upgrade php8.2 to 8.2.35 is unnecessary once off it; remove php8.2-fpm only after 7 days green (separate, noted in Utang terbuka).
4. Update root `CLAUDE.md`: PHP 8.4 everywhere (VPS fpm + CLI + Docker), runbook link, "Common Issues" FPM path `/etc/php/8.4/fpm/…`
5. Commit: "ops(PF-2): PHP 8.4 production runbook + runtime check"
6. No production action here (Ali, 2026-11-08: production PHP 8.4 + the composer patch ship together with the web release in Phase 6A). Infra is "done" for software work once 0A–0C are green locally.

**Verification:**
- [ ] `check-php-runtime.sh --compare` passes on the repo nginx template
- [ ] Runbook has a verify and a rollback line for every change step
- [ ] Runbook is referenced from Phase 6A and lists the PHP 8.4 steps before the Astro cutover steps

### Phase 1A: Scaffold `web/` (Astro server mode)

**Deps:** 0C
**Complexity:** medium — new app, but a known pattern (indusia.ai) to copy.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/package.json`
- Create: `web/astro.config.mjs`
- Create: `web/tsconfig.json`
- Create: `web/vitest.config.ts`
- Create: `web/src/lib/config.ts`
- Create: `web/.env.example`
- Modify: `.gitignore`
- Test: `web/src/lib/config.test.ts`

**Steps:**
1. Write failing test for `getConfig()` returning `apiInternalUrl` (env `API_INTERNAL_URL`, default `http://127.0.0.1:8080/api`), `siteUrl` (`SITE_URL`, default `https://alisadikinma.com`), `ga4Id` (`GA4_MEASUREMENT_ID`, default `''`), `internalToken` (`INTERNAL_API_TOKEN`, no default → throws when missing in production). Expected error: `Cannot find module './config'`
2. Run `npm test`, confirm it fails for that reason
3. Create the app: `astro@^7`, `@astrojs/node` (`mode: 'standalone'`), `output: 'server'`, `site: 'https://alisadikinma.com'`, deps from Tech Stack; scripts `dev`, `build`, `check`, `test` (vitest run); `.gitignore` adds `web/node_modules`, `web/dist`, `web/.astro`. Check the current Astro 7 + `@astrojs/node` docs before writing config (do not assume Astro 4/5 APIs)
4. Implement `config.ts`, run tests, confirm green; `npm run build` exits 0 and `node web/dist/server/entry.mjs` answers `/` locally
5. Commit: "feat(PF-2): scaffold Astro server app in web/"

**Verification:**
- [ ] `npm run build` exits 0 and the server starts on `HOST=127.0.0.1 PORT=4321`
- [ ] Missing `INTERNAL_API_TOKEN` with `NODE_ENV=production` throws a named error

### Phase 1B: Design tokens, fonts, base + component CSS

**Deps:** 1A
**Complexity:** medium — port of approved CSS, several files.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/styles/tokens.css`
- Create: `web/src/styles/base.css`
- Create: `web/src/styles/components.css`
- Test: `web/src/styles/tokens.test.ts`

**Steps:**
1. Write failing test for reads `tokens.css` and asserts `--bg-deep:#0a1220`, `--bg-surface:#0e1828`, `--bg-elevated:#16243a`, `--text-primary:#e8eef8`, `--text-secondary:#9fb0c7`, `--accent-primary:#3fb8c5`, `--accent-gold:#d4a24d`, `--header-h:68px` exist. Expected error: `ENOENT: tokens.css`
2. Run, confirm failure
3. Copy `Indusia-ai-web/src/styles/tokens.css` (add `--header-h`), port `design-system/alisadikinma/mockups/site.css` into `base.css` (reset, type, focus ring 2px teal + 2px offset, `scroll-padding-top`) and `components.css` (btn, nav incl. `.is-active` underline, sec, proof, journey, bento, lightbox, tms, topics, formats, form, faq, footer, hero `.hx*` incl. mobile clamp + `.hx-more`); fonts via `@fontsource` imports (Space Grotesk 500/600, Inter 400/500/600, JetBrains Mono 500), `font-display: swap`
4. Run tests green
5. Commit: "feat(PF-2): INDUSIA tokens + ported mockup CSS"

**Verification:**
- [ ] All 8 tokens asserted present with exact hex values
- [ ] No `fonts.googleapis.com` reference anywhere under `web/src`

### Phase 1C: API client with cache, timeout, locale

**Deps:** 1A
**Complexity:** complex — interface every page consumes; failure modes decide uptime.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/lib/api.ts`
- Test: `web/src/lib/api.test.ts`

**Steps:**
1. Write failing test for `apiGet(path, {lang, ttl})` with mocked `fetch`: builds `${apiInternalUrl}${path}?lang=`, sends `Cookie: lang_preference=<lang>`, caches by full URL for `ttl` (default 60 s), returns parsed `data`. Expected error: `apiGet is not a function`
2. Run, confirm failure
3. Implement with these cases each covered by a test: cache hit (fetch called once for two calls), expiry (second fetch after ttl via fake timers), 404 → returns `null` (page renders 404), 5xx or network error with a cached value → return stale value + `console.warn('[api] stale', url, status)`, 5xx without cache → throw `ApiError{status,url}`, timeout 4000 ms via `AbortController` → treated as network error, malformed JSON → `ApiError`, concurrent identical requests share one in-flight promise
4. Run tests green
5. Commit: "feat(PF-2): server-side API client with cache + stale fallback"

**Verification:**
- [ ] 8 behaviour cases above each have a passing test
- [ ] Warning log line includes URL and status for 3am debugging

### Phase 1D: Schema builder (JSON-LD)

**Deps:** 1A
**Complexity:** complex — the GEO core; wrong graph = wrong entity for Google/LLMs.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/lib/schema.ts`
- Test: `web/src/lib/schema.test.ts`

**Steps:**
1. Write failing test for `personNode()`: `@id` `https://alisadikinma.com/#person`, `name` "Ali Sadikin Ma", `jobTitle` "AI Consultant", `disambiguatingDescription` "Indonesian AI consultant and founder of INDUSIA.ai, based in Batam. Not the former Governor of Jakarta Ali Sadikin.", `image` portrait URL, `worksFor` and `founder` = `{"@id":"https://indusia.ai/#org"}`, `award` "1st Place, Global AI Demo Day 2026 (Outskill, Bengaluru)", `sameAs` from settings (input), `knowsAbout` list. Expected error: `personNode is not a function`
2. Run, confirm failure
3. Implement nodes + tests: `websiteNode`, `breadcrumb(items)`, `faqPage(qas)`, `blogPosting(post)` (headline, datePublished, dateModified, author `{@id person}`, image, inLanguage), `creativeWork(project)`, `serviceNode({name, url, lang})` (`provider` person, `areaServed` Indonesia, no `offers`), `profilePage()` (`mainEntity` person), `eventNode(award)` (name, startDate, location, organizer; person gets `award`), `graph(...nodes)` wrapper. Tests: no node anywhere contains `aggregateRating` or `review`; Person never has `foundingDate`; every output is valid JSON
4. Run tests green
5. Commit: "feat(PF-2): JSON-LD schema builder (Person-first graph)"

**Verification:**
- [ ] Person `worksFor`/`founder` reference `https://indusia.ai/#org` exactly
- [ ] Test proves no `aggregateRating`/`review` in any builder output

### Phase 1E: i18n + route map

**Deps:** 1A
**Complexity:** medium — pure functions, but every URL and hreflang depends on it.
**Estimated time:** 10 minutes

**Files:**
- Create: `web/src/lib/i18n.ts`
- Test: `web/src/lib/i18n.test.ts`

**Steps:**
1. Write failing test for `alternates('/id/pelatihan-ai')` returning `{id:'/id/pelatihan-ai', en:'/en/ai-training', 'x-default':'/en/ai-training'}`. Expected error: `alternates is not a function`
2. Run, confirm failure
3. Implement: `LANGS=['id','en']`; route pairs `pelatihan-ai↔ai-training`, `pembicara-ai↔ai-speaker`, `penghargaan/{slug}↔awards/{slug}`, `tentang↔about`? — NO: keep today's URLs `/{lang}/about`, `/{lang}/work`, `/{lang}/contact`, `/{lang}/blog…`, `/{lang}/projects…`, `/{lang}/faq` identical in both langs; `pickLang(acceptLanguage)` (id if first matching tag is `id`/`in`, else en); `localizedPath(path, lang)`. Tests: empty/garbage Accept-Language → en; unknown path → same path with lang swapped
4. Run tests green
5. Commit: "feat(PF-2): i18n route map + hreflang alternates"

**Verification:**
- [ ] Training/speaker/award pairs map both directions
- [ ] `pickLang('')`, `pickLang('*')`, `pickLang('id-ID,id;q=0.9')` → en, en, id

### Phase 1F: SEO gate script

**Deps:** 1A
**Complexity:** complex — this script is the acceptance test for every page phase.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/scripts/seo-gate.mjs`
- Create: `web/scripts/seo-gate.config.mjs`
- Create: `web/scripts/fixtures/good.html`
- Create: `web/scripts/fixtures/bad.html`
- Test: `web/scripts/seo-gate.test.mjs`

**Steps:**
1. Write failing test for `checkHtml(html, routeSpec)` on `bad.html` returns failures for each of the 12 spec gate items (title, description, single H1 w/ keyword, first paragraph ≥ 25 words, canonical, reciprocal hreflang, JSON-LD parse + `#person` + `#org` + page type + no aggregateRating, OG set, img alt/size, required INDUSIA backlinks dofollow with exact anchor, ≥ 2 contextual internal links, no noindex); on `good.html` returns `[]`. Expected error: `Cannot find module './seo-gate.mjs'`
2. Run, confirm failure
3. Implement: pure `checkHtml` (no browser, regex/DOM via `node-html-parser` devDependency) + CLI `node web/scripts/seo-gate.mjs --base http://127.0.0.1:4321 --routes <group>` that fetches with `User-Agent: Mozilla/5.0 (compatible; GPTBot/1.0)` and JS off, prints one line per failure `route | item | detail`, exits 1 on any failure. `seo-gate.config.mjs` holds per-route keyword, page type, and required backlinks (copy the spec backlink table verbatim, ID + EN targets)
4. Run tests green
5. Commit: "test(PF-2): SEO/GEO acceptance gate"

**Verification:**
- [ ] `bad.html` produces one failure per gate item (12)
- [ ] Backlink check fails on `rel="nofollow"` and on a wrong anchor

### Phase 1G: BaseLayout + SeoHead

**Deps:** 1B, 1D, 1E
**Complexity:** medium — one layout, uses tested libs.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/layouts/BaseLayout.astro`
- Create: `web/src/components/SeoHead.astro`
- Test: `web/src/components/SeoHead.test.ts`

**Steps:**
1. Write failing test for Astro container API — rendering `SeoHead` with `{title, description, path:'/id/faq', lang:'id', jsonld, image}` and asserting `<title>`, description, canonical `https://alisadikinma.com/id/faq`, hreflang id/en/x-default, OG/Twitter tags, one `application/ld+json`. Expected error: `Cannot find module './SeoHead.astro'`
2. Run, confirm failure
3. Implement; `BaseLayout` sets `<html lang>`, imports CSS, renders Header/Footer slots, GA4 `gtag` only when `ga4Id !== ''`, `<meta name="robots" content="index,follow">`, preload of the hero image when passed
4. Run tests green
5. Commit: "feat(PF-2): BaseLayout + SeoHead"

**Verification:**
- [ ] Empty `GA4_MEASUREMENT_ID` → no `googletagmanager` script in output
- [ ] Canonical and hreflang are absolute https URLs

### Phase 1H: Header (scrollspy) + Footer (INDUSIA backlink)

**Deps:** 1G
**Complexity:** medium — UI port + one small client script.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/components/Header.astro`
- Create: `web/src/components/Footer.astro`
- Create: `web/src/scripts/scrollspy.ts`
- Test: `web/src/scripts/scrollspy.test.ts`

**Steps:**
1. Write failing test for `pickActive(visibleIds, orderedIds)`: returns the LAST id in document order that is visible; empty set → `null` (hero = no highlight). Expected error: `pickActive is not a function`
2. Run, confirm failure
3. Implement header per mockup: brand "Ali Sadikin Ma / AI", nav (home in-page: Tentang `#tentang`, Perjalanan `#perjalanan`, Layanan `#layanan`, Blog `#tulisan` with `data-spy`; pages: Pelatihan AI, Pembicara AI; language switch link from `alternates()`), WhatsApp CTA; off-home the in-page links point to `/{lang}/#id`. Scrollspy: IntersectionObserver `rootMargin: "-45% 0px -50% 0px"`, sets `.is-active` + `aria-current="location"`. Footer: "© 2026 Ali Sadikin Ma · Founder INDUSIA.ai" with dofollow link (ID `https://indusia.ai/id/`, EN `https://indusia.ai/`), social links from settings, FAQ link
4. Run tests green
5. Commit: "feat(PF-2): header with scrollspy + footer backlink"

**Verification:**
- [ ] `pickActive` tests: none visible → null; two visible → later one
- [ ] Footer link to indusia.ai has no `rel` containing nofollow/ugc/sponsored

### Phase 1I: Laravel shared bot list + internal crawler-hit endpoint

**Deps:** 0C
**Complexity:** complex — new endpoint with shared-secret auth.
**Estimated time:** 15 minutes

**Files:**
- Create: `backend/config/ai_bots.php`
- Modify: `backend/app/Http/Middleware/LogAiCrawler.php`
- Create: `backend/app/Http/Controllers/Api/InternalCrawlerHitController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/config/services.php`
- Test: `backend/tests/Feature/InternalCrawlerHitTest.php`

**Steps:**
1. Write failing test for `POST /api/internal/crawler-hit` with header `X-Internal-Token: <services.internal.token>` and `{"bot":"GPTBot","path":"/id/faq"}` → 202 and `geo_crawler_hits` today/GPTBot `hits=1`; second call → `hits=2`. Expected error: `404 Not Found`
2. Run, confirm failure
3. Implement: move `BOT_MAP` to `config/ai_bots.php` (return `['map' => [...]]`), middleware reads config; `services.internal.token` = env `INTERNAL_API_TOKEN`; controller: token compared with `hash_equals`, missing/empty server token → 503 `INTERNAL_TOKEN_NOT_CONFIGURED`, wrong token → 401, `bot` not in config map → 422, same atomic upsert as middleware (extract to a small `CrawlerHitRecorder` class used by both — one implementation), `throttle:600,1`, route outside `auth:sanctum`; log warning on DB failure (fail-open, still 202)
4. Tests for: 401, 422, 503, double increment, existing `LogAiCrawlerTest` still green
5. Commit: "feat(PF-2): internal crawler-hit endpoint + shared bot config"

**Verification:**
- [ ] Security: wrong/missing token → 401; unset server token → 503; compare uses `hash_equals`
- [ ] `LogAiCrawlerTest` and `InternalCrawlerHitTest` green

### Phase 1J: Astro middleware — redirects + crawler logging

**Deps:** 1C, 1E, 1I
**Complexity:** complex — runs on every request; must never block or break a response.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/middleware.ts`
- Create: `web/src/lib/aiBots.ts`
- Test: `web/src/middleware.test.ts`
- Test: `web/src/lib/aiBots.test.ts`

**Steps:**
1. Write failing test for request `/` with `Accept-Language: id-ID` → 302 `/id/`; `/blog/x` → 301 `/en/blog/x`; `/about` → 301 `/en/about`; `/faq` → 301 `/en/faq`; `/en/awards` → 301 `/en/work?tab=awards`; `/en/gallery` → 301 `/en/work?tab=gallery`; `/en/awards-debug` → 404. Expected error: `onRequest is not a function`
2. Run, confirm failure
3. Implement; crawler logging: match UA against `aiBots.ts` map, fire-and-forget `fetch(apiInternalUrl+'/internal/crawler-hit', {method:'POST', headers:{'X-Internal-Token'}, body})` without `await` before `next()`, `.catch(e => console.warn('[crawler-hit]', e.message))`. `aiBots.test.ts` parses `../backend/config/ai_bots.php` keys with a regex and asserts the TS map has exactly the same keys
4. Run tests green (including a test that a rejected fetch does not throw or delay the response)
5. Commit: "feat(PF-2): Astro middleware (locale redirects, AI-crawler logging)"

**Verification:**
- [ ] Bot list parity test fails when a key is added to one side only
- [ ] Crawler POST failure leaves response status 200

### Phase 1K: Copy files (phase 1) + key parity

**Deps:** 1A
**Complexity:** light — content files + one parity test.
**Estimated time:** 10 minutes

**Files:**
- Create: `web/src/content/copy/id.ts`
- Create: `web/src/content/copy/en.ts`
- Create: `docs/copy/PF-2-phase1.md`
- Test: `web/src/content/copy/parity.test.ts`

**Steps:**
1. Write failing test for `id` and `en` copy objects have identical key trees and no empty strings. Expected error: `Cannot find module './id'`
2. Run, confirm failure
3. Write nav, footer, FAQ page title/description/H1/intro in ID + EN (keywords: "pakar AI Indonesia" / "AI consultant Indonesia"; FAQ H1 contains the keyword). Mirror the text into `docs/copy/PF-2-phase1.md` for Ali to approve
4. Run tests green
5. Commit: "content(PF-2): phase 1 copy ID/EN"

**Verification:**
- [ ] Parity test passes; adding a key to one language makes it fail
- [ ] `docs/copy/PF-2-phase1.md` marked "menunggu persetujuan Ali" until approved (record approval in ledger)

### Phase 1L: FAQ pilot route

**Deps:** 1F, 1G, 1H, 1J, 1K
**Complexity:** medium — first real page end-to-end.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/faq.astro`
- Create: `web/src/components/FaqList.astro`
- Test: `web/src/pages/faq.test.ts`

**Steps:**
1. Write failing test for container API, mocked `apiGet('/faq' — `) asserting H1 contains the keyword, `<dl>` with every Q&A, `FAQPage` + Person in JSON-LD, 404 for `lang` not in `LANGS`. Expected error: `Cannot find module '../pages/[lang]/faq.astro'`
2. Run, confirm failure
3. Implement page from `/api/faq`; API down with no cache → 503 page with a plain message, not a crash; at least 2 contextual internal links (e.g. to Pelatihan AI and About)
4. Run tests green; start Laravel (main docker stack) + `web` dev server; run `node web/scripts/seo-gate.mjs --routes faq` → exit 0 for `/id/faq` and `/en/faq`; send a GPTBot UA request and confirm a `geo_crawler_hits` row increments in the local DB
5. Commit: "feat(PF-2): /{lang}/faq pilot on Astro"

**Verification:**
- [ ] SEO gate exit 0 for `/id/faq` and `/en/faq`
- [ ] `curl` without JS shows H1, all Q&A, and the JSON-LD graph with `#person` and `#org`
- [ ] GPTBot request increments `geo_crawler_hits`

### Phase 2A: Blog index

**Deps:** 1L
**Complexity:** medium — list page, existing API.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/blog/index.astro`
- Create: `web/src/components/PostCard.astro`
- Create: `web/src/components/Pagination.astro`
- Test: `web/src/pages/blog-index.test.ts`

**Steps:**
1. Write failing test for renders posts from mocked `/posts?lang=id&page=2&per_page=12`, `rel="prev"/"next"` links, canonical keeps `?page=2` only when page>1, empty list shows "Belum ada artikel" state, `page=0`/`page=abc` → 301 to page 1, page beyond last → 404. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement with `CollectionPage` + `BreadcrumbList` JSON-LD; images use `image_variants` when present with width/height
4. Run tests green; seo-gate `--routes blog-index` exit 0
5. Commit: "feat(PF-2): blog index on Astro"

**Verification:**
- [ ] SEO gate passes for `/id/blog` and `/en/blog`
- [ ] Out-of-range page returns 404, malformed page redirects to page 1

### Phase 2B: Blog detail (+ author box backlink)

**Deps:** 2A
**Complexity:** complex — highest-traffic SEO page; HTML from CMS; dateModified; backlink on every post.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/blog/[slug].astro`
- Create: `web/src/components/AuthorBox.astro`
- Test: `web/src/pages/blog-detail.test.ts`

**Steps:**
1. Write failing test for mocked post renders H1 = title, `<time datetime>` published + modified, `BlogPosting` with `dateModified` = `updated_at`, `BreadcrumbList` Home→Blog→Category→Post, author box with dofollow anchor "founder INDUSIA.ai, perusahaan AI Indonesia" → `https://indusia.ai/id/` (EN → `https://indusia.ai/`), related posts, unknown slug → 404, post missing the requested language → 301 to the available language. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement; post `content` is admin/Content-Engine HTML rendered with `set:html` — strip `<script>` and `on*=` attributes with a tested `sanitizeHtml()` helper before rendering
4. Run tests green; seo-gate on 3 real slugs (one ID-only, one bilingual, one with images)
5. Commit: "feat(PF-2): blog detail with author box backlink"

**Verification:**
- [ ] Security: `sanitizeHtml` test removes `<script>` and `onerror=` from content
- [ ] SEO gate passes on 3 real posts, including the author-box INDUSIA backlink

### Phase 2C: Blog category

**Deps:** 2A
**Complexity:** light — variant of the index.
**Estimated time:** 10 minutes

**Files:**
- Create: `web/src/pages/[lang]/blog/category/[slug].astro`
- Test: `web/src/pages/blog-category.test.ts`

**Steps:**
1. Write failing test for renders category name as H1, its posts, unknown category → 404, empty category → empty state (still 200, `noindex`). Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement reusing `PostCard` + `Pagination`
4. Run tests green; seo-gate on 2 real categories
5. Commit: "feat(PF-2): blog category page"

**Verification:**
- [ ] Empty category page carries `noindex` and is excluded from the gate's indexable list
- [ ] Unknown category → 404

### Phase 2D: Projects list

**Deps:** 1L
**Complexity:** medium — list with filters.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/projects/index.astro`
- Create: `web/src/components/ProjectCard.astro`
- Test: `web/src/pages/projects-index.test.ts`

**Steps:**
1. Write failing test for renders projects from mocked `/projects?lang=`, category filter via query, empty result state, `CollectionPage` JSON-LD. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement
4. Run tests green; seo-gate `--routes projects-index`
5. Commit: "feat(PF-2): projects list on Astro"

**Verification:**
- [ ] SEO gate passes for `/id/projects` and `/en/projects`
- [ ] Filter with no match shows the empty state, not an error

### Phase 2E: Project detail

**Deps:** 2D
**Complexity:** medium — mirrors blog detail.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/projects/[slug].astro`
- Test: `web/src/pages/project-detail.test.ts`

**Steps:**
1. Write failing test for `CreativeWork` (creator `#person`) + breadcrumb, H1 = project title, related projects, unknown slug → 404. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement (reuse `sanitizeHtml`)
4. Run tests green; seo-gate on 3 real projects
5. Commit: "feat(PF-2): project detail on Astro"

**Verification:**
- [ ] SEO gate passes on 3 real projects
- [ ] `CreativeWork.creator` references `https://alisadikinma.com/#person`

### Phase 2F: Old-vs-new crawler parity diff

**Deps:** 2B, 2C, 2E
**Complexity:** medium — script comparing two outputs.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/scripts/parity-diff.mjs`
- Test: `web/scripts/parity-diff.test.mjs`

**Steps:**
1. Write failing test for `diffSeo(oldHtml, newHtml)` reports a dropped JSON-LD type, a changed canonical, a lost title keyword, a lost H1. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement; CLI fetches each URL from production (old Laravel prerender, read-only GET) and from local Astro, prints differences; allowed differences (canonical now language-prefixed, aggregateRating removed) are whitelisted in the script with a comment
4. Run on 10 blog posts, 3 categories, 5 projects; every non-whitelisted regression fixed before moving on
5. Commit: "test(PF-2): SEO parity diff old vs Astro"

**Verification:**
- [ ] Parity report has zero non-whitelisted regressions (saved in ledger)
- [ ] Whitelist entries each carry a reason comment

### Phase 3A: Hero (indexable portrait + point cloud island)

**Deps:** 1L
**Complexity:** complex — WebGL + GSAP + LCP + accessibility + performance budget.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/components/Hero.astro`
- Create: `web/src/scripts/hero-cloud.ts`
- Create: `web/src/scripts/portrait-sample.ts`
- Create: `web/public/images/ali-sadikin-ma-pakar-ai-indonesia.jpg`
- Test: `web/src/scripts/portrait-sample.test.ts`

**Steps:**
1. Write failing test for `samplePortrait(imageData, n)` on a synthetic 8×8 image (uniform border = background, 2×2 centre subject): returns only centre points, luminance contrast-stretched to 0..1; and `pointBudget({cores, memory, width})` → 160 mobile, 250 desktop, 110 when `cores<=4 || memory<=4`. Expected error: `Cannot find module './portrait-sample'`
2. Run, confirm failure
3. Port `mockups/hero-particles.js` to TS (import `ogl` from npm), split pure sampling into `portrait-sample.ts`; Hero markup per mockup: real `<img>` (`/images/ali-sadikin-ma-pakar-ai-indonesia.jpg`, alt "Ali Sadikin Ma, pakar AI Indonesia dan founder INDUSIA.ai", 1200×1200, `fetchpriority="high"`, it is the LCP), H1 `<span class="hx-h1-name">Ali Sadikin Ma</span> Pakar AI Indonesia`, third-person lead (`id="hero-lead"`, contains dofollow link "INDUSIA.ai" → indusia.ai), mobile `.hx-more` toggle (aria-expanded), CTAs, detection boxes (Ali · pakar AI; Juara 1 · Global AI Demo Day 2026; 56+ produk digital; 17 tahun membangun software), HUD (produk 56, tahun 17). Cloud starts after `load` (after LCP); `gsap.matchMedia()` with `prefers-reduced-motion: reduce` → final state, no ripple; no WebGL → duotone image stays; render only while hero intersects. Hero height `calc(100svh - var(--header-h))`, sizes from the ported CSS
4. Run tests green; Playwright measurement (one headed Chrome via CDP, see 5E) at 1920×1080, 1440×900, 1366×768, 1280×720, 1024×768, 768×1024, 390×844, 375×667, 360×640: CTA bottom ≤ HUD top, HUD bottom ≤ viewport, buttons inside viewport, face box bottom ≤ name top on widths < 760
5. Commit: "feat(PF-2): hero — indexable portrait + AI point cloud"

**Verification:**
- [ ] Hero fits one screen on all 9 viewports (numbers in ledger)
- [ ] Reduced motion shows final state; WebGL disabled shows the photo
- [ ] Hero `<img>` is the LCP element (Lighthouse trace)

### Phase 3B: Home static sections (proof, who, services) + copy

**Deps:** 3A, 1K
**Complexity:** medium — markup port + copy + backlinks.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/components/home/ProofBand.astro`
- Create: `web/src/components/home/WhoIAm.astro`
- Create: `web/src/components/home/Services.astro`
- Modify: `web/src/content/copy/id.ts`
- Modify: `web/src/content/copy/en.ts`
- Create: `docs/copy/PF-2-phase3.md`
- Test: `web/src/components/home/home-static.test.ts`

**Steps:**
1. Write failing test for ProofBand shows "#1 dari 26 startup AI asal 16 negara, Global AI Demo Day 2026", "56+" with label "produk digital di Satnusa: AI, IoT, aplikasi mobile", "17", "5 penghargaan dan fellowship startup" and never "16 negara dalam perjalanan karier" nor "56 produk AI"; WhoIAm has dofollow anchor "perusahaan AI Indonesia" → `https://indusia.ai/id/`; Services has 3 rows (Konsultasi AI → WhatsApp, Pelatihan & Workshop → `/id/pelatihan-ai`, Pembicara AI → `/id/pembicara-ai`) and the Konsultasi row links "jasa pembuatan AI" → `https://indusia.ai/id/ai-development-services/`. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement from mockup; WhatsApp number from `/api/settings/site` (or `about`) — if no field exists, STOP and ask Ali for the number (never hardcode a fake one)
4. Run tests green
5. Commit: "feat(PF-2): home proof, who-I-am, services sections"

**Verification:**
- [ ] Test fails if "16 negara" appears as a personal stat
- [ ] Both INDUSIA backlinks present with exact anchors (ID and EN)

### Phase 3C: Journey + stages bento + lightbox island

**Deps:** 3B
**Complexity:** complex — several API calls, interactive dialog, keyboard a11y.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/components/home/Journey.astro`
- Create: `web/src/components/home/Stages.astro`
- Create: `web/src/components/Lightbox.astro`
- Create: `web/src/scripts/lightbox.ts`
- Create: `web/public/images/career-marlin.jpg`
- Test: `web/src/components/home/journey.test.ts`

**Steps:**
1. Write failing test for Journey renders 4 chapters (Singapura 2008–2015 cover = gallery 14 `thumbnail`; Marlin 2016–2019 cover `career-marlin.jpg` + items of galleries 9,11,13,10,12 = 33 photos; Satnusa 2021–2025 cover = gallery 16 `thumbnail`; INDUSIA 2026–sekarang with product image + anchors "machine vision Indonesia" → `/id/ai-vision/`, "fleet management system" → `/id/ai-fleet-transportation/`, "chatbot WhatsApp AI" → `/id/ai-assistant/`), photo counts; Stages renders awards 13 (main), 10, 11 labelled "peserta", 7, 8 with gold locks, each card is an `<a href>` to its award page (3G) and also opens the lightbox. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement: galleries fetched in parallel with `Promise.allSettled`; a failed gallery hides its photo count but keeps the chapter text; lightbox = native `<dialog>`, arrows + Esc + backdrop close, focus returns to the trigger, counter "n / total"; covers use `object-position: 50% 15%` for 14 and 16
4. Run tests green
5. Commit: "feat(PF-2): career journey + stages bento + lightbox"

**Verification:**
- [ ] Marlin chapter counts 34 photos (cover + 33)
- [ ] One gallery API failure leaves the other chapters rendered (test)
- [ ] Lightbox keyboard: Esc closes, focus returns to the card

### Phase 3D: Testimonials + latest writing + newsletter island

**Deps:** 3B
**Complexity:** medium — data sections + one form island against an existing endpoint.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/components/home/Testimonials.astro`
- Create: `web/src/components/home/LatestWriting.astro`
- Create: `web/src/components/NewsletterForm.astro`
- Create: `web/src/scripts/newsletter-form.ts`
- Test: `web/src/scripts/newsletter-form.test.ts`

**Steps:**
1. Write failing test for `validateNewsletter({name,email,whatsapp})` (required name, valid email, WA `^\+[1-9]\d{6,14}$`) and `submitNewsletter` (button disabled + "Mengirim…", 422 → field errors shown, 429 → "Terlalu banyak percobaan, coba lagi nanti.", network error → retry message, success → on-screen confirmation, double click sends one request). Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement; Testimonials = 4 rows from `/api/testimonials` ordered by `sort_order`: photo (`/uploads/testimonials/…`, 56×56, alt = name), verbatim English excerpt (`lang="en"`, the first sentence(s) up to ~220 chars, cut at a sentence end), role from `job_title`/`company_name` (mockup labels), "Rekomendasi lengkap di LinkedIn" → `linkedin_url` (`rel="noopener"`); 0 testimonials → section hidden. LatestWriting from `/api/homepage/featured` `latest_articles`
4. Run tests green
5. Commit: "feat(PF-2): testimonials, latest writing, newsletter form"

**Verification:**
- [ ] Testimonials show 4 real people with photos from the live API
- [ ] Newsletter double-submit sends exactly one POST

### Phase 3E: Home page assembly + `services` section toggle

**Deps:** 3C, 3D
**Complexity:** medium — page + one seeder row.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/index.astro`
- Modify: `backend/database/seeders/PageSectionSeeder.php`
- Test: `backend/tests/Feature/PageSectionServicesSeederTest.php`
- Test: `web/src/pages/home.test.ts`

**Steps:**
1. Write failing test for PHP — running `PageSectionSeeder` twice yields exactly one `homepage`/`services` row, active. Expected error: `Failed asserting that 0 matches expected 1`
2. Run, confirm failure
3. Add the row (kebab-case, idempotent `firstOrCreate`); home page order: Hero → Who I Am → Proof → Journey → Services → Stages → Testimonials → Latest Writing → Newsletter; each section honours `/api/page-sections` `is_active` (absent row = on); section ids `tentang`, `perjalanan`, `layanan`, `testimoni`, `tulisan` match the nav
4. Home test (container): Person + WebSite JSON-LD, H1 contains name + keyword; run seo-gate `--routes home` for `/id/` and `/en/`
5. Commit: "feat(PF-2): home page on Astro + services section toggle"

**Verification:**
- [ ] SEO gate passes for `/id/` and `/en/` including hero + who + services + journey + footer backlinks
- [ ] Turning a section off in `/admin/page-sections` hides it on the next request after cache TTL

### Phase 3F: About = entity home (ProfilePage)

**Deps:** 3E
**Complexity:** medium — one page, important for GEO.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/about.astro`
- Test: `web/src/pages/about.test.ts`

**Steps:**
1. Write failing test for `ProfilePage` with `mainEntity` `#person`, dated career timeline (Singapore 2008–2015, Marlin 2016–2019, Satnusa 2021-01–2025-12, INDUSIA 2026-01-01–), awards list, every `sameAs` profile linked, INDUSIA paragraph with "INDUSIA.ai, perusahaan AI Indonesia" + the 3 product anchors, visible "Diperbarui <date>" from settings `updated_at`. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement; full bio text from `/api/settings/about` `bio` sanitized by `sanitizeHtml`
4. Run tests green; seo-gate `--routes about`
5. Commit: "feat(PF-2): about page as entity home"

**Verification:**
- [ ] SEO gate passes for `/id/about` and `/en/about`
- [ ] Timeline shows the 2019–2021 gap without filler text

### Phase 3G: Award pages + award slug

**Deps:** 3E
**Complexity:** complex — migration with backfill + new public endpoint + Event schema.
**Estimated time:** 15 minutes

**Files:**
- Create: `backend/database/migrations/2026_11_08_000001_add_slug_to_awards_table.php`
- Modify: `backend/app/Models/Award.php`
- Modify: `backend/app/Http/Controllers/Api/AwardController.php`
- Modify: `backend/routes/api.php`
- Create: `web/src/pages/[lang]/[awardsSeg]/[slug].astro`
- Test: `backend/tests/Feature/AwardSlugTest.php`
- Test: `web/src/pages/award-detail.test.ts`

**Steps:**
1. Write failing test for PHP — migration backfills a unique slug for every existing award from `title` (e.g. award 13 → `1st-place-winner-global-ai-demo-day-2026-outskill-ai-generalist-fellowship`, duplicates get `-2`), new awards get one on create, `GET /api/awards/slug/{slug}` returns the award + galleries, unknown → 404. Expected error: `404` / `Column not found: slug`
2. Run, confirm failure
3. Implement (Spatie `HasSlug` like `Project`, `doNotGenerateSlugsOnUpdate`); nullable column → backfill → unique index; Astro route serves `/id/penghargaan/{slug}` and `/en/awards/{slug}` (`awardsSeg` must equal `penghargaan` for id, `awards` for en, else 404) with `Event` JSON-LD (name, startDate, location, organizer), Person `award`, photos, honest result line (Startup Grind: "peserta")
4. Run both test suites green; seo-gate on 6 award pages
5. Commit: "feat(PF-2): per-award pages with Event schema"

**Verification:**
- [ ] Every existing award has a non-null unique slug after migrate (count query in ledger)
- [ ] SEO gate passes on all 6 award pages, ID + EN

### Phase 3H: Work page (tabs) + gallery

**Deps:** 3G
**Complexity:** medium — page with tab state in query.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/work.astro`
- Test: `web/src/pages/work.test.ts`

**Steps:**
1. Write failing test for `?tab=awards|gallery|projects` selects the tab server-side (no JS needed to read content), invalid tab → default `projects`, award cards link to award pages. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement; tabs are links (`?tab=`), canonical is `/{lang}/work` without the query
4. Run tests green; seo-gate `--routes work`
5. Commit: "feat(PF-2): work page with awards/gallery tabs"

**Verification:**
- [ ] Each tab's content is in the HTML without JS
- [ ] Canonical has no query string

### Phase 3I: Contact page

**Deps:** 3E
**Complexity:** medium — form island against an existing endpoint.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/contact.astro`
- Create: `web/src/scripts/contact-form.ts`
- Test: `web/src/scripts/contact-form.test.ts`

**Steps:**
1. Write failing test for contact form: blur validation, error summary focused after failed submit, disabled "Mengirim…", 422 field errors, 429 throttle message (endpoint allows 3 per 15 min), success state, single request on double click. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement against `POST /api/contact` (fields as `ContactController::store` validates — read it, do not guess)
4. Run tests green; seo-gate `--routes contact`
5. Commit: "feat(PF-2): contact page on Astro"

**Verification:**
- [ ] Security: form posts only to `/api/contact`; no secrets in client bundle (grep `web/dist/client` for `INTERNAL_API_TOKEN`)
- [ ] 429 shows the throttle message

### Phase 4A: Waitlist table + model

**Deps:** 0C
**Complexity:** medium — new table, pattern exists (newsletters).
**Estimated time:** 10 minutes

**Files:**
- Create: `backend/database/migrations/2026_11_08_000002_create_training_waitlist_table.php`
- Create: `backend/app/Models/TrainingWaitlist.php`
- Create: `backend/database/factories/TrainingWaitlistFactory.php`
- Test: `backend/tests/Feature/TrainingWaitlistModelTest.php`

**Steps:**
1. Write failing test for create a row with `client_key` twice → unique violation; enums `interest` (`inhouse|workshop|public_class`) and `status` (`new|contacted|cancelled`, default `new`). Expected error: `Base table or view not found: training_waitlist`
2. Run, confirm failure
3. Columns: `id`, `name` string(120), `company` string(160) nullable, `email` string(190) indexed, `whatsapp_number` string(20) nullable, `interest` enum, `note` text nullable, `source_page` string(120), `locale` enum(`id`,`en`), `newsletter_opt_in` bool default false, `status` enum default `new` indexed, `admin_note` text nullable, `client_key` uuid unique, timestamps
4. Run tests green
5. Commit: "feat(PF-2): training_waitlist table + model"

**Verification:**
- [ ] Duplicate `client_key` rejected at DB level
- [ ] `status` defaults to `new`

### Phase 4B: Public waitlist endpoint

**Deps:** 4A
**Complexity:** complex — public write endpoint (spam, idempotency, PII).
**Estimated time:** 15 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/TrainingWaitlistController.php`
- Create: `backend/app/Http/Requests/StoreTrainingWaitlistRequest.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/TrainingWaitlistSubmitTest.php`

**Steps:**
1. Write failing test for `POST /api/training-waitlist` valid → 201 `{success:true,data:{id}}`; same `client_key` again → 200 with the SAME id, no second row. Expected error: `404 Not Found`
2. Run, confirm failure
3. Implement + tests for: missing name/email → 422; bad email → 422; WA present but not E.164 → 422; `newsletter_opt_in=true` requires `whatsapp_number` (`required_if`) because newsletters require WA; honeypot `website` filled → 201 fake success, nothing stored (log info); throttle `5,60` → 429 on the 6th; opt-in → `Newsletter::firstOrCreate` by email with `source='training_waitlist'` and a generated `unsubscribe_token`; existing newsletter email → no duplicate; name/note trimmed and length-capped
4. Run tests green
5. Commit: "feat(PF-2): public training waitlist endpoint"

**Verification:**
- [ ] Security: honeypot, throttle and idempotency each covered by a passing test
- [ ] Opt-in without WA → 422, with WA → exactly one newsletters row

### Phase 4C: Admin waitlist endpoints

**Deps:** 4B
**Complexity:** medium — CRUD-ish, pattern `NewsletterAdminController`.
**Estimated time:** 15 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/TrainingWaitlistAdminController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/TrainingWaitlistAdminTest.php`

**Steps:**
1. Write failing test for unauthenticated `GET /api/admin/training-waitlist` → 401; authenticated → paginated list filtered by `interest`, `locale`, `status`, `search` (name/email/company). Expected error: `404 Not Found`
2. Run, confirm failure
3. Implement `index`, `update` (PATCH `status` + `admin_note` only, other fields ignored), `export` (streamed CSV: name, company, email, whatsapp, interest, locale, status, created_at in WIB `Asia/Jakarta`); all under `auth:sanctum`
4. Run tests green
5. Commit: "feat(PF-2): admin endpoints for training waitlist"

**Verification:**
- [ ] Security: every admin route returns 401 without a token
- [ ] CSV timestamps are WIB, header row present, filters applied

### Phase 4D: Vue admin "Waitlist Pelatihan"

**Deps:** 4C
**Complexity:** medium — view + composable + route + sidebar, existing pattern.
**Estimated time:** 15 minutes

**Files:**
- Create: `frontend/src/views/admin/TrainingWaitlist.vue`
- Create: `frontend/src/composables/useTrainingWaitlistAdmin.js`
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/layouts/AdminLayout.vue`
- Test: `frontend/src/composables/useTrainingWaitlistAdmin.test.js`

**Steps:**
1. Write failing test for vitest, mocked axios — the composable: list query key includes filters, `updateStatus` invalidates the list. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement modelled on `NewsletterSubscribers.vue` + `useNewsletterAdmin.js` (30 s staleTime, refetchOnMount 'always'): table, filters, status select + note save, CSV export button; route `/admin/training-waitlist` (auth); sidebar entry "Waitlist Pelatihan" next to "Newsletter"
4. Run tests green; `cd frontend && npm run build` exit 0
5. Commit: "feat(PF-2): admin Waitlist Pelatihan module"

**Verification:**
- [ ] Admin view lists real rows created via 4B in the local stack
- [ ] Status change persists after reload

### Phase 4E: Training page (/id/pelatihan-ai, /en/ai-training)

**Deps:** 4B, 3E
**Complexity:** medium — page + form island.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/[trainingSeg].astro`
- Create: `web/src/scripts/waitlist-form.ts`
- Create: `docs/copy/PF-2-phase4.md`
- Test: `web/src/scripts/waitlist-form.test.ts`

**Steps:**
1. Write failing test for the waitlist form: generates one `crypto.randomUUID()` `client_key` per page load and reuses it on retry; blur validation; error summary focus; disabled "Mengirim…"; 422 → field errors; 429 → throttle message; success → "Anda sudah masuk waitlist" state; newsletter checkbox makes WA required client-side. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement page per `mockups/pelatihan-ai.html` (status "Segera dibuka", topics, formats, form, FAQ 4–6 Q&A, `Service` + `FAQPage` JSON-LD, dofollow "jasa pembuatan AI INDUSIA" → ai-development-services); `trainingSeg` must be `pelatihan-ai` (id) or `ai-training` (en); EN copy in `docs/copy/PF-2-phase4.md` for Ali's approval
4. Run tests green; seo-gate `--routes training`
5. Commit: "feat(PF-2): training page with waitlist form"

**Verification:**
- [ ] SEO gate passes for `/id/pelatihan-ai` and `/en/ai-training`
- [ ] A real submit in the local stack appears in the admin module (4D)

### Phase 4F: Speaker page (/id/pembicara-ai, /en/ai-speaker)

**Deps:** 3G, 3E
**Complexity:** medium — page + sessions list + press kit files.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/src/pages/[lang]/[speakerSeg].astro`
- Create: `web/src/content/speaking.ts`
- Create: `web/public/press/ali-sadikin-ma-bio.txt`
- Test: `web/src/pages/speaker.test.ts`

**Steps:**
1. Write failing test for "Pernah tampil di" lists only events where Ali presented (Global AI Demo Day 2026, UN-UNCTAD × Alibaba eFounders 2019, Telkomsel NextDev, Fenox — NOT Google Startup Grind), each linking its award page; press kit section offers bio (short + long) and portrait download; WhatsApp CTA; `Service` + `FAQPage` JSON-LD; bio line has dofollow "founder INDUSIA.ai" → indusia.ai. Expected error: `Cannot find module`
2. Run, confirm failure
3. Implement per `mockups/pembicara-ai.html`; `speaking.ts` holds sessions (event, city, date, topic) — ONLY facts Ali confirmed; unknown fields left out, not invented; video embed only if Ali gives a URL (ask; until then the section is omitted)
4. Run tests green; seo-gate `--routes speaker`
5. Commit: "feat(PF-2): speaker page with sessions + press kit"

**Verification:**
- [ ] Test fails if "Startup Grind" appears in the presented-at list
- [ ] SEO gate passes for both languages

### Phase 4G: Sitemaps, robots, llms.txt for new pages

**Deps:** 4E, 4F, 3G, 3F
**Complexity:** medium — extend two existing controllers.
**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/SitemapController.php`
- Modify: `backend/app/Http/Controllers/Api/GeoController.php`
- Modify: `frontend/public/llms.txt`
- Test: `backend/tests/Feature/SitemapNewPagesTest.php`

**Steps:**
1. Write failing test for `/api/sitemap.xml` (or the pages sitemap) lists `/id/pelatihan-ai`, `/en/ai-training`, `/id/pembicara-ai`, `/en/ai-speaker`, `/{lang}/about`, `/{lang}/faq`, every award page, all language-prefixed with `xhtml:link` hreflang alternates, no unprefixed URLs. Expected error: `Failed asserting that ... contains "/id/pelatihan-ai"`
2. Run, confirm failure
3. Implement; `llms.txt` (static + `/api/llms.txt`): rewrite intro to "Ali Sadikin Ma — pakar AI Indonesia / AI consultant, founder INDUSIA.ai (https://indusia.ai)", list training, speaker, about, awards, FAQ; drop "AI Generalist" title, the "16 countries" personal claim, and any "56 AI products" wording (correct: 56+ digital products — AI, IoT, mobile — built at Satnusa)
4. Run tests green
5. Commit: "feat(PF-2): sitemaps + llms.txt include new pages"

**Verification:**
- [ ] Sitemap test green; no unprefixed public URL in any sitemap
- [ ] `llms.txt` contains no "16 countries" personal claim

### Phase 5A: Redirect completeness

**Deps:** 4G, 3H, 3I, 2F
**Complexity:** medium — table-driven test over every old URL.
**Estimated time:** 15 minutes

**Files:**
- Create: `web/scripts/redirect-map.test.mjs`
- Modify: `web/src/middleware.ts`

**Steps:**
1. Write failing test for listing every public path from `frontend/src/router/index.js` + `backend/routes/web.php` (`/`, `/about`, `/projects`, `/projects/x`, `/work`, `/blog`, `/blog/x`, `/blog/category/x`, `/contact`, `/faq`, `/{lang}/awards`, `/{lang}/gallery`, `/{lang}/awards-debug`, `/r/abc12`, `/login`, `/newsletter/unsubscribe`, `/admin`) with its expected status + Location against the running Astro server. Expected error: assertion failures for not-yet-mapped paths
2. Run, confirm failure
3. Fix mappings; `/r/*`, `/login`, `/newsletter/unsubscribe`, `/admin*` are NOT handled by Astro (nginx routes them; test documents that)
4. Run green
5. Commit: "test(PF-2): redirect map covers every old public URL"

**Verification:**
- [ ] Every old public URL returns 200 or a single 301 hop (no chains)
- [ ] No 302 except `/` → language

### Phase 5B: Remove public Vue code

**Deps:** 5A
**Complexity:** complex — wide deletion; admin must keep building.
**Estimated time:** 15 minutes

**Files:**
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/package.json`
- Modify: `frontend/index.html`
- Create: `frontend/src/router/router-public.test.js`

**Steps:**
1. Write failing test for router contains no public `/:lang…` routes; only `/login`, `/newsletter/unsubscribe`, `/admin…` and the catch-all. Expected error: assertion lists the public routes still present
2. Run, confirm failure
3. Delete public views/components (`Home.vue`, `About.vue`, `Work.vue`, `Projects.vue`, `ProjectDetail.vue`, `Awards*.vue`, `Blog*.vue`, `Gallery.vue`, `Contact.vue`, `FaqView.vue`, `components/home/*`, hero/cinematic components) only after `grep` shows no admin import of each; remove Three.js/TresJS deps, hero video assets (`public/videos/hero-*`), Font Awesome CDN link in `index.html`, static JSON-LD in `index.html`
4. `npm run build` exit 0; run frontend tests
5. Commit: "chore(PF-2): remove public Vue SPA code (admin stays)"

**Verification:**
- [ ] `frontend` build green and `/admin` loads locally after the deletion
- [ ] `grep -r "three\|@tresjs" frontend/package.json` returns nothing

### Phase 5C: Remove Laravel prerender layer

**Deps:** 5B
**Complexity:** complex — deletes SEO code; must not drop `/r/{code}` or admin.
**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/routes/web.php`
- Modify: `scripts/nginx/portfolio-8080.conf`
- Test: `backend/tests/Feature/WebRoutesAfterCutoverTest.php`

**Steps:**
1. Write failing test for `/r/{code}` still redirects; `SpaPrerenderController` routes (`/`, `/blog…`, `/projects/{slug}`, `/faq`) are gone from `route:list`. Expected error: assertion that prerender routes still exist
2. Run, confirm failure
3. Delete `SpaPrerenderController`, `app/Services/Seo/SeoHtmlComposer.php`, `SchemaGraphBuilder.php`, `resources/views/seo/*`, their tests, the web.php routes and the `localhost:5173` redirect closures; keep `LogAiCrawler` on `web` (still logs `/r/` hits); remove prerender location blocks from the nginx template
4. Run full PHP suite — no new failures vs 0B
5. Commit: "chore(PF-2): remove Laravel SPA prerender layer"

**Verification:**
- [ ] `/r/{code}` test green; no `SpaPrerenderController` reference left (`grep`)
- [ ] PHP failures ≤ 0B baseline

### Phase 5D: Ops — systemd, nginx, deploy.sh, runbook

**Deps:** 5C
**Complexity:** complex — production routing and process management.
**Estimated time:** 15 minutes

**Files:**
- Create: `scripts/systemd/portfolio-web.service`
- Modify: `scripts/nginx/portfolio-8080.conf`
- Modify: `scripts/deploy.sh`
- Create: `docs/runbooks/pf2-astro-cutover.md`
- Test: `scripts/tests/deploy-web-step.test.sh`

**Steps:**
1. Write failing test for bash — running deploy.sh's new `deploy_web` function with `DEPLOY_DRY_RUN=1` prints `npm ci`, `npm run build`, `systemctl restart portfolio-web`, and a health check `curl -fsS http://127.0.0.1:4321/id/faq`; with `DEPLOY_SKIP_WEB=1` it prints nothing. Expected error: `deploy_web: command not found`
2. Run, confirm failure
3. Implement: unit `portfolio-web.service` (User claudesn? — use the same user as the queue unit, `WorkingDirectory=/var/www/Portfolio_v2/web`, `ExecStart=/usr/bin/node dist/server/entry.mjs`, `Environment=HOST=127.0.0.1 PORT=4321 NODE_ENV=production`, `EnvironmentFile=/var/www/Portfolio_v2/web/.env`, `Restart=always`, `MemoryMax=512M`); nginx: `location ^~ /_astro/ { proxy_pass http://127.0.0.1:4321; expires 1y; }`, `/api/`, `/storage/`, `/uploads/`, `/r/`, `/login`, `/newsletter/unsubscribe`, `/admin` stay as today (SPA for admin), everything else `location / { proxy_pass http://127.0.0.1:4321; proxy_set_header Host $host; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }`; deploy.sh `deploy_web` after the frontend build; runbook: one-time install (node ≥ 22, `.env` with `API_INTERNAL_URL=http://127.0.0.1:8080/api`, `INTERNAL_API_TOKEN` = same value in Laravel `.env`, `GA4_MEASUREMENT_ID`), cutover order, rollback (restore previous nginx conf + SPA build, `systemctl stop portfolio-web`)
4. Run test green; `nginx -t` on the template inside an `nginx:1.27-alpine` container
5. Commit: "ops(PF-2): Astro service, nginx routing, deploy step, cutover runbook"

**Verification:**
- [ ] `nginx -t` passes on the template; `^~ /_astro/` sits above the regex static block
- [ ] Runbook has a rollback that restores the old site in one reload

### Phase 5E: Full SEO gate, Lighthouse, e2e, docs

**Deps:** 5D
**Complexity:** complex — the release gate for the whole ticket.
**Estimated time:** 30 minutes

**Files:**
- Create: `web/e2e/public.spec.ts`
- Create: `web/playwright.config.ts`
- Modify: `CLAUDE.md`
- Modify: `frontend/CLAUDE.md`

**Steps:**
1. Write failing test for e2e (Playwright, ONE headed Chrome window started once with `--remote-debugging-port`, specs attach via `connectOverCDP` and reuse one context/page, `workers: 1`, `retries: 0`, never close/reopen the window, no `show-report`) covering: home hero fits viewport + scrollspy highlight, lightbox open/close, waitlist submit → success state, newsletter submit, contact submit, language switch keeps the page. Expected error: `Cannot find module '../playwright.config'`
2. Run, confirm failure
3. Implement; run `seo-gate --routes all` (every indexable route ID + EN) → exit 0; Lighthouse on home, training, speaker, one blog post: SEO = 100, Accessibility ≥ 95, LCP element = hero img on home
4. Update root `CLAUDE.md` (architecture: Astro `web/`, nginx routing, `portfolio-web.service`, env vars `API_INTERNAL_URL` / `INTERNAL_API_TOKEN` / `GA4_MEASUREMENT_ID` / `SITE_URL`, new endpoints `/api/training-waitlist`, `/api/admin/training-waitlist*`, `/api/internal/crawler-hit`, `/api/awards/slug/{slug}`, Page Sections Mapping now read by Astro, removed prerender layer, PHP 8.4, "Last Updated"); run `graphify update .`
5. Commit: "test(PF-2): e2e + full SEO gate; docs updated"

**Verification:**
- [ ] `seo-gate --routes all` exit 0 (output saved in ledger)
- [ ] Lighthouse SEO 100 / A11y ≥ 95 on the 4 pages (scores in ledger)
- [ ] e2e green in one headed window

### Phase 6A: Release (only with Ali's OK)

**Deps:** 5E
**Complexity:** complex — production cutover.
**Estimated time:** 30 minutes

**Files:**
- Create: `scripts/smoke-prod.sh`
- Modify: `docs/runbooks/pf2-astro-cutover.md`

**Steps:**
1. Write failing test for `scripts/smoke-prod.sh https://alisadikinma.com` asserts `/id/` response contains `hx-h1-name` and the `#person` JSON-LD, `/id/pelatihan-ai` is 200, `/admin` serves the SPA, `/api/health` 200, `/r/<known code>` 301. Expected error: `smoke-prod: /id/ missing hx-h1-name` (run before cutover = proves the script detects the old site)
2. STOP and ask Ali for explicit OK to merge `feat/PF-2-portfolio-redesign-seo` into `main` and push (push = production deploy). Record in ledger
3. After OK, in this order: (a) on the VPS run `docs/runbooks/pf2-php84-upgrade.md` (php8.4-fpm + CLI 8.4.27, nginx socket → 8.4, verify `/api/health` + admin login on the OLD site first); (b) `git merge --no-ff`, push (deploys composer update + Astro build), watch the GitHub Actions run; (c) follow `docs/runbooks/pf2-astro-cutover.md` (install `portfolio-web.service`, `.env`, nginx swap, `nginx -t`, reload). Rollback for (a) and (c) are independent
4. Run `smoke-prod.sh` → exit 0; run `seo-gate --base https://alisadikinma.com --routes all`; submit sitemap in Google Search Console + Bing Webmaster (Ali); on any failure execute the runbook rollback immediately
5. Commit: "docs(PF-2): release notes + smoke results"

**Verification:**
- [ ] Security: release done only after Ali's recorded OK; rollback tested in the runbook
- [ ] `smoke-prod.sh` exit 0 and production SEO gate exit 0 after cutover

## Execution Waves

```
wave 1 lane 1: 0A
wave 2 lane 1: 0B
wave 3 lane 1: 0D
wave 4 lane 1: 0C
wave 5 lane 1: 1A
wave 5 lane 2: 1I
wave 5 lane 3: 4A
wave 6 lane 1: 1B
wave 6 lane 2: 1C
wave 6 lane 3: 1D
wave 6 lane 4: 1E
wave 6 lane 5: 1F
wave 6 lane 6: 1K
wave 6 lane 7: 4B
wave 7 lane 1: 1G
wave 7 lane 2: 1J
wave 7 lane 3: 4C
wave 8 lane 1: 1H
wave 8 lane 2: 4D
wave 9 lane 1: 1L
wave 10 lane 1: 2A
wave 10 lane 2: 2D
wave 10 lane 3: 3A
wave 11 lane 1: 2B
wave 11 lane 2: 2C
wave 11 lane 3: 2E
wave 11 lane 4: 3B
wave 12 lane 1: 2F
wave 12 lane 2: 3C
wave 12 lane 3: 3D
wave 13 lane 1: 3E
wave 14 lane 1: 3F
wave 14 lane 2: 3G
wave 14 lane 3: 3I
wave 14 lane 4: 4E
wave 15 lane 1: 3H
wave 15 lane 2: 4F
wave 16 lane 1: 4G
wave 17 lane 1: 5A
wave 18 lane 1: 5B
wave 19 lane 1: 5C
wave 20 lane 1: 5D
wave 21 lane 1: 5E
wave 22 lane 1: 6A
```

## Out of scope

- Wikidata item "Ali Sadikin Ma" and identical bio on LinkedIn/YouTube/Instagram/TikTok (Ali, off-site).
- Writing the 3–5 "pelatihan AI" cluster articles (Content Engine runs, after release).
- Removing `php8.2-fpm` from the VPS (after 7 days green, noted in Utang terbuka).
- Speaker video embed until Ali supplies a URL.
