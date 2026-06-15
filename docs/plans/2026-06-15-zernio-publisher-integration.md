# Zernio Publisher Integration (IG / TikTok / Threads)

> Status: **Design approved** — ready for `gaspol-plan` to append `## Implementation Plan`.
> Date: 2026-06-15 · Approach: **A — Parallel adapter + per-platform publisher selector**

## Design

### Goal & decisions (locked with operator)

Add **Zernio** (`https://zernio.com/api/v1`) as a new publisher for **Instagram, TikTok, Threads**, slotting into the existing cross-post fan-out alongside Publer.

| Decision | Choice |
|---|---|
| Role of Zernio | **Primary** for IG/TikTok/Threads. Publer stays wired (Facebook + transitional fallback); removed cleanly once Zernio is proven. |
| Approach | **A** — mirror the Publer trio (`ZernioClient` / `ZernioPayloadBuilder` / `PublishViaZernio`), add a per-platform publisher selector setting. NO refactor of the working Publer path; NO shared `Publisher` interface (YAGNI — Publer is slated for deletion). |
| Two API keys | **Two separate Zernio workspaces.** Key 1 → IG + TikTok; Key 2 → Threads. Adapter selects the key + `accountId` namespace per platform. |
| Account binding | **Admin UI manual entry** — operator pastes Zernio `accountId` per platform into a settings card. |
| v1 scope | All-image carousel + text → IG/TikTok/Threads · **mixed video+image IG carousel (GROK hook)** · admin settings card · **schedule support (`scheduledFor`)**. |

> ⚠️ **Security**: the two API keys were pasted in chat — **rotate both in the Zernio dashboard** and store the new values only as env vars (`ZERNIO_API_KEY_IGTT`, `ZERNIO_API_KEY_THREADS`), encrypted in `settings`. Never hardcode.

### Zernio API facts that shape the build (verified from docs, 2026-06-15)

