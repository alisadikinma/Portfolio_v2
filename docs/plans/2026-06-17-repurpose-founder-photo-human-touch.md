# Auto Founder/People-Photo Human-Touch for Repurpose Carousels

**Date:** 2026-06-17
**Branch:** feat/video-full-rebrand (or a fresh branch)
**Status:** Design approved — ready for implementation plan
**Trigger cases (both confirmed repurpose carousels with source photos on disk):**
- draft 172 (`/admin/sosmed-drafts/172`) → RepurposeJob 33, slide 3 "SIAPA CURSOR? 4 MIT Dropouts" icon-only; source `storage/app/repurpose/33/slide-01..09.jpg` has the 4 founders' real photos (**group / N faces**).
- draft 161 (`/admin/sosmed-drafts/161`) → RepurposeJob 20, slide 2 "SIAPA ASHISH VASWANI?" icon-only; source `storage/app/repurpose/20/slide-01..13.jpg` has Vaswani's real photo (**single person / 1 face**).

Both share the "SIAPA \<Name\>?" profile-slide headline — the matcher's primary signal.

## Design

### Problem

Repurpose carousel body slides that are *about specific real people* (founders, CEOs, a team)
render as pure sketchnote infographics (icons + text) with **no human presence**. The real
photos of those people already exist in the captured source IG slides, but nothing pulls them
through into the rebranded slide.

Operator intent (verbatim): *"kapan pun dibutuhkan foto founder, perlu tambahkan supaya ada
human touch nya"* — **general for all topics**, not Cursor-specific.

### Locked decisions

1. **Mechanism = auto crop + composite** (zero operator effort + real photos). Not Wikidata
   resolution (founders aren't notable enough), not GeminiGen re-draw (loses real likeness /
   doodles non-famous faces).
2. **Placement = reserved photo strip** — the slide prompt reserves a blank horizontal band;
   backend composites a row of real face cut-outs there with hand-drawn sketchnote frames +
   labels. No content overlap, deterministic placement.
3. **Scope = ALL repurpose-carousel topics**, vision-driven (no hardcoded names/topics).
   Repurpose-only by nature — only repurpose drafts have captured source slides; blog→carousel
   is a no-op (zero regression).
4. **Auto, but design leaves room for a manual override** as a follow-up (not v1).

### Scope & trigger

- Gate: `LinkedInPost::isRepurpose()` is true AND a linked `RepurposeJob` exists with a
  `slides_path` whose dir has `slide-*.jpg` files.
- Runs **once per draft**, idempotent via a per-slide `person_photos_enriched` flag.
- Fully **fail-safe**: any miss (no job / no source files / no faces / no matching generated
  slide / crop or composite failure) leaves the slide untouched — never blocks image dispatch.
- **Hook point:** beside the existing cover enrichment in
  `LinkedInCarouselImageService::dispatchAllSlides` (and the single-slide re-render path), so it
  participates in "Regenerate All Images" + per-slide re-render — exactly mirroring how
  `CarouselCoverFigureEnricher` is wired.

### Pipeline — new app-side `CarouselPersonPhotoEnricher`

App-side (not plugin) for the same reason `CarouselCoverFigureEnricher` is app-side: it needs
access to captured source slides + Intervention crop + post-render compositing the plugin can't
reach. The plugin/bundle stays the source of truth for the *visual standard* (sketchnote framed-
photo treatment); the app owns the *data plumbing*.

1. **Locate faces** — ONE Claude-CLI vision pass over `storage/app/repurpose/{job}/slide-*.jpg`
   (reuse the `SlideVisionExtractor` CLI image-input pattern). Returns per source slide:
   `faces: [{ label, bbox:[x,y,w,h] normalized 0..1 }]`. New `SourceFaceLocator` service.
2. **Match to a generated slide** — LLM call over the generated slide copies (`copy_id` /
   `copy_en`) + the located source faces → "which generated slide index depicts these specific
   real people, if any?" General, not topic-keyed. Heuristic fallback: slide copy matching
   people tokens (founder/dropout/CEO/team/co-founder/name) → the source slide with the most
   faces. Can fold into the same vision call to save a round-trip.
3. **Crop** — Intervention Image 3.11 (existing dep) crops each padded bbox from `slide-NN.jpg`
   → public cut-outs at `storage/app/public/repurpose-faces/{draft}/{slide}/face-NN.png` with
   **unique filenames** (immutable-CDN lesson — never reuse a fixed `/storage` filename).
