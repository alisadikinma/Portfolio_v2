# LinkedIn Auto-Schedule Manual Review Drafts

**Date:** 2026-05-07
**Status:** Design (awaiting implementation)
**Author:** Ali Sadikin (brainstormed with Claude via gaspol-brainstorm)

---

## Problem statement

Operator manually approves and schedules every LinkedIn draft sitting at `manual_review`. Backlog of 7+ carousel drafts in `/admin/linkedin-queue` "In progress" tab plus separate "Needs review" queue means operator becomes the bottleneck. Goal: cron that promotes `manual_review → awaiting_publish` and assigns `scheduled_at` to the next available high-quality time slot, respecting existing `posting_time_rules` research data (May 6 ship).

User clarification: "misal hari ini sudah full, AI akan auto next schedule besok dan misal jam ideal utk posting adalah jam 6 pagi maka dia akan auto schedule kan besok jam 6 pagi" — i.e., walk forward through ideal slots until a free one is found.

---

## Design

### Locked decisions (4 collaborative gates)

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| 1 | Quality gate before auto-approve | **None — auto-approve all manual_review** | Pre-flight virality filter at `linkedin:scan-blog` (default >=60) already filters at ingest. Operator chose maximum throughput. |
| 2 | Daily post cap | **None — fill all available ideal slots** | Algorithm self-throttles via slot scarcity (best_score >=85 yields ~2-4 slots/day on weekdays, ~0-1 on weekends). |
| 3 | Slot definition | **`posting_time_rules.best_score >= 85`** | Emerald slots only (high-quality time per research). Auto-skips low-research days. |
| 4 | Kill-switch | **New `linkedin_auto_approve_enabled` setting, default OFF** | Two independent switches (auto_approve + auto_publish). Operator can dry-test scheduling without enabling publish. Default OFF makes deploy safe. |

### Architecture

**4 new components + 1 refactor:**

1. **`App\Services\LinkedInAutoSchedulerService`** — `nextAvailableSlot(?Carbon $after = null): ?Carbon`. Pure function. Walks forward through `posting_time_rules WHERE best_score >= 85` ordered by `(day_offset ASC, hour ASC)`. For each candidate hour, checks `linkedin_posts` for conflict (±30 min window of any `awaiting_publish` or `published` row). Returns first free slot or `null` after 14-day lookahead exhausted.

2. **`App\Console\Commands\AutoScheduleManualReviewLinkedInPosts`** — artisan `linkedin:auto-schedule`. Daily 04:30 WIB. CLI flags: `--dry-run`, `--limit=N`, `--lookahead=N`.

3. **Settings rows:**
   - `linkedin_auto_approve_enabled` (default `'false'`) — added to `LinkedInSettingsSeeder`
   - `telegram_notify_linkedin_backlog_exhausted` (default `'false'`) — added to `TelegramSettingsSeeder`

4. **Admin UI** — 1 checkbox in `AboutSettings.vue` LinkedIn card, below existing `linkedin_auto_publish` toggle.

5. **Refactor:** Extract conflict-check logic from [`LinkedInDraftController::checkConflict`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) into a shared service helper (used by both the existing endpoint and the new auto-scheduler).

**Reused infra (no changes):**
- `PipelineGuard::advance` — FSM transition + audit log
- `GenerateLinkedInCarouselImages::dispatch` — carousel image gen self-heal (mirrors April 29 approve()-flow logic)
- `linkedin:process-scheduled` (every minute) — publishes after `scheduled_at`

### Algorithm

**Driver loop** (per cron tick):

```php
candidates = LinkedInPost::where('status', ManualReview)
    ->whereNull('deleted_at')
    ->join('content_ideas', 'linkedin_posts.content_idea_id', '=', 'content_ideas.id')
    ->orderByRaw('COALESCE(content_ideas.virality_score, 0) DESC')  // primary: high virality
    ->orderBy('linkedin_posts.created_at')                          // tiebreaker: FIFO
    ->select('linkedin_posts.*')
    ->lockForUpdate()
    ->get();

$assignedSlots = [];   // in-tick collision prevention
foreach ($candidates as $draft) {
    if ($this->wasRecentlyDemotedByKillSwitch($draft)) continue;  // loop guard
    $slot = $autoScheduler->nextAvailableSlot(after: now(), excluding: $assignedSlots);
    if ($slot === null) {
        $this->dispatchBacklogTelegramAlert();
        break;
    }
    $draft->scheduled_at = $slot;
    $draft->cancel_window_ends_at = $slot;  // = scheduled_at, no extra delay
    PipelineGuard::advance($draft, AwaitingPublish, 'auto_schedule:no_gate');
    if ($draft->format === 'carousel') $this->triggerImageGenIfSlidesPending($draft);
    $assignedSlots[] = $slot;
}
```

