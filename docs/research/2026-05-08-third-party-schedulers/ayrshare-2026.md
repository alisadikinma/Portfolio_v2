# Ayrshare — 2026 Status Report
**Researched:** 2026-05-08
**Website:** https://www.ayrshare.com
**Docs:** https://www.ayrshare.com/docs/

---

## TL;DR

Ayrshare is the category-leading developer-first SaaS social media API. Its primary value proposition for this operator: **operator does not need to register their own Meta or TikTok developer app, does not need to go through platform App Review, and does not need to wait 4–6 weeks for Instagram access or 2 weeks for TikTok access.** Ayrshare holds pre-approved partner credentials; operators connect their accounts via Ayrshare's OAuth UI in minutes. Confirmed Instagram carousel (up to 10 media items) and TikTok photo posts. PHP SDK available. Main drawback: SaaS-only ($149/mo minimum for 1 brand profile) with no self-hosted option.

---

## Key Differentiator: No Platform App Review Required

From the Ayrshare homepage (fetched 2026-05-08):
> "No need to spend time on complex approval and permission processes, and you do not have to pay the social networks for API access."

This is Ayrshare's core moat. They are an approved Meta Business Partner and TikTok Marketing API Partner. When an operator uses Ayrshare:
1. Operator calls `POST /api/profiles` to create a user profile
2. Operator gets an OAuth connect link from Ayrshare
3. User connects their Instagram/TikTok via Ayrshare's pre-approved OAuth app
4. Operator calls `POST /api/post` with the profile's API key in the header

No Meta Developer account. No TikTok Developer account. No waiting.

**Contrast with self-hosted tools (MixPost, Postiz):** those tools provide the publishing scaffolding but leave the platform approval process to the operator.

Note: Ayrshare introduced "Bring Your Own Keys" (BYOK) for X/Twitter in March 2026, allowing operators to use their own Twitter API credentials. This is opt-in, not required. BYOK for Meta/TikTok not mentioned in 2026 docs.

Sources:
- https://www.ayrshare.com/ (fetched 2026-05-08)
- https://www.ayrshare.com/docs/whatsnew/latest (fetched 2026-05-08)

---

## Instagram Carousel Support

From the 2026 changelog and API docs:

- **Up to 10 media items** per carousel (images, videos, or mix)
- `mediaUrls` array in the POST payload
- **Trial Reels** with graduation strategy (limited visibility that scales up automatically)
- Instagram-specific error codes for rate limits and timeouts (improved reliability April 2026)

**API call shape:**
```json
POST https://app.ayrshare.com/api/post
Authorization: Bearer {PROFILE_API_KEY}
{
  "post": "Caption text #hashtag",
  "platforms": ["instagram"],
  "mediaUrls": [
    "https://alisadikinma.com/storage/linkedin-carousel/alisadikinma-li-28-slide-01-cover.png",
    "https://alisadikinma.com/storage/linkedin-carousel/alisadikinma-li-28-slide-02-body.png",
    "https://alisadikinma.com/storage/linkedin-carousel/alisadikinma-li-28-slide-09-cta.png"
  ],
  "scheduleDate": "2026-05-10T09:00:00Z",
  "instagramOptions": {
    "type": "carousel"
  }
}
```

Source: https://www.ayrshare.com/docs/whatsnew/latest (fetched 2026-05-08); Ayrshare API docs (searched 2026-05-08)

---

## TikTok Photo Mode Support

From April 2026 changelog:
> "TikTok: now post to TikTok with a photo" (confirmed)
> "Sending a post to TikTok drafts now supports images as well as videos"

**TikTok Photo Mode (carousel/slideshow):**
```json
{
  "post": "Caption",
  "platforms": ["tiktok"],
  "mediaUrls": ["url1.png", "url2.png", "url3.png"],
  "tiktokOptions": {
    "privacy": "public",
    "disableComment": false
  }
}
```

