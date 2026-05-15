# GeminiGen Circuit Breaker — Service-Level Outage Detection

**Date:** 2026-05-15
**Status:** Design approved, implementation pending
**Author:** Ali Sadikin (brainstormed via `/gaspol-brainstorm`)
**Touches:** Portfolio_v2 backend (`ImageGenerationService`, `LinkedInCarouselImageService`, `RetryImageSegmentJob`, scheduler, new probe command, Telegram notification dedup)

---

## Design

### 1. Problem

GeminiGen.ai is experiencing a server outage as of 2026-05-15 — every image generation dispatch returns 5xx / connection timeout. Existing per-segment retry infrastructure (CLAUDE.md April 21 entry: `RetryImageSegmentJob` with max 2 auto attempts + exponential backoff 60s/2m/5m) wastes API calls and floods Telegram during a multi-hour service outage:

| # segments | × 2 auto retries | × N concurrent ideas | = wasted API calls per outage |
|---|---|---|---|
| 20 | 2 | 3 | 120 |

Plus N × Telegram exhaustion alerts. Plus operator manual retry (existing endpoint `POST /admin/content-engine/ideas/{id}/retry-segment/{i}`) also fails immediately because there's no global "service down" check before dispatch.

### 2. Solution — service-level circuit breaker

Classic Hystrix-style 3-state breaker, GLOBAL across all GeminiGen callers (blog article images + LinkedIn carousel slides). When 5 consecutive failures hit within 10-min sliding window, circuit OPENS and rejects all new dispatches with a friendly 503 response. Background canary probe every 5 min self-heals when GeminiGen returns; existing per-segment retry resumes from where it stopped.

### 3. State machine

```
                ┌──────────┐
                │  CLOSED  │ ← normal operation
                │ (default)│
                └────┬─────┘
            5 consec │ failures
            in 10min │
                     ▼
                ┌──────────┐
                │   OPEN   │ ← reject all dispatches with 503
                │          │   suppress segment_failed alerts
                └────┬─────┘
        every 5min   │ scheduler fires canary probe
                     ▼
                ┌──────────┐
                │ HALF_OPEN│ ← canary succeeded, allow next dispatch
                │          │   if real dispatch fails → back to OPEN
                └────┬─────┘
        real dispatch│ succeeds
                     ▼
              [back to CLOSED]
```

### 4. State storage

**Laravel Cache facade** (file driver — project default, no Redis dependency per CLAUDE.md stack).

Cache keys (TTL = 1 hour rolling, refreshed on each transition):

| Key | Type | Purpose |
|---|---|---|
| `geminigen:circuit:state` | string | `closed` / `open` / `half_open` |
| `geminigen:circuit:failure_log` | array | Last 10 failure timestamps (sliding window) |
| `geminigen:circuit:opened_at` | timestamp | When OPEN entered |
| `geminigen:circuit:next_probe_at` | timestamp | When scheduler should next probe |
| `geminigen:circuit:last_probe_result` | array | `{at, status_code, error}` — for admin UI surface |

Default state when keys absent = `closed`. So a cache wipe doesn't accidentally trip the breaker.

### 5. Failure classification

Only these count toward the trip counter:

| Failure type | Source | Count? | Reason |
|---|---|---|---|
| HTTP 5xx from GeminiGen API | Synchronous dispatch | ✅ | Server-side outage signal |
| Connection timeout / DNS failure | Synchronous dispatch | ✅ | Network/service down |
| HTTP 429 (rate limit) | Synchronous dispatch | ✅ | Backing off helps; rate limit IS a "down" signal |
| Webhook `event=IMAGE_GENERATION_FAILED` with server error | Async webhook | ✅ | Mid-flight server failure |
| HTTP 4xx (other) — prompt errors | Synchronous dispatch | ❌ | Prompt-class, fix prompt not service |
| `PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD` etc | Async webhook | ❌ | Safety refusal — handled by existing April 28 auto-rewrite |
| App-side validation error | Before HTTP call | ❌ | Bug in our code, not GeminiGen |

### 6. Trip & recover rules

**Trip:** 5th failure (any of the ✅ types above) added to `failure_log` while existing entries are all within `now - 10min` window → state flips to `open` + `opened_at=now` + `next_probe_at=now+5min` + Telegram alert sent.