**`nextAvailableSlot()` walk:**

```php
for ($dayOffset = 0; $dayOffset <= 14; $dayOffset++) {
    $day = $after->copy()->addDays($dayOffset)->timezone('Asia/Jakarta');
    $idealHours = PostingTimeRule::query()
        ->where('platform', 'linkedin')
        ->where('day_of_week', $day->dayOfWeek)
        ->where('best_score', '>=', 85)
        ->orderBy('hour')
        ->pluck('hour');

    foreach ($idealHours as $hour) {
        $candidate = $day->copy()->setTime($hour, 0, 0);
        if ($candidate->lt($after->copy()->addMinutes(30))) continue;  // 30-min lead time
        if (in_array($candidate->toIso8601String(), $excluding)) continue;
        if ($this->hasConflict($candidate)) continue;  // ±30 min window
        return $candidate;
    }
}
return null;
```

### Edge cases & safety rails

| Scenario | Behavior |
|---|---|
| Operator clicks Approve at same moment as cron | `lockForUpdate()` + `PipelineGuard` exception — one wins, other logs warning. No corruption. |
| Two cron ticks overlap | `->withoutOverlapping(15)` schedule modifier blocks. |
| 5 drafts in 1 tick collide on same slot | In-memory `$assignedSlots` array passed to `nextAvailableSlot(excluding: …)`. |
| Carousel slides still rendering at scheduled_at | Existing `linkedin:process-scheduled` guard refuses publish until all slides `image_status=done`. Reaper recovers. Frontend Calendar shows warning. No data loss. |
| Operator flips kill-switch OFF mid-day | Drafts already promoted stay at `awaiting_publish`. Kill-switch only gates next tick. Same ergonomics as `linkedin_auto_publish`. |
| Backlog > 14 days of ideal slots | `nextAvailableSlot` returns null. Cron logs warning, dispatches Telegram alert (gated by new setting). Drafts stay `manual_review`. |
| Empty `posting_time_rules` (fresh deploy) | Returns null for all. Logs INFO "no posting rules — run posting-rules:research first". Exits clean. |
| `auto_publish=false` + `auto_approve=true` loop | `linkedin:process-scheduled` demotes back to `manual_review` with `reason=kill_switch_demotion`. Auto-scheduler skips drafts with that reason in last 24h via `pipeline_state_log[]` check. Loop broken. |

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| `nextAvailableSlot` slot query | `posting_time_rules` table | ✅ May 6 ship | Read-only, indexed on `(platform, day_of_week, hour)` |
| Conflict check | `linkedin_posts.scheduled_at` | ✅ | Refactor: extract into shared helper from `LinkedInDraftController::checkConflict` |
| Promote draft | FSM via `PipelineGuard::advance` | ✅ | Audit log auto-written to `pipeline_state_log[]` |
| Carousel image trigger | `GenerateLinkedInCarouselImages::dispatch` | ✅ | Mirror April 29 self-heal logic from `approve()` |
| Kill-switch read | `settings.linkedin_auto_approve_enabled` | ❌ new (1 row) | Seeded `firstOrCreate`, default `'false'` |
| Admin UI toggle | `AboutSettings.vue` LinkedIn card | ✅ | Adds 1 checkbox below `linkedin_auto_publish` |
| Backlog Telegram alert | `DispatchTelegramNotification` job | ✅ | New flag `telegram_notify_linkedin_backlog_exhausted` |
| Loop guard | `LinkedInPost.pipeline_state_log[]` | ✅ | Scan last entry for `kill_switch_demotion` within 24h |

**Zero new migrations.** All tables exist. Only seeded settings rows + one service + one command + one schedule entry + one UI checkbox.

### Test plan (TDD)

