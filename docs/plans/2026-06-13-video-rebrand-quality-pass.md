# video_rebrand quality pass — hook / source-cleanup / CTA / chrome fixes

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations (EntityReferenceService,
> GeminiGenVideoService, /carousel-gen standard, VideoChromeRenderer). During
> execution, NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Design

### Context

`video_rebrand` (3rd RepurposeJob mode — IG VIDEO carousel re-skin) shipped and ran
end-to-end (job #15 → `drafted`, 10 slides). First real output surfaced **4 quality
issues** the operator flagged from the rendered carousel. This doc designs the fixes.

Brainstormed 2026-06-13. All four decisions locked with the operator (see option picks
inline). The original build plan is [docs/plans/2026-06-12-ig-video-carousel-rebrand.md];
this is the follow-up quality pass. Build state: [[video-rebrand-build-state]] memory.

**Core operator insight that shapes #1 + #3:** *"video kan cuma animasikan apa yang sudah
di-generate di image — jadi penentunya di image generation (NB2)."* The Veo clip merely
animates the keyframe image, so hook/CTA quality is decided at **keyframe image-gen**
(`nano-banana-pro`), authored to `/carousel-gen`'s already-proven hook/CTA standard. No
new plugin, no new skill — reuse the standard, orchestrate backend-side (repo pattern).

### The four fixes

#### #1 — Topic-aware HOOK (currently a static studio portrait)

**Now:** `GenerateRebrandAssets::KEYFRAME_PROMPT_HOOK` is a static constant — plain
creator portrait on brand-blue, identical for every topic. Monotone.

**Target:** keyframe image authored from the carousel's extracted topic, following
`/carousel-gen`'s hook standard (Spotlight Portrait + ≥3 floating real topic UI/logos +
topic-evocative scene). A relevant **public figure enters as a face REFERENCE, never a
name** (operator decision — sidesteps the named-public-figure *text* filter):

```
extracted topic (from slide header_titles)
  → LLM (RunsRepurposeClaudeCli, following bundled /carousel-gen hook standard ref):
      • pick the iconic relevant figure (Google→Pichai, OpenAI→Altman) — figure NAME used
        only to resolve the photo, NEVER placed in the image prompt
      • author a topic-evocative hook scene prompt (creator + figure as "reference image 2")
  → EntityReferenceService::findOrFetch(figureName, 'person')   [REUSE — Wikidata+Commons,
      license-whitelisted, already powers named-entity covers] → public photo URL
  → GeminiGenVideoService::dispatchKeyframe([aliFaceUrl, figureFaceUrl], prompt)  [9:16]
  → Veo I2V animates the keyframe (standard Audio: line — see build-state)
```

**Mandatory safety fallback:** NB2's `PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD` is an *upload*
filter and may reject a recognizable figure photo even as a ref. On that refusal: drop the
figure ref → re-author a **creator-only** topic scene → retry. Models the existing
`ImageGenerationService::handleSegmentFailure` proper-noun-strip pattern. Job must degrade,
never fail, on figure refusal.

#### #2 — Drop the SOURCE creator's own bookend slides (the "slide-2" bug)

**Now:** every captured source video becomes a `tool` slide. The source creator's own
hook/cover (their face + "SWIPE FOR MORE →") and their CTA leak in as fake tools.

**Target (decision: vision-classify & auto-drop):** `VideoSlideExtractor`'s per-slide
vision pass (already running) returns a classification:
`slide_kind ∈ { content | source_hook | source_cta }`. `ExtractVideoSlides` drops
non-`content` slides, **renumbers** the surviving tool slides contiguously, and recomputes
the stepper `total`. The dropped source files stay on disk (audit) but get no chrome and no
draft slot.

#### #3 — CTA with a real ask (currently a silent gesturing portrait)

**Now:** CTA is a raw Veo portrait with no chrome and no follow/comment/share ask.

**Target (decision: chrome overlay on CTA + caption):**
- CTA **keyframe** authored to `/carousel-gen`'s CTA standard (navy + gold glow, inviting).
- A baked **CTA chrome overlay** card (`Follow @handle · Save this · Comment "AI"`) rendered
  by `VideoChromeRenderer` (new CTA variant) + composited onto the CTA Veo clip via ffmpeg
  overlay — visible in-feed regardless of caption truncation.
- The same ask appended to the post caption in `FinalizeRepurpose`.
- Ask must NOT promise comment→DM auto-delivery (no auto-DM infra — see CLAUDE.md decision).

