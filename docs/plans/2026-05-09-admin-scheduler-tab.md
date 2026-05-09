# Admin Scheduler Tab — DB-Driven Cron Management

**Date:** 2026-05-09
**Author:** Ali Sadikin (via gaspol-brainstorm)
**Status:** Design — pending plan + implementation

## Problem

Schedules saat ini hardcoded di [`routes/console.php`](../../backend/routes/console.php) — 9+ cron entries (`linkedin:scan-blog` 03:00 WIB, `linkedin:purge-low-virality` 04:00, `linkedin:auto-schedule` 04:30, `content:auto-pipeline` 05:00, `linkedin:process-scheduled` setiap menit, `linkedin:reap-stuck-carousel-images` setiap 5 menit, `pull-trending-daily`, `newsletter:send-weekly` Jumat 09:00, `posting-rules:research` quarterly).

Operator butuh:
1. **Enable / disable** per-schedule tanpa deploy
2. **Adjust timing** (cron expression atau preset) tanpa edit code
3. **Visibility** ke last run + status + error
4. **Run-now** untuk testing
5. **Forward-compat slot** untuk Instagram / Facebook / TikTok publish + scan (commands belum ada)

## Decisions (locked)

| Decision | Pick | Rationale |
|---|---|---|
| Storage | Tabel baru `scheduled_commands` | Audit columns + dynamic registration |
| Disable behavior | Stop new runs only | No race condition dengan queued jobs; simplest semantics |
| Cron input UX | Preset dropdown + custom cron field | Cover 95% kasus dengan preset, escape hatch untuk power user |
| IG/FB/TikTok | "Coming soon" placeholder rows | Visibility ke roadmap di tab yang sama; flip flag ketika command real ada |

## Architecture

### 1. Schema

Migration `2026_05_09_create_scheduled_commands_table`:

```php
Schema::create('scheduled_commands', function (Blueprint $t) {
    $t->id();
    $t->string('signature')->unique();              // "linkedin:scan-blog"
    $t->string('display_name');                     // "LinkedIn — Scan Blog for Conversion"
    $t->text('description')->nullable();
    $t->enum('category', [
        'content_engine', 'linkedin', 'instagram',
        'facebook', 'tiktok', 'newsletter', 'system',
    ])->index();
    $t->string('cron_expression', 64);              // "0 3 * * *"
    $t->string('timezone', 64)->default('Asia/Jakarta');
    $t->boolean('enabled')->default(true)->index();
    $t->boolean('is_placeholder')->default(false);  // IG/FB/TikTok scaffolding
    $t->unsignedSmallInteger('without_overlapping_minutes')->nullable();
    $t->boolean('run_in_background')->default(false);
    $t->timestamp('last_run_at')->nullable();
    $t->timestamp('last_finished_at')->nullable();
    $t->enum('last_status', ['success', 'failed', 'running', 'never'])->default('never');
    $t->unsignedInteger('last_duration_ms')->nullable();
    $t->text('last_error')->nullable();
    $t->unsignedSmallInteger('sort_order')->default(0);
    $t->timestamps();
});
```

### 2. Dynamic registrar — replaces hardcoded console.php

```php
// app/Services/DynamicScheduleRegistrar.php
public function register(Schedule $schedule): void
{
    if (!Schema::hasTable('scheduled_commands')) return;  // pre-migrate safety

    ScheduledCommand::where('enabled', true)
        ->where('is_placeholder', false)
        ->each(function (ScheduledCommand $cmd) use ($schedule) {
            $event = $schedule->command($cmd->signature)
                ->cron($cmd->cron_expression)
                ->timezone($cmd->timezone);

            if ($cmd->without_overlapping_minutes) {
                $event->withoutOverlapping($cmd->without_overlapping_minutes);
            }
            if ($cmd->run_in_background) {
                $event->runInBackground();
            }
        });
}
```

[`routes/console.php`](../../backend/routes/console.php) jadi:

```php
Schedule::call(fn () => null);  // hold for closure scope
app(DynamicScheduleRegistrar::class)->register(app(Schedule::class));
```

(Atau wire via `Kernel::schedule()` untuk lebih clean — finalisasi saat plan.)

### 3. Audit hook — Laravel events, zero command coupling

```php
// app/Providers/AppServiceProvider.php boot()
Event::listen(ScheduledTaskStarting::class, function ($e) {
    ScheduledCommand::where('signature', $this->extractSignature($e->task))
        ->update([
            'last_run_at' => now(),
            'last_status' => 'running',
            'last_error' => null,
        ]);
});

Event::listen(ScheduledTaskFinished::class, function ($e) { /* mark success + duration */ });
Event::listen(ScheduledTaskFailed::class, function ($e) { /* mark failed + error */ });
```

`extractSignature()` parse `php artisan linkedin:scan-blog --hours=24` → `linkedin:scan-blog`.

### 4. API routes (auth:sanctum)

```
GET    /api/admin/scheduler                 list grouped by category, with next_run computed
PUT    /api/admin/scheduler/{id}            update enabled / cron_expression / description / etc
POST   /api/admin/scheduler/{id}/run        Artisan::queue($signature) — async via queue worker
POST   /api/admin/scheduler/refresh-status  optional: re-derive last_run from logs (manual sync)
```

Response shape:

```json
{
  "groups": {
    "linkedin": [
      {
        "id": 3, "signature": "linkedin:scan-blog",
        "display_name": "LinkedIn — Scan Blog for Conversion",
        "cron_expression": "0 3 * * *", "timezone": "Asia/Jakarta",
        "enabled": true, "is_placeholder": false,
        "next_run_at": "2026-05-10T03:00:00+07:00",
        "last_run_at": "2026-05-09T03:00:02+07:00", "last_status": "success",
        "last_duration_ms": 1842, "last_error": null
      }
    ],
    "instagram": [
      {
        "id": 12, "signature": "placeholder:instagram-publish",
        "display_name": "Instagram — Auto Publish (Coming soon)",
        "is_placeholder": true, "enabled": false,
        ...
      }
    ]
  }
}
```

### 5. UI — `SchedulerSettings.vue` (new tab di /admin/settings)

- Header: title + "Refresh status" button
- Tabel grouped per category, collapsible sections
- Per row 6 kolom:
  - **Name** — display_name (bold) + signature monospace small
  - **Schedule** — preset dropdown (Every minute / Every 5 min / Hourly / Daily HH:MM WIB / Weekly DAY HH:MM / Monthly / Quarterly / **Custom**) + raw cron field bila Custom + human-readable preview ("At 03:00 daily, Asia/Jakarta") via `dragonmantank/cron-expression` (already Laravel dep)
  - **Enabled** — toggle switch (locked + grey untuk placeholder)
  - **Last Run** — relative time + status pill (emerald success / red failed / cyan-pulse running / grey never), tooltip = absolute datetime + duration
  - **Next Run** — relative ("in 4h 22m") + tooltip absolute, computed dari cron expression server-side
  - **Actions** — `▶ Run Now` button (locked untuk placeholder), `✎` edit description inline
- Save inline per row dengan optimistic update via TanStack Query (mirror pattern `useLinkedInDrafts.js`)
- Placeholder rows: amber background tint, lock icon, badge "Coming soon", tooltip "Command not yet implemented — will activate when shipped"

### 6. Seeder

```php
// database/seeders/ScheduledCommandSeeder.php — idempotent firstOrCreate
$rows = [
    // Content Engine
    ['signature' => 'pull-trending-daily', 'display_name' => 'Content Engine — Pull Trending Daily',
     'category' => 'content_engine', 'cron_expression' => '0 6 * * *', 'sort_order' => 10],
    ['signature' => 'content:auto-pipeline', 'display_name' => 'Content Engine — Auto Pipeline Tick',
     'category' => 'content_engine', 'cron_expression' => '0 5 * * *', 'sort_order' => 20],

    // LinkedIn
    ['signature' => 'linkedin:scan-blog', 'display_name' => 'LinkedIn — Scan Blog for Conversion',
     'category' => 'linkedin', 'cron_expression' => '0 3 * * *', 'sort_order' => 10],
    ['signature' => 'linkedin:purge-low-virality', 'display_name' => 'LinkedIn — Purge Low-Virality Drafts',
     'category' => 'linkedin', 'cron_expression' => '0 4 * * *', 'sort_order' => 20],
    ['signature' => 'linkedin:auto-schedule', 'display_name' => 'LinkedIn — Auto-Schedule Manual Review',
     'category' => 'linkedin', 'cron_expression' => '30 4 * * *', 'sort_order' => 30],
    ['signature' => 'linkedin:process-scheduled', 'display_name' => 'LinkedIn — Process Scheduled Posts',
     'category' => 'linkedin', 'cron_expression' => '* * * * *', 'sort_order' => 40,
     'without_overlapping_minutes' => 5],
    ['signature' => 'linkedin:reap-stuck-carousel-images', 'display_name' => 'LinkedIn — Reap Stuck Carousel Images',
     'category' => 'linkedin', 'cron_expression' => '*/5 * * * *', 'sort_order' => 50],
    ['signature' => 'linkedin:reap-stuck', 'display_name' => 'LinkedIn — Reap Stuck Generation',
     'category' => 'linkedin', 'cron_expression' => '*/10 * * * *', 'sort_order' => 60],
    ['signature' => 'posting-rules:research --platform=linkedin', 'display_name' => 'LinkedIn — Quarterly Posting Rules Research',
     'category' => 'linkedin', 'cron_expression' => '0 3 1 */3 *', 'sort_order' => 70],

    // Newsletter
    ['signature' => 'newsletter:send-weekly', 'display_name' => 'Newsletter — Weekly Friday Digest',
     'category' => 'newsletter', 'cron_expression' => '0 9 * * 5', 'sort_order' => 10,
     'without_overlapping_minutes' => 60],

    // Placeholders (is_placeholder=true, enabled=false)
    ['signature' => 'placeholder:instagram-scan', 'display_name' => 'Instagram — Scan Blog (Coming soon)',
     'category' => 'instagram', 'cron_expression' => '0 3 * * *', 'is_placeholder' => true, 'enabled' => false, 'sort_order' => 10],
    ['signature' => 'placeholder:instagram-publish', 'display_name' => 'Instagram — Auto Publish (Coming soon)',
     'category' => 'instagram', 'cron_expression' => '* * * * *', 'is_placeholder' => true, 'enabled' => false, 'sort_order' => 20],
    ['signature' => 'placeholder:facebook-publish', 'display_name' => 'Facebook — Auto Publish (Coming soon)',
     'category' => 'facebook', 'cron_expression' => '* * * * *', 'is_placeholder' => true, 'enabled' => false, 'sort_order' => 10],
    ['signature' => 'placeholder:tiktok-publish', 'display_name' => 'TikTok — Auto Publish (Coming soon)',
     'category' => 'tiktok', 'cron_expression' => '* * * * *', 'is_placeholder' => true, 'enabled' => false, 'sort_order' => 10],
];
```

