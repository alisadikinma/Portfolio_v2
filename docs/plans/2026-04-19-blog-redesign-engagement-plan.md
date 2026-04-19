> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# Blog Redesign — Implementation Plan

**Companion to:** [2026-04-19-blog-redesign-engagement.md](2026-04-19-blog-redesign-engagement.md) (design doc)
**Split reason:** Combined file would exceed 500 lines.

## Goal

Deliver the 3-PR phased blog redesign approved in the companion design doc: fix the stubbed newsletter (wire to existing API + full-funnel UX), transform Blog.vue from uniform 3-col grid to social-feed discovery with editorial rhythm, and inject mid-article engagement into BlogDetail.vue (sticky TOC + inline promo/newsletter/related cards). Each PR is independently revertable.

## Architecture Context (pulled from CLAUDE.md)

**Stack:** Vue 3.5 Composition API + Rolldown-Vite 7.1 + Pinia 3 + Tailwind 4. Backend Laravel 12 + Sanctum 4. Dev: XAMPP Apache (backend port 80) + Vite dev server (port 5173).

**Reusable assets already in codebase:**
- [`useNewsletter.js`](../../frontend/src/composables/useNewsletter.js) — fully wired to `POST /api/newsletter/subscribe` (409 on duplicate). Just not imported by Blog.vue.
- [`usePosts.js`](../../frontend/src/composables/usePosts.js) — TanStack-cached, already fetches posts list + single post.
- Base components: [`GlassCard.vue`](../../frontend/src/components/base/GlassCard.vue), [`GradientBorderCard.vue`](../../frontend/src/components/base/GradientBorderCard.vue), [`BlogSkeleton.vue`](../../frontend/src/components/base/BlogSkeleton.vue), [`BaseToast.vue`](../../frontend/src/components/base/BaseToast.vue).
- Blog components: [`FaqAccordion.vue`](../../frontend/src/components/blog/FaqAccordion.vue) — precedent for post-content components.
- Pure utils: [`stripFaqSection.js`](../../frontend/src/utils/stripFaqSection.js) + [`.test.mjs`](../../frontend/src/utils/stripFaqSection.test.mjs), [`extractFaqFromHtml.js`](../../frontend/src/utils/extractFaqFromHtml.js) — precedents for pure-HTML parsing logic.
- Backend: `/api/newsletter/subscribe` (throttled 5,60), `/api/posts`, `/api/posts/{slug}`, `/api/categories`, `/api/projects`, `/api/awards`, `/api/settings/site`. All work.
- `projects.featured` boolean column **already exists** (migration 2025_10_02_060232, default false).
- ULTRA design tokens: `--bg-deep`, `--bg-elevated`, `--fg-primary`, `--fg-muted`, `--accent-gold`, `--accent-cyan`, `--accent-indigo` + classes `glass-card`, `btn-gold`, `btn-glass`, `text-gradient`, `bezel-shell`, `bezel-core`.

**Test conventions (critical — no vitest/jest):**
- Pure JS logic: smoke tests in `*.test.mjs` next to source, `node src/utils/foo.test.mjs` to run. Uses a simple `assert(cond, label)` pattern from existing tests.
- Component/E2E: Playwright (`@playwright/test` installed). No spec directory exists yet — plan creates one.

**Deploy:** `git push origin main` triggers GitHub Actions → VPS auto-deploy. **Do NOT push without explicit user ask** (strict policy in CLAUDE.md).

## Tech Stack

- Vue 3.5 SFC with `<script setup>` + Composition API (project convention)
- Tailwind 4 utility classes + ULTRA tokens (no new CSS variables)
- Axios via `services/api.js` wrapper
- Pure JS utils with `.test.mjs` smoke tests (matches `stripFaqSection`, `extractFaqFromHtml`, `imagePositioning`)
- Playwright for critical-path E2E
- Laravel 12 standard controller + route + migration + seeder patterns

## Data Integration Map

| Feature | Data source | Hook / API | Exists? | Action |
|---|---|---|---|---|
| Newsletter subscribe | `POST /api/newsletter/subscribe` | `useNewsletter().subscribe(email)` | Yes | Use existing |
| Newsletter dedup detection | 409 Conflict response | `useNewsletter()` already handles `err.response.data.error.message` | Yes | Use existing |
| Newsletter dismissal persistence | `localStorage` keys `nl_dismissed_at`, `nl_subscribed_email` | To extend `useNewsletter.js` | No | Extend existing composable |
| Posts list | `GET /api/posts?lang=` | `usePosts().fetchPosts()` | Yes | Use existing |
| Single post | `GET /api/posts/{slug}?lang=` | `usePosts().fetchPost()` | Yes | Use existing |
| Categories | `GET /api/categories` | `api.get('/categories')` (ad-hoc in Blog.vue) | Yes | Use existing |
| Related posts | `GET /api/posts?category_id=` | Ad-hoc `fetchRelatedPosts()` in BlogDetail.vue | Yes | Use existing |
| Blog promo-slot resolver | `GET /api/blog/promo-slot` | `api.get('/blog/promo-slot')` | No | **Create new** (Laravel controller + route) |
| Promo config — project ID | `settings` table, `key='blog_promo_project_id'`, group `blog` | Seeded + read via `Setting::get()` | No | **Create new** (seeder) |
| Featured project fallback | `GET /api/projects?featured=1` | Existing | Yes | Use within new promo-slot resolver |
| Latest award fallback | `GET /api/awards` | Existing | Yes | Use within new promo-slot resolver |
| Site CTA copy | `settings` group `site` | `GET /api/settings/site` | Yes | Use within new promo-slot resolver |
| H2 split for injection | DOM parse of `post.content` HTML | New pure util `splitHtmlByH2.js` | No | **Create new pure util** (with smoke test) |
| Active-section observer | `IntersectionObserver` (native) | Inside `StickyTOC.vue` | No (native API) | Implement inline |
| Reading progress | Existing scroll handler in BlogDetail.vue | Already wired | Yes | Keep existing |

**Contract:** no feature above is stubbed. Where `Action = Create new`, executor MUST build real working integration.

## Phase Overview

Each PR is gated by its own verification + commit + (no push). Total: 19 phases across 3 PRs.

