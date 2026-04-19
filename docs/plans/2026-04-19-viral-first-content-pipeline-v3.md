# Viral-First Content Pipeline v3.0

**Brainstormed:** 2026-04-19
**Status:** Design approved, ready for `gaspol-plan`
**Approach:** Phased rollout A → C → B

---

## Design

### Problem Statement

Current pipeline scores articles **post-write**, which confirms quality but doesn't prevent bad topics from consuming 8-11 min pipeline cost. Three compounding weaknesses:

1. **Topic discovery is un-scored** — [TrendingTopicService::getBestTopic()](backend/app/Services/TrendingTopicService.php#L67) returns AI-relevant topics filtered by keyword match, no virality ranking. User picks from unranked pool → some topics fundamentally cannot go viral regardless of writing quality.
2. **Research phase is shallow** — [/article-prep](D:/Projects/claude-plugin/article-content-writer/skills/article-prep/SKILL.md#L64) fires only 2-3 WebSearch queries, reads snippets only, collects 5-8 data points. For 2000w articles this caps the ceiling at "content farm grade" rather than "journalistic grade".
3. **Scoring duplicates mechanical work** — backend already computes SEO (6 metrics), Tier 1/2/3 violations, FAQ count, freshness signals via [`/mechanical-scores` endpoint](backend/app/Http/Controllers/Api/Automation). AI-subjective scoring (Virality 5 + Quality 10 + GEO 3) adds ~1 min per article and rarely triggers fix-loop when prep+write are solid.

### Goals

1. **Prevent failure at topic selection** (root cause fix) — rank topics by virality potential before user picks.
2. **Lift quality ceiling for high-value topics** — deep research framework when topic is worth the investment.
3. **Remove redundant AI work** — default to instant mechanical scoring; gate deep AI scoring behind opt-in button.

### Non-Goals

- Full UI redesign (augment existing modals, don't rebuild)
- Changing `/article-write` phase (unchanged)
- Changing `/article-images` or `/article-translate` phases (unchanged)
- Removing fix-and-re-score loop entirely (kept, but fires only for opt-in deep score)

### Core Concept

```
BEFORE: Random topic → thin research → write → AI score → maybe revise → Gate 1
AFTER:  Pre-scored viral topics → tiered deep research → write → mechanical score → Gate 1
                                                                    ↓
                                                        (opt-in) deep AI score button
```

### Decision Summary (from brainstorm)

| Decision | Chosen option | Rationale |
|----------|---------------|-----------|
| Overall scope | Unified A+B+C redesign | Three concerns interlock; compound effect > isolated fixes |
| Rollout order | Phased A → C → B | Per-phase validation, easy rollback, low risk escalation |
| Auto-tier threshold | Aggressive: virality_score ≥ 70 → deep research | Prioritizes quality (Q3 intent); ~60-70% topics hit deep tier |
| Model allocation | **Opus for deep research only**, Sonnet for everything else | Research is most context-demanding step; strategy/outline/write/score are template-based and Sonnet-capable |
| Skill architecture | **Split `/article-prep` into `/article-research` + `/article-strategy-outline`** | Allows Opus only for research step, saves ~50% Opus budget vs whole-prep upgrade |
| Visual assets (screenshots) | **Cancelled** — use written guides + GeminiGen-generated UI mockups | Screenshot scraping = copyright risk; written step-by-step + AI mockups cover the need safely |
| Brand-aware images | **Absorbed into Phase B research** — entity extraction includes visual_style descriptions, feeds `/article-images` prompts | No external brand library needed; prompt engineering sufficient with rich research data |

---

## Phase A — Topic Virality Pre-Scoring

### What Changes

**Service layer (backend):**
- Refactor [TrendingTopicService](backend/app/Services/TrendingTopicService.php): add `getScoredTopics()` alongside existing `getBestTopic()` (keep for backward compat)
- NEW `app/Services/TopicScoringService.php` — batch virality scorer
  - Input: array of 20 trending topics (title + source + snippet)
  - Output: per-topic score object `{momentum, virality_triggers, composite_score}`
  - Momentum: mechanical — source weight (Trends > News), recency (hours since pub), keyword heat
  - Virality triggers: 1 Sonnet batch call evaluating all 20 topics against 5 triggers (social currency, high-arousal emotion, practical utility, identity signaling, cognitive gap)
  - Batch call dispatched via [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) SSH pattern (reuse)

**Database:**
- Migration: `content_ideas` → add `virality_score` (unsigned tinyint 0-100, nullable) + `virality_breakdown` (json, nullable)

**API:**
- NEW `POST /api/admin/content-engine/trending/score-batch` — dispatches batch scoring, returns ranked topics
- Modify `GET /api/admin/content-engine/trending` — return scores inline if available from cache

**UI:**
- [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) Trending Preview modal:
  - Add score badge per card (composite 0-100 + color: green ≥80, amber 50-79, red <50)
  - Add sort dropdown: `Virality (default)`, `Momentum`, `Recency`
  - "Pull Trending" button shows progress for batch scoring (~30s)

**Caching:**
- Cache scored topics 1 hour (matches existing trending cache). Rescore on cache miss or manual refresh.

### Time/Cost

- 1 Sonnet batch call per 20 topics = ~$0.05-0.10 amortized to <$0.01 per topic
- Latency: ~30s for batch (parallelizable, runs during modal load animation)

---

## Phase C — Trim Scoring to Mechanical-Only

### What Changes

**Pipeline:**
- [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) — make `/article-score` invocation conditional on `ARTICLE_GEN_USE_SCORE_PHASE=true` (default false)
- When disabled: `/article-write` completion triggers idea status `article_ready` directly, skipping score phase
- When enabled: current behavior preserved (backward compat)

**Backend:**
- `content_ideas` → add `mechanical_scores_snapshot` (json, nullable) — store backend-computed scores at article_ready time
- Populate snapshot from existing `/automation/content-ideas/{id}/mechanical-scores` logic inline when article completes

**UI:**
- [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) Article Preview modal:
  - NEW scorecard section (above article content):
    - SEO: 6 metric cards (title_length, keyword_in_title, title_word_count, body_keyword_density, keyword_in_first_100, keyword_in_headings) with green/amber/red chips
    - GEO (mechanical portion): FAQ pair count + freshness signals count
    - AI Humanization (mechanical portion): Tier 1/2/3 violation counts
  - NEW "Run Deep Quality Analysis" button → dispatches on-demand AI scoring via existing `/article-score` skill (async with progress modal)

**API:**
- NEW `POST /api/admin/content-engine/ideas/{id}/run-deep-score` — on-demand AI score trigger (wraps `/article-score` skill)
- Reuse existing `/progress` polling for deep score progress

**Env flag:**
- `ARTICLE_GEN_USE_SCORE_PHASE=false` (default) — skip AI scoring in default pipeline

### Time/Cost

- Default pipeline: -1 min per article (-10% total time)
- Cost: 0 additional per article (mechanical is free, already computed)
- Opt-in deep score: ~1 min + 1 Sonnet call when user clicks button

---

## Phase B — Tiered Deep Research + Skill Split

### Architectural Change: Split `/article-prep` into 2 Skills

Current `/article-prep` bundles Research (Step 1, context-heavy) + Strategy (Step 2, template-based) + Outline (Step 3, mapping logic) into one CLI call with one model. For deep research tier we want **Opus** quality on research only — not Strategy/Outline, which Sonnet handles well. Splitting enables per-step model allocation.

**New skill architecture:**

```
/article-research (NEW)               model: Opus (deep) / Sonnet (quick)
  ├── Step 1: Research + entity extraction + visual_style extraction + written-guide extraction
  └── Output → PUT /save-research → content_ideas.research_data

/article-strategy-outline (NEW)       model: Sonnet (always)
  ├── Reads: research_data from previous step
  ├── Step 2: Framework + arc + hooks + template selection
  ├── Step 3: Outline + retention + image concepts + written-guide sections for how-to topics
  └── Output → PUT /save-prep (existing endpoint)

/article-prep (DEPRECATED but kept for backward compat)
  ├── Still callable; internally delegates to new pair for fallback path
```

### What Changes

**Plugin ([article-content-writer](D:/Projects/claude-plugin/article-content-writer)):**

1. NEW skill [/article-research](D:/Projects/claude-plugin/article-content-writer/skills/article-research/SKILL.md) (to be created)
   - Flags: `--idea-id`, `--api-url`, `--api-token`, `--topic`, `--keyword`, `--research-tier {quick|deep}`
   - Quick tier (1-2 min, Sonnet): current Step 1 behavior — 2-3 queries, 5-8 data points
   - Deep tier (5-8 min, Opus): 4-layer framework (below)
   - Saves research data via NEW `PUT /automation/content-ideas/{id}/save-research`
   - Triggers continue-pipeline with `completed_step: research`

2. NEW skill [/article-strategy-outline](D:/Projects/claude-plugin/article-content-writer/skills/article-strategy-outline/SKILL.md) (to be created)
   - Flags: `--idea-id`, `--api-url`, `--api-token`
   - Reads research_data from `/automation/content-ideas/{id}` endpoint
   - Runs Steps 2-3 (Strategy + Outline) on Sonnet
   - Saves prep data via existing `PUT /save-prep`
   - Triggers continue-pipeline with `completed_step: prep`

3. Compiled references:
   - NEW `refs-research.md` (~30 KB) — global-config + deep research framework + entity extraction rules + written guide extraction patterns
   - `refs-prep.md` slimmed → rename to `refs-strategy-outline.md` — frameworks-library, hook-repository, emotional-arcs, content-templates, retention-engine

**Deep research 4-layer framework (Opus only):**

**Layer 1 — Broader Discovery (parallel WebSearch, 6-8 queries):**
- `"[topic]" statistics benchmarks 2025 2026` (hard data)
- `"[topic]" case study success story` (real examples)
- `"[topic]" expert opinion interview` (authoritative quotes)
- `"[topic]" reddit OR quora OR hackernews` (community pain)
- `"[topic]" counter-argument problem issue` (balance)
- `"[topic]" trend adoption growth market` (momentum)
- `"[topic]" official documentation getting started` (step-by-step instructions for how-to topics)
- `site:github.com "[topic]"` (technical depth, conditional)

**Layer 2 — Deep Reading + Entity Extraction (WebFetch top 5-8 sources):**
- Full article reads (not snippets)
- Extract direct quotes with attribution
- Extract specific numbers (e.g. "47% of 2,400 surveyed")
- Extract **named entities** (companies, tools, people, places) with URLs
- Extract **visual_style description** per entity (brand color, UI pattern, layout aesthetic — informs `/article-images` prompts later)
- Flag data >24 months stale

**Layer 3 — Synthesis + Gap Analysis:**
- Cluster findings by theme
- Identify competitor coverage vs blind spots
- Generate contrarian angles (3 counter-narratives)
- Map data → intended outline sections

**Layer 4 — Reader Psychology + Written Guides:**
- Extract real questions from Reddit/Quora/forums
- Identify emotional triggers in user comments
- Map pain points to 3-5 specific personas
- **NEW for how-to topics:** extract step-by-step written instructions from official docs → feeds `/article-strategy-outline` to allocate dedicated "How To" sections with numbered steps + prose (replaces screenshot need)

**Research output schema v2 (lean — 5 top-level fields):**

```json
{
  "data_points": [
    { "stat": "47% of 2,400 devs use AI coding assistants daily", "source": "Stack Overflow Survey 2025", "url": "https://...", "year": 2025 }
  ],
  "quotes": [
    { "text": "Claude Design removes the designer-developer handoff entirely", "attribution": "Dario Amodei, CEO Anthropic", "url": "https://..." }
  ],
  "entities": [
    {
      "name": "ChatGPT",
      "url": "https://chat.openai.com",
      "visual_style": "Green-teal + dark grey palette. Centered chat with speech bubbles and sidebar history. Söhne sans-serif. Prominent input field with Send icon. Clean, clinical mood."
    }
  ],
  "personas": [
    {
      "name": "Overwhelmed Solo Founder",
      "pain": "No designer on team; UI looks amateur despite good code",
      "emotion": "Anxious — watching competitors with polished UIs",
      "voice": "I can ship features but my landing page looks like 2015"
    }
  ],
  "written_guides": [
    {
      "task": "Setting up Claude Design",
      "steps": [
        "Navigate to claude.ai/design (requires Pro subscription)",
        "Click 'New Prototype' top-right (lightning bolt icon)"
      ],
      "source_url": "https://docs.anthropic.com/..."
    }
  ]
}
```

**Target counts per tier:**

| Field | Quick (Sonnet) | Deep (Opus) |
|-------|----------------|-------------|
| `data_points` | 5-8 | 20-30 |
| `quotes` | 0-1 | 5+ |
| `entities` (with visual_style) | 0-2 | 10+ |
| `personas` | 1 | 3-5 |
| `written_guides` (how-to only) | 0 | 1-3 tasks |
| Time budget | 1-2 min | 5-8 min |

**Design decisions (simplifications over v1 draft):**
- `visual_style` flattened from 7-field object → 1 prose paragraph (Opus writes descriptive text better than nested JSON)
- `personas.voice` is single string (1 verbatim quote), not `language_samples[]` array
- `written_guides.steps[]` is flat string array, not `{number, action, detail}` nested object
- Dropped from v1 draft: metadata (`tier`, `model`, `researched_at` — derive from `content_ideas` columns), `counter_arguments` (inferred during write phase), `content_gaps` (implicit in outline), `trending_momentum` (already in `source_data`), `fresh_tier` auto-flag (inline stale note in stat text if needed), `source_type` taxonomy (enforced at prompt level, not output schema), `confidence` (always high in practice)

**Opus prompt strategy — 5 core principles:**

1. **Hard count targets per field** — "Collect 20-30 data_points, 10+ entities, 3-5 personas, 5+ quotes. If below target after Layer 2, run additional queries until hit."
2. **Source diversity matrix** — "≥4 primary_research, ≥3 forum, ≥3 expert, ≥2 counter, ≥2 case_study, ≥3 news. Enforce before save."
3. **Deep reading (not snippets)** — "WebFetch full content per URL. Visit entity landing page to write visual_style paragraph."
4. **Verbatim persona voice** — "From Reddit/Quora/HN, copy actual user quotes unchanged. Don't paraphrase or combine."
5. **Self-review gate** — "Before PUT /save-research: verify all counts hit targets; remediate with targeted queries if not."

**Opus prompt skeleton:**

```xml
<role>Senior investigative journalist. Opus depth, not Google snippet summary.</role>

<targets>20-30 data_points | 10+ entities with visual_style | 3-5 personas | 5+ quotes</targets>
<source_diversity>≥4 research | ≥3 forum | ≥3 expert | ≥2 counter | ≥2 case | ≥3 news</source_diversity>

<layer_1>6-8 parallel WebSearch queries across category matrix</layer_1>
<layer_2>WebFetch top 5-8 URLs. Full content read. Visit entity landing pages.</layer_2>
<layer_3>Cluster + contrarian sweep for 3 strongest claims</layer_3>
<layer_4>Verbatim persona extraction from forums. Written guides from official docs (how-to only).</layer_4>

<self_review>Hit all targets? Source matrix met? If no → fill-in queries.</self_review>

<output>JSON matching schema v2. PUT /save-research then POST /continue-pipeline.</output>
```

Full prompt lives in NEW `refs-research.md` compiled reference, injected via `--append-system-prompt-file`.

**Backend integration:**

- Migration: `content_ideas` → add:
  - `research_tier_override` (enum: `quick`, `deep`, `auto`, default `auto`)
  - `research_data` already exists via prep_data, ensure separate column OR nest under `prep_data.research_data` (confirm in plan phase)
- [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) `triggerForIdea()`:
  - Resolve tier: `idea.research_tier_override === 'auto' ? (virality_score >= 70 ? 'deep' : 'quick') : override`
  - Resolve model: `tier === 'deep' ? config('article.model_research_deep') : config('article.model_research_quick')`
  - Dispatch `/article-research --research-tier {tier}` with resolved model via `--model {sonnet|opus}` CLI flag
  - On `completed_step: research` callback → dispatch `/article-strategy-outline` (Sonnet)
  - On `completed_step: prep` callback → dispatch `/article-write` (Sonnet, unchanged)
- NEW endpoint `PUT /automation/content-ideas/{id}/save-research` (stores research_data separately from strategy+outline)
- `/article-images` skill augmentation: read `research_data.named_entities[].visual_style` to compose brand-aware prompts (no external assets needed)

**`/article-images` prompt enrichment (brand-aware):**

Current prompt: generic cinematic description
New prompt: if article entities include brands (e.g. ChatGPT, Claude, Figma), prompt includes:
```
Feature [brand_name]-style UI mockup:
  - Color palette: {visual_style.colors}
  - Layout pattern: {visual_style.layout}
  - Typography: {visual_style.typography}
  - Hero element: {visual_style.hero_element}
Stylized illustration matching brand identity (not photorealistic screenshot).
```
Example for ChatGPT article: "Purple-to-blue gradient header, speech-bubble conversation layout, sans-serif typography, GPT-4 model selector in top-left, centered input with circular Send button, dark mode aesthetic."

**UI:**
- [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) Gate 1 Config modal:
  - NEW tier picker: `Quick (1-2 min, Sonnet)` / `Deep (5-8 min, Opus)` / `Auto (based on virality score)`
  - Default: Auto
  - Show current auto-decision preview: "Auto will pick: Deep (score 82, Opus)"
- Progress modal: separate `RESEARCH` phase card + `STRATEGY+OUTLINE` phase card (instead of single `PREP`). Research card shows model badge (Opus/Sonnet) + 4 layer sub-steps for deep tier.

**Env flags:**
```env
ARTICLE_GEN_MODEL_RESEARCH_DEEP=opus       # NEW — Opus for deep research only
ARTICLE_GEN_MODEL_RESEARCH_QUICK=sonnet    # NEW — Sonnet for quick research
ARTICLE_GEN_MODEL_STRATEGY_OUTLINE=sonnet  # NEW — always Sonnet for strategy+outline
# Remove: ARTICLE_GEN_MODEL_PREP (deprecated; split)
ARTICLE_GEN_DEEP_RESEARCH_ENABLED=true     # Feature flag, default true once Phase B ships
ARTICLE_GEN_SKILL_SPLIT_ENABLED=true       # Feature flag, default false during rollout; flip to true after validation
```

### Time/Cost

- Quick tier: unchanged (1-2 min, Sonnet, ~1 WebSearch batch)
- Deep tier: +4-6 min, Opus (~5x Sonnet cost for research step only = ~2-3x total prep cost), +30-40K input tokens, +5-8 WebFetch calls
- Strategy+Outline: unchanged (30-60s, Sonnet)
- Expected mix: ~60-70% topics hit deep tier (aggressive threshold)
- Opus savings vs whole-prep-opus: ~50% (Opus only for research, not strategy/outline)

---

## Data Integration Map

| Component | Data Source | Existing? | Phase |
|-----------|-------------|-----------|-------|
| `TrendingTopicService::getScoredTopics()` | Google Trends + News RSS | YES (refactor) | A |
| `TopicScoringService::scoreBatch()` | NEW Sonnet call via ArticleGenerationService | NO | A |
| `content_ideas.virality_score` + `virality_breakdown` | NEW columns | NO (migration) | A |
| `POST /trending/score-batch` endpoint | NEW route + controller method | NO | A |
| Trending Preview score badges + sort | [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) | YES (augment) | A |
| `content_ideas.mechanical_scores_snapshot` | NEW column + inline compute | NO (migration) | C |
| `ARTICLE_GEN_USE_SCORE_PHASE` env flag | NEW in [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) | NO | C |
| Article Preview scorecard UI | [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) Article Preview modal | YES (augment) | C |
| `POST /ideas/{id}/run-deep-score` endpoint | NEW route + controller method | NO | C |
| "Run Deep Quality Analysis" button | [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) | NO | C |
| NEW skill `/article-research` (Opus/Sonnet tier-based) | NEW plugin skill | NO (create) | B |
| NEW skill `/article-strategy-outline` (Sonnet) | NEW plugin skill | NO (create) | B |
| NEW compiled `refs-research.md` | NEW reference file | NO (create) | B |
| `refs-prep.md` → `refs-strategy-outline.md` | Existing compiled ref | YES (rename + slim) | B |
| `--research-tier` flag in /article-research | NEW skill flag | NO | B |
| `--model` CLI flag dispatch | [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) | YES (augment) | B |
| Deep research 4-layer framework | `refs-research.md` | NO (create) | B |
| `content_ideas.research_tier_override` | NEW column | NO (migration) | B |
| NEW endpoint `PUT /save-research` | NEW route + controller method | NO | B |
| Tier + model resolution in ArticleGenerationService | [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) | YES (augment) | B |
| `/article-images` prompt enrichment (brand-aware) | [article-images/SKILL.md](D:/Projects/claude-plugin/article-content-writer/skills/article-images/SKILL.md) | YES (augment) | B |
| Gate 1 Config modal tier picker (with model badge) | [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) | YES (augment) | B |
| Progress modal — separate RESEARCH + STRATEGY+OUTLINE cards | [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) Progress modal | YES (refactor) | B |
| `/article-prep` skill (legacy fallback) | [article-prep/SKILL.md](D:/Projects/claude-plugin/article-content-writer/skills/article-prep/SKILL.md) | YES (deprecate, keep for fallback) | B |

**Zero placeholder risk** — every integration uses existing patterns (SSH dispatch, `continue-pipeline`, progress polling, file-based curl, env flags, migration conventions).

---

## Pipeline Timing Comparison

| Scenario | Research | Strategy+Outline | Write | Score | Images | Total |
|----------|----------|------------------|-------|-------|--------|-------|
| **Current** (single /article-prep call, Sonnet) | (bundled 2-3 min) | | 3-4 min | 1 min | 1-2 min | **8-11 min** |
| **New (quick research, marginal topic)** Sonnet | 1-2 min | ~1 min | 3-4 min | instant | 1-2 min | **6-10 min** ⬇️ |
| **New (deep research, viral topic)** Opus | 5-8 min | ~1 min | 3-4 min | instant | 1-2 min | **10-15 min** (higher quality ceiling) |
| **New + on-demand deep score** | (+ ~1 min when user clicks button) | | | | | |

Net effect: investment follows topic value. Low-virality topics get faster pipeline (6-10 min vs current 8-11); high-virality topics get Opus-grade deep research (10-15 min, but output quality 2-3x richer per metrics in research schema table).

---

## Rollout Plan (Phased A → C → B)

### Increment 1 — Phase A (Topic Virality Pre-Scoring)

**Why first:** Standalone change. Doesn't touch article generation pipeline. Low risk. Immediate gain: user picks better topics.

**Deliverables:**
- `TopicScoringService` + batch Sonnet call integration
- Migration + API endpoint
- Trending Preview modal score badges + sort dropdown
- Cache strategy (1 hour TTL)

**Validation:** Pull trending → confirm scores appear → verify ranking matches intuition.

### Increment 2 — Phase C (Trim Scoring to Mechanical-Only)

**Why second:** Low risk (mechanical already exists), immediate 10% speedup. Validates env-flag-gated rollout pattern before riskier Phase B.

**Deliverables:**
- `ARTICLE_GEN_USE_SCORE_PHASE` env flag + pipeline conditional
- Mechanical scores snapshot column + inline compute
- Article Preview scorecard UI
- "Run Deep Quality Analysis" button + on-demand endpoint

**Validation:** Generate article with flag off → verify status goes to `article_ready` without AI score → click button → verify deep score runs correctly.

### Increment 3 — Phase B (Tiered Deep Research + Skill Split)

**Why last:** Highest complexity (2 new plugin skills + skill split refactor + Opus dispatch + brand-aware `/article-images` augmentation + UI refactor). Benefits from A+C feedback. Largest quality impact — ship when foundation solid.

**Deliverables:**
- Plugin:
  - NEW `/article-research` skill (Opus/Sonnet tier-based)
  - NEW `/article-strategy-outline` skill (Sonnet)
  - NEW `refs-research.md` compiled reference (deep research framework + entity + written guide extraction)
  - `refs-prep.md` → `refs-strategy-outline.md` slim rename
  - `/article-prep` → deprecate but keep for backward-compat fallback
  - `/article-images` → augment to read `research_data.named_entities[].visual_style` for brand-aware prompts
- Backend:
  - Migration: `content_ideas.research_tier_override`
  - NEW `PUT /automation/content-ideas/{id}/save-research` endpoint
  - [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) — tier + model resolution, dispatch 2 skills via `continue-pipeline`
  - Env vars: `ARTICLE_GEN_MODEL_RESEARCH_DEEP`, `ARTICLE_GEN_MODEL_RESEARCH_QUICK`, `ARTICLE_GEN_MODEL_STRATEGY_OUTLINE`, `ARTICLE_GEN_SKILL_SPLIT_ENABLED`
- UI:
  - Gate 1 Config tier picker (with model badge: Sonnet/Opus preview)
  - Progress modal refactor: separate `RESEARCH` card (with layer indicators) + `STRATEGY+OUTLINE` card

**Validation:**
- Generate article with `tier=quick` → baseline (should match current behavior)
- Generate with `tier=deep` → verify `research_data` contains 20+ data points, 3+ personas, 10+ entities with visual_style, 5+ quoted sources
- Verify auto-tier picks deep for virality_score ≥ 70
- Verify Opus dispatch in SSH command (check process list / logs for `--model opus`)
- Verify `/article-images` uses entity visual_style for brand articles (e.g. ChatGPT article → purple gradient + speech bubbles in prompt, not generic "AI chat UI")
- Verify fallback: disable `ARTICLE_GEN_SKILL_SPLIT_ENABLED` → system falls back to legacy `/article-prep` single-call path

---

## Open Questions (defer to `gaspol-plan`)

1. ~~**Deep research skill architecture** — extend existing `/article-prep` with tier flag, or create separate `/article-research-deep` skill?~~ **RESOLVED:** split into `/article-research` (Opus-capable) + `/article-strategy-outline` (Sonnet). Rationale in Decision Summary.
2. **Mechanical scores snapshot compute timing** — at write completion (automatic) or on Gate 1 modal open (lazy)? Leaning automatic at completion (avoids modal load delay).
3. **Virality batch scoring prompt** — needs careful prompt engineering to evaluate 20 topics in single call without truncation. Plan phase to draft + test.
4. **research_data storage shape** — separate `content_ideas.research_data` column vs nest under `prep_data.research_data`? Leaning separate column for cleaner /save-research separation, but confirm during plan phase.
5. **`/article-images` visual_style schema** — what fields per entity? Proposed: `{colors: [], layout: string, typography: string, hero_element: string, mood: string}`. Plan phase to finalize schema + worked examples.
6. **Rollback strategy per increment** — all gated by env flags (`ARTICLE_GEN_USE_SCORE_PHASE`, `ARTICLE_GEN_DEEP_RESEARCH_ENABLED`, `ARTICLE_GEN_SKILL_SPLIT_ENABLED`). No DB migration rollback needed (new columns are nullable/additive). `/article-prep` legacy kept as fallback path.
7. **Cost ceiling** — if Opus cost balloons (e.g. 50 articles/day × deep × Opus ≈ $X/day), add soft rate limit or budget alert? Out of scope for this design, flag for ops monitoring.

---

## Anti-AI-Slop Self-Check

- [x] No placeholders — every data source maps to real existing endpoint, column, or service
- [x] Respects CLAUDE.md patterns — uses ArticleGenerationService SSH dispatch, file-based curl, env flag gating, migration conventions
- [x] YAGNI — only adds features tied to the 3 user questions; no speculative abstractions
- [x] Rollback-safe — env flags per phase, additive migrations, backward-compat `getBestTopic()` kept
- [x] Progressive disclosure — augments existing modals, no new UI surfaces
