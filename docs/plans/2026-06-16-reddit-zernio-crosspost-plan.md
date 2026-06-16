> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations against the live Zernio cross-post
> pipeline. During execution, NEVER substitute placeholders for real data sources without
> explicit user approval. If a data source doesn't exist yet, STOP and ask.
>
> **Companion design:** [2026-06-16-reddit-zernio-crosspost.md](2026-06-16-reddit-zernio-crosspost.md)
> (read its `## Design` + the Reddit + Facebook/YouTube sections first — feasibility, locked
> decisions, media routing, caption mapping, deploy prerequisites, security note live there).

# Implementation Plan — Reddit + Facebook + YouTube via Zernio

## Goal

Extend the existing Zernio cross-post pipeline from 3 platforms (IG/TikTok/Threads) to **6**:
add **Reddit** (reuses the Threads workspace key) and **Facebook + YouTube** (a new 3rd
workspace key `zernio_api_key_fbyt`). Facebook **migrates Publer→Zernio** (Publer-FB retired);
YouTube is **video_full-only** (single 60s → Short). All enabled on deploy except Reddit
(default `off`). Every change mirrors the shipped Threads/TikTok pattern — no new architecture.

## Architecture Context (from CLAUDE.md + code recon)

Two **distinct** publish paths exist; this plan touches both:

| Path | Trigger | Job | Builder | Per-platform state |
|---|---|---|---|---|
| **Carousel** | `ScanLinkedInForCrossPost` creates sibling rows | `PublishViaZernio` | `buildInstagram`/`buildTiktok`/`buildThreads` | sibling tables (`*_posts.zernio_post_id`/`zernio_request_id`) |
| **video_full / repurpose** | `VideoFullController::publishZernio` → per-platform dispatch | `PublishRepurposeViaZernio` | `buildVideoFull(RepurposeJob,$platform)` | `repurpose_jobs.zernio_publish[$platform]` JSON (NO sibling) |

Platform → reach map (design doc): **carousel** → IG·TikTok·Threads·**Reddit**·**Facebook** (NOT
YouTube); **video_full** → IG·TikTok·Threads·**Reddit**·**Facebook**·**YouTube**.

Key existing files (verified by recon, with real signatures):
- `app/Services/ZernioClient.php` — `forPlatform(string)`: ternary `threads→zernio_api_key_threads` else `zernio_api_key_igtt`. Reads `Setting` group `zernio`, decrypts.
- `app/Services/ZernioPayloadBuilder.php` — `buildThreads/buildTiktok/buildInstagram`; `buildVideoFull(RepurposeJob,$platform,?$caption)` (line 217, single video, `content` only, no platformSpecificData); `buildRepurposeVideoCarousel` (line 186); `isPlatformEnabled(string):bool` (static, `match` → `zernio_{platform}_account_id`); `resolveAccountId(string):string` (private, same `match`); `slideMediaItems`; caps `TIKTOK_TITLE_LIMIT=90`, `THREADS_CHAR_LIMIT=500`; `payload(...)` assembles `{content,mediaItems,platforms:[{platform,accountId,platformSpecificData?}]}`.
- `app/Jobs/PublishViaZernio.php` — ctor `($platform,$siblingPostId,?LinkedInPost)`; `handle()` `match($platform)` → `buildInstagram|buildTiktok|buildThreads`; idempotency layer-1 `zernio_post_id`, layer-2 `zernio_request_id`; `applyScheduling()` future→`scheduledFor` else `publishNow`. Queue `social-crosspost`, `$tries=3`, `backoff=[60,300,900]`.
- `app/Jobs/PublishRepurposeViaZernio.php` — ctor `($repurposeJobId,$platform,?$scheduledForIso)`; `handle()` branches `MODE_VIDEO_FULL → buildVideoFull` else `buildRepurposeVideoCarousel`; state via `RepurposeJob::zernio_publish[$platform]` JSON (`mergeState` row-locked); gates on `config('social-cross-post.zernio.enabled')` + `ZernioPayloadBuilder::isPlatformEnabled($platform)`.
- `app/Support/PublisherResolver.php` — `ZERNIO_PLATFORMS=['instagram','tiktok','threads']`; `for()` returns `'publer'` for non-Zernio platforms (facebook/youtube today), else `value==='publer'?'publer':'zernio'`; `isPlatformEnabled`/`publishedIdColumn`/`dispatchPublish`.
- `app/Console/Commands/ScanLinkedInForCrossPost.php` — `createInstagram/createTiktok/createThreads/createFacebook` sibling creators + fan-out loop.
- `database/seeders/ZernioSettingsSeeder.php` — `firstOrCreate(['key'=>...,'group'=>'zernio'],['value'=>...,'type'=>...])`; keys: `zernio_api_key_igtt`, `zernio_api_key_threads`, `zernio_{ig|tiktok|threads}_account_id`, `crosspost_publisher_{ig|tiktok|threads}` (default `'zernio'`).
- `app/Models/{InstagramPost,TiktokPost,ThreadsPost,FacebookPost}.php` + `LinkedInPost.php` (`redditPost?`/`facebookPost`/`instagramPost`/`tiktokPost`/`threadsPost` hasOne; `isRepurpose()`).
- `app/Models/RepurposeJob.php` — `zernio_publish` array cast; `captionFor($platform)`; `MODE_VIDEO_FULL`; `final_video_url`.
- `app/Http/Controllers/Api/Admin/VideoFullController.php` — `publishZernio()` with `platforms.* in:linkedin,instagram,tiktok,threads`.
- Settings API: `routes/api.php` `admin/settings/zernio` (GET/PUT + `/verify`) + its controller.
- `config/social-cross-post.php` — `zernio` section (`ZERNIO_*` env).
- Frontend: `src/views/admin/SettingsForm.vue` (`zernioPlatforms`, `zernioFormData`, `handleZernioVerify`, `autoFillZernioAccountIds`, `handleZernioSubmit`); `src/views/admin/LinkedInDraftDetail.vue` (`VISIBLE_PLATFORMS`, `PLATFORM_META`, `platformPostFor`); `src/stores/settings.js` (`fetchZernioSettings`/`updateZernioSettings`/`verifyZernioConnection`).

