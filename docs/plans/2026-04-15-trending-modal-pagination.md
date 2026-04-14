> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Fix the misleading "N selected" counter in the Trending Topics modal (`ContentEngine.vue` admin UI) and add client-side pagination + wider modal so users can scan 100+ trending records efficiently. The current counter shows global selection length even when filter hides items, leading users to believe filtered results are 119 when only 8 are visible. The fix splits the display into "in view" + "total", widens the modal to `max-w-7xl` with a 4-column grid, and paginates client-side at 24 records per page (no backend change).

Companion brainstorm captured in this conversation; no separate design doc since scope is single-file.

## Architecture Context

**From CLAUDE.md:**
- Frontend: Vue 3.5 + `<script setup>` only, Tailwind 4 utility-first, minimal custom CSS
- Admin Content Engine page: `frontend/src/views/admin/ContentEngine.vue` (~1140 lines, 4 inline modals: Edit, Trending, Configuration, Progress)
- Composable: `useContentEngine.js` provides `pullTrending(source)` + `importTrending(topics)` — no change needed
- Backend `ContentIdeaController::pullTrending` returns flat array via `TrendingTopicService` aggregating Google Trends, YouTube, TikTok, Google News, Instagram — no change needed

**Existing modal state (lines 515-552):**
- `showTrendingModal: ref(false)`
- `trendingTopics: ref([])` — full fetched list
- `selectedTrending: ref([])` — array of selected topic objects
- `trendingSourceFilter: ref('')`
- `trendingSearch: ref('')`
- `filteredTrending: computed(...)` — applies source + text filter
- `allFilteredSelected: computed(...)` — checks if all filtered are in selection
- `toggleSelectAll()` function — toggles all-filtered

**Existing modal template (lines 282-323):**
- Container: `max-w-lg w-full mx-4 p-6 max-h-[80vh]`
- Grid: `grid-cols-1 md:grid-cols-2 gap-2`
- Footer: `{{ selectedTrending.length }} selected` + `Add {{ selectedTrending.length }} to Ideas List →`

## Tech Stack

- Vue 3.5 Composition API (`<script setup>`)
- Tailwind 4 utility classes (no new CSS)
- No new dependencies, no new composables, no backend change
- Frontend has no automated test framework — manual verification via Vite dev server

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Trending list fetch | `TrendingTopicService` aggregator | `pullTrending(source)` from `useContentEngine.js` | Yes | Use existing — no API change |
| Topic import | `ContentIdeaController::importTrending` | `importTrending(topics)` from `useContentEngine.js` | Yes | Use existing — submits global `selectedTrending.value` |
| Filter (text + source) | `filteredTrending` computed | Already in `ContentEngine.vue` ~line 533 | Yes | Reuse |
| Visible-selected counter | Intersection of `filteredTrending` ∩ `selectedTrending` | NEW computed `visibleSelectedCount` | No | Create computed in `<script setup>` |
| Total-selected counter | `selectedTrending.value.length` | NEW computed `totalSelectedCount` | No | Create (trivial) |
| Page slice | `filteredTrending.value.slice(start, end)` | NEW computed `pagedTrending` | No | Create computed |
| Total pages | `Math.ceil(filtered.length / perPage)` | NEW computed `totalPages` | No | Create computed |
| Current page state | Local ref | NEW `currentPage = ref(1)` | No | Create ref |
| Page reset on filter change | Vue watcher | NEW `watch([trendingSearch, trendingSourceFilter])` | No | Create watcher |

## Phases

---

### Phase 1: Add pagination state + computed properties

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (script setup ~lines 515-552)

**Steps:**
1. Locate the trending modal state block in `<script setup>` (search for `const trendingSearch = ref`)
2. Add ref `const currentPage = ref(1)` and constant `const perPage = 24` immediately after `trendingSearch`
3. Add computed `visibleSelectedCount` after the existing `allFilteredSelected` computed:
   ```js
   const visibleSelectedCount = computed(() =>
     filteredTrending.value.filter(t => selectedTrending.value.includes(t)).length
   )
   const totalSelectedCount = computed(() => selectedTrending.value.length)
   const totalPages = computed(() =>
     Math.max(1, Math.ceil(filteredTrending.value.length / perPage))
   )
   const pagedTrending = computed(() => {
     const start = (currentPage.value - 1) * perPage
     return filteredTrending.value.slice(start, start + perPage)
   })
   const pageRangeLabel = computed(() => {
     if (filteredTrending.value.length === 0) return '0 of 0'
     const start = (currentPage.value - 1) * perPage + 1
     const end = Math.min(currentPage.value * perPage, filteredTrending.value.length)
     return `${start}-${end} of ${filteredTrending.value.length}`
   })
   ```
4. Add a watcher that resets to page 1 when filter or search changes:
   ```js
   watch([trendingSearch, trendingSourceFilter], () => { currentPage.value = 1 })
   ```
5. Verify `watch` is already imported from vue at line 457; if not, add it.

