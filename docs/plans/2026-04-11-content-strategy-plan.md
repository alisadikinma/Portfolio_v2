# AI Solopreneur Content Strategy Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.  
> **CRITICAL:** This plan covers content creation, messaging, and brand implementation.  
> Execute AFTER Nuxt 3 SSR is live. Do not start until SSR migration is complete.

## Goal

Build a content ecosystem that proves Ali Sadikin is a credible AI Solopreneur — attracting premium partnerships, product customers, and inbound opportunities from US tech companies. Not job-seeking. Not freelancing. Building a studio.

## Architecture Context (from CLAUDE.md + Brand Design)

**Brand:** AI Solopreneur Studio  
**Origin:** "AI changed everything for me"  
**Vision:** Building & selling AI products/tools + selective client partnerships  
**Strengths:** Vibe Coding + AI Video/Image Generation  
**Secondary:** AI Automation + AI Agents  
**Target:** Startups & Founders  
**Tone:** Confident, selective, founder-to-founder (never desperate)

**Design System:**
- Dark Cinema + Gold/Cyan
- Space Grotesk (display), Inter (body), JetBrains Mono (labels)
- Glass cards, gradient borders, glow effects

**Existing Infrastructure:**
- Blog system (posts + post_translations EN/ID)
- Projects system (projects + project_translations)
- Awards system
- Gallery system
- About/Settings (CMS-managed)
- TanStack Query caching
- SEO: HasSeoFields trait, JSON-LD, meta tags, llms.txt

---

## Data Integration Map

| Content | Data Source | Existing? | Action |
|---|---|---|---|
| Blog posts | `posts` + `post_translations` | Yes | Write new posts, use existing system |
| Projects/case studies | `projects` + `project_translations` | Yes | Add new projects with case study format |
| About page | `settings` (about group) | Yes | Update via admin panel |
| Site settings | `settings` (site group) | Yes | Update tagline, hero copy |
| Gallery | `galleries` + `gallery_items` | Yes | Add AI-generated work samples |
| Awards | `awards` | Yes | Add relevant certifications/achievements |
| Categories | `blog_categories` | Yes | Create 4 pillar categories |
| Page sections | `page_sections` | Yes | Reorder/update homepage sections |
| Social links | `settings` | Yes | Add Twitter, LinkedIn, GitHub URLs |
| Products showcase | Needs new section or use `projects` | Partial | May need new page or project type |

---

## Phase A: Blog Categories & Foundation (1 day)

**Estimated time:** 2 hours

**Steps:**

1. Create 4 blog categories (via admin panel or tinker):
   ```
   - Vibe Coding (slug: vibe-coding)
   - AI Automation (slug: ai-automation)
   - AI Agents (slug: ai-agents)
   - AI Image & Video (slug: ai-image-video)
   ```

2. Verify categories appear on frontend blog page

3. Add category descriptions (for SEO):
   ```
   Vibe Coding: "Ship AI-powered products 10x faster. Philosophy, tools, and real builds."
   AI Automation: "Eliminate manual work with n8n, Make.com, and custom workflows."
   AI Agents: "Autonomous systems that think, decide, and execute."
   AI Image & Video: "Generate stunning visual content with VEO, Gemini, and more."
   ```

**Verification:**
- [ ] 4 categories created in database
- [ ] Categories visible on blog page
- [ ] Category slugs work in URL (`/blog?category=vibe-coding`)

**Commit:** `content: create 4 pillar blog categories`

---

## Phase B: Anchor Blog Posts — 8 Posts (2 weeks)

**Estimated time:** 16 hours (2 hours per post)

**Goal:** Write 2 anchor posts per pillar. These are deep, authoritative posts that prove expertise. Not fluff. Not "Top 10 AI Tools" lists. Real insights from real experience.

### Post Writing Guidelines

**Tone:** Founder-to-founder. Confident. Sharing what you've learned building.
**Length:** 1500-2500 words per post
**Structure:**
```
1. Hook (why this matters to founders)
2. The problem (what most people get wrong)
3. My approach (what I actually do)
4. Step-by-step (actionable, with code/screenshots)
5. Results (metrics, before/after, ROI)
6. Takeaway (one key lesson)
```

**Languages:** EN (primary) + ID (translation)

### Pillar 1: Vibe Coding (2 posts)

