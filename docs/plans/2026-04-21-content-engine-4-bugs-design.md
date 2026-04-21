# Content Engine — 4 Bugs Brainstorm

**Date:** 2026-04-21
**Status:** Design ready for `/gaspol-plan`
**Source:** User-reported during post-deploy smoke test (blog redesign stream)

## Context snapshot

`content_ideas` FSM shipped in `d6a446b1` (state-machine foundation) with `generating_images → completed` and `images_ready → completed` transitions. The only site that transitions to `Completed` is [`ContentPublishService::publish`](../../backend/app/Services/ContentPublishService.php#L139-L145). That service creates a `Post` + primary `post_translations` row, then immediately flips the idea to `Completed` — the EN translation is a later **async** step handled by `triggerTranslationIfEnabled` (line 134). Auto-pipeline has a `ensureTranslationBeforePublish` gate but manual admin-publish does NOT.

`DispatchTelegramNotification` job already has a `publish_success` branch ([line 57](../../backend/app/Jobs/DispatchTelegramNotification.php#L57)) wired to `TelegramNotificationService::sendPublishSuccess`, BUT **no code path dispatches it** — the service is fully plumbed and orphaned.

Admin Content Engine UI "Published" column ([ContentEngine.vue:341](../../frontend/src/views/admin/ContentEngine.vue#L341)) reads `idea.source_data.pub_date` — the news source's publication date (Google News original article) — for ALL ideas regardless of status. That's intentional for Draft tab (helps sort freshness) but wrong for Completed tab (user expects blog-live date).

## The 4 bugs

### Bug 1 — Completed tab shows wrong "Published" date

**Observed:** Completed idea #1 shows "2d ago" — that's 2 days since Google News published the upstream article, not 2 days since the blog post went live. Post may have been published minutes ago.

**Root cause:** `source_data.pub_date` is the ORIGIN date (news source). No code path substitutes the Post's `published_at` for completed ideas.

**Data available:**
- `content_ideas.result_post_id` → `posts.id`
- `posts.published_at` is the canonical blog-live timestamp

**Fix shape:** Show Post `published_at` when `result_post_id` is set, else fall back to `source_data.pub_date`. Purely a display change — data already exists.

### Bug 2 — Regenerate on Completed should flip status back to in-progress

**Observed:** User clicks Regenerate → pipeline runs but status stays `Completed` in the tab counter. UX-confusing — the "Completed" tab shows ideas that aren't actually done being worked on.

**Root cause:**
- `regenerateArticle` ([line 595](../../backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L595)) only accepts `article_ready` / `images_ready` / `researching+failed`. **Rejects `completed` with 422**.
- `regenerateImagePrompts` ([line 1789](../../backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L1789)) whitelists `completed` at the route layer, but FSM `TRANSITIONS['completed']` is only `['archived']` — so the controller accepts the request, does the work, but can't legally transition to `generating_images`. Must be skipping the transition or silently failing — needs verification.

**Spec:**
1. FSM: add `completed → researching` (regen article) and `completed → generating_images` (regen images) to [`TRANSITIONS`](../../backend/app/Enums/ContentIdeaStatus.php#L17-L42)
2. `regenerateArticle`: whitelist `completed` + call `transitionTo(Researching, 'admin_regenerate_from_completed', ...)`
3. `regenerateImagePrompts`: whitelist already exists — audit that it actually transitions state (not just mutates image data)
4. **The live Post stays live** during regeneration. When user re-publishes, `ContentPublishService` should UPDATE existing Post via `result_post_id` rather than orphan + create new — see `updateOrCreate` pattern around [line 106](../../backend/app/Services/ContentPublishService.php#L106)

**Open question:** While regenerating, should the live Post be hidden (`published=false`) or kept live with stale content? Leaning toward **keep live** — the blog URL stays fresh for inbound traffic; when regen completes and user re-publishes, Post gets updated in-place. No orphaned 404.

### Bug 3 — Completed transition must require BOTH EN translation AND Post live

**Observed:** Ideas flip to Completed as soon as ID post is created, before EN translation runs. User considers an idea truly done only when both languages exist.

**Root cause:** `ContentPublishService::publish` transitions to Completed inline at line 139-142, before translation returns. Translation is fire-and-forget (`triggerTranslationIfEnabled`).

**Spec (two competing approaches):**

**Option A — Hold FSM at `images_ready` until translation returns**
- Remove the `Completed` transition from `publish()`. Set `result_post_id` + leave status as `images_ready`.
- Add `translation-complete` automation callback handler that flips to Completed when EN translation lands (the endpoint already exists — see routes `/automation/posts/{id}/translation-complete`)
- If translate phase flag is off → publish transitions directly to Completed (no EN expected)
- If translation fails 3x in auto-mode → Telegram alert (already exists) + transition to Completed anyway with `translation_exhausted` sentinel

**Option B — Add an intermediate status `published` between `images_ready` and `completed`**
- `published` = blog Post live, EN may or may not exist
- `completed` = blog Post live AND EN translation live
- More precise FSM but requires enum + migration + UI status-badge changes

**Recommendation: Option A.** Lower blast radius — no new enum value, no new status badges, just delays the Completed flip until the translation callback fires. The `images_ready` status already means "Post exists, images done" — holding there a few extra minutes until EN lands is semantically correct.

**Edge case:** Manual-publish mode (translate flag off) → Completed fires immediately, same as today. Only `use_translate_phase=true` installs the hold.

### Bug 4 — Telegram publish-success alert with blog URL

**Observed:** `publish_success` branch in `DispatchTelegramNotification` is orphaned — nothing dispatches it. Manual publishes send zero notifications.

**Spec:**
1. Dispatch `DispatchTelegramNotification::dispatch($idea->id, 'publish_success')` from [`ContentPublishService::publish`](../../backend/app/Services/ContentPublishService.php) — right after FSM transitions to Completed (so both auto-pipeline AND manual admin publish fire it).
2. `TelegramNotificationService::sendPublishSuccess` message body must include blog URL:
   - `https://alisadikinma.com/id/blog/{post.slug}` when ID primary
   - `https://alisadikinma.com/en/blog/{post.slug}` when EN primary
3. Gated on `telegram_notify_publish_success` setting (already exists, default `false` — user should flip to `true` in admin Settings when deploying this).
4. **Integration with Bug 3 fix:** if we adopt Option A, dispatch fires when the `translation-complete` callback finalizes the Completed transition — ensures the notification only fires on FULLY done ideas (post live + EN live), matching the user's mental model of "completed".

## Data Integration Map

| Concern | Source | Target | Action |
|---|---|---|---|
| Blog-live date (Bug 1) | `posts.published_at` via `result_post_id` | `ContentEngine.vue` Published column | Backend: include `result_post_published_at` in `/admin/content-engine/ideas` list response. Frontend: prefer that field for Completed rows. |
| Regen from completed (Bug 2) | FSM table | `ContentIdeaStatus::TRANSITIONS` | Add `'completed' => ['archived', 'researching', 'generating_images']` |
| Post update vs recreate (Bug 2) | `result_post_id` | `ContentPublishService::publish` | Use `Post::updateOrCreate(['id' => $idea->result_post_id], [...])` — fall back to create if null |
| Completed gating (Bug 3) | translate callback + publish service | FSM | Remove inline `transitionTo(Completed)` from `publish()`; add it inside `POST /automation/posts/{id}/translation-complete` handler + the already-existing `auto_translate_exhausted` fallback path |
| Publish-success notif (Bug 4) | publish event | `DispatchTelegramNotification` | Dispatch from wherever the `Completed` transition fires (one site — auto-pipeline + manual both funnel through the translation-complete callback in Option A) |
| Blog URL in message (Bug 4) | `Post` + primary language | `TelegramNotificationService::sendPublishSuccess` | Read `post_translations.language` + `posts.slug` to build full URL |

## Implementation Phases (for follow-up `/gaspol-plan`)

**Phase 1 — Display fix (Bug 1)**
Smallest blast radius. Backend adds one computed field to the resource; frontend reads it. 15 min + 1 Feature test for the resource shape.

**Phase 2 — FSM + regenerate from completed (Bug 2)**
FSM update + 2 controller whitelists + `updateOrCreate` in publish service. Needs `ContentIdeaStatusTransitionsTest` additions for the 2 new edges. 45 min + tests.

**Phase 3 — Completed gating via translation callback (Bug 3)**
Move the FSM transition from inline `publish()` to the `translation-complete` callback handler. Add auto-pipeline `auto_translate_exhausted` handler that also flips to Completed. Manual-publish when translate phase disabled stays immediate (early return). 60 min + 3-4 tests (hold at images_ready; flip on callback; flip on exhausted; flip immediately when flag off).

**Phase 4 — Telegram publish-success wiring (Bug 4)**
Dispatch call from the single Completed-transition site. Update `TelegramNotificationService::sendPublishSuccess` message body to include full blog URL. 30 min + 2 Unit tests (message shape; URL correctness per language).

**Total estimate:** ~2.5 hrs across 4 phases, each independently shippable.

## Open Questions for User

1. **Bug 2 — live Post during regen:** Keep it live or hide it? My recommendation: keep live. Confirm?
2. **Bug 3 — when translate phase flag is OFF:** Completed flips immediately (no EN expected). Correct behaviour?
3. **Bug 4 — Telegram message format:** Include blog URL — anything else? Title + virality score + language badges?
4. **Phase ordering:** Ship all 4 phases as ONE PR or split? Phases 1-2 are independent display/FSM changes; Phase 3 restructures publish flow (risk); Phase 4 is orphan-wiring. I'd split Phase 3 into its own PR and bundle Phases 1+2+4. Preference?

## Next step

Hand off to `/gaspol-dev:gaspol-plan` with this design doc → phase-by-phase implementation plan with TDD gates.
