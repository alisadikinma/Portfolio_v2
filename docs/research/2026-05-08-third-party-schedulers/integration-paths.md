# Integration Paths — Top 3 Tools with This Laravel Backend
**Researched:** 2026-05-08
**Assumes:** `instagram_posts` + `tiktok_posts` tables exist (referenced as Phase A/B per task brief)

---

## Option A: MixPost Pro/Enterprise (Laravel Package)

### Auth Path

**Instagram:**
1. Purchase Pro ($299) or Enterprise ($1,199)
2. Create Meta Developer account → Business App → request `instagram_basic`, `instagram_content_publish` scopes
3. Submit Meta App Review → wait 4–6 weeks → Switch to Live mode
4. In MixPost UI: Settings → Accounts → Connect Instagram
5. MixPost stores Instagram access token internally (not exposed to our app)

**TikTok (Enterprise only):**
1. Create TikTok Developer account → app with Content Posting API enabled
2. Verify your domain (for Photo Mode `PULL_FROM_URL`)
3. Submit TikTok Direct Post audit → wait 7–14 days
4. In MixPost UI: Settings → Accounts → Connect TikTok

### API Call Shape (from our Laravel backend)

MixPost runs as a sub-application at `https://alisadikinma.com/mixpost`. Our `instagram_posts` / `tiktok_posts` tables dispatch to MixPost's internal API:

```php
// In a queued job: PublishInstagramCarouselPost
Http::withToken(config('services.mixpost.token'))
    ->post('https://alisadikinma.com/mixpost/api/{workspaceUuid}/posts', [
        'versions' => [
            [
                'account_id' => config('services.mixpost.instagram_account_id'),
                'is_original' => true,
                'content' => [
                    [
                        'body' => $instagramPost->caption,
                        'media' => $this->uploadMediaToMixpost($instagramPost->slide_urls),
                    ]
                ],
            ]
        ],
        'scheduled_at' => $instagramPost->scheduled_at?->toDateTimeString(),
    ]);
```

Media upload is a separate `POST /api/{workspace}/media` call per image; returns media IDs to reference in the post.

### Webhook / Publish Confirmation

MixPost does not currently expose an outbound webhook for publish events. Operator must **poll** MixPost's `GET /api/{workspace}/posts/{postId}` to check `status` column until `published`. Polling interval: every 30s, with a max-timeout failsafe.

Alternative: since MixPost is installed in the same Laravel app, use a Laravel Event listener on MixPost's internal events (if it fires events on publish — check MixPost source). This is the cleanest path for same-app installation.

### Table Interface

```php
// instagram_posts table row → MixPost
$instagramPost->update([
    'status' => 'dispatched',
    'external_ref' => $mixpostPostId,  // store for polling
    'dispatched_at' => now(),
]);

// On polling success:
$instagramPost->update([
    'status' => 'published',
    'published_at' => now(),
    'platform_post_id' => $mixpostData['published_id'],
]);
```

### Cost at 30 Posts/Month

| Scenario | Year 1 | Year 2+ |
|----------|--------|---------|
| Pro (IG only, no TikTok Direct) | $299 | $0 (perpetual fallback) |
| Enterprise (IG + TikTok Direct) | $1,199 | $0 (perpetual fallback) |

No per-post fees, no bandwidth charges. VPS costs unchanged (same app, same worker).

---

## Option B: Postiz (Docker + REST API)

### Auth Path