## Tech Stack

Laravel 12 + PHP 8.2 + MySQL 8 (Pest tests, `php artisan test --filter`), Vue 3.5 + Pinia +
TanStack Query. Settings encrypted via `Crypt::encryptString`, masked `***SET***` in API.
Follow the **exact** Threads/TikTok mirror for every new method.

## Data Integration Map

| Feature | Data Source | Hook/API/Method | Exists? | Action |
|---|---|---|---|---|
| 3rd workspace key | `Setting{group=zernio,key=zernio_api_key_fbyt}` (encrypted) | `ZernioSettingsSeeder` + `ZernioClient::forPlatform` | No | Create — seed + route facebook/youtube to it |
| Reddit account id | `Setting{…,key=zernio_reddit_account_id}` | `resolveAccountId('reddit')` | No | Create |
| FB account id | `Setting{…,key=zernio_facebook_account_id}` | `resolveAccountId('facebook')` | No | Create |
| YT account id | `Setting{…,key=zernio_youtube_account_id}` | `resolveAccountId('youtube')` | No | Create |
| Reddit subreddit | `Setting{…,key=zernio_reddit_subreddit}`='u_alisadikinma' | `buildReddit`/`buildVideoFull` | No | Create |
| Publisher selectors | `crosspost_publisher_{reddit=off,facebook=zernio,youtube=zernio}` | `PublisherResolver::for` | No | Create |
| Reddit carousel sibling | `reddit_posts` table + `RedditPost` model | `LinkedInPost::redditPost` | No | Create (mirror `ThreadsPost`) |
| FB zernio columns | `facebook_posts.zernio_post_id/zernio_request_id` | migration | No | Create (mirror 2026-06-15 add-zernio migration) |
| YouTube state | `repurpose_jobs.zernio_publish['youtube']` JSON | `PublishRepurposeViaZernio` | **Yes** | Reuse — NO sibling table |
| Reddit payload | `ZernioPayloadBuilder::buildReddit(RedditPost)` | `PublishViaZernio` | No | Create |
| FB payload | `ZernioPayloadBuilder::buildFacebook(FacebookPost)` | `PublishViaZernio` | No | Create |
| YT/Reddit video_full | `buildVideoFull` per-platform `platformSpecificData` | `PublishRepurposeViaZernio` | Partial | Extend (add youtube+reddit branches) |
| Caption: Reddit/YT title | `tiktok_post.title` (≤90) or hook fallback | `createReddit` / `captionFor` | Yes | Reuse |
| Caption: body | LinkedIn draft `content` | `buildReddit`/`buildFacebook` | Yes | Reuse |
| Settings verify | `ZernioClient::forPlatform('…fbyt…')->listAccounts()` | `/admin/settings/zernio/verify` | Yes | Extend (fbyt workspace) |
| Draft caption tabs | `draft.reddit_post` / `draft.facebook_post` | `LinkedInDraftDetail.platformPostFor` | Yes | Extend (`VISIBLE_PLATFORMS`) |
| video_full targets UI | `/admin/video-full` platform checkboxes | VideoFull admin view | Yes | Extend (reddit/facebook/youtube) |

**Contract:** every "No" row is built as a real, working integration mirroring the existing
Threads/TikTok shape. If `FacebookPost`/`facebook_posts`/the VideoFull admin platform-picker turn
out to differ from recon assumptions, **STOP and ask** — do not stub.

---

## Phase Group 1 — Shared Zernio plumbing (3rd key + routing)

### Phase A: Seed the 3rd workspace key + 6 new settings

**Estimated time:** 10 min

**Files:**
- Modify: `backend/database/seeders/ZernioSettingsSeeder.php`
- Modify: `backend/config/social-cross-post.php`
- Test: `backend/tests/Feature/ZernioSettingsSeederTest.php` (create or extend)

