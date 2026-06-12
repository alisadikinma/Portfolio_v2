> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# IG Video-Carousel Rebrand → repurpose `video_rebrand` mode

## Goal

Add a 3rd mode `video_rebrand` to the existing Instagram-repurpose pipeline. Operator sends an Instagram **video carousel** URL to the Telegram bot → the system downloads each slide's video, keeps the center 16:9 demo unchanged, and re-skins the header/footer to Ali's brand (logo + cumulative `1·2·3` stepper + big number badge + navy/gold chrome). Hook + CTA slides are generated as Veo 9:16 clips (creator + floating elements), center-cropped to 4:5. Output is a 4:5 video carousel draft surfaced in **Social Studio with per-slide + download-all buttons** — **v1 publish is MANUAL** (operator downloads the ordered slide videos and posts the carousel by hand in the IG app, which natively supports video carousels). **All compositing was already validated in a live POC** (yt-dlp download headless + ffmpeg crop/vstack + Playwright HTML→PNG chrome) — this plan turns the POC into pipeline code.

> **Scope decision (2026-06-12):** ship the **generation + manual-download** path first. **Postiz auto-publish is DEFERRED** to a follow-up (no Postiz deploy / Meta App Review burden for v1). The Postiz path + deploy runbook are kept (Publish Decision below + [docs/runbooks/repurpose-video-rebrand-deploy.md](../runbooks/repurpose-video-rebrand-deploy.md)) for when auto-publish is picked up.

## Publish Decision — Publer is a dead end; **self-hosted Postiz** is the path

The operator's original requirement was "publish to Publer as carousel **video** not image." **Publer cannot do this — live-probed and confirmed (2026-06-12).** A separate research pass found **Postiz** (self-hosted, AGPL-3.0) publishes video carousels correctly, so the publish path adopts Postiz.

### Why Publer fails (live probe 2026-06-12 — all 3 types, all FAILED)

Tested against the real Publer workspace (scheduled far-future, deleted after):
- `network_fields.type:"carousel"` + all-video media → Publer **crashes internally**: `"undefined method 'first' for nil"` (same as the documented mixed failure — not mixed-specific).
- `network_fields.type:"video"` + 2 video media → Publer creates a post but it **fails at Instagram**: `"Error from Instagram: An unknown error has occurred."` (Publer treats `video` type as a single Reel, not a carousel).
- `network_fields.type:"post"` → `"Post type is not valid"`.
- Code corroboration: [`PublerPayloadBuilder::spec()`](../../backend/app/Services/PublerPayloadBuilder.php) only ever sets `network_fields.type` to `"photo"`/`"status"` (no video-carousel path); `publer_ig_mixed_video_enabled` defaults false. **Do not build any Publer video-carousel payload.**

### Why Postiz works (source-verified 2026-06-12)