#### #4 — Chrome rendering bugs (from the rendered tool slides)

Root causes confirmed live on VPS:
- **Broken logo (header top-left + footer):** `video-chrome.cjs` builds the page via
  `page.setContent(html)` → opaque `about:blank` origin → the `<img src="file://…">` brand
  logo is a `file://` sub-resource from an opaque origin → **Chromium silently blocks it**.
  The PNG itself is valid (55KB, 300×300, world-readable). **Fix:** inline the logo as a
  `data:image/png;base64,…` URI (origin-independent) — read bytes in the cjs (strip
  `file://`), base64-encode, emit `<img src="data:…">` for both header + footer.
- **Literal `@creator-brand` handle:** `creator_brand_slug = "creator-brand"` (placeholder)
  and `linkedin.creator_handle = NULL` → `VideoChromeRenderer::handle()` returns
  `@creator-brand`. **Fix:** (a) set the real handle data (`creator_brand_slug=alisadikinma`
  or `linkedin.creator_handle=alisadikinma` — idempotent seeder/one-shot), AND (b) harden
  `handle()` to ignore the known placeholder slug `creator-brand` and fall back to
  `@alisadikinma`.

### Plugin / skill verdict

**No new plugin, no new skill.** Orchestration stays backend (consistent with Content
Engine / LinkedIn / repurpose). The only new creative step (hook/CTA keyframe prompt
authoring) *follows* `/carousel-gen`'s hook/CTA standard, bundled onto the VPS as a ref and
appended via `--append-system-prompt-file` (mirrors `refs-carousel-gen-pipeline.md`).

### Data Integration Map

