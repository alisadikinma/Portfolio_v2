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
 * IG-repurpose jobs. Two DISJOINT sources unioned into one list:
 *   - IG  = all RepurposeJob rows (useRepurposeJobsList)
 *   - Blog = LinkedInPost queue drafts, repurpose-origin EXCLUDED
 *            (useLinkedInDraftsList, exclude_repurpose=1)
 *
 * Display matches the Content Engine spreadsheet (light/dark table, amber
 * accent, status pills, tab strip, per-row open action) so the two admin
 * surfaces read as one product.
 */

const router = useRouter()

// --- sources ---------------------------------------------------------------
// exclude_settled: once an IG job's carousel/blog is scheduled into the Content
// Calendar OR published, it leaves Social Studio — exactly as a blog draft does
// (the blog scope=queue gate already drops awaiting_publish + published drafts).
const igFilters = computed(() => ({ per_page: 100, exclude_settled: 1 }))
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
  // Private source slide-0 blob (if fetched) wins; else the public generated
  // carousel cover (card.coverUrl) so a thumbnail still shows after purge.
  return igCoverUrls.value[card.coverJobId] || card.coverUrl || null
}

onBeforeUnmount(() => {
  Object.values(igCoverUrls.value).forEach((url) => URL.revokeObjectURL(url))
})

// --- status presentation (source-aware, light/dark) -----------------------
const BLOG_STATUS = {
  pending_generation: ['Queued', 'blue'],
  generating: ['Generating', 'blue'],
  validating: ['Validating', 'blue'],
  manual_review: ['Review', 'amber'],
  awaiting_publish: ['Scheduled', 'emerald'],
  published: ['Published', 'green'],
  cancelled: ['Cancelled', 'neutral'],
  failed: ['Failed', 'red'],
}
const IG_STATUS = {
  received: ['Awaiting mode', 'neutral'],
  capturing: ['Capturing', 'blue'],
  captured: ['Captured', 'blue'],
  extracting: ['Reading', 'blue'],
  extracted: ['Extracted', 'blue'],
  researching: ['Fact-checking', 'blue'],
  researched: ['Verified', 'blue'],
  rewriting: ['Rewriting', 'blue'],
  rewritten: ['Rewritten', 'blue'],
  finalizing: ['Finalizing', 'blue'],
  drafted: ['Draft ready', 'emerald'],
  failed: ['Failed', 'red'],
}
const TONE = {
  emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
  green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  neutral: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
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
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Social Studio</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
          Draft queue + Instagram repurpose in one place — original → generated → publish.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50 active:scale-[0.98] disabled:opacity-60 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
        :disabled="isFetching"
        @click="refetchAll()"
      >
        <svg class="h-4 w-4" :class="{ 'animate-spin': isFetching }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" />
        </svg>
        {{ isFetching ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <!-- card -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
      <!-- filter tabs (Content Engine strip style) -->
      <div class="flex flex-wrap gap-1 border-b border-neutral-200 px-3 pt-2 dark:border-neutral-700">
        <button
          v-for="f in FILTERS"
          :key="f.key"
          type="button"
          @click="activeFilter = f.key"
          :class="[
            'px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
            activeFilter === f.key
              ? 'border-amber-500 text-amber-600 dark:text-amber-400'
              : 'border-transparent text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200',
          ]"
        >
          {{ f.label }}
          <span
            :class="[
              'ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold rounded-full',
              activeFilter === f.key
                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'
                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400',
            ]"
          >{{ counts[f.key] }}</span>
        </button>
      </div>

      <!-- table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-neutral-50 text-left dark:bg-neutral-700/50">
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 w-14"></th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 w-20">Source</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Topic</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 w-40">Platforms</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 w-32">Status</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 w-24">Updated</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 text-right w-16">Open</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
            <!-- loading -->
            <tr v-if="isLoading && allCards.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">
                <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                Loading…
              </td>
            </tr>

            <!-- empty -->
            <tr v-else-if="visibleCards.length === 0">
              <td colspan="7" class="px-4 py-14 text-center">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Nothing here yet.</p>
                <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-600">
                  Publish from Content Engine to create a draft, or send an Instagram URL to the Telegram bot to repurpose.
                </p>
              </td>
            </tr>

            <!-- rows -->
            <tr
              v-else
              v-for="card in visibleCards"
              :key="`${card.kind}-${card.id}`"
              class="cursor-pointer transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-700/30"
              @click="openCard(card)"
            >
              <!-- cover -->
              <td class="px-4 py-3">
                <div class="relative h-12 w-12 overflow-hidden rounded-lg bg-neutral-100 ring-1 ring-neutral-200 dark:bg-neutral-700 dark:ring-neutral-600">
                  <img v-if="cardCover(card)" :src="cardCover(card)" alt="" class="h-full w-full object-cover" loading="lazy" />
                  <div v-else class="flex h-full w-full items-center justify-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="h-5 w-5 text-neutral-400 dark:text-neutral-500">
                      <rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-5-5L5 21" />
                    </svg>
                  </div>
                </div>
              </td>

              <!-- source badge -->
              <td class="px-4 py-3 align-middle">
                <span
                  class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                  :class="card.sourceBadge === 'ig'
                    ? 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-300'
                    : 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'"
                >{{ card.sourceBadge === 'ig' ? 'IG' : 'Blog' }}</span>
              </td>

              <!-- topic -->
              <td class="px-4 py-3 font-medium text-neutral-900 dark:text-neutral-100">
                <span class="line-clamp-2">{{ card.title }}</span>
              </td>

              <!-- platforms -->
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="p in card.platforms"
                    :key="p"
                    class="rounded bg-neutral-100 px-1 py-0.5 text-[10px] font-medium uppercase tracking-wider text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400"
                  >{{ PLATFORM_LABEL[p] }}</span>
                  <span v-if="!card.platforms.length" class="text-xs text-neutral-300 dark:text-neutral-600">—</span>
                </div>
              </td>

              <!-- status -->
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium" :class="statusToneFor(card)">
                  {{ statusLabelFor(card) }}
                </span>
              </td>

              <!-- updated -->
              <td class="px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400">{{ relativeTime(card.updatedAt) }}</td>

              <!-- open -->
              <td class="px-4 py-3 text-right">
                <span class="inline-flex p-1.5 text-neutral-400 transition-colors group-hover:text-amber-600 dark:text-neutral-500">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                  </svg>
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- footer count -->
      <div class="flex items-center justify-end border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
        <span class="text-xs text-neutral-500 dark:text-neutral-400">
          {{ visibleCards.length }} shown / {{ allCards.length }} total
        </span>
      </div>
    </div>
  </div>
</template>