**Post B1: "How I Ship AI Products in Days, Not Months"**
```
Category: Vibe Coding
Tags: productivity, shipping, AI tools, solo founder
Content outline:
- Why traditional dev cycles are dead for AI products
- My actual workflow (Claude Code + Cursor + n8n)
- Example: [Real product you shipped fast]
- Before/after timeline comparison
- Key lesson: Speed is a competitive advantage

SEO target: "ship AI products fast", "vibe coding", "AI development speed"
```

**Post B2: "The AI Solopreneur Stack: Every Tool I Use Daily"**
```
Category: Vibe Coding
Tags: tools, stack, productivity, AI
Content outline:
- Why I went solo (AI amplifies individual work)
- My complete stack (with screenshots):
  - Coding: Claude Code, Cursor, VS Code
  - Automation: n8n, Make.com
  - Video/Image: VEO, Gemini, GeminiGen
  - Backend: Laravel, MySQL
  - Frontend: Vue/Nuxt, Tailwind
  - Hosting: XAMPP, VPS
- Cost breakdown (total monthly spend)
- What I'd cut vs what's essential

SEO target: "AI solopreneur tools", "AI developer stack", "best AI coding tools"
```

### Pillar 2: AI Automation (2 posts)

**Post B3: "I Automated My Entire Content Pipeline (Here's the Diagram)"**
```
Category: AI Automation
Tags: n8n, automation, content, workflows
Content outline:
- The problem: Content creation is time-consuming
- My solution: End-to-end automated pipeline
  - Topic discovery (trending topics API)
  - Article writing (Claude API)
  - Image generation (GeminiGen)
  - Publishing (automated blog posts)
  - Social sharing (scheduled)
- Architecture diagram (actual n8n screenshot)
- Results: X articles/week, Y hours saved

SEO target: "AI content automation", "n8n AI workflow", "automated blog"
```

**Post B4: "5 AI Automations Every Startup Founder Needs"**
```
Category: AI Automation
Tags: startups, automation, n8n, Make.com, productivity
Content outline:
- Why founders waste time on repetitive tasks
- Automation 1: Customer support (AI chatbot)
- Automation 2: Content generation (blog + social)
- Automation 3: Lead qualification (AI scoring)
- Automation 4: Reporting (auto-generated dashboards)
- Automation 5: Onboarding (personalized sequences)
- Each with: problem, solution, tools, estimated ROI

SEO target: "startup automation", "AI for startups", "founder productivity"
```

### Pillar 3: AI Agents (2 posts)

**Post B5: "Building My First AI Agent (Mistakes + Learnings)"**
```
Category: AI Agents
Tags: Claude API, agentic, autonomous, AI agents
Content outline:
- What is an AI agent (vs. chatbot vs. automation)
- My first agent: Blog Research Agent
  - Architecture (diagram)
  - How it discovers topics
  - How it evaluates quality
  - How it decides what to write
- Mistakes I made (and how I fixed them)
- Key lesson: Agents need guardrails

SEO target: "build AI agent", "Claude API agent", "agentic AI tutorial"
```

**Post B6: "The Multi-Agent System Behind My Portfolio"**
```
Category: AI Agents
Tags: multi-agent, Claude Code, orchestration
Content outline:
- Why one agent isn't enough
- My multi-agent architecture:
  - Orchestrator agent (coordinator)
  - Research agent (topic discovery)
  - Writer agent (content creation)
  - Review agent (quality check)
- How they communicate
- Real results (blog posts per week, quality metrics)
- Future: Adding more specialized agents

SEO target: "multi-agent system", "AI agent architecture", "autonomous agents"
```

### Pillar 4: AI Image & Video (2 posts)

**Post B7: "From Text Prompt to Cinematic Video in 30 Minutes"**
```
Category: AI Image & Video
Tags: VEO, video generation, AI video, prompts
Content outline:
- The old way: $10k+ video production
- The new way: AI-generated videos
- My workflow:
  1. Script writing (Claude)
  2. Scene breakdown
  3. Image generation (Gemini/NB2)
  4. Video generation (VEO)
  5. Editing & polish
- Example: [Real video you made]
- Cost: $0-50 vs $10k+ traditional

SEO target: "AI video generation", "VEO tutorial", "AI video production"
```

**Post B8: "AI Image Generation for Product Marketing (No Designer Needed)"**
```
Category: AI Image & Video
Tags: Gemini, image generation, marketing, product photos
Content outline:
- Problem: Startups can't afford photographers
- Solution: AI-generated product images
- My process:
  1. Product description → prompt
  2. Style reference selection
  3. Generation + iteration
  4. Post-processing
- Examples: Before/after (stock vs AI-generated)
- Results: 100 images in 2 hours vs 2 weeks traditional

SEO target: "AI product images", "AI marketing images", "Gemini image generation"
```

