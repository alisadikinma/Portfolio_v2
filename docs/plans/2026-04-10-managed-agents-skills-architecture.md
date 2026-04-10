# Multi-Agent Platform — Skills Architecture

> **Purpose:** Architecture document for Claude Managed Agents Skills-based RAG system.
> **Status:** Design phase — Blog Agent first, expand to Carousel/Video/Social later.
> **Date:** 2026-04-10

---

## Vision

Build a multi-agent content platform where each agent is a specialist (blog, carousel, video, social) but shares common knowledge via Custom Skills. Skills are the RAG layer — reusable, versioned, loaded on-demand.

```
┌──────────────────────────────────────────────────┐
│              CUSTOM SKILLS (RAG Layer)            │
│                                                   │
│  ┌─────────────┐ ┌──────────────┐ ┌───────────┐  │
│  │ Writing      │ │ Image Gen    │ │ Multi-    │  │
│  │ Framework    │ │ Knowledge    │ │ Language  │  │
│  │ (HOOK-CTA)   │ │ (NB2, API)   │ │ (EN/ID)   │  │
│  └──────┬──────┘ └──────┬───────┘ └─────┬─────┘  │
│         │               │               │         │
│  ┌──────┴──────┐ ┌──────┴───────┐ ┌─────┴─────┐  │
│  │ Hook        │ │ Emotion      │ │ Indonesian │  │
│  │ Library     │ │ Lexicon      │ │ Slang Bank │  │
│  │ (100 hooks)  │ │ (9 emotions)  │ │ (2026)     │  │
│  └─────────────┘ └──────────────┘ └───────────┘  │
└──────────────────────────────────────────────────┘
         │                │               │
    ┌────┴────┐      ┌───┴────┐     ┌───┴────┐
    │  Blog   │      │Carousel│     │ Video  │
    │  Agent  │      │ Agent  │     │ Agent  │
    │ (NOW)   │      │(LATER) │     │(LATER) │
    └─────────┘      └────────┘     └────────┘
```

---

## Skills Inventory

### Core Skills (Shared by ALL agents)

| Skill Name | Source File | Used By | Description |
|---|---|---|---|
| `content-writing-framework` | `docs/rag/blog-article-writing.md` §1-6 | Blog, Carousel, Video | HOOK-FORE-BODY-PEAK-CTA framework, copywriting (PAS/BAB/AIDA), open loops, bucket brigades |
| `ai-image-generation` | `docs/rag/ai-image-generation.md` | Blog, Carousel, Video | 14+ models, API specs, prompt engineering, cost tiers, fallback chains |
| `multilanguage-strategy` | `docs/rag/multilanguage-content-strategy.md` §1-3,5-8 | Blog, Carousel, Social | EN/ID strategy, hreflang, content adaptation, GEO, translation workflow |

### Specialized Skills (Shared by subset of agents)

| Skill Name | Source File | Used By | Description |
|---|---|---|---|
| `hook-library` | `docs/rag/blog-article-writing.md` §12 | Blog, Carousel, Video, Social | 100 hooks (5 categories x 20), topic mapping, psychology research |
| `emotion-lexicon` | `docs/rag/blog-article-writing.md` §13 | Blog, Carousel, Video | 9 emotions, power words EN/ID, intensity scores |
| `scoring-engine` | `docs/rag/blog-article-writing.md` §15 | Blog, Video | Per-section scoring (HOOK/BODY/PEAK/CTA), engagement benchmarks |
| `indonesian-slang` | `docs/rag/multilanguage-content-strategy.md` §4B | Blog, Carousel, Social | 17 slang terms, particles, code-mixing, emoji dictionary, pronouns |
| `seo-geo-optimization` | `docs/rag/blog-article-writing.md` §7-8 | Blog | SEO 2026, GEO, E-E-A-T, featured snippets, semantic SEO |
| `visual-direction` | `docs/rag/ai-image-generation.md` §9-11 | Carousel, Video, Blog | Emotion-to-visual mapping, prompt synthesis, carousel psychology |

### Agent → Skills Mapping

