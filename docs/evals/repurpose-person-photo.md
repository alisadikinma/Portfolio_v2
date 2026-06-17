# Eval — people_spotlight (real founder/people photos on profile carousel slides)

**Feature:** [docs/plans/2026-06-17-repurpose-founder-photo-human-touch.md](../plans/2026-06-17-repurpose-founder-photo-human-touch.md)
**Non-deterministic surfaces:** (1) plugin detection — does `/carousel-gen` flag a person-profile
slide `needs_real_faces` + fill `people[]`; (2) `SourceFaceLocator` — does the vision pass find the
named person's face + a sane bbox in the captured source slides. Deterministic unit/feature tests
cover the plumbing; these evals are the *output-quality* contract (`gaspol-verify` runs them).

## Fixtures (real, from production)

| # | Source | Draft | Job | Source slides | Profile slide | People | Faces |
|---|---|---|---|---|---|---|---|
| F1 | IG Cursor acquisition | 172 | RJ 33 | `storage/app/repurpose/33/slide-01..09.jpg` | "SIAPA CURSOR? 4 MIT Dropouts" | 4 founders | group / N |
| F2 | IG Attention paper | 161 | RJ 20 | `storage/app/repurpose/20/slide-01..13.jpg` | "SIAPA ASHISH VASWANI?" | Ashish Vaswani | single / 1 |

## Capability evals (must pass)

### E1 — plugin flags the profile slide (detection)
Run `/carousel-gen` pipeline mode on the F1 + F2 captions. **Pass@3 ≥ 2/3** that the profile slide
(`SIAPA <Name>?`) carries `needs_real_faces:true` with `people[]` = the named person(s) and a
`face_layout` (not `none`), AND its `image_prompt` reserves a clear photo band (no name baked as
on-image text, no doodled/invented person).

### E2 — plugin does NOT over-flag (specificity)
On the same runs, **every** non-profile concept/teaching body slide leaves `needs_real_faces` unset
(no false positives → no empty reserved bands on concept slides).

### E3 — locator finds the right face (vision)
`SourceFaceLocator::locate(F1.slidePaths, ['Michael Truell',...])` and
`locate(F2.slidePaths, ['Ashish Vaswani'])`. **Pass@3 ≥ 2/3** that ≥1 requested person resolves to a
plausible bbox (centred on a head, w/h ∈ [0.05, 0.9]) on a slide that actually shows them; unknown
names never fabricated.

### E4 — end-to-end human touch (visual)
"Regenerate All Images" on draft 172 + 161 on the VPS → the profile slide renders with the real
founder photo(s) framed in the band; a non-profile slide is untouched. Manual visual sign-off.

### E5 — group fallback for UNLABELLED people (the real Cursor blocker)
`SourceFaceLocator::locate()` matches faces BY NAME against visible labels, but a founders GROUP
photo ("4 MIT dropouts") shows the people **together, unlabelled** → 0 name matches → empty band.
`locateGroup($paths, $people, $topic)` is the fallback: it finds the ONE slide that is the group
portrait (by topic text + headcount) and returns EVERY face bbox on it, left-to-right, capped to the
headcount, **without name attribution** (showing the real faces is the human touch; guessing whom is
which would be wrong). The enricher invokes it whenever name-matching yields fewer faces than
`people`. **Pass@3 ≥ 2/3** that for F1 (`SourceFaceLocator::locateGroup(F1.paths, [4 founders],
'SIAPA CURSOR? 4 MIT Dropouts')`) it picks the founders group slide and returns ~4 plausible boxes.
> **Verified live 2026-06-17** (VPS, real job 33 slides): picked slide 4, returned 4 left-to-right
> boxes `x≈[0.07,0.27,0.46,0.67]`. The stale-bundle recompile (Cause #1) was necessary but NOT
> sufficient — without the group fallback the named-but-unlabelled founders never resolve.

## Regression evals (must not break)

- **R1** — a blog→carousel draft (no RepurposeJob) is a no-op: no slide gains `person_photo_refs`,
  no band reserved by the backend. (Covered: `CarouselPersonPhotoEnricherTest::test_it_is_a_noop_for_non_repurpose_drafts`.)
- **R2** — a profile slide whose people can't be located leaves the plain rendered slide (no empty
  composite, no crash). (Covered: enricher `marks_resolved_without_rerender`, wiring `keeps_plain_slide_when_composite_fails`.)
- **R3** — legacy carousel_slides (no contract fields) parse + render unchanged. (Covered: adapter
  `passes_through` defaults + plugin schema back-compat tests.)

## Deterministic test coverage (green)

| Layer | File | Tests |
|---|---|---|
| Plugin schema | `schema.test.ts` (people_spotlight) | 8 |
| Plugin authoring rule | `refs-content.test.ts` (People Spotlight) | 5 |
| Adapter passthrough | `CarouselGenOutputAdapterTest` | +2 (11 total) |
| Face locator | `SourceFaceLocatorTest` | 9 |
| Enricher (crop) | `CarouselPersonPhotoEnricherTest` | 4 |
| Composite layout | `carousel-person-strip-layout.test.cjs` | 5 |
| Composite renderer | `CarouselPersonStripRendererTest` | 4 |
| Webhook wiring | `CarouselPersonPhotoWiringTest` | 3 |

## How to run

```bash
# deterministic
cd backend && vendor/bin/phpunit tests/Unit/CarouselGenOutputAdapterTest.php \
  tests/Unit/SourceFaceLocatorTest.php tests/Feature/CarouselPersonPhotoEnricherTest.php \
  tests/Unit/CarouselPersonStripRendererTest.php tests/Feature/CarouselPersonPhotoWiringTest.php
node scripts/repurpose/__tests__/carousel-person-strip-layout.test.cjs
cd <plugin> && npx vitest run

# capability (needs VPS Claude CLI + the source slides) — manual until automated
#   E1/E2: claude -p "/carousel-gen --pipeline --blog-source <F1 caption>"  → inspect slides
#   E3:    tinker → app(SourceFaceLocator::class)->locate(<F2 paths>, [['name'=>'Ashish Vaswani']])
#   E4:    /admin/sosmed-drafts/172 → Regenerate All Images → visual check
```
