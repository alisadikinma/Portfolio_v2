> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Replace the cramped Article Preview modal (ContentEngine.vue:392-421) with a full-page preview that opens in a new browser tab. The new page provides multi-language tabs (EN/ID), editable title with live SEO analysis, movable image placement markers, a slide-in SEO panel, and a sticky score bar. Also update the backend simulation command and plugin SKILL.md to output the new nested-by-language data format with SEO fields.

## Architecture Context

**From CLAUDE.md:**
- Admin routes use `requiresAuth: true, layout: 'admin'` — pattern at `router/index.js:142-150`
- Content Engine route exists at `router/index.js:411-421`
- `useContentEngine.js` composable has 16 API methods — add `getIdea(id)` for single-idea fetch
- Backend `ContentIdeaController.php` has no `show($id)` method — need to add it
- Backend route group at `routes/api.php:424-445` — add `GET /ideas/{id}`
- `generated_article` is a `json` column on `content_ideas` table — no migration needed
- Tailwind CSS v4 with `@import "tailwindcss"` in `style.css` — `@tailwindcss/typography` NOT installed
- `prose dark:prose-invert` classes used in `ContentEngine.vue:407`, `ProjectDetail.vue:269`, `About.vue`
- `useToast.js` composable for notifications (used in ContentEngine.vue)
- Admin panel uses neutral `bg-white dark:bg-neutral-800` palette (NOT the ULTRA dark cinema theme)

## Tech Stack

- **Frontend:** Vue 3.5 (`<script setup>`), Tailwind CSS 4, Vue Router 4.5
- **Backend:** Laravel 12, PHP 8.2 (`D:\xampp\php\php.exe`)
- **Packages to add:** `@tailwindcss/typography` (npm)
- **Existing composables:** `useContentEngine.js`, `useToast.js`
- **Existing pattern:** Admin views in `src/views/admin/`, lazy-loaded routes

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Idea data (full) | `ContentIdea` model | `GET /admin/content-engine/ideas/{id}` | **No** | Add show() to controller + route + composable |
| Article title/content | `generated_article.en.title`, `.id.title` | From idea data | **No** (flat format) | Restructure in SimulateArticleGeneration.php |
| Quality/Virality scores | `generated_article.quality_score` | From idea data | **Yes** | Use directly |
| Framework/Hook/Arc | `generated_article.framework`, `.hook_type`, `.emotional_arc` | From idea data | **Yes** | Use directly |
| Sources | `generated_article.sources[]` | From idea data | **Yes** | Use directly |
| Image prompts | `generated_article.image_prompts[]` | From idea data | **Yes** | Add `suggested_position` field |
| Target keyword | `generated_article.target_keyword` | From idea data | **No** | Add to plugin output + simulation |
| SEO analysis | Client-side computation | Computed in component | **No** | Create `computeSeoAnalysis()` utility |
| Approve article | `ContentIdeaController::approveArticle()` | `POST /ideas/{id}/approve-article` | **Yes** | Update to accept per-language edited data |
| Revert to draft | `useContentEngine().revertToDraft(id)` | `POST /ideas/{id}/revert` | **Yes** | Use directly |
| Toast notifications | `useToast()` | Composable | **Yes** | Use directly |
| Prose typography | `@tailwindcss/typography` | CSS plugin | **No** | Install + configure |

---

## Phase 1: Install Tailwind Typography Plugin

**Estimated time:** 3 minutes

**Files:**
- Modify: `frontend/package.json` (npm install)
- Modify: `frontend/src/style.css` (add `@plugin`)

**Steps:**
1. Run `cd d:/Projects/Portfolio_v2/frontend && npm install @tailwindcss/typography`
2. Add `@plugin "@tailwindcss/typography";` after line 1 (`@import "tailwindcss";`) in `frontend/src/style.css`
3. Verify dev server starts without errors: `npm run dev`

**Verification:**
- [ ] `@tailwindcss/typography` appears in `package.json` dependencies
- [ ] `prose` classes now render styled typography (check existing pages: ProjectDetail.vue)
- [ ] No build errors

---

## Phase 2: Backend — Add Show Endpoint + Update Data Format

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (add `show()`)
- Modify: `backend/routes/api.php` (add `GET /ideas/{id}`)
- Modify: `backend/app/Console/Commands/SimulateArticleGeneration.php` (nested format)