| Agent | Skills Used |
|---|---|
| **Blog Article Generator** | content-writing-framework, ai-image-generation, multilanguage-strategy, hook-library, emotion-lexicon, scoring-engine, indonesian-slang, seo-geo-optimization |
| **Carousel Image Generator** (future) | ai-image-generation, hook-library, emotion-lexicon, indonesian-slang, visual-direction |
| **Video Promo Generator** (future) | content-writing-framework, ai-image-generation, hook-library, emotion-lexicon, scoring-engine, visual-direction |
| **Social Media Agent** (future) | hook-library, indonesian-slang, multilanguage-strategy, emotion-lexicon |

---

## Skill Format (SKILL.md Structure)

Each Custom Skill is a folder with a `SKILL.md` file. The agent sees the skill's description by default and loads the full content when the task is relevant.

```
skill-folder/
  └── SKILL.md    # The knowledge content
```

SKILL.md structure:
```markdown
---
name: hook-library
description: 100 research-backed content hooks organized by 5 psychology categories with topic mapping. Use when generating article titles, social media hooks, or video intros.
---

# Hook Library 2026

[Full content from RAG doc section...]
```

The `description` field is what the agent sees in context. The full content is loaded only when Claude decides it's relevant to the current task.

---

## Skills API Flow

### Upload Skills (one-time setup + update when RAG changes)

```typescript
import Anthropic from "@anthropic-ai/sdk";
import fs from "fs";

const client = new Anthropic();

// 1. Create the skill (once)
const skill = await client.beta.skills.create(
  { name: "hook-library" },
  { headers: { "anthropic-beta": "skills-2025-10-02" } }
);
console.log(`SKILL_ID=${skill.id}`); // skill_... → save this

// 2. Upload version (repeat when content updates)
const content = fs.readFileSync("skills/hook-library/SKILL.md", "utf-8");
const version = await client.beta.skills.versions.create(
  skill.id,
  { content },
  { headers: { "anthropic-beta": "skills-2025-10-02" } }
);
console.log(`Version: ${version.version}`);
```

### Reference Skills in Agent

```typescript
const agent = await client.beta.agents.create({
  name: "Blog Article Generator",
  model: "claude-opus-4-6",
  system: "You are an automated blog content pipeline for alisadikinma.com...",
  tools: [
    { type: "agent_toolset_20260401", default_config: { enabled: true } },
  ],
  skills: [
    { type: "custom", skill_id: "skill_abc123", version: "latest" }, // hook-library
    { type: "custom", skill_id: "skill_def456", version: "latest" }, // content-writing-framework
    { type: "custom", skill_id: "skill_ghi789", version: "latest" }, // ai-image-generation
    { type: "custom", skill_id: "skill_jkl012", version: "latest" }, // multilanguage-strategy
    { type: "custom", skill_id: "skill_mno345", version: "latest" }, // indonesian-slang
    { type: "custom", skill_id: "skill_pqr678", version: "latest" }, // seo-geo-optimization
    { type: "custom", skill_id: "skill_stu901", version: "latest" }, // emotion-lexicon
    { type: "custom", skill_id: "skill_vwx234", version: "latest" }, // scoring-engine
  ],
});
```

### Update Skill Content (when RAG docs change)

```typescript
// Create new version — existing agents on "latest" auto-get it
await client.beta.skills.versions.create(
  "skill_abc123", // hook-library
  { content: fs.readFileSync("skills/hook-library/SKILL.md", "utf-8") },
  { headers: { "anthropic-beta": "skills-2025-10-02" } }
);
```

---

## Skill Content Breakdown

### How to split RAG docs into Skills

The 3 RAG docs (`docs/rag/*.md`) are large monolithic files. For Skills, we split them by **topic** so agents only load what they need:

| Skill | Source Sections | Approx Size |
|---|---|---|
| `content-writing-framework` | blog-article-writing.md §1-6 (framework, psychology, hooks, open loops, bucket brigades, copywriting) | ~4,000 words |
| `hook-library` | blog-article-writing.md §12 (100 hooks, topic mapping) | ~2,500 words |
| `emotion-lexicon` | blog-article-writing.md §13 (9 emotions, EN/ID power words) | ~1,000 words |
| `scoring-engine` | blog-article-writing.md §15 (per-section scoring, benchmarks) | ~1,500 words |
| `seo-geo-optimization` | blog-article-writing.md §7-8 (SEO 2026, GEO, E-E-A-T) | ~3,000 words |
| `ai-image-generation` | ai-image-generation.md §1-8 (models, APIs, prompts, costs) | ~5,000 words |
| `visual-direction` | ai-image-generation.md §9-11 (emotion-to-visual, prompt synthesis, carousel) | ~2,500 words |
| `multilanguage-strategy` | multilanguage-content-strategy.md §1-3,5-8 (URL, hreflang, adaptation, workflow, SEO, GEO) | ~4,000 words |
| `indonesian-slang` | multilanguage-content-strategy.md §4B (slang bank, particles, code-mixing, emoji, hooks) | ~2,000 words |

**Total: 9 Custom Skills**

---

## Implementation Plan

### Phase A: Create Skills from RAG docs (15 min)

1. Create `skills/` directory in repo with 9 subdirectories
2. Split RAG doc content into individual SKILL.md files
3. Each SKILL.md has frontmatter (name, description) + full content

### Phase B: Upload Skills via API (10 min)

1. Create upload script: `scripts/upload-skills.ts`
2. Script reads each `skills/*/SKILL.md`, creates/updates via Skills API
3. Saves skill IDs to `skills/skill-ids.json` for agent reference

### Phase C: Create Blog Agent via Managed Agents API (15 min)

1. Create environment (unrestricted networking)
2. Create agent with all 9 skills + system prompt
3. Save agent_id and environment_id
4. Test with manual session

### Phase D: Setup Cloud Scheduled Task (5 min)

1. Create scheduled task pointing to Blog Agent
2. Set hourly schedule
3. Test with "Run now"

---

## Directory Structure

```
Portfolio_v2/
├── docs/rag/                          # Source of truth (human-editable)
│   ├── blog-article-writing.md
│   ├── ai-image-generation.md
│   └── multilanguage-content-strategy.md
│
├── skills/                            # Split for Skills API
│   ├── content-writing-framework/
│   │   └── SKILL.md
│   ├── hook-library/
│   │   └── SKILL.md
│   ├── emotion-lexicon/
│   │   └── SKILL.md
│   ├── scoring-engine/
│   │   └── SKILL.md
│   ├── seo-geo-optimization/
│   │   └── SKILL.md
│   ├── ai-image-generation/
│   │   └── SKILL.md
│   ├── visual-direction/
│   │   └── SKILL.md
│   ├── multilanguage-strategy/
│   │   └── SKILL.md
│   ├── indonesian-slang/
│   │   └── SKILL.md
│   └── skill-ids.json                 # Mapping of skill names → API IDs
│
├── scripts/
│   ├── upload-skills.ts               # Upload/update skills to API
│   └── setup-blog-agent.ts            # One-time agent + env setup
│
└── .claude/
    └── scheduled-task-prompt.md       # Prompt for Cloud Scheduled Task
```

---

## Future Agent Expansion

When adding a new agent (e.g., Carousel Image Generator):

1. **Identify needed skills** from the mapping table above
2. **Create agent** via API with selected skills
3. **Create scheduled task** or integrate into admin panel
4. **Reuse existing skills** — no RAG doc duplication

```typescript
// Example: Future Carousel Agent
const carouselAgent = await client.beta.agents.create({
  name: "Carousel Image Generator",
  model: "claude-opus-4-6",
  system: "You generate cinematic carousel images for social media...",
  tools: [
    { type: "agent_toolset_20260401", default_config: { enabled: true } },
  ],
  skills: [
    { type: "custom", skill_id: skillIds["ai-image-generation"], version: "latest" },
    { type: "custom", skill_id: skillIds["hook-library"], version: "latest" },
    { type: "custom", skill_id: skillIds["emotion-lexicon"], version: "latest" },
    { type: "custom", skill_id: skillIds["indonesian-slang"], version: "latest" },
    { type: "custom", skill_id: skillIds["visual-direction"], version: "latest" },
  ],
});
```

No new RAG docs needed — just pick and choose existing skills.
