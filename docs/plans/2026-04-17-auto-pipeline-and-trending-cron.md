# Auto-Pipeline + Trending Cron + Telegram Notifications

**Date:** 2026-04-17
**Status:** Design approved → ready for implementation plan
**Owner:** Ali Sadikin

## Context

Portfolio_v2 Content Engine already has a 2-gate manual pipeline (Gate 1 article approval, Gate 2 image approval) plus per-idea `auto_mode` toggle. Infrastructure exists for auto-advancing images → completed, but:

- No cron picks up `draft` ideas with `auto_mode=true` (without `scheduled_at`)
- No auto-advance from `article_ready` → image generation
- No auto-publish (create Post + trigger translate) after images done
- No automated daily trending pull
- No dedup when importing trending topics
- No success/failure notifications

Goal: fully autonomous pipeline — user toggles `auto_mode`, walks away, blog publishes + Telegram pings.

## Design

### Decisions Locked (from brainstorm)

| Area | Choice | Why |
|------|--------|-----|
| Auto gate | Per-idea `auto_mode=true` | Infrastructure already exists (toggle in UI + column). Fine-grained opt-in/out per idea. |
| Concurrency | STRICT sequential (global in-flight gate) | SSH + Claude CLI ~8-11 min per article. User explicit: "selagi ada 1 proses belum completed, jangan lanjut ke content berikutnya walau cron tick sampai". Two-layer lock: `withoutOverlapping(10)` + per-tick `inFlight` check on `researching`/`generating_images` status. |
| Retry | 3 attempts × fixed 5-min delay, then mark failed (manual review) | User spec. Simpler than exponential backoff. Matches existing translation retry pattern. |
| Trending dedup | Fuzzy: slug exact match OR `similar_text >= 85%` over last 30 days | Catches "AI Coding" vs "AI Coding 2026" variations. O(n×m) on <200 rows = <50ms. |
| Trending import | Auto-import with `auto_mode=true`, pillar=`general` | Fully autonomous overnight pipeline. |
| Notifications | Telegram (direct Bot API, no package) | Simpler than adding Slack/email. User has active Telegram. |
| Operating hours | Posting 06:00-22:00 Asia/Jakarta; trending pull 05:00 | User spec: 16-hour active window, 8-hour break (22:00-06:00). Prevents midnight Telegram pings. Trending pull runs just before window opens so ideas are ready. |

### Architecture Overview

```
┌──────────────────────────────────────────────────────────────┐
│  DAILY 05:00 (Asia/Jakarta)                                  │
│  content:pull-trending-daily                                 │
│    1. Pull from Google News + Google Trends                  │
│    2. For each incoming title:                               │
│       - slug exact match vs last 30d → skip                  │
│       - similar_text >= 85% vs last 30d → skip               │
│       - else: INSERT ContentIdea(auto_mode=true, status=draft)│
│    3. Log import_count / skip_count                          │
└─────────────────────────┬────────────────────────────────────┘
                          │
                          ▼ (next minute)
┌──────────────────────────────────────────────────────────────┐
│  EVERY MINUTE (withoutOverlapping 10min lock)                │
│  content:auto-pipeline                                       │
│                                                              │
│  ★ GATE 1: operating hours (06:00-22:00 Asia/Jakarta)        │
│    If current time outside window → exit (skip tick)         │
│    Running in-flight ideas continue via async callbacks,     │
│    but NO new stage advancement during 22:00-06:00 break.    │
│                                                              │
│  ★ GATE 2: in-flight check                                   │
│    If ANY auto_mode idea is in status=researching or         │
│    status=generating_images → exit (skip tick)               │
│    (guarantees STRICT sequential across cron ticks)          │
│                                                              │
│  Priority-ordered single-step advance:                       │
│                                                              │
│  1. FAILED + attempts<3 + next_retry_at<=now                 │
│     → reset status, re-trigger failed stage                  │
│                                                              │
│  2. auto_mode=true + status=draft + scheduled_at IS NULL     │
│     → trigger /article-prep (SSH, ~3min)                     │
│     → status=researching                                     │
│                                                              │
│  3. auto_mode=true + status=article_ready                    │
│     → trigger GeminiGen image gen                            │
│     → status=generating_images                               │
│     (skips Gate 1 human approval)                            │
│                                                              │
│  4. auto_mode=true + status=completed + NOT published yet    │
│     → call approveAndPublish service (create Post + trans)   │
│     → Telegram notify success                                │
│                                                              │
│  (stages 5-6 already handled by existing crons:              │
│    - blog:process-images (GeminiGen poll + auto→completed)   │
│    - content:process-pending-translations)                   │
└──────────────────────────────────────────────────────────────┘
```

### State Machine

