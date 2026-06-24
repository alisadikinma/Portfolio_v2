# GEO Hardening — Close the 3 Gaps vs. the 5-Pillar Framework

> Source review: Helena Liu "The GEO Toolkit" (5-Pillar) + video (youtu.be/20pTTT8jcEw), 2026-06-25.
> Audit verdict: Pillars 1, 3-signal, 4 essentially done. Gaps live in **Pillar 5 (zero measurement)**,
> **Pillar 1 (projects invisible to JS-blind crawlers)**, **Pillar 2 (FAQ only on blog)**.

## Design

### Current state (verified against files, not docs)

| Pillar | State | Evidence |
|---|---|---|
| 1 AI-readable | ✅ robots (9 AI agents), llms.txt static+dynamic, SSR home+blog | `frontend/public/robots.txt`, `GeoController`, `SpaPrerenderController` |
| 2 Extraction | ⚠️ FAQPage **blog-detail only**; homepage FAQ is static in `index.html` | `SchemaGraphBuilder::faqPage()` fed from `post_translations.faq_schema` |
| 3 Review platforms | ⚪ on-site half done (`Organization`+`aggregateRating`+`Review`) | `organizationWithRating()` |
| 4 Brand mentions | ✅ cross-post LinkedIn+Reddit+YouTube(+IG/TT/Threads/FB) | Zernio + LinkedIn pipelines |
| 5 Monitor | ❌ **no GA4 tag at all**, no AI-referral/crawl tracking | grep: zero gtag/GTM in frontend |

### Three gaps → three phases

