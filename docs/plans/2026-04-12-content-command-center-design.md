# Content Command Center — Design Document

**Date:** April 12, 2026
**Goal:** Admin page for managing content ideas pipeline with Content Engine integration
**Status:** Design Approved → Ready for Implementation Plan

---

## Summary

A spreadsheet-style admin page where Ali manages content ideas from draft to published content. Ideas come from manual input or trending topic pulls (5 sources). Each idea goes through a gated pipeline: draft → research → preview → generate → complete. Nothing generates without explicit user confirmation.

---

## Complete Flow

### Flow 1: Manual Idea Input

```
User clicks [+ Add Row]
  → New row in spreadsheet: topic, pillar, priority
  → Status: draft
  → Saved to content_ideas table (MySQL)
```

### Flow 2: Pull Trending Topics

```
User clicks [Pull Trending ▾] → select source (All / Google / YouTube / TikTok / News / Instagram)
  ↓
MODAL: Trending Preview
  Shows 10-20 trending topics from selected source(s)
  User checks relevant ones
  ↓
[Add N to Ideas List →]
  → Selected topics added to ideas list as "draft"
  → source field set to origin (google_trends/tiktok/etc)
  → source_data stores raw trending data
```

### Flow 3: Draft → Generate Pipeline

```
STATUS: draft
  User edits row: topic, pillar, priority
  Action: [Next →]
  ↓
MODAL 1: Configuration
  Output types (multi-select):
    ☑ Blog Article
    ☑ Instagram Carousel
    ☐ Social Video (9:16)
    ☐ Video Promo (16:9)
  
  Languages (multi-select):
    ☑ English
    ☑ Indonesian
  
  Instructions (optional):
    [Focus on solopreneur angle............]
  
  [Cancel]  [Confirm & Research →]
  ↓
STATUS: researching (automatic, ~2 min)
  Content Engine Research Agent runs
  Spinner on row
  ↓
STATUS: researched
  Action: [Preview 👁]
  ↓
MODAL 2: Research Preview
  Shows:
    - Trending Score: 8.5/10
    - Suggested Hooks: 3 options
    - Content Angles: 2 angles
    - Competitor Posts: 12 found
  
  Summary of what will be generated:
    ✓ Blog Article (EN + ID)
    ✓ Carousel (EN + ID)
  
  User can:
    - Edit topic/angle before generating
    - Go back to draft (← Back to Draft)
    - Approve (🚀 Approve & Generate)
  ↓
STATUS: generating
  Content Engine workflows running
  Auto-poll every 10 seconds
  Progress shown: Article 2/3 · Carousel 1/4
  ↓
STATUS: completed ✅
  Action: [View Drafts]
    → Blog draft at /admin/posts
    → Carousel draft at /admin/carousel-drafts
```

### Status Flow

```
draft → researching → researched → generating → completed
  ↑        (auto)         ↑           (auto)
  │                       │
  └── user can go "Back to Draft" from research preview

Separate: archived (user can archive any status)
```

### User Confirmation Points (2 gates)

1. **Gate 1:** Modal 1 — choose output types + languages → "Confirm & Research"
2. **Gate 2:** Modal 2 — review research results → "Approve & Generate"

**Nothing auto-generates. User always in control.**

---

