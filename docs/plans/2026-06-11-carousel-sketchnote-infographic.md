# Carousel → Sketchnote Infographic Style (knowledge-first)

> Status: Design (brainstorm). Next: `gaspol-plan` appends `## Implementation Plan`.
> Source request: draft `sosmed-drafts/149` output "jauh dari harapan" — operator wants
> the clean flat educational infographic look of the attached Granola+Claude / techwith.ram
> references, not the current cinematic photo carousel. Plus: drop foreshadow, make body+peak
> deep-knowledge infographics.

## Design

### Problem
Every blog→carousel (LinkedIn + the IG/TikTok/Threads siblings that reuse its 4:5 slides)
renders as a **photorealistic cinematic scene** — creator face, absurdist "BAHAYA" hook cover,
WOW gate (lighting drama / atmosphere / film stock). The operator wants the opposite: a flat,
text+diagram **educational infographic** (cream paper, doodle icons, numbered mechanism rows,
comparisons) so a reader understands the topic deeply at a glance. Draft 149 (the Obsidian
repurpose of techwith.ram) proves the gap: copy is knowledge-dense and correct, the *images*
are wrong-genre.

### Root cause — NOT the RAG content; the wiring + a missing bundle
Two independent bugs, both must be fixed or the change is inert:

1. **Backend never selects a style.** [`LinkedInGenerationService::buildCarouselGenPrompt`](../../backend/app/Services/LinkedInGenerationService.php#L1104)
   emits only `--pipeline --blog-source --bilingual=id,en --narrative=5act --target-slides=N`.
   No `--style`, so the plugin uses its `cinematic` default. `--narrative=5act` also hardcodes
   the HOOK→**FORESHADOW**→BODY→PEAK→CTA spine + the mandatory absurdist cover (Hard Rule #17).

2. **The infographic preset isn't in the VPS context.** The plugin already ships a complete
   `--style=sketchnote` preset in `references/style-presets.md` (cream paper, doodle icons, no
   creator face, DOODLE quality gate replacing WOW, "Granola-exact" forest-green+terracotta
   variant, body template = *numbered hand-drawn rows: icon + label + one-line explanation*,
   worked Granola cover example). **But `scripts/compile-refs.ts` does NOT bundle it** (8 source
   files, `style-presets.md` absent → verified on VPS: compiled bundle only name-drops it).
   In pipeline mode the model has no filesystem access, so the preset spec is simply missing.
   Passing the flag without bundling the preset = weak/garbled doodle or drift back to cinematic.

### Decisions locked (operator)
- **Aesthetic:** Sketchnote (Granola look) first — reuse the existing preset, ship fast.
  Editorial/Obsidian preset = possible fast-follow, out of scope here.
- **Scope:** ALL blog→carousel (not just IG-repurpose). One lever on the LinkedIn carousel-gen
  path automatically flows to IG/TikTok/Threads (they reuse the rendered 4:5 slides).
- **Density:** Knowledge-maximal — dense numbered rows, comparisons, annotated mechanisms
  (accepting some AI text-render risk).

### Approach (two halves, ordered)

**A. Plugin (`ai-image-carousel-prompt-gen`) — bundle the preset.** MUST land first / together.
- Add `style-presets.md` to `compile-refs.ts` `sourceFiles`.
- `npm run compile-refs` → redeploy `refs-carousel-gen-pipeline.md` to VPS
  (`/home/claudesn/...`). Bump plugin version. Operator/VPS step.
- (Optional polish) tighten the sketchnote body template toward the dense Granola "Phase 3"
  layout (numbered recipe-card rows + arrows + annotations) since density = knowledge-maximal.

**B. Backend (`Portfolio_v2`) — select the style + knowledge spine.** Pure prompt-string change.
- `buildCarouselGenPrompt`: add `--style=sketchnote`.
- Drop foreshadow: replace `--narrative=5act` → `--narrative=free` AND append an explicit
  **knowledge-spine** instruction so the storyline is COVER(promise) → CONTEXT/PROBLEM →
  MECHANISM(how it works) → COMPARISON/STEPS → KEY INSIGHT(peak) → CTA, with **every body/peak
  slide a self-contained mini-infographic** (diagram/comparison/numbered steps, not headline+scene).
- Keep the inline full-article body (already passed) so dense slides are grounded in real content.
- These are pure edits to one testable method (already a unit-test seam).

### Data Integration Map

| Piece | Source / File | Exists? | Change |
|---|---|---|---|
| Style flag | `LinkedInGenerationService::buildCarouselGenPrompt` | yes | add `--style=sketchnote` |
| Narrative | same method, `--narrative=5act` | yes | → `--narrative=free` + spine text |
| Sketchnote preset spec | `references/style-presets.md` | yes (unbundled) | add to `compile-refs.ts` |
| VPS compiled bundle | `/home/claudesn/refs-carousel-gen-pipeline.md` | yes (stale) | recompile + redeploy |
| Brand chrome tokens | `CarouselSlideEnhancer` | yes | unchanged (preset relaxes placement) |
| Cross-post slides | IG/TikTok/Threads reuse LinkedIn 4:5 | yes | unchanged (auto-inherits) |
| Image render | GeminiGen / Nano Banana Pro 4:5 | yes | unchanged (style lives in prompt) |

### Confirmed decisions (operator, round 2)
1. **Foreshadow = IG-repurpose ONLY.** Style=sketchnote applies to all carousels, but the
   foreshadow drop is scoped to repurpose drafts. → `buildCarouselGenPrompt` must branch:
   - IG-repurpose draft → `--narrative=free` (no foreshadow).
   - Normal blog carousel → `--narrative=5act` (keep foreshadow beat).
   **Requires an `isRepurpose($draft)` predicate** threaded into the prompt builder. Detection:
   a `RepurposeJob` referencing this `linkedin_post_id` (carousel mode) **OR** the draft's `post`
   links a `ContentIdea` with `source='instagram'` / `source_data.source='ig_repurpose'` (blog mode).
2. **Bilingual ID/EN kept** on all sketchnote slides (headline ID + subtitle EN baked-in, as today).
   Note: dense + bilingual raises text-render + truncation risk — mitigated by ≤7 slides + the
   1800-char/slide cap. If garble shows up in QA, single-language ID is the fallback lever.
3. **Slide count ≈7 dense** (`inferTargetSlides` unchanged): cover + 4–5 body-infographic + CTA.

### Feasibility / risks
- **AI text rendering** of dense multi-row labels is the real ceiling — the reference images are
  human-designed (Canva/Figma). The DOODLE gate + single-language + ≤7 slides mitigate; some
  slides may still need a per-slide regenerate. Honest expectation: a large jump toward the
  reference, not pixel-parity with hand-designed decks.
- **Bundle size:** adding style-presets.md grows the 256KB pipeline bundle ~+8KB — negligible.
- **Regression:** cinematic path is the same code; if `--style` is omitted nothing changes, so
  the switch is contained. Existing in-flight cinematic drafts unaffected until regenerated.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute
> placeholders for real data sources without explicit user approval. If a data source doesn't
> exist yet, STOP and ask.

### Goal
Switch every blog→carousel from the cinematic photo look to the flat **sketchnote infographic**
look the operator wants (Granola+Claude references), by (1) bundling the plugin's existing
`style-presets.md` into the pipeline refs so the VPS model can see the spec, and (2) wiring the
backend to pass `--style=sketchnote` + a knowledge-spine instruction. Foreshadow is dropped
**only for IG-repurpose drafts** (`--narrative=free`); normal blog carousels keep `--narrative=5act`.
A `linkedin_carousel_style` setting gives a no-redeploy revert to cinematic.

### Architecture Context (from CLAUDE.md + code)
- Carousel path: `LinkedInGenerationService::generate` (force-carousel) → `applyCarouselGenAdapter`
  ([:322](../../backend/app/Services/LinkedInGenerationService.php#L322)) → `dispatchCarouselGenEngine`
  ([:1041](../../backend/app/Services/LinkedInGenerationService.php#L1041)) → **`buildCarouselGenPrompt`**
  ([:1104](../../backend/app/Services/LinkedInGenerationService.php#L1104), pure/public test seam).
- IG/TikTok/Threads reuse LinkedIn's rendered 4:5 slides → one lever covers all platforms.
- Repurpose linkage: `RepurposeJob.{linkedin_post_id, anchor_post_id, content_idea_id}`
  ([RepurposeJob.php](../../backend/app/Models/RepurposeJob.php)); blog-mode repurpose tags the
  `ContentIdea.source='instagram'` (`result_post_id` → the draft's `post_id`).
- Plugin source repo (editable): `/Users/alisadikin/Drive-D/Projects/claude-plugin/ai-image-carousel-prompt-gen`
  (v2.23.1). Compiled bundle is **gitignored** (`references/compiled/`) → recompile on VPS.
- Settings convention: `Setting::get('linkedin_*', default)` kill-switches seeded idempotently in
  [`LinkedInSettingsSeeder`](../../backend/database/seeders/LinkedInSettingsSeeder.php).
- **No PHP on dev Mac** → backend tests authored full-fidelity, run in Docker sqlite / CI
  ([per memory: laravel-test-ci-pattern]). Plugin tests run via `vitest` locally.

### Tech Stack
Laravel 12 (PHPUnit, sqlite test DB) · plugin TS (`tsx`/`vitest`) · existing SSH `/carousel-gen` bridge.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Sketchnote preset spec | `references/style-presets.md` | `compile-refs.ts` sourceFiles | Yes (unbundled) | Add to array, recompile |
| VPS pipeline bundle | `/home/claudesn/refs-carousel-gen-pipeline.md` | symlink → cache compiled | Yes (stale) | Recompile on VPS (Phase E) |
| `--style` / `--narrative` flags | `buildCarouselGenPrompt` | method args | Yes | Add `$style`,`$isRepurpose` params |
| Repurpose detection | `RepurposeJob` + `ContentIdea.source` | new `isRepurposeDraft()` | No | Create predicate (real query) |
| Style kill-switch | `settings` table | `Setting::get('linkedin_carousel_style')` | No | Seed default `'sketchnote'` |
| Brand chrome | `CarouselSlideEnhancer` | placeholder tokens | Yes | Unchanged |

---

### Phase A: Bundle `style-presets.md` into the pipeline refs (plugin repo)

**Estimated time:** 8 minutes
**Repo:** `/Users/alisadikin/Drive-D/Projects/claude-plugin/ai-image-carousel-prompt-gen`

**Files:**
- Modify: `scripts/compile-refs.ts` (add `'style-presets.md'` to `sourceFiles`)
- Modify: `scripts/compile-refs.test.ts` (assert bundle contains the sketchnote spec)
- Generated (gitignored): `references/compiled/refs-carousel-gen-pipeline.md`

**Steps:**
1. Write failing test: in `compile-refs.test.ts` add `expect(content).toContain('Preset: \`sketchnote\`')` and `expect(content).toContain('DOODLE')`. Expected error: `AssertionError: expected '…' to contain 'Preset: \`sketchnote\`'`.
2. Run `npm test` (vitest), confirm it fails for that reason.
3. Add `'style-presets.md'` to the `sourceFiles` array in `compile-refs.ts` (after `'carousel-best-practices.md'`); update the file's header doc comment list.
4. Run `npm run compile-refs` then `npm test`, confirm bundle now contains sketchnote spec + all prior assertions pass.
5. Bump `package.json` version 2.23.1 → 2.24.0.
6. Commit: `feat(carousel-gen): bundle style-presets.md into pipeline refs (sketchnote available headless)`

**Verification:**
- [ ] `npm test` green (new sketchnote assertions + existing bundle assertions)
- [ ] `grep -c "DOODLE" references/compiled/refs-carousel-gen-pipeline.md` ≥ 5 (full gate present, not just name-drop)
- [ ] Bundle byte size grew ~+8KB vs prior
- [ ] No TODO/placeholder in the diff

---

### Phase B: `isRepurposeDraft()` predicate + thread params through the call chain (backend)

**Estimated time:** 12 minutes
**Repo:** Portfolio_v2/backend

**Files:**
- Modify: `app/Services/LinkedInGenerationService.php`
- Test: `tests/Feature/LinkedInIsRepurposeDraftTest.php` (new)

**Steps:**
1. Write failing test `LinkedInIsRepurposeDraftTest`: (a) draft with a `RepurposeJob{linkedin_post_id=draft.id}` → `isRepurposeDraft()` true; (b) draft whose `post` links `ContentIdea{result_post_id=post_id, source:'instagram'}` → true; (c) plain blog draft → false. Expected error: `Error: Call to undefined method …::isRepurposeDraft()`.
2. Run `php artisan test --filter=LinkedInIsRepurposeDraft` (Docker sqlite), confirm it fails for that reason.
3. Implement `public function isRepurposeDraft(LinkedInPost $draft): bool` — returns true if `RepurposeJob::where('linkedin_post_id',$draft->id)->orWhere('anchor_post_id',$draft->post_id)->exists()` OR (`$draft->post_id` and `ContentIdea::where('result_post_id',$draft->post_id)->where('source','instagram')->exists()`). Use real models; no stub.
4. Add trailing optional params (keeps existing tests/signatures valid): `applyCarouselGenAdapter(..., bool $isRepurpose = false)`, `dispatchCarouselGenEngine(..., bool $isRepurpose = false, string $style = 'sketchnote')`, `buildCarouselGenPrompt(array $brief, string $blogUrl, ?string $blogContent = null, bool $isRepurpose = false, string $style = 'sketchnote')`. Pass through unchanged (no flag emission yet — that's Phase C).
5. At the call site [:322], compute `$isRepurpose = $this->isRepurposeDraft($draft)` and `$style = (string) Setting::get('linkedin_carousel_style', 'sketchnote')`, pass both into `applyCarouselGenAdapter` → `dispatchCarouselGenEngine`.
6. Run `php artisan test --filter=LinkedInIsRepurposeDraft`, confirm pass. Run `php artisan test --filter=CarouselGenInlineContentPrompt` — existing prompt test must still pass (trailing-optional params = backward compatible).
7. Commit: `feat(carousel): isRepurposeDraft predicate + thread style/narrative params (no behavior change)`

**Verification:**
- [ ] New predicate test green (3 cases: repurpose-carousel, repurpose-blog, plain)
- [ ] Existing `CarouselGenInlineContentPromptTest` still green (no regression)
- [ ] `php -l` clean on `LinkedInGenerationService.php`
- [ ] No placeholder/TODO; predicate runs real queries

---

### Phase C: Emit `--style=sketchnote` + knowledge-spine + repurpose `--narrative=free` (backend)

**Estimated time:** 12 minutes
**Repo:** Portfolio_v2/backend

**Files:**
- Modify: `app/Services/LinkedInGenerationService.php` (`buildCarouselGenPrompt` body)
- Modify: `database/seeders/LinkedInSettingsSeeder.php` (seed `linkedin_carousel_style='sketchnote'`)
- Test: `tests/Unit/CarouselGenInlineContentPromptTest.php` (extend)

**Steps:**
1. Write failing assertions in `CarouselGenInlineContentPromptTest`: (a) prompt always contains `--style=sketchnote` when `$style='sketchnote'`; (b) `$isRepurpose=true` → contains `--narrative=free` and NOT `--narrative=5act`; (c) `$isRepurpose=false` → contains `--narrative=5act`; (d) prompt contains the knowledge-spine marker string (e.g. `KNOWLEDGE-FIRST INFOGRAPHIC`). Expected error: `Failed asserting that '…' contains '--style=sketchnote'`.
2. Run `php artisan test --filter=CarouselGenInlineContentPrompt`, confirm fail for that reason.
3. Implement in `buildCarouselGenPrompt`: set `$narrative = $isRepurpose ? 'free' : '5act'`; add `'--style=' . $style` to `$flags`; keep `--bilingual=id,en` + `--target-slides`. When `$style==='sketchnote'`, append a concise **KNOWLEDGE-FIRST INFOGRAPHIC** instruction block: every body/peak slide = a self-contained mini-infographic (numbered icon-rows / comparison / annotated mechanism), cover states the promise (no absurd hook needed), ≤7 slides, dense but legible. Keep the existing inline-article-body append. Stay pure (no DB/config reads — values arrive as params).
4. Add `'linkedin_carousel_style' => 'sketchnote'` to `LinkedInSettingsSeeder` via `firstOrCreate` (idempotent).
5. Run `php artisan test --filter=CarouselGenInlineContentPrompt`, confirm all (old + new) pass.
6. Commit: `feat(carousel): default sketchnote style + knowledge-spine; drop foreshadow for IG-repurpose`

**Verification:**
- [ ] All `CarouselGenInlineContentPromptTest` cases green (style always, narrative branches, spine present)
- [ ] `buildCarouselGenPrompt` remains pure (no `Setting::`/`config()` inside it — grep confirms)
- [ ] Seeder idempotent (`firstOrCreate`), re-seed yields 0 new rows on existing DB
- [ ] `php -l` clean

---

### Phase D: CLAUDE.md sync (backend)

**Estimated time:** 5 minutes

**Steps:**
1. Update root `CLAUDE.md` `linkedin` settings table: add `linkedin_carousel_style` row (default `'sketchnote'`, purpose: cinematic↔sketchnote switch). Update the `linkedin_force_carousel` / carousel-gen narrative note to record sketchnote default + IG-repurpose `--narrative=free`.
2. Add a Last-Updated changelog entry summarizing the two-repo change.
3. Commit: `docs(carousel): sketchnote infographic style + linkedin_carousel_style setting`

**Verification:**
- [ ] Settings table + changelog reflect the actual flags/setting shipped
- [ ] No stale claim that carousels are cinematic-only

---

### Phase E: VPS deploy + QA regenerate (operator runbook — NOT auto)

**Estimated time:** 10 minutes (operator)

**Steps (operator, on VPS as `claudesn`):**
1. Plugin: `cd <plugin repo on VPS> && git pull` to v2.24.0, then `npm install && npm run compile-refs` → regenerates `references/compiled/refs-carousel-gen-pipeline.md` (the `/home/claudesn/refs-carousel-gen-pipeline.md` symlink picks it up).
2. Confirm bundle: `grep -c DOODLE /home/claudesn/refs-carousel-gen-pipeline.md` ≥ 5.
3. Backend: `git push` (CI/CD `deploy.sh` runs `migrate --force` + idempotent `LinkedInSettingsSeeder` → seeds `linkedin_carousel_style`), then `php artisan config:cache && systemctl restart portfolio-queue.service`.
4. QA: regenerate draft 149 (`/admin/linkedin-drafts/149` → Regenerate All Images, or `regenerate` to re-author slides) → eyeball at `/admin/sosmed-drafts/149`. Expect cream-paper doodle infographic, no creator-face photo, no foreshadow, dense numbered body rows.
5. If text garbles on dense slides → fallback lever: flip slides to single-language ID (documented in Design open-decision #2) or per-slide regenerate.

**Verification:**
- [ ] `claude -p "/carousel-gen --style=sketchnote --help"` (or a regenerate) produces doodle-style `image_prompt`s (cream paper, doodle icons), not cinematic film-stock prose
- [ ] Draft 149 re-rendered matches the sketchnote reference materially (operator visual sign-off)
- [ ] `Setting::get('linkedin_carousel_style')` returns `'sketchnote'` on VPS
- [ ] Normal (non-repurpose) carousel still emits `--narrative=5act`; repurpose emits `--narrative=free`

---

### Execution notes
- **Phases A and B are independent** (different repos, no shared files) → can run in parallel.
  C depends on B (consumes the threaded params). D depends on C. E depends on A+C+D.
- This is an **LLM-output-shaping** change (prompt flags steer Sonnet + Nano Banana). Deterministic
  unit tests verify the *flags emitted*; the *rendered aesthetic* is verified by the Phase E
  operator visual sign-off (no automated eval — image fidelity is human-judged here).