**Unit (`LinkedInAutoSchedulerServiceTest`, 5 tests):**
- `next_slot_returns_earliest_ideal_hour_today_when_free`
- `next_slot_skips_to_tomorrow_when_today_full`
- `next_slot_respects_thirty_minute_lead_time`
- `next_slot_returns_null_when_lookahead_exhausted`
- `next_slot_skips_conflicting_scheduled_posts_within_thirty_minutes`

**Feature (`AutoScheduleManualReviewLinkedInPostsTest`, 5 tests):**
- `command_promotes_high_virality_drafts_first`
- `command_skips_when_kill_switch_off`
- `command_skips_drafts_demoted_from_kill_switch_within_24h`
- `command_dispatches_carousel_image_gen_when_slides_pending`
- `dry_run_logs_planned_promotions_without_state_change`

### Observability

- **INFO per promotion:** `[linkedin:auto-schedule] promoted draft #{id} virality={N} → scheduled_at={ISO} (slot best_score={N})`
- **DEBUG per skip:** with reason (`kill_switch_loop_guard` / `lookahead_exhausted` / `slot_conflict`)
- **INFO per tick summary:** `[linkedin:auto-schedule] processed: {N} promoted, {N} skipped, {N} failed (lookahead_days={N})`
- **Telegram alert** on backlog exhausted (gated)
- **Calendar UI** auto-displays via existing `scheduled_at` field — no separate UI

### File touch list (final)

**Backend (7 files):**
1. `app/Services/LinkedInAutoSchedulerService.php` (new, ~120 LoC)
2. `app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php` (new, ~80 LoC)
3. `app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (refactor — extract conflict-check helper, ~10 LoC moved)
4. `database/seeders/LinkedInSettingsSeeder.php` (+1 row)
5. `database/seeders/TelegramSettingsSeeder.php` (+1 row)
6. `routes/console.php` (+1 schedule entry)
7. Tests: `tests/Unit/LinkedInAutoSchedulerServiceTest.php` + `tests/Feature/AutoScheduleManualReviewLinkedInPostsTest.php`

**Frontend (1 file):**
1. `frontend/src/views/admin/AboutSettings.vue` (+1 checkbox in LinkedIn card)

**Docs:**
1. This file (`## Design` section above; `## Implementation Plan` appended by gaspol-plan)
2. CLAUDE.md `Last Updated` entry (post-implementation)

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations against existing infrastructure (`PipelineGuard`, `posting_time_rules`, `GenerateLinkedInCarouselImages`). NEVER substitute placeholders. If a referenced helper diverges from the signatures below, STOP and ask — do not silently adapt.

### Goal

Build the autonomous cron that promotes `manual_review → awaiting_publish` and assigns `cancel_window_ends_at` to the next available `posting_time_rules.score >= 85` slot, walking forward up to 14 days. Operator-gated by new `linkedin_auto_approve_enabled` setting (default OFF).

### Architecture corrections discovered during plan-write (vs design doc)

The Design section was written before re-reading actual code. Two corrections that change the implementation:

1. **`posting_time_rules` column is `score`, NOT `best_score`** (per [`migration 2026_05_06_000003`](backend/database/migrations/2026_05_06_000003_create_posting_time_rules_table.php) line 28). Use `where('score', '>=', 85)`. Audience filter `'b2b_tech'` matches existing [`checkConflict`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L920) convention.

2. **`scheduled_at` vs `cancel_window_ends_at` semantics are OPPOSITE of design doc.** Per existing [`approve()` lines 302-305](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L302):
   - `scheduled_at = now()` — immutable audit of WHEN the decision was made
   - `cancel_window_ends_at = $publishAt` — WHEN publish actually fires (the cron reads this)

   Auto-scheduler MUST follow this convention so `linkedin:process-scheduled` picks it up correctly:
   ```php
   $draft->update([
       'scheduled_at' => now(),                       // decision audit
       'cancel_window_ends_at' => $idealSlot,         // future ideal time
   ]);
   ```

### Architecture Context (from CLAUDE.md + verified)

