# IG-Repurpose LLM Hardening — Timeout Alignment + Repair-Retry

> **For Claude:** REQUIRED SKILL: Use gaspol-execute (or gaspol-tdd per phase) to implement this plan.
> **CRITICAL:** This plan specifies real integrations (Claude CLI exec over SSH, the
> `RunsRepurposeClaudeCli` trait, the three repurpose services). During execution,
> NEVER substitute placeholders. No PHP on the dev Mac — every test runs in Docker
> `serversideup/php:8.2-cli` (sqlite `:memory:`) and on CI. If a step needs a data
> source that doesn't exist, STOP and ask.

## Goal

Harden the Telegram→Instagram repurpose pipeline against the non-deterministic
Claude-CLI JSON failures that bricked 4 of the last jobs in Social Studio
(`vision_unparseable`, `rewrite_unparseable`, `exec_error: timeout 300s`). Root
cause (already diagnosed, confirmed on the production VPS): Sonnet hand-authors a
large JSON blob (16-slide vision extract → full HTML article rewrite) via raw CLI
with **no structured-output enforcement** and only a **300s** wall-clock budget, so
on big source carousels its output is either **truncated** (output-token cap),
**invalid** (unescaped `"` inside a JSON string — proven in job #7's
`... | "You're about to`), or **timed out** (job #5 hit 300s exactly). A re-run of
the identical prompt parsed cleanly on the VPS — the failures are a coin flip, not
a logic bug.

Two composable fixes:
- **Opt 2 — Timeout alignment.** Raise the CLI budget 300→900s (match
  `carousel-gen`), and raise the three step-job `$timeout`s so the queue worker
  doesn't kill a job mid-call (they are currently *below* the new CLI budget).
- **Opt 3 — Repair-retry + strict-JSON directive.** Add ONE repair attempt in the
  shared trait when the CLI returns output that won't parse / is missing required
  keys, plus an explicit "escape every `"`, output compact JSON, don't truncate"
  directive in each prompt. Repair fires **only on parse failure with output** —
  never on an exec timeout (a re-run would just time out again; that case is what
  Opt 2's bigger budget fixes).

## Architecture Context

Pulled from root `CLAUDE.md` (IG Repurpose section) + the code:

- **FSM:** [`RepurposeJobStatus`](../../backend/app/Enums/RepurposeJobStatus.php) on
  [`RepurposeJob`](../../backend/app/Models/RepurposeJob.php) via generic
  `HasStatusTransitions`. Each step job advances FSM; on failure →
  `failed` + Telegram notice.
- **Shared CLI seam:** [`RunsRepurposeClaudeCli`](../../backend/app/Services/Concerns/RunsRepurposeClaudeCli.php)
  trait — `runRepurposeSync(prompt, phase, model, refs)` (ssh|local exec, base64
  prompt transport, empty-mcp anti-leak flags) + `parseJsonObject(raw)`
  (balanced-brace scanner tolerating preamble + markdown fences). Used by all 3
  AI services.
- **Three consumers** (identical run→parse→validate→`Log::error(output_head)`
  shape):
  - [`SlideVisionExtractor::extract`](../../backend/app/Services/SlideVisionExtractor.php) — required key `claims`, error `vision_unparseable`, model `services.repurpose.model_vision`.
  - [`RepurposeResearchService::research`](../../backend/app/Services/RepurposeResearchService.php) — required key `verdicts`, error `research_unparseable`, model `model_research`, uses native WebSearch (slower).
  - [`RepurposeRewriteService::rewrite`](../../backend/app/Services/RepurposeRewriteService.php) — required keys `title`+`body`, error `rewrite_unparseable`, model `model_rewrite`, appends `refs_rewrite`.
- **Step jobs + current `$timeout`:** [`ExtractSlideContent`](../../backend/app/Jobs/ExtractSlideContent.php) 360 · [`ResearchRepurposeClaims`](../../backend/app/Jobs/ResearchRepurposeClaims.php) 600 · [`RewriteRepurposeContent`](../../backend/app/Jobs/RewriteRepurposeContent.php) 600. Single-try, idempotent, FSM-guarded.
- **Config:** [`config/services.php`](../../backend/config/services.php) `repurpose` block — `timeout` default currently `(int) env('REPURPOSE_TIMEOUT', 300)`. `carousel-gen.timeout_seconds` = 900 (the precedent to match).
- **Tests:** PHPUnit (NOT Pest), `Tests\TestCase` + `RefreshDatabase`, Mockery, `Process::fake`, `Http::fake`. SQLite `:memory:` via `phpunit.xml`. Existing repurpose suite under `tests/Feature/*Repurpose*`. Job tests mock the service (so unaffected by this change). No host PHP → run in Docker.

## Tech Stack

Laravel 10 / PHP 8.2. `Illuminate\Support\Facades\Process` (timeout + fake +
sequence). Mockery for service mocks. No new packages, no migrations, no FSM
changes, no publish-engine touch.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| CLI exec + JSON parse | Claude CLI over SSH | `RunsRepurposeClaudeCli::runRepurposeSync` / `parseJsonObject` | Yes | Use existing; wrap with new `runRepurposeParsed` |
| Repair-retry orchestration | n/a (new) | `RunsRepurposeClaudeCli::runRepurposeParsed` (NEW) | No | Create in trait — real, not stub |
| Strict-JSON directive | prompt strings | `buildPrompt()` in each of the 3 services | Yes | Append directive string |
| CLI timeout budget | config | `config('services.repurpose.timeout')` | Yes | Default 300→900 |
| Step job wall-clock | job property | `$timeout` on 3 step jobs | Yes | Raise to 1920 (covers 2× 900s + buffer) |
| Vision required key | parsed output | `parsed['claims']` | Yes | Pass to `runRepurposeParsed` |
| Research required key | parsed output | `parsed['verdicts']` | Yes | Pass to `runRepurposeParsed` |
| Rewrite required keys | parsed output | `parsed['title']`, `parsed['body']` | Yes | Pass to `runRepurposeParsed` |
| Process faking in tests | test harness | `Process::fake(['*' => Process::sequence()...])` | Yes | Use; set driver=local for 1 call/attempt |

## Design Decisions (locked)

1. **Repair fires ONLY on "exec succeeded but unparseable/missing-keys".** On exec
   failure (timeout / ssh error) return the error immediately — no repair. Rationale:
   a timeout re-run just times out again; Opt 2's 900s budget is what rescues
   timeouts (job #5 ran 301s and was cut at the 300s wall — 900s lets it finish).
2. **Worst-case job time** ≈ attempt-1 (≤900s) + repair (≤900s) = ~1800s, so step
   `$timeout` → **1920s** (1800 + 120 buffer). Trade-off: a doubly-failing repurpose
   job can hold the `default` queue worker up to ~32 min. Accepted — these are
   background jobs, failure is rare, the operator can cancel, and silent failure is
   worse. (The cross-post fan-out runs on its own `social-crosspost` pool, unaffected.)
3. **Trait owns run+parse+repair+repair-logging; services keep their specific error
   string + `output_head` forensic log.** `runRepurposeParsed` returns
   `['success','parsed','output','error','repaired']`. Each service maps a failure to
   its existing error string (`vision_unparseable` etc.) so existing job-test
   assertions stay valid.
4. **Wire all three services** (vision, research, rewrite), not just the two that
   failed — identical pattern, marginal cost, prevents the same class recurring in
   research.
5. **No structured-output / `--output-format json` flag** — the CLI runs an
   interactive agent (`-p`) and that flag isn't in the current invocation contract;
   the repair-retry + escaping directive is the in-contract lever (mirrors the
   documented `/carousel-gen` Sonnet-truncation handling).

---

## Implementation Plan

### Phase A: Timeout alignment (Opt 2)

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/config/services.php` (repurpose `timeout` default 300→900)
- Modify: `backend/app/Jobs/ExtractSlideContent.php` (`$timeout` 360→1920)
- Modify: `backend/app/Jobs/ResearchRepurposeClaims.php` (`$timeout` 600→1920)
- Modify: `backend/app/Jobs/RewriteRepurposeContent.php` (`$timeout` 600→1920)
- Modify: `backend/.env.example` (document `REPURPOSE_TIMEOUT=900`, add if missing)
- Test: `backend/tests/Unit/RepurposeTimeoutConfigTest.php`

**Steps:**
1. Write failing test for the timeout contract. Expected error: `Failed asserting that 300 matches expected 900` (and job-property asserts). Test asserts: `config('services.repurpose.timeout') === 900` (no `REPURPOSE_TIMEOUT` env in testing); `(new RewriteRepurposeContent(1))->timeout === 1920`; same for `ExtractSlideContent`, `ResearchRepurposeClaims`.
2. Run test in Docker, confirm it fails for the expected reason.
3. Change config default `env('REPURPOSE_TIMEOUT', 300)` → `env('REPURPOSE_TIMEOUT', 900)`; bump the three `$timeout` properties to `1920`; add `REPURPOSE_TIMEOUT=900` line to `.env.example` with a comment ("CLI budget per attempt; matches carousel-gen").
4. Run test, confirm pass.
5. Commit: `fix(repurpose): align CLI timeout 300→900s + step-job timeouts to cover repair headroom`

**Verification:**
- [ ] `php -l` clean on all 4 modified PHP files
- [ ] `config('services.repurpose.timeout')` resolves to 900 default; the 3 step jobs expose `$timeout === 1920`
- [ ] No placeholder/TODO comments
- [ ] Docker test run green: `RepurposeTimeoutConfigTest`

---

### Phase B: Strict-JSON directive in prompts (Opt 3a)

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Services/SlideVisionExtractor.php` (`buildPrompt`)
- Modify: `backend/app/Services/RepurposeResearchService.php` (`buildPrompt`)
- Modify: `backend/app/Services/RepurposeRewriteService.php` (`buildPrompt`)
- Test: `backend/tests/Unit/RepurposePromptDirectiveTest.php`

**Directive text (append to each prompt's "Return ONE JSON object…" block):**
```
STRICT JSON OUTPUT — your output is parsed by a machine, not a human:
- Output ONE compact JSON object only. No markdown fences, no preamble, no trailing prose.
- Escape EVERY double-quote inside a string value as \" (a raw " inside a value breaks parsing).
- Do not truncate. If content runs long, be more concise but ALWAYS close the JSON object.
```

**Steps:**
1. Write failing test for the directive presence. Expected error: `Failed asserting that '...prompt...' contains 'Escape EVERY double-quote'`. Test builds each service's prompt via reflection on the private `buildPrompt` (mirror the debug repro) with minimal fixtures, asserts each contains `'Escape EVERY double-quote'` and `'Do not truncate'`.
2. Run test in Docker, confirm it fails for the expected reason.
3. Append the directive to all three `buildPrompt` methods (keep the existing key schema lines).
4. Run test, confirm pass.
5. Commit: `feat(repurpose): strict-JSON escaping/no-truncate directive in vision/research/rewrite prompts`

**Verification:**
- [ ] `php -l` clean on the 3 services
- [ ] Each built prompt contains the escaping + no-truncate directive
- [ ] Existing key-schema example (`{"title":...}` etc.) still present — directive is additive
- [ ] Docker test run green: `RepurposePromptDirectiveTest`

---

### Phase C: Repair-retry core in the trait (Opt 3b)

**Estimated time:** 14 minutes

**Files:**
- Modify: `backend/app/Services/Concerns/RunsRepurposeClaudeCli.php` (add `runRepurposeParsed`, `buildRepairPrompt`, `hasRequiredKeys`)
- Test: `backend/tests/Unit/RepurposeRepairRetryTest.php`

**New trait method contract:**
```php
/**
 * Run the CLI, parse the JSON object, validate required keys. On a parse/keys
 * failure WHERE EXEC SUCCEEDED, fire ONE repair attempt with a strict re-prompt.
 * Exec failures (timeout/ssh) are returned immediately (no repair).
 *
 * @param string[] $requiredKeys keys that must be present & non-empty in parsed
 * @return array{success:bool, parsed:array<string,mixed>|null, output:string, error:string|null, repaired:bool}
 */
protected function runRepurposeParsed(
    string $prompt, string $phase, array $requiredKeys,
    string $model = 'sonnet', string $refsFile = ''
): array
```

**Behavior:**
- Attempt 1: `runRepurposeSync($prompt, $phase, $model, $refs)`.
  - exec failed → return `success:false, error: <exec error>, repaired:false` (NO repair).
  - exec ok + `parseJsonObject` yields array with all `$requiredKeys` non-empty → `success:true, parsed, repaired:false`.
- Else (exec ok but unparseable / missing keys) → `Log::warning('[Repurpose] repair retry', ['phase'=>...])`; repair attempt: `runRepurposeSync(buildRepairPrompt($prompt,$requiredKeys), "{$phase}-repair", $model, $refs)`.
  - repair exec ok + valid → `success:true, parsed, repaired:true`.
  - else → `success:false, parsed:null, output: <repair output ?: attempt1 output>, error:'unparseable_after_repair', repaired:true`.
- `buildRepairPrompt`: original prompt + a strict suffix ("Your previous output was invalid or incomplete JSON. Return ONLY one complete valid JSON object with keys: <list>. Escape every \" inside string values. Compact, no fences, do not truncate.").
- `hasRequiredKeys(?array $parsed, array $keys): bool` — false when `$parsed` null or any key empty (`empty()`).

**Steps:**
1. Write failing test. Expected error: `Error: Call to undefined method ...::runRepurposeParsed()`. Test uses an inline stub class `use RunsRepurposeClaudeCli` exposing `public function call(...) { return $this->runRepurposeParsed(...); }`; sets `config(['services.repurpose.driver' => 'local'])` (1 Process call/attempt); `Process::fake` with `Process::sequence()`.
2. Run test in Docker, confirm it fails for the expected reason.
3. Implement the three methods in the trait.
4. Run tests, confirm pass.
5. Commit: `feat(repurpose): runRepurposeParsed one-shot repair-retry on unparseable CLI output`

**Test cases (`RepurposeRepairRetryTest`):**
- `test_valid_first_attempt_no_repair` — fake returns valid `{"claims":["x"]}`; assert `success`, `repaired===false`, exactly ONE Process run (`Process::assertRanTimes` or sequence depth).
- `test_unparseable_then_repaired` — seq: bad output (`{"claims": "oops` unterminated) → valid `{"claims":["x"]}`; assert `success`, `repaired===true`, TWO runs; the 2nd command contains the repair suffix.
- `test_missing_required_key_triggers_repair` — seq: `{"title":"t"}` (no `body`, requiredKeys `['title','body']`) → `{"title":"t","body":"<p>x</p>"}`; assert `success`, `repaired===true`.
- `test_repair_also_fails_returns_error` — seq: bad → bad; assert `success===false`, `error==='unparseable_after_repair'`, `repaired===true`.
- `test_exec_timeout_no_repair` — fake a failed Process (non-zero exit / `Process::result(exitCode:124)`); assert `success===false`, `repaired===false`, ONE run, error carries the exec message.

**Verification:**
- [ ] `php -l` clean on the trait
- [ ] All 5 trait test cases green in Docker
- [ ] Repair fires on unparseable-with-output, NOT on exec failure (case 1 & 5)
- [ ] No placeholder/TODO comments

---

### Phase D: Wire the three services to `runRepurposeParsed`

**Estimated time:** 14 minutes

**Files:**
- Modify: `backend/app/Services/SlideVisionExtractor.php` (`extract`)
- Modify: `backend/app/Services/RepurposeResearchService.php` (`research`)
- Modify: `backend/app/Services/RepurposeRewriteService.php` (`rewrite`)
- Test: `backend/tests/Feature/RepurposeServiceRepairTest.php`

**Per service:** replace the `try{ runRepurposeSync }catch + if(!success) + parseJsonObject + if(null||empty key)` block with:
```php
$res = $this->runRepurposeParsed($prompt, '<phase>', ['<keys>'], (string) config('services.repurpose.model_<x>', 'sonnet') [, $refs]);
if (!$res['success']) {
    Log::error('[<Tag>] unparseable / ...', ['job'=>$job->id, 'output_head'=>mb_substr($res['output'],0,500), 'repaired'=>$res['repaired']]);
    return ['success'=>false, '<payloadKey>'=>null, 'error'=>'<existing_error_string>'];
}
$parsed = $res['parsed'];
```
Keep each service's existing post-processing (research's `corrected_count`, the
success `Log::info`) and its existing return shape + error strings. Preserve the
top-level `try/catch` only if `runRepurposeParsed` can throw — it does not (it
swallows exec failures into the return), so the outer catch can be dropped or kept
as defense; keep a thin catch returning `exec_error: ...` to be safe.

**Steps:**
1. Write failing test for end-to-end repair through a service. Expected error: assertion mismatch (service still returns failure on the bad→good sequence because it isn't wired yet). Test for rewrite: `RepurposeJob::factory()->create(['status'=>'researched','research'=>['verdicts'=>[['claim'=>'x','status'=>'ok']]],'extracted'=>['claims'=>['x'],'slides'=>[['text'=>'s']],'narrative'=>'n']])`; `config(['services.repurpose.driver'=>'local'])`; `Process::fake` seq bad→`{"title":"t","body":"<p>x</p>","excerpt":"e","meta_keywords":"a,b","sources_appendix":[]}`; assert `app(RepurposeRewriteService::class)->rewrite($job)` returns `success:true` with `rewritten['title']==='t'`.
2. Run test in Docker, confirm it fails for the expected reason.
3. Wire all three services to `runRepurposeParsed`.
4. Run the new test + the full existing repurpose suite, confirm all pass.
5. Commit: `refactor(repurpose): route vision/research/rewrite through runRepurposeParsed (repair-retry live)`

**Test cases (`RepurposeServiceRepairTest`):**
- `test_rewrite_recovers_on_repair` — bad→valid title+body → `success`, repaired path used.
- `test_rewrite_still_fails_after_repair_keeps_error_string` — bad→bad → `success:false`, `error==='rewrite_unparseable'`.
- `test_research_recovers_on_repair` — bad→`{"verdicts":[{"claim":"x","status":"ok"}]}` → `success`, `corrected_count` computed.
- `test_vision_recovers_on_repair` — create a temp slide dir under `storage_path('app/repurpose/test-<id>/')` with one `slide-01.jpg` + `caption.txt`, set `slides_path`; seq bad→`{"claims":["c"],"slides":[],"caption":"","narrative":"n"}` → `success`. (Clean up the temp dir in `tearDown`.)

**Verification:**
- [ ] `php -l` clean on the 3 services
- [ ] New `RepurposeServiceRepairTest` green in Docker
- [ ] **Existing repurpose suite still green** (job tests mock the service → unaffected): run `--filter='Repurpose|CaptureInstagramPost|ExtractSlideContent|ResearchRepurposeClaims|RewriteRepurposeContent|SlideVision'`
- [ ] Error strings (`vision_unparseable`/`research_unparseable`/`rewrite_unparseable`) preserved on terminal failure
- [ ] No placeholder/TODO comments

---

### Phase E: Docs + memory sync

**Estimated time:** 6 minutes

**Files:**
- Modify: `/CLAUDE.md` (root) — append a "Last Updated" changelog entry under the IG Repurpose context
- Modify: `backend/.env.example` (confirm `REPURPOSE_TIMEOUT=900` documented — done in Phase A; verify)
- Modify: `/Users/alisadikin/.claude/projects/-Users-alisadikin-Drive-D-Projects-Portfolio-v2/memory/` — add `repurpose-llm-hardening.md` + MEMORY.md pointer (root-cause + fix recipe for next session)

**Steps:**
1. Summarize the ship in the root CLAUDE.md changelog: root cause (Sonnet large-JSON truncation/unescaped-quote/timeout), Opt 2 (300→900 CLI + 1920 job timeouts), Opt 3 (`runRepurposeParsed` repair-retry + strict-JSON directive), files touched, test counts, NOT pushed.
2. Write the memory note (type: project) — one fact: "repurpose LLM JSON failures are non-deterministic; retry usually works; hardened via runRepurposeParsed repair-retry + 900s budget".
3. Commit: `docs(repurpose): sync CLAUDE.md + memory for LLM hardening (timeout + repair-retry)`

**Verification:**
- [ ] Root CLAUDE.md changelog entry present + accurate file list
- [ ] MEMORY.md pointer line added
- [ ] No code changed in this phase

---

## Test & Run Commands (Docker — no host PHP)

```bash
# from backend/ — sqlite :memory:, scoped to the touched surface
docker run --rm -v "$PWD":/app -w /app serversideup/php:8.2-cli \
  php artisan test --filter='RepurposeTimeoutConfig|RepurposePromptDirective|RepurposeRepairRetry|RepurposeServiceRepair'

# regression guard — full repurpose family
docker run --rm -v "$PWD":/app -w /app serversideup/php:8.2-cli \
  php artisan test --filter='Repurpose|SlideVision|ExtractSlideContent|ResearchRepurposeClaims|RewriteRepurposeContent|CaptureInstagramPost'

# syntax
for f in <changed .php files>; do docker run --rm -v "$PWD":/app -w /app serversideup/php:8.2-cli php -l "$f"; done
```

## Operator post-deploy

- Push → `deploy.sh` carries the config default (no env edit required; the 900s
  budget + 1920s job timeouts ship in code). Optionally set `REPURPOSE_TIMEOUT` in
  the VPS `.env` to override.
- The 4 currently-failed jobs (#3/#5/#7/#8): retry them from Social Studio — the
  diagnosed coin-flip means retry alone often succeeds now, and the repair-retry +
  900s budget materially raise the odds.
- No migration, no FSM change, no worker reconfig (the existing `portfolio-queue`
  systemd worker enforces the new `$timeout` automatically).

## Red-flag self-check

- [x] Data Integration Map present (real sources, no guessing)
- [x] Every phase has a TDD step-1 hard-gate (`Write failing test … Expected error: …`)
- [x] Every phase has a Verification block
- [x] CLAUDE.md read first (architecture context grounded in real files)
- [x] No placeholder language; repair-retry is a real integration
- [x] Phases ≤ ~15 min each