**Gap A — Pillar 5 (measurement).** Two distinct signals the toolkit conflates:
1. *Human referrals from AI chats* (someone clicks a citation in ChatGPT) → GA4 catches this **if** a GA4 tag exists. It doesn't. → install gtag, then the toolkit's "AI Traffic" channel group (manual GA4 dashboard step, regex provided).
2. *AI bot crawls* (GPTBot/ClaudeBot/PerplexityBot hitting the SSR routes) → **GA4 can NEVER see these** (bots don't run the JS tag). This is the higher-signal, code-native metric and the real proof the GEO work lands. → a tiny server-side crawler-hit logger.

**Gap B — Pillar 1 (projects SSR).** 56 projects = biggest proof asset, currently a blank `<div id="app">` to crawlers (only OG meta injected). Replicate the blog-detail SSR path for `/projects/{slug}`: `CreativeWork` JSON-LD + crawlable body. The OG-only closures in `web.php` get replaced by a controller method (consistency with blog).

**Gap C — Pillar 2 (dedicated /faq page).** The toolkit's explicit checklist item "Build a dedicated FAQ page." One curated `/faq` surface, FAQPage JSON-LD + visible body, single data source shared by SSR (crawlers) and a Vue view (humans). Per-project / about-page FAQ deferred (YAGNI for v1 — needs a new column + per-row authoring; revisit if measurement shows demand).

### Lazy choices (ponytail)
- **Crawler logger** = one middleware + one tiny `geo_crawler_hits` table (upsert daily counter per bot), not an APM. GA4-style dashboards are overkill for "did GPTBot visit."
- **FAQ data** = `config/faq.php` hand-curated array (~10 Q&A), no admin CRUD. Versioned, reviewed in git, zero new admin UI. Exposed once via a thin endpoint + read by both SSR and Vue.
- **GA4 channel group** = documentation, not code (it's a GA4 UI config). Code only installs the tag.
- Projects already route to PHP-FPM (OG closure works today) → **no nginx change** for Gap B. `/faq` is a new path → **needs the one-line nginx widening** (same as the SSR runbook).

### Data Integration Map

| Component | Data source | Existing? | Notes |
|---|---|---|---|
| `gtag` tag | `VITE_GA4_MEASUREMENT_ID` env | NEW | render-gated; empty id → no tag (dev safe) |
| Crawler logger | request User-Agent | NEW | `geo_crawler_hits(date,bot,hits)` upsert |
| Crawler stat (admin) | `geo_crawler_hits` | NEW | read-only count endpoint, reuse admin auth |
| Project SSR body | `projects` + `project_translations` (`content`/`description`) | ✅ | via `with('translations')` |
| `CreativeWork` JSON-LD | project fields | NEW builder | `SchemaGraphBuilder::creativeWork()` |
| Project SSR cache | DB cache `seo_html:project_detail:*` | ✅ pattern | purge from `Project::boot()` |
| `/faq` data | `config/faq.php` | NEW | shared by SSR + Vue + `FAQPage` |
| `/faq` Vue view | `GET /api/faq` | NEW | humans; SSR injects schema+body for bots |

---

## Implementation Plan

### Phase 1 — Pillar 5: Measurement

**1.1 GA4 tag (frontend).** In `frontend/index.html`, add a gtag snippet that no-ops when the id is absent. Inject the id at build via Vite env `VITE_GA4_MEASUREMENT_ID` (use the `%VITE_*%` HTML replacement or a small `main.js` conditional loader — prefer `main.js` so empty id skips the network call entirely).
- Verify: with id set, `window.dataLayer` populated + GA4 DebugView shows `page_view`; with id empty, no `googletagmanager` request fires.

**1.2 Crawler-hit logger (backend).**
- Migration `geo_crawler_hits`: `id, date (date), bot (string 40), hits (uint), unique(date,bot)`.
- `App\Http\Middleware\LogAiCrawler`: match `request()->userAgent()` against a small map (`GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-Web, PerplexityBot, Google-Extended, Applebot-Extended`). On hit, `DB::table('geo_crawler_hits')->upsert(... hits = hits+1 ...)`. Register on the `web` group only (the SSR routes). Fail-open in try/catch — logging must never 500 a crawl.
- `ponytail:` comment — daily granularity, raise to per-path if attribution by page ever matters.
- Verify: `curl -A 'GPTBot/1.0' https://…/blog/<slug>` increments today's `GPTBot` row; a normal UA does not.

**1.3 Admin read + runbook.**
- `GET /api/admin/geo/crawler-hits` (auth:sanctum) → last 30 days grouped by bot. (Optional small card on an existing admin settings/dashboard view — skip if no time.)
- `docs/runbooks/geo-ai-traffic-ga4.md`: the toolkit's GA4 "AI Traffic" channel group steps + the verbatim regex `chatgpt\.com|openai\.com|perplexity\.ai|gemini\.google\.com|copilot\.microsoft\.com|claude\.ai|anthropic\.com|deepseek\.com`.
- Verify: endpoint returns rows; runbook lists the 5 GA4 steps.

### Phase 2 — Pillar 1: Projects SSR

**2.1 `SchemaGraphBuilder::creativeWork(array $data)`** — `@type CreativeWork`, fields: name, description, url, image, dateCreated/dateModified, `author` (Person), `keywords`, optional `about`. Mirror the `blogPosting()` builder shape (omit empty keys).

**2.2 `resources/views/seo/project.blade.php`** — minimal crawlable body: `<article>` with `<h1>` title, summary/description, project `content`, and any client/role/outcome fields as a `<dl>`. Reuse `seo.article` styling conventions; do **not** contort `seo.article` (different shape).

**2.3 `SpaPrerenderController::projectDetail(Request, slug, ?lang)`** — clone `blogDetail()`:
- resolve-or-404 outside cache; cache key `seo_html:project_detail:{lang}:{slug}`.
- compose: title/description/keywords/canonical/og(`article` or `website`)/twitter/hreflang(`/projects/{slug}` variants) + jsonLd `[creativeWork, breadcrumbList(Home→Projects→title)]` + `bodyHtml`.
- `projectDetailPath()`/path helpers analogous to blog.

**2.4 Wire routes.** In `web.php`, replace the `$serveProjectOg` closures (and the now-unused `$injectOg`/`$resolveImage` if nothing else uses them) with:
```php
Route::get('/{lang}/projects/{slug}', [SpaPrerenderController::class, 'projectDetail'])->where(['lang'=>'en|id']);
Route::get('/projects/{slug}', [SpaPrerenderController::class, 'projectDetail']);
```
Update the block comment (projects no longer "OG-only").

**2.5 Cache purge.** Add `SpaPrerenderController::purgeForProject(Project $p)` (forget all-lang `seo_html:project_detail:{lang}:{slug}` incl. renamed-from slug) + wire `Project::boot()` saved/deleted → `purgeForProject` (mirror `Post::boot`). Home cache untouched (projects not on the SSR home body).
- Verify: `curl` a project slug returns `<article>` body + `CreativeWork` script; editing the project busts the cache; missing slug 404s.

### Phase 3 — Pillar 2: Dedicated /faq page

**3.1 `config/faq.php`** — `['items' => [['q'=>…,'a'=>…], …]]`, ~8-12 curated Q&A (answer-first, full name "Ali Sadikin Ma", per Pillar 2 tactics).

**3.2 `GET /api/faq`** — thin controller returning `config('faq.items')` (so the Vue view + SSR share one source).

**3.3 Frontend `/faq`** — `FaqView.vue` + router entry; fetches `/api/faq`, renders accordion. (Human surface; without it, hydration 404s for real users.)

**3.4 `SpaPrerenderController::faq()`** + route `GET /faq` — compose shell with `FAQPage` JSON-LD (reuse `schema->faqPage()` mapping `q/a`→question/answer) + a crawlable `<dl>` body + canonical/og. Cache `seo_html:faq` (static config → long TTL, no purge needed).
- **Operator step:** widen nginx to route `/faq` to PHP-FPM (same one-liner as `docs/runbooks/seo-geo-ssr-deploy.md`).
- Verify: `view-source:/faq` shows `FAQPage` script + visible Q&A; `/api/faq` returns items; Vue `/faq` renders for humans; Rich Results Test passes.

### Cross-cutting
- **Tests:** `SchemaGraphBuilder` creativeWork + faq mapping (unit); `projectDetail` SSR feature test (body + schema + 404 + cache purge); `LogAiCrawler` middleware (UA match increments, normal UA no-op, fail-open).
- **Docs:** update root `CLAUDE.md` GEO section (projects now SSR'd → P0 fully closed; add `/faq`, `geo_crawler_hits`, GA4 env) + Recent Changes line.
- **Push policy:** commit only; user pushes (CI auto-deploys + needs the nginx `/faq` widening done first).

### Risks
| Risk | Mitigation |
|---|---|
| `/faq` 404 in prod until nginx widened | Document as blocking operator step; route is harmless until then |
| Crawler logger write on every bot hit | Daily upsert = 1 cheap query; fail-open try/catch |
| GA4 id leaks to dev/preview | env-gated; empty id = no tag |
| Project SSR cache stampede on popular slug | 1h TTL + `Cache::remember` single-flight (same as blog, proven) |
| `CreativeWork` duplicates client-side Vue schema | SSR is authoritative in `<head>`; Vue injects post-hydration (crawlers read SSR only — same as blog today) |