Re-run safe via `firstOrCreate(['signature' => $row['signature']], $row)`.

## Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| `SchedulerSettings.vue` table | `GET /api/admin/scheduler` | NEW | TanStack Query, 30s staleTime, refetchOnMount:'always' |
| Toggle / cron edit | `PUT /api/admin/scheduler/{id}` | NEW | Optimistic update, rollback on error |
| Run Now | `POST /api/admin/scheduler/{id}/run` → `Artisan::queue` | EXISTS (queue worker) | Same `portfolio-queue.service` systemd unit |
| Audit columns | `ScheduledTaskFinished/Failed` events | EXISTS (Laravel) | `AppServiceProvider::boot()` listener |
| Cron preview ("At 03:00 daily") | `dragonmantank/cron-expression::Cron\CronExpression` | EXISTS (Laravel dep) | Server-side compute next_run_at |
| Tab navigation | [`SettingsForm.vue`](../../frontend/src/views/admin/SettingsForm.vue) tab strip | EXISTS | Add "Scheduler" tab + lazy-load route |

## YAGNI — intentionally NOT included

- ❌ Slack / Discord notify on failure → existing Telegram path bisa di-extend later
- ❌ Per-user permissions (who can toggle which) → admin = all, single-operator project
- ❌ Schedule history table (separate audit log) → `last_*` columns enough; install Telescope kalau butuh deep audit
- ❌ Multi-env support (`enabled_in_production`) → single-env project (XAMPP local + 1 VPS)
- ❌ Cron syntax wizard/builder UI → preset dropdown sudah cover 95% kasus
- ❌ Hot-reload schedules tanpa wait next minute → `schedule:run` re-reads DB tiap minute, max delay 60s
- ❌ Disable + drain queued jobs → "stop new runs only" semantics (locked decision above)

## Open questions for plan phase

1. **Migration safety**: existing schedules di [routes/console.php](../../backend/routes/console.php) tidak boleh ada double-run window saat deploy. Plan harus include: (a) seeder runs in `migrate --seed` order so `scheduled_commands` populated BEFORE `console.php` switches to dynamic registrar, (b) gunakan `Schema::hasTable` guard di registrar untuk safe boot pre-migration.

2. **`signature` matching dengan args**: command `posting-rules:research --platform=linkedin` punya argument inline. `extractSignature()` di event listener perlu strip args. Atau seed pakai signature bare `posting-rules:research` + simpan args terpisah di kolom `arguments` JSON. **Recommend kolom `arguments` JSON** — cleaner.

3. **Backward compat untuk schedule yang dipanggil dari kode lain**: cek apa ada `Schedule::call(fn() ...)` di `routes/console.php` yang BUKAN command — mereka tidak fit pattern dan harus tetap di console.php.

## Resolved open questions

1. **Migration safety** — `Schema::hasTable('scheduled_commands')` guard di registrar lets pre-migration boot pass. Seeder ditandai `--class=ScheduledCommandSeeder` di `DatabaseSeeder::run()` so `php artisan migrate --seed` populates rows BEFORE next `schedule:run` tick.

2. **Signature + args** — Add `arguments` JSON column. Seeder splits `'linkedin:scan-blog --hours=24'` into `signature='linkedin:scan-blog'` + `arguments=['--hours=24']`. Registrar concats at register time. Audit hook matches by signature alone (args identical per row).

3. **Non-command closures** — Verified via Read of [routes/console.php](backend/routes/console.php). Zero `Schedule::call(closure)` entries; only `Artisan::command('inspire')` which is a command DEFINITION, not a schedule. Stays.

4. **`content:auto-pipeline` 8x/day foreach loop** — Convert to single cron expression `0 5,6,12,15,17,18,19,20 * * *`. Single row, unique signature preserved.

## Final command inventory (14 real + 4 placeholder = 18 rows)

