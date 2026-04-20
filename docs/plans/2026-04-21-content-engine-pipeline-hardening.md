# Content Engine Pipeline Hardening

**Date:** 2026-04-21
**Status:** Design — ready for `gaspol-plan`
**Author:** Ali Sadikin + Claude
**Problem scope:** Fix 4 architectural gaps in the Content Engine pipeline: entity refresh on regenerate, per-segment retry state, translate-before-publish gating in cron auto-pipeline, and formal state machine enforcement.

**Related prior work:** [2026-04-20-named-entity-aware-cover-generation.md](2026-04-20-named-entity-aware-cover-generation.md) — ships the `entity_refs[]` + `awaiting_manual_upload` status + `DispatchTelegramNotification` infrastructure this design extends.

---

## Design

### Section 1 — Core Architecture

Four surgical interventions wrapped in shared infrastructure. No new external dependencies.

**Problem summary:**

| # | Concern | Current behavior | Gap |
|---|---|---|---|
| 1 | Entity detection on per-segment regenerate/retry | `regenerateImagePrompts()` re-runs plugin NER. `triggerForIdea()` reuses `entity_refs[]`. | No dedicated "retry image" endpoint exists — users retry by full regenerate (SSH cost). Also no auto-refresh when VD mutated externally. |
| 2 | Segment-level retry | `syncToContentIdea` advances when all segments `done\|failed`. No retry cap, no manual skip, no Telegram on exhaustion. | 3 failed segments block idea indefinitely (no `skipped` state). One flaky GeminiGen response = stuck idea. |
| 3 | Translation gating in cron | `publishReady()` fires on `status='completed'`. Post created with `translation_pending=true`. `ProcessPendingTranslations` retries async. | Cron-published posts go live monolingual. `ARTICLE_GEN_USE_TRANSLATE_PHASE` flag exists but not wired into orchestrator pre-publish step. |
| 4 | State machine formalization | `resumeIdea()` branches on `generated_article` shape; `update(['status' => ...])` called directly. | No formal current→next transition validator. Malformed states (completed + empty article) can leak through. |

**New primitives introduced:**

1. `App\Enums\ContentIdeaStatus` — 9-value enum (draft, researching, article_ready, awaiting_manual_upload, generating_images, images_ready, completed, failed, archived) with static `TRANSITIONS` map.
2. `App\Traits\HasStatusTransitions` — `transitionTo($next)` method; throws `InvalidStateTransitionException` on illegal jump.
3. `App\Services\PipelineGuard` — single entry point for cron→status changes; wraps `transitionTo` + emits structured log into new `pipeline_state_log` JSON column (FIFO 20-entry ring buffer).
4. `image_prompts[i]` JSON extensions: `retry_count`, `failure_history[]`, `terminal_at`, new `status='skipped'`. No migration required — JSON-column extension.
5. New DB columns on `content_ideas`: `pipeline_state_log` (JSON), `translation_attempts_auto` (int default 0), `translation_ready_at` (timestamp nullable).
6. `TelegramNotificationService` extended with `sendSegmentRetryExhausted(ContentIdea, int $segmentIdx)` + `sendAutoTranslateExhausted(ContentIdea)`. 2 new `settings` rows (`telegram_notify_segment_failed`, `telegram_notify_translate_failed`, both default `true`).

**Architecture diagram:**

```
┌────────── Admin UI / Cron Orchestrator ──────────┐
│                                                  │
│  Any status mutation goes through:               │
│                                                  │
│   ┌──────────────────────────────────┐           │
│   │   PipelineGuard::advance()       │           │
│   │     ├→ $idea->transitionTo()     │           │
│   │     ├→ append pipeline_state_log │           │
│   │     └→ structured Log::info      │           │
│   └──────────┬───────────────────────┘           │
│              │                                    │
│       (throws InvalidStateTransitionException     │
│        on illegal jump, HTTP 409)                 │
└──────────────┼────────────────────────────────────┘
               │
      ┌────────┴────────┐
      │                 │
┌─────▼──────┐   ┌──────▼──────────────────────┐
│  Segment   │   │  AutoPipelineOrchestrator   │
│  Retry FSM │   │   publishReady() gate:      │
│            │   │   ensureTranslationBefore   │
│  3 attempts│   │   Publish() preflight       │
│  ↓         │   │                             │
│  Telegram  │   │   (sync ~60s SSH if needed) │
│  exhausted │   └─────────────────────────────┘
└────────────┘
```

### Section 2 — Concern Implementations

#### Concern #1 — Entity refresh on regenerate (plugin path) + retry fast-path

Decision: **Plugin-only NER. Retry reuses cached `entity_refs[]`.** (No backend NER port.)

**Existing regenerate path** (`POST /admin/content-engine/ideas/{id}/regenerate-image-prompts`) already calls plugin `/article-images` which runs full NER + Wikidata. No backend change for regenerate.

**New retry path** — `POST /admin/content-engine/ideas/{id}/retry-segment/{i}`:
1. Validate segment index exists + has `status IN ('failed', 'generating')`.
2. `ImageGenerationService::retrySegment(ContentIdea, int $i)` — filtered `triggerForIdea` equivalent for one segment:
   - Increment `image_prompts[i].retry_count`
   - Reset `image_prompts[i].variations[]` (clear stale UUIDs)
   - Reuse existing `entity_refs[]`, `face_refs[]`, `brand_refs[]` as-is
   - Re-queue to GeminiGen with branded filename `{slug}-cover.png` (or `-body-N.png` for inline)
   - Set `image_prompts[i].status='generating'`
3. Return new `job_uuid` for frontend polling.

**Guardrail — VD/entity mismatch detection after regenerate:**

When `/article-images` completes with fresh `entity_refs[]`, inline helper `App\Services\EntityVdCoherenceChecker::check(array $prompt): bool`:
- If `entity_refs[]` contains `entity_type='person'` AND `entity_name` NOT in `visual_direction` (case-insensitive substring) → returns `false`.
- Backend auto-triggers existing `rewriteVD` sync Sonnet call to fix.
- Idempotent: if VD already names the person, skip.

**Frontend changes** (`ImageGeneration.vue`):
- Per-segment "Retry" button → calls `retrySegment()` (fast, no SSH)
- "Regenerate Prompt" button (existing) → calls `regenerateImagePrompts()` with `sections:[i]` (slow, plugin path, refreshes entity_refs)
- Tooltip copy disambiguates: "Retry: same prompt, new generation attempt" vs "Regenerate: rewrite prompt from article subject"

#### Concern #2 — Segment-level retry state machine

**Schema additions** to `image_prompts[i]` (JSON column, no migration):

```
{
  ...existing fields (prompt_text, face_refs, entity_refs, variations, etc.)...
  status: 'pending' | 'generating' | 'done' | 'failed' | 'skipped'
  retry_count: int (0..3)
  failure_history: [
    { attempt: int, timestamp: iso8601, reason: string, uuid: string }
  ]
  terminal_at: iso8601 | null   // set when retry_count >= MAX or manually skipped
}
```

Constant: `ImageGenerationService::MAX_SEGMENT_ATTEMPTS = 3` (mirrors orchestrator idea-level).

**Flow:**

1. **On GeminiGen failure** (via `ProcessPendingImages::syncToContentIdea` or webhook):
   - Bump `image_prompts[i].retry_count`
   - Append to `failure_history[]`
   - If `retry_count < MAX` AND `idea.auto_mode=true`:
     - Keep `status='failed'` but leave `terminal_at=null`
     - Dispatch `RetryImageSegmentJob` queued with 60s delay → calls `retrySegment(idea, i)`
   - If `retry_count >= MAX`:
     - Set `status='failed'`, `terminal_at=now()`
     - `dispatch(new DispatchTelegramNotification($idea, 'segment_retry_exhausted', ['segment_idx' => $i]))`

2. **Manual skip** — `POST /admin/content-engine/ideas/{id}/skip-segment/{i}`:
   - Validate segment exists + `status != 'done'`
   - Set `status='skipped'`, `terminal_at=now()`
   - Append `{attempt: retry_count, timestamp, reason: 'user_skip', uuid: null}` to `failure_history[]`
   - No Telegram (explicit user action).

