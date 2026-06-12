# Social Studio status sync + Telegram scheduling conversation

**Date:** 2026-06-12
**Status:** Design (brainstorm complete) — ready for `gaspol-plan`
**Author:** brainstorm session

Two connected operator requests on `/admin/social-studio`:

1. **List status ≠ detail status.** A carousel draft mid *re-authoring* (or with slides still rendering) shows "review/draft ready" in the Social Studio list while the detail page correctly shows "re-authoring slide". The list must reflect the same transient state the detail computes.
2. **Telegram-driven scheduling.** When a draft is *genuinely* review/draft ready, send a Telegram message asking when to post. Operator replies (tap a suggested slot **or** type a date/time); the system schedules it. The AI must suggest the next available date/time and, if the operator picks a slot that already has a posting scheduled, ask for confirmation back. Suggested slots must be **weekdays Mon–Fri only**, excluding **weekends + Indonesian public holidays (incl. cuti bersama)**.

---

## Design

### Shared insight

Both requests ride the same carousel activity ladder. Req 1 surfaces the lower rungs into the list pill; Req 2 fires when the ladder reaches READY:

```
re_authoring → rendering → (caption check) → READY → [Telegram prompt] → scheduled (awaiting_publish)
└──────── Req 1 list pill ────────┘                   └──────── Req 2 conversation ────────┘
```

### Locked decisions (from brainstorm Q&A)

| # | Decision | Choice |
|---|---|---|
| 1 | Req 2 input method | **Hybrid** — suggested-slot buttons + free-text custom |
| 2 | Holiday source | **Static hardcoded list** (config file, 2026 + 2027 operator-provided), NO API. Year with no data → weekend-only + log warning |
| 3 | Req 2 trigger | **Auto when ready**, replaces auto-schedule (master toggle gates the old cron to defer) |
| 4 | Req 1 scope | **re-authoring + rendering slides** (caption-not-ready & IG-source out of scope) |
| 5 | Free-text parse | **Claude CLI** (queued job; deterministic scheduler still owns suggestions + occupancy/holiday truth) |
| 6 | Stuck-draft policy | **One prompt, no reminder** |

---

### Req 1 — list pill matches detail

