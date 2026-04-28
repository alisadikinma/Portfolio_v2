# LinkedIn Carousel Engine Decoupling — Design

**Date:** 2026-04-28
**Status:** Design approved (Opsi D), pending implementation plan
**Author:** Ali Sadikin
**Related:** [docs/plans/2026-04-23-linkedin-admin-ui.md](2026-04-23-linkedin-admin-ui.md)
**Type:** Hybrid file — `## Design` (this) + `## Implementation Plan` (appended by `gaspol-plan`)

---

## Design

### Context

April 27, 2026 shipped LinkedIn carousel image rendering end-to-end:
- Plugin `linkedin-post-writer` v0.4.6+ skill `/linkedin-carousel` authors slide JSON
- Backend `LinkedInGenerationService` parses `OrchestratorOutputSchema`, dispatches `GenerateLinkedInCarouselImages` job, `CarouselSlideEnhancer` injects brand chrome, GeminiGen renders slides
- Spec doc `07-carousel-image-standards.md` was authored to mirror standards from sister plugin `ai-image-carousel-prompt-gen` into LinkedIn refs

This created a **duplication concern**: the carousel image authoring rules live in TWO plugins (LinkedIn-specific subset in `refs-linkedin-carousel.md` + general framework in `ai-image-carousel-prompt-gen` references). Maintenance drift risk is real — every update to cinematography/hook/visual standards needs syncing in two places.

Strategic motivation (user-stated): **"setiap plugin punya dedicated task dan bisa dipakai utk orang lain juga yg mau gunakan plugin saya"**. Clean plugin separation enables third-party adoption without bundled domain coupling.

### Cross-Platform Carousel Research (2026)

| Aspect | LinkedIn | IG Feed | TikTok Photo |
|---|---|---|---|
| Aspect ratio | 4:5 (1080×1350) | 4:5 (1080×1350) | 9:16 (1080×1920) |
| Slide count | 5-10 (B2B: 8-12) | 8-10 | 5-10 |
| Engagement avg | 24.42% (B2B) | 1.92% | varies (music-boosted) |
| Upload format | PDF preferred | JPG/PNG | JPG/PNG |
| Link strategy | Link-in-comment | Link-in-bio | Link-in-profile |
| Hashtag count | 3-5 | 10-30 | 3-5 |
| Dead zones | 75px margins | Standard | Top 150px + bottom 250px |

**Key finding:** LinkedIn + IG Feed are **90% spec-identical** (same aspect ratio, same slide range, same hook-first rule, similar dead zones). What's actually LinkedIn-exclusive turns out to be **publisher-side concerns**: PDF composition, link-in-comment discipline, Depth Score gate, hashtag count rules. Content authoring (cinematography, hook, narrative depth) is platform-agnostic with minor optimization deltas.