**Steps:**
1. Write failing test for the seeder: assert `Setting::where('group','zernio')` contains keys `zernio_api_key_fbyt`, `zernio_reddit_account_id`, `zernio_facebook_account_id`, `zernio_youtube_account_id`, `zernio_reddit_subreddit` (value `u_alisadikinma`), `crosspost_publisher_reddit` (value `off`), `crosspost_publisher_facebook` (value `zernio`), `crosspost_publisher_youtube` (value `zernio`) after `seed(ZernioSettingsSeeder::class)`. Expected error: `Failed asserting that null matches expected 'u_alisadikinma'` (keys absent).
2. Run `php artisan test --filter=ZernioSettingsSeederTest`, confirm it fails for that reason.
3. Add the 8 `firstOrCreate` rows to `ZernioSettingsSeeder` (api key `type='encrypted'` matching the existing fbyt-sibling key handling; selectors/account-ids/subreddit `type='text'`). Do NOT hardcode the literal `sk_…` value — seed `zernio_api_key_fbyt` with `null`/empty (operator pastes it in the UI).
4. Mirror env defaults in `config/social-cross-post.php` `zernio` section: add `ZERNIO_API_KEY_FBYT`, `ZERNIO_REDDIT_ACCOUNT_ID`, `ZERNIO_FACEBOOK_ACCOUNT_ID`, `ZERNIO_YOUTUBE_ACCOUNT_ID`, `ZERNIO_REDDIT_SUBREDDIT` (default `u_alisadikinma`) following the existing key style.
5. Run tests, confirm pass. Commit: `feat(zernio): seed fbyt workspace key + reddit/facebook/youtube settings`

**Verification:**
- [ ] `php artisan test --filter=ZernioSettingsSeederTest` passes
- [ ] Re-seeding is idempotent (firstOrCreate — second seed adds 0 rows; assert count stable)
- [ ] No plaintext `sk_…` value committed anywhere in source
- [ ] Reddit selector default `off`; FB + YT default `zernio`

### Phase B: Route facebook/youtube to the fbyt key

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Services/ZernioClient.php`
- Test: `backend/tests/Unit/ZernioClientForPlatformTest.php` (create)

**Steps:**
1. Write failing test: `ZernioClient::forPlatform('facebook')` + `('youtube')` resolve the key from `zernio_api_key_fbyt`; `('reddit')` from `zernio_api_key_threads`; `('instagram')`/`('tiktok')` from `zernio_api_key_igtt`; `('threads')` from `zernio_api_key_threads`. (Seed distinct fake values per key, assert the client's resolved key matches — expose via a tiny test seam or assert the outbound request header in an HTTP fake.) Expected error: facebook resolves the igtt key (current ternary fall-through).
2. Run `php artisan test --filter=ZernioClientForPlatformTest`, confirm fail.
3. Replace the `forPlatform` ternary with a `match($platform)`: `'threads','reddit' → 'zernio_api_key_threads'`; `'facebook','youtube' → 'zernio_api_key_fbyt'`; `'instagram','tiktok' → 'zernio_api_key_igtt'`; `default → 'zernio_api_key_igtt'` (preserve current fallback).
4. Run tests, confirm pass. Commit: `feat(zernio): forPlatform routes reddit→threads key, fb/yt→fbyt key`

**Verification:**
- [ ] All 6 platform→key mappings asserted green
- [ ] `tsc`-equiv: `php artisan test --filter=ZernioClient` passes, no other Zernio test regresses
- [ ] No placeholder/TODO in the match

### Phase C: Account-id resolution for the 3 new platforms

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Services/ZernioPayloadBuilder.php`
- Test: `backend/tests/Unit/ZernioPayloadBuilderPlatformGateTest.php` (create)

**Steps:**
1. Write failing test: `ZernioPayloadBuilder::isPlatformEnabled('reddit'|'facebook'|'youtube')` returns true when the matching `zernio_{platform}_account_id` is set, false when empty; and (via a public probe or reflection) `resolveAccountId` returns the set id / throws `InvalidArgumentException` when unset. Expected error: `Unknown platform: reddit` (the `match` `default` throws today).
2. Run filter test, confirm fail.
3. Add `'reddit' => 'zernio_reddit_account_id'`, `'facebook' => 'zernio_facebook_account_id'`, `'youtube' => 'zernio_youtube_account_id'` to BOTH the `isPlatformEnabled` and `resolveAccountId` `match` blocks.
4. Run tests, confirm pass. Commit: `feat(zernio): account-id gate + resolution for reddit/facebook/youtube`

**Verification:**
- [ ] 6 assertions (enabled/disabled × 3 platforms) green
- [ ] `resolveAccountId` throws the same `InvalidArgumentException` shape for an unset new platform
- [ ] Existing IG/TikTok/Threads gate tests still pass

