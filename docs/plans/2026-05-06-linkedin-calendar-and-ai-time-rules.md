---
title: LinkedIn Posts → Calendar View + AI Time-Slot Rules
status: brainstorm-complete (design approved, plan pending)
created: 2026-05-06
owner: Ali Sadikin
related:
  - docs/plans/2026-04-23-linkedin-admin-ui.md (existing FSM + sidebar)
  - docs/plans/2026-05-06-linkedin-en-caption-and-queue-polish.md (recent UI polish)
---

# Design

## 1. Why

The existing `/admin/linkedin-posts` Posts list (LinkedInPostsList.vue) is a card grid sorted by created_at. Three operator pain points it doesn't solve:

1. **No temporal awareness.** Operator can't see at a glance "what's shipping next Tuesday" or "is Friday empty?". Scrolling cards by created_at misses the *publication* timeline.
2. **No conflict detection.** Reschedule modal accepts any datetime. Two posts at 10:00 + 10:25 same day = LinkedIn algorithm splits reach between them (same-author posts within ~30 min cannibalize). Currently silent.
3. **No timing guidance.** Reschedule modal shows static hint "Tuesday-Thursday 9-11am for B2B reach" — never personalized, never sourced, identical for everyone.

This work replaces Posts list with a calendar view + ships an AI-researched static rules table that powers timing recommendations on every reschedule.

## 2. Locked decisions (from brainstorm)

| Decision | Locked | Rationale |
|---|---|---|
| **Calendar scope** | Replace Posts list. Queue list untouched. | Card grid loss acceptable — calendar shows same data with temporal context. Queue's triage table is irreplaceable for batch action on manual_review, keep. |
| **AI rules source** | One-time WebSearch → static rules table. Re-runnable quarterly via artisan. Multi-platform schema (LinkedIn + IG + TikTok + YouTube Shorts) but v1 reads only LinkedIn rows. | Avoids per-request latency + ongoing cost. Schema future-proof for multi-platform without v1 scope creep. |
| **Conflict rule** | Soft warning, ±30 min window, allow proceed | LinkedIn distributes reach worse for same-author posts within ~30 min. Soft warning respects operator autonomy (announcement + Q&A back-to-back is legitimate). |

## 3. Calendar UX

### 3.1 View hierarchy

```
/admin/linkedin-posts  (ROUTE UNCHANGED — replaces card grid with calendar)

┌─ Top toolbar ─────────────────────────────────────────────────────────────┐
│  ← Today →   May 2026          [Month] [Week] [List]      [🔄 Refresh AI] │
│  Filter: [All] [Scheduled] [Published] [Awaiting] [Failed]                 │
└────────────────────────────────────────────────────────────────────────────┘

┌─ Month grid (7 cols × 6 rows max) ─────────────────────────────────────────┐
│ Sun     Mon     Tue 🟢  Wed     Thu 🟢  Fri     Sat                        │
│ ─────  ─────  ──────  ─────  ──────  ─────  ─────                          │
│  27     28     29      30     1      2      3                              │
│                ●●     ●               ●                                    │
│  4      5      6      7      8 ★     9     10                              │
│         ●     ●●       ●●            ●                                     │
│ ...                                                                         │
└────────────────────────────────────────────────────────────────────────────┘

Tue/Thu header chip 🟢 = AI rules say "best day for B2B".
Cell background tint = composite of AI scores for that day's best slots
  (subtle — green ~5% opacity for optimal days, no tint for neutral, red ~5% for avoid).
Dots = posts on that day. Color = status (emerald=published, amber=awaiting, gold=scheduled, red=failed, cyan=manual_review).
Max 3 dots per cell + "+N" overflow chip.
★ = "today" indicator.
```

### 3.2 Cell click → side panel (slide-in from right, no route change)

