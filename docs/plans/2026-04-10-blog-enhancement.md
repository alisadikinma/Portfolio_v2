> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Enhance the Portfolio blog with two capabilities: (1) multi-language support connecting existing backend translations to frontend, and (2) automated article generation pipeline via Claude Cloud Scheduled Tasks using the HOOK-FORESHADOW-BODY-PEAK-CTA framework.

**Key architecture decision:** Claude Cloud Scheduled Task (FREE with Max subscription) handles ALL intelligence — article generation, image generation (via GeminiGen API), trend discovery. VPS only stores the final result via existing `POST /api/admin/posts` endpoint. No new backend services needed.

## Architecture Context

**From CLAUDE.md:**
- Backend: Laravel 12 + MySQL 8, API at `http://localhost/Portfolio_v2/backend/public/api`
- Frontend: Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + TanStack Query 5.90 + Tailwind 4
- Blog views: `Blog.vue`, `BlogDetail.vue`, `BlogCategory.vue`
- Blog composable: `usePosts.js` — has `selectedLang` state + `fetchPost(slug, lang='en')` but views don't pass lang
- i18n: `vue-i18n` configured with `en`/`id` locales, saved to localStorage
- Post model: `translations()` hasMany, `translation($lang)` helper method
- PostTranslation: title, slug, excerpt, content, meta_title, meta_description, meta_keywords, og_title, og_description, canonical_url, ai_summary, schema_markup, faq_schema
- PostController: `index()` and `show()` already extract `lang` from `?lang=` query param or `Accept-Language` header
- API client (`api.js`): already sets `Accept-Language` header
- SitemapController: exists but no hreflang
- Design system: Dark Cinema + Gold #D4A843 + Cyan #06B6D4, Space Grotesk + Inter
- RAG knowledge: `docs/rag/blog-article-writing.md`, `docs/rag/ai-image-generation.md`, `docs/rag/multilanguage-content-strategy.md`

## Tech Stack

- **Existing:** Vue 3 (Composition API), Pinia, TanStack Vue Query, vue-i18n, Tailwind 4, Laravel 12, Sanctum
- **New dependencies:** None required — all work uses existing packages
- **External APIs:** GeminiGen AI (image generation) — called by Claude Cloud Task, NOT by VPS
- **Automation:** Claude Cloud Scheduled Tasks (FREE with Max subscription)

---

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Blog post list (EN/ID) | PostController::index() | `usePosts().fetchPosts({lang})` | Partial | Pass lang param from i18n locale |
| Single post (EN/ID) | PostController::show() | `usePosts().fetchPost(slug, lang)` | Partial | Pass lang from route/i18n |
| Post translations | PostTranslation model | `Post::with('translations')` | Yes | Already loaded in controller |
| i18n locale state | vue-i18n | `useI18n().locale` | Yes | Connect to blog views |
| UI string translations | `i18n/en.json`, `i18n/id.json` | `$t('key')` | Yes | Add blog-specific keys |
| Language switcher | vue-i18n + localStorage | `useI18n()` | No | Create LanguageSwitcher component |
| Locale-aware routing | vue-router | `/:lang/blog/:slug` | No | Add locale prefix to routes |
| Hreflang meta tags | useMetaTags composable | `updateHreflang()` | No | Extend composable |
| Multilingual sitemap | SitemapController | `/api/sitemap-posts.xml` | Partial | Add hreflang xhtml:link |
| Translation status | post_translations.status | DB column | No | Add migration + update model |
| AI image generation | GeminiGen API | Claude Cloud Task WebFetch | No (Claude handles) | Claude calls API directly, sends URL to VPS |
| Article generation | Claude LLM | Claude Cloud Scheduled Task | No (Claude IS the LLM) | Claude reads RAG docs + generates directly |
| Post creation (automation) | POST /api/admin/posts | Existing admin endpoint | Yes | Use as-is — supports translations[] array |
| Dedup check | POST /api/posts/check-duplicate | Existing public endpoint | Yes | Use as-is |
| PostResource lang-aware | PostResource.php | API response | Partial | Enhance to merge translation fields |
| Admin translation tabs | BlogPostForm.vue | `translationsData` prop | Partial | Build tab UI |
| TanStack cache per lang | QueryClient | `queryKey` includes lang | No | Add lang to query keys |

---

## Phase 1: Backend — Translation-Aware API Response

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Http/Resources/PostResource.php`
- Modify: `backend/app/Http/Controllers/Api/PostController.php`

**Steps:**

### 1A. Enhance PostResource to merge translation fields

The API currently returns `translations` as a raw array. We need the resource to automatically merge the requested language's translation into the top-level fields.

**File:** `backend/app/Http/Resources/PostResource.php`

**What to do:**
- Accept `lang` from `additional` data (already passed: `->additional(['lang' => $language])`)
- If translation exists for requested lang, overlay: title, slug, excerpt, content, meta_title, meta_description, meta_keywords, og_title, og_description, canonical_url, ai_summary
- Add `current_language` field to response
- Add `available_languages` array listing all translations

```php
// In toArray():
$lang = $this->additional['lang'] ?? 'en';
$translation = $this->translations->firstWhere('language', $lang);