**Steps:**

### 2A: Add show() method to ContentIdeaController

Add after the `store()` method (around line 100):

```php
/**
 * Show a single content idea by ID.
 */
public function show($id): JsonResponse
{
    $idea = ContentIdea::find($id);
    if (!$idea) {
        return response()->json(['success' => false, 'message' => 'Content idea not found.'], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $idea,
    ]);
}
```

### 2B: Add route

In `routes/api.php`, add after line 426 (`Route::post('/ideas', ...)`):
```php
Route::get('/ideas/{id}', [ContentIdeaController::class, 'show']);
```

### 2C: Update SimulateArticleGeneration.php

Replace the flat `generated_article` array (lines 69-93) with nested-by-language format:

```php
'generated_article' => [
    'en' => [
        'title' => $this->generateOptimalTitle($idea->title, 'en'),
        'content' => $this->generateSampleArticle($idea->title, 'en'),
        'word_count' => 2150,
    ],
    'id' => [
        'title' => $this->generateOptimalTitle($idea->title, 'id'),
        'content' => $this->generateSampleArticle($idea->title, 'id'),
        'word_count' => 2280,
    ],
    'quality_score' => 8,
    'virality_score' => 4,
    'framework' => 'PASO',
    'hook_type' => 'Story',
    'emotional_arc' => 'Discovery',
    'target_keyword' => $this->extractKeyword($idea->title),
    'image_prompts' => [
        [
            'concept' => 'Hero visual representing the core theme',
            'prompt' => 'A cinematic wide shot of a futuristic workspace with holographic AI interfaces...',
            'model' => 'nano-banana-pro',
            'style' => 'Cinematic',
            'aspect_ratio' => '16:9',
            'resolution' => '2K',
            'placement' => 'Article header / social share thumbnail',
            'suggested_position' => 0,
        ],
        [
            'concept' => 'Data visualization showing key statistics',
            'prompt' => 'A clean minimal dashboard showing AI adoption statistics...',
            'model' => 'nano-banana-pro',
            'style' => 'Minimal',
            'aspect_ratio' => '4:3',
            'resolution' => '2K',
            'placement' => 'After statistics section',
            'suggested_position' => 4,
        ],
    ],
    'sources' => [
        ['title' => 'McKinsey Global AI Survey 2025', 'url' => 'https://mckinsey.com/ai-survey-2025'],
        ['title' => 'Stanford HAI AI Index Report', 'url' => 'https://aiindex.stanford.edu/report'],
    ],
],
```

Add helper methods:

```php
private function generateOptimalTitle(string $original, string $lang): string
{
    // Simulate a 50-60 char optimized title
    if ($lang === 'id') {
        return '7 Cara AI Mengubah Bisnis dalam 30 Hari ke Depan';
    }
    // Truncate/rephrase to optimal length
    $words = explode(' ', $original);
    $short = implode(' ', array_slice($words, 0, 9));
    return strlen($short) > 60 ? substr($short, 0, 57) . '...' : $short;
}

private function extractKeyword(string $title): string
{
    // Simple keyword extraction from title
    $lower = strtolower($title);
    foreach (['ai strategy', 'artificial intelligence', 'machine learning', 'ai'] as $kw) {
        if (str_contains($lower, $kw)) return $kw;
    }
    $words = explode(' ', $title);
    return strtolower(implode(' ', array_slice($words, 0, 2)));
}
```

Also update `generateSampleArticle()` to accept `$lang` parameter with Indonesian content variant.

### 2D: Update approveArticle()

In `ContentIdeaController::approveArticle()` (line 341), the existing code already accepts full `generated_article` JSON from the request body. No changes needed — the preview page will send the entire updated `generated_article` object (with edited titles per language + updated image positions).

**Verification:**
- [ ] `GET /api/admin/content-engine/ideas/1` returns idea JSON with `generated_article`
- [ ] Running `php artisan article:simulate {id}` produces nested-by-language format
- [ ] `approveArticle()` still works when receiving the updated nested JSON

---

