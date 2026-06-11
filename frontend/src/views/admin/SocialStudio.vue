<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useRepurposeJobsList } from '@/composables/useRepurposeJobs'
import { useLinkedInDraftsList } from '@/composables/useLinkedInDrafts'
import { fetchSlideObjectUrl } from '@/composables/useRepurposeJobs'
import {
  toCard,
  matchesFilter,
  mergeAndSort,
  countsBySource,
} from './socialStudioHelpers.js'
import { relativeTime } from './repurposeHelpers.js'

/**
 * Social Studio — merged work surface for blog-origin LinkedIn drafts +
 * IG-repurpose jobs. Two DISJOINT sources unioned into one card list:
 *   - IG  = all RepurposeJob rows (useRepurposeJobsList)
 *   - Blog = LinkedInPost queue drafts, repurpose-origin EXCLUDED
 *            (useLinkedInDraftsList, exclude_repurpose=1)
 * Cockpit dark aesthetic (neutral-950, amber/cyan, mono labels, inline SVG —
 * no emoji), matching LinkedInQueueList.
 */

const router = useRouter()

// --- sources ---------------------------------------------------------------
const igFilters = computed(() => ({ per_page: 100 }))
const { jobs: igJobs, isLoading: igLoading, isFetching: igFetching, refetch: refetchIg } =
  useRepurposeJobsList(igFilters)

const blogFilters = computed(() => ({ scope: 'queue', exclude_repurpose: 1, per_page: 100 }))
const { drafts: blogDrafts, isLoading: blogLoading, isFetching: blogFetching, refetch: refetchBlog } =
  useLinkedInDraftsList(blogFilters)

// --- unified cards ---------------------------------------------------------
const allCards = computed(() =>
  mergeAndSort(igJobs.value.map(toCard), blogDrafts.value.map(toCard)),
)
const counts = computed(() => countsBySource(allCards.value))

const FILTERS = [
  { key: 'all', label: 'All' },
  { key: 'blog', label: 'Blog' },
  { key: 'ig', label: 'IG' },
  { key: 'failed', label: 'Failed' },
]
const activeFilter = ref('all')
const visibleCards = computed(() =>
  allCards.value.filter((c) => matchesFilter(c, activeFilter.value)),
)

const isLoading = computed(() => igLoading.value || blogLoading.value)
const isFetching = computed(() => igFetching.value || blogFetching.value)
function refetchAll() {
  refetchIg()
  refetchBlog()
}

// --- lazy IG covers (private blobs) ---------------------------------------
// IG slide-0 is auth-gated → fetch as object URL per card; revoke on unmount.
const igCoverUrls = ref({}) // jobId -> objectURL
const igCoverTried = new Set()

watch(
  visibleCards,
  (cards) => {
    for (const c of cards) {
      if (c.kind === 'ig' && c.hasCover && !igCoverTried.has(c.coverJobId)) {
        igCoverTried.add(c.coverJobId)
        fetchSlideObjectUrl(c.coverJobId, 0)
          .then((url) => { igCoverUrls.value = { ...igCoverUrls.value, [c.coverJobId]: url } })
          .catch(() => { /* private slide unavailable — the empty-cover glyph shows instead */ })
      }
    }
  },
  { immediate: true },
)

function cardCover(card) {
  if (card.kind === 'blog') return card.coverUrl
  return igCoverUrls.value[card.coverJobId] || null
}

onBeforeUnmount(() => {
  Object.values(igCoverUrls.value).forEach((url) => URL.revokeObjectURL(url))
})

// --- status presentation (source-aware, presentational) -------------------
const BLOG_STATUS = {
  pending_generation: ['Queued', 'cyan'],
  generating: ['Generating', 'cyan'],
  validating: ['Validating', 'cyan'],
  manual_review: ['Review', 'amber'],
  awaiting_publish: ['Scheduled', 'emerald'],
  published: ['Published', 'emerald'],
  cancelled: ['Cancelled', 'neutral'],
  failed: ['Failed', 'red'],
}
const IG_STATUS = {
  received: ['Awaiting mode', 'neutral'],
  capturing: ['Capturing', 'cyan'],
  captured: ['Captured', 'cyan'],
  extracting: ['Reading', 'cyan'],
  extracted: ['Extracted', 'cyan'],
  researching: ['Fact-checking', 'cyan'],
  researched: ['Verified', 'cyan'],
  rewriting: ['Rewriting', 'cyan'],
  rewritten: ['Rewritten', 'cyan'],
  finalizing: ['Finalizing', 'cyan'],
  drafted: ['Draft ready', 'emerald'],
  failed: ['Failed', 'red'],
}
const TONE = {
  emerald: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
  amber: 'bg-amber-500/15 text-amber-300 ring-amber-500/30',
  cyan: 'bg-cyan-500/15 text-cyan-300 ring-cyan-500/30',
  red: 'bg-red-500/15 text-red-300 ring-red-500/30',
  neutral: 'bg-neutral-500/15 text-neutral-300 ring-neutral-500/30',
}
function statusMeta(card) {
  const map = card.kind === 'ig' ? IG_STATUS : BLOG_STATUS
  return map[card.status] || [card.status, 'neutral']
}
function statusLabelFor(card) { return statusMeta(card)[0] }
function statusToneFor(card) { return TONE[statusMeta(card)[1]] }