### Phase D: PublisherResolver — add platforms + support `off`

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Support/PublisherResolver.php`
- Test: `backend/tests/Unit/PublisherResolverTest.php` (create or extend)

**Steps:**
1. Write failing test: with `crosspost_publisher_reddit='off'` → `for('reddit')==='off'`, `isPlatformEnabled('reddit')===false`, and `dispatchPublish('reddit',…)` dispatches NOTHING (`Bus::fake()` → `assertNothingDispatched`). With `crosspost_publisher_facebook='zernio'` + account set → `for('facebook')==='zernio'`, `dispatchPublish` queues `PublishViaZernio`. `publishedIdColumn('facebook')==='zernio_post_id'`. Expected error: `for('reddit')` returns `'zernio'` (current code maps any non-`publer` value to zernio) / `for('facebook')` returns `'publer'` (not in ZERNIO_PLATFORMS).
2. Run filter test, confirm fail.
3. Add `'reddit','facebook','youtube'` to `ZERNIO_PLATFORMS`. Rewrite `for()` so that for a Zernio platform it returns the raw setting mapped three-way: `'publer'→'publer'`, `'off'→'off'`, else `'zernio'`. Update `isPlatformEnabled` (return false on `'off'`), `dispatchPublish` (no-op + `Log::info` on `'off'`), `publishedIdColumn` (`'off'` still returns `'zernio_post_id'` — harmless; never used since no dispatch).
4. Run tests, confirm pass. Commit: `feat(zernio): PublisherResolver supports off + reddit/facebook/youtube`

**Verification:**
- [ ] `off` → no dispatch, `isPlatformEnabled` false
- [ ] facebook now resolves via the generic selector (default zernio), no longer hard-Publer
- [ ] IG/TikTok/Threads behavior unchanged (regression assertions green)
- [ ] Security: `dispatchPublish('off')` cannot publish (asserted via Bus::fake)

---

## Phase Group 2 — Reddit (carousel sibling path)

### Phase E: `reddit_posts` table + `RedditPost` model

**Estimated time:** 12 min

**Files:**
- Create: `backend/database/migrations/2026_06_16_000001_create_reddit_posts_table.php`
- Create: `backend/app/Models/RedditPost.php`
- Modify: `backend/app/Models/LinkedInPost.php` (add `redditPost()` hasOne)
- Create: `backend/app/Enums/RedditPostStatus.php` (mirror `ThreadsPostStatus`)
- Test: `backend/tests/Feature/RedditPostModelTest.php`

**Steps:**
1. Write failing test: create a `LinkedInPost`, attach a `RedditPost` (fillable: `linkedin_post_id,post_id,status,title,caption,link_comment,hashtags,subreddit,scheduled_at,published_at,external_url,zernio_post_id,zernio_request_id,last_error`), assert `$draft->redditPost->subreddit` round-trips + `hashtags` casts to array. Expected error: `Class "App\Models\RedditPost" not found`.
2. Run filter test, confirm fail.
3. Create the migration mirroring `threads_posts` (id, nullable FK `linkedin_post_id`/`post_id` `nullOnDelete`, status enum, `title`(300 — Reddit cap), `text caption`, `json hashtags`, `string subreddit`(default null, snapshot at create), `scheduled_at`/`published_at`/`external_url`, `zernio_post_id`->unique, `zernio_request_id`, `last_error`, `json pipeline_state_log`, timestamps, softDeletes).
4. Create `RedditPost` model (fillable + casts mirroring `ThreadsPost` + `subreddit`), the `RedditPostStatus` enum, and `LinkedInPost::redditPost(): HasOne`.
5. `php artisan migrate`. Run tests, confirm pass. Commit: `feat(reddit): reddit_posts table + RedditPost model + LinkedInPost relation`

**Verification:**
- [ ] Migration runs clean + rolls back (`migrate:rollback` then re-migrate)
- [ ] `RedditPost` casts `hashtags` json; `subreddit` persists
- [ ] `LinkedInPost::redditPost` returns the row
- [ ] DB: `zernio_post_id` unique index present

### Phase F: `ZernioPayloadBuilder::buildReddit`

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Services/ZernioPayloadBuilder.php`
- Test: `backend/tests/Unit/ZernioBuildRedditTest.php`

**Steps:**
1. Write failing test: `buildReddit(RedditPost)` returns payload where `platforms[0].platform==='reddit'`, `platforms[0].accountId===<reddit account id>`, `platforms[0].platformSpecificData.subreddit==='u_alisadikinma'`, `platformSpecificData.title===<capped ≤300>`, `content===<LinkedIn body>`, `mediaItems` = the carousel slide images (image gallery). Add a cap test: a 320-char title is truncated to ≤300. Expected error: `Call to undefined method …::buildReddit()`.
2. Run filter test, confirm fail.
3. Implement `buildReddit(RedditPost $sibling): array` mirroring `buildThreads`: `slideMediaItems()` for the gallery, `content` = the sibling caption/body, add `platformSpecificData=['subreddit'=>$sibling->subreddit ?? setting('zernio_reddit_subreddit'),'title'=>$this->capRedditTitle($sibling->title)]`. Add `private const REDDIT_TITLE_LIMIT = 300;` + `capRedditTitle()` (mirror `capTiktokTitle`).
4. Run tests, confirm pass. Commit: `feat(reddit): ZernioPayloadBuilder::buildReddit (gallery + subreddit + title cap)`

