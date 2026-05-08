# Decision Tree — Choosing a Social Scheduler
**Researched:** 2026-05-08 | For: IG carousel + TikTok Photo Mode, Laravel 12 backend

```
START: Need to auto-publish Instagram carousel + TikTok Photo Mode
│
├─── Q1: Can you wait 4–8 weeks for Meta + TikTok App Review?
│    │
│    ├─── NO → GO TO Q2
│    │
│    └─── YES → GO TO Q3
│
├─── Q2 (can't wait): Is $149/month ongoing acceptable?
│    │
│    ├─── YES → ✅ AYRSHARE ($149/mo)
│    │          No App Review. PHP SDK. REST API.
│    │          Instagram carousel ✅, TikTok photo ✅.
│    │          SaaS-only, no self-hosted.
│    │
│    └─── NO → GO TO Q3 (reconsider wait, or use Postiz hosted at $29/mo)
│              If $29/mo acceptable → ✅ POSTIZ HOSTED ($29/mo)
│              Uses Postiz's pre-approved credentials, no operator App Review.
│              But: ongoing monthly cost.
│
├─── Q3 (willing to wait): Strictly prefer self-hosted / one-time cost?
│    │
│    ├─── YES → GO TO Q4
│    │
│    └─── NO (ok with SaaS) → ✅ POSTIZ HOSTED ($29/mo)
│                              AGPL-3.0 code is OSS, hosted by Postiz.
│                              REST API + webhooks. IG carousel ✅.
│                              TikTok: need to verify Photo Mode works on
│                              their approved app (scope bug is self-hosted only).
│
├─── Q4 (self-hosted, willing to wait): Is deepest Laravel integration a priority?
│    │
│    ├─── YES (want `composer require`, same-app install) → GO TO Q5
│    │
│    └─── NO (ok with separate Docker service) → ✅ POSTIZ SELF-HOSTED (free)
│                                                AGPL-3.0, Docker Compose.
│                                                REST API + webhooks.
│                                                Must handle own Meta + TikTok App Review.
│                                                TikTok Photo Mode: resolve scope bug first.
│                                                Heavy infra (Temporal + Elasticsearch).
│
└─── Q5 (want Laravel-native): Can budget $1,199 one-time for TikTok Direct Post?
     │
     ├─── YES → ✅ MIXPOST ENTERPRISE ($1,199 one-time)
     │          `composer require inovector/mixpost-enterprise`
     │          Same Laravel app, shared queue worker.
     │          IG carousel ✅, TikTok photo carousel ✅ (35 images, post-audit).
     │          No ongoing fees. Perpetual fallback license.
     │          Wait: Meta App Review 4–6 weeks + TikTok audit 7–14 days.
     │          Need: custom domain verified on TikTok's prefix list.
     │
     └─── NO (budget ~$299) → ✅ MIXPOST PRO ($299 one-time)
                              IG carousel ✅ (fully operational).
                              TikTok: sandbox-only (Direct Post needs Enterprise).
                              Workaround: use Ayrshare only for TikTok ($149/mo)
                              while MixPost Pro handles Instagram.
                              Hybrid cost: $299 one-time + $149/mo ongoing.
```

---

## Leaf Summary

| Decision | Tool | Key Trade-off |
|----------|------|---------------|
| Need it fast + $149/mo OK | **Ayrshare** | No App Review wait; SaaS-only; ongoing cost |
| Need it fast + $29/mo OK | **Postiz hosted** | No self-hosting; operator may still need developer app for TikTok |
| Can wait + want self-hosted + light infra | **MixPost Enterprise** ($1,199 OTP) | Laravel-native; one-time cost; TikTok App Review required |
| Can wait + free forever + Docker OK | **Postiz self-hosted** | AGPL-3.0, free; Temporal/Elasticsearch stack; own app review |
| Budget $299 + IG only for now | **MixPost Pro** | IG carousel works today; TikTok Direct Post blocked until Enterprise |
| Already using n8n | **n8n + Upload-Post** | Hybrid automation path; Upload-Post handles carousel format; ~$16/mo |

---

## Special Case: Hybrid Strategy (Recommended for Budget-Conscious)

Start with **Ayrshare Premium ($149/mo)** for immediate IG + TikTok launch. While running, complete the Meta App Review + TikTok App Review in the background (4–8 weeks). Once approved, purchase **MixPost Enterprise ($1,199)** and migrate. Cancel Ayrshare after migration (~2 months × $149 = $298 bridge cost). Total: $1,199 + $298 = $1,497 vs $1,788+/yr staying on Ayrshare forever. Break-even in ~10 months.

---

## What to Avoid

- **Buffer / Later / SocialPilot / Publer**: No meaningful backend-callable API for this operator's programmatic use case. UI-driven tools only.
- **Phyllo**: Publishing is not their core product; analytics/data access focus; enterprise-only pricing.
- **MixPost Lite (free)**: Does not include Instagram or TikTok. Non-starter.
- **Building direct Meta + TikTok API integration**: Meta app review 4–6 weeks + TikTok audit 2 weeks + ongoing SDK maintenance. All three tool options above are cheaper in engineering time.