return [
    'id' => $this->id,
    'title' => $translation->title ?? $this->title,
    'slug' => $translation->slug ?? $this->slug,
    'excerpt' => $translation->excerpt ?? $this->excerpt,
    'content' => $translation->content ?? $this->content,
    // ... other translated fields
    'current_language' => $lang,
    'available_languages' => $this->translations->pluck('language')->toArray(),
    // ... keep original fields: featured_image, category, tags, published_at, etc.
];
```

### 1B. Ensure PostController passes lang consistently

**File:** `backend/app/Http/Controllers/Api/PostController.php`

**What to do:**
- In `index()`: pass `lang` to each PostResource via `->additional(['lang' => $language])`
- In `show()`: already passes lang (verify)
- In both: also support `?lang=` in query string (already works)

**Verification:**
- [ ] `GET /api/posts?lang=id` returns Indonesian title/content from translation (if exists)
- [ ] `GET /api/posts/my-slug?lang=id` returns Indonesian version
- [ ] `available_languages` field present in response
- [ ] Fallback to English if Indonesian translation doesn't exist
- [ ] `GET /api/posts?lang=en` still works as before

---

## Phase 2: Backend — Multilingual Sitemap

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/SitemapController.php`

**Steps:**

### 2A. Add hreflang to sitemap posts

**What to do:**
- In `posts()` method, load translations with each post: `Post::published()->with('translations:post_id,language,slug')`
- For each post, generate `<xhtml:link>` alternates for each available translation
- Add `xmlns:xhtml="http://www.w3.org/1999/xhtml"` to urlset

```xml
<url>
  <loc>https://alisadikinma.com/en/blog/my-post</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://alisadikinma.com/en/blog/my-post"/>
  <xhtml:link rel="alternate" hreflang="id" href="https://alisadikinma.com/id/blog/my-post-id-slug"/>
  <xhtml:link rel="alternate" hreflang="x-default" href="https://alisadikinma.com/en/blog/my-post"/>
  <lastmod>2026-04-10T12:00:00+00:00</lastmod>
</url>
```

**Verification:**
- [ ] `/api/sitemap-posts.xml` includes hreflang links for posts with translations
- [ ] x-default points to English version
- [ ] Posts without translations only show single language entry
- [ ] XML is valid (no parsing errors)

---

## Phase 3: Frontend — Locale-Aware Routing

**Estimated time:** 15 minutes

**Files:**
- Modify: `frontend/src/router/index.js`

**Steps:**

### 3A. Add locale prefix to all public routes

**What to do:**
- Add optional `/:lang` prefix to all public routes
- Default to `'en'` if no lang param
- Supported languages: `['en', 'id']`
- Admin routes stay language-neutral (no prefix)
- Add `beforeEach` guard to:
  1. Validate lang param (redirect invalid to `en`)
  2. Sync `i18n.global.locale` with route lang param
  3. Set `localStorage.setItem('locale', lang)`

```javascript
// Route structure:
{ path: '/:lang/blog', name: 'blog', component: Blog }
{ path: '/:lang/blog/:slug', name: 'blog-detail', component: BlogDetail }
{ path: '/:lang/blog/category/:slug', name: 'blog-category', component: BlogCategory }
// ... same for /work, /about, /contact

// Redirect root to default locale
{ path: '/', redirect: () => {
    const saved = localStorage.getItem('locale') || 'en'
    return `/${saved}`
  }
}
{ path: '/blog', redirect: '/en/blog' }  // Legacy URL redirect
{ path: '/blog/:slug', redirect: to => `/en/blog/${to.params.slug}` }
```

**Verification:**
- [ ] `/en/blog` loads Blog.vue with English posts
- [ ] `/id/blog` loads Blog.vue with Indonesian posts
- [ ] `/blog` redirects to `/en/blog`
- [ ] `/fr/blog` redirects to `/en/blog` (unsupported language)
- [ ] Admin routes (`/admin/*`) still work without locale prefix
- [ ] `i18n.global.locale` syncs with route lang param
- [ ] Browser back/forward maintains correct language

---

## Phase 4: Frontend — Language Switcher Component

**Estimated time:** 10 minutes

**Files:**
- Create: `frontend/src/components/LanguageSwitcher.vue`
- Modify: `frontend/src/components/TheNavigation.vue`

**Steps:**

### 4A. Create LanguageSwitcher.vue

**What to do:**
- Two-button toggle (EN | ID) matching design system
- Reads current locale from `useI18n().locale`
- On click: changes route to same path with new lang prefix
- Uses `router.replace()` to swap lang param without adding history entry
- Styled: glass-card aesthetic, small, fits in navbar

```vue
<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'

const { locale } = useI18n()
const router = useRouter()
const route = useRoute()

const languages = [
  { code: 'en', label: 'EN' },
  { code: 'id', label: 'ID' }
]

function switchLanguage(lang) {
  if (locale.value === lang) return
  const newPath = route.fullPath.replace(`/${locale.value}`, `/${lang}`)
  locale.value = lang
  localStorage.setItem('locale', lang)
  router.replace(newPath)
}
</script>
```

### 4B. Add LanguageSwitcher to TheNavigation.vue

**What to do:**
- Import and place `<LanguageSwitcher />` in the navbar, right side before any action buttons
- Desktop: inline next to nav links
- Mobile: in mobile menu