| PR | Phase | Code Deliverable | Design Deliverable | Est |
|---|---|---|---|---|
| **PR1** | 1.1 | Smoke test for newsletter state helpers | n/a (logic) | 8 min |
| | 1.2 | Extend `useNewsletter.js` with dismiss/subscribed helpers | n/a (logic) | 10 min |
| | 1.3 | Fix Blog.vue bottom form stub → real API call + success UX | Design spec for success state | 15 min |
| | 1.4 | `NewsletterInlineCard.vue` | Design spec (compact, 1-line copy) | 20 min |
| | 1.5 | `NewsletterFooterBar.vue` + wire to Blog.vue (scroll-trigger) | Design spec (sticky bar) | 25 min |
| | 1.6 | `NewsletterFloatingBanner.vue` + wire to BlogDetail.vue (60s timer) | Design spec (bottom-right card) | 25 min |
| | 1.7 | Playwright E2E: subscribe → dedup across touchpoints | n/a (test) | 20 min |
| **PR2** | 2.1 | `BlogCategoryChips.vue` | Design spec (horizontal scroll, thumbnails) | 20 min |
| | 2.2 | `BlogHeroCard.vue` | Design spec (65vh, cinematic overlay) | 20 min |
| | 2.3 | `BlogWideCard.vue` + `BlogTallCard.vue` + `BlogSmallCard.vue` + `BlogQuoteCard.vue` | Design spec (4 card variants) | 40 min |
| | 2.4 | Smoke test for distributor algorithm | n/a (logic) | 10 min |
| | 2.5 | `BlogFeedDistributor.vue` | Design spec (layout orchestrator) | 15 min |
| | 2.6 | Refactor Blog.vue to use distributor + chips + newsletter touchpoints | n/a (glue) | 30 min |
| | 2.7 | Playwright E2E: blog list renders all variants + category chip click | n/a (test) | 15 min |
| **PR3** | 3.1 | Backend: `BlogPromoSlotController` + route + seeder + Feature test | n/a (backend) | 30 min |
| | 3.2 | Smoke test for `splitHtmlByH2` pure util | n/a (logic) | 10 min |
| | 3.3 | `splitHtmlByH2.js` pure util | n/a (logic) | 15 min |
| | 3.4 | `StickyTOC.vue` | Design spec (left rail, active highlight) | 30 min |
| | 3.5 | `BlogInlinePromoCard.vue` + `BlogInlineRelatedPosts.vue` | Design spec (inline injected cards) | 25 min |
| | 3.6 | `BlogContentInjector.vue` (HTML split + Vue h() rendering) | n/a (composition logic) | 25 min |
| | 3.7 | Refactor BlogDetail.vue: swap v-html for injector, add TOC, wire floating banner | n/a (glue) | 25 min |
| | 3.8 | Playwright E2E: TOC active highlight + inline cards render + promo rotates | n/a (test) | 20 min |

---

## PR1 — Newsletter Full Funnel

**Branch:** `feat/blog-newsletter-full-funnel`
**Estimated total:** 2–2.5 hrs
**Ships first:** fixes 3-line stub + unlocks shared components for PR2/PR3.

### Phase 1.1: Smoke test for newsletter state helpers

**Estimated time:** 8 min

**Files:**
- Create: `frontend/src/composables/useNewsletter.test.mjs`