**Verification:**
- [ ] Payload shape matches Zernio Reddit contract (subreddit required, title ≤300)
- [ ] Gallery = images only (no video) for the carousel path
- [ ] Title cap enforced; body = LinkedIn caption

### Phase G: `PublishViaZernio` Reddit branch

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Jobs/PublishViaZernio.php`
- Test: `backend/tests/Feature/PublishViaZernioRedditTest.php`

**Steps:**
1. Write failing test: dispatch `PublishViaZernio('reddit',$redditPostId)`, fake `ZernioClient` to return `{_id:'rid'}`, assert `reddit_posts.zernio_post_id==='rid'` after run + idempotent re-run skips (no 2nd createPost). Expected error: `Unknown platform: reddit` (handle() match default throws).
2. Run filter test, confirm fail.
3. Add `'reddit' => $builder->buildReddit($sibling)` to the `handle()` match, and add `RedditPost` resolution to the sibling loader (the `loadSibling`/match that maps platform→model).
4. Run tests, confirm pass. Commit: `feat(reddit): PublishViaZernio reddit branch (idempotent)`

**Verification:**
- [ ] Reddit publish persists `zernio_post_id` + reuses `zernio_request_id` on retry
- [ ] Re-dispatch after success is a no-op (layer-1 idempotency)
- [ ] Security: account-id missing → `InvalidArgumentException`, job fails cleanly (no partial post)

### Phase H: `ScanLinkedInForCrossPost::createReddit`

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Console/Commands/ScanLinkedInForCrossPost.php`
- Test: `backend/tests/Feature/CrossPostScanRedditTest.php`

**Steps:**
1. Write failing test: a carousel LinkedIn draft scanned → a `reddit_posts` row is created (after the TikTok sibling) with `title` sourced from the TikTok sibling title (fallback: first hook line of the draft content, capped 300), `caption` = draft content, `subreddit` = `zernio_reddit_subreddit` setting; a `video_rebrand`/text draft creates NO reddit row; idempotent (2nd scan = no duplicate). Expected error: no `reddit_posts` row created.
2. Run filter test, confirm fail.
3. Implement `createReddit()` mirroring `createThreads`/`createTiktok` (carousel-only gate, `hasLiveRedditRow` idempotency, snapshot subreddit at create, title-from-tiktok-with-fallback). Call it in the fan-out loop AFTER `createTiktok`. Respect `PublisherResolver::isPlatformEnabled('reddit')` so `off` → sibling created but not dispatched (or skip creation entirely — match the existing pattern the other platforms use for their gate).
4. Run tests, confirm pass. Commit: `feat(reddit): scan creates reddit sibling (title from tiktok, carousel-only)`

**Verification:**
- [ ] Reddit sibling created only for carousel format; skipped for video_rebrand/text
- [ ] Title falls back to hook line when no TikTok sibling
- [ ] Idempotent (no dup on re-scan)
- [ ] With selector `off`, no publish dispatched (Bus::fake assert)

---

## Phase Group 3 — Facebook (Publer→Zernio cutover)

### Phase I: Add zernio columns to `facebook_posts`

**Estimated time:** 8 min

**Files:**
- Create: `backend/database/migrations/2026_06_16_000002_add_zernio_to_facebook_posts.php`
- Modify: `backend/app/Models/FacebookPost.php` (add `zernio_post_id`,`zernio_request_id` to fillable)
- Test: `backend/tests/Feature/FacebookPostZernioColumnsTest.php`

**Steps:**
1. **First** confirm `FacebookPost` model + `facebook_posts` table exist (recon says `LinkedInPost::facebookPost` exists). If the table/model is absent, STOP and ask (the Publer-FB path shape differs from assumption). Otherwise write failing test: a `FacebookPost` persists `zernio_post_id`/`zernio_request_id`. Expected error: `Unknown column 'zernio_post_id'`.
2. Run filter test, confirm fail.
3. Create the `Schema::table('facebook_posts', …)` migration mirroring the 2026-06-15 add-zernio migration (`hasColumn` guards, `zernio_post_id` nullable unique, `zernio_request_id` nullable). Add both to `FacebookPost::$fillable`.
4. `php artisan migrate`. Run tests, confirm pass. Commit: `feat(facebook): add zernio_post_id/zernio_request_id to facebook_posts`

**Verification:**
- [ ] Migration `hasColumn`-guarded (idempotent on re-run)
- [ ] `FacebookPost` round-trips the new columns
- [ ] Existing Publer columns untouched