**Probe (every 5 min via scheduler):**
1. Skip if `state ≠ open` or `now < next_probe_at`
2. POST canary to GeminiGen: smallest possible request (`prompt="circuit probe", num_images=1, aspect_ratio="1:1"` — tiny but real, doesn't pollute callbacks because we set `webhook_url=null` or use a probe-specific webhook that no-ops)
3. **Success (2xx)** → state = `half_open`, log `last_probe_result`, **DO NOT close yet** — wait for real production dispatch to succeed before declaring fully closed
4. **Failure** → state stays `open`, `next_probe_at=now+5min`, log result

**Close:** When state = `half_open` AND a real production dispatch succeeds → state = `closed` + clear `failure_log` + Telegram recovery alert.

**Re-trip from half_open:** If state = `half_open` AND a real dispatch fails → state = `open`, `next_probe_at=now+5min` (don't extend cooldown — the probe will retry).

### 7. Integration points (3 places)

| Service / Job | Where the gate fires | Behavior when open |
|---|---|---|
| `ImageGenerationService::triggerForIdea()` | Before `Http::post(geminigen)` | Throw `GeminiGenCircuitOpenException` → caller catches → segment status stays `pending` (no retry_count increment) → log entry "deferred-outage" |
| `LinkedInCarouselImageService::dispatchAllSlides()` + `dispatchSingleSlide()` | Before `Http::post(geminigen)` | Same — slide stays `pending`, no status change |
| `RetryImageSegmentJob::handle()` | Before invoking dispatch | Re-queue self with longer delay (5 min) instead of dispatching. **Don't increment retry_count** — outage retries shouldn't burn the operator's budget |

### 8. Telegram dedup

| Event | When | Existing alert | New behavior |
|---|---|---|---|
| Circuit opens | 5th failure in 10min window | n/a | NEW alert: `🔴 GeminiGen outage detected at HH:MM (N segments paused). Probing every 5 min.` |
| Circuit closes | half_open → closed transition | n/a | NEW alert: `🟢 GeminiGen recovered at HH:MM (paused {duration}). Resuming dispatches.` |
| `segment_failed` (existing per-segment exhaustion alert) | Per-segment retry budget hit | Currently fires | SUPPRESSED while `state ≠ closed` — operator already knows there's an outage |

Two new settings keys in `telegram` group: `telegram_notify_geminigen_circuit_open` (default `true`), `telegram_notify_geminigen_circuit_close` (default `true`).

### 9. Admin UI surface

**Read-only**, no operator override (per locked design decision). New row in `/admin/automation/docs` GeminiGen Relay tab — wait, actually the relay endpoint is separate from this. The circuit applies to the ORIGINAL GeminiGen account (blog + linkedin carousel direct path), not the new relay account.

So surface as:
- New small status indicator in `/admin/content-engine` page header: `🟢 GeminiGen` / `🔴 GeminiGen — outage (opened HH:MM, probing)` / `🟡 GeminiGen — verifying recovery`
- Same indicator in `/admin/linkedin-queue` page header
- Click → tooltip with `last_probe_result` + `opened_at` + `failure_log` summary

No interactive controls — state is fully self-managed.

### 10. Out of scope

- **Per-account breaker** (separating original key from the May 14 relay plugin key): plugin uses different code path (stateless relay), not affected. If plugin's relay starts seeing the SAME GeminiGen outage, that's a separate problem to solve in plugin caller-side.
- **Per-operation breaker** (separate `/generate_image` vs `/generate_video`): YAGNI — current usage is image-only. Add only if video pipeline ships and shows different failure pattern.
- **Operator-facing force-open / force-close**: user explicitly rejected this in brainstorm Phase 1 — self-healing only.
- **Persistent state via DB row instead of cache**: cache file driver survives PHP-FPM restarts (file on disk). Only loss case is `php artisan cache:clear` mid-outage, which is acceptable — circuit will re-detect the outage within 5 failures.
- **Circuit state shared between blog + linkedin paths**: single global circuit — both paths share the same backend service (GeminiGen.ai), so a unified breaker is correct.

### 11. Risks accepted

| Risk | Mitigation | Severity |
|---|---|---|
| Cache wipe during outage resets circuit, allows N more wasted dispatches | Re-trips within 5 failures = same 30-60 sec wasted, not catastrophic | Low |
| Canary probe costs GeminiGen quota during outage (1 req / 5 min ≈ 12/hr) | Negligible — tiny request, only fires when circuit open | Low |
| Half-open state allows ONE real dispatch through that might fail noisily | By design — that's how Hystrix half-open works. Failure recycles circuit to open. | Low |
| New failure types we forget to classify | Default behavior: count toward trip (fail-safe trips early rather than miss). 4xx prompt errors explicitly ignored. | Low |
| 5-min probe interval too aggressive during prolonged outages | Configurable via env `GEMINIGEN_CIRCUIT_PROBE_INTERVAL_SECONDS=300` (default 300) | Low |

### 12. Acceptance criteria

1. ✅ Simulated 5 consecutive 5xx within 10 min trips circuit to `open` state visible in cache
2. ✅ While open, new `ImageGenerationService::triggerForIdea()` calls throw `GeminiGenCircuitOpenException` without firing HTTP
3. ✅ Scheduler probe every 5 min, single canary request, success → half_open
4. ✅ Real dispatch after half_open succeeds → state = closed + Telegram recovery alert sent + ONE alert total per outage (not N per-segment)
5. ✅ Per-segment retry_count does NOT increment during open state (operator's manual-retry budget preserved)
6. ✅ Existing safety auto-rewrite (PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD path) bypasses circuit entirely — that's prompt-class, not outage
7. ✅ Cache wipe during outage doesn't permanently break the system — circuit re-trips on next 5 failures

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship a service-level circuit breaker for GeminiGen.ai that detects multi-failure outages (5 consec failures in 10-min window), pauses all new dispatches across both article and LinkedIn carousel paths, self-heals via 5-min canary probing, dedups Telegram alerts to one open + one close, and surfaces status as a read-only indicator in 2 admin pages — all without DB migrations or new models.

### Architecture Context (from CLAUDE.md + Design Section)

- **Existing retry infra (April 21 entry):** `RetryImageSegmentJob` does per-segment auto-retry (2 attempts, exp backoff). Stays. Circuit breaker is the LAYER ABOVE — prevents retry from firing during outage.
- **Two GeminiGen call sites:**
  1. [`ImageGenerationService::triggerForIdea()`](backend/app/Services/ImageGenerationService.php) (blog/article path) — `Http::asMultipart()->post()` to GeminiGen
  2. [`LinkedInCarouselImageService::dispatchAllSlides()`](backend/app/Services/LinkedInCarouselImageService.php) + `dispatchSingleSlide()` (carousel path) — same HTTP shape
- **Two webhook receivers** (already exist, both swallow `event=IMAGE_GENERATION_FAILED`):
  1. [`BlogPipelineController::imageWebhook`](backend/app/Http/Controllers/Api/BlogPipelineController.php)
  2. [`LinkedInCarouselImageService::handleWebhook`](backend/app/Services/LinkedInCarouselImageService.php)
- **Telegram infra (April 20 entry):** `App\Services\TelegramNotificationService` sender + `App\Jobs\DispatchTelegramNotification` queued job. Settings group `telegram` already has per-event opt-in toggles. Add 2 new keys.
- **Settings seeder pattern:** `TelegramSettingsSeeder` + `firstOrCreate` per row. Idempotent re-runs.
- **Scheduler pattern (May 9 entry):** `ScheduledCommandSeeder` + `DynamicScheduleRegistrar` reads from DB. Admin UI at `/admin/settings?tab=scheduler` for visibility.
- **Cache facade:** `Illuminate\Support\Facades\Cache` — file driver per default Laravel config, no Redis required. `Cache::get/put/forget/increment` works for state + counters.
- **Admin status indicator pattern:** Existing TanStack Query composable convention (see `useLinkedInDrafts.js`, `useNewsletterAdmin.js`) — 30s staleTime + refetchOnMount:'always'.

### Tech Stack

- PHP 8.2 + Laravel 12
- Laravel Cache facade (file driver) — circuit state storage
- PHPUnit + Mockery — tests
- Vue 3 + TanStack Query + Tailwind 4 — admin indicator UI
- No new dependencies (no Redis, no migration, no model)

### Data Integration Map

| Feature | Data Source | Hook/API/Class | Exists? | Action |
|---|---|---|---|---|
| Circuit state machine | Cache facade keys `geminigen:circuit:*` | new `App\Services\GeminiGenCircuitBreaker` | No | Create new service |
| Failure classifier | HTTP status code + body | new `GeminiGenCircuitBreaker::classifyFailure()` static | No | Inline static method |
| Gate exception | Thrown when circuit open | new `App\Exceptions\GeminiGenCircuitOpenException` | No | Create new exception |
| Dispatch gates (3 sites) | `ImageGenerationService::triggerForIdea`, `LinkedInCarouselImageService::dispatch*()`, `RetryImageSegmentJob::handle` | Inject `GeminiGenCircuitBreaker` via constructor | Yes (services exist) | Edit existing services |
| Failure recording at HTTP call sites | After `Http::post()` returns 5xx OR throws ConnectionException | Same 3 services | Yes | Edit existing services |
| Failure recording from webhook | `BlogPipelineController::imageWebhook`, `LinkedInCarouselImageService::handleWebhook` | Inject breaker | Yes | Edit existing |
| Canary probe command | New `geminigen:circuit-probe` artisan command | new `App\Console\Commands\GeminigenCircuitProbe` | No | Create new command |
| Scheduler entry | `routes/console.php` OR `scheduled_commands` table | DynamicScheduleRegistrar (May 9 ship) | Yes | Add 1 row to `ScheduledCommandSeeder` |
| Telegram alerts (open + close) | New event types | `TelegramNotificationService` | Yes — extend | Add 2 new dispatch methods |
| Telegram settings keys | `settings` table, group `telegram` | `TelegramSettingsSeeder` | Yes — extend | Add 2 rows via seeder |
| `segment_failed` suppression during outage | Existing notification dispatch path | Check breaker state before dispatching | Yes — guard | Edit existing dispatch path |
| Admin status endpoint | `GET /api/admin/geminigen/circuit-status` | new controller method | No | Create new public method in existing AdminController or new one |
| Admin status composable | TanStack Query 30s staleTime | new `useGeminigenCircuit.js` | No | Create new |
| Status badge component | `BaseBadge` or inline | mounted in `ContentEngine.vue` + `LinkedInQueueList.vue` headers | Yes (Base components) | Inline badge with computed status class |
| Config keys | `config/geminigen-circuit.php` (new) | env: thresholds + probe interval | No | Create new config file |

### Phases

#### Phase A — Core CircuitBreaker service + Exception

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/app/Services/GeminiGenCircuitBreaker.php`
- Create: `backend/app/Exceptions/GeminiGenCircuitOpenException.php`
- Test: `backend/tests/Unit/GeminiGenCircuitBreakerTest.php`

**Steps:**
1. Write failing test for `GeminiGenCircuitBreaker::state()` returning `'closed'` when no cache keys set. Expected error: `Class "App\Services\GeminiGenCircuitBreaker" not found`.
2. Run test, confirm fail.
3. Implement service shell with 5 public methods: `state()`, `recordFailure(int $httpStatus, ?string $errorCode = null)`, `recordSuccess()`, `transitionToHalfOpen()`, `forceClose()` (test-only). Use `Cache::get/put/forget` with keys per design §4.
4. Add test cases (use `Carbon::setTestNow` for sliding-window control):
   - `test_records_failure_with_5xx`: 1 failure recorded, state stays `closed`
   - `test_trips_to_open_after_5_consecutive_failures_in_window`: 5 failures within 10 min → `state() === 'open'`
   - `test_failures_outside_window_dont_count`: 3 failures 11 min ago + 4 failures now → state stays `closed` (only 4 in window)
   - `test_4xx_prompt_errors_dont_count`: `recordFailure(400, 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD')` → counter not incremented
   - `test_4xx_other_dont_count`: `recordFailure(422, null)` → not counted
   - `test_429_does_count`: rate limit = outage signal
   - `test_record_success_during_closed_clears_failure_log`: passes
   - `test_record_success_during_half_open_transitions_to_closed`: state → `closed`
   - `test_record_failure_during_half_open_transitions_back_to_open`: state → `open`
   - `test_record_failure_during_open_resets_next_probe_at`: probe slot extended
5. Create exception class:
   ```php
   namespace App\Exceptions;
   class GeminiGenCircuitOpenException extends \RuntimeException {
       public function __construct(public ?\Carbon\Carbon $openedAt = null, public ?\Carbon\Carbon $nextProbeAt = null) {
           parent::__construct('GeminiGen circuit breaker is OPEN — service paused.');
       }
   }
   ```
6. Implement remaining service logic to pass all 10 tests.
7. Run: `D:/xampp/php/php.exe artisan test --filter=GeminiGenCircuitBreakerTest` — all pass.
8. `php -l` clean on both files.
9. Commit: `feat(geminigen-circuit): core breaker service + open exception`

**Verification:**
- [ ] All 10 unit test cases pass
- [ ] `php -l` clean on both files
- [ ] `Cache::has('geminigen:circuit:state')` returns false in fresh state (default `closed` is implicit, not cached)
- [ ] No placeholder/TODO comments

---

#### Phase B — Failure classifier helper

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Services/GeminiGenCircuitBreaker.php` (add static `classifyFailure()`)
- Modify: `backend/tests/Unit/GeminiGenCircuitBreakerTest.php` (add classifier tests)

**Steps:**
1. Write failing test for `GeminiGenCircuitBreaker::classifyFailure(503, null)` returning `'count'`. Expected error: `BadMethodCallException` or undefined method.
2. Run test, confirm fail.
3. Implement static method:
   ```php
   public static function classifyFailure(?int $httpStatus, ?string $errorCode, ?\Throwable $exception = null): string {
       // 'count' → adds to failure_log
       // 'prompt_class' → don't count, safety rewriter handles
       // 'ignore' → don't count, not an outage signal
       if ($exception instanceof \Illuminate\Http\Client\ConnectionException) return 'count';
       if ($httpStatus === null) return 'ignore';
       if ($httpStatus >= 500) return 'count';
       if ($httpStatus === 429) return 'count';
       if ($httpStatus >= 400 && $httpStatus < 500) {
           $promptCodes = ['PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD', 'PUBLIC_ERROR_MINORS', 'PUBLIC_ERROR_UNSAFE_CONTENT', 'PUBLIC_ERROR_SEXUAL_CONTENT'];
           if (in_array($errorCode, $promptCodes, true)) return 'prompt_class';
           return 'ignore';
       }
       return 'ignore';
   }
   ```
4. Add test cases: 5xx → count, 429 → count, ConnectionException → count, 400 + PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD → prompt_class, 422 → ignore, 200 → ignore.
5. Run tests, all pass.
6. Commit: `feat(geminigen-circuit): failure classifier (prompt-class vs outage)`

**Verification:**
- [ ] 6 new classifier tests pass
- [ ] `recordFailure()` internally calls `classifyFailure()` and only increments counter when result is `'count'`
- [ ] No placeholder/TODO comments

---

#### Phase C — Config + env scaffolding

**Estimated time:** 5 minutes

**Files:**
- Create: `backend/config/geminigen-circuit.php`
- Modify: `backend/.env.example`
- Modify: `backend/app/Services/GeminiGenCircuitBreaker.php` (read thresholds from config)

**Steps:**
1. Write failing test asserting `config('geminigen-circuit.failure_threshold')` returns 5. Expected error: `Failed asserting that null equals 5`.
2. Run test, confirm fail.
3. Create `config/geminigen-circuit.php`:
   ```php
   return [
       'failure_threshold' => (int) env('GEMINIGEN_CIRCUIT_FAILURE_THRESHOLD', 5),
       'window_seconds' => (int) env('GEMINIGEN_CIRCUIT_WINDOW_SECONDS', 600),
       'probe_interval_seconds' => (int) env('GEMINIGEN_CIRCUIT_PROBE_INTERVAL_SECONDS', 300),
       'state_ttl_seconds' => (int) env('GEMINIGEN_CIRCUIT_STATE_TTL_SECONDS', 3600),
       'canary_prompt' => env('GEMINIGEN_CIRCUIT_CANARY_PROMPT', 'circuit probe — solid color square'),
   ];
   ```
4. Edit service to read all magic numbers from config (replace hardcoded 5, 600, 300, 3600 from Phase A).
5. Append to `.env.example`:
   ```env
   # GeminiGen circuit breaker (outage detection for original GeminiGen account — blog + carousel paths)
   GEMINIGEN_CIRCUIT_FAILURE_THRESHOLD=5
   GEMINIGEN_CIRCUIT_WINDOW_SECONDS=600
   GEMINIGEN_CIRCUIT_PROBE_INTERVAL_SECONDS=300
   GEMINIGEN_CIRCUIT_STATE_TTL_SECONDS=3600
   ```
6. Run all Phase A+B tests again — still green (proves config drive doesn't break behavior).
7. Commit: `feat(geminigen-circuit): config + env keys`

**Verification:**
- [ ] `php artisan tinker --execute='dump(config("geminigen-circuit"));'` shows all 5 keys
- [ ] All Phase A+B tests still pass with config-driven values
- [ ] `.env.example` has the 4 env keys
- [ ] No hardcoded thresholds in service code

---

#### Phase D — Gate `ImageGenerationService::triggerForIdea()`

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Services/ImageGenerationService.php` (constructor inject breaker, gate before dispatch, record after)
- Test: `backend/tests/Feature/ImageGenerationServiceCircuitBreakerTest.php`

**Steps:**
1. Write failing test: when circuit `state='open'`, calling `triggerForIdea($idea)` throws `GeminiGenCircuitOpenException` without firing any HTTP. Expected error: `Http::assertNothingSent` fails OR the exception isn't thrown (depending on which gap is closed first).
2. Run test, confirm fail.
3. Inject `GeminiGenCircuitBreaker` via constructor. Before `Http::post(...)` call, check `if ($this->breaker->state() === 'open') throw new GeminiGenCircuitOpenException(...)`.
4. After HTTP call, on 5xx / 429 / ConnectionException, call `$this->breaker->recordFailure($status, $errorCode, $exception)`. On 2xx, call `$this->breaker->recordSuccess()`.
5. Add 3 more test cases:
   - `test_5xx_response_records_failure`: mock GeminiGen returns 503 → breaker increments counter
   - `test_2xx_response_records_success`: mock returns 200 → if state was half_open, transitions to closed
   - `test_prompt_class_400_does_not_record`: mock returns 400 with PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD → counter NOT incremented (safety rewrite handles separately)
6. Run tests, all 4 pass.
7. Commit: `feat(geminigen-circuit): gate ImageGenerationService dispatch + record outcomes`

**Verification:**
- [ ] 4 feature test cases pass
- [ ] `php -l` clean
- [ ] Calling `triggerForIdea` during open state throws exception, no HTTP fires (verified via `Http::fake()` + `assertNothingSent`)
- [ ] Existing `ImageGenerationServiceTest` (if any) still pass — no regression
- [ ] No placeholder/TODO comments

---

#### Phase E — Gate `LinkedInCarouselImageService::dispatch*()`

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Services/LinkedInCarouselImageService.php` (inject breaker, gate + record)
- Test: `backend/tests/Feature/LinkedInCarouselImageServiceCircuitBreakerTest.php`

**Steps:**
1. Write failing test: when circuit open, `dispatchAllSlides($draft)` skips all slides without HTTP. Expected error: `Http::assertNothingSent` fails OR slides not marked appropriately.
2. Run test, confirm fail.
3. Inject `GeminiGenCircuitBreaker`. Both `dispatchAllSlides()` and `dispatchSingleSlide()` check `$this->breaker->state()` before HTTP::post. When open: log + return without changing slide status (slide stays `pending`).
4. After HTTP call, classify response → recordFailure or recordSuccess (same pattern as Phase D).
5. Webhook handler `handleWebhook()`: when event = `IMAGE_GENERATION_FAILED` with a server error code (not safety code), call `recordFailure(500, $errorCode)`.
6. Add test cases:
   - `test_dispatch_all_slides_skips_when_circuit_open`: slide statuses unchanged, no HTTP
   - `test_dispatch_single_slide_skips_when_circuit_open`
   - `test_webhook_failure_with_server_error_records_failure`
   - `test_webhook_failure_with_safety_code_does_not_record`: prompt-class
7. Run tests, all 4 pass.
8. Commit: `feat(geminigen-circuit): gate LinkedInCarouselImageService + webhook failure recording`

**Verification:**
- [ ] 4 feature tests pass
- [ ] Slide status stays `pending` (not `failed`) when circuit open — operator sees "pending forever" not "failed 0 of 9"
- [ ] Webhook recording differentiates safety vs server failures
- [ ] No regression in existing LinkedInCarouselImageService tests

---

#### Phase F — Gate `RetryImageSegmentJob`

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Jobs/RetryImageSegmentJob.php` (check breaker before retry; re-queue if open)
- Test: `backend/tests/Feature/RetryImageSegmentJobCircuitBreakerTest.php`

**Steps:**
1. Write failing test: when circuit open, `RetryImageSegmentJob::handle()` re-queues itself with 5-min delay AND does NOT increment `retry_count`. Expected error: `Queue::assertPushed` fails OR retry_count is incremented when it shouldn't be.
2. Run test, confirm fail.
3. In `handle()`, before invoking `ImageGenerationService::dispatchSegment(...)`: check `if ($breaker->state() === 'open') { static::dispatch(...)->delay(now()->addMinutes(5)); return; }`. The `retry_count` increment stays inside the dispatch path (only fires after a real HTTP attempt).
4. Add test cases:
   - `test_retry_re_queues_with_delay_when_circuit_open`: assertion on `Queue::pushedWithDelay` + segment retry_count unchanged
   - `test_retry_dispatches_normally_when_circuit_closed`
5. Run tests, both pass.
6. Commit: `feat(geminigen-circuit): RetryImageSegmentJob respects breaker state`

**Verification:**
- [ ] 2 feature tests pass
- [ ] Operator's manual-retry budget (per CLAUDE.md: max 2 auto attempts) preserved during outage
- [ ] Job idempotent — multiple re-queues don't pile up beyond config-driven delay

---

#### Phase G — Webhook failure recording for blog path

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/BlogPipelineController.php` (imageWebhook — record failure on IMAGE_GENERATION_FAILED with server error)
- Test: `backend/tests/Feature/BlogPipelineImageWebhookCircuitTest.php`

**Steps:**
1. Write failing test: POST a fake webhook with `event=IMAGE_GENERATION_FAILED` and server error code → breaker records 1 failure. Expected error: `assertEquals(1, $breaker->failureCount())` fails because controller doesn't call breaker yet.
2. Run test, confirm fail.
3. In `imageWebhook` action, when event indicates server failure (not prompt class), call `$breaker->recordFailure(500, $errorCode)`. Inject via method-level resolution: `app(GeminiGenCircuitBreaker::class)`.
4. Add test case for the negative path: `event=IMAGE_GENERATION_FAILED` + safety code → counter NOT incremented.
5. Run tests, both pass.
6. Commit: `feat(geminigen-circuit): blog webhook records server failures only`

**Verification:**
- [ ] 2 feature tests pass
- [ ] Webhook still does its existing job (storing image, updating segment status) — no regression
- [ ] Safety-class webhooks bypass breaker (handled by April 28 auto-rewrite)

---

#### Phase H — Canary probe artisan command + scheduler

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/app/Console/Commands/GeminigenCircuitProbe.php`
- Modify: `backend/database/seeders/ScheduledCommandSeeder.php` (add row for `geminigen:circuit-probe`, every 5 min)
- Test: `backend/tests/Feature/GeminigenCircuitProbeCommandTest.php`

**Steps:**
1. Write failing test: when `state=closed`, command exits 0 without HTTP. Expected error: `Command "geminigen:circuit-probe" is not defined`.
2. Run test, confirm fail.
3. Create command with signature `geminigen:circuit-probe {--dry-run}`. Logic:
   - Read state. If `closed` → log "no-op, circuit closed" → exit 0.
   - If `open` AND `now < next_probe_at` → exit 0.
   - Else → fire canary `Http::timeout(20)->post(geminigen.ai/...)` with tiny prompt from config, `webhook_url=null` so no callback noise.
   - On 2xx → `$breaker->transitionToHalfOpen()` + log + exit 0.
   - On failure → `$breaker->recordFailure(...)` (which bumps next_probe_at) + exit 0.
4. Add test cases:
   - `test_no_op_when_closed`
   - `test_no_op_when_open_but_probe_not_due`
   - `test_canary_success_transitions_to_half_open`: Http::fake 200 → state changes
   - `test_canary_failure_stays_open_bumps_next_probe`
5. Add 1 row to `ScheduledCommandSeeder` array:
   ```php
   ['signature' => 'geminigen:circuit-probe', 'category' => 'content_engine', 'cron_expression' => '*/5 * * * *', 'description' => 'Probe GeminiGen API every 5 min when circuit breaker is open; transitions to half_open on success.', 'enabled' => true, 'sort_order' => 60, ...]
   ```
6. Run all tests + seeder dry-run, all pass.
7. Commit: `feat(geminigen-circuit): canary probe command + 5min schedule entry`

**Verification:**
- [ ] 4 feature test cases pass
- [ ] `php artisan schedule:list | grep geminigen:circuit-probe` shows the cron entry (after re-running seeder)
- [ ] Command is idempotent — running 100 times in succession with `state=closed` does nothing
- [ ] Canary `webhook_url=null` (don't pollute production webhook receivers)

---

#### Phase I — Telegram alerts + segment_failed suppression

**Estimated time:** 12 minutes

**Files:**
- Modify: `backend/app/Services/TelegramNotificationService.php` (add `sendGeminigenCircuitOpen` + `sendGeminigenCircuitClose` methods)
- Modify: `backend/app/Services/GeminiGenCircuitBreaker.php` (dispatch Telegram on state transitions)
- Modify: `backend/database/seeders/TelegramSettingsSeeder.php` (add 2 new opt-in keys)
- Modify: `backend/app/Services/ImageGenerationService.php` AND/OR existing `segment_failed` dispatch path (suppress alert when breaker state != closed)
- Test: `backend/tests/Feature/GeminigenCircuitTelegramTest.php`

**Steps:**
1. Write failing test: when circuit transitions `closed → open`, `TelegramNotificationService::sendGeminigenCircuitOpen` is called exactly once. Expected error: method doesn't exist OR not called.
2. Run test, confirm fail.
3. Extend `TelegramNotificationService` with 2 methods. Each: read corresponding settings key (`telegram_notify_geminigen_circuit_open` / `_close`); when `'true'`, dispatch synchronous `sendMessage` with formatted text (open: `🔴 GeminiGen outage detected at HH:MM ({duration probe interval}). Pausing dispatches.`; close: `🟢 GeminiGen recovered at HH:MM (was paused {Xm}). Resuming dispatches.`).
4. In `GeminiGenCircuitBreaker`: hook the 2 transitions (`closed → open` in `recordFailure`, `half_open → closed` in `recordSuccess`). Dispatch via `app(TelegramNotificationService::class)`.
5. Add `TelegramSettingsSeeder` rows:
   ```php
   ['key' => 'telegram_notify_geminigen_circuit_open', 'value' => 'true'],
   ['key' => 'telegram_notify_geminigen_circuit_close', 'value' => 'true'],
   ```
6. Existing `segment_failed` dispatch path: find call site (likely in `ImageGenerationService::handleSegmentFailure` per CLAUDE.md April 21 entry). Wrap dispatch with `if ($this->breaker->state() === 'closed') { ... }`. When breaker is open or half_open, segment_failed alert suppressed.
7. Add test cases:
   - `test_open_transition_dispatches_telegram_open_alert`
   - `test_close_transition_dispatches_telegram_close_alert`
   - `test_alert_suppressed_when_telegram_notify_setting_false`
   - `test_segment_failed_alert_suppressed_when_circuit_not_closed`
   - `test_segment_failed_alert_fires_when_circuit_closed`
8. Run all tests, all pass.
9. Run seeder `php artisan db:seed --class=TelegramSettingsSeeder` (idempotent — only adds 2 new rows).
10. Commit: `feat(geminigen-circuit): Telegram alerts on open/close + suppress segment_failed during outage`

**Verification:**
- [ ] 5 feature tests pass
- [ ] Seeder runs idempotently (re-runs add zero rows)
- [ ] `telegram_notify_geminigen_circuit_open/close` rows visible in DB
- [ ] segment_failed alert path verified via test, NOT just `grep` — production-critical regression risk

---

#### Phase J — Admin status endpoint

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/DashboardController.php` (add `geminigenCircuitStatus()` method) OR create new minimal controller
- Modify: `backend/routes/api.php` (add `GET /api/admin/geminigen/circuit-status` under `auth:sanctum`)
- Test: `backend/tests/Feature/GeminigenCircuitStatusEndpointTest.php`

**Steps:**
1. Write failing test: `GET /api/admin/geminigen/circuit-status` returns 200 JSON with `state, opened_at, next_probe_at, last_probe_result, failure_count_in_window`. Expected error: `Route not defined`.
2. Run test, confirm fail.
3. Add admin route under existing `auth:sanctum` admin group. Controller method reads breaker state via `app(GeminiGenCircuitBreaker::class)`, returns JSON with shape `{success: true, data: {state, opened_at, next_probe_at, last_probe_result, failure_count_in_window}}`.
4. Add test cases:
   - `test_closed_state_returns_closed_payload`: `data.state === 'closed'`, other fields null
   - `test_open_state_returns_full_payload`: state=open, opened_at + next_probe_at present
   - `test_unauthenticated_returns_401`
5. Run tests, all pass.
6. Commit: `feat(geminigen-circuit): admin status endpoint for UI indicator`

**Verification:**
- [ ] 3 feature tests pass
- [ ] Endpoint returns `text/json` with project's standard `{success, data}` shape
- [ ] `auth:sanctum` middleware enforced — anonymous → 401

---

#### Phase K — Admin status indicator (Vue UI)

**Estimated time:** 12 minutes

**Files:**
- Create: `frontend/src/composables/useGeminigenCircuit.js`
- Modify: `frontend/src/views/admin/ContentEngine.vue` (mount badge in page header)
- Modify: `frontend/src/views/admin/LinkedInQueueList.vue` (mount badge in page header)

**Steps:**
1. Write failing test (manual smoke acceptable for v1): Vue file imports `useGeminigenCircuit` from composable that doesn't exist. Expected error at build: import not found. Run `npm run build` to confirm fail (skip if build is too slow — instead just create empty test or proceed straight to impl since Vue tests are out of scope for this project's TDD).
2. Create `useGeminigenCircuit.js` mirroring `useLinkedInDrafts.js` pattern: TanStack Query `useQuery` hitting `/api/admin/geminigen/circuit-status`, 30s staleTime, `refetchOnMount: 'always'`, refetch interval 30s (active polling so operator sees real-time state).
3. Create badge UI inline in both Vue files (no new component — too small to justify):
   ```vue
   <span :class="circuitBadgeClass" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium">
     <span class="h-1.5 w-1.5 rounded-full" :class="circuitDotClass"></span>
     {{ circuitLabel }}
   </span>
   ```
   With computeds:
   - `circuitBadgeClass`: emerald (closed) / amber (half_open) / red (open)
   - `circuitDotClass`: same palette + `animate-pulse` when not closed
   - `circuitLabel`: `'GeminiGen OK'` / `'GeminiGen probing…'` / `'GeminiGen down — paused HH:MM'`
4. Mount in both view headers, near the existing page title.
5. Tooltip on hover: shows `opened_at`, `next_probe_at`, `last_probe_result.error` (when present) — use `title` attribute (no popover lib needed v1).
6. Run `npm run build` from `frontend/`. Confirm clean build (no template errors, no missing imports).
7. Commit: `feat(geminigen-circuit): admin status indicator on Content Engine + LinkedIn Queue`

**Verification:**
- [ ] `npm run build` clean
- [ ] Composable polls every 30s (verified via DevTools Network tab in browser)
- [ ] 3 visual states render correctly (manual smoke via deploy + observe during simulated outage)
- [ ] Tooltip surfaces `last_probe_result.error` so operator can see "DNS error" / "503 from GeminiGen" without leaving the page
- [ ] No new component file — inline badge matches project's "small UI, inline" convention (see LinkedInQueueList status pills)

---

#### Phase M — Firecrawl status-page accelerator (early trip)

**Estimated time:** 15 minutes

**Background:** After Wave 1 (A+B+C), the breaker trips at 5 failures within 10-min window. But GeminiGen publishes a real-time status page at `https://geminigen.ai/status` that surfaces per-model status (e.g. `Nano Banana Pro: partial outage`). When 3 consecutive failures hit, we should query that status page ONCE — if it confirms our specific model is in outage, trip the breaker IMMEDIATELY at failure #3 instead of waiting for failure #5. Saves 2 wasted API calls per outage.

**Cost control:** Firecrawl scrape ≈ 5 credits per call. Worst-case 1 outage/day = ~150 credits/month — within free tier. Anti-thrash via 5-min cache lock so concurrent failures only trigger 1 scrape.

**Files:**
- Create: `backend/app/Services/GeminigenStatusPoller.php`
- Create: `backend/app/Jobs/CheckGeminiGenStatusJob.php`
- Modify: `backend/app/Services/GeminiGenCircuitBreaker.php` (add public `forceOpen($reason)` method + dispatch job from `recordFailure` when count crosses 3)
- Modify: `backend/config/geminigen-circuit.php` (add 4 new keys: firecrawl_api_key, status_page_url, status_model_name, status_cache_seconds)
- Modify: `backend/.env.example` (4 env keys)
- Test: `backend/tests/Unit/GeminigenStatusPollerTest.php`
- Test: `backend/tests/Feature/CheckGeminiGenStatusJobTest.php`
- Modify: `backend/tests/Unit/GeminiGenCircuitBreakerTest.php` (add early-trip test)

**Steps:**
1. Write failing test for `GeminigenStatusPoller::isOurModelInOutage()` returning `true` when Firecrawl response contains our model with status containing 'outage'. Expected error: `Class "App\Services\GeminigenStatusPoller" not found`.
2. Run test, confirm fail.
3. Implement `GeminigenStatusPoller`:
   - Constructor injects nothing (uses `Http` facade + config)
   - Public `isOurModelInOutage(): ?bool` — returns `true` (confirmed outage), `false` (operational), or `null` (couldn't determine — Firecrawl down, parse error, etc.)
   - Check `Cache::has('geminigen:status:last_result')` first (TTL 5 min) — if hit, return cached
   - Else: POST to Firecrawl API (`https://api.firecrawl.dev/v2/scrape`) with URL + JSON extraction schema (identical to the working shape we tested: `{components: [{name, status}]}`)
   - Match by `config('geminigen-circuit.status_model_name')` (default `'Nano Banana Pro'`)
   - Return true if matched component's status contains 'outage' (case-insensitive), false if 'operational', null if model not found OR scrape failed
   - Cache result for 300s with `Cache::put('geminigen:status:last_result', $result, 300)`
4. Implement `CheckGeminiGenStatusJob` (queueable):
   - Constructor takes nothing
   - `handle(GeminigenStatusPoller $poller, GeminiGenCircuitBreaker $breaker)` — calls `$poller->isOurModelInOutage()`. If `true` → `$breaker->forceOpen('status_page_confirmed_outage')` + log warning. Else → log info "status check ran, no early trip".
   - Idempotent: if breaker already open before this fires, no-op.
5. Modify `GeminiGenCircuitBreaker`:
   - Add public method `forceOpen(string $reason): void` → wraps existing private `setOpen($reason)` for external callers
   - In `recordFailure()`: AFTER the existing classify+log+state-transition logic, BEFORE returning, check: if `$currentState === 'closed' && $countInWindow === 3 && Cache::missing('geminigen:status:check_in_flight')` → `Cache::put('geminigen:status:check_in_flight', true, 300)` + `CheckGeminiGenStatusJob::dispatch()`. The cache lock prevents thundering when many segments fail simultaneously.
6. Add 4 config keys in `backend/config/geminigen-circuit.php`:
   ```php
   'firecrawl_api_key' => env('FIRECRAWL_API_KEY'),
   'firecrawl_base_url' => env('FIRECRAWL_BASE_URL', 'https://api.firecrawl.dev/v2'),
   'status_page_url' => env('GEMINIGEN_STATUS_PAGE_URL', 'https://geminigen.ai/status'),
   'status_model_name' => env('GEMINIGEN_STATUS_MODEL_NAME', 'Nano Banana Pro'),
   'status_cache_seconds' => (int) env('GEMINIGEN_STATUS_CACHE_SECONDS', 300),
   ```
7. Add env keys to `.env.example` (5 new keys).
8. Add tests:
   - **`GeminigenStatusPollerTest`** (5 cases, mock Http facade):
     - `test_returns_true_when_our_model_status_contains_outage`
     - `test_returns_false_when_our_model_status_is_operational`
     - `test_returns_null_when_model_not_in_components`
     - `test_returns_null_when_firecrawl_call_fails`
     - `test_uses_cached_result_within_ttl`
   - **`CheckGeminiGenStatusJobTest`** (3 cases):
     - `test_force_opens_breaker_when_poller_returns_true`
     - `test_does_not_force_open_when_poller_returns_false`
     - `test_does_not_force_open_when_poller_returns_null`
   - **Append to `GeminiGenCircuitBreakerTest`** (1 case):
     - `test_dispatches_status_check_job_at_3rd_failure`: assert `Queue::assertPushed(CheckGeminiGenStatusJob::class)` exactly once when failure count crosses 3. Use `Queue::fake()` in setUp.
9. Run all tests: `cd backend && D:/xampp/php/php.exe artisan test --filter='GeminigenStatusPoller|CheckGeminiGenStatusJob|GeminiGenCircuitBreaker'` — all pass (9 new + 17 existing = 26 total in this phase suite).
10. Commit: `feat(geminigen-circuit): Firecrawl status-page accelerator (early trip at failure #3)`

**Verification:**
- [ ] 9 new tests pass + 17 existing pass = 26 total
- [ ] `php -l` clean on all new + modified files
- [ ] Cache lock prevents 2nd `CheckGeminiGenStatusJob::dispatch` within 5-min window — verified by test (2 consecutive `recordFailure` calls that BOTH cross threshold dispatch only 1 job)
- [ ] `forceOpen` does NOT increment failure_count or reset window (it's an out-of-band override)
- [ ] `isOurModelInOutage` gracefully returns null when Firecrawl API key missing (don't crash production)
- [ ] No placeholder/TODO comments
- [ ] `Nano Banana Pro` (current `DEFAULT_IMAGE_MODEL`) is what the matcher looks for by default; configurable via env if `DEFAULT_IMAGE_MODEL` ever flips

---

#### Phase L — CLAUDE.md sync + this plan's Done log

**Estimated time:** 8 minutes

**Files:**
- Modify: `CLAUDE.md` (Last Updated line + add new entry referencing the circuit breaker behavior)

**Steps:**
1. Read current Last Updated line at the bottom of `CLAUDE.md`.
2. Prepend a new entry summarizing the ship: 5 commits, surface added, settings keys, env keys, behavior change ("during GeminiGen outage, all dispatches pause; existing per-segment retry preserved; Telegram emits 1 open + 1 close alert + segment_failed suppressed during outage").
3. Demote the existing entry to `**Earlier (2026-05-14):**` marker preceding it.
4. Add 1 line to "Automation Routes" section listing the new endpoint `GET /api/admin/geminigen/circuit-status (auth:sanctum)`.
5. Commit: `docs(geminigen-circuit): CLAUDE.md sync — breaker ship summary`

**Verification:**
- [ ] CLAUDE.md "Last Updated" entry contains the 5 key facts (failure threshold, window, probe interval, integration points, Telegram dedup)
- [ ] Automation Routes section lists the new endpoint
- [ ] Diff size <100 lines (just one paragraph + 1 route line)

---

### Total Estimated Time

| Phase | Time |
|---|---|
| A — Core breaker + Exception | 12 min |
| B — Failure classifier | 8 min |
| C — Config + env | 5 min |
| D — Gate ImageGenerationService | 10 min |
| E — Gate LinkedInCarouselImageService | 10 min |
| F — Gate RetryImageSegmentJob | 8 min |
| G — Blog webhook failure recording | 8 min |
| H — Canary probe command + schedule | 12 min |
| I — Telegram alerts + suppression | 12 min |
| J — Admin status endpoint | 8 min |
| K — Admin status indicator UI | 12 min |
| L — CLAUDE.md sync | 8 min |
| **Total** | **~113 min** (≈2 hours, single focused session) |

### Parallel Execution Candidates

Hard dependencies: A → B → C (all touch same service). C must finish before D/E/F (those inject the service).

After C, the following waves are safe to run in parallel via `gaspol-parallel plan-phases`:

- **Wave 2 (after C):** D + E + F + G in parallel — independent files, all gates on top of the same breaker
- **Wave 3 (after Wave 2):** H + I + J — also independent
- **Wave 4 (after Wave 3):** K (UI depends on J endpoint)
- **Wave 5:** L (docs sync — pure docs, no code dependency)

Parallel savings: ~25 min (Wave 2 collapse from 36 min sequential to ~12 min concurrent, Wave 3 collapse from 32 min to ~12 min).

### Execution Handoff

**Option 1 — Sequential `/gaspol-execute`**
Run from this file. Per-phase checkpoints between A→L. Most predictable.

**Option 2 — `/gaspol-parallel mode=plan-phases`**
Dispatch A→B→C sequentially, then Wave 2 (D+E+F+G) in parallel, then Wave 3 (H+I+J), then K, then L. ~25 min total saved if all subagents complete clean.

**Option 3 — Separate session**
Plan is self-contained. Resume any time.

### Red Flag Self-Check ✓

- [x] Data Integration Map present (14 rows)
- [x] Every phase has TDD step 1 in mandatory format ("Write failing test for X. Expected error: Y")
- [x] Every phase has Verification block
- [x] CLAUDE.md referenced for ImageGenerationService, LinkedInCarouselImageService, RetryImageSegmentJob, Telegram infra, ScheduledCommand pattern, admin UI conventions
- [x] No vague data sources — every contract names the specific class/facade/file path
- [x] No phase exceeds 15 minutes estimated
- [x] No placeholder language — every gap is "create new" or "edit existing" with the path