| # | Signature | Args | Schedule | Category | withoutOverlap |
|---|---|---|---|---|---|
| 1 | content:process-scheduled | — | `* * * * *` | content_engine | — |
| 2 | blog:process-images | — | `* * * * *` | content_engine | — |
| 3 | content:process-pending-translations | — | `*/5 * * * *` | content_engine | 5 |
| 4 | content:auto-pipeline | — | `0 5,6,12,15,17,18,19,20 * * *` | content_engine | 10 |
| 5 | content:pull-trending-daily | — | `0 5 * * *` | content_engine | — |
| 6 | linkedin:process-scheduled | — | `* * * * *` | linkedin | 5 |
| 7 | linkedin:scan-blog | `--hours=24` | `0 3 * * *` | linkedin | 30 |
| 8 | linkedin:reap-stuck | — | `*/5 * * * *` | linkedin | 5 |
| 9 | linkedin:retry-failed | — | `*/10 * * * *` | linkedin | 15 |
| 10 | linkedin:reap-stuck-carousel-images | — | `*/5 * * * *` | linkedin | 5 |
| 11 | linkedin:purge-low-virality | — | `0 4 * * *` | linkedin | 15 |
| 12 | linkedin:auto-schedule | — | `30 4 * * *` | linkedin | 15 |
| 13 | newsletter:send-weekly | — | `0 9 * * 5` | newsletter | 60 |
| 14 | posting-rules:research | `--platform=linkedin` | `0 3 1 */3 *` | system | 15 |
| 15 | (reserved for future) | | | | |
| 16-19 | placeholder:instagram-scan, placeholder:instagram-publish, placeholder:facebook-publish, placeholder:tiktok-publish | — | various defaults | instagram/facebook/tiktok | — |

All real entries default `enabled=true`. All placeholders `is_placeholder=true, enabled=false`. Timezone `Asia/Jakarta` for all daily/weekly entries.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship `/admin/settings` "Scheduler" tab letting operator enable/disable, retime, and run-now any cron schedule from a DB-driven table — replacing 15 hardcoded entries in [routes/console.php](backend/routes/console.php) with a single `DynamicScheduleRegistrar::register()` call. Audit columns (last_run, status, duration, error) populated automatically via Laravel `ScheduledTask*` events. Forward-compat placeholders for IG/FB/TikTok seeded as "Coming soon" rows that flip-on when commands ship.

### Architecture Context

**Reuses (per CLAUDE.md):**
- Settings group + admin card pattern (`creator_brand`, `telegram`, `linkedin`, `mail` precedents)
- Auth: `auth:sanctum` middleware on admin routes
- TanStack Query composable pattern: `useLinkedInDrafts.js` (30s staleTime + `refetchOnMount:'always'`)
- Tab strip in [SettingsForm.vue](frontend/src/views/admin/SettingsForm.vue)
- Queue worker (`portfolio-queue.service` systemd unit) handles `Artisan::queue` from Run-Now button
- Host crontab (`portfolio-scheduler`) fires `php artisan schedule:run` per minute — reads new DB-driven schedules

**Creates new:**
- 1 table `scheduled_commands` (no FKs, no migration risk)
- 1 model + 1 factory + 1 service + 1 seeder + 1 controller
- 1 frontend composable + 1 view + 1 tab entry

**Stack:** Laravel 12 + PHP 8.2 + MySQL 8 (backend), Vue 3.5 + Pinia 3 + TanStack Query 5.90 + Tailwind 4 (frontend). `dragonmantank/cron-expression` already a Laravel transitive dep — used for cron parsing + next_run computation.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Schedule list table | `GET /api/admin/scheduler` | NEW `useScheduler()` composable | No | Create new (Phase F) |
| Toggle enabled / edit cron | `PUT /api/admin/scheduler/{id}` | `useScheduler.update()` mutation | No | Create new (Phase F) |
| Run Now button | `POST /api/admin/scheduler/{id}/run` → `Artisan::queue` | `useScheduler.runNow()` mutation | Backend-side EXISTS (Artisan + queue worker), endpoint NEW | Create endpoint (Phase E), wire UI (Phase F) |
| Audit columns (last_run_at, last_status, last_error, last_duration_ms) | Laravel events `ScheduledTaskStarting/Finished/Failed` | `AppServiceProvider::boot()` listeners | Laravel events EXIST, listeners NEW | Create listeners (Phase D) |
| Cron parse + next_run computation | `Cron\CronExpression::factory($expr)->getNextRunDate(...)` | direct lib call in Resource | EXISTS (transitive Laravel dep) | Use directly (Phase E) |
| Schedule registration | `Schedule::command()->cron()->timezone()->withoutOverlapping()` | `DynamicScheduleRegistrar::register($schedule)` | NEW service | Create service (Phase C) |
| Tab navigation in /admin/settings | [SettingsForm.vue](frontend/src/views/admin/SettingsForm.vue) tab strip + router | `<router-link>` + lazy-loaded route | EXISTS | Add tab + route entry (Phase F) |

### Phase A: Schema + Model + Factory

**Estimated time:** 25 min

**Files:**
- Create: `backend/database/migrations/2026_05_09_000001_create_scheduled_commands_table.php`
- Create: `backend/app/Models/ScheduledCommand.php`
- Create: `backend/database/factories/ScheduledCommandFactory.php`
- Create: `backend/tests/Unit/ScheduledCommandModelTest.php`

**Steps:**
1. Write failing test `ScheduledCommandModelTest::test_casts_arguments_to_array`. Expected error: `Class "App\Models\ScheduledCommand" not found`.
2. Run test, confirm it fails for the expected reason.
3. Create migration with full column set (per Schema section above), include `$table->json('arguments')->nullable()` and `$table->index(['category', 'sort_order'])`.
4. Create `ScheduledCommand` model with `$fillable` (all editable cols), `$casts = ['enabled' => 'boolean', 'is_placeholder' => 'boolean', 'run_in_background' => 'boolean', 'arguments' => 'array', 'last_run_at' => 'datetime', 'last_finished_at' => 'datetime']`.
5. Add scopes `scopeEnabled($q)`, `scopeNotPlaceholder($q)`, `scopeForCategory($q, $cat)`.
6. Create factory with sane defaults (signature unique via `Str::random`, category `system`, cron `'0 0 * * *'`, enabled true).
7. Run `php artisan migrate` to apply.
8. Run test, confirm casts work (assert `$cmd->arguments` is array).
9. Add 2 more unit tests: `test_scope_enabled_filters_disabled_rows`, `test_scope_not_placeholder_filters_placeholders`.
10. Run all 3 tests, confirm pass.
11. Commit: `feat(scheduler): add scheduled_commands schema + model + factory`.

