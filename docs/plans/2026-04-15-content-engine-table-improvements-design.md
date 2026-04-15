# Content Engine Table Improvements — Design

## Goal

Four UX + data-quality improvements to the Content Engine ideas table:
1. **Published date column** — show when each trending source item was originally published (relative "2h ago" + absolute on hover)
2. **Trending sources cleanup** — fix broken Google Trends scraper, disable TikTok/YouTube/Instagram in UI until implemented, keep Google News as the primary working source
3. **Bulk selection + actions** — checkbox column, select-all, sticky bulk bar with 4 actions (Delete, Start Research, Archive, Revert to Draft)
4. **Next action icon button** — replace inline "Next →" text button with a Play ▶ icon button matching other action icons (edit, archive, trash)

## Locked Decisions (from brainstorm)

| # | Decision | Choice |
|---|---|---|
| 1 | Trending sources strategy | Fix Google Trends (new endpoint), disable TikTok + YouTube + Instagram in UI with "Coming soon" badge |
| 2 | Published date display | Relative + absolute on hover (format: `2h ago` / `3d ago` / `yesterday`, tooltip `2026-04-15 11:30`) |
| 3 | Bulk action scope | Delete + Start Research + Archive + Revert to Draft (4 actions) |
| 4 | Next button icon | Play ▶ with dynamic tooltip per status |

## Diagnostic Findings (Phase 0 investigation, pre-design)

Current state of each source in `TrendingTopicService`:

