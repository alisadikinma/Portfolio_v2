# Homepage + Blog SEO & GEO Friendliness

**Date:** 2026-06-08
**Author:** Ali Sadikin (with Claude)
**Status:** Design (brainstorm complete — ready for `gaspol-plan`)
**Scope decision:** Full audit + fix every gap; render-layer work focused on **homepage + all blog surfaces** (list / detail / category). Projects keep their existing OG-only injection.

---

## Design

### Problem

The site must be both **SEO-friendly** (Google can index real page content + structured data) and **GEO-friendly** (LLM crawlers — ChatGPT, Perplexity, Claude, Google AI — can extract and cite real content). The root blocker: the frontend is a **Vue SPA with no SSR/prerender**. Per-page `<title>`, meta, OG, JSON-LD and the article body are all injected by JavaScript in `onMounted` (see [useMetaTags.js](../../frontend/src/composables/useMetaTags.js), [BlogDetail.vue:504](../../frontend/src/views/BlogDetail.vue#L504)). Any crawler that does not execute JS sees only the generic `index.html` shell with an empty `<div id="app">`.

### Audit — current state (verified 2026-06-08)

There is already a **partial** server-side renderer in [backend/routes/web.php:81](../../backend/routes/web.php#L81) (`$injectOg`) for `/{lang?}/blog/{slug}` and `/{lang?}/projects/{slug}`, and the nginx vhost **does** route those paths to PHP-FPM ([scripts/nginx/portfolio-8080.conf:98](../../scripts/nginx/portfolio-8080.conf#L98)). So link-preview crawlers receive correct OG tags today. Gaps:

| Surface | Meta/OG | JSON-LD | Crawlable body | hreflang | Routed to PHP? |
|---|---|---|---|---|---|
| Blog detail | ✅ server (regex, ~12 tags) | ❌ JS-only | ❌ empty `#app` shell | ❌ | ✅ |
| Project detail | ✅ server | ❌ JS-only | ❌ | ❌ | ✅ |
| Homepage | static `index.html` only | ✅ static Person+FAQ baked in | ⚠️ schema only, no body text | ❌ | ❌ (static) |
| Blog list / category | ❌ generic | ❌ | ❌ | ❌ | ❌ |
| About / Awards / Gallery / Contact | ❌ generic | ❌ | ❌ | ❌ | ❌ |

Additional gaps in the existing `$injectOg`: rewrites ~12 tags via regex only; no JSON-LD, no `robots`, no `hreflang`, no `keywords`; ignores stored `faq_schema` and `ai_summary` fields; no crawlable body; `max-age=300` with no purge-on-edit; homepage + list pages never reach it.

Net effect: **blog/project detail = correct metadata but blank body** (Google indexes title, not article; LLMs cannot extract/cite). **List + secondary pages = generic site-wide meta only.**

### Decisions (locked in brainstorm)

1. **Render approach:** Laravel head + content injection into the built `index.html`. No headless Chrome, no SSG, no Nuxt migration. Rides existing CI/CD. (Google prefers true SSR/static over UA-sniffing dynamic rendering; this serves the same enriched HTML to everyone.)
2. **Coverage:** homepage + blog list + blog detail + blog category. Projects keep current OG-only injection (can adopt the shared composer later for free).
3. **Not UI work** — backend + infra + content-structure only; design-intelligence phase skipped.

### Architecture — unified SSR-enrichment layer

Promote the inline `web.php` closures into a real controller + service:

- **`SpaPrerenderController`** — thin route handlers for homepage, blog list, blog detail, blog category (en/id + bare paths). Resolves model(s) + lang, delegates to the composer, returns cached HTML.
- **`SeoHtmlComposer` service** — loads `frontend/dist/index.html` once, then composes per route + lang:
  1. **Full head:** `<title>`, description, keywords, canonical, `robots`, **hreflang alternates (en / id / x-default)**, complete OG set (`og:type/url/title/description/image/locale/site_name`), full Twitter card.
  2. **JSON-LD graph (injected before `</head>`):**
     - Blog detail → `BlogPosting`/`Article` (headline, author `Person`, `datePublished`, `dateModified`, `image`, `publisher`, `mainEntityOfPage`), `BreadcrumbList`, `FAQPage` (from `post_translations.faq_schema` when present).
     - Blog list → `WebSite` + `ItemList` of recent posts + `BreadcrumbList`.
     - Blog category → `CollectionPage` + `ItemList` + `BreadcrumbList`.
     - Homepage → `Person` + `Organization` + `WebSite` + `FAQPage` (port/reuse `personSchema.js` logic, or reuse `GeoController` output) so it stays consistent with the static block already in `index.html`.
  3. **Crawlable content body:**
     - Blog detail → render real `<article>` with `<h1>`, author, `<time>` published/modified, `<figure>` + alt, and full `post_translations.content` HTML **into the `#app` container as pre-mount markup**. Vue overwrites it on `app.mount('#app')`, so real users are unaffected; non-JS crawlers + LLMs read real content.
     - Homepage + list + category → enriched head + JSON-LD + a concise `<noscript>` summary (identity blurb / post excerpts + `ai_summary`). Lower effort; homepage identity content is largely static already.

- **Reuse, don't reinvent:** pull meta/schema from the existing `HasSeoFields` trait (`getSeoMetaAttribute`, `getOgMetaAttribute`, `getSchemaMarkupAttribute`) and `PostResource`-equivalent fields. Translation fallback chain mirrors the current `$serveBlogOg` (requested lang → en → first → post primary fields).

### GEO layer (LLM extractability + citation)

- Verify `/api/llms.txt` + `/api/llms-full.txt` ([GeoController](../../backend/app/Http/Controllers/Api/GeoController.php)) enumerate **all published posts** with titles, summaries (`ai_summary`), and `dateModified`.
- Ensure sitemap `lastmod` is accurate ([SitemapController](../../backend/app/Http/Controllers/Api/SitemapController.php)) so AI crawlers see freshness.
- Surface `ai_summary` as an answer-first sentence in meta description + the crawlable body for blog detail.
- Confirm `robots.txt` AI-crawler allow-list (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, OAI-SearchBot, etc.) is present on the production-served file.

### Caching & infra

- Composed HTML cached server-side keyed by `route + lang`; `Cache-Control: public, max-age=...` + Cloudflare edge cache.
- **Cache purge** on `Post` create/update/delete (model `saved`/`deleted` hook) + on settings change affecting homepage.
- **nginx:** widen the location regex in [portfolio-8080.conf](../../scripts/nginx/portfolio-8080.conf) to also route `/`, `/{lang}/blog`, `/{lang}/blog/category/{slug}` (and bare equivalents) to PHP-FPM. Live config is SSH-edited — flag as an operator deploy step.

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Article meta / schema / body | `Post` + `PostTranslation` + `HasSeoFields` | ✅ | content is HTML in `post_translations.content` |
| FAQ schema | `post_translations.faq_schema` | ✅ | render as `FAQPage` JSON-LD |
| ai_summary (GEO) | `post_translations.ai_summary` | ✅ | answer-first meta + body |
| Homepage Person/FAQ schema | `personSchema.js` / `GeoController` | ✅ | keep consistent with static `index.html` block |
| Built SPA shell | `frontend/dist/index.html` | ✅ | composer reads + splices |
| Blog list / category data | `Post::published()` queries | ✅ | for `ItemList` + body summary |

### Risks / feasibility

- **nginx live-config drift** (#1 risk): the repo config is a template; the production vhost is SSH-edited. Widening the regex must be applied on the VPS. → explicit operator runbook step + verify after deploy.
- **Hydration flash** on blog detail (pre-mount body briefly visible before Vue mounts): acceptable, SEO-superior to `<noscript>`; mitigated by fast hydration + matching markup. Validate no layout jump.
- **PHP load** on homepage/list: mitigated by HTML cache + Cloudflare; purge-on-edit keeps it fresh.
- **`dist/index.html` absence in dev** → composer returns the SPA shell unmodified (existing null-guard pattern).

### Out of scope

- Project detail/list SSR upgrade (keep current OG-only injection).
- Migration to Nuxt/SSR framework.
- Headless-Chrome prerender service.

### Acceptance criteria

- `curl -A "Googlebot" https://alisadikinma.com/en/blog/{slug}` returns full article body text + `BlogPosting` JSON-LD + correct title/canonical/hreflang in raw HTML (no JS).
- Homepage raw HTML contains Person + FAQ + WebSite JSON-LD and an identity summary without JS.
- Blog list + category raw HTML contain `ItemList` JSON-LD + post titles/excerpts.
- `/api/llms.txt` lists all published posts with summaries + dates.
- Rich Results Test + Schema validator pass for blog detail, homepage, blog list.
- Editing a post purges its cached HTML within one request.

---

## Execution Progress (durable resume marker)

**Mode:** autonomous A→F, then update CLAUDE.md + README, `graphify update .`, commit + push. No PHP/vendor on dev Mac → PHP tests run on CI. SSR routes only fire after nginx widening on VPS (Phase F operator step).

- [x] **Phase A** — `SeoHtmlComposer` + `SeoHtmlComposerTest` (6 cases). DONE.
- [x] **Phase B** — `SchemaGraphBuilder` + `SchemaGraphBuilderTest` (11 cases). DONE.
- [x] **Phase C** — `SpaPrerenderController` (home/index/detail/category) + Blade `seo/article` + `seo/summary` + rewired `routes/web.php` (kept project closures; category registered before `{slug}`) + `SpaPrerenderTest` (8 cases). DONE.
- [x] **Phase D** — `Cache::remember` 1h per route/lang/slug + `config/seo.php` knobs + `Post::saved/deleted` → `purgeForPost()` + cache-purge assertions folded into `SpaPrerenderTest`. DONE.
- [x] **Phase E** — GeoController `llms.txt`/`llms-full.txt` freshness line + per-post date + `ai_summary` (`postSummary()`/`latestContentTimestamp()`); Sitemap already emitted per-post `lastmod` from `updated_at` (verified, no change) + `GeoFreshnessTest` (3 cases). DONE.
- [x] **Phase F** — nginx snapshot widened (`location = /` homepage + combined blog index/category/detail + project-detail regex) + `docs/runbooks/seo-geo-ssr-deploy.md`. DONE (live nginx edit is the operator step in the runbook).
- [ ] Docs: CLAUDE.md (root) SSR section + Last-Updated; README.md; `graphify update .`
- [ ] Commit + push (push auto-deploys; CI deploy may need manual VPS ssh per memory).

**Verification note:** no PHP/vendor on dev Mac — all PHPUnit suites (`SeoHtmlComposerTest`, `SchemaGraphBuilderTest`, `SpaPrerenderTest`, `GeoFreshnessTest`) authored full-fidelity and assertion-verified by hand against the implementations; they execute on CI (`php artisan test`). SSR only fires once nginx is widened on the VPS per the runbook.

Files (15): `backend/app/Services/Seo/SeoHtmlComposer.php`, `backend/app/Services/Seo/SchemaGraphBuilder.php`, `backend/app/Http/Controllers/SpaPrerenderController.php`, `backend/resources/views/seo/article.blade.php`, `backend/resources/views/seo/summary.blade.php`, `backend/config/seo.php`, `backend/routes/web.php` (rewired), `backend/app/Models/Post.php` (purge hook), `backend/app/Http/Controllers/Api/GeoController.php` (freshness), `scripts/nginx/portfolio-8080.conf` (widened), `docs/runbooks/seo-geo-ssr-deploy.md`, + 4 test files (`SeoHtmlComposerTest`, `SchemaGraphBuilderTest`, `SpaPrerenderTest`, `GeoFreshnessTest`).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations (real `Post`/`PostTranslation` data, the real built `frontend/dist/index.html` shell). During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Make the homepage and all blog surfaces (list, detail, category) fully crawlable by search engines **and** LLM/GEO crawlers without JavaScript, by extending the existing partial server-side renderer in [backend/routes/web.php](../../backend/routes/web.php) into a real `SeoHtmlComposer` service + `SpaPrerenderController`. The server splices full head meta, a JSON-LD graph, hreflang, and a crawlable content body into the built SPA shell. Vue still hydrates on top for real users. Projects keep their current OG-only injection (out of scope).

### Architecture Context (from CLAUDE.md + verified)

- **Existing SSR foothold:** `web.php` `$injectOg` closure loads `base_path('../frontend/dist/index.html')`, regex-rewrites ~12 OG/meta tags, returns HTML. nginx routes `/{lang?}/blog|projects/{slug}` to PHP-FPM ([scripts/nginx/portfolio-8080.conf:98](../../scripts/nginx/portfolio-8080.conf)). Translation fallback: requested lang → `en` → first → post primary fields.
- **Data:** `Post` (`og_image`, `featured_image`, `faq_schema`, `schema_markup`, `published_at`, `category()`, `translation($lang)`, `scopePublished`). `PostTranslation` (`title`, `excerpt`, `content` HTML, `meta_title/description/keywords`, `og_title/description`, `canonical_url`, `ai_summary`, `faq_schema[]`, `schema_markup[]`). `Category` (`name`, `slug`).
- **`HasSeoFields` trait** on `Post`: `getSeoMetaAttribute`, `getOgMetaAttribute`, `getSchemaMarkupAttribute`, `generateAiSummary`.
- **GEO endpoints:** [GeoController](../../backend/app/Http/Controllers/Api/GeoController.php) `llmsTxt()` / `llmsFullTxt()` / `identityBlock()`; [SitemapController](../../backend/app/Http/Controllers/Api/SitemapController.php).
- **Shell anchors** in [frontend/index.html](../../frontend/index.html): `<title>…</title>`, `<meta name="description" …>`, `<div id="app"></div>`, two static `<script type="application/ld+json" data-schema="person|faq">` blocks.
- **Cache:** `CACHE_STORE=database`. **Tests:** PHPUnit class-style (project convention); no PHP on dev Mac → composer/schema phases are **DB-free pure-PHP unit tests**, route/cache phases are feature tests that run on CI.

### Tech Stack

Laravel 12 (PHP 8.2), Eloquent, Laravel Cache (database driver), PHPUnit. No new packages. No headless Chrome, no SSG, no frontend framework change.

### Data Integration Map

| Feature | Data Source | API/Method | Exists? | Action |
|---|---|---|---|---|
| Blog detail meta/body | `Post` + `PostTranslation` | `Post::with('translations','category')->where('slug')` + `translation($lang)` | Yes | Use existing (mirror `$serveBlogOg` fallback) |
| Article JSON-LD | translation fields + `Post.faq_schema` | new `SchemaGraphBuilder::blogPosting()` | No | Create pure builder |
| FAQ JSON-LD | `post_translations.faq_schema` (array cast) | `SchemaGraphBuilder::faqPage()` | Yes (data) / No (builder) | Create builder, real data |
| ai_summary (GEO) | `post_translations.ai_summary` | direct field | Yes | Surface in meta + body |
| Blog list / category items | `Post::published()` + `Category` | controller query | Yes | Use existing scope |
| Homepage Person/FAQ schema | static block in `index.html` | reuse as-is | Yes | Add `WebSite` + identity `<noscript>` only |
| Built shell | `frontend/dist/index.html` | `file_get_contents` (null-guard) | Yes | Reuse existing pattern |
| HTML cache + purge | Laravel Cache + `Post` saved hook | `Cache::remember` + `Post::saved` | No | Create |
| llms.txt freshness | `GeoController` | extend existing methods | Yes | Extend |

### Phases

Phase A and B are independent pure modules (parallelizable). C depends on A+B. D depends on C. E independent. F is docs/operator.

---

### Phase A: `SeoHtmlComposer` service (pure, DB-free)

**Estimated time:** 15 min

**Files:**
- Create: `backend/app/Services/Seo/SeoHtmlComposer.php`
- Test: `backend/tests/Unit/SeoHtmlComposerTest.php`

**Contract:** `compose(string $shellHtml, array $seo): string` where `$seo = ['title','description','keywords','canonical','robots','locale','og'=>[...],'twitter'=>[...],'hreflang'=>['en'=>url,'id'=>url,'x-default'=>url],'jsonLd'=>[array,array,...],'bodyHtml'=>?string]`. Replaces existing title/description/keywords/canonical/og:*/twitter:* tags via the proven regex set; **inserts** `<link rel="alternate" hreflang>` + each `jsonLd` block (as `<script type="application/ld+json">`) immediately before `</head>`; when `bodyHtml` is non-null, replaces `<div id="app"></div>` with `<div id="app">{bodyHtml}</div>`. All values `htmlspecialchars`/`json_encode(JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)`. Returns shell unchanged if a tag/anchor is absent (graceful).

**Steps:**
1. Write failing test `test_inserts_jsonld_and_hreflang_before_head_close` using a fixture shell string. Expected error: `Error: Class "App\Services\Seo\SeoHtmlComposer" not found`.
2. Run test, confirm it fails for that reason.
3. Implement `compose()` (port regex map from `$injectOg`, add insert-before-`</head>` + `#app` body splice).
4. Add tests: `test_replaces_title_description_canonical`, `test_injects_body_into_app_div`, `test_returns_shell_unchanged_when_anchors_missing`, `test_escapes_html_and_encodes_jsonld_safely`.
5. Run tests, confirm all pass.
6. Commit: `feat(seo): add SeoHtmlComposer for server-side head + body splicing`

**Verification:**
- [ ] `php artisan test --filter=SeoHtmlComposerTest` green (or CI)
- [ ] JSON-LD emitted with unescaped slashes/unicode, valid JSON
- [ ] Body splice only fires when `bodyHtml` provided; `#app` shell otherwise intact
- [ ] No placeholder/TODO comments

---

### Phase B: `SchemaGraphBuilder` (pure JSON-LD builders)

**Estimated time:** 15 min

**Files:**
- Create: `backend/app/Services/Seo/SchemaGraphBuilder.php`
- Test: `backend/tests/Unit/SchemaGraphBuilderTest.php`

**Methods (return arrays):** `blogPosting(array $data)`, `breadcrumbList(array $items)`, `faqPage(array $faq)`, `webSite()`, `itemList(array $posts)`, `collectionPage(array $category, array $posts)`, `person()`/`organization()` (mirror the static `index.html` Person block for consistency — single source constant). Each takes plain arrays (no Eloquent) so tests are DB-free.

**Steps:**
1. Write failing test `test_blogPosting_has_required_schema_fields` (asserts `@type=BlogPosting`, `headline`, `author.@type=Person`, `datePublished`, `dateModified`, `image`, `mainEntityOfPage`). Expected error: class not found.
2. Run, confirm fail.
3. Implement builders.
4. Add tests: `test_faqPage_maps_faq_schema_array`, `test_breadcrumbList_positions_increment`, `test_itemList_from_posts`, `test_person_matches_static_index_html`.
5. Run, confirm pass.
6. Commit: `feat(seo): add SchemaGraphBuilder JSON-LD builders`

**Verification:**
- [ ] Builder tests green
- [ ] `faqPage` returns `null`/empty when `faq_schema` empty (no empty `FAQPage`)
- [ ] Person schema identical entity (name/sameAs) to `index.html` static block
- [ ] No placeholder/TODO

---

### Phase C: `SpaPrerenderController` + routes (homepage + blog)

**Estimated time:** 15 min

**Files:**
- Create: `backend/app/Http/Controllers/SpaPrerenderController.php`
- Modify: `backend/routes/web.php` (replace blog closures; add homepage + list + category; **leave project closures as-is**)
- Test: `backend/tests/Feature/SpaPrerenderTest.php`

**Methods:** `home()`, `blogIndex(?$lang)`, `blogDetail($slug, ?$lang)`, `blogCategory($slug, ?$lang)`. Each: resolve model(s) + lang (reuse `$serveBlogOg` fallback chain), build `$seo` (head + `SchemaGraphBuilder` graph + hreflang for en/id/x-default), set `bodyHtml` = rendered `<article>` (h1, author, `<time datePublished/dateModified>`, `<figure>`+alt, full `content` HTML) for **detail**; `<noscript>` summary (`ai_summary` / excerpts) for home/list/category. Read shell via existing `base_path('../frontend/dist/index.html')` guard → on null, 302 redirect (preserve current behavior). `Content-Type: text/html`.

**Steps:**
1. Write failing feature test `test_blog_detail_html_contains_article_body_and_blogposting_jsonld` — seed published `Post`+`PostTranslation`, GET `/en/blog/{slug}`, assert response sees `<article`, the post content, `"@type":"BlogPosting"`, `rel="alternate" hreflang="id"`. Expected error: route returns shell without body (or controller missing).
2. Run, confirm fail.
3. Implement controller; rewire `web.php` blog routes (`/{lang}/blog/{slug}`, `/blog/{slug}`, add `/{lang}/blog`, `/blog`, `/{lang}/blog/category/{slug}`, `/blog/category/{slug}`, and `/` homepage handler). Keep `$serveProjectOg` routes untouched.
4. Add tests: `test_homepage_html_has_person_faq_website_jsonld`, `test_blog_index_has_itemlist`, `test_category_has_collectionpage`, `test_missing_dist_redirects` (project routes regression: `test_project_detail_still_injects_og`).
5. Run, confirm pass.
6. Commit: `feat(seo): SpaPrerenderController serves SSR head+schema+body for homepage+blog`

**Verification:**
- [ ] Feature tests green on CI
- [ ] Raw blog-detail HTML (no JS) contains full article text + `BlogPosting` + `BreadcrumbList` + hreflang
- [ ] Homepage raw HTML has Person + FAQ + `WebSite` JSON-LD
- [ ] Project routes unchanged (regression test passes)
- [ ] No placeholder/TODO

---

### Phase D: HTML cache + purge-on-edit

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Http/Controllers/SpaPrerenderController.php` (wrap compose in `Cache::remember`, key `seo_html:{route}:{lang}:{slug?}`, TTL 1h)
- Modify: `backend/app/Models/Post.php` (boot `saved`/`deleted` → flush this post's + list keys)
- Test: `backend/tests/Feature/SpaPrerenderCacheTest.php`

**Steps:**
1. Write failing test `test_editing_post_purges_cached_html` — GET detail (warms cache), update translation title, GET again, assert new title present. Expected error: stale title returned (cache not purged).
2. Run, confirm fail.
3. Implement `Cache::remember` + `Post::saved` purge (flush detail key + blog-index/category keys for both langs).
4. Add `test_second_request_served_from_cache` (assert no extra DB query / cache hit marker).
5. Run, confirm pass.
6. Commit: `feat(seo): cache composed SSR HTML with purge-on-post-edit`

**Verification:**
- [ ] Cache tests green
- [ ] Editing/deleting a post invalidates its HTML + affected list pages
- [ ] `Cache-Control` header set; works with database cache driver
- [ ] No placeholder/TODO

---

### Phase E: GEO pass — llms.txt + sitemap freshness

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/GeoController.php` (`llmsTxt` top posts + `llmsFullTxt` all posts include `ai_summary` + `updated_at`/`published_at` date)
- Modify: `backend/app/Http/Controllers/Api/SitemapController.php` (post `lastmod` = `updated_at`)
- Test: `backend/tests/Feature/GeoFreshnessTest.php`

**Steps:**
1. Write failing test `test_llms_full_lists_posts_with_summary_and_date` — seed posts, GET `/api/llms-full.txt`, assert each post line has `ai_summary` text + an ISO date. Expected error: summary/date absent.
2. Run, confirm fail.
3. Implement: include `ai_summary` (fallback `generateAiSummary()`/excerpt) + date in both endpoints; sitemap `lastmod` from `updated_at`.
4. Add `test_sitemap_posts_have_lastmod`.
5. Run, confirm pass.
6. Commit: `feat(geo): surface ai_summary + freshness in llms.txt and sitemap`

**Verification:**
- [ ] GEO tests green
- [ ] `/api/llms-full.txt` enumerates all published posts with summary + date
- [ ] Sitemap `lastmod` reflects real edit time
- [ ] No placeholder/TODO

---

### Phase F: nginx widening + acceptance runbook (operator / docs)

**Estimated time:** 10 min — **no app code; VPS manual step**

**Files:**
- Modify: `scripts/nginx/portfolio-8080.conf` (template) — add location blocks routing `/`, `/{lang}/blog`, `/blog`, `/{lang}/blog/category/{slug}`, `/blog/category/{slug}` to PHP-FPM (ordered BEFORE the SPA `try_files` fallback)
- Create: `docs/runbooks/seo-geo-ssr-deploy.md` — operator steps + acceptance curls

**Steps:**
1. Add nginx location blocks to the repo template (mirroring the existing blog/projects block).
2. Document VPS apply: SSH-edit live vhost (live config is hand-edited per the file header), `nginx -t`, `systemctl reload nginx`. Flag drift risk.
3. Document acceptance: `curl -A "Googlebot" https://alisadikinma.com/en/blog/{slug}` shows article body + `BlogPosting`; homepage shows Person+FAQ+WebSite; `/blog` shows `ItemList`; run Google Rich Results Test + Schema.org validator; PageSpeed has no hydration CLS regression.
4. Commit: `docs(seo): nginx route widening + SSR acceptance runbook`

**Verification:**
- [ ] nginx template updated + ordering correct (Laravel locations before `/` static fallback)
- [ ] Runbook lists exact VPS commands + acceptance curls
- [ ] Post-deploy curl-as-Googlebot returns real content (manual, on VPS)

---

### Acceptance criteria (whole plan)

- `curl -A Googlebot /en/blog/{slug}` → full article body + `BlogPosting`/`BreadcrumbList`/`FAQPage` JSON-LD + canonical + hreflang, no JS.
- Homepage raw HTML → Person + FAQ + `WebSite` JSON-LD + identity summary, no JS.
- `/blog` + `/blog/category/{slug}` raw HTML → `ItemList`/`CollectionPage` + post titles/excerpts.
- `/api/llms-full.txt` → all published posts with `ai_summary` + dates; sitemap `lastmod` accurate.
- Editing a post purges its cached HTML within one request.
- Rich Results Test + Schema validator pass for homepage, blog list, blog detail.
- Project routes unchanged; Vue hydration unaffected for real users (no layout jump).
