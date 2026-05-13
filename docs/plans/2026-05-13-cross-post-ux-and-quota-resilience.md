# Cross-Post UX + LinkedIn Quota Resilience

**Date:** 2026-05-13
**Status:** Design — implementation pending. Triggered by production incident 2026-05-13 (LinkedIn 429 hot-loop) + operator UX feedback ("caption IG/TT/Threads hilang").
**Source:** /gaspol-debug session 2026-05-13. Plan F4 of F1+F2+F3+F4 bundle.
**Maintainer:** alisadikinma
**Related plans:**
- 2026-05-13-carousel-gen-narrative-output-fix.md (carousel-gen Mode B fix)
- 2026-05-12-linkedin-fixed-slots-and-cross-post-sync.md (slot scheduler + governor)

## Problem Statement

Two operator-facing problems surfaced together but root-caused into 5 sub-issues. F1+F2 (committed `90735824`) handled the urgent loop-and-quota leak. This plan covers the structural follow-ups.

### Issue surface

**Operator screenshot 2026-05-13 ~02:00:**
- Draft #116 detail page shows "Awaiting Publish · Depth 100"
- Sub-text: "SOSMED HEALTH: 0/2 published · 2 in progress"
- Sub-text: "Fanned out: Threads NEEDS REVIEW · click to view caption"
- Alert dialog: "LinkedIn API 429: Resource level throttle APPLICATION_AND_MEMBER DAY limit ..."

**Operator questions:**
1. Why does Publish Now button hit 429?
2. Why is Publer not publishing to IG/TikTok/Threads?
3. Why are IG/TikTok/Threads captions missing?

### Root-cause map

| # | Question | Root cause | Status |
|---|---|---|---|
| 1 | LinkedIn 429 | Hot retry loop: 3 stuck `awaiting_publish` drafts × cron tick/min = 180 wasted 429 calls/hr until daily quota fully depleted | ✅ Fixed in `90735824` (F1+F2) |
| 2 | Publer not publishing siblings | Threads + FB siblings stuck in `awaiting_review` waiting for operator approval. Cross-post flow requires explicit review before Publer dispatch by design | ⚠️ Architectural — needs UX clarification |
| 3a | IG caption "hilang" | IG sibling NOT CREATED. Text-format drafts skip IG by `ScanLinkedInForCrossPost::createInstagram()` guard — IG is photo-carousel-only platform | ⚠️ Expected behavior, operator mental model mismatch |
| 3b | TikTok caption "hilang" | Same as 3a — TT sibling skipped for text format | ⚠️ Expected behavior |
| 3c | Threads caption "hilang" | Threads caption EXISTS in DB (421 chars). Reachable via clicking Threads tab. Operator likely on default LinkedIn tab | ⚠️ UX discoverability gap |
| 4 | FB caption invisible | FB sibling EXISTS (1472 chars). UI chip hidden per CLAUDE.md May 10 "FB UI-only-disable" change. Operator cannot see/approve. | ⚠️ UI consistency gap |

## Goals

1. Eliminate operator's mental-model mismatch around which platforms get cross-post siblings + when
2. Make all generated cross-post captions visible in the admin UI regardless of FB-disable state
3. Reduce the chance of 429 ever happening again (telemetry + pre-flight)
4. Document the production incident postmortem so the failure mode is permanently archived

## Non-Goals

- **Cross-format expansion** (text → IG photo / TT text). Operator may eventually want this; covered in §"Future scope" not §"Goals".
- Direct LinkedIn API quota negotiation with LinkedIn. Stay within the existing dev-app quotas; design around them.
- Auto-approve cross-post siblings without human review. Per cross-post-publer-integration design, review gate is intentional — operator must see EN→ID translation quality before publishing brand content.
- Re-enabling format-mix governor (separate decision; depends on carousel-gen narrative-only fix plan landing).

## Design — three independent improvements

### Improvement A: Caption side-panel on LinkedIn tab

Today: operator must click Threads/IG/TikTok tab to see each cross-post caption. Most operators stay on the default LinkedIn tab and assume "caption for X is missing" when in fact they just haven't navigated.

**Proposal:** below the LinkedIn caption preview, add a collapsed accordion showing **all cross-post siblings inline**:

```
[ LinkedIn content preview rendered as before ]

▼ Cross-post fanout (2)
  ├─ Threads (awaiting_review · 421 chars · 2 hashtags)  [Preview ↓]
  └─ Facebook (awaiting_review · 1472 chars · 3 hashtags)  [Preview ↓]
```

When operator expands a row, show the actual caption text inline (no tab click needed). Each row gets quick-actions: Approve / Regenerate / Edit.

**Implementation scope:**
- Modify [LinkedInDraftDetail.vue](frontend/src/views/admin/LinkedInDraftDetail.vue) to render new section between LinkedIn body and timeline panel
- Reuses existing `platformPostFor(key)` helper — no new composables
- Status pill helpers (statusTone) reusable from existing platformMeta
- Effort: ~2 hours