3. **Advancement rule** (updated `ProcessPendingImages::syncToContentIdea`):
   ```php
   $allResolved = collect($prompts)->every(fn ($p) =>
       in_array($p['status'] ?? '', ['done', 'skipped'])
       || ($p['status'] === 'failed' && !empty($p['terminal_at']))
   );
   $anyDone = collect($prompts)->contains(fn ($p) => ($p['status'] ?? '') === 'done');
   $coverSegment = $prompts[0] ?? null;
   $coverCritical = $coverSegment && in_array($coverSegment['status'] ?? '', ['failed', 'skipped'])
       && !empty($coverSegment['terminal_at']);

   if ($allResolved && $anyDone && !$coverCritical) {
       // Advance to images_ready (manual) or completed (auto_mode)
   } elseif ($coverCritical) {
       // Block advance — cover is critical. Telegram alert + leave in generating_images.
       // User must retry or upload cover manually.
   }
   ```

4. **Cover-critical guard** — if segment index 0 (cover) is `failed` or `skipped` with `terminal_at` set:
   - Block idea advancement (stays at `generating_images`)
   - Dispatch `DispatchTelegramNotification` with `cover_critical` type (new)
   - Idea enters "needs manual attention" mode — user can retry, skip to manual upload, or abandon

#### Concern #3 — Translate-before-publish in cron

New `AutoPipelineOrchestrator::ensureTranslationBeforePublish(ContentIdea $idea): bool` — returns `true` when idea is ready to publish (translation done or waived), `false` when still working.

Called at the top of `publishReady()`:

```php
private function publishReady(): ?ContentIdea
{
    $idea = ContentIdea::where('auto_mode', true)
        ->where('status', 'completed')
        ->whereNull('result_post_id')
        ->orderBy('updated_at')
        ->first();

    if (!$idea) return null;
    if (Post::where('source_idea_id', $idea->id)->exists()) return null;

    // NEW: Translate-first gate
    if (!$this->ensureTranslationBeforePublish($idea)) {
        return null; // still working, try again next tick
    }

    // ...existing publish logic...
}
```

**`ensureTranslationBeforePublish()` logic:**

```php
private function ensureTranslationBeforePublish(ContentIdea $idea): bool
{
    // Backward compat: if flag off, no gating
    if (!config('content.use_translate_phase', false)) {
        return true;
    }

    $article = $idea->generated_article ?? [];
    $primaryLang = $article['language'] ?? 'id';
    $targetLang = $primaryLang === 'id' ? 'en' : 'id';

    // Already translated? proceed
    if (!empty($article[$targetLang]['content'] ?? '')) {
        $idea->update(['translation_ready_at' => $idea->translation_ready_at ?? now()]);
        return true;
    }

    // Exhausted? graceful degrade — publish monolingual + alert
    $attempts = (int) ($idea->translation_attempts_auto ?? 0);
    if ($attempts >= 3) {
        Log::warning("[AutoPipeline] Idea #{$idea->id} exhausted auto-translate retries, publishing monolingual");
        dispatch(new DispatchTelegramNotification($idea, 'auto_translate_exhausted'));
        $idea->update(['translation_ready_at' => now()]); // sentinel: we tried, giving up
        return true;
    }

    // Try translate (sync SSH ~60s)
    try {
        $result = $this->articleGen->triggerTranslatePreflight($idea);
        $idea->increment('translation_attempts_auto');
        $idea->update(['pipeline_last_attempt_at' => now()]);

        if ($result['success']) {
            Log::info("[AutoPipeline] Idea #{$idea->id} auto-translated (attempt {$idea->translation_attempts_auto})");
            return true;
        }

        Log::warning("[AutoPipeline] Auto-translate failed for idea #{$idea->id}: " . ($result['error'] ?? 'unknown'));
        return false; // retry on next tick
    } catch (\Throwable $e) {
        Log::error("[AutoPipeline] Auto-translate threw for idea #{$idea->id}: " . $e->getMessage());
        $idea->increment('translation_attempts_auto');
        return false;
    }
}
```

**New service method** `ArticleGenerationService::triggerTranslatePreflight(ContentIdea): array`:
- Sync SSH invocation (similar to `rewriteVisualDirectionForFace` pattern)
- Calls `claude -p "/article-translate ..." --model sonnet --append-system-prompt-file {refs-translate.md}`
- Writes result into `idea.generated_article.{en|id}.{title, content, excerpt, meta_title, meta_description, ai_summary, schema_markup, faq_schema}`
- Returns `['success' => bool, 'error' => ?string]`
- Gated by `ARTICLE_GEN_USE_TRANSLATE_PHASE=true` at service level (second safety belt)

**Post-publish flow** — unchanged. `ContentPublishService::publish` already detects `$hasRealTranslation` and calls `upsertSecondaryTranslation()`, so when the en content IS populated at publish time, the post goes live bilingual in one shot with `translation_pending=false`.

**Manual UI path** — unchanged. Already blocks Finalize until translation ready.

**Graceful degrade** — on exhaustion, post still goes live monolingual. This is intentional: blocking publish entirely on translation failure would create permanently stuck ideas. Operator gets Telegram alert + can manually retry translation via existing admin endpoint.

#### Concern #4 — State machine formalization

**`App\Enums\ContentIdeaStatus`:**

```php
<?php

namespace App\Enums;

enum ContentIdeaStatus: string
{
    case Draft = 'draft';
    case Researching = 'researching';
    case ArticleReady = 'article_ready';
    case AwaitingManualUpload = 'awaiting_manual_upload';
    case GeneratingImages = 'generating_images';
    case ImagesReady = 'images_ready';
    case Completed = 'completed';
    case Failed = 'failed';
    case Archived = 'archived';

    public const TRANSITIONS = [
        'draft' => ['researching', 'archived'],
        'researching' => ['article_ready', 'failed', 'awaiting_manual_upload'],
        'awaiting_manual_upload' => ['generating_images', 'failed', 'archived'],
        'article_ready' => ['generating_images', 'failed', 'archived'],
        'generating_images' => ['images_ready', 'completed', 'failed'],
        'images_ready' => ['completed', 'archived'],
        'completed' => ['archived'],
        'failed' => ['researching', 'article_ready', 'generating_images', 'archived'], // resume paths
        'archived' => ['draft'], // restore
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }
}
```

**`App\Traits\HasStatusTransitions`:**

```php
<?php

namespace App\Traits;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;

trait HasStatusTransitions
{
    public function transitionTo(ContentIdeaStatus|string $next, ?string $reason = null): self
    {
        $nextEnum = is_string($next) ? ContentIdeaStatus::from($next) : $next;
        $currentEnum = ContentIdeaStatus::from($this->status);

        if (!$currentEnum->canTransitionTo($nextEnum)) {
            throw new InvalidStateTransitionException(
                "Cannot transition {$this->status} → {$nextEnum->value} on idea #{$this->id}"
            );
        }

        // Append to ring buffer (FIFO 20)
        $log = $this->pipeline_state_log ?? [];
        $log[] = [
            'from' => $this->status,
            'to' => $nextEnum->value,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
        ];
        if (count($log) > 20) {
            $log = array_slice($log, -20);
        }

        $this->update([
            'status' => $nextEnum->value,
            'pipeline_state_log' => $log,
        ]);

        return $this;
    }
}
```

**`App\Services\PipelineGuard`** — thin wrapper for cron paths:

```php
public function advance(ContentIdea $idea, ContentIdeaStatus $next, string $reason, array $context = []): ContentIdea
{
    try {
        $idea->transitionTo($next, $reason);
        Log::info("[PipelineGuard] idea #{$idea->id} {$reason}: {$idea->getOriginal('status')} → {$next->value}", $context);
        return $idea;
    } catch (InvalidStateTransitionException $e) {
        Log::error("[PipelineGuard] illegal transition on idea #{$idea->id}: " . $e->getMessage(), $context);
        throw $e;
    }
}
```

**Migration callsites** (exhaustive list — replaces every `$idea->update(['status' => ...])` with `$idea->transitionTo(...)` or `$pipelineGuard->advance(...)`):