const PLATFORM_LABEL = { li: 'LI', ig: 'IG', tt: 'TT', th: 'TH' }

function openCard(card) {
  // Merged detail back-buttons must return to Social Studio.
  sessionStorage.setItem('linkedin:detail:origin', 'studio')
  router.push(card.route)
}
</script>

<template>
  <div class="px-4 py-6 sm:px-6 lg:px-8">
    <!-- header -->
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-neutral-100">Social Studio</h1>
        <p class="mt-1 text-sm text-neutral-400">
          Draft queue + IG repurpose in one place — original → generated → publish.
        </p>
      </div>
      <button
        class="rounded-lg bg-neutral-800 px-3 py-2 text-sm text-neutral-200 ring-1 ring-neutral-700 transition hover:bg-neutral-700 active:scale-[0.98]"
        :disabled="isFetching"
        @click="refetchAll()"
      >
        {{ isFetching ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <!-- filter chips -->
    <div class="mb-5 flex flex-wrap gap-2">
      <button
        v-for="f in FILTERS"
        :key="f.key"
        class="rounded-full px-4 py-1.5 text-xs font-medium uppercase tracking-wide ring-1 transition"
        :class="activeFilter === f.key
          ? 'bg-amber-500/20 text-amber-200 ring-amber-500/40'
          : 'bg-neutral-900/60 text-neutral-300 ring-neutral-800 hover:bg-neutral-800'"
        @click="activeFilter = f.key"
      >
        {{ f.label }}
        <span class="ml-1.5 text-neutral-500">{{ counts[f.key] }}</span>
      </button>
    </div>

    <!-- loading -->
    <div v-if="isLoading && allCards.length === 0" class="space-y-3">
      <div v-for="n in 4" :key="n" class="h-20 animate-pulse rounded-xl bg-neutral-900/60 ring-1 ring-neutral-800" />
    </div>

    <!-- empty -->
    <div
      v-else-if="visibleCards.length === 0"
      class="rounded-xl border border-dashed border-neutral-800 bg-neutral-900/30 px-6 py-16 text-center"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-3 h-12 w-12 text-neutral-700">
        <rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18M9 21V9" />
      </svg>
      <p class="text-sm text-neutral-400">Nothing here yet.</p>
      <p class="mt-1 text-xs text-neutral-600">
        Publish from Content Engine to create a draft, or send an Instagram URL to the Telegram bot to repurpose.
      </p>
    </div>

    <!-- cards -->
    <ul v-else class="space-y-3">
      <li
        v-for="card in visibleCards"
        :key="`${card.kind}-${card.id}`"
        class="group flex cursor-pointer items-center gap-4 rounded-xl bg-neutral-900/50 p-3 ring-1 ring-neutral-800 transition hover:-translate-y-px hover:bg-neutral-900 hover:ring-neutral-700"
        @click="openCard(card)"
      >
        <!-- cover -->
        <div class="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-neutral-800 ring-1 ring-neutral-700">
          <img v-if="cardCover(card)" :src="cardCover(card)" alt="" class="h-full w-full object-cover" loading="lazy" />
          <div v-else class="flex h-full w-full items-center justify-center">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="h-6 w-6 text-neutral-600">
              <rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-5-5L5 21" />
            </svg>
          </div>
        </div>

        <!-- title + badges -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <!-- source badge (text chip, no emoji) -->
            <span
              class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider ring-1"
              :class="card.sourceBadge === 'ig'
                ? 'bg-fuchsia-500/15 text-fuchsia-300 ring-fuchsia-500/30'
                : 'bg-sky-500/15 text-sky-300 ring-sky-500/30'"
            >{{ card.sourceBadge === 'ig' ? 'IG' : 'Blog' }}</span>
            <span
              v-for="p in card.platforms"
              :key="p"
              class="rounded bg-neutral-800 px-1 py-0.5 text-[10px] font-medium uppercase tracking-wider text-neutral-400 ring-1 ring-neutral-700"
            >{{ PLATFORM_LABEL[p] }}</span>
          </div>
          <p class="mt-1 truncate text-sm font-medium text-neutral-100">{{ card.title }}</p>
        </div>

        <!-- status + updated -->
        <div class="flex shrink-0 flex-col items-end gap-1">
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1" :class="statusToneFor(card)">
            {{ statusLabelFor(card) }}
          </span>
          <span class="text-xs text-neutral-500">{{ relativeTime(card.updatedAt) }}</span>
        </div>
      </li>
    </ul>
  </div>
</template>
