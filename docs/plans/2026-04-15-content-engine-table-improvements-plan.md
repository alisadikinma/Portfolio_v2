> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Four targeted improvements to the Content Engine ideas table (`/admin/content-engine`): a published-date column powered by existing `source_data.pub_date` JSON, a fix for the broken Google Trends scraper via the internal `dailytrends` JSON API, a bulk-selection UI with 4 actions (Delete, Start Research, Archive, Revert to Draft) that reuse existing per-ID endpoints via client-side `Promise.all` chunks, and conversion of the status-specific "Next →" text button into a consistent Play ▶ icon matching the sibling action icons. No migrations, no new endpoints, no new composables — 2 files modified (1 backend + 1 frontend).

Companion design doc: `docs/plans/2026-04-15-content-engine-table-improvements-design.md`.

## Architecture Context

**From Portfolio_v2 CLAUDE.md + verified code state:**
- Frontend admin page: `frontend/src/views/admin/ContentEngine.vue` (~1140 lines, 4 inline modals: Edit, Trending, Configuration, Progress). Table rows at lines 95-190, script-setup at 456-end.
- Composable: `frontend/src/composables/useContentEngine.js` already exports `deleteIdea`, `archiveIdea`, `revertToDraft`, `startResearch`, `listIdeas`, etc. — **all 4 bulk action building blocks already exist**. No new methods needed.
- Backend `TrendingTopicService.php` (~457 lines): `fetchGoogleTrends()` at line 110 uses deprecated `https://trends.google.com/trending/rss?geo=X` (returns empty since late 2024). Other sources: Google News works, YouTube/TikTok brittle, Instagram has zero implementation.
- `content_ideas.source_data` is a JSON column (cast `'source_data' => 'array'` in [ContentIdea model line 47](backend/app/Models/ContentIdea.php#L47)). `importTrending` controller already stores the full topic dict including `pub_date` ([ContentIdeaController.php:265](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L265)). So published date flows end-to-end for google_news today — just needs frontend rendering.
- Existing status-specific buttons at [ContentEngine.vue:148-170](frontend/src/views/admin/ContentEngine.vue#L148-L170): each status has its own colored text button with distinct labels (Next / View Progress / Preview Article / Finalize / View / Restore) all dispatching to existing handlers (`openConfigModal`, `openProgressModal`, `openResearchModal`, `handleRestore`). **Plan keeps dispatch logic unchanged** — only swaps text+color button for consistent Play icon button.
- Existing sibling icon buttons at [ContentEngine.vue:174-181](frontend/src/views/admin/ContentEngine.vue#L174-L181) use the style `p-1.5 rounded text-neutral-400 hover:text-amber-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors` — Play button will reuse this exact class set.

**Existing patterns reused:**
- Vue 3.5 `<script setup>` convention (no Options API)
- Tailwind 4 utility classes (no custom CSS)
- Heroicons inline SVG (no icon library)
- TanStack Query cache invalidation via composable methods (existing behavior)
- `Promise.all` parallel with chunking pattern (applied manually; no existing utility)

## Tech Stack

- Backend: Laravel 12 + PHP 8.2 + `Http::` facade (Guzzle). No new packages.
- Frontend: Vue 3.5 + Rolldown-Vite 7.1 + Tailwind 4. No new packages.
- Build verification: `npm run build` (no tsc since JS not TS in this project, no Vitest configured).
- Manual smoke: Vite dev server + browser.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Published date column | `idea.source_data.pub_date` (JSON) | Reactive render from existing `ideas.value[i]` | Yes | Render — no API change |
| TimeAgo formatter | Pure function `formatRelative(iso)` in script setup | NEW local fn | No | Create inline in ContentEngine.vue |
| Tooltip absolute datetime | Native `title` attr with `new Date(iso).toLocaleString()` | Browser API | Yes | Use directly |
| Google Trends data | `https://trends.google.com/trends/api/dailytrends` internal JSON API | `Http::get()` in TrendingTopicService | Partial | REPLACE `fetchGoogleTrends` body |
| Google Trends date parse | `$search.date` string "YYYYMMDD" | NEW helper `parseGoogleTrendsDate` | No | Add helper, Carbon parse |
| Disabled source UI | `trendingSources` array with added `disabled` + `badge` keys | Existing array in ContentEngine.vue:489-496 | Partial | Extend array shape |
| Bulk checkbox state | `selectedIdeaIds: ref([])` | NEW local ref | No | Create |
| Bulk select-all (current page) | Toggle-all computed against `ideas.value` | NEW local computed | No | Create |
| Bulk Delete | `deleteIdea(id)` composable | [useContentEngine.js:52](frontend/src/composables/useContentEngine.js#L52) | Yes | Loop via `Promise.all` chunks |
| Bulk Start Research | `startResearch(id, {languages:['id','en'], instructions:''})` | [useContentEngine.js:67](frontend/src/composables/useContentEngine.js#L67) | Yes | Loop, chunks of 3 (SSH-heavy) |
| Bulk Archive | `archiveIdea(id)` | [useContentEngine.js:54](frontend/src/composables/useContentEngine.js#L54) | Yes | Loop, chunks of 10 |
| Bulk Revert | `revertToDraft(id)` | [useContentEngine.js:58](frontend/src/composables/useContentEngine.js#L58) | Yes | Loop, chunks of 10 |
| Play icon button | Heroicons play `<path>` inline SVG | NEW markup | No | Replace existing status-specific text buttons |
| nextActionLabel(idea) | Status → verb map | NEW pure function | No | Create inline |
| nextActionHandler(idea) | Dispatch to existing handlers (openConfigModal/openProgressModal/openResearchModal/handleRestore) | [ContentEngine.vue:899 + 1125](frontend/src/views/admin/ContentEngine.vue#L899) | Yes | Dispatcher fn, no handler changes |

## Phases

---

### Phase 0: Create feature branch

**Estimated time:** 2 minutes

**Files:** None (git only)

**Steps:**
1. `cd D:\Projects\Portfolio_v2 && git checkout main && git pull origin main`
2. `git checkout -b feat/content-engine-table-improvements`
3. Create TodoWrite with 1:1 phase mapping (expected 10 phases)

**Verification:**
- [ ] Branch `feat/content-engine-table-improvements` active
- [ ] `git status` clean

---

### Phase 1: Backend — replace Google Trends scraper with dailytrends JSON API

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Services\TrendingTopicService.php`

**Steps:**
1. Locate `fetchGoogleTrends` method at line 110. Replace its body (not the signature) with the `dailytrends` implementation from the design doc (see "A. Fix Google Trends scraper" section). Key elements:
   - Use `https://trends.google.com/trends/api/dailytrends` with query params `hl`, `tz=-480`, `geo`, `ns=15`
   - Strip `)]}',?\s*` prefix from response body before `json_decode`
   - Parse nested path `default.trendingSearchesDays[].trendingSearches[]`
   - Extract `title.query` and `formattedTraffic`
   - Each item gets `source = 'google_trends'`, `country = $geo`, `score = 70`, `pub_date = parseGoogleTrendsDate($day['date'])`
2. Add private helper `parseGoogleTrendsDate(?string $yyyymmdd): ?string` after the method. Returns ISO8601 string or null. Use `\Carbon\Carbon::createFromFormat('Ymd', ...)`.
3. Keep existing `Log::info` + `Log::warning` pattern for consistency with siblings.
4. `D:/xampp/php/php.exe -l backend/app/Services/TrendingTopicService.php` — syntax check

**Verification:**
- [ ] PHP syntax clean
- [ ] `fetchGoogleTrends` returns array (empty OR populated)
- [ ] `parseGoogleTrendsDate('20260415')` returns ISO8601 string (manual tinker test when local MySQL up, else verify on VPS in Phase 9)
- [ ] No placeholder/TODO comments
- [ ] Handles malformed/missing JSON gracefully (returns empty array, not throws)

---

### Phase 2: Frontend — trending source dropdown cleanup (disable TikTok/YT, remove IG)

**Estimated time:** 6 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue`

**Steps:**
1. Find `trendingSources` array (search for `'Google Trends'` label) — currently around line 489-496. Replace with:
   ```js
   const trendingSources = [
     { label: 'All Sources', value: '' },
     { label: 'Google News', value: 'google_news' },
     { label: 'Google Trends', value: 'google_trends' },
     { label: 'YouTube', value: 'youtube', disabled: true, badge: 'Coming soon' },
     { label: 'TikTok', value: 'tiktok', disabled: true, badge: 'Coming soon' },
   ]
   ```
   Instagram entry removed entirely.
2. Find the source selector dropdown at the top of the Pull Trending action (the `<button v-for="src in trendingSources" ... @click="openTrendingModal(src.value)">` loop near line 30). Update the template:
   ```vue
   <button
     v-for="src in trendingSources"
     :key="src.value"
     @click="!src.disabled && openTrendingModal(src.value)"
     :disabled="src.disabled"
     class="... disabled:opacity-50 disabled:cursor-not-allowed"
   >
     {{ src.label }}
     <span v-if="src.badge" class="ml-2 inline-block px-1.5 py-0.5 text-[10px] font-medium rounded bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400">
       {{ src.badge }}
     </span>
   </button>
   ```
3. Also update the second source filter selector INSIDE the Trending Preview Modal (the `<select v-model="trendingSourceFilter">` around line 287). Add `:disabled="src.disabled"` on `<option>` — HTML supports disabled options natively.

**Verification:**
- [ ] Instagram no longer appears in any dropdown
- [ ] YouTube + TikTok appear grayed out with "Coming soon" badge in Pull Trending menu
- [ ] Clicking a disabled source does nothing (preventDefault via `!src.disabled &&`)
- [ ] Google News + Google Trends selectable as normal
- [ ] No console errors
- [ ] `npm run build` clean

---

### Phase 3: Frontend — add Published column + TimeAgo formatter

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue`

**Steps:**
1. In `<script setup>`, add helper function near other formatters (search for `formatStepName` or `formatDate` area, ~line 954):
   ```js
   function formatPubDateRelative(iso) {
     if (!iso) return null
     const d = new Date(iso)
     if (isNaN(d.getTime())) return null
     const diff = (Date.now() - d.getTime()) / 1000
     if (diff < 60) return 'just now'
     if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
     if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
     if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`
     return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
   }
   function formatPubDateAbsolute(iso) {
     if (!iso) return ''
     const d = new Date(iso)
     if (isNaN(d.getTime())) return ''
     return d.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
   }
   ```
2. In template header row (around line 120 area — `<th>Topic</th> <th>Pillar</th> ... <th>Source</th>`), add a new `<th>` AFTER `Source`:
   ```vue
   <th class="px-4 py-3 text-left font-medium text-neutral-500 dark:text-neutral-400">Published</th>
   ```
3. In the data row, add a new `<td>` AFTER the Source cell:
   ```vue
   <td class="px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400">
     <span
       v-if="formatPubDateRelative(idea.source_data?.pub_date)"
       :title="formatPubDateAbsolute(idea.source_data?.pub_date)"
     >
       {{ formatPubDateRelative(idea.source_data?.pub_date) }}
     </span>
     <span v-else>—</span>
   </td>
   ```
4. Confirm source_data JSON access works: `idea.source_data?.pub_date` — optional chaining handles null source_data (older manual ideas).

**Verification:**
- [ ] New "Published" column header renders between Source and Auto
- [ ] google_news ideas show "Xh ago" / "Xd ago" values
- [ ] Older manual ideas (no source_data) show "—"
- [ ] Hover tooltip shows absolute datetime like "Apr 15, 2026, 11:30 AM"
- [ ] `npm run build` clean

---

### Phase 4: Frontend — bulk selection state + checkbox column

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue`

**Steps:**
1. In `<script setup>`, after `const ideas = ref([])` (~line 505), add:
   ```js
   const selectedIdeaIds = ref([])
   const allPageSelected = computed(() =>
     ideas.value.length > 0 && ideas.value.every(i => selectedIdeaIds.value.includes(i.id))
   )
   const somePageSelected = computed(() =>
     selectedIdeaIds.value.length > 0 && !allPageSelected.value
   )
   function togglePageSelection() {
     if (allPageSelected.value) {
       const pageIds = ideas.value.map(i => i.id)
       selectedIdeaIds.value = selectedIdeaIds.value.filter(id => !pageIds.includes(id))
     } else {
       const newIds = ideas.value.map(i => i.id).filter(id => !selectedIdeaIds.value.includes(id))
       selectedIdeaIds.value = [...selectedIdeaIds.value, ...newIds]
     }
   }
   ```
2. Add watcher that clears selection on filter change (insert after existing watchers, ~line 552):
   ```js
   watch([() => filters.pillar, () => filters.status, () => filters.priority, () => filters.search], () => {
     selectedIdeaIds.value = []
   })
   ```
3. In template, PREPEND a new `<th>` before the existing `<th>#</th>`:
   ```vue
   <th class="px-4 py-3 w-10">
     <input
       type="checkbox"
       :checked="allPageSelected"
       :indeterminate="somePageSelected"
       @change="togglePageSelection"
       class="rounded border-neutral-300 text-amber-600 focus:ring-amber-500"
     />
   </th>
   ```
4. In the data row, PREPEND matching `<td>`:
   ```vue
   <td class="px-4 py-3">
     <input
       type="checkbox"
       :value="idea.id"
       v-model="selectedIdeaIds"
       class="rounded border-neutral-300 text-amber-600 focus:ring-amber-500"
     />
   </td>
   ```
5. `<input type="checkbox">` with `indeterminate` as HTML attribute doesn't actually work in Vue — need a ref-based approach OR an inline DOM touch. Simplest: add `ref="headerCheckbox"` and `watch(somePageSelected, (v) => { if (headerCheckbox.value) headerCheckbox.value.indeterminate = v })`. Only implement if needed for UX; otherwise just the `:checked` works fine and skip indeterminate visual.

**Verification:**
- [ ] Header checkbox renders, row checkboxes render, all clickable
- [ ] Clicking row checkbox adds/removes id from `selectedIdeaIds`
- [ ] Header checkbox toggles all current-page rows
- [ ] Changing filter (pillar/status/priority/search) clears `selectedIdeaIds`
- [ ] Select on page 1, navigate to page 2 — page 1 selections PRESERVED (selectedIdeaIds is cumulative)
- [ ] `npm run build` clean

---

### Phase 5: Frontend — bulk action handlers (4 actions + chunking)

**Estimated time:** 12 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue`

**Steps:**
1. In `<script setup>`, after individual handlers (`handleDelete`, `handleArchive`, etc. around line 826-860), add:
   ```js
   const bulkProcessing = ref(false)

   async function runChunked(ids, chunkSize, perIdFn) {
     const results = { success: 0, failed: 0, skipped: 0 }
     for (let i = 0; i < ids.length; i += chunkSize) {
       const chunk = ids.slice(i, i + chunkSize)
       const outcomes = await Promise.all(chunk.map(async (id) => {
         try {
           const r = await perIdFn(id)
           return r?.success ? 'success' : (r?.skipped ? 'skipped' : 'failed')
         } catch {
           return 'failed'
         }
       }))
       outcomes.forEach(o => { results[o] = (results[o] || 0) + 1 })
     }
     return results
   }

   async function bulkDelete() {
     if (!confirm(`Delete ${selectedIdeaIds.value.length} idea(s)? This cannot be undone.`)) return
     bulkProcessing.value = true
     const ids = [...selectedIdeaIds.value]
     const r = await runChunked(ids, 10, (id) => deleteIdea(id))
     bulkProcessing.value = false
     toast.success(`Deleted ${r.success} / ${ids.length}${r.failed ? ` (${r.failed} failed)` : ''}`)
     selectedIdeaIds.value = []
     await refreshIdeas()
   }

   async function bulkStartResearch() {
     const targets = ideas.value.filter(i => selectedIdeaIds.value.includes(i.id) && i.status === 'draft')
     const skipped = selectedIdeaIds.value.length - targets.length
     if (targets.length === 0) {
       toast.warning('No draft ideas in selection. Start Research only applies to draft status.')
       return
     }
     if (!confirm(`Start research for ${targets.length} idea(s)?${skipped ? ` (${skipped} non-draft will be skipped)` : ''}`)) return
     bulkProcessing.value = true
     const ids = targets.map(i => i.id)
     const config = { languages: ['id', 'en'], instructions: '' }
     const r = await runChunked(ids, 3, (id) => startResearch(id, config))
     bulkProcessing.value = false
     toast.success(`Started ${r.success} / ${ids.length}${r.failed ? ` (${r.failed} failed)` : ''}${skipped ? `, ${skipped} skipped` : ''}`)
     selectedIdeaIds.value = []
     await refreshIdeas()
   }

   async function bulkArchive() {
     const targets = ideas.value.filter(i => selectedIdeaIds.value.includes(i.id) && i.status !== 'archived')
     const skipped = selectedIdeaIds.value.length - targets.length
     if (targets.length === 0) {
       toast.warning('All selected ideas are already archived.')
       return
     }
     if (!confirm(`Archive ${targets.length} idea(s)?${skipped ? ` (${skipped} already archived)` : ''}`)) return
     bulkProcessing.value = true
     const ids = targets.map(i => i.id)
     const r = await runChunked(ids, 10, (id) => archiveIdea(id))
     bulkProcessing.value = false
     toast.success(`Archived ${r.success} / ${ids.length}${r.failed ? ` (${r.failed} failed)` : ''}`)
     selectedIdeaIds.value = []
     await refreshIdeas()
   }

   async function bulkRevert() {
     if (!confirm(`Revert ${selectedIdeaIds.value.length} idea(s) to draft? Generated content will be cleared.`)) return
     bulkProcessing.value = true
     const ids = [...selectedIdeaIds.value]
     const r = await runChunked(ids, 10, (id) => revertToDraft(id))
     bulkProcessing.value = false
     toast.success(`Reverted ${r.success} / ${ids.length}${r.failed ? ` (${r.failed} failed)` : ''}`)
     selectedIdeaIds.value = []
     await refreshIdeas()
   }
   ```
2. Ensure `archiveIdea`, `revertToDraft` destructured from `useContentEngine()` at top (~line 466-485). Currently `startResearch` + `deleteIdea` are — add `archiveIdea` and `revertToDraft` to that destructure if missing.

**Verification:**
- [ ] PHP syntax / Vue SFC builds clean
- [ ] Each bulk fn validates status before acting (skip non-eligible)
- [ ] Confirm dialog fires before destructive actions
- [ ] Progress throttle: Start Research = 3 concurrent, others = 10
- [ ] Selection clears after completion
- [ ] `refreshIdeas()` fires to update table state
- [ ] No placeholder/TODO comments

---

### Phase 6: Frontend — sticky bulk action bar UI

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue`

**Steps:**
1. Insert ABOVE the ideas table container (before the `<div class="bg-white dark:bg-neutral-800 rounded-xl shadow-sm ...">` that wraps the table — around line 93):
   ```vue
   <div
     v-if="selectedIdeaIds.length"
     class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 p-3 mb-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/50 rounded-lg shadow-sm"
   >
     <span class="text-sm font-medium text-amber-800 dark:text-amber-300">
       {{ selectedIdeaIds.length }} selected
     </span>
     <div class="flex flex-wrap gap-2">
       <button
         @click="bulkStartResearch"
         :disabled="bulkProcessing"
         class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
       >
         <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
         Start Research
       </button>
       <button
         @click="bulkArchive"
         :disabled="bulkProcessing"
         class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-50"
       >
         <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
         Archive
       </button>
       <button
         @click="bulkRevert"
         :disabled="bulkProcessing"
         class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-50"
       >
         Revert
       </button>
       <button
         @click="bulkDelete"
         :disabled="bulkProcessing"
         class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white disabled:opacity-50"
       >
         <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
         Delete
       </button>
       <button
         @click="selectedIdeaIds = []"
         class="px-3 py-1.5 text-xs font-medium rounded-lg text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
       >
         Clear
       </button>
     </div>
   </div>
   ```

**Verification:**
- [ ] Bar appears only when `selectedIdeaIds.length >= 1`
- [ ] Bar is sticky (scrolls with page to stay at top)
- [ ] All 5 buttons (Start Research, Archive, Revert, Delete, Clear) fire correct handlers
- [ ] During bulk operation, all 4 action buttons disabled (spinner not required for MVP)
- [ ] Clear button empties selection instantly
- [ ] `npm run build` clean

---

### Phase 7: Frontend — replace "Next →" text buttons with Play icon button

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue`

**Steps:**
1. In `<script setup>`, after existing handler functions, add:
   ```js
   function nextActionLabel(idea) {
     const map = {
       draft: 'Start Research',
       researching: 'View Progress',
       article_ready: 'Preview Article',
       generating_images: 'View Progress',
       images_ready: 'Finalize',
       completed: 'View',
       archived: 'Restore',
     }
     return map[idea.status] || 'Next'
   }
   function triggerNextAction(idea) {
     if (idea.status === 'draft') return openConfigModal(idea)
     if (idea.status === 'researching' || idea.status === 'generating_images') return openProgressModal(idea)
     if (idea.status === 'article_ready' || idea.status === 'images_ready' || idea.status === 'completed') return openResearchModal(idea)
     if (idea.status === 'archived') return handleRestore(idea.id)
   }
   ```
2. In template, REPLACE the block of 7 status-specific text buttons (ContentEngine.vue lines 148-170) with a SINGLE icon button:
   ```vue
   <!-- Unified status-aware action button (replaces 7 text buttons) -->
   <button
     @click="triggerNextAction(idea)"
     class="p-1.5 rounded text-neutral-400 hover:text-amber-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
     :title="nextActionLabel(idea)"
   >
     <!-- Show spinner for in-progress statuses -->
     <svg v-if="['researching', 'generating_images'].includes(idea.status)" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
       <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
       <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
     </svg>
     <!-- Play icon for actionable statuses -->
     <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
       <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
     </svg>
   </button>
   ```
3. The "Common actions" block below (edit / archive / trash at lines 172-183) stays unchanged. They still hide for `generating_images` status.
4. Also remove the `v-if="idea.status !== 'archived'"` guard on the archive button since Restore is now handled via the Play icon dispatch — wait, that guard ALSO hides the row archive button for already-archived ideas. Keep it. No change needed.

**Verification:**
- [ ] Old colored text buttons (Next / View Progress / Preview Article / Finalize / View / Restore) ALL removed
- [ ] Single icon button appears for every status
- [ ] Spinner shown for `researching` and `generating_images` statuses
- [ ] Play icon shown for all other statuses
- [ ] Hover tooltip shows correct status-specific label
- [ ] Click dispatches to same handler as before (openConfigModal for draft, openProgressModal for in-flight, etc.)
- [ ] `npm run build` clean
- [ ] Visual parity with sibling icons (edit, archive, trash) — same size + color scheme

---

### Phase 8: Frontend build + local smoke test

**Estimated time:** 5 minutes

**Files:** None (verification only)

**Steps:**
1. `cd D:\Projects\Portfolio_v2\frontend && npm run build`
2. Confirm build output under 48KB for `ContentEngine-*.js` chunk (current baseline: 46.16 kB; expected: ~50-52 kB after additions)
3. `npm run dev` + browser manual smoke:
   - Navigate to `/admin/content-engine`
   - Confirm Published column shows dates
   - Check a row, confirm bulk bar appears
   - Test "Clear" button
   - Click Play icon on a draft idea → Configure Research modal opens
   - Open Pull Trending → YouTube/TikTok are grayed with "Coming soon" badge, Instagram absent

**Verification:**
- [ ] Build succeeds
- [ ] No console errors on initial load
- [ ] All 4 improvements visible and functional locally
- [ ] No layout overflow on smaller desktop widths (1280px)

---

### Phase 9: Commit + push + VPS deploy

**Estimated time:** 8 minutes

**Files:** All Phases 1-7

**Steps:**
1. `cd D:\Projects\Portfolio_v2 && git add backend/app/Services/TrendingTopicService.php frontend/src/views/admin/ContentEngine.vue docs/plans/2026-04-15-content-engine-table-improvements-design.md docs/plans/2026-04-15-content-engine-table-improvements-plan.md`
2. Commit:
   ```
   feat(content-engine): published date column + bulk actions + Next icon + trending fixes

   Table improvements:
   - Published column from source_data.pub_date (relative + tooltip)
   - Bulk selection (checkbox col + sticky action bar)
   - 4 bulk actions: Delete / Start Research / Archive / Revert to Draft
   - Status-specific text buttons → unified Play ▶ icon button

   Trending sources:
   - Fix Google Trends (deprecated RSS → internal dailytrends JSON API)
   - Disable YouTube + TikTok in UI (Coming soon badge)
   - Remove Instagram from dropdown (no backend implementation)
   ```
3. Merge to main and push:
   - `git checkout main && git merge feat/content-engine-table-improvements --no-ff`
   - `git push origin main`
4. VPS deploy via SSH MCP:
   - `cd /var/www/Portfolio_v2 && sudo git pull origin main`
   - `cd backend && sudo -u www-data php artisan config:clear`
   - `cd ../frontend && sudo npm run build`
5. VPS smoke: hit Pull Trending — confirm both Google News AND Google Trends return items (or at least don't error).

**Verification:**
- [ ] Commit on main; pushed to origin
- [ ] VPS `git pull` clean
- [ ] VPS `npm run build` succeeds
- [ ] `curl https://alisadikinma.com/admin/content-engine` loads without 500
- [ ] Pull Trending on VPS returns items from both google_news AND google_trends (proves new scraper works on real Google endpoint)

---

### Phase 10: CLAUDE.md update (minor)

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\CLAUDE.md`

**Steps:**
1. Update the "Admin UI: Content Engine Page" bullet to mention bulk selection + published column.
2. Update "Pull Trending" bullet: note 2 working sources (Google News + Google Trends), 2 disabled (YouTube + TikTok — coming soon), Instagram removed.
3. No env var changes.
4. Commit: `docs: update CLAUDE.md — Content Engine table bulk actions + trending source state`.

**Verification:**
- [ ] CLAUDE.md reflects new UI state accurately
- [ ] No stale refs to Instagram source

---

## File Change Summary

| Phase | File | Action |
|---|---|---|
| 0 | (branch) | CHECKOUT |
| 1 | `backend/app/Services/TrendingTopicService.php` | REPLACE fetchGoogleTrends + add parseGoogleTrendsDate helper |
| 2 | `frontend/src/views/admin/ContentEngine.vue` | Trending source dropdown cleanup |
| 3 | `frontend/src/views/admin/ContentEngine.vue` | Published column + formatters |
| 4 | `frontend/src/views/admin/ContentEngine.vue` | Bulk state + checkbox col |
| 5 | `frontend/src/views/admin/ContentEngine.vue` | Bulk action handlers + chunking |
| 6 | `frontend/src/views/admin/ContentEngine.vue` | Sticky bulk bar |
| 7 | `frontend/src/views/admin/ContentEngine.vue` | Play icon replaces 7 text buttons |
| 8 | (build + smoke) | VERIFY |
| 9 | commit + VPS deploy | DEPLOY |
| 10 | `CLAUDE.md` | UPDATE DOCS |

**Total: 2 source files modified.**

## Dependencies

- No new npm packages
- No new composer packages
- No DB migration
- No new env vars
- No backend endpoint additions

## Rollout Safety

- All changes additive — bulk bar only appears on selection, Published column gracefully degrades to `—` when null, Play icon preserves existing dispatch logic
- Google Trends swap: if `dailytrends` API returns unparseable JSON, scraper logs warning + returns empty array (same graceful degrade as current broken state)
- No cross-phase dependencies that require specific ordering EXCEPT: Phase 4 must come before Phase 5 & 6 (handlers use `selectedIdeaIds`). Phase 7 independent of other phases.

## Estimated Total Time

- Phase 0 (branch): 2 min
- Phase 1 (backend): 10 min
- Phase 2 (source dropdown): 6 min
- Phase 3 (published column): 10 min
- Phase 4 (bulk state): 8 min
- Phase 5 (bulk handlers): 12 min
- Phase 6 (bulk bar UI): 8 min
- Phase 7 (Play icon): 8 min
- Phase 8 (smoke): 5 min
- Phase 9 (deploy): 8 min
- Phase 10 (docs): 3 min
- **Total: ~80 minutes**

## Open Questions

None — all 4 design decisions locked upfront.

## Known Deviations (pre-accepted)

- **No Feature tests added** — same sqlite+MODIFY COLUMN ENUM harness blocker as prior plans. Visual + functional verification via build + manual smoke at Phase 8 + VPS smoke at Phase 9.
- **No `indeterminate` header-checkbox visual** — Vue doesn't bind `indeterminate` via attribute; implementing requires template ref + watcher. YAGNI — the `:checked` binding is sufficient UX.
- **Instagram scraper NOT implemented** — explicit scope decision from brainstorm Q1. User can revisit later.