**Self-hosted path:**
1. Deploy Postiz Docker stack on VPS (separate port, e.g., `scheduler.alisadikinma.com`)
2. Register Meta Developer App → submit for App Review → 4–6 weeks → configure `FACEBOOK_APP_ID` + `FACEBOOK_APP_SECRET` in Postiz `.env`
3. Register TikTok Developer App → resolve scope issue (GitHub #1161: `video.create` scope may need manual workaround) → TikTok audit 7–14 days → configure `TIKTOK_CLIENT_ID` + `TIKTOK_CLIENT_SECRET`
4. In Postiz UI: connect Instagram + TikTok accounts via OAuth
5. Generate Postiz API key from Settings → Developers

**Hosted Postiz cloud ($29/mo) path:**
1. Sign up at postiz.com
2. Connect Instagram and TikTok via Postiz's OAuth UI (no separate developer app needed — Postiz uses their own approved credentials)
3. Generate API key

### API Call Shape (from our Laravel backend)

```php
// Step 1: Upload each slide image
$responses = collect($slideUrls)->map(function ($url) {
    return Http::withHeaders(['Authorization' => config('services.postiz.api_key')])
        ->attach('file', file_get_contents($url), basename($url))
        ->post('https://api.postiz.com/public/v1/upload');
});
$uploadedImages = $responses->map(fn($r) => [
    'id' => $r->json('id'),
    'path' => $r->json('path'),
]);

// Step 2: Create carousel post
$response = Http::withHeaders(['Authorization' => config('services.postiz.api_key')])
    ->post('https://api.postiz.com/public/v1/posts', [
        'type' => 'schedule',
        'date' => $scheduledAt->toIso8601String(),
        'shortLink' => false,
        'tags' => [$instagramChannelId],
        'value' => [
            [
                'content' => $caption,
                'image' => $uploadedImages->toArray(),
            ]
        ],
        'settings' => [
            'instagram' => ['post_type' => 'feed'],
        ],
    ]);
```

For TikTok, change `tags` to TikTok channel ID and `settings.tiktok` block.

**Rate limit:** 30 requests/hour — at 30 posts/month (1/day), this is irrelevant.

### Webhook / Publish Confirmation

Postiz includes webhooks on all plans. Register webhook URL in Postiz dashboard. Postiz will POST to `https://alisadikinma.com/api/automation/postiz-webhook` when a post publishes or fails.

```php
// In routes/api.php
Route::post('/automation/postiz-webhook', [PostizWebhookController::class, 'handle']);

// Webhook payload shape (from Postiz docs, 2026):
// { event: 'post.published', data: { id, platform, status, externalId } }
```

### Table Interface

```php
// On dispatch:
$instagramPost->update([
    'status' => 'dispatched',
    'external_ref' => $postizPostId,
    'dispatched_at' => now(),
]);

// On webhook:
$instagramPost->update([
    'status' => $event === 'post.published' ? 'published' : 'failed',
    'published_at' => $event === 'post.published' ? now() : null,
    'last_error' => $event !== 'post.published' ? $data['error'] : null,
]);
```

### Cost at 30 Posts/Month

| Scenario | Monthly | Annual |
|----------|---------|--------|
| Self-hosted (free) | $0 (VPS overhead ~$0 marginal) | $0 |
| Hosted Standard | $29/mo | $348/yr |

Self-hosted adds ~600-800MB RAM for Temporal + Elasticsearch stack. If current VPS is tight on memory, hosted ($29/mo) avoids infra complexity.

---

## Option C: Ayrshare (REST API + PHP SDK)

### Auth Path

1. Sign up at ayrshare.com ($149/mo Premium)
2. In Ayrshare dashboard: connect Instagram via Ayrshare's OAuth → done (no Meta developer account)
3. In Ayrshare dashboard: connect TikTok via Ayrshare's OAuth → done (no TikTok developer account)
4. Get Profile API key from Ayrshare dashboard
5. Install PHP SDK: `composer require ayrshare/social-media-api`

Total setup time: ~1 hour. No App Review wait.

### API Call Shape (from our Laravel backend)

```php
// config/services.php
'ayrshare' => [
    'api_key' => env('AYRSHARE_PROFILE_API_KEY'),
],

// In PublishInstagramCarouselJob:
$payload = [
    'post' => $instagramPost->caption,
    'platforms' => ['instagram'],
    'mediaUrls' => $instagramPost->slide_urls,  // must be public HTTPS URLs
    'scheduleDate' => $instagramPost->scheduled_at?->toIso8601String(),
    'instagramOptions' => ['type' => 'carousel'],
];
$response = Http::withToken(config('services.ayrshare.api_key'))
    ->post('https://app.ayrshare.com/api/post', $payload);

// For TikTok:
$payload = [
    'post' => $tiktokPost->caption,
    'platforms' => ['tiktok'],
    'mediaUrls' => $tiktokPost->slide_urls,
    'scheduleDate' => $tiktokPost->scheduled_at?->toIso8601String(),
    'tiktokOptions' => ['privacy' => 'public'],
];
```

**Media URL requirement:** Slide images must be publicly accessible HTTPS URLs. Our GeminiGen-generated slides are already stored at `https://alisadikinma.com/storage/linkedin-carousel/*.png` — these are direct HTTPS URLs. No separate upload step needed (unlike Postiz).

### Webhook / Publish Confirmation

Ayrshare supports webhooks. Register `https://alisadikinma.com/api/automation/ayrshare-webhook` in Ayrshare dashboard. Payload on publish includes `status`, `platform`, and `postId`.

Same webhook handler pattern as Postiz above. The `external_ref` stores Ayrshare's `postId` for cross-reference.

### Table Interface

Same pattern as Postiz. `instagram_posts.external_ref` = Ayrshare post ID. Status updated via webhook on publish.

### Existing Posting-Rules Integration

Our `posting_time_rules` table already contains AI-researched optimal posting slots per platform (LinkedIn implemented May 6, 2026). The same `PostingTimeRule::forPlatform('instagram')->optimal()` query can drive `scheduled_at` for Instagram posts. Ayrshare's `scheduleDate` field accepts ISO 8601 UTC — trivial to pass `$optimalSlot->toIso8601String()`.

### Cost at 30 Posts/Month

| Scenario | Monthly | Annual |
|----------|---------|--------|
| Premium (1 profile) | $149/mo | $1,788/yr |
| No per-post fees at this volume | — | — |

Break-even vs MixPost Enterprise: ~8 months ($1,199 MixPost Enterprise ÷ $149/mo Ayrshare). If operator expects to use this beyond 8 months, MixPost Enterprise is cheaper in the long run — but requires the 4–6 week Meta + 2-week TikTok app review upfront investment.

---

## Summary Comparison

| Dimension | MixPost Enterprise | Postiz (self-hosted) | Ayrshare |
|-----------|-------------------|----------------------|----------|
| Time to first Instagram post | 4–6 weeks (Meta App Review) | 4–6 weeks (Meta App Review) | 1–2 hours |
| Time to first TikTok post | 2+ weeks (TikTok audit) | 2+ weeks + scope bug resolution | 1–2 hours |
| Year 1 total cost | $1,199 one-time | $0 (self-hosted) | $1,788 |
| Year 3 total cost | $1,199 (no renewal needed) | $0 | $5,364 |
| Laravel integration | Native package | REST API (cross-service) | REST API (cross-service) |
| Webhooks for publish confirm | Polling only (no webhook) | ✅ Webhooks | ✅ Webhooks |
| TikTok Photo Mode certainty | ✅ Confirmed (Enterprise) | ⚠️ Self-hosted scope bug active | ✅ Confirmed |
| IG carousel certainty | ✅ | ✅ | ✅ |
