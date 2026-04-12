> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plugin is brand-agnostic. Brand identity, API config, and audience
> are read from PROJECT config (`.claude/article-writer.md`), NOT from plugin references.
> Plugin references contain ONLY universal writing knowledge.

## Goal

Build a Claude Code plugin (`article-content-writer`) that generates viral, engaging long-form blog articles using proven copywriting frameworks (Slippery Slide, Zeigarnik Effect, PASO, StoryBrand). Plugin is brand-agnostic — reads brand/API config from the project that uses it.

## Architecture

```
Plugin (universal engine):
  article-content-writer/
  ├── skills/         ← 3 skills (generate, research, validate)
  ├── agents/         ← 1 subagent
  ├── hooks/          ← session startup
  └── references/     ← writing knowledge ONLY (no brand/API)

Per-project config:
  YOUR_PROJECT/.claude/article-writer.md  ← brand, audience, API, tokens
```

## Tech Stack

- Claude Code Plugin system (`.claude-plugin/plugin.json`)
- Skills: SKILL.md frontmatter + markdown instructions
- Hooks: `hooks.json` + `session-start.sh`
- References: markdown knowledge files
- API integration: WebFetch tool (agent calls Portfolio_v2 API)

---

## Source Files to Copy

| Source | Destination | Action |
|--------|------------|--------|
| `content-engine-agents/knowledge/sparkfluence/topic-hook-mapping.md` | `references/content-strategy/hook-library.md` | Copy + rename |
| `content-engine-agents/knowledge/sparkfluence/retention-curve-model.md` | `references/content-strategy/retention-model.md` | Copy + rename |
| `content-engine-agents/knowledge/sparkfluence/emotion-arc-patterns.md` | `references/content-strategy/emotion-arcs.md` | Copy + rename |
| `content-engine-agents/knowledge/sparkfluence/transcreation-rules.md` | `references/content-strategy/transcreation-rules.md` | Copy |
| `ai-image-carousel-prompt-gen/references/prompt-formulas.md` | `references/image-generation/prompt-formulas.md` | Copy |
| `ai-image-carousel-prompt-gen/references/cinematography-lut.md` | `references/image-generation/cinematography-lut.md` | Copy |
| `content-engine-agents/knowledge/merged/scoring-gates.md` | `references/scoring-gates.md` | Copy + update min to 9/10 |
| NotebookLM output (already saved) | `references/writing-framework.md` | Already done ✅ |

## Files to Create from Scratch

| File | Purpose |
|------|---------|
| `references/image-generation/blog-image-specs.md` | Blog-specific aspect ratios (16:9 hero, 4:3 body) |
| `references/seo-geo-optimization.md` | SEO + GEO (LLM-friendly) writing rules |
| `templates/article-writer-config.md` | Template for per-project brand config |

---

## Phase A: Scaffold Plugin Structure (5 min)

**Estimated time:** 5 minutes

**Files:**
- Create: `D:\Projects\claude-plugin\article-content-writer\.claude-plugin\plugin.json`
- Create: `D:\Projects\claude-plugin\article-content-writer\CLAUDE.md`
- Create: `D:\Projects\claude-plugin\article-content-writer\README.md`
- Create: `D:\Projects\claude-plugin\article-content-writer\.gitignore`
- Create: `D:\Projects\claude-plugin\article-content-writer\hooks\hooks.json`
- Create: `D:\Projects\claude-plugin\article-content-writer\hooks\session-start.sh`

**Steps:**

1. Create `plugin.json`:
   ```json
   {
     "name": "article-content-writer",
     "description": "AI-powered long-form blog article generator using proven copywriting frameworks (Slippery Slide, Zeigarnik Effect, PASO, StoryBrand). Brand-agnostic — reads config from project.",
     "version": "1.0.0",
     "author": { "name": "Ali Sadikin", "url": "https://github.com/alisadikinma" },
     "repository": "https://github.com/alisadikinma/article-content-writer",
     "license": "MIT",
     "keywords": ["article", "blog", "copywriting", "content-writer", "seo", "viral", "long-form", "claude-code"]
   }
   ```

2. Create `hooks/hooks.json`:
   ```json
   {
     "hooks": {
       "SessionStart": [{
         "matcher": "startup|resume|clear|compact",
         "hooks": [{ "type": "command", "command": "${CLAUDE_PLUGIN_ROOT}/hooks/session-start.sh" }]
       }]
     }
   }
   ```