## UI Layout: Spreadsheet View

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│  CONTENT ENGINE                                    [🟢 Online] [+ Add Row] [Pull ▾] │
│                                                                                      │
│  Filter: [All Pillars ▾] [All Status ▾] [All Priority ▾]                [🔍 Search] │
│                                                                                      │
│  ┌────┬─────────────────────┬──────────┬────────┬──────────┬────────────┬───────────┐│
│  │ #  │ Topic               │ Pillar   │ Priority│ Status   │ Source     │ Actions   ││
│  ├────┼─────────────────────┼──────────┼────────┼──────────┼────────────┼───────────┤│
│  │ 6  │ AI Agents 2026      │ agents   │ high   │ draft    │ manual     │ [Next →]  ││
│  │ 5  │ Vibe Coding Tips    │ vibe     │ medium │ researched│ google    │ [Preview] ││
│  │ 4  │ n8n for Startups    │ auto     │ high   │ generating│ tiktok   │ 2/4 steps ││
│  │ 3  │ VEO 3 Tutorial      │ video    │ low    │ completed│ manual    │ [View]    ││
│  │ 2  │ Claude Plugins      │ vibe     │ medium │ archived │ google    │ [Restore] ││
│  └────┴─────────────────────┴──────────┴────────┴──────────┴────────────┴───────────┘│
│                                                                                      │
│  WORKFLOW HISTORY                                                        [Refresh]   │
│  ┌────┬───────────┬─────────────────────┬────────┬────────┬──────────────────────┐   │
│  │ ID │ Type      │ Topic               │ Status │ Step   │ Created              │   │
│  │ 7  │ carousel  │ n8n for Startups    │ proc…  │ 2/4    │ 2026-04-12 10:30     │   │
│  │ 6  │ article   │ n8n for Startups    │ proc…  │ 1/3    │ 2026-04-12 10:30     │   │
│  │ 5  │ carousel  │ VEO 3 Tutorial      │ done   │ 4/4    │ 2026-04-12 09:15     │   │
│  └────┴───────────┴─────────────────────┴────────┴────────┴──────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────────────┘
```

### Row Actions by Status

| Status | Action Button | What Happens |
|--------|--------------|-------------|
| draft | [Next →] | Opens Configuration Modal |
| researching | spinner | Auto-polling, no action needed |
| researched | [Preview 👁] | Opens Research Preview Modal |
| generating | progress (2/4) | Auto-polling, shows step count |
| completed | [View Drafts] | Links to blog/carousel/video drafts |
| archived | [Restore] | Moves back to draft |

### Additional Actions (per row)

- **Edit** — edit topic, pillar, priority (any status except generating)
- **Delete** — permanent delete (with confirmation)
- **Archive** — soft-archive (hide from main view, restorable)

---

## Modals

### Modal 1: Configuration (after clicking "Next")

```
┌───────────────────────────────────────────────────┐
│  Configure: "AI Agents for E-commerce"            │
│                                                   │
│  ── What to generate? ─────────────────────────── │
│  ☑ Blog Article                                   │
│  ☑ Instagram Carousel                             │
│  ☐ Social Video (9:16)                            │
│  ☐ Promo Video (16:9)                             │
│                                                   │
│  ── Languages? ────────────────────────────────── │
│  ☑ English                                        │
│  ☑ Indonesian                                     │
│                                                   │
│  ── Additional instructions (optional) ────────── │
│  [Focus on solopreneur angle...................]   │
│                                                   │
│  [Cancel]              [Confirm & Research →]     │
└───────────────────────────────────────────────────┘
```

### Modal 2: Research Preview (after research completes)

```
┌───────────────────────────────────────────────────┐
│  Research: "AI Agents for E-commerce"             │
│                                                   │
│  Trending Score: 8.5/10 🔥                        │
│                                                   │
│  Suggested Hooks:                                 │
│  1. "Stop hiring — deploy an AI agent"            │
│  2. "Your competitors already use AI agents"      │
│  3. "From 40 hours to 4 with AI agents"           │
│                                                   │
│  Content Angles:                                  │
│  • Tutorial: Build your first AI agent            │
│  • Case study: How startup X saved $200k          │
│                                                   │
│  ── Edit Topic (optional) ─────────────────────── │
│  [AI Agents for E-commerce Startups...........]   │
│                                                   │
│  Will generate:                                   │
│  ✓ Blog Article (EN + ID)                         │
│  ✓ Instagram Carousel (EN + ID)                   │
│                                                   │
│  [← Back to Draft]      [🚀 Approve & Generate]  │
└───────────────────────────────────────────────────┘
```

### Modal 3: Trending Topics Preview (after clicking "Pull Trending")

```
┌───────────────────────────────────────────────────┐
│  TRENDING TOPICS              Source: [All ▾]     │
│                                                   │
│  ☐ "AI Agents Replace Junior Devs"     google     │
│  ☑ "Vibe Coding Goes Mainstream"       tiktok     │
│  ☐ "OpenAI GPT-5 Leaked Features"     youtube    │
│  ☑ "n8n vs Make.com 2026"             google     │
│  ☐ "Instagram Reels Algorithm"         instagram  │
│  ☑ "Claude Code Plugins Trending"      news       │
│  ☐ "Midjourney V7 Release"            youtube    │
│                                                   │
│  3 selected                                       │
│                                                   │
│  [Cancel]           [Add 3 to Ideas List →]       │
└───────────────────────────────────────────────────┘
```

---

## Database Schema

### New Table: `content_ideas`

```sql
CREATE TABLE content_ideas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    source ENUM('manual','google_trends','youtube','tiktok','google_news','instagram') DEFAULT 'manual',
    pillar ENUM('vibe_coding','ai_automation','ai_agents','ai_video_image','general') DEFAULT 'general',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    tags JSON NULL,
    languages JSON NULL,                -- ["en","id"]
    output_types JSON NULL,             -- ["blog_article","carousel_rebrand"]
    instructions TEXT NULL,             -- user's custom instructions
    niche VARCHAR(100) DEFAULT 'AI & Tech',
    status ENUM('draft','researching','researched','generating','completed','archived') DEFAULT 'draft',
    research_data JSON NULL,            -- {trending_score, hooks[], angles[], competitors}
    workflows JSON NULL,                -- [{type, workflow_id, status, created_at}]
    source_data JSON NULL,              -- raw trending source data
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