4. **Reserve band** — ONLY after ≥1 face is successfully cropped (so a failure never leaves an
   empty band): set the slide flag, append an image-prompt mandate ("leave a clear horizontal
   band in the upper region empty — solid card colour, no icons/text — for a row of photo
   cut-outs"), store `person_photo_refs` (URLs + labels) + the band geometry, force re-render
   (`image_status='pending'`, `image_url=null`).
5. **Composite post-render** — in `LinkedInCarouselImageService::handleWebhook`, after
   downloading the rendered slide PNG, if `person_photo_refs` present → Playwright HTML overlay
   (`scripts/repurpose/carousel-person-strip.cjs`, mirrors `video-chrome.cjs`) drops the real
   cut-outs into the reserved band with hand-drawn sketchnote frames + labels → composited PNG
   (unique filename). `SharedDir::ensure(dir)` for the cross-user write (claudesn worker vs
   www-data 0755 dir — documented gotcha). Composite failure → fall back to the plain rendered
   slide.

### Idempotency & re-render

- `person_photos_enriched` flag on the targeted slide stops the vision pass re-running on every
  dispatch.
- Single-slide re-render of that slide clears the flag (mirror of the cover figure-lock reset)
  so an explicit operator re-render re-runs detection.

### Data Integration Map

| Component | Data source | Existing? | Notes |
|---|---|---|---|
| Source slide images | `RepurposeJob.slides_path` → `storage/app/repurpose/{id}/slide-*.jpg` | ✅ InstagramCaptureService | already persisted |
| Face locate | Claude CLI vision (image input) | ✅ pattern (SlideVisionExtractor) | new `SourceFaceLocator`; bbox approximate |
| Slide↔people match | Claude CLI over generated copy + source faces | new | general; heuristic fallback |
| Crop | Intervention Image 3.11 | ✅ dep | padded bbox |
| Reserved band | image_prompt mandate + new `person_photo_refs` slide keys | ✅ pattern (CarouselSlideEnhancer prepend) | new slide JSON keys |
| Composite | Playwright HTML overlay | ✅ pattern (VideoChromeRenderer / video-chrome.cjs) | new `carousel-person-strip.cjs` |
| Render hook | `LinkedInCarouselImageService::dispatchAllSlides` + `handleWebhook` | ✅ mirrors cover enricher | idempotent flag |
| Dir perms | `App\Support\SharedDir::ensure` | ✅ documented gotcha | cross-user write |

### Slide JSON additions (`linkedin_posts.carousel_slides[]`)

```json
{
  "person_photos_enriched": true,          // backend — idempotency lock
  "person_photo_refs": [                    // backend — cropped cut-out URLs + labels
    { "url": "https://.../repurpose-faces/172/2/face-01-<rand>.png", "label": "Co-founder" }
  ],
  "person_photo_band": { "y": 0.18, "h": 0.22 }  // backend — reserved band geometry (normalized)
}
```

### Honest risks (operator chose the most ambitious path)

1. **Vision bbox accuracy** — crops may be loose/off. Mitigation: bbox padding + framed cut-outs
   tolerate slack. Upgrade path: a Node face-detection lib for tight crops if vision proves
   unreliable.
2. **Slide↔people matching generality** — LLM match is fuzzy; could mis-target or miss.
   Fail-safe leaves the slide unchanged; no wrong-face risk because a miss = no composite.
3. **Real photos on a hand-drawn card** — leans on the "framed photo pinned to the board"
   sketchnote treatment to feel intentional, not pasted.
4. **Added cost/latency** — +1–2 CLI vision calls + a composite step per repurpose carousel.
   Bounded to repurpose carousels only.

### Cross-project standard (write-back)

Per `~/CLAUDE.md`: the *visual treatment* (sketchnote framed-photo / pinned-polaroid for real
people) is an image standard → update vault `30-Knowledge/image-gen-shared.md` §0 +
`10-Identity/visual-identity.md` when shipped. The *data plumbing* (source-photo reuse) is
app-specific and documented here + in root CLAUDE.md.

### Out of scope (v1)

- Operator manual override (pick source slide + nudge crop) — viable follow-up.
- Non-repurpose (blog→carousel) people photos — would need the Wikidata path (cover enricher
  already does this for single notable figures on the cover only).
- More than the single best-match people slide per carousel — start with one, generalize later.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations (captured source slides, Claude CLI vision,
> Intervention crop, Playwright composite, GeminiGen webhook). During execution, NEVER substitute
> placeholders for real data sources without explicit user approval. If a data source doesn't
> exist yet, STOP and ask.

### Goal

Give IG-repurpose carousels human touch: when a generated body slide is about specific real
people (founders/CEOs/team) and the captured source IG slides contain their photos, auto-locate
the faces (vision), crop them (Intervention), reserve a prompt band on the matched slide, and
composite the real cut-outs into that band post-render (Playwright) with hand-drawn sketchnote
frames. General across all repurpose topics, vision-driven, repurpose-only, idempotent,
fail-safe. Mirrors the existing `CarouselCoverFigureEnricher` wiring.

### Architecture Context (from root + backend CLAUDE.md)

- **Hook points** — `LinkedInCarouselImageService::dispatchAllSlides()` already calls
  `enrichCoverFigure($draft, $slides)` before the per-slide dispatch loop (insert
  `enrichPersonPhotos` right after); `::handleWebhook()` (line ~546) downloads each rendered slide
  via `downloadAndStore()` (line ~594) then sets `carousel_slides[i].image_url` (line ~620) —
  composite hook goes between download and persist.
- **CLI vision pattern** — `App\Services\Concerns\RunsRepurposeClaudeCli::runRepurposeParsed($prompt,
  $phase, $requiredKeys, $model, $refsFile)`. Image input = embed the local file path in the
  prompt text (`SlideVisionExtractor::buildPrompt`: "read the image file at this path: {$path}").
- **Source slides** — `RepurposeJob.slides_path` → `storage/app/repurpose/{id}/slide-*.jpg` (set
  by `InstagramCaptureService`). `LinkedInPost::isRepurpose()` gates repurpose drafts; resolve the
  job via the same linkage the cover enricher / draft detail use.
- **Crop** — Intervention Image 3.11 (existing dep).
- **Composite** — Playwright overlay pattern: `scripts/repurpose/video-chrome.cjs` +
  `VideoChromeRenderer` PHP wrapper. New `carousel-person-strip.cjs` + wrapper mirror it.
- **Dir perms** — `App\Support\SharedDir::ensure($absDir)` for cross-user (claudesn worker vs
  www-data) writes. **Unique filenames** for every regenerable `/storage` asset (immutable-CDN
  lesson) — append `bin2hex(random_bytes(8))`.
- **Test seam pattern** — `VideoHookSceneAuthor::runHookAuthor()` wraps the CLI call as a
  protected method so tests subclass + inject canned JSON. Mirror for `SourceFaceLocator`.
- **Idempotency/reset pattern** — `CarouselCoverFigureEnricher` (`figure_enriched`) +
  `LinkedInCarouselImageService::resetCoverFigureLockIfTargetingCover` (single-slide re-render
  clears the lock).

### Tech Stack

PHP 8.2 / Laravel 12, Pest tests, Intervention Image 3.11, Claude CLI (sonnet) via
`RunsRepurposeClaudeCli`, Node + Playwright (`*.cjs`). No migration (additions are
`carousel_slides[]` JSON keys). No new env vars (reuse `carousel-gen.refs_pipeline` /
`services.repurpose.*` model config).

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Source slide images | `RepurposeJob.slides_path` → `storage/app/repurpose/{id}/slide-*.jpg` | `RepurposeJob` model | Yes | Use existing |
| Repurpose gate + job linkage | `LinkedInPost::isRepurpose()` + linked RepurposeJob | model methods | Yes | Use existing |
| Face locate (vision) | Claude CLI image input | `RunsRepurposeClaudeCli::runRepurposeParsed` | Yes (trait) | New `SourceFaceLocator` service |
| Slide↔people match | generated `copy_id`/`copy_en` + located faces | heuristic + optional CLI | No | Create in enricher |
| Face crop | source `slide-NN.jpg` | Intervention Image 3.11 | Yes (dep) | New crop method |
| Cropped cut-out storage | `storage/app/public/repurpose-faces/{draft}/{slide}/face-NN-<rand>.png` | `Storage::disk('public')` + `SharedDir::ensure` | Yes | New dir |
| Reserved band + refs | `carousel_slides[i].{person_photos_enriched,person_photo_refs,person_photo_band}` | `LinkedInPost.carousel_slides` JSON | Yes (column) | New keys |
| Composite overlay | rendered slide PNG + cut-out URLs | `carousel-person-strip.cjs` + PHP wrapper | No | Create (mirror video-chrome) |
| Dispatch hook | `dispatchAllSlides` | `LinkedInCarouselImageService` | Yes | Add `enrichPersonPhotos` call |
| Webhook composite hook | `handleWebhook` | `LinkedInCarouselImageService` | Yes | Add composite step |

### Non-deterministic phases — eval contract

`SourceFaceLocator` (vision face locate) and the slide↔people match are LLM steps. **Phase A** and
**Phase D** must ship `docs/evals/repurpose-person-photo.md` (via `gaspol-eval`) with real
fixtures (job 33 source slides + generated copy) as the success contract — pass@k, not just unit
tests. `gaspol-verify` runs these.

---

### Phase A: `SourceFaceLocator` — vision face locate over source slides

**Estimated time:** 12 min

**Files:**
- Create: `backend/app/Services/SourceFaceLocator.php`
- Test: `backend/tests/Unit/SourceFaceLocatorTest.php`
- Create: `docs/evals/repurpose-person-photo.md` (locate section)

**Steps:**
1. Write failing test for `SourceFaceLocator::locate(array $slidePaths): array` returning
   `[{ slide_file, faces:[{label, bbox:[x,y,w,h]}] }]` from injected canned CLI JSON. Expected
   error: `Error: Class "App\Services\SourceFaceLocator" not found`.
2. Run test (`php artisan test --filter=SourceFaceLocatorTest`), confirm it fails for that reason.
3. Implement `SourceFaceLocator` using `RunsRepurposeClaudeCli`; embed each slide path in the
   prompt ("read the image file at this path: …"); prompt asks for normalized face bboxes + a
   short role label per face; required key `slides`; wrap the CLI call in a protected
   `runFaceLocate()` seam (mirror `VideoHookSceneAuthor::runHookAuthor`). Normalize/clamp bboxes
   to 0..1, drop malformed entries.
4. Run tests, confirm pass.
5. Write `docs/evals/repurpose-person-photo.md` locate fixtures (job 33 slide-04 → ≥1 founder
   face). Commit: "feat(repurpose): SourceFaceLocator vision face locator + eval".

**Verification:**
- [ ] `php artisan test --filter=SourceFaceLocatorTest` passes
- [ ] Returns clamped normalized bboxes from real CLI JSON shape; malformed entries dropped
- [ ] Test uses a subclass seam — no real CLI call in tests
- [ ] No placeholder/TODO comments in new code

---

### Phase B: `CarouselPersonPhotoEnricher` — gating, job/source resolution, idempotency

**Estimated time:** 12 min

**Files:**
- Create: `backend/app/Services/CarouselPersonPhotoEnricher.php`
- Test: `backend/tests/Unit/CarouselPersonPhotoEnricherGateTest.php`

**Steps:**
1. Write failing test: `enrich($draft)` returns false + mutates nothing when (a) draft not
   repurpose, (b) no linked RepurposeJob/slides_path, (c) no `slide-*.jpg` files, (d) target slide
   already `person_photos_enriched`. Expected error: `Error: Class
   "App\Services\CarouselPersonPhotoEnricher" not found`.
2. Run test, confirm it fails for that reason.
3. Implement the gates + constructor-inject `SourceFaceLocator` (+ later the cropper/matcher).
   Resolve source `slide-*.jpg` paths from `RepurposeJob.slides_path` (mirror
   `SlideVisionExtractor::resolveSlidePaths`). Every gate miss → return false, leave slides
   untouched, log. Wrap the whole body in try/catch (fail-safe).
4. Run tests, confirm pass.
5. Commit: "feat(repurpose): CarouselPersonPhotoEnricher gates + fail-safe skeleton".

**Verification:**
- [ ] Gate test passes; all four no-op paths leave `carousel_slides` byte-identical
- [ ] Non-repurpose + missing-source paths never throw
- [ ] No placeholder/TODO comments

---

### Phase C: Crop + reserve band + person_photo_refs (the mutation)

**Estimated time:** 14 min

**Files:**
- Modify: `backend/app/Services/CarouselPersonPhotoEnricher.php`
- Test: `backend/tests/Unit/CarouselPersonPhotoEnricherCropTest.php`
- Fixture: tiny test JPG under `backend/tests/fixtures/`

**Steps:**
1. Write failing test: given a stubbed locator returning one face bbox on a fixture slide + a
   matched slide index, `enrich()` crops the padded bbox (Intervention), writes a unique
   `repurpose-faces/{draft}/{slide}/face-01-<rand>.png`, sets `person_photo_refs`,
   `person_photo_band`, `person_photos_enriched=true`, and forces `image_status='pending'` +
   `image_url=null` on the matched slide. Expected error: assertion fail (refs not set / no file).
2. Run test, confirm it fails for that reason.
3. Implement: Intervention crop of padded bbox (pad ~12%, clamp to image bounds) →
   `SharedDir::ensure(dir)` → `Storage::disk('public')->put(unique filename)`; **only reserve the
   band after ≥1 crop succeeds**; append the band-reservation mandate to the slide `image_prompt`
   ("leave a clear horizontal band in the upper region empty — solid card colour, no icons/text —
   for a row of photo cut-outs"); set the new JSON keys; force re-render. Verify written file
   exists (mirror `downloadAndStore`'s `exists()` guard) — on write-fail, skip reservation
   (no empty band).
4. Run tests, confirm pass.
5. Commit: "feat(repurpose): crop source faces + reserve photo band on matched slide".

**Verification:**
- [ ] Crop test passes; cut-out PNG exists at a unique public path
- [ ] Band reserved ONLY when ≥1 face cropped (zero-face → no prompt mutation)
- [ ] Filenames carry a random suffix (immutable-CDN safe)
- [ ] `SharedDir::ensure` used for the output dir
- [ ] No placeholder/TODO comments

---

### Phase D: Slide↔people matcher (which generated slide gets the faces)

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Services/CarouselPersonPhotoEnricher.php`
- Test: `backend/tests/Unit/CarouselPersonPhotoMatchTest.php`
- Modify: `docs/evals/repurpose-person-photo.md` (match section)

**Steps:**
1. Write failing test: heuristic `matchPeopleSlide(array $slides, array $faces): ?int` returns the
   index of the slide that is a person/people profile. PRIMARY signal = the profile-headline
   pattern `^\s*(SIAPA|WHO\s+IS)\b` (both reported cases: "SIAPA ASHISH VASWANI?", "SIAPA CURSOR?
   4 MIT Dropouts") in `image_prompt`/`copy_id`/`copy_en`; SECONDARY = people tokens
   (founder|dropout|co-?founder|CEO|team|pendiri|author|engineer|insinyur). Returns null when none.
   Cover (`is_cover`/`layout_hint==='cover'`) and CTA (`is_cta`) slides are excluded — they're
   handled by the cover figure enricher. Works for **1 face (single person) and N faces (group)**
   alike. Expected error: method not found.
2. Run test, confirm it fails.
3. Implement heuristic-first matcher (headline pattern → token fallback); when ambiguous (≥2
   candidate slides) optionally refine via a light CLI call (reuse the trait) passing the generated
   copies + face labels → chosen index; fall back to heuristic on CLI miss. General — NO hardcoded
   names/topics. The face count placed = min(located faces for that person/group, a sane cap ~4).
4. Run tests, confirm pass; add match fixtures to the eval doc: Cursor copy → founders slide (4
   faces), Vaswani copy → "SIAPA ASHISH VASWANI?" slide (1 face).
5. Commit: "feat(repurpose): general profile-slide matcher (SIAPA/who-is headline + tokens)".

**Verification:**
- [ ] Match test passes incl. the no-match null path, the single-person (1-face) case, AND the
      group (N-face) case
- [ ] Cover + CTA slides excluded from matching
- [ ] No hardcoded topic/name strings
- [ ] CLI refine is fail-safe (heuristic fallback on miss)

---

### Phase E: `carousel-person-strip.cjs` composite + PHP wrapper

**Estimated time:** 14 min

**Files:**
- Create: `scripts/repurpose/carousel-person-strip.cjs`
- Create: `backend/app/Services/CarouselPersonStripRenderer.php`
- Test: `scripts/repurpose/__tests__/carousel-person-strip.test.cjs` (node)
- Test: `backend/tests/Unit/CarouselPersonStripRendererTest.php`

**Steps:**
1. Write failing node test for the pure layout builder in `carousel-person-strip.cjs`
   (`buildStripHtml({ faces, band })` → HTML with N framed `<img>` cut-outs + labels in the band).
   Expected error: module/function not found.
2. Run node test (`node --test scripts/repurpose/__tests__/carousel-person-strip.test.cjs`),
   confirm fail.
3. Implement the cjs: input = rendered slide PNG path + face URLs/labels + band geometry; Playwright
   `setContent` (data: URIs for faces — `setContent` opaque-origin blocks `file://`, the documented
   video-chrome gotcha) renders the base slide with the strip overlaid (hand-drawn sketchnote
   frames, drop shadow, labels) → output PNG. PHP `CarouselPersonStripRenderer::render($slidePng,
   $refs, $band): ?string` shells to it (mirror `VideoChromeRenderer`), `SharedDir::ensure`, unique
   output filename, returns null on failure.
4. Run both tests, confirm pass.
5. Commit: "feat(repurpose): carousel-person-strip composite + renderer".

**Verification:**
- [ ] Node layout test + PHP wrapper test pass
- [ ] Face images embedded as data: URIs (not file://)
- [ ] Renderer returns null on failure (caller falls back to plain slide)
- [ ] Unique output filename + `SharedDir::ensure`

---

### Phase F: Wire into `LinkedInCarouselImageService` (dispatch + webhook)

**Estimated time:** 13 min

**Files:**
- Modify: `backend/app/Services/LinkedInCarouselImageService.php`
- Test: `backend/tests/Feature/CarouselPersonPhotoWiringTest.php`

**Steps:**
1. Write failing feature test: a repurpose draft with stubbed locator (one founder face on a
   fixture source slide) → after `dispatchAllSlides`, the matched slide carries `person_photo_refs`
   + a reserved band; after a simulated `handleWebhook` `done` event, the persisted `image_url`
   points at a composited PNG (renderer stubbed to a sentinel path). Expected error: refs/composite
   absent.
2. Run test, confirm fail.
3. Implement: add private `enrichPersonPhotos($draft, $slides)` (try/catch, returns possibly-mutated
   slides) and call it right after `enrichCoverFigure` in `dispatchAllSlides`; in `handleWebhook`,
   after `downloadAndStore` and before persisting `image_url`, if the slide has `person_photo_refs`
   run `CarouselPersonStripRenderer::render(...)` and use its output URL when non-null (else the
   plain downloaded URL). Both paths non-fatal.
4. Run tests, confirm pass.
5. Commit: "feat(repurpose): wire person-photo enrich into dispatch + webhook composite".

**Verification:**
- [ ] Feature test passes end-to-end (dispatch → webhook → composited image_url)
- [ ] Enrich + composite are non-fatal (failure → plain slide, dispatch proceeds)
- [ ] Cover-figure path + non-repurpose drafts unaffected (regression assertions)
- [ ] No placeholder/TODO comments

---

### Phase G: Single-slide re-render reset + idempotency

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Services/LinkedInCarouselImageService.php`
- Test: `backend/tests/Feature/CarouselPersonPhotoReRenderTest.php`

**Steps:**
1. Write failing test: explicit single-slide re-render of a person-photo slide clears
   `person_photos_enriched` so the enricher re-runs (mirror
   `resetCoverFigureLockIfTargetingCover`); re-render of an unrelated slide does not. Expected
   error: flag not cleared.
2. Run test, confirm fail.
3. Implement the reset hook in `dispatchSingleSlide` (only when the target slide carries the
   person-photo lock).
4. Run tests, confirm pass.
5. Commit: "feat(repurpose): clear person-photo lock on explicit single-slide re-render".

**Verification:**
- [ ] Re-render test passes both branches
- [ ] Enricher remains idempotent on repeated `dispatchAllSlides` (no duplicate crops)

---

### Phase H: Docs, evals, VPS verification + write-back

**Estimated time:** 10 min

**Files:**
- Modify: root `CLAUDE.md` (Recent Changes + LinkedIn Carousel Image Generation section)
- Modify: `docs/evals/repurpose-person-photo.md` (finalize pass@k criteria)
- Create: `docs/runbooks/` note OR fold VPS step into the plan doc
- Vault write-back (per `~/CLAUDE.md`): `30-Knowledge/image-gen-shared.md` §0 +
  `10-Identity/visual-identity.md` (framed-photo / pinned-polaroid treatment for real people on
  sketchnote) + `hot.md`

**Steps:**
1. Run the full affected suite: `php artisan test --filter='Carousel|Repurpose|PersonPhoto'` + the
   node test. Confirm green.
2. `graphify update .`
3. Update root CLAUDE.md (additive entry; mark NOT pushed) + finalize the eval doc.
4. Vault write-back (image-gen-shared §0 + visual-identity + hot.md).
5. Commit: "docs(repurpose): person-photo human-touch — CLAUDE.md + evals + vault".

**Verification:**
- [ ] Full filtered suite + node test green
- [ ] `gaspol-verify` runs `docs/evals/repurpose-person-photo.md` (pass@k recorded)
- [ ] CLAUDE.md + vault updated; entry marked NOT pushed
- [ ] **Manual VPS check** (real CLI vision + Playwright): "Regenerate All Images" on draft 172 →
      the founders slide renders with the 4 real cropped faces in the reserved band

### Manual VPS verification (post-merge, real pipeline)

The vision locate + Playwright composite need the VPS (Claude CLI + Playwright + claudesn worker).
After deploy: open `/admin/sosmed-drafts/172` → "Regenerate All Images" → confirm the
"SIAPA CURSOR? 4 MIT Dropouts" slide shows the 4 real founder cut-outs framed in the reserved band,
and that a non-people slide is untouched.

---

## REVISION (2026-06-17) — Plugin-first architecture (SUPERSEDES app-side detection)

**Operator directive:** the intelligence ("does this slide need real faces?") must live in the
`ai-image-carousel-prompt-gen` plugin, NOT the backend — so the plugin gets smarter for EVERY
consumer, not just Portfolio. This is also the `~/CLAUDE.md` standing rule (app prompts stay THIN;
visual/authoring rules live in the plugin bundle).

**Hard boundary (honest):** the plugin only outputs prompt/JSON — it never renders or stores
photos. So only the **brain** moves to the plugin; the **photo bytes + deterministic composite**
stay app-side (deployment-specific). Split:

- **Plugin = brain (portable):** detect person-profile slides + emit a structured contract +
  reserve the photo band in `image_prompt` + own the framed-photo visual standard. A bare consumer
  feeds its own face refs and the image model renders them — graceful degrade, genuinely "advanced"
  for everyone.
- **App = fulfillment (driven BY the contract):** read `needs_real_faces` + `people[]` from the
  plugin output, resolve REAL photos (repurpose source-crop / Wikidata), composite for perfect
  likeness. **No app-side intent detection** — the `SIAPA X?` regex + headline heuristic are
  DELETED from the app.

This **supersedes Phase A's app-side intent role and Phase D entirely** (the matcher's "is this a
people slide?" job now belongs to the plugin). `SourceFaceLocator` survives but narrows to *pure
face location of a NAMED person* (the name comes from the plugin contract, not app detection).

### Plugin slide-schema contract (new optional fields on `CarouselSlideSchema`)

Repo: `/Users/alisadikin/Drive-D/Projects/claude-plugin/ai-image-carousel-prompt-gen`

```ts
// skills/carousel-gen/schema.ts — added to CarouselSlideSchema (all optional, back-compat)
needs_real_faces: z.boolean().optional(),
people: z.array(z.object({
  name: z.string().min(2).max(80),
  role: z.string().max(60).optional(),
})).max(6).optional(),
face_layout: z.enum(['photo_band_top', 'photo_band_inline', 'none']).optional(),
// superRefine: when needs_real_faces===true, require people.length>=1 + face_layout!=='none'
```

### Data Integration Map (revised)

| Feature | Data Source | Owner | Exists? | Action |
|---|---|---|---|---|
| "Slide needs faces?" detection | slide authoring | **Plugin** `/carousel-gen` | No | Teach in SKILL/creator-bible |
| `needs_real_faces`+`people[]`+`face_layout` contract | `CarouselSlideSchema` | **Plugin** | No | Add optional fields + superRefine |
| Reserved photo band in `image_prompt` | slide authoring | **Plugin** | No | Author band when needs_real_faces |
| Framed-photo visual standard (sketchnote) | creator-bible / sketchnote preset | **Plugin** + vault | No | Document once, single source |
| Carry contract → `carousel_slides[]` | `CarouselGenOutputAdapter::adapt` | App | Yes (mapper) | Pass new fields through |
| Resolve photo by NAME (repurpose) | source `slide-*.jpg` + `people[].name` | App `SourceFaceLocator` | Partial | Narrow to locate named face |
| Resolve photo by NAME (blog) | Wikidata/Commons | App `EntityReferenceService` | Yes | Reuse (license-clean) |
| Crop + composite | Intervention + `carousel-person-strip.cjs` | App | No | As original Phases C/E |
| Wire dispatch + webhook | `LinkedInCarouselImageService` | App | Yes | As original Phase F/G |

### TRACK 1 — Plugin phases (the brain)

#### Phase P1: schema contract fields + superRefine

**Files:** Modify `skills/carousel-gen/schema.ts`; Test `skills/carousel-gen/__tests__/schema.test.ts` (vitest)

**Steps:**
1. Write failing test: a slide with `needs_real_faces:true` but empty `people` is REJECTED; a slide
   with `needs_real_faces:true` + `people:[{name}]` + `face_layout:'photo_band_top'` PASSES; a
   legacy slide with none of the new fields still PASSES (back-compat). Expected error: schema has
   no such fields → test for rejection fails.
2. Run `npm test`, confirm fail.
3. Add the three optional fields + the superRefine rule.
4. Run tests, confirm pass.
5. Commit (plugin repo): "feat(carousel-gen): people_spotlight contract on slide schema".

**Verification:**
- [ ] vitest passes incl. back-compat (legacy slides valid) + the require-people rule
- [ ] `npm run build` / typecheck clean

#### Phase P2: SKILL + creator-bible authoring rules (detection + band + framed-photo standard)

**Files:** Modify `skills/carousel-gen/SKILL.md`; Modify the relevant `references/*` (creator-bible /
sketchnote preset); eval note.

**Steps:**
1. Add a deterministic authoring rule: when a slide PROFILES specific real person(s) — headline
   `SIAPA <Name>?` / "Who is X?", or copy centering on named founders/authors/CEOs — set
   `needs_real_faces:true`, fill `people[]` with the real name(s)+role, set `face_layout`, and
   author `image_prompt` to RESERVE a clear photo band (no icon/text there) + describe the
   framed-photo (pinned-polaroid) treatment for the people. NEVER write the real name as on-image
   text (the consumer supplies the photo).
2. Document the framed-photo visual standard once (creator-bible / sketchnote preset) so it's the
   single source of truth.
3. Add a fixture to the plugin's prompt eval: Cursor caption → founders slide flagged
   `needs_real_faces` with 4 people; Vaswani caption → profile slide flagged with 1 person.

**Verification:**
- [ ] A pipeline-mode run on the two fixtures emits `needs_real_faces` + correct `people[]`
- [ ] The flagged slide's `image_prompt` reserves a photo band (no people text baked in)
- [ ] Non-people slides leave the fields unset (no false positives)

#### Phase P3: compile-refs + version bump + VPS bundle

**Files:** `scripts/compile-refs.ts` output; `plugin.json`/marketplace version.

**Steps:**
1. `npm install && npm run compile-refs` → rebuild `refs-carousel-gen-pipeline.md`.
2. Bump plugin version; run full plugin test suite.
3. Commit (plugin repo). Deploy bundle to VPS `/home/claudesn/refs-carousel-gen-pipeline.md`
   (per the existing carousel-gen deploy recipe; re-enable plugin scope if version bump disables it
   — known gotcha [[vps-plugin-version-bump-disables-plugin]]).

**Verification:**
- [ ] Plugin suite green; bundle rebuilt; `claude -p "/carousel-gen --help"` works on VPS

### TRACK 2 — Backend phases (contract-driven fulfillment) — REVISES the original phases

- **Phase B-rev1 — adapter passthrough:** `CarouselGenOutputAdapter::adapt` carries
  `needs_real_faces` / `people` / `face_layout` into `carousel_slides[]`. Test: adapter preserves
  the fields; legacy envelopes (no fields) unaffected.
- **Phase B-rev2 — `SourceFaceLocator` narrowed:** locate the face of a *given named person* in the
  source slides (input = `people[].name` from the contract), return bbox. NO "is this a people
  slide" detection (that's the plugin's job now). (Replaces original Phase A's detection role +
  Phase D entirely.)
- **Phase C (unchanged):** crop padded bbox → unique public cut-out.
- **Phase B-rev3 — band already reserved by plugin:** the app trusts the plugin's reserved band
  (`face_layout`) — app no longer appends the band mandate (it only composites). Keep a thin
  fallback band only if `face_layout` present but the prompt clearly didn't reserve space.
- **Phase E (unchanged):** `carousel-person-strip.cjs` composite into the band.
- **Phase F/G (unchanged):** wire `dispatchAllSlides` (now: `if slide.needs_real_faces → fulfill`)
  + `handleWebhook` composite + single-slide re-render reset.
- **Phase H (unchanged):** evals + CLAUDE.md + vault write-back (the framed-photo standard is
  documented in the PLUGIN bundle + vault, per the rule).

**Backend trigger is now ONE flag:** `slide.needs_real_faces === true` (from the plugin). The app
fulfills via source-crop (repurpose) or Wikidata (blog). If it can't resolve a photo → no-op
(plugin's reserved band gracefully renders empty per the prompt, or the thin fallback removes the
band). Zero app-side intent detection.