3. Create `hooks/session-start.sh`:
   ```bash
   #!/bin/bash
   echo "SessionStart:startup hook success: article-content-writer loaded. Skills available:"
   echo "  article-generate  — full pipeline: research → write → image prompts → save"
   echo "  article-research  — research topic only (trending score, hooks, angles)"
   echo "  article-validate  — quality gate checker (min 9/10)"
   echo ""
   echo "Agent available:"
   echo "  article-writer-agent — subagent for batch article work"
   echo ""
   echo "REMINDER: Read .claude/article-writer.md from PROJECT ROOT for brand config."
   ```

4. Create `CLAUDE.md` with plugin overview, architecture, and setup instructions

**Verification:**
- [ ] Plugin directory structure created
- [ ] `plugin.json` has correct metadata
- [ ] `hooks.json` registered SessionStart hook
- [ ] `session-start.sh` is executable

**Commit:** `scaffold: initialize article-content-writer plugin`

---

## Phase B: Copy Reference Files (5 min)

**Estimated time:** 5 minutes

**Steps:**

1. Copy content-strategy files (rename from sparkfluence):
   ```bash
   mkdir -p references/content-strategy
   cp content-engine-agents/knowledge/sparkfluence/topic-hook-mapping.md → references/content-strategy/hook-library.md
   cp content-engine-agents/knowledge/sparkfluence/retention-curve-model.md → references/content-strategy/retention-model.md
   cp content-engine-agents/knowledge/sparkfluence/emotion-arc-patterns.md → references/content-strategy/emotion-arcs.md
   cp content-engine-agents/knowledge/sparkfluence/transcreation-rules.md → references/content-strategy/transcreation-rules.md
   ```

2. Copy image-generation files:
   ```bash
   mkdir -p references/image-generation
   cp ai-image-carousel-prompt-gen/references/prompt-formulas.md → references/image-generation/prompt-formulas.md
   cp ai-image-carousel-prompt-gen/references/cinematography-lut.md → references/image-generation/cinematography-lut.md
   ```

3. Copy scoring-gates:
   ```bash
   cp content-engine-agents/knowledge/merged/scoring-gates.md → references/scoring-gates.md
   ```
   Then edit: change minimum score from 7/10 to **9/10**

4. Verify `references/writing-framework.md` exists (already saved from NotebookLM)

**Verification:**
- [ ] 7 files copied to correct locations
- [ ] Filenames renamed (no "sparkfluence" naming)
- [ ] scoring-gates.md updated to 9/10 minimum
- [ ] writing-framework.md present

**Commit:** `feat: add reference knowledge files (content-strategy, image-gen, scoring)`

---

## Phase C: Create New Reference Files (15 min)

**Estimated time:** 15 minutes

**Files:**
- Create: `references/image-generation/blog-image-specs.md`
- Create: `references/seo-geo-optimization.md`
- Create: `templates/article-writer-config.md`

**Steps:**

1. Create `blog-image-specs.md`:
   - Hero image: 16:9, 1200x675px min, cinematic wide shot
   - Body images: 4:3 or 16:9, 800x600px min, illustrative
   - Prompt structure: same 8-element from prompt-formulas.md
   - Context: article illustration (NOT social media stop-scroll)
   - Number: 1 hero + 2-3 body images per article
   - Style: match article tone (technical = clean/minimal, story = cinematic/warm)

2. Create `seo-geo-optimization.md`:
   - meta_title: 60 chars max, primary keyword front-loaded
   - meta_description: 155 chars, includes CTA
   - meta_keywords: 5-8 relevant keywords
   - ai_summary: 2-3 sentences for LLM consumption (GEO)
   - H1 → H2 → H3 hierarchy
   - FAQ schema: 2-3 pairs per article
   - Semantic HTML: article, section, time, figure/figcaption
   - Internal linking strategy
   - Canonical URL pattern
   - E-E-A-T signals: data points, citations, author expertise

3. Create `templates/article-writer-config.md`:
   - Template that projects copy to `.claude/article-writer.md`
   - Sections: Brand, Voice, Target Audience, Content Rules, API Config
   - Placeholder values with clear instructions
   - Example filled version for reference

