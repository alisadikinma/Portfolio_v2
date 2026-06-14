# video_rebrand — error-aware retry + auto-learning rule loop

**Date:** 2026-06-14
**Status:** Part A SHIPPED (committed, NOT pushed) 2026-06-14 · Part B deferred (designed)
**Scope:** `backend/app/Console/Commands/PollRebrandAssets.php`, `backend/app/Jobs/GenerateRebrandAssets.php`, `backend/app/Services/GeminiGenVideoService.php`, `backend/app/Services/TelegramNotificationService.php`, `backend/app/Models/RepurposeJob.php`, `backend/app/Models/RepurposeVideoSlide.php`, 2 new migrations, 1 new service (`VideoGenErrorClassifier`), 1 new cron (`video-gen:distill-error-rules`), 1 vault digest note.

## Problem

The video_rebrand asset pipeline (face-gen keyframe → Veo I2V hook/CTA bookends, driven by the `repurpose:poll-rebrand-assets` cron) has four retry/observability defects:

1. **Blind retries.** On any error other than the `PROMINENT_PEOPLE` figure-refusal, `recover()` blanks `last_error` and re-dispatches the **identical prompt** → deterministic repeat → burns all 3 retries (observed live on `AUDIO_FILTERED`).
2. **Wasteful Veo-only retry (bug).** `recover()` nulls `keyframe_status` on any slide matching `veo_status='failed'`, so a CTA whose keyframe was `done` but Veo failed has its **good keyframe thrown away and re-rendered** (≈1 credit + the ~1–3 min SSH hook re-author). The line-142 "skip author if keyframe done" guard is defeated because recover already nulled it.
3. **Silent exhaustion.** On retry exhaustion, `checkCompletion()` transitions the job to `Failed` with **no Telegram alert** — the operator only finds out by checking admin.
4. **Uninformative notifications.** `sendRepurposeProgress($job, '🎬 Bikin klip…')` carries no job ID and no topic — the operator can't tell which job/topic is being processed.

Plus a meta-goal from the operator: **recurring errors should harden the generation rules automatically so the same mistake stops recurring on FUTURE jobs** ("AI Brain makin pintar").

## Design

Two parts. **Part A** = runtime fixes (the 4 asks). **Part B** = the auto-learning loop. A is shippable independently; B builds on A's error classification + ledger.

### Decisions locked (from brainstorm)

- **Hot path stays LLM-free.** Per-retry prompt degradation is a **static error-class map**, not an LLM call. The pipeline's "Static prompts, NO LLM step" de-risk philosophy is preserved.
- **Auto-learning is full-auto (no approval gate)** — but the mutation target is a **DB-backed reversible overlay**, never source/plugin files. Reason: `deploy.sh` runs `git reset --hard origin/main`, which would wipe any runtime edit to `VEO_PROMPT_*` or the plugin bundle. Learned state MUST live in MySQL. The safety envelope (kill-switch + Telegram-announce + auto-expire-on-no-improvement) replaces the human approval gate.
- **Substrate = relational table** (ExpeL/Voyager/Letta best practice: procedural rules → structured deterministic lookup, not vector, not markdown). Obsidian is the human-facing WHY digest + the **graduation target** for durable rules (promote DB rule → plugin static prompt → retire DB rule).

---

### Part A — Runtime fixes

#### A1. Error classifier (new) — `App\Services\VideoGenErrorClassifier`

```
classify(?string $reason): string
  → 'audio_filtered' | 'prominent_people' | 'content_policy' | 'transient' | 'unknown'
```

Deterministic substring map. Absorbs the existing `PollRebrandAssets::isSafetyError()` patterns (→ `prominent_people`) and adds `audio_filtered` (`PUBLIC_ERROR_AUDIO_FILTERED`, `audio generation failed`), `content_policy` (`unsafe`, `content policy`, `safety filter` not already figure), `transient` (`poll failed`, `timeout`, `stuck`, HTTP 5xx, download/crop failure). Pure, unit-testable.

#### A2. Persist the error class at failure time

New nullable column `repurpose_video_slides.last_error_class` (migration 1). Written alongside `last_error` in `pollKeyframes()`/`pollVeo()`/`markStuck()`/`dispatchKeyframe()` failure branches. Needed because `recover()` blanks `last_error` before re-dispatch — the class must survive to drive degradation + feed the ledger.