## Phase 3: Frontend — Add Route + Composable Method

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/router/index.js` (add preview route)
- Modify: `frontend/src/composables/useContentEngine.js` (add `getIdea()`)

**Steps:**

### 3A: Add route to router/index.js

Add after line 421 (the content-engine route), BEFORE the catch-all route:

```js
{
  path: '/admin/content-engine/:id/preview',
  name: 'admin-content-engine-preview',
  component: () => import('@/views/admin/ArticlePreview.vue'),
  meta: {
    title: 'Article Preview - Admin',
    requiresAuth: true,
    layout: 'admin'
  }
},
```

### 3B: Add getIdea() to useContentEngine.js

Add after `listIdeas` (line 42):

```js
const getIdea = (id) => request('get', `/admin/content-engine/ideas/${id}`)
```

And add `getIdea` to the return object (line 97).

**Verification:**
- [ ] Route `/admin/content-engine/1/preview` resolves (component may not exist yet — 404 is OK)
- [ ] `getIdea(1)` returns idea data from API

---

## Phase 4: Frontend — Create ArticlePreview.vue (Core Structure)

**Estimated time:** 15 minutes

**Files:**
- Create: `frontend/src/views/admin/ArticlePreview.vue`

**Steps:**

### 4A: Create the component with all sections

Build `ArticlePreview.vue` with `<script setup>` containing:

1. **Data loading:** Use `useContentEngine().getIdea(route.params.id)` in `onMounted`
2. **Backward compat helper:**
   ```js
   function getArticleContent(article, lang) {
     if (article?.[lang]) return { title: article[lang].title, content: article[lang].content, wordCount: article[lang].word_count }
     if (article?.title) return { title: article.title, content: article.content, wordCount: article.word_count }
     return { title: '', content: '', wordCount: 0 }
   }
   ```
3. **Reactive state:**
   - `idea` (ref) — the loaded idea
   - `activeLang` (ref, default `'en'`)
   - `editedTitles` (ref, `{ en: '', id: '' }`) — editable per language
   - `targetKeyword` (ref) — editable keyword
   - `showSeoPanel` (ref, default `false`)
   - `imageMarkers` (ref) — array of image prompts with current positions
4. **Computed:**
   - `article` — `idea.generated_article`
   - `currentContent` — content for active language tab
   - `seoAnalysis` — computed from `computeSeoAnalysis()`
   - `contentBlocks` — HTML split into paragraphs with image markers inserted
5. **Methods:**
   - `handleApprove()` — sends updated `generated_article` (with edited titles + image positions) via `approveArticle()`
   - `handleRevert()` — calls `revertToDraft()` and closes tab
   - `moveMarker(index, direction)` — moves image marker up/down
   - `removeMarker(index)` — removes image marker

### 4B: Template layout

```
<div class="min-h-screen bg-white dark:bg-neutral-900">
  <!-- Sticky Top Bar -->
  <!-- Language Tabs -->
  <!-- Title Editor with char counter -->
  <!-- Article Body with image markers -->
  <!-- SEO Slide Panel -->
  <!-- Bottom Action Bar -->
</div>
```

### 4C: SEO Analysis utility

Add `computeSeoAnalysis()` function inside the component (or as a local function):

```js
function computeSeoAnalysis(content, title, keyword) {
  if (!keyword || !content) return null
  const text = content.replace(/<[^>]*>/g, '')
  const words = text.split(/\s+/).filter(Boolean)
  const kw = keyword.toLowerCase()
  const kwRegex = new RegExp(kw, 'gi')

  const bodyMatches = text.match(kwRegex) || []
  const density = words.length ? (bodyMatches.length / words.length) * 100 : 0

  const inTitle = title.toLowerCase().includes(kw)
  const first100 = words.slice(0, 100).join(' ').toLowerCase()
  const inFirst100 = first100.includes(kw)

  const headingRegex = /<h[23][^>]*>(.*?)<\/h[23]>/gi
  let headingCount = 0
  let m
  while ((m = headingRegex.exec(content)) !== null) {
    if (m[1].toLowerCase().includes(kw)) headingCount++
  }

  return {
    density: { value: +density.toFixed(1), status: seoStatus(density, 0.5, 1.5, 0.3, 2.5) },
    inTitle: { value: inTitle, status: inTitle ? 'good' : 'bad' },
    inFirst100: { value: inFirst100, status: inFirst100 ? 'good' : 'bad' },
    inHeadings: { value: headingCount, status: seoStatus(headingCount, 1, 2, 0, 4) },
    titleLength: { value: title.length, status: seoStatus(title.length, 50, 60, 40, 70) },
    titleWords: { value: title.split(/\s+/).length, status: seoStatus(title.split(/\s+/).length, 6, 10, 5, 12) },
    totalWords: words.length,
    keywordCount: bodyMatches.length,
  }
}