| Capability | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Public-figure photo (license-clean) | Wikidata SPARQL + Commons | `EntityReferenceService::findOrFetch` | ✅ | Reuse for hook face-ref |
| Hook/CTA composition standard | ai-image-carousel-prompt-gen `/carousel-gen` | bundled VPS ref | ✅ | Follow it (don't reinvent) |
| Topic-aware prompt authoring | Claude CLI (SSH) | `RunsRepurposeClaudeCli` | ✅ | Extend with hook/CTA author step |
| Keyframe image gen (MULTI-ref) | GeminiGen `/generate_image` | `GeminiGenVideoService::dispatchKeyframe` | ⚠️ | Widen signature: single → array of refs |
| Veo I2V animate | GeminiGen `/video-gen/veo` | `dispatchVeoClip` + `finalizeVeoClip` | ✅ | Unchanged (keep standard Audio: line) |
| Slide kind classification | Claude vision (SSH) | `VideoSlideExtractor` | ✅ | Add `slide_kind` to extract prompt+parse |
| Drop + renumber source slides | — | `ExtractVideoSlides` | ✅ | Filter non-content, renumber, recompute total |
| CTA chrome overlay | Playwright HTML→PNG | `VideoChromeRenderer` + `video-chrome.cjs` | ✅ | Add CTA variant + ffmpeg overlay |
| Logo data-URI inline | local PNG bytes | `video-chrome.cjs` | ✅ | base64-inline (fixes #4 logo) |
| Handle resolution | settings | `VideoChromeRenderer::handle()` + Setting data | ✅ | Data fix + placeholder-hardening |
| Caption ask | — | `FinalizeRepurpose` | ✅ | Extend caption builder |
| Safety fallback (drop figure ref) | mirrors `handleSegmentFailure` | new, in PollRebrandAssets/GenerateRebrandAssets | ✅ pattern | Build modeled on existing |

### Risks

1. **NB2 refuses the figure photo even as a ref** (`PROMINENT_PEOPLE_UPLOAD` is an upload
   filter). → creator-only topic-scene fallback makes it degrade, not fail. **Highest risk.**
2. **`/carousel-gen` is tuned for 4:5 carousels, not 9:16 single bookends.** v1 reuses its
   *standard* (bundled ref) via a backend LLM author step, not a full `/carousel-gen` SSH
   invocation — keeps output shape under our control. Revisit full invocation only if the
   lean author step underperforms.
3. **Veo audio filter** — already solved (mandatory `Audio:` line, build-state). Keep it.
4. **Multi-ref keyframe identity bleed** — two faces in one frame may blend. Prompt must
   spatially separate ("creator on the left, reference-2 person on the right").

### Test posture

No PHP on dev Mac → backend tests in Docker `serversideup/php:8.2-cli` sqlite. Process/HTTP
faked (capture, GeminiGen, ffmpeg, SSH). The figure-ref + Veo realism + slide-classification
accuracy only truly verify on a live re-run of an IG video carousel (job #15's source URL is
the obvious regression fixture).

---

## Implementation Plan

> **Executor:** use gaspol-execute. TDD-first, one assertion-cluster per step, commit per
> phase. Tests run in Docker (no PHP on Mac):
> `cd backend && docker run --rm -v "$(pwd)":/app -w /app serversideup/php:8.2-cli php vendor/bin/phpunit --filter <Test>`
> (start Docker Desktop first). **Never `git push`** — operator authorizes deploys.

### Goal

Fix the four quality issues on the shipped `video_rebrand` carousel: (A) broken chrome
logo + wrong handle, (B) the source creator's own hook/CTA leaking in as fake tools,
(C) a monotone studio-portrait hook → topic-aware scene with a public figure as a face
*reference*, (D) a no-ask CTA → real follow/comment/save ask in both the CTA video chrome
and the caption. Ordered fastest-win-first; A is a near-pure fix, D depends on the chrome
work in A.

### Architecture Context (from CLAUDE.md + live reads)

- **Pipeline:** capture → `ExtractVideoSlides`/`VideoSlideExtractor` → `GenerateRebrandAssets`
  (forks Extracted→GeneratingAssets, creates hook[idx 0]+cta[idx max+1] bookends, dispatches
  keyframe gen) → `PollRebrandAssets` cron (keyframe→Veo→finalize, **sole** completion driver;
  GeminiGen never webhooks) → `ComposeVideoCarousel` (tool-slide chrome composite) →
  `FinalizeRepurpose::finalizeVideoRebrand` (→ Drafted, manual download).
- **Reusable:** `EntityReferenceService::findOrFetch($name,'person')` (Wikidata+Commons,
  license-clean, public URL — powers named-entity covers); `RunsRepurposeClaudeCli` trait
  (`runVisionParsed` / `runRepurposeParsed` w/ repair-retry); `GeminiGenVideoService`
  (`dispatchKeyframe`, `dispatchVeoClip`, `finalizeVeoClip`); the safety-strip pattern in
  `ImageGenerationService::handleSegmentFailure` / `isSafetyError`.
- **Standard:** `/carousel-gen` hook/CTA composition (ai-image-carousel-prompt-gen, Spotlight
  Portrait v3 — creator portrait + ≥3 floating topic UI + navy/gold CTA) — bundle the relevant
  reference onto the VPS, append via `--append-system-prompt-file`.

### Tech Stack

Laravel 12 / PHP 8.2 (PHPUnit, Process::fake, Http::fake), Node Playwright chrome cjs,
ffmpeg, GeminiGen (`nano-banana-pro` keyframe + Veo 3.1-fast I2V), Claude CLI over SSH.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Chrome logo inline | local PNG bytes | `scripts/repurpose/video-chrome.cjs` | Yes | base64 data-URI |
| Handle resolution | settings (`creator_brand`/`linkedin`) | `VideoChromeRenderer::handle()` | Yes | placeholder-harden + data fix |
| Slide kind classify | Claude vision | `VideoSlideExtractor::buildPrompt`+parse | Yes | add `slide_kind` field |
| Drop+renumber tools | — | `ExtractVideoSlides::handle` | Yes | delete non-content, renumber |
| Figure photo | Wikidata+Commons | `EntityReferenceService::findOrFetch` | Yes | reuse for hook ref |
| Multi-ref keyframe | GeminiGen `/generate_image` | `GeminiGenVideoService::dispatchKeyframe` | Partial | widen to array refs |
| Hook/CTA prompt author | Claude CLI + bundled carousel-gen ref | `RunsRepurposeClaudeCli` (new method) | Yes pattern | new author step |
| Safety fallback | mirrors `handleSegmentFailure` | `PollRebrandAssets`/`GenerateRebrandAssets` | New | creator-only re-author |
| CTA chrome overlay | Playwright HTML→PNG | `video-chrome.cjs` + `VideoChromeRenderer` | Yes | CTA variant |
| CTA video overlay | ffmpeg | `VideoRebrandComposer` (new method) | Yes pattern | overlay PNG on Veo clip |
| Caption ask | — | `FinalizeRepurpose::finalizeVideoRebrand` + controller | Yes | build + surface caption |

Non-deterministic steps (slide-kind classify, figure selection, scene authoring) get an eval
contract: `docs/evals/video-rebrand-quality.md` (fixtures = job #15 source URL + posters).
`gaspol-verify` runs pass@k; deterministic unit tests alone don't certify these.

---

### Phase A — Chrome bugs (logo + handle)  ⏱️ ~25 min

**Files:**
- Modify: `scripts/repurpose/video-chrome.cjs` (inline logo as data-URI)
- Modify: `backend/app/Services/VideoChromeRenderer.php` (`handle()` placeholder-harden; pass logo path not `file://`)
- Test: `backend/tests/Feature/VideoChromeRendererTest.php` (handle resolution)
- Test (node): `scripts/repurpose/__tests__/video-chrome-logo.test.cjs` (data-URI inline) — or assert via VideoChromeRenderer arg builder
- Data: one-shot/seeder set `creator_brand_slug=alisadikinma` (idempotent)

**Steps:**
1. Write failing test for `VideoChromeRenderer::handle()` ignoring the placeholder slug. Expected error: `Failed asserting that '@creator-brand' matches expected '@alisadikinma'.`
2. Run it, confirm it fails for that reason.
3. Harden `handle()`: treat `creator_brand_slug ∈ {creator-brand, creator_brand, ''}` as unset → fall back to `linkedin.creator_handle` then `@alisadikinma`.
4. Run test, confirm pass.
5. Write failing test that `video-chrome.cjs` emits a `data:image/png;base64,` `<img>` (not `file://`) when given a logo path. Expected error: assertion that output HTML contains `data:image/png;base64,` fails (currently emits `src="file://"`).
6. Run it, confirm fail.
7. In `video-chrome.cjs`: read the logo bytes (strip `file://` prefix if present, `fs.readFileSync`), base64-encode, build `data:image/png;base64,…`; use it for BOTH `logoTag` and `footLogo`. Empty/missing logo → no `<img>` (current behavior).
8. Run test, confirm pass.
9. Set the handle data on VPS via idempotent path (extend `CreatorBrandSettingsSeeder` or a one-shot `Setting::updateOrCreate`) so `creator_brand_slug` is a real value — log the change.
10. Commit: `fix(repurpose): inline chrome logo as data-URI + harden handle fallback`.

**Verification:**
- [ ] `VideoChromeRendererTest` green (handle = `@alisadikinma` with placeholder slug)
- [ ] cjs emits `data:image/png;base64,` for header + footer logos; no `file://` `<img>`
- [ ] Re-render a job-15 tool slide on VPS → logo visible, footer reads `@alisadikinma` (manual spot-check)
- [ ] No placeholder/TODO comments
- [ ] Full repurpose suite still green

---

### Phase B — Drop source bookend slides (vision-classify)  ⏱️ ~35 min

**Files:**
- Modify: `backend/app/Services/VideoSlideExtractor.php` (prompt + parse `slide_kind`, return dropped indices)
- Modify: `backend/app/Jobs/ExtractVideoSlides.php` (delete non-content tool rows, renumber survivors, log)
- Test: `backend/tests/Feature/VideoSlideClassifyTest.php`

**Steps:**
1. Write failing test: `VideoSlideExtractor::extract` (subclassed w/ canned vision JSON containing `slide_kind`) marks the first slide `source_hook`. Expected error: `Undefined array key "slide_kind"` / assertion the returned `dropped` list is empty fails.
2. Run, confirm fail.
3. Extend `buildPrompt`: add a `kind` field per slide — `"content"` (a real tool/tip) vs `"source_hook"` (the creator's own intro/cover — a talking-head or "swipe for more", no tool) vs `"source_cta"` (their follow/like/subscribe outro). Extend the JSON shape + parse `slide_kind` onto each entry; have `extract()` return `dropped` = slide_indexes classified non-content.
4. Run test, confirm pass.
5. Write failing test: `ExtractVideoSlides` deletes non-content tool rows and renumbers survivors to contiguous 1..K. Expected error: assertion that surviving tool `slide_index`es == [1,2,3] fails (gap left by dropped slide).
6. Run, confirm fail.
7. In `ExtractVideoSlides::handle` after a successful `extract()`: delete the `dropped` tool rows (retain files on disk), renumber surviving `role=tool` rows by ascending original index → 1..K, `Log::info` the dropped count + reasons. Source-file retention only (no `Storage::delete`).
8. Run test, confirm pass.
9. Add a guard: if ALL tool slides classify non-content (over-aggressive), keep them all + log a warning (never ship an empty carousel).
10. Commit: `feat(repurpose): vision-classify + drop source hook/cta slides`.

**Verification:**
- [ ] `VideoSlideClassifyTest` green (classify + drop + contiguous renumber + all-dropped guard)
- [ ] `GenerateRebrandAssets` still computes `maxToolIndex` correctly from renumbered rows (cta at K+1) — assert in test
- [ ] Eval fixture (job-15 posters) classification spot-checked in `docs/evals/video-rebrand-quality.md`
- [ ] No placeholder/TODO comments
- [ ] Repurpose suite green

---

### Phase C — Topic-aware hook keyframe (figure face-ref)  ⏱️ ~55 min

**Files:**
- Modify: `backend/app/Services/GeminiGenVideoService.php` (`dispatchKeyframe` single→array refs, back-compat)
- Modify: `backend/app/Jobs/GenerateRebrandAssets.php` (author hook scene + resolve figure + multi-ref dispatch)
- New: hook-author method on a service (reuse `RunsRepurposeClaudeCli`) following bundled carousel-gen hook standard
- Modify: `backend/app/Console/Commands/PollRebrandAssets.php` (safety fallback: drop figure ref → creator-only re-author on `PROMINENT_PEOPLE_UPLOAD`)
- Test: `backend/tests/Feature/KeyframeMultiRefTest.php`, `HookAuthorAndFallbackTest.php`
- Eval: `docs/evals/video-rebrand-quality.md` (figure selection + scene authoring)

**Steps:**
1. Write failing test: `dispatchKeyframe(array $refs, …)` emits one `file_urls` multipart entry per ref. Expected error: `TypeError: …dispatchKeyframe(): Argument #1 ($faceRefUrl) must be of type string, array given`.
2. Run, confirm fail.
3. Widen `dispatchKeyframe` first param to accept `string|array` refs; normalize to array; emit one `file_urls` entry per URL; keep the identity-lock prompt suffix. Update the single existing caller.
4. Run test (Http::fake asserts N file_urls entries), confirm pass.
5. Write failing test: hook-author step returns `{figure_name, scene_prompt}` from a topic (subclass + canned CLI JSON). Expected error: method/`figure_name` key missing.
6. Run, confirm fail.
7. Implement the author step (Claude CLI via `RunsRepurposeClaudeCli`, prompt bundles the carousel-gen hook standard ref via `--append-system-prompt-file`): input = topic (joined surviving slide `header_title`s) + identity; output strict JSON `{figure_name, scene_prompt}`. `scene_prompt` MUST spatially separate the two people ("creator on the left; the person matching reference image 2 on the right") and NEVER contain the figure's name. `figure_name` used only downstream to fetch the photo.
8. Run test, confirm pass.
9. Wire `GenerateRebrandAssets` hook path: author → `EntityReferenceService::findOrFetch($figureName,'person')` → if photo URL resolved, dispatch keyframe with `[aliFaceUrl, figureUrl]` + `scene_prompt`; else creator-only `[aliFaceUrl]`. CTA path unchanged this phase. Replace the static `KEYFRAME_PROMPT_HOOK` use for the hook bookend.
10. Write failing test: on a keyframe `PROMINENT_PEOPLE_UPLOAD` failure, the recovery re-dispatches creator-only (figure ref dropped). Expected error: assertion that the retry dispatch carries exactly 1 ref fails (still 2).
11. Run, confirm fail.
12. In `PollRebrandAssets` (or `GenerateRebrandAssets` recovery): detect the safety code on a failed hook keyframe (reuse the `isSafetyError` substring approach), re-author creator-only topic scene, re-dispatch with `[aliFaceUrl]`, mark a `figure_dropped` sentinel for idempotency. Bounded by existing `asset_retry_count`/MAX_RETRIES.
13. Run test, confirm pass.
14. Commit: `feat(repurpose): topic-aware hook keyframe with public-figure face-ref + safety fallback`.

**Verification:**
- [ ] `KeyframeMultiRefTest` + `HookAuthorAndFallbackTest` green
- [ ] `dispatchKeyframe` back-compat (string ref still works) — assert
- [ ] Figure name never appears in `scene_prompt` (regex assertion)
- [ ] Safety refusal degrades to creator-only, job does NOT fail — assert
- [ ] Eval `docs/evals/video-rebrand-quality.md`: figure selection (Google→Pichai etc.) + scene quality pass@k ≥ target on fixtures
- [ ] security: external photo URL from EntityReferenceService is license-checked + downloaded to our storage (existing behavior) — no raw remote URL passed to GeminiGen un-vetted
- [ ] No placeholder/TODO comments
- [ ] Repurpose suite green

---

### Phase D — CTA with a real ask (chrome overlay + caption)  ⏱️ ~50 min

**Files:**
- Modify: `backend/app/Jobs/GenerateRebrandAssets.php` (CTA keyframe authored to carousel-gen CTA standard)
- Modify: `scripts/repurpose/video-chrome.cjs` (CTA overlay-card variant)
- Modify: `backend/app/Services/VideoChromeRenderer.php` (render CTA overlay PNG)
- Modify: `backend/app/Services/VideoRebrandComposer.php` (ffmpeg overlay PNG onto CTA Veo clip)
- Modify: `backend/app/Console/Commands/PollRebrandAssets.php` (CTA finalize → overlay instead of plain crop)
- Modify: `backend/app/Jobs/FinalizeRepurpose.php` (`finalizeVideoRebrand` builds caption w/ ask)
- Modify: `backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php` (surface caption in detail)
- Test: `backend/tests/Feature/CtaOverlayAndCaptionTest.php`

**Steps:**
1. Write failing test: `video-chrome.cjs` CTA mode emits an overlay card with `Follow`, `Save`, `Comment` text (no comment→DM promise). Expected error: assertion output contains the ask card fails.
2. Run, confirm fail.
3. Add a `--mode cta` (or `--cta-overlay`) branch to `video-chrome.cjs`: a transparent-bg overlay PNG (bottom third, navy/gold tokens) with `Follow @handle · Save this · Comment "AI"`. No DM-promise copy.
4. Run test, confirm pass.
5. Write failing test: `VideoRebrandComposer::overlayCta($ctaClip, $overlayPng)` runs ffmpeg `overlay` and stores a public URL (mirror the public-disk convention from `f45ab308`). Expected error: method missing.
6. Run, confirm fail.
7. Implement `overlayCta` (ffmpeg `[0:v][1:v]overlay=...`, audio passthrough, write `Storage::disk('public')`, store full URL). Wire `PollRebrandAssets` CTA finalize to: `finalizeVeoClip` (4:5) → render CTA overlay PNG → `overlayCta`. Hook bookend stays plain.
8. Run test, confirm pass.
9. Write failing test: `finalizeVideoRebrand` stores a caption containing the follow/comment/save ask + surfaces it via the controller detail (`caption` field). Expected error: assertion `data.caption` present fails.
10. Run, confirm fail.
11. Build caption in `finalizeVideoRebrand` (compose from surviving tool titles + a standard ask block, NO comment→DM promise); persist (e.g. `rewritten['caption']` or a dedicated field) and add `'caption' => …` to the controller detail response for operator copy in Social Studio.
12. Run test, confirm pass.
13. Commit: `feat(repurpose): CTA follow/comment/save ask in chrome overlay + caption`.

**Verification:**
- [ ] `CtaOverlayAndCaptionTest` green (overlay card text, `overlayCta` public URL, caption surfaced)
- [ ] CTA clip = Veo portrait + baked ask card; hook clip unchanged
- [ ] Caption has the ask, contains NO comment→DM promise (regex assertion)
- [ ] Composited CTA stored on PUBLIC disk + full URL (consistent w/ tool slides post-`f45ab308`)
- [ ] No placeholder/TODO comments
- [ ] Repurpose suite green

---

### Cross-cutting verification (after all phases)

- [ ] Full backend repurpose suite green in Docker
- [ ] Live re-run on job #15's source IG URL (operator-triggered): logo+handle correct, source hook/cta dropped, hook scene topic-aware (figure or graceful creator-only), CTA has visible ask, all slides download 206 over HTTPS
- [ ] CLAUDE.md repurpose section + [[video-rebrand-build-state]] memory updated with the 4 fixes
- [ ] Commits staged; **await operator push authorization** (no autonomous push)

### Risk register

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | NB2 refuses figure photo even as ref | High | Phase C creator-only fallback — job degrades not fails |
| 2 | Vision misclassifies a real tool as source_hook | Med | all-dropped guard + eval fixtures + keep files for re-run |
| 3 | Two-face keyframe identity bleed | Med | spatial-separation prompt rule; eval spot-check |
| 4 | carousel-gen ref drift on VPS | Low | bundle ref pinned; document compile-refs step in runbook |
| 5 | CTA overlay covers the creator's face | Low | bottom-third overlay, tokened safe-zone; spot-check |
