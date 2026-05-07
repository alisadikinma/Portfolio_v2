# Pipeline Error Classifier + Bounded Auto-Retry

**Date:** 2026-05-07
**Status:** Design approved, awaiting implementation plan
**Owner:** Ali Sadikin
**Related KB:** [adr-2026-04-28-carousel-engine-publisher-separation](~/.claude/gaspol-knowledge/design-decisions/adr-2026-04-28-carousel-engine-publisher-separation.md)

## Design

### Problem Statement

Three pain points:

1. **LinkedIn FSM=Failed butuh manual click Regenerate.** CLAUDE.md eksplisit "we deliberately don't auto-redispatch — surfacing to admin keeps human in the loop". Tapi kalau >50% failures adalah transient (SSH timeout, queue worker crash), policy ini buang waktu operator.
2. **Carousel slide failures setelah safety rewrite habis.** Existing `applySafetyRewriteIfNeeded` cuma 1 attempt, treat semua NB2 errors sebagai 1 class, tidak ada escape hatch kalau Sonnet rewrite juga gagal.
3. **ContentIdea pipeline FSM=Failed sama (tidak ada auto-retry).**

User clarification: NB2 errors biasanya dari content policy. Webhook return error message yang spesifik (`PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD`, dll). Existing `isSafetyError` cuma binary detection — sudah bagus tapi rewrite strategy generic untuk semua class.

### Goals

- Bounded auto-retry untuk transient + deterministic-LLM errors (cost-capped, audit-trailed).
- Per-class targeted prompt rewrite untuk NB2 policy refusals (POLICY_PERSON, POLICY_BRAND, POLICY_NSFW, POLICY_MINOR, POLICY_GENERIC).
- Tier-2 generic-stock fallback prompt untuk slide yang sulit lolos.
- Surface NB2 error message verbatim ke admin UI.
- Telegram inline buttons untuk operator one-click resolve from notification.
- Single classifier service shared antara LinkedIn pipeline + Content Engine pipeline.

### Non-Goals

- NOT auto-retry untuk POLICY_* errors (operator decision tetap diperlukan; auto-retry dengan prompt yang sama akan reject lagi).
- NOT auto-retry untuk PERMANENT errors (validation rejected, depth fail — fix di plugin / source content).
- NOT building global pipeline-healer daemon (Approach C from brainstorm — over-engineered).

## Architecture

### New Components

```
┌──────────────────────────────────────────────────────────────┐
│  PipelineErrorClassifier (App\Services)                      │
│  classify(?string $error): PipelineErrorClass                │
│  - Pure function, no side effects                            │
│  - Substring matching with priority order                    │
│  - Returns enum value                                        │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│  PipelineErrorClass enum (App\Enums)                         │
│  - TRANSIENT (SSH timeout, queue crash, network, 5xx)        │
│  - DETERMINISTIC_LLM (Sonnet truncated, parse fail, schema)  │
│  - POLICY_PERSON (PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD)      │
│  - POLICY_MINOR (PUBLIC_ERROR_MINOR)                         │
│  - POLICY_NSFW (PUBLIC_ERROR_UNSAFE/SEXUAL)                  │
│  - POLICY_BRAND (heuristic: brand/logo/trademark mentions)   │
│  - POLICY_GENERIC (other safety filters)                     │
│  - PERMANENT (validation rejected, depth fail)               │
│  - UNKNOWN (no match — treat as PERMANENT, operator review)  │
└──────────────────────────────────────────────────────────────┘
```

### Per-Pipeline Wiring

**LinkedIn Pipeline:**
```
LinkedInGenerationService failure
  → markFailed sets last_error
  → cron RetryFailedLinkedInPosts (every 10min)
    → classify(last_error)
    → if TRANSIENT (backoff 5/30 min) or DETERMINISTIC_LLM (backoff 30 min):
        increment auto_retry_count (cap=2)
        record last_classified_error_class
        transition Failed → PendingGeneration via PipelineGuard
        dispatch GenerateLinkedInPost
    → if POLICY_* / PERMANENT / UNKNOWN: skip
    → on exhaustion (retry_count=2 + still failed):
        dispatch DispatchTelegramNotification('linkedin_auto_retry_exhausted')
```

**Carousel Slide Pipeline (extends existing applySafetyRewriteIfNeeded):**
```
GeminiGen webhook image_status=failed
  → reason = webhook.error_message
  → classify(reason)
  → if POLICY_*:
      tier = slide.image_rewrite_tier ?? 0
      tier 0 → Tier 1 (Sonnet rewrite WITH error_class injected to prompt)
      tier 1 → Tier 2 (in-process generic-stock fallback prompt builder)
      tier 2 → Tier 3 (mark image_status='failed_permanent', surface verbatim)
  → if TRANSIENT: existing GeminiGen retry path (already handled)
  → if PERMANENT/UNKNOWN: surface immediately, no auto-fix
```

**Content Engine Pipeline:**
```
ArticleGenerationService failure on /article-prep / write / score
  → ContentIdea.status='failed', last_error stamped
  → cron RetryFailedContentIdeas (every 10min)
    → classify(last_error)
    → if TRANSIENT/DETERMINISTIC_LLM:
        increment auto_retry_count (cap=2 — same as LinkedIn)
        record last_classified_error_class
        transition Failed → Researching via PipelineGuard
        re-trigger ArticleGenerationService.startResearch
    → on exhaustion: Telegram notify
```

### Per-Class Sonnet Prompt Branching (Tier 1)

Modify `ArticleGenerationService::buildSafetyRewritePrompt(string $vd, string $reason, array $context, ?string $errorClass = null)`:

| Error class | Sonnet branch instruction |
|---|---|
| POLICY_PERSON | Strip ONLY proper nouns referring to people (named persons, role+org like "CEO of Anthropic"). KEEP brand names, scene composition, lighting, mood. |
| POLICY_BRAND | Strip ONLY brand names + product references + logos. KEEP persons (use generic descriptors), scene composition. |
| POLICY_MINOR | Replace age-ambiguous descriptors with explicit "adult professional, 30+". Strip school/youth context. KEEP everything else. |
| POLICY_NSFW | Soften descriptors: replace tension/conflict/violent words with neutral synonyms. Strip suggestive elements. KEEP scene framing. |
| POLICY_GENERIC | Aggressive strip (current behavior — proper nouns + entity_refs + face_refs). |
| (null/default) | Aggressive strip (backward compat for non-classified callers). |

### Tier 2 Generic-Stock Fallback (no Sonnet)

Pure PHP template based on `slide.layout_hint`:

```php
buildGenericStockPrompt(string $layoutHint, string $copy): string
{
    $base = "Professional studio photograph, photorealistic, soft natural lighting, neutral background, business context, no recognizable people, no brands, no logos, 4:5 aspect ratio.";

    return match($layoutHint) {
        'cover' => "{$base} Hero composition with subtle depth, rule of thirds. Subject context: {$copy}",
        'data_point' => "{$base} Clean infographic-style layout with abstract data visualization elements.",
        'human_fingerprint', 'body' => "{$base} Generic workspace scene, abstract human silhouette in soft focus.",
        'cta' => "{$base} Minimalist composition with breathing room for text overlay.",
        default => $base,
    };
}
```

Brand chrome (logo, page indicator, swipe text) tetap di-append via existing `CarouselSlideEnhancer::appendBrandChrome` jadi slide tier-2 tetap on-brand walau scene-nya generic.

### Telegram Inline Buttons

New webhook endpoint:
```
POST /api/automation/telegram/webhook  (public, HMAC-verified)
```

Notification payload extended dengan `reply_markup.inline_keyboard`:

```
[Approve manual retry]   → callback_data: "retry:{kind}:{id}:{hmac}"
[Cancel draft]           → callback_data: "cancel:{kind}:{id}:{hmac}"
[Open in admin]          → URL button (no callback)
```

`{kind}` = `linkedin` or `idea`. `{hmac}` = HMAC-SHA256 of `{action}:{kind}:{id}:{secret}` truncated to 12 chars. Webhook handler verifies HMAC, idempotent action (action sudah dilakukan = 200 OK no-op), dispatches retry/cancel via existing service methods.

### Schema Changes

**1 migration**, 2 cols × 2 tables:

```sql
ALTER TABLE linkedin_posts
  ADD COLUMN auto_retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_error,
  ADD COLUMN last_classified_error_class VARCHAR(32) NULL AFTER auto_retry_count;

ALTER TABLE content_ideas
  ADD COLUMN auto_retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN last_classified_error_class VARCHAR(32) NULL;
```

In-JSON convention (no DDL):
- `carousel_slides[].image_rewrite_tier` INT 0|1|2

### Settings (telegram group)

Add 4 new settings keys (idempotent firstOrCreate seeder):
- `telegram_notify_linkedin_auto_retry_exhausted` (default `'true'`)
- `telegram_notify_idea_auto_retry_exhausted` (default `'true'`)
- `telegram_notify_carousel_tier2_failed` (default `'true'`)
- `telegram_webhook_secret` (random 32-char, encrypted)

### Admin UI Changes (minimal)

- `LinkedInDraftDetail.vue`: when slide.image_status='failed' or 'failed_permanent', show NB2 error verbatim in error block + class chip + tier indicator. Existing "Retry this slide" button works untuk reset tier=0.
- `LinkedInQueueList.vue`: kalau auto_retry_count>0 di Failed tab, tampilkan small chip "auto-retried Nx".
- `ContentEngine.vue`: same chip on failed ideas.
- AboutSettings.vue: add toggles untuk 3 new telegram notify keys + show webhook secret (masked).

## Data Integration Map

| Component | Data source | Existing? | Notes |
|---|---|---|---|
| `PipelineErrorClassifier::classify` | `string $error` from caller | new service | Pure function, no DB |
| `RetryFailedLinkedInPosts` cron | `linkedin_posts WHERE status=failed AND auto_retry_count<2` | new query, table exists | +2 columns via migration |
| `RetryFailedContentIdeas` cron | `content_ideas WHERE status=failed AND auto_retry_count<2` | new query, table exists | +2 columns via migration |
| Tier 1 Sonnet rewrite | `ArticleGenerationService::rewriteVisualDirectionForSafety` | extends existing — add `?string $errorClass` param | Backward compat (param optional) |
| Tier 2 generic-stock prompt | `carousel_slides[].layout_hint` + `copy` | already in JSON | New helper in `LinkedInCarouselImageService` |
| Telegram webhook | `POST /api/automation/telegram/webhook` | new endpoint | HMAC-verified callback handler |
| Telegram inline buttons | TelegramNotificationService payload | extends existing | Add `reply_markup` field, generate HMAC per button |
| NB2 error verbatim UI | `carousel_slides[i].image_error` | already exposed in API | Just render in Vue component |
| Telegram settings | new keys in `telegram` group | extends existing seeder | 4 new firstOrCreate rows |

## Constraints & Assumptions