- `AutoPipelineOrchestrator.php` — 8 callsites (startNextDraft, resumeIdea paths, dispatchImagesForReady, publishReady, markFailed)
- `ContentIdeaController.php` — 12+ callsites (approveArticle, generateImages, approveAndPublish, archive, restore, revertToDraft, etc.)
- `ProcessPendingImages.php::syncToContentIdea` — 2 callsites (generating_images → images_ready OR completed)
- `ContentPublishService.php` — 1 callsite (completed)

HTTP 409 on `InvalidStateTransitionException` via `app/Exceptions/Handler.php` mapping.

### Section 3 — Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| State enum + transitions | `App\Enums\ContentIdeaStatus` | NEW | Static PHP enum + constant map |
| `HasStatusTransitions` trait | `App\Traits\HasStatusTransitions` | NEW | `transitionTo()` method |
| `InvalidStateTransitionException` | `App\Exceptions\...` | NEW | Maps to HTTP 409 |
| `PipelineGuard` service | `App\Services\PipelineGuard` | NEW | Wraps trait + logging for cron |
| Transition log ring buffer | `content_ideas.pipeline_state_log` | NEW JSON column | FIFO 20 entries |
| Per-segment retry state | `content_ideas.generated_article.image_prompts[i].{retry_count,failure_history,terminal_at}` | NEW JSON fields | No migration |
| Segment 'skipped' status | `image_prompts[i].status` | Extended | Add `'skipped'` value |
| Translate auto attempts | `content_ideas.translation_attempts_auto` | NEW int column | Mirror of `posts.translation_attempts` |
| Translation ready timestamp | `content_ideas.translation_ready_at` | NEW timestamp | Set after en content present |
| Retry-segment endpoint | `POST /admin/content-engine/ideas/{id}/retry-segment/{i}` | NEW | Admin-only, auth:sanctum |
| Skip-segment endpoint | `POST /admin/content-engine/ideas/{id}/skip-segment/{i}` | NEW | Admin-only, auth:sanctum |
| Retry image segment job | `App\Jobs\RetryImageSegmentJob` | NEW queued job | 60s delay, calls retrySegment |
| Telegram segment alert | `TelegramNotificationService::sendSegmentRetryExhausted` | NEW method | Extends existing service |
| Telegram translate alert | `TelegramNotificationService::sendAutoTranslateExhausted` | NEW method | Extends existing service |
| Telegram cover-critical alert | `TelegramNotificationService::sendCoverCriticalAlert` | NEW method | When cover segment hits terminal state |
| New settings keys | `settings` group='telegram' (3 extra rows) | Extension | Idempotent seeder patch |
| Pre-publish translate | `ArticleGenerationService::triggerTranslatePreflight` | NEW method | Sync variant (~60s SSH) |
| Cron translate gate | `AutoPipelineOrchestrator::ensureTranslationBeforePublish` | NEW | Called from publishReady() |
| Entity/VD coherence check | `App\Services\EntityVdCoherenceChecker` | NEW | Post-regenerate guard |
| `ImageGenerationService::retrySegment` | Filtered single-segment retry | NEW method | Reuses existing `triggerForIdea` primitives |
| Admin UI retry + skip | `ImageGeneration.vue` per-segment buttons | Extension | New handlers |
| useContentEngine composable | `retrySegment()` + `skipSegment()` | Extension | 2 new methods |
| Settings UI toggles | `AboutSettings.vue` Telegram card | Extension | 3 new rows |

### Section 4 — Affected Files

**Backend (Laravel 12):**

*New files:*
- `app/Enums/ContentIdeaStatus.php`
- `app/Traits/HasStatusTransitions.php`
- `app/Exceptions/InvalidStateTransitionException.php`
- `app/Services/PipelineGuard.php`
- `app/Services/EntityVdCoherenceChecker.php`
- `app/Jobs/RetryImageSegmentJob.php`
- `database/migrations/2026_04_21_000001_add_state_machine_fields_to_content_ideas.php`
- `tests/Feature/ContentIdeaStatusTransitionsTest.php`
- `tests/Feature/SegmentRetryMachineTest.php`
- `tests/Feature/AutoPipelineTranslateGateTest.php`
- `tests/Feature/RetrySegmentEndpointTest.php`
- `tests/Feature/SkipSegmentEndpointTest.php`
- `tests/Feature/EntityVdCoherenceCheckerTest.php`

*Modified files:*
- `app/Models/ContentIdea.php` — use `HasStatusTransitions`, cast `pipeline_state_log` (array) + `translation_ready_at` (datetime)
- `app/Services/AutoPipelineOrchestrator.php` — add `ensureTranslationBeforePublish()`, route all status mutations through `PipelineGuard::advance()`
- `app/Services/ImageGenerationService.php` — add `retrySegment()`, `MAX_SEGMENT_ATTEMPTS` const, segment-retry state updates in webhook flow
- `app/Services/ArticleGenerationService.php` — add `triggerTranslatePreflight()`
- `app/Services/TelegramNotificationService.php` — add 3 new methods
- `app/Services/ContentPublishService.php` — route `completed` status via `transitionTo()`
- `app/Console/Commands/ProcessPendingImages.php` — update `syncToContentIdea` advance rule (include `skipped`, `terminal_at` check, cover-critical block), bump retry counters, dispatch RetryImageSegmentJob
- `app/Http/Controllers/Api/Admin/ContentIdeaController.php` — add `retrySegment()`, `skipSegment()` methods; migrate all status mutations to `transitionTo()`
- `app/Exceptions/Handler.php` — map `InvalidStateTransitionException` to HTTP 409
- `database/seeders/TelegramSettingsSeeder.php` — add 3 new notify toggle rows (idempotent upsert)
- `routes/api.php` — 2 new admin routes

**Frontend (Vue 3):**

*Modified files:*
- `src/components/admin/ImageGeneration.vue` — per-segment Retry + Skip buttons, `retry_count` badge, `failure_history` tooltip on hover
- `src/composables/useContentEngine.js` — add `retrySegment(ideaId, segmentIdx)` + `skipSegment(ideaId, segmentIdx)` methods
- `src/views/admin/AboutSettings.vue` — 3 new toggle rows in Telegram card (segment_failed, translate_failed, cover_critical)
- `src/stores/settings.js` — extend telegram settings shape

### Section 5 — Rollout Phases

Each phase is independently deployable + verifiable. Ship in order — later phases depend on earlier.

**Phase A: State Machine Foundation** (low risk, no behavior change)
- Enum + trait + migration + exception + PipelineGuard service
- `HasStatusTransitions` added to `ContentIdea` but orchestrator still uses `update()` → fully backward-compatible
- Tests verify transition validation works
- **Deploy trigger:** all tests green + manual smoke test of `$idea->transitionTo()` in tinker
- **Rollback:** drop new columns, revert trait usage

**Phase B: Segment Retry State Machine** (medium risk, isolated to image layer)
- `image_prompts[i]` JSON extensions
- `retry-segment` + `skip-segment` endpoints
- Frontend buttons
- `RetryImageSegmentJob` queued auto-retry
- Telegram alert on exhaustion + cover-critical
- Updated `syncToContentIdea` advance rule
- **Deploy trigger:** all tests green + QA: manually fail 3 test segments, verify retry → exhaustion flow
- **Rollback:** frontend revert + advance rule revert (retry state is additive, safe to leave in DB)

**Phase C: Translate-Before-Publish Gate** (medium risk, gated by existing env flag)
- `triggerTranslatePreflight()` in ArticleGenerationService
- `ensureTranslationBeforePublish()` in orchestrator
- `translation_attempts_auto` + `translation_ready_at` columns populated
- Telegram alert on exhaustion
- Still gated by `ARTICLE_GEN_USE_TRANSLATE_PHASE=false` default → no runtime behavior change until explicitly enabled
- **Deploy trigger:** Phase B green + manual QA in staging with flag=true
- **Rollback:** flag off = instant rollback

**Phase D: State Machine Enforcement** (highest blast radius, ship last)
- Replace every `$idea->update(['status' => ...])` with `$idea->transitionTo(...)` or `PipelineGuard::advance()`
- ~25+ callsites across orchestrator + controller + publish service + process-pending-images
- HTTP 409 handler mapping
- **Deploy trigger:** Phase A/B/C green + regression test suite (all existing flows still pass)
- **Rollback:** revert commit (transitions allowed by map are superset of current flows, so rollback only matters if an illegal transition was legitimately needed somewhere — should never happen if map is right)

