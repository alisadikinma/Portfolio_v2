> **For Claude:** REQUIRED SKILL: gaspol-execute. Real integrations only — no placeholders.

# Publish video_rebrand carousel → Zernio (IG + Threads), Approve + Schedule

## Goal

Give `video_rebrand` repurpose jobs a real publish path to Zernio — the reason the
project switched publisher (Publer/Postiz can't do a multi-clip video carousel;
Zernio can — live-validated 2026-06-15: IG ✅ + Threads ✅ both published a 9-clip
video carousel; TikTok ❌ 400 "single video file only"). Operator gets an
**Approve (publish now)** button and a **Schedule for later** (datetime → Zernio
native `scheduledFor`) button on `/admin/repurpose/{id}` for a composited
video_rebrand job.

## Architecture Context

- `RepurposeJob` (mode=`video_rebrand`, status terminal `drafted`) + `RepurposeVideoSlide`
  rows with `composited_path` = PUBLIC https URLs (`alisadikinma.com/storage/...mp4`).
- Zernio adapter already exists: `ZernioClient::forPlatform()`, `ZernioPayloadBuilder`,
  `PublishViaZernio` (for LinkedIn-carousel siblings). This feature is a parallel,
  sibling-free publish path for repurpose jobs.
- Publish status tracked FSM-neutral (like `composited_status`) — job stays `drafted`.
- Validated facts (memory `zernio-video-carousel-works`): public URLs direct (no upload);
  `publishNow:true` normalizes to `scheduledFor=now`; poll `GET /posts/{id}` for terminal
  status; account id = bare 24-char hex.

## Data Integration Map

| Concern | Source | Exists? | Action |
|---|---|---|---|
| Video clip URLs | `RepurposeVideoSlide.composited_path` ordered by slide_index | Yes | Read directly |
| IG/Threads account id | Setting zernio.zernio_{platform}_account_id | Yes | `ZernioPayloadBuilder::resolveAccountId` |
| Workspace key | Setting zernio.zernio_api_key_{igtt|threads} | Yes | `ZernioClient::forPlatform` |
| Publish enabled gate | config social-cross-post.zernio.enabled | Yes | check in job |
| Caption | RepurposeJob extracted.caption / displayTopic fallback | Yes | new `RepurposeJob::igCaption()` |
| Publish state | NEW `repurpose_jobs.zernio_publish` JSON | No | migration |

## Phases (TDD each)

### Phase A — schema + model
- Migration: nullable JSON `zernio_publish` on `repurpose_jobs` (per-platform `{status, post_id, request_id, url, scheduled_for, error, updated_at}`).
- `RepurposeJob`: `zernio_publish` fillable + `'array'` cast; `igCaption()` accessor; `compositedVideoUrls()` (ordered) helper.
- Test: cast round-trips; igCaption falls back; compositedVideoUrls ordered.
- Verify: migration runs; model test green.

### Phase B — payload builder
- `ZernioPayloadBuilder::buildRepurposeVideoCarousel(RepurposeJob $job, string $platform, ?string $caption=null): array` — video mediaItems from composited URLs (cap IG/Threads to 10), platform entry via `resolveAccountId($platform)`, Threads caption capped 500 (reuse `capThreadsContent`). No firstComment (no article).
- Test: IG payload has N video mediaItems + instagram platform; Threads caption capped; throws when no composited slides.
- Verify: builder test green.

### Phase C — publish job
- `PublishRepurposeViaZernio(int $jobId, string $platform, ?string $scheduledForIso=null)` on `social-crosspost`, tries=3 backoff [60,300,900].
- Gates: zernio.enabled (master), `ZernioPayloadBuilder::isPlatformEnabled($platform)`, idempotent skip when `zernio_publish[platform].post_id` set.
- Persist request_id+status=publishing before call; scheduledFor only when future; createPost; 201→published(post_id,url); 409→published(existingId); 4xx→failed; 5xx→throw.
- Test: publishes-now path persists post_id; future schedule sends scheduledFor; 4xx→failed; idempotent skip; disabled master→skip.
- Verify: job test green.

### Phase D — controller + routes
- `RepurposeJobController::publishZernio(int $id, Request)` — validate `platforms[] in:instagram,threads`, optional `scheduled_at` (future ISO). Guard: job is video_rebrand + all slides composited. Dispatch one job per platform. 202.
- Route `POST /admin/repurpose/{id}/publish-zernio`.
- Test: 202 dispatches per platform (now + scheduled); 422 non-video_rebrand / not-composited / bad platform / past schedule; auth required.
- Verify: controller test green.

### Phase E — frontend
- `useRepurposeJobs.js`: `usePublishRepurposeZernio` mutation.
- `RepurposeJobDetail.vue`: for video_rebrand drafted jobs → "Approve & Publish (IG + Threads)" + "Schedule for later" (datetime modal) buttons + per-platform publish status chips (from `zernio_publish`). TikTok intentionally absent (single-video only — noted in UI hint).
- Verify: build passes; buttons gated to video_rebrand drafted.

### Phase F — docs
- Root CLAUDE.md changelog entry + this plan linked.
- Verify: doc updated.

## Out of scope (v1)
- TikTok single-clip publish (TikTok rejects video carousel). Endpoint is platform-parameterized so it can be added later.
- Caption editing UI (auto-derived v1).
- Auto-publish on job completion (operator-triggered only).