- **Cost ceiling:** Max 2 auto-retries per record × ~$0.05 Sonnet per retry × 1 SSH call ~= $0.10 worst-case per record. Acceptable.
- **Backoff windows** (locked): TRANSIENT 5min/30min, DETERMINISTIC_LLM 30min, POLICY_* no retry, PERMANENT no retry.
- **Cron cadence:** every 10min for both retry crons (within `routes/console.php`, `withoutOverlapping(15)`).
- **Telegram webhook URL:** must be public + HTTPS (✅ already on alisadikinma.com via Cloudflare). Bot setWebhook ke endpoint baru, manual one-time setup di operator runbook.
- **Idempotency:** all retry actions safe to call multiple times. Telegram callback handler must respond 200 OK even on duplicate (Telegram retries on 5xx).
- **No deferred cost cap setting** (Phase 2 if first phase shows runaway). Per-record cap=2 is the bound.

## Open Questions (resolved during brainstorm)

- ✅ Cost cap setting deferred — per-record cap=2 sudah cukup
- ✅ Tier-2 auto-fire (operator confirm tidak diperlukan) — tetap on-brand via chrome enhancer
- ✅ POLICY_MINOR/NSFW kategori dimasukkan walau jarang trigger — biar classifier complete

## Success Criteria

1. LinkedIn `failed` row dengan `last_error` mengandung "SSH timeout" auto-recovers dalam <40 menit (5min wait + retry 1 success or 30min wait + retry 2).
2. Carousel slide reject dengan PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD: tier-1 Sonnet rewrite (strip persons only) berhasil 80%+ time. Tier-2 generic-stock catch the rest.
3. Operator buka Telegram, klik "Approve manual retry" button, draft transitions kembali ke pending_generation tanpa buka admin UI.
4. Audit trail: setiap retry visible di `pipeline_state_log` dengan reason `auto_retry_class_TRANSIENT`, dst.
5. Admin UI: NB2 error message muncul verbatim di slide error block (vs current "Last Error: ..." generic).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Eliminate the "manual click Regenerate" tax on LinkedIn + Content Engine pipelines and rescue carousel slides that hit NB2 policy with targeted, error-class-driven prompt rewrites — bounded per-record retry budget (max 2), full audit trail, operator-resolvable from Telegram inline buttons.

### Architecture Context (from CLAUDE.md)