### Section 6 — Feasibility Check

**Real data availability:**
- ✅ All status mutations already exist in code — formalization only
- ✅ `generated_article.image_prompts[]` already has flexible JSON schema (`variations[]` added post-v1)
- ✅ Telegram infra + `DispatchTelegramNotification` job already shipped (named-entity feature)
- ✅ `ARTICLE_GEN_USE_TRANSLATE_PHASE` env flag + `refs-translate.md` compiled references already on VPS
- ✅ `rewriteVisualDirectionForFace` sync SSH pattern exists — reuse for `triggerTranslatePreflight`

**No placeholder implementations needed.** Every backend service method has a concrete existing counterpart to extend. Every frontend handler maps to an existing Pinia store/composable pattern.

**Risk areas:**
1. **Phase D regression surface** — 25+ callsites. Mitigation: comprehensive test suite runs before Phase D ships. Trait method is drop-in compatible (still calls `->update()` under the hood).
2. **Segment retry job flood** — if many ideas fail simultaneously, 60s-delayed queue could stack. Mitigation: use single worker for `default` queue. Per-idea retry cap prevents runaway.
3. **Pre-publish translate latency** — each sync SSH call adds ~60s to publish path. Mitigation: gated by env flag, operator can toggle off if publish throughput matters more than bilingual coverage.

---

## Open Questions for Implementation Phase

These are resolvable at plan or execute time — no blocker for design approval:

1. **Telegram chat for segment alerts** — same chat as manifest/publish (single `telegram_chat_id`) or separate operator chat? → Default: single chat, all alerts through existing `telegram_chat_id`.
2. **Segment retry UUID mapping** — if a retry creates new `variations[]` entry, how does `failure_history[]` correlate old vs new UUIDs? → Each history entry includes UUID of the attempt; variations ring reset on retry clears stale slots.
3. **`pipeline_state_log` retention** — FIFO 20 entries may be too few for debugging long-running ideas. → Start with 20, revisit if ops complain.

---

## Next Step

Hand off to `/gaspol-dev:gaspol-plan` to append detailed Implementation Plan section with per-phase checkpoints, Data Integration Maps per phase, per-file change diffs, and verification criteria.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship 4 architectural fixes to the Content Engine pipeline (entity refresh, segment retry, translate-before-publish, state machine) as 4 independently-deployable phases. Zero backward-compat breakage for manual admin flow. Cron auto-pipeline behavior changes only behind existing env flag (`ARTICLE_GEN_USE_TRANSLATE_PHASE`) until Phase C ships, then becomes default-safe. Phase D enforces FSM transitions — highest blast radius, shipped last with full regression coverage.

### Architecture Context

From [CLAUDE.md](../../CLAUDE.md):
- `ContentIdea` model uses `array` casts for JSON fields; status is string column (no enum yet) with 7 active values.
- `DispatchTelegramNotification` job exists (3 tries, 30s/2min/5min backoff) — dispatcher pattern uses `match ($this->notificationType)`; new types add match arms.
- `TelegramNotificationService::isEnabledFor($type)` reads `settings` table `telegram_notify_{type}` key — existing pattern.
- `ArticleGenerationService::translateArticle(array $source): array` already exists (sync SSH, ~30-60s) — returns `['success', 'translated', 'error']` with `translated` dict of `{title, content, excerpt, meta_title, meta_description, og_title, og_description, ai_summary}`.
- `ArticleGenerationService::rewriteVisualDirectionForFace` is the sync SSH template pattern (~10-20s).
- `config('services.article_generation.use_translate_phase')` already wired from `ARTICLE_GEN_USE_TRANSLATE_PHASE` env var. `ContentPublishService` line 345 already checks this flag for post-publish translate path.
- `ProcessPendingImages::syncToContentIdea` is the webhook/polling sync point — currently advances on `every($p => in_array($p['status'], ['done', 'failed']))`.
- `AutoPipelineOrchestrator` has `MAX_ATTEMPTS=3` + `RETRY_DELAY_MINUTES=5` at idea level — mirror this for segment level.
- 25+ `$idea->update(['status' => ...])` callsites across: `AutoPipelineOrchestrator` (8), `ContentIdeaController` (12+), `ImageGenerationService` (2), `ProcessPendingImages` (2), `ContentPublishService` (1).

### Tech Stack

- Laravel 12 + PHP 8.2 (Enums native since PHP 8.1, traits, queued jobs)
- MySQL 8 JSON columns for `pipeline_state_log`
- Pest PHP test framework (existing test suites in `backend/tests/Feature/`)
- Vue 3 Composition API + Pinia for frontend (`<script setup>` only)
- Tailwind 4 utility classes (no custom CSS)

### Data Integration Map (Global)

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| ContentIdea model + JSON casts | `content_ideas` table | Eloquent model | Yes | Extend |
| Status string column | `content_ideas.status` (varchar) | Direct attribute | Yes | Wrap via trait |
| Telegram master toggle | `settings` group='telegram' | `Setting::where(...)` | Yes | Add 3 new rows |
| Queued Telegram dispatch | `DispatchTelegramNotification` job | `dispatch(new Job(...))` | Yes | Add 3 new match arms |
| Sync translate primitive | `ArticleGenerationService::translateArticle` | Direct method call | Yes | Wrap in preflight |
| Sync VD rewrite primitive | `ArticleGenerationService::rewriteVisualDirectionForFace` | Direct method call | Yes | Use as template for coherence check |
| Cron orchestrator tick | `AutoPipelineOrchestrator::tick()` | Called from `content:auto-pipeline` artisan | Yes | Add gate |
| Image webhook sync | `ProcessPendingImages::syncToContentIdea` | Called from `blog:process-images` + webhook handler | Yes | Update advance rule |
| Admin status mutations | `ContentIdeaController` (25+ callsites) | HTTP endpoints | Yes | Route via trait |
| Frontend idea detail modal | `ImageGeneration.vue` | Vue component | Yes | Add per-segment buttons |
| useContentEngine composable | `src/composables/useContentEngine.js` | Composition API | Yes | Add 2 methods |
| About Settings Telegram card | `AboutSettings.vue` | Vue component | Yes | Add 3 toggle rows |
| Retry job queued | `App\Jobs\RetryImageSegmentJob` | Laravel queue | No | Create new |
| FSM enum + trait + guard | `App\Enums\*`, `App\Traits\*`, `App\Services\*` | PHP native | No | Create new |
| FSM exception | `App\Exceptions\InvalidStateTransitionException` | PHP exception | No | Create new + handler map |

---

### Phase A: State Machine Foundation

**Estimated time:** 45 minutes
**Risk:** Low (additive — no behavior change, no existing callsite touched)

**Data Integration Map (Phase A):**

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Status enum values | 9 hardcoded strings | `App\Enums\ContentIdeaStatus` | No | Create new |
| Transition allow-map | `ContentIdeaStatus::TRANSITIONS` const | Static PHP array | No | Create new |
| `transitionTo($next)` method | `App\Traits\HasStatusTransitions` | Trait method on ContentIdea | No | Create new |
| `InvalidStateTransitionException` | `App\Exceptions\...` | Thrown from trait | No | Create new |
| HTTP 409 mapping | `app/Exceptions/Handler.php::render` | Existing handler | Yes | Extend |
| `pipeline_state_log` column | `content_ideas.pipeline_state_log` JSON | Migration + cast | No | Create migration |
| `translation_attempts_auto` | `content_ideas.translation_attempts_auto` int | Migration + fillable | No | Add to migration |
| `translation_ready_at` | `content_ideas.translation_ready_at` timestamp | Migration + cast | No | Add to migration |
| `PipelineGuard::advance` | `App\Services\PipelineGuard` | Service class | No | Create new |

