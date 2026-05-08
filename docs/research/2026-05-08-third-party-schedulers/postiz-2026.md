# Postiz — 2026 Status Report
**Researched:** 2026-05-08
**Repository:** https://github.com/gitroomhq/postiz-app
**Docs:** https://docs.postiz.com

---

## TL;DR

Postiz is the strongest free open-source contender. AGPL-3.0, 30k+ GitHub stars, Docker Compose deployment, REST API + webhooks included, confirmed Instagram carousel support. TikTok Photo Mode has open GitHub issues on self-hosted instances as of early 2026 (OAuth scope `video.create` missing from TikTok's developer console). The main limitation: **operator must register their own Meta Developer App + TikTok Developer App and pass each platform's independent App Review process** — the same burden as building direct integration, but Postiz provides the OAuth scaffolding code.

---

## Version & Activity

| Metric | Value |
|--------|-------|
| Latest version | v2.21.7 (April 27, 2026) |
| GitHub stars | 30,100+ |
| GitHub license | AGPL-3.0 |
| Primary language | TypeScript / Next.js (NestJS backend) |
| Monthly revenue (founder disclosed) | ~$17k/mo (March 2026 startup story) |
| Founded | 2024 by Nevo David |

Source: https://github.com/gitroomhq/postiz-app (fetched 2026-05-08); https://www.thestartupstorys.com/2026/03/nevo-david-postiz-open-source-saas-17k-month.html (searched 2026-05-08)

---

## Platform Support (32 platforms as of 2026)

Core social for this operator:
- **Instagram**: ✅ Feed posts + carousels (multi-image), Stories; business accounts + standalone accounts
- **TikTok**: ⚠️ Video confirmed; Photo Mode in UI but **known OAuth scope bug** on self-hosted (see issues section)
- LinkedIn, X, Facebook, YouTube, Threads, Pinterest, Bluesky, Reddit, Mastodon: ✅

---

## Instagram Carousel — Confirmed

From the public API docs (https://docs.postiz.com/public-api/providers/instagram, fetched 2026-05-08):

POST to `https://api.postiz.com/public/v1/posts`:
```json
{
  "type": "now",
  "date": "2026-05-10T09:00:00Z",
  "shortLink": false,
  "tags": ["instagram-account-id"],
  "value": [
    {
      "content": "Caption text",
      "image": [
        {"id": "upload-id-1", "path": "/path/to/image1.jpg"},
        {"id": "upload-id-2", "path": "/path/to/image2.jpg"},
        {"id": "upload-id-3", "path": "/path/to/image3.jpg"}
      ]
    }
  ],
  "settings": {
    "instagram": {
      "post_type": "feed"
    }
  }
}
```
Multiple images = carousel. Confirmed working in both hosted and self-hosted (when Meta developer app is configured).

Source: https://docs.postiz.com/public-api/providers/instagram (fetched 2026-05-08)

---

## TikTok Photo Mode — Open Issues

**What Postiz docs say:** Postiz provides TikTok integration via its own registered developer app (hosted) or via operator's app (self-hosted). Required scopes per docs: `user.info.basic`, `video.create`, `video.publish`, `video.upload`, `user.info.profile`.

**Known issue (self-hosted):** GitHub Issue #1161 (2026): "TikTok connection fails in self-hosted Postiz: OAuth shows 'please correct: client_key' + docs reference missing video.create scope (Docker on Oracle ARM64)." The `video.create` scope is documented by Postiz but is not available in TikTok's developer console UI for new apps.

**Status as of 2026-05-08:** TikTok's API has changed; the `video.create` scope appears to have been replaced by different scope names in TikTok's developer portal. This is an active unresolved issue for self-hosted operators. The hosted Postiz cloud version uses Postiz's own pre-approved TikTok app credentials, which may bypass this problem.

Source: https://github.com/gitroomhq/postiz-app/issues/1161 (searched 2026-05-08)

**Practical implication:** If using Postiz **hosted** (postiz.com cloud), TikTok likely works because Postiz's app has already been approved. If **self-hosted**, TikTok setup requires resolving the scope issue manually + completing TikTok App Review with a public-facing website.

---

## Auth Burden — The Critical Constraint

For self-hosted Postiz:
- **Meta (Instagram):** Operator must create a Meta Developer account → create Business App → request `instagram_basic` + `instagram_content_publish` scopes → submit Meta App Review → wait 4–6 weeks → switch to Live mode
- **TikTok:** Create TikTok Developer account → create app → request Content Posting API scopes → pass TikTok audit (7–14 days) → production access

Postiz provides: the OAuth consent flow UI, token storage, refresh logic, publishing code. But the platform approvals remain operator's responsibility.

**Hosted Postiz cloud:** Uses Postiz's pre-approved credentials. Operator just connects accounts via OAuth — no separate app registration. But costs $29-99/mo.

---

## REST API & Webhooks

**Base URL (hosted):** `https://api.postiz.com/public/v1`
**Base URL (self-hosted):** `https://{your-domain}/public/v1`

**Authentication:**
```http
Authorization: {apiKey}
```
API key from Settings > Developers > Public API.

**Upload media:**
```
POST /upload
Content-Type: multipart/form-data
```
Returns `{id, path}` to reference in post creation.

**Create/schedule post:**
```
POST /posts
```

**Rate limit:** 30 requests/hour. Multiple posts can be batched in one request.

**Webhooks:** Included on all plans (per pricing page). Webhook events allow confirmation of publish status. Source: https://postiz.com/pricing (fetched 2026-05-08)

---

## Self-Hosted Deployment Requirements

From Docker Compose docs (https://docs.postiz.com/installation/docker-compose, fetched 2026-05-08):

**Required services:**
- Postiz app (Next.js + NestJS)
- PostgreSQL 17
- Redis 7.2
- Temporal + Temporal PostgreSQL + Elasticsearch (workflow orchestration)
- Temporal UI (port 8080)

**Minimum VPS spec (tested):** Ubuntu 24.04, 2GB RAM, 2 vCPUs

**Note for this operator:** Postiz's Temporal stack is heavier than the existing XAMPP/Laravel setup. This would run as a **separate Docker stack** (not inside the existing Laravel app), communicating via its REST API. The existing VPS (`alisadikinma.com`) runs Apache + PHP-FPM; Postiz would need either a separate container port or a subdomain (`scheduler.alisadikinma.com`). Memory impact: Temporal + Elasticsearch adds ~600-800MB RAM overhead. Check if VPS has headroom.

---

## Pricing

| Deployment | Cost |
|------------|------|
| Self-hosted | Free (operator provides server + handles app reviews) |
| Hosted — Standard | $29/mo (5 channels, 400 posts/mo, API + webhooks) |
| Hosted — Team | $39/mo (10 channels, unlimited posts) |
| Hosted — Pro | $49/mo (30 channels, unlimited posts) |
| Hosted — Ultimate | $99/mo (100 channels) |

All hosted plans include a 7-day free trial. Self-hosted is perpetually free under AGPL-3.0.

Source: https://postiz.com/pricing (fetched 2026-05-08)

---

## Integration Fit for This Operator

**Positives:**
- AGPL-3.0 (free forever self-hosted)
- 30k stars, financially viable company ($17k MRR), not abandonware
- REST API + webhooks usable from Laravel `Http::post()`
- Instagram carousel confirmed working
- No per-post fees

**Negatives:**
- Not a Laravel package — runs as a separate Docker stack
- Meta + TikTok developer app registration and App Review still operator's burden (self-hosted)
- TikTok Photo Mode has active OAuth scope bugs on self-hosted (2026)
- AGPL-3.0: if you modify Postiz source and deploy it, you must open-source your modifications (standard AGPL copyleft)
- Temporal + Elasticsearch infra overhead (~600-800MB RAM beyond the app itself)

---

## Recommendation

**Best choice if:** operator wants zero ongoing cost, values OSS freedom, can absorb the Meta + TikTok app review overhead (4–6 weeks Meta, 2 weeks TikTok), and has VPS RAM headroom for Temporal stack.

**Use hosted Postiz ($29/mo)** if the app review burden is acceptable but the heavy self-hosted stack is not (hosted Postiz uses Postiz's own pre-approved credentials, bypassing the individual registration step for TikTok).

**Skip self-hosted** if TikTok Photo Mode is required urgently — wait for GitHub issue #1161 to resolve, or go Ayrshare.
