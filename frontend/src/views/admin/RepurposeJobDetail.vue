<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useRepurposeJob,
  useRetryRepurposeJob,
  fetchSlideObjectUrl,
} from '@/composables/useRepurposeJobs'
import {
  useLinkedInDraft,
  useApproveLinkedInDraft,
  useCancelLinkedInDraft,
  useRegenerateAllCarouselImages,
  usePublishLinkedInDraftNow,
} from '@/composables/useLinkedInDrafts'
import {
  statusTone,
  statusLabel,
  modeLabel,
  relativeTime,
  inferFailedStep,
  rightPaneMode,
} from './repurposeHelpers.js'

/**
 * Source ↔ Generated comparison workspace for an IG-repurpose job.
 *
 * The hero is an aligned slide-by-slide grid: captured IG slide N (PRIVATE →
 * blob-fetched) on the left, the generated slide N (PUBLIC carousel image) on
 * the right, so the operator sees the transformation per slide. Below it: the
 * extracted intelligence (caption, claims, fact-check) beside the generated
 * caption + the SAME approve/cancel/regenerate/publish mutations the draft
 * detail uses (no duplicated publish logic). Light/dark themed to match the
 * Content Engine admin surface.
 */

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))

const { job, isLoading, refetch } = useRepurposeJob(id)
const retry = useRetryRepurposeJob()

// --- right pane: linked LinkedIn draft (generated side) --------------------
const linkedinPostId = computed(() => job.value?.linkedin_post_id || null)
const { draft } = useLinkedInDraft(linkedinPostId) // enabled only when id present

const approve = useApproveLinkedInDraft()
const cancel = useCancelLinkedInDraft()
const regenImages = useRegenerateAllCarouselImages()
const publishNow = usePublishLinkedInDraftNow()

const paneMode = computed(() => rightPaneMode(job.value))
const draftStatus = computed(() => draft.value?.status || null)
const generatedSlides = computed(() =>
  Array.isArray(draft.value?.carousel_slides) ? draft.value.carousel_slides : [],
)

async function doApprove() { await approve.mutateAsync(linkedinPostId.value); refetch() }
async function doCancel() { await cancel.mutateAsync(linkedinPostId.value); refetch() }
async function doRegenImages() { await regenImages.mutateAsync(linkedinPostId.value); refetch() }
async function doPublishNow() { await publishNow.mutateAsync(linkedinPostId.value); refetch() }
const anyDraftActionPending = computed(() =>
  approve.isPending.value || cancel.isPending.value ||
  regenImages.isPending.value || publishNow.isPending.value,
)

// --- left pane: private captured slides ------------------------------------
const slideUrls = ref([])
let loadedForCount = -1

watch(
  () => job.value?.slide_count,
  async (count) => {
    if (!count || count === loadedForCount) return
    loadedForCount = count
    revokeSlides()
    const urls = []
    for (let i = 0; i < count; i++) {
      try { urls.push(await fetchSlideObjectUrl(id.value, i)) } catch { urls.push(null) }
    }
    slideUrls.value = urls
  },
)
function revokeSlides() {
  slideUrls.value.forEach(u => u && URL.revokeObjectURL(u))
  slideUrls.value = []
}
onBeforeUnmount(revokeSlides)

// --- comparison pairing ----------------------------------------------------
const hasSourceSlides = computed(() => slideUrls.value.some(Boolean))
const comparisonPairs = computed(() => {
  const n = Math.max(slideUrls.value.length, generatedSlides.value.length)
  return Array.from({ length: n }, (_, i) => ({
    i,
    src: slideUrls.value[i] || null,
    gen: generatedSlides.value[i] || null,
  }))
})
const showComparison = computed(() => paneMode.value !== 'blog' && comparisonPairs.value.length > 0)
// Source slides are cleared by the 7-day reaper; an old drafted job may have a
// generated carousel but no captured source left to compare against.
const sourceCleared = computed(() =>
  paneMode.value === 'generated' && !hasSourceSlides.value && generatedSlides.value.length > 0,
)