function seoStatus(val, goodMin, goodMax, warnMin, warnMax) {
  if (val >= goodMin && val <= goodMax) return 'good'
  if (val >= warnMin && val <= warnMax) return 'warn'
  return 'bad'
}
```

### 4D: Content blocks with image markers

Split HTML content into top-level blocks and interleave image markers:

```js
const contentBlocks = computed(() => {
  const html = currentContent.value?.content || ''
  // Split at top-level block boundaries (p, h2, h3, ol, ul, blockquote)
  const parser = new DOMParser()
  const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html')
  const children = Array.from(doc.body.firstChild.children)

  const blocks = children.map((el, i) => ({
    index: i,
    html: el.outerHTML,
  }))

  return blocks
})
```

Image markers are rendered between blocks based on `imageMarkers[n].position`.

### 4E: Styling

- Top bar: `sticky top-0 z-40 bg-white dark:bg-neutral-800 border-b shadow-sm`
- Language tabs: amber/gold underline for active, neutral for inactive
- Title input: `text-2xl font-bold w-full bg-transparent border-b-2 focus:border-amber-500`
- Article body: `prose dark:prose-invert prose-lg max-w-3xl mx-auto`
- Image markers: `border-2 border-dashed border-amber-300 dark:border-amber-600 bg-amber-50/50 dark:bg-amber-900/20 rounded-lg p-4`
- SEO panel: `fixed right-0 top-0 h-full w-80 bg-white dark:bg-neutral-800 shadow-xl z-50 transform translate-x-full transition-transform` (open: `translate-x-0`)
- Score badges: green (`bg-green-100 text-green-700`), amber (`bg-amber-100 text-amber-700`), red (`bg-red-100 text-red-700`)
- Bottom bar: `sticky bottom-0 bg-white dark:bg-neutral-800 border-t px-6 py-4`

**Verification:**
- [ ] Page loads at `/admin/content-engine/{id}/preview` with idea data
- [ ] Language tabs switch between EN/ID content
- [ ] Title is editable with live char counter and color coding
- [ ] Article content renders with proper prose typography
- [ ] Image markers appear at suggested positions with move/remove controls
- [ ] SEO panel slides in/out
- [ ] Score badges show correct colors
- [ ] Approve button sends updated data
- [ ] Revert button works and closes tab
- [ ] Backward compatible: flat `generated_article` format still renders (English only)

---

## Phase 5: Frontend — Update ContentEngine.vue (Remove Modal, Add Link)

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/views/admin/ContentEngine.vue`

**Steps:**

### 5A: Replace modal open with window.open()

In `openResearchModal()` (line 827), when the idea status is `article_ready`, open in new tab instead of showing modal:

```js
async function openResearchModal(idea) {
  if (idea.status === 'article_ready') {
    // Open full-page preview in new tab
    const url = `/admin/content-engine/${idea.id}/preview`
    window.open(url, '_blank')
    return
  }
  // ... rest of existing code for research data loading
}
```

### 5B: Remove old Article Preview modal

Delete lines 392-421 (the `<!-- Article Preview Modal -->` section). This removes:
- The `v-if="showResearchModal && currentIdea?.status === 'article_ready'"` modal
- The title display, v-html content, and action buttons

### 5C: Update handleApproveArticle to handle redirect from preview

The approve flow now happens in `ArticlePreview.vue`, not in `ContentEngine.vue`. The existing `handleApproveArticle()` function (line 857) is still needed for the image config modal flow but won't be triggered from the removed modal. Keep it as-is since `ArticlePreview.vue` calls `approveArticle()` directly via `useContentEngine()`.