**Verification:**
- [ ] Migration applies cleanly via `php artisan migrate` (verify table exists in MySQL)
- [ ] All 3 unit tests pass
- [ ] Model casts behave as expected (arguments → array, enabled → bool, last_run_at → Carbon)
- [ ] No placeholder/TODO comments in new code
- [ ] `composer dump-autoload` not needed (Laravel autoload handles model)

### Phase B: Seeder for 15 real + 4 placeholder rows

**Estimated time:** 20 min

**Files:**
- Create: `backend/database/seeders/ScheduledCommandSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php` (add `$this->call(ScheduledCommandSeeder::class)`)
- Create: `backend/tests/Feature/ScheduledCommandSeederTest.php`

**Steps:**
1. Write failing test `ScheduledCommandSeederTest::test_seeds_15_real_and_4_placeholder_rows`. Expected error: `Class "Database\Seeders\ScheduledCommandSeeder" not found`.
2. Run test, confirm it fails.
3. Create seeder with `$rows` array — copy verbatim from "Final command inventory" table above. Use `firstOrCreate(['signature' => $row['signature']], $row)` for idempotency.
4. Wire into `DatabaseSeeder::run()` (after existing `LinkedInSettingsSeeder` etc).
5. Run `php artisan db:seed --class=ScheduledCommandSeeder` once to verify no errors.
6. Run test — assert `ScheduledCommand::count() === 19`, `ScheduledCommand::where('is_placeholder', true)->count() === 4`.
7. Add second test `test_seeder_is_idempotent` — call `seed()` twice, assert count unchanged.
8. Add third test `test_seeded_signatures_match_console_php` — hardcoded list of 14 known signatures, assert each exists.
9. Run all 3 tests.
10. Commit: `feat(scheduler): seed 15 real + 4 placeholder cron commands`.

**Verification:**
- [ ] Seeder produces exactly 19 rows on fresh DB
- [ ] Re-running seeder yields zero new rows (idempotent)
- [ ] All 14 real signatures from current console.php present
- [ ] 4 placeholder rows have `is_placeholder=true, enabled=false`
- [ ] All 3 feature tests pass
- [ ] No placeholder/TODO comments in new code

### Phase C: DynamicScheduleRegistrar service + console.php cutover

**Estimated time:** 30 min

**Files:**
- Create: `backend/app/Services/DynamicScheduleRegistrar.php`
- Modify: `backend/routes/console.php` (replace 14 Schedule::command entries with single registrar call)
- Create: `backend/tests/Feature/DynamicScheduleRegistrarTest.php`

**Steps:**
1. Write failing test `DynamicScheduleRegistrarTest::test_registers_only_enabled_non_placeholder_rows`. Use `app(Schedule::class)` and assert event count via reflection on `Schedule::events()`. Expected error: `Class "App\Services\DynamicScheduleRegistrar" not found`.
2. Run test, confirm it fails.
3. Create `DynamicScheduleRegistrar` with single public method `register(Schedule $schedule): void`. Logic:
   - `if (!Schema::hasTable('scheduled_commands')) return;` (boot safety)
   - `ScheduledCommand::enabled()->notPlaceholder()->each(function ($cmd) use ($schedule) { ... })`
   - Inside callback: build full signature `$cmd->signature . ' ' . implode(' ', $cmd->arguments ?? [])`, then `$schedule->command($fullSig)->cron($cmd->cron_expression)->timezone($cmd->timezone)`, conditionally `->withoutOverlapping($cmd->without_overlapping_minutes)` and `->runInBackground()`.
4. Run test 1, confirm pass.
5. Add test 2 `test_skips_placeholder_rows`, test 3 `test_applies_arguments_to_signature`, test 4 `test_safe_when_table_missing` (call against fresh DB without migration).
6. Modify [routes/console.php](backend/routes/console.php): keep `Artisan::command('inspire')` block (lines 8-10), DELETE all 14 `Schedule::command(...)` entries (lines 13-128), replace with single block:
   ```php
   use App\Services\DynamicScheduleRegistrar;
   app(DynamicScheduleRegistrar::class)->register(app(Schedule::class));
   ```
7. Run `php artisan schedule:list` — verify 14 entries appear (1 per enabled row), confirm cron expressions match seed values.
8. Run all 4 registrar tests, confirm pass.
9. Run full Laravel test suite (`php artisan test --filter=Schedule`) to catch regressions.
10. Commit: `feat(scheduler): replace hardcoded schedules with DynamicScheduleRegistrar`.

