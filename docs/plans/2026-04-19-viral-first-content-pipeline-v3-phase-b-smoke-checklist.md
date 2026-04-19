# Phase B Plugin Smoke-Test Runbook

**Date:** 2026-04-19 · **Phase:** B.15 (final) — Manual verification for Tiered Deep Research + Skill Split
**Related:** [parent plan](./2026-04-19-viral-first-content-pipeline-v3.md) · [Phase A](./2026-04-19-viral-first-content-pipeline-v3-phase-a-plan.md) · [Phase C](./2026-04-19-viral-first-content-pipeline-v3-phase-c-plan.md)

The split plugin skills (`/article-research`, `/article-strategy-outline`) run live on the VPS via Claude CLI and can't be PHPUnit-tested. This runbook is the ground-truth verification for Phase B after staging deploy.

## Prereqs

Before running any scenario, confirm ALL of the following:

- [ ] Plugin repo commits pushed to `D:\Projects\claude-plugin\article-content-writer` and installed/synced on VPS:
  - B.8 = `6de0f96` (`/article-research` skill)
  - B.11 = `ae9f1e2` (`/article-strategy-outline` skill)
  - B.9 = `a7008ae` (compiled refs `refs-research.md` + `refs-strategy-outline.md`)
  - B.10 = `e20332c` (`/article-images` brand-aware prompt wiring)
- [ ] Compiled refs present on VPS: `/home/claudesn/refs-research.md` and `/home/claudesn/refs-strategy-outline.md` (non-zero size, readable by `claudesn` user)
- [ ] Portfolio staging `.env` has `ARTICLE_GEN_SKILL_SPLIT_ENABLED=true` and `php artisan config:clear` run
- [ ] Staging DB has at least one idea with `virality_score >= 70` AND one with `virality_score < 70` (or the ability to set `research_tier_override` manually via SQL)
- [ ] VPS log path tailable: `/tmp/article-research-*.log` + `/tmp/article-strategy-outline-*.log`
- [ ] Access to staging Portfolio admin UI (Content Engine page)
- [ ] Access to staging DB (mysql CLI or phpMyAdmin) for JSON inspection

---

## Scenario 1 — Quick Tier Happy Path

**Goal:** Verify quick tier dispatches Sonnet and produces 5-8 data_points + 1 persona in ~1-2 min.

**Execution:**
1. Pick idea with `virality_score < 70`, or force via SQL: `UPDATE content_ideas SET research_tier_override='quick' WHERE id={id};`
2. Click Play ▶ → Config Modal → select tier **Quick** → Confirm & Research
3. On VPS: `ssh claudesn@staging-host 'tail -f /tmp/article-research-{id}.log'`
4. Inspect log for `--model sonnet` in the invocation line
5. Wait 1-2 min for research callback
6. Query: `SELECT research_data FROM content_ideas WHERE id={id};` — inspect JSON

**Pass criteria:**
- Log shows `--model sonnet` (not opus)
- `research_data.data_points` length is 5-8
- `research_data.entities` length is 0-2
- `research_data.personas` length is exactly 1, with `voice` as a verbatim quote string
- `research_data.quotes` length is 0-1
- After research save, backend auto-dispatches `/article-strategy-outline` → tail `/tmp/article-strategy-outline-{id}.log` → `--model sonnet`
- After ~1 min strategy-outline callback, `SELECT generated_article FROM content_ideas WHERE id={id};` shows `generated_article.prep_data.outline.sections` populated
- Backend then dispatches `/article-write` at progress=35 (standard continuation)

---

## Scenario 2 — Deep Tier Happy Path

**Goal:** Verify deep tier dispatches Opus and produces 20-30 data_points + 10+ entities with visual_style + 3-5 personas + 5+ quotes in 5-8 min.

**Execution:**
1. Pick idea with `virality_score >= 70` (Phase A pre-scored), or force via SQL: `UPDATE content_ideas SET research_tier_override='deep' WHERE id={id};`
2. Click Play ▶ → Config Modal → select tier **Deep** (or leave Auto if virality_score ≥ 70)
3. `ssh claudesn@staging-host 'tail -f /tmp/article-research-{id}.log'`
4. Inspect log for `--model opus`
5. Wait 5-8 min
6. Query `research_data` JSON

