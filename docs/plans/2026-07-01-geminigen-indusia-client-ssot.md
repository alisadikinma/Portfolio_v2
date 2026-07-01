# GeminiGen → indusia client as single source of truth

**Date:** 2026-07-01
**Type:** Backend / infra refactor (media generation)
**Status:** Design approved — ready for gaspol-plan

## Design

### Problem

The Portfolio backend has a hand-rolled, **drifted** copy of the GeminiGen wire
protocol spread across three services, each POSTing to the **pre-rebrand domain**
`https://api.geminigen.ai/uapi/v1` (hardcoded as a private `$baseUrl` string):

- `ImageGenerationService.php:25,165` — blog article images
- `LinkedInCarouselImageService.php:44,496` — carousel slides
- `GeminiGenVideoService.php:38,130,225,295,374` — GROK hook + Veo rebrand

The `indusiagen-api-client` repo (the `indusia-image-gen` / `indusia-video-gen`
MCP servers + `/geminigen-image` `/geminigen-video` CLIs, sharing one Python core
`scripts/geminigen_client.py`) targets the **current** host
`https://api.snapgen.ai/uapi/v1` and carries bugfixes the PHP copies lack:

- per-model endpoint + field routing via `MODEL_SPECS` / `VIDEO_SPECS` registry
- local-file-vs-`https`-URL ref routing (sending a local file under the URL field
  is an HTTP 400 — the ref bug)
- `extract_media_url` (nano-banana result-URL location fix)
- GROK PNG-alpha → flattened JPG padding
- History-API endpoint auto-discovery, `status==2` done / `status==3` fail

**Goal (locked with user): single source of truth.** Stop maintaining two GeminiGen
clients. The Python client owns the wire protocol; the backend owns everything else.

### Non-goals

- Not a vendor switch — same GeminiGen/snapgen vendor + same `GEMINIGEN_API_KEY`.
- Not moving generation into MCP-in-Claude-session (PHP can't call MCP; the pipeline's
  per-image `ImageGenerationJob` tracking / FSM / circuit breaker must stay).
- Not touching prompt authoring (`CoverBrandingEnhancer`, `CarouselSlideEnhancer`,
  `VideoHookSceneAuthor`, all `/carousel-gen` plugin logic) — those produce the values
  the bridge forwards, unchanged.

### Why SSOT — one core, everything else inherits

The wire protocol (base URL, per-model endpoint + field routing, ref local-vs-URL
routing, `extract_media_url`, status codes, History-API discovery) must live in **one
file, one repo**: `geminigen_client.py` in `indusiagen-api-client`. The MCP servers and
the CLIs are **thin wrappers** that `import` that core — they hold no protocol of their
own. After this project's cutover the PHP backend also holds no protocol (it only shells
the CLI + parses `uuid:` / `cdn_url:`).

```
BEFORE (drifted — wire protocol in 4 places, why maintenance is doubled):
  geminigen_client.py            (client, current)
  ImageGenerationService.php     ┐
  LinkedInCarouselImageService.php├ PHP copies — drifted, still on dead api.geminigen.ai
  GeminiGenVideoService.php       ┘
  → snapgen API change = edit 4 places.

AFTER (SSOT — wire protocol in 1 place):
                    geminigen_client.py   ← the ONLY place wire protocol lives
                    (MODEL_SPECS / VIDEO_SPECS registry, endpoints, base URL,
                     ref routing, status codes, extract_media_url)
                            │  every consumer imports/wraps this core
        ┌───────────────────┼────────────────────┐
        ▼                   ▼                    ▼
   MCP servers          CLI (geminigen-      PHP backend
   (indusia-image/       image / -video)     (GeminiGenClientBridge:
    -video-gen)                               shell CLI + parse stdout)
   wrapper — inherit    wrapper — inherit    consumer — inherit
   free                 free                 free
```

**Maintenance after cutover — what a future snapgen change actually touches:**

| snapgen changes… | you edit |
|---|---|
| endpoint URL, param name, new required field, base domain, status codes, ref routing | **`geminigen_client.py` only** (1 file, 1 repo) → redeploy venv on VPS. MCP + CLI + PHP inherit, **zero edits** |
| — | MCP server: **never** (unless adding a brand-new tool) |
| — | PHP backend: **not for protocol** — only to *use* a new capability (new model/param), and then just a one-flag pass-through |

The only residual coupling is the CLI→PHP stdout contract (`uuid:` / `cdn_url:`) —
pinned with `--json` if the CLI supports it (verify step 0), so even a CLI output-format
change leaves PHP untouched.

### Architecture — the SSOT split

The backend calls the client's **CLI** form (not the MCP — MCP is for interactive
sessions; both share the same `geminigen_client.py` core, so the SSOT is identical).
Only **two seams per surface** change:

1. **Submit** — replace `Http::asMultipart()->post(...)` with a CLI invocation
   (`--no-wait`, returns a `uuid` immediately, no blocking). Webhook stays OUT:
   per project history (memory: "GeminiGen webhook never fires — poller is the sole
   completion path") the poll crons are the real completion driver, so `--no-wait`
   changes nothing functionally except deleting the dead `webhook_url` field.
2. **Poll-check** — the existing crons stop GETting `geminigen.ai/history/{uuid}`
   directly and instead call `geminigen-image check <uuid>` (or the video CLI's
   equivalent), which returns `pending` / `done + cdn_url` / `failed`.

Everything else stays in PHP: `ImageGenerationJob` rows, per-slide/per-segment status,
FSM transitions, **download + branding + watermark + branded filenames**, and the
`GeminiGenCircuitBreaker`. The CLI only returns a CDN URL; PHP downloads it exactly
as today.

```
SUBMIT (was: Http::asMultipart->post(api.geminigen.ai/...))
  enhancer builds prompt+refs+model+aspect  ->  GeminiGenClientBridge::submit()
    -> exec `geminigen-image --no-wait --model .. --prompt .. --ref ..`  -> uuid
    -> store uuid on ImageGenerationJob / slide / video_slide  (unchanged tracking)

POLL  (was: cron GET api.geminigen.ai/history/{uuid})
  ProcessPendingImages / ReapStuckLinkedInCarouselImages /
  PollHookVideos / PollRebrandAssets
    -> GeminiGenClientBridge::check(uuid) -> {status, cdn_url}
    -> on done: existing PHP downloadAndStore(cdn_url) + branding  (unchanged)
    -> on fail: existing PHP failure/retry/safety-rewrite FSM       (unchanged)
```

### New component — `GeminiGenClientBridge` (single PHP service)

Contract (the ONLY new backend surface, ~1 file + config):

- `submit(string $endpoint, array $fields, array $refs, string $model): string` → `uuid`
  - builds CLI args from values the enhancers already produce
  - **driver**: `local` (direct `Process::run` of the venv python) when the caller
    runs as `claudesn` (all queue-dispatched paths — carousel, video); `ssh` to
    `claudesn@localhost` when the caller is `www-data` (blog-image HTTP-context path).
    Same two-context rule as `ARTICLE_GEN_*` (www-data key) vs `LINKEDIN_GEN_*`
    (claudesn key). `local` avoids the wasted claudesn→claudesn SSH hop.
  - **gpt-image-2 ref handling**: when `model === 'gpt-image-2'`, any `https` ref is
    materialized to a temp local file first (gpt-image-2 rejects URL refs), and the
    CLI is called with `--mode low|medium|high` instead of `--style`, no `--format`.
    Bridge owns this quirk so surfaces stay ignorant of it.
- `check(string $uuid, bool $video = false): array` → `['status' => pending|done|failed, 'cdn_url' => ?string, 'error' => ?string]`
  - if the video CLI lacks a `check` subcommand (verify in step 0), fall back to a
    tiny `python -c "from scripts.geminigen_client import poll,...; ..."` one-liner
    over the same core — still SSOT.
- stdout parse: CLI prints `OK\nlocal:…\ncdn_url:…\nuuid:…` (mirrors the MCP return);
  parse `uuid:` on submit, `cdn_url:` + status on check. `ERROR (…)` line → throw /
  record circuit-breaker failure. Precedent: backend already parses `linkedin-gen` /
  `carousel-gen` stdout.
- every call records success/failure into the existing `GeminiGenCircuitBreaker`.

### Seam map (exact insertion points)

| Surface | Submit seam (replace) | Poll cron (route check through bridge) | Wave |
|---|---|---|---|
| Blog images | `ImageGenerationService:165` (`triggerForIdea`) | `ProcessPendingImages` (`blog:process-images`) | 1 |
| Carousel slides | `LinkedInCarouselImageService:496` (`dispatchOne`) | `ReapStuckLinkedInCarouselImages` | 1 |
| GROK hook | `GeminiGenVideoService:130/374` (`dispatchHookVideo`/`dispatchGrokClip`) | `PollHookVideos` | 2 |
| Veo rebrand | `GeminiGenVideoService:225/295` (`dispatchKeyframe`/`dispatchVeoClip`) | `PollRebrandAssets` | 2 |

### gpt-image-2 for carousel (in scope)

gpt-image-2 = literal instruction-following, strongest typography/infographic → ideal
for the `linkedin_carousel_style='sketchnote'` knowledge-first infographic slides
(text renders accurately vs nano-banana drift). Scope-limited to carousel only (blog
covers stay photorealistic nano-banana-pro; video is image-irrelevant).

- New setting `linkedin_carousel_image_model` (group `linkedin`), default
  `nano-banana-pro` (**zero behavior change until flipped**), opt-in `gpt-image-2`.
  Seeded idempotently in `LinkedInSettingsSeeder`.
- `LinkedInCarouselImageService::dispatchOne` reads it and passes `model` to the bridge.
- Bridge handles gpt-image-2's local-ref-only + `mode`-not-`style` differences (above).
- Endpoint routing (`imagen/gpt-image-2` vs `generate_image`) lives in the Python
  registry — backend just passes the model name.

**Wire params confirmed against official docs (docs.snapgen.ai/resources/3.gpt-image-2, 2026-07-01):**
`POST /imagen/gpt-image-2` multipart — `prompt` (required), `mode` (low/medium/high,
default low), `aspect_ratio` (NOT `aspect` — 1:1/16:9/9:16/4:3/3:4/21:9/3:2/2:3),
`resolution` (1K/2K/4K/8K/10K/12K, default 2K), `files[]` (local `.png/.jpg/.jpeg/.webp`
upload), `ref_history` (uuid of a prior snapgen generation — alt ref mode, no re-upload).
Response: `{uuid, status, generate_result, estimated_credit, media_type}`; `status`
`1`=Processing / `2`=Completed / `3`=Failed; `generate_result` = image URL after done.
Refs for gpt-image-2 are `files` (local) or `ref_history` (uuid) only — **no `file_urls`
URL field**, so the bridge's URL→temp-local materialization is required. Our creator-face
+ brand-logo are uploaded assets → local path; `ref_history` is a future optimization
(out of scope). The Python client already emits `aspect_ratio`; verify in step 0.

### Reconciled against official docs (docs.snapgen.ai, 2026-07-01)

Full API set read from `/Users/alisadikin/Drive-D/my-data/knowledge/*.pdf`
(Image, GPT-Image-2, Veo, Extend-Veo, Grok, Seedance, Kling, Webhooks). Confirms the
client-code grounding; deltas that the bridge/plan must honor:

- **All endpoints uniform:** `POST https://api.snapgen.ai/uapi/v1/<endpoint>` multipart,
  `x-api-key` header; response `{uuid, status, generate_result, status_percentage,
  error_code, error_message}`; `status` `1`=Processing / `2`=Completed / `3`=Failed;
  `generate_result` = CDN URL after completion. `check(uuid)` reads exactly these.
- **Image `/generate_image`** (nano-banana-pro/-2, imagen-4): `prompt`, `model`,
  `aspect_ratio`, `style`, `resolution`, refs via `files` (local) **or `file_urls` (URL)**.
- **gpt-image-2 `/imagen/gpt-image-2`**: refs via `files` (local) or `ref_history` (uuid)
  — **no `file_urls`**, confirming the bridge's URL→local materialization is gpt-image-2-only.
- **Veo `/video-gen/veo`**: `model`, `resolution` (720p/1080p, veo-2=720p), `duration`,
  `aspect_ratio` = **ratio strings** (`16:9`/`9:16`), `ref_images` (local `@` or URL),
  `mode_image` (`frame`/`ingredient`).
- **Grok `/video-gen/grok`**: `model=grok-3`, `resolution`, `duration`, `mode=custom`,
  `aspect_ratio` = **named tokens** (`landscape`/`portrait`/`square`), refs via `files`
  + `file_urls` + `ref_images`(uuid). ⚠️ **Wave-2 wire delta**: GROK aspect ≠ Veo aspect
  (named vs ratio). The Python `VIDEO_SPECS` registry maps this — Wave-2 step 0 verifies
  the CLI emits the correct token before flipping the video flag.
- **Webhooks = account-level, registered in the dashboard "Service Integration settings"**
  — **NOT per-request**. This is exactly why the backend's per-request `webhook_url` field
  was ignored ("0 hits ever" in project memory). Events: `IMAGE_/VIDEO_GENERATION_
  COMPLETED|FAILED`, signed `x-signature` = HMAC-SHA256 (the existing `GeminiGenRelayController`
  already verifies via `geminigen-relay.public_key_path`). Docs explicitly endorse the
  fallback: *"If your system cannot use webhooks, use the get history API."* → **poll stays
  the primary completion driver** (unchanged, matches current working state). Registering the
  account webhook to `/api/automation/geminigen/webhook` is a possible *future* accelerator
  (fires on every completion), out of scope — do not make Wave 1/2 depend on it.
- **Seedance / Kling / Extend-Veo** documented but out of scope (not wired in the surfaces).

### Config — `config/geminigen.php` (new)

Base URL stops being a hardcoded private string in 3 services.

```env
GEMINIGEN_BASE_URL=https://api.snapgen.ai/uapi/v1        # fixes the rebrand drift
GEMINIGEN_CLIENT_DRIVER=local                            # local | ssh (per context)
GEMINIGEN_CLIENT_PATH=/home/claudesn/indusiagen-api-client/.venv/bin/python
GEMINIGEN_CLIENT_REPO=/home/claudesn/indusiagen-api-client
GEMINIGEN_CLIENT_SSH_HOST=localhost
GEMINIGEN_CLIENT_SSH_USER=claudesn
GEMINIGEN_CLIENT_SSH_KEY=/home/claudesn/.ssh/id_ed25519  # queue ctx; www-data path SSHs here
GEMINIGEN_CLIENT_TIMEOUT=60                              # submit is fast; check is fast
GEMINIGEN_USE_INDUSIA_IMAGES=false                       # Wave 1 flag
GEMINIGEN_USE_INDUSIA_VIDEO=false                        # Wave 2 flag
```

`GEMINIGEN_API_KEY` already exists (`config/services.php:39`); the client reads it via
its own 3-tier resolver on the VPS (`$GEMINIGEN_API_KEY` in the client's `.env`).

### Rollout — 2 waves, feature-flagged, old path = fallback

- **Wave 1 (images):** blog + carousel submit + both image poll-crons route through the
  bridge when `GEMINIGEN_USE_INDUSIA_IMAGES=true`. gpt-image-2 carousel knob ships here.
- **Wave 2 (video):** GROK hook + Veo rebrand submit + both video poll-crons when
  `GEMINIGEN_USE_INDUSIA_VIDEO=true`.
- Per surface, flag-off keeps the current PHP `Http::` path verbatim. Flip on after live
  validation; **delete dead PHP wire code only once each surface is green in prod.**

### VPS one-time deploy (operator step — like the refs-*.md bundles)

```bash
git clone https://github.com/alisadikinma/indusiagen-api-client.git /home/claudesn/indusiagen-api-client
cd /home/claudesn/indusiagen-api-client
python -m venv .venv && .venv/bin/pip install -r requirements.txt
cp .env.example .env   # set GEMINIGEN_API_KEY + GEMINIGEN_BASE_URL=https://api.snapgen.ai/uapi/v1
# verify:
.venv/bin/python scripts/geminigen_image.py --help
# real submit smoke:
.venv/bin/python scripts/geminigen_image.py --model nano-banana-pro --prompt "test" --no-wait
```

Two-context key: HTTP/www-data (blog images) SSHs → claudesn; queue/claudesn execs `local`.
Chown/venv owned by claudesn; www-data reaches it only via SSH (mode-600 key already wired
for `LINKEDIN_GEN`).

### Risks / open items

1. **Video CLI `check` subcommand** — confirmed present in image CLI (`geminigen-image
   check {uuid}`, L237). Step 0 of Wave 2 verifies the video CLI has it; else use the
   `python -c` poll fallback over the same core.
2. **Stdout contract stability** — bridge parses `uuid:` / `cdn_url:` lines. If a `--json`
   flag exists on the CLI, prefer it (verify step 0). Pin the parse with a unit test using
   `Process::fake` on the exact `OK\n…` shape.
3. **Per-poll exec cost** — low volume (personal portfolio). `ponytail:` batch via the
   CLI's `--concurrent` only if in-flight count ever grows.
4. **Circuit breaker semantics** — now keyed on CLI outcome (exit code / `ERROR` line)
   instead of HTTP status. Map `ERROR (submit)` / `ERROR (poll)` → failure; `OK` → success.
5. **gpt-image-2 = Premium plan only** (official docs). If the `GEMINIGEN_API_KEY`
   account is not Premium, every gpt-image-2 submit fails. VPS deploy step 0 verifies a
   real gpt-image-2 submit succeeds before flipping `linkedin_carousel_image_model` to it.
   Bridge treats a plan-denied submit as a hard error → carousel falls back to
   nano-banana-pro (the default) so slides still render; surface via circuit breaker /
   log, don't silently stall the FSM.

## Data Integration Map

| Component | Data source | Existing? | Notes |
|---|---|---|---|
| `GeminiGenClientBridge` | indusia CLI stdout (real) | NEW | ~1 service file + `config/geminigen.php` |
| Blog image submit | `ImageGenerationService::triggerForIdea` | reuse | swap `Http::post` → `bridge->submit` |
| Carousel submit | `LinkedInCarouselImageService::dispatchOne` | reuse | + `linkedin_carousel_image_model` setting |
| Video submit | `GeminiGenVideoService::dispatch*` | reuse | 4 seams, Wave 2 |
| Poll completion | `ProcessPendingImages`, `ReapStuckLinkedInCarouselImages`, `PollHookVideos`, `PollRebrandAssets` | reuse | GET history → `bridge->check` |
| Download + branding | existing `downloadAndStore` + enhancers | reuse | unchanged (CDN url in, branded file out) |
| Circuit breaker | `GeminiGenCircuitBreaker` | reuse | fed by CLI outcome |
| Prompt authoring | `CoverBrandingEnhancer` / `CarouselSlideEnhancer` | reuse | untouched — produces bridge inputs |
| gpt-image-2 model | `settings` group `linkedin` (new key) | NEW | default nano-banana-pro (no-op until flipped) |
| API key | `config/services.php:39` + client `.env` | reuse | unchanged |

## Tests

- `GeminiGenClientBridgeTest` (unit, `Process::fake`): arg-building per model/surface
  (incl. gpt-image-2 mode/local-ref/no-format), stdout parse happy + `ERROR`, driver
  `local` vs `ssh` selection, circuit-breaker record on success/fail.
- Per-surface feature test (`Http::fake` old path + `Process::fake` new path): flag-on
  routes to bridge, flag-off keeps PHP path byte-for-byte.
- Poll-cron test: `check` → done downloads via existing path; failed hits existing FSM.

## Out of scope (trivial follow-ups once the bridge exists)

- Seedance / Kling video models (CLI-only in the client today).
- gpt-image-2 for blog covers, extend endpoints, per-surface model UI beyond the one
  carousel knob.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan swaps a real generation transport (indusia CLI) for the
> existing PHP HTTP path. NEVER substitute placeholders for real snapgen calls. If the
> VPS client isn't deployed yet, the flag stays OFF and the old PHP path runs — never
> stub the bridge to "fake success". If a seam differs from what's described, STOP and ask.

### Execution deviation (2026-07-01, discovered in Phase B)

The client's `check <uuid>` subcommand is an **unimplemented stub** in the deployed
version (`raise ClickException("check subcommand will be implemented in Phase 7")`), and
a history poll is a **model-agnostic `GET /history/{uuid}` → `status`+`generate_result`**
with no per-model wire logic to drift. So the design is adjusted:

- **Bridge is SUBMIT-only** (`GeminiGenClientBridge::submit()`); the `check()` method is
  dropped. SSOT still covers the part that actually drifted (endpoint routing, ref
  local-vs-URL, model param mapping).
- **Poll-check stays in the backend crons** — already pointed at `config('geminigen.base_url')`
  = snapgen by **Phase A**. **Phases E and G reduce** to confirming the existing poll parse
  reads `status`/`generate_result` correctly (it does) — no `bridge->check` wiring.
- Confirmed CLI shape: group `cli` → subcommand `image`/`video`, **prompt positional**,
  `--aspect` (not `--aspect_ratio`), `--ref` (multi), `--no-wait` prints
  `submitted: uuid=<uuid> status=<n>`; relative imports → invoke `python -m scripts.geminigen_image`.

### Goal

Make the indusia Python client (`geminigen_client.py`) the single source of truth for the
GeminiGen/snapgen wire protocol. The Laravel backend stops owning the wire (kills 3 drifted
copies still on the dead `api.geminigen.ai`) and instead shells the client CLI for the two
seams that carry protocol — **submit** and **poll-check** — while keeping all
orchestration, `ImageGenerationJob` tracking, FSM, download, branding, and the circuit
breaker in PHP. Ships in 2 feature-flagged waves (images, then video) with the old PHP path
as per-surface fallback.

### Architecture Context (from CLAUDE.md + code, real seams)

- **Submit seams** (all build `$multipart = [['name'=>,'contents'=>], …]` → `Http::withHeaders(['x-api-key'=>$this->apiKey])->asMultipart()->post("{$this->baseUrl}/<endpoint>")` → read `json('uuid')`; breaker-gated):
  - [`ImageGenerationService.php:163`](../../backend/app/Services/ImageGenerationService.php) `/generate_image` (blog; fields prompt/model/aspect_ratio/style/webhook_url/file_urls[])
  - [`LinkedInCarouselImageService.php:496`](../../backend/app/Services/LinkedInCarouselImageService.php) `dispatchOne` `/generate_image` (aspect 3:4, model `resolveModel()`)
  - [`GeminiGenVideoService.php`](../../backend/app/Services/GeminiGenVideoService.php): `dispatchHookVideo:130` (`/video-gen/grok`), `dispatchKeyframe:226` (`/generate_image`), `dispatchVeoClip:296` (`/video-gen/veo`), `dispatchGrokClip:375` (`/video-gen/grok`)
- **Poll-check seams** (GET `https://api.geminigen.ai/uapi/v1/history/{uuid}` → `$data['status']` int 1/2/3 + `$data['generate_result']` (image) / `generated_video[0].video_url` (video)):
  - [`ProcessPendingImages.php:46`](../../backend/app/Console/Commands/ProcessPendingImages.php) — **sole image completion path, blog + carousel both** (queries all `ImageGenerationJob` where `status='processing'`)
  - [`PollHookVideos.php:74`](../../backend/app/Console/Commands/PollHookVideos.php), [`PollRebrandAssets.php:501`](../../backend/app/Console/Commands/PollRebrandAssets.php) — video
  - `ReapStuckLinkedInCarouselImages` is a **re-dispatcher only** (no history GET) — leave untouched.
- **Base URL** hardcoded `private string $baseUrl = 'https://api.geminigen.ai/uapi/v1'` in all 3 services → move to `config('geminigen.base_url')` = `https://api.snapgen.ai/uapi/v1`.
- **Config**: `config/services.php:38` `geminigen.api_key` + `video_model`/`veo_model`/`ffmpeg_path` (keep as-is). **API key stays server-side** — client resolves it from its own `.env` on the VPS; **never** pass on the CLI argv (visible in `ps`).
- **Setting seeder**: [`LinkedInSettingsSeeder.php:90`](../../backend/database/seeders/LinkedInSettingsSeeder.php) pattern `Setting::firstOrCreate(['key'=>…,'group'=>'linkedin'], …)`.
- **Breaker**: `GeminiGenCircuitBreaker` — service keeps `state()==='open'` gate + `recordSuccess()/recordFailure()`; bridge result (throw / null) drives which it calls. No breaker logic moves into the bridge.

### Tech Stack

Laravel 12 / PHP 8.2 (in `portfolio_backend` Docker — run `docker exec portfolio_backend php artisan test`). `Illuminate\Process` (`Process::run` array form, **no shell string**) for local exec + SSH. Tests: PHPUnit with `Process::fake()` (new path) + `Http::fake()` (old path). Indusia client = Python venv on VPS (one-time deploy).

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Bridge submit | indusia CLI stdout (`uuid:` line) | `GeminiGenClientBridge::submit()` | No | Create — real `Process::run` |
| Bridge check | indusia CLI stdout (`status`+`cdn_url:`) | `GeminiGenClientBridge::check()` | No | Create — real `Process::run` |
| Base URL | `config/geminigen.php` | `config('geminigen.base_url')` | No | Create config (snapgen) |
| Blog submit | `ImageGenerationService` | existing `$multipart` build | Yes | Swap POST block → bridge (flag) |
| Carousel submit | `LinkedInCarouselImageService::dispatchOne` | existing | Yes | Swap + read model setting |
| Video submit ×4 | `GeminiGenVideoService::dispatch*` | existing | Yes | Swap POST blocks → bridge (flag) |
| Image completion | `ProcessPendingImages` | existing history GET | Yes | Swap GET → `bridge->check` (flag) |
| Video completion | `PollHookVideos`, `PollRebrandAssets` | existing history GET | Yes | Swap GET → `bridge->check` (flag) |
| Carousel model knob | `settings` group `linkedin` | `linkedin_carousel_image_model` | No | Seed (default `nano-banana-pro`) |
| Download + branding | `downloadAndStore` + enhancers | existing | Yes | Untouched |
| Circuit breaker | `GeminiGenCircuitBreaker` | existing | Yes | Fed by bridge outcome |
| API key | client `.env` on VPS | client 3-tier resolver | Yes | Never in argv |

**Not a non-deterministic/LLM phase:** we swap transport, not prompts/output. No image-quality eval — plumbing tests (uuid returned, url parsed, flag routing) are the contract. Prompt authoring (`CoverBrandingEnhancer`, `/carousel-gen`) is untouched.

---

### Phase A — `config/geminigen.php` + kill the dead-domain drift

**Estimated time:** 12 min. **Files:** Create `backend/config/geminigen.php`; Modify `ImageGenerationService.php:25`, `LinkedInCarouselImageService.php:44`, `GeminiGenVideoService.php:38` (+ the 4 poll crons' hardcoded history URL); Test `backend/tests/Unit/GeminiGenConfigTest.php`.

**Steps:**
1. Write failing test for config defaults. Expected error: `Failed asserting that null matches expected 'https://api.snapgen.ai/uapi/v1'` (config file absent).
2. Run `docker exec portfolio_backend php artisan test --filter=GeminiGenConfigTest`, confirm fail.
3. Create `config/geminigen.php`: `base_url` (env `GEMINIGEN_BASE_URL`, default `https://api.snapgen.ai/uapi/v1`), `client_driver`/`client_path`/`client_repo`/`client_ssh_host`/`client_ssh_user`/`client_ssh_key`/`client_timeout`, `use_indusia_images` (bool, default false), `use_indusia_video` (bool, default false).
4. Replace each `private string $baseUrl = '…geminigen…'` with `config('geminigen.base_url')` (read in constructor); replace the 4 crons' hardcoded `https://api.geminigen.ai/uapi/v1/history/` with `config('geminigen.base_url').'/history/'`.
5. Run tests, confirm pass. Commit: `fix(geminigen): move base URL to config, cut over to api.snapgen.ai`.

**Verification:**
- [ ] `php artisan test --filter=GeminiGenConfigTest` passes
- [ ] `grep -rn "api.geminigen.ai" backend/app` returns nothing
- [ ] existing image/video suites still green (old PHP path now hits snapgen — real fix, no flag needed)
- [ ] No placeholder/TODO in new code

### Phase B — `GeminiGenClientBridge` service (submit + check)

**Estimated time:** 15 min. **Files:** Create `backend/app/Services/GeminiGenClientBridge.php`; Test `backend/tests/Unit/GeminiGenClientBridgeTest.php`.

**Steps:**
1. Write failing test for `submit()` returning a uuid from faked stdout. Expected error: `Error: Class "App\Services\GeminiGenClientBridge" not found`.
2. Run `--filter=GeminiGenClientBridgeTest`, confirm fail.
3. Implement `submit(string $endpoint, array $fields, array $refs, string $model): string`:
   - build argv **array** (never a shell string) — `[python, script, '--model', $model, '--prompt', $fields['prompt'], '--aspect', $fields['aspect_ratio'], '--resolution', …, '--no-wait']`, one `--ref` per ref; map `endpoint`→CLI (`geminigen_image.py` vs `geminigen_video.py`).
   - **gpt-image-2 branch**: materialize each `https` ref to a temp local file (client has no `file_urls` for it), pass `--mode` not `--style`, drop `--format`.
   - driver: `local` → `Process::path($repo)->run($argv)`; `ssh` → `Process::run(['ssh','-i',$key,"$user@$host", <quoted argv>])`.
   - parse stdout: `uuid:` line → return; `ERROR (…)` line or non-zero exit → throw `GeminiGenClientException`.
   - **do NOT put api key in argv.**
4. Implement `check(string $uuid, bool $video=false): array` → run `<cli> check <uuid>`, parse `status`/`cdn_url:`/`ERROR` → `['status'=>'pending'|'done'|'failed','cdn_url'=>?, 'error'=>?]`. Fallback to `python -c` over `poll()` if the video CLI lacks `check` (guarded by a config `client_check_supported`, default true; verified in Phase H step 0).
5. Run tests (Process::fake for happy + ERROR + gpt-image-2 arg shape + local vs ssh argv), confirm pass. Commit: `feat(geminigen): add GeminiGenClientBridge (CLI submit + check)`.

**Verification:**
- [ ] `--filter=GeminiGenClientBridgeTest` passes (submit happy, ERROR→throw, check pending/done/failed, gpt-image-2 materializes url→local + `--mode`, driver local vs ssh argv)
- [ ] **Security:** argv passed as array to `Process` (no shell interpolation of prompt/ref text); api key never in argv; SSH key path from config, not literal
- [ ] No placeholder/TODO; `Process::fake` used, no real network in tests

### Phase C — Wave 1: blog image submit → bridge (flagged)

**Estimated time:** 10 min. **Files:** Modify `ImageGenerationService.php` (constructor + submit block ~150-190); Test `backend/tests/Feature/ImageGenIndusiaSubmitTest.php`.

**Steps:**
1. Write failing test: flag ON routes submit through `Process::fake` (bridge) and stores the faked uuid; flag OFF still uses `Http::fake` old path. Expected error: assertion fail (bridge not wired — still hits Http).
2. Run `--filter=ImageGenIndusiaSubmitTest`, confirm fail.
3. Inject `?GeminiGenClientBridge $bridge = null` in constructor. In the submit path, wrap: `if (config('geminigen.use_indusia_images'))` → keep breaker `open` gate, call `$this->bridge->submit('generate_image', $fields, $refs, $model)`, `recordSuccess()` on uuid / `recordFailure()` + return null on `GeminiGenClientException`; `else` → existing `Http::…->post()` block verbatim.
4. Run tests, confirm pass. Commit: `feat(geminigen): route blog image submit through bridge behind flag`.

**Verification:**
- [ ] `--filter=ImageGenIndusiaSubmitTest` passes (flag ON→Process, OFF→Http byte-identical)
- [ ] breaker `recordSuccess`/`recordFailure` still called on both paths
- [ ] No placeholder/TODO

### Phase D — Wave 1: carousel submit → bridge + gpt-image-2 knob

**Estimated time:** 14 min. **Files:** Modify `LinkedInCarouselImageService.php` (`dispatchOne`, `resolveModel`); Modify `LinkedInSettingsSeeder.php`; Test `backend/tests/Feature/CarouselIndusiaSubmitTest.php`.

**Steps:**
1. Write failing test: with `linkedin_carousel_image_model='gpt-image-2'` + flag ON, `dispatchOne` calls bridge with model `gpt-image-2` and endpoint `generate_image` mapping handled client-side; default setting → `nano-banana-pro`. Expected error: setting/method absent.
2. Run `--filter=CarouselIndusiaSubmitTest`, confirm fail.
3. Seed `['key'=>'linkedin_carousel_image_model','value'=>'nano-banana-pro','type'=>'text']` (linkedin group). `resolveModel()` reads it (fallback chain preserved). `dispatchOne`: `if (config('geminigen.use_indusia_images'))` → bridge submit with resolved model + refs (creator-face + logo `file_urls`); else existing POST. Bridge handles gpt-image-2 local-ref materialization.
4. Run tests + `db:seed --class=LinkedInSettingsSeeder` (idempotent), confirm pass. Commit: `feat(geminigen): carousel submit via bridge + gpt-image-2 model setting`.

**Verification:**
- [ ] `--filter=CarouselIndusiaSubmitTest` passes (default model + gpt-image-2 path)
- [ ] seeder idempotent (re-run adds 0 rows)
- [ ] gpt-image-2 path materializes URL refs to local (asserted via bridge fake)
- [ ] No placeholder/TODO

### Phase E — Wave 1: image completion poll → `bridge->check`

**Estimated time:** 12 min. **Files:** Modify `ProcessPendingImages.php:46-80`; Test `backend/tests/Feature/ProcessPendingImagesIndusiaTest.php`.

**Steps:**
1. Write failing test: flag ON → `ProcessPendingImages` gets completion via faked `bridge->check` (done+cdn_url) and runs existing `downloadAndStore` + FSM; failed → existing failure path. Expected error: still calls `Http::get` history.
2. Run `--filter=ProcessPendingImagesIndusiaTest`, confirm fail.
3. Replace the `Http::…->get(base/history/{uuid})` + `$data['status']`/`generate_result` block: `if (config('geminigen.use_indusia_images'))` → `$r = $bridge->check($job->uuid)`; map `done`→ existing download path with `$r['cdn_url']`, `failed`→ existing failure, `pending`→ skip; `else` existing GET. Keep blog + carousel branches (both are `ImageGenerationJob` rows) unchanged downstream.
4. Run tests, confirm pass. Commit: `feat(geminigen): image completion poll via bridge->check behind flag`.

**Verification:**
- [ ] `--filter=ProcessPendingImagesIndusiaTest` passes (done downloads, failed→FSM, pending skips)
- [ ] carousel-slide `ImageGenerationJob` rows still mirror onto `carousel_slides[]` (existing sync unchanged)
- [ ] No placeholder/TODO
- [ ] **Wave-1 integration:** with both flags ON in a scratch run, blog + carousel submit→check→download works end-to-end against faked CLI

### Phase F — Wave 2: video submit ×4 → bridge (flagged)

**Estimated time:** 15 min. **Files:** Modify `GeminiGenVideoService.php` (4 dispatch methods); Test `backend/tests/Feature/VideoIndusiaSubmitTest.php`.

**Steps:**
1. Write failing test: flag `use_indusia_video` ON routes each of the 4 dispatchers through the bridge with the right endpoint (`video-gen/grok`, `generate_image`, `video-gen/veo`) + **GROK named aspect** (`2:3`→`portrait` token mapping owned by client; assert bridge receives the service's value and the client maps it — verify token in Phase H step 0). Expected error: still Http.
2. Run `--filter=VideoIndusiaSubmitTest`, confirm fail.
3. In each dispatcher, wrap the POST block: `if (config('geminigen.use_indusia_video'))` → `$bridge->submit($endpoint, $fields, $refs, $model)`; else existing. Preserve breaker gate + record. Keep GROK JPG-pad/alpha preprocessing (client also handles it; harmless).
4. Run tests, confirm pass. Commit: `feat(geminigen): route video submit (grok+veo+keyframe) through bridge behind flag`.

**Verification:**
- [ ] `--filter=VideoIndusiaSubmitTest` passes (4 dispatchers, correct endpoint + aspect per model)
- [ ] Veo uses ratio aspect, GROK uses named token (assert value passed)
- [ ] breaker record preserved; No placeholder/TODO

### Phase G — Wave 2: video completion polls → `bridge->check`

**Estimated time:** 13 min. **Files:** Modify `PollHookVideos.php:74`, `PollRebrandAssets.php:501` (+ its other history GETs at ~265/359/395); Test `backend/tests/Feature/VideoPollIndusiaTest.php`.

**Steps:**
1. Write failing test: flag ON → both crons complete via faked `bridge->check(uuid, video:true)` (done→ `generated_video` url path, failed→ existing recover/fail FSM). Expected error: still Http history.
2. Run `--filter=VideoPollIndusiaTest`, confirm fail.
3. Replace each `Http::…->get(base/history/{uuid})` with the flag-gated `bridge->check($uuid, video:true)`; map done/failed/pending to the existing branches (keep `PollRebrandAssets` veo/keyframe two-pass + recovery edges intact). `else` existing GET.
4. Run tests, confirm pass. Commit: `feat(geminigen): video completion polls via bridge->check behind flag`.

**Verification:**
- [ ] `--filter=VideoPollIndusiaTest` passes (hook + rebrand, done/failed/pending)
- [ ] `PollRebrandAssets` recovery/two-pass logic unchanged
- [ ] No placeholder/TODO

### Phase H — VPS deploy runbook + step-0 verification

**Estimated time:** 10 min (doc) + operator run. **Files:** Create `docs/runbooks/geminigen-indusia-client-deploy.md`.

**Steps:**
1. Write the runbook: clone `indusiagen-api-client` to `/home/claudesn/`, `python -m venv .venv && pip install -r requirements.txt`, client `.env` with `GEMINIGEN_API_KEY` + `GEMINIGEN_BASE_URL=https://api.snapgen.ai/uapi/v1`, chown claudesn, two-context SSH key (www-data→claudesn for blog image HTTP path). Portfolio `.env`: `GEMINIGEN_CLIENT_*` + flags OFF.
2. **Step 0 verification block** (operator runs before flipping flags): `geminigen-image --help` shows `--no-wait`/`check`/`--json`?; real `--no-wait` submit returns uuid; `check <uuid>` returns status; **gpt-image-2 real submit succeeds** (Premium gate); `geminigen-video` has `check` (else set `client_check_supported=false`); GROK aspect token confirmed.
3. Commit: `docs(geminigen): indusia client VPS deploy runbook + step-0 checks`.

**Verification:**
- [ ] runbook covers clone/venv/env/keys/two-context + step-0 checklist
- [ ] flags documented OFF-by-default; flip criteria explicit (step-0 green in prod)

### Phase I — Cutover + dead-code deletion (after prod-green)

**Estimated time:** 8 min. **Files:** Modify the 3 services + 3 crons (remove old `Http::` wire blocks + flag branches once each wave validated in prod).

**Steps:**
1. After Wave 1 flag ON + validated in prod (blog + carousel render via indusia), delete the old `Http::…->post/get` image blocks + `use_indusia_images` branch (bridge becomes unconditional for images). Run image suites.
2. After Wave 2 validated, same for video + `use_indusia_video`. Run video suites.
3. Commit each wave separately: `refactor(geminigen): drop dead PHP wire code after <wave> cutover`.

**Verification:**
- [ ] `grep -rn "asMultipart\|/uapi/v1/history" backend/app` returns nothing for cut surfaces
- [ ] full `php artisan test` green
- [ ] CLAUDE.md updated (base URL, bridge, config, flags, model setting) per the mandatory post-change doc rule

---

### Execution notes

- **Phases A + B are independent of C-G** and unblock everything → do first, in order (A fixes the live dead-domain bug immediately, shippable alone).
- Wave 1 (C, D, E) and Wave 2 (F, G) are sequential within a wave; **Wave 1 and Wave 2 are independent** — could `gaspol-parallel` if desired, but flags make sequential safe and simpler.
- H before flipping any flag in prod. I only after each wave is prod-green.
- Run every phase's tests via `docker exec portfolio_backend php artisan test --filter=<name>` (no host PHP).