### Phase J: `ZernioPayloadBuilder::buildFacebook`

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Services/ZernioPayloadBuilder.php`
- Test: `backend/tests/Unit/ZernioBuildFacebookTest.php`

**Steps:**
1. Write failing test: `buildFacebook(FacebookPost)` returns `platform==='facebook'`, `accountId===<fb id>`, `content===<LinkedIn body>`, `mediaItems` = carousel slide images (multi-image, ≤10), and first-comment SUPPRESSED when the parent `LinkedInPost::isRepurpose()` (mirror buildInstagram's repurpose check) — else `link_comment` becomes `platformSpecificData.firstComment`. Expected error: `Call to undefined method …::buildFacebook()`.
2. Run filter test, confirm fail.
3. Implement `buildFacebook` mirroring `buildInstagram` (without the hook-video lead): `array_slice(slideMediaItems, 0, FB_MAX_IMAGES=10)`, body content, repurpose-aware firstComment. Add `private const FB_MAX_IMAGES = 10;`.
4. Run tests, confirm pass. Commit: `feat(facebook): ZernioPayloadBuilder::buildFacebook (multi-image + repurpose-safe first comment)`

**Verification:**
- [ ] ≤10 images; images-only (no mixed media)
- [ ] firstComment suppressed for repurpose posts (no public article link)
- [ ] body = LinkedIn caption

### Phase K: `PublishViaZernio` Facebook branch + retire Publer-FB

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Jobs/PublishViaZernio.php`
- Modify: `backend/app/Console/Commands/ScanLinkedInForCrossPost.php` (`createFacebook` → Zernio route)
- Test: `backend/tests/Feature/PublishViaZernioFacebookTest.php` + extend `CrossPostScan` test

**Steps:**
1. Write failing test: dispatch `PublishViaZernio('facebook',$fbPostId)` → `facebook_posts.zernio_post_id` set + idempotent; AND scanning a carousel draft routes Facebook via `PublisherResolver`→`PublishViaZernio` (Bus::fake → `assertDispatched(PublishViaZernio)` for facebook, `assertNotDispatched(PublishViaPubler)`). Expected error: `Unknown platform: facebook` (handle) / Publer still dispatched for FB.
2. Run filter test, confirm fail.
3. Add `'facebook' => $builder->buildFacebook($sibling)` to `handle()` match + `FacebookPost` to the sibling loader. In `createFacebook` (scan), ensure dispatch goes through `PublisherResolver::dispatchPublish('facebook',…)` (now → Zernio by default selector) rather than any hardcoded Publer dispatch. Remove/neutralize the Publer-FB dispatch path (keep the `FacebookPost` row creation).
4. Run tests, confirm pass. Commit: `feat(facebook): publish via Zernio (Publer-FB path retired)`

**Verification:**
- [ ] FB carousel publishes via Zernio, persists `zernio_post_id`
- [ ] `PublishViaPubler` no longer dispatched for facebook
- [ ] Idempotent re-publish; account-missing fails cleanly
- [ ] Security: no FB token/secret in source; publisher resolved server-side

---

## Phase Group 4 — YouTube + Reddit + Facebook on the video_full path

### Phase L: `buildVideoFull` per-platform `platformSpecificData`

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Services/ZernioPayloadBuilder.php`
- Test: `backend/tests/Unit/ZernioBuildVideoFullPlatformTest.php`

**Steps:**
1. Write failing test: `buildVideoFull($job,'youtube')` → `platformSpecificData` has `title` (≤100, from `captionFor`/hook), `visibility='public'`, `categoryId='28'`, `madeForKids=false`, `containsSyntheticMedia=true`, `mediaItems=[{video}]`; `buildVideoFull($job,'reddit')` → `platformSpecificData.subreddit='u_alisadikinma'` + `title`; `buildVideoFull($job,'facebook')` → no extra platformSpecificData (plain single video); existing IG/TikTok/Threads output byte-unchanged. Expected error: youtube/reddit produce no `platformSpecificData`.
2. Run filter test, confirm fail.
3. Extend `buildVideoFull`: after building the base, branch by `$platform` to attach `platformSpecificData` (youtube + reddit). Title source = `capYoutubeTitle`/`capRedditTitle` of `captionFor` first line. Pass `platformSpecificData` into `payload(...)`. Keep `threads` cap behavior intact.
4. Run tests, confirm pass. Commit: `feat(zernio): buildVideoFull adds youtube/reddit platformSpecificData (synthetic-media, subreddit)`

**Verification:**
- [ ] YouTube: title ≤100, `containsSyntheticMedia=true` always set
- [ ] Reddit: subreddit + title present
- [ ] IG/TikTok/Threads/Facebook video_full output unchanged (snapshot assert)

### Phase M: VideoFull fan-out widened to reddit/facebook/youtube

**Estimated time:** 10 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/VideoFullController.php`
- Modify: `backend/app/Models/RepurposeJob.php` (`captionFor` handles youtube/reddit if needed)
- Test: `backend/tests/Feature/VideoFullPublishPlatformsTest.php`

