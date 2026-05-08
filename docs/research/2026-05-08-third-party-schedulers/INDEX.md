# Third-Party Social Scheduler Research — Executive Summary
**Researched:** 2026-05-08 | **Scope:** IG carousel + TikTok Photo Mode auto-publish for Indonesian B2B tech operator on Laravel 12

---

## Top 3 Recommendations

### 1. MixPost Pro ($299 one-time) — Best overall for this stack
Laravel-native package (`composer require inovector/mixpost-pro-team`), installs directly into the existing backend, one-time payment with no per-post SaaS tax. Confirmed support for Instagram carousel and TikTok photo carousel (up to 35 images). API available for programmatic scheduling. **Critical caveat:** TikTok Direct Post requires Enterprise ($1,199) + passing TikTok's own App Review (7–14 days). Pro users get sandbox-only TikTok; Direct Post is blocked at the platform level for non-audited clients.

### 2. Postiz (self-hosted, free AGPL-3.0) — Best for zero ongoing cost
30k+ GitHub stars, v2.21.7 released April 2026, actively maintained. Docker Compose deployment. Supports Instagram carousel (multi-image feed posts) and TikTok (confirmed video; Photo Mode documented in UI but scoping issues active in self-hosted GitHub issues as of 2026). REST API + webhooks included on all plans including self-hosted. **Auth burden:** self-hosted instances require operator to create own Meta Developer App + TikTok Developer App and pass each platform's App Review separately — same burden as building direct, but Postiz provides the OAuth scaffolding.

### 3. Ayrshare ($149/mo Premium) — Best to skip the app-review burden entirely
SaaS-only (no self-hosted), but Ayrshare operates as an approved Meta + TikTok developer partner — operator just connects their accounts via Ayrshare's OAuth UI, no separate Meta/TikTok app review needed. REST API + PHP SDK. Confirmed Instagram carousel (10 media items) and TikTok photo posts. Cost is ongoing ($149/mo for 1 brand profile) vs one-time for MixPost.

---

## Single-Sentence Verdicts

| Tool | Verdict |
|------|---------|
| **MixPost Pro** | Best Laravel-native fit at $299 one-time, but TikTok Direct Post needs Enterprise ($1,199) + platform App Review |
| **Postiz** | Best free OSS option (AGPL-3.0, Docker, REST API + webhooks), but requires operator to handle own Meta/TikTok developer app registration |
| **Ayrshare** | Best developer API for skipping platform approval burden, but $149+/mo ongoing and SaaS-only |
| **Upload-Post.com** | Simple REST API for carousel cross-posting at low cost (~$16/mo), good n8n fit, no PHP SDK |
| **Blotato** | Strong carousel API at $29/mo, good Make.com/n8n fit, no self-hosted option |
| **Postiz Cloud** | Skip if self-hosting — same tool at $29-99/mo with operator still handling own developer apps |
| **Phyllo** | Skip — focus is creator data/analytics, not publishing; custom pricing, no self-hosted |
| **Buffer/Later/Publer** | Skip — no backend-callable API for Laravel use case; UI-driven tools |
| **n8n + Upload-Post** | Valid hybrid path if n8n already in stack; Upload-Post handles carousel format translation |

---

## MixPost 2026 Status

**MixPost has fully added Instagram and TikTok since April 2023.** As of v4.5.0 (December 2025) it supports: Instagram (feed posts, reels, stories, carousels including video-in-carousel since v4.2.0 Aug 2025), TikTok (video + photo carousel up to 35 images). The free Lite edition still only covers Facebook Pages, X, and Mastodon. Instagram + TikTok require Pro ($299 one-time) or Enterprise ($1,199 one-time). TikTok Direct Post (non-sandbox) requires Enterprise + passing TikTok's App Review process. **The April 2023 evaluation that rejected MixPost for LinkedIn is no longer relevant — MixPost now covers IG + TikTok in Pro.**