```
┌─ Posts on Thu, May 8 ────────────────────────────────────────┐
│                                                               │
│  AI says: 🟢 Optimal day                                      │
│  Best slots: 09:30 · 10:00 · 14:00 WIB                       │
│                                                               │
│  ─────────────────────────────────────                        │
│                                                               │
│  10:00 ● AWAITING PUBLISH · CAROUSEL · 9 slides              │
│         "Goldman Sachs IPO Conditions..."                     │
│         [Open detail]                                         │
│                                                               │
│  14:30 ● SCHEDULED · TEXT                                     │
│         "Why I gave up Notion for Obsidian"                  │
│         [Open detail]                                         │
│                                                               │
│  ─────────────────────────────────────                        │
│                                                               │
│  [+ Schedule new draft for May 8]                             │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

`+ Schedule new` opens a draft picker (autocomplete over `manual_review` + `awaiting_publish` drafts) → pre-fills the existing reschedule modal with that date.

### 3.3 Click post dot → goes to detail page (existing flow, no change)

### 3.4 Week view (deferred to v1.1)

Month view ships first. Week view = same UX with hourly rows × 7 day cols + AI heatmap as background gradient. Saves ~3 days of work now; no operator pain reported for week-grain navigation.

### 3.5 List view (cheap, ships v1)

Falls back to existing card grid sorted by `published_at DESC NULLS LAST, scheduled_at DESC`. One-line LOC change — toggles a `mode` ref between `'month'` / `'list'`. Lets operator escape calendar when they need batch-scan published posts.

## 4. Conflict warning UX

Triggers on **two surfaces**:

### 4.1 At reschedule modal (LinkedInDraftDetail.vue)

When user types a new datetime in the existing `<input type="datetime-local">`, a debounced (300ms) check fires `POST /admin/linkedin-drafts/{id}/check-conflict?at={iso}`. If response has conflicts:

```
PUBLISH AT (YOUR LOCAL TIME)
[ 2026-05-08 10:25 ]   [ ✓ Reschedule ] [ Discard ]

⚠ Conflict: 'Goldman Sachs IPO Conditions' is scheduled at 10:00 (25 min apart).
   LinkedIn distributes reach poorly when same-author posts ship within 30 minutes.
   Suggested alternatives: 09:00 · 11:30 · 14:00 (all green slots Thu)
```

Reschedule button stays enabled. Tooltip on hover: "Soft warning — proceed if intentional."

### 4.2 At calendar `+ Schedule new` flow

Same check fires when picking a slot. Inline warning under the time picker.

### 4.3 What counts as "conflict"

```
WHERE id != :current_draft_id
  AND status IN ('awaiting_publish', 'scheduled', 'published')
  AND ABS(TIMESTAMPDIFF(MINUTE, scheduled_at, :proposed_at)) < 30
  AND deleted_at IS NULL
