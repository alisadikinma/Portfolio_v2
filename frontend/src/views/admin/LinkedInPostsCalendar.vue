<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, toRef } from 'vue'
import { useRouter } from 'vue-router'
import {
  usePostingRules,
  useRefreshPostingRules,
} from '@/composables/useLinkedInDrafts'
import { useSocialCalendar } from '@/composables/useSocialCalendar'
import {
  effectiveStatusMeta,
  MOOD_CLASSES,
  formatDateTime,
  formatPinTimeWIB,
  pinTimeLabel,
  ICON,
} from './linkedinHelpers'

/**
 * Generalized Posts calendar — used by LinkedIn + Facebook + Instagram +
 * TikTok via the `platform` prop. Originally LinkedIn-only; Phase 3 of
 * the cross-post sprint widened it to all 4 platforms with a shared
 * SocialPlatformTabs strip + per-platform calendar feed via
 * useSocialCalendar().
 *
 * Per the brainstorm: 1 platform active at a time (B1) — switching tabs
 * navigates to that platform's `/admin/{platform}-posts` route.
 *
 * Three view modes via toolbar toggle:
 *   - month: 7×6 grid with cell-click side panel
 *   - list:  card grid fallback (escape hatch when calendar grain is wrong)
 *
 * Heatmap (subtle 0-15% opacity tint) is sourced from posting_time_rules
 * keyed on the current platform. When no rules exist for the active
 * platform (FB/IG/TikTok haven't been seeded yet on production VPS),
 * the heatmap shows nothing and an empty-state hint surfaces the
 * `posting-rules:research --platform=X` command operators need to run.
 */

const props = defineProps({
  platform: {
    type: String,
    default: 'linkedin',
    validator: (v) => ['linkedin', 'facebook', 'instagram', 'tiktok'].includes(v),
  },
})

const platformRef = toRef(props, 'platform')

// Route resolver for the "go to Queue" link in the header. Every
// platform has its own dedicated /admin/{platform}-queue route — see
// router/index.js Phase 4 entries.
const queueRouteForPlatform = computed(() => `/admin/${platformRef.value}-queue`)

const router = useRouter()

// ---------------------------------------------------------------------------
// Date math (native Date, no date-fns dep)
// ---------------------------------------------------------------------------

const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

