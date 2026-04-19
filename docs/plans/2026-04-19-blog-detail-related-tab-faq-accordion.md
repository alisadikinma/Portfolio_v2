# Blog Detail — Related-Tab + FAQ Accordion

**Date:** 2026-04-19
**Status:** Design approved, ready for implementation plan
**Scope:** Frontend-only (BlogDetail.vue + 1 new component)
**Risk:** Low — no backend, no schema, no API changes

## Design

### Problem

Two small UX gaps on the blog detail page:

1. **Related post clicks lose reading context.** The "Continue reading" grid uses `<router-link>` which navigates in the same tab. A reader scanning related articles loses their scroll position on the current article and can't easily compare both.
2. **FAQ section is static.** The article pipeline emits FAQs as plain `h2 → h3/h4 + p` pairs inside the content HTML. Users must scroll through 2-4 full Q&A blocks regardless of what they actually want to read. No visual affordance that items are scannable.

### Constraints (from CLAUDE.md)

- Vue 3.5 Composition API, `<script setup>`, Tailwind CSS 4
- Dark Cinema design tokens: `bg-bg-elevated`, `accent-gold`, `accent-cyan`, `.glass-card`
- Article body rendered via `v-html="post.content"` — we don't own the DOM ownership for Vue reactivity inside
- SEO: must keep all FAQ text in the DOM (crawlable), do not remove schema markup
- No server-side changes — `faq_schema` is already populated by `article-write` plugin

### Approach

#### Feature 1: Related Posts → New Tab