Note: The distinction between TikTok "photo carousel" (Photo Mode, user swipes) vs "slideshow" (auto-play with music) is not explicitly called out in Ayrshare's 2026 docs. Given the changelog says "post to TikTok with a photo/images," this likely maps to TikTok Photo Mode (the modern format). Music selection is not mentioned — TikTok's Photo Mode allows users to add their own music after posting; API-published photo carousels typically don't support programmatic music selection as of 2026.

Source: https://www.ayrshare.com/docs/whatsnew/latest (fetched 2026-05-08)

---

## PHP SDK

```bash
composer require ayrshare/social-media-api
```

GitHub: https://github.com/ayrshare/social-media-api

PHP SDK confirmed available. Multiple language SDKs: Node.js, Python, PHP, C#, Go, Java, Ruby.

Laravel integration pattern:
```php
use Ayrshare\SocialPost;

$post = new SocialPost(config('services.ayrshare.api_key'));
$result = $post->postToSocialMedia([
    'post' => $caption,
    'platforms' => ['instagram', 'tiktok'],
    'mediaUrls' => $slideUrls,
    'scheduleDate' => $scheduledAt->toIso8601String(),
]);
```

Source: https://github.com/ayrshare/social-media-api (searched 2026-05-08)

---

## Pricing (2026)

| Tier | Price | Profiles | Notes |
|------|-------|----------|-------|
| Premium | $149/mo | 1 brand profile | 13+ platforms, unlimited scheduled posts, analytics, API |
| Launch | $299/mo | 10 profiles | Multi-user platform features |
| Business | $599/mo | 30 profiles (expandable to 5,000) | Scaling rates: $7.99/profile 31-100, $2.99 101-500, $1.99 500+ |
| Enterprise | Custom | Thousands | Dedicated account manager, compliance |

For this operator (1 brand profile, 30 posts/month): **$149/mo Premium** is the minimum applicable tier.

Annual equivalent: $1,788/year (vs MixPost Pro $299 one-time + Enterprise $1,199 one-time, no ongoing cost).

Source: https://www.ayrshare.com/pricing/ (fetched 2026-05-08)

---

## Webhook Support

Ayrshare supports webhooks for post-publish status callbacks. Operator can register a webhook URL and receive `{status: "success"|"error", platform, postId}` payloads when a scheduled post publishes.

This enables: `instagram_posts.status` and `tiktok_posts.status` columns to be updated server-side without polling.

---

## What Ayrshare Does NOT Cover

- **Self-hosted option**: None. Code is proprietary SaaS. If Ayrshare goes down or raises prices, operator is locked in.
- **BYOK for Meta/TikTok**: Not available (only for X/Twitter as of 2026).
- **Music selection for TikTok**: Not programmable via API; TikTok's API does not expose music selection for API-published posts.
- **Instagram Stories scheduling**: Supported per changelog (images/video).
- **LinkedIn**: Supported (operator already using their own direct API — no need to route through Ayrshare for LinkedIn).

---

## Risk Assessment

| Risk | Level | Mitigation |
|------|-------|------------|
| Price increase | Medium | Currently $149/mo; no historical data on increases in research |
| Vendor lock-in | Medium | API is clean REST; migrating to direct IG/TikTok API later is feasible |
| Platform approval dependency | Low | Ayrshare already approved — their approval remains valid |
| Downtime dependency | Low-Medium | Ayrshare status page exists; 99.9% SLA implied by enterprise tier |
| BYOK mandate (forced for Meta/TikTok) | Low | No current indication Meta/TikTok BYOK is coming |

---

## Recommendation

**Best choice if:** operator cannot wait for Meta/TikTok App Review (4–6 weeks), wants to ship IG + TikTok publishing in days not weeks, and $149/mo is within budget. Also best if the stack is already a commercial one (GeminiGen is SaaS, VPS is managed — adding one more SaaS is coherent).

**Skip if:** operator strictly prefers self-hosted / zero ongoing costs and is willing to invest 4–6 weeks in Meta App Review + 2 weeks TikTok App Review to unlock the self-hosted path via MixPost Enterprise or Postiz.
