# GROK Hook Video for Instagram Mixed Carousel

> Status: Design (brainstorm complete 2026-06-12). Implementation plan to be appended by `gaspol-plan`.

## Design

### Goal
Animate the carousel **hook slide (slide 1)** into a short looping video (creator subtle movement + floating topic icons drift) and use it as the **first item of the Instagram mixed carousel** (video hook + image slides 2–N). All other surfaces stay all-image: **LinkedIn, TikTok, Threads = unchanged image carousel** (LinkedIn cannot mix video+image; TikTok cannot mix in one post). Generation is **automatic** per draft (GROK is free → no per-clip cost concern).

### Locked decisions (operator)
| Decision | Choice |
|---|---|
| Trigger | **Automatic** when the hook slide finishes rendering (no operator button) |
| Engine/model | **GeminiGen GROK** (`grok-3`), image-to-video, **free** |
| Aspect | `vertical` (2:3) — accepted vs the 4:5 image slides (IG renders one ratio; minor letterbox/crop) |
| Platforms using the video | **Instagram only** (mixed carousel). TikTok/LinkedIn/Threads stay all-image |
| UI | Hook slide shows **Image + Video** tabs on the draft detail (both versions visible) |
| Duration / res / mode | `duration=6` (min allowed), `resolution=720p`, `mode=normal` |

### GROK API contract (confirmed — GeminiGen docs 2026-06-12)
```
POST https://api.geminigen.ai/uapi/v1/video-gen/grok   (multipart/form-data)
Header: x-api-key: <GEMINIGEN_API_KEY>
Fields:
  prompt        (req)  text description
  model         (req)  grok-3
  resolution           480p (default) | 720p
  aspect_ratio         landscape(16:9) | portrait(9:16) | square(1:1) | vertical(2:3) | horizontal(3:2)
  duration             6 | 10 | 15
  mode                 custom | normal | extremely-crazy | extremely-spicy-or-crazy
  file_urls[]          PUBLIC image URL(s) = image-to-video frame  ← we use this
  (files[] / ref_images[] are the other two ref methods; priority files > file_urls > ref_images, only ONE method per request)
```
**Image-to-video input:** pass the hook slide's existing public URL (`alisadikinma.com/storage/linkedin-carousel/...png`) as `file_urls` — no upload needed.

**Residual unknown — async delivery:** this endpoint's doc page does NOT list a `webhook_url` field. The image endpoint + the `/geminigen-video` skill (`--webhook-cb`) support webhook→Portfolio relay, so it *likely* works here too, but it may be **poll-only** (returns a job uuid → GET job status). **Design webhook-first with a poll fallback** (reuse the `blog:process-images` poller pattern); verify with one live test call in Phase 0 of execution.

**Default prompt:** "The creator makes a subtle, natural movement (slight head/hand motion, blink, breathing); the floating topic UI cards and icons drift and parallax gently around them; minimal slow camera push-in; keep the headline text crisp and readable; smooth seamless short loop, photoreal, no warping."

### Two workstreams

#### Workstream 1 — `geminigen-api-client` MCP + RAG: add GROK provider (dev tooling)
Production backend calls the GROK HTTP API **directly** (headless prod has no MCP — `--mcp-config empty-mcp.json`). The MCP/RAG enhancement is for **interactive/dev use** (so the operator + Claude can test-generate GROK videos), per operator's explicit ask.
- `scripts/geminigen_client.py` `VIDEO_SPECS`: add a `grok` spec — endpoint `uapi/v1/video-gen/grok`, models `['grok-3']`, `aspect_ratio` named enum (landscape/portrait/square/vertical/horizontal), `resolution` 480p/720p, `duration` 6/10/15, `mode` enum, refs via `files`/`file_urls`/`ref_images` (priority). Note GROK's param names differ from VEO (`aspect_ratio` named values vs VEO `aspect` 16:9/9:16; `mode` = generation-style vs VEO `mode_image` = frame/ingredient) — the spec abstraction must carry per-provider param mapping.
- `mcp_video_server.py`: route `generate_video` to the grok spec, widen aspect/mode validation, update `list_video_models` text.
- RAG: new `references/grok-video-best-practices.md`, add a GROK row to `references/model-decision-tree.md`, generalize `veo-best-practices.md` framing to "video"; update `skills/geminigen-video/SKILL.md` to document grok. Bump plugin version + `compile-refs` if bundled.

