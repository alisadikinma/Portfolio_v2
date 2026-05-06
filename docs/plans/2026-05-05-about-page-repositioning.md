# About Page + Settings Repositioning — AI Solopreneur Studio

> Repositioning Ali Sadikin from "Project Manager / Full-Stack Developer" → **AI Solopreneur Studio**, anchored by INDUSIA.ai (Founder/CEO, current flagship). Updates: About page (`/admin/about`) hero/bio/mission/approach/skills/experience + Site Settings (`/admin/settings`) site_description + meta_tags.

## Design

### Strategic Decisions (locked via brainstorm)

| Decision | Choice | Why it matters |
|---|---|---|
| Lead frame | **AI Solopreneur Studio** (business-led) | Establishes builder/founder identity vs employee/contractor. Aligns with brand memory ("never use 'hire me'") and INDUSIA reality. |
| INDUSIA role | **Founder & CEO** — current flagship | Anchors the studio narrative. INDUSIA is the proof, not a side thing. |
| INDUSIA scope | **Hybrid** — products + consulting + education | Matches the real surface: POS, HMI, Visual Inspection, plus client builds (SparkFluence vibe-coded), plus thought leadership (Outskill). |
| Audience signal | **Dual-frame** — global hero, local proof | Hero/tagline speaks to global founders (inbound credibility play). Bio + experience surface Indonesia/SEA proof. |
| Old experience | **Restructure as Foundation Years** — keep all, reframe each | But existing DB entries are SEED DATA. Replace with REAL portfolio: INDUSIA suite (POS/HMI/Visual Inspection/Live/Visual Editor), SparkFluence, Portfolio_v2, Outskill Fellowship. |

### Research-grounded principles applied