**Files:**
- Create: `backend/app/Enums/ContentIdeaStatus.php` (~50 LoC)
- Create: `backend/app/Traits/HasStatusTransitions.php` (~40 LoC)
- Create: `backend/app/Exceptions/InvalidStateTransitionException.php` (~15 LoC)
- Create: `backend/app/Services/PipelineGuard.php` (~35 LoC)
- Create: `backend/database/migrations/2026_04_21_000001_add_state_machine_fields_to_content_ideas.php` (~30 LoC)
- Modify: `backend/app/Models/ContentIdea.php` — use trait, add 3 fillable, add 3 casts (~6 LoC net)
- Modify: `backend/app/Exceptions/Handler.php` — add render branch for `InvalidStateTransitionException` → HTTP 409 (~8 LoC)
- Test: `backend/tests/Feature/ContentIdeaStatusTransitionsTest.php` (~180 LoC, 8 tests)
- Test: `backend/tests/Feature/PipelineGuardTest.php` (~90 LoC, 4 tests)

**Steps:**

1. Write failing test for `ContentIdeaStatus::canTransitionTo` allowed path (`draft → researching`). Expected error: `Error: Class "App\Enums\ContentIdeaStatus" not found`.
2. Create `ContentIdeaStatus.php` enum with 9 cases + `TRANSITIONS` const + `canTransitionTo(self $next): bool` method. Run test, confirm pass.
3. Write failing test for `ContentIdeaStatus::canTransitionTo` disallowed path (`draft → completed` should return false). Run test, see fail for logic bug if any. Fix enum logic if needed. Commit: `feat(content-engine): ContentIdeaStatus enum with transition map`.
4. Write failing test for migration — `$idea->pipeline_state_log` accessible as array, `$idea->translation_attempts_auto` defaults 0, `$idea->translation_ready_at` nullable datetime. Expected error: `Unknown column 'pipeline_state_log'`.
5. Create migration `add_state_machine_fields_to_content_ideas.php` — add `pipeline_state_log` JSON nullable, `translation_attempts_auto` tinyInt default 0, `translation_ready_at` timestamp nullable. Run `php artisan migrate`. Update `ContentIdea.php` fillable + casts. Run test, confirm pass. Commit: `feat(content-ideas): state-machine migration + model casts`.
6. Write failing test for `HasStatusTransitions::transitionTo` — legal transition `draft → researching` updates status + appends to `pipeline_state_log`. Expected error: `Call to undefined method transitionTo()`.
7. Create `HasStatusTransitions` trait with `transitionTo(ContentIdeaStatus|string $next, ?string $reason = null)` method — casts to enum, checks `canTransitionTo`, throws `InvalidStateTransitionException` on fail, otherwise appends `{from, to, reason, timestamp}` to `pipeline_state_log` (FIFO 20 via `array_slice(-20)`), calls `$this->update(['status' => ..., 'pipeline_state_log' => ...])`. Use trait on `ContentIdea` model. Run test, confirm pass.
8. Write failing test for illegal transition (`draft → completed`) — expect `InvalidStateTransitionException` with message containing "Cannot transition". Run test. Create `InvalidStateTransitionException` class extending `\DomainException`. Confirm pass. Commit: `feat(content-engine): HasStatusTransitions trait + exception`.
9. Write failing test for `PipelineGuard::advance` — wraps `transitionTo` + logs via `Log::info`. Use `Log::shouldReceive('info')->once()`. Expected error: `Class "App\Services\PipelineGuard" not found`.
10. Create `PipelineGuard::advance(ContentIdea $idea, ContentIdeaStatus $next, string $reason, array $context = []): ContentIdea` — try `transitionTo`, log success, on exception log error + rethrow. Run test, confirm pass. Commit: `feat(content-engine): PipelineGuard service`.
11. Write failing test for `Handler::render` — `InvalidStateTransitionException` thrown from controller returns HTTP 409 with `{success: false, message: '...'}`. Use `$this->getJson(...)` with a test route that throws. Expected initial: 500.
12. Modify `app/Exceptions/Handler.php` — add branch `if ($e instanceof InvalidStateTransitionException) return response()->json([...], 409)`. Run test, confirm pass. Commit: `feat(exceptions): map InvalidStateTransitionException to HTTP 409`.

**Verification:**
- [ ] `php artisan migrate` on clean DB succeeds (3 new columns created)
- [ ] `php artisan test --filter=ContentIdeaStatusTransitionsTest` all pass
- [ ] `php artisan test --filter=PipelineGuardTest` all pass
- [ ] `ContentIdea::first()->pipeline_state_log` returns array (tinker smoke test)
- [ ] Existing `syncToContentIdea` + orchestrator still use `update(['status' => ...])` — no behavior change verified by existing test suite
- [ ] `php artisan test` full suite passes (no regressions)
- [ ] No placeholder/TODO comments in new files

**Rollback:**
- `php artisan migrate:rollback --step=1` drops new columns
- Revert 4 new class files + 2 modified files
- Trait is inert if not used; safe to leave in codebase

---

### Phase B: Segment Retry State Machine

**Estimated time:** 90 minutes
**Risk:** Medium (touches webhook sync path + admin endpoints + frontend — isolated to image layer)

**Data Integration Map (Phase B):**

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Per-segment retry_count | `content_ideas.generated_article.image_prompts[i].retry_count` | JSON field | No | Extend schema (no migration) |
| Per-segment failure_history | `image_prompts[i].failure_history[]` | JSON array | No | Extend schema |
| Per-segment terminal_at | `image_prompts[i].terminal_at` | ISO string | No | Extend schema |
| `skipped` status value | `image_prompts[i].status` enum | String value | No | Add to allowed values |
| `retrySegment(ContentIdea, int $i)` | `ImageGenerationService` method | Service method | No | Create |
| `MAX_SEGMENT_ATTEMPTS` const | `ImageGenerationService::MAX_SEGMENT_ATTEMPTS = 3` | Class const | No | Add |
| POST retry-segment endpoint | `POST /admin/content-engine/ideas/{id}/retry-segment/{i}` | Route + controller method | No | Create |
| POST skip-segment endpoint | `POST /admin/content-engine/ideas/{id}/skip-segment/{i}` | Route + controller method | No | Create |
| `RetryImageSegmentJob` | `App\Jobs\RetryImageSegmentJob` | Queued job | No | Create |
| Advance rule update | `ProcessPendingImages::syncToContentIdea` | Existing method | Yes | Modify |
| Telegram segment exhausted | `TelegramNotificationService::sendSegmentRetryExhausted` | Service method | No | Add |
| Telegram cover critical | `TelegramNotificationService::sendCoverCriticalAlert` | Service method | No | Add |
| 2 new telegram toggles | `settings` keys `telegram_notify_segment_failed`, `telegram_notify_cover_critical` | Setting rows | No | Seeder upsert |
| DispatchTelegramNotification match arms | `match ($this->notificationType)` | Existing switch | Yes | Add 2 arms |
| Frontend Retry + Skip buttons | `ImageGeneration.vue` per-segment | Vue template | Yes | Extend |
| useContentEngine.retrySegment / skipSegment | `src/composables/useContentEngine.js` | Composable | Yes | Extend |