#### A3. Veo-only retry (fixes bug #2)

Rework `recover()` to route each failed bookend by bucket instead of the blunt "null everything" reset:

- **Keyframe-broken** (`keyframe_status IN ('failed', NULL)`): full reset (`keyframe_status=null, veo_status=null`) → job bounces to `Extracted` → `GenerateRebrandAssets` re-runs (re-renders keyframe; idempotent `dispatchKeyframe` now actually skips `done` keyframes because we no longer null them).
- **Veo-only-broken** (`keyframe_status='done' AND veo_status='failed'`): **keep `keyframe_url`**, reset only `veo_status=null`, and re-dispatch Veo **directly** via `GeminiGenVideoService::dispatchVeoClip($slide->keyframe_url, $degradedPrompt, '9:16', $slide->id)` → set `veo_status='generating', veo_job_uuid=$uuid`. Job stays in `GeneratingAssets` (no Extracted bounce). This is the literal "retry video only — jangan re-gen image."

Both buckets can co-exist on one job; route per slide. `asset_retry_count` stays **job-level** (one increment per recover tick) — matches "max 3x" and the existing budget. The `MAX_RETRIES=3` cap and 5-min cooldown are unchanged.

#### A4. Error-aware prompt degradation on retry (fixes #1, runtime layer)

At re-dispatch (both A3 paths), build the prompt as:

```
finalPrompt = basePrompt
            + degrade(error_class, retry_n)      // static map
            + activeLearnedConstraints(scope)    // Part B overlay, empty until B ships
```

