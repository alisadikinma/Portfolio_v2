# Comparison Matrix — Social Scheduler Tools
**Researched:** 2026-05-08 | For: Indonesian B2B tech operator, Laravel 12, IG carousel + TikTok Photo Mode

Legend: ✅ Supported / ⚠️ Partial or conditional / ❌ Not supported / ❓ Unclear from research

| Tool | IG Carousel | TikTok Photo Mode | Self-Hosted | License | Backend API | Auth Burden | Cost (10-50 posts/mo) | Active (2026) |
|------|-------------|-------------------|-------------|---------|-------------|-------------|----------------------|---------------|
| **MixPost Pro** | ✅ Up to 10 images + video mix (Pro+) | ✅ Up to 35 images — requires domain verify & Enterprise for non-sandbox | ✅ Laravel package or Docker | ⚠️ MIT (Lite free); Pro/Enterprise require paid license | ✅ Native REST API (`/api/{workspace}/posts`) | ⚠️ Need own Meta App (Pro); TikTok Direct Post needs Enterprise + TikTok App Review (7-14d) | ✅ $299 one-time (Pro) or $1,199 (Enterprise) | ✅ v4.5.0 Dec 2025; actively maintained |
| **Postiz** | ✅ Multi-image feed posts; both `instagram` (business) and `instagram-standalone` modes | ⚠️ Video confirmed; Photo Mode in UI but open GitHub issues (scoping bugs) on self-hosted | ✅ Docker Compose (needs Postgres + Redis + Temporal stack) | ✅ AGPL-3.0 | ✅ REST API (api-key + OAuth2); 30 req/hr; webhooks included | ⚠️ Self-hosted: must register own Meta + TikTok developer apps + pass each platform's App Review | ✅ Free (self-hosted) or $29-99/mo cloud | ✅ v2.21.7 Apr 2026; 30k GitHub stars |
| **Ayrshare** | ✅ Up to 10 media items (images + video mix) | ✅ Photo posts confirmed; TikTok drafts with images | ❌ SaaS-only | ❌ Proprietary SaaS | ✅ REST API; PHP SDK available; multiple language SDKs | ✅ Handles Meta + TikTok partnership — no operator app review needed | ⚠️ $149/mo (1 profile, Premium) ongoing | ✅ April 2026 changelog active; latest updates confirmed |
| **Upload-Post.com** | ✅ "Uploads images as carousel to Instagram; slideshow to TikTok" | ✅ Slideshow to TikTok (their term for Photo Mode equivalent) | ❌ SaaS-only | ❌ Proprietary SaaS | ✅ REST API (Apikey header); n8n native node included in n8n default | ✅ Likely handles own app credentials (SaaS model) — unclear if operator needs own Meta/TikTok app | ✅ ~$16/mo paid (free 10 uploads/mo) | ✅ Active (n8n templates updated 2026) |
| **Blotato** | ✅ Multi-image carousel to Instagram; 5-platform carousel dispatch | ✅ TikTok + Instagram carousel confirmed | ❌ SaaS-only | ❌ Proprietary SaaS | ✅ REST API; native n8n + Make.com nodes; `mediaUrls[]` array payload | ✅ Managed SaaS — appears to handle platform credentials | ⚠️ $29/mo (freemium trial available) | ✅ 2026 blog posts + n8n templates active |
| **MixPost Lite (free)** | ❌ Lite only supports Facebook Pages, X, Mastodon | ❌ Not included in Lite | ✅ Docker or Laravel package | ✅ MIT open source | ⚠️ API available but limited platform scope | N/A (IG/TikTok not available) | ✅ Free forever | ✅ Separate active Lite changelog |
| **Phyllo** | ⚠️ API gateway for creator data; publishing exists but focus is analytics | ⚠️ Unknown — primarily analytics-oriented | ❌ SaaS-only | ❌ Proprietary SaaS | ✅ REST API; Meta partnership bypasses app review | ✅ Meta partner — no operator App Review | ❌ Custom pricing (enterprise), no public tiers | ✅ G2 reviews 2026 |
| **Buffer** | ✅ Instagram carousel scheduling | ⚠️ TikTok supported but API is "early access rebuild" | ❌ SaaS-only | ❌ Proprietary SaaS | ⚠️ API exists but not marketed for production programmatic use | ⚠️ Unknown — Buffer manages OAuth | ⚠️ $6-12/mo per channel (Essentials/Team) | ✅ Active 2026 |
| **n8n + Upload-Post** | ✅ Via Upload-Post node | ✅ Via Upload-Post node | ✅ n8n self-hosted | ✅ n8n: Apache 2.0 (CE) / EE license | ✅ n8n has native Upload-Post node + webhook trigger | ✅ Upload-Post handles credentials | ✅ n8n free self-hosted + ~$16/mo Upload-Post | ✅ n8n 2026 templates active |
| **Postiz Cloud ($29/mo)** | ✅ Same as self-hosted | ⚠️ Same TikTok scoping uncertainty | ❌ Hosted by Postiz | ✅ AGPL (code is OSS) | ✅ Same REST API | ⚠️ Still requires own Meta + TikTok developer apps | ⚠️ $29-99/mo ongoing | ✅ Active |

---

## Notes on Auth Burden Detail

**"Operator needs own app review"** means: operator registers at developers.facebook.com + developers.tiktok.com, creates a Business-type app, requests required scopes (instagram_basic, instagram_content_publish for IG; video.publish + video.create for TikTok), submits for platform App Review. Meta: 4-6 weeks. TikTok: 7-14 days (but see active scope bugs in Postiz self-hosted: `video.create` scope no longer appears in TikTok developer console as of 2026). Posts remain private/draft until approved.

**"Handles it" (Ayrshare, Blotato, Upload-Post)** means: these SaaS tools hold platform-approved developer app credentials; operator just connects their social account via the tool's OAuth UI. No separate developer app registration or App Review required on operator side.

---

## Sources
- MixPost TikTok docs: https://docs.mixpost.app/services/social/tik-tok/ (fetched 2026-05-08)
- MixPost pricing: https://mixpost.app/pricing (fetched 2026-05-08)
- MixPost changelog: https://mixpost.app/releases/pro (fetched 2026-05-08)
- MixPost API: https://docs.mixpost.app/api/posts/create/ (fetched 2026-05-08)
- Postiz GitHub: https://github.com/gitroomhq/postiz-app (fetched 2026-05-08)
- Postiz pricing: https://postiz.com/pricing (fetched 2026-05-08)
- Postiz API: https://docs.postiz.com/public-api/introduction (fetched 2026-05-08)
- Postiz Instagram API: https://docs.postiz.com/public-api/providers/instagram (fetched 2026-05-08)
- Postiz TikTok docs: https://docs.postiz.com/providers/tiktok (fetched 2026-05-08)
- Ayrshare pricing: https://www.ayrshare.com/pricing/ (fetched 2026-05-08)
- Ayrshare changelog: https://www.ayrshare.com/docs/whatsnew/latest (fetched 2026-05-08)
- Upload-Post.com: https://www.upload-post.com/ (fetched 2026-05-08)
- Blotato API: https://help.blotato.com/api/start (searched 2026-05-08)
- n8n carousel templates: https://n8n.io/workflows/3524-upload-carousel-of-images-to-tiktok-and-instagram-with-upload-postcom/ (searched 2026-05-08)
- TikTok API app review: https://zernio.com/blog/tiktok-developer-api (searched 2026-05-08)
- Instagram App Review: https://www.getphyllo.com/post/instagram-api-integration-101-for-developers-of-the-creator-economy (searched 2026-05-08)