**Files:**
- Create: `backend/app/Jobs/RetryImageSegmentJob.php` (~50 LoC)
- Modify: `backend/app/Services/ImageGenerationService.php` — add `retrySegment()`, `MAX_SEGMENT_ATTEMPTS` const, update failure path in webhook/sync (~120 LoC net)
- Modify: `backend/app/Services/TelegramNotificationService.php` — add `sendSegmentRetryExhausted` + `sendCoverCriticalAlert` methods (~80 LoC)
- Modify: `backend/app/Jobs/DispatchTelegramNotification.php` — add 2 match arms + `payload` param support for segment index (~15 LoC)
- Modify: `backend/app/Console/Commands/ProcessPendingImages.php` — update `syncToContentIdea` advance rule, bump retry counters on failure, dispatch `RetryImageSegmentJob` (~60 LoC net)
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` — add `retrySegment()` + `skipSegment()` methods (~80 LoC)
- Modify: `backend/database/seeders/TelegramSettingsSeeder.php` — add 2 new rows idempotent upsert (~10 LoC)
- Modify: `backend/routes/api.php` — 2 new admin routes (~4 LoC)
- Modify: `frontend/src/components/admin/ImageGeneration.vue` — per-segment Retry + Skip UI + retry_count badge + failure_history tooltip (~120 LoC)
- Modify: `frontend/src/composables/useContentEngine.js` — add `retrySegment(ideaId, segmentIdx)` + `skipSegment(ideaId, segmentIdx)` (~30 LoC)
- Test: `backend/tests/Feature/SegmentRetryMachineTest.php` (~220 LoC, 8 tests)
- Test: `backend/tests/Feature/RetrySegmentEndpointTest.php` (~140 LoC, 5 tests)
- Test: `backend/tests/Feature/SkipSegmentEndpointTest.php` (~100 LoC, 4 tests)
- Test: `backend/tests/Feature/CoverCriticalBlockTest.php` (~120 LoC, 4 tests)

**Steps:**

1. Write failing test for `ImageGenerationService::retrySegment` — given idea with `image_prompts[0].status='failed'` + `retry_count=1`, calling retrySegment(idea, 0) dispatches new GeminiGen job via `queue()` mock + bumps `retry_count` to 2 + clears stale `variations`. Expected error: `Call to undefined method retrySegment()`.
2. Implement `retrySegment(ContentIdea $idea, int $i): ?string` in ImageGenerationService — reads `$idea->generated_article['image_prompts'][$i]`, increments retry_count, resets `variations=[]`, calls `queue()` with reused `entity_refs/face_refs/brand_refs`, appends new variation slot with returned UUID, sets `status='generating'`, saves idea. Returns UUID or null. Add `const MAX_SEGMENT_ATTEMPTS = 3` at class top. Run test, confirm pass. Commit: `feat(image-gen): retrySegment method with retry_count tracking`.
3. Write failing test for `ProcessPendingImages::syncToContentIdea` failure path — when GeminiGen returns failure on segment with `retry_count=0`, bump to 1 AND (when auto_mode) dispatch `RetryImageSegmentJob` with 60s delay. Expected error: job not dispatched. Use `Queue::fake()` + `Queue::assertPushed(RetryImageSegmentJob::class, fn($j) => $j->delay === 60)`.
4. Create `App\Jobs\RetryImageSegmentJob` — queued job with `$ideaId` + `$segmentIdx` props, `handle(ImageGenerationService)` calls `$service->retrySegment()`. Modify `syncToContentIdea` failure branch: increment `retry_count`, append to `failure_history[]`, if `retry_count < MAX && auto_mode` → `RetryImageSegmentJob::dispatch($idea->id, $i)->delay(now()->addSeconds(60))`. Run test, confirm pass. Commit: `feat(image-gen): RetryImageSegmentJob with 60s delay`.
5. Write failing test for segment exhaustion — when `retry_count` bumps from 2 to 3, set `terminal_at`, dispatch `DispatchTelegramNotification` with type `segment_retry_exhausted`. Expected: notification not dispatched.
6. Update `syncToContentIdea` failure branch — on `retry_count >= MAX`, set `terminal_at=now()`, dispatch `DispatchTelegramNotification($idea, 'segment_retry_exhausted', ['segment_idx' => $i])`. Modify `DispatchTelegramNotification` job to accept optional `$payload` array (3rd constructor param) + pass to service call via match. Add `sendSegmentRetryExhausted(ContentIdea, int $segmentIdx)` method to `TelegramNotificationService` — Markdown message with segment index, VD snippet, last failure reason from `failure_history[]`, Open Admin link. Add `isEnabledFor('segment_failed')` check. Add `telegram_notify_segment_failed` row to seeder. Run test, confirm pass. Commit: `feat(content-engine): segment retry exhaustion + Telegram alert`.
7. Write failing test for `ProcessPendingImages` advance rule — 3 segments where one is `done`, one is `failed+terminal`, one is `skipped` should advance idea to `images_ready` (manual) or `completed` (auto). Currently fails because `skipped` isn't recognized.
8. Update `syncToContentIdea` advance rule to: `$allResolved = every(in_array(status, ['done','skipped']) || (status==='failed' && !empty(terminal_at)))`. Run test, confirm pass. Commit: `feat(image-gen): advance rule accepts done+skipped+failed-terminal`.
9. Write failing test for cover-critical block — when `image_prompts[0].status='failed'` + `terminal_at` set, idea must NOT advance (stays `generating_images`) + dispatches `cover_critical` Telegram. Expected: advance still fires.
10. Update advance rule with `$coverCritical` check — if segment 0 has terminal failure/skip, block advance + dispatch `DispatchTelegramNotification($idea, 'cover_critical')`. Add `sendCoverCriticalAlert` method + `telegram_notify_cover_critical` setting row. Run test, confirm pass. Commit: `feat(content-engine): cover-critical block + Telegram alert`.
11. Write failing test for skip-segment endpoint — `POST /admin/content-engine/ideas/{id}/skip-segment/0` marks segment as skipped + terminal_at + appends user_skip to failure_history. Expected: 404 (route not defined).
12. Add routes in `api.php` — `Route::post('/admin/content-engine/ideas/{id}/skip-segment/{i}', [ContentIdeaController::class, 'skipSegment'])` + `retry-segment` parallel. Implement `skipSegment($id, $i)` in controller — validate idea exists + segment index in range + `status !== 'done'`, set `status='skipped'`, `terminal_at=now()`, append history. Run test, confirm pass.
13. Write failing test for retry-segment endpoint — `POST /admin/content-engine/ideas/{id}/retry-segment/0` calls `retrySegment` service method + returns job UUID. Implement `retrySegment($id, $i)` controller method — thin wrapper calling `$this->imageGen->retrySegment($idea, $i)`. Run test, confirm pass. Commit: `feat(content-engine): retry-segment + skip-segment admin endpoints`.
14. Write failing test for frontend `useContentEngine.retrySegment(id, idx)` composable method — mock `axios.post` + assert correct URL. Implement in `useContentEngine.js`. Parallel for `skipSegment`. Commit: `feat(frontend): retrySegment + skipSegment composable methods`.
15. Modify `ImageGeneration.vue` — per-segment card gets 2 new buttons ("Retry" visible when status=failed, "Skip" visible when status IN [failed, generating with long duration]). Add `retry_count` badge (e.g., "Attempt 2/3"). Add tooltip on segment card showing `failure_history[]` (time + reason). Wire to composable methods. Commit: `feat(frontend): per-segment Retry + Skip buttons`.

**Verification:**
- [ ] `php artisan test --filter=SegmentRetryMachineTest` all pass (8 tests)
- [ ] `php artisan test --filter=RetrySegmentEndpointTest` all pass (5 tests)
- [ ] `php artisan test --filter=SkipSegmentEndpointTest` all pass (4 tests)
- [ ] `php artisan test --filter=CoverCriticalBlockTest` all pass (4 tests)
- [ ] `php artisan test` full suite — 0 regressions in existing image pipeline tests (`PhaseBSplitPipelineE2ETest`, `WatermarkInjectionTest`, `CoverBrandingAutoInjectTest`)
- [ ] QA manual: force 3 test segments to fail (via disabled GeminiGen key), verify idea stuck at `generating_images` with Telegram exhaustion alerts + cover-critical alert when cover fails
- [ ] QA manual: click "Skip" on a failed non-cover segment, verify advance to `images_ready`
- [ ] Frontend: `npm run dev` + open ContentEngine admin page, verify Retry + Skip buttons render per-segment + retry_count badge shows correct attempt count
- [ ] No placeholder/TODO comments in new files
- [ ] `tsc --noEmit` N/A (JS project); `npm run build` succeeds

**Rollback:**
- Revert 4 backend files + 2 frontend files (PR-level revert)
- JSON schema extensions are additive — safe to leave `retry_count`/`failure_history`/`terminal_at` fields in existing idea records
- New Telegram setting rows: harmless (just unused toggles)

---

### Phase C: Translate-Before-Publish Gate

**Estimated time:** 60 minutes
**Risk:** Medium (cron behavior change gated by existing env flag)

**Data Integration Map (Phase C):**

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Feature flag | `config('services.article_generation.use_translate_phase')` | Env var → config | Yes | Already wired |
| Primary-lang article source | `idea.generated_article[{lang}].{title,content,...}` | Model attribute | Yes | Read |
| `translateArticle` primitive | `ArticleGenerationService::translateArticle(array $source): array` | Service method | Yes | Reuse |
| Preflight wrapper | `ArticleGenerationService::triggerTranslatePreflight(ContentIdea): array` | Service method | No | Create (thin wrapper) |
| Translation attempt counter | `content_ideas.translation_attempts_auto` (Phase A added) | Model attribute | Yes (from Phase A) | Increment |
| Translation ready timestamp | `content_ideas.translation_ready_at` (Phase A added) | Model attribute | Yes (from Phase A) | Set |
| Orchestrator gate | `AutoPipelineOrchestrator::ensureTranslationBeforePublish(ContentIdea): bool` | Method | No | Create |
| publishReady() call | `AutoPipelineOrchestrator::publishReady()` | Existing method | Yes | Gate |
| Telegram translate exhausted | `TelegramNotificationService::sendAutoTranslateExhausted` | Service method | No | Add |
| `DispatchTelegramNotification` match arm | Match expression | Existing | Yes | Add 1 arm |
| Setting toggle | `telegram_notify_translate_failed` setting row | Seeder | No | Add |
| Existing post-publish translate | `ContentPublishService::triggerTranslationIfEnabled` | Existing | Yes | Unchanged (still runs when `idea.generated_article.en` empty — shouldn't happen after preflight) |

**Files:**
- Modify: `backend/app/Services/ArticleGenerationService.php` — add `triggerTranslatePreflight(ContentIdea): array` (~45 LoC)
- Modify: `backend/app/Services/AutoPipelineOrchestrator.php` — add `ensureTranslationBeforePublish(ContentIdea): bool`, gate `publishReady()` (~50 LoC net)
- Modify: `backend/app/Services/TelegramNotificationService.php` — add `sendAutoTranslateExhausted(ContentIdea): bool` (~30 LoC)
- Modify: `backend/app/Jobs/DispatchTelegramNotification.php` — add `'auto_translate_exhausted'` match arm (~2 LoC)
- Modify: `backend/database/seeders/TelegramSettingsSeeder.php` — add `telegram_notify_translate_failed` row (~4 LoC)
- Modify: `frontend/src/views/admin/AboutSettings.vue` — add 3 toggle rows: `segment_failed`, `cover_critical`, `translate_failed` (~30 LoC, covers Phase B + C Telegram toggles)
- Modify: `frontend/src/stores/settings.js` — extend telegramSettings shape (~6 LoC)
- Test: `backend/tests/Feature/AutoPipelineTranslateGateTest.php` (~200 LoC, 6 tests)
- Test: `backend/tests/Feature/TriggerTranslatePreflightTest.php` (~120 LoC, 4 tests)

**Steps:**

1. Write failing test for `ArticleGenerationService::triggerTranslatePreflight` — given idea with `generated_article.id.content` populated, method calls existing `translateArticle($source)` + writes result into `generated_article.en.{title, content, excerpt, meta_title, meta_description, ai_summary}` + returns `['success' => true]`. Use `Mockery::mock(ArticleGenerationService::class)->makePartial()->shouldReceive('translateArticle')`. Expected error: `Call to undefined method triggerTranslatePreflight()`.
2. Implement `triggerTranslatePreflight(ContentIdea $idea): array` — extract primary-lang dict from `generated_article[$primaryLang]`, call `$this->translateArticle($source)`, on success write translated dict into `generated_article[$targetLang]` (merge with existing), save idea. Return service result. Run test, confirm pass.
3. Write failing test: when flag off (`config('services.article_generation.use_translate_phase', false)`), preflight returns success immediately without calling `translateArticle`. Use config override + `shouldNotReceive('translateArticle')`.
4. Add early-return guard in `triggerTranslatePreflight` — if flag off, return `['success' => true, 'skipped' => true]`. Run test, confirm pass. Commit: `feat(article-gen): triggerTranslatePreflight method`.
5. Write failing test for `AutoPipelineOrchestrator::ensureTranslationBeforePublish` — with flag on + no existing en content + attempts < 3, calls preflight + increments `translation_attempts_auto` + on success sets `translation_ready_at` + returns true. Expected error: `Call to undefined method`.
6. Implement `ensureTranslationBeforePublish(ContentIdea $idea): bool` in orchestrator per design Section 2 Concern #3 — 4 branches (flag off, already translated, exhausted, try). Run test, confirm pass.
7. Write failing test: `publishReady()` defers (returns null) when `ensureTranslationBeforePublish` returns false. Test scenario: flag on + preflight mocked to fail + attempts=1 (not exhausted yet). Idea stays at `completed` + `result_post_id=null`. Run test, see it fail (current behavior publishes anyway).
8. Modify `publishReady()` — call `ensureTranslationBeforePublish($idea)` after the existing `Post::where('source_idea_id')` check, return null if false. Run test, confirm pass. Commit: `feat(auto-pipeline): translate-before-publish gate in publishReady`.
9. Write failing test for exhaustion path — with `translation_attempts_auto=3` + en content empty, `ensureTranslationBeforePublish` dispatches `DispatchTelegramNotification` with type `auto_translate_exhausted` + sets `translation_ready_at=now()` + returns true (graceful degrade → publish monolingual). Expected: dispatch not called.
10. Add exhaustion branch to orchestrator + add `sendAutoTranslateExhausted(ContentIdea): bool` service method + match arm in `DispatchTelegramNotification` + `telegram_notify_translate_failed` seeder row. Run test, confirm pass. Commit: `feat(content-engine): auto-translate exhaustion Telegram alert + graceful degrade`.
11. Write failing test: when `idea.generated_article.en.content` already populated, `ensureTranslationBeforePublish` returns true immediately without incrementing attempts. Validates idempotency for manual flow (user already approved+translated in Gate 2).
12. Add early-return branch in `ensureTranslationBeforePublish` — if en content non-empty, set `translation_ready_at` (if not set) + return true. Run test, confirm pass. Commit: `feat(auto-pipeline): skip preflight when translation already present`.
13. Frontend: modify `AboutSettings.vue` Telegram card — add 3 new toggle rows for `segment_failed`, `cover_critical`, `translate_failed` (Phase B + C settings consolidated). Update `stores/settings.js` telegramSettings shape. Commit: `feat(settings): 3 new Telegram notification toggles`.

**Verification:**
- [ ] `php artisan test --filter=AutoPipelineTranslateGateTest` all pass (6 tests)
- [ ] `php artisan test --filter=TriggerTranslatePreflightTest` all pass (4 tests)
- [ ] `php artisan test` full suite — 0 regressions
- [ ] Manual smoke test in tinker: `config(['services.article_generation.use_translate_phase' => true])` + call `orchestrator->tick()` on a `completed` idea with no en content — verify `translation_attempts_auto=1` + en content populated after preflight
- [ ] Flag off (default) — orchestrator behavior unchanged, same as pre-Phase C
- [ ] Frontend: 3 new toggles render in AboutSettings, PUT saves correctly
- [ ] No placeholder/TODO comments

**Rollback:**
- Env flag off (`ARTICLE_GEN_USE_TRANSLATE_PHASE=false`) — instant rollback, zero code revert needed
- Full revert: 4 modified backend files + 2 frontend files
- No schema changes (migration in Phase A already provides columns — leave in place)

---

### Phase D: State Machine Enforcement

**Estimated time:** 75 minutes
**Risk:** High (touches 25+ callsites across 6 files — highest blast radius)

**Data Integration Map (Phase D):**

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| All status mutations | `$idea->update(['status' => ...])` 25+ callsites | Eloquent | Yes | Replace with `transitionTo()` |
| Orchestrator callsites | `AutoPipelineOrchestrator` (8 sites) | Methods | Yes | Migrate |
| Controller callsites | `ContentIdeaController` (12+ sites) | Methods | Yes | Migrate |
| ImageGenerationService callsites | 2 sites (`$idea->status = 'generating_images'`, `$idea->update(['status' => 'images_ready'])`) | Methods | Yes | Migrate |
| ProcessPendingImages callsites | 2 sites (`$idea->status = 'completed'`, `'images_ready'`) | Methods | Yes | Migrate |
| ContentPublishService callsite | 1 site (line 129 `$idea->update(['status' => 'completed'])`) | Method | Yes | Migrate |

**Files:**
- Modify: `backend/app/Services/AutoPipelineOrchestrator.php` — 8 callsites → `transitionTo()` / `PipelineGuard::advance()` (~40 LoC net)
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` — 12+ callsites → `transitionTo()` with try/catch → HTTP 409 (~60 LoC net)
- Modify: `backend/app/Services/ImageGenerationService.php` — 2 callsites → `transitionTo()` (~6 LoC net)
- Modify: `backend/app/Console/Commands/ProcessPendingImages.php` — 2 callsites → `transitionTo()` (~6 LoC net)
- Modify: `backend/app/Services/ContentPublishService.php` — 1 callsite → `transitionTo()` (~4 LoC net)
- Test: `backend/tests/Feature/FsmEnforcementRegressionTest.php` (~250 LoC, 10 tests — covers all 6 legal transition paths + 4 illegal)
- Test: All existing tests must still pass — serves as regression coverage