`degrade()` static ladder:
| class | scope | degradation |
|---|---|---|
| `audio_filtered` | veo | strip the `Ambient:` line, swap `Audio:` to a barer single-positive-ambiance bed; retry 3 → drop motion cues to near-still |
| `prominent_people` | keyframe/scene | set `figure_dropped=true` (already done at failure time) → `GenerateRebrandAssets` re-authors creator-only |
| `content_policy` | scene | force static `KEYFRAME_PROMPT_*` (skip the authored scene) |
| `transient` | — | **no degradation** — retry same prompt (wasn't a prompt fault) |
| `unknown` | — | per-retry escalate-simplify (retry2 → static fallback, retry3 → barest) |

For the Veo-only path the degradation targets `VEO_PROMPT_*` audio/motion; for the keyframe path it flows through `GenerateRebrandAssets::buildHookKeyframe()` (figure drop / static fallback already exist — extend with `content_policy` → force static).

#### A5. Exhaustion → Telegram with inline Retry (fixes #3)

New `TelegramNotificationService::sendRepurposeAssetsFailed(RepurposeJob $job, string $lastError): bool` (master-toggle gated like sibling repurpose notifs). Called from `checkCompletion()` at the `Failed` transition. Message: header (A6) + "klip hook/CTA gagal setelah 3x retry — perlu action" + last slide error (truncated).

**Inline `🔁 Retry` button (in scope, decided):** HMAC-signed `callback_data` via the existing `signCallback('retry', 'repurpose', $job->id, $secret)` + a new `kind='repurpose'` branch in `TelegramWebhookController` → calls `RepurposeJobController::retry($job)` logic (extract the retry body into a shared method so the webhook + the HTTP endpoint share one path). Plus a `🛠 Open admin` URL button to `/admin/repurpose/{id}` as fallback. Verify the `RepurposeJobController::retry` `RETRY_MAP` already covers the video asset states (it does, per the 2026-06-14 fix) so the Telegram-triggered retry resumes at the correct guard + zeroes `asset_retry_count`.

#### A6. Informative notifications (fixes #4)

- `RepurposeJob::displayTopic(): string` — `video_rebrand` → tool slide `header_title`s joined (truncated ~60c); else `angle`; else host of `source_url`.
- `TelegramNotificationService::repurposeHeader(RepurposeJob $job): string` → `job #{id} · "{displayTopic}"`.
- Prepend the header to **all** repurpose notifications: `sendRepurposeProgress`, `sendRepurposeFailed`, `sendRepurposeDrafted`, `sendRepurposeModePrompt`, and the new `sendRepurposeAssetsFailed`. Progress bubble also gains the step + retry counter.

Example: `🎬 job #19 · "5 AI Coding Tools 2026" — Bikin klip hook + CTA (face-gen → Veo)… ↻ retry 2/3`.

---

### Part B — Auto-learning rule loop (DB overlay)

#### B1. Ledger — `video_gen_error_events` (migration 2)

`id, repurpose_job_id (FK), slide_role, scope, error_class, error_message, created_at`. One row per failure, written in `pollKeyframes`/`pollVeo`/`markStuck` failure branches (alongside A2).

#### B2. Rules overlay — `video_gen_learned_rules` (migration 2)

`id, error_class, scope (audio|scene|figure|all), constraint_text, is_active, source_event_id (FK), hit_count, applied_count, success_after_count, created_at, expires_at`.

At dispatch (A4's `activeLearnedConstraints(scope)`): `SELECT constraint_text FROM video_gen_learned_rules WHERE is_active AND expires_at > now() AND scope IN (:jobScopes)` → append each as a prompt line. Deterministic, indexed by `(error_class, scope, is_active)`.

#### B3. Distillation — `video-gen:distill-error-rules` cron (the ONE allowed LLM step, **offline**)

Runs ~hourly (or triggered on threshold). For each `error_class` with ≥3 events in 7d **and no active covering rule**:
- **Known class** (`audio_filtered`, `prominent_people`, `content_policy`) → INSERT a **static templated** constraint, `is_active=true` (no LLM).
- **Novel/`unknown` class** → offline Sonnet (ExpeL-style: feed the N collected `error_message`s, "distill ONE defensive prompt constraint, ≤25 words, additive only") → INSERT, `is_active=true` (full-auto per decision).
- Every insert → `TelegramNotificationService::sendLearnedRuleAnnounced(...)` (transparency, not approval).

#### B4. Effectiveness tracking + auto-expire (the self-correcting envelope)

- On a job SUCCESS where active rules were applied → `success_after_count++` on those rules, renew `expires_at`.
- Aging cron: a rule applied ≥N times whose `error_class` still recurs at the same rate (no `success_after` lift) → `is_active=false` + Telegram "rule retired, tidak membantu." Bad auto-rules remove themselves.
- Kill-switch: any rule is one `is_active=false` away from gone (DB/tinker v1; small admin UI = follow-up).

#### B5. Obsidian graduation (WHY layer)

Periodic digest writes/updates vault `30-Knowledge/video-gen-learned-rules.md` (active rules + rationale + hit/success counts). When a rule proves durable, a human/agent **promotes** it into the plugin static prompt (`VEO_PROMPT_*` / `video-pipeline-shared.md §0`) — permanent source-of-truth — then retires the DB rule. DB = auto-learning staging; plugin+vault = curated permanent.

## Data Integration Map

| Component | Data source | Existing? | Notes |
|---|---|---|---|
| Error classifier | slide `last_error` | reuse | absorbs `isSafetyError` |
| `last_error_class` | new column | migration 1 | survives recover's blank |
| Veo-only re-dispatch | `dispatchVeoClip(keyframe_url,…)` | existing svc method | no re-render |
| Exhaustion notif | `checkCompletion` Failed transition | existing hook point | new `sendRepurposeAssetsFailed` |
| Notif header | `displayTopic` ← tool `header_title`s | existing data | new accessor |
| Ledger | `video_gen_error_events` | migration 2 | raw signal |
| Rules overlay | `video_gen_learned_rules` | migration 2 | applied at dispatch |
| Distillation | offline Sonnet (novel only) | existing SSH pattern | NOT hot path |

## Phasing

- **Phase A (ship first):** A1–A6. Pure runtime fixes — closes all 4 operator asks, low risk, no learning infra. Tests: classifier unit, recover veo-only vs keyframe-broken routing, exhaustion-notify, header formatting.
- **Phase B (after A validated on prod):** B1–B5. Tables, overlay-at-dispatch, distill cron, effectiveness/expiry, vault digest.

## Anti-patterns enforced

- ❌ LLM in the hot retry path (offline distill only).
- ❌ Auto-editing `VEO_PROMPT_*` / plugin bundle (git-reset wipes; silent regression). DB overlay only.
- ❌ Vector store / Obsidian as the runtime rule store (non-deterministic / not deploy-safe).
- ❌ Throwing away a `done` keyframe on a Veo-only failure.
- ❌ Silent `Failed` transition with no operator alert.
- ❌ Per-retry escalation with no cap (3x hard cap retained).

---

## Implementation Plan — Part A

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER
> substitute placeholders for real data sources without explicit user approval.
> If a data source doesn't exist yet, STOP and ask. Part B (auto-learning loop)
> is OUT OF SCOPE here — documented as the follow-up phase in the Design above.

### Goal

Make the video_rebrand asset retry loop **error-aware, frugal, and observable**: read the GeminiGen error and degrade the prompt deterministically (no LLM) so the same failure stops recurring within the 3-retry budget; retry **only** the broken stage (never re-render a good keyframe on a Veo flake); alert the operator on exhaustion with a one-tap Telegram Retry; and put the job ID + topic into every repurpose notification.

### Architecture Context (from CLAUDE.md + code read)

- **Driver:** `repurpose:poll-rebrand-assets` cron ([PollRebrandAssets.php](../../backend/app/Console/Commands/PollRebrandAssets.php)) — 4 passes (keyframe poll, veo poll, completion, recover). GeminiGen NEVER webhooks → poll is the sole completion path.
- **Asset job:** [GenerateRebrandAssets.php](../../backend/app/Jobs/GenerateRebrandAssets.php) — `tries=1`, static `VEO_PROMPT_HOOK/CTA` + `KEYFRAME_PROMPT_HOOK/CTA` consts, `buildHookKeyframe()` (figure-drop + static fallback already exist).
- **Dispatch svc:** [GeminiGenVideoService.php](../../backend/app/Services/GeminiGenVideoService.php) — `dispatchKeyframe()`, `dispatchVeoClip($keyframeUrl,$prompt,'9:16',$slideId)`, `finalizeVeoClip()`.
- **Slide model:** [RepurposeVideoSlide.php](../../backend/app/Models/RepurposeVideoSlide.php) — `ROLE_HOOK|TOOL|CTA`, `keyframe_status`, `veo_status`, `keyframe_url`, `last_error`, `figure_dropped`. Job-level `asset_retry_count` on [RepurposeJob.php](../../backend/app/Models/RepurposeJob.php) (`displayTopic` to be added; `derivedTitle()` in the controller is the prior art to generalize).
- **Shared retry seam:** [RepurposeJobController::retry()](../../backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php#L144) body (RETRY_MAP routing + video_rebrand bookend reset + `asset_retry_count=0`) — extract into a service so the Telegram webhook reuses it.
- **Telegram webhook:** [TelegramWebhookController](../../backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php) `resolveRepurposeAction()` already handles `kind='repurpose'` (blog/carousel/video_rebrand) — add a `retry` action. Secret-header + HMAC `verifyCallback` already enforced. Notif methods in [TelegramNotificationService](../../backend/app/Services/TelegramNotificationService.php) (`signCallback`/`buildResolveInlineKeyboard` reusable).
- **Tests to mirror:** `tests/Feature/PollRebrandAssetsTest.php`, `GenerateRebrandAssetsTest.php`, `TelegramRepurposeCallbackTest.php`, `TelegramRepurposeMessageTest.php`, `RepurposeJobAdminControllerTest.php`. Factory: `RepurposeJobFactory`. **Test runner is the serversideup Docker phpunit (no host PHP); scope with `--filter` (suite has ~186 legacy fails) and re-run on the known stale-autoload bind-mount flake.**

### Tech Stack

Laravel 12 + PHP 8.2, Pest/PHPUnit feature+unit tests, MySQL (migration for the new column). NO new packages. Hot path stays LLM-free — degradation is a static PHP map.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Error classification | slide `last_error` string | `VideoGenErrorClassifier::classify()` | No | Create service (absorbs `PollRebrandAssets::isSafetyError`) |
| Persist class | `repurpose_video_slides.last_error_class` | migration + `$fillable` | No | Create column |
| Veo-only re-dispatch | `keyframe_url` + `dispatchVeoClip()` | `GeminiGenVideoService` | Yes | Use existing method |
| Prompt degradation | static `VEO_PROMPT_*` / `KEYFRAME_PROMPT_*` | `VideoGenPromptDegrader` | No | Create (static map, no LLM) |
| Shared retry | `RepurposeJobController::retry()` body | `RepurposeRetryService::retry()` | No | Extract from controller |
| Exhaustion alert + Retry btn | `checkCompletion()` Failed transition | `sendRepurposeAssetsFailed()` + `signCallback('retry','repurpose',id)` | No (notif) / Yes (HMAC) | Create notif, reuse HMAC |
| Webhook retry action | `resolveRepurposeAction()` | new `retry` branch → `RepurposeRetryService` | Partial | Add action |
| Topic label | `rewritten.title` / tool `header_title`s / caption | `RepurposeJob::displayTopic()` | No | Create (generalize `derivedTitle`) |
| Notif header | `displayTopic` + `job->id` | `TelegramNotificationService::repurposeHeader()` | No | Create + prepend to all repurpose notifs |

### Phase A1 — `VideoGenErrorClassifier` (pure service)

**Estimated time:** 12 min

**Files:** Create `backend/app/Services/VideoGenErrorClassifier.php`; Test `backend/tests/Unit/VideoGenErrorClassifierTest.php`

**Steps:**
1. Write failing test for `classify()` mapping. Expected error: `Error: Class "App\Services\VideoGenErrorClassifier" not found`. Cases: `'PUBLIC_ERROR_AUDIO_FILTERED'`→`audio_filtered`; `'prominent people'`→`prominent_people`; `'content policy'`→`content_policy`; `'poll failed'`/`'timeout'`/`'stuck 16min'`→`transient`; `''`/`null`→`transient`; `'weird new error'`→`unknown`.
2. Run test, confirm fail for the expected reason.
3. Implement `classify(?string $reason): string` — deterministic lowercased substring map; reuse the `isSafetyError` pattern list for `prominent_people`. Order: prominent_people → audio_filtered → content_policy → transient (timeout/poll/stuck/download/5xx) → unknown.
4. Run tests, confirm pass.
5. Commit: `feat(video_rebrand): add VideoGenErrorClassifier (static error→class map)`

**Verification:**
- [ ] `php artisan test --filter=VideoGenErrorClassifier` green
- [ ] Pure, no DB/HTTP deps; no placeholder/TODO
- [ ] All 5 classes covered incl. null/empty → `transient`

### Phase A2 — `last_error_class` column + write at failure

**Estimated time:** 14 min

**Files:** Create `backend/database/migrations/2026_06_14_000001_add_last_error_class_to_repurpose_video_slides.php`; Modify `RepurposeVideoSlide.php` ($fillable), `PollRebrandAssets.php` (failure branches), `GenerateRebrandAssets.php` (`dispatchKeyframe` fail); Test `backend/tests/Feature/PollRebrandAssetsTest.php`

**Steps:**
1. Write failing test: a `veo_status='generating'` slide whose poll returns `error_message='PUBLIC_ERROR_AUDIO_FILTERED'` persists `last_error_class='audio_filtered'`. Expected error: assertion fail (`last_error_class` null / column missing).
2. Run, confirm fail.
3. Add nullable `string('last_error_class', 32)->nullable()` migration; add to `$fillable`; run migration.
4. In `pollKeyframes`/`pollVeo`/`markStuck` (+ `GenerateRebrandAssets::dispatchKeyframe` fail) set `last_error_class => app(VideoGenErrorClassifier::class)->classify($reason)` alongside every `last_error` write.
5. Run tests, confirm pass.
6. Commit: `feat(video_rebrand): persist last_error_class on slide failures`

**Verification:**
- [ ] Migration runs clean; column nullable
- [ ] Every `last_error` write has a paired `last_error_class` write
- [ ] `--filter=PollRebrandAssets` green

### Phase A3 — Veo-only retry routing in `recover()`

**Estimated time:** 15 min

**Files:** Modify `PollRebrandAssets.php` (`recover()`); Test `PollRebrandAssetsTest.php`

**Steps:**
1. Write failing test: job with hook `keyframe='done',keyframe_url=set,veo='failed'` + cooldown elapsed → after `recover()`, the hook keeps its `keyframe_url`, `veo_status='generating'` (re-dispatched via `dispatchVeoClip`), job STAYS `generating_assets` (no Extracted bounce), `GenerateRebrandAssets` NOT dispatched. Second test: `keyframe='failed'` → full reset (kf+veo null) + Extracted bounce + `GenerateRebrandAssets` dispatched. Expected error: current code nulls keyframe_status on the veo-only slide (assertion fail).
2. Run, confirm fail.
3. Rework `recover()`: per failed bookend, bucket by `keyframe_status==='done' && veo_status==='failed'` (veo-only) vs else (keyframe-broken). Veo-only → reset `veo_status=null` only, `dispatchVeoClip($slide->keyframe_url, <prompt for role>, '9:16', $slide->id)`, set `veo_status='generating',veo_job_uuid=$uuid`; no FSM bounce. Keyframe-broken → existing full-reset + Extracted bounce + `GenerateRebrandAssets`. Increment `asset_retry_count` once per recover tick; keep `MAX_RETRIES`/cooldown guards. Mock `GeminiGenVideoService` in tests.
4. Run tests, confirm pass.
5. Commit: `fix(video_rebrand): retry Veo-only on Veo failure, preserve good keyframe`

**Verification:**
- [ ] Veo-only failure preserves `keyframe_url`, re-dispatches Veo, no `GenerateRebrandAssets`
- [ ] Keyframe failure still full-resets + bounces to Extracted
- [ ] `asset_retry_count` increments once/tick; MAX_RETRIES respected
- [ ] `--filter=PollRebrandAssets` green

### Phase A4 — Error-aware prompt degradation

**Estimated time:** 15 min

**Files:** Create `backend/app/Services/VideoGenPromptDegrader.php`; Modify `PollRebrandAssets.php` (veo-only re-dispatch uses degraded prompt), `GenerateRebrandAssets.php` (`content_policy` → force static keyframe via a passed sentinel/flag, reusing `figure_dropped`-style branch); Test `backend/tests/Unit/VideoGenPromptDegraderTest.php`

**Steps:**
1. Write failing test for `degradeVeo(string $base, string $errorClass, int $retryN): string`: `audio_filtered` → returned prompt drops the `Ambient:` line + swaps to a barer single-ambiance `Audio:` bed; `transient` → returns `$base` unchanged; `retryN>=3` → near-still motion. Expected error: class not found.
2. Run, confirm fail.
3. Implement `VideoGenPromptDegrader` (pure static map; includes an `// LEARNED-CONSTRAINTS APPEND POINT (Part B)` comment marker returning `''` for now). Wire `degradeVeo()` into A3's veo-only re-dispatch (look up `last_error_class` BEFORE blanking). For keyframe `content_policy`: pass a `forceStaticScene` signal so `buildHookKeyframe` returns the static `KEYFRAME_PROMPT_*` (extend the existing fallback branch).
4. Run tests, confirm pass.
5. Commit: `feat(video_rebrand): error-aware static prompt degradation on retry`

**Verification:**
- [ ] `audio_filtered` degrades audio bed; `transient` unchanged (no needless mutation)
- [ ] Degrader is pure/static — NO LLM call, NO HTTP
- [ ] `content_policy` keyframe path forces static scene
- [ ] `--filter=VideoGenPromptDegrader` green

### Phase A5 — Exhaustion Telegram alert + inline Retry button

**Estimated time:** 18 min · **Security-sensitive (Telegram callback / webhook action)**

**Files:** Create `backend/app/Services/RepurposeRetryService.php`; Modify `RepurposeJobController.php` (delegate `retry()`), `TelegramNotificationService.php` (`sendRepurposeAssetsFailed`), `TelegramWebhookController.php` (`resolveRepurposeAction` `retry` branch), `PollRebrandAssets.php` (`checkCompletion` calls notif on Failed); Tests `RepurposeJobAdminControllerTest.php`, `TelegramRepurposeCallbackTest.php`, `PollRebrandAssetsTest.php`

**Steps:**
1. Write failing test: `TelegramRepurposeCallbackTest` — a `retry`/`repurpose`/{id} HMAC callback on a `Failed` video_rebrand job zeroes `asset_retry_count`, resets failed bookends, transitions to `extracted`, dispatches `GenerateRebrandAssets`. Plus `PollRebrandAssetsTest`: exhaustion (`asset_retry_count>=3` + bookend failed) calls `sendRepurposeAssetsFailed`. Expected error: webhook returns "Unknown action" / notif method missing.
2. Run, confirm fail.
3. Extract `RepurposeJobController::retry()` body → `RepurposeRetryService::retry(RepurposeJob $job): array{ok,message,status}`; controller delegates (existing controller tests stay green — refactor only). Add `sendRepurposeAssetsFailed($job,$lastError)` with `inline_keyboard`: `🔁 Retry` (`signCallback('retry','repurpose',$job->id,$secret)`) + `🛠 Open admin` URL `/admin/repurpose/{id}`. Add `retry` branch to `resolveRepurposeAction` (idempotent: only when `status===Failed`) → `RepurposeRetryService`. Call notif from `checkCompletion` at the Failed transition.
4. Run tests, confirm pass.
5. Commit: `feat(video_rebrand): exhaustion Telegram alert + one-tap inline Retry`

**Verification:**
- [ ] Retry callback dispatches via shared `RepurposeRetryService` (no logic dupe vs HTTP endpoint)
- [ ] Callback HMAC-verified + idempotent (no-op when not Failed); webhook secret-gated (no new auth surface)
- [ ] Exhaustion fires exactly one notif; master-toggle gated
- [ ] `--filter=TelegramRepurposeCallback`, `--filter=RepurposeJobAdminController`, `--filter=PollRebrandAssets` green

### Phase A6 — Informative notifications (topic + job ID)

**Estimated time:** 14 min

**Files:** Modify `RepurposeJob.php` (`displayTopic()`), `TelegramNotificationService.php` (`repurposeHeader()` + prepend to all repurpose notifs), `GenerateRebrandAssets.php` (progress copy), `PollRebrandAssets.php` (retry-count in progress where applicable); Tests `TelegramRepurposeMessageTest.php` / `RepurposeJobListTitleTest.php`

**Steps:**
1. Write failing test: `sendRepurposeProgress`/`sendRepurposeFailed`/`sendRepurposeAssetsFailed` for a video_rebrand job render a header `job #{id} · "{topic}"` where topic = tool `header_title`s. Expected error: message lacks job id/topic.
2. Run, confirm fail.
3. Add `RepurposeJob::displayTopic(): string` (rewritten title → video_rebrand tool `header_title`s joined ≤60c → first caption line → `source_url` host). Add `repurposeHeader($job)` and prepend to `sendRepurposeProgress`, `sendRepurposeFailed`, `sendRepurposeDrafted`, `sendRepurposeModePrompt`, `sendRepurposeAssetsFailed`. Update `GenerateRebrandAssets` progress string to the headered form (`🎬 job #N · "topic" — Bikin klip hook + CTA…`); include `↻ retry k/3` on recover-path progress.
4. Run tests, confirm pass.
5. Commit: `feat(video_rebrand): job id + topic in all repurpose Telegram notifs`

**Verification:**
- [ ] Every repurpose notif carries `job #id` + topic; Markdown-escaped (no Telegram 400)
- [ ] `displayTopic` falls back cleanly (no fatal on null rewritten/extracted)
- [ ] `--filter=TelegramRepurpose` green

### Post-Plan: sync docs

After A6: update root `CLAUDE.md` (video_rebrand retry section + the new `last_error_class` column / `RepurposeRetryService` / classifier) and the `## Recent Changes` log; update the vault `hot.md` + `video-pipeline-shared.md §0` with the error-aware-retry standard. Run `graphify update .`. (Per CLAUDE.md "After Changes" — docs are a completion gate, not optional.)

### Execution Handoff

- **Option 1 (recommended):** Execute now with `gaspol-execute` — phases A1→A6 are mostly sequential (A2 depends on A1; A3 on A2; A4 on A3; A5 on the shared-retry extract; A6 independent).
- **Option 2:** `gaspol-parallel` — A1 and A6's `displayTopic` are independent of the A2→A4 chain, but the chain dominates, so parallelism gain is small. Sequential is cleaner here.
- **Option 3:** Save for a new session — this doc has everything needed.
```