**Backend** — `LinkedInDraftController::index()` adds a per-row `regenerate_activity` field (mirror of `show()`'s `resolveRegenerateActivity`). The list payload already carries `carousel_slides[].image_status`, so the **rendering** phase is free; the `linkedin_regenerate_lock:{id}` cache read (plus the per-slide `image_status === 'reauthoring'` marker, also already in payload) adds the **re-authoring** signal. Cheap per-row loop, no migration.

**Frontend** — `socialStudioHelpers.js::blogCard` stops returning `draft.status` verbatim. It derives the display status via the **same** `resolveCarouselActivity(draft)` helper the detail page uses (`linkedinHelpers.js`): phase `re_authoring` / `rendering` overrides the raw FSM status; otherwise raw status passes through (text drafts unaffected). One derivation function → list pill == detail pill by construction. `SocialStudio.vue` renders the effective status; tab counts unaffected (status filtering still on the FSM `status`).

**Explicitly out of scope (residual, documented):**
- caption-not-ready is NOT reflected in the list pill (operator scoped it out).
- IG-source repurpose cards still show `RepurposeJob.status` (`drafted`) while their linked LinkedInPost re-authors — possible future extension.

---

### Req 2 — Telegram scheduling conversation

#### Trigger (cron, replaces auto-schedule)

- New cron `linkedin:prompt-schedule` (every ~minute via `ScheduledCommandSeeder`, category `linkedin`). Scans drafts that are **genuinely ready**:
  - carousel: all slides `image_status === 'done'` **AND** `LinkedInSlotReadinessService::captionReadinessForApproval()->ready`
  - text: validation-passed `manual_review`
  - **AND** `scheduled_at IS NULL` **AND** `schedule_prompt_sent_at IS NULL`.
- Sends **one** prompt, stamps new nullable column `linkedin_posts.schedule_prompt_sent_at`. **No reminder** (operator choice) — if unanswered, the draft simply waits for the operator.
- **Serialized per chat:** the cron sends at most one outstanding prompt while a `telegram_schedule_state:{chat_id}` exists; the next ready draft waits until that state clears. Keeps free-text replies unambiguous.
- **Master toggle** `linkedin_telegram_schedule_enabled` (group `linkedin`, default `'false'`, seeded in `LinkedInSettingsSeeder`). When `'true'`, `AutoScheduleManualReviewLinkedInPosts` (`linkedin:auto-schedule`) **skips/defers** so the two never race. Enabling it supersedes `linkedin_auto_approve_enabled`.

#### The prompt message

- Compute 3 suggested slots via new `LinkedInFixedSlotScheduler::nextAvailableSlots(3)` — weekday + holiday + occupancy filtered (extends existing `weekdaysOnly` + occupancy logic with the holiday service).
- Inline buttons (HMAC-signed, `kind=schedule`): **slot index encoded**, not a timestamp — Telegram `callback_data` ≤ 64 bytes can't hold a full ISO string + signature. Button = `kind=schedule&action=slot&id={draft}&i={0..2}` + hmac. The 3 candidate ISO slots are stored in the cache state and resolved by index on tap.
- Body text: draft title + "Saran slot kosong berikutnya:" + the 3 buttons + "atau ketik tanggal & jam sendiri (mis. `17 Jun 18:00`)".

#### Conversation state

- Cache key `telegram_schedule_state:{chat_id}` = `{ draft_id, step: 'awaiting_datetime'|'awaiting_conflict_confirm', candidate_slots: [iso,iso,iso], proposed_slot, conflict_draft_id }`. TTL ~60 min.
- Cache (not a DB table) matches the existing `linkedin_regenerate_lock` idiom; expiry = safe self-cleanup. Single-operator → no multi-tenant concern.

#### Inbound handling (extends `TelegramWebhookController`)

- **Slot button tap** (`callback_query`, `kind=schedule`, `action=slot`, `i`): resolve `candidate_slots[i]` from cache → `LinkedInSchedulingService::scheduleAt()` (guaranteed free, came from `nextAvailableSlots`) → ack `✅ Dijadwalkan Sen 16 Jun · 12:00 WIB` → clear state.
- **Free-text reply** (`message`, state = `awaiting_datetime` for this chat): webhook returns 200 immediately + dispatches queued `ParseAndScheduleReply(chat_id, draft_id, text)`:
  - Job runs **Claude CLI** parse (claudesn worker context, reuses `RunsRepurposeClaudeCli`-style ssh|local exec + `--mcp-config empty-mcp.json --strict-mcp-config` guard). Prompt: given current WIB datetime + the user message, return JSON `{datetime: ISO8601|null, note}`. LLM ONLY parses; it does not pick slots.
  - Validate the parsed datetime: must be future, weekday, not a holiday, ≥ lead-time. Invalid → reply `⚠️ {date} jatuh di {akhir pekan|libur nasional|sudah lewat}. Saran terdekat: {nextAvailableSlots(1)}` → keep state (re-prompt).
  - Conflict check via `LinkedInScheduleConflictService` (±30 min): conflict → set step `awaiting_conflict_confirm` + `proposed_slot` + `conflict_draft_id` → reply `⚠️ Slot {time} sudah ada draft #Y ("{title}"). Tetap jadwalkan? [Ya, tetap] [Pilih lain]` (`action=confirm`/`reject`).
  - Clear → `scheduleAt()` → ack + clear state.
  - LLM returns null/ambiguous → reply with an example format + keep state.
- **Confirm/reject buttons** (`action=confirm`/`reject`): confirm → `scheduleAt()` at `proposed_slot` (operator override of the ±30 min collision) → ack + clear; reject → re-send the 3 suggestions, reset to `awaiting_datetime`.

#### Holiday service (static list, no API)

- New `IndonesianHolidayService::isHoliday(Carbon $date): bool`, backed by a static `config/id_holidays.php` map `year => ['YYYY-MM-DD' => 'Holiday name', ...]`. NO network, fully deterministic. National holidays + cuti bersama both treated as non-working. The operator-provided 2026 + 2027 lists seed it verbatim (weekend-falling holidays included for completeness — harmless, the weekend rule already excludes them).
- **2027 is a pre-SKB estimate** (lunar dates shift until the SKB 3 Menteri decree). Mark the 2027 block with a `// ESTIMATE — verify against official SKB` comment.
- **Missing-year fallback:** date in a year with no config entry → `isHoliday` returns `false` (degrade to weekend-only) **and logs a `warning`** (`[IndonesianHoliday] no data for year {Y} — weekend-only`) so the operator knows to add the new year + redeploy before that year's scheduling runs.
- Adding a future year = append a block to `config/id_holidays.php` + deploy. No code change, no migration.

#### Shared scheduling write

- Extract `LinkedInSchedulingService::scheduleAt(LinkedInPost $draft, Carbon $slot, string $reason)`: sets `scheduled_at` + `cancel_window_ends_at`, advances `manual_review → awaiting_publish` via `PipelineGuard`, propagates `scheduled_at` to cross-post siblings (mirrors `ScanLinkedInForCrossPost` slot propagation), clears the schedule prompt state.
- Reused by: admin `LinkedInDraftController::approve()` (refactor to call it), the Telegram slot button, and the Telegram free-text confirm. One write path, three callers.

#### Telegram notification methods (new, in `TelegramNotificationService`)

- `sendSchedulePrompt($draft, array $candidateSlots): bool`
- `sendScheduleConflict($draft, Carbon $proposed, LinkedInPost $conflict): bool`
- `sendScheduleConfirmed($draft, Carbon $slot): bool`
- `sendScheduleParseHelp($chatId, string $example): bool`
- Reuse `signCallback`/`verifyCallback` with `kind='schedule'`.

---

## Data Integration Map

| Component | Data source / store | Exists? | Notes |
|---|---|---|---|
| List re-authoring/rendering signal | `regenerate_activity` + `carousel_slides[].image_status` | Partial | add to `index()`; reuse `resolveCarouselActivity` |
| "Genuinely ready" gate | `captionReadinessForApproval` + slides done | ✅ | reused as Req 2 trigger |
| Next free slots (×3) | `LinkedInFixedSlotScheduler` | Extend | add `nextAvailableSlots(3)` + holiday skip |
| Holiday list | static `config/id_holidays.php` (2026+2027) | ❌ new | `IndonesianHolidayService`, no API; missing-year → weekend-only + log warn |
| Conflict ±30 min | `LinkedInScheduleConflictService` | ✅ | reused for free-text path |
| Free-text → datetime | Claude CLI (queued `ParseAndScheduleReply`) | ❌ new | reuses ssh/local exec + empty-mcp guard |
| Conversation state | Cache `telegram_schedule_state:{chat}` | ❌ new | TTL 60 min, serialized per chat |
| One-prompt idempotency | `linkedin_posts.schedule_prompt_sent_at` | ❌ new | nullable col, migration |
| Schedule write | `LinkedInSchedulingService::scheduleAt` | Extract | from existing `approve()` |
| Signed buttons | `signCallback`/`verifyCallback` `kind=schedule` | ✅ | reuse, index-encoded (64-byte limit) |
| Master toggle | `linkedin_telegram_schedule_enabled` | ❌ new | gates `linkedin:auto-schedule` defer |
| Prompt cron | `linkedin:prompt-schedule` | ❌ new | `ScheduledCommandSeeder` row |

---

## Edge cases & risks

- **64-byte callback_data** → slot encoded as index `i`, candidate ISO slots held in cache state. Cache miss/expiry on tap → reply "prompt kedaluwarsa, akan dikirim ulang" and let the cron re-prompt (since `schedule_prompt_sent_at` was set but draft still unscheduled — note: with "one prompt no reminder", expiry means operator must trigger via admin UI; document this).
- **Webhook latency** → Claude CLI parse is queued, never inline; webhook always returns fast 200 so Telegram doesn't retry.
- **Worker context** → parse job runs as claudesn (queue) with the SSH key + empty-mcp guard already established for repurpose/linkedin-gen.
- **Holiday year not in config** → `isHoliday` returns false (weekend-only) + log warning; scheduling never hard-blocked. Operator must add the new year before that year arrives.
- **Timezone** → all WIB (Asia/Jakarta) end to end.
- **Conflict override** allowed (operator can intentionally double-book within ±30 min).
- **Two drafts ready same tick** → serialized; only one open conversation per chat.
- **Stuck draft** (no reply) → stays `manual_review`, unscheduled, no reminder (operator choice). Visible in admin UI; operator can still approve there.
- **Master toggle default false** → zero behavior change until operator flips it on the VPS.

---

## Test surface (for the plan)

- `IndonesianHolidayServiceTest` — known holiday hit (e.g. 2026-08-17), non-holiday miss, missing-year returns false + logs warning, weekend-falling holiday still in list.
- `LinkedInFixedSlotSchedulerTest` — extend: holiday skip, `nextAvailableSlots(3)` returns 3 distinct free weekday slots.
- `LinkedInSchedulingServiceTest` — scheduleAt sets fields + advances FSM + propagates siblings.
- `PromptScheduleCommandTest` — fires only on genuinely-ready unscheduled drafts, idempotent via `schedule_prompt_sent_at`, defers when toggle off.
- `TelegramScheduleCallbackTest` — slot tap schedules; confirm/reject paths; HMAC verify.
- `ParseAndScheduleReplyTest` — CLI parse (Process::fake) → validate weekday/holiday → conflict → schedule; invalid/null re-prompt.
- `AutoScheduleDefersWhenTelegramEnabledTest` — `linkedin:auto-schedule` skips when master toggle on.
- Frontend: `socialStudioHelpers.test.mjs` — blogCard effective status = re_authoring/rendering when active, raw otherwise.

---

## Operator post-deploy

- `deploy.sh migrate --force` adds `schedule_prompt_sent_at` + idempotent seeders (master toggle + `linkedin:prompt-schedule` cron row; verify in `/admin/settings?tab=scheduler`).
- Flip `linkedin_telegram_schedule_enabled='true'` to activate (auto-schedule then defers).
- Ensure the queue worker + scheduler crontab are running; Claude CLI + empty-mcp already provisioned.
- **No API key** for holidays — list ships in `config/id_holidays.php`. Before 2028 (or when the 2027 SKB is officially released), add/verify the year block and redeploy; the log warning surfaces if a year is missing.

---

## Appendix — holiday data for `config/id_holidays.php`

Operator-provided. National holidays + cuti bersama; weekend-falling dates included verbatim (harmless).

**2026 (official):**
- 2026-01-01 Tahun Baru Masehi · 2026-01-16 Isra Mikraj · 2026-02-17 Tahun Baru Imlek · 2026-03-19 Nyepi · 2026-03-21..22 Idulfitri 1447 H · 2026-04-03 Wafat Yesus Kristus · 2026-04-05 Paskah · 2026-05-01 Hari Buruh · 2026-05-14 Kenaikan Yesus Kristus · 2026-05-27 Iduladha 1447 H · 2026-05-31 Waisak · 2026-06-01 Hari Lahir Pancasila · 2026-06-16 Tahun Baru Islam 1448 H · 2026-08-17 Kemerdekaan RI · 2026-08-25 Maulid Nabi · 2026-12-25 Natal

**2027 (ESTIMATE — verify vs official SKB before relying on it):**
- 2027-01-01 Tahun Baru Masehi · 2027-01-05 Isra Mikraj · 2027-02-06 Tahun Baru Imlek · 2027-03-09 Nyepi · 2027-03-10..11 Idulfitri 1448 H · 2027-03-26 Wafat Yesus Kristus · 2027-03-28 Paskah · 2027-05-01 Hari Buruh · 2027-05-06 Kenaikan Yesus Kristus · 2027-05-17 Iduladha 1448 H · 2027-05-20 Waisak · 2027-06-01 Hari Lahir Pancasila · 2027-06-06 Tahun Baru Islam 1449 H · 2027-08-15 Maulid Nabi · 2027-08-17 Kemerdekaan RI · 2027-12-25 Natal · 2027-12-26 Isra Mikraj (bagian 2)

> Note: most 2026/2027 holidays that fall on weekdays are the ones that matter for slot exclusion; weekend ones are redundant with the existing weekend rule but kept for a complete, paste-from-SKB list.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. NEVER substitute placeholders
> for real data sources without explicit user approval. If a data source doesn't
> exist, STOP and ask.
> **NO PHP on the dev Mac** — every backend test is authored TDD-first and run in
> Docker `serversideup/php:8.2-cli` with sqlite (`:memory:`). Frontend via
> `node --test` + `npm run build`. Backend "see it fail / pass" happens in Docker.

### Goal

Make the Social Studio list pill reflect the same transient carousel state the detail page shows (re-authoring + rendering), and add a Telegram human-in-the-loop scheduling conversation: when a draft is genuinely ready, the bot asks when to post, suggests weekday/holiday-aware free slots as buttons, accepts a typed date/time (parsed via Claude CLI), warns on ±30-min collisions, and writes the schedule — replacing the auto-schedule cron when enabled.

### Architecture Context (from CLAUDE.md + code probe)

- **Scheduler** [`LinkedInFixedSlotScheduler`](backend/app/Services/LinkedInFixedSlotScheduler.php): ctor `(?array $slots, ?int $leadTimeMinutes, ?bool $weekdaysOnly)`; `nextAvailableSlot(?Carbon $from)`; private `isOccupied()`; weekend skip at the day loop (`$this->weekdaysOnly && $dayDate->isWeekend()`). Slots from `linkedin_publish_slots`, lead time `linkedin_slot_lead_time_minutes`.
- **Conflict** [`LinkedInScheduleConflictService`](backend/app/Services/LinkedInScheduleConflictService.php): `hasConflict(Carbon $proposed, ?int $excludeDraftId, int $windowMinutes=30)`, `findConflicts(...)` → `[{id, post_title, scheduled_at, minutes_apart}]`.
- **Readiness** [`LinkedInSlotReadinessService`](backend/app/Services/LinkedInSlotReadinessService.php): `captionReadinessForApproval($draft)` → `['ready'=>bool,'blockers'=>[]]` (carousel-only; text → ready).
- **Schedule write today** lives inline in [`LinkedInDraftController::approve()`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L709): sets `scheduled_at` **and** `cancel_window_ends_at` both = slot, `guard->advance(manual_review → awaiting_publish)`, propagates `scheduled_at` to `facebookPost/instagramPost/tiktokPost/threadsPost` siblings.
- **Telegram callbacks** [`TelegramNotificationService::signCallback($action,$kind,$id,$secret)`](backend/app/Services/TelegramNotificationService.php#L469) → `"<action>:<kind>:<id>:<hmac12>"`; `verifyCallback` returns `['action','kind','id']`, requires exactly 4 colon-parts. **Slot encoding rides this with composite actions** (`slot0|slot1|slot2|confirm|reject`, `kind='schedule'`) — NO format change, well under 64 bytes.
- **CLI exec** [`RunsRepurposeClaudeCli`](backend/app/Services/Concerns/RunsRepurposeClaudeCli.php) trait: `runRepurposeParsed(...)` / `runRepurposeSync(...)`, ssh|local toggle, `--mcp-config empty-mcp --strict-mcp-config` guard, config `services.repurpose.*` (already provisioned). `ParseAndScheduleReply` reuses this trait.
- **Webhook** [`TelegramWebhookController`](backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php): `dispatchAction()` switches on `kind`; `handleMessage()` currently only IG URLs. Secret-header + HMAC two-layer auth already in place.
- **Seeders**: `LinkedInSettingsSeeder` (`firstOrCreate ['key','value','type']`), `ScheduledCommandSeeder` (`firstOrCreate` on `signature`, fields `signature/category/cron_expression/enabled/...`).
- **Front-end**: [`socialStudioHelpers.js::blogCard`](frontend/src/views/admin/socialStudioHelpers.js#L88) uses raw `draft.status`; [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js) owns `resolveCarouselActivity`. Pure `.mjs`, node-test'd.

### Tech Stack

Laravel 12 / PHP 8.2 (PHPUnit, sqlite in Docker), Vue 3 + Tailwind (node --test `.mjs`, `npm run build`). Reuse: `PipelineGuard`, `signCallback`/`verifyCallback`, `RunsRepurposeClaudeCli`, `LinkedInScheduleConflictService`, `LinkedInFixedSlotScheduler`, `Cache` (lock idiom), `TelegramNotificationService::send`.

### Data Integration Map (executor contract)

| Feature | Data source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| List re-auth/render pill | `regenerate_activity` + `carousel_slides[].image_status` | `index()` + `resolveCarouselActivity` | Partial | Add field to `index()`; reuse FE helper |
| Genuinely-ready gate | `captionReadinessForApproval` + slides `done` | `LinkedInSlotReadinessService` | Yes | Reuse in prompt cron |
| Next free slots ×3 | `LinkedInFixedSlotScheduler` | `nextAvailableSlots(3)` | Extend | Add method + holiday skip |
| Holiday check | `config/id_holidays.php` | `IndonesianHolidayService::isHoliday` | No | Create (static, no API) |
| Conflict ±30m | live drafts | `LinkedInScheduleConflictService::findConflicts` | Yes | Reuse in free-text path |
| Free-text → datetime | Claude CLI | `ParseAndScheduleReply` (uses `RunsRepurposeClaudeCli`) | No | Create queued job |
| Conversation state | Cache `telegram_schedule_state:{chat}` | `Cache::put/get` | No | Create (TTL 60m) |
| One-prompt idempotency | `linkedin_posts.schedule_prompt_sent_at` | migration | No | Add nullable column |
| Schedule write | sets sched+window, advances FSM, siblings | `LinkedInSchedulingService::scheduleAt` | Extract | From `approve()` |
| Signed buttons | `kind=schedule` composite actions | `signCallback`/`verifyCallback` | Yes | Reuse as-is |
| Master toggle | `linkedin_telegram_schedule_enabled` | `Setting` | No | Seed + gate auto-schedule |
| Prompt cron | scan ready drafts | `linkedin:prompt-schedule` | No | Command + seeder row |

---

### Phase A — migration + settings

**Files:** Create `database/migrations/2026_06_12_000005_add_schedule_prompt_sent_at_to_linkedin_posts.php`, `tests/Feature/LinkedInScheduleSettingsSeederTest.php`; Modify `app/Models/LinkedInPost.php` (`$fillable` + `$casts` datetime), `database/seeders/LinkedInSettingsSeeder.php`.

**Steps:**
1. Write failing test for the seeder adding `linkedin_telegram_schedule_enabled='false'`. Expected error: `Failed asserting that a row in table 'settings' matches ...` (key absent).
2. Run in Docker, confirm it fails for that reason.
3. Add nullable `schedule_prompt_sent_at` timestamp migration; add column to `LinkedInPost` `$fillable` + `$casts => 'datetime'`.
4. Add `['key'=>'linkedin_telegram_schedule_enabled','value'=>'false','type'=>'text']` to `LinkedInSettingsSeeder` linkedin group.
5. Run test in Docker, confirm pass.
6. Commit: `feat(li-schedule): schedule_prompt_sent_at column + telegram-schedule master toggle`.

**Verification:**
- [ ] Docker `php artisan migrate` + seeder run clean on sqlite
- [ ] Seeder idempotent (firstOrCreate) — re-run adds 0 rows
- [ ] `LinkedInPost::create([... 'schedule_prompt_sent_at' => now()])` casts to Carbon
- [ ] No TODO/placeholder

### Phase B — Indonesian holiday config + service

**Files:** Create `config/id_holidays.php`, `app/Services/IndonesianHolidayService.php`, `tests/Unit/IndonesianHolidayServiceTest.php`.

**Steps:**
1. Write failing test: `isHoliday(Carbon::parse('2026-08-17'))===true`, `2026-08-18===false`, missing-year `2099-01-01===false` AND logs a warning (`Log::shouldReceive('warning')`). Expected error: `Error: Class "App\Services\IndonesianHolidayService" not found`.
2. Run in Docker, confirm fail.
3. Create `config/id_holidays.php` returning `[2026 => ['2026-01-01'=>'...', ... per Appendix], 2027 => [ /* ESTIMATE — verify vs official SKB */ ... ]]` (full operator lists).
4. Implement `IndonesianHolidayService::isHoliday(Carbon $d): bool` — `$year=$d->year`; if `!isset($map[$year])` → `Log::warning('[IndonesianHoliday] no data for year '.$year.' — weekend-only')` + return false; else `array_key_exists($d->format('Y-m-d'), $map[$year])`.
5. Run test in Docker, confirm pass.
6. Commit: `feat(holidays): static IndonesianHolidayService (2026 official + 2027 estimate)`.

**Verification:**
- [ ] Known weekday holiday → true; ordinary weekday → false
- [ ] Missing year → false + single warning log
- [ ] 2027 block carries `ESTIMATE` comment
- [ ] No network call anywhere in the service

### Phase C — scheduler holiday skip + `nextAvailableSlots(3)`

**Files:** Modify `app/Services/LinkedInFixedSlotScheduler.php`; create/extend `tests/Unit/LinkedInFixedSlotSchedulerHolidayTest.php`.

**Steps:**
1. Write failing test: a slot on a configured holiday is skipped to the next eligible weekday, and `nextAvailableSlots(3)` returns 3 distinct ascending free slots (inject a fake/real `IndonesianHolidayService`, fixed `Carbon::setTestNow`). Expected error: `Error: Call to undefined method ...::nextAvailableSlots()`.
2. Run in Docker, confirm fail.
3. Resolve `IndonesianHolidayService` lazily in `ensureResolved()` (or `app()` fallback so existing `new LinkedInFixedSlotScheduler(...)` callers keep working). Add `|| $this->holidays->isHoliday($dayDate)` to the day-skip condition (alongside `isWeekend`). Holiday skip applies regardless of `weekdaysOnly` (a national holiday is never a valid slot).
4. Add `public function nextAvailableSlots(int $count = 3): array` — loop `nextAvailableSlot($cursor)`, push, advance `$cursor = $slot->copy()->addMinute()`, until `$count`.
5. Run test in Docker, confirm pass.
6. Commit: `feat(scheduler): skip Indonesian holidays + nextAvailableSlots(3)`.

**Verification:**
- [ ] Holiday date never returned as a slot (even when weekdays_only off)
- [ ] `nextAvailableSlots(3)` → 3 ascending, occupancy- + holiday- + weekend-filtered
- [ ] Existing `LinkedInFixedSlotSchedulerTest` still green (no regression)
- [ ] Backward-compatible ctor (no required new arg)

### Phase D — extract `LinkedInSchedulingService::scheduleAt`

**Files:** Create `app/Services/LinkedInSchedulingService.php`, `tests/Feature/LinkedInSchedulingServiceTest.php`; Modify `LinkedInDraftController::approve()` to delegate.

**Steps:**
1. Write failing test: `scheduleAt($draft, $slot, 'reason')` sets `scheduled_at`+`cancel_window_ends_at`=slot, advances `manual_review→awaiting_publish` (PipelineGuard), propagates `scheduled_at` to a tiktok sibling, clears `schedule_prompt_sent_at`. Expected error: `Error: Class "App\Services\LinkedInSchedulingService" not found`.
2. Run in Docker, confirm fail.
3. Implement `scheduleAt(LinkedInPost $draft, Carbon $slot, string $reason): void` — extract the body of `approve()` lines ~835–845 verbatim (set both timestamps, `guard->advance(...AwaitingPublish, $reason)`, foreach sibling rel `->update(['scheduled_at'=>$slot])`), plus `$draft->update(['schedule_prompt_sent_at'=>null])`.
4. Refactor `approve()` to call `app(LinkedInSchedulingService::class)->scheduleAt($draft, $publishAt, 'admin_approve')` — behavior identical.
5. Run `LinkedInDraft*` suite in Docker, confirm pass (approve unchanged externally).
6. Commit: `refactor(li-schedule): extract LinkedInSchedulingService::scheduleAt`.

**Verification:**
- [ ] approve() endpoint behavior byte-identical (existing approve tests green)
- [ ] scheduleAt advances FSM via PipelineGuard + propagates siblings
- [ ] Reused by 3 callers later (admin/button/free-text) — single write path
- [ ] No duplicated scheduling logic remains in approve()

### Phase E — `linkedin:prompt-schedule` cron + auto-schedule defer

**Files:** Create `app/Console/Commands/PromptScheduleReadyDrafts.php`, `tests/Feature/PromptScheduleCommandTest.php`, `tests/Feature/AutoScheduleDefersWhenTelegramEnabledTest.php`; Modify `database/seeders/ScheduledCommandSeeder.php`, `app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php`, `app/Services/TelegramNotificationService.php` (the `sendSchedulePrompt` stub used here — full impl in Phase F).

**Steps:**
1. Write failing test: command sends one prompt for a genuinely-ready unscheduled draft, stamps `schedule_prompt_sent_at`, skips on 2nd run (idempotent), and no-ops when `linkedin_telegram_schedule_enabled!=true`. Expected error: `The command "linkedin:prompt-schedule" does not exist.`
2. Run in Docker, confirm fail.
3. Implement command: gate on `linkedin_telegram_schedule_enabled==='true'`; query `manual_review` drafts where `scheduled_at IS NULL AND schedule_prompt_sent_at IS NULL`; ready = carousel→all slides `done` AND `captionReadinessForApproval()->ready`, text→true; **serialize**: skip if `Cache::has("telegram_schedule_state:{$chatId}")`; compute `nextAvailableSlots(3)`, store candidate ISO slots in `telegram_schedule_state:{chat}` `{draft_id, step:'awaiting_datetime', candidate_slots}` (TTL 60m), call `telegram->sendSchedulePrompt($draft,$slots)`, stamp `schedule_prompt_sent_at`. Process at most one draft per run (one open conversation).
4. Add `AutoScheduleManualReviewLinkedInPosts::handle()` early-return when `linkedin_telegram_schedule_enabled==='true'` (log defer).
5. Add `ScheduledCommandSeeder` row `linkedin:prompt-schedule`, category `linkedin`, cron `* * * * *`, enabled true.
6. Run both tests in Docker, confirm pass.
7. Commit: `feat(li-schedule): prompt-schedule cron + auto-schedule defer`.

**Verification:**
- [ ] One prompt per ready draft, idempotent via `schedule_prompt_sent_at`; **no reminder** path
- [ ] Serialized: no 2nd prompt while a conversation state exists
- [ ] Toggle off → command + sibling cron both no-op
- [ ] `linkedin:auto-schedule` defers when toggle on (regression test green)
- [ ] Seeder row appears (idempotent)

### Phase F — Telegram conversation (buttons + free-text parse job)

**Files:** Modify `TelegramNotificationService.php` (send methods), `TelegramWebhookController.php` (kind=schedule callback + message branch); Create `app/Jobs/ParseAndScheduleReply.php`, `tests/Feature/TelegramScheduleCallbackTest.php`, `tests/Feature/ParseAndScheduleReplyTest.php`, `tests/Unit/TelegramScheduleNotificationTest.php`.

**Steps:**
1. Write failing test: tapping `slot1` (callback `slot1:schedule:{id}:{hmac}`) reads `candidate_slots[1]` from cache and calls `LinkedInSchedulingService::scheduleAt` then clears state; invalid HMAC rejected. Expected error: `dispatchAction` returns 'Unknown target.' (no `schedule` kind yet).
2. Run in Docker, confirm fail.
3. Add `TelegramNotificationService` methods: `sendSchedulePrompt($draft, array $candidateSlots)` (3 buttons `signCallback("slot{$i}",'schedule',$id,$secret)` + custom-type hint), `sendScheduleConflict($draft, Carbon $proposed, LinkedInPost $conflict)` (`[Ya, tetap]=confirm`, `[Pilih lain]=reject`), `sendScheduleConfirmed($draft, Carbon $slot)`, `sendScheduleParseHelp($chatId,$example)`.
4. `TelegramWebhookController::dispatchAction` → add `kind==='schedule'` → `resolveScheduleAction($draft,$action)`: `slot0|1|2` → resolve cache `candidate_slots[i]` → `scheduleAt` + `sendScheduleConfirmed` + clear state (cache-miss → "prompt kedaluwarsa"); `confirm` → `scheduleAt($proposed_slot)` (override) + clear; `reject` → re-send `nextAvailableSlots(3)`, reset state to `awaiting_datetime`.
5. `TelegramWebhookController::handleMessage` → **before** IG-URL logic: if `Cache::has("telegram_schedule_state:{$chat}")` with step `awaiting_datetime` and text isn't an IG URL → `ParseAndScheduleReply::dispatch($chat,$draftId,$text)` + return (webhook stays fast).
6. Implement `ParseAndScheduleReply` (uses `RunsRepurposeClaudeCli`): CLI prompt = current WIB datetime + user text → JSON `{datetime:ISO|null,note}`; null/parse-fail → `sendScheduleParseHelp` (keep state); else validate future + weekday + `!isHoliday` (else re-prompt with `nextAvailableSlots(1)` suggestion); then `LinkedInScheduleConflictService::findConflicts(±30)` → conflict → set state `awaiting_conflict_confirm`+`proposed_slot`+`conflict_draft_id` + `sendScheduleConflict`; else `scheduleAt` + `sendScheduleConfirmed` + clear.
7. Run all 3 tests in Docker (Process::fake for the CLI), confirm pass.
8. Commit: `feat(li-schedule): telegram scheduling conversation (buttons + claude-cli free-text)`.

**Verification (security-sensitive — webhook + user input + signed callbacks):**
- [ ] HMAC `verifyCallback` enforced; forged/expired callback rejected (no schedule write)
- [ ] callback_data ≤ 64 bytes (composite action, no timestamp in payload)
- [ ] Webhook returns 200 fast; CLI parse runs only in the queued job (claudesn + empty-mcp guard)
- [ ] Weekend/holiday/past datetime rejected with re-prompt; conflict → confirm-back; override schedules
- [ ] No secrets in source; chat allowlist (`telegram_chat_id`) honored; Process::fake test green

### Phase G — (folded into E) confirm auto-schedule defer

Covered by Phase E step 4 + `AutoScheduleDefersWhenTelegramEnabledTest`. No separate commit. **Verification:** [ ] toggle-on defer asserted green.

### Phase H — Req 1: list pill == detail

**Files:** Modify `LinkedInDraftController::index()`; Modify `frontend/src/views/admin/socialStudioHelpers.js`; Create/extend `frontend/src/views/admin/socialStudioHelpers.test.mjs` (+ reuse `resolveCarouselActivity` from `linkedinHelpers.js`).

**Steps:**
1. Write failing node test: `blogCard` for a draft with a `reauthoring` slide → card status reflects `re_authoring` (not raw `manual_review`); slides mid-`generating` → `rendering`; all done/text → raw status. Expected error: assertion fail (currently returns `draft.status`).
2. Run `node --test`, confirm fail.
3. Backend: in `index()` `array_map`, attach `$draft->regenerate_activity = $this->resolveRegenerateActivity($draft)` per row (method already exists/private → reuse; make callable from index). Keep `carousel_slides` image_status (already present).
4. Frontend: `socialStudioHelpers.js` import-free constraint — inline a tiny `effectiveStatus(draft)` that reads `regenerate_activity.active` + `carousel_slides` image_status to yield `re_authoring`/`rendering`/raw (mirror `resolveCarouselActivity`’s non-ready phases; keep `.mjs` import-free per the file’s header note). `blogCard.status = effectiveStatus(draft)`.
5. Run `node --test` + `npm run build`, confirm pass + clean build.
6. Commit: `fix(social-studio): list pill matches detail (re-authoring + rendering)`.

**Verification:**
- [ ] List card status == detail phase for re_authoring + rendering
- [ ] Text drafts + done carousels show raw status (no false "rendering")
- [ ] `.mjs` stays import-free (node --test passes without vue/alias resolution)
- [ ] `npm run build` clean; tab counts still keyed on FSM `status`

### Phase I — docs sync

**Files:** Modify root `CLAUDE.md` (Last Updated changelog + linkedin settings table row `linkedin_telegram_schedule_enabled` + scheduler inventory `linkedin:prompt-schedule`), this plan’s status → shipped.

**Steps:**
1. Update CLAUDE.md changelog entry summarizing both reqs + the new setting/cron/config/service/job/migration.
2. Add `linkedin_telegram_schedule_enabled` to the `settings` group `linkedin` table; add `linkedin:prompt-schedule` to the scheduler inventory list.
3. Commit: `docs(li-schedule): CLAUDE.md sync (Phase I)`.

**Verification:**
- [ ] Settings table + scheduler inventory + Last Updated all reflect the new pieces
- [ ] Operator post-deploy steps (migrate, flip toggle, no holiday API) documented

---

### Execution order & parallelism

Sequential dependency chain: **A → B → C → D → E → F**, then **H** (independent of E/F, can run in parallel with E/F), then **I** last.
- Parallelizable: **H** (Req 1, frontend-mostly) is file-disjoint from B–G and can run alongside E/F via `gaspol-parallel`.
- Strictly sequential: B→C (scheduler needs the holiday service), C→E (cron needs `nextAvailableSlots`), D→F (conversation needs `scheduleAt`), A before E/F (column + toggle).

### Handoff

- **Option 1 (recommended):** `gaspol-execute` per-phase A→I with Docker test gates.
- **Option 2:** `gaspol-parallel plan-phases` — run **H** concurrently with the E/F backend track once A–D land.
- **Option 3:** Save for a fresh session — this file has the full design + plan.
