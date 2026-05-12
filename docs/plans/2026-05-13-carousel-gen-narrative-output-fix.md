# Fix `/carousel-gen` Narrative-Only Output (JSON Envelope Skipped)

**Date:** 2026-05-13
**Status:** Investigation complete. Implementation pending. Cross-repo work (Portfolio_v2 + `ai-image-carousel-prompt-gen` plugin).
**Source:** /gaspol-debug session 2026-05-13 (Action C of LinkedIn timeout incident — see commits `49a215e6`, `2336fd82`).
**Maintainer:** alisadikinma

## Problem Statement

`/carousel-gen` pipeline-mode invocations fail at a non-zero rate with one of two distinct failure modes:

**Mode A — SSH timeout (exit 124):** Sonnet hangs past the 900s remote `timeout` wrapper, killed by the kernel signal. No stdout captured. Backend logs `SSH carousel-gen exec failed: exit 124`. No forensic dump written.

**Mode B — Narrative-only output (this plan's primary scope):** Sonnet exits cleanly (exit 0) but emits a Markdown reasoning summary instead of the contracted JSON envelope. Backend's balanced-brace parser anchors on first `{`, finds none, returns `null`. `LinkedInGenerationService::dispatchCarouselGenEngine` writes a forensic dump to `storage/app/carousel-gen-debug/draft-{id}-{ts}.txt` and surfaces `last_error="carousel-gen dispatch failed or returned null/empty stdout"`. The draft routes to FSM `failed`.

### Evidence — Mode B

Two recent forensic dumps confirm the pattern.

**Dump 1: `draft-106-20260512232622.txt` (2334 bytes)** — verbatim head:

```
**Pipeline run complete.** Status: `complete` · 7 slides · 4:5 · bilingual `id/en` · narrative `5act`.

---

**Pipeline resolution log:**

| Decision | Resolved To | Source |
|---|---|---|
| Costume | Tech / AI (dark charcoal hoodie + earbud) | `topic_match` — keywords "OpenAI", "GPT" in blog title |
| Visual hook | **Scale Disruption** — tiny creator vs skyscraper-sized OpenAI brain | §5 dramatic ranking (tech/benchmark topic) |
...
**Verified facts used:**
- +15.2% Big Bench Audio, +13.8% Audio MultiChallenge vs GPT-Realtime-1.5 — ✓ OpenAI + Artificial Analysis
...
Sources:
- [Advancing voice intelligence with new models in the API | OpenAI](...)
...
```

**Dump 2: `draft-115-20260510213911.txt` (1389 bytes)** — same shape: Pipeline resolution summary, 5-act structure table, brand refs note. **Both dumps contain ZERO `{` characters** — the parser correctly returned null.

### Why this happens

SKILL.md at `ai-image-carousel-prompt-gen/skills/carousel-gen/SKILL.md` lines 90-95 (v2.21.0) does state the contract:

> In pipeline mode, the skill emits **one JSON document to stdout** matching `CarouselGenOutputSchema`. No Markdown wrapping. No leading prose, no trailing narration. ... the contract is "JSON only."

But the constraint sits in the middle of a 36KB SKILL file. Sonnet's instruction-following is biased toward the **most recent instruction in context** (last-instruction-wins). After 36KB of mode docs, reference tables, hook libraries, and creative direction, the JSON-only constraint is forgotten by output time. Sonnet emits its chain-of-thought "Step 16 result summary" instead.

### Why this didn't surface earlier

- May 2-4 entries in [CLAUDE.md] documented a *different* truncation pattern: Sonnet emitting partial JSON (slides 1-7) then narrating "Completing slide 8 and slide 9..." continuation prose instead of valid JSON. That issue was about output **token cap exhaustion**.
- Mode B (this plan) is about **instruction compliance**, not token capacity. Dumps are SMALL (1-2KB), not truncation-large (27-41KB like the May 2-4 dumps).
- Production rate was low pre-May-12 because the format-mix governor wasn't shipped — `/carousel-gen` only fired on operator-initiated carousel regenerates.
- Post-governor (May 12): every text draft → governor over-ride → `/carousel-gen` invocation → exposure to Mode B amplified ~10×.

## Goals

1. Reduce Mode B failure rate to <5% of `/carousel-gen` invocations
2. Add defense-in-depth: backend detects narrative-only output and emits a clearer last_error (currently the operator sees the generic "dispatch failed" message)
3. Document the failure pattern so future contributors don't conflate it with Mode A or the May 2-4 truncation issue

## Non-Goals

- Eliminating Mode A (SSH timeout). Separate plan when frequency justifies. Mitigations exist (lower `target_slides`, `CAROUSEL_GEN_MODEL=opus`).
- Plugin-side overhaul of carousel-gen's reasoning chain. The interactive mode behaviour is correct and valued — only pipeline mode needs the constraint tightened.
- Backend retry on Mode B failure. Sonnet emits the same narrative on a fresh run with the same prompt — retry would waste another SSH cycle. Surface to operator for manual review instead.

## Design

### Three-pronged fix

**P1. Plugin-side constraint reinforcement (primary fix):**
- Move the "JSON only" contract block from line 90 to the **last 30 lines** of `skills/carousel-gen/SKILL.md`, after all reference tables and creative direction. Last-instruction-wins bias works in our favour.
- Append a stronger imperative: *"Your entire response MUST be parsed as JSON by `JSON.parse()`. The first character of your response MUST be `{`. The last character MUST be `}`. Do not narrate. Do not summarize. Do not emit Markdown tables. Do not cite sources. Do not include 'Pipeline run complete.' prose."*
- Add a self-check directive: *"Before emitting your response, verify the first character is `{` — if you find yourself drafting Markdown headers or tables, you have violated the contract; restart and emit only the JSON object."*

**P2. Pipeline-input post-prompt (defense-in-depth):**
- `LinkedInGenerationService::dispatchCarouselGenEngine` (and any other carousel-gen caller) appends a final-line instruction to the prompt sent over SSH:
  ```
  CRITICAL OUTPUT CONTRACT: Respond with valid JSON only. First character `{`, last character `}`. No prose before or after. No Markdown. No tables. No sources list. The backend parses your output with JSON.parse() — non-JSON responses are dropped.
  ```
- This sits AFTER all SKILL.md content in Sonnet's context, getting the strongest instruction-following weight. Even if plugin SKILL.md doesn't update, this caller-injected constraint compensates.

**P3. Backend detection + clearer error (UX improvement):**
- `CarouselGenOutputAdapter::adapt` already throws on null/empty. Extend the upstream call site (`LinkedInGenerationService::dispatchCarouselGenEngine`) to inspect stdout BEFORE parsing:
  - If stdout is non-empty AND contains zero `{` characters → classify as Mode B and write a different last_error: `"carousel-gen returned narrative summary instead of JSON envelope — see storage/app/carousel-gen-debug/draft-{id}-{ts}.txt"`.
  - If stdout is empty AND exit code 124 → classify as Mode A: `"carousel-gen SSH timed out — see system log around timestamp X"`.
  - Existing generic error fires only on unknown failure mode.
- `PipelineErrorClassifier::classify` gains two new substring matches:
  - `"narrative summary instead of JSON"` → new class `PipelineErrorClass::DeterministicLlm` (already exists, no enum change; behavior: 1 retry only — but since Mode B is deterministic on same prompt, the retry will fail again; operator can manually trigger after plugin fix)
  - `"carousel-gen SSH timed out"` → `PipelineErrorClass::Transient` (already correct)

### Implementation phases

| Phase | Repo | File(s) | Owner | Effort |
|---|---|---|---|---|
| **P1a** Restructure SKILL.md output contract | `ai-image-carousel-prompt-gen` | `skills/carousel-gen/SKILL.md` | Plugin maintainer | ~30 min |
| **P1b** Bump plugin version 2.21.0 → 2.22.0, tag, push | `ai-image-carousel-prompt-gen` | `package.json` + git tag | Plugin maintainer | ~10 min |
| **P1c** Update marketplace pin | `alisadikinma-ai-content-suite` | `.claude-plugin/marketplace.json` | Operator | ~5 min |
| **P1d** VPS deploy: clone v2.22.0, `npm install + npm run compile-refs`, repoint `/home/claudesn/refs-carousel-gen-pipeline.md` symlink | VPS | shell ops | Operator | ~10 min |
| **P2** Add CRITICAL OUTPUT CONTRACT post-prompt | `Portfolio_v2` | `LinkedInGenerationService::dispatchCarouselGenEngine` (~line 916) | Backend dev | ~20 min + 2 unit tests |
| **P3a** Backend Mode A/B/Unknown classifier in dispatch site | `Portfolio_v2` | `LinkedInGenerationService::dispatchCarouselGenEngine` | Backend dev | ~30 min + 3 unit tests |
| **P3b** Classifier substring matches | `Portfolio_v2` | `PipelineErrorClassifier` | Backend dev | ~10 min + 2 unit tests |
| **P4** Verification — run `/carousel-gen` on 3 historical Mode-B blogs (#106, #115, plus 1 fresh) | VPS | manual tinker | Operator | ~30 min |

Total: ~3 hours across two repos.

### Verification

1. **Pre-deploy baseline:** count current Mode B occurrences in `storage/app/carousel-gen-debug/` — 2 in last 14 days (`draft-106`, `draft-115`). Rate = ~10% of recent /carousel-gen invocations.
2. **Post-deploy synthetic test:** SSH to VPS, manually invoke `/carousel-gen` 5 times on blog post #34 (OpenAI GPT topic, similar to the failing #106). Expect 5/5 valid JSON envelopes. Acceptance: ≥4/5.
3. **Production smoke (7-day window):** re-enable format-mix governor (`linkedin_format_governor_enabled='true'`). Watch dump directory + admin Failed tab. Acceptance: <1 new Mode B occurrence per 7 days under typical scan volume (~5 text drafts/day → ~5 carousel-gen calls/day → ~35/week).

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Plugin SKILL.md restructure breaks interactive mode | Low | High | P1a touches only the output-contract block, leaves reasoning chain + reference tables untouched. Run interactive smoke test before tagging v2.22.0. |
| Post-prompt P2 conflicts with strict refs-carousel-gen-pipeline.md context | Low | Med | Test on local Laravel `.env` (`CAROUSEL_GEN_DRIVER=local`) before pushing |
| New backend error message breaks `PipelineErrorClassifier` regex test fixtures | Med | Low | P3 includes unit test updates — covered |
| Mode A separately resurges, masking P1+P2 benefit | Med | Med | Mode A and Mode B distinguishable by dump-existence + exit code; classifier separates. Tracked independently. |
| Plugin v2.22.0 SKILL.md change is also "narrated to death" by Sonnet on first run | Low | Med | P2 backend post-prompt provides backstop; even if P1 fails, P2's last-instruction enforcement should hold |

## Operational State (current)

- Format-mix governor: **OFF** (`linkedin_format_governor_enabled='false'`, set 2026-05-13 by /gaspol-debug Action A). No new `/carousel-gen` invocations from auto-text-override path until this plan lands.
- Drafts #103, #106 (Mode B victims from today): remain in `failed` state pending operator manual regenerate.
- Existing carousel-gen dumps preserved at `storage/app/carousel-gen-debug/` — do not delete; useful for P4 regression testing.

## When to re-enable governor

After P1+P2 deploy AND P4 synthetic test passes (≥4/5 valid JSON), flip `linkedin_format_governor_enabled='true'` via `/admin/settings → LinkedIn`. Monitor `/storage/app/carousel-gen-debug/` directory size for 48h — if new dumps appear at >1 per day, roll back the setting and re-investigate.

## Out of scope (separate plans)

- **Mode A SSH timeout root cause** — Sonnet web-fetch hangs, MCP cleanup, network hiccups. Mitigations exist (lower `target_slides`, `CAROUSEL_GEN_MODEL=opus`). Document if rate exceeds 5%.
- **Plugin v0.7.0 `format_preference` honor** — May 12 CLAUDE.md entry incorrectly stated this is deferred; v0.6.0 already honors. CLAUDE.md needs correction commit (not a plan, just a docs fix).
- **Switch `/carousel-gen` model to Opus** — 4-5x cost, justify only if P1+P2 insufficient. Treat as Plan B for this same problem.

## References

- Failure mode discovery: /gaspol-debug session 2026-05-13 (commits `49a215e6`, `2336fd82`)
- May 2-4 truncation pattern (different issue): root [CLAUDE.md] "Phase D SHIPPED (May 2, 2026)" entry
- Plugin SKILL.md: `ai-image-carousel-prompt-gen/skills/carousel-gen/SKILL.md` v2.21.0 lines 88-117
- Backend caller: [backend/app/Services/LinkedInGenerationService.php](../../backend/app/Services/LinkedInGenerationService.php) `dispatchCarouselGenEngine` method (~line 916)
- Classifier: [backend/app/Services/PipelineErrorClassifier.php](../../backend/app/Services/PipelineErrorClassifier.php)
- Forensic dumps: `/var/www/Portfolio_v2/backend/storage/app/carousel-gen-debug/`