**Verification:**
- [ ] blog-image-specs.md has clear rules for hero + body images
- [ ] seo-geo-optimization.md covers SEO + GEO + schema
- [ ] template has all required fields with placeholder values
- [ ] No hardcoded brand/API info in reference files

**Commit:** `feat: add blog image specs, SEO/GEO rules, and project config template`

---

## Phase D: Create Skills (20 min)

**Estimated time:** 20 minutes

**Files:**
- Create: `skills/article-generate/SKILL.md`
- Create: `skills/article-research/SKILL.md`
- Create: `skills/article-validate/SKILL.md`

**Steps:**

1. Create `article-generate/SKILL.md` — main pipeline skill:

   **Frontmatter:**
   ```yaml
   ---
   name: article-generate
   description: Generate viral long-form blog articles using proven copywriting frameworks. Supports on-demand (specific topic) and auto mode (pending ideas + trending). Brand-agnostic — reads config from .claude/article-writer.md.
   ---
   ```

   **Skill body must include:**
   - MANDATORY: Read `.claude/article-writer.md` FIRST (brand config)
   - Two modes: `--auto` (check pending, fallback trending) and topic arg
   - 9-step pipeline:
     1. Read project config (brand, API, audience)
     2. Topic analysis (hook-library.md → select hook type)
     3. Emotion arc selection (emotion-arcs.md → select arc)
     4. Retention curve planning (retention-model.md → map sections)
     5. Write article EN (writing-framework.md → PASO/StoryBrand/AIDA)
     6. Transcreate to ID (transcreation-rules.md)
     7. Image prompts (prompt-formulas.md + blog-image-specs.md)
     8. SEO optimization (seo-geo-optimization.md)
     9. Quality gate (scoring-gates.md → min 9/10, REWRITE if below)
   - Save to API (from project config endpoints)
   - Reference file read order table

2. Create `article-research/SKILL.md` — research only:
   - Read project config
   - Analyze topic against hook-library
   - Score trending potential
   - Suggest 3 hooks + 2 angles
   - Recommend emotion arc
   - Output: research report (no article written)

3. Create `article-validate/SKILL.md` — quality checker:
   - Read generated article
   - Score against 10-point checklist from scoring-gates.md
   - Check: word count, readability, hook quality, retention techniques,
     bucket brigades (min 3), open loops, statistics density, SEO fields,
     transcreation quality, image prompt structure
   - Output: score (X/10) + specific issues
   - FAIL if below 9/10

**Verification:**
- [ ] Each SKILL.md has correct frontmatter (name, description)
- [ ] article-generate references ALL knowledge files in correct order
- [ ] article-generate reads `.claude/article-writer.md` FIRST
- [ ] No hardcoded brand/API info in skills
- [ ] Quality gate enforces 9/10 minimum
- [ ] All reference file paths are relative to plugin root

**Commit:** `feat: add 3 skills (article-generate, article-research, article-validate)`

---

## Phase E: Create Agent + Final Docs (10 min)

**Estimated time:** 10 minutes

**Files:**
- Create: `agents/article-writer-agent.md`
- Update: `CLAUDE.md`
- Update: `README.md`

**Steps:**

1. Create `article-writer-agent.md` — subagent for batch work:
   - Agent reads all reference files
   - Accepts topic or auto mode
   - Runs full pipeline
   - Returns article JSON
   - Used by Content Engine for programmatic calls

2. Update `CLAUDE.md` with:
   - Full plugin architecture
   - Reference file index
   - Setup instructions (how to add project config)
   - CLI usage examples

3. Update `README.md` with:
   - Quick start guide
   - Installation: `claude plugin add /path/to/article-content-writer`
   - Project setup: copy template to `.claude/article-writer.md`
   - Usage examples
   - Reference file descriptions

**Verification:**
- [ ] Agent template works with Agent tool
- [ ] CLAUDE.md is comprehensive
- [ ] README has clear setup instructions
- [ ] No placeholder/TODO comments

**Commit:** `feat: add article-writer agent and documentation`

---

## Phase F: Setup Project Configs (5 min)

**Estimated time:** 5 minutes

**Steps:**

1. Copy template to Portfolio_v2:
   ```bash
   cp templates/article-writer-config.md D:\Projects\Portfolio_v2\.claude\article-writer.md
   ```

2. Fill in Portfolio_v2 config:
   - Brand: Ali Sadikin Ma, AI Solopreneur
   - Voice: Confident, founder-to-founder
   - Audience: Startups & Founders
   - API endpoints: Portfolio_v2 URLs
   - Auth token: from .env