function ymd(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function startOfMonth(year, monthIndex) {
  return new Date(year, monthIndex, 1)
}

function endOfMonth(year, monthIndex) {
  return new Date(year, monthIndex + 1, 0, 23, 59, 59, 999)
}

function addDays(date, days) {
  const next = new Date(date)
  next.setDate(next.getDate() + days)
  return next
}

function sameDay(a, b) {
  return a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate()
}

/**
 * Build the 6-row × 7-col grid for a month. The grid starts on Sunday of
 * the week containing the 1st and ends on Saturday of the week containing
 * the last day. Always 42 cells (some are previous/next month overflow).
 */
function buildMonthGrid(year, monthIndex) {
  const first = startOfMonth(year, monthIndex)
  const dowOfFirst = first.getDay() // 0=Sun..6=Sat
  const gridStart = addDays(first, -dowOfFirst)
  const cells = []
  for (let i = 0; i < 42; i++) {
    cells.push(addDays(gridStart, i))
  }
  return cells
}

// ---------------------------------------------------------------------------
// Reactive month navigation
// ---------------------------------------------------------------------------

const today = new Date()
const cursorYear = ref(today.getFullYear())
const cursorMonth = ref(today.getMonth()) // 0-indexed

const cursorLabel = computed(() => `${MONTH_NAMES[cursorMonth.value]} ${cursorYear.value}`)

function navMonth(delta) {
  let m = cursorMonth.value + delta
  let y = cursorYear.value
  while (m < 0) { m += 12; y -= 1 }
  while (m > 11) { m -= 12; y += 1 }
  cursorMonth.value = m
  cursorYear.value = y
  selectedDay.value = null
}

function jumpToToday() {
  cursorYear.value = today.getFullYear()
  cursorMonth.value = today.getMonth()
  selectedDay.value = null
}

const gridCells = computed(() => buildMonthGrid(cursorYear.value, cursorMonth.value))

// ---------------------------------------------------------------------------
// View mode + status filter
// ---------------------------------------------------------------------------

// View mode preference is platform-namespaced so operators can keep
// LinkedIn on `month` while triaging Instagram on `list` (or whatever
// they prefer per platform).
const VIEW_KEY = computed(() => `${platformRef.value}:calendar:view-mode`)
const viewMode = ref('month') // 'month' | 'list'
onMounted(() => {
  const saved = sessionStorage.getItem(VIEW_KEY.value)
  if (saved === 'list' || saved === 'month') viewMode.value = saved
})
watch(viewMode, (v) => sessionStorage.setItem(VIEW_KEY.value, v))
// Reload preference when platform changes — different per-platform
// tabs may have different last-used view modes.
watch(VIEW_KEY, (key) => {
  const saved = sessionStorage.getItem(key)
  viewMode.value = (saved === 'list' || saved === 'month') ? saved : 'month'
})

const STATUS_TABS = [
  { key: 'all', label: 'All', accent: 'text-neutral-300' },
  { key: 'awaiting_publish', label: 'Scheduled', accent: 'text-amber-400' },
  { key: 'published', label: 'Published', accent: 'text-emerald-400' },
  { key: 'manual_review', label: 'Awaiting', accent: 'text-cyan-400' },
  { key: 'failed', label: 'Failed', accent: 'text-red-400' },
]
const activeStatus = ref('all')

// ---------------------------------------------------------------------------
// Calendar data (TanStack Query) — fetches posts whose pin_at falls in the
// 42-cell visible window (gridStart..gridStart+42 days)
// ---------------------------------------------------------------------------

const range = computed(() => {
  const cells = gridCells.value
  if (cells.length === 0) return null
  return {
    from: ymd(cells[0]),
    to: ymd(cells[cells.length - 1]),
    status: activeStatus.value === 'all' ? null : activeStatus.value,
  }
})

const { items: calendarItems, isLoading: calendarLoading, isFetching: calendarFetching } = useSocialCalendar(platformRef, range)

// Bucket posts by pin_at day (YYYY-MM-DD key, WIB local date).
// pin_at is ISO 8601 UTC from backend. Naive .slice(0,10) keys by UTC date,
// which puts a post scheduled May 7 06:00 WIB (= May 6 23:00 UTC) into the
// May 6 cell. Use Asia/Jakarta to match the WIB-rendered display labels.
const WIB_DATE_KEY = new Intl.DateTimeFormat('sv-SE', {
  timeZone: 'Asia/Jakarta',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})

const postsByDay = computed(() => {
  const buckets = {}
  for (const item of calendarItems.value) {
    if (!item.pin_at) continue
    const key = WIB_DATE_KEY.format(new Date(item.pin_at)) // 'YYYY-MM-DD' in WIB
    if (!buckets[key]) buckets[key] = []
    buckets[key].push(item)
  }
  // Sort each day's posts by pin_at ASC so detail panel + cell thumbnails
  // show chronological order (03:20 AM → 05:00 PM) regardless of status.
  for (const key in buckets) {
    buckets[key].sort((a, b) => {
      const ta = a.pin_at ? new Date(a.pin_at).getTime() : 0
      const tb = b.pin_at ? new Date(b.pin_at).getTime() : 0
      return ta - tb
    })
  }
  return buckets
})

function postsForDate(date) {
  return postsByDay.value[ymd(date)] || []
}

// ---------------------------------------------------------------------------
// Posting rules heatmap data
// ---------------------------------------------------------------------------

const { daySummaries, lastResearchedAt, sources, ruleCount } = usePostingRules(platformRef)
const refreshRulesMutation = useRefreshPostingRules()

const dayBestScore = computed(() => {
  const map = {}
  for (const s of daySummaries.value) {
    map[s.day_of_week] = s.best_score
  }
  return map
})

const dayBestHours = computed(() => {
  const map = {}
  for (const s of daySummaries.value) {
    map[s.day_of_week] = s.best_hours || []
  }
  return map
})

function dayChip(dow) {
  const score = dayBestScore.value[dow]
  if (score === undefined) return null
  if (score >= 85) return { color: 'text-emerald-400', label: 'Optimal' }
  if (score >= 70) return { color: 'text-amber-400', label: 'Good' }
  return null
}

function cellTintStyle(date) {
  const score = dayBestScore.value[date.getDay()]
  if (!score || score < 50) return ''
  // Optimal days get a faint emerald tint, lower days no tint.
  // Cap opacity at 12% so it never overwhelms.
  const opacity = Math.min(0.12, Math.max(0, (score - 50) / 100))
  if (score >= 85) return `background-color: rgba(16, 185, 129, ${opacity});` // emerald-500
  if (score >= 70) return `background-color: rgba(245, 158, 11, ${opacity * 0.6});` // amber-500
  return ''
}

const lastResearchedRelative = computed(() => {
  const iso = lastResearchedAt.value
  if (!iso) return null
  const now = Date.now()
  const then = new Date(iso).getTime()
  if (Number.isNaN(then)) return null
  const minutes = Math.round((now - then) / 60000)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.round(hours / 24)
  return `${days}d ago`
})

const refreshDisabled = computed(() => {
  if (refreshRulesMutation.isPending.value) return true
  // Soft cooldown: if rules were researched < 24h ago, no need to spam refresh.
  const iso = lastResearchedAt.value
  if (!iso) return false
  const ageMinutes = (Date.now() - new Date(iso).getTime()) / 60000
  return ageMinutes < 60 * 24
})

async function doRefreshRules() {
  if (refreshDisabled.value) {
    if (!confirm('Rules were refreshed recently. Force refresh anyway?')) return
  }
  try {
    await refreshRulesMutation.mutateAsync({ platform: platformRef.value })
    alert('Refresh dispatched. New rules will appear within ~60 seconds.')
  } catch (e) {
    alert('Refresh failed: ' + (e?.message || 'unknown error'))
  }
}

// ---------------------------------------------------------------------------
// Side panel state
// ---------------------------------------------------------------------------

const selectedDay = ref(null) // Date | null

function selectDay(date) {
  selectedDay.value = date
}

function closeSidePanel() {
  selectedDay.value = null
}

const selectedDayPosts = computed(() => selectedDay.value ? postsForDate(selectedDay.value) : [])

const selectedDayLabel = computed(() => {
  const d = selectedDay.value
  if (!d) return ''
  return `${DAY_NAMES[d.getDay()]}, ${MONTH_NAMES[d.getMonth()]} ${d.getDate()}`
})

const selectedDaySuggestedHours = computed(() => {
  const d = selectedDay.value
  if (!d) return []
  return dayBestHours.value[d.getDay()] || []
})

function formatHour(h) {
  return `${String(h).padStart(2, '0')}:00 WIB`
}

function openDetail(id) {
  // Origin key contract:
  //   sessionStorage[`${platform}:detail:origin`] = 'feed' | 'queue'
  // Consumed by:
  //   - LinkedInDraftDetail.vue (reads 'linkedin:detail:origin')
  //   - CrossPostDraftDetail.vue (reads `${routeParam.platform}:detail:origin`)
  // If you add a new write-site or change the key shape, audit both
  // readers — silent mismatch produces a wrong-destination back button.
  sessionStorage.setItem(`${platformRef.value}:detail:origin`, 'feed')
  if (platformRef.value === 'linkedin') {
    router.push({ name: 'admin-linkedin-draft-detail', params: { id } })
  } else {
    router.push({
      name: 'admin-cross-post-detail',
      params: { platform: platformRef.value, id },
    })
  }
}

// ---------------------------------------------------------------------------
// Cell helpers
// ---------------------------------------------------------------------------

function isCurrentMonth(date) {
  return date.getMonth() === cursorMonth.value
}

function isToday(date) {
  return sameDay(date, today)
}

function statusDotClass(status) {
  // Map status to a small dot color. Keep aligned with linkedinHelpers MOOD_CLASSES.
  const meta = effectiveStatusMeta({ status })
  switch (meta?.mood) {
    case 'success': return 'bg-emerald-400'
    case 'live':
    case 'decision': return 'bg-amber-400'
    case 'progress': return 'bg-cyan-400'
    case 'error': return 'bg-red-400'
    case 'archive': return 'bg-neutral-500'
    default: return 'bg-neutral-400'
  }
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Header -->
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <!-- Tracking is uppercase via Tailwind, but platform values
             arrive lowercase. CSS capitalize on the dynamic platform
             span renders title-case ("Linkedin", "Facebook" etc.) within
             the all-caps eyebrow row. -->
        <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-emerald-400/80 mb-1">
          <span class="capitalize">{{ platform }}</span> pipeline
        </p>
        <h1 class="text-3xl sm:text-4xl font-display font-semibold tracking-tight text-neutral-100">
          SOSMED Posts
        </h1>
        <p class="mt-2 text-sm text-neutral-400 max-w-xl">
          Calendar of scheduled and published drafts. For drafts that need attention, go to
          <router-link :to="queueRouteForPlatform" class="text-cyan-400 hover:text-cyan-300 underline-offset-2 hover:underline">Draft Posts</router-link>.
        </p>
      </div>
      <router-link
        :to="queueRouteForPlatform"
        class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-700/60 bg-neutral-900/40 px-3 py-1.5 text-sm text-neutral-200 hover:bg-neutral-800/60 transition"
      >
        Queue
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="w-3.5 h-3.5"><path d="M9 18l6-6-6-6"/></svg>
      </router-link>
    </header>

    <!-- Empty-state hint when posting_time_rules table has no rows for
         this platform (FB/IG/TikTok ship with rules empty until the
         operator runs `posting-rules:research --platform=X` on VPS). -->
    <div
      v-if="ruleCount === 0"
      class="rounded-lg border border-amber-400/30 bg-amber-500/5 px-3 py-2 text-xs text-amber-200/90"
    >
      AI posting rules for <strong class="font-semibold capitalize">{{ platform }}</strong> haven't been seeded yet. Heatmap will stay neutral until the operator runs
      <code class="rounded bg-neutral-900/60 px-1.5 py-0.5 font-mono">php artisan posting-rules:research --platform={{ platform }}</code>
      on VPS, or clicks Refresh AI above.
    </div>

    <!-- Toolbar: date nav + view toggle + status filter + AI refresh -->
    <div class="rounded-xl border border-neutral-800/80 bg-neutral-950/40 p-4 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <!-- Month nav -->
        <div class="flex items-center gap-2">
          <button
            @click="navMonth(-1)"
            class="rounded-md border border-neutral-700/70 bg-neutral-900/60 p-1.5 text-neutral-300 hover:bg-neutral-800/80 transition"
            aria-label="Previous month"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="w-4 h-4"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <button
            @click="jumpToToday"
            class="text-sm text-neutral-400 hover:text-neutral-200 px-2 py-1 transition"
          >
            Today
          </button>
          <button
            @click="navMonth(1)"
            class="rounded-md border border-neutral-700/70 bg-neutral-900/60 p-1.5 text-neutral-300 hover:bg-neutral-800/80 transition"
            aria-label="Next month"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="w-4 h-4"><path d="M9 18l6-6-6-6"/></svg>
          </button>
          <span class="ml-3 text-base font-display font-semibold text-neutral-100">{{ cursorLabel }}</span>
          <span v-if="calendarFetching && !calendarLoading" class="ml-2 text-[11px] font-mono uppercase tracking-wider text-cyan-400/70">syncing…</span>
        </div>

        <!-- View toggle -->
        <div class="inline-flex rounded-lg border border-neutral-700/70 bg-neutral-900/60 p-0.5">
          <button
            @click="viewMode = 'month'"
            :class="[
              'rounded-md px-3 py-1.5 text-xs font-mono uppercase tracking-wider transition',
              viewMode === 'month' ? 'bg-neutral-800 text-neutral-100' : 'text-neutral-400 hover:text-neutral-200',
            ]"
          >
            Month
          </button>
          <button
            @click="viewMode = 'list'"
            :class="[
              'rounded-md px-3 py-1.5 text-xs font-mono uppercase tracking-wider transition',
              viewMode === 'list' ? 'bg-neutral-800 text-neutral-100' : 'text-neutral-400 hover:text-neutral-200',
            ]"
          >
            List
          </button>
        </div>

        <!-- AI refresh -->
        <div class="flex items-center gap-2">
          <span v-if="lastResearchedAt" class="text-[11px] font-mono uppercase tracking-wider text-neutral-500">
            AI rules: {{ lastResearchedRelative }} · {{ ruleCount }} slots
          </span>
          <span v-else-if="ruleCount === 0" class="text-[11px] font-mono uppercase tracking-wider text-amber-400/70">
            No AI rules yet — run posting-rules:research
          </span>
          <button
            @click="doRefreshRules"
            :disabled="refreshRulesMutation.isPending.value"
            class="inline-flex items-center gap-1.5 rounded-md border border-cyan-400/30 bg-cyan-500/10 px-2.5 py-1 text-xs text-cyan-200 hover:bg-cyan-500/20 transition disabled:opacity-50 disabled:cursor-not-allowed"
            :title="refreshDisabled ? 'Rules refreshed recently — click to force refresh anyway' : 'Trigger AI research (~60s)'"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="w-3.5 h-3.5">
              <path d="M21 12a9 9 0 11-3.5-7.1"/>
              <path d="M21 4v5h-5"/>
            </svg>
            {{ refreshRulesMutation.isPending.value ? 'Dispatching…' : 'Refresh AI' }}
          </button>
        </div>
      </div>

      <!-- Status filter pills -->
      <div class="flex flex-wrap items-center gap-1.5">
        <button
          v-for="tab in STATUS_TABS"
          :key="tab.key"
          @click="activeStatus = tab.key"
          :class="[
            'rounded-md border px-2.5 py-1 text-xs font-mono uppercase tracking-wider transition',
            activeStatus === tab.key
              ? `border-neutral-500 bg-neutral-800/80 ${tab.accent}`
              : 'border-neutral-800 bg-neutral-900/40 text-neutral-500 hover:text-neutral-300',
          ]"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Month grid -->
    <div v-if="viewMode === 'month'" class="rounded-xl border border-neutral-800/80 bg-neutral-950/40 overflow-hidden">
      <!-- Day-of-week header row with AI chips -->
      <div class="grid grid-cols-7 border-b border-neutral-800/80 bg-neutral-900/30">
        <div
          v-for="(name, dow) in DAY_NAMES"
          :key="dow"
          class="px-2 py-2 text-center"
        >
          <div class="flex items-center justify-center gap-1">
            <span class="text-[11px] font-mono uppercase tracking-wider text-neutral-400">{{ name }}</span>
            <span
              v-if="dayChip(dow)"
              :class="['text-[11px]', dayChip(dow).color]"
              :title="`AI says: ${dayChip(dow).label} day for B2B (best_score=${dayBestScore[dow]})`"
            >●</span>
          </div>
        </div>
      </div>

      <!-- 6×7 cell grid -->
      <div class="grid grid-cols-7 grid-rows-6">
        <button
          v-for="(date, idx) in gridCells"
          :key="idx"
          @click="selectDay(date)"
          :style="cellTintStyle(date)"
          :class="[
            'group relative min-h-[96px] border-b border-r border-neutral-800/60 p-2 text-left transition',
            isCurrentMonth(date) ? 'bg-neutral-950/0' : 'bg-neutral-950/40 text-neutral-600',
            isToday(date) ? 'ring-1 ring-inset ring-amber-400/40' : '',
            selectedDay && sameDay(selectedDay, date) ? 'ring-2 ring-inset ring-cyan-400/60' : '',
            'hover:bg-neutral-900/60',
            (idx + 1) % 7 === 0 ? 'border-r-0' : '',
            idx >= 35 ? 'border-b-0' : '',
          ]"
        >
          <div class="flex items-start justify-between">
            <span :class="[
              'text-sm font-mono',
              isCurrentMonth(date) ? 'text-neutral-200' : 'text-neutral-600',
              isToday(date) ? 'font-bold text-amber-300' : '',
            ]">
              {{ date.getDate() }}
            </span>
            <span v-if="isToday(date)" class="text-[9px] font-mono uppercase tracking-widest text-amber-400/80">today</span>
          </div>

          <!-- Post dots — max 3 visible, +N overflow -->
          <div v-if="postsForDate(date).length" class="mt-2 space-y-1">
            <div
              v-for="post in postsForDate(date).slice(0, 3)"
              :key="post.id"
              class="flex items-center gap-1.5 text-[11px] truncate"
              @click.stop="openDetail(post.id)"
            >
              <span :class="['inline-block w-1.5 h-1.5 rounded-full flex-shrink-0', statusDotClass(post.status)]"></span>
              <span class="truncate text-neutral-300 group-hover:text-neutral-100">{{ post.post_title }}</span>
            </div>
            <div v-if="postsForDate(date).length > 3" class="text-[10px] font-mono text-neutral-500">
              +{{ postsForDate(date).length - 3 }} more
            </div>
          </div>
        </button>
      </div>
    </div>

    <!-- List mode (escape hatch) -->
    <div v-else-if="viewMode === 'list'" class="space-y-2">
      <div
        v-if="calendarLoading"
        class="rounded-xl border border-neutral-800/80 bg-neutral-950/40 p-8 text-center text-sm text-neutral-500"
      >
        Loading…
      </div>
      <div
        v-else-if="!calendarItems.length"
        class="rounded-xl border border-dashed border-neutral-800/80 bg-neutral-950/40 p-12 text-center"
      >
        <p class="text-sm text-neutral-400">No posts scheduled or published in this range.</p>
      </div>
      <div
        v-for="post in calendarItems"
        :key="post.id"
        @click="openDetail(post.id)"
        class="cursor-pointer rounded-xl border border-neutral-800/80 bg-neutral-950/40 px-4 py-3 hover:bg-neutral-900/40 transition"
      >
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-2 min-w-0 flex-1">
            <span :class="['inline-block w-2 h-2 rounded-full flex-shrink-0', statusDotClass(post.status)]"></span>
            <span class="text-sm text-neutral-100 truncate">{{ post.post_title }}</span>
          </div>
          <div class="flex items-center gap-3 text-xs text-neutral-400 flex-shrink-0">
            <span class="uppercase tracking-wider font-mono text-[10px]">{{ post.format }}</span>
            <span :class="['font-mono uppercase tracking-wider text-[10px]', post.status === 'awaiting_publish' ? 'text-amber-400/80' : post.status === 'published' ? 'text-emerald-400/80' : 'text-neutral-500']">
              {{ pinTimeLabel(post) }}
            </span>
            <span class="font-mono">{{ formatPinTimeWIB(post.pin_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Side panel (slide in from right when day selected) -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-x-8 opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-8 opacity-0"
    >
      <aside
        v-if="selectedDay && viewMode === 'month'"
        class="fixed top-0 right-0 bottom-0 w-full sm:w-96 z-40 bg-neutral-950 border-l border-neutral-800 shadow-2xl overflow-y-auto"
      >
        <div class="sticky top-0 bg-neutral-950 border-b border-neutral-800 px-4 py-3 flex items-center justify-between">
          <div>
            <p class="text-[11px] font-mono uppercase tracking-wider text-neutral-500">Day detail</p>
            <h2 class="text-base font-display font-semibold text-neutral-100">{{ selectedDayLabel }}</h2>
          </div>
          <button
            @click="closeSidePanel"
            class="rounded-md p-1.5 text-neutral-400 hover:bg-neutral-800/80 hover:text-neutral-100 transition"
            aria-label="Close panel"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="w-4 h-4"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="p-4 space-y-4">
          <!-- AI suggested slots for this day-of-week -->
          <div v-if="selectedDaySuggestedHours.length" class="rounded-lg border border-emerald-400/20 bg-emerald-500/5 px-3 py-2.5">
            <p class="text-[11px] font-mono uppercase tracking-wider text-emerald-400/80 mb-1">
              AI says — best slots this {{ DAY_NAMES[selectedDay.getDay()] }}
            </p>
            <div class="flex flex-wrap gap-1.5 mt-1">
              <span
                v-for="hour in selectedDaySuggestedHours"
                :key="hour"
                class="rounded-md border border-emerald-400/30 bg-emerald-500/10 px-2 py-0.5 text-xs text-emerald-100"
              >
                {{ formatHour(hour) }}
              </span>
            </div>
          </div>

          <!-- Posts on this day -->
          <div v-if="selectedDayPosts.length">
            <p class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-2">
              Posts ({{ selectedDayPosts.length }})
            </p>
            <div class="space-y-2">
              <button
                v-for="post in selectedDayPosts"
                :key="post.id"
                @click="openDetail(post.id)"
                class="w-full text-left rounded-lg border border-neutral-800 bg-neutral-900/40 px-3 py-2.5 hover:bg-neutral-800/60 transition"
              >
                <div class="flex items-center gap-2 mb-1">
                  <span :class="['inline-block w-1.5 h-1.5 rounded-full flex-shrink-0', statusDotClass(post.status)]"></span>
                  <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500">
                    {{ post.status.replace(/_/g, ' ') }} · {{ post.format }}
                  </span>
                </div>
                <p class="text-sm text-neutral-100 truncate mb-1.5">{{ post.post_title }}</p>
                <div v-if="post.pin_at" class="flex items-center gap-1.5 text-[11px]">
                  <span :class="['font-mono uppercase tracking-wider', post.status === 'awaiting_publish' ? 'text-amber-400/80' : post.status === 'published' ? 'text-emerald-400/80' : 'text-neutral-500']">
                    {{ pinTimeLabel(post) }}:
                  </span>
                  <span class="font-mono text-neutral-300">{{ formatPinTimeWIB(post.pin_at) }}</span>
                </div>
              </button>
            </div>
          </div>
          <div v-else class="rounded-lg border border-dashed border-neutral-800 bg-neutral-900/30 px-3 py-6 text-center">
            <p class="text-sm text-neutral-400">No posts on this day.</p>
            <p class="mt-1 text-xs text-neutral-500">Schedule a draft from the Queue.</p>
          </div>

          <!-- Sources footnote when AI rules loaded -->
          <div v-if="sources.length" class="border-t border-neutral-800 pt-3">
            <p class="text-[10px] font-mono uppercase tracking-wider text-neutral-600 mb-1">AI rules sourced from</p>
            <ul class="space-y-1">
              <li v-for="(s, i) in sources.slice(0, 3)" :key="i" class="text-[11px] text-neutral-500 truncate">
                <a :href="s.url" target="_blank" rel="noopener" class="hover:text-neutral-300 hover:underline">{{ s.title || s.url }}</a>
              </li>
            </ul>
          </div>
        </div>
      </aside>
    </Transition>
  </div>
</template>
