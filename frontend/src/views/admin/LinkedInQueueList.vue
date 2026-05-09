<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import {
  useLinkedInDraftsList,
  useCancelLinkedInDraft,
  useApproveLinkedInDraft,
  useRegenerateLinkedInDraft,
  postTitle,
} from '@/composables/useLinkedInDrafts'
import {
  effectiveStatusMeta,
  inspectCarouselRenderState,
  MOOD_CLASSES,
  formatChip,
  relativeTime,
  generatingProgress,
  QUEUE_TAB_KEY,
  ICON,
} from './linkedinHelpers'

const router = useRouter()

// --- Tab persistence: restore the active tab on mount, save on change.
// Lets the operator come back from a draft detail and land on the same tab.
//
// Tab simplification (May 9, 2026): merged 'manual_review' into 'in_progress'.
// Anything pre-published (pending_generation / generating / validating /
// manual_review / awaiting_publish) is now one bucket — operators can act
// on it AND fan out to IG/TikTok/FB cross-post variants while it sits there.
const activeTab = ref('in_progress')
onMounted(() => {
  const saved = sessionStorage.getItem(QUEUE_TAB_KEY)
  // Migrate legacy persisted 'manual_review' → 'in_progress'.
  if (saved === 'manual_review') {
    activeTab.value = 'in_progress'
  } else if (saved && ['failed', 'in_progress', 'all'].includes(saved)) {
    activeTab.value = saved
  }
})
watch(activeTab, (v) => sessionStorage.setItem(QUEUE_TAB_KEY, v))

// --- Live ticker for generating progress % --------------------------------
// Increments every second so generatingProgress(draft) re-evaluates against
// fresh Date.now(). The status pill template references `tick` via the
// comma-operator pattern so Vue's reactivity tracker subscribes to it
// without re-computing for every draft on every tick.
const tick = ref(0)
let tickerHandle = null
onMounted(() => {
  tickerHandle = setInterval(() => { tick.value++ }, 1000)
})
onBeforeUnmount(() => {
  if (tickerHandle) {
    clearInterval(tickerHandle)
    tickerHandle = null
  }
})

// Always fetch the full queue (no server-side status filter) so client-side
// classification can re-route carousel drafts whose slide rendering is still
// in flight or has terminal-failed.
const filters = computed(() => ({
  scope: 'queue',
  per_page: 100,
}))

const { drafts: allDrafts, isLoading, error } = useLinkedInDraftsList(filters)

function classifyForQueue(draft) {
  // Anything pre-published (pending → manual_review → awaiting_publish)
  // lives in the In Progress bucket so operators can act on it AND fan
  // out to Instagram / TikTok / Facebook variants from one place.
  // Carousel drafts whose slides are still rendering (or all-failed)
  // surface in the same bucket — render state shows in the issue column.
  const inProgressStates = [
    'pending_generation',
    'generating',
    'validating',
    'manual_review',
    'awaiting_publish',
  ]
  if (inProgressStates.includes(draft.status)) {
    // Carousel-specific failure: every slide rejected → bucket as failed
    // so the operator's action is "retry slides", not "review caption".
    if (draft.status === 'manual_review' && draft.format === 'carousel') {
      const imgState = inspectCarouselRenderState(draft)
      if (imgState === 'failed') return 'failed'
    }
    return 'in_progress'
  }
  if (draft.status === 'failed') return 'failed'
  return null
}

// --- Sorting ----------------------------------------------------------------
// Default: virality DESC then published DESC (freshest, most viral first).
// Operator can click any header to override; second click flips direction;
// third click drops back to default (sortKey=null).
const SORT_DEFAULT = { key: null, dir: 'desc' }
const sortState = ref({ ...SORT_DEFAULT })

function toggleSort(key) {
  if (sortState.value.key !== key) {
    sortState.value = { key, dir: 'desc' }
  } else if (sortState.value.dir === 'desc') {
    sortState.value = { key, dir: 'asc' }
  } else {
    sortState.value = { ...SORT_DEFAULT }
  }
}

// Pluck the comparable value for a given sort key. Nulls always sink so
// drafts with missing data never beat drafts that have it (avoids unscored
// drafts ranking #1 just because their null sorts before 100).
function sortValue(draft, key) {
  switch (key) {
    case 'virality':  return draft.post?.content_idea?.virality_score ?? null
    case 'published': {
      const p = draft.post?.published_at
      return p ? new Date(p).getTime() : null
    }
    case 'source':    return (postTitle(draft) || '').toLowerCase()
    default:          return null
  }
}

