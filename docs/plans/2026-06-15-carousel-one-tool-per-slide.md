# Repurpose carousel: one Tool/Skill/Plugin per slide (caption-driven, fact-checked)

**Date:** 2026-06-15
**Status:** PLAN — decisions LOCKED, ready to implement
**Operator decisions (2026-06-15):**
- Slide count follows the SOURCE's natural tool count (10 tools → 10 slides). No artificial ceiling — a real listicle is never "puluhan" slides; keep only a sanity guard.
- Per-tool slide content authored via a **light CLI call per tool** (on-brand, opsi A) — NOT a deterministic template.
- The source caption is the **reference + fact-check basis**: fact-check it, and if it holds, follow it but **rewritten in Ali's voice** (the existing research→rewrite flow).
- Every slide — body tools, hook, AND cta — is **bilingual: `copy_id` (ID, primary/main) + `copy_en` (EN companion)**.

---

## 1. Problem (verified on prod, draft 164 / job 25)

Repurpose carousel "10 GitHub tools" shipped only **7 slides** → 2–3 tools crammed per body slide. Cause: the repurpose carousel reuses the original-content path — [`FinalizeRepurpose::finalizeCarousel`](../../backend/app/Jobs/FinalizeRepurpose.php#L255) → [`GenerateLinkedInPost`](../../backend/app/Jobs/FinalizeRepurpose.php#L305) → force-carousel `/carousel-gen` with [`inferTargetSlides()=7`](../../backend/app/Services/LinkedInGenerationService.php#L1206). It re-narrates the list into a 7-slide 5-act story.

## 2. Where the reliable tool list actually lives (key finding)

**NOT the captured slide images.** The IG capture for job 25 grabbed **28 frames** but vision-extraction found only **1 real tool slide** (`role=point`: AutoHedge) + 1 hook; the other 26 are noise — random photos and OTHER carousels' covers from the same creator ("GOATED VS CODE EXTENSIONS", "7 YOUTUBE COURSES", "Claude Fable 5 vs Opus 4.8"…). The capture pulled the profile grid / unrelated posts, not the single carousel. So "mirror the captured slides 1:1" is built on garbage.

**The clean, complete tool list lives in `extracted.caption`:**
> "…10 open-source gems… 1) AutoHedge 2) Vibe Trading 3) Fincept Terminal 4) LibreChat 5) Open Higgsfield AI 6) Open LLM VTuber 7) Claude Ads 8) Agentic Inbox 9)… 10)…"

…plus `extracted.claims[]` (17 per-tool facts). `ResearchRepurposeClaims` already **fact-checks** these; `RewriteRepurposeContent` already rewrites in Ali's voice (but as a narrative `rewritten.body` HTML article — not a per-tool list). So the count + per-tool facts are reliably available; only the per-tool *slide* structuring is missing.

## 3. Solution — caption-driven, fact-checked, 1 tool = 1 bilingual slide

Repurpose carousels ONLY (original blog→carousel untouched):

1. **Parse the tool list from `extracted.caption`** (the numbered `N) Tool — desc` list) → N distinct tools = the slide budget. `claims` + `research` supply each tool's fact-checked detail. (Captured frames are ignored — too noisy.)
2. **Slide count = N tools.** No artificial cap; a sanity guard (e.g. 20, IG hard max) only logs + trims the absurd case. 10 tools → 10 body slides.
3. **One bilingual slide per tool (opsi A — CLI per tool).** For each tool, a light `/carousel-gen`-style CLI call authors a sketchnote `image_prompt` + `copy_id` (ID primary) + `copy_en` (EN) from its **fact-checked** caption line + matching claims, in Ali's voice. Per-tool & independent → no monolithic envelope → the Sonnet truncation that forced the ≤7 cap **cannot occur**.
4. **Hook + CTA replaced, bilingual.** Cover → Ali's branded hook (`copy_id`+`copy_en`, curiosity gap; reuse [`CarouselCoverFigureEnricher`](../../backend/app/Services/CarouselCoverFigureEnricher.php) when a public figure is named). Last → Ali's single-command CTA (`copy_id`+`copy_en`). Source hook/promo frames dropped (like `video_rebrand` drops bookends).
5. **Post caption (text body) unchanged.** Keep the existing fact-checked, Ali-voiced rewrite/caption-rebuild — it already realizes "source caption → fact-check → Ali's style".
6. **Then the normal image pipeline** — `GenerateLinkedInCarouselImages` + `CarouselSlideEnhancer` (brand chrome) + cross-post fan-out. No downstream change.

Philosophy mirrors `video_rebrand` (drop source bookends, rebrand each content unit), but the content UNIT is sourced from the **fact-checked caption list**, not the noisy frames.

## 4. Integration point

- **New** `BuildRepurposeCarouselFromSource` (job/service): parse caption → tool list; per-tool CLI author (bilingual, fact-checked, Ali voice) → assemble `carousel_slides[]` (cover + N tools + cta) onto the `LinkedInPost`; dispatch `GenerateLinkedInCarouselImages`.
- [`FinalizeRepurpose::finalizeCarousel`](../../backend/app/Jobs/FinalizeRepurpose.php#L255) dispatches THIS for carousel mode instead of `GenerateLinkedInPost`. Runs after `ResearchRepurposeClaims` (fact-check) + `RewriteRepurposeContent` (Ali voice) so their output is available.
- Original blog→carousel never hits `finalizeCarousel` → unaffected.

## 5. Data Integration Map

| Need | Source | Path |
|---|---|---|
| Tool list + count | `extracted.caption` numbered list | parse `N) Name — desc` |
| Per-tool facts (fact-checked) | `extracted.claims` + `research` | ResearchRepurposeClaims output |
| Ali-voice tone | `rewritten` (voice reference) | RewriteRepurposeContent |
| Per-tool slide author | light CLI + carousel-gen ref bundle | new builder, opsi A, bilingual `copy_id`+`copy_en` |
| Hook replacement | visual-identity / figure enricher | `CarouselCoverFigureEnricher` + sketchnote hook |
| CTA replacement | single-command standard, bilingual | template/CLI |
| Render + chrome + cross-post | unchanged | `GenerateLinkedInCarouselImages`, `CarouselSlideEnhancer`, Publer |

## 6. Phases (TDD)
1. **A — caption parser** (unit): `extracted.caption` → ordered distinct tools `[{name, desc}]`; handles `1) .. 2) ..` numbering; ignores captured frames. Sanity-cap at 20 with `log()`. `RepurposeCaptionToolListTest`.
2. **B — per-tool slide author** (unit, CLI mocked): each tool → slide with non-empty `copy_id` AND `copy_en`, `image_prompt`, fact-checked detail. Assert bilingual on every slide.
3. **C — hook/CTA** (unit): cover = Ali hook (+ figure enricher when applicable), last = single-command CTA; **both carry `copy_id`+`copy_en` — assert neither English-only**; source hook/promo dropped.
4. **D — assembly + integration** (feature): builder → `carousel_slides` count == tool count (10), 1 tool/slide; `finalizeCarousel` dispatches the builder, NOT `GenerateLinkedInPost`; blog→carousel path untouched.
5. **E — VPS/verify**: re-run job 25 → 10 tool slides (AutoHedge…#10), 1 tool each, bilingual ID+EN hook/CTA, no truncation.

## 7. Anti-patterns to avoid
- ❌ Mirroring the 28 captured frames — they're profile-grid noise; the tool list is the CAPTION.
- ❌ Skipping fact-check — per-tool text must come from the fact-checked claims, not raw source copy.
- ❌ English-only hook/CTA — both must be `copy_id` (primary) + `copy_en`.
- ❌ Monolithic N-slide envelope — author per-tool independently (kills truncation).
- ❌ Touching the original blog→carousel path — scope is repurpose only.
- ❌ Re-implementing visual rules in backend — author via the reference bundle (CLAUDE.md single-source-of-truth); backend only maps structure + count.

---

**Related:** [2026-06-12-ig-video-carousel-rebrand.md](2026-06-12-ig-video-carousel-rebrand.md) (`video_rebrand` twin), [2026-06-11-carousel-sketchnote-infographic.md](2026-06-11-carousel-sketchnote-infographic.md) (≤7 cap + knowledge-first), [2026-06-10-telegram-ig-repurpose-carousel.md](2026-06-10-telegram-ig-repurpose-carousel.md) (repurpose pipeline + SlideVisionExtractor + research/rewrite).
