# Zernio cross-post expansion — Reddit (4th platform) + Facebook & YouTube (3rd workspace key)

**Date:** 2026-06-16
**Status:** Design (ready for `gaspol-plan`)
**Author:** brainstorm session (gaspol-brainstorm)

> **Scope:** This doc covers TWO related expansions that ship together:
> 1. **Reddit** — 4th Zernio platform, reuses the existing **Threads** workspace key.
> 2. **Facebook + YouTube** — a **3rd Zernio workspace key** (`zernio_api_key_fbyt`); Facebook
>    migrates Publer→Zernio (Publer-FB retired), YouTube is a brand-new platform.
>
> After this, Zernio publishes 6 platforms across 3 workspace keys:
> `igtt` (IG+TikTok) · `threads` (Threads+Reddit) · `fbyt` (Facebook+YouTube).

## Design

### Goal

Add **Reddit** as a 4th Zernio-published cross-post platform alongside Instagram, TikTok,
and Threads. Reddit reuses the **Threads Zernio workspace API key**
(`zernio_api_key_threads`). A new **Reddit** tab appears in the Zernio settings card and in
the per-platform caption tabs on the LinkedIn draft detail.

This is a **PRECEDENT-level** change — a near-exact mirror of the `threads` platform path
shipped 2026-06-15 (commit `Zernio publisher integration`). No new architecture; it extends
the same 6 seams.

### Feasibility research (Zernio Reddit contract — docs.zernio.com/platforms/reddit, fetched 2026-06-16)

| Capability | Supported? | Notes |
|---|---|---|
| Text / link post | ✅ | first line of `content` = title, rest = body |
| Single image | ✅ | |
| Single native video | ✅ | transcoded 1080p/30fps cap; `videogif` option |
| Image gallery (multiple images) | ✅ | **images only** |
| **Video carousel (multi-video)** | ❌ | Reddit has no multi-video post type |
| **Mixed video + image in one post** | ❌ | galleries are image-only |
| Title length | ≤ 300 chars (required, immutable after post) |
| Body length | ≤ 40,000 chars (Reddit Markdown) |
| Subreddit | **required** per post (no profile-feed fan-out by default) |
| Reported failure rate | **53.9%** (AI-content bans, karma gates, mandatory flair) |

`platformSpecificData` fields: `subreddit` (required), `title`, `url` (link post),
`nativeVideo`, `videogif`, `videoPosterUrl`, `flairId`/`flairText`, `nsfw`, `spoiler`,
`forceSelf`. Media via top-level `mediaItems[]` (same shape as IG/TikTok/Threads).

### Locked decisions

1. **Subreddit target = own profile `u_alisadikinma`.** Zero moderation — no AI-content ban,
   no flair, no karma gate, no AutoMod removal, ~100% success. Stored as a single setting
   `zernio_reddit_subreddit` (default `u_alisadikinma`); **no per-draft subreddit picker**.
2. **Auto fan-out, OFF by default.** Reddit sibling is auto-created on the carousel scan like
   the others, but the publisher gate `crosspost_publisher_reddit` defaults to `'off'` so it
   never publishes blind. Operator flips it to `'zernio'` to enable.