function compareDrafts(a, b, key, dir) {
  const av = sortValue(a, key)
  const bv = sortValue(b, key)
  // Null sink: present values always win over null regardless of direction
  if (av === null && bv === null) return 0
  if (av === null) return 1
  if (bv === null) return -1
  if (av < bv) return dir === 'asc' ? -1 : 1
  if (av > bv) return dir === 'asc' ? 1 : -1
  return 0
}

const drafts = computed(() => {
  const filtered = activeTab.value === 'all'
    ? [...allDrafts.value]
    : allDrafts.value.filter((d) => classifyForQueue(d) === activeTab.value)

  // User-driven sort overrides default
  if (sortState.value.key) {
    return filtered.sort((a, b) =>
      compareDrafts(a, b, sortState.value.key, sortState.value.dir)
    )
  }

  // Default sort: virality DESC, then published DESC. Stable on ties.
  return filtered.sort((a, b) => {
    const cv = compareDrafts(a, b, 'virality', 'desc')
    if (cv !== 0) return cv
    return compareDrafts(a, b, 'published', 'desc')
  })
})

// Sort indicator helper for header rendering
function sortIndicator(key) {
  if (sortState.value.key !== key) return null
  return sortState.value.dir === 'asc' ? '▲' : '▼'
}

const counts = computed(() => {
  const c = { failed: 0, in_progress: 0 }
  for (const d of allDrafts.value) {
    const bucket = classifyForQueue(d)
    if (bucket && c[bucket] !== undefined) c[bucket]++
  }
  return c
})

const cancelMutation = useCancelLinkedInDraft()
const approveMutation = useApproveLinkedInDraft()
const regenerateMutation = useRegenerateLinkedInDraft()

// --- Manual blog scan (bypass daily cron) ---------------------------------
// Queues `linkedin:scan-blog` via the admin scheduler endpoint so the
// operator doesn't have to wait for the 03:00 WIB run after a new article
// ships from Content Engine.
const queryClient = useQueryClient()
const scanRunning = ref(false)
const scanFlash = ref(null) // { type: 'success'|'error', message: '...' }

async function scanBlogNow() {
  if (scanRunning.value) return
  scanRunning.value = true
  scanFlash.value = null
  try {
    const res = await api.post('/admin/linkedin-drafts/scan-blog-now')
    const data = res?.data ?? {}
    const created = data?.created ?? 0
    scanFlash.value = {
      type: created > 0 ? 'success' : 'info',
      message: data?.message || (created > 0
        ? `Created ${created} draft(s).`
        : 'No new posts to convert.'),
    }
    if (created > 0) {
      // Auto-jump to In Progress so the operator sees the freshly-created
      // pending_generation rows rather than staring at an unchanged
      // Manual Review tab.
      activeTab.value = 'in_progress'
      // Refresh the list a couple times — drafts go pending → generating
      // within ~5s, then validating, then manual_review/awaiting_publish
      // over the next 30-90s.
      queryClient.invalidateQueries({ queryKey: ['linkedin-drafts'] })
      setTimeout(() => queryClient.invalidateQueries({ queryKey: ['linkedin-drafts'] }), 5000)
      setTimeout(() => queryClient.invalidateQueries({ queryKey: ['linkedin-drafts'] }), 30000)
    }
  } catch (err) {
    scanFlash.value = {
      type: 'error',
      message: err?.response?.data?.error?.message || err?.message || 'Scan failed.',
    }
  } finally {
    scanRunning.value = false
    // Info / success messages auto-dismiss after 12s (operator needs longer
    // to read the explanatory copy when zero drafts were created); errors
    // also dismiss but you can read longer.
    setTimeout(() => { scanFlash.value = null }, 12000)
  }
}

const tabs = [
  { key: 'in_progress', label: 'In progress', accent: 'text-cyan-400' },
  { key: 'failed', label: 'Failed', accent: 'text-red-400' },
  { key: 'all', label: 'Everything', accent: 'text-neutral-300' },
]

function openDetail(id) {
  // Tag origin so the detail page's back button knows where to return.
  sessionStorage.setItem('linkedin:detail:origin', 'queue')
  router.push({ name: 'admin-linkedin-draft-detail', params: { id } })
}

function depthTone(score) {
  if (score === null || score === undefined) return 'text-neutral-500'
  if (score >= 80) return 'text-emerald-400'
  if (score >= 70) return 'text-amber-400'
  return 'text-red-400'
}

