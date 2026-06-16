# Source-Mirror Carousel on the "Regenerate all images" Button

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Make the admin **"Regenerate all images"** button on an IG-repurpose carousel draft
(e.g. `https://alisadikinma.com/admin/sosmed-drafts/162`) produce a **source-mirrored**
carousel: **one slide per source tool** with **per-tool detail**, where the slide count
**follows the source caption's tool list** (10 tools → ~12 slides: cover + 10 + cta)
instead of the current hard-cap of 7 re-narrated slides.

The mirroring logic already exists (`RepurposeCarouselBuilder`) and is wired into the
**auto-pipeline** behind a flag — but it is **unreachable from the regenerate button**,
which bypasses that branch entirely (see Architecture Context). This plan wires the
builder into the button path and turns it on by default for repurpose drafts. Scope is
**the regenerate button only** — the auto-pipeline stays on `/carousel-gen` (its 360s
budget can't hold sequential per-slide authoring; that path is a separate, parallelization-
gated follow-up).

## Architecture Context

(Pulled from root `/CLAUDE.md` + verified against code 2026-06-16.)

### The bug being fixed (from the gaspol-review of draft 162)

1. **Button → wrong path.** `POST /admin/linkedin-drafts/{id}/regenerate-images`
   → `LinkedInDraftController::regenerateAllImages` ([line 1218](../../backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L1218))
   → dispatches `RegenerateLinkedInCarouselContent`.
   That job calls `LinkedInGenerationService::dispatchCarouselGenEngine()` **directly**
   ([RegenerateLinkedInCarouselContent.php:126](../../backend/app/Jobs/RegenerateLinkedInCarouselContent.php#L126)) —
   it does **NOT** call `applyCarouselGenAdapter()`. The source-mirror branch lives ONLY
   inside `applyCarouselGenAdapter` ([LinkedInGenerationService.php:1025](../../backend/app/Services/LinkedInGenerationService.php#L1025)),
   so the button can never trigger it.

2. **Hard cap 7.** The job passes `$brief = []` to `dispatchCarouselGenEngine`
   ([line 107](../../backend/app/Jobs/RegenerateLinkedInCarouselContent.php#L107)) →
   `buildCarouselGenPrompt` → `inferTargetSlides([])` → `default => 7`
   ([LinkedInGenerationService.php:1235](../../backend/app/Services/LinkedInGenerationService.php#L1235)).
   No source-tool-count signal is read at all.

### The mirroring engine (already built, reuse as-is)

- `App\Services\RepurposeCarouselBuilder` ([file](../../backend/app/Services/RepurposeCarouselBuilder.php)):
  - `buildForDraftId(int $draftId): array` — resolves the source `RepurposeJob`
    (`RepurposeJob::where('linkedin_post_id', $draftId)`), parses the caption's numbered
    tool list, and authors **cover + 1 slide per tool + cta**, each bilingual
    (`copy_id`/`copy_en`) + sketchnote `image_prompt`. Returns `[]` when no tool list
    parses (→ caller must fall back).
  - Slide count = `count($tools) + 2`, capped at `MAX_TOOL_SLIDES = 20`.
  - Per-slide authoring is **sequential** CLI calls via `runSlideAuthor` →
    `RunsRepurposeClaudeCli::runRepurposeParsed` (one repair retry per slide on parse
    failure; a hard failure degrades to a deterministic non-empty fallback slide, never
    breaks the carousel).
  - Slide shape from `slide()`: `{slide_number, layout_hint, copy_id, copy_en,
    image_prompt, image_status:'pending', image_url:null, (+is_cover|is_cta)}` — the
    SAME shape the auto-pipeline source-mirror path produces (already exercised by 38
    existing tests + the downstream render path).

### Budget reality (verified 2026-06-16)

- `RegenerateLinkedInCarouselContent::$timeout = 1200` (20 min). Worker
  `portfolio-queue.service` runs `--queue=default --timeout=1260 --max-time=3600`
  (single worker). So 20 min is the effective ceiling.
- Typical IG listicle = 7–12 tools → 9–14 sequential authoring calls. Each single-slide
  author is a short prompt (tiny JSON out) ≈ 30–90s → ≈ 5–16 min, fits 20 min.
- Pathological 20-tool lists could approach/exceed 20 min. `buildSlides` is an in-memory
  loop — **nothing is persisted until it returns**, so a job timeout leaves the old slides
  intact (no partial write); operator retries. Accepted tradeoff per the chosen
  "sequential first" strategy. Parallelization is the documented follow-up.
- `DB_QUEUE_RETRY_AFTER=720` < job `$timeout`. With a SINGLE worker this can't duplicate
  (the worker is busy, not polling). `WithoutOverlapping` is added as cheap insurance
  against the documented future multi-worker scaling.

### Decision: a SEPARATE flag for the button path (ADR-worthy)

The existing `config('linkedin.repurpose_source_mirror')` (env `LINKEDIN_REPURPOSE_SOURCE_MIRROR`,
default `false`) gates the **auto-pipeline** branch, which has the unsafe 360s budget.
Flipping ITS default to `true` would silently enable source-mirror in the auto-pipeline →
regression. Therefore we introduce a **second, independent** flag for the button path,
defaulting `true`:

- `linkedin.repurpose_source_mirror_regenerate` (env `LINKEDIN_REPURPOSE_SOURCE_MIRROR_REGENERATE`, default **true**) → button uses source-mirror.
- `linkedin.repurpose_source_mirror` (auto-pipeline) → **unchanged**, default stays `false`.

This honors all three product decisions: scope = regenerate-button-only, sequential,
default-ON for the button. The flag remains a no-redeploy revert lever.

## Tech Stack

- Laravel 12 / PHP 8.2, Pest/PHPUnit (`php artisan test`).
- Queued jobs (`ShouldQueue`), `Illuminate\Queue\Middleware\WithoutOverlapping`.
- Existing services: `RepurposeCarouselBuilder`, `LinkedInGenerationService`,
  `GenerateLinkedInCarouselImages` job. No new external integration, no frontend change
  (the button + endpoint already exist).
- Config via `config/linkedin.php` + `env()`.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Source tool list → slides | `RepurposeJob.extracted.caption` → parsed | `RepurposeCarouselBuilder::buildForDraftId($draftId)` | Yes | Use directly |
| Is this draft a repurpose? | `RepurposeJob` linkage / `ContentIdea.source` | `LinkedInGenerationService::isRepurposeDraft($draft)` | Yes | Use directly |
| Button enable toggle | config/env | `config('linkedin.repurpose_source_mirror_regenerate')` | No | Create (default true) |
| Persist new slides | `linkedin_posts.carousel_slides` JSON | `LinkedInPost::update(['carousel_slides' => ...])` | Yes | Use existing |
| Render the slides | GeminiGen dispatch | `GenerateLinkedInCarouselImages::dispatch($draftId)` | Yes | Use existing |
| Fallback path | `/carousel-gen` SSH | `LinkedInGenerationService::dispatchCarouselGenEngine()` | Yes | Keep as-is (fallback) |
| Concurrent-run guard | queue middleware | `WithoutOverlapping($draftId)` | Yes (Laravel) | Add to job |

## Phases

Order: A (config) → B (wire + tests, the core) → C (hardening) → D (docs + verify).
A and C are independent of each other; B depends on A. No frontend/UI phase.

---

### Phase A: Add the button-path flag

**Estimated time:** 5 minutes

**Files:**
- Modify: `backend/config/linkedin.php`
- Test: `backend/tests/Unit/LinkedInConfigFlagTest.php` (create)

**Steps:**
1. Write failing test for the new flag default. Expected error: `Failed asserting that null matches expected true` (config key absent).
   ```php
   test('repurpose_source_mirror_regenerate defaults to true', function () {
       expect(config('linkedin.repurpose_source_mirror_regenerate'))->toBeTrue();
   });
   test('auto-pipeline repurpose_source_mirror default is unchanged (false)', function () {
       expect(config('linkedin.repurpose_source_mirror'))->toBeFalse();
   });
   ```
2. Run `php artisan test --filter=LinkedInConfigFlagTest`, confirm it fails for the expected reason (new key resolves to null).
3. Add to `config/linkedin.php` directly under the existing `repurpose_source_mirror` line:
   ```php
   // Button-path source-mirror (2026-06-16). SEPARATE from the auto-pipeline
   // flag above: the "Regenerate all images" job has a 20-min budget so it can
   // run sequential per-slide authoring safely, whereas the auto-pipeline (360s)
   // cannot. Default ON for the button; revert lever via env.
   'repurpose_source_mirror_regenerate' => env('LINKEDIN_REPURPOSE_SOURCE_MIRROR_REGENERATE', true),
   ```
4. Run the test, confirm both assertions pass.
5. Commit: `feat(linkedin): button-path source-mirror flag (default on, separate from auto-pipeline gate)`

**Verification:**
- [ ] `php artisan test --filter=LinkedInConfigFlagTest` passes (2 assertions).
- [ ] Existing `repurpose_source_mirror` default is still `false` (auto-pipeline untouched).
- [ ] No placeholder/TODO comments in new code.

---

### Phase B: Wire source-mirror into the regenerate job (CORE)

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Jobs/RegenerateLinkedInCarouselContent.php`
- Test: `backend/tests/Feature/RegenerateCarouselSourceMirrorTest.php` (create)

**Steps:**
1. Write failing test `test_repurpose_with_parseable_list_uses_source_mirror_and_skips_carousel_gen`. Expected error: `Mockery: dispatchCarouselGenEngine() was called` (the unimplemented job still calls `/carousel-gen` instead of the builder).
   - Arrange: a `LinkedInPost` carousel draft + linked `post` with slug; flag on.
   - `$this->mock(LinkedInGenerationService::class)` → `isRepurposeDraft` returns `true`, `shouldNotReceive('dispatchCarouselGenEngine')`.
   - `$this->mock(RepurposeCarouselBuilder::class)` → `buildForDraftId($id)` returns 4 canned slides (cover + 2 tools + cta).
   - `Queue::fake()`.
   - Act: `(new RegenerateLinkedInCarouselContent($draft->id))->handle($genMock, $adapterMock, ...)` (or `dispatchSync`).
   - Assert: `$draft->fresh()->carousel_slides` === the 4 canned slides; `slide_asset_urns` null; `last_error` null; `Queue::assertPushed(GenerateLinkedInCarouselImages::class)`.
2. Run `php artisan test --filter=RegenerateCarouselSourceMirrorTest`, confirm it fails for the expected reason.
3. Implement: in `RegenerateLinkedInCarouselContent::run()`, immediately AFTER `$isRepurpose = $generation->isRepurposeDraft($draft);` (currently [line 117](../../backend/app/Jobs/RegenerateLinkedInCarouselContent.php#L117)), insert:
   ```php
   if ($isRepurpose && config('linkedin.repurpose_source_mirror_regenerate', true)) {
       $sourceSlides = app(\App\Services\RepurposeCarouselBuilder::class)->buildForDraftId($this->draftId);
       if ($sourceSlides !== []) {
           $draft->update([
               'carousel_slides'  => $sourceSlides,
               'slide_asset_urns' => null,
               'last_error'       => null,
           ]);
           Log::info('[RegenerateCarouselContent] source-mirror slides assembled (skipping /carousel-gen)', [
               'draft_id'    => $this->draftId,
               'slide_count' => count($sourceSlides),
           ]);
           GenerateLinkedInCarouselImages::dispatch($this->draftId);
           return;
       }
       Log::info('[RegenerateCarouselContent] source-mirror yielded no slides — falling back to /carousel-gen', [
           'draft_id' => $this->draftId,
       ]);
   }
   ```
   (The existing `/carousel-gen` block below remains the fallback, unchanged.)
4. Run the test, confirm it passes.
5. Commit: `feat(linkedin): regenerate button source-mirrors IG-repurpose carousels (1 slide/tool, follows source count)`

**Additional test steps (same file, repeat write→run→pass→commit per test):**
6. `test_no_tool_list_falls_back_to_carousel_gen`: builder returns `[]` → assert `dispatchCarouselGenEngine` IS called once and the carousel-gen adapter path runs (mock it to return a valid envelope/slides). Expected first-run failure: builder result short-circuits / `dispatchCarouselGenEngine` not called.
7. `test_flag_off_uses_carousel_gen`: `config()->set('linkedin.repurpose_source_mirror_regenerate', false)` → builder `shouldNotReceive('buildForDraftId')`; `dispatchCarouselGenEngine` called.
8. `test_non_repurpose_never_calls_source_mirror`: `isRepurposeDraft` → false; builder `shouldNotReceive('buildForDraftId')`; `dispatchCarouselGenEngine` called.
9. `test_slide_count_follows_source_tool_count`: builder returns 12 slides (cover + 10 tools + cta) → assert `count($draft->fresh()->carousel_slides) === 12` (proves no 7-cap).

**Verification:**
- [ ] `php artisan test --filter=RegenerateCarouselSourceMirrorTest` passes (5 tests).
- [ ] Source-mirror path persists exactly the builder's slides and dispatches `GenerateLinkedInCarouselImages` without calling `/carousel-gen`.
- [ ] Fallback (`[]` / flag off / non-repurpose) preserves the existing `/carousel-gen` behavior — verified by mock expectations.
- [ ] FSM state, caption, hashtags, `link_comment`, draft id are untouched (the job never transitions FSM; only `carousel_slides` + `slide_asset_urns` + `last_error` change).
- [ ] No placeholder/TODO comments in new code.

---

### Phase C: Concurrent-run hardening (`WithoutOverlapping`)

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Jobs/RegenerateLinkedInCarouselContent.php`
- Test: `backend/tests/Feature/RegenerateCarouselSourceMirrorTest.php` (add 1 test)

**Steps:**
1. Write failing test `test_job_declares_without_overlapping_middleware`. Expected error: `Failed asserting that array {} contains an instance of WithoutOverlapping` (no `middleware()` method yet).
   ```php
   $mw = (new RegenerateLinkedInCarouselContent(1))->middleware();
   expect($mw)->toContainOnlyInstancesOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class);
   ```
2. Run the test, confirm it fails (method absent → returns nothing / error).
3. Implement a `middleware()` method on the job:
   ```php
   public function middleware(): array
   {
       return [
           (new \Illuminate\Queue\Middleware\WithoutOverlapping($this->draftId))
               ->dontRelease()       // a duplicate reservation is dropped, not requeued
               ->expireAfter(1320),  // > job $timeout(1200) + worker --timeout(1260) margin
       ];
   }
   ```
4. Run the test, confirm it passes. Re-run the full `RegenerateCarouselSourceMirrorTest` to confirm no regression.
5. Commit: `fix(linkedin): guard regenerate job with WithoutOverlapping (safe under future multi-worker)`

**Verification:**
- [ ] `php artisan test --filter=RegenerateCarouselSourceMirrorTest` still fully green.
- [ ] Middleware keyed on `draftId`, `dontRelease()`, `expireAfter` > worker timeout.
- [ ] Phase B behavior unchanged (middleware is transport-layer only).

---

### Phase D: Docs + manual verification on prod draft 162

**Estimated time:** 12 minutes

**Files:**
- Modify: root `/CLAUDE.md` — (a) the `RegenerateLinkedInCarouselContent` behavior note,
  (b) the `settings group: linkedin` flag table (add `repurpose_source_mirror_regenerate`),
  (c) the "Recent Changes" log (newest entry).
- Modify: Obsidian vault — append one `hot.md` line + note the standard under the
  IG-repurpose section (via `obsidian` MCP or direct file write to
  `/Users/alisadikin/Drive-D/Obsidian-Vault`).
- Run: `graphify update .` (per CLAUDE.md after-change rule).

**Steps:**
1. Update the three root `/CLAUDE.md` spots above with the new flag + button behavior
   (one slide per source tool, follows source count; auto-pipeline still `/carousel-gen`).
2. Add `hot.md` entry (Indonesian, 1–3 lines, high-signal) summarizing the fix +
   the two-flag separation rationale. Link `[[...]]` to the IG-repurpose note.
3. Run `graphify update .` to re-index.
4. **Manual prod verification** (after deploy/push — operator-gated per Git Push Policy):
   - Confirm draft 162's source `RepurposeJob.extracted.caption` actually contains a
     numbered tool list (`SELECT ... ` via tinker or admin). If it does NOT, the draft
     legitimately falls back to `/carousel-gen` (source-mirror only applies to listicle
     sources) — document that as expected, not a bug.
   - Click "Regenerate all images" on draft 162; observe `carousel_slides` count ==
     `tools + 2` and per-tool detail; confirm `laravel.log` shows
     `source-mirror slides assembled`.
5. Commit: `docs: source-mirror regenerate button + two-flag separation` (docs only).

**Verification:**
- [ ] root `/CLAUDE.md` reflects the new flag, the button behavior, and the changelog entry.
- [ ] `hot.md` updated; `graphify update .` ran clean.
- [ ] (post-deploy) draft 162 regenerate yields source-count-faithful slides, OR a
      documented fallback if its caption has no numbered list — log line confirms which path ran.
- [ ] No code changed in this phase (docs/verification only).

---

## Out of Scope (explicit)

- **Parallelizing per-slide authoring** — required only for the AUTO-pipeline (360s budget).
  Not needed for the button (20-min budget). Separate follow-up; keep the existing
  `repurpose_source_mirror` (auto) flag OFF.
- **Per-slide author timeout tuning** (`REPURPOSE_TIMEOUT_SLIDE_AUTHOR`) — optional safety
  for very long (>~15-tool) lists. Deferred; current per-call 300s timeout + in-memory
  loop (no partial write on timeout) is acceptable for typical lists.
- **Frontend changes** — none; the button + endpoint already exist.
- **Slide-quality evals** — the `RepurposeCarouselBuilder` output is unchanged (38 existing
  tests cover it); this plan only changes WHERE it is invoked.

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Source caption has no numbered list → not mirrored | Medium | Low | Builder returns `[]` → clean fallback to `/carousel-gen` (logged). Expected behavior. |
| Very long list (>15 tools) exceeds 20-min job timeout | Low | Low | In-memory build = no partial write; old slides intact; operator retries. `MAX_TOOL_SLIDES=20` caps absurd lists. |
| Default-ON flag accidentally also flips auto-pipeline | — | High | Eliminated by design: separate flag; auto `repurpose_source_mirror` default untouched (Phase A test asserts it). |
| Duplicate run under future multi-worker scaling | Low | Medium | `WithoutOverlapping($draftId)->dontRelease()` (Phase C). |
| Downstream render expects `copy` not `copy_id/copy_en` | Low | Medium | Auto-pipeline source-mirror already produces this exact shape and renders fine (38 tests); Phase B asserts slides persist; Phase D visual check confirms render. |