**Verification:**
- [ ] `npm run build` runs without errors
- [ ] Vue DevTools shows `visibleSelectedCount`, `totalSelectedCount`, `totalPages`, `pagedTrending`, `pageRangeLabel` reactive
- [ ] Typing in trending search resets `currentPage` to 1 (verify in DevTools)
- [ ] `pagedTrending.value.length` ≤ 24 always
- [ ] No placeholder/TODO comments in added code

---

### Phase 2: Widen modal + switch grid to 4 columns

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (template ~line 284, 300)

**Steps:**
1. Locate trending modal container (line 284): `<div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl max-w-lg w-full mx-4 p-6 max-h-[80vh] flex flex-col">`
2. Replace `max-w-lg` with `max-w-7xl` (Tailwind = 1280px)
3. Replace `max-h-[80vh]` with `max-h-[85vh]` for slightly more vertical room
4. Locate the grid container (line 300): `<div class="flex-1 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-2 min-h-0 content-start">`
5. Replace grid classes with: `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2`

**Verification:**
- [ ] Modal opens at 1280px max width on desktop
- [ ] At ≥1280px viewport: 4 cards per row visible
- [ ] At ~1024-1279px: 3 cards per row
- [ ] At ~640-1023px: 2 cards per row
- [ ] At <640px: 1 card per row
- [ ] No layout overflow / horizontal scrollbar
- [ ] No placeholder/TODO comments

---

### Phase 3: Switch v-for to paged slice

**Estimated time:** 2 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (template ~line 306)

**Steps:**
1. Locate the card v-for loop (line 306): `<label v-for="topic in filteredTrending" :key="topic.title || topic.topic" class="...">`
2. Change `v-for="topic in filteredTrending"` → `v-for="topic in pagedTrending"`
3. Locate empty-state line (305): `<div v-else-if="!filteredTrending.length" ...>` — KEEP as `filteredTrending.length` (we want empty state to fire when filter returns nothing across all pages, not just current page)

**Verification:**
- [ ] Modal shows max 24 cards per page
- [ ] Empty filter result still shows "No trending topics found" message
- [ ] Loading spinner still works
- [ ] Selecting a topic on page 2 still updates `selectedTrending` (Vue reactivity preserved)
- [ ] No placeholder/TODO comments

---

### Phase 4: Pagination control bar

**Estimated time:** 6 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (template — insert between grid and footer ~line 313-314)

**Steps:**
1. Insert pagination bar AFTER the closing `</div>` of the grid (line 313) and BEFORE the footer divider (line 314):
   ```vue
   <div v-if="filteredTrending.length > perPage" class="flex items-center justify-between mt-3 pt-3 border-t border-neutral-100 dark:border-neutral-700/50">
     <span class="text-xs text-neutral-500 dark:text-neutral-400">
       Showing {{ pageRangeLabel }}
     </span>
     <div class="flex items-center gap-1">
       <button
         @click="currentPage = Math.max(1, currentPage - 1)"
         :disabled="currentPage === 1"
         class="px-2 py-1 text-xs rounded border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-40 disabled:cursor-not-allowed"
       >
         &lsaquo; Prev
       </button>
       <span class="px-2 text-xs text-neutral-600 dark:text-neutral-400">
         Page {{ currentPage }} of {{ totalPages }}
       </span>
       <button
         @click="currentPage = Math.min(totalPages, currentPage + 1)"
         :disabled="currentPage === totalPages"
         class="px-2 py-1 text-xs rounded border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-40 disabled:cursor-not-allowed"
       >
         Next &rsaquo;
       </button>
     </div>
   </div>
   ```
2. YAGNI: skip numbered page buttons (1-2-3...n). Prev/Next is enough for 6-7 pages typical use case.

**Verification:**
- [ ] Pagination bar appears only when `filteredTrending.length > 24`
- [ ] Bar disappears when filter narrows results below 25
- [ ] Prev disabled on page 1, Next disabled on last page
- [ ] Clicking Next advances `currentPage` and grid scrolls to top of next slice (cards re-render)
- [ ] Page label shows correct "Page X of Y"
- [ ] Range label shows "1-24 of 127" then "25-48 of 127" etc.
- [ ] No placeholder/TODO comments

---

### Phase 5: Fix the misleading counter (footer)

**Estimated time:** 4 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (template — footer ~line 314-322)

**Steps:**
1. Locate the footer block (line 314): `<div class="flex items-center justify-between mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">`
2. Replace the existing `<span class="text-xs text-neutral-500 ...">{{ selectedTrending.length }} selected</span>` with a two-line stack:
   ```vue
   <div class="flex flex-col text-xs">
     <span class="text-neutral-700 dark:text-neutral-300 font-medium">
       {{ visibleSelectedCount }} in view selected
     </span>
     <span v-if="totalSelectedCount !== visibleSelectedCount" class="text-neutral-500 dark:text-neutral-400">
       {{ totalSelectedCount }} total across all filters
     </span>
   </div>
   ```