function issueSummary(draft) {
  if (draft.format === 'carousel' && draft.status === 'manual_review') {
    const slides = Array.isArray(draft.carousel_slides) ? draft.carousel_slides : []
    if (slides.length > 0) {
      const imgState = inspectCarouselRenderState(draft)
      const inFlight = slides.filter((s) => s?.image_status === 'generating').length
      const failed = slides.filter((s) => s?.image_status === 'failed').length
      const done = slides.filter((s) => s?.image_status === 'done' && s?.image_url).length
      if (imgState === 'generating') return `Rendering ${inFlight} of ${slides.length} slides`
      if (imgState === 'pending') return `Click Approve to render ${slides.length} slides`
      if (imgState === 'failed') return `All ${slides.length} slides rejected`
      if (imgState === 'partial') return `${done} done, ${failed} failed`
    }
  }
  if (draft.last_error) {
    return draft.last_error.length > 60
      ? draft.last_error.slice(0, 60) + '…'
      : draft.last_error
  }
  const f = draft.validation_log?.failures?.[0]
  if (f) return f.message || f.rule
  return '—'
}

// Approve gate: same logic as detail page slidesReadyForPublish — block
// quick-approve when format=carousel and any slide is still pending/generating/
// failed. Text drafts always pass.
function carouselReadyForApprove(draft) {
  if (!draft) return false
  if (draft.format !== 'carousel') return true
  const slides = Array.isArray(draft.carousel_slides) ? draft.carousel_slides : []
  if (slides.length === 0) return false
  return slides.every(s => s?.image_status === 'done' && !!s?.image_url)
}

async function doApprove(id) {
  await approveMutation.mutateAsync(id)
}
async function doCancel(id) {
  if (!confirm('Cancel this draft?')) return
  await cancelMutation.mutateAsync(id)
}
async function doRegenerate(id) {
  if (!confirm('Regenerate from scratch? This archives the current draft and queues a new generation.')) return
  await regenerateMutation.mutateAsync(id)
}