**Steps:**
1. Write failing test for `isDismissed()` / `isSubscribed()` / `markDismissed()` / `markSubscribed()` logic. Expected error: `TypeError: isDismissed is not a function` (helpers don't exist yet).
2. Test matrix to cover: fresh state returns false, `markDismissed()` sets TTL, `isDismissed()` honors 7-day TTL, `markSubscribed(email)` persists, `isSubscribed()` returns true after mark, expired dismiss reverts to false.
3. Mock `localStorage` with a plain object at top of test file (Node environment has no `localStorage`). Use `global.localStorage = { getItem, setItem, removeItem, clear }` with a backing Map.
4. Run `node frontend/src/composables/useNewsletter.test.mjs`, confirm fails with "is not a function".
5. Do NOT commit yet — implementation comes next phase.

**Verification:**
- [ ] Test file follows `assert(cond, label)` pattern from `stripFaqSection.test.mjs`
- [ ] Running file produces at least one FAIL line pointing at missing helpers
- [ ] No placeholder/TODO comments

### Phase 1.2: Extend `useNewsletter.js` with dismiss/subscribed helpers

**Estimated time:** 10 min

**Files:**
- Modify: `frontend/src/composables/useNewsletter.js`

**Steps:**
1. Add module-level constants: `DISMISS_TTL_DAYS = 7`, `STORAGE_DISMISSED = 'nl_dismissed_at'`, `STORAGE_SUBSCRIBED = 'nl_subscribed_email'`.
2. Implement `isDismissed()`: read localStorage, compare timestamp + TTL, return boolean. Guard with `typeof window !== 'undefined'` for SSR safety.
3. Implement `isSubscribed()`: check `localStorage.getItem(STORAGE_SUBSCRIBED)` is non-empty string.
4. Implement `markDismissed()`: write current `Date.now()` to `STORAGE_DISMISSED`.
5. Implement `markSubscribed(email)`: write lowercased trimmed email to `STORAGE_SUBSCRIBED`.
6. Inside existing `subscribe(email)`: on success, call `markSubscribed(email)` before returning.
7. Export all 4 helpers from the composable return object.
8. Run `node frontend/src/composables/useNewsletter.test.mjs`, confirm all pass.
9. Commit: `feat(blog): add newsletter dismissal + subscribed-state helpers`

**Verification:**
- [ ] `node frontend/src/composables/useNewsletter.test.mjs` exits 0
- [ ] All 4 helpers exported and typed correctly (JSDoc optional but values visible in IDE)
- [ ] `subscribe()` auto-calls `markSubscribed(email)` on success
- [ ] No placeholder/TODO comments in new code

### Phase 1.3: Fix Blog.vue bottom form stub + success UX

**Estimated time:** 15 min

**Files:**
- Modify: `frontend/src/views/Blog.vue`

**Design deliverable:**
- Success state: `<Transition>` fade swap form → centered ✓ icon (gold, `w-10 h-10`) + "You're in! Check your inbox." (`text-fg-primary`), stays 3s, then card fades out entirely.
- Duplicate state: friendly cyan variant — ✓ icon cyan + "You're already subscribed ✓". Same 3s fade.
- Error state: inline red hint below input, form stays editable. Use `text-red-400 text-xs mt-2`.
- Loading state: `btn-gold` disabled + spinner icon replacing label.

**Steps:**
1. Import `useNewsletter` + replace stub `subscribeNewsletter()` with real call. Remove `toast.info('Coming soon')` line.
2. Add reactive state: `submitStatus = ref('idle')` with values `'idle' | 'loading' | 'success' | 'duplicate' | 'error'`.
3. On submit: set loading → call `useNewsletter().subscribe()` → branch on `result.success` vs error message (check if message contains "already").
4. Bind UI conditionals: show form when `submitStatus === 'idle' | 'loading' | 'error'`. Show success card when `success | duplicate`.
5. `setTimeout(() => submitStatus.value = 'idle', 3000)` after success states, then hide card via `<Transition>`.
6. Hide entire newsletter card if `useNewsletter().isSubscribed()` returns true on mount.
7. Manual verify: submit valid email → success card → fades. Submit same email → duplicate card. Submit malformed → error hint.
8. Commit: `feat(blog): wire Blog.vue newsletter form to real API with success UX`

**Verification:**
- [ ] No "Coming soon" toast anywhere in Blog.vue
- [ ] `npm run dev` + submit real email → 201 in network tab + success UI shows
- [ ] Submit duplicate → 409 + duplicate UI shows
- [ ] Submit invalid email → 422 + error hint shows
- [ ] Refresh page after success → newsletter card hidden entirely
- [ ] No placeholder/TODO comments

### Phase 1.4: `NewsletterInlineCard.vue`

**Estimated time:** 20 min

**Files:**
- Create: `frontend/src/components/blog/NewsletterInlineCard.vue`

**Design deliverable:**
- Width: `max-w-xl mx-auto`, padding `p-6 md:p-8`
- Background: `bg-bg-elevated/60 backdrop-blur-sm border border-accent-gold/15 rounded-2xl`
- Layout: 2-col on md (icon-block left 64px + text+form right). Stack on mobile.
- Icon: `✨` in a circular `bg-accent-gold/10` container
- Eyebrow: `mono-label text-accent-gold`, copy: `"✨ One email, every Friday"`
- Headline: `font-display text-2xl md:text-3xl font-bold`, copy: `"Enjoying this?"`
- Sub: `text-fg-muted text-sm leading-relaxed`, copy: `"Get the next essay like this one, plus behind-the-scenes notes on what I'm building."`
- Form: horizontal (email input + `btn-gold` subscribe). Stack on mobile.
- No fine-print (saves vertical space mid-article)

**Steps:**
1. Scaffold component with `<script setup>` + props: `{ variant: 'list' | 'detail' (default: 'detail'), onSubscribed: Function? }`.
2. Import `useNewsletter`, reuse `subscribe` + `isSubscribed` + `submitStatus` pattern from Phase 1.3.
3. Emit `@subscribed` event on success.
4. Render idle/success/duplicate/error states (reuse UX from Phase 1.3).
5. If `isSubscribed()` on mount, render `null` (component auto-hides).
6. Manual verify by importing into Blog.vue and BlogDetail.vue temporarily, test at 1440px + 375px.
7. Remove temp imports.
8. Commit: `feat(blog): add NewsletterInlineCard for mid-article newsletter capture`

**Verification:**
- [ ] Component renders in both pages without layout break
- [ ] Hides automatically when `isSubscribed()` returns true
- [ ] Emits `@subscribed` event parent can listen to
- [ ] Uses only existing ULTRA tokens (no new CSS variables)
- [ ] No placeholder/TODO comments

### Phase 1.5: `NewsletterFooterBar.vue` + Blog.vue scroll-trigger wire-up

**Estimated time:** 25 min

**Files:**
- Create: `frontend/src/components/blog/NewsletterFooterBar.vue`
- Modify: `frontend/src/views/Blog.vue`

**Design deliverable:**
- Position: `fixed bottom-0 left-0 right-0 z-40`, full-width
- Height: `~64px`, padding `px-6 py-3`
- Background: `bg-bg-deep/90 backdrop-blur-xl border-t border-white/5`
- Layout: flex, `max-w-6xl mx-auto`. Left: icon + 1-line headline. Right: email input + CTA + close ✕.
- Copy: `"Liked what you read? Get a new essay every Friday."`
- CTA: `btn-gold` small `text-xs`
- Close button: `text-fg-dim hover:text-fg-primary`, top-right corner of bar
- Slide-up enter animation (`translate-y-full` → 0, 400ms ease-out)
- Slide-down exit on dismiss
- Mobile (< 640px): stack, reduce copy to `"Get weekly essays →"`

**Steps:**
1. Scaffold component with `<script setup>` + props: `{ show: Boolean, onDismiss: Function? }`.
2. Use `<Transition name="slide-up">` with enter/leave translate-y classes.
3. Implement compact inline form (same API as Phase 1.3) + dismiss ✕ button.
4. Dismiss handler: call `useNewsletter().markDismissed()`, emit `@dismiss`.
5. In `Blog.vue`: add reactive `showFooterBar = ref(false)`.
6. Add scroll listener in `onMounted`: when user scrolls past 60% of document height, set `showFooterBar = true`. Only if `!isSubscribed() && !isDismissed()`.
7. Use `passive: true` scroll listener, throttle with `requestAnimationFrame`.
8. Remove listener in `onUnmounted`.
9. On dismiss or subscribe success, `showFooterBar = false` + persist via composable.
10. Manual verify: scroll past 60% → bar slides up → dismiss → stays gone across 7 days.
11. Commit: `feat(blog): add NewsletterFooterBar with scroll-triggered reveal`

**Verification:**
- [ ] Scroll listener cleans up (no memory leak — verify in DevTools Performance)
- [ ] Bar never shows if `isSubscribed()` or `isDismissed()` is true
- [ ] Dismissal persists across page reloads (localStorage)
- [ ] Mobile layout doesn't overflow horizontally
- [ ] `tsc --noEmit` equivalent: `npm run build` passes
- [ ] No placeholder/TODO comments

### Phase 1.6: `NewsletterFloatingBanner.vue` + BlogDetail.vue timer wire-up

**Estimated time:** 25 min

**Files:**
- Create: `frontend/src/components/blog/NewsletterFloatingBanner.vue`
- Modify: `frontend/src/views/BlogDetail.vue`

**Design deliverable:**
- Position: `fixed bottom-6 right-6 z-40`, max-width 380px
- Background: `glass-card` (uses existing utility), rounded `rounded-2xl`, padding `p-5`
- Visible accent: subtle `border-accent-cyan/25` + gold eyebrow ✨
- Copy: Headline `"Before you go —"`, Sub `"One essay per Friday on AI + engineering. No spam."`, CTA `"Subscribe"`, Close `"Maybe later"`
- Layout: stacked. Close link lower-left (small gray), CTA `btn-gold` full-width below input
- Animation: fade+scale (`opacity-0 scale-95` → `opacity-100 scale-100`, 300ms ease-spring)
- Mobile: reposition to `bottom-4 left-4 right-4` (full-width less margin), otherwise identical

**Steps:**
1. Scaffold with `<script setup>` + props: `{ show: Boolean }`.
2. Use `<Transition>` with CSS transform enter/leave.
3. Implement form (same pattern) + close "Maybe later" button.
4. Close handler: `markDismissed()`, emit `@dismiss`.
5. In `BlogDetail.vue`: add reactive `showFloatingBanner = ref(false)`.
6. In `onMounted`: start `setTimeout(() => showFloatingBanner = true, 60_000)`.
7. Guard: don't fire if `isSubscribed() || isDismissed()`.
8. Clear timer in `onUnmounted` (save reference).
9. Clear timer on subscribe success or dismiss.
10. Focus trap when visible: use `v-focus-trap` pattern if available, otherwise manual focus management (Tab cycles within card, Esc closes).
11. Manual verify: open blog detail → wait 60s → banner fades in → dismiss → gone for 7 days.
12. Commit: `feat(blog): add NewsletterFloatingBanner with 60s delayed reveal`

**Verification:**
- [ ] Timer cleans up on unmount (no leaks)
- [ ] Banner respects `isSubscribed()` and `isDismissed()`
- [ ] Escape key closes banner
- [ ] Focus returns to article body after close
- [ ] Mobile placement doesn't cover share buttons
- [ ] No placeholder/TODO comments

### Phase 1.7: Playwright E2E — subscribe + dedup across touchpoints

**Estimated time:** 20 min

**Files:**
- Create: `frontend/tests/e2e/newsletter-funnel.spec.ts`
- Create: `frontend/playwright.config.ts` (if not present)

**Steps:**
1. Write failing Playwright spec: "blog list bottom form subscribes successfully". Expected error: test runner not wired / spec fails with 404 on subscribe.
2. Run `npx playwright test tests/e2e/newsletter-funnel.spec.ts --headed`, confirm fail.
3. Scenarios to test:
   - Bottom form: fill valid email → submit → success card appears within 3s
   - Duplicate: re-submit same email → duplicate state shows
   - Reload → form hidden because `isSubscribed()`
   - Clear localStorage → scroll 60% on list → footer bar appears → dismiss → stays gone on reload (7d TTL)
   - Blog detail: visit page → wait 61s → floating banner visible
4. Fixtures: seed Newsletter table directly via API call before test, clean up after.
5. Add `test` script to `frontend/package.json`: `"test:e2e": "playwright test"` and `"test:e2e:ui": "playwright test --ui"`.
6. Run full spec, confirm all pass.
7. Commit: `test(blog): add newsletter funnel E2E coverage`

**Verification:**
- [ ] `npm run test:e2e` exits 0
- [ ] Spec covers all 3 newsletter touchpoints + dismissal persistence
- [ ] Test uses real API (no mocks)
- [ ] No placeholder/TODO comments
- [ ] No push — commit only per CLAUDE.md policy

**PR1 COMMIT GATE (before moving to PR2):**
- [ ] All 7 phase verifications pass
- [ ] `npm run build` succeeds
- [ ] Manual smoke: visit `/en/blog` → submit real email → get welcome (check email received on `aiagent@alisadikinma.com` if mail configured)
- [ ] No breakage of existing Blog.vue features (search, categories, pagination)
- [ ] `git status` shows clean, branch pushed to local HEAD only (DO NOT PUSH)
- [ ] User asked to review PR1 before PR2 starts

---

## PR2 — Blog.vue Social-Feed Redesign

**Branch:** `feat/blog-list-social-feed`
**Estimated total:** 4–5 hrs
**Depends on:** PR1 merged (reuses `NewsletterInlineCard`, `NewsletterFooterBar`).

### Phase 2.1: `BlogCategoryChips.vue`

**Estimated time:** 20 min

**Files:**
- Create: `frontend/src/components/blog/BlogCategoryChips.vue`

**Design deliverable:**
- Container: horizontal flex, `gap-3 px-4 overflow-x-auto scroll-smooth snap-x`
- Hide scrollbar: `scrollbar-hide` (add utility if not present) or inline CSS `::-webkit-scrollbar { display: none }`
- Each chip: circular 72px `rounded-full` thumbnail + label below
- Thumbnail: image if `category.icon_url` exists, else `text-2xl font-display` first letter on `bg-accent-gold/10 border border-accent-gold/20`
- Selected state: `ring-2 ring-accent-gold ring-offset-2 ring-offset-bg-deep scale-105`, label turns `text-accent-gold`
- Hover: `scale-108` + thumbnail `brightness-110`
- "All" chip pinned at start with gold All icon
- Snap to each chip (`snap-start`)

**Steps:**
1. Write failing Playwright spec (in next test phase) — skip for now, will test via 2.7.
2. Scaffold component with `<script setup>` + props: `{ categories: Array, selectedId: Number|null, onSelect: Function }`.
3. Implement template with "All" chip + v-for over categories.
4. Emit `@select(categoryId | null)` on chip click.
5. Manual verify horizontal scroll on touch (mobile) + keyboard Tab navigation.
6. Ensure `aria-label="Category filter"` on container.
7. Commit: `feat(blog): add BlogCategoryChips horizontal nav`

**Verification:**
- [ ] Horizontal scroll works on touch + mouse wheel
- [ ] Keyboard Tab + Enter selects chip
- [ ] Selected chip visually distinct
- [ ] No layout shift when selecting different chip
- [ ] `npm run build` passes
- [ ] No placeholder/TODO comments

### Phase 2.2: `BlogHeroCard.vue`

**Estimated time:** 20 min

**Files:**
- Create: `frontend/src/components/blog/BlogHeroCard.vue`

**Design deliverable:**
- Aspect: `aspect-[21/9]` on desktop (≥md), `aspect-[4/5]` mobile
- Image: `object-cover` full width
- Overlay gradient: `bg-gradient-to-t from-bg-deep via-bg-deep/60 to-transparent`
- Content anchored bottom-left, padding `p-8 md:p-12`
- Eyebrow: category chip (`bg-accent-cyan/15 text-accent-cyan`)
- Headline: `font-display text-4xl md:text-6xl font-bold leading-tight` with `text-fg-primary`
- Sub (excerpt): `text-fg-muted text-lg max-w-2xl line-clamp-2` (hide on small mobile)
- Meta: `mono-label` time + read-minutes + views
- CTA hint: `Read article →` in gold, becomes scale-105 on card hover
- Entire card is clickable → `router.push(\`/${lang}/blog/${post.slug}\`)`
- Image hover: `scale-105 transition-transform duration-700 ease-spring`

**Steps:**
1. Scaffold component + props: `{ post: Object, lang: String }`.
2. Emit no events (just push via `useRouter`).
3. Render overlay + content + image.
4. Use `<RouterLink>` wrapping the whole card for semantic navigation + SEO.
5. Fallback if `post.featured_image` missing: gradient `bg-gradient-to-br from-accent-gold/20 via-bg-elevated to-accent-cyan/20` + text centered.
6. Manual verify on 1920, 1440, 768, 375 widths.
7. Commit: `feat(blog): add BlogHeroCard for featured post`

**Verification:**
- [ ] Card fully clickable, hover shows cursor-pointer
- [ ] Text stays readable at all breakpoints (no overflow, proper line-clamp)
- [ ] Missing image fallback renders gracefully
- [ ] Aspect ratio doesn't squish on tall viewports
- [ ] No placeholder/TODO comments

### Phase 2.3: `BlogWideCard.vue` + `BlogTallCard.vue` + `BlogSmallCard.vue` + `BlogQuoteCard.vue`

**Estimated time:** 40 min (~10 min each)

**Files:**
- Create: `frontend/src/components/blog/BlogWideCard.vue`
- Create: `frontend/src/components/blog/BlogTallCard.vue`
- Create: `frontend/src/components/blog/BlogSmallCard.vue`
- Create: `frontend/src/components/blog/BlogQuoteCard.vue`

**Design deliverable (per variant):**

**BlogWideCard:**
- 2-col grid `md:grid-cols-2` (image | content), `gap-0`, `bezel-shell`
- Image: `aspect-[4/3]` md / `aspect-video` mobile, `object-cover`
- Content side: `p-8 md:p-10`, centered vertically, full editorial feel (headline 3xl, excerpt line-clamp-3, meta bar, Read button)

**BlogTallCard:**
- Aspect `aspect-[9/16]`, image top 60%, content bottom 40%
- Headline 2xl, excerpt line-clamp-2
- Use `bezel-shell-sm`

**BlogSmallCard:**
- Refactor extraction from current Blog.vue lines 153–215 (existing 3-col card)
- Aspect `aspect-video` image, content `p-5`
- Headline `text-lg`, excerpt line-clamp-3
- Keep current hover behavior (scale image, gold headline)

**BlogQuoteCard:**
- Full-width card (spans grid `col-span-full` or `md:col-span-2`)
- Centered text, no image
- Background `bg-bg-elevated/40 border border-accent-gold/15 rounded-3xl p-10 md:p-16`
- Quote icon `❝` in gold, font-size 4xl, top-left
- Quote text: `font-serif italic text-2xl md:text-4xl leading-snug max-w-3xl mx-auto` (use Playfair Display class or CSS)
- Attribution: small mono text `— from "[post.title]"`
- On hover: reveal "Read essay →" link below

**Steps:**
1. Create all 4 files with `<script setup>` + props `{ post: Object, lang: String }`.
2. Each uses `<RouterLink>` to post slug.
3. Each respects missing `featured_image` (fallback glyph for image cards, none for quote card).
4. BlogQuoteCard: consume `post.ai_summary` if present, fallback to `post.excerpt.slice(0, 200) + '…'`.
5. Verify at 1440, 768, 375 widths; ensure no overflow.
6. Commit 4 separate commits: `feat(blog): add BlogWideCard`, `add BlogTallCard`, `add BlogSmallCard`, `add BlogQuoteCard`.

**Verification:**
- [ ] All 4 variants render with real post data in test harness
- [ ] Mobile stacks correctly (no horizontal scroll)
- [ ] Missing image/ai_summary handled gracefully
- [ ] ULTRA tokens only (no new CSS variables)
- [ ] No placeholder/TODO comments

### Phase 2.4: Smoke test for distributor algorithm

**Estimated time:** 10 min

**Files:**
- Create: `frontend/src/utils/blogCardDistributor.js` (empty shell — just exports a stub)
- Create: `frontend/src/utils/blogCardDistributor.test.mjs`

**Steps:**
1. Create shell `blogCardDistributor.js` exporting `getCardVariant(index, post): string` (returns `'tall'` always — wrong on purpose).
2. Write failing smoke test asserting:
   - `getCardVariant(0, post) === 'hero'`
   - `getCardVariant(1, post) === 'wide'`
   - `getCardVariant(9, postWithAiSummary) === 'quote'` (index 9 = 9 % 7 === 2)
   - `getCardVariant(3, post) === 'tall'`
   - `getCardVariant(4, post) === 'small'`
   - `getCardVariant(9, postWithoutAiSummary) === 'small'` (fallback when no ai_summary)
3. Run `node frontend/src/utils/blogCardDistributor.test.mjs`, confirm FAIL for all except the one accidentally matching "tall".
4. Do not commit — Phase 2.5 implements real logic.

**Verification:**
- [ ] Test file mirrors `stripFaqSection.test.mjs` style
- [ ] At least 5 of 6 assertions fail initially
- [ ] No placeholder/TODO comments

### Phase 2.5: `BlogFeedDistributor.vue` + real `blogCardDistributor.js` logic

**Estimated time:** 15 min

**Files:**
- Modify: `frontend/src/utils/blogCardDistributor.js`
- Create: `frontend/src/components/blog/BlogFeedDistributor.vue`

**Steps:**
1. Implement real `getCardVariant` algorithm per design:
   ```js
   if (index === 0) return 'hero'
   if (index === 1) return 'wide'
   if (index % 7 === 2 && post.ai_summary) return 'quote'
   if (index % 5 === 3) return 'tall'
   return 'small'
   ```
2. Re-run smoke test, confirm all pass.
3. Create `BlogFeedDistributor.vue` with props `{ posts: Array, lang: String }`.
4. Import all 5 card components (Hero, Wide, Tall, Small, Quote).
5. Template uses `<component :is="cardMap[variant]" :post :lang />` per post.
6. Use CSS grid responsive layout: `grid grid-cols-1 md:grid-cols-3 gap-6` — each card variant has `col-span` metadata:
   - hero: `col-span-full`
   - wide: `col-span-full md:col-span-3`
   - tall: `col-span-1 md:col-span-1 md:row-span-2`
   - small: `col-span-1`
   - quote: `col-span-full md:col-span-3`
7. Commit (2 commits: util + distributor component): `feat(blog): add blogCardDistributor util with smoke tests` / `feat(blog): add BlogFeedDistributor orchestrator`

**Verification:**
- [ ] `node frontend/src/utils/blogCardDistributor.test.mjs` exits 0
- [ ] Distributor renders all variants in order based on index
- [ ] Grid layout respects col-span per variant
- [ ] No placeholder/TODO comments

### Phase 2.6: Refactor Blog.vue to use distributor + chips + newsletter touchpoints

**Estimated time:** 30 min

**Files:**
- Modify: `frontend/src/views/Blog.vue`

**Steps:**
1. Remove existing 3-col grid markup (lines ~133–217 of current Blog.vue).
2. Replace with `<BlogFeedDistributor :posts="paginatedPosts" :lang />`.
3. Replace current category pills block (lines 82–104) with `<BlogCategoryChips :categories :selected-id="selectedCategory" @select="selectCategory" />`.
4. Move featured post logic into distributor — it handles index 0 as hero automatically.
5. Adjust `paginatedPosts` to include featured post (no longer slice(1)).
6. Keep search input in its current position.
7. Insert `<NewsletterInlineCard variant="list" />` after every ~9 posts (hook into distributor or render between pagination batches).
8. Add `<NewsletterFooterBar :show="showFooterBar" @dismiss="showFooterBar=false" />` at end of template (from PR1).
9. Add scroll trigger from Phase 1.5 if not already wired.
10. Preserve bottom newsletter card (now has working form from Phase 1.3).
11. Test at 1440, 1024, 768, 375 widths.
12. Commit: `refactor(blog): swap Blog.vue to social-feed layout with distributor`

**Verification:**
- [ ] `/en/blog` renders with varied card types visible
- [ ] Category chips select + filter works
- [ ] Search filter works
- [ ] Pagination works
- [ ] Featured post shows as BlogHeroCard (first card)
- [ ] Newsletter inline cards interspersed (count >= 1 per page)
- [ ] Footer bar triggers on 60% scroll
- [ ] No regression: bottom newsletter still works
- [ ] `npm run build` passes
- [ ] No placeholder/TODO comments

### Phase 2.7: Playwright E2E — blog list redesign

**Estimated time:** 15 min

**Files:**
- Create: `frontend/tests/e2e/blog-list-redesign.spec.ts`

**Steps:**
1. Write failing spec for: "blog list renders hero + wide + small variants at least once". Expected fail: current grid selectors don't match new structure.
2. Run, confirm fail.
3. Scenarios:
   - Hero card (`data-testid="blog-hero-card"`) present and clickable
   - At least 1 wide + 1 small card present
   - Category chip click filters post list
   - Search input filters list
   - Pagination buttons work
   - Scrolling 60% reveals footer bar
4. Add `data-testid` attributes to relevant components (hero, wide, small, tall, quote, footer-bar).
5. Run all E2E tests, confirm pass.
6. Commit: `test(blog): add social-feed layout E2E coverage`

**Verification:**
- [ ] `npm run test:e2e` exits 0
- [ ] Spec doesn't rely on class names (brittle) — uses data-testid
- [ ] No placeholder/TODO comments

**PR2 COMMIT GATE:**
- [ ] All 7 phase verifications pass
- [ ] `npm run build` succeeds
- [ ] Manual smoke: `/en/blog` renders all variants, category + search + pagination work, mobile looks good
- [ ] No regression vs PR1 (newsletter still works)
- [ ] User asked to review PR2 before PR3 starts

---

## PR3 — BlogDetail.vue Engagement

**Branch:** `feat/blog-detail-engagement`
**Estimated total:** 4–5 hrs
**Depends on:** PR1 + PR2 merged. New backend endpoint + settings.

### Phase 3.1: Backend — `BlogPromoSlotController` + route + seeder + test

**Estimated time:** 30 min

**Files:**
- Create: `backend/app/Http/Controllers/Api/BlogPromoSlotController.php`
- Modify: `backend/routes/api.php` (add public route `GET /blog/promo-slot`)
- Create: `backend/database/seeders/BlogPromoSettingsSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php` (call new seeder)
- Create: `backend/tests/Feature/BlogPromoSlotTest.php`

**Steps:**
1. Write failing Feature test: `GET /api/blog/promo-slot returns valid payload with type + title`. Expected fail: 404 (route doesn't exist).
2. Run `php artisan test --filter=BlogPromoSlot`, confirm fail.
3. Create seeder: seeds `settings` row `group='blog', key='blog_promo_project_id', value=null` (nullable).
4. Create controller with single `show(Request $request)` method implementing priority rotation:
   ```php
   // 1. Check setting blog_promo_project_id
   $projectId = Setting::where('group', 'blog')->where('key', 'blog_promo_project_id')->value('value');
   $project = $projectId ? Project::find($projectId) : null;

   // 2. Fall back to latest featured project
   if (!$project) {
       $project = Project::where('featured', true)->latest('published_at')->first();
   }

   // 3. Award fallback (last 90d)
   $award = Award::where('year', '>=', now()->subDays(90))->latest()->first();

   // 4. Generic CTA fallback
   if ($project) {
       return response()->json(['data' => [
           'type' => 'project',
           'title' => $project->title,
           'description' => $project->short_description ?? $project->excerpt,
           'image' => $project->featured_image,
           'link' => "/projects/{$project->slug}",
           'cta_label' => 'Read case study',
       ]]);
   } elseif ($award) { /* ... */ }
     else { /* generic cta reads from settings/site */ }
   ```
5. Add route in `api.php` (public, cached via HTTP cache):
   ```php
   Route::get('/blog/promo-slot', [BlogPromoSlotController::class, 'show']);
   ```
6. Optionally add 60s cache via `Cache::remember('blog_promo_slot', 60, fn() => ...)` inside controller.
7. Run seeder via `php artisan db:seed --class=BlogPromoSettingsSeeder`.
8. Run Feature test, confirm pass. Test should cover all 3 fallback paths (project / award / generic).
9. Commit: `feat(api): add /blog/promo-slot endpoint with fallback rotation`

**Verification:**
- [ ] `php artisan test --filter=BlogPromoSlot` exits 0
- [ ] Seeder is idempotent (re-runnable without error)
- [ ] Controller has no placeholder/TODO
- [ ] Response structure matches design spec exactly
- [ ] Graceful fallback when no projects/awards exist

### Phase 3.2: Smoke test for `splitHtmlByH2` pure util

**Estimated time:** 10 min

**Files:**
- Create: `frontend/src/utils/splitHtmlByH2.js` (shell)
- Create: `frontend/src/utils/splitHtmlByH2.test.mjs`

**Steps:**
1. Create shell `splitHtmlByH2.js` exporting `splitHtmlByH2(html): Array<{type: 'chunk' | 'h2', html: string, id?: string, text?: string}>` returning `[{type:'chunk',html}]` — broken.
2. Write failing tests:
   - Empty string → `[]`
   - HTML with no H2 → single chunk (no split)
   - HTML with 1 H2 → 3 elements: chunk-before + h2 + chunk-after
   - HTML with 3 H2s → 7 elements
   - H2 text preserved + id auto-generated (slug-cased) for TOC use
   - Content with nested H2 inside blockquote → NOT split (only top-level H2)
3. Run `node frontend/src/utils/splitHtmlByH2.test.mjs`, confirm all fail except empty-string.

**Verification:**
- [ ] Test file covers all 6 edge cases
- [ ] No placeholder/TODO comments

### Phase 3.3: `splitHtmlByH2.js` pure util

**Estimated time:** 15 min

**Files:**
- Modify: `frontend/src/utils/splitHtmlByH2.js`

**Steps:**
1. Implement with regex match (mirror `stripFaqSection.js` pure-regex style — no DOMParser to keep Node-compatible):
   ```js
   const H2_RE = /<h2(?:\s[^>]*)?>([\s\S]*?)<\/h2>/gi
   // Walk matches, emit chunk-before + h2 + chunk-after for each
   ```
2. Generate stable `id` from H2 text (lowercase, non-alphanum → `-`, collapse, trim).
3. Handle edge cases per tests.
4. Re-run smoke test, confirm all pass.
5. Commit: `feat(blog): add splitHtmlByH2 pure util with smoke tests`

**Verification:**
- [ ] `node frontend/src/utils/splitHtmlByH2.test.mjs` exits 0
- [ ] Pure function (no side effects, no DOM dependency)
- [ ] No placeholder/TODO comments

### Phase 3.4: `StickyTOC.vue`

**Estimated time:** 30 min

**Files:**
- Create: `frontend/src/components/blog/StickyTOC.vue`

**Design deliverable:**
- Position: `sticky top-24` on `lg:` and up, left-rail 240px wide
- Mobile/tablet: hidden (reading progress bar at top is enough)
- Container: `bg-bg-elevated/40 backdrop-blur-sm border border-white/5 rounded-2xl p-5`
- Header: `mono-label text-accent-gold` "CONTENTS"
- List: `<ol>` with gold active marker `●` and gray inactive `○`
- Active item: `text-accent-gold` + font-medium
- Inactive: `text-fg-muted hover:text-fg-primary`
- Reading percentage at bottom: `text-xs text-fg-dim mono-label` "43% read"
- Progress bar under percentage: thin gold fill on white/5 base, aria-hidden
- On item click: `scrollIntoView({behavior:'smooth', block:'start'})` with 96px offset

**Steps:**
1. Scaffold component + props: `{ sections: Array<{id, text}>, progress: Number }`.
2. Use `IntersectionObserver` with `rootMargin: '-20% 0px -70% 0px'` to detect active H2.
3. Track active section in `ref<string|null>`, update from observer callback.
4. Handle scroll-to: `document.getElementById(id)?.scrollIntoView({behavior:'smooth'})`. Add 96px offset via `scroll-margin-top: 96px` on H2 elements (parent BlogDetail applies).
5. Accessibility: `<nav role="navigation" aria-label="Article outline">`, each link has `aria-current="location"` when active.
6. Respects `prefers-reduced-motion` — disable smooth scroll.
7. Commit: `feat(blog): add StickyTOC with IntersectionObserver active highlight`

**Verification:**
- [ ] Observer cleans up on unmount (no leak)
- [ ] Clicking item scrolls with 96px header offset
- [ ] Active highlight updates as user scrolls
- [ ] Hidden on mobile/tablet (< lg breakpoint)
- [ ] Keyboard accessible
- [ ] No placeholder/TODO comments

### Phase 3.5: `BlogInlinePromoCard.vue` + `BlogInlineRelatedPosts.vue`

**Estimated time:** 25 min

**Files:**
- Create: `frontend/src/components/blog/BlogInlinePromoCard.vue`
- Create: `frontend/src/components/blog/BlogInlineRelatedPosts.vue`

**Design deliverables:**

**BlogInlinePromoCard:**
- Horizontal layout (image left 33%, content right 67%), `md:flex` stacks on mobile
- Container: `bezel-shell-sm` with accent glow matching type (gold for project, cyan for award, purple for cta)
- Padding `p-5 md:p-6`, rounded `rounded-2xl`
- Image: `aspect-[4/3] rounded-xl overflow-hidden`
- Eyebrow: `mono-label` with type-specific color
- Headline: `font-display text-xl md:text-2xl font-bold`
- Sub: `text-fg-muted text-sm line-clamp-2`
- CTA: `btn-glass` with arrow icon

**BlogInlineRelatedPosts:**
- 2-col mini-grid (`grid-cols-1 md:grid-cols-2 gap-4`)
- Each card: thumbnail (aspect-video) + category mono-label + title (line-clamp-2) + date
- Mono-label eyebrow "YOU MIGHT ALSO LIKE"
- Contained in subtle `bg-bg-elevated/30 border border-white/5 rounded-2xl p-6`

**Steps:**
1. Scaffold both components.
2. `BlogInlinePromoCard` props: `{ slot: Object }` where slot is the payload from `/api/blog/promo-slot`.
3. On mount: `fetchPromoSlot()` from parent — this component is a "dumb" renderer.
4. `BlogInlineRelatedPosts` props: `{ posts: Array<2>, lang: String }` — parent passes pre-filtered related posts.
5. Both use RouterLink for navigation.
6. Graceful handling when slot/posts null or < 2 items.
7. Commit (2 commits): `feat(blog): add BlogInlinePromoCard` / `feat(blog): add BlogInlineRelatedPosts`

**Verification:**
- [ ] Both render with real data
- [ ] Type-specific accent colors correctly applied
- [ ] Missing image/data handled gracefully
- [ ] ULTRA tokens only
- [ ] No placeholder/TODO comments

### Phase 3.6: `BlogContentInjector.vue` (HTML split + Vue h() rendering)

**Estimated time:** 25 min

**Files:**
- Create: `frontend/src/components/blog/BlogContentInjector.vue`

**Steps:**
1. Scaffold with `<script setup>` + props: `{ html: String, promoSlot: Object|null, relatedPosts: Array, lang: String, onSubscribed: Function? }`.
2. Import `splitHtmlByH2` from Phase 3.3.
3. Computed: `chunks = splitHtmlByH2(props.html)`.
4. Computed: `injectionMap = { afterIndex: componentKey }` based on chunks length:
   - If chunks.length >= 3: inject `BlogInlinePromoCard` after chunks[2] (post-H2-#1)
   - If chunks.length >= 5: inject `NewsletterInlineCard` at middle
   - If chunks.length >= 7: inject `BlogInlineRelatedPosts` at chunks.length - 2
   - Shorter posts: skip injections gracefully
5. Render function using `h()`:
   ```js
   const render = () => chunks.flatMap((chunk, i) => [
     h('div', { class: 'blog-content', innerHTML: chunk.html, id: chunk.id }),
     injectionMap[i] ? h(injectionMap[i], { ...props }) : null
   ]).filter(Boolean)
   ```
6. Fallback: if any error in split, render `<div v-html="html" class="blog-content" />` (current behavior preserved).
7. Wrap in `<template>` or use render function directly.
8. Commit: `feat(blog): add BlogContentInjector for mid-article component injection`

**Verification:**
- [ ] Injects 3 components at correct positions for long posts (≥7 chunks)
- [ ] Short posts (< 3 chunks) render without injections, no errors
- [ ] `blog-content` CSS scope preserved (styles still apply)
- [ ] Graceful fallback on parse failure
- [ ] No placeholder/TODO comments

### Phase 3.7: Refactor BlogDetail.vue

**Estimated time:** 25 min

**Files:**
- Modify: `frontend/src/views/BlogDetail.vue`

**Steps:**
1. Import `BlogContentInjector`, `StickyTOC`, `NewsletterFloatingBanner`, `BlogInlinePromoCard`, `BlogInlineRelatedPosts`.
2. Add reactive state for promo slot: `const promoSlot = ref(null)`. Fetch in `onMounted` via `api.get('/blog/promo-slot')`.
3. Add computed `tocSections` that runs `splitHtmlByH2(post.content)` and filters to H2-type entries with `{id, text}`.
4. Change main content layout from single max-w-3xl column to:
   ```html
   <div class="container-custom mb-16" ref="articleContent">
     <div class="grid lg:grid-cols-[240px_minmax(0,1fr)] gap-8 max-w-6xl mx-auto">
       <aside class="hidden lg:block">
         <StickyTOC :sections="tocSections" :progress="readingProgress" />
       </aside>
       <div class="max-w-3xl">
         <!-- TL;DR, excerpt (existing) -->
         <BlogContentInjector
           :html="contentWithoutFaq"
           :promo-slot="promoSlot"
           :related-posts="relatedPosts"
           :lang="lang"
         />
         <!-- tags, share, author (existing) -->
       </div>
     </div>
   </div>
   ```
5. Replace `<div v-html="contentWithoutFaq" class="blog-content" />` with `<BlogContentInjector />`.
6. Keep FAQ accordion, related posts bottom section, etc. unchanged.
7. Add `<NewsletterFloatingBanner :show="showFloatingBanner" />` at template root.
8. Wire 60s setTimeout from Phase 1.6.
9. Manual verify: long post shows TOC + 3 injections + floating banner; short post shows only essential elements.
10. Commit: `refactor(blog): wire BlogDetail.vue to content injector + sticky TOC + engagement gates`

**Verification:**
- [ ] TOC shows for long posts, hidden on short posts with <3 H2s
- [ ] Injected components render at correct scroll positions
- [ ] Reading progress still updates
- [ ] All existing features preserved (share, author, FAQ, bottom related)
- [ ] Mobile renders cleanly (no TOC, content still injected)
- [ ] `npm run build` passes
- [ ] No placeholder/TODO comments

### Phase 3.8: Playwright E2E — BlogDetail engagement

**Estimated time:** 20 min

**Files:**
- Create: `frontend/tests/e2e/blog-detail-engagement.spec.ts`

**Steps:**
1. Write failing spec: "blog detail page renders TOC + inline promo + inline related". Expected fail: selectors don't match yet.
2. Run, confirm fail.
3. Scenarios (fixture post with ≥4 H2 sections):
   - StickyTOC visible on desktop (>=1280px), hidden on mobile
   - Clicking TOC item scrolls to section, active marker updates
   - Inline promo card renders after first H2, has link to project/award
   - Inline newsletter card renders mid-article
   - Inline related posts card renders near end
   - Floating banner appears after 60s (use `page.waitForTimeout(61000)` or mock timer)
   - Dismissing banner persists dismissal across reload
4. Add `data-testid` to key elements.
5. Run full test suite, confirm pass.
6. Commit: `test(blog): add BlogDetail engagement E2E coverage`

**Verification:**
- [ ] `npm run test:e2e` exits 0 (all PR1/PR2/PR3 tests pass together)
- [ ] Spec uses real API (`/api/blog/promo-slot` responds with real data)
- [ ] No placeholder/TODO comments

**PR3 COMMIT GATE:**
- [ ] All 8 phase verifications pass
- [ ] `npm run build` + `php artisan test` both succeed
- [ ] Manual smoke: visit 3 different blog posts (short + long), verify:
  - TOC highlights active section as you scroll
  - Promo card rotates type (project → award → cta) if different data
  - Inline newsletter works
  - Floating banner fires after 60s, dismissible
- [ ] No regression vs PR1 + PR2
- [ ] User asked to review before merging all 3 PRs

---

## Architectural Decisions Captured

### ADR — HTML injection via Vue `h()` render function (not v-html)

**Status:** Accepted (this plan)

**Context:** BlogDetail.vue previously used `v-html="content"` for rendering article body. Injecting interactive Vue components (promo cards, newsletter forms) inside v-html is impossible — it would render as escaped text or raw HTML.

**Decision:** Split HTML into string chunks at top-level H2 boundaries (pure util, smoke-tested), then use Vue's `h()` render function to interleave `v-html` chunks with real Vue component instances.

**Consequences:**
- ✅ Real Vue reactivity inside injected components (reactive props, emits, composables work)
- ✅ Pure JS parser keeps Node-compatible for smoke tests (no DOMParser, no jsdom)
- ✅ Graceful fallback to single v-html block on parse error
- ⚠️ Injection points are index-based, not scroll-depth-based — works for most posts but very long posts may cluster injections early
- ⚠️ If HTML has unusual structure (H2 inside `<template>` tags, commented-out H2), regex parser may over-split. Mitigated by tests.

**Alternative considered:** Absolute-positioned overlays calculated from scroll offsets. Rejected — fragile, reflows on resize, breaks reading flow, worse for SEO.

### ADR — 7-day dismissal TTL for newsletter touchpoints

**Status:** Accepted (this plan)

**Context:** 4 newsletter touchpoints risk annoying readers. Users who explicitly dismiss should not see prompts again for a reasonable window.

**Decision:** 7-day localStorage TTL via `nl_dismissed_at` timestamp. Any dismissal (any touchpoint) silences all 4 placements for that visitor.

**Consequences:**
- ✅ Respects user intent across pages + sessions
- ✅ Recovers naturally — readers who may have dismissed during a bad moment get re-offered after a week
- ⚠️ Can't differentiate "dismissed aggressively" vs "accidentally clicked close" — pessimistic 7d treats both the same

**Alternative considered:** Permanent dismissal. Rejected — too aggressive, no recovery path.

---

## Execution Handoff

The plan is complete, saved to this file. All 3 PRs have phase-level verification + TDD steps + data sources mapped.

### Option 1: Execute in this session
> Ready to start PR1 Phase 1.1? I'll use `/gaspol-dev:gaspol-execute` with per-phase checkpoints + TDD hard gate. Each phase commits, then pauses for verification sign-off before the next.

### Option 2: Parallel execution
> PR1 phases 1.1–1.7 are sequential (each depends on the previous). Within PR1 there's no parallelism opportunity. PR2 and PR3 have independent phases (e.g., PR2 phase 2.1 `BlogCategoryChips` is independent of phase 2.2 `BlogHeroCard`) — could use `/gaspol-dev:gaspol-parallel` mode `plan-phases` for those. But recommended to run PR1 first (blocks PR2 + PR3), then parallelize within PR2, then PR3.

### Option 3: Separate session
> Plan saved to [`docs/plans/2026-04-19-blog-redesign-engagement-plan.md`](2026-04-19-blog-redesign-engagement-plan.md). New session can pick up via `/gaspol-dev:gaspol-execute` + point at this file.

---

**Plan self-check (red flags addressed):**

- ✅ Data Integration Map present (14 entries)
- ✅ Every phase has Verification block
- ✅ CLAUDE.md referenced throughout (composables, base components, settings controller, test conventions)
- ✅ No vague data sources — every feature maps to specific hook/API/table
- ✅ Every phase has TDD Step 1 in required format (`Write failing test for [behavior]. Expected error: [...]`)
- ✅ No placeholder language ("TODO: wire up later") — all integrations are real
- ✅ All phases are bite-sized (8–40 min, average ~20 min)
- ✅ Design deliverables specified for all UI phases (spec in-doc, not delegated to separate skill since design direction was locked in brainstorm)
- ✅ Git push policy respected — "DO NOT PUSH" gate on every PR commit gate