**Verification:**
- [ ] Language switcher visible in desktop and mobile nav
- [ ] Clicking "ID" switches route to `/id/...` and updates i18n locale
- [ ] Clicking "EN" switches back to `/en/...`
- [ ] Page content changes language after switch
- [ ] Switcher highlights current active language
- [ ] Matches dark cinema design system (glass aesthetic)

---

## Phase 5: Frontend — Connect Blog Views to Language

**Estimated time:** 15 minutes

**Files:**
- Modify: `frontend/src/composables/usePosts.js`
- Modify: `frontend/src/views/Blog.vue`
- Modify: `frontend/src/views/BlogDetail.vue`
- Modify: `frontend/src/views/BlogCategory.vue`

**Steps:**

### 5A. Make usePosts.js language-aware with TanStack Query

**What to do:**
- `fetchPosts()` must accept and use `lang` parameter
- Include `lang` in TanStack Query keys: `['posts', lang, ...params]`
- Include `lang` in single post query key: `['post', slug, lang]`
- When lang changes, TanStack Query auto-refetches (reactive key)

```javascript
// Query key includes language for proper cache separation
const postsQueryKey = computed(() => ['posts', selectedLang.value, queryParams.value])
const postQueryKey = computed(() => ['post', selectedPostSlug.value, selectedLang.value])
```

### 5B. Connect Blog.vue to route language

**What to do:**
- Read `route.params.lang` and pass to `fetchPosts()`
- Watch for lang changes and refetch
- No hardcoded 'en' — always use route lang

### 5C. Connect BlogDetail.vue to route language

**What to do:**
- Read `route.params.lang` and pass to `fetchPost(slug, lang)`
- Watch for lang changes and refetch

### 5D. Connect BlogCategory.vue to route language

**What to do:**
- Same pattern as Blog.vue — pass `route.params.lang`

**Verification:**
- [ ] `/en/blog` shows English posts, `/id/blog` shows Indonesian posts
- [ ] `/en/blog/my-post` shows English version, `/id/blog/my-post` shows Indonesian
- [ ] Switching language via LanguageSwitcher refetches content in new language
- [ ] TanStack cache stores EN and ID responses separately (check DevTools)
- [ ] Posts without Indonesian translation gracefully fallback to English content
- [ ] Pagination works correctly per language

---

## Phase 6: Frontend — Hreflang & SEO Meta Tags

**Estimated time:** 10 minutes

**Files:**
- Modify: `frontend/src/composables/useMetaTags.js`
- Modify: `frontend/src/views/BlogDetail.vue`

**Steps:**

### 6A. Add hreflang injection to useMetaTags.js

**What to do:**
- Add new function `updateHreflang(currentLang, alternates)` that injects `<link rel="alternate" hreflang="...">` tags into document head
- Add `inLanguage` field to `injectArticleSchema()` BlogPosting schema
- Clean up old hreflang tags before injecting new ones

```javascript
const updateHreflang = (currentLang, slug, availableLanguages) => {
  // Remove existing hreflang links
  document.querySelectorAll('link[hreflang]').forEach(el => el.remove())
  
  const base = 'https://alisadikinma.com'
  availableLanguages.forEach(lang => {
    const link = document.createElement('link')
    link.rel = 'alternate'
    link.hreflang = lang
    link.href = `${base}/${lang}/blog/${slug}`
    document.head.appendChild(link)
  })
  // x-default
  const xdefault = document.createElement('link')
  xdefault.rel = 'alternate'
  xdefault.hreflang = 'x-default'
  xdefault.href = `${base}/en/blog/${slug}`
  document.head.appendChild(xdefault)
}
```

### 6B. Call hreflang in BlogDetail.vue

**What to do:**
- After fetching post, call `updateHreflang(lang, slug, post.available_languages)`
- Pass `inLanguage` to article schema: `injectArticleSchema(post, lang)`

**Verification:**
- [ ] `<link rel="alternate" hreflang="en" ...>` present in head on blog detail page
- [ ] `<link rel="alternate" hreflang="id" ...>` present when ID translation exists
- [ ] `<link rel="alternate" hreflang="x-default" ...>` always points to EN version
- [ ] BlogPosting schema includes `"inLanguage": "en"` or `"id"`
- [ ] Old hreflang tags cleaned up on navigation between posts

---

## Phase 7: Frontend — i18n UI Strings for Blog

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/i18n/en.json`
- Modify: `frontend/src/i18n/id.json`

**Steps:**

### 7A. Add blog-specific translation keys

**What to do:**
- Add keys for all static text in Blog.vue, BlogDetail.vue, BlogCategory.vue
- Include: "Read more", "Share", "Related posts", "Categories", "Search", "No posts found", pagination labels, newsletter CTA, reading time, etc.

```json
// en.json additions:
{
  "blog": {
    "title": "Blog",
    "search_placeholder": "Search articles...",
    "read_more": "Read More",
    "reading_time": "{minutes} min read",
    "share": "Share",
    "related_posts": "Related Posts",
    "no_posts": "No posts found",
    "categories": "Categories",
    "all_categories": "All",
    "load_more": "Load More",
    "published": "Published",
    "newsletter_title": "Stay Updated",
    "newsletter_desc": "Get the latest articles delivered to your inbox.",
    "newsletter_cta": "Subscribe"
  }
}