3. **Media routing — Reddit posts ONLY for:**
   - **Image carousel** → Reddit **image gallery** (carousel slides; GROK hook video dropped).
   - **`video_full` (single 60s video)** → Reddit **single native video** (`buildVideoFull` is
     already platform-generic).
   - **`video_rebrand` (multi-clip all-video carousel) → SKIPPED** (Reddit can't do multi-video).

### Caption mapping (user spec)

Reddit content model = first line title + body. Store two fields, build payload as
`platformSpecificData.title` + `content` (body):

- **Title** ← TikTok title (`tiktok_post.title`, already capped ≤ 90 — safe under Reddit 300).
- **Body** ← LinkedIn caption (draft `content`, ~1100–1300 — safe under Reddit 40k).

`createReddit` runs **after** `createTiktok` in the scan so the TikTok title is available;
falls back to deriving the title from the draft hook line if no TikTok sibling exists.

### Data Integration Map

| Layer | Element | Existing? | Change |
|---|---|---|---|
| DB | `reddit_posts` table | ❌ | New — mirror `threads_posts` + `zernio_post_id`, `zernio_request_id`, `title`, `subreddit` (snapshot at create), `flair_id` (nullable, unused for profile) |
| Model | `RedditPost` | ❌ | Mirror `ThreadsPost`; add `LinkedInPost::reddit_post` hasOne |
| Settings | `zernio` group (`ZernioSettingsSeeder`) | ✅ | +`zernio_reddit_account_id`, +`zernio_reddit_subreddit`='u_alisadikinma', +`crosspost_publisher_reddit`='off' |
| Client | `ZernioClient::forPlatform()` | ✅ | `reddit → zernio_api_key_threads` (shared Threads workspace) |
| Payload | `ZernioPayloadBuilder::buildReddit(RedditPost)` | ❌ | title+body+`subreddit`+(optional)`flairId`; gallery via `slideMediaItems()`; `isPlatformEnabled('reddit')` gate on `zernio_reddit_account_id` |
| Router | `PublisherResolver` | ✅ | Add `reddit` to ZERNIO_PLATFORMS; support `'off'` value (`for()` returns 'off' → `isPlatformEnabled` false, `dispatchPublish` no-op) |
| Scan | `ScanLinkedInForCrossPost::createReddit()` | ✅ | New branch — carousel + video_full modes only, ordered after createTiktok |
| Job | `PublishViaZernio` | ✅ | Add `reddit` to its platform switch (resolve RedditPost, call buildReddit) |
| Settings API | `GET/PUT/POST verify /admin/settings/zernio` | ✅ | Validate + return 3 new keys; Verify on Threads key auto-fills Reddit account id |
| FE settings | `SettingsForm.vue` Zernio tab | ✅ | +Reddit account-id input, +subreddit input, +publisher selector `[Off, Zernio]` |
| FE draft | `LinkedInDraftDetail.vue` caption tabs | ✅ | Add `reddit` to `VISIBLE_PLATFORMS` + `PLATFORM_META`; reads `draft.reddit_post` (title + body) |

### Deploy-time live-verifies (Reddit-specific risk — do before flipping publisher ON)

1. **Profile-post acceptance** — one-shot Zernio API probe that `subreddit: 'u_alisadikinma'`
   publishes (mirrors the Zernio video-carousel live-probe). If Zernio needs a distinct
   profile flag, adjust `buildReddit` here.
2. **Reddit account in Threads workspace** — confirm Verify on `zernio_api_key_threads`
   returns the Reddit account id (Reddit must be connected in Zernio's dashboard under that
   workspace).

### Security note

The Zernio Threads-workspace API key was pasted in plaintext during this session →
**rotate it in the Zernio dashboard** after deploy (per existing Zernio settings warning +
memory `zernio-reconnect-stale-account-id-403`). Backend always reads the key from the
encrypted `zernio` settings group — never hardcode it.

### Out of scope (YAGNI)

Per-draft subreddit picker · flair UI · Reddit analytics ingest · Publer-Reddit fallback ·
`video_rebrand` → Reddit · NSFW/spoiler flags · DM/comment inbox.

### Testing surface

Mirror the Threads test suite: `RedditPost` model, `ZernioPayloadBuilder::buildReddit`
(title/body/gallery/video_full/account-gate), `PublisherResolver` (`'off'` value + reddit
routing), `ScanLinkedInForCrossPost::createReddit` (carousel + video_full create, video_rebrand
skip, title-from-tiktok + fallback), settings API (3 new keys, masked key, verify auto-fill),
`PublishViaZernio` reddit branch (idempotency via `zernio_post_id` + `zernio_request_id`).

---

## Extension: Facebook + YouTube via 3rd Zernio workspace key

### Goal

Add a **3rd Zernio workspace API key** (`zernio_api_key_fbyt`, value `sk_e4ceed…`) serving
**Facebook** and **YouTube**. Facebook **migrates Publer → Zernio** (the Publer-FB path is
retired); YouTube is a **brand-new** cross-post platform. Both ship **enabled on deploy**.

### Feasibility research (Zernio contracts — docs.zernio.com, fetched 2026-06-16)

**Facebook** (`/platforms/facebook`) — posts to **Pages only** (NOT personal profiles):

| Capability | Supported? | Notes |
|---|---|---|
| Text-only / single image | ✅ | |
| Multi-image post | ✅ | up to **10 images** |
| Multi-link carousel (2–5 cards) | ✅ | images only, per-card link/headline |
| Single video | ✅ | feed video ≤240min |
| Reel | ✅ | single vertical 9:16, **3–60s** |
| First comment | ✅ | feed + reels (not stories); skipped on draft |
| **Video carousel (multi-video)** | ❌ | 1 video per post |
| **Mixed video + image** | ❌ | "You cannot mix images and videos in the same post" |
| Body length | ≤ 63,206 (truncated ~480 "See more") |
| `platformSpecificData` | `contentType` (story/reel), `title` (reel), `firstComment`, `pageId`, `carouselCards`, `geoRestriction` |

**YouTube** (`/platforms/youtube`) — **video-only**, every post requires exactly 1 video:

| Capability | Supported? | Notes |
|---|---|---|
| Single video → **Short** | ✅ | auto-detected when ≤3min **AND** 9:16 vertical (our 60s clip qualifies) |
| Regular (long-form) video | ✅ | not our content |
| **Image / image carousel** | ❌ | no image posts, **no Community posts** |
| **Video carousel (multi-video)** | ❌ | 1 video per post |
| Title | ≤ 100 chars |
| Description | ≤ 5,000 chars |
| First comment | ✅ | auto-posted + pinned |
| AI disclosure | `containsSyntheticMedia` flag — **set TRUE** (clips are AI-generated; YouTube now enforces) |
| `platformSpecificData` | `title`, `visibility` (public), `categoryId` (default 28 Science&Tech), `madeForKids:false`, `containsSyntheticMedia:true`, `firstComment` |

### Media routing (which content mode reaches which platform)

| Content mode | Facebook | YouTube |
|---|---|---|
| **Image carousel** | ✅ multi-image post (≤10 slides) | ❌ skip (YT is video-only) |
| **`video_full` (single 60s)** | ✅ feed video (Reel optional, see note) | ✅ **Short** (60s 9:16) |
| **`video_rebrand` (multi-clip)** | ❌ skip (no multi-video) | ❌ skip |

- **Carousel scan fan-out** targets now: IG · TikTok · Threads · Reddit · **Facebook** (NOT YouTube).
- **`video_full` fan-out** targets: IG · TikTok · Threads · Reddit · Facebook · **YouTube**.
- FB `video_full` defaults to a **feed video** (safe up to 240min); Reel (3–60s) is a flagged
  follow-up — a clip ≥60.x s would be rejected by Reel's hard 60s cap, so feed video first.

### Locked decisions

1. **Facebook = Zernio only.** The Publer-FB publish path is retired; `crosspost_publisher_facebook`
   defaults to `'zernio'`. (The "Facebook always Publer" special-case in `PublisherResolver` is removed.)
2. **Enabled on deploy.** Both `crosspost_publisher_facebook` and `crosspost_publisher_youtube`
   default to `'zernio'` (NOT `'off'`). ⚠️ This makes connecting the FB Page + YT channel in the
   FB+YT workspace a **BLOCKING pre-deploy step** — there is no Publer fallback, so an unconnected
   Page = FB publishing breaks on the first post after deploy.
3. **YouTube = `video_full` only.** It's excluded from the carousel scan entirely; it joins only the
   `video_full` fan-out. Hard platform constraint, not a preference.
4. **AI disclosure ON.** Every YouTube upload sets `containsSyntheticMedia: true`.

### Caption mapping

- **Facebook** body (`content`) ← LinkedIn caption (draft `content`). No FB feed title. Carousel →
  multi-image; first-comment suppressed for repurpose posts (no public article to link, per
  `RepurposeJob::isRepurposePost()` — same rule as the other platforms).
- **YouTube** `title` (≤100) ← TikTok sibling title if present, else first line (hook) of the
  LinkedIn caption, capped 100. `description` (≤5000) ← LinkedIn caption. (Mirrors the Reddit rule.)

### Data Integration Map (delta on top of the Reddit map)

| Layer | Element | Existing? | Change |
|---|---|---|---|
| Settings | `zernio` group | ✅ | +`zernio_api_key_fbyt` (encrypted), +`zernio_facebook_account_id`, +`zernio_youtube_account_id`, +`crosspost_publisher_youtube`='zernio'; **change** `crosspost_publisher_facebook` default → 'zernio' |
| Client | `ZernioClient::forPlatform()` | ✅ | `facebook → zernio_api_key_fbyt`, `youtube → zernio_api_key_fbyt` |
| Router | `PublisherResolver` | ✅ | +`youtube` to ZERNIO_PLATFORMS; **remove** the Facebook→Publer special-case (facebook now resolves via the generic selector) |
| DB | `facebook_posts` table | ✅ | +`zernio_post_id`, +`zernio_request_id` (migrate Publer→Zernio); Publer columns kept (dead weight) |
| DB | **NO** `youtube_posts` table | — | **Correction:** YouTube is **video_full-only**; the video path stores per-platform state in `repurpose_jobs.zernio_publish` JSON (no sibling). No table, no model. |
| Payload | `ZernioPayloadBuilder::buildFacebook(FacebookPost)` | ❌ | body←LinkedIn; carousel via `slideMediaItems()`; `isPlatformEnabled('facebook')` gate on `zernio_facebook_account_id` (carousel path) |
| Payload | `ZernioPayloadBuilder::buildVideoFull()` per-platform `platformSpecificData` | ✅ | +YouTube branch (`title`≤100, `categoryId=28`, `visibility=public`, `madeForKids=false`, `containsSyntheticMedia=true`) + Reddit branch (`subreddit`, `title`); existing single-video body unchanged for FB |
| Scan | `ScanLinkedInForCrossPost::createFacebook()` | ✅ | route the existing FB sibling through `PublisherResolver`→Zernio (was Publer) |
| Video fan-out | `VideoFullController` publish + `PublishRepurposeViaZernio` | ✅ | widen `platforms.* in:` to allow reddit/facebook/youtube; YouTube + Facebook + Reddit become video_full targets (single 60s → Short on YT) |
| Job | `PublishViaZernio` (carousel) | ✅ | +`facebook` branch (resolve sibling, call buildFacebook); Reddit via its own branch (see Reddit map) — NOT youtube |
| Settings API | `GET/PUT/POST verify /admin/settings/zernio` | ✅ | validate + return the 3 new keys; Verify on `zernio_api_key_fbyt` auto-fills FB + YT account ids |
| FE settings | `SettingsForm.vue` Zernio tab | ✅ | +3rd workspace-key (`fbyt`) input + Verify, +`reddit`/`facebook`/`youtube` to `zernioPlatforms` (account-id + publisher selector each) |
| FE draft | `LinkedInDraftDetail.vue` caption tabs | ✅ | +`reddit` + re-show `facebook` in `VISIBLE_PLATFORMS` (carousel siblings). **YouTube has NO caption tab** (video_full-only). |
| FE video | `/admin/video-full` platform targets | ✅ | +reddit/facebook/youtube checkboxes (video_full fan-out target selection) |

### Deploy-time BLOCKING prerequisites (must finish BEFORE the push that cuts FB to Zernio)

1. **FB Page connected** in the FB+YT Zernio workspace (Pages only — a personal profile won't
   publish). Publer-FB is retired, so this is the *only* FB path post-deploy.
2. **YouTube channel connected + upload scope granted** in the same workspace (60s clips are under
   the 15-min unverified cap, so phone-verification is optional, but the channel must not be suspended).
3. **Verify** on `zernio_api_key_fbyt` returns both account ids → confirm auto-fill populates
   `zernio_facebook_account_id` + `zernio_youtube_account_id`.
4. One-shot live probe: a single Zernio publish to FB (multi-image) + YT (60s Short with
   `containsSyntheticMedia:true`) succeeds before relying on the fan-out.

### Security note

The FB+YT workspace key `sk_e4ceed…` was **also pasted in plaintext** this session →
**rotate it in the Zernio dashboard after deploy** (same rule as the Threads key). Backend always
reads it from the encrypted `zernio` settings group — never hardcode.

### Out of scope (YAGNI)

FB Reel auto-routing · FB multi-Page · FB stories · YouTube long-form / thumbnails / playlists /
COPPA-kids · YT image content (impossible) · `video_rebrand` → FB/YT · Publer-FB fallback (retired) ·
FB/YT analytics ingest · inbox/DM/comment management.

### Testing surface (FB + YT additions)

`YoutubePost` model; `ZernioClient::forPlatform` (facebook+youtube → fbyt key);
`ZernioPayloadBuilder::buildFacebook` (carousel multi-image, video_full single video, account-gate,
repurpose first-comment suppression) + `buildYoutube` (single video, title cap 100, desc cap 5000,
synthetic-media flag, account-gate); `PublisherResolver` (facebook no longer Publer-special, youtube
routing, `'off'` still valid); `ScanLinkedInForCrossPost::createFacebook` (Zernio route);
`video_full` fan-out includes FB + YT, excludes video_rebrand; `PublishViaZernio` fb + yt branches
(idempotency via `zernio_post_id` + `zernio_request_id`); settings API (3 new keys, masked fbyt key,
verify auto-fills both account ids); migration adds `facebook_posts.zernio_*` + creates `youtube_posts`.