```

`'scheduled'` is not currently an FSM state (we have `awaiting_publish` for approved-with-cancel-window). Including it as guard for any future state addition. `'published'` included so retro-conflict detection works (operator picks slot 25 min after a just-shipped post).

## 5. AI Time Rules — schema + research flow

### 5.1 Schema

New table `posting_time_rules`:

```sql
CREATE TABLE posting_time_rules (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  platform ENUM('linkedin','instagram','tiktok','youtube_shorts') NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,    -- 0=Sun..6=Sat (PHP/Carbon convention)
  hour TINYINT UNSIGNED NOT NULL,           -- 0..23 in target timezone
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
  score TINYINT UNSIGNED NOT NULL,          -- 0=avoid, 50=neutral, 100=optimal
  audience VARCHAR(64) NOT NULL DEFAULT 'b2b_tech',  -- future: b2c, creator, etc.
  source_url TEXT NULL,                     -- the WebSearch result citation
  source_title VARCHAR(255) NULL,
  notes TEXT NULL,                          -- "Hootsuite 2026 study, n=300k posts"
  last_researched_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,

  UNIQUE KEY ux_platform_dow_hour_audience (platform, day_of_week, hour, audience, timezone),
  KEY idx_platform_score (platform, score)
);
```

7 days × 24 hours × 4 platforms × 1 audience (v1) = 672 rows. Trivial size.

### 5.2 Research artisan command

```bash
php artisan posting-rules:research --platform=linkedin [--audience=b2b_tech] [--dry-run]
php artisan posting-rules:research --platform=all  # all 4 platforms
```

Implementation (`App\Console\Commands\ResearchPostingTimeRules`):

1. Read existing rules for platform — if `last_researched_at < 90 days ago` AND not `--force`, abort with "Rules fresh, refresh in N days. Use --force to override."
2. Build a fixed prompt:
   ```
   Research current 2026 best posting times for {{platform}} for {{audience}} audience
   in Asia/Jakarta (Indonesia) timezone.

   Return JSON only, no prose:
   {
     "sources": [
       {"url": "...", "title": "...", "n_size": "300k posts"}
     ],
     "rules": [
       {"day_of_week": 2, "hour": 9, "score": 95, "note": "B2B peak"},
       {"day_of_week": 2, "hour": 10, "score": 90},
       ...
     ]
   }

   Score 0-100 (100=optimal, 50=neutral, 0=avoid). Cover all 7 days × 24 hours.
   Cite at minimum 3 sources from 2025-2026 (Hootsuite, Buffer, Sprout Social,
   Hubspot, Later, etc.). Adjust for Indonesia tz (e.g., LinkedIn US "9am EST" → "9pm WIB").
   ```
3. Call via existing SSH bridge to claude CLI (same pattern as `LinkedInGenerationService`):
   ```
   ssh claudesn@localhost claude -p "<prompt>" --model sonnet \
     --mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config \
     --output-format json
   ```
   Sonnet has WebSearch tool by default. No extra refs file needed (research prompt is self-contained, ~600 tokens).
4. Parse JSON → upsert into `posting_time_rules` table (UNIQUE constraint handles re-runs).
5. Stamp `last_researched_at = now()`.
6. Print summary table to stdout: top 10 slots + bottom 5 slots + sources cited.

Cost: ~1 SSH call per platform per quarter. Effectively free.

### 5.3 Schedule

Add to `routes/console.php`:
```php
Schedule::command('posting-rules:research --platform=linkedin')
    ->cron('0 3 1 */3 *')   // 03:00 WIB, 1st day of every 3rd month (quarterly)
    ->timezone('Asia/Jakarta');
```

V1 only auto-refreshes LinkedIn. IG/TT/YT operator-triggered until a UI surface uses them.

### 5.4 Manual refresh from calendar UI

Top toolbar `🔄 Refresh AI rules` button → `POST /api/admin/posting-rules/refresh?platform=linkedin` → dispatches the same artisan via `Artisan::queue()`. Status pill on toolbar shows "Researching… (~30s)" → "Updated 12 min ago" once done. Disabled-state if last refresh < 24h ago.

### 5.5 Heatmap rendering

Calendar header chips (Tue 🟢 / Thu 🟢) computed from:
```sql
SELECT day_of_week, MAX(score) as best_score
FROM posting_time_rules
WHERE platform='linkedin' AND audience='b2b_tech'
GROUP BY day_of_week
```
Day chip 🟢 if best_score ≥ 85, 🟡 if 70-84, no chip if <70.

Cell background tint computed similarly per day, opacity = (best_score - 50) / 100, capped 0-15%.

Best-slot suggestions in side panel + reschedule modal: top 3 hours that day where score ≥ 80, formatted in operator's timezone.

## 6. Backend additions

### 6.1 New endpoints

```
GET    /api/admin/linkedin-posts/calendar?from=2026-05-01&to=2026-05-31
       → returns posts with scheduled_at OR published_at in range, light shape
       → response: [{id, status, format, scheduled_at, published_at, post_title,
                     conflict_neighbors_count}]

POST   /api/admin/linkedin-drafts/{id}/check-conflict
       body: {at: "2026-05-08T10:25:00+07:00"}
       → returns {has_conflict, conflicts: [{id, post_title, scheduled_at, minutes_apart}]}

GET    /api/admin/posting-rules?platform=linkedin
       → returns {rules: [{day_of_week, hour, score, note}], last_researched_at,
                  sources, day_summaries: [{dow, best_score, best_hours: [9,10,14]}]}

POST   /api/admin/posting-rules/refresh
       body: {platform: "linkedin"}
       → dispatches artisan, returns {dispatched_at, eta_seconds}