| Source | Status | Details |
|---|---|---|
| `google_news` | ✅ Works | RSS from news.google.com, 3 feed URLs, `pub_date` captured correctly |
| `google_trends` | ❌ Broken | `/trending/rss?geo=X` deprecated by Google late 2024; returns empty |
| `youtube` | ⚠️ Unstable | Piped public instances (kavin.rocks etc.) frequently shut down in 2025; `pub_date` never captured |
| `tiktok` | ⚠️ Brittle | HTML regex scrape of Creative Center dehydrated state; breaks when TikTok updates page |
| `instagram` | ❌ Missing | Listed in frontend dropdown ([ContentEngine.vue:495](frontend/src/views/admin/ContentEngine.vue#L495)) but NO backend method exists |

**Root cause of user's "127 all from google_news":** Google News is the only functional source right now.

**Published date — already captured:** [TrendingTopicService.php:337](backend/app/Services/TrendingTopicService.php#L337) — `parseRss()` extracts `pubDate` into `pub_date`. This flows through `importTrending` → `ContentIdea::create([..., 'source_data' => $topic])` ([ContentIdeaController.php:265](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L265)). So `content_ideas.source_data->pub_date` is populated for google_news and (once fixed) google_trends. No DB change needed — frontend just needs to render it.

## Architecture

### 1. Published Date Column

**Backend:** No changes. `pub_date` already flows through `source_data` JSON.

**Frontend:**
- Add column header `<th>Published</th>` between Source and Auto
- Cell: `<TimeAgo :datetime="idea.source_data?.pub_date" />` — small formatter component
- Tooltip via native `title` attr with absolute datetime
- Fallback: `—` when pub_date is null or missing

**Date formatting logic:**
```js
function formatRelative(iso) {
  if (!iso) return null
  const d = new Date(iso)
  if (isNaN(d.getTime())) return null
  const diff = (Date.now() - d.getTime()) / 1000 // seconds
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
```

### 2. Trending Sources Cleanup

**Backend changes:**

**A. Fix Google Trends scraper** — swap deprecated RSS for Google's internal `dailytrends` JSON API (stable since 2019):

```php
private function fetchGoogleTrends(): array
{
    $results = [];
    $regions = ['US', 'ID'];

    foreach ($regions as $geo) {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get('https://trends.google.com/trends/api/dailytrends', [
                    'hl' => $geo === 'ID' ? 'id' : 'en-US',
                    'tz' => -480,
                    'geo' => $geo,
                    'ns' => 15,
                ]);

            if (!$response->successful()) continue;

            // Google prepends )]}' to prevent JSON hijacking — strip it
            $body = preg_replace('/^\)\]\}\',?\s*/', '', $response->body());
            $data = json_decode($body, true);

            $days = data_get($data, 'default.trendingSearchesDays', []);
            foreach ($days as $day) {
                foreach ($day['trendingSearches'] ?? [] as $search) {
                    $title = data_get($search, 'title.query');
                    if (empty($title)) continue;

                    $results[] = [
                        'title' => $title,
                        'description' => data_get($search, 'formattedTraffic', '') . ' searches',
                        'source' => 'google_trends',
                        'country' => $geo,
                        'score' => 70,
                        'pub_date' => $this->parseGoogleTrendsDate($day['date'] ?? null),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("[TrendingTopic] Google Trends {$geo}: {$e->getMessage()}");
        }
    }

    Log::info("[TrendingTopic] Google Trends: " . count($results) . " items");
    return $results;
}

private function parseGoogleTrendsDate(?string $yyyymmdd): ?string
{
    if (!$yyyymmdd || strlen($yyyymmdd) !== 8) return null;
    try {
        return \Carbon\Carbon::createFromFormat('Ymd', $yyyymmdd)->toIso8601String();
    } catch (\Exception $e) {
        return null;
    }
}
```

**B. Keep but hide broken scrapers:** leave `fetchTikTokTrending()` + `fetchYouTubeTrending()` code in place (commented out calls OR with early return if feature-flagged off). Add env var `TRENDING_SOURCES_ENABLED=google_news,google_trends` as allow-list. Default disables tiktok, youtube, instagram. Future flip-on when implementations are reliable.

**C. Remove Instagram from frontend dropdown** — pure frontend change, backend never had it.

**Frontend changes:**

```js
// ContentEngine.vue — update trendingSources list
const trendingSources = [
  { label: 'All Sources', value: '' },
  { label: 'Google News', value: 'google_news' },
  { label: 'Google Trends', value: 'google_trends' },
  // Disabled until scrapers fixed:
  { label: 'YouTube', value: 'youtube', disabled: true, badge: 'Coming soon' },
  { label: 'TikTok', value: 'tiktok', disabled: true, badge: 'Coming soon' },
  // Instagram removed entirely
]
```

Dropdown items with `disabled: true` render grayed out + can't be selected. Badge "Coming soon" shown as pill next to label.

### 3. Bulk Selection + Actions

**Frontend state additions:**
```js
const selectedIdeaIds = ref([])  // array of idea IDs checked in current page
const allPageSelected = computed(() =>
  ideas.value.length > 0 && ideas.value.every(i => selectedIdeaIds.value.includes(i.id))
)
```

**Header checkbox** (first column):
```vue
<th class="px-4 py-3 w-10">
  <input type="checkbox" :checked="allPageSelected" @change="toggleSelectAllPage" />
</th>
```

**Row checkbox:**
```vue
<td class="px-4 py-3">
  <input type="checkbox" :value="idea.id" v-model="selectedIdeaIds" />
</td>
```

**Sticky bulk bar** (renders above table when `selectedIdeaIds.length >= 1`):
```vue
<div v-if="selectedIdeaIds.length" class="sticky top-0 z-20 flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700/50 rounded-lg shadow-sm">
  <span class="text-sm font-medium text-amber-800 dark:text-amber-300">
    {{ selectedIdeaIds.length }} selected
  </span>
  <div class="flex gap-2">
    <button @click="bulkDelete">🗑 Delete</button>
    <button @click="bulkStartResearch">▶ Start Research</button>
    <button @click="bulkArchive">📦 Archive</button>
    <button @click="bulkRevert">↶ Revert to Draft</button>
    <button @click="selectedIdeaIds = []">Clear</button>
  </div>
</div>
```

**Bulk action semantics:**
- **Delete** — confirm modal, then loop DELETE `/admin/content-engine/ideas/{id}` per ID (reuse existing single-delete endpoint). Show progress toast "Deleted N of M".
- **Start Research** — validate all selected ideas have `status === 'draft'`; if any fail, show warning + only process valid ones. Loop POST `/admin/content-engine/ideas/{id}/research` with default config (languages: ['id','en'], empty instructions). Skip those already in flight.
- **Archive** — validate status not already `archived`. Loop POST `/admin/content-engine/ideas/{id}/archive`.
- **Revert to Draft** — any status → `draft`, clears `generated_article`. Loop POST `/admin/content-engine/ideas/{id}/revert`.

**YAGNI:** no new bulk backend endpoints. Reuse existing per-ID endpoints in parallel client-side loop (max 10 concurrent via `Promise.all` chunked). Good enough for admin tool scale (<100 bulk at a time).

**Clear on page change:** pagination reset also clears `selectedIdeaIds` to avoid stale state.

### 4. Next Action Icon Button

**Frontend:** replace inline text button with icon button matching sibling actions (same `p-1.5 rounded text-neutral-400 hover:text-amber-600 hover:bg-neutral-100 dark:hover:bg-neutral-700` style as pencil/archive/trash).

```vue
<button
  @click="triggerNextAction(idea)"
  class="p-1.5 rounded text-neutral-400 hover:text-amber-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
  :title="nextActionLabel(idea)"
>
  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <!-- Heroicons play icon -->
    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
  </svg>
</button>
```

**Dynamic tooltip + handler dispatch:**
```js
function nextActionLabel(idea) {
  const map = {
    draft: 'Start Research',
    researching: 'View Progress',
    article_ready: 'Review Article',
    generating_images: 'View Progress',
    images_ready: 'Approve & Publish',
    completed: 'View Published Post',
    archived: 'Restore',
  }
  return map[idea.status] || 'Next'
}

function triggerNextAction(idea) {
  // Dispatch to existing handlers per status — unchanged logic
  if (idea.status === 'draft') return openConfigModal(idea)
  if (idea.status === 'researching' || idea.status === 'generating_images') return openProgressModal(idea)
  if (idea.status === 'article_ready') return router.push({ name: 'article-preview', params: { id: idea.id } })
  if (idea.status === 'images_ready') return router.push({ name: 'article-finalize', params: { id: idea.id } })
  if (idea.status === 'completed') return window.open(`/blog/${idea.result_post_id}`, '_blank')
  if (idea.status === 'archived') return restoreIdea(idea.id)
}
```

**Keep existing text buttons untouched** if the plan is just to match icons. Actually per user request: "Button Next ganti ke button instead of tulisan" = replace text with icon only. Confirmed.

## Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Published date | `idea.source_data.pub_date` | Yes | Already populated by `parseRss()` for RSS sources |
| TimeAgo formatter | Pure function in ContentEngine.vue setup | No | Small utility — no need for composable (YAGNI) |
| Google Trends data | New `/trends/api/dailytrends` endpoint | No | Replace broken `/trending/rss` call in `fetchGoogleTrends` |
| Disabled source UI | `trendingSources[].disabled + .badge` | No | Add props to existing array; render gray + pill |
| Bulk checkbox state | `selectedIdeaIds: ref([])` | No | New local state |
| Bulk delete | `deleteIdea(id)` composable | Yes | Loop client-side via `Promise.all` |
| Bulk start research | `startResearch(id, config)` composable | Yes | Default config `{ languages: ['id','en'], instructions: '' }` |
| Bulk archive | `archiveIdea(id)` composable | Yes | Loop |
| Bulk revert | `revertToDraft(id)` composable | Yes | Loop |
| Next icon button | Heroicons `<path>` inline SVG | Reuse pattern | Match existing edit/archive/trash button classes |
| nextActionLabel | Pure function | No | Status → verb map |
| triggerNextAction | Dispatches to existing handlers | Partial | Some already exist (`openConfigModal`, `openProgressModal`); router.push calls to Review/Finalize are new |

## YAGNI Cuts

- ❌ NO new bulk backend endpoints — reuse per-ID endpoints
- ❌ NO select-across-pages (power user feature; single page scope fine)
- ❌ NO date-range filter for published date (future add)
- ❌ NO Instagram implementation (user didn't ask; flag deferred)
- ❌ NO migration — `pub_date` lives in existing `source_data` JSON
- ❌ NO new composable — inline date formatting in ContentEngine.vue
- ❌ NO TimeAgo reactive counter — static on render (no need for per-second re-render of "2m ago")

## Implementation Feasibility

✅ All real integrations available.

⚠️ **Google Trends `dailytrends` API** — undocumented internal Google API. Has been stable since 2019 but could break anytime without notice. Risk accepted; fallback = log warning + return empty array (graceful degrade).

⚠️ **Bulk Start Research** — each idea triggers an SSH call to VPS Claude CLI. 10 concurrent SSH spawns could overload the VPS. Mitigation: throttle bulk to process 3 at a time (sequential chunks).

## File Change Summary

| Layer | File | Action |
|---|---|---|
| Backend | `app/Services/TrendingTopicService.php` | REPLACE `fetchGoogleTrends` method; add `parseGoogleTrendsDate` helper |
| Backend | `config/services.php` (optional) | Add `trending_sources_enabled` env-gated allow-list (future-proofing) |
| Frontend | `src/views/admin/ContentEngine.vue` | Add checkbox column, bulk bar, published date column, Next icon button, disabled dropdown options, date formatter fn, bulk handlers |
| Frontend | (optional) `src/composables/useContentEngine.js` | If bulk handlers grow complex, extract; otherwise inline |

**Total: 2 files modified** (1 backend + 1 frontend), no new files needed.

## Estimated Total Time

- Backend Google Trends swap: ~15 min
- Published date column: ~8 min
- Sources UI cleanup (disable/remove): ~5 min
- Bulk selection state + UI: ~15 min
- Bulk action handlers (4 actions): ~20 min
- Next icon button + dispatch: ~8 min
- Build + manual smoke: ~10 min
- Commit + VPS deploy: ~10 min
- **Total: ~90 minutes**

## Open Questions

None — all 4 design decisions locked upfront.