```
draft ──(auto-pipeline)──> researching
  ↑                              │
  │ (retry)                      ▼
failed ◄─(3 attempts)────── article_ready
  ↑                              │
  │ (retry)           (auto-pipeline, skip Gate 1)
  │                              ▼
  └───────(3 attempts)───── generating_images
                                 │
                    (blog:process-images cron)
                                 ▼
                            completed (auto_mode=true path)
                                 │
                      (auto-pipeline stage 4)
                                 ▼
                            published → Post created
                                 │
                    (ProcessPendingTranslations)
                                 ▼
                           translation done → Telegram ping
```

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|-----------|-----------|-----------|-------|
| `auto_mode` flag | `content_ideas.auto_mode` col | ✅ | Already used by ProcessPendingImages |
| Article trigger | `ArticleGenerationService::triggerPrep()` | ✅ | Same service used by manual `startResearch` |
| Image gen trigger | Logic inside `ContentIdeaController::generateImages` | ✅ (needs extraction) | Move to `ImageGenerationService::triggerForIdea` for reuse |
| Publish logic | `ContentIdeaController::approveAndPublish` | ✅ (needs extraction) | Move to `ContentPublishService::publish` for reuse |
| Translate retry | `ProcessPendingTranslations` cron | ✅ | Keep as-is, unchanged |
| Trend fetcher | `TrendingTopicService::getAllTrends` | ✅ | Keep as-is, unchanged |
| Dedup service | NEW `App\Services\TopicDedupService` | ❌ | Single method `isDuplicate(title): ?ContentIdea` |
| Pipeline attempts | NEW columns (migration) | ❌ | `pipeline_attempts`, `pipeline_last_attempt_at`, `pipeline_next_retry_at`, `pipeline_failed_stage` |
| Published tracking | `posts.source_idea_id` col | ✅ (verify exists) | Used for "NOT yet published" check — join posts table |
| Telegram notifier | NEW `App\Services\TelegramNotifier` | ❌ | Direct HTTP POST, no package |
| Auto-pipeline cron | NEW `content:auto-pipeline` command | ❌ | Everyminute, withoutOverlapping(10) |
| Trending daily cron | NEW `content:pull-trending-daily` command | ❌ | dailyAt('05:00') TZ Asia/Jakarta |

### Schema Changes

**Migration:** `add_pipeline_retry_fields_to_content_ideas`

```php
Schema::table('content_ideas', function (Blueprint $table) {
    $table->unsignedTinyInteger('pipeline_attempts')->default(0)->after('auto_mode');
    $table->timestamp('pipeline_last_attempt_at')->nullable()->after('pipeline_attempts');
    $table->timestamp('pipeline_next_retry_at')->nullable()->after('pipeline_last_attempt_at');
    $table->string('pipeline_failed_stage', 32)->nullable()->after('pipeline_next_retry_at');
    $table->index(['auto_mode', 'status', 'pipeline_next_retry_at'], 'idx_auto_pipeline_scan');
});
```

### New Status Value

Add `'failed'` to `content_ideas.status` enum/string (current: draft, researching, article_ready, generating_images, images_ready, completed, archived).

Filament/UI needs to handle `failed` status badge (red color, "needs manual review" tooltip).

### Environment Variables

```env
# Telegram notifications
TELEGRAM_BOT_TOKEN=              # From @BotFather
TELEGRAM_CHAT_ID=                # Private chat or group ID
TELEGRAM_ENABLED=true            # Kill-switch for dev

# Already existing (no change)
ARTICLE_GEN_USE_TRANSLATE_PHASE=true   # Must be true for Telegram success ping timing
```

### Telegram Message Templates

**Success (after publish + translate done):**
```
🚀 *New blog published*

*{{ title }}*

🔗 {{ full_url }}

📝 {{ ai_summary_or_meta_description_300_chars }}

Pillar: {{ pillar }} | Source: {{ source }}
```

**Failure (after 3 retries exhausted):**
```
⚠️ *Content pipeline failed* — needs manual review

*{{ title }}*

Failed stage: {{ pipeline_failed_stage }}
Error: {{ last_error_message_truncated }}

Review: {{ admin_url }}/content-engine
```

### Operating Hours Window

**Active window:** 06:00 – 22:00 Asia/Jakarta (16 hours)
**Break:** 22:00 – 06:00 (8 hours)
**Trending pull:** 05:00 (runs just before window opens so fresh ideas are ready when cron resumes)

Operating-hours gate at top of `content:auto-pipeline` (before in-flight check):

```php
$tz = new \DateTimeZone(config('app.cron_timezone', 'Asia/Jakarta'));
$now = new \DateTime('now', $tz);
$hour = (int) $now->format('G');

if ($hour < 6 || $hour >= 22) {
    // Outside operating window — stay silent (don't log every tick)
    return 0;
}
```

**Behavior when window closes (22:00) mid-pipeline:**
- In-flight idea's current async stage (e.g. SSH running, GeminiGen processing) **continues to completion** — we don't kill external work
- Callbacks from SSH/GeminiGen still write to DB (idea flips to `article_ready` / `completed` normally)
- But the NEXT stage advancement (e.g. `article_ready` → trigger images) **will not fire** until 06:00 next day
- Telegram "publish success" notifications also blocked during break (so no 3 AM phone buzz)

**Behavior when window opens (06:00):**
- Cron tick at 06:00 → in-flight check → picks up whatever is ready (status=`article_ready` waiting for image trigger, status=`completed` waiting for publish)
- Queue drains at ~1 stage per cron tick until empty or window closes again

**Why 06:00 for window open (not 05:00 same as trending)?**
- 05:00 trending cron runs, populates ideas as `draft`
- 05:00-06:00: ideas sit quietly in DB (users can still manually review/adjust before auto picks up)
- 06:00: auto-pipeline starts processing
- Gives 1-hour buffer for manual intervention before autopilot engages