```

All `auth:sanctum`. Calendar endpoint uses existing eager-load patterns.

### 6.2 New controller methods

`Admin\LinkedInDraftController` gains:
- `calendar(Request $req): JsonResponse` — date range query
- `checkConflict(Request $req, int $id): JsonResponse` — ±30 min lookup

New `Admin\PostingRuleController`:
- `index(Request $req): JsonResponse` — read rules
- `refresh(Request $req): JsonResponse` — dispatch artisan

### 6.3 Migration

`database/migrations/2026_05_06_000001_create_posting_time_rules_table.php` per §5.1 schema.

Seeded migration ships with **zero rows** — first `posting-rules:research --platform=linkedin` call populates. Until then, calendar shows uniform background (no heatmap), reschedule modal shows the existing static hint string as fallback.

## 7. Frontend additions

### 7.1 New view: `frontend/src/views/admin/LinkedInPostsCalendar.vue`

Replaces `LinkedInPostsList.vue` at the `/admin/linkedin-posts` route. The old file gets archived (renamed `.list-archive.vue`) for a few weeks in case operator wants to revert.

Components inside the view:
- `<CalendarToolbar>` — date nav + view toggle + filter pills + AI refresh button
- `<CalendarMonthGrid>` — 7×6 cell grid, dot rendering, AI tint
- `<CalendarSidePanel>` — slide-in from right when cell clicked
- `<CalendarListMode>` — fallback list (reuses existing `LinkedInDraftCard` component from current Posts list)

State: a single `useLinkedInCalendar()` composable wrapping TanStack Query for `/calendar` endpoint, keyed by `[from, to, statusFilters]`. 30s staleTime + refetchOnMount: 'always' (matches existing `useLinkedInDrafts` pattern).

### 7.2 Conflict check integration

`useLinkedInDrafts.js` gains `useConflictCheck(draftId)` mutation (debounced via lodash.debounce 300ms in the consumer, not the composable). Reschedule modal in `LinkedInDraftDetail.vue` watches `scheduled_at` ref and re-runs check on change.

### 7.3 Sidebar nav (no rename)

`AdminLayout.vue` keeps "Posts" label — operator's mental model of "Posts" doesn't change, the rendering does. Avoids breaking link history. List mode toggle in toolbar gives the old card grid for nostalgic batch browsing.

### 7.4 Date library

Use `date-fns` (already in package.json — `Activity-feed.vue` uses it). No new dep.

Calendar grid math: ~80 LoC of date-fns helpers (`startOfMonth`, `endOfMonth`, `eachDayOfInterval`, `isSameDay`). No FullCalendar / Vue Cal — overkill for our grain (max ~30 events/month) and clashes with Tailwind utility-first.

## 8. Anti-patterns enforced

- ❌ NO drag-drop reschedule. Adds complex interaction (drag-edit-cancel undo flow) without clear win — datetime input is fine for our cadence.
- ❌ NO recurring events. We don't post on a recurring schedule; every draft has unique content.
- ❌ NO calendar export (.ics). Operator's calendar app is irrelevant — we own the LinkedIn schedule.
- ❌ NO real-time WebSocket updates. TanStack 30s staleTime + refetchOnMount handles staleness fine.
- ❌ NO timezone selector. Asia/Jakarta hardcoded (matches existing `linkedin:process-scheduled` cron tz).
- ❌ NO multi-platform UI in v1. Schema is multi-platform, rendering is LinkedIn-only.

## 9. Data Integration Map

| Component / Field | Data Source | Existing? | Notes |
|---|---|---|---|
| Calendar grid posts | `linkedin_posts` table | ✅ | New `/calendar` endpoint, light shape |
| Post status colors | `LinkedInPostStatus` enum + `linkedinHelpers.js::statusMeta()` | ✅ | Reuse existing palette |
| Post title rendering | `linkedin_posts.post.translations` (relation) | ✅ | Eager-load in calendar query |
| Day-of-week heatmap | `posting_time_rules` table | 🆕 | New table, populated by artisan |
| AI source citations | `posting_time_rules.source_url`, `source_title` | 🆕 | Shown in tooltip on day chip |
| Conflict detection query | `linkedin_posts WHERE status IN (...) AND scheduled_at within ±30min` | ✅ | New endpoint, existing schema |
| Reschedule modal | `LinkedInDraftDetail.vue` reschedule section | ✅ | Augmented with conflict check + suggested slots |
| Side panel slide-in | New component | 🆕 | Pure Vue Transition + Tailwind, no new dep |
| Date math | `date-fns` | ✅ | Already in package.json |
| Refresh AI button | New endpoint dispatches artisan | 🆕 | Same SSH pattern as LinkedInGenerationService |

## 10. Phased delivery

**Phase A — Schema + research artisan + heatmap data ready** (no UI yet, ~1 day)
1. Migration `posting_time_rules`
2. `App\Console\Commands\ResearchPostingTimeRules` + SSH wrapper helper (lift from LinkedInGenerationService — DRY into a shared `ClaudeCliRunner` later)
3. Run `php artisan posting-rules:research --platform=linkedin` once on dev → seed local rules
4. New endpoint `GET /admin/posting-rules` + controller
5. Smoke test: visit endpoint, see 168 rows of LinkedIn rules with sources

**Phase B — Calendar view (month + list mode) + cell side panel** (~2-3 days)
1. New endpoint `GET /admin/linkedin-posts/calendar`
2. New view `LinkedInPostsCalendar.vue` + 4 sub-components
3. Replace route handler in router (old file → `.list-archive.vue`)
4. Heatmap tint + day chips wired to `/admin/posting-rules` data

**Phase C — Conflict detection + reschedule modal augmentation** (~0.5 day)
1. New endpoint `POST /admin/linkedin-drafts/{id}/check-conflict`
2. `useConflictCheck()` composable + debounced wire-up in `LinkedInDraftDetail.vue`
3. Inline warning UI under datetime input

**Phase D — Quarterly cron + manual refresh button** (~0.5 day)
1. `routes/console.php` schedule entry
2. `POST /admin/posting-rules/refresh` endpoint
3. Toolbar refresh button + status pill
4. Update CLAUDE.md "Recent migrations" + new section "LinkedIn Posts Calendar + Posting Time Rules"

**Total: ~4-5 days. Phase A standalone-shippable** (no UI dependency). Phase B/C/D can land in one PR or split.

## 11. Open questions deferred to plan stage

- Should `posting_time_rules` carry `audience` axis in v1? Schema yes, UI no (single audience `b2b_tech`). Revisit if multi-audience surfaces.
- Cancel-window timer interaction with calendar: when a draft is awaiting_publish with cancel_window_ends_at in 5 min, do we render it differently in the calendar? V1: same as scheduled. V2: pulsing dot.
- "Schedule new" flow from calendar: should it open a *new draft creation* wizard or just pick from existing manual_review? V1: existing-only (matches operator's actual workflow — calendar is for ops, not authoring).

## 12. Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Sonnet WebSearch returns crap rules JSON | Medium | Artisan validates schema (Zod-style PHP check) before upsert; `--dry-run` flag prints proposed changes; on failure, keep existing rules |
| Operator schedules many posts on same day, side panel grows | Low | Side panel scrolls; max ~5 posts/day in observed cadence |
| Calendar slow with 100+ posts in month range | Low | Endpoint paginates by month; index on `scheduled_at` already exists |
| Heatmap visually noisy when overlaid with existing post dots | Medium | Tint capped at 15% opacity; user-test in dev before ship |
| Reschedule modal conflict check latency on slow network | Low | 300ms debounce + non-blocking (warning is informational, button always works) |

## 13. Success criteria

- Operator can see all scheduled + published LinkedIn posts on a month view in one screen — no scrolling card grid by created_at.
- Operator gets a soft warning (non-blocking, with alternative suggestions) when scheduling within 30 min of an existing post.
- Calendar shows AI-recommended best days/hours as visual tint based on real 2025-2026 industry research, with cited sources visible on hover.
- AI rules refreshable manually + auto-refresh quarterly. Last-refresh timestamp visible.
- Phase A (rules data) is shippable independently — operator can run artisan and verify rules exist in DB before any UI work lands.
