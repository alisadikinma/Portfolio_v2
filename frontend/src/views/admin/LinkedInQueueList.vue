<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  useLinkedInDraftsList,
  useCancelLinkedInDraft,
  useApproveLinkedInDraft,
  useRegenerateLinkedInDraft,
  postTitle,
} from '@/composables/useLinkedInDrafts'

const router = useRouter()

const activeTab = ref('manual_review') // 'manual_review' | 'failed' | 'in_progress' | 'all'

// Always fetch the full queue (no server-side status filter) so client-side
// classification can re-route carousel drafts whose slide rendering is still
// in flight or has terminal-failed. A draft sitting at status='manual_review'
// with zero rendered slides is NOT actually reviewable — it belongs in
// In Progress (still rendering) or Failed (all slides rejected).
const filters = computed(() => ({
  scope: 'queue',
  per_page: 100,
}))

const { drafts: allDrafts, isLoading, error } = useLinkedInDraftsList(filters)

/**
 * Inspect carousel slide image_status[] and return one of:
 *   'ready'      — all slides have a rendered PNG
 *   'partial'    — some slides done, rest failed/pending (operator can review)
 *   'generating' — at least one slide pending or generating (still in flight)
 *   'failed'     — every slide is failed (terminal — no rendered images at all)
 *   'pending'    — no slides have been dispatched yet (legacy / never queued)
 */
function carouselImageState(draft) {
  if (draft.format !== 'carousel') return 'ready'
  const slides = Array.isArray(draft.carousel_slides) ? draft.carousel_slides : []
  if (slides.length === 0) return 'failed' // empty carousel ≈ parse failure

  let done = 0
  let inFlight = 0
  let failed = 0
  for (const slide of slides) {
    const s = slide?.image_status
    if (s === 'done' && slide?.image_url) done++
    else if (s === 'pending' || s === 'generating') inFlight++
    else if (s === 'failed') failed++
  }

  if (done === slides.length) return 'ready'
  if (inFlight > 0) return 'generating'
  if (done > 0) return 'partial'
  if (failed === slides.length) return 'failed'
  return 'pending' // none dispatched (legacy carousel pre-Apr 27)
}

/**
 * Returns one of: 'manual_review' | 'in_progress' | 'failed' | null
 * — null means the draft does not belong in the operator queue at all.
 */
function classifyForQueue(draft) {
  if (['pending_generation', 'generating', 'validating'].includes(draft.status)) {
    return 'in_progress'
  }
  if (draft.status === 'failed') return 'failed'

  if (draft.status === 'manual_review') {
    if (draft.format === 'text') return 'manual_review'
    const imgState = carouselImageState(draft)
    if (imgState === 'pending' || imgState === 'generating') return 'in_progress'
    if (imgState === 'failed') return 'failed'
    return 'manual_review' // ready or partial — operator can review what we've got
  }

  return null
}

const drafts = computed(() => {
  if (activeTab.value === 'all') return allDrafts.value
  return allDrafts.value.filter((d) => classifyForQueue(d) === activeTab.value)
})

const cancelMutation = useCancelLinkedInDraft()
const approveMutation = useApproveLinkedInDraft()
const regenerateMutation = useRegenerateLinkedInDraft()

const tabs = [
  { key: 'manual_review', label: 'Manual Review' },
  { key: 'failed', label: 'Failed' },
  { key: 'in_progress', label: 'In Progress' },
  { key: 'all', label: 'All' },
]

function openDetail(id) {
  router.push({ name: 'admin-linkedin-draft-detail', params: { id } })
}

function statusBadgeClass(status) {
  return {
    manual_review: 'bg-amber-500/20 text-amber-400 border-amber-500/40',
    failed: 'bg-red-500/20 text-red-400 border-red-500/40',
    pending_generation: 'bg-neutral-500/20 text-neutral-400 border-neutral-500/40',
    generating: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40',
    validating: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40',
  }[status] || 'bg-neutral-500/20 text-neutral-400'
}

function statusLabel(status) {
  return {
    manual_review: 'Manual Review',
    failed: 'Failed',
    pending_generation: 'Pending',
    generating: 'Generating…',
    validating: 'Validating…',
  }[status] || status
}

function issueSummary(draft) {
  // Carousel-specific image-state hints take precedence — they explain WHY a
  // manual_review draft is showing in the In Progress or Failed bucket.
  if (draft.format === 'carousel' && draft.status === 'manual_review') {
    const slides = Array.isArray(draft.carousel_slides) ? draft.carousel_slides : []
    if (slides.length > 0) {
      const imgState = carouselImageState(draft)
      const inFlight = slides.filter((s) => ['pending', 'generating'].includes(s?.image_status)).length
      const failed = slides.filter((s) => s?.image_status === 'failed').length
      const done = slides.filter((s) => s?.image_status === 'done' && s?.image_url).length
      if (imgState === 'generating') return `Rendering ${inFlight} of ${slides.length} slides…`
      if (imgState === 'pending') return `${slides.length} slides queued, awaiting dispatch`
      if (imgState === 'failed') return `All ${slides.length} slides rejected — see detail`
      if (imgState === 'partial') return `${done} done · ${failed} failed (review and retry)`
    }
  }

  if (draft.last_error) {
    return draft.last_error.slice(0, 60) + (draft.last_error.length > 60 ? '…' : '')
  }
  const firstFailure = draft.validation_log?.failures?.[0]
  if (firstFailure) {
    return firstFailure.message || firstFailure.rule
  }
  return '—'
}

