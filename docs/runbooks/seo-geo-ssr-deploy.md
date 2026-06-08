# Runbook — SEO + GEO SSR-Enrichment Deploy

**Ships:** server-side `<head>` + JSON-LD graph + crawlable body injection for the
**homepage** and **all blog surfaces** (list / detail / category), so search
engines and LLM/GEO crawlers (ChatGPT, Perplexity, Claude, Google AI) that don't
run JavaScript receive real, indexable, citable content instead of an empty
`<div id="app">`. Projects keep their existing OG-only injection.

**Design + plan:** [docs/plans/2026-06-08-homepage-blog-seo-geo.md](../plans/2026-06-08-homepage-blog-seo-geo.md)

> **Backend code deploys automatically via GitHub Actions** (`git push origin main`
> → `scripts/deploy.sh`). The **only manual step is the nginx widening** below —
> `deploy.sh` does NOT touch nginx. Until nginx routes the new paths to PHP-FPM,
> the new controller exists but never fires (the SPA catch-all serves un-enriched
> `index.html`).

---

## 0. What changed (no DB migration)

| Area | File | Note |
|---|---|---|
| Composer | `backend/app/Services/Seo/SeoHtmlComposer.php` | Pure head/body splicer |
| Schema | `backend/app/Services/Seo/SchemaGraphBuilder.php` | JSON-LD builders |
| Controller | `backend/app/Http/Controllers/SpaPrerenderController.php` | home + blog index/detail/category, 1h HTML cache |
| Views | `backend/resources/views/seo/{article,summary}.blade.php` | Crawlable body + noscript summary |
| Routes | `backend/routes/web.php` | home + blog routes (projects closure kept) |
| Config | `backend/config/seo.php` | `spa_shell_path`, `html_cache_ttl` |
| Cache purge | `backend/app/Models/Post.php` | `saved`/`deleted` → `purgeForPost()` |
| GEO | `backend/app/Http/Controllers/Api/GeoController.php` | `llms.txt` freshness + `ai_summary` |
| nginx | `scripts/nginx/portfolio-8080.conf` | **snapshot — manually sync to live** |

No migration. No new env vars are *required* (sensible defaults), but two are
available:

```env
# Override only if frontend dist lives somewhere non-standard, or for tests.
SEO_SPA_SHELL_PATH=/var/www/Portfolio_v2/frontend/dist/index.html
SEO_HTML_CACHE_TTL=3600
```

---

## 1. Prerequisite — frontend build must exist

The controller reads `frontend/dist/index.html`. `deploy.sh` step 6 runs
`npm ci && npm run build`, so a normal deploy satisfies this. Verify:

```bash
test -f /var/www/Portfolio_v2/frontend/dist/index.html && echo OK || echo MISSING
```

If MISSING, the controller falls back to a 302 redirect (no crash) — but no SSR
enrichment happens until the build is present.

---

## 2. Manual step — widen nginx (the cutover)

The live vhost is `/etc/nginx/sites-enabled/portfolio-8080` (listen 8080 behind
Traefik). The repo snapshot is `scripts/nginx/portfolio-8080.conf` — copy the new
blocks from there.

SSH to the VPS and add, **above `location /`** (and above the existing OG detail
block is fine — replace it), the two location blocks:

```nginx
# Homepage
location = / {
    root /var/www/Portfolio_v2/backend/public;
    rewrite ^(.*)$ /index.php?$1 break;
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME /var/www/Portfolio_v2/backend/public/index.php;
    fastcgi_param REQUEST_URI $request_uri;
    include fastcgi_params;
    fastcgi_hide_header X-Powered-By;
    fastcgi_read_timeout 60;
}

# Blog index + category + detail; project detail (locale-optional)
location ~ ^/(?:(?:en|id)/)?(?:blog(?:/category/[^/]+|/[^/]+)?|projects/[^/]+)/?$ {
    root /var/www/Portfolio_v2/backend/public;
    rewrite ^(.*)$ /index.php?$1 break;
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME /var/www/Portfolio_v2/backend/public/index.php;
    fastcgi_param REQUEST_URI $request_uri;
    include fastcgi_params;
    fastcgi_hide_header X-Powered-By;
    fastcgi_read_timeout 60;
}
```

