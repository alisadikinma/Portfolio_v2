# Blog Redesign — Social-Feed Discovery + Engagement Gates

**Date:** 2026-04-19
**Status:** Design approved — ready for `/gaspol-dev:gaspol-plan`
**Author:** Ali Sadikin (via gaspol-brainstorm)
**Scope:** 3 PRs, ~10–12 hrs total
**Notification email:** `aiagent@alisadikinma.com` (reply-to / from-address for newsletter confirmation)

---

## Design

### Context

Current state at [frontend/src/views/Blog.vue](../../frontend/src/views/Blog.vue) and [frontend/src/views/BlogDetail.vue](../../frontend/src/views/BlogDetail.vue):

- **Blog list (Blog.vue)** uses a uniform 3-col grid — no editorial rhythm, every article looks the same weight. The hero is a single large "featured post" card then identical 3-col cards below. No category discovery beyond flat pills.
- **Blog detail (BlogDetail.vue)** has reading-progress bar, TL;DR, share bar, author card, "Continue reading" at the **bottom**. Nothing mid-article. Readers who scroll past ~50% have zero engagement touchpoints until they finish the piece.
- **Newsletter** at [Blog.vue:388-391](../../frontend/src/views/Blog.vue#L388-L391) is a **3-line stub** that shows `toast.info('Coming soon — newsletter subscription is not yet available.')`. The backend API at [NewsletterController.php](../../backend/app/Http/Controllers/Api/NewsletterController.php) already works (validates, dedupes, creates row). The [useNewsletter.js](../../frontend/src/composables/useNewsletter.js) composable is fully wired to that API. Blog.vue just never imports it.

Brand positioning: "AI Generalist Expert" — mature but needs the visual energy of a modern content brand (Verge / Wired / Stratechery mobile feel, not TikTok).

### Direction (locked via user answers)

| Decision | Choice | Why |
|---|---|---|
| Blog list visual | **Social-feed discovery** | User: big visual cards, vertical scroll rhythm, story-chip categories. Will blend with editorial sophistication to avoid clash with mature AI positioning. |
| Mid-article engagement | **All 4 + promo slots** | Inline related posts + sticky TOC + inline newsletter CTA + featured project/award/CTA rotation. Sequenced by scroll depth so they never compete. |
| Newsletter scope | **Full funnel** | 4 touchpoints (bottom form wire-up + inline mid-article + floating banner + sticky footer bar) sharing one composable and dedup state. Target: 3-5x conversion over single form. |
| Rollout | **Phased in 3 PRs** | PR1 ships smallest + highest-value first (newsletter works). PR2 & PR3 are each independently revertable. |

### Design System (unchanged — ULTRA dark cinema)

```
--bg-deep: #050506        --accent-gold: #D4A843
--bg-elevated: #0C0C0F    --accent-cyan: #06B6D4
--fg-primary: #EDEDEF     --accent-indigo: #5E6AD2
--fg-muted: #8A8F98

Fonts: Space Grotesk (display), Inter (body),
       JetBrains Mono (labels), Playfair Display italic (pull quotes)

Effects: .glass-card, .gradient-border, .text-gradient,
         .glow-gold, .btn-gold, .btn-glass
```

No new tokens. All new components consume the existing system.

---

## PR1 — Newsletter Full Funnel (~2–3 hrs, smallest, ship first)

### Changes

**Swap the stub at [Blog.vue:388-391](../../frontend/src/views/Blog.vue#L388-L391)** from `toast.info(...)` to real API call via existing `useNewsletter()` composable. One-line root fix + 3 new components for extra touchpoints.

### Components (NEW — all under [frontend/src/components/blog/](../../frontend/src/components/blog/))

| Component | Purpose | Used by |
|---|---|---|
| `NewsletterInlineCard.vue` | Compact card-style form (~400px wide), brand copy "Enjoying this? One essay per Friday on AI + engineering." | Blog.vue list (between rows), BlogDetail.vue (mid-article at ~50% scroll) |
| `NewsletterFloatingBanner.vue` | Bottom-right sticky card, auto-appears after 60s on BlogDetail, dismissible with ✕, respects localStorage | BlogDetail.vue |
| `NewsletterFooterBar.vue` | Sticky bottom bar (~64px tall), dismissible, appears on Blog.vue after user scrolls past 60% of list | Blog.vue |

All 3 call the same [useNewsletter.js](../../frontend/src/composables/useNewsletter.js) composable — no new state management.

### Shared state (via composable additions)

Extend `useNewsletter.js` with:

```js
// Persistent state across touchpoints
const DISMISS_TTL_DAYS = 7
const STORAGE_DISMISSED = 'nl_dismissed_at'
const STORAGE_SUBSCRIBED = 'nl_subscribed_email'

function isDismissed() { /* respects 7-day TTL */ }
function isSubscribed() { /* checks localStorage */ }
function markDismissed() { /* sets timestamp */ }
function markSubscribed(email) { /* persists after success */ }
```

Rule: if `isSubscribed()` OR `isDismissed()`, **no** touchpoint renders. One subscribe OR one dismiss silences all 4 placements for that visitor.

### Success UX (all touchpoints)

- Inline animated checkmark (fades in after 201 response)
- Swap form → "You're in! Check your inbox." (stays for 3s, then component fades out)
- Duplicate (409) → "You're already subscribed ✓" (friendly, not error-styled)
- Network error → inline red hint, form stays editable
- On success: `markSubscribed(email)` → all other placements auto-hide

### Rate limiting (backend addition)

[routes/api.php](../../backend/routes/api.php) — add `throttle:3,60` to `/newsletter/subscribe` (3 attempts per 60 min per IP). Prevents form spam.

### Reply-to configuration

Backend Newsletter notifications should use `aiagent@alisadikinma.com` as `MAIL_FROM_ADDRESS`. No new code — just `.env` update on VPS.

---

## PR2 — Blog.vue Social-Feed Redesign (~4–5 hrs, medium)

### Layout rhythm (replaces current uniform 3-col grid)

```
[Hero header — preserved, slightly lifted type]
[Category story-chips — horizontal scroll with thumbnails, NEW]
[Featured BigCard — 65vh, dominates fold, NEW]
[Row 1 — WideCard (single post, 2-col: image | text)]
[Row 2 — 3× SmallCard]
[Row 3 — TallCard + 2× SmallCard stacked]
[Row 4 — QuoteCard (uses post.ai_summary, if present)]
[Row 5 — 3× SmallCard]
[...cycle through distribution rules until posts exhausted]
[Newsletter footer bar (sticky, from PR1)]
[Bottom Newsletter card — already exists, wired in PR1]
```

### Components (NEW — under [frontend/src/components/blog/](../../frontend/src/components/blog/))

| Component | Purpose |
|---|---|
| `BlogCategoryChips.vue` | Horizontal CSS-scroll-snap row with circular thumbnails (use `category.icon` or fallback to first letter). Selected state = gold ring + scale-up. |
| `BlogHeroCard.vue` | Full-width card (65vh), cinematic image + overlay gradient (bg-deep → transparent), huge `font-display` headline, category tag, reading time. |
| `BlogWideCard.vue` | 2-col editorial layout (md:grid-cols-2), image left / rich content right (title, excerpt, meta). |
| `BlogTallCard.vue` | Portrait 9:16 card for left-column emphasis. Image top, compact text bottom. |
| `BlogSmallCard.vue` | Extracted from current Blog.vue 3-col card. Minimal refactor. |
| `BlogQuoteCard.vue` | Playfair Display italic pull-quote styled card. Uses `post.ai_summary` if set, otherwise `post.excerpt` truncated. No image — pure typography. |
| `BlogFeedDistributor.vue` | Layout orchestrator. Takes `posts[]`, emits `<slot>` per card variant based on index rules. Isolates grid logic from Blog.vue. |

### Card distribution algorithm

```js
// Inside BlogFeedDistributor
posts.forEach((post, i) => {
  if (i === 0)                         return 'hero'      // feature
  if (i === 1)                         return 'wide'      // editorial
  if (i % 7 === 2 && post.ai_summary)  return 'quote'     // breathing room
  if (i % 5 === 3)                     return 'tall'      // vertical rhythm
  return 'small'                                           // default
})
```

Result: every 5-7 cards gets a visual "beat" (wide, quote, tall) — readers get variety, no monotony.

### Mobile

- Stacks to single column
- Category chips stay horizontal (already touch-scrollable)
- BlogHeroCard → 50vh on mobile
- BlogWideCard → stacks image-over-text
- BlogQuoteCard → smaller type, still has editorial feel

### Animation discipline

- No aurora on list page (reserved for BlogDetail hero)
- Card hover: `translate-y-[-2px]` + subtle border glow only, no bounce
- Image hover: `scale-105` over 700ms (existing pattern)
- Category chip hover: glow + thumbnail scale 1.08
- Respects `prefers-reduced-motion`

---

## PR3 — BlogDetail.vue Engagement (~4–5 hrs, largest)

### Layout (desktop)

```
[Reading progress bar — existing]
[Hero header — existing]
[Featured image — existing]

┌─────────────┬──────────────────────────────┐
│ Left rail   │ Article body                 │
│ (sticky)    │                              │
│             │ [TL;DR box — existing]       │
│ TOC (NEW)   │ [Excerpt — existing]         │
│             │                              │
│ ● Intro     │ <Intro paragraphs>           │
│ ○ Section 1 │                              │
│ ○ Section 2 │ ## Section 1                 │
│ ○ Section 3 │ <paragraphs>                 │
│ ○ Section 4 │                              │
│ ○ Wrap      │ ┌─ BlogInlinePromoCard ───┐  │ ← after H2 #1
│             │ │ Featured Project...     │  │
│ 43% read    │ └─────────────────────────┘  │
│             │                              │
│             │ ## Section 2                 │
│             │ <paragraphs>                 │
│             │                              │
│             │ ┌─ NewsletterInlineCard ──┐  │ ← after midpoint H2
│             │ │ Enjoying this? ...      │  │
│             │ └─────────────────────────┘  │
│             │                              │
│             │ ## Section 3                 │
│             │ <paragraphs>                 │
│             │                              │
│             │ ┌─ BlogInlineRelatedPosts ┐  │ ← after last H2
│             │ │ You might also like     │  │
│             │ └─────────────────────────┘  │
│             │                              │
│             │ ## Wrap-up                   │
│             │ <paragraphs>                 │
└─────────────┴──────────────────────────────┘

[Tags — existing]
[Share bar — existing]
[Author card — existing]
[FAQ accordion — existing]
[Continue reading — existing]
[Floating newsletter banner — NEW, from PR1, 60s delay]
```

### Components (NEW — under [frontend/src/components/blog/](../../frontend/src/components/blog/))

| Component | Purpose |
|---|---|
| `StickyTOC.vue` | Extracts H2/H3 from rendered content, shows ordered list with dots (● active, ○ unvisited), scroll-to-section on click, IntersectionObserver highlights active section as reader scrolls. Reading % indicator at bottom. |
| `BlogInlinePromoCard.vue` | Rotates between: featured project / latest award / generic "Work with me" CTA. Small (~200px tall), right-aligned accent border, image + headline + sub + button. |
| `BlogInlineRelatedPosts.vue` | 2-card mini-grid using existing `relatedPosts` data (already fetched in BlogDetail). Thumbnail + title + category. Links to siblings, not new tabs. |
| `BlogContentInjector.vue` | Parses `post.content` HTML, splits by `<h2>`, renders array-of-chunks with injection points between them. Replaces current `v-html="contentWithoutFaq"`. |

### Injection strategy (BlogContentInjector)

```js
// Pseudocode
const chunks = splitHtmlByH2(post.content)
// chunks = [before-first-h2, h2+body, h2+body, h2+body, ...]

const injectionPoints = [
  { afterChunkIndex: 1, component: 'BlogInlinePromoCard' },  // after H2 #1
  { afterChunkIndex: Math.floor(chunks.length / 2), component: 'NewsletterInlineCard' },
  { afterChunkIndex: chunks.length - 2, component: 'BlogInlineRelatedPosts' },
]

// Render with Vue's h() so injected components are real Vue, not v-html strings
```

Edge cases:
- **Article has <3 H2s?** Skip injections 2 and 3 gracefully. Short posts keep minimal interruptions.
- **Article has no H2s?** No injections. Just raw content + bottom engagement.
- **HTML parse fails?** Fall back to current `v-html="contentWithoutFaq"` render. Never crash.

### StickyTOC behavior

```
Desktop (≥lg): fixed left rail, 280px wide, sticky at top-24, scrolls with article
Tablet (md):   collapses to top floating button, expands to modal
Mobile (sm):   hidden (reading progress bar at top is enough)

Active section detection:
  IntersectionObserver with rootMargin '-20% 0px -70% 0px'
  Highlights H2 closest to top 20% of viewport
  Updates reading percentage every 100ms (debounced scroll)
```

### BlogInlinePromoCard — rotation priority

```
1. If setting('blog_promo_project_id') is set AND exists → show that project
2. Else if project tagged "featured" exists → show latest featured project
3. Else if Award created within last 90d exists → show latest award
4. Else → generic "Work with me →" CTA linking to /contact
```

Data source: single new endpoint `GET /api/blog/promo-slot` on backend that returns the resolved payload:

```json
{
  "type": "project",  // or "award" | "cta"
  "title": "...",
  "description": "...",
  "image": "...",
  "link": "/projects/...",
  "cta_label": "View case study"
}
```

This avoids 3 separate API calls on every blog detail render + centralizes rotation logic.

---

## Data Integration Map

| Component / feature | Data source | Existing? | Notes |
|---|---|---|---|
| Newsletter subscribe | `POST /api/newsletter/subscribe` | ✅ Working | Already validates + dedupes (409 on duplicate) |
| Newsletter unsubscribe | `DELETE /api/newsletter/unsubscribe` | ✅ Working | `useNewsletter.js` already wires it |
| Newsletter rate-limit | `throttle:3,60` middleware | ⚠️ NEW | Add to routes group in api.php |
| Posts list | `GET /api/posts` | ✅ Working | `usePosts()` composable, TanStack cached 5min |
| Categories | `GET /api/categories` | ✅ Working | Already fetched in Blog.vue onMounted |
| Related posts | `GET /api/posts?category_id=X` | ✅ Working | Already in BlogDetail `fetchRelatedPosts()` |
| Featured projects | `GET /api/projects?featured=1` | ❓ | May need `featured` boolean column on projects (check schema) |
| Latest award | `GET /api/awards` | ✅ Working | Public endpoint exists |
| Site settings | `GET /api/settings/site` | ✅ Working | For CTA copy fallback |
| Blog promo config | `settings` table `key='blog_promo_project_id'` | ⚠️ NEW | Add via settings UI; graceful null fallback |
| Promo slot resolver | `GET /api/blog/promo-slot` | ⚠️ NEW | New controller action + route |
| AI summary for quote card | `post.ai_summary` | ✅ Working | Already in PostResource, populated by article-score |

**No placeholders. No mock data. All components work with real backend.**

---

## Performance Considerations

- **BlogContentInjector HTML parsing**: parse once on mount, memoize via `computed(() => splitHtmlByH2(post.content))`. No re-parse on scroll.
- **StickyTOC IntersectionObserver**: single observer instance watching all H2 elements (not N observers). Detach on unmount.
- **Floating banner**: 60s `setTimeout` cleared on subscribe, dismiss, or unmount. No leaks.
- **Card variant components**: each under 8KB gzipped. No lazy-loading needed but can add `defineAsyncComponent` if bundle watch flags it.
- **Category chips**: pure CSS scroll-snap, no JS scroll handler.
- **Blog.vue bundle impact**: estimated +12–15KB gzipped for 7 new components. Acceptable.
- **Promo slot endpoint**: cache for 60s server-side (award/project don't change often). Single query per visitor session.

---

## Accessibility

- `StickyTOC`: `<nav role="navigation" aria-label="Article outline">` + `<ol>` with `<a>` anchors. Keyboard navigable (Tab + Enter).
- Skip-to-content link added to BlogDetail (jumps past TOC to main article).
- All card components keyboard-activatable (Enter/Space triggers router push).
- `NewsletterFloatingBanner`: focus trap when visible, `Esc` to close, `aria-live="polite"` on success message.
- `NewsletterFooterBar`: announces dismissal to screen reader via `aria-live`.
- `prefers-reduced-motion: reduce` → disables card hover lifts, aurora blobs, category chip glow, card animations.
- Color contrast: all gold-on-dark / cyan-on-dark combos verified against WCAG AA (existing tokens already pass).

---

## Anti-AI-Slop Self-Check

Avoided:

- ❌ "Subscribe to our newsletter" → ✅ "One essay per Friday on AI + engineering. No spam."
- ❌ Purple/blue gradient hero → ✅ preserves ULTRA gold/cyan
- ❌ Heavy drop shadows → ✅ dark-cinema flat + hairline borders (1px rgba white 0.05–0.08)
- ❌ Emoji spam in CTAs → ✅ disciplined (one ✨ on subscribe headlines max)
- ❌ "Lorem ipsum" / placeholder copy → ✅ brand-voice copy authored below
- ❌ Stock-photo dependency → ✅ all featured images come from existing post.featured_image
- ❌ Generic "Learn more →" → ✅ specific copy per context ("Read case study" / "View award" / "Work with me")
- ❌ Endless variety with no anchor → ✅ clear hierarchy: Hero → Wide → (distributed) small/tall/quote

### Authored copy snippets

Newsletter touchpoints:

```
Bottom card (Blog.vue):
  Eyebrow:    "Stay in the loop"
  Headline:   "Get the latest articles"
  Sub:        "Thoughtful pieces on AI, engineering, and the future of work."
  CTA:        "Subscribe"
  Fine-print: "No spam. Unsubscribe anytime."

Inline card (both pages, mid-article):
  Eyebrow:    "✨ One email, every Friday"
  Headline:   "Enjoying this?"
  Sub:        "Get the next essay like this one, plus behind-the-scenes notes on what I'm building."
  CTA:        "Subscribe"
  (No fine-print — saves height mid-article)

Floating banner (BlogDetail, 60s):
  Headline:   "Before you go —"
  Sub:        "One essay per Friday on AI + engineering. No spam."
  CTA:        "Subscribe"
  Close:      "Maybe later"

Sticky footer bar (Blog.vue, after 60% scroll):
  Headline:   "Liked what you read? Get a new essay every Friday."
  CTA:        "Subscribe →"
  Close:      "✕"
```

Promo card rotation copy:

```
Type: project
  Eyebrow:    "Case study"
  Headline:   [project.title]
  Sub:        [project.excerpt || project.short_description]
  CTA:        "Read case study →"

Type: award
  Eyebrow:    "Recognition"
  Headline:   [award.title]
  Sub:        "Recent award from [award.organization]"
  CTA:        "See details →"

Type: cta (fallback)
  Eyebrow:    "Let's build"
  Headline:   "Working on something with AI?"
  Sub:        "I help founders ship AI products without the complexity overhead."
  CTA:        "Work with me →"  (links to /contact)
```

---

## Open Questions for Implementation Planning

1. **Featured project selection**: admin-selectable per-post meta field OR one global setting? **Default: global setting** (`blog_promo_project_id`) for simplicity. Per-post can come later.
2. **StickyTOC fixed vs in-flow**: **Default: fixed**, respects container max-width via sticky offset + top-24.
3. **Mobile floating banner trigger**: 60s timer OR exit-intent? **Default: 60s timer on both mobile+desktop.** Exit-intent on mobile is unreliable (no mouse-leave).
4. **Rate limiting value**: `throttle:3,60` or stricter? **Default: 3 per 60min per IP.** Adjust if spam observed.
5. **Projects `featured` column**: exists already? Need to `describe projects` table. If not, add in PR3 migration.
6. **QuoteCard distribution**: only when `post.ai_summary` is populated — many older posts lack this. Graceful fallback: swap in another Small/Tall variant if pool runs out.
7. **Dark mode only?** Current site is dark-only by design. No light-mode variants needed for new components.

---

## Success Metrics (post-launch)

| Metric | Baseline | Target |
|---|---|---|
| Newsletter conversions | 0 (stub) | 3-5x of prior working baseline once historical comparable is available |
| Blog detail scroll depth (p50) | unknown — instrument | +25% after PR3 |
| Blog detail avg time-on-page | unknown — instrument | +30% after PR3 |
| Blog list bounce rate | current Plausible/GA value | -15% after PR2 |
| Inline-related-post CTR vs bottom-related CTR | n/a | measure which wins |

Add basic Plausible / GA event tracking for:
- `newsletter_subscribe` (source: bottom / inline / floating / footer)
- `promo_card_click` (type: project / award / cta)
- `related_post_click` (placement: inline-midarticle / bottom)
- `toc_click` (section index)

---

## Risks & Rollback

| Risk | Likelihood | Mitigation | Rollback |
|---|---|---|---|
| BlogContentInjector breaks on malformed HTML | Low | Try/catch + fallback to v-html | Revert PR3 only; PR1/PR2 unaffected |
| StickyTOC layout shift on slow networks | Low | Reserve width with `min-w-[280px]` placeholder while loading | CSS-only fix, no revert needed |
| Floating banner feels aggressive | Med | 7-day dismiss TTL + only 1 fire per session | Set `setTimeout` to 120s or disable via feature flag |
| Promo card 404 if project deleted | Low | Backend resolver falls back to next priority | No rollback, graceful by design |
| Newsletter spam after rate limit bypass | Low | Add Cloudflare Turnstile in v2 if observed | Backend-only addition |
| Social-feed layout overwhelms on small screens | Low | Mobile-first design + tested breakpoints | Tailwind utilities, easy iteration |

**Rollback strategy per PR:**

- PR1 revert → back to stub toast (inconvenient but no broken state)
- PR2 revert → back to current 3-col grid (no data loss)
- PR3 revert → back to current BlogDetail structure (no data loss)

Each PR is independently revertable via `git revert <sha>`.

---

## Summary

This redesign transforms the blog from **uniform / corporate** → **editorial social-feed**, adds **mid-article engagement gates** that respect reading flow, and ships a **working newsletter funnel** that replaces a 3-line stub.

The expensive-feeling part (sticky TOC, 7 new card variants, content injection) is all incremental on top of the existing ULTRA design system. No new tokens, no new dependencies, no backend rewrites. Just **wiring what's already there** + **visual rhythm** + **engagement seeded at the right scroll depths**.

**Next step:** Run `/gaspol-dev:gaspol-plan` to append the `## Implementation Plan` section with per-PR file-level changes, verification criteria, and test plans.
