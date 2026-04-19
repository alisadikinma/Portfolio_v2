# Trending Topics UI — refactor to single combined score

**Date:** 2026-04-19
**Owner:** Ali Sadikin
**Status:** Design approved, ready for implementation plan
**Driver:** User reported confusion — Tier / HOT / TRENDING / ⚡ score badges all equal-sized, sort dropdown unclear, HOT items not surfacing on top.

---

## Design

### Problem

The Trending Topics modal surfaces four overlapping signals on every card with equal visual weight:

| Badge | Meaning | Axis |
|---|---|---|
| `TIER 1` / `TIER 2` | Publisher authority (NYT/Reuters vs TechCrunch/Verge) | Quality |
| `🔥 HOT` | 3+ trusted publishers covered it in ≤24h | Velocity |
| `📈 TRENDING` | 2+ publishers in ≤12h | Velocity (weaker) |
| `⚡ 71` | AI-scored `composite_score` (virality + momentum blend) | Aggregate |

Plus a sort dropdown: `⚡ Virality` / `📊 Momentum` / `🕐 Recency`.

Result: the user cannot tell why a Tier-1 ⚡71 outranks a Tier-2 🔥HOT ⚡66 (because sort key is `composite_score`, which ignores heat entirely). The signals stopped reinforcing and started competing.

### Decision

**Collapse to one number.** Fold heat boost and tier boost into `composite_score` on the backend, expose it as `display_score`, sort by that on the frontend, and drop all four badges from the card. Keep the underlying signals available via the ⚡ hover tooltip for the one time in ten a power-user wants to know *why*.

**No more sort dropdown.** Always `display_score` desc. Search + Trusted-only + source filter are enough escape hatches.

### Score formula

```
display_score = clamp(composite_score + heatBoost + tierBoost, 0, 100)

  heatBoost: HOT +15, TRENDING +8, standard 0
  tierBoost: tier-1 +5, tier-2 +2, tier-3 0
```

Weights live in `config/content.php` so they can be re-tuned without a code change.

Rationale for the numbers: a 🔥HOT tier-1 story should beat a vanilla-score tier-1 by ~20 points — enough to flip rank order even when the raw AI scores are close, but not so much that heat alone drowns out a genuinely-higher-scored niche scoop. Tier boost is smaller because it already skews the feed via the `Trusted only` filter.

### Card layout

Before (badge soup):

```
☐ Anthropic launches Claude Design, a new product…
  TechCrunch · 1d ago · 9 sources
  [🔥 HOT] [TIER 1] [google_news] [⚡ 66]
```

After (single number, quiet metadata):

```
☐  ⚡ 86   Anthropic launches Claude Design, a new product…
         TechCrunch · 1d ago · 9 sources · google_news
```

- `⚡ 86` is large, left-aligned, color-graded (green ≥80 / amber 50-79 / grey <50).
- Source pill (`google_news` / `instagram`) demoted to the meta line alongside publisher/date/source-count — it's provenance, not a ranking signal.
- Heat + tier stay in the `title=` tooltip on the ⚡ chip:
  `Virality 72 · Momentum 61 · 🔥 HOT (+15) · Tier 1 (+5) = 92`

### Modal chrome

Before:

```
Trending Topics                    [⚡ Virality v]  [All Sources v]
[ search… ]                                        ☑ Trusted only
```

After:

```
Trending Topics                                    [All Sources v]
[ search… ]                                        ☑ Trusted only
```

Sort dropdown gone. One fewer moving part.

---

## Data Integration Map

| Piece | Source | Existing? | Change |
|---|---|---|---|
| `composite_score`, `virality_score`, `momentum_score` | `TopicScoringService::scoreBatch` | ✅ | None |
| `heat` (hot/trending/standard) | `TrendingTopicService::enrichWithHeatAndClusters` | ✅ | None — still computed, consumed downstream |
| `publisher_tier` (1/2/3) | `TrendingTopicService::classifyPublisherTier` | ✅ | None |
| `display_score` | NEW — computed in `TrendingTopicService::getScoredTopics` after AI scoring | ➕ | ~8 LOC |
| Boost weights | NEW — `config/content.php` → `trending.heat_boost`, `trending.tier_boost` | ➕ | ~6 LOC |
| `viralityTooltip(topic)` | [ContentEngine.vue:808](../../frontend/src/views/admin/ContentEngine.vue#L808) | ✅ → extend | Add heat + tier breakdown + final sum |
| Sort computed | `sortedTrending` [ContentEngine.vue:847](../../frontend/src/views/admin/ContentEngine.vue#L847) | ✅ → simplify | Collapse 3-branch switch to single `display_score` sort |
| Sort dropdown template | [ContentEngine.vue:386-390](../../frontend/src/views/admin/ContentEngine.vue#L386) | ✅ → delete | Remove `<select v-model="trendingSortBy">` + 3 options |
| `trendingSortBy` ref | [ContentEngine.vue:779](../../frontend/src/views/admin/ContentEngine.vue#L779) | ✅ → delete | Unused after dropdown removal |
| Card badges | Template block [ContentEngine.vue:429-456](../../frontend/src/views/admin/ContentEngine.vue#L429-L456) | ✅ → simplify | Remove HOT/TRENDING/TIER 1/TIER 2 badges; keep source pill + ⚡ chip; reposition ⚡ chip to leading position |

### Signals we're **keeping** on the backend

Even though the UI no longer surfaces them directly, `heat`, `publisher_tier`, and the individual AI sub-scores must still be populated — they feed the tooltip and the score formula. Nothing gets deleted from the service layer.

### Signals we're **removing from the UI**

Visual: all four badge `<span>`s in the card template.
State: `trendingSortBy` ref and the sort `<select>`.
Behavior: the 3-branch sort switch collapses to a single comparator.

---

## Feasibility

- **Zero new API fields** — `display_score` is computed server-side from values already present in the scored-topic payload. Old frontend clients (browser tabs cached pre-deploy) still render correctly, they just sort by the old `composite_score` until they reload.
- **Zero migrations.**
- **No new tests required**, but existing `TrendingTopicServiceTest` may assert on `composite_score` ordering — will need to check and update to assert on `display_score` where applicable.
- **Backend-first** rollout is safe: ship the `display_score` field on the API response first, then cut over the frontend in the same deploy (Vite rebuilds with the template change).

---

## Out of scope

- Re-tuning the AI `composite_score` formula itself (virality vs momentum weighting inside `TopicScoringService`). Separate work.
- Adding a "rising fast" filter later. If the user misses momentum-first view they'll ask, and we'll add a tiny toggle — not a generic sort picker.
- Redesigning the Ideas List table (different screen, different concerns).

---

## Follow-ups after ship

- Watch for "too many green 90+ scores" — if heatBoost is too aggressive the entire list looks HOT and the color grade stops discriminating. If that happens, drop HOT to +10 and TRENDING to +5 via `config/content.php`.
- Consider removing the `Trusted only` checkbox default=true behavior if tier-3 publishers stop showing up at the top after the score rework (they shouldn't, but worth checking).