// id.json additions:
{
  "blog": {
    "title": "Blog",
    "search_placeholder": "Cari artikel...",
    "read_more": "Baca Selengkapnya",
    "reading_time": "{minutes} menit baca",
    "share": "Bagikan",
    "related_posts": "Artikel Terkait",
    "no_posts": "Belum ada artikel",
    "categories": "Kategori",
    "all_categories": "Semua",
    "load_more": "Muat Lebih Banyak",
    "published": "Dipublikasikan",
    "newsletter_title": "Tetap Update",
    "newsletter_desc": "Dapatkan artikel terbaru langsung di inbox kamu.",
    "newsletter_cta": "Langganan"
  }
}
```

### 7B. Replace hardcoded strings in Blog views

**What to do:**
- In Blog.vue, BlogDetail.vue, BlogCategory.vue: replace all hardcoded English text with `{{ $t('blog.key') }}`

**Verification:**
- [ ] All static text in blog views shows in correct language based on route
- [ ] No hardcoded English strings remain in blog templates
- [ ] Fallback to English works if key missing in ID

---

## Phase 8: Admin — Translation Management Tabs

**Estimated time:** 20 minutes

**Files:**
- Modify: `frontend/src/components/blog/BlogPostForm.vue`

**Steps:**

### 8A. Add language tab UI to BlogPostForm

**What to do:**
- Add tab bar at top: "English (EN)" | "Indonesian (ID)"
- Each tab shows: title, slug, excerpt, content (RichTextEditor), meta_title, meta_description, meta_keywords, ai_summary
- Shared fields (not per-tab): category, tags, featured_image, published, published_at
- On save: send translations array with both languages

```javascript
const activeTranslationTab = ref('en')
const translationData = reactive({
  en: { title: '', slug: '', excerpt: '', content: '', meta_title: '', meta_description: '', meta_keywords: '', ai_summary: '' },
  id: { title: '', slug: '', excerpt: '', content: '', meta_title: '', meta_description: '', meta_keywords: '', ai_summary: '' }
})
```

### 8B. Update form submission to include translations

**What to do:**
- When creating/updating post, include `translations` array in payload
- Backend PostController `store()` and `update()` must handle `translations` array
- If only EN filled, don't create empty ID translation

**Backend change needed in PostController:**
```php
// In store() and update():
if ($request->has('translations')) {
    foreach ($request->input('translations') as $langData) {
        $post->translations()->updateOrCreate(
            ['language' => $langData['language']],
            $langData
        );
    }
}
```

**Verification:**
- [ ] Admin post form shows EN/ID tabs
- [ ] Switching tabs preserves entered data
- [ ] Creating post saves translations for both languages
- [ ] Editing post loads existing translations into correct tabs
- [ ] Empty translation tab is not saved (no empty rows in DB)
- [ ] Shared fields (category, tags, image) stay consistent across tabs

---

## Phase 9: Custom Skills + Managed Agent + Scheduled Task

**Estimated time:** 20 minutes

**Architecture: Skills-Based Multi-Agent Platform**

RAG docs are packaged as **Custom Skills** via Skills API. The Blog Agent references these skills, and future agents (Carousel, Video) reuse the same skills. See [managed-agents-skills-architecture.md](2026-04-10-managed-agents-skills-architecture.md) for full architecture doc.

```
[Custom Skills] (uploaded once, versioned)
  ├── content-writing-framework
  ├── hook-library (100 hooks)
  ├── emotion-lexicon
  ├── scoring-engine
  ├── seo-geo-optimization
  ├── ai-image-generation
  ├── visual-direction
  ├── multilanguage-strategy
  └── indonesian-slang
         │
         v
[Blog Agent] (Managed Agent, references 9 skills)
         │
         v
[Cloud Scheduled Task] (hourly, $0 with Max)
  ├── Claude loads skills on-demand
  ├── WebFetch Google Trends
  ├── Generate article EN + ID
  ├── WebFetch GeminiGen → hero image
  └── POST /api/admin/posts → save draft
