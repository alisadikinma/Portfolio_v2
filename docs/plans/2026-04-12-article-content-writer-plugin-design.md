# Article Content Writer Plugin — Design Document

**Date:** April 12, 2026
**Goal:** Claude Code plugin for generating viral, engaging long-form blog articles
**Status:** Design Phase — Pending Deep Research + Implementation
**Location:** `D:\Projects\claude-plugin\article-content-writer\`

---

## Architecture Decision: CLI-Based (Not RemoteTrigger)

**Decision:** All content generation uses Claude Code CLI on VPS, NOT RemoteTrigger.

**Why:**
- CLI can access installed plugins (RemoteTrigger cannot)
- On-demand triggering (no cron delay)
- Cron schedule controlled locally on VPS (not Anthropic cloud)
- Full control from admin panel
- Uses MAX plan (no extra API billing)

**How it works:**
```
Mode 1: ON-DEMAND (admin clicks button)
  Content Engine → subprocess.run(["claude", "-p", "/article-generate --idea 5"])
  → Instant, no waiting

Mode 2: SCHEDULED (VPS crontab)
  */30 * * * * claude -p "/article-generate --auto"
  → Check pending ideas → generate
  → No pending? → pull trending → pick best → generate
  → Blog always has fresh content
```

---

## Plugin Structure

```
article-content-writer/
├── .claude-plugin/plugin.json        ← Plugin metadata
├── CLAUDE.md                          ← Project docs
├── README.md
│
├── hooks/
│   ├── hooks.json                    ← SessionStart reminder
│   └── session-start.sh
│
├── agents/
│   └── article-writer-agent.md       ← Subagent for batch work
│
├── skills/
│   ├── article-generate/SKILL.md     ← Main pipeline (end-to-end)
│   ├── article-research/SKILL.md     ← Research only (trending + hooks)
│   └── article-validate/SKILL.md     ← Quality gate checker
│
└── references/                        ← Knowledge/framework files
    ├── global-config.md              ← Brand identity, API URLs, auth
    ├── sparkfluence/                 ← CORE FRAMEWORK (proven viral content)
    │   ├── topic-hook-mapping.md     ← 8 hook types, engagement boosts
    │   ├── retention-curve-model.md  ← 5-point engagement engineering
    │   ├── emotion-arc-patterns.md   ← 4 emotional journeys
    │   └── transcreation-rules.md    ← EN→ID viral localization
    ├── writing-framework.md          ← Article structure (uses Sparkfluence)
    ├── seo-geo-optimization.md       ← SEO + LLM discoverability rules
    ├── scoring-gates.md              ← Quality gate (min 7/10 or rewrite)
    └── blog-api-reference.md         ← Portfolio_v2 API endpoints
```

---

## Skills

### Skill 1: `article-generate` (Main Pipeline)

```
Two modes:

Mode A: On-demand (user specifies topic or idea ID)
  /article-generate "AI Agents for E-commerce" --lang en,id
  /article-generate --idea 5

Mode B: Auto (check pending ideas, fallback to trending)
  /article-generate --auto
  → GET /api/automation/content-ideas/pending
  → Found? → generate for that idea
  → Not found? → pull trending → pick best → generate
```

**Pipeline steps:**
```
Step 1: TOPIC ANALYSIS (Sparkfluence)
  → Read topic-hook-mapping.md
  → Score: which of 8 hook types fits?
  → Select best hook type + 3 variations

Step 2: EMOTIONAL ARC SELECTION (Sparkfluence)
  → Read emotion-arc-patterns.md
  → Match topic to arc (Surprise→Joy? Fear→Relief?)
  → Map arc points to article sections

Step 3: RETENTION CURVE ENGINEERING (Sparkfluence)
  → Read retention-curve-model.md
  → Apply 5-point engagement arc:
    SECTION 1 (Hook):   Stop scroll. Bold claim.      ← 70% bounce point
    SECTION 2 (Build):  Raise stakes. Personal.        ← 50% bounce point
    SECTION 3 (Peak):   Key insight. Data/proof.       ← 30% bounce point
    SECTION 4 (Payoff): Proof. Before/after.           ← 15% bounce point
    SECTION 5 (CTA):    Clear next step.               ← 60-80% reach here

Step 4: WRITE ARTICLE (EN)
  → 1,800-2,400 words
  → Structure: HOOK → FORESHADOW → BODY (3-4 H2) → PEAK → CTA
  → Bucket brigades between sections
  → Open loops early, close late
  → Statistics every 150-200 words
  → Subheadings = mini-hooks (not descriptive)
  → Flesch-Kincaid grade 7-9

Step 5: TRANSCREATE TO ID (NOT translate)
  → Read transcreation-rules.md
  → Code-mixing: keep tech terms in English
  → Pronouns: kita/lo/gue (NEVER saya/Anda)
  → Particles: sih, dong, deh, nih, kan, banget
  → Different meta_title, meta_description for ID SEO

Step 6: IMAGE PROMPTS
  → 1 hero image (16:9, cinematic wide shot)
  → 2-3 inline images (4:3, matching article sections)
  → 8-element structure: subject, action, setting, camera, lighting, style, texture, color

Step 7: SEO OPTIMIZATION
  → meta_title (60 chars), meta_description (155 chars)
  → meta_keywords, ai_summary (GEO)
  → Canonical URL, FAQ schema (2-3 pairs)

Step 8: QUALITY GATE
  → Score article against scoring-gates.md
  → MINIMUM 7/10 or REWRITE
  → Check: hook power, retention curve, emotion arc, shareability