3. Update the submit button text to use `totalSelectedCount` (it always submits the global selection):
   - Change `:disabled="!selectedTrending.length || isLoading"` → `:disabled="!totalSelectedCount || isLoading"`
   - Change `Add {{ selectedTrending.length }} to Ideas List →` → `Add {{ totalSelectedCount }} to Ideas List →`

**Verification:**
- [ ] When filter is empty + 24 selected → counter shows "24 in view selected" (no second line, totals match)
- [ ] When filter active + 5 visible-selected + 100 hidden-selected → first line "5 in view selected", second line "105 total across all filters"
- [ ] Submit button shows count from `totalSelectedCount` (the actual number being imported)
- [ ] Button disabled when `totalSelectedCount === 0`
- [ ] No placeholder/TODO comments

---

### Phase 6: Reset pagination state on modal open + after import

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (functions `openTrendingModal`, `handleImportTrending`)

**Steps:**
1. Find `openTrendingModal` function (search for `function openTrendingModal`)
2. Add `currentPage.value = 1` immediately after the existing `selectedTrending.value = []` reset (or wherever modal state is reset on open)
3. Find `handleImportTrending` function — at the success branch where modal closes (`showTrendingModal.value = false`), also add `currentPage.value = 1` for clean re-open

**Verification:**
- [ ] Reopening the modal always shows page 1
- [ ] After successful import, modal closes and next open starts at page 1
- [ ] No regressions in existing reset behavior (selection clears, search clears)
- [ ] No placeholder/TODO comments

---

### Phase 7: Manual smoke test on Vite dev server

**Estimated time:** 5 minutes (manual)

**Files:** None

**Steps:**
1. `cd D:\Projects\Portfolio_v2\frontend && npm run dev`
2. Open http://localhost:5173/admin/content-engine
3. Click "Pull Trending" → "All Sources"
4. Verify wider modal renders 3-4 cards per row
5. Verify pagination bar appears with "Showing 1-24 of N" + Prev/Next
6. Click "Select All" → counter shows "24 in view selected" + "127 total across all filters"
7. Type "claude" in search → counter updates to "8 in view selected" (or however many match) + "127 total" (selections preserved)
8. Click "Add 127 to Ideas List" → modal closes, ideas added to table
9. Reopen modal → page 1, no selection, no search

**Verification:**
- [ ] All flows work without console errors
- [ ] Modal width matches design (1280px on >1280px viewport)
- [ ] Pagination bar appears/disappears correctly when filter narrows below 25 results
- [ ] Counter math is correct in all filter combinations
- [ ] Selection persists across page changes within a session
- [ ] Selection clears on modal close

---

### Phase 8: Commit

**Estimated time:** 2 minutes

**Files:** All Phase 1-6 changes

**Steps:**
1. `cd D:\Projects\Portfolio_v2 && git add frontend/src/views/admin/ContentEngine.vue`
2. Commit:
   ```
   fix(content-engine): trending modal pagination + accurate selection counter

   - Widen modal max-w-lg → max-w-7xl, grid 2 → 4 cols at xl breakpoint
   - Add client-side pagination (24 per page, Prev/Next bar)
   - Split misleading global counter into "in view" + "total across filters"
   - Submit button binds to total (preserves multi-filter selection workflow)
   - Auto-reset to page 1 on filter/search change + modal open
   ```

**Verification:**
- [ ] `git status` clean after commit
- [ ] `git log --oneline -1` shows the expected message
- [ ] No untracked files left behind

---

## File Change Summary

| Phase | File | Action |
|---|---|---|
| 1 | `frontend/src/views/admin/ContentEngine.vue` | Add 5 computed + 1 watcher + 2 ref vars in script setup |
| 2 | `frontend/src/views/admin/ContentEngine.vue` | Widen modal container + responsive grid classes |
| 3 | `frontend/src/views/admin/ContentEngine.vue` | Switch v-for to `pagedTrending` |
| 4 | `frontend/src/views/admin/ContentEngine.vue` | Insert pagination control bar |
| 5 | `frontend/src/views/admin/ContentEngine.vue` | Replace footer counter + button text |
| 6 | `frontend/src/views/admin/ContentEngine.vue` | Reset `currentPage` on open + after import |
| 7 | (manual smoke test) | No file change |
| 8 | (commit) | git commit |

**Total: 1 file modified, ~50 lines added/changed.**

## Dependencies

- No new npm packages
- No backend change
- No DB migration
- No environment variables

## Rollout Safety

- All changes client-side only
- No API contract change
- Existing selection workflow preserved (multi-filter accumulation still works)
- Build verification at Phase 1 catches any syntax issues early
- Manual smoke test at Phase 7 validates UX before commit

## Estimated Total Time

- Phase 1 (state): 5 min
- Phase 2 (width + grid): 3 min
- Phase 3 (paged v-for): 2 min
- Phase 4 (pagination bar): 6 min
- Phase 5 (counter fix): 4 min
- Phase 6 (reset state): 3 min
- Phase 7 (smoke test): 5 min
- Phase 8 (commit): 2 min
- **Total: ~30 minutes**

## Open Questions

None — all design decisions resolved in brainstorm phase.