**Verification:**
- [ ] 8 blog posts written (EN)
- [ ] 8 translations (ID)
- [ ] Each post has: meta_title, meta_description, og_image, tags
- [ ] Each post categorized correctly
- [ ] SEO score > 70 for each post
- [ ] Posts visible on blog page
- [ ] Blog is searchable by category

**Commit after each post pair:** `content: add [pillar] anchor posts`

---

## Phase C: Project Case Studies — 6 Projects (1 week)

**Estimated time:** 8 hours

**Goal:** Add/update 6 projects that clearly demonstrate AI expertise with business impact.

### Case Study Format

```
Title: [Project Name]
Subtitle: [One-line impact statement]

Problem:
What was the challenge? (2-3 sentences)

Solution:
What did I build? (2-3 sentences + architecture diagram)

Tech Stack:
[List of AI tools, frameworks, APIs used]

Results:
- Metric 1: [Before → After]
- Metric 2: [ROI or time saved]
- Metric 3: [Scale achieved]

Timeline: Shipped in [X days/weeks]

Pillar: [Vibe Coding / AI Automation / AI Agents / AI Video]
```

### 6 Case Studies to Create

**C1: This Portfolio (Vibe Coding)**
```
Title: "AI-Powered Portfolio Platform"
Problem: Traditional portfolios take months to build
Solution: Built full-stack platform (Laravel + Vue + SSR) using Vibe Coding approach
Tech: Claude Code, Laravel 12, Vue 3, Nuxt 3, MySQL, Tailwind
Results:
- Shipped in 4 weeks (vs 3-6 months traditional)
- 120+ API endpoints
- LLM-friendly (discoverable by ChatGPT/Claude)
- Multi-language (EN/ID)
Timeline: 4 weeks
Pillar: Vibe Coding
```

**C2: Automated Blog Pipeline (AI Automation)**
```
Title: "AI Content Pipeline That Writes 100 Articles/Month"
Problem: Content creation is expensive and slow
Solution: End-to-end automation: topic discovery → writing → image gen → publishing
Tech: Claude API, n8n, GeminiGen, Laravel, TanStack Query
Results:
- 100 articles/month (automated)
- $0 content cost (vs $5k+/month freelancers)
- 80% less manual work
Timeline: 3 weeks
Pillar: AI Automation
```

**C3: Multi-Agent Research System (AI Agents)**
```
Title: "Autonomous Research Agent System"
Problem: Manual topic research takes 10+ hours/week
Solution: Multi-agent system that discovers, evaluates, and queues topics autonomously
Tech: Claude API, Custom agents, WebSearch, n8n webhooks
Results:
- 3x/day topic discovery (automated)
- Quality scoring (AI evaluates relevance)
- Zero manual intervention
Timeline: 2 weeks
Pillar: AI Agents
```

**C4: AI Video Production Tool (AI Video/Image)**
```
Title: "AI Video Production Pipeline"
Problem: Professional video production costs $10k+ per video
Solution: AI-powered pipeline: script → scene → image → video generation
Tech: VEO API, Gemini, NB2, Claude (scripting)
Results:
- Video in 30 minutes (vs weeks traditional)
- Cost: $0-50 per video (vs $10k+)
- Cinematic quality output
Timeline: 2 weeks
Pillar: AI Video
```

**C5: AI Carousel Generator (AI Image)**
```
Title: "AI Social Media Carousel Generator"
Problem: Creating carousel content is tedious and repetitive
Solution: Prompt-to-carousel pipeline with localization support
Tech: AI image prompts, NB2, multi-language templates
Results:
- 10 carousels/hour (automated)
- Multi-language (EN/ID)
- Consistent brand quality
Timeline: 1 week
Pillar: AI Image & Video
```

**C6: GEO-Optimized Portfolio (Vibe Coding + AI)**
```
Title: "LLM-Friendly Portfolio (GEO Score 9.5/10)"
Problem: Traditional SPAs invisible to AI search engines
Solution: Nuxt 3 SSR + llms.txt + JSON-LD + ChatGPT plugin
Tech: Nuxt 3, SSR, JSON-LD, OpenAPI, llms.txt
Results:
- GEO score: 7.5 → 9.5/10
- Discoverable by ChatGPT, Gemini, Claude
- Full content indexable (no JS required)
Timeline: 6 weeks
Pillar: Vibe Coding
```