**Verification:**
- [ ] Clicking "Preview" on an `article_ready` idea opens a new browser tab
- [ ] Old modal code is removed — no `Article Preview` modal appears
- [ ] Research modal still works for non-article_ready statuses
- [ ] Image config modal still opens correctly after approval (from preview page)

---

## Phase 6: Plugin — Update SKILL.md Output Format + SEO Rules

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-gen\SKILL.md`

**Steps:**

### 6A: Update completion callback format

Replace the flat `generated_article` JSON in the completion callback section (SKILL.md lines 128-145) with the nested-by-language format:

```json
{
  "generated_article": {
    "en": {
      "title": "{english_title}",
      "content": "{english_html_content}",
      "word_count": "{english_word_count}"
    },
    "id": {
      "title": "{indonesian_title}",
      "content": "{indonesian_html_content}",
      "word_count": "{indonesian_word_count}"
    },
    "quality_score": "{quality_score}",
    "virality_score": "{virality_score}",
    "framework": "{framework_name}",
    "hook_type": "{hook_type}",
    "emotional_arc": "{arc_type}",
    "target_keyword": "{primary_keyword}",
    "image_prompts": [
      {
        "concept": "{description}",
        "prompt": "{full_prompt}",
        "model": "nano-banana-pro",
        "style": "{style}",
        "aspect_ratio": "{ratio}",
        "resolution": "2K",
        "placement": "{human_readable_placement}",
        "suggested_position": "{paragraph_index}"
      }
    ],
    "sources": [{"title": "...", "url": "..."}]
  }
}
```

### 6B: Add SEO Rules section

Add a new section after the Step-to-percentage mapping (around line 119):

```markdown
### SEO Optimization Rules (MANDATORY)

**Title Rules:**
- Length MUST be 50-60 characters (6-10 words)
- Primary keyword MUST appear in the title
- Use proven formulas: "How to [X] in [Y]", "N Ways to [X]", "[Number] + [Value] + [Unique Angle]"
- Odd numbers (7, 9, 11) outperform even numbers in titles

**Keyword Density Rules:**
- Identify and output the `target_keyword` in the article metadata
- Primary keyword MUST appear in the first 100 words
- Primary keyword MUST appear in 1-2 H2/H3 headings
- Body keyword density: 0.5-1.5% (roughly 1 mention per 400-500 words)
- Never exceed 3% density (triggers over-optimization penalties)
- Use semantic variants and related terms throughout — not just exact-match repetition

**Image Position Rules:**
- Each `image_prompt` MUST include a `suggested_position` field (0-based paragraph index)
- Position 0 = before first paragraph (hero image)
- Position -1 = end of article
- Hero image is always position 0
- Body images should be placed after key data points or section transitions
```

### 6C: Update the final output description

Update the existing output description (line 583) to mention the new fields:

```
The `generated_article` JSON must include per-language content nested as `{ en: { title, content, word_count }, id: { title, content, word_count } }`, plus shared fields: `quality_score`, `virality_score`, `framework`, `hook_type`, `emotional_arc`, `target_keyword`, `image_prompts` (array with `suggested_position`), and `sources`.
```

**Verification:**
- [ ] SKILL.md completion callback shows nested-by-language format
- [ ] SEO rules section is present with title length, keyword density, image position rules
- [ ] `target_keyword` and `suggested_position` fields documented
- [ ] No references to old flat format remain in the output specification

---

## Phase Summary

| Phase | Description | Est. Time | Dependencies |
|-------|-------------|-----------|--------------|
| 1 | Install Tailwind Typography | 3 min | None |
| 2 | Backend: show endpoint + nested data format | 10 min | None |
| 3 | Frontend: route + composable method | 5 min | Phase 2 |
| 4 | Frontend: ArticlePreview.vue (full component) | 15 min | Phase 1, 3 |
| 5 | Frontend: Update ContentEngine.vue | 5 min | Phase 4 |
| 6 | Plugin: Update SKILL.md | 8 min | None |
| **Total** | | **~46 min** | |

**Parallel-eligible phases:** Phase 1 + 2 + 6 can run in parallel (no dependencies). Phase 3 depends on Phase 2. Phase 4 depends on 1 + 3. Phase 5 depends on 4.

---

## Design Reference

Full design spec: `docs/plans/2026-04-13-article-preview-redesign.md`
