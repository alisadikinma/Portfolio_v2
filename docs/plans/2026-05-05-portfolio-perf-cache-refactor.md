> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

# Portfolio Performance + Cache Strategy Refactor

## Goal

Two interlocking problems solved as one integrated refactor:

1. **Image perf**: Project thumbnails 540KB+ each, no WebP, no responsive srcset, no LQIP. First load slow, revisit feels slow when browser disk-cache evicts large assets.
2. **Stale cache**: `localStorage['PORTFOLIO_QUERY_CACHE']` 24-hour persistence at [main.js:39-67](../../frontend/src/main.js#L39-L67) restores TanStack queries with `setQueryData()` (sets `dataUpdatedAt = now` → query treated as fresh → `refetchOnMount: 'always'` skipped). Hides newly-added page-section rows (e.g., today's `what-i-solve`, `testimonials`) until user clears localStorage.

End state: revisit any visited page is instant from browser/SW cache, image payload reduced 60-80% via WebP variants + responsive srcset, cache invalidation deterministic, no 24-hour stale window.

## Architecture Context

Pulled from root + frontend `CLAUDE.md`:

- **Backend stack**: Laravel 12.32 + PHP 8.2 + Intervention Image 3.11 (already installed, verified). Storage: `Storage::disk('public')` symlinked at `/storage/...`. Gallery raw uploads at `/uploads/...` (PHP-FPM 50MB limit).
- **Frontend stack**: Vue 3.5 + Rolldown-Vite 7.1 + Tailwind 4. TanStack Query 5.90 (in-memory) + manual persistent cache in [main.js](../../frontend/src/main.js). Service Worker at [public/sw.js](../../frontend/public/sw.js) — cache-first for media, only registers in PROD.
- **Infra**: nginx 1.24 on VPS. Confirmed via curl (`Server: nginx/1.24.0`). **Cloudflare proxy NOT active** (orange cloud OFF). DNS likely points directly to VPS IP.
- **Existing image headers (verified prod)**: `Cache-Control: max-age=2592000, public, immutable` on `/storage/*` images — already optimal. `index.html` correctly `no-cache, no-store`. API JSON `no-cache, private` (no ETag yet).
- **DB schema**: `projects.image` (varchar), `projects.images` (longtext JSON), `posts.featured_image` (varchar), `gallery_items.file_path` (varchar). No variant columns exist.
- **Components to reuse**: 17 components in [components/base/](../../frontend/src/components/base/). Add `BaseImage.vue` here.
- **Deploy**: GitHub Actions `.github/workflows/deploy.yml` → VPS. NO manual deploy.

## Tech Stack

- Backend: Intervention Image 3.11 for variant generation, Laravel queued jobs for backfill, model observer for upload-time generation
- Frontend: native `<picture>` + `<source srcset>`, `loading="lazy"`, `decoding="async"`, `fetchpriority="high"`, base64 LQIP blur placeholder
- SW: vanilla (no Workbox dependency), versioned cache key tied to Vite build hash
- ETag: Laravel built-in `Response::setEtag()` via custom middleware on API GET routes
- CDN: Cloudflare free tier (orange cloud + page rules, no Polish/Pro)

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Image variants storage | `projects.image_variants` JSON | new column via migration | No | Create migration (Phase C1) |
| Image variants storage | `posts.image_variants` JSON | new column | No | Create migration (Phase C1) |
| Image variants storage | `gallery_items.image_variants` JSON | new column | No | Create migration (Phase C1) |
| Variant generation | Intervention Image | new `App\Services\ImageVariantService` | No (service) | Create (Phase C2). Library installed. |
| Generation trigger | Upload-time auto | new `App\Traits\HasImageVariants` + observer | No | Create (Phase C3) |
| Backfill | Existing image rows | new artisan `images:generate-variants` | No | Create (Phase C4) |
| API exposure | `ProjectResource`, `PostResource`, `GalleryItemResource` | existing resources | Yes | Modify (Phase C5) |
| Frontend rendering | `BaseImage.vue` | new component | No | Create (Phase D1) |
| Frontend integration | ProjectCard, BlogCard, GalleryItem, Home hero, BlogDetail hero | existing components | Yes | Modify (Phase D2) |
| ETag JSON | `App\Http\Middleware\ApiETag` | new middleware | No | Create (Phase A3) |
| SW upgrade | `frontend/public/sw.js` | existing file | Yes | Modify (Phase B) |
| Persistent cache removal | `frontend/src/main.js` | existing | Yes | Modify (Phase A1) |
| Page-sections refetch fix | `frontend/src/composables/usePageSections.js` | existing | Yes | Modify (Phase A2) |
| Cloudflare proxy | DNS toggle + page rules | infra ops | No (manual) | Operator step (Phase E) |

## Phases overview

| Phase | Topic | Time | Depends on | Parallel-safe with | Status |
|---|---|---|---|---|---|
| **E** | Cloudflare proxy + page rules | ~30 min ops | — | A, C | **NEXT** (start here) |
| **A** | Cache strategy refactor (drop persist, add ETag, fix usePageSections) | ~3-4 hr | — | E, C | Pending |
| **C** | Backend image variant generation (service + trait + migration + backfill) | ~5-6 hr | — | E, A | Pending |
| **D** | Frontend BaseImage component + replace usages | ~3-4 hr | C (need API exposing variants) | — | Pending |
| ~~B~~ | ~~Service Worker upgrade~~ | ~~1-2 hr~~ | — | — | **DEFERRED** — browser HTTP cache + CF edge cache already cover 95% of revisit perf. Revisit if real offline/PWA use case emerges. |

Total: ~12-15 hours. **Recommended order**: E → A (paralel, day 1) → C → D (day 2-4). Phase B dropped from current scope.

---

## Phase A: Cache Strategy Refactor

**Estimated time:** 3-4 hours. **Files affected:** 4.

**Files:**
- Modify: [frontend/src/main.js](../../frontend/src/main.js) (remove persistent cache block)
- Modify: [frontend/src/composables/usePageSections.js](../../frontend/src/composables/usePageSections.js) (remove early-return)
- Create: `backend/app/Http/Middleware/ApiETag.php`
- Modify: `backend/bootstrap/app.php` (register middleware)
- Test: `backend/tests/Feature/ApiETagMiddlewareTest.php`
- Test: `frontend/src/composables/__tests__/usePageSections.test.js` (smoke)

### A1. Remove PORTFOLIO_QUERY_CACHE persistence

**Steps:**
1. Write failing test: `frontend/src/__tests__/main-cache.test.mjs` — assert `localStorage.getItem('PORTFOLIO_QUERY_CACHE')` is null after app boot. Expected error: `AssertionError: expected '...' to be null` (test currently no-ops since main.js writes it).
2. Run test, see fail.
3. In [main.js](../../frontend/src/main.js): delete lines 39-98 (CACHE_KEY block + saveCache + beforeunload + setInterval). Keep QueryClient defaults intact.
4. Run test, see pass.
5. Commit: `refactor(cache): drop 24h PORTFOLIO_QUERY_CACHE localStorage persistence`

**Why this is safe to remove:** TanStack staleTime per-query (5min posts, 60min projects, 30s page-sections) handles in-memory cache during one session. On hard refresh, all queries re-fetch. Browser HTTP cache (with ETag from A3) makes JSON revalidation cheap (~50 byte 304). No user-visible regression beyond first-paint of NEW visit needing 1 round-trip per query.

### A2. Fix usePageSections early-return

**Steps:**
1. Write failing test in `usePageSections.test.js`: mock `queryClient.getQueryData` to return stale data, assert `refetch` is still called. Expected: assertion fails because current code returns early.
2. Run test, see fail.
3. In [usePageSections.js:120-124](../../frontend/src/composables/usePageSections.js#L120-L124): remove the `if (queryCache) return early` block. Always call `refetch()`. (TanStack will serve stale-cached data immediately while refetching in background — that's the desired UX.)
4. Run test, see pass.
5. Commit: `fix(page-sections): always refetch instead of early-returning on cache hit`

### A3. Add ETag middleware for API GET responses

**Steps:**
1. Write failing test `ApiETagMiddlewareTest.php`: GET `/api/projects`, capture response. Re-request with `If-None-Match` header set to first response's ETag. Assert second response = 304 with empty body. Expected error: middleware doesn't exist yet.
2. Run test, see fail.
3. Create `ApiETag.php` middleware:
   - Only acts on GET requests with 200 status
   - Compute MD5 of response content → set `ETag: "..."` header
   - If `If-None-Match` matches → return 304 with empty body (drop content)
   - Add `Cache-Control: private, no-cache, must-revalidate` (already mostly set; ensure consistent)
4. Register in `bootstrap/app.php` `withMiddleware(...)` for `api` group.
5. Run test, see pass.
6. Add test for non-GET (POST should bypass), large response (>1MB skip MD5 to avoid CPU), and authenticated route (still works, scoped per-user via Vary).
7. Commit: `feat(api): add ETag/304 middleware for cheap revalidation on GET endpoints`

**Verification (Phase A):**
- [ ] `localStorage.getItem('PORTFOLIO_QUERY_CACHE')` is null after app boot in production build
- [ ] Toggling page-section in admin reflects on Home within next navigation (no 24h staleness)
- [ ] `curl -I -H "If-None-Match: <etag>" /api/projects` returns 304 Not Modified
- [ ] `php artisan test --filter=ApiETag` passes
- [ ] No new TanStack warnings about cache hydration in browser console
- [ ] No placeholder/TODO comments in modified code

---

## Phase B: Service Worker Upgrade

**Estimated time:** 1-2 hours. **Files affected:** 2.

**Files:**
- Modify: [frontend/public/sw.js](../../frontend/public/sw.js)
- Modify: [frontend/src/main.js](../../frontend/src/main.js) (registration message version)
- Create: `frontend/public/sw-build.json` (deploy hash injected at build)
- Modify: `frontend/vite.config.js` (build-time SW version injection)

### B1. Build-hash cache versioning

**Steps:**
1. Write failing test: open `sw.js`, search for hardcoded `media-cache-v1`. Expected: hardcoded version exists.
2. Modify Vite build to inject `__BUILD_HASH__` into `sw.js` during build (use `vite-plugin-static-copy` or simple build-time string replace). Replace `const CACHE_NAME = 'media-cache-v1'` with `const CACHE_NAME = 'media-cache-__BUILD_HASH__'`.
3. Verify production build emits `sw.js` with concrete hash (e.g., `media-cache-a3f9b2c`).
4. Test: deploy → cache name changes → old cache cleaned by activate listener (already there at line 37-49).
5. Commit: `feat(sw): version cache by build hash for deterministic invalidation`

### B2. Stale-while-revalidate strategy

**Steps:**
1. Write failing test (or manual repro): visit page, see cached image served instantly. Currently cache-first never refreshes.
2. Replace fetch handler: instead of pure cache-first, use SWR — return cached immediately if exists, fire fetch in background, update cache for next visit.
3. Keep cache-first fallback for offline (when fetch fails).
4. Test in browser with DevTools: throttle network → cached image instant → background revalidate visible in network tab.
5. Commit: `feat(sw): switch to stale-while-revalidate for media cache`

### B3. Pre-cache critical homepage images on install

**Steps:**
1. Write failing test: count `caches.match()` cache hits for top 5 project thumbnails on first visit. Expected: 0 (none pre-cached).
2. In `install` event, fetch the top 5 project thumbnails URLs (passed via `__PRECACHE_URLS__` build-time replacement, computed at build from a static manifest in `frontend/public/precache-manifest.json`).
3. **Decision pending**: how to know which 5? Option (a) hardcode in manifest, (b) fetch from API at SW install — too complex. Pick (a) — operator updates `precache-manifest.json` quarterly. Add to `gaspol-sync-docs` checklist.
4. Run test, see pass after install.
5. Commit: `feat(sw): pre-cache top homepage thumbnails on install`

**Verification (Phase B):**
- [ ] `sw.js` after build contains `media-cache-{hash}` (not `media-cache-v1`)
- [ ] DevTools → Application → Cache Storage shows cache name with deploy hash
- [ ] Old cache entries deleted on next visit after deploy (via activate handler line 37-49)
- [ ] Throttled network: cached image served <50ms, background revalidate fires
- [ ] First-time visitor sees pre-cached images on Home <1s after SW install
- [ ] No SW errors in DevTools console

---

## Phase C: Backend Image Variant Generation

**Estimated time:** 5-6 hours. **Files affected:** ~15.

**Files:**
- Create migration: `2026_05_05_000001_add_image_variants_to_projects_posts_gallery.php`
- Create: `backend/app/Services/ImageVariantService.php`
- Create: `backend/app/Traits/HasImageVariants.php`
- Create: `backend/app/Observers/ImageVariantObserver.php`
- Create: `backend/app/Console/Commands/GenerateImageVariants.php`
- Modify: `backend/app/Models/Project.php` (use trait)
- Modify: `backend/app/Models/Post.php` (use trait)
- Modify: `backend/app/Models/GalleryItem.php` (use trait)
- Modify: `backend/app/Http/Resources/ProjectResource.php`
- Modify: `backend/app/Http/Resources/PostResource.php`
- Modify: `backend/app/Http/Resources/GalleryItemResource.php`
- Tests: `backend/tests/Unit/ImageVariantServiceTest.php`, `backend/tests/Feature/ImageVariantsApiTest.php`

### C1. Schema migration

**Steps:**
1. Write failing test `ImageVariantsApiTest::test_projects_response_includes_variants_field` — expect JSON has `image_variants` key. Current = no such field.
2. Run test, see fail.
3. Migration adds nullable JSON column `image_variants` to `projects`, `posts`, `gallery_items`. Same column name across 3 tables for trait reuse.
4. Run migration locally, verify with `Schema::hasColumn('projects', 'image_variants') === true`.
5. Test still fails because resource hasn't exposed it — fix in C5.
6. Commit: `feat(db): add image_variants JSON column to projects, posts, gallery_items`

**Schema shape (stored value):**
```json
{
  "320w": "/storage/projects/49_dlp-form-request-cybersecurity-320w.webp",
  "640w": "/storage/projects/49_dlp-form-request-cybersecurity-640w.webp",
  "1024w": "/storage/projects/49_dlp-form-request-cybersecurity-1024w.webp",
  "1920w": "/storage/projects/49_dlp-form-request-cybersecurity-1920w.webp",
  "lqip": "data:image/jpeg;base64,/9j/4AAQ...K"
}
```

### C2. ImageVariantService

**Steps:**
1. Write failing test `ImageVariantServiceTest::test_generate_creates_4_widths_and_lqip` — invoke service with a fixture image, assert 4 .webp files exist + LQIP base64 string returned.
2. Run test, see fail.
3. Implement service:
   - `generate(string $sourcePath): array` returns variants map + writes `.webp` files to same dir
   - Widths: 320, 640, 1024, 1920. Skip widths >= original width (no upscale).
   - WebP only (skip AVIF for v1 — encoding 5-10x slower; revisit later)
   - LQIP: resize to 24w, encode JPEG quality 30, base64 dataURI
   - Idempotent: skip generation if variant file already exists with newer mtime than source
4. Run test, see pass.
5. Add test for skip-upscale (small source).
6. Add test for non-image input (returns empty variants, logs warning, doesn't throw).
7. Commit: `feat(images): ImageVariantService with WebP variants + LQIP placeholder`

### C3. HasImageVariants trait + observer

**Steps:**
1. Write failing test: model with trait, save with new image path → after save, `image_variants` column populated.
2. Implement `HasImageVariants` trait — declares `imageVariantSource(): string` abstract method (returns the source column name like `'image'` or `'featured_image'` or `'file_path'`).
3. Boot method registers `ImageVariantObserver`. Observer's `saving()` event: if source column dirty AND truthy, dispatch queued job (or sync call for v1) → service → write to `image_variants` JSON.
4. Decision: queued vs sync? Sync = upload feels slow for big images (~3-5s for 1920w generation). Queued = upload feels fast but variant URLs `null` until job runs (frontend must handle null gracefully via fallback to original).
5. Pick **queued** + frontend fallback. Job: `App\Jobs\GenerateImageVariantsJob`.
6. Run test, see pass.
7. Commit: `feat(images): HasImageVariants trait dispatches variant generation on upload`

### C4. Backfill artisan command

**Steps:**
1. Write failing test: seed Project without variants → run command → variants populated.
2. Implement command: chunked iteration over Projects/Posts/GalleryItems where `image_variants IS NULL` AND source column NOT NULL. Dispatches GenerateImageVariantsJob per row.
3. Add `--model=Project|Post|GalleryItem|all` flag, `--limit=N`, `--dry-run`.
4. Run test, see pass.
5. Commit: `feat(images): images:generate-variants artisan backfill command`

### C5. Expose variants in API resources

**Steps:**
1. Update test from C1 — should now pass.
2. Modify `ProjectResource::toArray()`: add `'image_variants' => $this->image_variants`. Same for Post, GalleryItem resources.
3. Run all 3 tests, see pass.
4. Commit: `feat(api): expose image_variants in Project/Post/GalleryItem resources`

**Verification (Phase C):**
- [ ] `php artisan test --filter=ImageVariant` all green (C2 unit + C3 trait + C5 API)
- [ ] `php artisan migrate` clean (forward + rollback)
- [ ] `php artisan images:generate-variants --dry-run` reports correct count of pending rows
- [ ] After actual run on prod fixture, `/storage/projects/49_*-320w.webp` exists with content-type image/webp
- [ ] LQIP string starts with `data:image/jpeg;base64,` and is < 2KB
- [ ] API response includes `image_variants` object on Projects/Posts/Galleries
- [ ] Queue worker (`portfolio-queue.service`) successfully processes `GenerateImageVariantsJob`

---

## Phase D: Frontend BaseImage Component

**Estimated time:** 3-4 hours. **Files affected:** ~10.

**Files:**
- Create: `frontend/src/components/base/BaseImage.vue`
- Modify: `frontend/src/components/base/index.js` (export)
- Modify: `frontend/src/components/projects/ProjectCard.vue`
- Modify: `frontend/src/components/blog/BlogCard.vue`
- Modify: `frontend/src/components/awards/AwardCard.vue`
- Modify: `frontend/src/views/BlogDetail.vue`
- Modify: `frontend/src/views/ProjectDetail.vue`
- Modify: `frontend/src/views/Gallery.vue`
- Modify: `frontend/src/components/home/LatestBlog.vue`
- Modify: `frontend/src/components/home/ProjectsBento.vue`
- Test: `frontend/src/components/base/__tests__/BaseImage.test.js`

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| D1 | BaseImage component | LQIP blur fade-in spec, sizes attribute table per breakpoint | Tests + visual smoke |
| D2 | Replace `<img>` in 8 sites | n/a (drop-in replacement) | No layout shift, perf metrics |

### D1. BaseImage component

**Props:**
```js
{
  src: String,                    // fallback original URL
  variants: Object,                // { '320w': url, '640w': url, '1024w': url, '1920w': url, lqip: base64 }
  alt: String,
  sizes: String,                   // e.g., "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
  eager: Boolean,                  // default false → loading="lazy"
  fetchpriority: String,           // 'high'|'low'|'auto', default 'auto'
  aspectRatio: String,             // e.g., "16/9" — for placeholder box to prevent CLS
  class: String
}
```

**Render:**
```html
<picture>
  <source v-if="variants" type="image/webp"
          :srcset="srcsetString" :sizes="sizes">
  <img :src="src" :alt="alt" :loading="eager ? 'eager' : 'lazy'"
       :fetchpriority="fetchpriority" decoding="async"
       :style="lqipBackgroundStyle" @load="onLoad" />
</picture>
```

**LQIP behavior**: bg `style="background-image: url(lqip); background-size: cover; filter: blur(8px);"` — image transitions opacity 0→1 on load via `onLoad` handler removing the bg + blur. Prevents CLS via `aspectRatio` CSS.

**Steps:**
1. Write failing test: mount BaseImage with mock variants, assert `srcset` attribute matches.
2. Implement component.
3. Add fallback: if `variants` is null/empty, render plain `<img src>` (graceful degrade for un-backfilled rows).
4. Run test, see pass.
5. Add test for LQIP placeholder visible until image loaded.
6. Commit: `feat(image): BaseImage component with picture/srcset/LQIP/lazy`

### D2. Replace `<img>` usages

**Steps:**
1. For each file (ProjectCard, BlogCard, AwardCard, BlogDetail hero, ProjectDetail hero, Gallery, LatestBlog, ProjectsBento):
   - Identify hero image vs grid thumbnail (sets `eager` + `fetchpriority="high"` for hero, `eager: false` for grid)
   - Replace `<img>` with `<BaseImage :src=".." :variants=".." sizes=".."/>`
   - Sizes attribute per breakpoint: hero `100vw`, grid card `(max-width: 640px) 100vw, 50vw`, thumbnail `200px`
2. Test each page in browser: hero loads <1s, no CLS (Lighthouse), grid lazy-loads as scrolled.
3. Commit per file: `feat(<view>): use BaseImage with responsive variants`

**Verification (Phase D):**
- [ ] BaseImage renders correct `<picture><source srcset=...>` markup
- [ ] LQIP visible during load, fades on `onload`
- [ ] No CLS in Lighthouse on Home / Blog list / Project detail / Gallery
- [ ] Lighthouse Performance score >85 on prod build (was likely <70)
- [ ] Images <100KB on mobile viewport (vs 540KB before)
- [ ] Graceful fallback when `image_variants` is null (un-backfilled rows still render)

---

## Phase E: Cloudflare Proxy + Page Rules

**Estimated time:** 30 min ops + 1 hr verification. **Manual operator step**.

**Files:**
- Create: `docs/ops/cloudflare-setup.md` (runbook)

### E1. Operator setup steps

**Pre-flight:**
- [ ] Cloudflare free account active for `alisadikinma.com` zone
- [ ] DNS records visible in CF dashboard
- [ ] Origin IP recorded (for SSL/TLS Full mode origin verification)

**Steps:**
1. Toggle DNS A record proxy to ON (orange cloud) for `alisadikinma.com` + `www.alisadikinma.com`
2. Wait for DNS propagation (~5 min). Verify: `dig alisadikinma.com` returns Cloudflare IP (104.x or 172.x range)
3. SSL/TLS mode = "Full (strict)" — validates origin cert
4. Page Rule 1: `alisadikinma.com/storage/*` → Cache Level: Cache Everything, Edge Cache TTL: 1 month, Browser Cache TTL: 1 month
5. Page Rule 2: `alisadikinma.com/uploads/*` → same as above
6. Page Rule 3: `alisadikinma.com/api/*` → Cache Level: Bypass (respect origin no-cache headers)
7. Always Use HTTPS = ON
8. Auto Minify (HTML, CSS, JS) = ON
9. Brotli = ON
10. HTTP/3 (QUIC) = ON

**Verification (Phase E):**
- [ ] `curl -sI https://alisadikinma.com/storage/projects/thumbnail/49_dlp-form-request-cybersecurity.png` shows `cf-cache-status: HIT` after second request
- [ ] `dig alisadikinma.com` returns CF anycast IP
- [ ] `curl -sI https://alisadikinma.com/api/projects` shows `cf-cache-status: BYPASS`
- [ ] CF analytics dashboard shows traffic flowing through edge after 24h
- [ ] No cert warnings in browser
- [ ] Origin VPS bandwidth drops measurably (CF caches at edge)

---

## Rollback Plan

Each phase is independently reversible:

- **Phase A**: revert main.js + usePageSections.js commit. Re-add ETag middleware can be removed via `bootstrap/app.php` config.
- **Phase B**: revert `sw.js` to v1. Browser auto-cleans new cache via existing activate handler.
- **Phase C**: rollback migration (column drops, no data lost — variants regeneratable). Service/trait/observer code stays; just no-ops without column.
- **Phase D**: revert each component file. BaseImage gracefully falls back to plain `<img>` when variants null, so partial revert OK.
- **Phase E**: toggle CF proxy OFF (grey cloud) — DNS reverts to direct origin in <5 min.

## Open Decisions (need user confirmation before execution)

1. **AVIF in v1 or skip?** Recommend skip (encoding 5-10x slower than WebP, marginal browser support diff in 2026 — modern Safari has WebP). Add later if needed. → Default: SKIP.
2. **Variant generation queued vs sync?** Recommend queued (Phase C3) — non-blocking upload UX. Frontend handles null variants via fallback. → Default: QUEUED.
3. **Pre-cache manifest in SW** — hardcode top 5 thumbnails or fetch dynamically? Recommend hardcode (simpler, deterministic). → Default: HARDCODE.
4. **Cloudflare account already exists?** If no, sign-up step adds ~30 min. → Need user confirmation.

## Execution Handoff

Three options:

**Option 1: Execute in this session sequentially**
> "Ready to start Phase A? I'll use gaspol-execute with per-phase checkpoints. ETA Phase A: 3-4 hr."

**Option 2: Parallel execution where safe**
> "Phases A + B + E (after CF account confirmed) can run in parallel via gaspol-parallel. Then C, then D. Total wall-clock ~8-10 hr instead of 13-17."

**Option 3: Save for separate session**
> "This file at `docs/plans/2026-05-05-portfolio-perf-cache-refactor.md` has everything. Re-enter via `/gaspol-execute docs/plans/2026-05-05-portfolio-perf-cache-refactor.md`."