**Verification:**
- [ ] 6 projects created with case study format
- [ ] Each tagged to correct pillar
- [ ] Each has: problem, solution, results, timeline
- [ ] Featured images for each project
- [ ] SEO fields filled (meta_title, description, og_image)
- [ ] Projects visible on portfolio page

**Commit:** `content: add 6 AI case study projects`

---

## Phase D: Homepage Messaging Update (1 day)

**Estimated time:** 3 hours

**Goal:** Update homepage copy to reflect AI Solopreneur Studio positioning.

### D1: Update Hero Section

**Current:** Generic hero with video  
**New messaging:**
```
Headline: "AI Solopreneur Studio"
Subhead: "I build AI products and help founders ship 10x faster"
CTA primary: "See What I'm Building" → scrolls to products
CTA secondary: "Let's Collaborate" → calendar link
```

Update via: Admin panel → Page Sections → hero section fields

### D2: Update Skill Sections (Already Exist)

**Current sections (from Home.vue):**
- Vibe Coding ✅ (keep, update copy)
- AI Automation ✅ (keep, update copy)
- AI Agents ✅ (keep, update copy)
- AI Video Generation ✅ (keep, update copy)

**Updated copy for each:**

**Vibe Coding:**
```
Title: "Vibe Coding"
Subtitle: "Ship Products 10x Faster"
Description: "I use AI to compress months of development into days.
Claude Code, Cursor, and automation handle 80% of the work.
I focus on the 20% that matters: architecture, UX, and business logic."
```

**AI Automation:**
```
Title: "AI Automation"
Subtitle: "Zero Manual Work"
Description: "Every repetitive task in my business is automated.
Content pipeline, lead generation, reporting, deployment —
all handled by AI + n8n workflows running 24/7."
```

**AI Agents:**
```
Title: "AI Agents"
Subtitle: "Autonomous Task Execution"
Description: "I build AI agents that think, decide, and act independently.
From research agents that discover topics to writer agents
that produce publication-ready articles — no human intervention needed."
```

**AI Video Generation:**
```
Title: "AI Video Generation"
Subtitle: "From Prompt to Film"
Description: "Professional video production used to cost $10k+.
Now I generate cinematic content in 30 minutes using VEO, Gemini,
and custom prompt engineering. The future of content is AI-generated."
```

### D3: Add "How We Work Together" Section (New)

Add via Page Sections or new component:
```
Section: "How We Work Together"
Content:
"I partner with founders & startups who:
✓ Are moving fast and need AI expertise
✓ Have interesting problems worth solving
✓ Value speed and quality over process

We can work together through:
→ AI product consulting (strategy + hands-on)
→ Custom AI tool development (built for your needs)
→ Licensing my tools & templates (ready-to-use)

If you're building something interesting,
let's talk about how AI can accelerate it."

CTA: "Schedule a Conversation" → Calendly/Cal.com link
```

### D4: Update About Page

Update via admin panel (Settings → About):
```
Bio: "I'm Ali Sadikin — AI Solopreneur.

I went solo when I discovered AI could amplify individual work 10x.
Instead of working in teams of 20, I now ship products that used to
require entire departments.

My focus areas:
• Vibe Coding — shipping AI products 10x faster
• AI Automation — eliminating manual work with intelligent workflows
• AI Agents — building autonomous systems that think and act
• AI Video/Image — generating cinematic content from text prompts

I'm currently building AI SaaS tools and sharing
everything I learn through my blog and courses.

If you're a founder building with AI, let's connect."

Location: Indonesia (remote, US timezone compatible)
```

**Verification:**
- [ ] Hero copy updated (visible on homepage)
- [ ] 4 skill sections updated with new copy
- [ ] "How We Work Together" section visible
- [ ] About page updated with origin story
- [ ] All CTAs point to calendar link (not generic contact form)
- [ ] No "hire me" or "looking for work" language anywhere

**Commit:** `content: update homepage & about messaging for AI Solopreneur positioning`

---

## Phase E: Social Proof & Credibility (3 days)

**Estimated time:** 6 hours

### E1: Gallery — AI-Generated Work Samples

Add 10-20 AI-generated images/videos to gallery:
- AI-generated product shots
- VEO video screenshots
- Carousel examples
- Before/after comparisons

### E2: Awards & Achievements