**Verification:**
- [ ] `php artisan schedule:list` shows 14 entries (matches seeded enabled count)
- [ ] Cron expressions, timezones, withoutOverlapping minutes match per-row seed values
- [ ] Placeholder rows do NOT appear in `schedule:list` output
- [ ] All 4 registrar feature tests pass
- [ ] No regressions in existing test suite
- [ ] [routes/console.php](backend/routes/console.php) reduced from 128 lines to ~15 lines (Artisan inspire + use + 1-line registrar call)
- [ ] No placeholder/TODO comments in new code

### Phase D: Audit hook (event listeners)

**Estimated time:** 25 min

**Files:**
- Modify: `backend/app/Providers/AppServiceProvider.php` (register 3 event listeners in `boot()`)
- Create: `backend/app/Support/ScheduledTaskSignatureExtractor.php` (helper class — pure, testable)
- Create: `backend/tests/Unit/ScheduledTaskSignatureExtractorTest.php`
- Create: `backend/tests/Feature/ScheduledCommandAuditHookTest.php`

**Steps:**
1. Write failing test `ScheduledTaskSignatureExtractorTest::test_extracts_signature_from_artisan_command_string`. Inputs: `'php artisan linkedin:scan-blog --hours=24'` → `'linkedin:scan-blog'`. Expected error: `Class "App\Support\ScheduledTaskSignatureExtractor" not found`.
2. Run test, confirm fails.
3. Create extractor: static method `extract(string $command): ?string`. Strip `'php '` + binary path prefix, split on whitespace, return token immediately after `'artisan'`.
4. Add 4 more unit tests: bare command (no `php artisan` prefix), command with no args, command with multiple args, malformed input returns null.
5. Run 5 unit tests, confirm pass.
6. Write failing test `ScheduledCommandAuditHookTest::test_starting_event_marks_running`. Synthesize a `ScheduledTaskStarting` event with task command `'php /path/to/artisan linkedin:scan-blog --hours=24'`, dispatch via `Event::dispatch($event)`, assert `ScheduledCommand::where('signature', 'linkedin:scan-blog')->first()->last_status === 'running'`. Expected fail: listener not registered yet.
7. Register listeners in `AppServiceProvider::boot()`:
   ```php
   Event::listen(ScheduledTaskStarting::class, function ($e) {
       $sig = ScheduledTaskSignatureExtractor::extract($e->task->command ?? '');
       if (!$sig) return;
       ScheduledCommand::where('signature', $sig)->update([
           'last_run_at' => now(),
           'last_status' => 'running',
           'last_error' => null,
       ]);
   });
   // ScheduledTaskFinished: set last_finished_at, last_status='success', last_duration_ms
   // ScheduledTaskFailed: set last_finished_at, last_status='failed', last_error (truncated to TEXT cap, first 2000 chars)
   ```
8. Run hook test 1, confirm pass.
9. Add 2 more feature tests: `test_finished_event_marks_success_with_duration`, `test_failed_event_marks_failed_with_error_message`.
10. Run all 8 audit tests (5 unit + 3 feature), confirm pass.
11. Commit: `feat(scheduler): wire ScheduledTask events to audit columns`.

**Verification:**
- [ ] Listener fires on real `php artisan schedule:run` invocation (manual smoke test on local: trigger `linkedin:scan-blog` via `Schedule::call(...)->everyMinute()` temporary, observe `last_run_at` updates)
- [ ] Failed command captures stack trace excerpt in `last_error` (truncated to 2000 chars)
- [ ] Duration_ms reflects actual runtime (verify >0 for any non-instant command)
- [ ] All 8 tests pass
- [ ] No placeholder/TODO comments

### Phase E: SchedulerController + API routes + Resource