Sources consulted:
- [LinkedIn Carousel Specs 2026 — Postiv](https://postiv.ai/blog/linkedin-carousel-size)
- [Instagram Carousel 2026 — HeyOrca](https://www.heyorca.com/blog/instagram-media-specs-best-practices-2026)
- [TikTok Photo Carousel — PostFast](https://postfa.st/sizes/tiktok/carousel)
- Plugin internal: `ai-image-carousel-prompt-gen/references/platform-specs.md`

### Goals

1. **Plugin separation of concerns** — `linkedin-post-writer` owns LinkedIn text-post + brief + validate; `ai-image-carousel-prompt-gen` owns universal carousel image authoring
2. **Zero LinkedIn coupling** in `ai-image-carousel-prompt-gen` — orang lain bisa adopt plugin tanpa domain pollution
3. **Preserve narrative quality** — bilingual headline (`copy_id`/`copy_en`), `direct_answer_block`, `human_fingerprint` slides become **cross-platform best-practice defaults**, not LinkedIn-only opt-ins
4. **Backward-compat** — existing `linkedin_posts.carousel_slides` JSON shape preserved (no DB migration), draft #28+ continues to render
5. **Future-flex** — adding IG/TikTok publishers later requires only new dispatcher, not new content engine

### Non-Goals

- ❌ Build IG/TikTok publishers in this iteration (engine-ready, dispatcher deferred)
- ❌ Refactor existing LinkedIn text-post pipeline (`/linkedin-convert` + `/linkedin-validate` stay unchanged)
- ❌ Refactor `CarouselSlideEnhancer` — publisher-side brand chrome injection stays
- ❌ Change `linkedin_posts.carousel_slides` JSON shape (adapter normalizes upstream)
- ❌ Build PDF composition (TCPDF + DocumentShare deferred — `publishCarousel` still 503)
- ❌ Add interactive ambiguity in pipeline mode (defaults from creator-bible only)

### Decision: Opsi D — Universal Carousel Engine + Publisher-Side Platform Concerns

After evaluating four options (see decision log below), Opsi D selected:

| Opsi | Effort | LinkedIn ROI Loss | Distribution Cleanliness | Future-flex |
|---|---|---|---|---|
| A (linkedin opt-in flag) | HIGH | None | Medium | High |
| B (drop all narrative) | LOW | HIGH | High | Medium |
| C (post-processor enhance) | MEDIUM | None | Low (drift risk) | Medium |
| **D (universal engine + publisher-side)** | **MEDIUM** | **None** | **HIGHEST** | **HIGHEST** |

### Architecture

```
┌──────────────────────────────────────────────────────────┐
│ /linkedin-brief (linkedin-post-writer)                    │
│ Input: blog → Output: brief {format, hook, pillar, ...}   │
└────────────────────────────┬─────────────────────────────┘
                             │
              ┌──────────────┴──────────────┐
              │                              │
     format='text'                  format='carousel'
              │                              │
              ▼                              ▼
   /linkedin-convert              /carousel-gen
   (linkedin-post-writer)         (ai-image-carousel-prompt-gen)
   text post 1100-1300 chars      --pipeline
              │                   --blog-source=<url>
              ▼                   --bilingual=id,en (optional)
   /linkedin-validate             --narrative=5act (optional)
   Depth Score ≥80                          │
              │                              ▼
              │                   CarouselGenOutput JSON
              │                              │
              │                              ▼
              │                   CarouselGenOutputAdapter
              │                   (backend, NEW)
              │                              │
              └──────────────┬───────────────┘
                             │
                             ▼
              linkedin_posts.carousel_slides JSON
              (existing shape preserved)
                             │
                             ▼
              GenerateLinkedInCarouselImages job
                             │
                             ▼
              CarouselSlideEnhancer
              (brand chrome injection — UNCHANGED)
                             │
                             ▼
              GeminiGen render → webhook
                             │
                             ▼
              [Operator approve → publish]
                             │
                             ▼
              LinkedInPublishService
              (PDF compose + DocumentShare — deferred)
```

### Component Changes

#### `linkedin-post-writer` plugin (bump v0.5.0)

| Component | Action | Notes |
|---|---|---|
| `skills/linkedin-carousel/` | **DELETE** | Move to `deprecated/` for git history; remove from plugin manifest |
| `references/compiled/refs-linkedin-carousel.md` | **DELETE** | Drop from `scripts/compile-refs.ts`, ensure `compile-refs` build doesn't reference it |
| `skills/linkedin-gen/SKILL.md` | **REFACTOR** | Text-only path; for carousel format return `{status: 'route_to_carousel_gen', brief: {...}}` and exit. No inline carousel logic. |
| `skills/linkedin-gen/schema.ts` `OrchestratorOutputSchema` | **REFACTOR** | Add `route_to_carousel_gen` status variant; carousel envelope still needed for backward-compat parser tolerance |
| Other skills (`linkedin-brief`, `linkedin-convert`, `linkedin-validate`) | **UNCHANGED** | LinkedIn text-post focus preserved |

#### `ai-image-carousel-prompt-gen` plugin (bump v3.0.0 — major)

| Component | Action | Notes |
|---|---|---|
| `skills/carousel-gen/SKILL.md` | **REFACTOR** | Add pipeline-mode (auto-detect from `--blog-source`); add `--bilingual=id,en` flag; add `--narrative=5act\|free` flag; add `--alt-aspect=9:16` flag (future-prep, lazy-load) |
| `skills/carousel-gen/schema.ts` | **NEW** | Zod schema `CarouselGenOutputSchema` — stable shape with optional `bilingual`, `direct_answer_block`, `human_fingerprint`, slide layout types |
| `references/carousel-best-practices.md` | **UPDATE** | Add 5-act narrative spine (HOOK→FORESHADOW→BODY→PEAK→CTA), `human_fingerprint` slide spec, `direct_answer_block` spec — as cross-platform best practices |
| `references/platform-specs.md` | **UPDATE** | Add `dead_zones` per platform, `link_strategy` per platform (publisher-side info, advisory) |
| New `references/non-interactive-defaults.md` | **NEW** | Default rules for ambiguity: profession costume → use creator-bible default wardrobe; setting ambiguity → use creator-bible default; log warning |
| `references/global-config.md` | **UPDATE** | Document new flags + bilingual contract (when enabled) |

#### Portfolio backend (`Portfolio_v2`)

| Component | Action | Notes |
|---|---|---|
| `backend/app/Services/LinkedInGenerationService.php` | **REFACTOR** | Becomes router: branch on brief.format. Text → SSH `/linkedin-convert` (or stay with `/linkedin-gen` for text format). Carousel → SSH `/carousel-gen --pipeline --blog-source=<url> --bilingual=id,en --narrative=5act` |
| `backend/app/Services/CarouselGenOutputAdapter.php` | **NEW** | Map `CarouselGenOutput` JSON → `linkedin_posts.carousel_slides` shape. Preserve `slide_number`, `layout_hint` (cover/body/human_fingerprint/direct_answer/cta), `is_cover`, `is_cta`, `direct_answer_block`. Handle bilingual `{copy_id, copy_en}` OR single `copy`. |
| `backend/app/Services/CarouselSlideEnhancer.php` | **UNCHANGED** | Publisher-side brand chrome injection (`{{CREATOR_FACE}}`, `{{BRAND_LOGO}}`, `{{HANDLE}}`) keeps working — operates on adapter output |
| `backend/app/Jobs/GenerateLinkedInCarouselImages.php` | **UNCHANGED** | Job receives slides from adapter output — no change needed |
| `backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php` | **UNCHANGED** | Webhook unchanged |
| `backend/config/linkedin.php` | **UPDATE** | Drop `LINKEDIN_GEN_REFS_CAROUSEL` env. Add `CAROUSEL_GEN_*` config section (mirrors `LINKEDIN_GEN_*` SSH pattern): `driver`, `ssh_host/user/key`, `claude_path`, `model`, `refs_pipeline`, `timeout_seconds` |
| `.env.example` | **UPDATE** | Document new `CAROUSEL_GEN_*` env vars, deprecate `LINKEDIN_GEN_REFS_CAROUSEL` |
| Existing draft #28+ | **NO CHANGE** | Adapter consumes `OrchestratorOutputSchema`-shape data through legacy path; new dispatches go through `/carousel-gen` |

### Schema Changes

#### `CarouselGenOutputSchema` (new — in `ai-image-carousel-prompt-gen/skills/carousel-gen/schema.ts`)

```typescript
const CarouselSlideSchema = z.object({
  slide_number: z.number().int().min(1),
  layout_hint: z.enum(['cover', 'body', 'human_fingerprint', 'direct_answer', 'cta']),
  copy: z.string().optional(),                    // single-language mode
  copy_id: z.string().optional(),                 // bilingual mode (Indonesian)
  copy_en: z.string().optional(),                 // bilingual mode (English)
  image_prompt: z.string().min(300).max(2500),
  is_cover: z.boolean(),
  is_cta: z.boolean(),
  direct_answer_block: z.string().min(150).max(600).optional(), // only on direct_answer slide
}).superRefine((slide, ctx) => {
  // Either copy OR (copy_id AND copy_en), not both modes
  const hasSingle = !!slide.copy;
  const hasBilingual = !!(slide.copy_id && slide.copy_en);
  if (!hasSingle && !hasBilingual) {
    ctx.addIssue({ code: 'custom', message: 'slide must have copy OR (copy_id + copy_en)' });
  }
  if (hasSingle && hasBilingual) {
    ctx.addIssue({ code: 'custom', message: 'slide cannot mix single + bilingual copy' });
  }
  // Per-layout length limits applied here
});

const CarouselGenOutputSchema = z.object({
  status: z.enum(['complete', 'failed']),
  format: z.literal('carousel'),
  total_slides: z.number().int().min(5).max(15),
  aspect_ratio: z.enum(['4:5', '1:1', '9:16']).default('4:5'),
  bilingual: z.boolean().default(false),
  narrative: z.enum(['5act', 'free']).default('5act'),
  slides: z.array(CarouselSlideSchema).min(5).max(15),
  // Optional alt-aspect render hint (future TikTok/Reels)
  alt_aspect: z.object({
    aspect_ratio: z.enum(['9:16']),
    slides: z.array(CarouselSlideSchema), // re-rendered for alt aspect
  }).optional(),
  generated_at: z.string().datetime(),
});
```

#### `linkedin_posts.carousel_slides` (existing shape — adapter normalizes to this)

```json
{
  "slide_number": 1,
  "layout_hint": "cover",
  "copy_id": "Why $60B isn't crazy",
  "copy_en": "The math behind the OpenAI valuation",
  "image_prompt": "...",
  "is_cover": true,
  "is_cta": false,
  "direct_answer_block": null,
  "image_status": "pending|generating|done|failed",
  "image_url": null,
  "image_job_uuid": null,
  "image_error": null
}
```

Adapter handles:
- Single-language `copy` → set `copy_id` = copy, `copy_en` = null (or vice versa based on detected language)
- Bilingual mode pass-through
- `image_status`/`image_url`/`image_job_uuid`/`image_error` initialized to `pending`/null

### Migration Strategy

**Phase A: Forward-compat (no breakage)**
1. Build `CarouselGenOutputAdapter` + `/carousel-gen` pipeline mode in parallel branch
2. Add feature flag `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false` (default off)
3. New dispatches gated by flag; legacy `/linkedin-carousel` path stays default
4. Tests: parity test — same blog input through both engines should produce structurally compatible output (modulo prompt text differences)

**Phase B: Cutover**
1. Flip `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=true` for new dispatches
2. Existing draft #28+ unaffected — they already have rendered slides in DB
3. Monitor for 1 week — Telegram alert on adapter errors

**Phase C: Cleanup**
1. Delete `/linkedin-carousel` skill folder
2. Drop `refs-linkedin-carousel.md` from `compile-refs.ts`
3. Bump `linkedin-post-writer` v0.5.0
4. Drop `LINKEDIN_GEN_REFS_CAROUSEL` env

### Risks + Mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| `/carousel-gen` interactive prompts hang in non-interactive pipeline mode | HIGH | Build `references/non-interactive-defaults.md`; auto-detect pipeline mode from `--blog-source` flag; fail-fast with clear error if ambiguity unavoidable |
| Bilingual contract regression — copy_id/copy_en go missing | MEDIUM | Schema-strict via Zod; adapter validates; integration test on draft #28-style input |
| Existing draft #28+ slides break post-cleanup | MEDIUM | Phase A/B/C cutover; never modify existing JSON; adapter only writes new slides |
| `direct_answer_block` slide type breaks existing `/carousel-gen` users | LOW | Optional field; default narrative=`free` (no enforcement); only `--narrative=5act` activates direct_answer requirement |
| Plugin `ai-image-carousel-prompt-gen` v3.0.0 breaks third-party adopters | MEDIUM | Major version bump signals breaking; CHANGELOG.md migration guide; non-interactive mode opt-in via flag |
| `LinkedInGenerationService` SSH timeout on `/carousel-gen` (longer prompt) | LOW | Reuse existing 300s timeout; carousel-gen prompt is similar length to linkedin-carousel |
| Brand chrome injection via `CarouselSlideEnhancer` breaks if adapter misses placeholder tokens | MEDIUM | Adapter MUST preserve `{{CREATOR_FACE}}`, `{{BRAND_LOGO}}`, `{{HANDLE}}`, `{{PORTFOLIO_URL}}`, `{{PAGE_INDICATOR}}`, `{{SWIPE_TEXT}}` placeholders in `image_prompt` if `/carousel-gen` doesn't natively emit them |

### Open Questions for `gaspol-plan`

1. **`/carousel-gen` placeholder strategy** — does plugin author prompts WITH `{{CREATOR_FACE}}` placeholders (matching LinkedIn pattern), or does adapter inject placeholders post-hoc? Recommendation: keep placeholders in plugin (cleaner separation, plugin still platform-agnostic since placeholders are operator-defined).
2. **Bilingual default when `--blog-source` is Indonesian-language blog** — auto-detect language and enable bilingual? Or require explicit flag? Recommendation: explicit flag (predictability over magic).
3. **Slide count default** — current `/linkedin-carousel` defaults to 9 (listicle); `/carousel-gen` doesn't have hard default. Adapter should pass `--target-slides=N` based on blog shape detection (listicle → 9, framework → 6, case-study → 8)?
4. **Test fixtures** — which existing draft (#26, #28, #29) to use as parity benchmark? #28 has clean carousel JSON. Recommendation: snapshot #28 input, run both engines, diff output for structural compatibility.
5. **Telegram notification on adapter error** — re-use existing `DispatchTelegramNotification` job with new event type `carousel_adapter_error`?
6. **`/linkedin-gen` orchestrator behavior post-refactor** — when brief.format=carousel, return `{status: 'route_to_carousel_gen', brief: {...}}` AND skip Step 2-4. Backend dispatches `/carousel-gen` separately. Confirm this matches admin UI expectations.

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| `/carousel-gen` pipeline mode | stdin/argv blog JSON | **NEW** | Auto-detect from `--blog-source` flag → non-interactive defaults |
| `/carousel-gen` Zod schema | `plugin/schemas/carousel-output.ts` | **NEW** | Stable shape with optional `bilingual`, `direct_answer_block`, `human_fingerprint` |
| `/carousel-gen` narrative defaults | `references/carousel-best-practices.md` | UPDATE | Add 5-act spine, human_fingerprint, direct_answer as defaults |
| `/carousel-gen` `--bilingual=id,en` | new flag | **NEW** | Opt-in; when set output `copy_id` + `copy_en`, else `copy` |
| `/carousel-gen` `--blog-source=URL` | new input mode | **NEW** | Auto-derive narrative from blog post body |
| `/carousel-gen` compiled refs | new `refs-carousel-gen-pipeline.md` | **NEW** | Bundle global-config + creator-bible + hook-formula-bank + cinematography-LUT |
| `/linkedin-gen` orchestrator | existing skill | REFACTOR | Text-only path; route carousel format to `/carousel-gen` |
| `/linkedin-carousel` skill | `linkedin-post-writer/skills/linkedin-carousel/` | **DELETE** (Phase C) | Move to deprecated/ + remove from plugin manifest |
| `refs-linkedin-carousel.md` | `linkedin-post-writer/references/compiled/` | **DELETE** (Phase C) | Drop from `compile-refs.ts` |
| `LinkedInGenerationService` | [backend/app/Services/LinkedInGenerationService.php](../../backend/app/Services/LinkedInGenerationService.php) | REFACTOR | Becomes router: text → `/linkedin-convert`, carousel → `/carousel-gen` |
| `CarouselGenOutputAdapter` | `backend/app/Services/CarouselGenOutputAdapter.php` | **NEW** | Map `/carousel-gen` JSON → `linkedin_posts.carousel_slides` shape |
| `CarouselSlideEnhancer` | [backend/app/Services/CarouselSlideEnhancer.php](../../backend/app/Services/CarouselSlideEnhancer.php) | KEEP | Publisher-side brand chrome injection — unchanged |
| `GenerateLinkedInCarouselImages` job | [backend/app/Jobs/GenerateLinkedInCarouselImages.php](../../backend/app/Jobs/GenerateLinkedInCarouselImages.php) | KEEP | Job receives slides from adapter — unchanged |
| `LinkedInCarouselImageWebhookController` | [backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php](../../backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php) | KEEP | Webhook unchanged |
| `linkedin_posts.carousel_slides` JSON | DB column | KEEP shape | Backward-compat via adapter |
| `LINKEDIN_GEN_REFS_CAROUSEL` env | [backend/config/linkedin.php](../../backend/config/linkedin.php) | **DELETE** (Phase C) | No longer compiled/needed |
| `CAROUSEL_GEN_REFS_PIPELINE` env | new env var | **NEW** | Path to compiled refs for `/carousel-gen` pipeline mode on VPS |
| `CAROUSEL_GEN_*` config section | new `backend/config/carousel-gen.php` | **NEW** | Mirror `LINKEDIN_GEN_*` SSH pattern |
| `LINKEDIN_USE_CAROUSEL_GEN_ENGINE` flag | new env (Phase A/B feature flag) | **NEW** | Default false in Phase A; true in Phase B; removed in Phase C |
| Plugin distribution: `linkedin-post-writer` | `linkedin-post-writer/plugin.json` | UPDATE (Phase C) | Bump v0.5.0, drop `/linkedin-carousel` |
| Plugin distribution: `ai-image-carousel-prompt-gen` | `ai-image-carousel-prompt-gen/plugin.json` | UPDATE | Bump v3.0.0 (major) — pipeline mode, bilingual flag, narrative slide types |

### Decision Log

**Considered but rejected:**

- **Opsi A (LinkedIn opt-in flag in `/carousel-gen`)** — rejected because LinkedIn-specific knowledge bleeds into engine plugin; distribution to third parties less clean; effort higher than Opsi D
- **Opsi B (drop all narrative features)** — rejected because loses SEO investment April 27 (`direct_answer_block` for Perplexity/ChatGPT crawlers, bilingual ID+EN reach); loses `human_fingerprint` credibility anchor; LinkedIn ROI hit
- **Opsi C (post-processor `/linkedin-carousel-enhance`)** — rejected because bilingual headline must be baked PRE-image-generation (NB2 renders text in-frame, not overlay), so post-processor would have to FULL REWRITE image_prompt → effectively duplicates carousel-gen authoring effort; two plugins to keep in sync = drift risk

**Selected:** Opsi D — Universal carousel engine + publisher-side platform concerns. Cleanest plugin separation, narrative features become cross-platform defaults, future platform addition trivial.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Decouple carousel image authoring from LinkedIn-specific publisher concerns by promoting `/carousel-gen` (in `ai-image-carousel-prompt-gen` plugin) to be the universal carousel engine, while reducing `linkedin-post-writer` to LinkedIn text-post + brief + validate. Preserve narrative quality (5-act spine, bilingual headline, `direct_answer_block`, `human_fingerprint`) as cross-platform defaults. Backward-compat via `CarouselGenOutputAdapter`. 3-phase migration (forward-compat → cutover → cleanup) gated by feature flag.

### Architecture Context (from CLAUDE.md)

- **LinkedIn admin pipeline**: [`LinkedInGenerationService`](../../backend/app/Services/LinkedInGenerationService.php) SSH → Claude CLI pattern (mirrors `ARTICLE_GEN_*`), [`parseOrchestratorOutput`](../../backend/app/Services/LinkedInGenerationService.php) balanced-brace scanner with 8 unit tests in [`LinkedInGenerationServiceParseTest`](../../backend/tests/Unit/LinkedInGenerationServiceParseTest.php), FSM via [`HasStatusTransitions`](../../backend/app/Traits/HasStatusTransitions.php) + [`LinkedInPostStatus`](../../backend/app/Enums/LinkedInPostStatus.php) enum (8-state).
- **Carousel rendering**: [`GenerateLinkedInCarouselImages`](../../backend/app/Jobs/GenerateLinkedInCarouselImages.php) queued job, [`CarouselSlideEnhancer`](../../backend/app/Services/CarouselSlideEnhancer.php) brand chrome injection with placeholder tokens `{{CREATOR_FACE}}`, `{{BRAND_LOGO}}`, `{{HANDLE}}`, `{{PORTFOLIO_URL}}`, `{{PAGE_INDICATOR}}`, `{{SWIPE_TEXT}}`. [`LinkedInCarouselImageWebhookController`](../../backend/app/Http/Controllers/Api/LinkedInCarouselImageWebhookController.php) callback handler at `POST /api/automation/linkedin/carousel-image-webhook`.
- **DB**: `linkedin_posts.carousel_slides` JSON column, `image_generation_jobs` with `type='carousel_slide'` + `linkedin_post_id` FK + `slide_index` + `slide_image_role`.
- **Plugin compile-refs pattern**: `scripts/compile-refs.ts` in each plugin bundles refs from `references/` into `references/compiled/refs-*.md`. Deployed to VPS at `/home/claudesn/refs-*.md`.
- **VPS deploy**: GitHub Actions auto-deploy on `git push origin main`. Never manual SSH deploy.
- **Existing test pattern**: Pest 3 framework, `php artisan test --filter=<name>` for isolation, factories at `database/factories/`.

### Tech Stack

- **Backend**: Laravel 12 + PHP 8.2 + MySQL 8 + Pest 3 + Sanctum 4
- **Plugins**: TypeScript + Zod 3 schemas + bun run compile-refs.ts
- **SSH bridge**: `claudesn@localhost`, `claude` CLI binary, `--append-system-prompt-file` system-prompt injection (300s timeout)
- **Image generation**: GeminiGen (Nano Banana Pro / `gemini-3-pro-image-preview`) multipart POST + webhook
- **Reusable patterns**: `LinkedInGenerationService::execClaudeCommand` SSH exec, balanced-brace JSON scanner (extend for carousel-gen output), `Cache::lock` for preflight locks, `DispatchTelegramNotification` queued job for operator alerts.

### Open Questions — Resolved

| # | Question | Resolution |
|---|---|---|
| 1 | Placeholder strategy in `/carousel-gen` output | **Keep placeholders in plugin** (`{{CREATOR_FACE}}`, `{{BRAND_LOGO}}`, `{{HANDLE}}` etc). Plugin emits placeholders as part of `image_prompt`; backend `CarouselSlideEnhancer` resolves them. Plugin stays platform-agnostic — placeholders are operator-defined contract documented in `references/global-config.md`. |
| 2 | Bilingual auto-detect from blog language | **Explicit flag only** (`--bilingual=id,en`). Predictability over magic. Backend's adapter decides based on user setting (future: per-blog config). |
| 3 | Slide count default | **Adapter passes `--target-slides=N`** based on `LinkedInGenerationService` blog shape detection (listicle → 9, framework → 6, case study → 8). `/carousel-gen` accepts `--target-slides` as soft hint with own range validation 5-15. |
| 4 | Parity test fixture | **Draft #28** (cleanest carousel JSON post-April-27). Snapshot input + expected adapter output as `tests/Unit/fixtures/draft28.json`. |
| 5 | Telegram error notification | **Reuse `DispatchTelegramNotification`** with new event `carousel_adapter_error`. Add to `telegram_notify_*` settings. |
| 6 | `/linkedin-gen` orchestrator behavior post-refactor | **Returns `{status: 'route_to_carousel_gen', brief: {...}}`** when `brief.format='carousel'`. Backend `LinkedInGenerationService::generateForDraft()` detects route status → dispatches `/carousel-gen` separately. Skips Step 2-4 of orchestrator. |

### Data Integration Map (Executor Contract)

| Feature | Data Source | Hook/API/Service | Exists? | Action |
|---|---|---|---|---|
| Blog input for `/carousel-gen` | `LinkedInPost->post->translations[primary]` | `LinkedInPost::with('post.translations')` | Yes | Use existing — pass to SSH command |
| Brief decision (text vs carousel) | `/linkedin-brief` skill output | SSH → Claude CLI | Yes | Use existing — extract brief from current orchestrator output |
| Carousel JSON output | `/carousel-gen` plugin (NEW pipeline mode) | SSH → Claude CLI | **No** | Build pipeline mode in plugin |
| Output schema validation | `CarouselGenOutputSchema` Zod | TypeScript Zod | **No** | Create new in plugin |
| JSON → DB shape adapter | `CarouselGenOutputAdapter` | `App\Services\CarouselGenOutputAdapter` | **No** | Create new PHP service |
| Brand chrome resolution | `CarouselSlideEnhancer` | Existing service | Yes | Unchanged — operates on adapter output |
| GeminiGen dispatch | `LinkedInCarouselImageService` | Existing service | Yes | Unchanged |
| Webhook callback | `LinkedInCarouselImageWebhookController` | Existing controller | Yes | Unchanged |
| Slide JSON storage | `linkedin_posts.carousel_slides` | DB column | Yes | Adapter normalizes to existing shape |
| Feature flag | `config('linkedin.use_carousel_gen_engine')` | New config key | **No** | Add to `config/linkedin.php` |
| `/carousel-gen` SSH config | `config('carousel_gen.*')` | New config file | **No** | Create `config/carousel-gen.php` mirroring `config/linkedin.php` |
| Compiled refs deployment | VPS `/home/claudesn/refs-carousel-gen-pipeline.md` | Plugin compile-refs.ts | **No** | Build new compile target in plugin |
| Telegram alert | `DispatchTelegramNotification` job | Existing job | Yes | Add new event `carousel_adapter_error` |
| Plugin v3.0.0 manifest | `ai-image-carousel-prompt-gen/plugin.json` | Plugin manifest | Yes | Bump major version |
| Plugin v0.5.0 manifest | `linkedin-post-writer/plugin.json` | Plugin manifest | Yes | Bump minor version |

---

### Phase A — Forward-compat (parallel build, feature-flagged OFF)

Goal: Build new engine + adapter behind feature flag without breaking existing carousel pipeline. All existing dispatches continue using `/linkedin-carousel`.

#### A1: Add Zod schema + bilingual flag to `/carousel-gen` plugin

**Estimated time:** 8 minutes

**Files:**
- Create: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/skills/carousel-gen/schema.ts`
- Test: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/skills/carousel-gen/schema.test.ts`

**Steps:**
1. Write failing test for `CarouselGenOutputSchema` validating draft #28-style bilingual carousel input. Expected error: `Cannot find module './schema'`.
2. Run test, confirm fail with module-not-found
3. Implement `schema.ts` with `CarouselSlideSchema` + `CarouselGenOutputSchema` (per Design § Schema Changes — copy/copy_id/copy_en branching, layout_hint enum, direct_answer_block optional, total_slides 5-15)
4. Run test, confirm pass
5. Add 6 more tests: rejects single+bilingual mix, rejects invalid layout_hint, enforces direct_answer_block min/max, validates total_slides matches slides.length, requires is_cover on slide 1, requires is_cta on last slide
6. Run all tests, confirm pass
7. Commit: `feat(carousel-gen): add Zod schema with bilingual + narrative slide types`

**Verification:**
- [ ] `bun test schema.test.ts` passes (7 tests)
- [ ] Schema rejects malformed inputs at parse time
- [ ] Bilingual + single-language modes mutually exclusive enforced
- [ ] No placeholder/TODO comments in new code

#### A2: Add pipeline-mode + non-interactive defaults to `/carousel-gen`

**Estimated time:** 12 minutes

**Files:**
- Modify: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/skills/carousel-gen/SKILL.md`
- Create: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/references/non-interactive-defaults.md`
- Update: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/references/carousel-best-practices.md` (add 5-act + human_fingerprint + direct_answer)
- Test: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/skills/carousel-gen/golden.test.ts`

**Steps:**
1. Write failing golden-file test for pipeline mode: input = sample blog JSON + flags `--pipeline --bilingual=id,en --narrative=5act --target-slides=9`, expected output matches `golden/draft28-equivalent.json`. Expected error: `Cannot find module './golden.test'`.
2. Run test, confirm fail
3. Update `SKILL.md`: add pipeline-mode section (auto-detect from `--blog-source` flag), document `--bilingual`, `--narrative=5act|free`, `--target-slides=N`, `--alt-aspect=9:16` (future-prep). Add `--non-interactive` keyword.
4. Create `references/non-interactive-defaults.md` documenting defaults for ambiguity (profession costume → creator-bible default wardrobe; setting → creator-bible default; log warning)
5. Update `references/carousel-best-practices.md`: add 5-act narrative spine HOOK→FORESHADOW→BODY→PEAK→CTA as cross-platform best practice, document `human_fingerprint` slide spec (war story / proprietary metric), document `direct_answer_block` spec (150-600 chars, EN-only, AI-search-optimized)
6. Run golden test, iterate prompt until output matches golden file (allow narrative variation but enforce structural compliance: total_slides=9, exactly 1 cover, exactly 1 cta, ≥1 human_fingerprint, ≥1 direct_answer with non-empty direct_answer_block)
7. Commit: `feat(carousel-gen): add pipeline mode + 5-act narrative + non-interactive defaults`

**Verification:**
- [ ] Golden test passes
- [ ] `references/carousel-best-practices.md` documents 5-act + human_fingerprint + direct_answer_block as defaults
- [ ] `references/non-interactive-defaults.md` exists with profession + setting fallback rules
- [ ] SKILL.md documents pipeline mode flags
- [ ] Schema validates output

#### A3: Build compile-refs target for `/carousel-gen` pipeline mode

**Estimated time:** 6 minutes

**Files:**
- Modify: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/scripts/compile-refs.ts`
- Output: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/references/compiled/refs-carousel-gen-pipeline.md`

**Steps:**
1. Write failing test verifying `refs-carousel-gen-pipeline.md` exists after compile and contains required sections (`Hard Rules`, `5-Act Narrative Spine`, `Non-Interactive Defaults`, `Bilingual Contract`, `Brand Chrome Placeholders`). Expected error: `File not found`.
2. Run test, confirm fail
3. Modify `compile-refs.ts` to add new compile target: bundle `global-config.md` + `creator-bible.md` + `hook-formula-bank.md` + `cinematography-LUT.md` + `prompt-formulas.md` + `carousel-best-practices.md` + `non-interactive-defaults.md` into `refs-carousel-gen-pipeline.md`
4. Run `bun run compile-refs`, verify output file generated
5. Run test, confirm pass
6. Commit: `build(carousel-gen): add pipeline-mode compiled refs target`

**Verification:**
- [ ] `references/compiled/refs-carousel-gen-pipeline.md` generated (~150-250 KB)
- [ ] Contains all 5 required sections
- [ ] Test passes

#### A4: Build `CarouselGenOutputAdapter` PHP service

**Estimated time:** 10 minutes

**Files:**
- Create: `D:/Projects/Portfolio_v2/backend/app/Services/CarouselGenOutputAdapter.php`
- Create: `D:/Projects/Portfolio_v2/backend/tests/Unit/CarouselGenOutputAdapterTest.php`
- Create: `D:/Projects/Portfolio_v2/backend/tests/Unit/fixtures/carousel-gen-output-bilingual.json` (from draft #28 input)
- Create: `D:/Projects/Portfolio_v2/backend/tests/Unit/fixtures/carousel-gen-output-single.json`

**Steps:**
1. Write failing test: `CarouselGenOutputAdapter::adapt($json)` returns array shape matching `linkedin_posts.carousel_slides` with `slide_number`, `layout_hint`, `copy_id`, `copy_en`, `image_prompt`, `is_cover`, `is_cta`, `direct_answer_block`, `image_status='pending'`, `image_url=null`, `image_job_uuid=null`, `image_error=null`. Expected error: `Class "App\Services\CarouselGenOutputAdapter" not found`.
2. Run test (`php artisan test --filter=CarouselGenOutputAdapterTest`), confirm fail
3. Implement `CarouselGenOutputAdapter::adapt($carouselGenJson): array` — handle bilingual + single-language branching, preserve placeholder tokens in `image_prompt`, initialize image_status fields
4. Run test, confirm pass
5. Add 5 more tests: handles single-language (sets copy_id only, copy_en=null), preserves direct_answer_block when present, throws on schema-invalid input, preserves slide_number gapless ordering, fails on missing required fields
6. Run all tests, confirm pass
7. Snapshot draft #28 input → save to `tests/Unit/fixtures/draft28-input.json`, expected adapter output → `tests/Unit/fixtures/draft28-expected.json`
8. Add parity test: adapter output for draft #28 input matches expected fixture exactly
9. Run all tests, confirm pass
10. Commit: `feat(backend): add CarouselGenOutputAdapter with parity test against draft #28`

**Verification:**
- [ ] `php artisan test --filter=CarouselGenOutputAdapterTest` passes (8 tests)
- [ ] Parity test against draft #28 passes byte-for-byte
- [ ] Adapter preserves all 6 placeholder tokens
- [ ] Single-language and bilingual modes both supported
- [ ] No PHP type errors (`./vendor/bin/phpstan analyse` if configured)

#### A5: Add `CAROUSEL_GEN_*` config + env vars

**Estimated time:** 5 minutes

**Files:**
- Create: `D:/Projects/Portfolio_v2/backend/config/carousel-gen.php`
- Modify: `D:/Projects/Portfolio_v2/backend/.env.example`
- Test: `D:/Projects/Portfolio_v2/backend/tests/Feature/CarouselGenConfigTest.php`

**Steps:**
1. Write failing test verifying `config('carousel-gen.driver')` returns `ssh`, `config('carousel-gen.timeout_seconds')` returns 300, `config('carousel-gen.refs_pipeline')` resolves to a path. Expected error: `Undefined index "carousel-gen"`.
2. Run test, confirm fail
3. Create `config/carousel-gen.php` mirroring `config/linkedin.php` generation section: `driver`, `ssh_host`, `ssh_user`, `ssh_key`, `claude_path`, `model` (sonnet), `refs_pipeline`, `timeout_seconds` (300), all driven by env vars `CAROUSEL_GEN_*`
4. Update `.env.example` with `CAROUSEL_GEN_*` block + comment block describing usage
5. Run test, confirm pass
6. Commit: `feat(backend): add carousel-gen SSH config mirroring linkedin-gen pattern`

**Verification:**
- [ ] `config('carousel-gen.*')` accessible via test
- [ ] `.env.example` documents all 8 env vars
- [ ] Default values match `LINKEDIN_GEN_*` defaults (driver=ssh, model=sonnet, timeout=300)

#### A6: Add feature flag + router branching to `LinkedInGenerationService`

**Estimated time:** 14 minutes

**Files:**
- Modify: `D:/Projects/Portfolio_v2/backend/app/Services/LinkedInGenerationService.php`
- Modify: `D:/Projects/Portfolio_v2/backend/config/linkedin.php` (add `use_carousel_gen_engine` key)
- Test: `D:/Projects/Portfolio_v2/backend/tests/Feature/LinkedInGenerationServiceCarouselGenRouterTest.php`

**Steps:**
1. Write failing test: when `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=true` AND brief.format=carousel, `LinkedInGenerationService::generateForDraft()` calls `/carousel-gen` SSH command with adapter output ending up in `linkedin_posts.carousel_slides`. Expected error: feature flag not implemented yet.
2. Run test, confirm fail
3. Add `use_carousel_gen_engine` key to `config/linkedin.php` with env binding `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false`
4. Add private method `dispatchCarouselGen(LinkedInPost $draft, array $brief): array` — invokes SSH with `claude -p "/carousel-gen --pipeline --blog-source=<url> --bilingual=id,en --narrative=5act --target-slides=N" --append-system-prompt-file refs-carousel-gen-pipeline.md`. Parse output via existing balanced-brace scanner. Validate against `CarouselGenOutputSchema` (call adapter's validate-or-fail).
5. Add private method `routeCarouselGeneration(LinkedInPost $draft, array $orchestratorOutput): void` — when `orchestratorOutput.status === 'route_to_carousel_gen'`, calls `dispatchCarouselGen` + `CarouselGenOutputAdapter::adapt` + persist to `linkedin_posts.carousel_slides`.
6. Modify `parseOrchestratorOutput` to recognize `status: 'route_to_carousel_gen'` as valid (not failure) when feature flag enabled.
7. Modify `generateForDraft` to call `routeCarouselGeneration` when status matches.
8. Add fallback: when feature flag OFF, behave exactly as today (legacy `/linkedin-carousel` path).
9. Run test, confirm pass
10. Add 4 more tests: flag OFF preserves legacy behavior, adapter error triggers Telegram alert, SSH timeout triggers FSM failure transition, schema validation failure logs error + retains FSM state
11. Run all tests, confirm pass
12. Commit: `feat(backend): add carousel-gen router with feature flag (default OFF)`

**Verification:**
- [ ] All 5 tests pass
- [ ] Feature flag default `false` — existing dispatches unchanged
- [ ] When flag `true` + carousel format → `/carousel-gen` invoked + adapter mirrors to `carousel_slides`
- [ ] When flag `true` + text format → unchanged (`/linkedin-convert` path)
- [ ] Adapter errors trigger Telegram alert via existing `DispatchTelegramNotification`
- [ ] FSM transitions still work end-to-end

#### A7: Deploy compiled refs + plugin to VPS (manual setup, not auto-deploy)

**Estimated time:** 4 minutes

**Files (VPS, via SSH):**
- Sync: `~/refs-carousel-gen-pipeline.md` (from `ai-image-carousel-prompt-gen/references/compiled/`)
- Sync: `~/.claude/plugins/.../ai-image-carousel-prompt-gen/` (latest plugin version)
- Verify: `claude --version` works on VPS as `claudesn` user

**Steps:**
1. Build compiled refs locally: `cd ai-image-carousel-prompt-gen && bun run compile-refs`
2. Copy `refs-carousel-gen-pipeline.md` to VPS `~/refs-carousel-gen-pipeline.md` (operator manual `scp` or build CI job)
3. Update plugin marketplace cache on VPS so `/carousel-gen` resolves to new pipeline-mode SKILL
4. SSH test as `claudesn`: `claude -p "/carousel-gen --help"` — verify pipeline-mode flags appear
5. Set VPS env vars in Laravel `.env`: `CAROUSEL_GEN_REFS_PIPELINE=/home/claudesn/refs-carousel-gen-pipeline.md`, etc.
6. Restart queue worker: `php artisan queue:restart`
7. **Do NOT** flip feature flag yet — Phase B handles that.

**Verification:**
- [ ] `~/refs-carousel-gen-pipeline.md` exists on VPS, ~150-250 KB
- [ ] `claude -p "/carousel-gen --help"` succeeds as `claudesn`
- [ ] Backend `php artisan tinker` → `config('carousel-gen.refs_pipeline')` resolves to existing file
- [ ] Queue worker running with new env loaded
- [ ] Feature flag `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false` (still off)

---

### Phase B — Cutover (flip flag, monitor 7 days)

Goal: Activate new engine for ALL new dispatches. Existing draft #28+ unaffected (already rendered). Monitor for adapter errors, SSH timeouts, schema mismatches.

#### B1: Smoke test with one manual draft

**Estimated time:** 10 minutes

**Steps:**
1. Pick a published blog post from `posts` table that has NO live `linkedin_posts` row (use `php artisan tinker` to find one)
2. Manually create a `linkedin_posts` row with `status=PendingGeneration`, `format=carousel` (force carousel via brief override env)
3. Set `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=true` ONLY for one Laravel queue worker (e.g., `php artisan queue:work --queue=linkedin_test` with custom `.env.local`)
4. Dispatch `GenerateLinkedInPost::dispatchSync($draft->id)`
5. Wait for synchronous return (~60-120s)
6. Verify: `linkedin_posts.carousel_slides` JSON populated with bilingual slides + structured layout types + brand chrome placeholders intact
7. Verify: `GenerateLinkedInCarouselImages` job dispatched, slide_index/uuid populated in `image_generation_jobs`
8. Wait for GeminiGen webhook callbacks (~3-5 min for 9 slides)
9. Verify: each slide has `image_status='done'` + `image_url` resolves on R2
10. Open `LinkedInDraftDetail.vue` in browser, confirm slides render visually + brand chrome correct

**Verification:**
- [ ] Draft completes full FSM transition: PendingGeneration → Generating → Validating → AwaitingPublish (or ManualReview)
- [ ] All 9 slides render with rendered images + brand chrome
- [ ] No errors in `storage/logs/laravel.log`
- [ ] No Telegram alerts fired
- [ ] Operator visual review: slides match draft #28 quality bar

#### B2: Flip feature flag globally

**Estimated time:** 3 minutes

**Steps:**
1. Update VPS `.env`: `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=true`
2. `php artisan config:cache && php artisan queue:restart`
3. Verify `config('linkedin.use_carousel_gen_engine')` returns `true` in tinker
4. Commit env change to deploy script if applicable (note: `.env` is NOT committed; manual VPS update or set via GitHub Actions secret + `.env.production` if exists)

**Verification:**
- [ ] Flag = true on VPS
- [ ] Queue worker restarted with new config
- [ ] Next scheduled `linkedin:scan-blog` cron picks up flag
- [ ] No deploy errors in `Actions` log

#### B3: 7-day monitoring window

**Estimated time:** 7 days passive monitoring

**Monitoring touchpoints:**
- Telegram alerts: any `carousel_adapter_error`, `linkedin_generation_failed`, or `image_generation_failed` event
- Daily check: `php artisan tinker` → `LinkedInPost::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count()` should be ≤ historical avg
- Slack/operator UI: visual inspection of new drafts at `/admin/linkedin-queue`
- Quality benchmark: compare slide narrative depth vs draft #28 (should be on par or better)

**Steps:**
1. Day 1: heightened monitoring, immediate triage of any error
2. Day 2-3: daily morning check
3. Day 4-7: weekly summary review
4. Decision point at end of week 1: proceed to Phase C OR rollback (B5)

**Verification:**
- [ ] Adapter error rate < 5% of dispatches
- [ ] No FSM corruption (state log audit clean)
- [ ] Visual quality maintained or improved
- [ ] No P0/P1 production incidents

#### B4: Document operator-visible changes

**Estimated time:** 6 minutes

**Files:**
- Update: `D:/Projects/Portfolio_v2/CLAUDE.md` (LinkedIn Carousel section)
- Update: `D:/Projects/Portfolio_v2/PROJECT_STATUS.md` (current state)

**Steps:**
1. Update CLAUDE.md "LinkedIn Carousel Image Generation" section with new architecture diagram + reference to this plan
2. Update PROJECT_STATUS.md with current cutover status
3. Commit: `docs: document carousel-gen engine cutover (Phase B)`

**Verification:**
- [ ] CLAUDE.md reflects new flow (router + adapter + carousel-gen)
- [ ] PROJECT_STATUS.md shows Phase B complete

#### B5: Rollback procedure (if needed during 7-day window)

**Estimated time:** 5 minutes (emergency)

**Steps:**
1. Set VPS `.env`: `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false`
2. `php artisan config:cache && php artisan queue:restart`
3. New dispatches revert to legacy `/linkedin-carousel` path
4. Existing in-flight drafts that used new engine: leave as-is (already persisted to DB)
5. Diagnose issue, fix, re-test in B1 mode
6. Re-attempt B2 cutover

**Verification:**
- [ ] Rollback completed within 5 min of detection
- [ ] Next dispatch uses legacy path
- [ ] No data loss in existing draft rows

---

### Phase C — Cleanup (remove deprecated code)

Goal: Remove `/linkedin-carousel` skill from `linkedin-post-writer`, drop `refs-linkedin-carousel.md`, bump plugin versions, finalize separation.

**Pre-condition:** Phase B success monitoring passed (zero P0 incidents, error rate within tolerance).

#### C1: Move `/linkedin-carousel` skill to deprecated folder

**Estimated time:** 4 minutes

**Files:**
- Move: `D:/Projects/claude-plugin/linkedin-post-writer/skills/linkedin-carousel/` → `D:/Projects/claude-plugin/linkedin-post-writer/deprecated/skills/linkedin-carousel/`
- Update: `D:/Projects/claude-plugin/linkedin-post-writer/plugin.json` (drop from skills list)

**Steps:**
1. Write failing test: plugin manifest does NOT list `/linkedin-carousel` in skills array. Expected error: skill still listed.
2. Run test, confirm fail
3. `git mv skills/linkedin-carousel deprecated/skills/linkedin-carousel`
4. Update `plugin.json` skills list, remove `linkedin-carousel` entry
5. Run test, confirm pass
6. Commit: `chore(linkedin-post-writer): deprecate /linkedin-carousel skill (graduated to /carousel-gen)`

**Verification:**
- [ ] Test passes
- [ ] `claude -p "/linkedin-carousel --help"` returns "skill not found"
- [ ] Git history preserved via `git mv`

#### C2: Drop `refs-linkedin-carousel.md` from compile-refs

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:/Projects/claude-plugin/linkedin-post-writer/scripts/compile-refs.ts` (remove carousel target)
- Delete: `D:/Projects/claude-plugin/linkedin-post-writer/references/compiled/refs-linkedin-carousel.md`

**Steps:**
1. Write failing test: `compile-refs` build does NOT produce `refs-linkedin-carousel.md`. Expected error: file still produced.
2. Run test, confirm fail
3. Modify `compile-refs.ts` — remove `linkedin-carousel` build target
4. Delete existing `references/compiled/refs-linkedin-carousel.md`
5. Run `bun run compile-refs`, verify only 3 files generated (playbook, templates, formats)
6. Run test, confirm pass
7. Commit: `chore(linkedin-post-writer): drop refs-linkedin-carousel.md compile target`

**Verification:**
- [ ] `references/compiled/` contains only 3 files (no carousel)
- [ ] Compile build succeeds
- [ ] Test passes

#### C3: Refactor `/linkedin-gen` orchestrator to text-only path

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:/Projects/claude-plugin/linkedin-post-writer/skills/linkedin-gen/SKILL.md`
- Modify: `D:/Projects/claude-plugin/linkedin-post-writer/skills/linkedin-gen/schema.ts` (add route status variant)
- Test: `D:/Projects/claude-plugin/linkedin-post-writer/skills/linkedin-gen/route.test.ts`

**Steps:**
1. Write failing test: when input blog implies carousel format, orchestrator returns `{status: 'route_to_carousel_gen', brief: {...}}` (no inline carousel logic). Expected error: still returns `format='carousel'` envelope.
2. Run test, confirm fail
3. Update `schema.ts`: add `'route_to_carousel_gen'` to status enum, allow null `post`/`carousel`/`validation` when route status active.
4. Update `SKILL.md`: rewrite Step 2b to instead emit `route_to_carousel_gen` envelope when `format='carousel'`. Skip Step 3-4 in carousel branch.
5. Run test, confirm pass
6. Add 2 more tests: text format still produces full envelope, validation still scored on text format
7. Run all tests, confirm pass
8. Commit: `refactor(linkedin-gen): route carousel format to /carousel-gen (text-only orchestrator)`

**Verification:**
- [ ] All 3 tests pass
- [ ] Text format unchanged behavior
- [ ] Carousel format returns route status without authoring slides

#### C4: Remove `LINKEDIN_GEN_REFS_CAROUSEL` env + config

**Estimated time:** 4 minutes

**Files:**
- Modify: `D:/Projects/Portfolio_v2/backend/config/linkedin.php` (drop `refs_carousel` from generation section)
- Modify: `D:/Projects/Portfolio_v2/backend/.env.example`
- Modify: `D:/Projects/Portfolio_v2/backend/app/Services/LinkedInGenerationService.php` (drop unused config read)

**Steps:**
1. Write failing test: `config('linkedin.generation.refs_carousel')` returns null (was a path). Expected error: still returns path.
2. Run test, confirm fail
3. Remove `refs_carousel` key from `config/linkedin.php`
4. Drop `LINKEDIN_GEN_REFS_CAROUSEL` from `.env.example`
5. Search `LinkedInGenerationService.php` for `refs_carousel` usage; remove all references (legacy carousel branch is dead code)
6. Run test, confirm pass
7. Manual VPS step: remove `LINKEDIN_GEN_REFS_CAROUSEL` from VPS `.env`
8. Commit: `chore(backend): drop LINKEDIN_GEN_REFS_CAROUSEL (legacy /linkedin-carousel removed)`

**Verification:**
- [ ] Test passes
- [ ] No remaining references to `refs_carousel` in backend code (`grep -r refs_carousel backend/`)
- [ ] VPS env updated

#### C5: Bump `linkedin-post-writer` to v0.5.0

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:/Projects/claude-plugin/linkedin-post-writer/plugin.json` (version → 0.5.0)
- Update: `D:/Projects/claude-plugin/linkedin-post-writer/CHANGELOG.md`

**Steps:**
1. Update `plugin.json` version to `0.5.0`
2. Update CHANGELOG.md with v0.5.0 entry: `BREAKING: removed /linkedin-carousel skill — use /carousel-gen from ai-image-carousel-prompt-gen plugin instead`. Migration guide pointing to backend `CarouselGenOutputAdapter` reference impl.
3. Commit: `chore(linkedin-post-writer): release v0.5.0 — drop /linkedin-carousel`

**Verification:**
- [ ] `plugin.json` version = 0.5.0
- [ ] CHANGELOG.md documents breaking change + migration

#### C6: Bump `ai-image-carousel-prompt-gen` to v3.0.0

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/plugin.json` (version → 3.0.0)
- Update: `D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/CHANGELOG.md`

**Steps:**
1. Update `plugin.json` version to `3.0.0`
2. Update CHANGELOG.md with v3.0.0 entry: `BREAKING: added pipeline mode (--pipeline flag), Zod schema (CarouselGenOutputSchema), --bilingual=id,en flag, --narrative=5act|free flag, --target-slides=N flag. Existing interactive mode preserved as default. New cross-platform best-practice defaults: 5-act narrative spine, human_fingerprint slide, direct_answer_block.`
3. Commit: `chore(carousel-gen): release v3.0.0 — pipeline mode + bilingual + narrative slide types`

**Verification:**
- [ ] `plugin.json` version = 3.0.0
- [ ] CHANGELOG.md documents breaking changes + new features

#### C7: Update CLAUDE.md to reflect new architecture

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:/Projects/Portfolio_v2/CLAUDE.md`

**Steps:**
1. Update "LinkedIn Carousel Image Generation" section: replace plugin layer subsection with new architecture (carousel-gen as engine, linkedin-post-writer as text-only)
2. Add reference to `CarouselGenOutputAdapter` service in service list
3. Update env vars list: add `CAROUSEL_GEN_*`, drop `LINKEDIN_GEN_REFS_CAROUSEL`
4. Update "Last Updated" line at bottom of CLAUDE.md
5. Commit: `docs(claude): document carousel-gen engine decoupling (Phase C complete)`

**Verification:**
- [ ] CLAUDE.md reflects current code state
- [ ] Last Updated line current
- [ ] No stale references to `/linkedin-carousel` skill

---

### Test Strategy

#### Unit tests (~15 new tests)

| Layer | Test File | Coverage |
|---|---|---|
| Plugin schema | `carousel-gen/schema.test.ts` | 7 tests — Zod validation, bilingual mode, layout types, slide invariants |
| Plugin pipeline | `carousel-gen/golden.test.ts` | 1 golden-file test — blog input → JSON output structural compliance |
| Backend adapter | `tests/Unit/CarouselGenOutputAdapterTest.php` | 8 tests — bilingual + single-language modes, placeholder preservation, parity vs draft #28, error handling |
| Backend config | `tests/Feature/CarouselGenConfigTest.php` | 3 tests — config keys resolve, env vars bind, defaults match `LINKEDIN_GEN_*` pattern |

#### Integration tests (~5 new tests)

| Test | Coverage |
|---|---|
| `LinkedInGenerationServiceCarouselGenRouterTest` | Feature flag branching, full route flow text + carousel, adapter error → Telegram alert, SSH timeout → FSM failure |
| `tests/Feature/LinkedInCarouselEndToEndTest` (extended) | Full pipeline: brief → carousel-gen → adapter → CarouselSlideEnhancer → GeminiGen dispatch → webhook → slide done |

#### Parity tests (1 critical test)

| Test | Coverage |
|---|---|
| `tests/Unit/CarouselGenOutputAdapterTest::test_draft_28_parity` | Snapshot input from draft #28 (currently in DB) → adapter output → expected fixture. Byte-for-byte equality on essential fields (slide_number, layout_hint, copy_id, copy_en, is_cover, is_cta). Allow `image_prompt` text variation but enforce structural compliance (placeholder tokens preserved, length bounds respected). |

#### Manual verification (Phase B)

| Check | Frequency | Action |
|---|---|---|
| Visual quality benchmark | Every new draft Day 1 | Compare 5 random new drafts vs draft #28 visually |
| Brand chrome integrity | Every new draft Day 1-3 | Verify page indicator, @handle, brand icon, SWIPE indicator render correctly |
| FSM state log audit | Daily Week 1 | `LinkedInPost::where('created_at', '>=', now()->subDay())->pluck('state_log')` — no illegal transitions |
| Adapter error count | Daily Week 1 | Telegram alert count + `storage/logs/laravel.log` grep for `CarouselGenAdapter` errors |

---

### Rollback Plan

**Trigger conditions** (any of):
- Adapter error rate > 5% of dispatches
- 3+ consecutive failed drafts during 7-day monitoring
- Visual quality regression flagged by operator
- Brand chrome consistently broken (placeholder tokens not resolving)
- P0/P1 production incident

**Rollback steps** (5 min):
1. SSH to VPS, set `.env`: `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false`
2. `php artisan config:cache && php artisan queue:restart`
3. Verify next dispatch uses legacy `/linkedin-carousel` path
4. Telegram notification: "Carousel engine rollback complete"
5. Diagnose root cause, fix, re-attempt cutover after fix verified

**Post-rollback recovery:**
- Existing drafts that completed via new engine: leave as-is (already in DB, slides rendered)
- In-flight drafts at rollback time: let them complete on whatever engine they started on (FSM tolerates)
- Phase C cleanup: DEFER until next successful cutover. `/linkedin-carousel` skill stays available as fallback.

---

### Phase Independence (for `gaspol-parallel`)

| Phase | Dependencies | Parallelizable? |
|---|---|---|
| A1 (schema) | None | Yes — independent of A2-A7 |
| A2 (pipeline mode) | A1 (schema reference) | Sequential after A1 |
| A3 (compile refs) | A2 (references updated) | Sequential after A2 |
| A4 (adapter) | A1 (schema for input shape) | Yes — parallel with A2-A3 (only needs schema TypeScript types) |
| A5 (config) | None | Yes — independent |
| A6 (router) | A4 (adapter) + A5 (config) | Sequential after A4+A5 |
| A7 (deploy) | A3 (compiled refs) + A6 (backend code) | Sequential after A3+A6 |
| B1-B5 | Phase A complete | Sequential within Phase B |
| C1-C7 | Phase B success | Sequential, but C1+C2 parallelizable, C5+C6 parallelizable |

**Recommended parallel groups:**
- Group 1 (parallel): A1, A5
- Group 2 (parallel after Group 1): A2, A4
- Group 3 (sequential): A3, A6, A7
- Phase B: sequential (production cutover)
- Group 4 (parallel after Phase B): C1, C2
- Group 5 (parallel): C5, C6
- Group 6 (sequential): C3, C4, C7

---

### Verification Checklist (End-of-Plan)

Before marking implementation complete, all must be ✓:

- [ ] All Phase A tests pass (15 unit + 5 integration + 1 parity = 21 tests)
- [ ] Feature flag mechanism verified (off = legacy, on = new engine)
- [ ] Phase B 7-day monitoring window passed without rollback
- [ ] Phase C cleanup complete (no deprecated code paths)
- [ ] CLAUDE.md updated reflecting new architecture
- [ ] Plugin v0.5.0 + v3.0.0 published
- [ ] No P0/P1 incidents during cutover
- [ ] Operator visual approval on 5+ drafts post-cutover
- [ ] ADR `adr-2026-04-28-carousel-engine-publisher-separation` status remains `accepted` (no supersession needed)

---

### Execution Handoff

Three options, pick what fits:

**Option 1: Execute in this session**
Ready to start Phase A1? I'll use `gaspol-execute` to implement with per-phase checkpoints and TDD hard gate enforcement. ~10-15 phases, est. 80-100 min for Phase A only.

**Option 2: Parallel execution**
Want to use `gaspol-parallel` for independent phases? Group 1 (A1, A5) and Group 2 (A2, A4) can run in parallel — saves ~30 min on Phase A.

**Option 3: Separate session**
Save plan for a new session. The plan file at [docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md](docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md) has everything needed (design + plan + open questions resolved + Data Integration Map). Future session starts fresh: `gaspol-execute docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md`.