**Steps:**
1. Write failing test: `POST` the video_full publish endpoint with `platforms:['youtube','facebook','reddit']` → 3× `PublishRepurposeViaZernio` dispatched (Bus::fake), validation accepts the new platforms; an unknown platform still 422s. Expected error: `422` (validation `in:` excludes youtube/reddit/facebook).
2. Run filter test, confirm fail.
3. Widen the `platforms.* in:` rule to include `reddit,facebook,youtube`. Ensure the dispatch loop dispatches `PublishRepurposeViaZernio($jobId,$platform,$scheduledForIso)` for each (it's already generic). Confirm `RepurposeJob::captionFor('youtube'|'reddit')` returns a sensible caption (falls back to `igCaption()`); add `caption_youtube`/`caption_reddit` resolution only if the existing fallback is insufficient.
4. Run tests, confirm pass. Commit: `feat(video-full): allow youtube/facebook/reddit publish targets`

**Verification:**
- [ ] Endpoint accepts + dispatches youtube/facebook/reddit
- [ ] `PublishRepurposeViaZernio` gates on master switch + `isPlatformEnabled` (account configured)
- [ ] YouTube publish state lands in `repurpose_jobs.zernio_publish['youtube']`
- [ ] Security: endpoint is `auth:sanctum`; platforms validated server-side

---

## Phase Group 5 — Settings API + Frontend

### Phase N: Settings API — 3rd key + verify auto-fill

**Estimated time:** 12 min

**Files:**
- Modify: the zernio settings controller (GET/PUT/verify — path from `routes/api.php`)
- Test: `backend/tests/Feature/ZernioSettingsApiTest.php`

**Steps:**
1. Write failing test: `GET /admin/settings/zernio` returns the 3 new account-id keys + `zernio_api_key_fbyt_configured` flag + masks `zernio_api_key_fbyt` to `***SET***` when set; `PUT` encrypts a new `zernio_api_key_fbyt`, preserves it on empty submit, persists the 3 selectors + account ids; `POST /verify {workspace:'fbyt'}` calls `ZernioClient::forPlatform` on the fbyt key + returns `accounts[]` for FB + YT auto-fill. Expected error: fbyt key not in GET response / verify rejects `fbyt` workspace.
2. Run filter test, confirm fail.
3. Extend the controller: add the 3 new keys to the GET projection + mask/`_configured`; PUT validation + encrypt-on-write + preserve-on-empty for `zernio_api_key_fbyt`; map `workspace='fbyt'` → the fbyt key in `verify`. Keep `auth:sanctum`.
4. Run tests, confirm pass. Commit: `feat(zernio): settings API for fbyt key + reddit/facebook/youtube + verify`

**Verification:**
- [ ] fbyt key masked in GET, encrypted at rest, preserved on empty PUT
- [ ] verify(`fbyt`) returns accounts for auto-fill
- [ ] Security: key never returned in plaintext; route auth-gated; inputs validated

### Phase O: SettingsForm.vue — fbyt workspace + 3 platforms

**Estimated time:** 12 min · **UI phase**

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| O | 3rd key input + Verify, +3 platforms in `zernioPlatforms` | Reuse existing Zernio-tab tokens/layout (no new design) | Build + manual render |

**Files:**
- Modify: `frontend/src/views/admin/SettingsForm.vue`
- Modify: `frontend/src/stores/settings.js` (verify already takes `workspace` — confirm fbyt passes through)

**Steps:**
1. Write failing test (Vitest, if a SettingsForm spec exists; else a `zernioPlatforms`-shape unit assert): `zernioFormData` includes `zernio_api_key_fbyt`, `zernio_{reddit,facebook,youtube}_account_id`, `crosspost_publisher_{reddit,facebook,youtube}`; `zernioPlatforms` contains reddit/facebook/youtube. Expected error: keys absent. (If no test harness for the view, add a tiny exported `buildZernioFormDefaults()` helper and unit-test that.)
2. Run the FE test, confirm fail.
3. Add the `fbyt` API-key input + `handleZernioVerify('fbyt')` button (mirrors igtt/threads), add the 3rd `zernioIgttKeyConfigured`-style computed (`zernioFbytKeyConfigured`), extend `zernioPlatforms` with reddit/facebook/youtube (icons), extend `zernioFormData` + the preserve-on-empty delete in `handleZernioSubmit` for `zernio_api_key_fbyt`. `autoFillZernioAccountIds` already matches by platform key — works for the new 3.
4. Run `npm run build`, confirm clean. Commit: `feat(zernio-ui): 3rd workspace key + reddit/facebook/youtube settings rows`

**Verification:**
- [ ] `npm run build` passes (no type/template errors)
- [ ] Verify on fbyt auto-fills FB + YT account-id fields
- [ ] Reddit selector defaults to `off` in the UI (option list includes Off/Zernio/Publer as applicable)
- [ ] Empty fbyt key on save preserves the stored key

### Phase P: LinkedInDraftDetail.vue — Reddit tab + re-show Facebook

**Estimated time:** 8 min · **UI phase**

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| P | `VISIBLE_PLATFORMS` +reddit +facebook; `PLATFORM_META.reddit` | Reuse existing tab accent tokens | Build + manual render |

**Files:**
- Modify: `frontend/src/views/admin/LinkedInDraftDetail.vue`

**Steps:**
1. Write failing assert (or manual checklist if no spec): `VISIBLE_PLATFORMS` includes `reddit` + `facebook` (NOT `youtube`); `PLATFORM_META.reddit` exists (orange accent). Expected: reddit/facebook absent from the tab strip.
2. Confirm fail (render shows no Reddit tab).
3. Add `reddit` to `PLATFORM_META` (orange) and add `'reddit'` + `'facebook'` to `VISIBLE_PLATFORMS`. `platformPostFor` is generic (reads `draft.{key}_post`) — Reddit (`title`+`content`) + Facebook (`content`) work unchanged. Do NOT add youtube (video_full-only, no sibling).
4. Run `npm run build`, confirm clean. Commit: `feat(crosspost-ui): show Reddit + Facebook caption tabs on the draft detail`

**Verification:**
- [ ] Reddit + Facebook tabs render; YouTube absent (correct — no sibling)
- [ ] Reddit tab shows title + body; Facebook shows body
- [ ] `npm run build` clean

### Phase Q: /admin/video-full — reddit/facebook/youtube targets

**Estimated time:** 10 min · **UI phase**

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| Q | platform-target checkboxes for reddit/facebook/youtube | Reuse video-full admin layout | Build + manual render |

**Files:**
- Modify: the `/admin/video-full` detail view (frontend) that selects publish platforms
- Modify (if present): its composable that posts to the video_full publish endpoint

**Steps:**
1. **First** locate the video-full admin publish UI (grep `video-full` views for the platform picker / the call to the publish endpoint). If it does not yet expose a platform picker (the branch is WIP), STOP and ask how platforms are currently selected. Write failing assert: the platform options include reddit/facebook/youtube. Expected: only linkedin/instagram/tiktok/threads listed.
2. Confirm fail.
3. Add reddit/facebook/youtube to the platform options posted to the (now-widened) video_full publish endpoint.
4. Run `npm run build`, confirm clean. Commit: `feat(video-full-ui): reddit/facebook/youtube publish targets`

**Verification:**
- [ ] The 3 new platforms selectable; selection posts and dispatches
- [ ] YouTube target produces a Short publish (60s vertical) end-to-end in a manual smoke test (deploy)
- [ ] `npm run build` clean

---

## Phase Group 6 — Deploy verification (manual, blocking)

### Phase R: Deploy prerequisites + live probe runbook

**Estimated time:** 10 min (doc) + operator steps at deploy

**Files:**
- Create: `backend/docs/runbooks/zernio-reddit-fb-yt-deploy.md` (or `docs/runbooks/…`)

**Steps:**
1. Write the runbook capturing the design doc's BLOCKING prerequisites: (a) connect FB **Page** + YouTube **channel** in the FB+YT Zernio workspace BEFORE the push (Publer-FB retired → no fallback); (b) paste + `Verify` the fbyt key in `/admin/settings` → auto-fill FB + YT account ids → Save; (c) one-shot live probe — publish a FB multi-image + a YT 60s Short (`containsSyntheticMedia:true`) + a Reddit gallery to `u_alisadikinma`; (d) flip `crosspost_publisher_reddit` `off→zernio` only after the Reddit probe succeeds; (e) **rotate** `sk_e4ceed…` in the Zernio dashboard post-deploy.
2. Cross-link from the design doc's "Deploy-time BLOCKING prerequisites" section.
3. Commit: `docs(zernio): deploy runbook for reddit/facebook/youtube cutover`

**Verification:**
- [ ] Runbook lists the FB-Page/YT-channel connect step as blocking
- [ ] Reddit stays `off` until its probe passes
- [ ] Key-rotation step present
- [ ] No code in this phase — operator checklist only

---

## Red-flag self-check

- ✅ Data Integration Map present (every new source flagged real, YouTube-table correction noted)
- ✅ Every phase has TDD step 1 (`Write failing test … Expected error: …`) + a Verification block
- ✅ Grounded in CLAUDE.md + real recon (exact signatures quoted in Architecture Context)
- ✅ Security lines on auth/secrets/endpoint phases (D, G, K, M, N)
- ✅ Phases ≤12 min; no placeholder language; STOP-and-ask guards on the 3 recon-uncertain spots (FacebookPost shape in I/K, video-full platform picker in Q)

## Execution handoff

- **Sequential dependency:** Group 1 (A–D) is the shared foundation — do first. Groups 2/3/4
  depend on A–D but are independent of each other. Group 5 depends on all backend groups.
- **Parallelizable:** Group 2 (Reddit), Group 3 (Facebook), Group 4 (video_full) can run in
  parallel after Group 1. UI Group 5 phases (O/P/Q) are independent of each other.
- **Commit-only policy:** per project CLAUDE.md — commit each phase, do NOT push. Deploy is
  operator-triggered after Phase R prerequisites.