**Estimated time:** 30 min

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/SchedulerController.php`
- Create: `backend/app/Http/Resources/ScheduledCommandResource.php`
- Modify: `backend/routes/api.php` (add 3 routes inside admin auth:sanctum group)
- Create: `backend/tests/Feature/SchedulerControllerTest.php`

**Steps:**
1. Write failing test `SchedulerControllerTest::test_index_returns_grouped_by_category_with_next_run`. Auth as admin, GET `/api/admin/scheduler`, assert response shape `{groups: {linkedin: [...], content_engine: [...], ...}}`. Expected error: route not defined → 404.
2. Run test, confirm fails.
3. Create `ScheduledCommandResource` — fields: `id, signature, display_name, description, category, cron_expression, cron_human_readable` (computed via `Cron\CronExpression::getNextRunDate()` + `cronExpressionToHumanString()` helper using `dragonmantank/cron-expression`'s describe), `timezone, enabled, is_placeholder, without_overlapping_minutes, arguments, last_run_at` (ISO + relative), `last_status, last_duration_ms, last_error, next_run_at` (computed: `Cron\CronExpression::factory($cron)->getNextRunDate(now()->setTimezone($tz))->toIso8601String()`).
4. Create `SchedulerController` with `index()` returning grouped + sorted (`sort_order ASC, display_name ASC`).
5. Add route `Route::get('admin/scheduler', [SchedulerController::class, 'index'])->middleware('auth:sanctum')`.
6. Run test 1, confirm pass.
7. Write failing test 2 `test_update_modifies_enabled_and_cron`. PUT `/api/admin/scheduler/{id}` with `{enabled: false, cron_expression: '0 6 * * *'}`. Expected: 200 + DB updated.
8. Add `update(Request $r, ScheduledCommand $cmd)` action with validation: `enabled` boolean, `cron_expression` string + custom rule via `Cron\CronExpression::isValidExpression()`, `description` nullable string max 1000, `without_overlapping_minutes` nullable int 1-1440, `timezone` valid timezone string. Return 422 with structured error on validation fail. Block edits to placeholder rows: `if ($cmd->is_placeholder) return response()->json([...], 403)`.
9. Add route `Route::put('admin/scheduler/{cmd}', ...)`.
10. Run test 2, confirm pass. Add test 3 `test_update_rejects_invalid_cron_expression`, test 4 `test_update_rejects_placeholder_edits`.
11. Write failing test 5 `test_run_now_dispatches_artisan_queue`. POST `/api/admin/scheduler/{id}/run`. Assert `Queue::assertPushed` for the artisan command via `Queue::fake()`.
12. Add `run(ScheduledCommand $cmd)` action: validate not placeholder + enabled, call `Artisan::queue($cmd->signature, $cmd->arguments ?? [])`, set `last_status='running' + last_run_at=now()` immediately so UI reflects pending state. Return 202 Accepted.
13. Add route `Route::post('admin/scheduler/{cmd}/run', ...)`.
14. Run test 5, confirm pass. Add test 6 `test_run_rejects_placeholder`, test 7 `test_run_requires_auth_sanctum`.
15. Run all 7 controller tests, confirm pass.
16. Commit: `feat(scheduler): add admin scheduler API (list/update/run-now)`.

**Verification:**
- [ ] All 7 controller feature tests pass
- [ ] Index returns valid `next_run_at` for every row (manual: `curl /api/admin/scheduler` and verify timestamps reasonable)
- [ ] Cron expression validator rejects `'invalid cron'`, accepts `'0 5,6,12 * * *'`
- [ ] Placeholder rows readable but not editable / runnable (403 on update + run)
- [ ] `Artisan::queue` honored by queue worker (manual smoke: click run-now equivalent via curl, observe job in `jobs` table, worker picks it up, last_status flips to success/failed)
- [ ] No placeholder/TODO comments

### Phase F: Frontend composable + base view + tab integration

**Estimated time:** 35 min

**Files:**
- Create: `frontend/src/composables/useScheduler.js`
- Create: `frontend/src/views/admin/SchedulerSettings.vue`
- Modify: `frontend/src/router/index.js` (add `/admin/settings/scheduler` lazy route OR sub-route within settings)
- Modify: `frontend/src/views/admin/SettingsForm.vue` (add "Scheduler" tab to tab strip)

**Steps:**
1. Create `useScheduler.js` composable mirroring `useLinkedInDrafts.js` patterns (TanStack Query, 30s staleTime, refetchOnMount:'always'). Exports: `useScheduledCommands()` (list query, returns `{groups, isLoading, error, refetch}`), `useUpdateScheduledCommand()` (mutation), `useRunScheduledCommand()` (mutation with optimistic last_status='running' update).
2. Create `SchedulerSettings.vue` with skeleton: title, refresh button, loop over `Object.entries(groups)` rendering one `<section>` per category with collapsible header. Each section contains `<table>` with 6 cols (Name, Schedule, Enabled, Last Run, Next Run, Actions). Empty placeholder rows shown with amber tint + "Coming soon" badge + lock icon.
3. Inline action: enabled toggle uses `useUpdateScheduledCommand` with optimistic update + rollback on error.
4. Inline action: cron edit — initial render shows compact preset dropdown (8 presets covering 95% of seeded values: `'* * * * *'`, `'*/5 * * * *'`, `'*/10 * * * *'`, `'0 * * * *'`, `'0 3 * * *'`, `'0 5 * * *'`, `'0 9 * * 5'`, `'0 3 1 */3 *'`, `'Custom...'`). Selecting Custom expands raw text input. Both modes show `cron_human_readable` from API as preview chip below.
5. Inline action: Run Now button — disabled for placeholders, fires `useRunScheduledCommand`, shows toast "Queued" + flips status pill to cyan-pulse "running" optimistically.
6. Add tab "Scheduler" to [SettingsForm.vue](frontend/src/views/admin/SettingsForm.vue) tab strip alongside existing tabs (Site, About, etc). Tab clicked → mount `<SchedulerSettings>` inline (NOT a separate route, keep it within `/admin/settings` per user's request).
7. Add status pill helper: emerald success, red failed, cyan-pulse running, grey never. Tooltip = absolute datetime + duration_ms.
8. Smoke test in browser:
   - Navigate to `/admin/settings`, click "Scheduler" tab
   - See 19 rows grouped by category (LinkedIn, Content Engine, Newsletter, System, Instagram, Facebook, TikTok)
   - Toggle one schedule off → verify `enabled=false` in DB via `php artisan tinker`
   - Edit cron preset → verify saved + next_run_at recomputed
   - Click Run Now on `linkedin:reap-stuck` → verify job appears in `jobs` table within 1s
   - Click on placeholder row Run Now → button disabled (should not fire)
9. Commit: `feat(scheduler): admin scheduler tab with toggle/edit/run-now`.

**Verification:**
- [ ] `npm run build` clean, no TS/lint errors
- [ ] Tab visible in /admin/settings, shows 19 rows total
- [ ] Toggle off / on persists across reload
- [ ] Cron preset change updates next_run_at chip immediately (optimistic) and confirms via refetch
- [ ] Run Now button enqueues job (visible in `jobs` table) for non-placeholder rows
- [ ] Placeholder rows: toggle locked, edit locked, Run Now disabled, "Coming soon" badge visible
- [ ] No placeholder/TODO comments in new code

### Phase G: CLAUDE.md update + manual end-to-end verification

**Estimated time:** 20 min

**Files:**
- Modify: `CLAUDE.md` (root) — add new "Scheduler" section near Content Engine + LinkedIn Admin UI sections
- Modify: `CLAUDE.md` "Last Updated" footer line

**Steps:**
1. Add new section "Admin Scheduler (May 9, 2026)" to root CLAUDE.md documenting: schema, endpoints, registrar pattern, audit hook, tab location, IG/FB/TikTok placeholder convention. Reference design doc path. ~150 words.
2. Update Page Sections Mapping table if relevant (probably not — this is an admin feature, not public-facing).
3. Update "Admin Routes" subsection in root CLAUDE.md with 3 new endpoints under `/api/admin/scheduler`.
4. Update "Last Updated" line at bottom of root CLAUDE.md.
5. Manual end-to-end smoke test on local:
   - Run `php artisan schedule:list` — confirm 14 enabled entries (matches seed minus 4 placeholders + 1 reserved=15-1=14) with correct cron expressions
   - Wait for next minute boundary, observe `linkedin:process-scheduled` (or any per-minute schedule) firing — confirm `last_run_at` updates in DB
   - Toggle one schedule off via UI, wait 60 seconds, verify it does NOT appear in `php artisan schedule:list` after `php artisan schedule:list` re-reads (or after `config:cache` if cached)
   - Click Run Now on `blog:process-images`, observe queue worker picks up job within 5s
6. Commit: `docs(claude-md): document admin scheduler tab + DB-driven schedules`.

**Verification:**
- [ ] CLAUDE.md updated with full Scheduler section
- [ ] `php artisan schedule:list` shows expected 14 entries with correct cron syntax + timezone
- [ ] Audit columns update on real schedule fire (not just simulated events)
- [ ] Toggle OFF stops next run (verified by waiting + re-running schedule:list)
- [ ] Run Now → queue worker → audit columns reflect outcome end-to-end
- [ ] Last Updated line at bottom of CLAUDE.md bumped to 2026-05-09

---

### Total estimated time

~3 hours across 7 phases. Phases A-E are backend-only, can run sequentially. Phase F is frontend-only, can run in parallel with Phase D+E if dispatched separately. Phase G is documentation + smoke, must run last.

### Parallelization candidate (optional)

Phase A → B → C → D → E sequential (each builds on the prior). Phase F can dispatch in parallel with Phase E (frontend only needs API contract documented, not implemented). Phase G last.

If using `/gaspol-parallel` plan-phases mode: split A-E as one stream, F as second stream after E's API contract committed.

### Risk register

| Risk | Mitigation |
|---|---|
| Migration applied but seeder forgotten → DB has table but no rows → `schedule:list` returns 0 entries → ALL crons silently stop | Wire `ScheduledCommandSeeder` into `DatabaseSeeder::run()` (Phase B step 4). Plus deploy.sh already runs idempotent seeders post-migrate (per CLAUDE.md). |
| Operator toggles linkedin:process-scheduled OFF accidentally → publish backlog grows silently | Add UI safeguard: toggle off triggers confirm modal "This will stop processing. Affected backlog: N drafts in `awaiting_publish`. Confirm?". OUT OF SCOPE for v1 — flag as Phase H follow-up. |
| Cron expression with invalid syntax saved to DB → `schedule:run` throws every minute | Phase E step 8 server-side validator + UI client-side validation. Defense-in-depth. |
| `Artisan::queue($signature, $args)` doesn't honor positional args correctly | Phase E step 12 unit test verifies `Queue::assertPushed` matches signature + args separately. |
| Audit listener miss-fires on commands NOT in scheduled_commands table (e.g., manual `php artisan migrate`) | Listener early-returns if `extract()` finds no matching row (`update()` returns 0 affected, no error). Verified Phase D step 7 logic. |
| Hot-reload delay — toggle OFF takes up to 60s to take effect (next `schedule:run` tick) | Documented expected behavior. Acceptable for v1 — operator typically toggles + walks away. |

### Out-of-scope follow-ups (potential Phase H)

- Confirm modal on disable when current schedule has live impact (per Risk #2)
- Slack / Telegram notify on `last_status='failed'` (extend existing Telegram path)
- Schedule history table (separate audit log beyond last_*)
- "Pause for N hours" temporary disable (auto-re-enable)

---

## Execution handoff

**Option 1: Execute in this session**
> Ready to start Phase A? I'll use `/gaspol-execute` to implement with per-phase checkpoints + TDD hard gate.

**Option 2: Parallel execution**
> Want to use `/gaspol-parallel` for independent streams? Phases A-E (backend) + Phase F (frontend, after E's API contract lands) can split.

**Option 3: Separate session**
> Save plan for a new session — file at `docs/plans/2026-05-09-admin-scheduler-tab.md` has everything needed for fresh context.