| Existing | Path | Role in this plan |
|---|---|---|
| `PipelineGuard::advance(Model, BackedEnum, string $reason, array $context = [])` | [Service](backend/app/Services/PipelineGuard.php) | Sole FSM transition entry — auto-scheduler calls this once per promotion |
| `LinkedInPostStatus::ManualReview / AwaitingPublish` | [Enum](backend/app/Enums/LinkedInPostStatus.php) | FSM allows `manual_review → awaiting_publish` (existing transition) |
| `LinkedInPost.pipeline_state_log[]` JSON | Model attribute | Loop-guard reads last entry for `kill_switch_demotion` reason |
| `posting_time_rules` table | [Model](backend/app/Models/PostingTimeRule.php) | Read-only — `score`/`day_of_week`/`hour`/`audience` columns |
| `LinkedInDraftController::checkConflict` | [Controller line 870](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L870) | Refactor: extract conflict-detection into shared service helper, controller delegates |
| `LinkedInDraftController::approve` carousel self-heal | [Controller line 314-331](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L314) | Mirror this exact pattern in auto-scheduler |
| `GenerateLinkedInCarouselImages::dispatch($draftId)` | [Job](backend/app/Jobs/GenerateLinkedInCarouselImages.php) | Idempotent — skips already-`done` slides |
| `LinkedInSettingsSeeder` + `TelegramSettingsSeeder` | Seeders | `firstOrCreate` pattern (won't clobber operator UI edits) |
| Existing schedule entries | [routes/console.php](backend/routes/console.php) | New `linkedin:auto-schedule` slot: 04:30 WIB (between 04:00 purge + 05:00 auto-pipeline) |

### Tech Stack

PHP 8.2 / Laravel 12 / MySQL 8 / PHPUnit (project convention — NOT Pest). Vue 3.5 + Tailwind 4 for the 1 admin UI checkbox. No new packages.

### Data Integration Map

| Feature | Data Source | Hook/API/Service | Exists? | Action |
|---|---|---|---|---|
| Slot picker query | `posting_time_rules` (column `score`) | New `LinkedInAutoSchedulerService::nextAvailableSlot` | Yes (table) | Create service |
| Conflict check (±30 min) | `linkedin_posts.cancel_window_ends_at` for awaiting_publish + published | Refactored `LinkedInScheduleConflictService::hasConflict` | Yes (logic in controller) | Extract from controller |
| Manual review draft list | `LinkedInPost` joined with `content_ideas.virality_score` | Eloquent in command | Yes | Use directly |
| FSM transition | `PipelineGuard::advance` | Existing service | Yes | Call directly |
| Carousel image self-heal | `GenerateLinkedInCarouselImages::dispatch($id)` | Existing job | Yes | Mirror approve() pattern |
| Loop guard | `LinkedInPost.pipeline_state_log[]` last entry | Inline in command | Yes | Read directly |
| Kill-switch read | `settings.linkedin_auto_approve_enabled` (string `'true'`/`'false'`) | `Setting::firstWhere(...)->value` | No (1 row needed) | Extend seeder |
| Backlog Telegram alert | `DispatchTelegramNotification::dispatch` | Existing job | Yes | Add new event key |
| Notify toggle | `settings.telegram_notify_linkedin_backlog_exhausted` | Setting read | No (1 row) | Extend seeder |
| Admin UI toggle | `AboutSettings.vue` LinkedIn card | Existing form | Yes | Add 1 checkbox |

**Zero new migrations.** All tables exist.

---

### Phase A: Refactor — extract conflict-check into shared service

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/app/Services/LinkedInScheduleConflictService.php`
- Modify: `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (lines 870-941, replace inline logic with service call)
- Test: `backend/tests/Unit/LinkedInScheduleConflictServiceTest.php`

**Steps:**
1. Write failing test `it_detects_conflict_within_30_minute_window` in `LinkedInScheduleConflictServiceTest`. Expected error: `Error: Class "App\Services\LinkedInScheduleConflictService" not found`.
2. Run `php artisan test --filter=LinkedInScheduleConflictServiceTest`, confirm fail for the expected reason.
3. Create `LinkedInScheduleConflictService` with two public methods:
   - `hasConflict(Carbon $proposed, ?int $excludeDraftId = null, int $windowMinutes = 30): bool`
   - `findConflicts(Carbon $proposed, ?int $excludeDraftId = null, int $windowMinutes = 30): Collection` (returns shape `[{id, post_title, scheduled_at, minutes_apart}]` for controller round-trip compat)
   - Query reads `cancel_window_ends_at` (NOT `scheduled_at`) since that's the publish-time field per existing semantics. **Re-verify in Phase A step 4 by reading lines 889-909 of LinkedInDraftController.php — if existing checkConflict reads `scheduled_at`, match that to preserve behavior; if `cancel_window_ends_at`, match that.**
4. Read existing `checkConflict` lines 889-909 to confirm field. Update service to match exactly.
5. Add 4 more test cases:
   - `it_returns_no_conflict_outside_window`
   - `it_excludes_specified_draft_id`
   - `it_only_considers_awaiting_publish_and_published_statuses`
   - `it_finds_multiple_conflicts_with_minutes_apart_diff`
6. Implement until all 5 tests pass. Run `php artisan test --filter=LinkedInScheduleConflictServiceTest`.
7. Refactor `LinkedInDraftController::checkConflict` to delegate to the service (preserve identical JSON response shape — controller test must still pass).
8. Run existing `php artisan test --filter=LinkedInDraftCheckConflictTest` to confirm no regression.
9. Commit: `refactor(linkedin): extract schedule conflict check into shared service`

**Verification:**
- [ ] `php -l` clean on 2 modified + 1 new PHP file
- [ ] `php artisan test --filter=LinkedInScheduleConflict` — 5 unit tests pass
- [ ] `php artisan test --filter=LinkedInDraftCheckConflictTest` — existing tests still pass (no regression)
- [ ] No placeholder/TODO comments in new service
- [ ] Controller `checkConflict()` is now a thin wrapper (≤15 lines)

---

### Phase B: Build slot picker — `LinkedInAutoSchedulerService`

**Estimated time:** 18 minutes

**Files:**
- Create: `backend/app/Services/LinkedInAutoSchedulerService.php`
- Test: `backend/tests/Unit/LinkedInAutoSchedulerServiceTest.php`

**Steps:**
1. Write failing test `next_slot_returns_earliest_ideal_hour_today_when_free`. Expected error: `Error: Class "App\Services\LinkedInAutoSchedulerService" not found`.
2. Run test, confirm fail for the expected reason.
3. Create service with constructor-injected `LinkedInScheduleConflictService` (from Phase A).
4. Implement public method `nextAvailableSlot(Carbon $after, array $excludingIso8601 = []): ?Carbon`:
   - For `$dayOffset = 0` to `14`:
     - Compute `$day = $after->copy()->addDays($dayOffset)->timezone('Asia/Jakarta')`
     - Query: `PostingTimeRule::where('platform', 'linkedin')->where('audience', 'b2b_tech')->where('day_of_week', $day->dayOfWeek)->where('score', '>=', 85)->orderBy('hour')->pluck('hour')`
     - For each `$hour`:
       - `$candidate = $day->copy()->setTime($hour, 0, 0)`
       - Skip if `$candidate->lt($after->copy()->addMinutes(30))` (lead-time guard)
       - Skip if `in_array($candidate->toIso8601String(), $excludingIso8601, true)`
       - Skip if `$conflictService->hasConflict($candidate)`
       - Return `$candidate`
   - Return `null` after exhausting 14 days
5. Implement test 1, run, confirm pass.
6. Add 4 more test cases:
   - `next_slot_skips_to_tomorrow_when_today_full` (seed today's all-conflicts via factory)
   - `next_slot_respects_thirty_minute_lead_time` (seed slot 5 min in future, verify skipped)
   - `next_slot_returns_null_when_lookahead_exhausted` (seed zero high-score rules)
   - `next_slot_skips_excluded_iso_strings` (verify in-tick collision prevention)
7. Run `php artisan test --filter=LinkedInAutoSchedulerServiceTest` — 5 pass.
8. Commit: `feat(linkedin): add LinkedInAutoSchedulerService with 14-day slot walker`

**Verification:**
- [ ] `php -l` clean
- [ ] All 5 unit tests pass
- [ ] Service uses `LinkedInScheduleConflictService` from Phase A (constructor-injected, not new'd inline)
- [ ] Reads from `posting_time_rules.score` column (NOT `best_score`)
- [ ] Audience filter is `'b2b_tech'` (matches existing `checkConflict` convention)
- [ ] Returns `Carbon` instance in `Asia/Jakarta` timezone, never raw timestamp string

---

### Phase C: Add settings rows + admin UI checkbox

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/database/seeders/LinkedInSettingsSeeder.php` (+1 row)
- Modify: `backend/database/seeders/TelegramSettingsSeeder.php` (+1 row)
- Modify: `frontend/src/views/admin/AboutSettings.vue` (+1 checkbox in LinkedIn card)
- Test: `backend/tests/Feature/LinkedInAutoApproveSettingTest.php`

**Steps:**
1. Write failing test `seeder_creates_linkedin_auto_approve_enabled_with_false_default`. Expected error: row not found in `settings` table.
2. Run, confirm fail.
3. Add row to `LinkedInSettingsSeeder` via existing `firstOrCreate` pattern: `key='linkedin_auto_approve_enabled', group='linkedin', value='false', type='boolean'` (match existing seeder convention exactly — re-read first 30 lines of file to copy schema).
4. Add row to `TelegramSettingsSeeder`: `key='telegram_notify_linkedin_backlog_exhausted', group='telegram', value='false', type='boolean'`.
5. Run `php artisan db:seed --class=LinkedInSettingsSeeder && php artisan db:seed --class=TelegramSettingsSeeder` — verify idempotent (second run = 0 new rows).
6. Run test, confirm pass.
7. Add second test: `setting_persists_when_admin_updates_to_true_via_existing_endpoint` — uses existing `PUT /api/admin/settings/linkedin` route to verify the new key is part of the linkedin settings group payload.
8. In `AboutSettings.vue` LinkedIn card, add 1 checkbox bound to `linkedinSettings.linkedin_auto_approve_enabled` directly below the existing `linkedin_auto_publish` checkbox. Match existing checkbox markup exactly (Tailwind classes, label structure, helper text). Helper text: `"Promote 'manual review' drafts to scheduled when auto-publish runs (gated by linkedin_auto_publish)."`
9. `npm run build` — verify Vite build clean.
10. Commit: `feat(linkedin): add auto-approve kill-switch + backlog telegram setting`

**Verification:**
- [ ] `php artisan test --filter=LinkedInAutoApproveSetting` — 2 tests pass
- [ ] Both seeders idempotent (re-run produces no duplicate rows)
- [ ] Vite build clean
- [ ] AboutSettings.vue checkbox renders + persists on PUT

---

### Phase D: Build the cron command

**Estimated time:** 22 minutes

**Files:**
- Create: `backend/app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php`
- Test: `backend/tests/Feature/AutoScheduleManualReviewLinkedInPostsTest.php`

**Steps:**
1. Write failing test `command_skips_when_kill_switch_off`. Expected error: command class not found.
2. Run, confirm fail.
3. Create artisan command:
   - Signature: `linkedin:auto-schedule {--dry-run} {--limit=} {--lookahead=14}`
   - Description: `"Promote LinkedIn manual_review drafts to awaiting_publish on next ideal posting_time_rules slot. Gated by linkedin_auto_approve_enabled."`
   - Constructor-inject `LinkedInAutoSchedulerService` + `PipelineGuard`
   - Read `Setting::firstWhere(['group' => 'linkedin', 'key' => 'linkedin_auto_approve_enabled'])` — exit early with INFO log when value !== `'true'`
4. Run test 1, confirm pass.
5. Implement promotion loop:
   ```php
   $candidates = LinkedInPost::where('linkedin_posts.status', LinkedInPostStatus::ManualReview->value)
       ->whereNull('linkedin_posts.deleted_at')
       ->leftJoin('content_ideas', 'linkedin_posts.content_idea_id', '=', 'content_ideas.id')
       ->orderByRaw('COALESCE(content_ideas.virality_score, 0) DESC')
       ->orderBy('linkedin_posts.created_at')
       ->select('linkedin_posts.*')
       ->lockForUpdate()
       ->get();

   $assignedSlots = [];
   foreach ($candidates as $draft) {
       if ($this->wasRecentlyDemotedByKillSwitch($draft)) { /* skip + log debug */ continue; }
       $slot = $this->scheduler->nextAvailableSlot(now(), $assignedSlots);
       if ($slot === null) { $this->dispatchBacklogAlert(); break; }
       if ($this->option('dry-run')) { /* log INFO planned */ continue; }
       $draft->update([
           'scheduled_at' => now(),
           'cancel_window_ends_at' => $slot,
       ]);
       $this->guard->advance($draft, LinkedInPostStatus::AwaitingPublish, 'auto_schedule:no_gate', [
           'slot' => $slot->toIso8601String(),
           'virality_score' => $draft->contentIdea?->virality_score,
       ]);
       if ($draft->format === 'carousel') $this->triggerImageGenIfSlidesPending($draft);
       $assignedSlots[] = $slot->toIso8601String();
   }
   $this->info("[linkedin:auto-schedule] processed: {$promoted} promoted, {$skipped} skipped");
   ```
6. Implement private helpers:
   - `wasRecentlyDemotedByKillSwitch(LinkedInPost $draft): bool` — scans `pipeline_state_log[]` for last entry where `to === 'manual_review'` AND reason matches regex `/kill_switch/i` AND timestamp within 24h.
   - `triggerImageGenIfSlidesPending(LinkedInPost $draft): void` — copy carousel self-heal pattern from controller approve() lines 314-331 verbatim. Keep wrapped in try/catch (non-fatal — pattern from existing code).
   - `dispatchBacklogAlert(): void` — read `telegram_notify_linkedin_backlog_exhausted` setting, dispatch `DispatchTelegramNotification::dispatch(['event' => 'linkedin_backlog_exhausted', ...])` if `'true'`.
7. Add remaining test cases:
   - `command_promotes_high_virality_drafts_first` (seed 3 drafts virality 80/60/40, verify 80 gets earliest slot)
   - `command_skips_drafts_demoted_from_kill_switch_within_24h` (seed pipeline_state_log with kill_switch_demotion 1h ago)
   - `command_dispatches_carousel_image_gen_when_slides_pending` (assert Bus::fake then `Bus::assertDispatched(GenerateLinkedInCarouselImages::class)`)
   - `dry_run_logs_planned_promotions_without_state_change` (verify zero FSM transitions when `--dry-run`)
   - `command_dispatches_telegram_alert_when_backlog_exhausted` (seed 0 ideal slots + 1 manual_review draft, assert telegram job dispatched)
8. Run `php artisan test --filter=AutoScheduleManualReviewLinkedInPostsTest` — 5 pass.
9. Commit: `feat(linkedin): add linkedin:auto-schedule cron with virality-DESC + kill-switch + loop-guard`

**Verification:**
- [ ] `php -l` clean
- [ ] All 5 feature tests pass
- [ ] Command writes `cancel_window_ends_at = $slot` (NOT `scheduled_at = $slot` — semantics fix)
- [ ] `--dry-run` produces zero FSM transitions
- [ ] Loop-guard skips drafts demoted by kill-switch within 24h
- [ ] Carousel format triggers `GenerateLinkedInCarouselImages` (Bus::assertDispatched)
- [ ] No placeholder/TODO comments

---

### Phase E: Wire schedule entry + smoke test end-to-end

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/routes/console.php` (+1 schedule entry)

**Steps:**
1. Add schedule entry in `routes/console.php` after the `linkedin:purge-low-virality` block (lines 88-91), matching existing comment + cron style:
   ```php
   // LinkedIn: daily 04:30 WIB — auto-promote manual_review drafts to awaiting_publish
   // assigning cancel_window_ends_at to next posting_time_rules.score >= 85 slot
   // (14-day lookahead). Gated by linkedin_auto_approve_enabled (default OFF).
   // Runs after purge-low-virality (04:00) + before content:auto-pipeline (05:00)
   // so backlog is fresh + cleaned. Per docs/plans/2026-05-07-linkedin-auto-
   // schedule-manual-review.md.
   Schedule::command('linkedin:auto-schedule')
       ->dailyAt('04:30')
       ->timezone('Asia/Jakarta')
       ->withoutOverlapping(15);
   ```
2. Run `php artisan schedule:list` — verify new entry appears with correct cron expression `30 4 * * *`.
3. Run end-to-end smoke (local XAMPP, kill-switch OFF):
   ```
   php artisan linkedin:auto-schedule --dry-run
   ```
   Verify INFO log "kill switch off" and exit 0.
4. Toggle kill-switch ON via tinker:
   ```
   Setting::firstWhere(['group'=>'linkedin','key'=>'linkedin_auto_approve_enabled'])->update(['value' => 'true']);
   php artisan linkedin:auto-schedule --dry-run
   ```
   Verify it lists candidates without promoting (dry-run path).
5. Toggle back to OFF before commit (don't ship a flipped switch to production).
6. Commit: `feat(linkedin): schedule linkedin:auto-schedule daily 04:30 WIB`

**Verification:**
- [ ] `php artisan schedule:list` shows the new entry
- [ ] `--dry-run` smoke produces correct log output (kill-switch off then on)
- [ ] Kill-switch reverted to `'false'` before commit
- [ ] No other schedule entries broken (run all existing tests once: `php artisan test`)

---

### Phase F: Update CLAUDE.md + ship

**Estimated time:** 6 minutes

**Files:**
- Modify: `CLAUDE.md` root (Last Updated entry + LinkedIn settings group table)
- Modify: `CLAUDE.md` root (admin LinkedIn routes section if any new endpoints — none in this plan)

**Steps:**
1. Add `linkedin_auto_approve_enabled` row to the `settings` group: `linkedin` table in CLAUDE.md (around line 360 in the existing settings table block).
2. Add `telegram_notify_linkedin_backlog_exhausted` row to the `telegram` group LinkedIn extension list (around line 410).
3. Add new schedule entry to the LinkedIn cron list (under "LinkedIn pipeline integration" architecture section — same area where `linkedin:scan-blog`, `linkedin:purge-low-virality`, etc. are documented).
4. Append concise Last Updated entry summarizing the ship: cron name, cadence, gate, slot rule, virality-DESC ordering, loop-guard, file count.
5. Commit: `docs(claude): document linkedin:auto-schedule cron + settings`

**Verification:**
- [ ] CLAUDE.md `settings` group `linkedin` table includes new row
- [ ] CLAUDE.md `telegram` group includes new notify toggle
- [ ] CLAUDE.md cron list includes the daily 04:30 entry
- [ ] Last Updated entry mentions ship in 1-2 sentences (project convention — NOT a multi-paragraph essay)
- [ ] `git status` clean post-commit

---

### Cross-cutting verification (after Phase F before merge to main)

- [ ] `php artisan test` — full suite pass (no regressions in 100+ existing tests)
- [ ] `php artisan schedule:list` — 9 LinkedIn schedule entries present (8 existing + 1 new)
- [ ] `php artisan db:seed --class=LinkedInSettingsSeeder` — idempotent
- [ ] `php artisan db:seed --class=TelegramSettingsSeeder` — idempotent
- [ ] Vite production build clean
- [ ] Admin UI checkbox visible + persists on PUT
- [ ] No `// TODO`, `// FIXME`, `// placeholder` strings in new files (`grep -r "TODO\|FIXME\|placeholder" backend/app/Services/LinkedInAutoSchedulerService.php backend/app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php backend/app/Services/LinkedInScheduleConflictService.php`)
- [ ] CLAUDE.md updated in same commit chain (per project policy — CLAUDE.md sync is not optional)

### Rollback plan

If anything goes wrong post-deploy:
1. Set `linkedin_auto_approve_enabled = 'false'` via admin UI (instant — cron becomes no-op next tick)
2. If cron is misbehaving worse than just doing nothing: comment out the `Schedule::command('linkedin:auto-schedule')` block in `routes/console.php`, deploy. Service/command code can stay (dormant without schedule entry).
3. No DB rollback needed (zero migrations).
4. No state cleanup needed — drafts already promoted to `awaiting_publish` are valid state, operator can manually cancel via existing UI if undesired.

---

### Estimated total time

~76 minutes hands-on + verification + commits. Realistically 90-120 min including test debugging and any unexpected codebase divergence at Phase A step 4 (the conflict-check field semantics double-check).

### Execution handoff

**Option 1 (recommended): Execute in this session via gaspol-execute**
> "Ready to start Phase A? gaspol-execute will enforce per-phase TDD checkpoints + anti-placeholder gate."

**Option 2: Parallel via gaspol-parallel**
> Phases A, B, C are independent of each other (A=refactor, B=new service depends on A, C=settings/UI standalone). Can run A+C parallel, then B after A completes, then D+E+F sequential. Saves ~10 min wall-time but adds dispatch overhead. Only worth it if operator is time-constrained.

**Option 3: Save for separate session**
> The plan + design doc at `docs/plans/2026-05-07-linkedin-auto-schedule-manual-review.md` is self-sufficient — any next session can resume cold.