**Steps:**

1. Write failing test: attempting `$idea->update(['status' => 'completed'])` on a `draft` idea via legacy update() should STILL work (trait is non-intrusive) — sanity check that Phase A trait doesn't break anything. Run existing test suite — should all pass (confirms Phase A backward-compat).
2. Write failing test for orchestrator `startNextDraft` using `transitionTo` — mock `ContentIdea` with status=`draft`, call `startNextDraft()`, verify idea status=`researching` AND `pipeline_state_log[0].from='draft'` AND `pipeline_state_log[0].to='researching'` AND `reason='auto_pipeline_start'`. Expected: current code uses `update()` so `pipeline_state_log` stays empty.
3. Replace `AutoPipelineOrchestrator::startNextDraft` status mutation with `$pipelineGuard->advance($idea, ContentIdeaStatus::Researching, 'auto_pipeline_start')`. Inject `PipelineGuard` into constructor. Run test, confirm pass.
4. Repeat step 2+3 for remaining 7 orchestrator callsites: `resumeAtPrep` (→researching), `resumeAtWrite` (→researching), `resumeAtScore` (→researching), `dispatchImagesForReady` 2 sites (→generating_images, →completed), `markFailed` (→failed), `publishReady` (no status change — already completed). Each gets dedicated test. Commit: `refactor(auto-pipeline): route all status mutations through PipelineGuard`.
5. Write failing test for `ContentIdeaController::archive` → verify `pipeline_state_log` updated after archive action. Replace line 189 `$idea->update(['status' => 'archived'])` with `$idea->transitionTo(ContentIdeaStatus::Archived, 'admin_archive')`. Catch `InvalidStateTransitionException` in a wrapper — return HTTP 409 via handler (already mapped in Phase A). Run test, confirm pass.
6. Repeat for remaining 11 controller callsites:
   - Line 203 `revertToDraft` → `transitionTo(Draft, 'admin_revert')` (only legal from `archived` per map — update TRANSITIONS if needed)
   - Line 222, 366 (store, duplicate) — creation path, NOT a transition, leave as `update(['status' => 'draft'])`
   - Line 456, 506, 618 (research triggers) → `transitionTo(Researching, 'admin_research')`
   - Line 741, 961 (generate-images) → `transitionTo(GeneratingImages, 'admin_generate_images')`
   - approveArticle → `transitionTo(ArticleReady, 'admin_approve_article')`
   - approveAndPublish → handled by `ContentPublishService` (line 129 → `Completed`)
   - Verify `awaiting_manual_upload` → `generating_images` path in `uploadEntityReference` / `skipEntityReference` controller methods. Add `transitionTo` calls where needed.
   
   Each callsite gets test. Commit: `refactor(content-idea-controller): route status mutations through transitionTo`.
