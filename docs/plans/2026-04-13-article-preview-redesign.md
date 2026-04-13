# Article Preview Redesign — Full-Page with SEO Intelligence

**Date:** April 13, 2026
**Status:** Design Approved
**Scope:** Frontend (new page), Backend (data format), Plugin (SEO rules)

---

## Problem Statement

The current Article Preview modal in ContentEngine.vue has critical UX issues:

1. **Cramped modal** — 2,000+ word articles shoved into a `max-w-4xl` modal with `text-sm` font
2. **No multi-language support** — Config modal lets users select EN/ID, but preview only shows one flat article
3. **Poor readability** — Raw HTML dump via `v-html`, dense text, no visual hierarchy
4. **No SEO analysis** — Title length, keyword density, keyword placement not visible
5. **No image placement** — `image_prompts` exist in data but not shown/positionable in preview
6. **Title too long** — No enforcement of optimal 50-60 char title length

## Solution

Replace the Article Preview modal with a **full-page view** that opens in a **new browser tab**. The page provides:

- Full-width, beautifully typeset article content
- Language tabs (EN/ID) for multi-language review
- Editable title with live SEO feedback
- Movable image placement markers
- Slide-in SEO analysis panel
- Sticky score bar + action buttons

## Architecture

### Route
```
/admin/content-engine/:id/preview
```
Opens via `window.open()` from ContentEngine.vue — new tab, keeps list open.

### Component
```
src/views/admin/ArticlePreview.vue
```
Full-page view within AdminLayout.

---

## Layout Design

### Sticky Top Bar
Horizontal strip, always visible:
```
← Back to Content Engine  |  QS 8/10 · VS 4/5 · 2,150w · PASO  |  [SEO] [Approve]
```
- "Back" navigates to `/admin/content-engine` (or closes tab)
- Score badges: color-coded (green/amber/red based on thresholds)
- `[SEO]` button toggles the slide-in SEO panel
- `[Approve]` triggers approve action

### Language Tabs
```
[🇺🇸 English]  [🇮🇩 Indonesia]
```
- Flag-only labels (per existing convention in the project)
- Gold underline on active tab
- "(missing)" badge if that language wasn't generated
- Tabs determined by `currentIdea.languages` config

### Title Editor
- Full-width `<input>` or `<textarea>` (auto-resize for long titles)
- `text-2xl font-bold` styling
- Live character counter: `42/60 chars`
- Color coding: green (50-60), amber (40-50 or 60-70), red (<40 or >70)
- Keyword-in-title indicator: checkmark or warning

### Article Body
- Centered column: `max-w-3xl mx-auto`
- Typography: `text-base` (16px), `leading-relaxed` (1.625 line-height)
- Tailwind Typography plugin (`prose dark:prose-invert`) with customizations:
  - H2: `text-xl font-bold`, 2rem top margin
  - H3: `text-lg font-semibold`, 1.5rem top margin
  - Paragraphs: 1.25rem bottom margin
  - Blockquotes: gold left border, italic
  - Lists: 0.5rem gap between items
  - Links: cyan colored
- Content rendered via `v-html` with proper prose classes