#### Workstream 2 — `Portfolio_v2` backend + UI: auto IG hook video (production feature)
- **Schema:** add `hook_video_url`, `hook_video_status` (pending/generating/done/failed), `hook_video_job_uuid`, `hook_video_error` to **`instagram_posts`** (the row that consumes it). Nullable.
- **Trigger:** when the parent carousel's hook slide (slide 1 / `is_cover`) reaches `image_status=done` AND an Instagram sibling exists → dispatch `GenerateHookVideo` job. Wiring candidate: the existing IG fan-out path (`ScanLinkedInForCrossPost` after IG sibling creation, or `LinkedInCarouselImageService::maybeDispatchCrossPostFanout`). Idempotent (skip if `hook_video_status` already set).
- **Job `GenerateHookVideo`:** POST the GROK request (frame = parent hook slide `image_url` via `file_urls`, `aspect_ratio=vertical`, `duration=6`, `mode=normal`, `webhook_url`=new video webhook). Set `hook_video_status=generating` + store job uuid.
- **Webhook `/api/automation/grok-hook-video-webhook`** (mirrors `LinkedInCarouselImageWebhookController`): match by uuid → download MP4 → `storage/app/public/hook-videos/{ig_id}-hook.mp4` → set `hook_video_url` + `hook_video_status=done` (transaction + lock). Poll-fallback command for dropped webhooks.
- **Circuit breaker:** route through the existing `GeminiGenCircuitBreaker` (same provider) so a GeminiGen outage fails fast.
- **Publish assembly (`PublerPayloadBuilder`):** for **Instagram only**, when `hook_video_status=done`, build a mixed-media carousel — media item 1 = the MP4 (Publer pre-uploads via `/media/from-url`, `type:video`), items 2…N = the image slides. TikTok/Threads/LinkedIn untouched. If video not ready/failed → IG falls back to all-image carousel (never block publish).
- **Readiness gate:** extend `LinkedInSlotReadinessService` / `PublishSlotOrchestrator` so IG publish waits for `hook_video_status ∈ {done, failed}` (failed = graceful image-only fallback, not a block).
- **UI (`LinkedInDraftDetail.vue`):** the hook slide gets **Image | Video** tabs; Video tab shows the MP4 (or spinner while `generating`, error+retry while `failed`, "not generated" otherwise). Auto-poll while `generating`. Add a manual "Regenerate hook video" action.

### Data Integration Map
| Piece | Data source | Existing? | Notes |
|---|---|---|---|
| Hook frame image | `linkedin_posts.carousel_slides[0].image_url` | ✅ | public storage URL → `file_urls` |
| Video generation | GeminiGen GROK HTTP API | ⚠️ new call | reuse `x-api-key` + circuit breaker; new dispatch |
| Async delivery | webhook relay OR poller | ✅ pattern exists | webhook-first, poll fallback (verify in exec) |
| Video storage | `storage/app/public/hook-videos/` | ✅ disk | new subdir |
| Status mirror | `instagram_posts.hook_video_*` | ⚠️ new cols | migration |
| IG publish | `PublerPayloadBuilder` + `PublishViaPubler` | ✅ | extend IG branch to mixed media |
| Readiness | `LinkedInSlotReadinessService` | ✅ | add hook_video gate (IG) |
| UI | `LinkedInDraftDetail.vue` + `useLinkedInDrafts.js` | ✅ | image/video tabs + poll |

### Out of scope (v1)
- Threads mixed video (Threads supports it, but keep image-only for v1).
- ffmpeg crop 2:3→4:5 (accept 2:3 as-is per operator).
- Operator manual-trigger-only mode (chose automatic).
- VEO/Seedance/Kling/Sora providers in the backend (GROK only; MCP may add others later).

### Open items to resolve in execution Phase 0
1. Confirm GROK endpoint accepts `webhook_url` (else poll). One live test call.
2. Confirm Publer supports IG **mixed** video+image carousel via `/media/from-url` (`type:video`) — if not, IG hook video posts as a separate video (fallback decision).
3. Confirm `x-api-key` is the same secret already configured for GeminiGen image gen.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations (GeminiGen GROK API, Publer, GeminiGen circuit breaker). During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a contract is unconfirmed, STOP at the Phase 0 spike and ask.

### Goal
Auto-generate a short GROK image-to-video animation of the carousel hook slide and publish it as the first item of the **Instagram-only** mixed carousel, leaving LinkedIn/TikTok/Threads as all-image. Plus enhance the `geminigen-api-client` MCP+RAG to expose GROK for dev/interactive use.

