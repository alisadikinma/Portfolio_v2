<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useRepurposeJob,
  useRetryRepurposeJob,
  fetchSlideObjectUrl,
} from '@/composables/useRepurposeJobs'
import { useLinkedInDraft } from '@/composables/useLinkedInDrafts'
import {
  statusTone,
  statusLabel,
  modeLabel,
  relativeTime,
  inferFailedStep,
  rightPaneMode,
} from './repurposeHelpers.js'

/**
 * Source ↔ Generated comparison + review surface for an IG-repurpose job.
 *
 * This page is review-only — it shows what the pipeline produced and how it
 * compares to the original. All publish/edit actions live in the draft editor
 * (LinkedInDraftDetail) for carousel jobs, or Content Engine for blog jobs, so
 * the IG flow stays consistent with the blog flow (review here → act there).
 *
 * Hero: aligned slide-by-slide grid (captured IG slide N → generated slide N).
 * Captured slides are PRIVATE → blob-fetched; they're retained until the 7-day
 * repurpose:reap reaper, so an older drafted job may have its source cleared.
 */

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))

const { job, isLoading, refetch } = useRepurposeJob(id)
const retry = useRetryRepurposeJob()

// --- linked LinkedIn draft (generated side, read-only here) ----------------
const linkedinPostId = computed(() => job.value?.linkedin_post_id || null)
const { draft } = useLinkedInDraft(linkedinPostId) // enabled only when id present

const paneMode = computed(() => rightPaneMode(job.value))
const isBlog = computed(() => paneMode.value === 'blog')
const draftStatus = computed(() => draft.value?.status || null)
const generatedSlides = computed(() =>
  Array.isArray(draft.value?.carousel_slides) ? draft.value.carousel_slides : [],
)

// --- private captured slides ------------------------------------------------
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

// --- comparison ------------------------------------------------------------
const hasSourceSlides = computed(() => slideUrls.value.some(Boolean))
const comparisonPairs = computed(() => {
  const n = Math.max(slideUrls.value.length, generatedSlides.value.length)
  return Array.from({ length: n }, (_, i) => ({
    i,
    src: slideUrls.value[i] || null,
    gen: generatedSlides.value[i] || null,
  }))
})
// Paired rows only when we actually have source slides to compare against;
// otherwise show the generated carousel on its own (source cleared by reaper).
const showPaired = computed(() => !isBlog.value && hasSourceSlides.value && comparisonPairs.value.length > 0)
const showGeneratedOnly = computed(() => !isBlog.value && !hasSourceSlides.value && generatedSlides.value.length > 0)
const showComparison = computed(() => showPaired.value || showGeneratedOnly.value)

const verdicts = computed(() => job.value?.research?.verdicts || [])
const claims = computed(() => job.value?.extracted?.claims || [])
const correctedCount = computed(() => job.value?.research?.corrected_count ?? 0)
const isFailed = computed(() => job.value?.status === 'failed')
const headerTitle = computed(() =>
  job.value?.title || job.value?.rewritten?.title || `Repurpose #${job.value?.id ?? ''}`,
)