**Pass criteria:**
- Log shows `--model opus`
- `research_data.data_points`: 20-30 entries
- `research_data.entities`: 10+ entries, each with `visual_style` as a prose paragraph (30-60 words, NOT nested JSON)
- Spot-check 3 entities: `visual_style` reads like prose — e.g. `"Green-teal + dark grey palette. Centered chat with speech bubbles and sidebar history..."`
- `research_data.personas`: 3-5 entries
- Spot-check 1 persona: `voice` is a single verbatim quote string (not an array)
- `research_data.quotes`: 5+ entries, each with `attribution`
- `research_data.written_guides`: 0-3 (how-to topics only — 0 is valid when topic has no how-to keywords)
- Strategy-outline → write continues as normal

---

## Scenario 3 — Brand-Aware `/article-images`

**Goal:** Verify `/article-images` reads `research_data.entities[].visual_style` and composes brand-aware prompts.
**Prereq:** Scenario 2 idea that includes ≥1 well-known brand entity (ChatGPT, Figma, GitHub Copilot, etc.).

**Execution:**
1. Wait for article to reach `article_ready`, approve Gate 1
2. Trigger Gate 2 (Generate Images)
3. After `/article-images` completes: `SELECT generated_article FROM content_ideas WHERE id={id};` and inspect `generated_article.image_prompts[]`

**Pass criteria:**
- Brand-section `image_prompts[i].prompt` contains specific visual_style phrases from that entity (e.g. ChatGPT: `"Green-teal"`, `"speech bubbles"`, `"Söhne sans-serif"`)
- Prompt contains literal `"Brand aesthetic:"` followed by the visual_style paragraph
- Prompt begins with `"Feature {brand_name}-style UI mockup matching brand identity."`
- Non-brand inline images fall back to generic cinematic prompts — no crash when no entity matches

---

## Scenario 4 — Legacy Fallback

**Goal:** Verify `ARTICLE_GEN_SKILL_SPLIT_ENABLED=false` reverts to legacy single-call `/article-prep`.

**Execution:**
1. On VPS, set `ARTICLE_GEN_SKILL_SPLIT_ENABLED=false` in `.env`, then `php artisan config:clear`
2. Pick a fresh `draft` idea, click Play ▶ → Confirm & Research (tier setting is inert in legacy mode)
3. `tail -f /tmp/article-prep-{id}.log` — NOT article-research

**Pass criteria:**
- Log file is `/tmp/article-prep-{id}.log`; NO `/tmp/article-research-*.log` exists for this idea
- Pipeline runs `/article-prep` → `/article-write` → `/article-score` in ~2-3 min
- `research_data` column may be empty (legacy stored inside `generated_article.prep_data.research_data`)
- Confirms backward-compat: flag flip OFF = instant rollback

**Cleanup:** Set `ARTICLE_GEN_SKILL_SPLIT_ENABLED=true` + `php artisan config:clear` before continuing.

---

## Scenario 5 — Kill Switch

**Goal:** Verify `ARTICLE_GEN_DEEP_RESEARCH_ENABLED=false` forces quick tier even for high-virality ideas.

**Execution:**
1. On VPS: `ARTICLE_GEN_DEEP_RESEARCH_ENABLED=false` (keep `ARTICLE_GEN_SKILL_SPLIT_ENABLED=true`), `php artisan config:clear`
2. Pick idea with `virality_score=95` AND `research_tier_override='auto'` (or unset)
3. Click Play ▶ → Config Modal shows **Auto** with Opus badge (Phase B.12 UI resolves client-side from virality_score)
4. Confirm & Research

**Pass criteria:**
- UI badge shows Opus, but server honors the kill switch
- `/tmp/article-research-{id}.log` shows `--model sonnet` (NOT opus)
- Short run (~1-2 min, quick-tier pace)
- `research_data.data_points` count ≤ 8

**Note:** UI badge out of sync with actual dispatch is a known/accepted limitation — the kill switch is an emergency server-side cost control, not normal operation.

**Cleanup:** Set `ARTICLE_GEN_DEEP_RESEARCH_ENABLED=true` + `php artisan config:clear` to restore.

---

## Actual Results

_To be filled after executing on staging. Record run-date, idea IDs used, pass/fail per scenario, any surprises._

### 2026-MM-DD — first smoke run
- Scenario 1:
- Scenario 2:
- Scenario 3:
- Scenario 4:
- Scenario 5:
