# Research Agent — Topic Discovery System

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **Status:** Design phase — approved by user, implement in next session.
> **Date:** 2026-04-11

---

## Goal

Replace reactive RSS headline grabbing with a **Dedicated Research Agent** that proactively discovers trending AI/tech topics from developer communities. Separate "finding topics" from "writing articles" into 2 distinct agents.

## Problem

Current `TrendingTopicService` grabs Google News/Trends RSS headlines every hour. This produces:
- Generic mainstream news ("Microsoft updates Windows")
- Misses niche tech signals ("Claude Code launches Managed Agents")
- No deep context — just headline + description
- Reactive, not proactive

## Architecture: 2-Agent System

```
┌─────────────────────────────────────────────────┐
│           RESEARCH AGENT (Cloud Scheduled Task)  │
│           Runs 3x/day (8am, 2pm, 8pm)           │
│                                                   │
│  Sources (8):                                     │
│  1. HackerNews — top 30 stories                  │
│  2. Reddit — r/artificial, r/programming top/day │
│  3. GitHub — trending repos today                │
│  4. ProductHunt — new launches today             │
│  5. WebSearch — "AI news today {date}"           │
│  6. WebSearch — "new developer tools this week"  │
│  7. WebSearch — "tech announcements {date}"      │
│  8. Google News RSS — tech section (existing)    │
│                                                   │
│  Process:                                         │
│  1. Gather 50+ raw signals from all sources      │
│  2. Cluster similar topics (same story, diff src)│
│  3. Score each cluster:                           │
│     - Source count (more sources = hotter)        │
│     - HackerNews points/comments                 │
│     - Reddit upvotes                             │
│     - GitHub stars                               │
│     - Freshness (hours since first seen)          │
│     - Relevance to Ali's blog niche              │
│  4. Write brief context for top 5-10 topics      │
│  5. POST /api/automation/blog/submit-topics      │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────┐
│              topic_queue TABLE                    │
│  id | title | description | context | sources    │
│  score | status | category_id | created_at       │
│  status: pending | writing | written | rejected  │
└──────────────────┬───────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────┐
│           WRITER AGENT (Cloud Scheduled Task)    │
│           Runs hourly                             │
│                                                   │
│  1. GET /api/automation/blog/next-topic           │
│     (picks highest-scored pending topic)          │
│  2. WebSearch for deep research on topic          │
│  3. Read skills/*.md for writing framework        │
│  4. Generate article EN + ID                      │
│  5. Generate image prompts                        │
│  6. POST /api/automation/blog/save-draft          │
│  7. Backend marks topic as 'written'              │
└──────────────────────────────────────────────────┘
```

## Data Integration Map

| Feature | Data Source | Exists? | Action |
|---------|-----------|---------|--------|
| HackerNews stories | `https://hacker-news.firebaseio.com/v0/topstories.json` | No | Research Agent WebFetch |
| Reddit posts | `https://www.reddit.com/r/{sub}/top.json?t=day` | No | Research Agent WebFetch |
| GitHub trending | `https://github.com/trending?since=daily` | No | Research Agent WebFetch + parse |
| ProductHunt | `https://www.producthunt.com` | No | Research Agent WebFetch |
| Topic queue table | `topic_queue` MySQL table | No | Create migration |
| Submit topics endpoint | `POST /api/automation/blog/submit-topics` | No | Create in BlogPipelineController |
| Next topic endpoint | `GET /api/automation/blog/next-topic` | No | Create in BlogPipelineController |
| Writer Agent prompt | `.claude/writer-agent-prompt.md` | Partial | Update existing prompt |
| Research Agent prompt | `.claude/research-agent-prompt.md` | No | Create new |
| Research Agent scheduled task | Cloud Scheduled Task | No | Create at claude.ai/code/scheduled |

## Phase 1: Backend — Topic Queue

**Migration: `topic_queue` table**

```sql
CREATE TABLE topic_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  context TEXT,                    -- Research agent's brief analysis
  sources JSON,                    -- [{source: "hackernews", url: "...", score: 150}, ...]
  relevance_score INT DEFAULT 0,   -- 0-100 composite score
  category_id INT NULL,            -- suggested category
  status ENUM('pending', 'writing', 'written', 'rejected') DEFAULT 'pending',
  post_id BIGINT NULL,             -- linked post after article written
  discovered_at TIMESTAMP,         -- when first seen
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX(status, relevance_score),
  INDEX(discovered_at)
);
```

**Endpoints:**

```
POST /api/automation/blog/submit-topics    (Research Agent submits discovered topics)
  Body: { topics: [{ title, description, context, sources, relevance_score, category_id }] }
  Auth: Sanctum token
  Logic: Insert topics, skip duplicates (similar_text check against existing queue + posts)

GET  /api/automation/blog/next-topic       (Writer Agent picks best unwritten topic)
  Auth: Sanctum token
  Logic: Find highest relevance_score where status='pending', mark as 'writing', return

POST /api/automation/blog/mark-written     (Writer Agent marks topic after article saved)
  Body: { topic_id, post_id }
  Auth: Sanctum token

GET  /api/admin/blog/topic-queue           (Admin panel view)
  Auth: Sanctum
  Logic: List all topics with status filter, allow reject/prioritize
```

## Phase 2: Research Agent Prompt