const verdicts = computed(() => job.value?.research?.verdicts || [])
const claims = computed(() => job.value?.extracted?.claims || [])
const correctedCount = computed(() => job.value?.research?.corrected_count ?? 0)
const isFailed = computed(() => job.value?.status === 'failed')
const headerTitle = computed(() =>
  job.value?.title || job.value?.rewritten?.title || `Repurpose #${job.value?.id ?? ''}`,
)

function claimText(c) { return typeof c === 'string' ? c : (c?.claim || JSON.stringify(c)) }
function verdictTone(status) {
  if (status === 'wrong' || status === 'outdated') return 'text-amber-600 dark:text-amber-400'
  if (status === 'correct') return 'text-emerald-600 dark:text-emerald-400'
  return 'text-neutral-500 dark:text-neutral-400'
}
async function doRetry() { await retry.mutateAsync(id.value); refetch() }
function goBack() { router.push({ name: 'admin-social-studio' }) }
</script>

<template>
  <div class="px-4 py-6 sm:px-6 lg:px-8">
    <button
      class="mb-4 inline-flex items-center gap-1 text-sm text-neutral-500 transition-colors hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200 motion-reduce:transition-none"
      @click="goBack"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
      Back to Social Studio
    </button>

    <div v-if="isLoading" class="py-12 text-center text-neutral-500 dark:text-neutral-400">Loading…</div>
    <div v-else-if="!job" class="py-12 text-center text-neutral-500 dark:text-neutral-400">Job not found.</div>

    <div v-else class="space-y-5">
      <!-- Header -->
      <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h1 class="truncate text-xl font-bold text-neutral-900 dark:text-neutral-100">{{ headerTitle }}</h1>
            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
              <span class="inline-flex items-center rounded px-2 py-0.5 font-medium" :class="statusTone(job.status)">
                {{ statusLabel(job.status) }}
              </span>
              <span class="rounded bg-neutral-100 px-2 py-0.5 font-medium text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ modeLabel(job.mode) }}</span>
              <span>#{{ job.id }} · {{ relativeTime(job.updated_at) }}</span>
            </div>
          </div>
          <button
            v-if="isFailed"
            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-600 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
            :disabled="retry.isPending.value"
            @click="doRetry"
          >
            <svg class="h-4 w-4" :class="{ 'animate-spin': retry.isPending.value }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
            {{ retry.isPending.value ? 'Retrying…' : `Retry ${inferFailedStep(job.pipeline_state_log) || 'capture'} step` }}
          </button>
        </div>
        <p v-if="job.last_error" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-900/20 dark:text-red-300 dark:ring-red-500/20">
          {{ job.last_error }}
        </p>
      </div>

      <!-- Comparison: Source -> Generated -->
      <div v-if="showComparison" class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="mb-4 flex items-center gap-2">
          <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Slide comparison</h2>
          <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ comparisonPairs.length }} slides</span>
        </div>

        <!-- column legend -->
        <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_1fr]">
          <span class="text-[11px] font-bold uppercase tracking-widest text-fuchsia-600 dark:text-fuchsia-400">Source · Original IG</span>
          <span class="hidden w-6 sm:block"></span>
          <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Generated · Your version</span>
        </div>

        <p v-if="sourceCleared" class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:ring-amber-500/20">
          The original Instagram slides were cleared after drafting — showing the generated version only.
        </p>

        <!-- paired rows -->
        <div class="space-y-3">
          <div
            v-for="pair in comparisonPairs"
            :key="pair.i"
            class="grid grid-cols-1 items-center gap-3 sm:grid-cols-[1fr_auto_1fr]"
          >
            <!-- source slide -->
            <div class="overflow-hidden rounded-lg bg-neutral-100 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-700">
              <img v-if="pair.src" :src="pair.src" :alt="`source slide ${pair.i + 1}`" class="block w-full object-contain" />
              <div v-else class="flex aspect-[4/5] items-center justify-center text-xs text-neutral-400 dark:text-neutral-600">
                {{ sourceCleared ? 'cleared' : 'slide ' + (pair.i + 1) }}
              </div>
            </div>

            <!-- arrow -->
            <div class="flex items-center justify-center">
              <svg class="h-5 w-5 rotate-90 text-neutral-300 dark:text-neutral-600 sm:rotate-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
            </div>

            <!-- generated slide -->
            <div class="overflow-hidden rounded-lg bg-neutral-100 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-700">
              <img
                v-if="pair.gen && pair.gen.image_status === 'done' && pair.gen.image_url"
                :src="pair.gen.image_url"
                :alt="`generated slide ${pair.i + 1}`"
                class="block w-full object-contain"
              />
              <div v-else class="flex aspect-[4/5] items-center justify-center px-2 text-center text-xs"
                   :class="pair.gen && pair.gen.image_status === 'failed' ? 'text-red-500 dark:text-red-400' : 'text-neutral-400 dark:text-neutral-600'">
                {{ pair.gen ? (pair.gen.image_status === 'failed' ? 'failed' : 'rendering…') : (paneMode === 'in_progress' ? 'generating…' : 'not generated') }}
              </div>
            </div>
          </div>
        </div>

        <!-- generated caption + actions -->
        <template v-if="paneMode === 'generated' && draft">
          <div class="mt-5 flex items-center gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-700">
            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium" :class="statusTone(draftStatus)">
              {{ statusLabel(draftStatus) }}
            </span>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">LinkedIn draft #{{ linkedinPostId }}</span>
          </div>

          <p v-if="draft.content" class="mt-3 whitespace-pre-line rounded-lg bg-neutral-50 p-3 text-sm text-neutral-700 dark:bg-neutral-900/40 dark:text-neutral-300">
            {{ draft.content }}
          </p>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
              v-if="draftStatus === 'manual_review'"
              class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
              :disabled="anyDraftActionPending" @click="doApprove"
            >{{ approve.isPending.value ? 'Approving…' : 'Approve & schedule' }}</button>
            <button
              v-if="draftStatus === 'awaiting_publish'"
              class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
              :disabled="anyDraftActionPending" @click="doPublishNow"
            >{{ publishNow.isPending.value ? 'Publishing…' : 'Publish now' }}</button>
            <a
              v-if="draftStatus === 'published' && draft.linkedin_post_url"
              :href="draft.linkedin_post_url" target="_blank" rel="noopener"
              class="rounded-lg bg-[#0077B5] px-3 py-1.5 text-sm font-medium text-white transition hover:brightness-110 motion-reduce:transition-none"
            >Open on LinkedIn ↗</a>
            <button
              class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-50 active:scale-[0.98] disabled:opacity-60 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700 motion-reduce:transition-none"
              :disabled="anyDraftActionPending" @click="doRegenImages"
            >{{ regenImages.isPending.value ? 'Regenerating…' : 'Regenerate images' }}</button>
            <button
              v-if="draftStatus && !['published', 'cancelled'].includes(draftStatus)"
              class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm text-red-600 transition-colors hover:bg-red-50 active:scale-[0.98] disabled:opacity-60 dark:border-red-500/30 dark:bg-neutral-800 dark:text-red-300 dark:hover:bg-red-900/20 motion-reduce:transition-none"
              :disabled="anyDraftActionPending" @click="doCancel"
            >{{ cancel.isPending.value ? 'Cancelling…' : 'Cancel' }}</button>
            <router-link
              :to="{ name: 'admin-sosmed-draft-detail', params: { id: linkedinPostId } }"
              class="rounded-lg px-3 py-1.5 text-sm font-medium text-amber-600 transition-colors hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 motion-reduce:transition-none"
            >Open full editor →</router-link>
          </div>
        </template>
      </div>

      <!-- Intelligence: source extraction + (blog rewrite | pipeline) -->
      <div class="grid gap-5 lg:grid-cols-2">
        <!-- Source intelligence -->
        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
          <h2 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Source intelligence</h2>
          <a :href="job.source_url" target="_blank" rel="noopener" class="block truncate text-sm text-amber-600 hover:underline dark:text-amber-400">
            {{ job.source_url }}
          </a>
          <p v-if="job.angle" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Angle: {{ job.angle }}</p>

          <p v-if="job.extracted?.caption" class="mt-4 text-sm text-neutral-600 dark:text-neutral-300">
            <span class="font-medium text-neutral-500 dark:text-neutral-400">Original caption:</span> {{ job.extracted.caption }}
          </p>

          <div v-if="claims.length" class="mt-4">
            <p class="mb-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">Extracted claims ({{ claims.length }})</p>
            <ul class="space-y-1.5 text-sm text-neutral-700 dark:text-neutral-300">
              <li v-for="(c, i) in claims" :key="i" class="flex gap-2">
                <span class="text-neutral-400 dark:text-neutral-600">{{ i + 1 }}.</span><span>{{ claimText(c) }}</span>
              </li>
            </ul>
          </div>

          <div v-if="verdicts.length" class="mt-4">
            <p class="mb-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">Fact-check · {{ correctedCount }} corrected</p>
            <ul class="space-y-3 text-sm">
              <li v-for="(v, i) in verdicts" :key="i" class="border-l-2 border-neutral-200 pl-3 dark:border-neutral-700">
                <p class="text-neutral-700 dark:text-neutral-300">{{ v.claim }}</p>
                <p :class="verdictTone(v.status)" class="text-xs font-medium uppercase tracking-wide">{{ v.status }}</p>
                <p v-if="v.corrected && v.corrected !== v.claim" class="mt-0.5 text-neutral-500 dark:text-neutral-400">→ {{ v.corrected }}</p>
                <div v-if="Array.isArray(v.sources) && v.sources.length" class="mt-1 flex flex-wrap gap-2">
                  <a v-for="(s, si) in v.sources" :key="si" :href="s" target="_blank" rel="noopener" class="text-xs text-amber-600 hover:underline dark:text-amber-400">
                    source {{ si + 1 }}
                  </a>
                </div>
              </li>
            </ul>
          </div>
        </section>

        <!-- Right: blog rewrite preview OR pipeline (generated handled above) -->
        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
          <template v-if="paneMode === 'blog'">
            <h2 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Generated · Blog draft</h2>
            <h3 v-if="job.rewritten?.title" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ job.rewritten.title }}</h3>
            <p v-if="job.rewritten?.excerpt" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ job.rewritten.excerpt }}</p>
            <div
              v-if="job.rewritten?.body"
              class="prose prose-sm mt-3 max-h-80 max-w-none overflow-y-auto rounded-lg bg-neutral-50 p-3 text-neutral-700 dark:prose-invert dark:bg-neutral-900/40 dark:text-neutral-300"
              v-html="job.rewritten.body"
            ></div>
            <router-link
              v-if="job.content_idea_id"
              :to="{ name: 'admin-content-engine' }"
              class="mt-4 inline-block text-sm font-medium text-amber-600 hover:underline dark:text-amber-400"
            >Open in Content Engine (idea #{{ job.content_idea_id }}) →</router-link>
          </template>
          <template v-else>
            <h2 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Pipeline status</h2>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
              Current step:
              <span class="font-medium text-blue-600 dark:text-blue-400">{{ statusLabel(job.status) }}</span>
            </p>
            <ol class="mt-4 space-y-2 text-xs">
              <li v-for="(e, i) in (job.pipeline_state_log || [])" :key="i" class="flex flex-wrap items-center gap-1.5">
                <span class="text-neutral-400 dark:text-neutral-500">{{ relativeTime(e.timestamp) }}</span>
                <span class="text-neutral-400 dark:text-neutral-500">{{ e.from }} →</span>
                <span :class="e.to === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-neutral-700 dark:text-neutral-200'">{{ e.to }}</span>
                <span class="text-neutral-400 dark:text-neutral-600">· {{ e.reason }}</span>
              </li>
            </ol>
          </template>
        </section>
      </div>

      <!-- Pipeline timeline (full) -->
      <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <h2 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Pipeline timeline</h2>
        <ol class="space-y-2 text-xs">
          <li v-for="(e, i) in (job.pipeline_state_log || [])" :key="i" class="flex flex-wrap items-center gap-1.5">
            <span class="text-neutral-400 dark:text-neutral-500">{{ relativeTime(e.timestamp) }}</span>
            <span class="text-neutral-400 dark:text-neutral-500">{{ e.from }} →</span>
            <span :class="e.to === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-neutral-700 dark:text-neutral-200'">{{ e.to }}</span>
            <span class="text-neutral-400 dark:text-neutral-600">· {{ e.reason }}</span>
          </li>
        </ol>
      </div>
    </div>
  </div>
</template>