function depthDisplay(score) {
  if (score === null || score === undefined) return '—'
  return score
}

function relativeTime(datetime) {
  if (!datetime) return ''
  const diff = Date.now() - new Date(datetime).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
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

const emptyMessage = computed(() => {
  if (activeTab.value === 'manual_review') return 'No drafts need attention.'
  if (activeTab.value === 'failed') return 'No failures in the last 7 days.'
  if (activeTab.value === 'in_progress') return 'No drafts currently being generated.'
  return 'Queue is clear.'
})

const counts = computed(() => {
  const c = { manual_review: 0, failed: 0, in_progress: 0 }
  for (const d of allDrafts.value) {
    const bucket = classifyForQueue(d)
    if (bucket && c[bucket] !== undefined) c[bucket]++
  }
  return c
})
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
          LinkedIn Queue
        </h1>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">
          Drafts needing attention. For successfully shipped posts, see
          <router-link to="/admin/linkedin-posts" class="text-amber-600 hover:underline">Posts</router-link>.
        </p>
      </div>
      <router-link
        to="/admin/linkedin-posts"
        class="px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-600 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700"
      >
        ← Go to Posts
      </router-link>
    </div>

    <!-- Filter tabs with counts -->
    <div class="mb-4 flex flex-wrap gap-2 border-b border-neutral-200 dark:border-neutral-700 pb-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2',
          activeTab === tab.key
            ? 'bg-amber-500/20 text-amber-700 dark:text-amber-400'
            : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
        ]"
      >
        {{ tab.label }}
        <span
          v-if="tab.key !== 'all' && counts[tab.key] > 0"
          class="text-[10px] px-1.5 py-0.5 rounded-full bg-neutral-500/20 text-neutral-600 dark:text-neutral-400"
        >
          {{ counts[tab.key] }}
        </span>
      </button>
    </div>

    <!-- Loading / error / empty -->
    <div v-if="isLoading" class="py-12 text-center text-neutral-500">
      Loading queue…
    </div>
    <div v-else-if="error" class="py-12 text-center">
      <p class="text-red-500">Failed to load queue</p>
      <p class="text-xs text-neutral-500 mt-1">{{ error.message || 'Unknown error' }}</p>
    </div>
    <div v-else-if="drafts.length === 0" class="py-16 text-center text-neutral-500">
      {{ emptyMessage }}
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 dark:bg-neutral-800 text-left">
          <tr>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300">Blog source</th>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300">Status</th>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300">Format</th>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300 text-right">Depth</th>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300">Issue</th>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300 text-right">Retries</th>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300">Updated</th>
            <th class="px-4 py-3 font-semibold text-neutral-700 dark:text-neutral-300 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="draft in drafts"
            :key="draft.id"
            @click="openDetail(draft.id)"
            class="border-t border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer"
          >
            <td class="px-4 py-3 text-neutral-800 dark:text-neutral-200 max-w-[260px]">
              <div class="truncate font-medium">
                {{ postTitle(draft) }}
              </div>
              <div class="text-[11px] text-neutral-500 truncate">#{{ draft.id }}</div>
            </td>
            <td class="px-4 py-3">
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wider border"
                :class="statusBadgeClass(draft.status)"
              >
                {{ statusLabel(draft.status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400 font-mono text-xs uppercase">
              {{ draft.format }}
            </td>
            <td class="px-4 py-3 text-right font-mono" :class="{
              'text-red-400': draft.depth_score !== null && draft.depth_score < 70,
              'text-yellow-500': draft.depth_score >= 70 && draft.depth_score < 80,
              'text-amber-400': draft.depth_score >= 80,
              'text-neutral-500': draft.depth_score === null || draft.depth_score === undefined,
            }">
              {{ depthDisplay(draft.depth_score) }}
            </td>
            <td class="px-4 py-3 text-neutral-700 dark:text-neutral-300 text-xs max-w-[280px]">
              <span class="truncate block">{{ issueSummary(draft) }}</span>
            </td>
            <td class="px-4 py-3 text-right text-neutral-600 dark:text-neutral-400 font-mono text-xs">
              {{ draft.retry_count }}/3
            </td>
            <td class="px-4 py-3 text-neutral-500 text-xs whitespace-nowrap">
              {{ relativeTime(draft.updated_at) }}
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <div class="inline-flex gap-1">
                <button
                  v-if="draft.status === 'manual_review'"
                  @click.stop="doApprove(draft.id)"
                  :disabled="approveMutation.isPending.value"
                  title="Approve"
                  class="px-2 py-1 text-[11px] rounded bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/30 disabled:opacity-50"
                >
                  Approve
                </button>
                <button
                  v-if="['manual_review', 'failed', 'cancelled'].includes(draft.status)"
                  @click.stop="doRegenerate(draft.id)"
                  :disabled="regenerateMutation.isPending.value"
                  title="Regenerate"
                  class="px-2 py-1 text-[11px] rounded bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 hover:bg-cyan-500/30 disabled:opacity-50"
                >
                  Regen
                </button>
                <button
                  v-if="!['cancelled', 'published'].includes(draft.status)"
                  @click.stop="doCancel(draft.id)"
                  :disabled="cancelMutation.isPending.value"
                  title="Cancel"
                  class="px-2 py-1 text-[11px] rounded bg-neutral-500/20 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-500/30 disabled:opacity-50"
                >
                  Cancel
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