**For non-existent siblings (e.g., IG/TT on text drafts):** show a greyed-out row with explanation:

```
▼ Cross-post fanout (2)
  ├─ Threads (awaiting_review · ...)         [Preview ↓]
  ├─ Facebook (awaiting_review · ...)        [Preview ↓]
  ├─ Instagram — not created (text format only routes to FB + Threads)
  └─ TikTok — not created (text format only routes to FB + Threads)
```

This solves the "caption hilang" mental model issue at source — operator sees explicitly that IG/TT are intentionally skipped, not broken.

### Improvement B: Restore FB tab (or auto-approve FB siblings)

Today: FB chip hidden via CLAUDE.md May 10 "FB UI-only-disable" change. FB siblings persisted to DB but invisible in UI. Operator cannot approve them, so they sit in `awaiting_review` forever and never publish.

**Two options:**

**B1. Restore FB chip in UI** — flip the May 10 hide flag. Reasoning behind the original hide (per CLAUDE.md): "operator wants ability to revive anytime since FB will eventually return as direct Graph API integration mirroring LinkedIn." If operator now wants FB cross-post live (evidenced by FB siblings being generated with 1472-char captions), un-hide is the cleanest path.

**B2. Auto-approve FB siblings on creation** — when `ScanLinkedInForCrossPost::createFacebook()` writes a new FacebookPost row, transition status `pending_generation → generating → ... → awaiting_publish` directly (skip `awaiting_review`). Trade-off: no human gate, so if generation quality is poor, low-quality content publishes.

**Recommendation: B1** because it preserves the human-review safety rail consistent with other platforms. B2 introduces a hidden auto-publish path that's hard to audit.

**Implementation scope (B1):**
- Re-show FB chip in [LinkedInDraftDetail.vue](frontend/src/views/admin/LinkedInDraftDetail.vue) PLATFORM_META + VISIBLE_PLATFORMS
- Re-show FB chip in [SocialPlatformTabs.vue](frontend/src/components/admin/SocialPlatformTabs.vue) PLATFORMS array
- Add FB to `sosmedHealth` platform arrays in detail/list views
- Effort: ~30 min

### Improvement C: 429 telemetry + admin UI surfacing

Today: F2 circuit breaker writes `linkedin_quota_pause_until` setting when 429 hit. Operator sees nothing in admin UI — must check tinker or laravel.log to know publisher is paused.

**Proposal: surface quota state in admin UI.**

Add a banner at the top of [LinkedInPostsCalendar.vue](frontend/src/views/admin/LinkedInPostsCalendar.vue) AND [LinkedInQueueList.vue](frontend/src/views/admin/LinkedInQueueList.vue) AND [LinkedInDraftDetail.vue](frontend/src/views/admin/LinkedInDraftDetail.vue) when `linkedin_quota_pause_until` is in the future:

```
⚠️ LinkedIn publish paused
After hitting daily quota at 2026-05-13 01:48 UTC, publishing is auto-paused
until 2026-05-14 01:48 UTC (24h reset window). Operator can override by
clearing the setting in /admin/settings → LinkedIn → Quota override.
[ Clear pause now ]
```

Backend endpoint additions:
- `GET /admin/settings/linkedin/quota-state` — returns `{paused, until, hit_at, hit_reason}`
- `DELETE /admin/settings/linkedin/quota-pause` — clears the setting (operator override)

Pre-flight guard: in [LinkedInDraftController::publishNow](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php), check quota pause BEFORE calling `LinkedInPublishService::publish`. If active, return 423 (Locked) with descriptive JSON body. Frontend shows toast: "LinkedIn quota paused until X. Override or wait."

This prevents operator from even ATTEMPTING a publish that will fail — saves API call, saves quota.

**Implementation scope:**
- Backend: 2 routes + controller methods + middleware-style guard in publishNow ≈ 1 hour
- Frontend: composable `useLinkedInQuotaState()` + banner component + use in 3 views ≈ 1.5 hour
- Effort: ~2.5 hours total

### Improvement D: Production incident postmortem in CLAUDE.md

The 429 hot-loop incident is documented in the F1+F2 commit message and this plan, but CLAUDE.md root entry (currently at "May 12 ship") doesn't reflect the May 13 work. Future contributors debugging similar quota issues need:

- Concrete description of the failure mode (hot loop + missing FSM edge)
- Detection signature (laravel.log pattern: `[social:publish-slot] FSM advance to failed threw`)
- Recovery procedure (move stuck drafts to manual_review, or wait 24h for quota reset)
- The 429-aware circuit breaker contract (setting key + reset behavior)

**Implementation scope:**
- Add a new entry at the bottom of root CLAUDE.md "Last Updated" section
- Mirror format of existing entries (May 12 fixed-slot scheduler entry style)
- Effort: ~30 min during next CLAUDE.md sync commit

## Implementation order

Recommended phasing:

| Phase | Improvement | Effort | Dependency |
|---|---|---|---|
| 1 | Improvement D (CLAUDE.md postmortem) | 30 min | None — pure docs |
| 2 | Improvement B (restore FB chip) | 30 min | None — frontend-only |
| 3 | Improvement C (quota telemetry + UI banner) | 2.5 hr | Depends on F2 backend already deployed |
| 4 | Improvement A (caption side-panel) | 2 hr | None — UX polish |

Total ~5.5 hours across 4 commits.

Phase 1 + 2 are cheap quick wins. Phase 3 has the highest operator value (prevents recurrence). Phase 4 is polish.

## Verification

**Improvement A:** Visit `/admin/sosmed-drafts/116` — see "Cross-post fanout (2)" accordion below LinkedIn body. Expand Threads → see 421-char Bahasa Indonesia caption inline. See IG/TikTok greyed rows explaining "not created (text format ...)".

**Improvement B:** Visit `/admin/sosmed-drafts/116` — see Facebook tab in platform switcher. Click → see 1472-char caption. SOSMED HEALTH count updates to "0/3 published" reflecting FB + Threads + LinkedIn fanout.

**Improvement C:** Trigger manual 429 (set `linkedin_quota_pause_until` to `now()+1h` via tinker). Visit any LinkedIn admin view → see amber banner with countdown. Click "Clear pause now" → setting deleted, banner disappears. Click Publish Now on any awaiting_publish draft → blocked at controller with 423 toast, no API call attempted.

**Improvement D:** `git log CLAUDE.md` shows new "May 13" entry referencing commits `90735824` (F1+F2) + `49a215e6` (timeout fix) + `2336fd82` (retry FSM fix).

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| B1 re-showing FB chip surfaces stale `awaiting_review` siblings dating back weeks | Med | Low | One-shot artisan `cross-post:reset-pending-fb [--limit=N]` to bulk-cancel pre-fix FB siblings before un-hiding chip |
| C banner UX competes with existing operational toasts (governor off, kill switch state) | Low | Low | Use distinct color (amber not red), one-line height, dismissible |
| A side-panel accordion adds visual noise on drafts with no siblings (drafts in `pending_generation`) | Low | Low | Hide accordion when zero sibling rows exist |
| Improvement D's CLAUDE.md update might conflict with concurrent edits | Low | Med | Land D in its own commit before B/C/A so other branches can rebase cleanly |

## Operational state at plan-write time

- LinkedIn quota: PAUSED (F2 setting `linkedin_quota_pause_until` written 2026-05-13 01:48 UTC, auto-elapses 2026-05-14 01:48 UTC)
- Stuck drafts moved to manual_review: 6 total (#103, #106, #116, #119, #123, #125 + earlier session ones)
- Format-mix governor: OFF (`linkedin_format_governor_enabled='false'`)
- Plugin `linkedin-post-writer`: v0.6.0 on VPS (deployed earlier session)
- Plugin `ai-image-carousel-prompt-gen`: v2.22.0 on VPS (deployed earlier session, includes FINAL OUTPUT CONTRACT enforcement)

## Out of scope (separate plans)

- **Cross-format expansion** — text drafts producing IG photo posts (1080×1080 quote graphic) or TT text-overlay videos. Substantial scope (plugin schema additions + image gen for text-only drafts + brand chrome design for non-carousel platforms). Defer until operator explicitly requests cross-format coverage.
- **LinkedIn quota upgrade** — moving from dev-app to verified production app gets higher daily quota (~5x). Requires LinkedIn Developer review process (1-2 week turnaround). Operator decision: stay in dev mode + manage quota carefully, or invest in verified app.
- **/carousel-gen Mode B fix** — covered in `2026-05-13-carousel-gen-narrative-output-fix.md`. v2.22.0 SKILL.md enforcement deployed; real-world test pending.
- **Telegram alerts on 429** — F2 logs `[LinkedInPublish] quota pause activated` to laravel.log but doesn't ping Telegram. Could add to `DispatchTelegramNotification` system events. Low value if Improvement C banner is shipped (operator sees it in admin UI directly).

## References

- F1+F2 fix commit: `90735824` (this session)
- Loop trigger: `social:publish-slot` cron in [PublishSlotOrchestrator.php:240-268](backend/app/Console/Commands/PublishSlotOrchestrator.php)
- FSM map (now with `failed` edge for awaiting_publish): [LinkedInPostStatus.php:33-42](backend/app/Enums/LinkedInPostStatus.php)
- Circuit breaker: [LinkedInPublishService.php:55-71](backend/app/Services/LinkedInPublishService.php) (guard) + `:719-781` (helpers)
- Cross-post creation logic: [ScanLinkedInForCrossPost.php:136-153](backend/app/Console/Commands/ScanLinkedInForCrossPost.php) (text/carousel format → which siblings)
- May 10 FB UI-only-disable: CLAUDE.md "May 10, 2026 late evening" entry
