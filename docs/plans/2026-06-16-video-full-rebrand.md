# Video Full Rebrand — `video_full` (talking-head full-regenerate repurpose mode #4)

> Source brief: rebrand a single IG talking-head reel (e.g. https://www.instagram.com/p/DZmqSoRKOQ9/ — vaibhavsisinty)
> into Ali's own Indonesian-language reel. Distinct from the existing `video_rebrand` mode (which re-skins IG **carousels**).
> Date: 2026-06-16 · Status: Design (brainstorm complete) — ready for gaspol-plan.

## Design

### Problem
Take a source talking-head reel and republish it as Ali's content: the on-camera creator becomes Ali, the language becomes Indonesian, the existing b-roll is reused, and hook/CTA are freshly generated. The source has three segment types to handle: full-frame to-camera talking, pure b-roll, and **split-screen** (b-roll top half + creator face bottom half — the bottom must become Ali).

### Locked decisions (from brainstorm, 2026-06-16)
1. **Talking-head strategy = Avatar Ali full dub ID.** Discard the original talking footage entirely; regenerate Ali speaking Indonesian. Reuse only the b-roll.
2. **NO face-swap.** Everything bearing Ali's face is generated fresh via **geminigen.ai** (Veo/Grok + Ali keyframe), not swapped onto source footage.
3. **Voice = Veo-generated → voice-changed to Ali.** Veo produces the talking clips (its own ID audio + matching lips). Post-process each clip's audio through **RVC (local, speech-to-speech)** to convert timbre to Ali's cloned voice — timing/pronunciation preserved so **lips stay matched** and the voice is **consistent across all 8s clips** (solves Veo's per-clip voice-discontinuity). ElevenLabs Voice Changer = quality fallback.
4. **Scope = dedicated new menu + full automation** (not a throwaway POC). 4th repurpose mode `video_full` with its own admin surface.
5. **Generation runs on the LOCAL MacBook** (Whisper + Remotion + RVC + ffmpeg + geminigen MCP). The MacBook is the heavy worker.
6. **Topology = VPS admin + local-worker bridge.** `/admin/video-full` lives in Portfolio_v2 (VPS); the MacBook runs a local agent that polls/claims jobs, does the work, and uploads results back for review/publish.
7. **Trigger = Telegram.** The IG URL is passed via Telegram. Add a **4th mode button "🎥 Video 60s"** alongside the existing carousel / blog / 🎬 video_rebrand buttons (gated `telegram_video_full_enabled`). Target output ≈60s reel. Tapping it creates the `video_full` job in `queued_local` on the VPS for the MacBook worker to claim. (Admin paste-URL optional secondary path.)
8. **Editing toolchain = ffmpeg + Remotion** (headless, reproducible per job; no GUI NLE in the automated path).
9. **B-roll = 2 sources only (no stock, no Veo-for-broll):** Remotion-recreate when animatable (so it's not 100% like Vaibhav's) + cleaned source reuse otherwise, behind a cleaning gate (no Vaibhav face / no brand). Burned-in English subtitle on reused spans → **overlay/timpa** with an opaque ID caption bar sized to cover it (no inpaint).

### Why these (key reasoning surfaced in brainstorm)
- **The lip-sync vs translation conflict:** face-swap preserves source lips (locked to source audio); translation changes audio → lips break. Full avatar regen sidesteps it — Veo authors both face and lips for the new ID line. ✅
- **Veo is NOT an audio-driven dubber:** Veo makes its own audio, 8s max, can't lip-sync to an external voice track. So "Ali speaking MY voice" can't come from Veo directly → resolved by voice-CHANGE (RVC/ElevenLabs s2s) in post, which keeps Veo's timing (lips stay valid) while replacing timbre. Audio-driven models (Kling 3.0 / Seedance 2.0 via geminigen CLI) were considered but not needed given the voice-change approach.
- **Voice-discontinuity mitigation:** also minimize to-camera Veo to hook/CTA/short beats; carry the body with reused b-roll + animated Indonesian captions (Remotion). This is why Whisper (transcript+timing) and Remotion (captions+stitch) are in the stack.

### Pipeline (runs on MacBook worker)
| Stage | Tool | Detail |
|---|---|---|
| Capture | yt-dlp (headless) | Download source reel (proven for IG) |
| ASR | **Whisper** (local install) | EN transcript + word-level timestamps |
| Segment | ffmpeg scene-detect + vision classify | Each segment → `to_camera` / `b_roll` / `split_screen` |
| Translate | Claude CLI | EN → Indonesian, aligned to segment timing → script + caption text |
| Keyframe | indusia-image-gen + Ali face-ref (HTTPS) | Ali still per talking beat / hook / CTA / split-bottom |
| Animate | Veo 3.1 i2v (9:16) — GROK failover | Ali talking ID; **GROK failover** on safety/figure (per veo-audio-celebrity-grok-failover) |
| Voice-change | **RVC** (local) → ElevenLabs fallback | Veo audio → Ali timbre, timing preserved (lips stay matched, consistent voice) |
| B-roll | **Remotion-recreate (preferred) / cleaned source reuse** | Animatable/explainer b-roll → rebuilt in Remotion (own look, not source pixels → not 100% like Vaibhav's); generic live-action → reuse via cleaning gate (no Vaibhav face/brand) |
| Split-screen | ffmpeg `vstack` | Top = source b-roll (crop) + Bottom = Veo Ali strip (generate→crop). Format preserved, face becomes Ali |
| Captions | **Remotion** (local install) | Animated Indonesian captions (timing from Whisper) + brand chrome |
| Compose | Remotion/ffmpeg | Stitch all segments in order → final 9:16 MP4 |
| Publish | **Zernio → LinkedIn/IG/TikTok/Threads** | Single MP4 = simplest Zernio case (all platforms support single video). Reuse `PublishRepurposeViaZernio` + `ZernioPayloadBuilder` (+`buildVideoFull`). LinkedIn-via-Zernio support to verify (else native/flag). Manual download kept as fallback |

### B-roll: 2 sources (no stock, no Veo for b-roll) + cleaning gate
B-roll never comes from stock or Veo. Two sources, chosen per segment:
1. **Remotion-recreate (preferred when animatable)** — explainer/concept/graphic/screen-recording b-roll is **rebuilt** as Remotion motion-graphics in Ali's own look (our pixels, not the source's) → output is **not 100% identical to Vaibhav's** and carries zero face/brand/subtitle from source. Also the default **fallback** for any reuse span that fails the cleaning gate but is animatable.
2. **Cleaned source reuse (live-action / non-animatable)** — ffmpeg-cut from the source reel, gated:
   - **Face gate** — face-detect + recognize Vaibhav (reference face). His face present → drop span, or crop/zoom out if confined to a maskable region. Split-screen bottom (his face) is never reused → replaced by Veo Ali strip; clean top b-roll kept.
   - **Brand/text gate** — OCR + logo/watermark detect for `@vaibhavsisinty`, name, channel logo.
   - **Burned-in subtitle (decided: OVERLAY/timpa)** — source reels carry burned-in English captions. OCR the subtitle bounding box → cover it fully with the Indonesian Remotion caption bar (opaque solid/gradient background sized to fully contain the old box). No inpaint/crop; deterministic; hides English + shows ID in one move. (Only the reuse path needs this; Veo + Remotion-recreate segments have no source subtitle.)
   - Cleaning runs on the MacBook worker (ffmpeg frame sampling + vision/OCR + face model).
- **Fallback when a reuse span is dirty AND uncroppable AND not animatable:** drop (shorten) by default; operator can override per-segment in admin.

### Bridge protocol (VPS ↔ MacBook)
- VPS holds `video_full` jobs (reuse `RepurposeJob` infra + new mode + states). Admin creates a job by pasting an IG URL (or Telegram, gated `telegram_video_full_enabled`). Job starts in `queued_local`.
- MacBook **local agent** (candidate: a Claude Code skill in `/loop`, or a small daemon — has geminigen MCP + Bash for whisper/remotion/rvc/ffmpeg) loop:
  - `GET /api/admin/video-full/claim` (bearer token) → atomic-claim next `queued_local` → receive job spec.
  - `PUT /api/admin/video-full/{id}/progress` → step/percentage/log (admin live view).
  - `POST /api/admin/video-full/{id}/assets` → multipart upload of per-segment previews + final MP4 (or scp to VPS storage + register URL).
- Admin reviews per-segment, can `POST /api/admin/video-full/{id}/regenerate-segment/{n}` → re-queues that segment's local work.
- Heartbeat: if MacBook offline, jobs sit `queued_local`; admin sees "waiting for local worker."

### FSM (proposed)
`queued_local → captured → transcribed → segmented → translated → generating_assets → voice_processing → composing → composed → [published]` (+ `failed`, per-segment retry). Reuse `HasStatusTransitions` + `forceStatus` for operator re-runs (same pattern as RepurposeJob).

### Data Integration Map
| Component | Source | Existing? | Notes |
|---|---|---|---|
| Job FSM / model | `RepurposeJob` pattern (or new `VideoFullJob`) | ✅ reuse pattern | new mode `video_full` + states |
| Veo/GROK i2v | `indusia-video-gen` MCP / geminigen CLI | ✅ | 8s, 9:16; GROK failover |
| Keyframe Ali | `indusia-image-gen` + face-ref | ✅ | HTTPS face-ref gotcha |
| Whisper ASR | — | ❌ install on MacBook | whisper.cpp / openai-whisper |
| Remotion render | — | ❌ install on MacBook | `npx create-video@latest` + Chromium |
| RVC voice change | — | ❌ install on MacBook | train on ~10min Ali audio; ElevenLabs fallback |
| yt-dlp / ffmpeg | MacBook | ✅/install | |
| Bridge endpoints + token | Portfolio_v2 API | ❌ new | claim/progress/assets/regenerate-segment |
| Admin UI | `/admin/video-full` list + detail | ❌ new | timeline, per-segment preview/regen, publish |

### Infra to install (MacBook)
- Whisper (whisper.cpp or `openai-whisper`), Remotion (`npx create-video@latest` + Chromium), RVC (Retrieval-based-Voice-Conversion-WebUI), yt-dlp, ffmpeg. Optional: ElevenLabs API key (fallback). geminigen MCP already configured in this Claude Code session.

### Risks (explicit — calibrate via per-segment regen in admin)
1. **MacBook must be ON + agent running** — single point; nothing processes otherwise.
2. **Large asset upload** over home internet (final MP4 + intermediates).
3. **Local-agent VPS token** security (claim + upload scope).
4. **geminigen access from a headless local agent** must match this session's config.
5. **Veo ID pronunciation** (voice-change preserves mispronunciation), **audio safety filter** (→GROK), **8s stitching seams**, **split-screen reconstruction** (lighting/scale match of bottom Ali strip).
6. **Cost** — many Veo clips per reel; add a budget cap.

### Open questions for gaspol-plan
- Local agent form: Claude Code `/loop` skill vs standalone daemon.
- Asset transport: multipart upload endpoint vs scp/rsync to VPS storage.
- Segment classifier: vision model + heuristics for split-screen geometry detection.
- ~~Remotion vs ffmpeg for captions~~ → DECIDED: **ffmpeg (cut/crop/concat/vstack/audio-mux) + Remotion (animated ID captions + brand chrome + final compose)**. No GUI NLE (incompatible with headless per-job automation; optional operator polish only).
- Whether to share the `repurpose_jobs` table (new mode) or a new `video_full_jobs` table.

### Related memory / vault
- [[veo-audio-celebrity-grok-failover]], [[indusia-image-gen-face-ref-gotcha]], [[ig-video-carousel-rebrand-poc]], [[video-rebrand-build-state]]
- Standards: vault `30-Knowledge/video-pipeline-shared.md` §0 + `10-Identity/visual-identity.md` (hook/CTA/keyframe rules — append plugin bundle, don't hardcode).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

### Goal
Ship `video_full` — the 4th IG-repurpose mode — that turns a single source talking-head reel into Ali's own ≈60s Indonesian reel (Ali's face, Ali's voice timbre, Indonesian captions), triggered from Telegram, reviewed/published from a new `/admin/video-full` surface. This is a **dual-runtime** system: the VPS (Laravel) owns the job record, FSM, Telegram trigger, bridge API, and admin UI; the **MacBook local worker** (Node) owns the heavy pipeline (yt-dlp, Whisper, ffmpeg, geminigen, RVC, Remotion). It is a large build — sequenced so the highest-risk unknowns (Veo voice/ID quality, segment cleaning, the bridge) are validated on the real reel *early*, while every module is production code (no throwaway).

### Architecture Context (from CLAUDE.md + infra map)
- **Reuse (VPS, patterns + classes):** `RepurposeJob` model (add mode `video_full`), `RepurposeJobStatus` enum (`HasStatusTransitions` + `PipelineGuard::advance` are enum-generic), `RepurposeJobController` (mode-agnostic — extend), `repurpose_jobs` table, `useRepurposeJobs.js` + `RepurposeJobDetail.vue`/`SocialStudio.vue` patterns, `TelegramSettingsSeeder` + `telegram_repurpose_enabled` gate pattern, Telegram mode-button flow (carousel/blog/🎬 video_rebrand).
- **Do NOT reuse for heavy lifting:** `GenerateRebrandAssets`, `PollRebrandAssets`, `GeminiGenVideoService`, `VideoChromeRenderer`, `ComposeToolSlides` — these are VPS-resident PHP. `video_full`'s generation runs on the MacBook (Node). The worker re-implements the geminigen HTTP calls (same endpoints `GeminiGenVideoService` hits) + GROK-failover logic ([[veo-audio-celebrity-grok-failover]]) + face-ref gotcha ([[indusia-image-gen-face-ref-gotcha]]) in JS.
- **New tables:** `video_full_segments` (per-segment lifecycle, mirrors `repurpose_video_slides` shape but for a talking/b-roll timeline). Add worker-lifecycle columns to `repurpose_jobs` (or to the new mode rows): `worker_progress`, `worker_step`, `worker_claimed_at`, `worker_heartbeat_at`.

### Tech Stack
- **VPS:** Laravel 12, Pest tests, Sanctum token (new ability `video-full:work`), existing settings/seeder pattern.
- **Local worker (MacBook):** **Node.js daemon** (aligns with Remotion's Node requirement; calls geminigen via HTTP API, shells whisper/ffmpeg/rvc). Vitest for unit tests; manual eval on the source reel for non-deterministic steps. *(ADR fork: Node daemon vs Laravel-artisan-local vs Claude-Code `/loop` skill — Node chosen: Remotion forces Node anyway, no PHP-on-Mac dependency, geminigen via HTTP not MCP so it runs headless/unattended. Capture via `gaspol-adr` at Phase A.)*
- **Local tools to install:** `yt-dlp`, `ffmpeg`, `whisper.cpp` (or `openai-whisper`), Remotion (`npx create-video@latest`), RVC (Retrieval-based-Voice-Conversion-WebUI), `tesseract` (OCR), a face-detect/recognition lib (`@vladmandic/face-api` or python `insightface`).

### Data Integration Map (CONTRACT)

| Feature | Data Source | Hook/API/Class | Exists? | Action |
|---|---|---|---|---|
| Job record + FSM | `RepurposeJob` + `RepurposeJobStatus` | `HasStatusTransitions`, `PipelineGuard` | Yes | Extend: add mode `video_full` + new states |
| Per-segment timeline | `video_full_segments` table | new `VideoFullSegment` model | No | Create real table + model |
| Worker lifecycle fields | `repurpose_jobs` columns | migration | No | Add `worker_progress/step/claimed_at/heartbeat_at` |
| Telegram trigger + buttons | Telegram webhook + mode-button flow | existing repurpose Telegram handler | Yes | Add 4th button "🎥 Video 60s" + callback → create video_full job |
| Setting gate | settings group `telegram` | `TelegramSettingsSeeder` + `setting()` | Yes(pattern) | Add `telegram_video_full_enabled` |
| Bridge API (claim/progress/assets/regen) | new admin/automation routes | new `VideoFullWorkerController` | No | Create real endpoints + Sanctum `video-full:work` token |
| Asset storage | `storage/app/public/video-full/{job}/...` | Laravel Storage | Yes | Use existing disk |
| Source download | yt-dlp | worker module `capture.js` | No | Create (proven yt-dlp path) |
| ASR transcript+timing | Whisper (local) | worker `asr.js` | No | Create |
| Segment classify (to_camera/b_roll/split) | vision LLM (Claude API or geminigen vision) | worker `segment.js` | No | Create + eval |
| Translate EN→ID | Claude API (or VPS) | worker `translate.js` | No | Create + eval |
| Keyframe Ali | geminigen image HTTP + face-ref (HTTPS) | worker `keyframe.js` | Yes(API) | Call existing geminigen endpoint |
| Veo/GROK i2v | geminigen video HTTP | worker `animate.js` | Yes(API) | Call + GROK failover |
| Voice-change → Ali | RVC (local) / ElevenLabs fallback | worker `voice.js` | No | Create |
| B-roll cleaning gate | face-detect + tesseract OCR | worker `clean.js` | No | Create |
| Captions + recreate + compose | Remotion + ffmpeg | worker `compose.js` + Remotion project | No | Create |
| Admin UI | `useRepurposeJobs` patterns | new `useVideoFull.js` + `/admin/video-full` views | No(pattern Yes) | Create following repurpose patterns |
| Publish | Zernio (single MP4) → LI/IG/TikTok/Threads | `PublishRepurposeViaZernio` + `ZernioPayloadBuilder` (+`buildVideoFull`), `captionFor()` per-platform | Yes(IG/TT/TH) | Reuse + add video_full builder; verify Zernio LinkedIn (else native/flag) |

### Phases

> TDD note: PHP phases use Pest (write failing test → see fail → implement → pass → commit). Node phases use Vitest for pure logic; non-deterministic steps (segment classify, translate, hook/CTA author, face/brand thresholds) get an **eval contract** at `docs/evals/video-full.md` (capability + regression fixtures from the source reel) per `gaspol-eval` — `gaspol-verify` runs these.

#### Phase A — Local pipeline core (Node modules) + ADR, validated on the real reel
**Est:** ~6 steps (spike-but-production). **Files:** create `worker/` package (`package.json`, `capture.js`, `asr.js`, `segment.js`, `translate.js`, `lib/manifest.js`), `worker/__tests__/manifest.test.mjs`; `docs/evals/video-full.md`.
**Steps:**
1. Write failing test for `buildSegmentManifest()` (pure: merges Whisper words + scene cuts + classification into ordered segment list). Expected error: `ReferenceError: buildSegmentManifest is not defined`.
2. Run test, confirm fail for that reason.
3. Implement `capture.js` (yt-dlp download of the source URL → mp4), `asr.js` (Whisper → words+timestamps JSON), `segment.js` (ffmpeg scene-detect + vision-classify each span → `to_camera|b_roll|split_screen`), `translate.js` (EN→ID aligned to spans), `lib/manifest.js`.
4. Run unit test, confirm pass; then run the modules manually on `https://www.instagram.com/p/DZmqSoRKOQ9/` → produce `manifest.json`.
5. Capture ADR (`gaspol-adr`): worker runtime = Node daemon (record alternatives + why).
6. Commit: "feat(video-full): local pipeline core — capture/asr/segment/translate + manifest".
**Verification:**
- [ ] Vitest passes; `manifest.json` produced from the real reel with correct segment count + types
- [ ] `docs/evals/video-full.md` exists with segment-classify + translate fixtures from the reel
- [ ] No placeholder/TODO in new code; ADR written

#### Phase B — Asset generation modules (keyframe → Veo/GROK → voice-change)
**Est:** ~6 steps. **Files:** create `worker/keyframe.js`, `worker/animate.js`, `worker/voice.js`, `worker/lib/geminigen.js`, tests `worker/__tests__/geminigen.test.mjs`.
**Steps:**
1. Write failing test for `pickProvider(errorClass)` (Veo→GROK failover decision: figure/audio_filtered → grok). Expected error: `ReferenceError: pickProvider is not defined`.
2. Run, confirm fail.
3. Implement `lib/geminigen.js` (HTTP client to the same image/video endpoints `GeminiGenVideoService` uses, API key from worker env), `keyframe.js` (Ali face-ref **HTTPS URL** per gotcha → 9:16 still), `animate.js` (Veo i2v + GROK failover, poll for completion), `voice.js` (extract Veo audio → RVC speech-to-speech → swap back, timing preserved; ElevenLabs fallback flag).
4. Run unit tests, confirm pass; manual run: one to_camera segment → Ali keyframe → Veo clip → voice-changed clip.
5. Verify the voice-change preserves lip timing (lips still match) on the sample clip.
6. Commit: "feat(video-full): keyframe + Veo/GROK animate + RVC voice-change".
**Verification:**
- [ ] Vitest passes; GROK-failover triggers on audio_filtered/prominent_people classes
- [ ] Sample Ali talking clip generated with Ali face + Ali-timbre voice, lips matched
- [ ] geminigen calls use HTTPS face-ref; secrets from env, none in source

#### Phase C — B-roll cleaning gate + Remotion (captions, recreate, compose)
**Est:** ~7 steps. **Files:** create `worker/clean.js`, `worker/compose.js`, `remotion/` project (captions composition + explainer templates), tests.
**Steps:**
1. Write failing test for `subtitleCoverBox(ocrBoxes)` (computes the opaque caption-bar rect that fully covers detected source subtitle). Expected error: `ReferenceError: subtitleCoverBox is not defined`.
2. Run, confirm fail.
3. Implement `clean.js`: face-gate (detect+recognize Vaibhav reference face → drop/crop span), brand-gate (tesseract OCR `@vaibhavsisinty`/logo → mask/drop), subtitle-gate (OCR box → overlay-cover rect).
4. Implement `remotion/` compositions: animated Indonesian caption track (timed from manifest) with opaque bar covering source subtitle; explainer template library (kinetic-typography, diagram, code-window, stat-counter, comparison, bullet-reveal) for b-roll **recreate**.
5. Implement `compose.js`: ffmpeg cut/crop/`vstack` (split-screen: top clean b-roll + bottom Veo Ali strip) + Remotion render + final stitch → 9:16 MP4.
6. Manual run: produce the full final MP4 for the source reel end-to-end (Phases A+B+C chained). **This is the empirical risk-validation gate** (Veo voice continuity, ID quality, cleaning, seams).
7. Commit: "feat(video-full): cleaning gate + Remotion captions/recreate + compose".
**Verification:**
- [ ] Vitest passes; cleaning gate drops/masks Vaibhav face + brand + covers source subtitle on the reel
- [ ] Final ≈60s 9:16 MP4 produced; no Vaibhav face/brand/EN-subtitle visible; ID captions present
- [ ] Operator-reviewed quality note appended to `docs/evals/video-full.md` (voice/ID/seam acceptability)

#### Phase D — VPS: `video_full` mode + FSM states + segment table (Pest, PHP)
**Est:** ~6 steps. **Files:** modify `app/Enums/RepurposeJobStatus.php`, `app/Models/RepurposeJob.php`; create migration `..._add_video_full_to_repurpose.php` (+ `video_full_segments` table) + `app/Models/VideoFullSegment.php`; test `tests/Feature/VideoFullFsmTest.php`.
**Steps:**
1. Write failing test asserting `RepurposeJob` can transition `queued_local → claimed_local → processing_local → uploaded → composed_local → drafted` and rejects illegal edges. Expected error: enum cases missing / `InvalidStateTransitionException` not thrown as asserted.
2. Run, confirm fail.
3. Add the new `video_full` lifecycle states + transitions to `RepurposeJobStatus::TRANSITIONS` (fork off `extracted`/its own entry); add `worker_progress/step/claimed_at/heartbeat_at` columns + `video_full_segments` table; add `mode='video_full'` handling + `VideoFullSegment` relation.
4. Run tests, confirm pass.
5. Wire `FinalizeRepurpose`/admin to recognize the new mode (Zernio publish target like video_rebrand; per-platform caption fields seeded at finalize via `setCaption()`).
6. Commit: "feat(video-full): RepurposeJob video_full mode + FSM + segments table".
**Verification:**
- [ ] `php artisan migrate` clean; Pest green; illegal transitions throw
- [ ] No placeholder; existing video_rebrand FSM tests still pass

#### Phase E — VPS: bridge API (claim/progress/assets/regenerate-segment) + token (Pest, PHP) — SECURITY-SENSITIVE
**Est:** ~7 steps. **Files:** create `app/Http/Controllers/Api/VideoFullWorkerController.php` + admin counterpart; routes in `routes/api.php`; test `tests/Feature/VideoFullBridgeTest.php`.
**Steps:**
1. Write failing test: `POST /api/worker/video-full/claim` with a valid `video-full:work` token atomically claims one `queued_local` job and returns its spec; second claim returns none. Expected error: 404 route / assertion fail.
2. Run, confirm fail.
3. Implement endpoints: `claim` (atomic `lockForUpdate`), `PUT {id}/progress` (step/pct/log → `worker_*` fields + heartbeat), `POST {id}/assets` (multipart upload per-segment preview + final MP4 → Storage, register URL), `POST {id}/regenerate-segment/{n}` (admin → re-queue that segment for the worker). Mint Sanctum token ability `video-full:work`.
4. Run tests, confirm pass.
5. Add heartbeat/timeout read so admin can show "waiting for local worker / worker offline".
6. Run `gaspol-security-review` on this phase (token scope, upload validation, authz server-side, no path traversal in asset write, size caps).
7. Commit: "feat(video-full): VPS↔MacBook bridge API + worker token".
**Verification:**
- [ ] Pest green; atomic claim prevents double-processing; upload size/type validated
- [ ] Security: token-scoped, inputs validated, asset filenames sanitized, no secrets in source
- [ ] Heartbeat surfaces worker-offline state

#### Phase F — Telegram "🎥 Video 60s" mode button (Pest, PHP)
**Est:** ~5 steps. **Files:** modify the existing repurpose Telegram handler + button keyboard; `VideoFullSettings/TelegramSettingsSeeder` add `telegram_video_full_enabled`; test `tests/Feature/VideoFullTelegramTriggerTest.php`.
**Steps:**
1. Write failing test: an IG URL + "video_full" button callback (when `telegram_video_full_enabled=true`) creates a `RepurposeJob` mode `video_full` status `queued_local`; gate off → ignored. Expected error: assertion fail (button/handler absent).
2. Run, confirm fail.
3. Add the 4th inline button "🎥 Video 60s" beside carousel/blog/🎬, callback creates the job; seed `telegram_video_full_enabled` (default false, idempotent `firstOrCreate`).
4. Run tests, confirm pass.
5. Commit: "feat(video-full): Telegram Video 60s mode button + gate".
**Verification:**
- [ ] Pest green; button creates queued_local job only when gate on; existing 3 buttons unaffected

#### Phase G — Local worker daemon (Node) wiring
**Est:** ~6 steps. **Files:** create `worker/index.js` (poll loop), `worker/config.js`, `worker/README.md` (install + run), `worker/.env.example`; test `worker/__tests__/loop.test.mjs`.
**Steps:**
1. Write failing test for `nextAction(jobState)` (claim vs resume-segment vs upload decision). Expected error: `ReferenceError: nextAction is not defined`.
2. Run, confirm fail.
3. Implement the daemon: poll `claim` → run Phases A→B→C modules per segment with `progress` callbacks + heartbeat → `assets` upload → handle `regenerate-segment` re-queues; backoff + crash-safe resume.
4. Run unit test, confirm pass; run the daemon against a real `queued_local` job end-to-end (Telegram → daemon → uploaded → admin sees result).
5. Write `worker/README.md`: install yt-dlp/ffmpeg/whisper/Remotion/RVC/tesseract + face lib, env (geminigen key, VPS base URL, `video-full:work` token, RVC model path, ElevenLabs fallback key), `node worker` run + keep-alive (launchd/pm2).
6. Commit: "feat(video-full): local worker daemon + ops README".
**Verification:**
- [ ] One full Telegram→worker→VPS round trip succeeds; progress + heartbeat update live
- [ ] README lets a fresh MacBook run the worker; resume-after-crash works

#### Phase H — Admin UI `/admin/video-full` (Vue) — UI phase
**Est:** ~7 steps. **Files:** create `frontend/src/composables/useVideoFull.js`, `frontend/src/views/admin/VideoFullList.vue` + `VideoFullDetail.vue`; router + `AdminLayout.vue` nav entry; (optional) surface in `SocialStudio.vue`.

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| H | list + detail (segment timeline, per-segment preview/regen, worker-status, download) | reuse existing admin design system (tokens, RepurposeJobDetail.vue patterns); invoke `gaspol-design` for the segment-timeline layout | TanStack patterns + design-system compliance |

**Steps:**
1. Write failing test/stub for `useVideoFull` list query (mirrors `useRepurposeJobsList`). Expected error: module not found.
2. Run, confirm fail.
3. Invoke `gaspol-design` for the segment-timeline detail view (states: queued/processing/per-segment done/failed; worker-offline banner).
4. Implement `useVideoFull.js` (list + detail poll while non-terminal/segment mid-flight + regenerate-segment mutation), `VideoFullList.vue`, `VideoFullDetail.vue` (segment timeline, preview, per-segment regen, final download).
5. Add `/admin/video-full` + `/admin/video-full/:id` routes + sidebar nav.
6. Verify against a real uploaded job (live data, not mock).
7. Commit: "feat(video-full): admin UI list + detail + per-segment regen".
**Verification:**
- [ ] Renders real data from the bridge API; poll updates live; regenerate-segment re-queues worker
- [ ] Design-system compliant (no AI-slop); worker-offline state visible

#### Phase I — Publish via Zernio (LI/IG/TikTok/Threads) + settings seeder + runbook
**Est:** ~7 steps. **Files:** modify `app/Services/ZernioPayloadBuilder.php` (add `buildVideoFull`), `app/Jobs/PublishRepurposeViaZernio.php` (widen platforms to incl. linkedin+tiktok for video_full), `app/Http/Controllers/Api/Admin/RepurposeJobController.php` or a video_full controller (publish-zernio + captions actions), `VideoFullDetail.vue` (Approve & Publish + Schedule + per-platform status chips + per-platform ID captions + manual-download fallback); `database/seeders/VideoFullSettingsSeeder.php` wired into `DatabaseSeeder`; `docs/runbooks/video-full-deploy.md`; update root `CLAUDE.md` + vault `hot.md`.
**Steps:**
1. Write failing test: `ZernioPayloadBuilder::buildVideoFull($job)` emits a single-video mediaItem payload for each enabled platform; `PublishRepurposeViaZernio` dispatches per-platform for a video_full job. Expected error: method/branch missing.
2. Run, confirm fail.
3. Implement `buildVideoFull` (single MP4 mediaItem + per-platform ID caption via `captionFor()`); widen `PublishRepurposeViaZernio` + publish endpoint platform validation to `[linkedin, instagram, tiktok, threads]`; **verify Zernio supports a LinkedIn account** — if not, route LinkedIn via native path or flag + drop from the Zernio set (record decision).
4. Run tests, confirm pass.
5. Frontend: "Approve & Publish (LI/IG/TikTok/Threads)" + "Schedule for later" + per-platform publish-status chips + per-platform ID caption editors + manual-download fallback button (mirror `RepurposeJobDetail.vue`'s Zernio UI). Seed video_full settings idempotently.
6. Write `docs/runbooks/video-full-deploy.md` (MacBook worker setup, keep-alive, geminigen/RVC/ElevenLabs keys, `video-full:work` token mint, Zernio keys/accounts, troubleshooting: worker offline, upload stalls, Veo safety-filter, Zernio LinkedIn).
7. Update `CLAUDE.md` (new mode + routes + tables + env + Zernio video_full) + vault `hot.md` + `30-Knowledge/video-pipeline-shared.md`. Commit: "feat(video-full): Zernio publish (LI/IG/TikTok/Threads) + settings + runbook + docs".
**Verification:**
- [ ] Pest green; `buildVideoFull` single-video payload per platform; publish dispatches per-platform
- [ ] Zernio LinkedIn support confirmed (or LinkedIn routed/flagged with recorded decision)
- [ ] Final video publishes to enabled platforms; per-platform status chips update; manual-download fallback works
- [ ] Settings seed idempotent; runbook complete; CLAUDE.md/vault updated

### Execution order & parallelism
- **Sequential risk-first:** A → B → C (validate the real reel end-to-end on the Mac) **before** D–I. C's manual run is the go/no-go on Veo voice/ID quality.
- **Parallelizable after C:** D (FSM) ∥ F (Telegram) are independent; E depends on D; G depends on B+C+E; H depends on E (+ ideally G for live data); I last.
- Phases E (bridge, security) and F/D (PHP) can run via `gaspol-parallel` once C passes.

### Open ADR forks (capture during execution)
- Worker runtime (Node daemon — Phase A ADR).
- Segment-classify + translate provider (Claude API vs geminigen vision) — pick in Phase A, record in eval.
- Reuse `repurpose_jobs` rows vs dedicated `video_full_jobs` table (plan reuses + adds columns — revisit if it crowds the table).
- RVC model hosting + retrain cadence on Ali's voice; ElevenLabs fallback threshold.