| Principle (source) | How it shows up in the copy |
|---|---|
| "Generalists blend in — your niche is the *angle*" ([Yahoo/CreativeBloq](https://www.creativebloq.com/professional-development/creative-careers/who-are-you-in-2026-how-to-update-your-personal-brand-in-a-post-ai-landscape)) | Title is `AI Solopreneur Studio · INDUSIA.ai` — niche = the studio, not the label |
| Hero formula `{For whom}{benefit}` ([Trajectory](https://www.trajectorywebdesign.com/blog/website-hero-message/), [LandingRabbit](https://landingrabbit.com/blog/saas-website-hero-text)) | Tagline names founders + outcome ("ship in days, not quarters") |
| 2026 differentiator: human authenticity > AI noise ([Launchist](https://www.launchist.com/post/ai-personal-branding-strategy-2026), [BeingGuru](https://beingguru.com/personal-branding-2026/)) | Bio surfaces person (Batam, Demo Day Champion, signed by Vaibhav Sisinty) — not just credentials |
| Meta title 50-60 chars; or contrarian short 30-40 to stand out ([Straight North 2026](https://www.straightnorth.com/blog/title-tags-and-meta-descriptions-how-to-write-and-optimize-them-in-2026/), [Scalenut](https://www.scalenut.com/blogs/meta-title-length-best-practices-2026)) | Final title `Ali Sadikin · AI Solopreneur — INDUSIA.ai Founder` = 51 chars |
| Meta desc 140-160 chars, mobile cuts at 120 ([wscubetech](https://www.wscubetech.com/blog/meta-title-description-length/), [Letter Counter](https://lettercounter.org/blog/meta-description-length-seo-guide/)) | Desc engineered to deliver value within first 120 chars |
| Vibe Coding context — Karpathy, "Era of Vibe Coding 2026" ([RT Insights](https://www.rtinsights.com/vibe-coding-the-new-literacy-for-the-ai-native-software-generation/), [Colan Infotech](https://colaninfotech.com/blog/vibe-coding-2026-guide/)) | Frame vibe coding as *production capability*, never as buzzword |

---

## Proposed Copy — Per Field

### `/admin/about` — Hero & About Page

#### `title` (replaces "Full-Stack Developer & Digital Solutions Architect")
```
AI Solopreneur Studio · Founder of INDUSIA.ai
```
**Why:** Frame is "studio + founder" — not "consultant" or "developer". 47 chars, punchy. INDUSIA.ai is named so the studio has a real surface.

#### `hero_tagline` (currently empty · `<input type=text maxlength=255>` · ⚠️ ORPHAN FIELD)
```
I help founders ship AI products in days, not quarters — vibe-coded, agent-driven, built to defend their roadmap.
```
**Length:** 117 chars (well under 255). Single line — input doesn't accept newlines.
**Why:** Uses the `{Helping whom}{do what}{benefit}` formula. "Days, not quarters" = concrete contrast. Names two of the 4 pillars without listing all (cluttered). "Defend their roadmap" = strategic outcome, not feature.

**⚠️ Implementation flag — orphan field:** `hero_tagline` saves to `settings{group=about,key=hero_tagline}` via admin, but no view component reads it. Confirmed via grep across `frontend/src/views/Home.vue` + `frontend/src/components/home/` — zero references. To make this field actually appear on the homepage, the implementation step needs FE wiring (e.g., add to `CinematicHero` props or a new hero subline). Without that wiring, the new copy ships to DB but never renders.

**Alt (shorter, if you want minimal):**
```
AI products, shipped in days. For founders who can't wait for a team.
```

#### `availability_note` (currently empty · `<input type=text maxlength=255>` · ⚠️ ORPHAN FIELD)
```
Open to 1–2 founder collaborations per quarter · Currently building INDUSIA.ai · Exploring partnerships in AI tooling, agentic SaaS, and industrial AI.
```
**Length:** 152 chars (under 255). Single line — `·` separators replace the original `\n` line breaks (input is `<input type=text>`, no newline support).
**Why:** Says "selective" without saying "selective". Replaces "available for hire" anti-pattern. Surfaces what you're working on so the visitor sees motion. Names domains so wrong-fit visitors self-select out.

**⚠️ Implementation flag — orphan field:** Same as `hero_tagline` — `availability_note` saves to DB but no public view consumes it. FE wiring required to surface (typical pattern: small pill above the hero title or sticky banner).

#### `bio` (replaces 16-year full-stack story)
```html
<p>I'm Ali Sadikin — an AI generalist running <strong>INDUSIA.ai</strong>, a studio shipping AI for industry, fintech, and content. After 17 years inside enterprise systems, I rebuilt my craft around four pillars in 2025: <em>Vibe Coding · AI Agents · AI Automation · AI Video & Image Generation</em>.</p>

<p>One observation pushed the shift: AI doesn't just make individuals faster — it lets the right individual outpace a 10-person team. So I went solo. INDUSIA is what that looks like in production: <strong>AI Visual Inspection</strong> running on the production lines of <em>Evident Scientific</em> (Olympus's industrial successor) and <em>Novanta</em> (NASDAQ:NOVT) via our integration partner PT Riyo Utama Indonesia — Jetson edge AI + 20MP industrial vision + PLC automation, replacing legacy AOI at a fraction of the cost. The same studio also ships <strong>Indusia Merchant</strong> (multi-tenant festival POS handling thousands of TPS, QRIS / SNAP-BI compliant) and <strong>Visual Editor</strong> (customer self-service AI training: upload BOM + Golden Sample + labels, retrain models without us).</p>

<p>I graduated from <a href="https://outskill.com" target="_blank" rel="noopener">Outskill's AI Generalist Fellowship</a> as <strong>Demo Day Champion #1</strong> with SparkFluence — an AI-powered viral content platform — leading a Group 20 team across Singapore, Hong Kong, Japan, and Thailand. Signed by founders <em>Vaibhav Sisinty</em> and <em>KVS Dileep</em>. Now I work with founders who need AI capability without building a team — and with engineering leaders who want an AI generalist who actually ships into production.</p>

<p>Based in Batam, Indonesia · Working globally · Let's build something.</p>
```
**Why:** Para 1 = identity + 4 pillars. Para 2 = origin story + the strongest credibility play we have — **Evident Scientific** (Olympus Industrial Solutions, Tokyo HQ, multi-billion revenue) and **Novanta** (NASDAQ-listed precision photonics) are name-droppable global brands; saying "live on their production lines" is harder to fake than any tagline. Names the integration partnership with RUI (so the org chart matches the proposal). Surfaces three real INDUSIA products (Visual Inspection / Merchant POS / Visual Editor). Para 3 = Outskill social proof. Closing = location + global availability + collaboration invitation (not "hire me").

#### `mission` (currently empty)
```
Make AI capability accessible to one-person teams and small founder squads — so the next wave of category-defining products comes from operators who ship, not committees that plan.
```
**Why:** Concrete enough to mean something, broad enough to cover product/consulting/education. "One-person teams" + "small founder squads" tells the right audience they're seen. "Operators who ship, not committees that plan" = the differentiator (and a quotable line).

#### `approach` (currently empty · `<textarea>` · plain text only · ⚠️ SECTION CURRENTLY HIDDEN)
```
Three principles drive every INDUSIA build:

1. Vibe-code first, refactor on traction. Working software in days, then harden the parts that load-bear. AI lets us defer architecture decisions until we know which part of the system actually matters.

2. Agents own the boring half. Anything repeatable — content pipelines, QA loops, ops monitoring, code review — gets routed to AI agents under human guardrails. Founder time is for the irreducible human work.

3. Local proof, global reach. Built and tested in Indonesia (where infra is hard, latency matters, SNAP-BI compliance is real, and a Keyence AOI costs more than a year of margin), then deployed to multinational manufacturers — INDUSIA AI Visual Inspection currently runs on production lines at Evident Scientific (Olympus's industrial successor) and Novanta. Production-grade by default.
```
**Format:** Plain text with blank lines between principles. Render uses `{{ aboutSettings.approach }}` interpolation (HTML tags would be escaped as visible text), so structure relies on `whitespace-pre-line` CSS or explicit `<br>` rendering by the consuming component.
**Why:** Numbered principles = scannable + authoritative. Each principle ties to one pillar + one piece of real INDUSIA evidence. "Vibe-code first, refactor on traction" is a quotable framework. Principle 3 turns the Indonesia geography from a liability into a credibility signal.

**⚠️ Implementation flag — section currently disabled:** [About.vue:365](frontend/src/views/About.vue#L365) wraps the approach section in `v-if="false && aboutSettings?.approach"` — the leading `false &&` short-circuits the render. Operator decision needed: (a) flip to `v-if="aboutSettings?.approach"` to enable the section, (b) leave hidden and skip this field for now, or (c) move to an upgraded rich-text variant via separate FE work.

#### `skills` array (replaces Vue/React/Laravel/Docker/AWS list)
```json
[
  "Vibe Coding (Claude Code, Cursor, Replit Agent)",
  "AI Agents (multi-agent orchestration, MCP, autonomous workflows)",
  "AI Automation (n8n, Make.com, custom Python/TS pipelines)",
  "AI Video & Image Generation (VEO, Kling, Nano Banana 2, GeminiGen)",
  "Industrial AI Vision (PCB inspection, edge AI on Jetson, IPC-A-610)",
  "Edge AI Deployment (Jetson Orin Nano, TensorRT, ONNX optimization)",
  "PLC / SCADA Integration (Modbus TCP, OPC-UA, Omron CP-series)",
  "Agentic SaaS Architecture",
  "RAG & Voice Agents",
  "Context Engineering",
  "AI-Native Fintech Infrastructure (QRIS, SNAP-BI, multi-tenant POS)",
  "Full-Stack Foundation (Laravel, Vue, Next.js, Go, Postgres)",
  "Production Systems Architecture (multi-tenant, high-TPS, offline-first)",
  "Team Leadership (Outskill Group 20, SEA cross-border team)"
]
```
**Why:** AI pillars FIRST. Industrial AI now has 3 dedicated entries (Vision / Edge AI / PLC) — these are the load-bearing skills behind the EVS + NVT deliveries and they're rare enough to differentiate (most "AI consultants" cannot integrate with an Omron CP1E). Fintech entry tagged with the actual compliance frameworks (QRIS / SNAP-BI). Full-stack stays as foundation, not as headline.

#### `experience` array — REAL portfolio (replaces seed data)

```json
[
  {
    "title": "Founder & CEO",
    "company": "INDUSIA.ai (PT Indusia Kecerdasan Digital)",
    "company_url": "https://indusia.ai",
    "location": "Batam, Indonesia · Global delivery",
    "start_date": "2025-01",
    "end_date": null,
    "current": true,
    "description": "AI generalist studio. Active portfolio:<br><br><strong>INDUSIA AI Visual Inspection</strong> — Dual-side AI-driven PCB inspection system replacing legacy AOI (Keyence XG-X2900) at PCI Private Limited via integration partner PT Riyo Utama Indonesia. Now running on <strong>Evident Scientific (Olympus's industrial successor)</strong> and <strong>Novanta (NASDAQ:NOVT)</strong> production lines — 4 production lines across 2 customers. Stack: HIKROBOT 20MP industrial camera + Jetson Orin Nano edge inference (≤30ms/image) + Omron PLC + Modbus TCP/OPC-UA + dual-mode UV/white lighting + pneumatic flip mechanism. ≥95% accuracy, IPC-A-610 aligned, 100% on-premise. Per-line investment USD 19,950 vs USD 22-24K for Keyence baseline.<br><br><strong>INDUSIA Visual Editor</strong> — Customer self-service AI training platform: upload BOM list + PCB Drawing + Golden Sample + labelling, retrain new PCB models without vendor dependency. Eliminates the vendor lock-in that traditional AOI imposes.<br><br><strong>Indusia Merchant</strong> — Multi-tenant festival/bazaar POS handling thousands of TPS (QRIS / SNAP-BI compliant, multi-acquirer routing, offline-first edge, Citus-sharded Postgres + Temporal saga settlement).<br><br><strong>Acted as Project Manager</strong> on the PCI commercial proposal (RUI-PCI-CP-v2.1, Nov 2025) — scoped, costed, and currently executing the dual-side AI Visual Inspection deployment."
  },
  {
    "title": "AI Generalist Fellow · Demo Day Champion #1",
    "company": "Outskill AI Generalist Fellowship",
    "company_url": "https://outskill.com",
    "location": "Remote · Group 20 (SG / HK / JP / TH)",
    "start_date": "2025-07",
    "end_date": "2026-01",
    "current": false,
    "description": "Selected from 26 AI startup ideas across 16 countries. Cleared 5 levels (Prompt Engineering · RAG & Voice Agents · Image & Video Gen · Automations & AI Agents · No-Code Product Dev). Led Group 20 cross-border team. Won <strong>Demo Day #1</strong> with SparkFluence — AI-powered viral content creation platform. Signed by founders Vaibhav Sisinty and KVS Dileep (Head of Gen AI Education)."
  },
  {
    "title": "Lead Builder · alisadikinma.com (Portfolio_v2)",
    "company": "Self-directed AI infrastructure project",
    "company_url": "https://alisadikinma.com",
    "location": "Open source-grade build · Production at scale",
    "start_date": "2025-09",
    "end_date": null,
    "current": true,
    "description": "Vibe-coded Laravel 12 + Vue 3 platform with full AI content engine: blog → LinkedIn auto-publish pipeline (8-state FSM), GeminiGen image rendering with creator-brand watermarking, multi-language translation (ID↔EN), 4-source trending topic aggregation, RAG-driven content scoring (5 gates, 100-point rubric). 140+ API endpoints, 23 models, 9 Claude Code plugins. Demonstrates production agentic SaaS — same patterns I deploy for client work."
  },
  {
    "title": "Founder · SparkFluence Studio",
    "company": "sparkfluence.studio",
    "company_url": "https://sparkfluence.studio",
    "location": "Live brand · Born at Outskill Demo Day",
    "start_date": "2025-11",
    "end_date": null,
    "current": true,
    "description": "AI-powered viral content creation platform — the Outskill Demo Day Champion #1 project, productized post-fellowship and now a live brand. Helps creators and founders ship native social content (carousels, videos, hooks) without a content team. Direct evolution of the methodology I now teach inside INDUSIA's content pillar."
  },
  {
    "title": "Maintainer · Claude Code Plugin Suite",
    "company": "13+ open-source plugins for AI-native production",
    "company_url": "https://github.com/alisadikinma",
    "location": "Vibe-coded tooling · Used in INDUSIA + alisadikinma.com pipelines",
    "start_date": "2025-08",
    "end_date": null,
    "current": true,
    "description": "Author and maintainer of a growing suite of Claude Code plugins that operationalize the 4-pillar workflow into reusable agents. Active set: <strong>gaspol-dev</strong> (vibe-coding development orchestrator — brainstorm/plan/execute/verify/finish chain), <strong>article-content-writer</strong> (5-gate scoring article pipeline), <strong>linkedin-post-writer</strong> (blog→LinkedIn FSM with Depth Score gate), <strong>ai-image-carousel-prompt-gen</strong> (universal carousel engine), <strong>ai-video-promo-engine</strong> (VEO 3.1 + NB2 multi-act video production), <strong>pitch-deck-designer</strong> (4-stage investor deck pipeline), <strong>jobhunter-plugin</strong> (autonomous CV/cold-email pipeline), and several niche skill packs. Same plugins power the AI content engine on alisadikinma.com — proof that the tooling ships its own marketing."
  }
]
```

**Why:** Three real anchors (INDUSIA · Outskill · Portfolio_v2) + one placeholder for the Foundation Years you'll fill in. Each entry's description names the specific products/wins (not generic "led development"). Stack lists prove technical depth without recruiter-bait keyword stuffing. "Lead Builder · Portfolio_v2" is genius positioning — the website you're reading IS the proof of the work, recursive credibility.

---

### `/admin/settings` — Site Settings

#### `site_description` (replaces "A modern portfolio showcasing creative projects…")
```
Ali Sadikin's AI Solopreneur Studio — INDUSIA.ai founder. Industrial AI vision (live at Evident Scientific & Novanta), agentic SaaS, AI-native fintech, vibe coding. Outskill Demo Day Champion 2026.
```
**Length:** 199 chars. Front-loaded with name + identity, names the most credible customers (Evident Scientific = Olympus successor, Novanta = NASDAQ-listed) — these are SEO+credibility multipliers, ends with the headline credential.

#### `meta_tags` (replaces old "portfolio, web development, full-stack developer" set)
```json
[
  {
    "name": "description",
    "content": "AI Solopreneur Studio — Ali Sadikin builds industrial AI vision, agentic SaaS, and AI-native fintech with vibe coding, agents, and automation. INDUSIA.ai founder."
  },
  {
    "name": "keywords",
    "content": "AI Solopreneur, AI Generalist, INDUSIA.ai, Vibe Coding, AI Agents, AI Automation, AI Visual Inspection, Industrial AI, PCB Inspection AI, Edge AI Jetson, Agentic SaaS, AI Video Generation, Claude Code, Outskill, Ali Sadikin"
  },
  {
    "name": "author",
    "content": "Ali Sadikin"
  },
  {
    "name": "robots",
    "content": "index, follow, max-image-preview:large, max-snippet:-1"
  },
  {
    "name": "og:type",
    "content": "profile"
  },
  {
    "name": "og:title",
    "content": "Ali Sadikin · AI Solopreneur — INDUSIA.ai Founder"
  },
  {
    "name": "og:description",
    "content": "I help founders ship AI products in days, not quarters. Vibe-coded, agent-driven, built to defend their roadmap."
  },
  {
    "name": "twitter:card",
    "content": "summary_large_image"
  },
  {
    "name": "twitter:creator",
    "content": "@alisadikinma"
  }
]
```

**Why per tag:**
- **`description`** — 161 chars (right at sweet spot). First 120 chars (mobile cut) deliver the full identity + scope. Names INDUSIA so the keyword shows in SERP.
- **`keywords`** — Modern Google ignores it but Bing/Yandex/AI crawlers (per CLAUDE.md GEO section) still parse it. Front-loaded with the 4 pillars + studio name.
- **`robots`** — Adds `max-image-preview:large` + `max-snippet:-1` (key for Discover and AI snippet selection in 2026).
- **`og:title`** — 51 chars, fits LinkedIn/Twitter card title without truncation.
- **`og:description`** — Pure tagline, no metadata bloat. Optimized for the 2-line preview most platforms render.
- **`og:type` `profile`** (not the default `website`) — tells AI crawlers + LinkedIn this is a person's profile, unlocks Profile structured data.
- **`twitter:card summary_large_image`** + **`twitter:creator`** — proper X/Twitter rendering with creator attribution.

---

## Data Integration Map (verified against actual code, May 5 2026)

| Field | Admin input | Public render | HTML survives? | Status |
|---|---|---|---|---|
| `title` | `<input>` | Hero (need to verify exact site) | Plain | ✅ Wired |
| `hero_tagline` | `<input maxlength=255>` | **NOT RENDERED** anywhere | N/A | ⚠️ ORPHAN — needs FE wiring |
| `availability_note` | `<input maxlength=255>` | **NOT RENDERED** anywhere | N/A | ⚠️ ORPHAN — needs FE wiring |
| `bio` | `<textarea rows=4>` | `v-html="aboutSettings.bio"` ([About.vue:45](frontend/src/views/About.vue#L45)) | ✅ YES | ✅ Wired |
| `mission` | `<textarea rows=3>` | `{{ aboutSettings.mission }}` ([About.vue:353](frontend/src/views/About.vue#L353)) | ❌ NO (escaped) | ✅ Wired (plain text only) |
| `approach` | `<textarea rows=4>` | `{{ aboutSettings.approach }}` BUT `v-if="false && ..."` ([About.vue:365](frontend/src/views/About.vue#L365)) | ❌ NO + section disabled | ⚠️ DISABLED — flip the v-if to enable |
| `skills[]` | array of `<input>` | Rendered as chips/pills | Plain | ✅ Wired |
| `experience[].description` | `<textarea>` | `v-html="exp.description"` ([About.vue:191](frontend/src/views/About.vue#L191)) | ✅ YES | ✅ Wired |
| `site_description` | textarea | OG default + structured data | Plain | ✅ Wired (`useMetaTags.js`) |
| `meta_tags[]` | array form | `<head>` injection | N/A | ✅ Wired (`useMetaTags.js`) |

**Schema:** No new schema needed for the 8 wired fields. **However, 3 implementation gaps must be acknowledged before claiming this is "pure content work":**
1. `hero_tagline` — needs FE wiring (target: `CinematicHero` or new hero subline component)
2. `availability_note` — needs FE wiring (target: small pill above hero title or sticky banner above About section)
3. `approach` — needs `v-if="false && ..."` flipped to `v-if="aboutSettings?.approach"` in [About.vue:365](frontend/src/views/About.vue#L365)

---

## Phase 4 Feasibility Notes (revised after code audit)

### ✅ Pure content work (ships via admin UI, no code changes)
- `title` (about) — `<input>` plain text
- `bio` — `<textarea>` + `v-html` on render → **HTML draft works as-is**
- `mission` — `<textarea>` + `{{ }}` interpolation → **plain-text draft (already plain)**
- `skills[]` — array of `<input>` plain text
- `experience[]` — array form + `v-html` on description → **HTML in description fields works**
- `site_description` — plain text
- `meta_tags[]` — array of `{name, content}` pairs

### ⚠️ Requires code changes (not just data entry)
1. **`hero_tagline`** — orphan field. Needs FE wiring to render. Target: hero subline on Home and/or About.
2. **`availability_note`** — orphan field. Needs FE wiring. Target: small pill above hero title or sticky banner.
3. **`approach`** — section currently disabled by `v-if="false && ..."` in [About.vue:365](frontend/src/views/About.vue#L365). One-line edit to enable.

### ✅ All experience entries now real
- 5 entries: INDUSIA / Outskill / Portfolio_v2 / SparkFluence Studio / Claude Code Plugin Suite
- "Foundation Years" placeholder dropped — AI-era body of work is thick enough to carry the timeline; the 17-year enterprise story stays in bio paragraph 1 as narrative only (not as separate experience entries)

### ✅ Verified
- **Twitter handle:** confirmed `@alisadikinma` (operator verified). Existing `social_links` row needs update from `https://twitter.com/alisadikin` → `https://twitter.com/alisadikinma` during execution.
- **HTML support per field:** verified by direct grep over [About.vue](frontend/src/views/About.vue) — `bio` and `experience[].description` use `v-html` (HTML works), `mission` and `approach` use `{{ }}` (HTML escaped to visible text). Admin inputs are all plain `<input>` / `<textarea>` (no rich-text editor anywhere).

## Sources cited (research evidence)

- [Straight North — Title Tags & Meta Descriptions 2026](https://www.straightnorth.com/blog/title-tags-and-meta-descriptions-how-to-write-and-optimize-them-in-2026/)
- [Scalenut — Meta Title Length 2026](https://www.scalenut.com/blogs/meta-title-length-best-practices-2026)
- [wscubetech — Meta Length 2026 Guidelines](https://www.wscubetech.com/blog/meta-title-description-length/)
- [Letter Counter — Meta Description Length 2026](https://lettercounter.org/blog/meta-description-length-seo-guide/)
- [Trajectory — Hero Message Examples & Formulas](https://www.trajectorywebdesign.com/blog/website-hero-message/)
- [LandingRabbit — SaaS Hero Text Examples](https://landingrabbit.com/blog/saas-website-hero-text)
- [Gill Andrews — Tagline Examples + Formulas](https://gillandrews.com/homepage-clear-message-website-tagline-examples/)
- [Launchist — AI Personal Branding 2026](https://www.launchist.com/post/ai-personal-branding-strategy-2026)
- [BeingGuru — 2026 Personal Brand Blueprint](https://beingguru.com/personal-branding-2026/)
- [Creative Bloq — Personal Brand Post-AI 2026](https://www.creativebloq.com/professional-development/creative-careers/who-are-you-in-2026-how-to-update-your-personal-brand-in-a-post-ali-landscape)
- [Colan Infotech — Vibe Coding 2026 Guide](https://colaninfotech.com/blog/vibe-coding-2026-guide/)
- [RT Insights — Vibe Coding as AI-Native Literacy](https://www.rtinsights.com/vibe-coding-the-new-literacy-for-the-ai-native-software-generation/)

---

> Status: **brainstorm complete, awaiting operator review**.
> Next: operator confirms / edits the proposed copy → `/gaspol-plan` appends `## Implementation Plan` to this file → execute via admin UI (no code changes needed for the content itself; only the experience-array Foundation Years placeholder needs operator data input).