7. Write failing test for `ImageGenerationService::triggerForIdea` line 250 transition (`article_ready → generating_images`). Replace with `transitionTo`. Repeat for line 387 (`generating_images → images_ready`). Run tests. Commit: `refactor(image-gen): transitionTo for status mutations`.
8. Write failing test for `ProcessPendingImages::syncToContentIdea` transitions (line 160/163 → completed/images_ready). Replace with `transitionTo`. Run tests. Commit: `refactor(process-images): transitionTo for advance`.
9. Write failing test for `ContentPublishService::publish` line 129 (`→ Completed`). Replace with `transitionTo`. Run tests. Commit: `refactor(content-publish): transitionTo for completed`.
10. Write `FsmEnforcementRegressionTest` — 10 tests covering:
    - Legal: draft→researching, researching→article_ready, article_ready→generating_images, generating_images→images_ready, generating_images→completed (auto_mode), images_ready→completed, completed→archived, failed→researching (resume)
    - Illegal: draft→completed (HTTP 409), researching→images_ready (HTTP 409), archived→generating_images (HTTP 409), images_ready→draft (HTTP 409)
    Run all, confirm pass. Commit: `test(fsm): comprehensive transition regression coverage`.
11. Run full `php artisan test` suite. Fix any regressions. Common issue: tests that `factory()->create(['status' => 'completed'])` then update status without going through valid transition — fix by using `factory->state(['status' => 'intermediate'])` + chain `transitionTo`. Commit any test updates: `test(fsm): adapt existing tests to use transitionTo`.

**Verification:**
- [ ] `php artisan test --filter=FsmEnforcementRegressionTest` all pass (10 tests)
- [ ] `php artisan test` full suite — 0 regressions (if any surface, fix in same phase, not after)
- [ ] Grep confirms 0 remaining `$idea->update(['status' =>` or `$idea->status = '` in codebase (only legal callsites: factories + creation paths, NOT transition paths)
- [ ] QA manual: admin flow (pull trending → research → approve article → generate images → approve & publish) end-to-end works identically to pre-Phase D
- [ ] QA manual: simulate illegal transition via tinker (`$idea->transitionTo(ContentIdeaStatus::Completed)` on draft idea) — verify exception raised
- [ ] QA manual: HTTP 409 response visible when frontend sends invalid action (e.g., archive a completed post via direct API call when not allowed)
- [ ] `pipeline_state_log` populated on a freshly-advanced idea — verify via `ContentIdea::latest()->first()->pipeline_state_log`
- [ ] No placeholder/TODO comments

**Rollback:**
- Git revert the Phase D commit range (high blast radius — don't cherry-pick)
- Phase A trait remains safe (inert when not called)
- Schema + enum from Phase A remain in place — no migration rollback needed

---

### Phase Dependencies

```
A (Foundation) ─────────────────────────▶ D (Enforcement)
   │                                        ▲
   ├──▶ B (Segment Retry) ──────────────────┤
   │                                        │
   └──▶ C (Translate Gate) ─────────────────┘
```

- Phase A MUST ship first (provides enum + trait + migration)
- Phases B and C are independent of each other — can ship in either order, even in parallel
- Phase D MUST ship last (requires A + all callsites stable)

### Rollback Strategy Summary

| Phase | Rollback Method | Time to Rollback |
|---|---|---|
| A | `php artisan migrate:rollback --step=1` + revert 4 files | <5 min |
| B | Git revert PR (additive JSON schema safe to leave) | <10 min |
| C | `ARTICLE_GEN_USE_TRANSLATE_PHASE=false` env toggle | <1 min (instant) |
| D | Git revert PR range (highest risk — requires regression verification after revert) | <15 min |

### Testing Strategy

- **Phase A:** Unit tests for enum + trait + service. Integration test for exception handler. Total: ~12 tests.
- **Phase B:** Integration tests for retry job + endpoints + webhook sync path. Manual QA for frontend. Total: ~21 tests.
- **Phase C:** Integration tests for orchestrator gate + preflight wrapper + Telegram dispatch. Manual smoke for flag toggle. Total: ~10 tests.
- **Phase D:** Regression suite covers all transition paths. Uses existing test suite as implicit regression — any existing test that worked pre-D must work post-D. Total: ~10 new + ~all-existing = comprehensive.

**Total new tests: ~53 feature tests across 4 phases.**

### Git Commit Strategy

- One commit per TDD cycle (red → green) — roughly 15-20 commits across 4 phases
- Conventional commits format: `feat(...)`, `refactor(...)`, `test(...)`, `fix(...)`
- Each phase ends with a clean test run + deployable state
- Phase boundaries are natural PR boundaries — merge phase A, deploy, verify; then phase B/C in parallel PRs, etc.
- DO NOT push to `main` without user approval (per CLAUDE.md git push policy)

