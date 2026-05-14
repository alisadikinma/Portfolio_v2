# GeminiGen Relay Webhook — Shared Portfolio Endpoint for `geminigen-api-client` Plugin

**Date:** 2026-05-14
**Status:** Design approved, implementation pending
**Author:** Ali Sadikin (brainstormed via `/gaspol-brainstorm`)
**Touches:** Portfolio_v2 backend (`api.php` + 1 controller + 1 service + token category extension), plus separate plugin `D:\Projects\claude-plugin\geminigen-api-client`

---

## Design

### 1. Problem

A new Claude Code plugin `geminigen-api-client` is being built so that other plugins/skills (`linkedin-carousel`, `video-image`, future image/video generators) can call GeminiGen.ai through ONE shared interface instead of each reimplementing HTTP wrapping, signature verification, retry, asset polling.

GeminiGen.ai is fundamentally **async with webhook callback** (per [docs.geminigen.ai/getting-started/webhooks](https://docs.geminigen.ai/getting-started/webhooks)). Caller skills run in a Claude Code session — they have NO public HTTPS endpoint. Webhook callback MUST land somewhere with a public URL. Portfolio_v2 (`https://alisadikinma.com`) is the only piece of infrastructure with a stable public HTTPS endpoint in this ecosystem.

Additional constraint surfaced mid-brainstorm: **the new plugin uses a separate GeminiGen account** (API key `geminiai-6c98ca9915736c70b4096bde15261219`), distinct from the existing key that powers `BlogPipelineController::imageWebhook` + `LinkedInCarouselImageWebhookController`. Mixing accounts in one API key slot would corrupt billing/quota attribution and break existing flows.

### 2. Existing webhook footprint (don't touch)

Two existing GeminiGen webhook endpoints in Portfolio_v2, **use-case-specific**:

| Endpoint | Controller | Account | Mirror target |
|---|---|---|---|
| `POST /api/automation/blog/image-webhook` | `BlogPipelineController::imageWebhook` | Original | `content_ideas.image_prompts[]` |
| `POST /api/automation/linkedin/carousel-image-webhook` | `LinkedInCarouselImageWebhookController` | Original | `linkedin_posts.carousel_slides[]` |

Both:
- Public routes, no auth
- Mirror asset into Portfolio storage (`storage/app/public/`)
- **Do NOT verify `x-signature` RSA** (security gap inherited — out of scope for this design)
- Tied to specific DB models (not reusable for new callers)

**Design decision: leave these untouched.** The new relay coexists at a new path. Existing endpoints continue to serve blog + LinkedIn carousel flows with the original account.

### 3. Architecture decisions log

Six decision points were traversed in the brainstorm. Final choices in **bold**.

| # | Question | Options considered | Chosen |
|---|---|---|---|
| 1 | Webhook arch | Generic + caller registry / Per-caller endpoints / Generic + extend `image_generation_jobs` | **Generic + caller registry** (later softened to pure stateless relay — see #4) |
| 2 | Caller auth | Sanctum token + caller_id / Shared secret in settings / No auth | **Sanctum token** (reuses `/admin/automation/tokens` UI per CLAUDE.md May 6 generalization) |
| 3 | Who calls GeminiGen | Plugin direct / Portfolio proxy / Hybrid register-then-call | **Plugin owns API key, Portfolio webhook-only** — Portfolio never holds the new account's API key. Keeps blast radius small if Portfolio is compromised; keeps Portfolio scope minimal. |
| 4 | Callback routing | Stateless URL-encoded callback / Stateful pre-register / Hybrid with audit log | **Stateless — encode callback URL in webhook_url query** (`?cb={base64url}&token={sanctum_token}`). No DB writes, no registry table, pure relay. |
| 5 | Asset handling | Mirror to Portfolio storage / Forward as-is / Hybrid opt-in mirror | **Forward as-is** — caller responsible for downloading from GeminiGen R2 signed URL immediately. No storage footprint at Portfolio. Trade-off: caller queue worker must be responsive (existing portfolio-queue.service systemd unit already is). |
| 6 | Secret scope | Single global HMAC / Per-caller secret in settings / Single + nonce | **Reuse existing Sanctum token system at `/admin/automation/tokens`** — add new token category `geminigen_relay` with ability `geminigen:relay`. Operator mints per-deployment token via existing admin UI. Zero new secret-management surface. |

### 4. Final architecture

```
┌───────────────────────────┐
│  Caller skill             │
│  (linkedin-carousel-v2,   │
│   video-image-gen, etc.)  │
└──────────────┬────────────┘
               │ invokes /geminigen-generate-image
               │   --prompt "..."
               │   --callback https://caller/cb
               ▼
┌───────────────────────────┐
│  Plugin                   │
│  geminigen-api-client     │       ┌──────────────────────────┐
│                           │──────▶│  GeminiGen.ai            │
│  - holds API key          │ POST  │  /v1/generate_image      │
│  - constructs webhook_url:│       │                          │
│    /api/automation/       │       │  webhook_url stored      │
│    geminigen/webhook      │       │  for callback            │
│      ?cb={b64url}         │       └─────────────┬────────────┘
│      &token={sanctum}     │                     │ POST webhook
└───────────────────────────┘                     │ + x-signature RSA
                                                  ▼
                              ┌───────────────────────────────────┐
                              │  Portfolio_v2 (alisadikinma.com)  │
                              │                                   │
                              │  POST /api/automation/            │
                              │       geminigen/webhook           │
                              │                                   │
                              │  1. auth.sanctum + ability        │
                              │       geminigen:relay             │
                              │  2. RSA verify x-signature        │
                              │       against public key          │
                              │  3. base64-decode cb param        │
                              │  4. HTTP POST decoded URL         │
                              │       with payload as-is          │
                              │       + X-Portfolio-Relay-Token   │
                              │       header                      │
                              │  5. return 200 to GeminiGen       │
                              └─────────────┬─────────────────────┘
                                            │ POST decoded cb URL
                                            ▼
                              ┌───────────────────────────────────┐
                              │  Caller's webhook endpoint        │
                              │                                   │
                              │  1. verify X-Portfolio-Relay-Token│
                              │       matches expected            │
                              │  2. download asset from GeminiGen │
                              │       R2 URL IMMEDIATELY          │
                              │       (signed URL expires fast)   │
                              │  3. process                       │
                              └───────────────────────────────────┘
```

### 5. Portfolio_v2 surface (new code)

| File | Type | LoC est. | Purpose |
|---|---|---|---|
| `app/Http/Controllers/Api/GeminiGenRelayController.php` | Controller (single-action `__invoke`) | ~80 | Verify, decode, forward, log |
| `app/Services/GeminiGenSignatureVerifier.php` | Service | ~40 | Wrap `openssl_verify()` with public key from env path |
| `routes/api.php` | Edit (1 line) | — | `Route::post('automation/geminigen/webhook', ...)` under `auth:sanctum` + `ability:geminigen:relay` |
| `config/services.php` (new section) OR `config/geminigen-relay.php` | Config | ~15 | `public_key_path`, `forward_timeout` (default 15s), `retry_max` (default 2), `forward_user_agent` |
| `app/Http/Controllers/Api/TokenController.php` | Edit | +5 lines | Add 3rd row to `CATEGORIES` constant: `geminigen_relay` prefix `geminigen-`, abilities `['geminigen:relay']` |
| `tests/Feature/GeminiGenRelayWebhookTest.php` | Test | ~150 | Token auth gate, RSA verify positive/negative, cb decode, forward POST, malformed payload rejection, forward failure handling |

**Zero migrations. Zero new models. Zero admin UI changes** — token UI is already generic per CLAUDE.md May 6 entry ("Token Generalization for category-based surfaces").

### 6. Plugin `geminigen-api-client` surface

Separate repo at `D:\Projects\claude-plugin\geminigen-api-client`. Out of scope for this design doc beyond the contract it MUST honor:

**Plugin responsibilities:**
- Hold GeminiGen API key (`geminiai-6c98ca9915736c70b4096bde15261219`) in plugin config (NOT Portfolio env)
- Hold Sanctum relay token (minted via Portfolio `/admin/automation/tokens` UI) in plugin config
- Expose slash commands: at minimum `/geminigen-generate-image`, ideally `/geminigen-generate-video` (Phase 2)
- Construct `webhook_url` with `cb` (base64url-encoded caller callback) + `token` (Sanctum bearer) query params
- POST to GeminiGen API with appropriate multipart payload
- Return job UUID to caller; caller polls its own callback endpoint

**Plugin does NOT:**
- Receive webhooks itself (no public endpoint)
- Verify GeminiGen signature (Portfolio does)
- Store asset (caller does)
- Maintain job state (caller does)

### 7. Security model

**Three trust boundaries:**

1. **GeminiGen → Portfolio**
   - Inbound auth: Sanctum bearer token in `?token=` query (ability `geminigen:relay`)
   - Payload integrity: RSA `x-signature` header verified against per-account public key
   - Both must pass — token alone is not sufficient (anyone with leaked token could spoof callbacks); signature alone is not sufficient (anyone could call the endpoint and DOS Portfolio's RSA-verify CPU cost)

2. **Portfolio → caller**
   - Forward includes `X-Portfolio-Relay-Token` header echoing the inbound token (or its prefix — TBD in implementation)
   - Caller compares against its expected value (caller knows the token because it's stored in caller's config alongside the plugin)
   - Trust model: caller skills are user's own infrastructure; shared-token between Portfolio + caller is acceptable for personal portfolio project blast radius

3. **Token in URL query — known trade-off**
   - Sanctum bearer in `?token=` leaks to nginx access logs (VPS-private, HTTPS-encrypted in transit)
   - Accepted for v1 because: (a) HTTPS protects wire, (b) VPS access logs are private, (c) operator can rotate token via `/admin/automation/tokens` UI any time
   - Mitigation if escalated: add 2-step pre-register flow that exchanges token for short-lived nonce — adds state, deferred

**Public key storage:**
- `.pem` file uploaded to VPS at operator-chosen path
- Path in `.env`: `GEMINIGEN_RELAY_PUBLIC_KEY_PATH=/home/claudesn/geminigen-relay-public.pem`
- File mode 0640, owner claudesn:www-data (both PHP-FPM and queue worker can read)
- Plugin config holds path to SAME public key locally (plugin doesn't verify webhooks but may want it for local testing)

### 8. Data Integration Map

| Concern | Source | Existing? | Notes |
|---|---|---|---|
| Inbound token validation | `personal_access_tokens` table (Sanctum) | ✅ — Live since CLAUDE.md May 6 generalization | Add ability `geminigen:relay`, prefix `geminigen-` |
| Token category UI | `/admin/automation/tokens` Vue view | ✅ — Already supports category dropdown | Add 3rd category to backend `CATEGORIES` constant, frontend auto-populates from `/api/admin/automation/categories` |
| RSA public key | File on VPS at `GEMINIGEN_RELAY_PUBLIC_KEY_PATH` | ❌ — One-time operator upload | Downloaded from GeminiGen account settings page (per docs PDF p.1) |
| Webhook payload shape | GeminiGen docs PDF — `{event, uuid, data}` | ✅ — Existing controllers prove parsing works | Reuse 1:1 |
| Forward HTTP client | `Illuminate\Support\Facades\Http` | ✅ — Laravel core | `Http::timeout(15)->retry(2, 1000)->post($cb, $payload)` |
| Logging | `Log::channel('single')` | ✅ — Laravel core | Log inbound (token name + caller IP + event) + forward result (status code + duration); NEVER log full payload (may contain prompts with proprietary data) |
| Test fixtures | `tests/Feature/` + factory | ✅ — Existing pattern | RSA keypair generated in test setup; no real GeminiGen call needed |

### 9. Risks accepted

| Risk | Mitigation | Severity |
|---|---|---|
| Token in URL leaks to logs | HTTPS + private VPS logs + rotatable | Low |
| Caller endpoint down → webhook drops | Portfolio retries forward 2x with 1s backoff (`Http::retry(2, 1000)`); if still fails, log + 200 OK back to GeminiGen so it doesn't retry-storm us. Caller loses that callback. Acceptable for personal project; not acceptable for SaaS-grade — would need durable queue. | Medium |
| GeminiGen signed URL expires before caller downloads | Caller MUST download synchronously in webhook handler before queue dispatch — pattern documented in plugin SKILL.md. | Medium |
| Public key file missing on VPS at deploy time | Signature verification will fail → 500 to GeminiGen → GeminiGen retries 3x at 1h intervals (per docs) → eventually drops. Operator must upload key BEFORE first plugin use. Document in setup runbook. | Low |
| Token compromised | Operator deletes token via `/admin/automation/tokens` → all subsequent webhook calls return 401 → existing in-flight jobs lost. Mint new token, update plugin config, redeploy plugin. | Low |
| Replay attack — attacker re-sends a captured valid webhook | NOT mitigated in v1. GeminiGen `uuid` field could be tracked in a tiny `geminigen_seen_uuids` table with TTL cleanup; deferred unless attack pattern observed. | Low (target is personal project, attack surface is private) |

### 10. Open questions / deferred

| Question | Resolution path |
|---|---|
| Should the `X-Portfolio-Relay-Token` echo the full token or just its prefix? | Resolve during implementation. Prefix is safer (less leakage); full token is simpler. Likely prefix + HMAC of payload signed with token's hashed value. |
| Caller endpoint contract — required fields, error response shape | Documented in plugin SKILL.md, not Portfolio's concern. |
| Video generation events (VIDEO_GENERATION_COMPLETED/FAILED) | Same code path as image events — `event` field discriminator. No special handling needed at relay level. |
| Rate limit on `/webhook` endpoint | Use existing Laravel `throttle:60,1` middleware if abuse pattern emerges. Not needed v1. |
| Plugin distribution — npm, git submodule, manual copy | Out of scope. Plugin repo decides. |

### 11. Acceptance criteria

The relay is considered shipped when:

1. ✅ Operator can mint a `geminigen_relay`-category token at `/admin/automation/tokens` via existing UI (only backend `CATEGORIES` change, frontend auto-discovers).
2. ✅ A test webhook POSTed to `/api/automation/geminigen/webhook` with valid token + valid RSA signature + decodable `cb` param + reachable caller endpoint → forwards payload to caller within 15s; caller endpoint receives identical body + `X-Portfolio-Relay-Token` header; relay logs the round-trip duration.
3. ✅ Invalid token → 401, no RSA verify attempted (cheap path first).
4. ✅ Invalid signature → 403, no forward attempted.
5. ✅ Unreachable caller URL → 2 retries 1s apart → log error → 200 OK back to GeminiGen (don't trigger GeminiGen's 3x retry).
6. ✅ Existing blog + linkedin carousel webhooks continue to function unchanged (regression test).
7. ✅ Operator runbook in `docs/runbooks/geminigen-relay-setup.md` covers: public key upload path, token mint flow, plugin config wiring, smoke test command.

### 12. Out of scope

- Plugin implementation (`D:\Projects\claude-plugin\geminigen-api-client`)
- Caller skill implementations (`/geminigen-generate-image` consumers — future LinkedIn carousel v2, video pipeline, etc.)
- Migration of existing blog + linkedin carousel endpoints to use the relay (they continue using their own account; can migrate later if desired)
- Multi-tenant relay supporting multiple GeminiGen accounts (single new account suffices for v1; extending would require per-token public key mapping)
- Webhook replay protection via UUID dedup table (deferred unless attacked)
- Async forward via queue job (synchronous forward suffices; GeminiGen's own 200-OK semantics tolerate up to ~30s response time)

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship the `geminigen-api-client` plugin's webhook receiver as a stateless relay in Portfolio_v2 with zero new migrations, zero new models, and zero admin-UI changes — leveraging the existing Sanctum token UI at `/admin/automation/tokens` for credential management.

### Architecture Context (from CLAUDE.md + Design Section)

- **Sanctum token infra** generalized per May 6, 2026 entry. `TokenController::CATEGORIES` constant declares prefix + abilities per category; frontend AutomationTokens.vue + Pinia store auto-discover categories via `GET /api/admin/automation/categories`. Adding a new category = 1 backend constant row.
- **Existing GeminiGen webhook controllers** (`BlogPipelineController::imageWebhook`, `LinkedInCarouselImageWebhookController`) prove the `{event, uuid, data}` payload parsing pattern works. Reuse the shape, do NOT touch those controllers.
- **HTTPS forward client**: Laravel `Illuminate\Support\Facades\Http` with `->timeout()->retry()->post()` chain — already standard across the codebase.
- **Single-action controller pattern**: `LinkedInCarouselImageWebhookController` is the closest precedent — single `__invoke()` method, public route, JSON response. Mirror this shape.
- **Config file convention**: project has `config/linkedin.php`, `config/carousel-gen.php`, `config/posting-rules.php`, `config/cv.php`, `config/homepage.php`. Add `config/geminigen-relay.php`.
- **Test infra**: PHPUnit (NOT Pest — project convention per CLAUDE.md), Feature tests at `tests/Feature/`, Unit at `tests/Unit/`. `RefreshDatabase` works on MySQL in CI but blocked on local Windows by stale tablespace (documented known issue) — local syntax check via `php -l` is the floor.

### Tech Stack

- PHP 8.2 + Laravel 12
- Laravel Sanctum 4 for inbound auth (existing)
- `openssl_verify()` PHP core — RSA SHA-256 signature verification (no new dependency)
- `Illuminate\Support\Facades\Http` — outbound forward
- PHPUnit + Mockery — tests

### Data Integration Map (Contract)

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Inbound token validation | `personal_access_tokens` table (Sanctum) | `auth:sanctum` + `ability:geminigen:relay` middleware | Yes | Use existing |
| Token category registry | `TokenController::CATEGORIES` constant | `getCategories()` endpoint | Yes — add 1 row | Edit existing |
| Token UI dropdown | `AutomationTokens.vue` + `useAutomation` Pinia store | `GET /api/admin/automation/categories` | Yes — auto-discovers | No frontend change |
| RSA public key | File on VPS at env-configured path | `openssl_pkey_get_public()` + `openssl_verify()` | No — operator uploads .pem | Create config + service |
| Webhook payload parsing | `{event, uuid, data}` shape per GeminiGen docs | Existing precedent in `BlogPipelineController` | Yes — pattern proven | Copy shape, fresh parse |
| Forward HTTP client | `Http::timeout()->retry()->post()` | Laravel core facade | Yes | Use existing |
| Caller callback URL | Decoded from `?cb={base64url}` query param | `base64_decode(strtr($cb, '-_', '+/'))` | n/a — pure decode | Inline in controller |
| Forward header | `X-Portfolio-Relay-Token` echoes token prefix (first 8 chars) | Inline string slice | n/a | Inline in controller |
| Logging | `Log::channel('single')` | Laravel core | Yes | Use existing |
| Test fixtures | RSA keypair generated in test `setUp()` | `openssl_pkey_new()` core | Yes | Inline in test |

### Phases

#### Phase A — Add `geminigen_relay` token category

**Estimated time:** 8 minutes

**Files:**
- Test: `backend/tests/Feature/TokenCategoryGeminiGenTest.php` (new)
- Modify: `backend/app/Http/Controllers/Api/TokenController.php` (add 1 row to `CATEGORIES`)

**Steps:**
1. Write failing test for `geminigen_relay` category exposure via `GET /api/admin/automation/categories`. Expected error: `Failed asserting that array contains 'geminigen_relay'` (category doesn't exist yet).
2. Run test, confirm it fails for the expected reason.
3. Add 3rd entry to `TokenController::CATEGORIES` constant:
   ```php
   'geminigen_relay' => [
       'slug' => 'geminigen_relay',
       'prefix' => 'geminigen-',
       'label' => 'GeminiGen Relay',
       'abilities' => ['geminigen:relay'],
       'description' => 'Webhook callbacks from GeminiGen.ai (used by geminigen-api-client plugin).',
   ],
   ```
4. Add test cases: (a) minting token with `category=geminigen_relay` succeeds, name auto-prefixed `geminigen-*`, (b) cross-category ability rejection (e.g. `post:write` on geminigen_relay → 422), (c) `?category=geminigen_relay` filter returns only those tokens.
5. Run tests, confirm all pass.
6. Commit: `feat(token-category): add geminigen_relay for webhook plugin`.

**Verification:**
- [ ] `php -l app/Http/Controllers/Api/TokenController.php` clean
- [ ] All 3 new test cases pass on CI (or `php artisan test --filter=TokenCategoryGeminiGen` if local tablespace issue resolved)
- [ ] `php artisan tinker --execute='dd(\App\Http\Controllers\Api\TokenController::CATEGORIES)'` shows 3 entries
- [ ] No placeholder/TODO comments in new code
- [ ] Frontend `/admin/automation/tokens` Create modal dropdown auto-shows "GeminiGen Relay" option (verified manually post-deploy)

---

#### Phase B — RSA signature verifier service

**Estimated time:** 10 minutes

**Files:**
- Test: `backend/tests/Unit/GeminiGenSignatureVerifierTest.php` (new)
- Create: `backend/app/Services/GeminiGenSignatureVerifier.php`

**Steps:**
1. Write failing test for `GeminiGenSignatureVerifier::verify($body, $signatureHex, $publicKeyPath)` — generate RSA keypair in `setUp()`, sign known body, assert verify returns true. Expected error: `Class "App\Services\GeminiGenSignatureVerifier" not found`.
2. Run test, confirm it fails for the expected reason.
3. Implement service with single public method `verify(string $body, string $signatureHex, string $publicKeyPath): bool` — reads PEM public key, computes MD5 of body (per GeminiGen docs PDF p.1 — `event_data_hash = md5(data.encode()).digest()`), `openssl_verify($md5Hash, hex2bin($signatureHex), $publicKey, OPENSSL_ALGO_SHA256)`. Returns false on any exception or 0/-1 verify result.
4. Add test cases: (a) valid signature → true, (b) tampered body → false, (c) wrong public key → false, (d) missing key file → false (no exception thrown — logged warning), (e) malformed hex signature → false.
5. Run tests, confirm all 5 pass.
6. Commit: `feat(geminigen-relay): RSA signature verifier service`.

**Verification:**
- [ ] `php -l app/Services/GeminiGenSignatureVerifier.php` clean
- [ ] All 5 unit test cases pass
- [ ] Service handles missing key file gracefully (logs warning, returns false — does not throw)
- [ ] No placeholder/TODO comments

---

#### Phase C — Config file + env wiring

**Estimated time:** 5 minutes

**Files:**
- Test: extend `backend/tests/Unit/GeminiGenSignatureVerifierTest.php` with config-resolution case
- Create: `backend/config/geminigen-relay.php`
- Modify: `backend/.env.example` (add 3 new keys)

**Steps:**
1. Write failing test that asserts `config('geminigen-relay.public_key_path')` returns env-overridden value. Expected error: `Failed asserting that null is not null` (config key missing).
2. Run test, confirm fail.
3. Create `config/geminigen-relay.php`:
   ```php
   return [
       'public_key_path' => env('GEMINIGEN_RELAY_PUBLIC_KEY_PATH'),
       'forward_timeout' => env('GEMINIGEN_RELAY_FORWARD_TIMEOUT', 15),
       'forward_retries' => env('GEMINIGEN_RELAY_FORWARD_RETRIES', 2),
       'forward_retry_delay_ms' => env('GEMINIGEN_RELAY_FORWARD_RETRY_DELAY_MS', 1000),
   ];
   ```
4. Append to `.env.example`:
   ```env
   # GeminiGen Relay (used by geminigen-api-client plugin's webhook callback)
   GEMINIGEN_RELAY_PUBLIC_KEY_PATH=/home/claudesn/geminigen-relay-public.pem
   GEMINIGEN_RELAY_FORWARD_TIMEOUT=15
   GEMINIGEN_RELAY_FORWARD_RETRIES=2
   ```
5. Run `php artisan config:clear && php artisan config:cache`, run test, confirm pass.
6. Commit: `feat(geminigen-relay): config + env scaffolding`.

**Verification:**
- [ ] `php -l config/geminigen-relay.php` clean
- [ ] `php artisan tinker --execute='dd(config("geminigen-relay"))'` shows all 4 keys
- [ ] `.env.example` has the 3 new keys with sensible defaults
- [ ] No placeholder/TODO comments

---

#### Phase D — Relay controller: token + signature gates (no forward yet)

**Estimated time:** 12 minutes

**Files:**
- Test: `backend/tests/Feature/GeminiGenRelayWebhookTest.php` (new)
- Create: `backend/app/Http/Controllers/Api/GeminiGenRelayController.php`
- Modify: `backend/routes/api.php` (add route under `auth:sanctum` + `ability:geminigen:relay`)

**Steps:**
1. Write failing test for `POST /api/automation/geminigen/webhook` returning 401 when token missing. Expected error: `Route [api/automation/geminigen/webhook] not defined` (route not yet registered).
2. Run test, confirm fail.
3. Stub controller `__invoke(Request $request)` returning `response()->json(['ok' => true])`.
4. Register route in `routes/api.php` under existing automation group:
   ```php
   Route::post('automation/geminigen/webhook', GeminiGenRelayController::class)
       ->middleware(['auth:sanctum', 'ability:geminigen:relay']);
   ```
5. Run failing test, confirm 401 returned (Sanctum gate). Move to next case.
6. Add test cases: (a) missing token → 401, (b) valid token but no `?cb=` → 422 with `error.code=MISSING_CB`, (c) valid token + valid `?cb=` + missing `X-Signature` header → 403 with `error.code=MISSING_SIGNATURE`, (d) valid token + `?cb=` + bogus signature → 403 with `error.code=INVALID_SIGNATURE`, (e) `?cb=` with non-decodable base64 → 422 with `error.code=INVALID_CB`.
7. Implement gates in controller using `GeminiGenSignatureVerifier`. Read public key path from `config('geminigen-relay.public_key_path')`. Decode `cb` with `base64_decode(strtr($cb, '-_', '+/'), true)` (strict mode — returns false on garbage).
8. Run tests, confirm all 5 cases pass.
9. Commit: `feat(geminigen-relay): controller scaffolding + auth+sig gates`.

**Verification:**
- [ ] `php -l app/Http/Controllers/Api/GeminiGenRelayController.php` clean
- [ ] `php artisan route:list | grep geminigen` shows the route with `auth:sanctum,ability:geminigen:relay`
- [ ] All 5 test cases pass
- [ ] RSA verify is NOT called when token check fails (cheap path first — verified by spying on `GeminiGenSignatureVerifier`)
- [ ] No placeholder/TODO comments

---

#### Phase E — Relay controller: forward to decoded callback URL

**Estimated time:** 12 minutes

**Files:**
- Modify: `backend/tests/Feature/GeminiGenRelayWebhookTest.php` (add 4 forward cases)
- Modify: `backend/app/Http/Controllers/Api/GeminiGenRelayController.php` (complete `__invoke`)

**Steps:**
1. Write failing test: valid token + valid signature + valid `cb` + reachable caller URL → controller POSTs payload to decoded URL with `X-Portfolio-Relay-Token` header (first 8 chars of original token). Use `Http::fake()` for caller endpoint. Expected error: `Http::assertSent` fails because forward not implemented yet.
2. Run test, confirm fail.
3. Implement forward in `__invoke` after gates pass:
   ```php
   $decodedCb = base64_decode(strtr($cbParam, '-_', '+/'), true);
   $tokenPrefix = substr($request->bearerToken() ?? '', 0, 8);
   $response = Http::timeout(config('geminigen-relay.forward_timeout'))
       ->retry(
           config('geminigen-relay.forward_retries'),
           config('geminigen-relay.forward_retry_delay_ms'),
           throw: false
       )
       ->withHeaders(['X-Portfolio-Relay-Token' => $tokenPrefix])
       ->post($decodedCb, $request->json()->all());
   Log::info('[GeminiGenRelay] forwarded', [
       'event' => $request->input('event'),
       'uuid' => $request->input('uuid'),
       'cb_host' => parse_url($decodedCb, PHP_URL_HOST),
       'status' => $response->status(),
       'duration_ms' => /* ... */,
   ]);
   return response()->json(['relayed' => true, 'forward_status' => $response->status()]);
   ```
4. Add 3 more test cases: (a) caller responds 2xx → relay returns 200 + `relayed=true`, (b) caller responds 5xx after 2 retries → relay STILL returns 200 (don't trigger GeminiGen's own retry storm), logs error, (c) caller times out → relay returns 200, logs error with `forward_status=null`. Each verified via `Http::fake()` + `Log::spy()`.
5. Run tests, confirm 4 forward cases pass + all 5 from Phase D still pass (9 total).
6. Commit: `feat(geminigen-relay): forward decoded callback with token-prefix header`.

**Verification:**
- [ ] `php -l app/Http/Controllers/Api/GeminiGenRelayController.php` clean
- [ ] All 9 feature test cases pass
- [ ] Forward header `X-Portfolio-Relay-Token` is the first 8 chars of the inbound token (NOT the full token — leak mitigation per design §7)
- [ ] Relay returns 200 OK to GeminiGen even when caller is down (prevents GeminiGen 3x-1h retry storm)
- [ ] Log entry contains `event`, `uuid`, `cb_host` (NOT full URL — log sanitation), `status`, `duration_ms`
- [ ] No placeholder/TODO comments

---

#### Phase F — Regression check: existing webhooks still work

**Estimated time:** 5 minutes

**Files:**
- Read-only: `backend/tests/Feature/` (any existing tests for blog/linkedin carousel webhooks)

**Steps:**
1. Run full test suite for existing webhook controllers: `php artisan test --filter=ImageWebhook` + `--filter=LinkedInCarouselImage`.
2. Confirm green. If any new test fails, investigate — Phase A-E changes should be ADDITIVE only.
3. Manually inspect `routes/api.php` diff: only ONE new line added under automation group; no existing routes modified.
4. Inspect `TokenController.php` diff: only the `CATEGORIES` constant has a new row; no existing methods touched.
5. Commit if any drift discovered (should be zero — Phase F is pure verification, no expected changes).

**Verification:**
- [ ] Existing webhook tests still green
- [ ] Routes file diff is +1 line only (the new route)
- [ ] TokenController diff is +6-8 lines (new CATEGORIES entry only)
- [ ] No model changes
- [ ] No migration files added

---

#### Phase G — Operator runbook + CLAUDE.md update

**Estimated time:** 10 minutes

**Files:**
- Create: `docs/runbooks/geminigen-relay-setup.md`
- Modify: `CLAUDE.md` (Last Updated line + new entry in API Routes section)

**Steps:**
1. Write `docs/runbooks/geminigen-relay-setup.md` covering:
   - Section 1: Generate GeminiGen account API key + download webhook public key `.pem` from account settings (with URL reference)
   - Section 2: Upload `.pem` to VPS: `scp geminigen-relay-public.pem claudesn@vps:/home/claudesn/` → `chmod 640` → `chown claudesn:www-data`
   - Section 3: Set `.env` keys (`GEMINIGEN_RELAY_PUBLIC_KEY_PATH=...`), run `php artisan config:cache && systemctl restart portfolio-queue.service`
   - Section 4: Mint relay token: navigate to `/admin/automation/tokens`, click Create, select "GeminiGen Relay" category, name `geminigen-prod`, abilities `geminigen:relay`, copy plain-text token (cached only in current session per CLAUDE.md May 6 token system)
   - Section 5: Paste token + API key into plugin config (`D:\Projects\claude-plugin\geminigen-api-client\config.json` or equivalent — plugin-side decision)
   - Section 6: Smoke test command: `curl -X POST 'https://alisadikinma.com/api/automation/geminigen/webhook?cb=aHR0cHM6Ly9leGFtcGxlLmNvbS9jYg==&token=<paste>' -H 'X-Signature: deadbeef' -H 'Content-Type: application/json' -d '{"event":"IMAGE_GENERATION_COMPLETED","uuid":"test","data":{}}'` — expect 403 (bad signature) confirming auth gate works
   - Section 7: Rollback — delete token via `/admin/automation/tokens`; all subsequent webhooks return 401
2. Update `CLAUDE.md`:
   - Add to "Automation Routes" section: `POST /api/automation/geminigen/webhook (auth:sanctum + ability:geminigen:relay — relay for geminigen-api-client plugin)`
   - Add new bullet to "Last Updated" line summarizing this ship
3. Commit: `docs(geminigen-relay): operator runbook + CLAUDE.md sync`.

**Verification:**
- [ ] Runbook covers all 7 sections, operator can follow without asking
- [ ] CLAUDE.md "Automation Routes" section lists the new endpoint
- [ ] CLAUDE.md "Last Updated" line includes this entry
- [ ] Smoke test command in runbook executes (returns 403 as expected with bad signature, proving the endpoint is wired)

---

### Total Estimated Time

| Phase | Time |
|---|---|
| A — Token category | 8 min |
| B — Signature verifier | 10 min |
| C — Config + env | 5 min |
| D — Controller gates | 12 min |
| E — Controller forward | 12 min |
| F — Regression check | 5 min |
| G — Runbook + CLAUDE.md | 10 min |
| **Total** | **~62 min** (≈1 hour, single focused session) |

### Parallel Execution Candidates

Most phases are sequential (each builds on prior). The only parallel-safe split:

- **Phase A** (token category) and **Phase B** (signature verifier) — independent files, no shared state. Can run via `gaspol-parallel plan-phases mode` if desired. Saves ~10 min.

All other phases have linear dependencies (C → D → E → F → G).

### Execution Handoff

**Option 1 — Execute in this session**
Run `/gaspol-execute` from this file. Per-phase checkpoints between A→G.

**Option 2 — Parallel A+B then sequential**
Run `/gaspol-parallel mode=plan-phases` to dispatch A and B as independent subagent tasks, then continue C→G sequentially.

**Option 3 — Separate session**
Plan is complete and self-contained at this file path. Resume any time with `/gaspol-execute`.

### Red Flag Self-Check ✓

- [x] Data Integration Map present (10 rows)
- [x] Every phase has TDD step 1 in mandatory format ("Write failing test for X. Expected error: Y")
- [x] Every phase has Verification block
- [x] CLAUDE.md referenced for token system, controller pattern, config convention, test infra
- [x] No vague data sources — every contract names the specific class/facade/middleware
- [x] No phase exceeds 15 minutes estimated
- [x] No placeholder language ("TODO: wire later" — absent throughout)