## Feature Checklist

### Core Features
- [x] Spreadsheet-style ideas list (persistent, MySQL)
- [x] Manual idea input (add row)
- [x] Pull trending topics (5 sources: Google Trends, YouTube, TikTok, Google News, Instagram)
- [x] Trending preview modal (user selects relevant topics)
- [x] Status pipeline: draft → researching → researched → generating → completed
- [x] Configuration modal (output types + languages + instructions)
- [x] Research preview modal (hooks, angles, score)
- [x] Edit topic/angle after research
- [x] 2 user confirmation gates (config + research preview)
- [x] Multi-select output types (article, carousel, video social, video promo)
- [x] Multi-select languages (EN, ID)
- [x] Auto-poll workflow progress (10s interval)
- [x] Workflow history table

### Management Features
- [x] Edit idea (topic, pillar, priority) — any status except generating
- [x] Delete idea (permanent, with confirmation)
- [x] Archive/restore ideas
- [x] Filter by pillar, status, priority
- [x] Search by topic

### Integration Points
- [x] Content Engine `POST /workflows` — trigger generation
- [x] Content Engine `GET /workflows/{id}` — poll status
- [x] Content Engine `GET /health` — health indicator
- [x] TrendingTopicService — pull trending from 4 sources
- [x] Content Engine `GET /instagram/media` — Instagram trending
- [x] Existing blog/carousel draft review pages — link from completed ideas

---

## Data Integration Map

| Component | Data Source | Exists? | Action |
|-----------|-----------|---------|--------|
| Ideas list | `content_ideas` table | **No** | Create migration + model |
| Trending (4 sources) | `TrendingTopicService.php` | **Yes** | Reuse existing |
| Trending (Instagram) | Content Engine `GET /instagram/media` | **Yes** | Call via ContentEngineService |
| Create workflow | Content Engine `POST /workflows` | **Yes** | Use ContentEngineService |
| Poll workflow | Content Engine `GET /workflows/{id}` | **Yes** | Use ContentEngineService |
| List workflows | Content Engine `GET /workflows` | **Yes** | Use ContentEngineService |
| Health check | Content Engine `GET /health` | **Yes** | Use ContentEngineService |
| Blog drafts | `/admin/posts` page | **Yes** | Link to existing |
| Carousel drafts | `/admin/carousel-drafts` page | **Yes** | Link to existing |
| Content Engine service | `ContentEngineService.php` | **No** | Create (from integration plan) |
| Content Engine config | `config/services.php` | **No** | Add content_engine key |
| Admin API controller | `ContentEngineController.php` | **No** | Create |
| Frontend composable | `useContentEngine.js` | **No** | Create |
| Admin page | `ContentEngine.vue` | **No** | Create |
| Sidebar nav link | `AdminLayout.vue` | **Yes** | Add router-link |
| Router entry | `router/index.js` | **Yes** | Add route |

---

**Design by:** Claude Code + gaspol-brainstorm
**Last updated:** April 12, 2026