```

**Future agents reuse same skills:**
- Carousel Agent → `ai-image-generation` + `hook-library` + `visual-direction` + `indonesian-slang` + `emotion-lexicon` (+ knowledge from `ai-image-carousel-prompt-gen` plugin)
- Video Agent → shared skills + knowledge from `ai-video-promo-engine` plugin

**Files:**
- Create: `skills/*/SKILL.md` (9 skill files)
- Create: `scripts/upload-skills.ts`
- Create: `scripts/setup-blog-agent.ts`
- Create: `skills/skill-ids.json`

**Steps:**

### 9A. Split RAG docs into 9 Skill files

**What to do:**
Split the 3 monolithic RAG docs into 9 focused SKILL.md files:

| Skill Directory | Source | Sections |
|---|---|---|
| `skills/content-writing-framework/` | blog-article-writing.md | §1-6 (framework, psychology, hooks, open loops, bucket brigades, copywriting) |
| `skills/hook-library/` | blog-article-writing.md | §12 (100 hooks, 5 categories, topic mapping) |
| `skills/emotion-lexicon/` | blog-article-writing.md | §13 (9 emotions, EN/ID power words) |
| `skills/scoring-engine/` | blog-article-writing.md | §15 (per-section scoring, benchmarks) |
| `skills/seo-geo-optimization/` | blog-article-writing.md | §7-8 (SEO 2026, GEO, E-E-A-T) |
| `skills/ai-image-generation/` | ai-image-generation.md | §1-8 (models, APIs, prompts, costs) |
| `skills/visual-direction/` | ai-image-generation.md | §9-11 (emotion-to-visual, prompt synthesis) |
| `skills/multilanguage-strategy/` | multilanguage-content-strategy.md | §1-3,5-8 (URL, hreflang, adaptation, workflow) |
| `skills/indonesian-slang/` | multilanguage-content-strategy.md | §4B (slang bank, particles, emoji, hooks) |

Each SKILL.md format:
```markdown
---
name: hook-library
description: 100 research-backed content hooks in 5 psychology categories with topic mapping. Use for article titles, social hooks, video intros.
---

[Full content extracted from RAG doc section]
```

### 9B. Create upload-skills.ts script

**File:** `scripts/upload-skills.ts`

```typescript
import Anthropic from "@anthropic-ai/sdk";
import fs from "fs";
import path from "path";

const client = new Anthropic();
const SKILLS_DIR = path.join(__dirname, "../skills");
const IDS_FILE = path.join(SKILLS_DIR, "skill-ids.json");

// Load existing IDs or start fresh
const skillIds: Record<string, string> = fs.existsSync(IDS_FILE)
  ? JSON.parse(fs.readFileSync(IDS_FILE, "utf-8"))
  : {};

const skillDirs = fs.readdirSync(SKILLS_DIR)
  .filter(d => fs.statSync(path.join(SKILLS_DIR, d)).isDirectory());

for (const dir of skillDirs) {
  const skillPath = path.join(SKILLS_DIR, dir, "SKILL.md");
  if (!fs.existsSync(skillPath)) continue;

  const content = fs.readFileSync(skillPath, "utf-8");

  if (!skillIds[dir]) {
    // Create new skill
    const skill = await client.beta.skills.create(
      { name: dir },
      { headers: { "anthropic-beta": "skills-2025-10-02" } }
    );
    skillIds[dir] = skill.id;
    console.log(`Created skill: ${dir} → ${skill.id}`);
  }

  // Upload new version
  await client.beta.skills.versions.create(
    skillIds[dir],
    { content },
    { headers: { "anthropic-beta": "skills-2025-10-02" } }
  );
  console.log(`Uploaded version for: ${dir}`);
}

// Save IDs
fs.writeFileSync(IDS_FILE, JSON.stringify(skillIds, null, 2));
console.log(`Skill IDs saved to ${IDS_FILE}`);
```

### 9C. Create setup-blog-agent.ts script

**File:** `scripts/setup-blog-agent.ts`

```typescript
import Anthropic from "@anthropic-ai/sdk";
import fs from "fs";
import path from "path";

const client = new Anthropic();
const skillIds = JSON.parse(
  fs.readFileSync(path.join(__dirname, "../skills/skill-ids.json"), "utf-8")
);

// 1. Create environment (once)
const environment = await client.beta.environments.create({
  name: "blog-generator-env",
  config: {
    type: "cloud",
    networking: { type: "unrestricted" },
  },
});

// 2. Create agent with all 9 skills
const agent = await client.beta.agents.create({
  name: "Blog Article Generator",
  model: "claude-opus-4-6",
  system: `You are an automated blog content pipeline for alisadikinma.com.

Your job: find a trending tech topic, write a bilingual article (EN + ID),
generate a hero image via GeminiGen API, and save as draft via the blog API.

RULES:
- ALWAYS save as draft (published: false), NEVER auto-publish
- Use the HOOK-FORESHADOW-BODY-PEAK-CTA framework from your skills
- Generate both EN and ID versions (transcreation, not translation)
- Indonesian: use code-mixing, kita/lo/gue pronouns, particles (sih, dong, banget)
- Quality > quantity: skip if no good topic found
- Target: 1,800-2,400 words per article

API DETAILS:
- Blog API: POST https://alisadikinma.com/api/admin/posts
- Auth: Bearer {BLOG_API_TOKEN} (in env vars)
- Dedup: POST https://alisadikinma.com/api/posts/check-duplicate
- Image: POST https://api.geminigen.ai/uapi/v1/generate_image
  Headers: x-api-key: {GEMINIGEN_API_KEY}
  model=imagen-pro, aspect_ratio=16:9, style=Photorealistic
- Trends: https://trends.google.com/trending/rss?geo=ID and ?geo=US`,
  tools: [
    { type: "agent_toolset_20260401", default_config: { enabled: true } },
  ],
  skills: Object.values(skillIds).map(id => ({
    type: "custom" as const,
    skill_id: id as string,
    version: "latest",
  })),
});

// Save config
const config = {
  environment_id: environment.id,
  agent_id: agent.id,
  agent_version: agent.version,
  skill_ids: skillIds,
};
fs.writeFileSync(
  path.join(__dirname, "../.claude/agent-config.json"),
  JSON.stringify(config, null, 2)
);
console.log("Agent config saved to .claude/agent-config.json");
console.log(config);
```

### 9D. Setup Cloud Scheduled Task (manual, one-time)

**Via Web UI:**
1. Visit [claude.ai/code/scheduled](https://claude.ai/code/scheduled)
2. Click "New scheduled task"
3. Name: "Blog Article Generator"
4. Prompt:
```
Find a trending tech topic (AI, web dev, Laravel, Vue.js),
write a bilingual blog article (EN + ID) using the
HOOK-FORESHADOW-BODY-PEAK-CTA framework, generate a hero image,
and save as draft to the blog API.

Steps:
1. WebFetch Google Trends RSS (geo=ID and geo=US), filter for tech
2. POST /api/posts/check-duplicate to avoid repeats
3. Generate 1,800-2,400 word article in EN following the framework
4. Transcreate to ID (code-mixing, particles, gue/lo pronouns)
5. WebFetch GeminiGen API to generate hero image (imagen-pro, 16:9)
6. POST /api/admin/posts with translations[en, id] + featured_image
7. ALWAYS published: false (draft only)
```
5. Repository: Portfolio_v2 GitHub repo
6. Environment:
   - Network access: **Full**
   - Env variables: `BLOG_API_TOKEN`, `GEMINIGEN_API_KEY`
7. Schedule: **Hourly**

### 9E. Create automation Sanctum token

**What to do:**
- Login to admin panel → Automation Tokens → Create new
- Name: "Claude Blog Agent"
- Copy token → set as `BLOG_API_TOKEN` in Cloud Task environment

**Verification:**
- [ ] 9 SKILL.md files created in `skills/` directory
- [ ] `scripts/upload-skills.ts` uploads all skills successfully
- [ ] `skills/skill-ids.json` contains all 9 skill IDs
- [ ] `scripts/setup-blog-agent.ts` creates agent + environment
- [ ] `.claude/agent-config.json` saved with IDs
- [ ] Cloud scheduled task created and configured
- [ ] "Run now" creates session — Claude loads skills on-demand
- [ ] Article generated with EN + ID translations
- [ ] Hero image generated via GeminiGen
- [ ] Post saved as DRAFT to blog API
- [ ] Session reviewable at claude.ai/code

---

## Phase 10: Admin — Pipeline Status on Dashboard

**Estimated time:** 10 minutes

**Files:**
- Modify: `frontend/src/views/admin/Dashboard.vue`

**Steps:**

### 10A. Add "Pending Drafts" section to Dashboard

**What to do:**
- Show count of unpublished/draft posts
- List recent drafts: title (EN), generated date, hero image preview, "Review" button
- "Review" → navigates to PostEdit

### 10B. Add "Quick Publish" action in PostEdit

**What to do:**
- Prominent "Publish" button for draft posts
- Sets `published = true`, `published_at = now()`
- Confirmation: "Publish this article in EN and ID?"

**Verification:**
- [ ] Dashboard shows draft post count
- [ ] Recent drafts listed with preview
- [ ] Quick publish works

---

## Phase Summary

| Phase | Component | Est. Time | Dependencies |
|-------|-----------|-----------|--------------|
| 1 | Backend: Translation-aware API response | 15 min | None |
| 2 | Backend: Multilingual sitemap | 10 min | None |
| 3 | Frontend: Locale-aware routing | 15 min | None |
| 4 | Frontend: Language switcher | 10 min | Phase 3 |
| 5 | Frontend: Connect blog views to language | 15 min | Phase 1, 3 |
| 6 | Frontend: Hreflang & SEO meta | 10 min | Phase 5 |
| 7 | Frontend: i18n UI strings | 5 min | Phase 3 |
| 8 | Admin: Translation management tabs | 20 min | Phase 1 |
| 9 | Backend: AI image generation service | 15 min | None |
| 10 | Frontend: Image generation composable | 10 min | Phase 9 |
| 11 | Admin: Image generation UI | 15 min | Phase 10 |
| 12 | Backend: Article generation service | 20 min | None |
| 13 | Admin: Article generation UI | 15 min | Phase 8, 12 |

**Total estimated:** ~175 minutes (~3 hours)

## Parallel Execution Opportunities

These phase groups can run in parallel:

**Group A (Backend):** Phase 1, 2, 9, 12 — all backend, no dependencies on each other
**Group B (Frontend Core):** Phase 3, 4, 7 — routing + switcher + strings (after Group A Phase 1)
**Group C (Frontend Integration):** Phase 5, 6 — connect views to language (after Group B)
**Group D (Admin):** Phase 8, 10, 11, 13 — admin features (after respective backends)

## Execution Recommendation

Use `gaspol-parallel` for Group A (4 backend phases), then sequential for Groups B→C→D.

---

## Phase 14: Claude Cloud Scheduled Task — Hybrid Pipeline

**Estimated time:** 15 minutes

**Architecture: Hybrid (Claude = Brain, VPS = Storage)**

```
[Claude Cloud Scheduled Task] ← runs hourly, FREE with Max subscription
  │
  ├── 1. git clone Portfolio_v2 repo
  ├── 2. Read docs/rag/blog-article-writing.md     (HOOK-FORE-BODY-PEAK-CTA framework)
  ├── 3. Read docs/rag/multilanguage-content-strategy.md  (EN/ID writing rules)
  ├── 4. Read docs/rag/ai-image-generation.md       (image prompt engineering)
  ├── 5. WebFetch Google Trends RSS (tech topics)
  ├── 6. WebFetch recent posts: GET /api/posts?limit=10  (dedup check)
  ├── 7. Claude GENERATES full article EN + ID       (FREE — Max quota)
  ├── 8. Claude GENERATES image prompts using RAG    (FREE — Max quota)
  └── 9. POST /api/automation/create-article         (sends to VPS)
          │
          v
[VPS Laravel API]
  ├── 1. Generate hero image from prompt (GeminiGen FREE)
  ├── 2. Generate inline images from prompts
  ├── 3. Save Post + EN translation + ID translation
  └── 4. published = false (ALWAYS DRAFT)
          │
          v
[MySQL DB] → Draft ready for review in admin
```

**Cost: $0 extra/month** (Claude Max included + GeminiGen imagen-pro FREE tier)

**Files:**
- Create: `.claude/scheduled-task-prompt.md`

**Steps:**

### 14A. Create the scheduled task prompt

**File:** `.claude/scheduled-task-prompt.md`

This is the complete prompt Claude executes every hour. It must be self-contained because the Cloud Scheduled Task has no memory between runs.

```markdown
# Blog Article Generator — Automated Pipeline

You are an automated content pipeline for alisadikinma.com.
Your job: find a trending tech topic, write a bilingual article (EN + ID),
generate image prompts, and save as draft via the blog API.

## Step 1: Read RAG Knowledge (MANDATORY)

Read these files from the repo BEFORE generating anything:
- docs/rag/blog-article-writing.md — Article framework + hooks + scoring
- docs/rag/multilanguage-content-strategy.md — EN/ID writing rules + slang
- docs/rag/ai-image-generation.md — Image prompt engineering rules

## Step 2: Find Trending Tech Topic

Fetch trending topics from these sources:
- Google Trends (ID): https://trends.google.com/trending/rss?geo=ID
- Google Trends (US): https://trends.google.com/trending/rss?geo=US
- Google News Tech: https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRGRqTVhZU0FtVnVHZ0pWVXlnQVAB?hl=en&gl=US&ceid=US:en

Filter for topics related to: AI, machine learning, web development, coding,
Laravel, Vue.js, design, tech careers, developer tools, SaaS.

## Step 3: Dedup Check

Fetch recent posts: GET https://alisadikinma.com/api/posts?limit=20
Compare trend topic against existing post titles.
If too similar (>70% overlap), pick the next trend.
If NO unique topic found, STOP and log "No unique trending topic found today."

## Step 4: Generate Article (EN)

Using the HOOK-FORESHADOW-BODY-PEAK-CTA framework from the RAG doc:
- HOOK: 100 words, shocking stat or curiosity gap
- FORESHADOW: 100 words, preview + open main loop
- BODY: 3-4 sections, escalating value, 1200-1500 words
- PEAK: biggest insight, quotable, 200-300 words
- CTA: close all loops, single action, FAQ, 200-300 words
- Total: 1,800-2,400 words
- Readability: Flesch-Kincaid grade 7-9
- GEO: statistic every 150-200 words, direct answer in first 200 words
- SEO: primary keyword in H1, first 100 words, 2+ H2s
- Format: HTML with proper H2/H3, lists, bold, code blocks

Also generate: meta_title (60 chars), meta_description (155 chars),
meta_keywords (5-8), excerpt (150 chars), ai_summary (2-3 sentences),
tags (3-5), faq (2-3 Q&A pairs), suggested category slug.

## Step 5: Generate Article (ID)

Transcreate (NOT translate) the article to Indonesian:
- Use code-mixing: [Bahasa] + [English tech terms] + [particles]
- Pronouns: kita (we), lo/gue (casual) — NEVER saya/Anda
- Particles: sih, tuh, dong, deh, nih, kan, banget
- Opening: warm "Halo teman-teman!" style
- Keep ALL tech terms in English (deploy, framework, API, etc.)
- Adapt hooks for Indonesian culture (use ID slang from RAG doc)
- Different meta_title, meta_description, meta_keywords for ID SEO

## Step 6: Generate Image Prompts

Using the 8-element structure from the RAG doc:
1. Hero image prompt (16:9, Photorealistic style)
2. 2-3 inline image prompts (4:3, matching article sections)

Each prompt must include: subject, action, setting, camera, lighting, style, texture, color.
Use film stocks from RAG: Kodak Vision3 500T, Portra 400, etc.

## Step 7: Send to VPS API

POST https://alisadikinma.com/api/automation/create-article
Authorization: Bearer {BLOG_AUTOMATION_TOKEN}
Content-Type: application/json

Send the complete payload with all EN/ID content, SEO fields, and image prompts.
VPS handles image generation and DB storage.

## Rules
- NEVER set auto_publish to true. ALWAYS save as draft.
- If any step fails, log the error and STOP. Do not publish broken content.
- Quality over quantity: skip if no good topic found.
- Each article should be genuinely useful, not SEO-stuffed filler.
```

### 14B. Setup Cloud Scheduled Task

**How to deploy (manual step, one-time):**

**Option A: Via CLI**
```
/schedule "Blog Article Generator" hourly
```
Then paste the prompt content when asked.

**Option B: Via Web UI**
1. Visit [claude.ai/code/scheduled](https://claude.ai/code/scheduled)
2. Click "New scheduled task"
3. Name: "Blog Article Generator"
4. Prompt: content from `.claude/scheduled-task-prompt.md`
5. Repository: Portfolio_v2 GitHub repo
6. Environment:
   - Network access: **Full** (needs to call blog API + fetch trends)
   - Env variables: `BLOG_AUTOMATION_TOKEN` (your automation API token)
   - Setup script: none needed (Claude reads files from repo)
7. Schedule: **Hourly**
8. Connectors: optional (Slack for notifications)

### 14C. Create automation token on VPS

**What to do:**
- In admin panel: Automation Tokens → Create new token
- Name: "Claude Scheduled Task"
- Copy token value
- Set as `BLOG_AUTOMATION_TOKEN` in Cloud Scheduled Task environment

**Verification:**
- [ ] Prompt file `.claude/scheduled-task-prompt.md` is comprehensive and self-contained
- [ ] Cloud scheduled task created and shows in [claude.ai/code/scheduled](https://claude.ai/code/scheduled)
- [ ] "Run now" executes successfully — creates reviewable session
- [ ] Claude reads RAG docs from repo during execution
- [ ] Claude fetches trends and deduplicates against existing posts
- [ ] Article sent to VPS API is complete (EN + ID + image prompts + SEO)
- [ ] VPS generates images and saves post as DRAFT
- [ ] Session is reviewable — you can see what Claude did
- [ ] Hourly runs don't create duplicate articles
- [ ] Skips gracefully when no unique topic found

---

## Final Phase Summary

| Phase | Component | Est. Time | Dependencies |
|-------|-----------|-----------|--------------|
| 1 | Backend: Translation-aware API response | 15 min | None |
| 2 | Backend: Multilingual sitemap | 10 min | None |
| 3 | Frontend: Locale-aware routing | 15 min | None |
| 4 | Frontend: Language switcher | 10 min | Phase 3 |
| 5 | Frontend: Connect blog views to language | 15 min | Phase 1, 3 |
| 6 | Frontend: Hreflang & SEO meta | 10 min | Phase 5 |
| 7 | Frontend: i18n UI strings | 5 min | Phase 3 |
| 8 | Admin: Translation management tabs | 20 min | Phase 1 |
| 9 | Custom Skills + Managed Agent + Scheduled Task | 20 min | None (uses existing API) |
| 10 | Admin: Pipeline status on dashboard | 10 min | None |

**Total: 10 phases, ~125 minutes (~2 hours)**

## Parallel Execution Opportunities

**Group A (Independent):** Phase 1, 2, 9 — can run in parallel
**Group B (Frontend Core):** Phase 3, 4, 7 — after Phase 1
**Group C (Frontend Integration):** Phase 5, 6 — after Group B
**Group D (Admin):** Phase 8, 10 — after Phase 1

## Cost Summary

| Component | Cost |
|-----------|------|
| Claude article generation (hourly) | **$0** — included in Max subscription |
| Claude image generation via GeminiGen | **$0** — imagen-pro FREE tier (1000/day) |
| Cloud Scheduled Task runtime | **$0** — included in Max subscription |
| VPS hosting | Already running (alisadikinma.com) |
| New backend code | **None** — uses existing `POST /api/admin/posts` |
| New dependencies | **None** |
| **Total extra monthly cost** | **$0** |

## Multi-Agent Future Roadmap

Skills infrastructure built in Phase 9 enables rapid expansion:

| Future Agent | Reuses Skills | New Skills Needed | Knowledge Source |
|---|---|---|---|
| Carousel Image Agent | ai-image-generation, hook-library, visual-direction, indonesian-slang, emotion-lexicon | carousel-prompt-gen | `D:\Projects\claude-plugin\ai-image-carousel-prompt-gen` |
| Video Promo Agent | content-writing-framework, hook-library, emotion-lexicon, scoring-engine, visual-direction | video-promo-engine | `D:\Projects\claude-plugin\ai-video-promo-engine` |
| Social Media Agent | hook-library, indonesian-slang, multilanguage-strategy, emotion-lexicon | social-post-templates | New (to be created) |

Architecture doc: [managed-agents-skills-architecture.md](2026-04-10-managed-agents-skills-architecture.md)

## What Was Eliminated (Simplification)

| Removed | Why |
|---------|-----|
| ~~Phase 9: ImageGenerationService~~ | Claude calls GeminiGen directly via WebFetch |
| ~~Phase 10: useImageGeneration composable~~ | No backend image service to call |
| ~~Phase 11: Image generation UI~~ | Images auto-generated by pipeline |
| ~~Phase 12: AutomationArticleController~~ | Uses existing `POST /api/admin/posts` |
| ~~Phase 13: ArticleGenerationService~~ | Claude IS the LLM — no separate service needed |
| ~~Phase 14: Separate pipeline phase~~ | Merged into Phase 9 |
| ~~OpenRouter/Gemini API keys on VPS~~ | Claude handles all LLM work |
| ~~backend/.env API keys~~ | Only `GEMINIGEN_API_KEY` in Cloud Task env |