**Configurable (env):**
```env
CONTENT_AUTO_WINDOW_START=6      # hour 0-23
CONTENT_AUTO_WINDOW_END=22       # hour 0-23 (exclusive)
CONTENT_AUTO_TIMEZONE=Asia/Jakarta
```

### Concurrency + Locking (STRICT Sequential)

**Requirement:** While ANY idea is mid-pipeline (not `completed` / `published` / `failed` / `archived` / `draft`), cron must NOT start a new idea even if ticks keep firing.

**Two-layer locking:**

1. **Cron-tick lock (overlap prevention):**
   `Schedule::command('content:auto-pipeline')->everyMinute()->withoutOverlapping(10)`
   Prevents simultaneous ticks if one takes >1min.

2. **In-flight gate (global sequential across ticks) — FIRST CHECK EVERY TICK:**
   ```php
   // At top of command handle():
   $inFlight = ContentIdea::where('auto_mode', true)
       ->whereIn('status', ['researching', 'generating_images'])
       ->exists();

   if ($inFlight) {
       $this->info('In-flight idea exists — skipping this tick');
       return 0;
   }
   ```
   Rationale: `researching` and `generating_images` are async (SSH fire-and-forget, GeminiGen webhook). Status flips back to `article_ready` / `completed` / `failed` only when work truly finishes. As long as any idea is in these states, the queue is "busy".

3. **Retry-slot exception:** Stage 1 (failed + next_retry_at<=now) bypasses the in-flight gate ONLY if the retry is for the SAME idea that's stuck. Prevents retry loop from being blocked by its own stuck state. Actually simpler: retry resets status BEFORE in-flight check (flip to previous stage → then cron retriggers next tick).

**Effective behavior:**
- Tick 1 (00:00): idea #1 draft → flips to researching → SSH fire → exit
- Tick 2 (00:01): in-flight check finds #1 researching → skip, exit
- ... (ticks 3-10 all skip)
- Tick N (00:09): SSH callback flipped #1 to article_ready → in-flight check passes → idea #1 article_ready → triggers image gen → status=generating_images → exit
- Tick N+1: in-flight check finds #1 generating_images → skip
- ... eventually #1 → completed → published → notifies Telegram
- Next tick: in-flight check passes → picks up idea #2 draft → repeats

**Worst case:** 10 ideas × ~10min avg (full pipeline, sequential) = ~100min total. Acceptable for overnight batch.

**Lock files:** Laravel default `storage/framework/cache/data/*`. No additional infra needed.

**Stuck-detection safety net (prevents eternal block):**
If an idea has been in `researching` or `generating_images` for > timeout threshold (research: 15min, images: 10min) without progress update, auto-pipeline flips it to `failed` and increments attempts — freeing the in-flight slot for the next idea.

