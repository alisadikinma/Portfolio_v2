# RAG Knowledge: Multi-Language Content Strategy (EN/ID)

> **Purpose:** Reference guide for building and maintaining a bilingual English/Indonesian blog on alisadikinma.com.
> **Source:** Web research (2025-2026 best practices) + Portfolio_v2 existing infrastructure analysis.
> **Last Updated:** 2026-04-10

---

## Table of Contents

1. [URL Structure & Architecture](#1-url-structure--architecture)
2. [Hreflang Implementation](#2-hreflang-implementation)
3. [Content Adaptation vs Translation](#3-content-adaptation-vs-translation)
4. [Indonesian Tech Writing Style](#4-indonesian-tech-writing-style)
5. [Write-First Language Strategy](#5-write-first-language-strategy)
6. [Translation Workflow](#6-translation-workflow)
7. [Per-Locale SEO Strategy](#7-per-locale-seo-strategy)
8. [GEO for Multi-Language Content](#8-geo-for-multi-language-content)
9. [Indonesian Digital Landscape](#9-indonesian-digital-landscape)
10. [SPA (Vue 3) SEO Solutions](#10-spa-vue-3-seo-solutions)
11. [Existing Infrastructure (Portfolio_v2)](#11-existing-infrastructure-portfolio_v2)

---

## 1. URL Structure & Architecture

### Recommendation: Subdirectories

```
alisadikinma.com/en/blog/...    (English)
alisadikinma.com/id/blog/...    (Indonesian)
alisadikinma.com/                (Default: English, x-default)
```

**Why subdirectories over subdomains:**
- Inherit main domain's authority (subdomains are treated as separate sites by Google)
- Case studies show 15-45% traffic increases when moving from subdomains to subdirectories
- Subdirectories account for 20%+ of top-3 ranking positions in international SEO
- Single SSL certificate, single analytics property, simpler maintenance

**DO NOT use:**
- Query parameters (`?lang=id`) — poor crawlability, no authority sharing
- Separate domains (`alisadikinma.id`) — splits authority, doubles maintenance

### Route Structure

```
# Public blog routes
/en/blog                    → Blog listing (English)
/id/blog                    → Blog listing (Indonesian)
/en/blog/:slug              → Blog post (English)
/id/blog/:slug              → Blog post (Indonesian)

# Other public routes follow same pattern
/en/work, /id/work
/en/about, /id/about
/en/contact, /id/contact

# Admin routes stay language-neutral
/admin/posts                → Manage posts (all languages)
```

### Default Language Behavior

- Root `/` defaults to English (primary international audience)
- Auto-detect via `Accept-Language` header for first visit
- Store preference in localStorage
- `x-default` hreflang points to English version

---

## 2. Hreflang Implementation

### Critical Rules

1. **Bidirectional linking is mandatory** — if EN points to ID, ID MUST point back to EN. Missing return links cause Google to ignore hreflang entirely.
2. **Self-referencing required** — every page includes hreflang pointing to itself.
3. **Use x-default** — designate fallback for users whose language doesn't match any version.
4. **ISO 639-1 codes** — use `en` for English, `id` for Indonesian (NOT `in`, NOT `bahasa`).
5. Google treats hreflang as **hints, not directives** — canonical tags and site structure also matter.

### HTML Head Tags

```html
<!-- On English page: /en/blog/my-post -->
<link rel="alternate" hreflang="en" href="https://alisadikinma.com/en/blog/my-post" />
<link rel="alternate" hreflang="id" href="https://alisadikinma.com/id/blog/my-post" />
<link rel="alternate" hreflang="x-default" href="https://alisadikinma.com/en/blog/my-post" />
<link rel="canonical" href="https://alisadikinma.com/en/blog/my-post" />

<!-- On Indonesian page: /id/blog/my-post -->
<link rel="alternate" hreflang="en" href="https://alisadikinma.com/en/blog/my-post" />
<link rel="alternate" hreflang="id" href="https://alisadikinma.com/id/blog/my-post" />
<link rel="alternate" hreflang="x-default" href="https://alisadikinma.com/en/blog/my-post" />
<link rel="canonical" href="https://alisadikinma.com/id/blog/my-post" />
```

### XML Sitemap with Hreflang

```xml
<url>
  <loc>https://alisadikinma.com/en/blog/my-post</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://alisadikinma.com/en/blog/my-post"/>
  <xhtml:link rel="alternate" hreflang="id" href="https://alisadikinma.com/id/blog/my-post"/>
  <xhtml:link rel="alternate" hreflang="x-default" href="https://alisadikinma.com/en/blog/my-post"/>
</url>
<url>
  <loc>https://alisadikinma.com/id/blog/my-post</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://alisadikinma.com/en/blog/my-post"/>
  <xhtml:link rel="alternate" hreflang="id" href="https://alisadikinma.com/id/blog/my-post"/>
  <xhtml:link rel="alternate" hreflang="x-default" href="https://alisadikinma.com/en/blog/my-post"/>
</url>
```

### Laravel Sitemap Extension

Using `spatie/laravel-sitemap`:
```php
Url::create('/en/blog/my-post')
    ->addAlternate('/id/blog/my-post', 'id')
    ->addAlternate('/en/blog/my-post', 'en');
```

---

## 3. Content Adaptation vs Translation

### The Spectrum

```
Pure Translation ←————————————→ Pure Transcreation
(Word-for-word)                    (Rewrite from scratch)

Technical tutorials    Blog intros/CTAs    About page
API documentation      Personal stories    Brand messaging
Code comments          Cultural refs       Bio/portfolio
```

### When to Translate vs Transcreate

| Content Type | Approach | Why |
|---|---|---|
| Technical tutorials | **Guided translation** — translate core, adapt examples | Tech terms are universal; precision matters |
| Blog intro/hook | **Transcreation** — rewrite for cultural resonance | Hooks must feel native, not translated |
| Blog body (technical) | **Translation + adaptation** | Keep technical accuracy, adapt metaphors |
| Blog CTA | **Transcreation** | EN: action-oriented, ID: invitation-oriented |
| About page | **Write independently per language** | Identity/brand content must feel authentic |
| Project descriptions | **English first, adapt to ID** | International clients are primary audience |
| Personal stories | **Indonesian first** if targeting ID audience | Authenticity matters most |

### Key Differences: EN vs ID Tech Content

| Dimension | English | Indonesian |
|---|---|---|
| Register | Direct, concise, authoritative | Warmer, conversational, relational |
| Humor | Dry wit, pop culture refs | Memes, self-deprecating, "ngoding" culture |
| Trust signals | Credentials, data, metrics | Community belonging, relatability, story |
| CTA style | "Get started now", "Try it free" | "Yuk, kita mulai", "Coba bareng yuk" |
| Length | Scannable, concise preferred | Slightly longer, more explanatory is OK |
| Opening | Problem statement or bold claim | Warm greeting: "Halo teman-teman!" |

### Anti-Patterns (DO NOT)

- Machine-translate and publish without human review (sounds "translated" to natives)
- Force Indonesian equivalents for established English tech terms
- Use the same featured/OG image if cultural reference differs
- Assume same content structure works for both audiences

---

## 4. Indonesian Tech Writing Style

### Code-Mixing is the Norm

Indonesian tech content naturally blends Bahasa Indonesia with English tech terms. Research shows:
- **85.3% insertion** — English words in Indonesian sentences: "Kita perlu setup environment dulu sebelum mulai coding."
- **14.7% alternation** — Switching between full clauses: "Jadi, basically, the API returns a JSON response."

### Why Code-Mixing Works

- Many tech terms have no natural Indonesian equivalent ("deploy", "commit", "API", "framework")
- Using English tech terms prevents misinterpretation ("branch" ≠ "cabang" in Git context)
- Code-mixing signals tech community membership (identity marker)

### The Indonesian Tech Blog Voice

| Aspect | Style |
|---|---|
| **Tone** | Conversational, like explaining to a friend |
| **Tech terms** | Keep in English: "deploy", "database", "frontend" |
| **Opening** | Warm: "Halo teman-teman!" or "Hai, kali ini kita akan..." |
| **Pronoun** | Use inclusive "kita" (we) not "kamu" (you) — collaborative framing |
| **Humor** | Programming memes, relatable dev struggles ("ngoding sampai subuh") |
| **Formality** | Semi-informal. Not slang-heavy, not academic |
| **Structure** | Step-by-step with clear headers. Indonesians prefer detailed explanations |
| **Connectors** | Informal: "Nah,", "Jadi,", "Oke,", "Terus," |
| **Closing** | Encouragement: "Selamat mencoba! Kalau ada pertanyaan, tanya di komentar ya." |

### Writing Rules for ID Content

1. **Open warmly**: "Halo! Di artikel kali ini, kita akan belajar tentang..." NOT "Artikel ini membahas tentang..."
2. **Keep tech terms English**: "Kita akan membuat REST API menggunakan Laravel" — NEVER "Antarmuka Pemrograman Aplikasi"
3. **Use "kita" (we)**: Creates learning-together dynamic. Avoid formal "Anda" unless official content.
4. **Add foundational context**: Indonesian readers may need more background explanation than EN readers.
5. **Use informal connectors**: "Nah,", "Jadi,", "Oke,", "Terus," — these feel natural.
6. **End with encouragement**: Not just CTA, genuine supportive closing.

### Bilingual Glossary (Maintain Consistency)

| English | Indonesian Usage | Notes |
|---|---|---|
| Deploy | Deploy (keep EN) | "Mendeploy" is acceptable |
| Database | Database (keep EN) | Not "basis data" |
| Frontend/Backend | Frontend/Backend (keep EN) | Universal terms |
| Framework | Framework (keep EN) | Not "kerangka kerja" |
| API | API (keep EN) | Not "Antarmuka Pemrograman" |
| Machine Learning | Machine Learning (keep EN) | "Pembelajaran Mesin" sounds awkward |
| Website | Website (keep EN) | "Situs web" is too formal |
| Tutorial | Tutorial (keep EN) | Same in both languages |
| Code | Code / Kode | Both acceptable |
| Bug | Bug (keep EN) | "Kutu" would confuse everyone |

---

## 4B. Indonesian Slang Bank 2026

> **Source:** SparkFluence `_shared/knowledge/08-indonesian-slang-2026.md` + `slangLookup.ts` — validated Gen-Z slang with virality scores.

### High-Virality Terms (Use in Blog Content)

| Term | Virality | Meaning | Blog Usage Example |
|---|---|---|---|
| Stecu | 10/10 | Setuju (agree) | "Stecu gak sih kalau Laravel itu framework terbaik?" |
| Rizz | 10/10 | Charisma/charm | "Portfolio lo harus punya rizz biar dilirik recruiter" |
| Delulu | 10/10 | Delusional | "Delulu kalau mikir bisa jago coding tanpa praktek" |
| Slay | 10/10 | Nailed it | "Tutorial ini bakal bikin project lo slay" |
| Red flag | 9/10 | Warning sign | "5 red flag di code review yang sering dilewatin" |
| Cringe | 9/10 | Embarrassing | "Kesalahan coding cringe yang bikin senior geleng-geleng" |
| Healing | 9/10 | Self-care/relax | "Weekend healing buat developer: side project ringan" |
| Burnout | 9/10 | Exhaustion | "Cara handle burnout tanpa resign" |
| FOMO | 9/10 | Fear of missing out | "FOMO framework baru? Ini yang beneran worth it" |
| Toxic | 9/10 | Harmful/negative | "Kebiasaan coding toxic yang harus lo stop" |
| Flex | 8/10 | Show off | "Bukan flex, tapi portfolio gue dapat 10 interview" |
| Glow up | 8/10 | Transformation | "Glow up website dari basic ke premium" |
| Ghosting | 8/10 | Disappearing | "Client ghosting? Ini cara handle-nya" |
| Spill | 8/10 | Share tea/secrets | "Spill: tool AI gratisan yang jarang orang tau" |
| Vibes | 8/10 | Atmosphere | "Dark mode vibes buat portfolio developer" |
| Bucin | 8/10 | Budak cinta (love slave) | Use sparingly in blog — more for social media |
| No cap | 8/10 | No lie | "No cap, ini framework paling gampang dipelajari" |

### Mandatory Particles (Natural Indonesian Flow)

These particles MUST be used for natural Indonesian writing. Without them, text sounds robotic/translated:

| Particle | Function | Example |
|---|---|---|
| **sih** | Softener, "to be fair" | "Sebenernya sih gak susah-susah amat" |
| **tuh** | Pointing, emphasis | "Nah tuh kan, error-nya di situ" |
| **gitu** | "like that", casual | "Konsepnya gitu deh, simpel kan?" |
| **dong** | Persuasion, "come on" | "Coba dong pake framework ini" |
| **deh** | Assurance | "Pasti works deh kalau ikutin step-nya" |
| **nih** | "here", offering | "Nih gue kasih template gratis" |
| **kan** | Seeking agreement | "Kita semua pernah ngalamin kan?" |
| **banget** | "very", intensifier | "Tutorial ini helpful banget" |
| **parah** | Extreme intensifier | "Performance improvement-nya parah sih" |
| **bet** | Very (shortened banget) | "Cepet bet load-nya" |

### Code-Mixing Formula

> The dominant pattern in Indonesian tech content (85.3% insertion rate):

```
Formula: [Bahasa] + [English buzzword] + [Bahasa] + [particle]

Examples:
"Kita perlu setup environment dulu sebelum mulai coding."
"Lo harus deploy dulu ke staging baru push ke production."
"Framework ini bikin development workflow lo jadi smooth banget."
"Gue udah test, performance-nya improve parah sih."
```

### Pronoun Rules

| Use | Avoid | Context |
|---|---|---|
| **gue/gw** (I) | saya | Blog with casual tone |
| **lo** (you) | kamu, Anda | Direct address |
| **kita** (we, inclusive) | kami | Collaborative framing: "kita belajar bareng" |

**Exception:** Use "saya" only in formal About page or professional bio.

### Gen-Z Emoji Dictionary 2026

> **CRITICAL: These meanings differ from traditional emoji meanings.**

| Emoji | Gen-Z Meaning | NOT This | Blog Usage |
|---|---|---|---|
| 😭 | Laughing SO hard | Crying/sadness | "Ini errornya 😭" (= hilarious) |
| 💀 | DEAD from laughter | Death/danger | "Code review hasilnya 💀" |
| 😂 | **OUTDATED/Boomer** | Funny | **DO NOT USE in blog** |
| 👍 | **Passive-aggressive** | Approval/OK | **AVOID — use ✅ instead** |
| 🙂 | Masking discomfort | Happy | **AVOID — ambiguous** |
| 🔥 | Amazing/fire | Literal fire | "Tutorial ini 🔥" |
| ✨ | Sarcasm/emphasis | Magic | "✨Productivity✨" (ironic) |
| 💅 | Unbothered sass/slay | Nail polish | "Deploy tanpa bug 💅" |
| 🤡 | Self-deprecating | Clown | "Gue lupa git commit lagi 🤡" |
| 👀 | Interested/curious | Watching | "Ada update baru 👀" |

### Outdated Terms (INSTANT CRINGE — Never Use)

| Term | Why It's Dead | Replacement |
|---|---|---|
| alay | 2010s era | Just don't |
| lebay | Replaced by "dramatic" | dramatic, over |
| woles | Dead since 2018 | santai, chill |
| kids jaman now | Boomer phrase | Gen-Z, anak muda |
| LOL | Replaced by 💀😭 | 💀 or 😭 |
| Ciyus/Miapah | 2013 era | serius?, beneran? |

### Indonesian Hook Templates for Blog

> **Source:** SparkFluence `_shared/knowledge/08-indonesian-slang-2026.ts` — 5 hook types.

**1. Statistical Hook:**
```
"[X]% orang gak tau kalau [surprising fact]. Lo termasuk yang mana?"
"9 dari 10 developer masih [common mistake]. Ini data-nya."
```

**2. Warning Hook:**
```
"STOP [activity] sekarang juga sebelum [consequence]."
"Lo masih [old practice]? Red flag sih ini."
```

**3. POV Hook:**
```
"POV: Lo baru aja discover [tool/technique] dan semuanya berubah."
"POV: Code lo akhirnya clean setelah baca artikel ini."
```

**4. Curiosity Hook:**
```
"Gue nemu [something] yang bikin [result]. Tapi ada catch-nya..."
"Tool AI ini gratis tapi capability-nya setara yang bayar. Spill di bawah."
```

**5. Controversy Hook:**
```
"Unpopular opinion: [bold claim]. Hear me out."
"[Popular tool] itu overrated. Ini buktinya."
```

### Indonesian CTA Templates for Blog

```
Engagement: "Menurut lo gimana? Drop pendapat lo di comment!"
Save: "Save artikel ini buat reference nanti."
Share: "Share ke temen developer lo yang butuh ini."
Follow: "Follow blog ini biar gak ketinggalan update selanjutnya."
Action: "Yuk, langsung coba sekarang. Gue udah siapin template-nya."
```

### Indonesian Foreshadow Pattern

```
"[Preview content] + yang [terakhir/ketiga] paling [gila/unexpected]"
"Baca sampai habis karena tips terakhir itu game-changer."
"5 technique yang bakal gue bahas — nomor 4 paling unexpected."
```

---

## 5. Write-First Language Strategy

| Content Type | Write-First | Why |
|---|---|---|
| Technical tutorials, AI/ML posts | **English first** | Tech terms originate in EN; wider initial audience |
| Personal stories, ID market content | **Indonesian first** | Authentic voice matters; cultural nuance is primary |
| Portfolio/project descriptions | **English first** | International clients are primary audience |
| About page, CTA sections | **Write independently** | Brand content = transcreation, not translation |
| Indonesian community/event posts | **Indonesian only** | No need to translate hyper-local content |

---

## 6. Translation Workflow

### Recommended Process

```
1. Author writes in primary language
     ↓
2. AI-draft second language (Claude/GPT) → heavily edit for naturalness
     ↓
3. Quality gate: read aloud — if sounds "translated", rewrite
     ↓
4. SEO review: per-locale keyword optimization (different keywords per language)
     ↓
5. Publish both versions simultaneously
     ↓
6. Track sync status per translation
```

### Translation Status Tracking

Add `status` to post_translations table:

| Status | Meaning |
|---|---|
| `draft` | AI-generated draft, needs human review |
| `translated` | Human-reviewed translation |
| `reviewed` | Quality-checked, ready to publish |
| `published` | Live on site |
| `stale` | Source language updated, needs re-review |

### Sync Rules

- When EN version updated → ID translation status changes to `stale`
- When ID version updated → EN translation status changes to `stale`
- Dashboard shows stale translations count
- Never publish a stale translation without review

---

## 7. Per-Locale SEO Strategy

### Keyword Strategy

**English keywords:** Target global tech audience
- Long-tail: "how to build REST API with Laravel 12"
- Informational: "Vue 3 composition API best practices"
- Use tools: Ahrefs, SEMrush for English keyword research

**Indonesian keywords:** Target Indonesian tech audience
- Mixed-language queries are common: "tutorial Laravel bahasa Indonesia"
- Indonesian phrasing: "cara membuat website", "belajar coding", "AI untuk pemula"
- Tech terms stay English even in ID queries
- Use tools: Google Keyword Planner (set region to Indonesia)

### Per-Language Meta Tags

```
English post:
  meta_title: "How to Build a REST API with Laravel 12 | Ali Sadikin"
  meta_description: "Step-by-step guide to building a production-ready REST API..."
  focus_keyword: "laravel 12 rest api"

Indonesian post:
  meta_title: "Cara Membuat REST API dengan Laravel 12 | Ali Sadikin"
  meta_description: "Panduan langkah demi langkah membuat REST API production-ready..."
  focus_keyword: "cara membuat rest api laravel"
```

### Structured Data (Schema.org)

Both language versions need:
```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "...",
  "inLanguage": "en",
  "author": {
    "@type": "Person",
    "name": "Ali Sadikin",
    "url": "https://alisadikinma.com/en/about"
  },
  "datePublished": "...",
  "dateModified": "..."
}
```

Change `inLanguage` to `"id"` for Indonesian version. Change author URL to match locale.

---

## 8. GEO for Multi-Language Content

### What is GEO?

GEO (Generative Engine Optimization) = getting content **cited** in AI-generated responses (Google AI Overviews, ChatGPT Search, Perplexity). Unlike traditional SEO (ranking on page 1), GEO means being part of the answer itself.

### Key GEO Strategies (Research-Backed)

| Strategy | Impact | How to Apply |
|---|---|---|
| **Statistics Addition** | High | Include concrete data points every 150-200 words |
| **Quotation Addition** | High | Cite authoritative sources with direct quotes |
| **Cite Sources** | High | Reference specific studies, tools, version numbers |
| **Fluency Optimization** | Moderate | Clean, well-structured prose without filler |
| **Authoritative Tone** | Moderate | Write as expert, not summarizer |
| **Technical Terms** | Moderate | Use precise domain vocabulary |

### Indonesian GEO Opportunity

This is a significant first-mover advantage:
- **Google AI Mode officially supports Bahasa Indonesia** (September 2025)
- Indonesia is one of only **6 countries** with AI Mode (US, India, Japan, South Korea, Indonesia, Brazil)
- **Over 1/3 of Indonesians use ChatGPT monthly** — significant AI search adoption
- Indonesian-language content optimized for GEO has very low competition

### Bilingual GEO Strategy

1. **EN content** — Optimize for ChatGPT, Perplexity, Google AI Overviews globally
2. **ID content** — Focus on Google AI Overviews/AI Mode (strongest Indonesian support)
3. **Cross-language authority** — EN content builds global signals that benefit ID visibility, and vice versa
4. **Schema markup on both versions** — AI engines use structured data to understand content relationships
5. **Fact density** — Statistics every 150-200 words in both languages
6. **Direct answers first** — First 40-60 words of each section should directly answer the implied question
7. **Quotable passages** — Write 1-2 sentences per section that are self-contained, authoritative, and citation-worthy

---

## 9. Indonesian Digital Landscape

### Key Stats (2025-2026)

- **220+ million internet users** (~80% of population)
- **95%+ mobile access** — mobile-first is non-negotiable
- **180 million social media identities** (62.9% of population)
- **Average 21h 50min/week** on social media (3+ hours/day)
- **WhatsApp is #1 platform** (90% monthly active) — key content sharing channel
- **Digital ad spend: $3.64B** (52% of total ad spend), growing 8% YoY
- **70% of online retailers prioritize SEO**
- **3.1 million Indonesian developers on GitHub** — third-largest in APAC
- **213% growth** in public generative AI projects from Indonesian developers

### Indonesian Developer Platforms (Reference in Content)

- **Dicoding** (dicoding.com) — #1 developer hub, Indonesian-language courses
- **Petani Kode** (petanikode.com) — Popular coding tutorial blog
- **CodePolitan** (codepolitan.com) — Developer news and tutorials

---

## 10. SPA (Vue 3) SEO Solutions

### The Problem

Vue SPAs render client-side. Googlebot may not fully execute JavaScript, meaning hreflang tags and meta content injected via JS might not be processed.

### Solutions (Ranked by Recommendation)

**Option A: Dynamic Rendering (Best for current architecture)**

Least-disruptive for existing Vue 3 SPA + Laravel API:
1. Use Prerender.io (SaaS) or self-hosted Puppeteer/Rendertron
2. Laravel middleware detects bot user agents → serves pre-rendered HTML
3. Pre-rendered HTML includes proper hreflang, meta tags, OG tags
4. Regular users get the full SPA experience

**Option B: Vite-SSG (Static Site Generation)**

- Use `vite-ssg` to generate static HTML at build time
- Hreflang tags baked into HTML
- Works with vue-i18n for localized routes
- Caveat: requires rebuild when CMS content changes

**Option C: Nuxt 3 Migration (Most robust, highest effort)**

- Nuxt 3 + `@nuxtjs/i18n` provides automatic hreflang with `seo: true`
- SSR out of the box
- Automatic locale-aware routing
- Significant migration effort from plain Vue 3

### Recommendation for alisadikinma.com

- **Short-term:** Dynamic rendering (Prerender.io or Laravel + Puppeteer)
- **Long-term:** Evaluate Nuxt 3 migration if SEO becomes primary growth channel

---

## 11. Existing Infrastructure (Portfolio_v2)

### What's Already Built

| Component | Status | Multi-lang Ready? |
|---|---|---|
| `post_translations` table | Complete | Yes — stores per-language title, slug, content, SEO fields |
| `PostTranslation` model | Complete | Yes — unique constraint on [post_id, language] |
| `PostController` API | Complete | Yes — accepts `lang` query param and `Accept-Language` header |
| vue-i18n setup | Complete | Framework only — EN/ID locale files with 96 UI string keys |
| `useMetaTags.js` composable | Complete | Needs locale-aware updates |
| `usePosts.js` composable | Complete | Supports `lang` param in `fetchPost(slug, lang='en')` |
| API client (`api.js`) | Complete | Already sets `Accept-Language` header |
| Blog views (Blog.vue, BlogDetail.vue) | Complete | Not connected to translations yet |
| Admin BlogPostForm.vue | Complete | Has `translationsData` property, needs translation tabs UI |

### What Needs to Be Built

1. **Language switcher UI** in TheNavigation.vue
2. **Locale-aware routing** (`/en/blog/...`, `/id/blog/...`)
3. **Connect blog views to translations** — pass locale to API calls
4. **Admin translation tabs** — edit EN/ID content side by side
5. **Translation status tracking** — `status` field on post_translations
6. **Hreflang meta injection** — via useMetaTags.js composable
7. **Multilingual sitemap** — extend SitemapController
8. **Dynamic rendering for SEO** — Prerender.io or equivalent