**Reusable infra (must NOT reinvent):**
- [`App\Traits\HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) — generic FSM trait, already on `ContentIdea` + `LinkedInPost`
- [`App\Services\PipelineGuard::advance(Model, BackedEnum, reason, extra)`](backend/app/Services/PipelineGuard.php) — uniform logging
- [`App\Enums\LinkedInPostStatus`](backend/app/Enums/LinkedInPostStatus.php) + [`App\Enums\ContentIdeaStatus`](backend/app/Enums/ContentIdeaStatus.php)
- [`App\Jobs\DispatchTelegramNotification`](backend/app/Jobs/DispatchTelegramNotification.php) + `App\Services\TelegramNotificationService`
- [`App\Services\ImageGenerationService::isSafetyError`](backend/app/Services/ImageGenerationService.php) (current binary detection — wrap in new classifier)
- [`App\Services\ArticleGenerationService::rewriteVisualDirectionForSafety`](backend/app/Services/ArticleGenerationService.php) + `buildSafetyRewritePrompt` (extend with optional `errorClass` param)
- [`App\Services\LinkedInCarouselImageService::applySafetyRewriteIfNeeded`](backend/app/Services/LinkedInCarouselImageService.php) (extend with tier progression)
- `Setting::get/set` for telegram group additions; `firstOrCreate` seeder pattern (matches `TelegramSettingsSeeder`, `LinkedInSettingsSeeder`)

**Existing crons** (`routes/console.php`) — pattern to mirror: `Schedule::command(...)->everyTenMinutes()->withoutOverlapping(15)`.

**Vue admin views to extend:** [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue), [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue), [`ContentEngine.vue`](frontend/src/views/admin/ContentEngine.vue), [`AboutSettings.vue`](frontend/src/views/admin/AboutSettings.vue). All use TanStack Query via existing composables.

### Tech Stack

- Laravel 12 + PHP 8.2 + MySQL 8 (extend existing migrations pattern, idempotent seeders)
- PHPUnit (NOT Pest — project convention)
- Pure-function classifier = unit-testable without DB (avoid RefreshDatabase env block per CLAUDE.md)
- Vue 3.5 + Tailwind 4 (no new deps)
- Telegram Bot API v6+ (inline keyboard, callbackQuery, setWebhook) — already integrated for outbound; add inbound webhook
- HMAC-SHA256 truncated 12 chars for callback_data tamper protection (no new deps — `hash_hmac()` built-in)

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Error classification (all callers) | `string $error` | `PipelineErrorClassifier::classify()` | No | **Create new pure-function service** |
| Error class enum | n/a | `App\Enums\PipelineErrorClass` | No | **Create new BackedEnum** |
| LinkedIn retry candidate query | `linkedin_posts WHERE status=failed AND auto_retry_count<2` | new Eloquent query in cron | Table exists; **2 cols added via migration** | Migration creates cols |
| Content Idea retry candidate query | `content_ideas WHERE status=failed AND auto_retry_count<2` | new Eloquent query in cron | Table exists; **2 cols added via migration** | Migration creates cols |
| LinkedIn FSM transition | `LinkedInPostStatus::Failed → PendingGeneration` | `PipelineGuard::advance()` | Yes | Use existing |
| ContentIdea FSM transition | `ContentIdeaStatus::Failed → Researching` | `PipelineGuard::advance()` | Yes | Use existing |
| Re-dispatch LinkedIn gen | `GenerateLinkedInPost::dispatch($id)` | existing job | Yes | Use existing |
| Re-trigger article gen | `ArticleGenerationService::startResearch($idea)` | existing service | Yes | Use existing |
| Tier-1 Sonnet rewrite | `ArticleGenerationService::rewriteVisualDirectionForSafety()` | existing — **add optional `errorClass` param** | Yes | Extend signature (backward compat) |
| Tier-2 generic-stock template | `slide.layout_hint` + `slide.copy` | new `buildGenericStockPrompt()` in `LinkedInCarouselImageService` | No | **Create new private helper** |
| Tier indicator on slide | `carousel_slides[].image_rewrite_tier` | JSON field | Convention only | No DDL — just write/read JSON |
| Telegram notify (exhaustion) | `DispatchTelegramNotification::dispatch($key, $payload)` | existing job | Yes | Add 3 new event keys |
| Telegram inline buttons | `reply_markup.inline_keyboard` in payload | extend `TelegramNotificationService::sendMessage()` | Partial — **add reply_markup support** | Modify service |
| Telegram webhook inbound | `POST /api/automation/telegram/webhook` (public, HMAC verified) | new controller | No | **Create new endpoint** |
| Telegram callback action handler | dispatch retry/cancel via existing service methods | existing `LinkedInDraftController::regenerate/cancel`, `ContentIdeaController::reset` | Yes | Use existing |
| Telegram bot setWebhook | one-time CLI call | new artisan `telegram:set-webhook` | No | **Create new artisan** |
| 4 new telegram settings | `telegram_notify_*` keys + `telegram_webhook_secret` | extends `TelegramSettingsSeeder` (firstOrCreate) | Yes — pattern exists | Add 4 rows |
| Verbatim NB2 error in slide UI | `carousel_slides[i].image_error` | already in API resource | Yes | Render in [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) |
| Auto-retry chip in queue UI | `linkedin_posts.auto_retry_count` | exposed via existing show/list endpoints | New col → exposed via existing resource | Verify resource includes new fields |
| Auto-retry chip in Content Engine | `content_ideas.auto_retry_count` | exposed via existing list endpoint | New col → exposed via existing resource | Verify resource |

### Phases

| Phase | Code Deliverable | UI? | Verification |
|---|---|---|---|
| A | Classifier service + enum + unit tests | No | All test patterns pass |
| B | Migration + model fillable updates | No | `migrate` clean, factory works |
| C | LinkedIn retry cron + tests | No | Cron runs, advances FSM correctly |
| D | Content Idea retry cron + tests | No | Cron runs, advances FSM correctly |
| E | Carousel tier-1 per-class + tier-2 generic + tests | No | Per-class branch tested, tier progression correct |
| F | Telegram inline buttons + webhook + HMAC + artisan | No | HMAC roundtrip works, idempotent action handler |
| G | Admin UI chips + verbatim NB2 error | Yes | Vite build clean, smoke check on dev |

---

### Phase A: Pipeline Error Classifier (Foundation)

**Estimated time:** 25 min

**Files:**
- Create: `backend/app/Enums/PipelineErrorClass.php`
- Create: `backend/app/Services/PipelineErrorClassifier.php`
- Test: `backend/tests/Unit/PipelineErrorClassifierTest.php`

**Steps:**
1. **Write failing test for `PipelineErrorClassifier::classify()` returning `PipelineErrorClass::POLICY_PERSON` for input `"PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD: detected named individual"`. Expected error: `Error: Class "App\Services\PipelineErrorClassifier" not found`.**
2. Run `D:\xampp\php\php.exe artisan test --filter=PipelineErrorClassifierTest` — confirm fails with class-not-found.
3. Create `App\Enums\PipelineErrorClass` as `string`-backed enum with cases: `Transient`, `DeterministicLlm`, `PolicyPerson`, `PolicyMinor`, `PolicyNsfw`, `PolicyBrand`, `PolicyGeneric`, `Permanent`, `Unknown`.
4. Create `App\Services\PipelineErrorClassifier` with single `classify(?string $error): PipelineErrorClass` method. Implementation = ordered substring matching (priority: specific PUBLIC_ERROR codes first, free-text patterns next, network/timeout patterns next, fallback Unknown).
5. Run test — confirm POLICY_PERSON case passes.
6. Add ~12 more test cases covering each enum value: empty/null → Unknown, "ssh: connect to host: Connection timed out" → Transient, "Could not parse orchestrator JSON from stdout" → DeterministicLlm, "PUBLIC_ERROR_MINOR" → PolicyMinor, "PUBLIC_ERROR_UNSAFE" → PolicyNsfw, "validation rejected: depth score 65 below threshold 80" → Permanent, "logo of OpenAI detected" → PolicyBrand, etc.
7. Run all tests, confirm pass.
8. Commit: `feat(pipeline): add PipelineErrorClassifier service + enum`

**Verification:**
- [ ] `D:\xampp\php\php.exe -l backend/app/Services/PipelineErrorClassifier.php` clean
- [ ] `D:\xampp\php\php.exe -l backend/app/Enums/PipelineErrorClass.php` clean
- [ ] All ~13 unit test cases pass
- [ ] Each enum case has at least one passing test
- [ ] Pure function — no DB queries, no side effects, no `static` state
- [ ] No placeholder/TODO comments

---

### Phase B: Migration + Model Updates

**Estimated time:** 15 min

**Files:**
- Create: `backend/database/migrations/2026_05_07_000001_add_auto_retry_columns_to_linkedin_posts_and_content_ideas.php`
- Modify: `backend/app/Models/LinkedInPost.php` (add to `$fillable`)
- Modify: `backend/app/Models/ContentIdea.php` (add to `$fillable`)
- Test: `backend/tests/Unit/AutoRetryColumnsModelTest.php`

**Steps:**
1. **Write failing test asserting `LinkedInPost` factory can `make(['auto_retry_count' => 1, 'last_classified_error_class' => 'transient'])` and the model carries those attributes through to a `toArray()` output. Expected error: `Add [auto_retry_count] to fillable property`.**
2. Run test — confirm fails on fillable guard.
3. Create migration with two table-add blocks (linkedin_posts + content_ideas), each adding `auto_retry_count` TINYINT UNSIGNED DEFAULT 0 + `last_classified_error_class` VARCHAR(32) NULL. Down() drops both.
4. Add the two columns to `$fillable` on both models. No `$casts` change (tinyint = int by default, varchar = string).
5. Run `D:\xampp\php\php.exe artisan migrate` — confirm clean.
6. Run test — confirm pass.
7. Verify backward compat: existing `LinkedInPost::factory()->create()` and `ContentIdea::factory()->create()` still work without specifying the new fields (defaults to 0, null).
8. Commit: `feat(pipeline): add auto_retry_count + last_classified_error_class columns`

**Verification:**
- [ ] `D:\xampp\php\php.exe artisan migrate` clean (forward + rollback both work)
- [ ] Both new fields appear in `LinkedInPost->getFillable()` and `ContentIdea->getFillable()`
- [ ] Model test passes
- [ ] Factory smoke: `LinkedInPost::factory()->create()` succeeds with default 0/null
- [ ] No CHECK constraints / ENUMs on `last_classified_error_class` (string column matches enum value range — values fed via `PipelineErrorClass->value`, validated app-side)

---

### Phase C: LinkedIn FSM Bounded Retry Cron

**Estimated time:** 35 min

**Files:**
- Create: `backend/app/Console/Commands/RetryFailedLinkedInPosts.php`
- Modify: `backend/routes/console.php` (1 line schedule entry)
- Test: `backend/tests/Feature/RetryFailedLinkedInPostsCommandTest.php`

**Steps:**
1. **Write failing test asserting that running `linkedin:retry-failed` on a fixture `LinkedInPost{status='failed', last_error='SSH timeout connecting to localhost', auto_retry_count=0, updated_at=now()->subMinutes(10)}` advances FSM to `pending_generation`, increments `auto_retry_count` to 1, sets `last_classified_error_class='transient'`, and dispatches `GenerateLinkedInPost`. Expected error: `Command "linkedin:retry-failed" is not defined.`**
2. Run test — confirm fails on undefined command.
3. Create `RetryFailedLinkedInPosts` artisan command. Signature: `linkedin:retry-failed {--dry-run} {--limit=20}`. Constructor injects `PipelineErrorClassifier` + `PipelineGuard`.
4. Implementation: query failed rows with `auto_retry_count < 2`. For each: classify, compute backoff via class-aware logic (TRANSIENT: 5min then 30min for retry 1 then 2; DETERMINISTIC_LLM: 30min for retry 1, no retry 2). Skip POLICY_*/PERMANENT/UNKNOWN entirely. If still within backoff window (relative to `updated_at`), skip. Otherwise: increment `auto_retry_count`, set `last_classified_error_class`, transition Failed→PendingGeneration via `PipelineGuard::advance($draft, LinkedInPostStatus::PendingGeneration, 'auto_retry_class_'.$class->value)`, dispatch `GenerateLinkedInPost::dispatch($draft->id)`.
5. On exhaustion (retry_count was 2 before, fail again — caught next tick when row STILL `failed` with retry_count=2): dispatch `DispatchTelegramNotification::dispatch('linkedin_auto_retry_exhausted', ['draft_id' => $draft->id, ...])` ONCE (use idempotency flag in `pipeline_state_log` to avoid spam — check last entry's reason).
6. Add `Schedule::command('linkedin:retry-failed')->everyTenMinutes()->withoutOverlapping(15);` to `routes/console.php`.
7. Add test cases: TRANSIENT retry path (above), DETERMINISTIC_LLM retry path, POLICY_PERSON skip path (no retry), backoff-not-yet-elapsed skip path, exhaustion notify path, dry-run path.
8. Run all tests, confirm pass.
9. Commit: `feat(linkedin): add bounded auto-retry cron for failed drafts`

**Verification:**
- [ ] `php artisan linkedin:retry-failed --dry-run` runs without error on dev DB
- [ ] All ~6 test cases pass
- [ ] FSM transition uses `PipelineGuard::advance` (not raw `update`)
- [ ] Skip paths logged at INFO level with explicit reason (debuggable)
- [ ] Exhaustion telegram fires exactly ONCE per draft (idempotency check)
- [ ] No new code without test coverage

---

### Phase D: Content Engine FSM Bounded Retry Cron

**Estimated time:** 30 min

**Files:**
- Create: `backend/app/Console/Commands/RetryFailedContentIdeas.php`
- Modify: `backend/routes/console.php` (1 line)
- Test: `backend/tests/Feature/RetryFailedContentIdeasCommandTest.php`

**Steps:**
1. **Write failing test asserting `content:retry-failed` on `ContentIdea{status='failed', last_error='Sonnet output truncation', auto_retry_count=0, updated_at=now()->subMinutes(35)}` advances FSM to `researching`, increments `auto_retry_count` to 1, sets `last_classified_error_class='deterministic_llm'`. Expected error: `Command "content:retry-failed" is not defined.`**
2. Run — confirm fails.
3. Create `RetryFailedContentIdeas` mirror of LinkedIn cron. Re-trigger via `ArticleGenerationService::startResearch($idea)` (synchronous SSH dispatch — same path operator click takes). On dispatch failure, catch + log + leave row at `failed` (next tick will skip due to backoff window).
4. Add `Schedule::command('content:retry-failed')->everyTenMinutes()->withoutOverlapping(15);`.
5. Test cases mirror Phase C: TRANSIENT retry, DETERMINISTIC_LLM retry, POLICY_* skip, exhaustion notify (`idea_auto_retry_exhausted` event).
6. Commit: `feat(content-engine): add bounded auto-retry cron for failed ideas`

**Verification:**
- [ ] `php artisan content:retry-failed --dry-run` runs clean
- [ ] All test cases pass
- [ ] Re-trigger calls real `ArticleGenerationService::startResearch` (not mocked) in feature test (or assert dispatch happened via spy/mock if SSH would actually fire)
- [ ] FSM via PipelineGuard
- [ ] Idempotent telegram notify

---

### Phase E: Carousel Tier-1 Per-Class + Tier-2 Generic Fallback

**Estimated time:** 50 min

**Files:**
- Modify: `backend/app/Services/ImageGenerationService.php` (add `classifyError(?string $reason): PipelineErrorClass` method that wraps `PipelineErrorClassifier`; keep existing `isSafetyError()` as backward-compat boolean returning `match` against POLICY_* + DETERMINISTIC_LLM cases)
- Modify: `backend/app/Services/ArticleGenerationService.php` (extend `rewriteVisualDirectionForSafety` signature with `?PipelineErrorClass $errorClass = null` param; extend `buildSafetyRewritePrompt` to inject per-class branch instructions when class non-null)
- Modify: `backend/app/Services/LinkedInCarouselImageService.php` (extend `applySafetyRewriteIfNeeded` to use tier progression: read `slide['image_rewrite_tier']`, if 0 → tier-1 Sonnet with `errorClass`, if 1 → tier-2 generic-stock prompt, if 2 → mark `image_status='failed_permanent'`. Add private `buildGenericStockPrompt(string $layoutHint, string $copy): string` helper.)
- Test: `backend/tests/Unit/CarouselTierProgressionTest.php` + `backend/tests/Unit/PerClassSafetyPromptTest.php`

**Steps:**
1. **Write failing test asserting `ArticleGenerationService::buildSafetyRewritePrompt($vd, $reason, $context, PipelineErrorClass::PolicyBrand)` produces a prompt string containing both the input VD AND the literal phrase `"Strip ONLY brand names"`. Expected error: `ArgumentCountError: Too few arguments` or `TypeError` depending on how method signature was extended.**
2. Run — confirm fails on signature.
3. Extend `buildSafetyRewritePrompt` signature with optional `?PipelineErrorClass $errorClass = null`. Add a `match` block that returns class-specific instruction strings (5+ branches per design table). Default branch (null or unmatched) preserves current aggressive-strip behavior.
4. Test all 5 POLICY_* class branches inject the right instruction phrase.
5. Add `ImageGenerationService::classifyError` method (uses `PipelineErrorClassifier`). Keep `isSafetyError` returning `true` only for POLICY_* classes (preserves current call sites).
6. **Write failing test for `LinkedInCarouselImageService::applySafetyRewriteIfNeeded` tier progression:** given a fixture `LinkedInPost` with one `carousel_slides[]` entry at `{image_status:'failed', image_error:'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD', image_rewrite_tier:0}`, after method runs, slide should have `image_rewrite_tier=1` and re-dispatched. Expected error: assertion failure (tier_field not set).
7. Refactor `applySafetyRewriteIfNeeded` to read tier, branch on tier value. Tier 0 = call extended `rewriteVisualDirectionForSafety` with classified error class, write rewritten prompt, set tier=1, redispatch. Tier 1 = call new `buildGenericStockPrompt`, write fallback prompt, set tier=2, redispatch. Tier 2 = mark `image_status='failed_permanent'`, set `image_error="[Tier 2 fallback failed] {original_message}"`, dispatch `carousel_slide_tier2_failed` telegram notify.
8. Test tier progression: 0→1, 1→2, 2→failed_permanent.
9. Test `buildGenericStockPrompt` returns layout-aware base prompt (cover, cta, body each get distinct template).
10. Run all tests, confirm pass.
11. Commit: `feat(carousel): tier-1 per-class Sonnet + tier-2 generic-stock + tier-3 surface`

**Verification:**
- [ ] `buildSafetyRewritePrompt` backward-compat: existing callers passing 3 args (no errorClass) still work, default behavior unchanged
- [ ] All 5 POLICY_* branches tested with distinct expected substrings
- [ ] Tier progression test covers 0→1, 1→2, 2→permanent
- [ ] Generic-stock prompt is layout-aware (cover, cta, body = different templates)
- [ ] No Sonnet call in tier 2 path (in-process template only — verified by mock assertion that Sonnet exec method NOT called)
- [ ] Telegram `carousel_slide_tier2_failed` fires exactly when tier=2 fallback fails

---

### Phase F: Telegram Inline Buttons + Webhook + HMAC

**Estimated time:** 60 min

**Files:**
- Modify: `backend/app/Services/TelegramNotificationService.php` (add `reply_markup.inline_keyboard` per event; new private helper `buildInlineKeyboard(array $eventPayload): ?array`)
- Modify: `backend/database/seeders/TelegramSettingsSeeder.php` (add 4 new firstOrCreate rows: `telegram_notify_linkedin_auto_retry_exhausted`, `telegram_notify_idea_auto_retry_exhausted`, `telegram_notify_carousel_tier2_failed`, `telegram_webhook_secret`)
- Create: `backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php` (single-action `__invoke`, public route, verifies Telegram secret_token header + per-button HMAC)
- Create: `backend/app/Console/Commands/SetTelegramWebhook.php` (one-shot CLI: reads `telegram_bot_token` + `telegram_webhook_secret`, POSTs to `https://api.telegram.org/bot{token}/setWebhook` with `secret_token` and our endpoint URL)
- Modify: `backend/routes/api.php` (add `POST /api/automation/telegram/webhook`)
- Test: `backend/tests/Unit/TelegramHmacButtonTest.php` (HMAC sign/verify roundtrip) + `backend/tests/Feature/TelegramWebhookControllerTest.php` (HMAC reject, action dispatch, idempotency)

**Steps:**
1. **Write failing test asserting that `TelegramWebhookController` returns 200 OK on a synthetic Telegram callback_query payload with valid HMAC for `retry:linkedin:42:{hmac}` AND that the LinkedIn draft #42 (fixture `status=failed`) transitions to `pending_generation`. Expected error: `404 Not Found` (route undefined) or `500` (controller missing).**
2. Run — confirm fails on route or controller.
3. Add `telegram_webhook_secret` to `TelegramSettingsSeeder` with `Str::random(32)` default; run seeder; verify in DB. Add 3 notify-toggle rows.
4. Implement HMAC helper inside `TelegramNotificationService`: `signCallback(string $action, string $kind, int $id): string` returns `"$action:$kind:$id:".substr(hash_hmac('sha256', "$action:$kind:$id:".$secret, $secret), 0, 12)`. Inverse: `verifyCallback(string $callbackData): ?array` returns `['action','kind','id']` or null.
5. Extend `TelegramNotificationService::sendMessage` (or its underlying API call) to accept optional `reply_markup` array. Implement `buildInlineKeyboard($eventKey, $payload)` returning `[[ ['text' => 'Approve manual retry', 'callback_data' => signCallback('retry', $kind, $id)], ['text' => 'Cancel', 'callback_data' => signCallback('cancel', $kind, $id)] ], [ ['text' => 'Open in admin', 'url' => $adminUrl] ]]` for the 3 new event keys; null for other events.
6. Create `TelegramWebhookController::__invoke(Request $request)`. Step-by-step: (a) verify `X-Telegram-Bot-Api-Secret-Token` header matches `telegram_webhook_secret` setting (else 403); (b) extract `callback_query.data`; (c) `verifyCallback($data)` (else 200 OK no-op for security via timing-equality `hash_equals`); (d) idempotency: check current FSM state of the target record — if action already applied (e.g., row already `pending_generation`), return 200 OK no-op; (e) dispatch action: `retry` calls existing `LinkedInDraftController::regenerate` logic OR `ContentIdeaController::resetForRetry` OR new helper that wraps `PipelineGuard::advance` + redispatch; `cancel` advances to Cancelled; (f) return 200 OK + answer Telegram callback_query with confirmation toast.
7. Add HMAC verify unit tests: valid sign+verify roundtrip, tampered HMAC rejected, malformed format rejected.
8. Add feature tests: end-to-end webhook with valid payload mutates DB; missing secret_token returns 403; invalid HMAC silent-noop; idempotent action (call twice, only 1 transition logged in `pipeline_state_log`).
9. Create `SetTelegramWebhook` artisan command: signature `telegram:set-webhook {--unset}`. Reads bot token + secret from settings, builds URL `config('app.url')."/api/automation/telegram/webhook"`, POSTs to Telegram API with `{"url": ..., "secret_token": ...}`. `--unset` flag deletes webhook. Print result.
10. Add operator runbook entry: one-time `php artisan telegram:set-webhook` after deploy + verify `getWebhookInfo`.
11. Commit: `feat(telegram): inline buttons + webhook + HMAC for one-click retry/cancel`

**Verification:**
- [ ] HMAC sign+verify unit tests pass (5+ cases including tamper-rejection)
- [ ] Webhook returns 403 on missing/wrong `X-Telegram-Bot-Api-Secret-Token` header
- [ ] Webhook returns 200 + no-op on tampered HMAC (silent for security)
- [ ] Idempotent: calling webhook twice with same valid payload → only ONE FSM transition recorded
- [ ] `telegram:set-webhook` command runs without error (mock the HTTP call in test)
- [ ] All 4 new settings rows present after seeder run (idempotent on re-run)
- [ ] Backward compat: existing telegram notifications without `reply_markup` still work (no buttons attached)
- [ ] Notification service `buildInlineKeyboard` returns null for non-retry events (existing event keys unaffected)

---

### Phase G: Admin UI Chips + Verbatim NB2 Error

**Estimated time:** 45 min

**Files:**
- Modify: [`frontend/src/views/admin/LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) (slide error block: render `image_error` verbatim + add `image_rewrite_tier` chip + add `last_classified_error_class` hover-tooltip)
- Modify: [`frontend/src/views/admin/LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue) (Failed tab: chip "auto-retried Nx" when `auto_retry_count > 0`)
- Modify: [`frontend/src/views/admin/ContentEngine.vue`](frontend/src/views/admin/ContentEngine.vue) (Failed status filter: same chip)
- Modify: [`frontend/src/views/admin/AboutSettings.vue`](frontend/src/views/admin/AboutSettings.vue) (Telegram Notifications card: 3 new toggle rows + masked webhook secret display + "Re-set webhook" button)
- Modify: [`frontend/src/composables/useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js) (verify TanStack Query cache returns new fields — likely auto via wildcard resource)
- Verify: existing API resources (`LinkedInPost` show resource, `ContentIdea` index resource) include new fields

**Steps:**
1. **Write failing assertion: open dev frontend, navigate to LinkedIn Queue → Failed tab. Confirm chip "auto-retried 1x" does NOT yet render for any failed row (expected — UI not updated).** Document baseline in checklist.
2. Verify backend resources expose new fields. Open `backend/app/Http/Resources/LinkedInPostResource.php` (if exists) or controller's `show()`. Add `auto_retry_count` + `last_classified_error_class` if missing. Same for ContentIdea show endpoint.
3. Update `LinkedInDraftDetail.vue` slide error block: replace generic "Last Error: ..." with structured block: `<div>NB2 message: {{ slide.image_error }}</div><div>Class: {{ slide.last_classified_error_class || 'unclassified' }}</div><div>Tier: {{ slide.image_rewrite_tier || 0 }}</div>`. Color the tier chip (0=blue, 1=amber, 2=red).
4. Update `LinkedInQueueList.vue`: in Failed tab row template, add `<span v-if="row.auto_retry_count > 0" class="...">auto-retried {{ row.auto_retry_count }}x</span>`. Color: emerald if `last_classified_error_class === 'transient'` (likely auto-recoverable), amber if deterministic_llm.
5. Update `ContentEngine.vue` table similarly for Failed-filter rows.
6. Update `AboutSettings.vue` Telegram card: add 3 toggle rows for new notify keys (mirror existing toggle pattern from `telegram_notify_publish_success`). Add masked webhook secret display (`***SET***` if configured else "Not configured"). Add "Re-set webhook" button → calls new admin endpoint `POST /api/admin/settings/telegram/set-webhook` (which itself calls `Artisan::call('telegram:set-webhook')`).
7. Run `npm run build` — confirm clean (~10s).
8. Smoke test on dev: load admin LinkedIn Queue, expect chips render correctly when DB has fixture data with `auto_retry_count=2`. Load LinkedIn Draft Detail with a failed slide having all new fields populated — expect verbatim error block.
9. Commit: `feat(admin-ui): expose auto-retry counter + verbatim NB2 error + tier chip`

**Verification:**
- [ ] `npm run build` exits 0 with no warnings beyond baseline
- [ ] LinkedInDraftDetail.vue: failed slide renders verbatim NB2 message in dedicated block (not inside a generic "Last Error" string)
- [ ] LinkedInQueueList.vue: chip "auto-retried Nx" visible on Failed tab when `auto_retry_count > 0`
- [ ] ContentEngine.vue: same chip visible on failed ideas
- [ ] AboutSettings.vue: 3 new toggle rows present + masked secret + Re-set webhook button functional (sends API call)
- [ ] Color coding correct: tier 0 blue, tier 1 amber, tier 2 red; transient chip emerald, deterministic chip amber
- [ ] No new console errors / warnings on page load
- [ ] Manual smoke: toggle a notify setting, refresh page, value persists (idempotency)

---

### Cross-Phase Verification (run after Phase G)

**Backend tests:** `D:\xampp\php\php.exe artisan test --testsuite=Unit` AND `--testsuite=Feature` — all pass (note: RefreshDatabase tests blocked locally per CLAUDE.md; Unit suite + non-RefreshDatabase Feature tests must pass).

**End-to-end smoke (manual on dev or staging):**
1. Create a fixture `LinkedInPost` with `status='failed', last_error='ssh: Connection timed out'`. Wait/force `linkedin:retry-failed`. Confirm row advances to `pending_generation`, `auto_retry_count=1`, `last_classified_error_class='transient'`.
2. Trigger a fixture carousel slide failure with `image_error='PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'`. Run `applySafetyRewriteIfNeeded`. Confirm tier=1 + redispatch. Force second failure. Confirm tier=2 + generic-stock prompt + redispatch. Force third failure. Confirm tier=2 → `failed_permanent` + telegram notify fires.
3. Send a synthetic Telegram callback_query payload with valid HMAC to webhook. Confirm action dispatches.
4. Open admin UI on Failed tab — chips visible.

**Documentation:**
- [ ] Update root `CLAUDE.md` "Last Updated" entry with summary line
- [ ] Add operator runbook for one-time `telegram:set-webhook` call
- [ ] Document new env vars (none — all settings via DB)

### Dependencies & Sequencing

- Phase A → independent (foundation)
- Phase B → depends on A (uses enum value for column data)
- Phase C → depends on A + B
- Phase D → depends on A + B (parallel-safe with C)
- Phase E → depends on A only (independent of B/C/D)
- Phase F → depends on A only (uses enum for event key naming)
- Phase G → depends on B + C + D + E + F (UI surfaces backend state from all)

**Parallelizable groups via `gaspol-parallel`:** {A} → {B, E, F} (B, E, F independent of each other) → {C, D} → {G}. Or sequentially A→B→C→D→E→F→G.

### Out of Scope (explicit)

- Auto-retry for POLICY_* errors (operator decision required)
- Cost cap setting (deferred to Phase 2 if runaway observed)
- Telegram inline buttons for non-retry/cancel actions (e.g., "approve carousel tier-2 prompt" — not part of this design)
- Multi-platform publish (IG/TikTok) — separate brainstorm scope (#3)
- Auto-schedule peak-hour publishing for `manual_review` — separate brainstorm scope (#2)

### Estimated Total

7 phases × ~30-60 min = **4-5 hours** of focused implementation. Plus Vite build + smoke test = ~5-6 hours end-to-end. ~12-15 files (8 new, 7 modified) + 1 migration + 4 settings rows + 2 new routes + 2 new artisan commands + 3 new event keys.