### Image Markers
Injected between paragraphs at plugin-suggested positions:
```
┌── 📷 Image Placement ──────────────────────────────────┐
│  Hero: "Futuristic workspace with holographic AI..."    │
│  16:9 · Cinematic · nano-banana-pro      [▲] [▼] [✕]   │
└─────────────────────────────────────────────────────────┘
```
- Dashed border, subtle background
- Arrow buttons move marker up/down between paragraphs
- X button removes the marker (image won't be generated for that slot)
- Shows prompt concept, aspect ratio, style, model

### SEO Slide Panel
Right-side drawer, toggleable via top bar button:

**Contents:**
1. **Target Keyword** — editable input field
2. **Placement Checks:**
   - In title: yes/no (with count)
   - In first 100 words: yes/no
   - In H2/H3 headings: count
3. **Keyword Density** — percentage with visual bar
   - Green: 0.5-1.5%
   - Amber: 0.3-0.5% or 1.5-2.5%
   - Red: <0.3% or >3%
4. **Title Length** — chars with optimal range indicator
5. **Sources** — clickable list from `generated_article.sources[]`
6. **Image Prompts Summary** — count + list of concepts

Width: ~320px, glass backdrop, slide animation from right.

### Bottom Action Bar
Sticky at viewport bottom:
```
[← Back to Draft]                    [Approve Text & Continue to Images →]
```

---

## Data Structure Changes

### `generated_article` — New Nested Format

```json
{
  "en": {
    "title": "7 Fastest Ways to Align Your AI Strategy in 30 Days",
    "content": "<h2>...</h2><p>...</p>",
    "word_count": 2150
  },
  "id": {
    "title": "7 Cara Tercepat Menyelaraskan Strategi AI Anda dalam 30 Hari",
    "content": "<h2>...</h2><p>...</p>",
    "word_count": 2280
  },
  "quality_score": 8,
  "virality_score": 4,
  "framework": "PASO",
  "hook_type": "Story",
  "emotional_arc": "Discovery",
  "target_keyword": "AI strategy",
  "image_prompts": [
    {
      "concept": "Hero visual representing the core theme",
      "prompt": "A cinematic wide shot of...",
      "model": "nano-banana-pro",
      "style": "Cinematic",
      "aspect_ratio": "16:9",
      "resolution": "2K",
      "placement": "Article header / social share thumbnail",
      "suggested_position": 0
    },
    {
      "concept": "Data visualization dashboard",
      "prompt": "A clean, minimal dashboard...",
      "model": "nano-banana-pro",
      "style": "Minimal",
      "aspect_ratio": "4:3",
      "resolution": "2K",
      "placement": "After statistics section",
      "suggested_position": 3
    }
  ],
  "sources": [
    { "title": "McKinsey Global AI Survey 2025", "url": "https://..." },
    { "title": "Stanford HAI AI Index Report", "url": "https://..." }
  ]
}
```

### Backward Compatibility

Preview component detects format:
```js
function getArticleContent(article, lang) {
  // New nested format
  if (article[lang]) return { title: article[lang].title, content: article[lang].content }
  // Legacy flat format (treat as English)
  if (article.title) return { title: article.title, content: article.content }
  return { title: '', content: '' }
}
```

---

## SEO Rules Engine

### Title Optimization (enforced in plugin + displayed in preview)

| Metric | Optimal (Green) | Warning (Amber) | Danger (Red) |
|--------|-----------------|------------------|--------------|
| Title length | 50-60 chars | 40-50 or 60-70 | <40 or >70 |
| Keyword in title | Present | — | Missing |
| Word count (title) | 6-10 words | 5 or 11-12 | <5 or >12 |

### Keyword Density (enforced in plugin + displayed in preview)

| Metric | Optimal (Green) | Warning (Amber) | Danger (Red) |
|--------|-----------------|------------------|--------------|
| Body density | 0.5-1.5% | 0.3-0.5% or 1.5-2.5% | <0.3% or >3% |
| In first 100 words | Present | — | Missing |
| In H2/H3 headings | 1-2 times | 0 | >3 (stuffing) |

### Client-Side SEO Analysis Function

```js
function computeSeoAnalysis(content, title, keyword) {
  const textContent = stripHtml(content)
  const words = textContent.split(/\s+/)
  const totalWords = words.length
  const keywordLower = keyword.toLowerCase()

  // Keyword density
  const keywordRegex = new RegExp(keywordLower, 'gi')
  const bodyMatches = textContent.match(keywordRegex) || []
  const density = (bodyMatches.length / totalWords) * 100

  // In title
  const inTitle = title.toLowerCase().includes(keywordLower)

  // In first 100 words
  const first100 = words.slice(0, 100).join(' ').toLowerCase()
  const inFirst100 = first100.includes(keywordLower)

  // In headings
  const headingRegex = /<h[23][^>]*>(.*?)<\/h[23]>/gi
  let headingMatches = 0
  let match
  while ((match = headingRegex.exec(content)) !== null) {
    if (match[1].toLowerCase().includes(keywordLower)) headingMatches++
  }

  // Title length
  const titleLength = title.length
  const titleWords = title.split(/\s+/).length

  return {
    density: { value: density.toFixed(1), status: getStatus(density, 0.5, 1.5, 0.3, 2.5) },
    inTitle: { value: inTitle, status: inTitle ? 'good' : 'bad' },
    inFirst100: { value: inFirst100, status: inFirst100 ? 'good' : 'bad' },
    inHeadings: { value: headingMatches, status: getStatus(headingMatches, 1, 2, 0, 3) },
    titleLength: { value: titleLength, status: getStatus(titleLength, 50, 60, 40, 70) },
    titleWords: { value: titleWords, status: getStatus(titleWords, 6, 10, 5, 12) },
    totalWords,
    keywordCount: bodyMatches.length
  }
}
```

---

## Image Marker System

### Plugin Output
Each `image_prompt` includes `suggested_position` (paragraph index, 0-based):
- `0` = before first paragraph (hero position)
- `3` = after the 3rd paragraph
- `-1` = end of article

### Preview Behavior
1. Parse HTML content into paragraph blocks
2. Inject image markers at `suggested_position` indices
3. Render markers as styled cards with move/remove controls
4. On approve, save final positions back to `generated_article.image_prompts[].position`

### Move Controls
- `[▲]` — Move marker one paragraph up
- `[▼]` — Move marker one paragraph down
- `[✕]` — Remove marker (image won't be placed)

---

## Changes Required

### 1. Frontend (Vue)

| File | Action | Description |
|------|--------|-------------|
| `views/admin/ArticlePreview.vue` | **CREATE** | Full-page article preview with all components |
| `router/index.js` | **EDIT** | Add `/admin/content-engine/:id/preview` route |
| `views/admin/ContentEngine.vue` | **EDIT** | Replace modal with `window.open()` to preview page |
| `composables/useContentEngine.js` | **EDIT** | Add `fetchIdea(id)` for single idea fetch if not exists |

### 2. Backend (Laravel)

| File | Action | Description |
|------|--------|-------------|
| `SimulateArticleGeneration.php` | **EDIT** | Update sample data to nested-by-language format |
| `ContentIdeaController.php` | **EDIT** | Accept per-language title updates in `approveArticle()` |

### 3. Plugin (article-content-writer)

| File | Action | Description |
|------|--------|-------------|
| `skills/article-gen/SKILL.md` | **EDIT** | Add SEO rules, nested output format, `target_keyword`, `suggested_position` |

### 4. No Migration Needed
`generated_article` is already a `json` column — schema change is purely in data format.

---

## Score Thresholds

### Quality Score (0-10)
- Green: 7-10
- Amber: 5-6
- Red: 0-4

### Virality Score (0-5)
- Green: 4-5
- Amber: 3
- Red: 0-2

---

## Design Decisions

1. **Full page over modal** — 2,000+ word articles need breathing room
2. **New tab** — Admin keeps Content Engine list open, reviews article in parallel
3. **Nested-by-language** — Cleaner than flat, scales to more languages, shared metadata at root
4. **Client-side SEO** — Instant feedback without API calls, keyword analysis computed from content
5. **Auto-suggest + adjust** — Plugin suggests image positions, admin fine-tunes in preview
6. **Editable + advisory** — Show warnings but let admin override; edit title/keyword directly
7. **Backward compatible** — Detects flat vs nested format, handles both gracefully