**Timeout interaction with operating window:** Timeout is measured by `updated_at` delta, not wall clock. If window closes at 22:00 with idea in-flight, the 8-hour break is NOT counted as "stuck" because:
- SSH/GeminiGen callbacks fire during break → `updated_at` keeps refreshing on progress_log writes
- If idea finishes during break, status flips to `article_ready` / `completed` (not timeout'd)
- Only truly-stuck ideas (no callbacks at all for >15min continuous) trigger timeout
- At 06:00 window reopen, cron checks `updated_at` — if stale >15min, flips to `failed`; if recent, assumes still progressing

### Retry Detection Logic

**When does a stage count as "failed"?**

| Stage | Failure signal |
|-------|---------------|
| research | Status stuck at `researching` for > 15 min (no progress callback) OR explicit error in progress_log |
| images | Status stuck at `generating_images` for > 10 min OR all image jobs failed |
| publish | Exception thrown during `ContentPublishService::publish()` |

Auto-pipeline cron detects stuck ideas via `updated_at + timeout_threshold < now()`, flips to `failed`, sets `pipeline_failed_stage`, schedules retry at `pipeline_next_retry_at = now() + 5 min`.

### Published Detection

"NOT yet published" check for stage 4:
```php
$idea->status === 'completed'
  && !Post::where('source_idea_id', $idea->id)->exists()
```

Requires `posts.source_idea_id` column (verify presence; add migration if missing).

### Edge Cases

1. **Stuck idea at `researching` >15min with no progress_log update** → flip to `failed`, increment attempts, schedule retry
2. **SSH trigger itself throws exception** → catch, log, flip to `failed` immediately
3. **Telegram API down** → log warning, don't fail the publish
4. **Trending pull returns 0 results** (upstream API down) → log warning, no import, exit 0
5. **Dedup service matches 100% (exact title already exists)** → skip silently, log count
6. **`auto_mode=false` idea exists** → cron ignores entirely (manual flow preserved)
7. **Idea archived mid-pipeline** → cron skips (status=archived is terminal)
8. **Existing `ProcessPendingImages` flips to `completed`** → auto-pipeline picks up at stage 4, publishes. No conflict.

### Out of Scope (explicitly NOT building)

- ❌ Parallel processing (sequential only)
- ❌ Exponential backoff (fixed 5-min interval per user spec)
- ❌ Email/Slack notifications (Telegram only for now)
- ❌ Admin UI changes beyond `failed` status badge
- ❌ Modifying existing ProcessPendingImages or ProcessPendingTranslations crons
- ❌ Changing manual flow (non-auto_mode ideas continue working as before)

## Open Questions (to resolve during planning)

- [ ] Verify `posts.source_idea_id` column exists (check migrations)
- [ ] Confirm Telegram bot token storage — `.env` vs Setting (DB)
- [ ] Confirm timezone: `Asia/Jakarta` for 5 AM cron (per user location, Indonesia)
- [ ] Failure notif timing: after 3rd retry exhausted OR at each retry? (Design says once after 3/3)

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Build a strictly-sequential, time-windowed autonomous content pipeline that processes `auto_mode=true` ideas from `draft` through to published blog posts with Telegram notifications, plus a daily 05:00 trending pull with fuzzy dedup. Preserves all existing manual flows untouched.

### Architecture Context (from root CLAUDE.md + backend CLAUDE.md)

**Existing infrastructure to reuse (do NOT rebuild):**
- `ContentIdea` model — `app/Models/ContentIdea.php` (has `auto_mode`, `scheduled_at`, `generated_article`, `generated_images`, `progress_log`, `status`, `languages`, `instructions`, `result_post_id`)
- `ArticleGenerationService::triggerPrep(int $ideaId, array $config)` — `app/Services/ArticleGenerationService.php:52` (SSH to VPS, fire-and-forget)
- `TrendingTopicService::getAllTrends()` — `app/Services/TrendingTopicService.php` (Google News RSS + Google Trends JSON)
- `ContentIdeaController::approveAndPublish($id)` — `app/Http/Controllers/Api/Admin/ContentIdeaController.php:809` (~180 lines of publish logic — extract to service in Phase 4)
- `ContentIdeaController::startImageGeneration($id)` — triggers GeminiGen image batch via `ImageGenerationService`
- `ProcessPendingImages` cron — already flips `auto_mode=true` ideas → `completed` when images resolve (`app/Console/Commands/ProcessPendingImages.php:159`)
- `ProcessPendingTranslations` cron — already retries failed translations 3× every 5min
- `posts.source_idea_id` column — already exists (`2026_04_16_000001_add_translation_tracking_to_posts.php`), has index
- `posts.translation_pending` flag — already drives translation retry cron
- Scheduler file: `routes/console.php` (Laravel 12 new-style)

**Patterns to follow:**
- Pest tests (see `tests/Feature/GalleryApiTest.php` for pattern — `test('...', function () { ... });`)
- Services in `app/Services/` namespace `App\Services`
- Console commands in `app/Console/Commands/` with `$signature` + `$description` + `handle()`
- Graceful error logging via `Log::error()` / `Log::warning()` — never throw from cron handlers
- DB ops wrapped in try/catch within cron loops so one bad idea doesn't crash the batch

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8, Pest 3 for tests, Guzzle (via `Http` facade) for Telegram.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Pick auto ideas | `ContentIdea` where `auto_mode=true` | Eloquent query | Yes | Use existing model |
| Trigger research | `ArticleGenerationService::triggerPrep` | Service method | Yes | Call directly |
| Trigger image gen | `ImageGenerationService` via `ContentIdeaController::startImageGeneration` logic | Needs extraction → `ImageGenerationService::triggerForIdea` | Partial | **Extract** in Phase 5 |
| Publish post | `ContentIdeaController::approveAndPublish` | Needs extraction → `ContentPublishService::publish` | Partial | **Extract** in Phase 4 |
| Check published | `Post` where `source_idea_id=?` | Eloquent query | Yes | Use existing |
| Translate retry | `ProcessPendingTranslations` cron | — | Yes | Leave unchanged |
| Pull trending | `TrendingTopicService::getAllTrends` | Service method | Yes | Call directly |
| Dedup logic | `TopicDedupService::isDuplicate(title)` | New service | **No** | Create in Phase 2 |
| Telegram notify | `TelegramNotifier::notifyPublishSuccess(Post)` + `notifyFinalFailure(ContentIdea)` | New service | **No** | Create in Phase 3 |
| Pipeline retry state | `content_ideas.pipeline_attempts`, `pipeline_last_attempt_at`, `pipeline_next_retry_at`, `pipeline_failed_stage` | New columns | **No** | Migration in Phase 1 |
| In-flight check | Eloquent query: `ContentIdea::whereIn('status', ['researching', 'generating_images'])->where('auto_mode', true)->exists()` | — | Yes | Use existing columns |
| Operating window | Env: `CONTENT_AUTO_WINDOW_START`, `CONTENT_AUTO_WINDOW_END`, `CONTENT_AUTO_TIMEZONE` | New config | **No** | Phase 8 config/env |

---

### Phase 1: Migration + Model Updates

**Estimated time:** 5 min

**Files:**
- Create: `backend/database/migrations/2026_04_17_100000_add_pipeline_retry_fields_to_content_ideas.php`
- Modify: `backend/app/Models/ContentIdea.php` (add 4 fields to `$fillable` + 2 casts)
- Test: `backend/tests/Feature/ContentIdeaPipelineFieldsTest.php`

**Steps:**
1. Write failing test `backend/tests/Feature/ContentIdeaPipelineFieldsTest.php` asserting `ContentIdea::create([..., 'pipeline_attempts' => 1, 'pipeline_failed_stage' => 'research'])` persists both. Expected error: `SQLSTATE[42S22]: Column not found: pipeline_attempts`.
2. Run `php d:/xampp/php/php.exe artisan test --filter=ContentIdeaPipelineFieldsTest` — confirm SQL error.
3. Create migration adding columns: `pipeline_attempts` (unsignedTinyInteger, default 0, after `auto_mode`), `pipeline_last_attempt_at` (timestamp nullable), `pipeline_next_retry_at` (timestamp nullable), `pipeline_failed_stage` (string 32 nullable). Add composite index `idx_auto_pipeline_scan` on `(auto_mode, status, pipeline_next_retry_at)`.
4. Run `php d:/xampp/php/php.exe artisan migrate`.
5. Add 4 field names to `$fillable` array in `ContentIdea.php`. Add `'pipeline_last_attempt_at' => 'datetime', 'pipeline_next_retry_at' => 'datetime'` to `$casts`.
6. Run test — confirm it passes.
7. Commit: `feat(content-engine): add pipeline retry tracking fields to content_ideas`

**Verification:**
- [ ] `php artisan migrate:status` shows the new migration ran
- [ ] `SHOW INDEX FROM content_ideas` shows `idx_auto_pipeline_scan`
- [ ] Test passes
- [ ] `ContentIdea::create` accepts all 4 new fields
- [ ] No placeholder/TODO comments in new code

---

### Phase 2: TopicDedupService (TDD)

**Estimated time:** 10 min

**Files:**
- Create: `backend/app/Services/TopicDedupService.php`
- Test: `backend/tests/Feature/TopicDedupServiceTest.php`

**Steps:**
1. Write failing test for `TopicDedupService::isDuplicate()` covering: exact slug match returns match; `similar_text >= 85%` returns match; new topic returns null; topics older than 30 days ignored. Expected error: `Class "App\Services\TopicDedupService" not found`.
2. Run `php d:/xampp/php/php.exe artisan test --filter=TopicDedupServiceTest` — confirm class-not-found.
3. Create service with single public method `isDuplicate(string $title): ?ContentIdea`. Internal logic: generate slug via `Str::slug`, query `ContentIdea::where('created_at', '>=', now()->subDays(30))->get()`, iterate: if `Str::slug($existing->title) === $incomingSlug` → return match; else compute `similar_text($title, $existing->title, $percent)` → if `$percent >= 85` → return match.
4. Run tests — confirm all 4 pass.
5. Commit: `feat(content-engine): add TopicDedupService for fuzzy trending dedup`

**Verification:**
- [ ] All 4 test cases pass
- [ ] No N+1 queries (single `->get()` call loads comparison set)
- [ ] 30-day window respected
- [ ] Similarity threshold configurable via constant `SIMILARITY_THRESHOLD = 85`
- [ ] No placeholder/TODO comments

---

### Phase 3: TelegramNotifier Service (TDD)

**Estimated time:** 8 min

**Files:**
- Create: `backend/app/Services/TelegramNotifier.php`
- Modify: `backend/config/services.php` (add `telegram` section)
- Modify: `backend/.env.example` (add `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_ENABLED`)
- Test: `backend/tests/Feature/TelegramNotifierTest.php`

**Steps:**
1. Write failing test using `Http::fake()` covering: `notifyPublishSuccess(Post)` sends correct message body; `notifyFinalFailure(ContentIdea)` sends different template; when `TELEGRAM_ENABLED=false`, zero HTTP calls; when API returns 500, logs error but doesn't throw. Expected error: `Class "App\Services\TelegramNotifier" not found`.
2. Run test — confirm class-not-found.
3. Add `config/services.php` entry: `'telegram' => ['bot_token' => env('TELEGRAM_BOT_TOKEN'), 'chat_id' => env('TELEGRAM_CHAT_ID'), 'enabled' => env('TELEGRAM_ENABLED', true)]`.
4. Create `TelegramNotifier` with 2 public methods + private `send($message)`. `send()` uses `Http::timeout(10)->asJson()->post('https://api.telegram.org/bot{token}/sendMessage', ['chat_id' => ..., 'text' => $message, 'parse_mode' => 'Markdown'])`. Wrap in try/catch → `Log::warning`. Short-circuit when `!enabled`.
5. Format templates per design section ("🚀 New blog published" / "⚠️ Content pipeline failed"). Use `e()` to escape user content; Telegram Markdown escape special chars `_*[]()~`.
6. Run tests — all 4 pass.
7. Append 3 lines to `.env.example`.
8. Commit: `feat(content-engine): add Telegram notification service`

**Verification:**
- [ ] All 4 test cases pass
- [ ] `Http::fake()` assertions verify URL + payload shape
- [ ] `enabled=false` short-circuits before HTTP call
- [ ] Error path logs warning, returns silently
- [ ] No real HTTP calls during tests
- [ ] No placeholder/TODO comments

---

### Phase 4: Extract ContentPublishService

**Estimated time:** 12 min

**Files:**
- Create: `backend/app/Services/ContentPublishService.php`
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (thin wrapper in `approveAndPublish`)
- Test: `backend/tests/Feature/ContentPublishServiceTest.php`

**Steps:**
1. Write failing test: `ContentPublishService::publish(ContentIdea)` returns created `Post` with `source_idea_id`, publishes translations, handles missing translation (sets `translation_pending=true`), cleans up non-selected variation URLs, throws on invalid status. Expected error: `Class "App\Services\ContentPublishService" not found`.
2. Run test — confirm class-not-found.
3. Extract lines 822-end of `ContentIdeaController::approveAndPublish` into `ContentPublishService::publish(ContentIdea $idea): Post`. Move private helpers `spliceBodyImagesIntoContent`, `buildSeoDefaults` along with it. Throw `\DomainException` on invalid status (controller catches → 422).
4. Update controller `approveAndPublish($id)` to: find idea → call `$this->publishService->publish($idea)` → return JSON. Inject service via constructor.
5. Update `routes/api.php` route binding — no change needed (same endpoint, same response).
6. Run tests — all pass. Run existing controller tests if any — confirm no regression.
7. Commit: `refactor(content-engine): extract publish logic to ContentPublishService`

**Verification:**
- [ ] `publish()` method callable from both controller and cron
- [ ] Controller `approveAndPublish` is now <30 lines (just validation + call + response)
- [ ] Existing `POST /api/admin/content-engine/ideas/{id}/publish` still works (smoke test via `curl`)
- [ ] `Post` upsert on `source_idea_id` still idempotent (re-publish works)
- [ ] Variation cleanup still fires at correct timing
- [ ] No placeholder/TODO comments

---

### Phase 5: Extract ImageGenTrigger to Service

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Services/ImageGenerationService.php` (add method `triggerForIdea(ContentIdea): void`)
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php::startImageGeneration` (thin wrapper)
- Test: `backend/tests/Feature/ImageGenerationTriggerTest.php`

**Steps:**
1. Write failing test: `ImageGenerationService::triggerForIdea($idea)` dispatches GeminiGen jobs for each `image_prompts` entry, flips idea status to `generating_images`, handles empty prompts gracefully. Expected error: `Call to undefined method App\Services\ImageGenerationService::triggerForIdea()`.
2. Run test — confirm method-not-found.
3. Extract job-dispatch loop from `ContentIdeaController::startImageGeneration` into the new service method. Preserve variations-aware handling (multiple jobs per prompt when `variations` array present).
4. Update controller `startImageGeneration` to: find idea → call `$this->imageGen->triggerForIdea($idea)` → return JSON.
5. Run tests — all pass.
6. Commit: `refactor(content-engine): extract image generation trigger to service`

**Verification:**
- [ ] `triggerForIdea` callable from cron (no request context required)
- [ ] Status flip to `generating_images` idempotent (safe to call twice)
- [ ] Empty `image_prompts` returns gracefully (no jobs dispatched, idea not flipped)
- [ ] Existing `POST /api/admin/content-engine/ideas/{id}/generate-images` endpoint still works
- [ ] No placeholder/TODO comments

---

### Phase 6: AutoPipelineOrchestrator Service (TDD)

**Estimated time:** 20 min

**Files:**
- Create: `backend/app/Services/AutoPipelineOrchestrator.php`
- Test: `backend/tests/Feature/AutoPipelineOrchestratorTest.php`

**Steps:**
1. Write failing test covering all 4 advance priorities: (a) retry a `failed` idea with `pipeline_attempts<3` and `next_retry_at<=now()`; (b) advance `draft` with `auto_mode=true` and `scheduled_at=null` → `researching`; (c) advance `article_ready` → triggers image gen → `generating_images`; (d) advance `completed` not yet published → calls `ContentPublishService::publish` → fires Telegram success. Plus 3 gate tests: outside operating window returns null without action; in-flight idea (`researching` exists) returns null; no auto_mode=true ideas returns null. Plus stuck-detection test: idea `researching` with `updated_at` >15min ago → flip to `failed`. Expected error: `Class "App\Services\AutoPipelineOrchestrator" not found`.
2. Run test — confirm class-not-found.
3. Create service with public `tick(): ?ContentIdea` returning the processed idea (or null if no-op). Inject `ArticleGenerationService`, `ImageGenerationService`, `ContentPublishService`, `TelegramNotifier`. Implement gates + priority logic per design section. Timeouts: research 15min, images 10min (constants). On failure throw → catch, increment `pipeline_attempts`, set `pipeline_failed_stage`, `pipeline_next_retry_at = now()->addMinutes(5)`, status=`failed`. On 3rd attempt exhausted → fire `TelegramNotifier::notifyFinalFailure` + leave status=`failed` (`pipeline_next_retry_at = null` to stop retrying).
4. Operating window logic: read `config('content.auto_window_start', 6)`, `auto_window_end` (22), `auto_timezone` (Asia/Jakarta). Compare current hour. Return null if outside.
5. In-flight check: `ContentIdea::where('auto_mode', true)->whereIn('status', ['researching', 'generating_images'])->exists()` before advancing.
6. Publish-detection: `Post::where('source_idea_id', $idea->id)->exists()` for the `completed` stage check.
7. Run tests — all 11 cases pass.
8. Commit: `feat(content-engine): add AutoPipelineOrchestrator with strict sequential gating`

**Verification:**
- [ ] All 11 test cases pass
- [ ] Operating-hours gate verified with `Carbon::setTestNow()` at 23:00 and 05:00
- [ ] In-flight gate blocks when any auto_mode idea is `researching` or `generating_images`
- [ ] Stuck detection flips idea to `failed` after 15min (research) / 10min (images) of no `updated_at` change
- [ ] Retry logic: 3 attempts × 5min, then `next_retry_at=null` + Telegram failure notify
- [ ] Telegram success fires AFTER publish succeeds (not before)
- [ ] No placeholder/TODO comments

---

### Phase 7: `content:auto-pipeline` Artisan Command

**Estimated time:** 6 min

**Files:**
- Create: `backend/app/Console/Commands/RunAutoPipeline.php`
- Test: `backend/tests/Feature/RunAutoPipelineCommandTest.php`

**Steps:**
1. Write failing test: `artisan('content:auto-pipeline')` exits 0 when orchestrator returns null; logs processed idea title when orchestrator returns idea. Expected error: `Command "content:auto-pipeline" is not defined`.
2. Run test — confirm command-not-defined.
3. Create command with `$signature = 'content:auto-pipeline'`, `$description = 'Advance auto_mode content ideas one stage per tick with strict sequential gating'`. Inject `AutoPipelineOrchestrator`. Handler: call `tick()`, log result via `$this->info()` or `$this->line('idle')`, return 0.
4. Run test — passes.
5. Commit: `feat(content-engine): add content:auto-pipeline artisan command`

**Verification:**
- [ ] `php artisan list` shows `content:auto-pipeline`
- [ ] `php artisan content:auto-pipeline` runs without error
- [ ] Command logs which idea/stage was processed (or "idle")
- [ ] Test passes
- [ ] No placeholder/TODO comments

---

### Phase 8: `content:pull-trending-daily` Artisan Command

**Estimated time:** 10 min

**Files:**
- Create: `backend/app/Console/Commands/PullTrendingDaily.php`
- Test: `backend/tests/Feature/PullTrendingDailyCommandTest.php`

**Steps:**
1. Write failing test: command creates `ContentIdea` records for unique trends, skips duplicates detected by `TopicDedupService`, sets `auto_mode=true` and `status='draft'` and `source_data=[...]`, logs counts. Fake `TrendingTopicService` to return 3 titles where 1 is already in DB. Expected error: `Command "content:pull-trending-daily" is not defined`.
2. Run test — confirm command-not-defined.
3. Create command with `$signature = 'content:pull-trending-daily'`. Inject `TrendingTopicService`, `TopicDedupService`. Handler: call `getAllTrends()`, iterate, for each trend call `TopicDedupService::isDuplicate($title)` → if null, `ContentIdea::create(['title' => $title, 'source' => $source, 'status' => 'draft', 'auto_mode' => true, 'pillar' => 'general', 'source_data' => $trend])`. Tally imported/skipped. `$this->info("Imported: $imported, Skipped: $skipped")`.
4. Wrap each idea create in try/catch → log and continue (one bad trend shouldn't abort batch).
5. Run tests — pass.
6. Commit: `feat(content-engine): add content:pull-trending-daily artisan command`

**Verification:**
- [ ] `php artisan content:pull-trending-daily` executes without error
- [ ] Duplicates correctly skipped (verify count in log)
- [ ] All new ideas have `auto_mode=true` and `status=draft`
- [ ] `source_data` JSON preserved for later inspection
- [ ] No placeholder/TODO comments

---

### Phase 9: Wire Scheduler + Config + Env

**Estimated time:** 5 min

**Files:**
- Modify: `backend/routes/console.php` (2 new `Schedule::command` entries)
- Create: `backend/config/content.php` (operating hours config)
- Modify: `backend/.env.example` (window + timezone vars)
- Modify: `CLAUDE.md` (root) — add paragraph under "Content Pipeline (CLI-Based)" section documenting auto-pipeline + trending crons

**Steps:**
1. Create `backend/config/content.php`:
   ```php
   return [
       'auto_window_start' => env('CONTENT_AUTO_WINDOW_START', 6),
       'auto_window_end' => env('CONTENT_AUTO_WINDOW_END', 22),
       'auto_timezone' => env('CONTENT_AUTO_TIMEZONE', 'Asia/Jakarta'),
   ];
   ```
2. Append to `routes/console.php`:
   ```php
   Schedule::command('content:auto-pipeline')
       ->everyMinute()
       ->withoutOverlapping(10);

   Schedule::command('content:pull-trending-daily')
       ->dailyAt('05:00')
       ->timezone('Asia/Jakarta');
   ```
3. Append to `.env.example`: `TELEGRAM_BOT_TOKEN=`, `TELEGRAM_CHAT_ID=`, `TELEGRAM_ENABLED=true`, `CONTENT_AUTO_WINDOW_START=6`, `CONTENT_AUTO_WINDOW_END=22`, `CONTENT_AUTO_TIMEZONE=Asia/Jakarta`.
4. Add 1-paragraph section to root `CLAUDE.md` under "Content Pipeline (CLI-Based)" heading — document the 2 new crons, operating hours, retry policy.
5. Run `php artisan schedule:list` — confirm both new entries appear.
6. Run `php artisan config:clear` to flush config cache.
7. Commit: `feat(content-engine): wire auto-pipeline and trending crons to scheduler`

**Verification:**
- [ ] `php artisan schedule:list` shows `content:auto-pipeline` at `* * * * *` with `withoutOverlapping`
- [ ] `php artisan schedule:list` shows `content:pull-trending-daily` at `0 5 * * *` with `Asia/Jakarta`
- [ ] `config('content.auto_window_start')` returns 6
- [ ] `CLAUDE.md` updated with cron documentation
- [ ] `.env.example` contains all 6 new vars
- [ ] No placeholder/TODO comments

---

### Phase 10: End-to-End Smoke Test

**Estimated time:** 10 min

**Files:**
- Create: `backend/tests/Feature/AutoPipelineE2ETest.php`

**Steps:**
1. Write Pest integration test (mark `@group slow`) simulating full pipeline with fakes:
   - Seed 2 `ContentIdea` with `auto_mode=true, status=draft`
   - Fake `ArticleGenerationService::triggerPrep` to return `['success' => true, 'pid' => 1234]`
   - Fake `Http` for Telegram + GeminiGen
   - Run `artisan('content:auto-pipeline')` 10 times with `Carbon::setTestNow` advancing 1min each
   - Simulate SSH callback flipping status manually between ticks (mimicking plugin behavior)
   - Assert: only 1 idea active at a time (in-flight gate honored); second idea never starts while first in `researching`
2. Additional test: run command at 23:00 → assert no-op (operating hours gate).
3. Additional test: run command when one idea is `researching` → assert `tick()` returns null (in-flight gate).
4. Run tests — pass.
5. Commit: `test(content-engine): add auto-pipeline end-to-end integration tests`

**Verification:**
- [ ] All 3 E2E scenarios pass
- [ ] No real SSH / HTTP calls during tests
- [ ] Sequential ordering verified (idea #2 blocked while #1 in-flight)
- [ ] Operating-hours gate verified at 23:00
- [ ] `php artisan test` full suite green

---

### Phase 11: Manual QA + Telegram Test Ping

**Estimated time:** 8 min (manual)

**Files:**
- None (runtime verification only)

**Steps:**
1. Set `.env` locally: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` (create bot via `@BotFather`, get chat ID via `@userinfobot`).
2. Run one-shot test: `php artisan tinker` → `app(\App\Services\TelegramNotifier::class)->send('test from auto-pipeline')` → confirm phone receives message.
3. Create 1 test idea manually in admin UI with `auto_mode=true`, status=`draft`.
4. Run `php artisan content:auto-pipeline` manually during operating hours → verify status flips to `researching` and SSH log shows trigger.
5. Wait ~3-5 min for article-prep callback → verify status becomes `article_ready`.
6. Run cron again → verify image gen triggered, status=`generating_images`.
7. Wait for images → verify auto-flip to `completed`.
8. Run cron again → verify `Post` created, Telegram ping received.
9. Force a failure: create idea with invalid title (e.g., empty) → verify 3 retries then final failure Telegram.
10. Trigger `php artisan content:pull-trending-daily` manually → verify new ideas imported with dedup logs.

**Verification:**
- [ ] Real Telegram message received on publish success (phone)
- [ ] Real Telegram message received on final failure
- [ ] `Post` visible at `/blog/{slug}` frontend
- [ ] `content_ideas.result_post_id` populated
- [ ] Trending command imports with dedup logs (`Imported: X, Skipped: Y`)
- [ ] Scheduler loop runs for 1 hour without errors (check `storage/logs/laravel.log`)

---

### Phase Dependency Graph

```
1 (migration) ──┬──► 6 (orchestrator) ──► 7 (command) ──┐
                │                                        │
2 (dedup) ──────┼──► 8 (trending cmd) ──────────────────┤
                │                                        ├──► 9 (wire) ──► 10 (E2E) ──► 11 (manual QA)
3 (telegram) ──┤                                         │
                │                                        │
4 (publish svc)─┼──► 6 ──────────────────────────────────┤
                │                                        │
5 (image svc) ──┘                                        │
```

**Parallelizable:** Phases 1, 2, 3 can run concurrently (no dependencies between them). Phases 4 + 5 can run concurrently after 1 is done. Phase 6 requires 1, 3, 4, 5 complete. Phases 7 + 8 can run after 6 + 2 respectively.

**Total estimated time:** ~100 min sequential, ~60 min with parallel phases 1+2+3 and 4+5.

### Rollback Plan

If any phase fails mid-implementation:
- Phases 1-9: `git revert` the phase's commit, `php artisan migrate:rollback` if Phase 1 ran
- Phase 10-11: no code changes, just delete test data
- **Kill-switch for production:** set `TELEGRAM_ENABLED=false` + comment out 2 scheduler lines in `routes/console.php` — pipeline fully dormant, manual flow unaffected

### Post-Completion

- Update root `CLAUDE.md` "Last Updated" date + 1-line changelog entry
- Announce to user: Telegram bot token + chat ID setup steps (Phase 11 prerequisite)
- Monitor `storage/logs/laravel.log` for first 24 hours of autonomous operation

