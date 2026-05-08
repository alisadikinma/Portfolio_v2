# MixPost — 2026 Status Report
**Researched:** 2026-05-08
**Reference:** Was evaluated April 23, 2026 and **rejected for LinkedIn** (only FB+Twitter+Mastodon at that time). Instagram + TikTok status was the key question for this round.

---

## TL;DR

MixPost has substantially expanded platform support since April 2023. As of version 4.5.0 (December 2025), it supports Instagram and TikTok in the Pro and Enterprise tiers. The free Lite edition still only covers Facebook Pages, X (Twitter), and Mastodon. Instagram + TikTok require either a $299 one-time Pro license or $1,199 Enterprise license.

**Critical for this operator:** TikTok's Content Posting API Direct Post feature (needed for non-draft auto-publish) is locked behind:
1. The Enterprise license (not Pro)
2. Submitting MixPost's app through TikTok's App Review process (7–14 days, may require public website + ToS/Privacy Policy URLs)

This is a **platform-imposed restriction** (TikTok requires audited client apps), not a MixPost design choice. Pro users can test in TikTok sandbox mode only.

---

## Version & Activity

| Metric | Value |
|--------|-------|
| Latest version | v4.5.0 (December 29, 2025) |
| GitHub stars | ~3,200 (Lite repo) |
| GitHub license | MIT |
| Primary language | Vue 49% / PHP 44% |
| Last commit | March 16, 2026 (v2.6.0 of standalone app) |
| Maintained by | Inovector (commercial company) |

Source: https://github.com/inovector/mixpost (fetched 2026-05-08)

---

## Platform Support by Tier (2026)

| Platform | Lite (Free) | Pro ($299 OTP) | Enterprise ($1,199 OTP) |
|----------|-------------|----------------|-------------------------|
| Facebook Pages | ✅ | ✅ | ✅ |
| X / Twitter | ✅ | ✅ | ✅ |
| Mastodon | ✅ | ✅ | ✅ |
| Instagram (feed, reels, stories) | ❌ | ✅ | ✅ |
| Instagram Carousels | ❌ | ✅ | ✅ |
| TikTok Video | ❌ | ⚠️ Sandbox only | ✅ Direct Post (after App Review) |
| TikTok Photo Carousel | ❌ | ⚠️ Sandbox only | ✅ Direct Post (after App Review) |
| LinkedIn | ❌ | ✅ | ✅ |
| YouTube | ❌ | ✅ | ✅ |
| Pinterest | ❌ | ✅ | ✅ |
| Threads | ❌ | ✅ | ✅ |
| Bluesky | ❌ | ✅ | ✅ |
| Google Business Profile | ❌ | ✅ | ✅ |

Sources:
- https://mixpost.app/pricing (fetched 2026-05-08)
- https://docs.mixpost.app/services/social/tik-tok/faq/ (fetched 2026-05-08)

---

## 2026 Changelog Highlights

**v4.5.0 (Dec 29, 2025):**
- TikTok AI-generated content option (auto-captions using TikTok's API)
- TikTok title field in text editor
- Environment variables for default timezone, time format, first day of week

**v4.3.0 (Oct 2025):**
- TikTok: support for publishing videos larger than 64MB
- TikTok: navigation button to published TikTok video from preview card

**v4.2.0 (Aug 2025):**
- Instagram: video content in carousel posts (previously images only)

**v4.1.0 (early 2025):**
- Threads: scheduling and publishing support added

**v4.0.x:**
- Chunked uploads for large media files (Feb 2026)
- Download remote media files from URL
- Compatibility with more video formats; image previews in Media Library

Source: https://mixpost.app/releases/pro (fetched 2026-05-08)

---

## TikTok Photo Carousel — Specific Requirements

From the official docs (https://docs.mixpost.app/services/social/tik-tok/):

> "Mixpost supports TikTok photo carousel posts (up to 35 photos per post) in addition to videos."

However:
- TikTok accepts photo carousels **only via `PULL_FROM_URL`** — your media host domain must be on TikTok's verified prefix list
- Default Amazon S3 URLs cannot be verified (need a custom domain)
- This operator uses GeminiGen R2 + custom domain `alisadikinma.com/storage/linkedin-carousel/` — this domain **can** be verified, so the requirement is meetable
- Direct Post (non-sandbox, actually visible to public) requires Enterprise + App Review

---

## API Integration

MixPost Pro ships with a native REST API at `https://{host}/{MIXPOST_CORE_PATH}/api/{workspaceUuid}/posts`.

**Create a carousel post (POST body):**
```json
{
  "versions": [
    {
      "account_id": 123,
      "is_original": true,
      "content": [
        {
          "body": "Caption text here",
          "media": [101, 102, 103]  // media IDs from prior upload
        }
      ]
    }
  ],
  "scheduled_at": "2026-05-10 09:00:00"
}
```

Authentication: `Authorization: Bearer <token>` header.

Instagram platform-specific options: `instagram.post_type` (post|reel|story).
TikTok platform-specific options: `tiktok.privacy_level`, `tiktok.allow_comment`, `tiktok.allow_duet`, `tiktok.allow_stitch`.

Source: https://docs.mixpost.app/api/posts/create/ (fetched 2026-05-08)

Additionally, a community REST API add-on exists: https://github.com/btafoya/mixpost-api (MIT, v1.0.0 Oct 2025, 1 star — provides n8n-oriented endpoints but is community-maintained, not official).

---

## Installation into This Laravel Stack

```bash
composer require inovector/mixpost-pro-team "^4.0"
php artisan mixpost:install
php artisan migrate
```

MixPost runs as a subpath of the existing Laravel app (`/mixpost` by default). The existing queue worker (`portfolio-queue.service`) handles MixPost's scheduled publish jobs automatically — no separate worker needed.

Source: https://docs.mixpost.app/pro/installation/laravel-package/ (searched 2026-05-08)

---

## Cost at 30 Posts/Month

| License | Cost | TikTok |
|---------|------|--------|
| Pro | $299 one-time (year 1 updates included, perpetual fallback) | Sandbox only |
| Enterprise | $1,199 one-time | Direct Post (after App Review) |
| After year 1 | No renewal required to keep using; updates require renewal | — |

For this operator: Pro ($299) covers Instagram carousel fully. TikTok at full Direct Post capability requires Enterprise ($1,199) + ~2 weeks App Review overhead. If TikTok sandbox mode is acceptable for testing and the operator can wait for the Enterprise path, total one-time cost is $1,199. Zero ongoing per-post cost at any volume.

---

## Recommendation

**Best choice if:** operator can absorb $1,199 upfront for Enterprise + 2-week TikTok App Review, values zero monthly fees, wants the tightest Laravel integration (single `composer require`).

**Skip if:** operator needs TikTok live immediately (can't wait for App Review), or budget is constrained to <$300.