Change the `<router-link>` in the "Continue reading" grid at [BlogDetail.vue:234-263](frontend/src/views/BlogDetail.vue#L234-L263) to open in a new browser tab.

```vue
<router-link
  v-for="related in relatedPosts"
  :key="related.id"
  :to="`/${lang}/blog/${related.slug}`"
  target="_blank"
  rel="noopener noreferrer"
  class="group rounded-xl overflow-hidden bg-bg-elevated/50 border border-white/5 hover:border-accent-gold/20 transition-all duration-300 relative"
>
  <!-- existing card body -->

  <!-- New: external-link hint, visible on group-hover -->
  <div class="absolute top-3 right-3 w-7 h-7 rounded-full bg-bg-deep/70 backdrop-blur-sm border border-white/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
    <svg class="w-3.5 h-3.5 text-accent-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
  </div>
</router-link>
```

**Why `<router-link target>`:** Vue Router 4 passes `target` and `rel` through to the underlying `<a>` element. No need to drop to a raw anchor (which would lose SPA prefetch benefit on hover).

**Why the hover icon:** Subtle signal that click leaves context — helps set user expectation and matches Dark Cinema aesthetic (gold accent, glass pill).

#### Feature 2: FAQ Accordion

Three moving parts.

**A. Computed structured FAQ from existing schema**

`post.seo.faq_schema.mainEntity[]` is populated by `article-write` plugin (confirmed via [SKILL.md:208-227](D:/Projects/claude-plugin/article-content-writer/skills/article-write/SKILL.md)). Shape:

```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    { "@type": "Question", "name": "Apakah Gemini 3 bisa diakses secara gratis?",
      "acceptedAnswer": { "@type": "Answer", "text": "Ya. Gemini 3 tersedia..." } }
  ]
}
```

Map to a simple array:

```js
const faqItems = computed(() => {
  const entities = post.value?.seo?.faq_schema?.mainEntity
  if (!Array.isArray(entities) || entities.length === 0) return []
  return entities
    .filter(e => e?.name && e?.acceptedAnswer?.text)
    .map(e => ({
      question: e.name,
      answer: e.acceptedAnswer.text,
    }))
})
```

**B. Strip FAQ section from content HTML**

Use `DOMParser` (native, no new deps) to find the FAQ h2 and remove it + subsequent siblings until the next h2.

```js
const FAQ_HEADING_RE = /^\s*(faq|frequently\s+asked|pertanyaan(\s+yang\s+sering\s+ditanya)?)/i

const contentWithoutFaq = computed(() => {
  const html = post.value?.content
  if (!html || faqItems.value.length === 0) return html

  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html')
  const root = doc.body.firstElementChild
  const faqH2 = Array.from(root.querySelectorAll('h2'))
    .find(h => FAQ_HEADING_RE.test(h.textContent))

  if (!faqH2) return html // graceful — FAQ only in schema, not in HTML

  // Remove FAQ h2 and every following sibling until next h2 (or end)
  let node = faqH2
  while (node) {
    const next = node.nextElementSibling
    node.remove()
    if (next && next.tagName === 'H2') break
    node = next
  }
  return root.innerHTML
})
```

**Why DOMParser over regex:** FAQ answers may contain HTML (bolds, links, lists); regex is fragile on nested markup. DOMParser is native to every browser we target.

**Graceful degradation:**
- `faqItems` empty → accordion doesn't render, original HTML untouched
- FAQ in HTML but not in schema → accordion doesn't render, original HTML untouched
- FAQ in schema but not in HTML → accordion renders, HTML untouched

**C. New component `FaqAccordion.vue`**

Glass-card items, gold chevron, first item open by default, keyboard a11y.

```vue
<!-- frontend/src/components/blog/FaqAccordion.vue -->
<script setup>
import { ref } from 'vue'

const props = defineProps({
  items: { type: Array, required: true },
})

const openIndex = ref(0) // first item open by default

function toggle(i) {
  openIndex.value = openIndex.value === i ? -1 : i
}

function onKey(e, i) {
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    toggle(i)
  }
}
</script>

<template>
  <section
    v-if="items.length"
    class="container-custom mb-16"
    aria-label="Frequently asked questions"
  >
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-4 mb-8">
        <p class="mono-label text-accent-gold">FAQ</p>
        <div class="flex-1 h-px bg-white/5"></div>
      </div>

      <ul class="space-y-3">
        <li
          v-for="(item, i) in items"
          :key="i"
          class="rounded-xl bg-bg-elevated/50 border border-white/5 transition-colors duration-200"
          :class="openIndex === i && 'border-accent-gold/25 bg-accent-gold/[0.03]'"
        >
          <button
            type="button"
            class="w-full flex items-start justify-between gap-4 p-5 text-left"
            :aria-expanded="openIndex === i"
            :aria-controls="`faq-answer-${i}`"
            @click="toggle(i)"
            @keydown="onKey($event, i)"
          >
            <span class="font-display font-semibold text-fg-primary text-base md:text-lg leading-snug">
              {{ item.question }}
            </span>
            <svg
              class="w-5 h-5 flex-shrink-0 mt-0.5 text-accent-gold transition-transform duration-300"
              :class="openIndex === i && 'rotate-180'"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <div
            :id="`faq-answer-${i}`"
            class="grid transition-[grid-template-rows] duration-300 ease-out"
            :class="openIndex === i ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
          >
            <div class="overflow-hidden">
              <p class="px-5 pb-5 text-fg-muted text-[15px] leading-relaxed">
                {{ item.answer }}
              </p>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
```

**Why grid-rows animation:** `max-height` animation requires arbitrary pixel guess. `grid-template-rows: 0fr → 1fr` is the modern height-auto animation pattern — smooth, no JS measurement, works with dynamic content height.

**Keyboard a11y:** `<button>` is natively focusable, Enter/Space trigger handled by native button + explicit `onKey` fallback. `aria-expanded` + `aria-controls` + region label per WAI-ARIA accordion pattern.

**SEO note:** Even when collapsed, answer `<p>` stays in DOM (just visually hidden via grid rows collapse). `overflow-hidden` wrapper hides content — but because grid rows is `0fr`, text has zero height, matching visually-hidden semantics without `display:none`. Crawlers + screen readers still see all content.

#### Integration in BlogDetail.vue

```vue
<!-- Replace: -->
<div v-if="post.content" class="blog-content" v-html="post.content"></div>

<!-- With: -->
<div v-if="contentWithoutFaq" class="blog-content" v-html="contentWithoutFaq"></div>
<!-- (accordion rendered below, outside article body div, before Related Posts) -->
<FaqAccordion :items="faqItems" />
```

FAQ accordion sits BETWEEN article body and "Continue reading" section — matches the reading flow (article → FAQ → related posts → end).

### Data Integration Map

| Component / Change | Data Source | Existing? | Notes |
|---|---|---|---|
| Related post `target="_blank"` | Current `relatedPosts` ref | ✅ | No new data |
| Related post hover ↗ icon | Static SVG | ✅ | Tailwind utility only |
| `faqItems` computed | `post.seo.faq_schema.mainEntity` | ✅ | Pipeline already populates |
| `contentWithoutFaq` computed | `post.content` HTML + DOMParser | ✅ | Client-side parse, no API |
| `FaqAccordion.vue` | `faqItems` prop | ❌ NEW | Single-file component |

### Anti-AI-slop self-check

- ✅ No emoji pellet spam in SVG (single chevron icon)
- ✅ No generic "awesome/amazing" copy — mono-label uses existing `"FAQ"` text
- ✅ Reuses existing tokens (`bg-bg-elevated/50`, `border-white/5`, `text-accent-gold`) — not inventing colors
- ✅ Animation uses grid-rows technique (industry-current, not `max-height: 1000px` hack)
- ✅ A11y: aria-expanded + aria-controls + native button semantics

### Implementation Feasibility

All data exists, all tokens exist, no placeholder risk:
- ✅ `post.seo.faq_schema.mainEntity` populated by pipeline on every generated article
- ✅ `DOMParser` is native browser API, no polyfill, no new dep
- ✅ Tailwind tokens all present in existing `index.css` / design system
- ✅ No route changes, no auth changes, no DB changes

### Test Matrix (manual, post-implementation)

1. **Related post new tab** — click a card in "Continue reading", confirm new tab opens, original page still visible
2. **FAQ renders** — load any article with `faq_schema` populated (e.g. `/id/blog/10-best-vibe-coding-tools-2026-untuk-developer-modern`), confirm accordion appears below article body
3. **First item expanded** — on load, first FAQ answer visible, chevron rotated, card has gold border
4. **Toggle behavior** — click question, current closes, clicked one opens (single-open mode)
5. **Click-to-collapse** — click an already-open question, it collapses
6. **Keyboard a11y** — Tab to FAQ question, Enter toggles, screen reader announces "expanded/collapsed"
7. **Graceful degradation** — article without FAQ (e.g. short draft), accordion section doesn't render, no console error
8. **HTML stripped from body** — view source after mount, FAQ h2 + content removed from v-html, only in accordion markup

### Out of scope

- Search inside FAQ — not needed for 2-4 items
- "Expand all / Collapse all" button — not requested, YAGNI
- Multi-open mode (accordion where multiple can be open) — single-open is the UX standard
- Deep-linking to specific FAQ item via hash — can add later if analytics show demand
- Backend FAQ editor — `faq_schema` already editable via admin post edit (JSON field)

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship two frontend-only UX improvements to the blog detail page: (1) open related-post clicks in a new tab so readers don't lose context, and (2) render FAQ sections as an interactive expand/collapse accordion using the structured `faq_schema` already populated by the article-write pipeline. Zero backend changes. Zero new dependencies. Graceful degradation when FAQ absent.

### Architecture Context (from CLAUDE.md)

**Relevant files already in repo:**
- [frontend/src/views/BlogDetail.vue](frontend/src/views/BlogDetail.vue) — single touch point for both features
- [frontend/src/utils/imagePositioning.js](frontend/src/utils/imagePositioning.js) + [imagePositioning.test.mjs](frontend/src/utils/imagePositioning.test.mjs) — precedent for pure ES module utility with `.mjs` smoke test
- [backend/app/Http/Resources/PostResource.php:60](backend/app/Http/Resources/PostResource.php#L60) — exposes `seo.faq_schema` on every post response (already shipped)
- [D:/Projects/claude-plugin/article-content-writer/skills/article-write/SKILL.md:208-227](D:/Projects/claude-plugin/article-content-writer/skills/article-write/SKILL.md) — pipeline contract for `faq_schema` shape (`mainEntity[].name` + `mainEntity[].acceptedAnswer.text`)

**Design system tokens in use:**
- `bg-bg-elevated/50`, `border-white/5` — card surface (matches Continue Reading cards)
- `text-accent-gold`, `border-accent-gold/25` — active/expanded accent
- `font-display` (Space Grotesk), `mono-label` (JetBrains Mono uppercase)
- `.container-custom`, `.max-w-3xl mx-auto` — matches article body column width

**Test tooling:**
- Playwright for e2e ([frontend/tests/](frontend/tests/))
- Pure `.mjs` smoke tests for ES module utilities (no Vitest in repo)
- No jsdom dep — pure-string helpers preferred

### Tech Stack

- Vue 3.5 `<script setup>`
- Tailwind CSS 4 utilities only (no new CSS files)
- Vue Router 4 (uses `target` passthrough on `<router-link>`)
- Native `DOMParser` in component + pure regex in test-friendly helper

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Related-post `target="_blank"` | `relatedPosts` ref in BlogDetail.vue | `fetchRelatedPosts()` already defined | Yes | Add 2 attrs to existing `<router-link>` |
| Related-post hover ↗ icon | Static SVG, Tailwind utils | n/a | Yes | Inline SVG inside card |
| `faqItems` computed | `post.value.seo.faq_schema.mainEntity` | `usePosts().post` ref | Yes | Map in `computed()` |
| `contentWithoutFaq` computed | `post.value.content` HTML | Component-local `stripFaqSection()` helper | No | Create pure helper + `.mjs` smoke test |
| `FaqAccordion` UI | `faqItems` prop | n/a | No | Create new SFC |
| Tailwind tokens | `bg-bg-elevated`, `accent-gold`, etc. | `frontend/src/assets/styles/main.css` | Yes | Use existing tokens |

**Executor contract:** If any "Yes" row turns out to be missing during execution, STOP and ask. Do not fabricate fallback data.

### Phase Overview

| Phase | Code Deliverable | Design Deliverable | Verification | Est. time |
|-------|------------------|--------------------|--------------|-----------|
| A | `stripFaqSection.js` pure helper | n/a (pure logic) | `.mjs` smoke test passes | 8 min |
| B | `FaqAccordion.vue` SFC | Glass card + gold chevron + first-open default (see §Approach in same file) | Renders with fixture items, toggle works in browser | 12 min |
| C | `BlogDetail.vue` integration | Related-post `target="_blank"` + hover ↗ icon + FAQ accordion mount (see §Approach) | Dev server: related link opens new tab; FAQ accordion renders + toggles | 10 min |
| D | Manual QA + optional Playwright smoke | n/a | 8-item test matrix from §Test Matrix passes | 10 min |

Total: ~40 min across 4 bite-sized phases.

---

### Phase A: Pure `stripFaqSection` helper with TDD smoke test

**Estimated time:** 8 minutes

**Files:**
- Create: `frontend/src/utils/stripFaqSection.js`
- Test: `frontend/src/utils/stripFaqSection.test.mjs`

**Why pure regex (no DOMParser):** Test runs in plain Node (`node *.test.mjs` per precedent). DOMParser is browser-only; jsdom would be a new dep. The pipeline's FAQ markup shape is stable (`<h2>` heading containing "FAQ"/"Pertanyaan"/"Frequently" text, followed by Q&A blocks until next `<h2>` or end), so a narrow regex is safe.

**Steps:**
1. Write failing test for `stripFaqSection`. Expected error: `SyntaxError: Cannot find module './stripFaqSection.js'` (or `TypeError: stripFaqSection is not a function` on the import).
2. Run `node frontend/src/utils/stripFaqSection.test.mjs`, confirm it fails for the expected reason.
3. Implement `stripFaqSection.js` — exports `stripFaqSection(html)`:
   - Returns `html` unchanged if not a non-empty string
   - Matches `/<h2[^>]*>[\s\S]*?(?:faq|frequently\s+asked|pertanyaan)[\s\S]*?<\/h2>/i` for the FAQ heading
   - From heading end, searches for next `/<h2[\s>]/i` — slices out `[headingStart, nextH2Start)`
   - Returns concatenated pre + post slices; if no next h2, slices to end of string
4. Run `node frontend/src/utils/stripFaqSection.test.mjs`, confirm all 4 assertions pass.
5. Commit: `feat(frontend): add stripFaqSection helper for FAQ-free article body`

**Test cases to cover (4 assertions):**
1. Returns empty string unchanged
2. Returns non-FAQ HTML unchanged (no h2 with FAQ keyword)
3. Strips FAQ h2 + siblings until next h2 (mid-article FAQ)
4. Strips FAQ h2 + siblings to end of string (end-article FAQ)

**Verification:**
- [ ] `node frontend/src/utils/stripFaqSection.test.mjs` — all 4 assertions pass
- [ ] Helper is pure (no Vue, no DOM, no I/O)
- [ ] No placeholder/TODO comments
- [ ] File < 40 lines

---

### Phase B: `FaqAccordion.vue` component

**Estimated time:** 12 minutes

**Files:**
- Create: `frontend/src/components/blog/FaqAccordion.vue`

**Design deliverable (from §Approach in this same file):**
- Glass card per item: `bg-bg-elevated/50 border border-white/5`, active state adds `border-accent-gold/25 bg-accent-gold/[0.03]`
- Gold chevron (`text-accent-gold`) rotates 180° when open via `rotate-180` class
- First item (`openIndex = 0`) open by default; single-open mode
- `grid-rows-[0fr→1fr]` height animation (modern auto-height pattern, no max-height hack)
- Answer `<p>` always in DOM for SEO — collapsed via grid-rows + overflow-hidden, not `display:none`
- A11y: `<button>` native semantics + `aria-expanded` + `aria-controls` + `aria-label` region
- Mono-label header "FAQ" with gold color, matching "Continue reading" section style

**Steps:**
1. Write failing test — create minimal inline Vue fixture in dev server (manual — no Vitest). Expected initial state: `frontend/src/components/blog/FaqAccordion.vue` does not exist → import fails in BlogDetail.vue if attempted. (Phase B is verified in-browser in Phase C; skip unit test — Playwright smoke in Phase D covers behavior.)
2. Run dev server; importing non-existent component would break HMR. Confirm file absent.
3. Implement `FaqAccordion.vue` per §Approach code spec in this file — accept `items: Array` prop, render accordion list, `openIndex` ref, `toggle(i)` method, `onKey(e,i)` keyboard handler.
4. Use Tailwind utilities only — no `<style>` block needed unless transitions require it.
5. Commit: `feat(frontend): add FaqAccordion component with glass cards + gold chevron`

**Verification:**
- [ ] Component file exists at `frontend/src/components/blog/FaqAccordion.vue`
- [ ] Uses `<script setup>` + `defineProps({ items: { type: Array, required: true } })`
- [ ] `openIndex` default = 0 (first open)
- [ ] `toggle(i)` toggles same-index → -1, else sets to i
- [ ] Template has `aria-expanded`, `aria-controls`, `<button type="button">`
- [ ] Returns empty early when `items.length === 0` (via `v-if`)
- [ ] No placeholder/TODO comments

---

### Phase C: `BlogDetail.vue` integration

**Estimated time:** 10 minutes

**Files:**
- Modify: [frontend/src/views/BlogDetail.vue](frontend/src/views/BlogDetail.vue)

**Changes in order:**
1. Import: `import FaqAccordion from '@/components/blog/FaqAccordion.vue'` + `import { stripFaqSection } from '@/utils/stripFaqSection'`
2. Add two computeds after `thumbnailUrl`:
   ```js
   const faqItems = computed(() => {
     const entities = post.value?.seo?.faq_schema?.mainEntity
     if (!Array.isArray(entities)) return []
     return entities
       .filter(e => e?.name && e?.acceptedAnswer?.text)
       .map(e => ({ question: e.name, answer: e.acceptedAnswer.text }))
   })

   const contentWithoutFaq = computed(() => {
     const html = post.value?.content
     if (!html || faqItems.value.length === 0) return html
     return stripFaqSection(html)
   })
   ```
3. Swap article body render: `v-html="post.content"` → `v-html="contentWithoutFaq"` at [BlogDetail.vue:150-154](frontend/src/views/BlogDetail.vue#L150-L154)
4. Mount `<FaqAccordion :items="faqItems" />` between the article body `</div>` and the `<!-- ─── Related Posts ─── -->` comment (around line 225)
5. Modify related-post `<router-link>` at [BlogDetail.vue:234-263](frontend/src/views/BlogDetail.vue#L234-L263):
   - Add `target="_blank"` and `rel="noopener noreferrer"` attributes
   - Add `relative` class to enable absolute-positioned hover icon
   - Add hover ↗ icon pill inside the card (absolute top-3 right-3, opacity-0 → group-hover:opacity-100)

**Steps:**
1. Write failing check — save BlogDetail.vue with imports but no template changes yet. Expected: tsc equivalent (Vue compiler) fails with "FaqAccordion is not defined in template" when accordion tag added. (Vue lacks native tsc; use `npm run build` as the compiler gate.)
2. Run `npm run build` in frontend, confirm compile error surfaces (or skip — HMR in Phase C step 2 gives faster feedback).
3. Apply all 5 changes above to BlogDetail.vue.
4. Start dev server (`npm run dev`), navigate to `/id/blog/10-best-vibe-coding-tools-2026-untuk-developer-modern` (or any article with FAQ populated). Confirm:
   - FAQ accordion renders below article body, above Continue Reading
   - First item expanded, chevron rotated, gold border
   - Clicking another item closes current, opens clicked
   - Clicking an open item closes it
   - Article body has no duplicate FAQ heading + Q&A (stripped)
5. Click a Continue Reading card — confirm opens in new tab.
6. Commit: `feat(frontend): open related posts in new tab + render FAQ as accordion`

**Verification:**
- [ ] `npm run build` succeeds (no Vue compile errors)
- [ ] `/id/blog/<slug>` loads with `faq_schema` populated → accordion visible
- [ ] Article body has no duplicate FAQ section (stripped)
- [ ] Related-post card opens in new tab (window.open-equivalent via anchor target)
- [ ] Related-post hover shows ↗ icon pill
- [ ] FAQ accordion: first open by default, single-open toggle behavior
- [ ] No console errors/warnings
- [ ] No placeholder/TODO comments added

---

### Phase D: Manual QA + optional Playwright smoke

**Estimated time:** 10 minutes

**Files:**
- Optional create: `frontend/tests/blog-detail-faq.spec.js` (Playwright smoke)

**Steps:**
1. Manual 8-item test matrix from §Test Matrix (brainstorm section above) — all must pass:
   1. Related post opens new tab
   2. FAQ accordion renders when `faq_schema` present
   3. First item expanded by default
   4. Single-open toggle works
   5. Click-to-collapse works
   6. Keyboard Tab + Enter toggles (a11y)
   7. Graceful degradation (article without `faq_schema` → no accordion, no errors)
   8. FAQ stripped from v-html body (view source check — heading + Q&A removed)
2. (Optional) Write minimal Playwright smoke at `frontend/tests/blog-detail-faq.spec.js`:
   - Navigate to known FAQ-containing article
   - Assert `[aria-label="Frequently asked questions"]` visible
   - Assert first `[aria-expanded="true"]` question exists
   - Click second question, assert aria-expanded flips
   - Assert related-post card has `target="_blank"`
3. If Playwright test added: `npx playwright test blog-detail-faq.spec.js` — passes.
4. Commit (only if Playwright test added): `test(frontend): add smoke test for FAQ accordion + related-post new tab`

**Verification:**
- [ ] All 8 manual test-matrix items pass
- [ ] Optional: Playwright smoke passes (`npx playwright test blog-detail-faq`)
- [ ] No regressions — existing BlogDetail features (reading progress, share bar, author card, meta tags) still work

---

### Red Flags Self-Check

| Red Flag | Status |
|---|---|
| No Data Integration Map | ✅ Present above |
| Phase without Verification | ✅ Every phase has verification block |
| No reference to CLAUDE.md | ✅ Root + frontend CLAUDE.md both read, tokens + patterns cited |
| Vague data sources | ✅ Specific: `post.value.seo.faq_schema.mainEntity`, `relatedPosts` ref |
| No test steps | ✅ Phase A is pure TDD; Phase B/C verified via dev server; Phase D optional Playwright |
| Phase too large | ✅ All phases ≤ 12 min |
| Placeholder language | ✅ None — all integrations are real (`faq_schema` already emitted by pipeline) |

### Execution Handoff

Three options:

**1. Execute in this session** — Run `/gaspol-execute` now for Phase A → D with per-phase checkpoints.

**2. Parallel execution** — Phases A + B are independent (pure helper vs component) and could run in parallel via `/gaspol-parallel`. Phase C depends on both. Phase D depends on C. Minor time saving (~3 min) for this small plan — probably not worth the coordination overhead.

**3. Separate session** — All context is in this file; a fresh session can `/gaspol-execute docs/plans/2026-04-19-blog-detail-related-tab-faq-accordion.md` and pick up cleanly.