### Architecture Context (from CLAUDE.md — reuse, don't reinvent)
- **GeminiGen dispatch pattern:** [`LinkedInCarouselImageService::dispatchAllSlides`/`dispatchSingleSlide`](backend/app/Services/LinkedInCarouselImageService.php) POST multipart to `{$baseUrl}/generate_image` with `file_urls` + `webhook_url`; `downloadAndStore($url)` mirrors the asset locally. **Mirror this for video** (`/video-gen/grok`).
- **Webhook pattern:** [`LinkedInCarouselImageWebhookController`](backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php) → service `handleWebhook` (uuid match → download → mirror in `DB::transaction`+`lockForUpdate`).
- **Circuit breaker:** [`GeminiGenCircuitBreaker`](backend/app/Services/GeminiGenCircuitBreaker.php) via the `assertCircuitClosed()` guard pattern (4 chokepoints documented).
- **Poll fallback:** [`ReapStuckLinkedInCarouselImages`](backend/app/Console/Commands/ReapStuckLinkedInCarouselImages.php) + `blog:process-images`.
- **IG fan-out:** [`ScanLinkedInForCrossPost::createInstagram`](backend/app/Console/Commands/ScanLinkedInForCrossPost.php#L345) → `GenerateInstagramPost::dispatch(...)->onQueue('social-crosspost')`; `hasLiveInstagramRow` idempotency. Early fan-out also fires from `LinkedInCarouselImageService::maybeDispatchCrossPostFanout`.
- **Publer:** [`PublerPayloadBuilder::buildInstagram(InstagramPost)`](backend/app/Services/PublerPayloadBuilder.php#L94) + private `buildMediaUrls(object)`; [`PublishViaPubler`](backend/app/Jobs/PublishViaPubler.php) pre-uploads each media URL via [`PublerClient::uploadAndAwaitMedia`](backend/app/Services/PublerClient.php) → `media_id`, then bulk-publish `networks.instagram.media=[{id,type}]`. `isPlatformEnabled($platform)` gate.
- **Readiness/publish:** [`LinkedInSlotReadinessService::isReady`](backend/app/Services/LinkedInSlotReadinessService.php) + [`PublishSlotOrchestrator`](backend/app/Console/Commands/PublishSlotOrchestrator.php).
- **UI:** [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) + [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) (TanStack poll while mid-flight).

### Tech Stack
Laravel 12 (PHPUnit, sqlite in Docker `serversideup/php:8.2-cli` — **no PHP on dev Mac**), Vue 3 + TanStack Query (`node --test` + `npm run build`), Python MCP (pytest) for `geminigen-api-client`. Commit-only (operator authorizes pushes).

### Data Integration Map
| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Hook frame URL | `linkedin_posts.carousel_slides[0].image_url` | model JSON | Yes | Read directly |
| GROK dispatch | `POST /uapi/v1/video-gen/grok` | new `GeminiGenVideoService` | No | Create (mirror image service) |
| Video API key | `config('services.geminigen.*')` (`x-api-key`) | existing GeminiGen secret | Yes (verify P0.3) | Reuse |
| Circuit guard | `GeminiGenCircuitBreaker` | `assertCircuitClosed()` | Yes | Reuse |
| Async delivery | webhook OR poller | new webhook + `grok:reap-stuck-hook-videos` | No | Create (webhook-first) |
| Status cols | `instagram_posts.hook_video_*` | migration | No | Create |
| Video storage | `storage/app/public/hook-videos/` | disk | Yes | New subdir |
| IG mixed media | `PublerPayloadBuilder::buildInstagram` | extend | Yes | Modify (video item 1) |
| Publer video upload | `PublerClient::uploadAndAwaitMedia` | `type:video` | Yes | Reuse |
| IG readiness | `LinkedInSlotReadinessService` | add hook_video gate | Yes | Modify |
| Hook tabs UI | `LinkedInDraftDetail.vue` | image/video tabs | Yes | Modify |

---

### Phase 0 — Verification spikes (research gate, NO code)
**Est:** 20 min. Resolve the 3 unconfirmed contracts BEFORE any code. Document answers inline in this file under "Phase 0 results".
**Steps:**
1. **GROK webhook?** `curl` a minimal GROK request (`mode=normal`, `duration=6`, `aspect_ratio=vertical`, a `file_urls` test image) **with** a `webhook_url` form field pointing at a request inspector (or the relay). Observe: does a callback fire? If not, find the job-status GET endpoint (`/uapi/v1/.../status/{uuid}` or similar) for poll mode.
2. **Publer IG mixed media?** Check Publer API docs / probe a scheduled-far-future IG bulk post with `networks.instagram.media=[{id,type:video},{id,type:image}]` then delete it. Confirm mixed accepted (else: IG hook video posts as a separate video — record the fallback).
3. **API key parity?** Confirm the GROK `x-api-key` == the secret already in `config('services.geminigen.*')` / `.env`.
**Verification:**
- [x] Phase 0 results block written (webhook vs poll decision, Publer mixed yes/no, key parity yes/no)
- [x] No answer blocks the design (one plan adjustment + one residual deferred to Phase G)

#### Phase 0 results (verified live on prod VPS 2026-06-12)
**1. GROK delivery = ASYNC + POLL (NOT webhook).** Live `POST /uapi/v1/video-gen/grok` returns HTTP 200 in ~3s with a job: `{id, uuid, type:"video", model_name:"grok-video", status:1, estimated_credit:5}` — no URL yet. Poll `GET https://api.geminigen.ai/uapi/v1/history/{uuid}` (same endpoint the image poller uses). **Done = `generated_video[0].video_url` non-null** (R2 signed URL; mirrors `generated_image[0].image_url`); the item also carries `duration/aspect_ratio/resolution/grok_mode` + `thumbnail_url` + `last_frame_url`. **Webhook is unreliable** (GeminiGen never POSTs callbacks — known incident, the `blog:process-images` poller is the sole image completion path). → **PLAN ADJUSTMENT: poll-primary.** Reuse the `ImageGenerationJob` + `ProcessPendingImages` pattern (add a `hook_video` job type that polls `/history/{uuid}`); webhook controller (orig Phase D) is **dropped / demoted to optional best-effort**. The reaper (Phase E) becomes the primary completion driver.
**Contract corrections vs the docs PDF:**
- `aspect_ratio` must be `2:3` (raw ratio) — the API **rejects** `vertical` (valid: `landscape`/`portrait`/`square`/`3:2`/`2:3`).
- `model=grok-3` accepted (maps server-side to `model_name=grok-video`).
- **Cost:** `estimated_credit:5` per clip — cheap but **not literally free**; automatic-per-draft ≈ 5 credits/IG draft. Operator-flag.
- Test job uuid `cefb948a-6626-11f1-9764-aea233c4149c` left running — eyeball its output MP4 quality later (image-to-video fidelity check).

**2. Publer IG mixed video+image = SUPPORTED at platform level; bulk-confirm deferred to Phase G.** Instagram Graph API allows a CAROUSEL with mixed IMAGE+VIDEO children; Publer's `media[]` already carries per-item `{id,type}`, so we set the hook item `type:video` (pre-uploaded via `PublerClient::uploadAndAwaitMedia`). **RESIDUAL:** confirm Publer's bulk-publish accepts mixed types in ONE post via a guarded scheduled-far-future + delete probe **inside Phase G** (the documented June-10 validation method). **Fallback (designed-in, non-blocking):** if Publer rejects mixed, post the hook video as a **separate IG video**; IG carousel stays all-image.

**3. `x-api-key` parity = CONFIRMED.** GROK uses the **same** `config('services.geminigen.api_key')` (`GEMINIGEN_API_KEY`, len 41) and the **same base** `https://api.geminigen.ai/uapi/v1` as the image endpoint (`LinkedInCarouselImageService` line 233 `x-api-key`). No new secret.

#### GROK generation recipe (reverse-engineered live — Phase B contract)
- **Input frame MUST be JPG, not PNG.** GROK rejects our `.png` slides (`error: "re-export as JPG… checkered pattern"` = it reads PNG alpha as transparency). → Phase B must **flatten the hook slide PNG → JPG** (white bg, strip alpha; `ffmpeg -i in.png out.jpg` works, Intervention Image also available) and pass that public JPG URL as `file_urls`.
- **Prompt: ONE sentence, comma-separated, NO semicolons.** The API truncates `input_text` at the first `;` (bare test only got "The creator makes a subtle natural movement").
- **`negative_prompt` is IGNORED by GROK** (Seedance/Veo-only field — returned `None`). Bake all negatives INTO the positive prompt as explicit constraints: "exactly one coffee mug in one hand, anatomically correct hands, mouth closed and not speaking" (kills the observed duplicate-mug / extra-hand / lip-sync artifacts).
- **Strip audio on download** (`ffmpeg -i raw.mp4 -an -c:v copy out.mp4`) — operator wants no sound / no lip-sync.
- **Params:** `model=grok-3` (→ `model_name=grok-video`), `aspect_ratio=2:3`, `duration=6`, `resolution=720p`, `mode=normal`. Render ~60-90s, ~5 credits.
- **Prompt MUST NOT hardcode props** (operator directive: the creator isn't always holding coffee — could be headphones, a phone, gesturing). Since GROK is image-to-video (it *sees* the frame), the production default is a **frame-respecting GENERIC prompt** + hard no-new-objects constraint: "the creator continues exactly the relaxed pose/action already in the source image, introduces NO new object, hands + anything held stay as in the frame (no duplicated object/hand), camera static, floating side icons drift/bob/parallax gently, text stays crisp, mouth closed, photoreal, no morphing." Works for any hook slide. **Motion knobs that worked:** `mode=custom` (tighter adherence than `normal`), explicit "static locked camera no zoom/pan", "animate ONLY existing elements, add/invent NO new object/icon" (the only lever against duplicate-mug + phantom-object artifacts since `negative_prompt` is ignored).
- **DECISION — LOCKED (operator-approved on v7, 2026-06-12):** **GENERIC prompt + hard no-new-objects constraint + padded 2:3 frame — NO LLM-derive step.** The earlier v6 phantom-object failure was caused by the *unpadded* 4:5→2:3 outpaint, NOT by the generic prompt itself. Once the frame is pre-padded to 2:3 (GROK has no empty margin to hallucinate into) **and** the prompt carries the explicit "animate only what exists, add nothing new" constraint, the generic prompt renders clean (v7 = `grok-test-158-v7.mp4`, operator verdict "Bersih"). This is **simpler than the LLM-derive path** (no Sonnet round-trip, deterministic, no per-slide prompt drift) and adapts to any held prop because GROK reads it from the frame. **Phase B uses a single static prompt constant — no LLM call.** (v5 `grok-test-158-v5.mp4` specific-action render remains the quality reference bar; v7 matches it without the LLM step.)
- **Aspect outpaint fix (REQUIRED, the actual root cause of phantom objects):** source slide is 4:5 but GROK outputs 2:3 (taller) → without padding, GROK outpaints + hallucinates in the added strips. **Pre-pad the JPG 4:5 → 2:3 with brand-blue (#0F59B6) fill** (`ffmpeg pad`) BEFORE dispatch so GROK has nothing to invent in the margins, then **crop the result 2:3 → 4:5** on download (+ `-an` audio strip) so the final video matches the image slides' 4:5 (v7 output dims 768×960 = 4:5, audio stripped).

**Net plan deltas:** (a) Completion path is **poll-primary** — extend `ImageGenerationJob` with a `hook_video` type + a poller (mirror `ProcessPendingImages`) instead of a webhook controller; original Phase D (webhook) → replaced by a poll/reaper, folded with Phase E. (b) `aspect_ratio=2:3` (not `vertical`). (c) Publer mixed-media confirmation is a guarded probe within Phase G, with the separate-video fallback already designed.

---

### Phase A — Schema: `instagram_posts.hook_video_*`
**Est:** 8 min. **Files:** Create `database/migrations/2026_06_12_000003_add_hook_video_to_instagram_posts.php`; Modify [`InstagramPost`](backend/app/Models/InstagramPost.php); Test `tests/Feature/InstagramPostHookVideoColumnsTest.php`.
**Steps:**
1. Write failing test for the 4 columns + fillable. Expected error: `Illuminate\Database\QueryException: no such column: instagram_posts.hook_video_status`.
2. Run (Docker sqlite), confirm fail for that reason.
3. Migration: `hook_video_url` (string null), `hook_video_status` (string 16 null), `hook_video_job_uuid` (string null, indexed), `hook_video_error` (text null). Add all 4 to `$fillable`.
4. Run, confirm pass.
5. Commit: `feat(ig-hook-video): instagram_posts hook_video columns`.
**Verification:**
- [ ] Migration runs on sqlite + (MySQL-safe types)
- [ ] 4 columns fillable; no TODO comments
- [ ] Test green in Docker

---

### Phase B — `GeminiGenVideoService` (GROK dispatch + circuit breaker)
**Est:** 18 min. **Files:** Create `app/Services/GeminiGenVideoService.php`; Modify `config/services.php` (geminigen video base/key reuse); Test `tests/Unit/GeminiGenVideoServiceTest.php`. **No LLM step** — static locked prompt constant (v7 recipe).
**Frame prep + dispatch (locked recipe):**
- `prepareFrame(slidePngUrl): string` — download the hook slide PNG, **flatten PNG→JPG** (strip alpha, white/blue bg) **and pad 4:5→2:3 with `#0F59B6`** via `ffmpeg` (one pass: `-vf "format=rgb24,pad=iw:ceil(iw*3/2/2)*2:(ow-iw)/2:(oh-ih)/2:color=0x0F59B6"`), store under `storage/app/public/linkedin-carousel/grok-frame-{igPostId}.jpg`, return its public URL. ffmpeg path from `config('services.repurpose.*')` precedent (ffmpeg confirmed on VPS).
- `HOOK_VIDEO_PROMPT` constant = the locked v7 generic+constraint one-sentence prompt (comma-separated, NO semicolons): creator continues the exact pose/action in the frame, animate ONLY existing elements add/invent NO new object/icon, hands + held items stay identical (no duplicate/extra hand), floating side icons drift/bob gently, static locked camera no zoom/pan, mouth closed not speaking, photoreal no morphing.
**Steps:**
1. Write failing test: `dispatchHookVideo(InstagramPost, frameUrl)` posts multipart to `…/uapi/v1/video-gen/grok` with `model=grok-3`, `aspect_ratio=2:3`, `resolution=720p`, `duration=6`, `mode=custom`, `prompt=HOOK_VIDEO_PROMPT`, `file_urls=<frameUrl>`, header `x-api-key`; returns the response job `uuid`. Expected error: `Error: Class "App\Services\GeminiGenVideoService" not found`.
2. Run (Docker), confirm fail.
3. Implement: `Http::fake`-friendly POST mirroring `LinkedInCarouselImageService` lines 219-235; `prompt=HOOK_VIDEO_PROMPT` (static, no Sonnet call); call `GeminiGenCircuitBreaker::assertCircuitClosed()` first (throws `GeminiGenCircuitOpenException`); return the GROK job uuid. (`prepareFrame` ffmpeg path exercised via a `Process::fake`/seam in a separate unit test — keep HTTP test pure.)
4. Add a 2nd test: circuit-open short-circuits (no HTTP call). Run, confirm pass.
5. Commit: `feat(ig-hook-video): GeminiGenVideoService GROK dispatch (static v7 prompt)`.
**Verification:**
- [ ] Multipart shape matches the locked GROK contract: `model=grok-3`, `aspect_ratio=2:3`, `mode=custom`, `duration=6`, `resolution=720p`
- [ ] Static `HOOK_VIDEO_PROMPT` constant, comma-separated, zero `;`, no hardcoded prop word ("coffee"/"headphones"), no LLM call
- [ ] Circuit-open throws before HTTP; `Http::fake` asserts request
- [ ] No secrets hardcoded (key from config); tests green
- [ ] Security: outbound only, key from config, frameUrl is our own storage URL

---

### Phase C — `GenerateHookVideo` job
**Est:** 10 min. **Files:** Create `app/Jobs/GenerateHookVideo.php`; Test `tests/Feature/GenerateHookVideoJobTest.php`.
**Steps:**
1. Write failing test: job for an IG sibling whose parent hook slide is `done` calls `GeminiGenVideoService::dispatchHookVideo`, sets `hook_video_status=generating` + `hook_video_job_uuid`. Expected error: class not found.
2. Run, confirm fail.
3. Implement: resolve parent `LinkedInPost` hook slide (`carousel_slides[0].image_url`); bail (log) if format≠carousel, no IG sibling, hook slide not done, or `hook_video_status` already set (idempotent). 360s timeout, 1 try.
4. Run, confirm pass.
5. Commit: `feat(ig-hook-video): GenerateHookVideo job`.
**Verification:**
- [ ] Idempotent (skips when already generating/done); bails cleanly
- [ ] Sets status+uuid; test green

---

### Phase D — Webhook controller + route + `handleHookVideoWebhook`
**Est:** 14 min. **Files:** Create `app/Http/Controllers/Api/GrokHookVideoWebhookController.php`; add method to `GeminiGenVideoService::handleWebhook`; Modify `routes/api.php`; Test `tests/Feature/GrokHookVideoWebhookTest.php`.
**Steps:**
1. Write failing test: POST `/api/automation/grok-hook-video-webhook` with `{uuid,data:{...mp4 url}}` → downloads (Http::fake) → `hook_video_status=done` + `hook_video_url` set. Expected error: `404`/route not found.
2. Run, confirm fail.
3. Implement: public route (mirror carousel-image-webhook); match by `hook_video_job_uuid`; `downloadAndStore` MP4 → `storage/app/public/hook-videos/{ig_id}-hook.mp4`; mirror in `DB::transaction`+`lockForUpdate`; failure path sets `hook_video_status=failed`+`hook_video_error`; record circuit success/failure.
4. Run, confirm pass.
5. Commit: `feat(ig-hook-video): GROK webhook + mirror`.
**Verification:**
- [ ] Concurrent-safe (lock); failure mirrored; MP4 stored under public disk
- [ ] Security: public endpoint validates uuid match; no arbitrary file write (server-set filename, int id)

---

### Phase E — Poll fallback command + scheduler row
**Est:** 10 min. **Files:** Create `app/Console/Commands/ReapStuckHookVideos.php` (`grok:reap-stuck-hook-videos`); Modify `database/seeders/ScheduledCommandSeeder.php`; Test `tests/Feature/ReapStuckHookVideosTest.php`.
**Steps:**
1. Write failing test: an IG row `hook_video_status=generating` with `updated_at` >15m re-dispatches `GenerateHookVideo` (excludes done/failed). Expected error: class not found.
2. Run, confirm fail.
3. Implement reaper (`--generating-threshold=15 --dry-run`) + idempotent `ScheduledCommandSeeder` row (every 10 min, category `instagram`/`system`).
4. Run, confirm pass.
5. Commit: `feat(ig-hook-video): poll-fallback reaper + cron`.
**Verification:**
- [ ] Re-dispatches only stuck rows; seeder idempotent; tests green

---

### Phase F — Auto-dispatch trigger (IG fan-out wiring)
**Est:** 10 min. **Files:** Modify `ScanLinkedInForCrossPost::createInstagram` (or `LinkedInCarouselImageService::maybeDispatchCrossPostFanout`); Test `tests/Feature/HookVideoAutoDispatchTest.php`.
**Steps:**
1. Write failing test: when hook slide is `done` AND an IG sibling is created, `GenerateHookVideo` is dispatched (Bus::fake), idempotent on re-scan. Expected error: assertion fails (not dispatched).
2. Run, confirm fail.
3. Implement: after IG sibling create + when parent hook slide done, `GenerateHookVideo::dispatch($ig->id)->onQueue('social-crosspost')`; guard via `hook_video_status` null.
4. Run, confirm pass.
5. Commit: `feat(ig-hook-video): auto-dispatch on IG fan-out`.
**Verification:**
- [ ] Fires once per IG sibling; no double dispatch; tests green

---

### Phase G — Publer IG mixed-media (video item 1) + fallback
**Est:** 14 min. **Files:** Modify [`PublerPayloadBuilder::buildInstagram`](backend/app/Services/PublerPayloadBuilder.php) + `buildMediaUrls`; possibly `PublishViaPubler` (video pre-upload type); Test `tests/Unit/PublerInstagramHookVideoTest.php`.
**Steps:**
1. Write failing test: `buildInstagram` with `hook_video_status=done` yields media spec where item 1 is the MP4 (`type:video`) + slides 2…N images; with status≠done yields all-image (fallback). Expected error: assertion fail (no video item).
2. Run, confirm fail.
3. Implement: prepend hook video to the IG media list with a video-type marker; `PublishViaPubler` pre-uploads it via `uploadAndAwaitMedia` as `type:video` → `{id,type:"video"}`; gate behind P0.2 result (if Publer rejects mixed → post video as a separate IG video per fallback).
4. Run, confirm pass.
5. Commit: `feat(ig-hook-video): Publer IG mixed video+image`.
**Verification:**
- [ ] Video only when done; graceful all-image fallback; IG-only (TikTok/Threads/LinkedIn untouched — assert)
- [ ] tests green

---

### Phase H — IG readiness gate
**Est:** 8 min. **Files:** Modify `LinkedInSlotReadinessService` (+ `PublishSlotOrchestrator` if needed); Test `tests/Feature/IgHookVideoReadinessTest.php`.
**Steps:**
1. Write failing test: IG sibling NOT ready while `hook_video_status=generating`; ready when `done` OR `failed` (failed = image-only fallback, not a block). Expected error: assertion fail.
2. Run, confirm fail.
3. Implement gate (IG-only; never blocks other platforms).
4. Run, confirm pass.
5. Commit: `feat(ig-hook-video): readiness waits for hook video`.
**Verification:**
- [ ] generating → blocked; done/failed → allowed; LinkedIn/TikTok unaffected; tests green

---

### Phase I — UI: hook Image|Video tabs + poll + regenerate
**Est:** 14 min. **Design Deliverable:** tabs spec (Image|Video toggle on the hook slide; Video states: rendered `<video>` / spinner `generating` / error+retry `failed` / "not generated"; matches existing draft-detail dark theme + status-pill palette). **Files:** Modify [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue), [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js); add endpoint `POST /admin/linkedin-drafts/{id}/regenerate-hook-video` + controller method; Test `frontend/src/views/admin/linkedinHelpers.test.mjs` (+ helper for video state).
**Steps:**
1. Write failing node test for a pure helper `hookVideoState(igSibling)` → `{mode:'video'|'image'|'generating'|'failed'|'none'}`. Expected error: import/assertion fail.
2. Run (`node --test`), confirm fail.
3. Implement helper + wire Image|Video tabs on the hook slide; poll while `generating` (extend existing poll predicate); add "Regenerate hook video" action + `useRegenerateHookVideo` mutation + backend endpoint.
4. `npm run build` + `node --test`, confirm pass.
5. Commit: `feat(ig-hook-video): hook image/video tabs + regenerate`.
**Verification:**
- [ ] All 5 video states render; poll active while generating; build clean; node tests green
- [ ] Design deliverable matches draft-detail tokens

---

### Phase J — MCP: GROK provider in `geminigen-api-client`
**Est:** 14 min (repo `/Users/alisadikin/Drive-D/Projects/claude-plugin/geminigen-api-client`). **Files:** Modify `scripts/geminigen_client.py` (`VIDEO_SPECS` + grok spec), `mcp_video_server.py`; Test `tests/test_mcp_video_server.py`.
**Steps:**
1. Write failing pytest: `generate_video(model='grok-3', aspect_ratio='vertical', ...)` routes to `uapi/v1/video-gen/grok` with the named-aspect + `mode` enum + `file_urls`/`files`/`ref_images` priority; rejects bad aspect/duration. Expected error: grok model not in registry.
2. Run pytest, confirm fail.
3. Implement grok `VIDEO_SPEC` (per-provider param mapping: `aspect_ratio` named, `mode` style enum, refs priority `files>file_urls>ref_images`, duration 6/10/15, resolution 480p/720p); widen `mcp_video_server` validation + `list_video_models`.
4. Run pytest, confirm pass.
5. Commit: `feat(grok): add GROK video provider to MCP`.
**Verification:**
- [ ] grok routing + validation correct; VEO tests still green; no key hardcoded

---

### Phase K — RAG + plugin version bump
**Est:** 10 min (same repo). **Files:** Create `references/grok-video-best-practices.md`; Modify `references/model-decision-tree.md`, `skills/geminigen-video/SKILL.md`, `package.json` + `.claude-plugin/plugin.json` (version bump), marketplace if bundled.
**Steps:**
1. Write `grok-video-best-practices.md` (contract, image-to-video via file_urls, aspect/mode/duration guidance, "free" note, when-to-use vs VEO).
2. Add GROK row to `model-decision-tree.md`; note grok in `geminigen-video/SKILL.md`.
3. Bump version (patch/minor); `npm run compile-refs` if the references are bundled.
4. Commit: `docs(grok): RAG + version bump`.
**Verification:**
- [ ] Docs cite the confirmed contract; version bumped; refs compile (if bundled)

---

### Phase L — Docs sync
**Est:** 6 min. **Files:** Modify root `CLAUDE.md` (LinkedIn/cross-post + settings sections + Last Updated), this plan's "Phase 0 results".
**Steps:**
1. Add a CLAUDE.md entry describing the IG GROK hook-video feature (cols, job, webhook, Publer mixed media, readiness, UI, MCP/RAG).
2. Commit: `docs: GROK IG hook-video feature`.
**Verification:**
- [ ] CLAUDE.md reflects new routes/cols/jobs/cron; Last Updated bumped

---

### Execution notes
- **Sequencing:** Phase 0 → A → B → C → D → E → F → G → H → I (backend+UI), then J → K (MCP/RAG, independent — `gaspol-parallel` candidate), L last.
- **Tests:** backend in Docker `serversideup/php:8.2-cli` sqlite; frontend `node --test` + `npm run build`; MCP `pytest`. No PHP on dev Mac.
- **Push policy:** commit only; operator authorizes pushes + VPS deploy (migration via `deploy.sh`, plugin VPS recompile + enable per the version-bump gotcha).
- **Carry-over (separate, not in this plan):** the staged "30% opacity" leak fix (backend `CarouselSlideEnhancer` + plugin `hook-visual-library.md`) still needs commit+deploy; draft 158 blue-gradient regenerate needs a result check.