- **Auth**: `Authorization: Bearer sk_…` (standard Bearer, unlike Publer's `Bearer-API`). Key is workspace-scoped → one key per workspace.
- **Create post**: `POST /v1/posts` with `{ content?, mediaItems:[{url,type}], platforms:[{platform, accountId, platformSpecificData?}], publishNow? | scheduledFor?+timezone? | isDraft? }`. `publishNow:true` returns `platformPostUrl` in the response (synchronous result — no await-poll like Publer).
- **Media = public URLs, no upload step.** `mediaItems[].url` must be a public CDN URL returning raw bytes. Your slides at `https://alisadikinma.com/storage/linkedin-carousel/*.png` qualify → pass directly. (Presigned upload `POST /v1/media/presign` exists but is **not** required.)
- **IG carousel**: up to 10 **mixed** image/video items; **first item sets the aspect ratio** for the whole carousel. `platformSpecificData.firstComment` posts the first comment (links). Business/Creator account required (already true).
- **TikTok**: photo carousel (≤35 photos) **OR** 1 video — **no mixing**. → hook video NOT allowed in a TikTok image carousel.
- **Threads**: **500-char hard cap** (#1 failure mode) + **no video carousels** (≤10 images). Caption already authored short by `/threads-gen`.
- **Idempotency**: send a stable `x-request-id` (UUID) per logical publish → retries return the original post (HTTP 200) instead of double-posting. Independent content-hash dedup returns **HTTP 409** within 24h (treat as already-published, capture `existingPostId`).
- **Scheduling**: `scheduledFor` (ISO-8601) + `timezone` maps 1:1 to the existing fixed-slot scheduler.

### Architecture — how Zernio slots into the existing fan-out

The fan-out (`ScanLinkedInForCrossPost` → sibling rows `InstagramPost`/`TiktokPost`/`ThreadsPost` → `Generate*Post` caption job → publish job) is **unchanged**. The ONLY change is at the publish-dispatch site: each `Generate*Post` job picks the publish job via a per-platform selector setting.

```
Generate{Instagram,Tiktok,Threads}Post  (caption authored, status→validating)
        │  PublisherResolver::for($platform)  → reads settings.crosspost_publisher_{platform}
        ├─ 'zernio'  → PublishViaZernio::dispatch($siblingId)
        └─ 'publer'  → PublishViaPubler::dispatch($siblingId)   (unchanged path)
```

**One Zernio `createPost` per sibling** (single-platform `platforms[]` entry), NOT one multi-platform call — because (a) two-key routing means IG/TikTok and Threads hit different workspaces, (b) each sibling has its own FSM + its own per-platform caption, (c) keeps failure isolation identical to today.

`PublishViaZernio` flow (mirrors `PublishViaPubler`, minus the upload loop):
1. Load sibling + source `LinkedInPost.carousel_slides`.
2. `ZernioPayloadBuilder::build{Instagram,Tiktok,Threads}($sibling)` → payload (public slide URLs in `mediaItems`, per-platform rules below).
3. `ZernioClient::forPlatform($platform)->createPost($payload, $xRequestId)` — `publishNow` OR `scheduledFor`.
4. Parse `platformPostUrl` / post `_id` → store on `zernio_post_id`; status → `published` (or `scheduled`). HTTP 409 → treat as already-published (store `existingPostId`). Errors → `failed` + `last_error`.

### Per-platform payload rules (in `ZernioPayloadBuilder`)

| Platform | Key | mediaItems | Caption | First comment | Notes |
|---|---|---|---|---|---|
| Instagram | igtt | slide PNGs as `image`; if hook video present, slide[0] = `{type:video}` then images | IG caption (`/instagram-gen`) | `platformSpecificData.firstComment` = blog link (suppressed for repurpose) | hook video **must be 4:5** to match slides (first item sets ratio) |
| TikTok | igtt | slide PNGs as `image` only (≤35) | TikTok caption (`/tiktok-gen`) | n/a | **no hook video** (no mixing) |
| Threads | threads | slide PNGs as `image` only (≤10) | Threads caption (`/threads-gen`), **≤500 chars** | n/a | no video carousel |

### Components / Data Integration Map

| Component | Type | Data source / role | Existing? |
|---|---|---|---|
| `ZernioClient` | new service | REST wrapper; `forPlatform($p)` selects key (igtt/threads); `createPost($payload,$reqId)`, `listAccounts()` (verify), `getPost($id)` | mirror of `PublerClient` |
| `ZernioPayloadBuilder` | new service | sibling + `carousel_slides[]` → Zernio payload; per-platform rules above; reads `accountId` from settings | mirror of `PublerPayloadBuilder` |
| `PublishViaZernio` | new job | `social-crosspost` queue; build → createPost → mirror status; 409-as-published; retries | mirror of `PublishViaPubler` |
| `PublisherResolver` | new helper | reads `settings.crosspost_publisher_{platform}` → `'zernio'\|'publer'` (default `zernio` for the 3) | new |
| `config/social-cross-post.php` | edit | add `zernio` block: `base_url`, `api_key_igtt`/`api_key_threads` (env), `max_retries`, `http_timeout_seconds`, `schedule_enabled` | exists (publer block) |
| `settings` group `zernio` | seeder | `zernio_api_key_igtt`, `zernio_api_key_threads` (encrypted), `zernio_{instagram,tiktok,threads}_account_id`, `crosspost_publisher_{instagram,tiktok,threads}` | new group |
| migration | new | add `zernio_post_id` (+ `zernio_request_id`) to `instagram_posts`, `tiktok_posts`, `threads_posts` (mirror `publer_post_id`) | mirror |
| `Generate{Instagram,Tiktok,Threads}Post` | edit | swap hardcoded `PublishViaPubler::dispatch` → `PublisherResolver`-driven dispatch | exists |
| Zernio settings card | new Vue | `AboutSettings.vue` card: 2 keys, 3 accountIds, 3 publisher selectors, "Verify connection" button | mirror Publer/Postiz cards |
| settings controller | edit | GET/PUT `zernio` settings (mask keys) + POST `verify` → `ZernioClient::listAccounts()` per key | mirror |
| env | edit | `ZERNIO_BASE_URL`, `ZERNIO_API_KEY_IGTT`, `ZERNIO_API_KEY_THREADS` | new |

### Feasibility — all real, no placeholders

- Slide URLs are already public + CDN-served → `mediaItems` works with zero new infra.
- `social-crosspost` queue + `portfolio-crosspost@{1..N}` workers already run the publish jobs.
- Synchronous `publishNow` result removes Publer's await-poll complexity.
- The two-key routing is a constructor switch in `ZernioClient::forPlatform()`.

### Out of scope / follow-ups

- Migrating Facebook to Zernio (Publer keeps FB for now).
- Deleting Publer (do it after Zernio is proven — selector flip + file removal).
- Zernio webhooks, analytics, inbox/DM, IG Stories/Reels-as-Reel, Threads thread-sequences.
- Confirm the GROK hook video render is 4:5 (or transcode) so it leads the IG carousel ratio — flagged as a plan verification step.

### Known unknowns to verify in plan/execute

1. GROK hook video aspect ratio vs IG carousel 4:5 requirement (first item sets ratio).
2. Exact `accountId` shape Zernio returns from `listAccounts()` vs what the dashboard shows the operator to paste.
3. Whether content-hash dedup (409) trips on legit re-publish after a failed-then-retried slide swap (mitigate with `x-request-id` stability + caption variance).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations (live Zernio API, real settings-backed credentials, real cross-post sibling rows). During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Add **Zernio** as the primary publisher for Instagram / TikTok / Threads by mirroring the existing Publer trio (`PublerClient` / `PublerPayloadBuilder` / `PublishViaPubler`) with a parallel `ZernioClient` / `ZernioPayloadBuilder` / `PublishViaZernio`, selected at a single dispatch site via a new `PublisherResolver`. Publer stays fully wired and untouched (Facebook + transitional fallback), removed cleanly later. Net effect: IG gets mixed video+image carousels (the thing Publer couldn't do), and all three platforms publish through a workspace-scoped Zernio API with no media-upload step.

### Architecture Context (from CLAUDE.md + code exploration)

- **Fan-out is unchanged**: `ScanLinkedInForCrossPost` (cron `social-cross-post:scan`) creates sibling rows `InstagramPost`/`TiktokPost`/`ThreadsPost` (FK `linkedin_post_id`), each with its own status FSM (`InstagramPostStatus` etc., via `HasStatusTransitions`) and per-platform caption authored by `/instagram-gen`/`/tiktok-gen`/`/threads-gen`.
- **Single publish dispatch site**: [`BaseSocialGenerationService.php:324`](backend/app/Services/BaseSocialGenerationService.php#L324) — `\App\Jobs\PublishViaPubler::dispatch($platform, $draft->id)`, fired right after `transitionTo(<Status>::AwaitingReview, 'generation_complete', …)`. **This one line is the only swap point.**
- **Publer mirror targets**: [`PublishViaPubler`](backend/app/Jobs/PublishViaPubler.php) (`$tries=3`, `$backoff=[60,300,900]`, `$timeout=600`, queue `social-crosspost`, ctor `(string $platform, int $siblingPostId)`), [`PublerPayloadBuilder`](backend/app/Services/PublerPayloadBuilder.php) (`buildInstagram/Tiktok/Threads(...)`, `resolveSiblingContent`, `siblingHasVideoMedia`, static `isPlatformEnabled`), [`PublerClient`](backend/app/Services/PublerClient.php) (ctor `(?string $apiKey=null)`, reads `Setting{group:publer,key:publer_api_key}` + `Crypt::decryptString`, `Http::withHeaders(...)->baseUrl(config('social-cross-post.publer.base_url'))`).
- **Sibling models** (`app/Models/InstagramPost.php` etc.): `$casts['status'] => <Enum>::class`, `carousel_slides => json`; existing `publer_post_id` column — add a sibling `zernio_post_id` + `zernio_request_id`.
- **Settings pattern**: read `Setting::where('group',…)->where('key',…)->value('value')`; write `Setting::updateOrCreate(['group'=>…,'key'=>…],['value'=>…])`; secrets `Crypt::encryptString`, masked in API as `***SET***` + `*_configured` bool.
- **Zernio API** (verified, see Design): `POST https://zernio.com/api/v1/posts`, `Authorization: Bearer sk_…`, `mediaItems:[{url,type}]` with public CDN URLs (slides at `https://alisadikinma.com/storage/linkedin-carousel/*.png`), `platformSpecificData.firstComment` (IG), `publishNow|scheduledFor+timezone`, `x-request-id` idempotency, HTTP 409 content-dedup.

### Tech Stack

Laravel 12 · PHP 8.2 · Pest/PHPUnit (`php artisan test`) · `Http` facade (`Http::fake`) · MySQL (sqlite in CI) · Vue 3.5 + TanStack Query (admin card). Mirror existing conventions — no new libraries.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Zernio API keys (2 workspaces) | `Setting{group:zernio, key:zernio_api_key_igtt\|zernio_api_key_threads}` (encrypted) | `ZernioClient::forPlatform()` | No | Create (seeder + admin card) |
| Per-platform account id | `Setting{group:zernio, key:zernio_{instagram,tiktok,threads}_account_id}` | `ZernioPayloadBuilder` reads | No | Create (manual entry in card) |
| Publisher selector | `Setting{group:zernio, key:crosspost_publisher_{instagram,tiktok,threads}}` (default `zernio`) | `PublisherResolver::for()` | No | Create |
| Slide image URLs | `LinkedInPost.carousel_slides[].image_url` (public CDN) | read directly in builder | Yes | Use existing |
| IG hook video | `InstagramPost.hook_video_url` + `hook_video_status` | read in `buildInstagram` | Yes | Use existing |
| Per-platform caption | `{Instagram,Tiktok,Threads}Post.caption` (+ `link_comment`) | read in builder | Yes | Use existing |
| Sibling status FSM | `<Platform>PostStatus` via `HasStatusTransitions` | `$sibling->transitionTo()` | Yes | Use existing |
| Published id storage | new `zernio_post_id` / `zernio_request_id` columns | migration | No | Create (migration) |
| Publish dispatch | `BaseSocialGenerationService.php:324` | `PublisherResolver` + match | Yes (Publer) | Modify (1 line → match) |
| Settings endpoints | `SettingsController` + `routes/api.php` admin group | `getZernio/updateZernio/verifyZernio` | No | Create (mirror Publer) |
| Admin card | `frontend/src/views/admin/AboutSettings.vue` | fetch/PUT zernio settings | No | Create (mirror Publer/Postiz card) |

> Contract: every "No" row is built as a **real working integration**, never a stub. If any cannot be built as specified, STOP and ask.

---

### Phase A — Config block + env

**Estimated time:** 6 min

**Files:**
- Modify: `backend/config/social-cross-post.php` (add `zernio` block)
- Modify: `backend/.env.example` (add `ZERNIO_*` keys)
- Test: `backend/tests/Unit/ZernioConfigTest.php`

**Steps:**
1. Write failing test for `config('social-cross-post.zernio.base_url')` returning `https://zernio.com` and `zernio.api_path` `/api/v1`. Expected error: `Failed asserting that null matches expected 'https://zernio.com'`.
2. Run test, confirm it fails for the expected reason.
3. Add `zernio` block: `enabled` (`env('ZERNIO_PUBLISH_ENABLED', false)`), `base_url` (`env('ZERNIO_BASE_URL','https://zernio.com')`), `api_path` (`/api/v1`), `max_retries`, `http_timeout_seconds`, `schedule_enabled` (`env('ZERNIO_SCHEDULE_ENABLED', true)`). **No media-poll keys** (no upload step). Add `ZERNIO_*` to `.env.example`.
4. Run tests, confirm pass.
5. Commit: `feat(zernio): add social-cross-post config block`

**Verification:**
- [ ] `php artisan test --filter=ZernioConfigTest` passes
- [ ] `config('social-cross-post.zernio')` returns the full array; no media-poll keys present
- [ ] No placeholder/TODO comments

---

### Phase B — Migration: zernio columns on sibling tables

**Estimated time:** 8 min

**Files:**
- Create: `backend/database/migrations/2026_06_15_000001_add_zernio_columns_to_cross_post_tables.php`
- Modify: `backend/app/Models/{InstagramPost,TiktokPost,ThreadsPost}.php` (`$fillable`)
- Test: `backend/tests/Feature/ZernioColumnsMigrationTest.php`

**Steps:**
1. Write failing test asserting `Schema::hasColumn('instagram_posts','zernio_post_id')` and `…'zernio_request_id'` are true (also tiktok_posts, threads_posts). Expected error: `Failed asserting that false is true`.
2. Run test, confirm fail.
3. Add migration: nullable `string zernio_post_id` + nullable `string zernio_request_id` (indexed) on `instagram_posts`, `tiktok_posts`, `threads_posts`. Add both to each model's `$fillable`.
4. Run `php artisan migrate` (test DB) + tests, confirm pass.
5. Commit: `feat(zernio): add zernio_post_id + zernio_request_id to sibling tables`

**Verification:**
- [ ] Migration up/down both run clean
- [ ] All three tables have both columns; both in `$fillable`
- [ ] `ZernioColumnsMigrationTest` passes

---

### Phase C — Settings seeder (keys, account ids, selectors)

**Estimated time:** 8 min

**Files:**
- Create: `backend/database/seeders/ZernioSettingsSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php` (register)
- Test: `backend/tests/Feature/ZernioSettingsSeederTest.php`

**Steps:**
1. Write failing test: after seeding, `Setting{group:zernio,key:crosspost_publisher_instagram}->value === 'zernio'` and the 8 keys exist (2 api keys null, 3 account ids null, 3 selectors `zernio`). Expected error: seeder class not found / null value.
2. Run test, confirm fail.
3. Implement idempotent `firstOrCreate` seeder: `zernio_api_key_igtt`, `zernio_api_key_threads` (null), `zernio_{instagram,tiktok,threads}_account_id` (null), `crosspost_publisher_{instagram,tiktok,threads}` (`'zernio'`). Register in `DatabaseSeeder`.
4. Run seeder twice + tests, confirm idempotent + pass.
5. Commit: `feat(zernio): settings seeder (keys, account ids, publisher selectors)`

**Verification:**
- [ ] Re-seeding yields zero new rows (idempotent)
- [ ] Selectors default to `zernio`; api keys/account ids null
- [ ] Test passes

---

### Phase D — ZernioClient (two-key routing + createPost + listAccounts)

**Estimated time:** 14 min

**Files:**
- Create: `backend/app/Services/ZernioClient.php`
- Test: `backend/tests/Unit/ZernioClientTest.php`

**Steps:**
1. Write failing test: `ZernioClient::forPlatform('instagram')` builds a request to `https://zernio.com/api/v1/posts` with header `Authorization: Bearer <decrypted igtt key>`; `forPlatform('threads')` uses the threads key. Use `Http::fake` + `Http::assertSent`. Expected error: `Error: Class "App\Services\ZernioClient" not found`.
2. Run test, confirm fail for that reason.
3. Implement: `forPlatform(string $platform): self` resolves key setting (`zernio_api_key_igtt` for instagram/tiktok, `zernio_api_key_threads` for threads) via `Setting` + `Crypt::decryptString`; `client()` = `Http::withHeaders(['Authorization'=>'Bearer '.$key])->baseUrl(config('…zernio.base_url').config('…zernio.api_path'))->timeout(...)`. Methods: `createPost(array $payload, ?string $requestId=null)` (sets `x-request-id` header when given; returns decoded body), `listAccounts()` (`GET /accounts`), `getPost(string $id)`. Throw a typed `ZernioApiException` on 401/403; return structured result on 409 (`{duplicate:true, existingPostId}`).
4. Run tests, confirm pass.
5. Commit: `feat(zernio): ZernioClient with per-platform key routing`

**Verification:**
- [ ] `php artisan test --filter=ZernioClientTest` passes
- [ ] IG/TikTok → igtt key; Threads → threads key (asserted via `Http::assertSent`)
- [ ] 409 returns duplicate result (not exception); 401/403 throw
- [ ] Security: keys read from encrypted settings, never logged; no secrets in source

---

### Phase E — ZernioPayloadBuilder (per-platform rules)

**Estimated time:** 15 min

**Files:**
- Create: `backend/app/Services/ZernioPayloadBuilder.php`
- Test: `backend/tests/Unit/ZernioPayloadBuilderTest.php`

**Steps:**
1. Write failing test for `buildInstagram($sibling)`: returns `platforms[0].platform==='instagram'`, `accountId` from settings, `mediaItems` = slide image URLs as `{type:'image'}`, and when `hook_video_status==='done'` the FIRST `mediaItems` entry is `{type:'video', url:hook_video_url}`, plus `platformSpecificData.firstComment` = link_comment. Expected error: class not found.
2. Run test, confirm fail.
3. Implement `buildInstagram/buildTiktok/buildThreads(<Model> $sibling): array`:
   - shared `mediaItemsFromSlides($sibling)` reads `LinkedInPost.carousel_slides[].image_url`.
   - **IG**: prepend hook video when present (4:5 — see Phase J); `platformSpecificData.firstComment` = `link_comment` when non-empty (suppressed for repurpose — reuse existing `isRepurpose` check).
   - **TikTok**: images only (drop any video); cap 35.
   - **Threads**: images only (no video), cap 10; **assert/truncate caption ≤500 chars** (guard + log when truncated).
   - all read `accountId` from `Setting{group:zernio,key:zernio_{platform}_account_id}`; throw if empty.
4. Run tests (one per platform + the 500-char guard + the no-video-on-tiktok guard), confirm pass.
5. Commit: `feat(zernio): ZernioPayloadBuilder per-platform payloads`

**Verification:**
- [ ] 4+ tests pass (IG mixed-media + firstComment, TikTok all-image, Threads all-image + 500-cap)
- [ ] Threads payload never contains a video item; caption length ≤500
- [ ] IG hook video is `mediaItems[0]` when status done; absent otherwise
- [ ] No placeholder/TODO

---

### Phase F — PublishViaZernio job

**Estimated time:** 15 min

**Files:**
- Create: `backend/app/Jobs/PublishViaZernio.php`
- Test: `backend/tests/Feature/PublishViaZernioTest.php`

**Steps:**
1. Write failing test: dispatchSync for an `awaiting_review` IG sibling with `Http::fake` 201 `{post:{_id:'z1', platforms:[{platformPostUrl:'https://instagram.com/p/x'}]}}` results in `status=published`, `zernio_post_id='z1'`. Expected error: class not found.
2. Run test, confirm fail.
3. Implement mirroring `PublishViaPubler`: ctor `(string $platform, int $siblingPostId)`, `$tries=3`, `$backoff=[60,300,900]`, `$timeout=600`, `onQueue('social-crosspost')`. `handle(ZernioClient $client, ZernioPayloadBuilder $builder)`:
   - load sibling by platform; **idempotency gate**: skip if `zernio_post_id` already set.
   - generate/persist a stable `zernio_request_id` (UUID) if null → reuse on retry.
   - transition `→ Publishing`; build payload; set `publishNow:true` OR `scheduledFor`+`timezone` when `scheduled_at` set and `config('…zernio.schedule_enabled')`.
   - `createPost($payload, $sibling->zernio_request_id)` → on success store `zernio_post_id` + `external_url`, transition `→ Published`; on 409-duplicate store `existingPostId` as `zernio_post_id` + `→ Published` (idempotent); on 401/403/validation → `→ Failed` + `last_error`; 5xx/network → rethrow for retry.
4. Run tests (published, scheduled, 409-as-published, failed-on-4xx, retry-on-5xx), confirm pass.
5. Commit: `feat(zernio): PublishViaZernio job`

**Verification:**
- [ ] 5 tests pass; `social-crosspost` queue set
- [ ] 409 path lands `published` (not failed); 4xx lands `failed`; 5xx rethrows
- [ ] Retry reuses the same `x-request-id` (asserted)
- [ ] No placeholder/TODO

---

### Phase G — PublisherResolver + swap the single dispatch site

**Estimated time:** 10 min

**Files:**
- Create: `backend/app/Support/PublisherResolver.php`
- Modify: `backend/app/Services/BaseSocialGenerationService.php` (line ~324)
- Test: `backend/tests/Unit/PublisherResolverTest.php`, `backend/tests/Feature/CrossPostDispatchRoutingTest.php`

**Steps:**
1. Write failing test: `PublisherResolver::for('instagram') === 'zernio'` by default; returns `'publer'` when setting `crosspost_publisher_instagram='publer'`; unknown platform → `'publer'`. Expected error: class not found.
2. Run test, confirm fail.
3. Implement `PublisherResolver::for(string $platform): string` reading `Setting{group:zernio,key:crosspost_publisher_{platform}}` (default `zernio` for ig/tiktok/threads, `publer` otherwise). Swap dispatch site to: `match (PublisherResolver::for($platform)) { 'zernio' => PublishViaZernio::dispatch($platform,$draft->id), default => PublishViaPubler::dispatch($platform,$draft->id) };`
4. Write/​run a feature test (`Queue::fake`) asserting IG routes to `PublishViaZernio` by default and to `PublishViaPubler` when selector flipped. Confirm pass.
5. Commit: `feat(zernio): PublisherResolver routing at dispatch site`

**Verification:**
- [ ] Both tests pass
- [ ] Default routes IG/TikTok/Threads → Zernio; flip → Publer (asserted via `Queue::assertPushed`)
- [ ] Publer job/class untouched (diff shows only the dispatch `match` + new files)

---

### Phase H — Settings API endpoints (GET / PUT / verify)

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/*/SettingsController.php` (mirror Publer methods)
- Modify: `backend/routes/api.php` (admin settings group)
- Test: `backend/tests/Feature/ZernioSettingsApiTest.php`

**Steps:**
1. Write failing test: `GET /api/admin/settings/zernio` (auth:sanctum) returns selectors + account ids + masked keys (`***SET***` when set, `*_configured` bools), never the raw key. Expected error: 404 route not found.
2. Run test, confirm fail.
3. Implement `getZernioSettings` (mask keys), `updateZernioSettings` (encrypt keys via `Crypt::encryptString`, **preserve on empty submit**, validate selector ∈ {zernio,publer}, account ids string), `verifyZernioConnection` (calls `ZernioClient::forPlatform()` per key → `listAccounts()`, returns `{ok, accounts:[{platform,_id,username}]}` so operator can copy account ids). Register 3 routes under the admin settings group.
4. Run tests (get-masked, put-encrypts, put-preserves-on-empty, verify-returns-accounts via `Http::fake`), confirm pass.
5. Commit: `feat(zernio): admin settings endpoints (get/put/verify)`

**Verification:**
- [ ] 4 tests pass
- [ ] Raw key never returned; empty submit preserves stored key
- [ ] Security: `auth:sanctum` enforced, keys encrypted at rest + masked in API, selector input validated, no secrets in logs

---

### Phase I — Admin settings card (Vue)

**Estimated time:** 14 min

**Files:**
- Modify: `frontend/src/views/admin/AboutSettings.vue` (add "Zernio Publishing" card, mirror Publer/Postiz card)
- Modify: relevant composable/settings fetch (mirror existing pattern)

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| I | Zernio settings card (2 key inputs, 3 account-id inputs, 3 publisher selects, Verify button) | n/a — reuses existing AboutSettings card layout/tokens (no new design language) | Renders, saves, masks, verify lists accounts |

**Steps:**
1. Add a "Zernio Publishing" card below the Publer/Postiz card: 2 password inputs (`***SET***` placeholder when configured), 3 account-id text inputs, 3 `<select>` publisher dropdowns (Zernio/Publer), a "Verify connection" button per workspace key, and a Save button (own submit handler, mirror `handleBrandSubmit`).
2. Wire fetch (`GET /admin/settings/zernio`) on mount + PUT on save + POST verify (renders returned accounts so operator copies the `accountId`).
3. Manual check: load `/admin/settings`, card renders, save round-trips (key stays masked), verify lists accounts.
4. Commit: `feat(zernio): admin settings card on AboutSettings.vue`

**Verification:**
- [ ] Card renders with 2 keys + 3 account ids + 3 selectors + verify buttons
- [ ] Save preserves masked key on empty; selectors persist
- [ ] Verify button surfaces account list from the live API
- [ ] No hardcoded keys in the Vue source

---

### Phase J — Known-unknowns verification (live, gated)

**Estimated time:** 12 min — **requires rotated keys configured + a real test draft**

**Steps (verification, not TDD — these de-risk the 3 open unknowns):**
1. **GROK hook video 4:5**: inspect a real `InstagramPost.hook_video_url` clip dimensions. If 9:16 (not 4:5), the IG carousel first-item ratio rule will distort/reject — decide: transcode hook to 4:5 OR keep hook image-only for IG carousel. Record finding in this doc.
2. **accountId shape**: call the Verify button (Phase I) against the live workspaces; confirm the `_id` returned by `listAccounts()` is exactly what `createPost.platforms[].accountId` expects (paste into settings). Record the format.
3. **409 on legit re-publish**: publish a test IG draft, then trigger a slide re-render + re-publish; confirm the `x-request-id` stability + caption variance avoids a false-positive 409. If 409 still trips a legitimate re-publish, add a caption-cache-buster or rotate `zernio_request_id` on intentional re-publish.

**Verification:**
- [ ] Hook video ratio finding recorded + IG hook strategy decided
- [ ] accountId format confirmed against live `listAccounts()`
- [ ] Re-publish path verified not to false-409; mitigation applied if needed
- [ ] One real test post lands on IG (mixed carousel), TikTok (photo), Threads (≤500) via Zernio

---

### Phase dependency / parallelism

- **Sequential core**: A → B → C → (D, E in parallel) → F → G.
- **Parallel-eligible**: D and E (independent classes); H depends on D (verify uses client); I depends on H.
- **J last** (needs everything + live keys).
- Publer path is never modified except the one `match` in Phase G — regression surface is minimal.

### Self-check (red flags) — all clear

- ✅ Data Integration Map present · ✅ every phase has TDD step-1 + Verification · ✅ references real files from CLAUDE.md/exploration · ✅ data sources specified (exact settings keys, columns, dispatch line) · ✅ no placeholder language · ✅ phases ≤15 min · ✅ security lines on Phases D/H · ✅ no LLM/non-deterministic phase (publishing is deterministic — no eval doc needed).