[`instagram.provider.ts`](https://raw.githubusercontent.com/gitroomhq/postiz-app/main/libraries/nestjs-libraries/src/integrations/social/instagram.provider.ts) `post()` does the **official Meta Graph API video-carousel sequence** that Publer botches:
1. Per video slide → child container `video_url={url}&media_type=VIDEO&is_carousel_item=true`.
2. **Polls each child `status_code` until processing finishes** (30s loop — the step Publer skips).
3. Parent container `media_type=CAROUSEL&children={ids}`, poll, then `media_publish`.
4. Enforces aspect 4:5–1.91:1 (our 1080×1350 slides pass) and 2–10 items.

**Postiz facts that make it the right call:**
- **Free** — AGPL-3.0; self-hosted has **no feature limits** vs cloud; all 32 channels available (just need each platform's own OAuth dev-app keys in `.env`). Runs as its own Docker stack (Postgres + Redis + Temporal + Node) alongside Portfolio_v2.
- **Public API** — self-hosted base `https://{NEXT_PUBLIC_BACKEND_URL}/public/v1`: `POST /upload` (multipart) → `{id,path}`; `POST /posts` envelope (`type:now|schedule`, `integration.id`, `settings.__type:"instagram", post_type:"post"`). API key from Settings → Developers. Rate-limit 90/hr on create-post (`API_LIMIT` env).
- **Claude-drivable** — MCP server (`/mcp`, 9 tools incl. `schedulePostTool`), CLI (`npm i -g postiz`, JSON output), and agent skill (`gitroomhq/postiz-agent`). Backend can call API directly (preferred) or via MCP.

### Out of scope here (recorded as follow-ups, NOT built in this plan)

- **Postiz as cross-post hub** replacing Publer for LinkedIn/X/Threads/Reddit/IG broadly (big migration — separate plan once `video_rebrand` proves Postiz stable in prod).
- **Blog → Medium clone with canonical backlink** — Postiz Medium provider sends `canonicalUrl` (source-verified: `...(settings.canonical ? { canonicalUrl } : {})`), giving SEO `rel=canonical` to alisadikinma.com. Caveat: verify Medium still issues integration tokens for the account.
- **Auto-DM (ManyChat-style comment-keyword→DM)** — Postiz **cannot** do this (no comment listener / no DM send; docs + code confirm). It's a separate Laravel build using the **Meta IG Private Replies API** (`POST /{ig-user-id}/messages` with `recipient:{comment_id}`) + a `comments` webhook, gated on Meta App Review for `instagram_manage_messages`. **Deferred** — until it ships, CTA must stay Save/Share/Comment with NO "comment X to get the link" promise.

## Architecture Context (from code map, 2026-06-12)

**Repurpose FSM** — [`RepurposeJobStatus`](../../backend/app/Enums/RepurposeJobStatus.php): 12 states `received→capturing→captured→extracting→extracted→researching→researched→rewriting→rewritten→finalizing→drafted` (+ every state→`failed`; `failed`→{capturing,captured,extracted,researched,rewritten} per-step retry). `drafted` terminal. [`RepurposeJob`](../../backend/app/Models/RepurposeJob.php) has `mode` = plain nullable string (`'blog'`/`'carousel'` today, validated only in `resolveRepurposeAction`), `slides_path`, JSON `extracted`/`research`/`rewritten`/`pipeline_state_log`, FKs `content_idea_id`/`linkedin_post_id`/`anchor_post_id`, `chat_id`. Uses generic `HasStatusTransitions`.

**Telegram trigger** — [`TelegramWebhookController::handleMessage`](../../backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php): gated by `telegram_repurpose_enabled` + `hash_equals` chat allowlist, `extractInstagramUrl` regex (`/p/|/reel/|/reels/|/tv/`), creates `RepurposeJob(received, mode=null)` → `sendRepurposeModePrompt` (2 signed buttons). `resolveRepurposeAction` validates mode ∈ {blog,carousel}, sets mode, `transitionTo(Capturing)`, dispatches `CaptureInstagramPost`.

**Step jobs** (all default queue, ctor `int $repurposeJobId`, FSM-guarded): Capture→Extract→Research→Rewrite→Finalize. [`FinalizeRepurpose`](../../backend/app/Jobs/FinalizeRepurpose.php) mode-branches `finalizeBlog` (ContentIdea + purge slides) vs `finalizeCarousel` (Post + PostTranslation + LinkedInPost(carousel,pending_generation) in a txn → `GenerateLinkedInPost::dispatch`; **retains** slides for Social Studio).

**Capture** — [`InstagramCaptureService::capture()`](../../backend/app/Services/InstagramCaptureService.php) execs [`scripts/playwright/ig-capture.cjs`](../../scripts/playwright/ig-capture.cjs) (ssh|local), returns `{ok,count,slides[],caption,error}`, saves to `storage/app/repurpose/{id}/`. **Images-only** — DOM scan of `img[src/srcset]` filtered to cdninstagram/fbcdn; NO `<video>` handling. → video capture is a NEW mechanism (yt-dlp, per POC), not an extension of this script.

**LLM CLI** — [`RunsRepurposeClaudeCli::runRepurposeParsed($prompt,$phase,$requiredKeys,$model,$refs)`](../../backend/app/Services/Concerns/RunsRepurposeClaudeCli.php) → `{success,parsed,output,error,repaired}` (one repair-retry; empty-mcp guard). `config/services.php` `repurpose` (driver/ssh/claude_path/empty_mcp_config/model_vision/timeout=900) + `instagram_capture` (driver/ssh/node_path/script_path/timeout).

**Video gen** — [`GeminiGenVideoService`](../../backend/app/Services/GeminiGenVideoService.php) is **GROK-only** today: `dispatchHookVideo($ig,$frameUrl)` POSTs `/video-gen/grok` (model grok-3, aspect 2:3, mode custom, 6s, 720p, keyframe via `file_urls`, `x-api-key`), `prepareFrame` (flatten+pad 4:5→2:3 `#0F59B6`), `finalizeHookVideo` (crop 2:3→4:5, `-an`). **No Veo path** — base url hardcoded `https://api.geminigen.ai/uapi/v1`. Poll lives in [`PollHookVideos`](../../backend/app/Console/Commands/PollHookVideos.php) (`crosspost:poll-hook-videos`, GET `/history/{uuid}`→`generated_video[0].video_url`, stuck>15m→failed, retry cooldown). `instagram_posts.hook_video_{url,status,job_uuid,error,retry_count}`. The MCP `indusia-video-gen` exposes Veo 3.1 (9:16/16:9, 4/6/8s, 720/1080p, frame refs) — but the **backend service has no Veo dispatch**; this plan adds it.

**Publer** — [`PublerClient`](../../backend/app/Services/PublerClient.php): `uploadAndAwaitMedia(url,name)` (`/media/from-url` `{media:[{url,name}],type:single}`→job poll→`media_id`), `publishNow($post)` (`/posts/schedule/publish` `{bulk:{state:scheduled,posts:[…]}}`), `awaitPublishResult` (failure shapes). [`PublishViaPubler`](../../backend/app/Jobs/PublishViaPubler.php) (`social-crosspost` queue, 3 tries, transient classify, media upload loop uses `spec.media_types[i]`→ext mp4|png, `assemblePost` sets `media[].type` from `media_types`). [`PublerPayloadBuilder::spec()`](../../backend/app/Services/PublerPayloadBuilder.php) `network_fields.type` = photo|status only. `config/social-cross-post.php` publer.* keys; account IDs + `publer_ig_mixed_video_enabled` in DB `settings` group=publer. **Publer is NOT used for `video_rebrand` publish** (can't do video carousels — see Publish Decision) — it stays the cross-post path for the existing blog/carousel modes only.

**Postiz** (NEW dependency for `video_rebrand` publish) — self-hosted Docker stack (Postgres + Redis + Temporal + Node) on the VPS, own subdomain (e.g. `postiz.alisadikinma.com`). Connect Ali's IG (Business/Professional, linked FB Page) via a Meta app (`FACEBOOK_APP_ID/SECRET` or standalone `INSTAGRAM_APP_ID/SECRET`). Backend → Postiz **public API** (`/public/v1/upload` multipart → `{id,path}`; `/public/v1/posts` envelope w/ `integration.id` + `settings.__type:"instagram", post_type:"post"`; API key in `Authorization` header). NEW backend service `PostizClient` mirrors `PublerClient` (upload-each-video → create-post → poll). NEW `settings` group=`postiz` (base URL, API key, IG integration id) + `config/services.php` `postiz.*`. No webhook needed — `/posts` is synchronous-enqueue; Postiz handles IG's async child-status polling internally.

**POC reference** — VPS `~/poc-video-rebrand/` (claudesn): `render-chrome.cjs` (working HTML→PNG header/footer + stepper, navy/gold, Space Grotesk), 8 source `*.mp4` test fixtures, validated ffmpeg recipe `crop=720:406:0:339,scale=1080:609` + `vstack` header[1080×508]+center+footer[1080×233]→1080×1350.

## Tech Stack

Laravel 12 (PHP 8.2) backend; queued jobs on `social-crosspost`; FSM via `HasStatusTransitions`; ffmpeg (VPS `/usr/bin/ffmpeg`) for crop/scale/vstack/audio; Playwright + bundled Chromium (`/var/www/Portfolio_v2/node_modules/playwright`) for HTML→PNG chrome; yt-dlp single binary for IG video download; GeminiGen API (Veo) poll-based (webhook never fires per [[geminigen-webhook-never-fires]]); Claude CLI (`RunsRepurposeClaudeCli`) for vision. **No PHP on dev Mac** → every backend test RED→GREEN in Docker `serversideup/php:8.2-cli` sqlite. Operator authorizes pushes.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Telegram `video_rebrand` button | `resolveRepurposeAction` mode allowlist | TelegramWebhookController | Partial | Extend allowlist {blog,carousel,**video_rebrand**} + 3rd signed button |
| Video carousel download | yt-dlp binary on VPS | new `VideoCarouselCaptureService` + cjs/binary | No | Create — yt-dlp per-slide mp4 + poster (POC-proven) |
| Per-slide role + header text | Claude CLI vision on poster frames | `RunsRepurposeClaudeCli::runRepurposeParsed` | Yes (trait) | New `VideoSlideExtractor` service using existing trait |
| Brand chrome PNG | Playwright HTML→PNG | new `VideoChromeRenderer` (from POC `render-chrome.cjs`) | No (POC only) | Port POC script into repo `scripts/playwright/video-chrome.cjs` |
| ffmpeg composite | `/usr/bin/ffmpeg` | new `VideoRebrandComposer` service | No | Create — crop center (auto-detect band) + vstack chrome |
| Hook/CTA Veo clip | GeminiGen `/video-gen/veo` | extend `GeminiGenVideoService` (`dispatchVeoClip` + poll) | Partial | Add Veo dispatch (GROK path is the template) |
| Veo keyframe | `indusia-image-gen` face ref OR static brand frame | TBD at Phase E | Partial | Decide cheapest: brand-frame keyframe vs face-gen |
| Per-slide async tracking | new `repurpose_video_slides` table | Eloquent child of RepurposeJob | No | Create migration + model (mirrors `carousel_slides` shape) |
| FSM video states | `RepurposeJobStatus` | enum + TRANSITIONS | Partial | Add `generating_assets,assets_ready,compositing,composed` (+ retry entrypoints) |
| Finalize draft | `FinalizeRepurpose` video branch | new `finalizeVideoRebrand` | No | Create — store composited slide videos as draft |
| Publish (v1) | Manual — Social Studio download buttons | composited_path → `Storage::url` | Partial | Add per-slide + download-all buttons; operator posts in IG app |
| Publish (deferred) | Self-hosted Postiz `/public/v1` | `PostizClient` (follow-up) | No | Deferred — auto-publish later (Phase 0 deploy gate) |
| Review UI | Social Studio detail | RepurposeJobDetail.vue / SocialStudio.vue | Yes | Extend to show video slides (Phase H) |

## Phases

### Phase 0: Deploy Postiz + connect IG + live video-carousel test — **DEFERRED (not in v1)**

**Status:** NOT a gate for v1. v1 ships generation + manual download (operator posts by hand). This phase belongs to the deferred Postiz auto-publish follow-up. Execution can start directly at **Phase A** — there is no blocking gate.

**Estimated time (when picked up):** 2–4 h (one-time infra, operator-assisted; no repo changes)

> The original Phase 0 (Publer all-video probe) ran 2026-06-12 and **FAILED all 3 type variants** (recorded under "Publish Decision" above). When auto-publish is resumed, this becomes the **Postiz feasibility gate** — prove the replacement publishes a video carousel before writing `PostizClient`. Full steps: [docs/runbooks/repurpose-video-rebrand-deploy.md](../runbooks/repurpose-video-rebrand-deploy.md).

**Files:** none in this repo (Docker deploy + Meta app config on the VPS). Full step-by-step: [docs/runbooks/repurpose-video-rebrand-deploy.md](../runbooks/repurpose-video-rebrand-deploy.md).

**Steps:**
1. Deploy Postiz self-hosted via official docker-compose on the VPS (Postgres + Redis + Temporal + Node), behind a subdomain (e.g. `postiz.alisadikinma.com`) with TLS + reverse proxy forwarding `/public/v1` and (optional) `/mcp` with chunked streaming. Set required env: `DATABASE_URL`, `REDIS_URL`, `JWT_SECRET`, `FRONTEND_URL`, `NEXT_PUBLIC_BACKEND_URL`, `BACKEND_INTERNAL_URL`, `IS_GENERAL=true`, `DISABLE_REGISTRATION=true` (single-operator).
2. Create a Meta app (or reuse the IG/FB app already used for publishing) with scopes `instagram_basic, pages_show_list, pages_read_engagement, business_management, instagram_content_publish, instagram_manage_comments, instagram_manage_insights`; set `FACEBOOK_APP_ID/SECRET` (or standalone `INSTAGRAM_APP_ID/SECRET`); connect Ali's IG channel in Postiz. Add the account as an Instagram Tester if not yet App-Reviewed.
3. Mint a Postiz API key (Settings → Developers → Public API). Manually drive the public API against a **throwaway scheduled-far-future post**: `POST /public/v1/upload` for 2–3 short rebranded mp4 slides → `POST /public/v1/posts` with `type:"schedule"`, far-future date, `settings.__type:"instagram", post_type:"post"`, `value[].image:[{id,path}…]` (the uploaded videos). Confirm it builds the CAROUSEL container without error. Then either let one publish to a private/test IG and verify the live carousel, or delete before the scheduled time.
4. Record the working request/response shape (upload payload, posts envelope, integration id) inline in this plan — that becomes the `PostizClient` contract for Phase G.

**Verification:**
- [ ] Postiz reachable at its subdomain; `/public/v1/integrations` returns Ali's IG channel
- [ ] A real (or test-account) **video carousel** posts successfully via the public API, OR the container builds + polls FINISHED and only `media_publish` is withheld
- [ ] Exact upload + posts payload documented inline for Phase G
- [ ] No unintended live post on the primary account

> **STOP-AND-ASK:** If Postiz fails to publish a video carousel for Ali's account (e.g. App-Review blocks `instagram_content_publish`, or aspect/format rejection), surface to operator before writing Phase G — fallback is the manual Social-Studio draft (operator posts in IG app).

### Phase A: Migration — mode, FSM states, per-slide table

**Estimated time:** 25 min

**Files:**
- Create: `backend/database/migrations/2026_06_13_000001_create_repurpose_video_slides_table.php` (`repurpose_job_id` FK, `slide_index`, `role` enum hook|tool|cta, `source_video_path`, `poster_path`, `header_title`, `header_desc`, `crop_y`, `crop_h`, `veo_job_uuid`, `veo_status`, `veo_url`, `composited_path`, `composited_status`, `last_error`, timestamps)
- Modify: `backend/app/Enums/RepurposeJobStatus.php` (add `GeneratingAssets='generating_assets'`, `AssetsReady='assets_ready'`, `Compositing='compositing'`, `Composed='composed'`; extend TRANSITIONS: `extracted→[researching,generating_assets,failed]`, `generating_assets→[assets_ready,failed]`, `assets_ready→[compositing,failed]`, `compositing→[composed,failed]`, `composed→[finalizing,failed]`; add the 4 to `failed→[…]` retry entrypoints)
- Create: `backend/app/Models/RepurposeVideoSlide.php`
- Test: `backend/tests/Unit/RepurposeJobStatusVideoTransitionsTest.php`

**Steps:**
1. Write failing test asserting `RepurposeJobStatus::Extracted->canTransitionTo(GeneratingAssets)` is true and `GeneratingAssets→Compositing` path exists. Expected error: `Error: Undefined constant ...GeneratingAssets` / assertion false.
2. Run test, confirm fails for that reason.
3. Add enum cases + TRANSITIONS edges + retry entrypoints; create migration + model.
4. Run tests (Docker sqlite), confirm pass.
5. Commit: `feat(repurpose): video_rebrand FSM states + repurpose_video_slides table`

**Verification:**
- [ ] `php artisan migrate` clean on sqlite + MySQL-safe (no enum-ALTER quirks)
- [ ] Existing blog/carousel transitions unchanged (regression test green)
- [ ] No placeholder/TODO; RepurposeVideoSlide `$fillable`/`$casts` complete
- [ ] Security: migration adds no user-facing input surface

### Phase B: Video capture (yt-dlp) — `VideoCarouselCaptureService`

**Estimated time:** 40 min

**Files:**
- Create: `backend/app/Services/VideoCarouselCaptureService.php` (`capture(RepurposeJob): array{ok,count,slides[],error}` — ssh|local exec of yt-dlp, downloads each carousel item mp4 + extracts poster frame via ffmpeg `-ss 2 -frames:v 1`, saves to `storage/app/repurpose/{id}/video/`, ffprobe each for w/h/duration/has-audio)
- Create: `backend/app/Jobs/CaptureVideoCarousel.php` (FSM guard `Capturing`, `social-crosspost` queue, → `Captured` → dispatch `ExtractVideoSlides`)
- Modify: `config/services.php` add `repurpose.ytdlp_path` (env `REPURPOSE_YTDLP_PATH`, default `/home/claudesn/poc-video-rebrand/bin/yt-dlp` → relocate to a stable path)
- Test: `backend/tests/Feature/VideoCarouselCaptureTest.php` (Process::fake yt-dlp+ffprobe, assert per-slide rows created + FSM advance)

**Steps:**
1. Write failing test: capture parses a faked yt-dlp playlist of 3 mp4s into 3 `repurpose_video_slides` rows + advances to `Captured`. Expected error: `Error: Class VideoCarouselCaptureService not found`.
2. Run, confirm fail.
3. Implement service + job (POC commands as the contract — `yt-dlp <url> -o '%(id)s_%(playlist_index)s.%(ext)s'`).
4. Run tests, confirm pass.
5. Commit: `feat(repurpose): yt-dlp video carousel capture`

**Verification:**
- [ ] Slides persisted with `source_video_path`+`poster_path`; FSM `Capturing→Captured`
- [ ] Non-video/login-wall URL → `Failed` + Telegram notice (reuse pattern)
- [ ] Security: yt-dlp invoked with `escapeshellarg`(url); url already host-validated upstream; no shell interpolation of untrusted text
- [ ] Tests green in Docker sqlite

### Phase C: Vision extract — slide role + header text + center-band crop

**Estimated time:** 35 min

**Files:**
- Create: `backend/app/Services/VideoSlideExtractor.php` (per slide: Claude CLI vision on poster via `runRepurposeParsed(requiredKeys:['role','title','desc'])` → role hook|tool|cta + header title/desc; PLUS row-luminance center-band auto-detect via a small ffmpeg/node helper writing `crop_y`/`crop_h`)
- Create: `backend/app/Jobs/ExtractVideoSlides.php` (guard `Captured`→`Extracting`→`Extracted`→dispatch `GenerateRebrandAssets`)
- Test: `backend/tests/Feature/VideoSlideExtractTest.php`

**Steps:**
1. Write failing test: extractor sets `role/header_title/header_desc/crop_y/crop_h` per slide from a faked CLI JSON + faked luminance probe; FSM `Captured→…→Extracted`. Expected error: class not found.
2. Run, confirm fail.
3. Implement (band-detect = the POC `scale=1:H,format=gray` row-classify, ported to a deterministic helper).
4. Run tests, pass.
5. Commit: `feat(repurpose): vision extract slide roles + header text + center-band detect`

**Verification:**
- [ ] Each slide tagged hook/tool/cta with header text; crop band detected (fallback to proportional default if detection ambiguous, logged)
- [ ] No placeholder; CLI uses empty-mcp guard
- [ ] LLM phase: add `docs/evals/video-slide-extract.md` (role-tagging + title-extraction fixtures) per gaspol-eval
- [ ] Tests green in Docker

### Phase D: Chrome renderer + ffmpeg composer

**Estimated time:** 45 min

**Files:**
- Create: `scripts/playwright/video-chrome.cjs` (port of POC `render-chrome.cjs`; params via argv/JSON: `title,desc,activeStep,totalSteps,number,logoPath,handle,site,role` → writes `header.png`+`footer.png`; brand tokens navy `#0F59B6`→gradient + gold `#F5A623`, Space Grotesk, cumulative stepper, big number badge, gold "Geser →" pill; CTA copy = brand engagement, NO "comment to get link")
- Create: `backend/app/Services/VideoChromeRenderer.php` (exec the cjs ssh|local, resolve `creator_brand_logo`/`@handle` from settings)
- Create: `backend/app/Services/VideoRebrandComposer.php` (`composeSlide(RepurposeVideoSlide): string` — ffmpeg `crop=W:crop_h:0:crop_y,scale=1080:609` + `vstack` header/center/footer → 1080×1350, audio preserved; writes `composited_path` under `storage/app/public/repurpose-video/{job}/`)
- Test: `backend/tests/Feature/VideoComposeTest.php` (Process::fake ffmpeg, assert filtergraph string + output path + status flip)

**Steps:**
1. Write failing test asserting `VideoRebrandComposer::buildFilter($slide)` returns the exact `crop=…:{crop_h}:0:{crop_y},scale=1080:609[c];[1:v][c][2:v]vstack=inputs=3` string. Expected error: class not found.
2. Run, confirm fail.
3. Implement renderer + composer (pure `buildFilter` for testability, like `PublerPayloadBuilder`).
4. Run tests, pass.
5. Commit: `feat(repurpose): brand chrome render + ffmpeg video composite`

**Verification:**
- [ ] `buildFilter` unit-tested byte-exact; composited file is 1080×1350 with audio (asserted via ffprobe in an integration smoke on VPS, not CI)
- [ ] Chrome uses real `creator_brand_logo` setting + stepper reflects `activeStep/totalSteps`
- [ ] No "comment to get link" copy anywhere
- [ ] Tests green in Docker

### Phase E: Veo hook/CTA generation + poll

**Estimated time:** 45 min

**Files:**
- Modify: `backend/app/Services/GeminiGenVideoService.php` (add `dispatchVeoClip(string $keyframeUrl, string $prompt, string $aspect='9:16'): ?string` POSTing `/video-gen/veo` with model `veo-3.1`/`veo-3.1-fast`, 6s, 720p, frame ref; + `finalizeVeoClip` crop 9:16→4:5 `crop=iw:floor(iw*5/4/2)*2`… actually 9:16→4:5 needs vertical center-crop `crop=iw:iw*5/4` then verify ratio — pin exact filter in test)
- Create: `backend/app/Jobs/GenerateRebrandAssets.php` (guard `Extracted`→`GeneratingAssets`; for hook+cta slides: build keyframe + `dispatchVeoClip`, store `veo_job_uuid`/`veo_status='generating'`; tool slides need no Veo → mark assets-ready immediately)
- Modify: `backend/app/Console/Commands/PollHookVideos.php` OR new `PollRebrandAssets` (`crosspost:poll-rebrand-assets`) — poll Veo uuids, on all-done advance job `GeneratingAssets→AssetsReady`→dispatch `ComposeVideoCarousel`
- Modify: `config/services.php` add `geminigen.veo_model` (env `GEMINIGEN_VEO_MODEL=veo-3.1-fast`)
- Add scheduler row: `crosspost:poll-rebrand-assets` (`ScheduledCommandSeeder`)
- Test: `backend/tests/Feature/RebrandVeoAssetsTest.php` + `backend/tests/Unit/GeminiGenVeoDispatchTest.php` (Http::fake)

**Steps:**
1. Write failing test: `dispatchVeoClip` posts to `/video-gen/veo` with `veo-3.1-fast`+`9:16`+frame ref and returns uuid. Expected error: method not found.
2. Run, confirm fail.
3. Implement Veo dispatch + asset job + poll advance.
4. Run tests, pass.
5. Commit: `feat(repurpose): Veo hook/CTA clip generation + poll`

**Verification:**
- [ ] Veo dispatch/poll mirrors GROK pattern (circuit breaker honored, poll-only, [[geminigen-webhook-never-fires]])
- [ ] Keyframe decision documented (brand-frame vs face-gen) — STOP-AND-ASK if face-gen cost unclear
- [ ] `crop 9:16→4:5` filter unit-pinned
- [ ] Tests green in Docker; scheduler row idempotent

### Phase F: Compose job + Finalize video branch + Telegram 3rd button

**Estimated time:** 35 min

**Files:**
- Create: `backend/app/Jobs/ComposeVideoCarousel.php` (guard `AssetsReady`→`Compositing`; loop slides → `VideoRebrandComposer::composeSlide`; all done → `Composed`→dispatch `FinalizeRepurpose`)
- Modify: `backend/app/Jobs/FinalizeRepurpose.php` (mode branch: `video_rebrand`→`finalizeVideoRebrand` — guard `Composed` instead of `Rewritten` for this mode; create the draft record holding composited slide video paths; retain slides; Telegram drafted notice)
- Modify: `backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php` + `TelegramNotificationService::sendRepurposeModePrompt` (3rd signed button `video_rebrand`; `resolveRepurposeAction` allowlist + dispatch `CaptureVideoCarousel` when mode=video_rebrand)
- Test: `backend/tests/Feature/FinalizeVideoRebrandTest.php` + `backend/tests/Feature/TelegramVideoRebrandButtonTest.php`

**Steps:**
1. Write failing test: tapping `video_rebrand` button sets mode + dispatches `CaptureVideoCarousel` (not `CaptureInstagramPost`). Expected error: assertion `CaptureVideoCarousel` not dispatched.
2. Run, confirm fail.
3. Implement compose job + finalize branch + button wiring.
4. Run tests, pass.
5. Commit: `feat(repurpose): compose + finalize video_rebrand + Telegram button`

**Verification:**
- [ ] `video_rebrand` finalize guards on `Composed`; blog/carousel paths untouched (regression green)
- [ ] Telegram callback HMAC signed (reuse `signCallback` kind=repurpose); chat allowlist intact
- [ ] Security: signed callbacks verified, mode allowlist strict, no new unauthenticated surface
- [ ] Tests green in Docker

### Phase G: Manual publish — Social Studio download (v1)

**Estimated time:** 30 min

v1 publish = operator downloads the composited slide videos in order and posts the carousel by hand in the IG app. No Postiz, no Publer. Just expose the assets cleanly.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/...` (the controller serving repurpose-job detail) — ensure each `RepurposeVideoSlide.composited_path` resolves to a public `Storage::url` (videos already under `storage/app/public/repurpose-video/{job}/`), exposed in the job-detail API payload ordered by `slide_index`
- Modify: `frontend/src/views/admin/RepurposeJobDetail.vue` — per-slide "⬇ Download" button + a "⬇ Download all (N slides)" action (sequential downloads or a zipped bundle) + a one-line "Post these in order as an IG carousel" hint
- Test: `backend/tests/Feature/RepurposeVideoSlideUrlsTest.php` (detail payload exposes ordered public URLs) + `frontend/src/views/admin/socialStudioHelpers.test.mjs` (download-list helper returns slides in `slide_index` order)

**Steps:**
1. Write failing test: job-detail payload for a `video_rebrand` job returns `slides[]` each with a public `download_url`, ordered by `slide_index`. Expected error: assertion `download_url` missing.
2. Run, confirm fail.
3. Implement URL exposure + download buttons.
4. Run tests, confirm pass.
5. Commit: `feat(repurpose): manual download of composited video_rebrand slides`

**Verification:**
- [ ] Operator can download every composited slide video in slide order from Social Studio
- [ ] Download-all works (sequential or zip); filenames are slide-ordered (e.g. `slide-01.mp4`…)
- [ ] Security: download URLs are public storage paths (no auth-bypass of private data); no publish side-effects
- [ ] Tests green in Docker + node

### Phase G2 (DEFERRED follow-up): Auto-publish via Postiz

Not in v1. When picked up: deploy Postiz (Phase 0 / runbook), then build `PostizClient` + `PostizPayloadBuilder` + `PublishVideoRebrandViaPostiz` (`social-crosspost` queue) against the Phase-0-recorded `/public/v1/upload` + `/posts` contract, gated by `postiz_ig_enabled` + operator approval. `settings` group=postiz (base url, encrypted api key, IG integration id). Manual download (Phase G) stays as the permanent fallback.

### Phase H: Social Studio surfacing + docs sync

**Estimated time:** 25 min

**Files:**
- Modify: `frontend/src/views/admin/RepurposeJobDetail.vue` + `socialStudioHelpers.js` (render `video_rebrand` job: source video slides ↔ composited video slides comparison; `<video>` players)
- Modify: `frontend/src/composables/useRepurposeJobs.js` if payload shape extends
- Modify: root `CLAUDE.md` (new mode + tables + jobs + scheduler row + **manual-download v1 publish**; note Publer can't do video carousels + Postiz auto-publish deferred) — `docs/runbooks/repurpose-video-rebrand-deploy.md` already covers the deferred Postiz deploy
- Test: `frontend/src/views/admin/socialStudioHelpers.test.mjs` (node --test) + `npm run build`

**Steps:**
1. Write failing node test for a `video_rebrand` card mapping helper. Expected error: assertion fail.
2–5. Implement, build, commit.

**Verification:**
- [ ] Detail shows source↔rebranded video comparison; build clean
- [ ] CLAUDE.md Last-Updated entry + Data-flow accurate
- [ ] node tests green; `npm run build` clean

## Red-flag self-check

- Data Integration Map present ✓ · Per-phase verification ✓ · CLAUDE.md-referenced (file paths) ✓ · Real data sources named ✓ · TDD step-1 hard gate per phase ✓ · Publish path RESOLVED (Postiz) with Phase-0 deploy/live-publish gate + STOP-AND-ASK fallback ✓ · No placeholder language ✓.

## Open decisions (resolve before/at execution)

1. **Publish path** — RESOLVED for v1: **manual download** (Phase G — operator posts in IG app). Publer is dead for video carousels; **Postiz auto-publish is the chosen follow-up** (Phase G2 + Phase 0 deploy, source-verified) but explicitly OUT of v1 to avoid the deploy/App-Review burden.
2. **Veo keyframe** — brand-frame (cheap, deterministic) vs face-gen via indusia-image-gen (creator-forward, costs a gen). Default: brand-frame for v1.
3. **Chip color** — gold (brand, default) vs green (source reference). Cosmetic, default gold.

## Follow-up scope (separate plans — NOT in this one)

- **Postiz as central cross-post hub** (operator's stated end-state, 2026-06-12) — publish every carousel/video **simultaneously to LinkedIn + Instagram + TikTok + Threads (+ FB)**, and for blog-post content **also to Medium with canonical**. The 4-platform fan-out ALREADY EXISTS via Publer (`linkedin_force_carousel` sibling creation + `portfolio-crosspost@{1..N}` pool); migration = swap `PublerClient`→`PostizClient` in the existing fan-out + add a Medium lane. Do AFTER `video_rebrand` proves Postiz stable. Real platform gates: **TikTok** Content Posting API needs app audit (pre-audit = draft/SELF_ONLY only, no public auto-post); **IG** `instagram_content_publish` needs Meta App Review; **Medium** integration-token issuance may be closed. All 32 channels free on self-hosted; each needs its own OAuth dev-app keys.
- **Blog → Medium clone w/ canonical backlink** — Postiz Medium provider sends `canonicalUrl` → SEO `rel=canonical` to alisadikinma.com. Gate: verify Medium still issues an integration token for the account.
- **Auto-DM (ManyChat-style comment-keyword → DM)** — NOT Postiz (no comment listener / DM send). Separate Laravel build: Meta IG **Private Replies API** (`POST /{ig-user-id}/messages` `recipient:{comment_id}`) + `comments` webhook, gated on Meta App Review for `instagram_manage_messages`. Until shipped, CTA stays Save/Share/Comment — **no "comment X to get the link" promise**.