3. Initialize git repo for plugin:
   ```bash
   cd D:\Projects\claude-plugin\article-content-writer
   git init && git add . && git commit -m "initial: article-content-writer plugin v1.0.0"
   ```

**Verification:**
- [ ] Portfolio_v2 has `.claude/article-writer.md` with real config
- [ ] Plugin git repo initialized
- [ ] Template values replaced with real data in project config

**Commit (plugin):** `initial: article-content-writer plugin v1.0.0`
**Commit (Portfolio_v2):** `config: add article-writer project config`

---

## Phase G: Local CLI Test (10 min)

**Estimated time:** 10 minutes

**Steps:**

1. Install plugin locally:
   ```bash
   claude plugin add D:\Projects\claude-plugin\article-content-writer
   ```

2. Test article-research skill:
   ```bash
   cd D:\Projects\Portfolio_v2
   claude -p "/article-research 'AI Agents for E-commerce Startups'"
   ```
   Verify: outputs research report with hooks, angles, score

3. Test article-generate skill (dry run):
   ```bash
   claude -p "/article-generate 'How Claude Code CLI Automates Blog Pipelines' --lang en"
   ```
   Verify: generates full article, saves to API (or shows output)

4. Test article-validate skill:
   ```bash
   claude -p "/article-validate"
   ```
   Verify: scores the generated article, reports issues

**Verification:**
- [ ] Plugin loads on session start (hook fires)
- [ ] `/article-research` returns research report
- [ ] `/article-generate` produces full article
- [ ] `/article-validate` scores article (target 9/10)
- [ ] Brand config read from `.claude/article-writer.md`
- [ ] No hardcoded brand in skill output

**Commit:** No commit (testing only)

---

## Phase H: VPS Deployment (5 min)

**Estimated time:** 5 minutes

**Steps:**

1. Push plugin to GitHub:
   ```bash
   cd D:\Projects\claude-plugin\article-content-writer
   gh repo create alisadikinma/article-content-writer --public --push
   ```

2. Install Claude Code CLI on VPS (if not installed):
   ```bash
   ssh claudesn@31.97.188.145
   npm i -g @anthropic-ai/claude-code
   claude login
   ```

3. Install plugin on VPS:
   ```bash
   claude plugin add https://github.com/alisadikinma/article-content-writer
   ```

4. Copy project config to VPS Portfolio_v2:
   ```bash
   scp .claude/article-writer.md claudesn@31.97.188.145:/var/www/Portfolio_v2/.claude/
   ```

5. Test on VPS:
   ```bash
   cd /var/www/Portfolio_v2
   claude -p "/article-generate --auto"
   ```

6. Setup crontab:
   ```bash
   crontab -e
   # Add:
   */30 * * * * cd /var/www/Portfolio_v2 && claude -p "/article-generate --auto" >> /var/log/article-writer.log 2>&1
   ```

**Verification:**
- [ ] Plugin installed on VPS
- [ ] CLI authenticated on VPS
- [ ] `/article-generate --auto` works on VPS
- [ ] Crontab configured (every 30 min)
- [ ] Log file captures output

---

## Summary

| Phase | What | Files | Time |
|-------|------|-------|------|
| A | Scaffold plugin | 6 new | 5 min |
| B | Copy reference files | 7 copy | 5 min |
| C | Create new references | 3 new | 15 min |
| D | Create 3 skills | 3 new | 20 min |
| E | Agent + docs | 3 files | 10 min |
| F | Project configs | 2 files | 5 min |
| G | Local CLI test | 0 | 10 min |
| H | VPS deployment | 0 | 5 min |
| **Total** | | **~24 files** | **~75 min** |

## Key Design Decisions

1. **Brand-agnostic:** Plugin has NO brand/API config. Projects provide their own via `.claude/article-writer.md`
2. **Quality gate 9/10:** Premium content only. Rewrite if below threshold.
3. **Sparkfluence renamed:** Folder = `content-strategy/` (descriptive, not branded)
4. **Image knowledge reused:** Copied from carousel plugin (prompt-formulas + cinematography-lut)
5. **CLI-based triggering:** On-demand + cron, NOT RemoteTrigger
6. **Writing framework from research:** NotebookLM deep research (23 sources → comprehensive framework)