Add relevant certifications/achievements:
- Outskill Fellowship (Demo Day Champion)
- Any AI certifications
- Notable projects completed
- Community contributions

### E3: Testimonials

Collect/add 3-5 testimonials:
- From people you've helped with AI
- From course/template buyers
- From collaboration partners

**Verification:**
- [ ] Gallery has 10+ AI-generated work samples
- [ ] Awards page updated
- [ ] 3+ testimonials added
- [ ] All with proper images/attribution

**Commit:** `content: add social proof (gallery, awards, testimonials)`

---

## Phase F: SEO & LLM Optimization (1 day)

**Estimated time:** 3 hours

### F1: Update llms.txt

```
# Ali Sadikin Ma — AI Solopreneur

## About
AI Solopreneur building products that help founders ship 10x faster.
Expert in Vibe Coding, AI Automation, AI Agents, and AI Video/Image Generation.

## Expertise
- Vibe Coding: Ship AI products in days using Claude Code, Cursor, and AI-first workflows
- AI Automation: n8n, Make.com, Zapier workflows for zero-manual-work operations
- AI Agents: Autonomous multi-agent systems using Claude API
- AI Video/Image: VEO, Gemini, GeminiGen for cinematic content generation

## Products
- [Product 1]: [description]
- [Product 2]: [description]

## Blog
Latest insights on building with AI:
- [Top 5 post titles with URLs]

## Portfolio
- https://alisadikinma.com/projects
- https://alisadikinma.com/blog

## Contact
- Website: https://alisadikinma.com
- Email: ali.sadikincom85@gmail.com
- Twitter: [handle]
- LinkedIn: [URL]
- GitHub: [URL]
```

### F2: Update robots.txt

Ensure AI crawlers can access all content pages.

### F3: Update JSON-LD Schemas

Add Person schema to homepage:
```json
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Ali Sadikin Ma",
  "jobTitle": "AI Solopreneur",
  "description": "Building AI products that help founders ship 10x faster",
  "knowsAbout": ["Vibe Coding", "AI Automation", "AI Agents", "AI Video Generation"],
  "url": "https://alisadikinma.com",
  "sameAs": ["[twitter]", "[linkedin]", "[github]"]
}
```

**Verification:**
- [ ] llms.txt updated with solopreneur positioning
- [ ] robots.txt allows AI crawlers
- [ ] Person JSON-LD on homepage
- [ ] BlogPosting JSON-LD on each blog post
- [ ] CreativeWork JSON-LD on each project

**Commit:** `seo: update llms.txt and JSON-LD for AI Solopreneur positioning`

---

## Phase G: Content Calendar Setup (Ongoing)

**Estimated time:** 1 hour setup, then ongoing

### Monthly Content Cadence

```
Week 1: Vibe Coding post (EN + ID)
Week 2: AI Automation post (EN + ID)
Week 3: AI Agents post (EN + ID)
Week 4: AI Image/Video post (EN + ID)
```

### Social Distribution

Each blog post → distribute to:
- Twitter thread (key insights)
- LinkedIn article (cross-post)
- Instagram carousel (AI-generated visuals)

### Quarterly Goals

**Q2 2026 (Apr-Jun):**
- 8 anchor posts live (Phase B)
- 6 case studies live (Phase C)
- Homepage messaging updated (Phase D)
- SSR live (discoverable)

**Q3 2026 (Jul-Sep):**
- 12 more blog posts (3/month)
- First product beta launch
- Twitter: 1k followers
- First inbound partnership inquiry

**Q4 2026 (Oct-Dec):**
- 24+ total blog posts
- Product launch
- Course launch
- Twitter: 5k followers
- 2-3 premium opportunities (inbound)

---

## Summary

| Phase | What | Time | Priority |
|---|---|---|---|
| A | Blog categories | 1 day | P0 |
| B | 8 anchor blog posts | 2 weeks | P0 |
| C | 6 project case studies | 1 week | P0 |
| D | Homepage messaging | 1 day | P1 |
| E | Social proof | 3 days | P1 |
| F | SEO/LLM optimization | 1 day | P1 |
| G | Content calendar | Ongoing | P2 |

**Total initial effort:** 4-5 weeks (after SSR is live)
**Ongoing effort:** 2-4 hours/week (blogging + social)

---

**Plan created by:** Claude Code + gaspol-brainstorm + gaspol-plan  
**Prerequisites:** Nuxt 3 SSR must be live first  
**Last updated:** April 11, 2026