This **replaces** the old single block
`location ~ ^/(?:(?:en|id)/)?(?:blog|projects)/[^/]+/?$`. The new regex still
covers project detail, and additionally covers the homepage, blog index, and blog
category. `/projects` (the list) is intentionally NOT matched (still client-rendered).

Then:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

> **Why same HTML for bots and humans?** This is progressive enhancement, not
> cloaking — Laravel returns the SPA shell with `<head>`/schema/body injected;
> Vue still hydrates over `#app`. Google explicitly allows this.

---

## 3. Cloudflare note (edge cache vs purge-on-edit)

Homepage + blog HTML now comes from PHP with `Cache-Control: public, max-age=300`.

- Laravel's `purgeForPost()` clears the **origin** HTML cache instantly on edit.
- Cloudflare's **edge** cache still honors `max-age=300`, so an edited post
  surfaces to crawlers within **≤5 minutes**. For an immediate refresh, purge the
  CF cache for the URL (CF dash → Caching → Purge → enter URL), or lower
  `max-age` if you need faster propagation.
- Keep the existing CF rule: `/api/*` = **Bypass** (so `llms.txt`/sitemap stay
  dynamic). Do NOT add a Bypass rule for `/` or `/blog*` — let them cache.

---

## 4. Acceptance verification (no-JS crawler's-eye view)

Run after the nginx reload. `curl` does not execute JS, so this is exactly what a
crawler sees.

```bash
BASE=https://alisadikinma.com
SLUG=<a-real-published-post-slug>

# Blog detail: crawlable article body + BlogPosting + BreadcrumbList
curl -s "$BASE/blog/$SLUG" | grep -c '<article'                 # ≥ 1
curl -s "$BASE/blog/$SLUG" | grep -o '"@type":"BlogPosting"'    # prints match
curl -s "$BASE/blog/$SLUG" | grep -o '"@type":"BreadcrumbList"' # prints match
curl -s "$BASE/blog/$SLUG" | grep -o 'hreflang="id"'            # prints match

# Homepage: WebSite schema + identity summary
curl -s "$BASE/"      | grep -o '"@type":"WebSite"'             # prints match

# Blog index: ItemList
curl -s "$BASE/blog"  | grep -o '"@type":"ItemList"'           # prints match

# Category
curl -s "$BASE/blog/category/<cat-slug>" | grep -o '"@type":"CollectionPage"'

# GEO freshness on llms.txt
curl -s "$BASE/api/llms.txt" | grep 'Last updated:'
```

Then validate structured data:

- Google Rich Results Test: <https://search.google.com/test/rich-results> → paste a blog URL.
- Schema.org validator: <https://validator.schema.org/> → paste a blog URL.
- Re-fetch in Google Search Console (URL Inspection → Test Live URL → View Crawled Page) and confirm rendered HTML now contains the article text.

---

## 5. Rollback

The backend code is inert without nginx routing, so rollback is **nginx-only**:

1. Restore the previous single block in `/etc/nginx/sites-enabled/portfolio-8080`:
   ```nginx
   location ~ ^/(?:(?:en|id)/)?(?:blog|projects)/[^/]+/?$ { ... }
   ```
   (remove the `location = /` homepage block and the widened blog regex).
2. `sudo nginx -t && sudo systemctl reload nginx`.

Homepage + blog index/category revert to client-rendered SPA; blog/project detail
revert to OG-only. No data is touched.

To clear stale origin HTML cache at any time:

```bash
cd /var/www/Portfolio_v2/backend && php artisan cache:clear
```