const emptyMessage = computed(() => ({
  in_progress: { title: 'Quiet on the wire', body: 'No drafts in the pipeline. Click "Scan blog now" to pull recent published posts.' },
  failed: { title: 'Nothing broken', body: 'No failed runs in the queue.' },
  all: { title: 'Queue is clear', body: 'No drafts in the pipeline.' },
}[activeTab.value]))
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Header -->
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-amber-400/80 mb-1">LinkedIn pipeline</p>
        <h1 class="text-3xl sm:text-4xl font-display font-semibold tracking-tight text-neutral-100">
          Draft Posts
        </h1>
        <p class="text-sm text-neutral-400 mt-2 max-w-xl">
          Drafts that need a decision, are mid-generation, or hit an error. Successfully shipped posts live in
          <router-link to="/admin/sosmed-posts" class="text-amber-400 hover:underline">SOSMED Posts</router-link>.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <!-- Manual scan: pulls completed blog posts now instead of waiting
             for the daily 03:00 WIB cron. Useful right after publishing a
             new article from Content Engine. -->
        <button
          @click="scanBlogNow"
          :disabled="scanRunning"
          title="Scan completed blog posts and create LinkedIn drafts now (instead of waiting for the daily 03:00 cron)"
          class="inline-flex items-center gap-2 px-3.5 py-2 text-sm text-neutral-100 border border-amber-500/40 bg-amber-500/10 rounded-lg hover:bg-amber-500/20 hover:border-amber-400/60 transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5" :class="scanRunning ? 'animate-spin' : ''">
            <path :d="ICON.refresh" />
          </svg>
          {{ scanRunning ? 'Scanning…' : 'Scan blog now' }}
        </button>
        <router-link
          to="/admin/sosmed-posts"
          class="inline-flex items-center gap-2 px-3.5 py-2 text-sm text-neutral-300 border border-neutral-800 rounded-lg hover:border-amber-500/40 hover:bg-amber-500/5 hover:text-amber-300 transition"
        >
          <svg viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-[#0077B5]">
            <path :d="ICON.linkedin" />
          </svg>
          View shipped posts
        </router-link>
      </div>
    </header>

    <!-- Scan flash message — auto-dismisses after 12s.
         3 tones: success (drafts created), info (zero candidates),
         error (request failed). -->
    <div
      v-if="scanFlash"
      class="rounded-lg px-3.5 py-2 text-sm border"
      :class="{
        'border-emerald-500/30 bg-emerald-500/5 text-emerald-300': scanFlash.type === 'success',
        'border-amber-500/30 bg-amber-500/5 text-amber-200': scanFlash.type === 'info',
        'border-red-500/30 bg-red-500/5 text-red-300': scanFlash.type === 'error',
      }"
    >
      {{ scanFlash.message }}
    </div>

    <!-- Tab rail (segmented control style, not generic pills) -->
    <nav class="flex flex-wrap gap-1 p-1 rounded-xl bg-neutral-900/50 border border-neutral-800/80 w-max max-w-full">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-medium transition-all',
          activeTab === tab.key
            ? 'bg-neutral-800 text-neutral-100 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)]'
            : 'text-neutral-400 hover:text-neutral-200',
        ]"
      >
        <span :class="activeTab === tab.key ? tab.accent : ''">{{ tab.label }}</span>
        <span
          v-if="tab.key !== 'all' && counts[tab.key] > 0"
          class="text-[10px] font-mono px-1.5 py-0.5 rounded-full"
          :class="activeTab === tab.key ? 'bg-neutral-700 text-neutral-200' : 'bg-neutral-800 text-neutral-500'"
        >
          {{ counts[tab.key] }}
        </span>
      </button>
    </nav>

    <!-- Loading -->
    <div v-if="isLoading" class="space-y-2">
      <div v-for="i in 6" :key="i" class="h-14 rounded-xl bg-neutral-900/40 animate-pulse" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/5 p-8 text-center">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 text-red-400 mx-auto mb-3">
        <path :d="ICON.alertCircle" />
      </svg>
      <p class="text-red-300 font-medium">Failed to load queue</p>
      <p class="text-xs text-neutral-500 mt-2 font-mono">{{ error.message || 'Unknown error' }}</p>
    </div>

    <!-- Empty -->
    <div
      v-else-if="drafts.length === 0"
      class="rounded-2xl border border-dashed border-neutral-800 p-12 text-center"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" class="w-12 h-12 text-neutral-700 mx-auto mb-3">
        <path :d="ICON.inbox" />
      </svg>
      <p class="text-base text-neutral-300 font-medium">{{ emptyMessage.title }}</p>
      <p class="text-sm text-neutral-500 mt-1 max-w-sm mx-auto">{{ emptyMessage.body }}</p>
    </div>

    <!-- Table-list (cockpit, hairline dividers, no card boxes) -->
    <div v-else class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 overflow-hidden">
      <!-- Header row: 7 cols. Sortable headers are buttons; non-sortable
           are plain spans. Active sort col gets amber accent + ▲/▼ glyph. -->
      <div class="hidden md:grid grid-cols-[1fr_90px_130px_1fr_110px_130px] px-5 py-2.5 text-[10px] font-mono uppercase tracking-[0.14em] text-neutral-500 border-b border-neutral-800/80 gap-x-4">
        <button
          @click="toggleSort('source')"
          class="text-left inline-flex items-center gap-1 hover:text-neutral-300 transition-colors"
          :class="sortState.key === 'source' ? 'text-amber-400' : ''"
        >
          Source
          <span v-if="sortIndicator('source')" class="text-[8px]">{{ sortIndicator('source') }}</span>
        </button>
        <button
          @click="toggleSort('virality')"
          class="text-right inline-flex items-center justify-end gap-1 hover:text-neutral-300 transition-colors"
          :class="sortState.key === 'virality' ? 'text-amber-400' : ''"
        >
          Virality
          <span v-if="sortIndicator('virality')" class="text-[8px]">{{ sortIndicator('virality') }}</span>
        </button>
        <span>Status</span>
        <span>Issue</span>
        <button
          @click="toggleSort('published')"
          class="text-right inline-flex items-center justify-end gap-1 hover:text-neutral-300 transition-colors"
          :class="sortState.key === 'published' ? 'text-amber-400' : ''"
        >
          Published
          <span v-if="sortIndicator('published')" class="text-[8px]">{{ sortIndicator('published') }}</span>
        </button>
        <span class="text-right">Actions</span>
      </div>

      <div class="divide-y divide-neutral-800/60">
        <article
          v-for="draft in drafts"
          :key="draft.id"
          @click="openDetail(draft.id)"
          class="group relative px-5 py-3.5 grid md:grid-cols-[1fr_90px_130px_1fr_110px_130px] gap-y-2 gap-x-4 cursor-pointer hover:bg-neutral-900/40 transition-colors"
        >
          <!-- Source -->
          <div class="min-w-0">
            <p class="text-sm text-neutral-200 truncate font-medium">{{ postTitle(draft) }}</p>
            <p class="text-[11px] font-mono text-neutral-600">#{{ draft.id }} · {{ formatChip(draft.format) }}<template v-if="draft.format === 'carousel' && Array.isArray(draft.carousel_slides)"> · {{ draft.carousel_slides.length }} slides</template></p>
          </div>

          <!-- Virality (sourced from post.content_idea.virality_score) -->
          <div class="md:self-center md:text-right">
            <span
              v-if="draft.post?.content_idea?.virality_score !== null && draft.post?.content_idea?.virality_score !== undefined"
              class="text-sm font-mono font-bold"
              :class="depthTone(draft.post.content_idea.virality_score)"
              :title="`Virality score: ${draft.post.content_idea.virality_score}/100 (from Content Engine)`"
            >
              {{ draft.post.content_idea.virality_score }}
            </span>
            <span v-else class="text-sm text-neutral-600 font-mono" title="No Content Engine source — manual blog post">—</span>
          </div>

          <!-- Status — uses effectiveStatusMeta so carousel rows in
               manual_review with un-rendered slides show "Awaiting render"
               instead of a misleading "Manual review". -->
          <div class="md:self-center">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono uppercase tracking-[0.12em]"
              :class="MOOD_CLASSES[effectiveStatusMeta(draft).mood]?.chip"
            >
              <span class="w-1 h-1 rounded-full" :class="MOOD_CLASSES[effectiveStatusMeta(draft).mood]?.dot" />
              {{ effectiveStatusMeta(draft).short }}
              <!-- Comma-operator forces tick subscription without breaking the function call -->
              <template v-if="(tick, generatingProgress(draft)) !== null">
                <span class="opacity-70">~{{ generatingProgress(draft) }}%</span>
              </template>
            </span>
          </div>

          <!-- Issue -->
          <div class="md:self-center text-xs text-neutral-400 truncate min-w-0">
            {{ issueSummary(draft) }}
            <span
              v-if="(draft.auto_retry_count ?? 0) > 0"
              class="ml-2 inline-block px-1.5 py-0.5 rounded text-[9px] font-mono tracking-normal whitespace-nowrap align-middle"
              :class="{
                'bg-emerald-950/60 text-emerald-300': draft.last_classified_error_class === 'transient',
                'bg-amber-950/60 text-amber-300': draft.last_classified_error_class === 'deterministic_llm',
                'bg-neutral-800 text-neutral-400': !['transient','deterministic_llm'].includes(draft.last_classified_error_class),
              }"
              :title="`Auto-retried ${draft.auto_retry_count}× (class: ${draft.last_classified_error_class || 'unknown'})`"
            >
              ↻ {{ draft.auto_retry_count }}×
            </span>
          </div>

          <!-- Published (source blog's published_at, falls back to draft updated_at) -->
          <div class="md:self-center md:text-right text-xs text-neutral-500 font-mono whitespace-nowrap">
            <template v-if="draft.post?.published_at">
              <span :title="`Blog published: ${draft.post.published_at}`">{{ relativeTime(draft.post.published_at) }}</span>
            </template>
            <span v-else class="text-neutral-600" :title="`Draft last updated: ${draft.updated_at}`">
              {{ relativeTime(draft.updated_at) }}*
            </span>
          </div>

          <!-- Actions -->
          <div class="md:self-center md:text-right" @click.stop>
            <div class="inline-flex items-center gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
              <button
                v-if="draft.status === 'manual_review' && classifyForQueue(draft) === 'in_progress'"
                @click="doApprove(draft.id)"
                :disabled="approveMutation.isPending.value || !carouselReadyForApprove(draft)"
                :title="carouselReadyForApprove(draft) ? 'Approve' : 'Carousel slides not all rendered yet'"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 ring-1 ring-emerald-500/30 disabled:opacity-30 disabled:cursor-not-allowed"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                  <path :d="ICON.check" />
                </svg>
              </button>
              <button
                v-if="['manual_review', 'failed'].includes(draft.status)"
                @click="doRegenerate(draft.id)"
                :disabled="regenerateMutation.isPending.value"
                title="Regenerate"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20 ring-1 ring-cyan-500/30 disabled:opacity-50"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                  <path :d="ICON.refresh" />
                </svg>
              </button>
              <button
                v-if="!['cancelled', 'published'].includes(draft.status)"
                @click="doCancel(draft.id)"
                :disabled="cancelMutation.isPending.value"
                title="Cancel"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-neutral-800/70 text-neutral-400 hover:bg-red-500/15 hover:text-red-400 ring-1 ring-neutral-800 hover:ring-red-500/30 disabled:opacity-50"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                  <path :d="ICON.x" />
                </svg>
              </button>
            </div>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>