function genState(s) {
  if (s && s.image_status === 'done' && s.image_url) return null
  if (s && s.image_status === 'failed') return 'failed'
  if (s) return 'rendering…'
  return paneMode.value === 'in_progress' ? 'generating…' : 'not generated'
}
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
          <!-- Single handoff to where the work actually happens -->
          <router-link
            v-if="!isBlog && linkedinPostId"
            :to="{ name: 'admin-sosmed-draft-detail', params: { id: linkedinPostId } }"
            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-600 active:scale-[0.98] motion-reduce:transition-none"
          >Open in draft editor →</router-link>
          <router-link
            v-else-if="isBlog && job.content_idea_id"
            :to="{ name: 'admin-content-engine' }"
            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-600 active:scale-[0.98] motion-reduce:transition-none"
          >Open in Content Engine →</router-link>
          <button
            v-else-if="isFailed"
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
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Slide comparison</h2>
            <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ comparisonPairs.length }} slides</span>
          </div>
          <span v-if="draftStatus" class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium" :class="statusTone(draftStatus)">
            {{ statusLabel(draftStatus) }}
          </span>
        </div>

        <!-- paired rows (source available) -->
        <template v-if="showPaired">
          <div class="mb-2 grid grid-cols-[1fr_auto_1fr] gap-3">
            <span class="text-[11px] font-bold uppercase tracking-widest text-fuchsia-600 dark:text-fuchsia-400">Source · Original IG</span>
            <span class="w-5"></span>
            <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Generated · Your version</span>
          </div>
          <div class="space-y-3">
            <div v-for="pair in comparisonPairs" :key="pair.i" class="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
              <div class="flex items-center justify-center rounded-lg bg-neutral-100 p-1 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-700">
                <img v-if="pair.src" :src="pair.src" :alt="`source slide ${pair.i + 1}`" class="max-h-44 w-auto rounded object-contain" />
                <span v-else class="flex h-44 items-center justify-center text-xs text-neutral-400 dark:text-neutral-600">slide {{ pair.i + 1 }}</span>
              </div>
              <svg class="h-4 w-4 text-neutral-300 dark:text-neutral-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
              <div class="flex items-center justify-center rounded-lg bg-neutral-100 p-1 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-700">
                <img v-if="!genState(pair.gen)" :src="pair.gen.image_url" :alt="`generated slide ${pair.i + 1}`" class="max-h-44 w-auto rounded object-contain" />
                <span v-else class="flex h-44 items-center justify-center px-2 text-center text-xs"
                      :class="genState(pair.gen) === 'failed' ? 'text-red-500 dark:text-red-400' : 'text-neutral-400 dark:text-neutral-600'">{{ genState(pair.gen) }}</span>
              </div>
            </div>
          </div>
        </template>

        <!-- generated-only (source cleared) -->
        <template v-else-if="showGeneratedOnly">
          <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:ring-amber-500/20">
            The original Instagram slides were cleared after drafting — showing the generated version only.
          </p>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div v-for="(s, i) in generatedSlides" :key="i" class="flex items-center justify-center rounded-lg bg-neutral-100 p-1 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-700">
              <img v-if="!genState(s)" :src="s.image_url" :alt="`generated slide ${i + 1}`" class="max-h-52 w-full rounded object-contain" />
              <span v-else class="flex aspect-[4/5] w-full items-center justify-center px-1 text-center text-xs"
                    :class="genState(s) === 'failed' ? 'text-red-500 dark:text-red-400' : 'text-neutral-400 dark:text-neutral-600'">{{ genState(s) }}</span>
            </div>
          </div>
        </template>
      </div>

      <!-- Source intelligence (summary + collapsible detail) + blog rewrite -->
      <div class="grid gap-5" :class="isBlog ? 'lg:grid-cols-2' : ''">
        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
          <h2 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Source intelligence</h2>
          <a :href="job.source_url" target="_blank" rel="noopener" class="block truncate text-sm text-amber-600 hover:underline dark:text-amber-400">
            {{ job.source_url }}
          </a>
          <p v-if="job.angle" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Angle: {{ job.angle }}</p>

          <p v-if="job.extracted?.caption" class="mt-3 line-clamp-3 text-sm text-neutral-600 dark:text-neutral-300">
            <span class="font-medium text-neutral-500 dark:text-neutral-400">Caption:</span> {{ job.extracted.caption }}
          </p>

          <!-- one-line summary; full claims + fact-check fold away -->
          <p v-if="claims.length || verdicts.length" class="mt-3 text-sm text-neutral-700 dark:text-neutral-200">
            <span class="font-medium">{{ claims.length }}</span> claims extracted
            <template v-if="verdicts.length"> · <span class="font-medium text-amber-600 dark:text-amber-400">{{ correctedCount }}</span> corrected by fact-check</template>
          </p>

          <details v-if="claims.length || verdicts.length" class="group mt-2">
            <summary class="cursor-pointer select-none text-xs font-medium text-amber-600 hover:underline dark:text-amber-400">
              <span class="group-open:hidden">Show claims &amp; fact-check ↓</span>
              <span class="hidden group-open:inline">Hide claims &amp; fact-check ↑</span>
            </summary>

            <ul v-if="claims.length" class="mt-3 space-y-1.5 text-sm text-neutral-700 dark:text-neutral-300">
              <li v-for="(c, i) in claims" :key="i" class="flex gap-2">
                <span class="text-neutral-400 dark:text-neutral-600">{{ i + 1 }}.</span><span>{{ claimText(c) }}</span>
              </li>
            </ul>

            <ul v-if="verdicts.length" class="mt-4 space-y-3 text-sm">
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
          </details>
        </section>

        <!-- Blog rewrite preview (blog mode only) -->
        <section v-if="isBlog" class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
          <h2 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Generated · Blog draft</h2>
          <h3 v-if="job.rewritten?.title" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ job.rewritten.title }}</h3>
          <p v-if="job.rewritten?.excerpt" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ job.rewritten.excerpt }}</p>
          <div
            v-if="job.rewritten?.body"
            class="prose prose-sm mt-3 max-h-80 max-w-none overflow-y-auto rounded-lg bg-neutral-50 p-3 text-neutral-700 dark:prose-invert dark:bg-neutral-900/40 dark:text-neutral-300"
            v-html="job.rewritten.body"
          ></div>
        </section>
      </div>

      <!-- Pipeline timeline (collapsed by default) -->
      <details class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <summary class="flex cursor-pointer select-none items-center justify-between text-sm font-semibold text-neutral-900 dark:text-neutral-100">
          Pipeline timeline
          <span class="text-xs font-normal text-neutral-400 dark:text-neutral-500 group-open:hidden">show</span>
          <span class="hidden text-xs font-normal text-neutral-400 dark:text-neutral-500 group-open:inline">hide</span>
        </summary>
        <ol class="mt-3 space-y-2 text-xs">
          <li v-for="(e, i) in (job.pipeline_state_log || [])" :key="i" class="flex flex-wrap items-center gap-1.5">
            <span class="text-neutral-400 dark:text-neutral-500">{{ relativeTime(e.timestamp) }}</span>
            <span class="text-neutral-400 dark:text-neutral-500">{{ e.from }} →</span>
            <span :class="e.to === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-neutral-700 dark:text-neutral-200'">{{ e.to }}</span>
            <span class="text-neutral-400 dark:text-neutral-600">· {{ e.reason }}</span>
          </li>
        </ol>
      </details>
    </div>
  </div>
</template>