```markdown
# Research Agent — Topic Discovery

You are a tech research scout for alisadikinma.com blog.
Your job: find the HOTTEST AI and technology topics that developers
are talking about RIGHT NOW. Not mainstream news — developer community signals.

## Step 1: Browse Sources

WebFetch these sources and extract trending topics:

1. HackerNews front page:
   https://hacker-news.firebaseio.com/v0/topstories.json
   Then fetch top 30 story details: /v0/item/{id}.json
   Look for: AI tools, new frameworks, developer drama, launch announcements

2. Reddit top posts today:
   https://www.reddit.com/r/artificial/top.json?t=day&limit=20
   https://www.reddit.com/r/programming/top.json?t=day&limit=20
   https://www.reddit.com/r/MachineLearning/top.json?t=day&limit=10

3. GitHub trending today:
   https://github.com/trending?since=daily
   Focus on: new AI tools, developer productivity, interesting projects

4. WebSearch queries:
   "AI news today {current_date}"
   "new AI tools released this week"
   "developer tools trending 2026"
   "Claude Anthropic latest updates"
   "OpenAI latest news"

## Blog Focus Topics (MUST match these niches)

Only submit topics that fit these categories:
1. **Vibe Coding Tools** — Cursor, Windsurf, Claude Code, Copilot, v0, Bolt, Lovable, Replit, Devin
2. **AI Automation** — n8n, workflow automation, AI pipelines, prompt engineering
3. **AI Agents** — OpenClaw, MCP, Managed Agents, CrewAI, LangChain, autonomous agents
4. **AI Image Generation** — Midjourney, DALL-E, Stable Diffusion, Flux, ComfyUI, Nano Banana
5. **AI Video Generation** — Sora, Runway, Pika, Kling, VEO, Wan, Luma, Minimax
6. **LLMs** — Claude, ChatGPT, Gemini, GPT, Llama, Mistral, DeepSeek, Qwen
7. **AI Tools & Products** — new AI apps, SaaS, developer tools

REJECT topics about: crypto trading, gaming, celebrity, sports, politics, generic business news.

## Step 2: Cluster & Score

Group similar stories (same event reported by multiple sources).
Score each cluster 0-100:
- Sources count: 1 source = 20, 2 = 40, 3+ = 60
- HackerNews: >100 points = +20, >500 = +30
- Reddit: >100 upvotes = +15
- GitHub: >100 stars today = +15
- Freshness: last 24h = +10, last 48h = +5
- Relevance to blog focus topics above: high = +10, medium = +5, low = 0

## Step 3: Submit Top Topics

POST https://alisadikinma.com/api/automation/blog/submit-topics
Authorization: Bearer {BLOG_API_TOKEN}

{
  "topics": [
    {
      "title": "Claude Code launches Managed Agents — here's what it means for developers",
      "description": "Anthropic released Managed Agents API...",
      "context": "This is significant because it means developers can now build stateful agents without managing infrastructure. Currently #1 on HackerNews with 500+ points and trending on Reddit r/artificial.",
      "sources": [
        {"source": "hackernews", "url": "https://...", "score": 523},
        {"source": "reddit", "url": "https://...", "score": 340},
        {"source": "websearch", "url": "https://...", "score": 0}
      ],
      "relevance_score": 92,
      "category_id": 1
    },
    ...up to 10 topics
  ]
}

## Rules
- Focus on AI, developer tools, programming, and tech industry
- Prefer NICHE community signals over mainstream news
- Include context paragraph explaining WHY this topic matters
- Don't submit topics already in the queue (POST endpoint handles dedup)
- Quality > quantity: 5 great topics > 10 mediocre ones
```

## Phase 3: Writer Agent Prompt (Updated)

```markdown
# Writer Agent — Article Generation

## Step 1: Get Topic from Queue

GET https://alisadikinma.com/api/automation/blog/next-topic
Authorization: Bearer {BLOG_API_TOKEN}

Response includes: title, description, context, sources, category_id.
Use the CONTEXT field — it has research from the Research Agent.

## Step 2: Deep Research on This Specific Topic

WebSearch for more details about this topic:
- Official announcement/blog post
- Community reactions and opinions
- Technical details and implications
- Comparison with alternatives

## Step 3-6: Same as current Writer Agent
(Read skills, generate article EN+ID, image prompts, save draft)
```

## Phase 4: Admin — Topic Queue UI

Simple table view in admin panel:

```
┌──────────────────────────────────────────────────────┐
│ Topic Queue                               + Add Topic │
├──────┬──────────────────────┬───────┬────────┬───────┤
│Score │ Topic                │Sources│Status  │Actions│
├──────┼──────────────────────┼───────┼────────┼───────┤
│ 92   │ Claude Managed Agents│ HN+R │pending │✏️ 🗑️ │
│ 85   │ OpenClaw hits 100k   │ GH+R │writing │  ⏳   │
│ 78   │ GPT-5 benchmarks     │ HN+WS│written │  ✅   │
│ 45   │ New CSS framework    │ R    │rejected│  ❌   │
└──────┴──────────────────────┴───────┴────────┴───────┘
```

Admin can: reject topics, boost priority, manually add topics.

## Phase Summary

| Phase | What | Est. Time |
|-------|------|-----------|
| 1 | Backend: topic_queue migration + 4 endpoints | 20 min |
| 2 | Research Agent: scheduled task prompt | 10 min |
| 3 | Writer Agent: update to use topic queue | 10 min |
| 4 | Admin: Topic Queue UI page | 15 min |
| 5 | Setup: 2 Cloud Scheduled Tasks | 5 min |

**Total: ~60 minutes**

## Cost: $0/month (Max subscription)

Both agents run on Claude Cloud Scheduled Tasks included in Max.
Research Agent: 3x/day = free
Writer Agent: 24x/day = free