Step 9: SAVE TO PORTFOLIO
  → PUT /api/automation/content-ideas/{id}/complete
  → OR POST /api/automation/blog/save-draft
```

### Skill 2: `article-research` (Research Only)

```
/article-research "AI Agents for E-commerce"

Output:
  - Trending score (1-10)
  - Best hook type + 3 variations
  - Emotion arc recommendation
  - Competitor analysis
  - Suggested angle/positioning
```

### Skill 3: `article-validate` (Quality Gate)

```
/article-validate

Checks:
  ✅ Word count (1,800-2,400)
  ✅ Flesch-Kincaid grade 7-9
  ✅ H2 structure (3-4 sections)
  ✅ Statistics density (1 per 150-200 words)
  ✅ Hook quality (matches hook-library pattern)
  ✅ Bucket brigades present (min 3)
  ✅ Open loops (opened and closed)
  ✅ SEO fields complete
  ✅ ID transcreation quality (not direct translation)
  ✅ Image prompts follow 8-element structure
  ✅ Overall engagement score (min 7/10)

Output: Score + specific issues to fix
```

---

## Sparkfluence = CORE ENGINE (Not Optional)

Sparkfluence is the MANDATORY framework for every article. Not a "nice-to-have reference."

**Enforcement in SKILL.md:**
```
MANDATORY: Before writing ANY article:
1. Read sparkfluence/topic-hook-mapping.md → SELECT hook type
2. Read sparkfluence/emotion-arc-patterns.md → SELECT emotion arc
3. Read sparkfluence/retention-curve-model.md → APPLY engagement curve
4. EVERY section must serve the retention curve
5. Quality gate MINIMUM 7/10 or REWRITE
```

**Why Sparkfluence for articles (adapted from short-form):**

| Sparkfluence Element | Short-Form (carousel) | Long-Form (article) |
|---------------------|----------------------|---------------------|
| Hook (0-2s) | First slide headline | First 2 sentences |
| Build (2-4s) | Slides 2-3 | First 200 words + foreshadow |
| Peak (4-6s) | Slide 4 (key insight) | H2 section 3 (biggest revelation) |
| Payoff (6-8s) | Slide 5 (proof) | Before/after, case study |
| CTA (8-10s) | Last slide | Closing section + FAQ |

---

## Deep Research Needed (NEXT SESSION)

Before building the plugin, do deep research via NotebookLM on:

1. **Proven copywriting frameworks for long-form articles**
   - PAS (Problem, Agitate, Solution)
   - AIDA (Attention, Interest, Desire, Action)
   - StoryBrand 7-part framework (Donald Miller)
   - Slippery Slide (Joe Sugarman) — every sentence pulls to next
   - PASTOR (Problem, Amplify, Story, Transformation, Offer, Response)

2. **Viral article mechanics**
   - What makes articles shareable (emotion, utility, relatability)
   - Completion rate optimization (mobile-first, short paragraphs)
   - Opening must grab within 150 words
   - "Name what readers feel but haven't articulated"

3. **Retention techniques for long-form**
   - Bucket brigades ("But here's the thing...", "It gets better...")
   - Open loops (create curiosity → resolve later)
   - Pattern interrupts (break monotony with format changes)
   - Seeds of curiosity (Sugarman: "But there's more", "So read on")
   - Micro-dopamine hits per paragraph

4. **Emotional engagement**
   - 4 emotion arcs (from Sparkfluence, adapted for articles)
   - Content that surprises/inspires performs 22× better than neutral
   - Story/narrative hooks have +55% engagement boost

5. **SEO + Readability**
   - Flesch-Kincaid grade 7-9
   - Short paragraphs (mobile-first)
   - One idea per sentence
   - Vary cadence to avoid monotony

**Goal:** Combine all research into `writing-framework.md` — a comprehensive, proven framework that ensures every article keeps readers engaged from first word to last.

---

## Integration Points

### Portfolio_v2 API (existing)
```
GET  /api/automation/content-ideas/pending     ← Get next idea to generate
PUT  /api/automation/content-ideas/{id}/complete ← Save generated article
POST /api/automation/blog/save-draft           ← Direct blog save (fallback)
GET  /api/automation/blog/trending-topic       ← Get trending topic
```

### VPS Deployment
```
Install plugin: claude plugin install /path/to/article-content-writer
Crontab: */30 * * * * cd /var/www/Portfolio_v2 && claude -p "/article-generate --auto"
On-demand: Content Engine subprocess.run(["claude", "-p", "/article-generate --idea 5"])
```

### Brand Config (global-config.md)
```
Brand:     Ali Sadikin Ma — AI Solopreneur
Voice:     Confident, founder-to-founder, technical but accessible
Audience:  Startups & Founders
Pillars:   Vibe Coding, AI Automation, AI Agents, AI Video/Image
Tone EN:   Professional, actionable, data-driven
Tone ID:   Casual, code-mixing, lo/gue/kita (NEVER saya/Anda)
```

---

## Next Steps

1. **Deep Research** — Use NotebookLM to research copywriting best practices
2. **Create writing-framework.md** — Comprehensive framework from research
3. **Build plugin** — Implement skills, references, hooks
4. **Test locally** — CLI test with sample topics
5. **Install on VPS** — Deploy + crontab setup
6. **Test end-to-end** — Admin panel → CLI → article generated → preview → approve

---

**Design by:** Claude Code + gaspol-brainstorm
**Last updated:** April 12, 2026
