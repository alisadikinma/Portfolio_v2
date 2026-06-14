<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useRepurposeJob,
  useRetryRepurposeJob,
  useRefetchRepurposeSource,
  useRegenerateAllRepurposeSlides,
  useRegenerateRepurposeSlide,
  useReskinRepurposeSlide,
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
const refetchSource = useRefetchRepurposeSource()

// --- linked LinkedIn draft (generated side, read-only here) ----------------
const linkedinPostId = computed(() => job.value?.linkedin_post_id || null)
const { draft } = useLinkedInDraft(linkedinPostId) // enabled only when id present

const paneMode = computed(() => rightPaneMode(job.value))
const isBlog = computed(() => paneMode.value === 'blog')

// Once a carousel job is a finished draft, the work happens in the draft
// editor — skip this review page and jump straight there. replace() so the
// repurpose page doesn't linger in history (Back returns to Social Studio).
const willRedirectToDraft = computed(() =>
  job.value?.status === 'drafted' &&
  paneMode.value === 'generated' &&
  !!job.value?.linkedin_post_id,
)
watch(
  willRedirectToDraft,
  (redirect) => {
    if (redirect) {
      router.replace({ name: 'admin-sosmed-draft-detail', params: { id: job.value.linkedin_post_id } })
    }
  },
  { immediate: true },
)
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

// --- re-fetch source on demand (reaper clears it ~7d after publish) --------
const refetching = ref(false)
let pollTimer = null
function clearPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null } }
async function doRefetchSource() {
  if (refetching.value) return
  refetching.value = true
  try { await refetchSource.mutateAsync(id.value) } catch { /* surfaced as no-image */ }
  clearPoll()
  let tries = 0
  pollTimer = setInterval(async () => {
    tries += 1
    await refetch()
    if ((job.value?.slide_count || 0) > 0 || tries >= 20) {
      clearPoll()
      refetching.value = false
    }
  }, 6000)
}
onBeforeUnmount(() => { revokeSlides(); clearPoll() })

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

// --- video_rebrand: composited 4:5 MP4s for manual download ------------------
const isVideoRebrand = computed(() => job.value?.mode === 'video_rebrand')
const videoSlides = computed(() => (Array.isArray(job.value?.video_slides) ? job.value.video_slides : []))
const readyVideoSlides = computed(() => videoSlides.value.filter(s => s.composited_status === 'done' && s.composited_url))
// Still working through face-gen → Veo → composite — the composable auto-polls.
const videoGenerating = computed(() =>
  isVideoRebrand.value &&
  ['generating_assets', 'assets_ready', 'compositing', 'composed'].includes(job.value?.status),
)
function roleLabel(role) {
  return role === 'hook' ? 'Hook' : role === 'cta' ? 'CTA' : 'Tool'
}
function videoSlideState(s) {
  if (s.composited_status === 'done' && s.composited_url) return null
  if (s.composited_status === 'failed' || s.keyframe_status === 'failed' || s.veo_status === 'failed') return 'failed'
  return 'rendering…'
}
function slideFilename(s) {
  const idx = String(s.slide_index).padStart(2, '0')
  return `alisadikinma-rebrand-${job.value?.id ?? 'x'}-slide-${idx}-${s.role}.mp4`
}
function downloadSlide(s) {
  if (!s.composited_url) return
  const a = document.createElement('a')
  a.href = s.composited_url
  a.download = slideFilename(s)
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
}
function downloadAll() {
  // Stagger the synthetic clicks — browsers drop simultaneous download bursts.
  readyVideoSlides.value.forEach((s, i) => setTimeout(() => downloadSlide(s), i * 350))
}

// --- regenerate (per-slide + batch) -----------------------------------------
const regenAll = useRegenerateAllRepurposeSlides()
const regenSlide = useRegenerateRepurposeSlide()
const reskinSlide = useReskinRepurposeSlide()
const regeneratingIndex = ref(null) // slide_index currently re-dispatching (single)
const reskinningIndex = ref(null)    // slide_index currently re-skinning (bookend)

async function doRegenerateAll() {
  if (!window.confirm('Regenerate ALL slides? The hook & CTA are re-rendered with Veo (uses video credits); tool slides re-skin for free. This replaces the current carousel.')) return
  await regenAll.mutateAsync(id.value)
  refetch()
}

async function doRegenerateSlide(s) {
  const isBookend = s.role === 'hook' || s.role === 'cta'
  const msg = isBookend
    ? `Re-render the ${roleLabel(s.role)} slide? This runs Veo video generation (uses credits).`
    : `Re-skin the "${s.header_title || 'tool'}" slide? Free — re-renders the brand chrome only.`
  if (!window.confirm(msg)) return
  regeneratingIndex.value = s.slide_index
  try {
    await regenSlide.mutateAsync({ id: id.value, slideIndex: s.slide_index })
    refetch()
  } finally {
    regeneratingIndex.value = null
  }
}

// Credit-free re-skin of a hook/CTA bookend — re-applies the brand overlay
// (title/logo/CTA ask) onto the existing Veo clip without re-rendering the video.
async function doReskinSlide(s) {
  if (!window.confirm(`Re-skin the ${roleLabel(s.role)} slide? Free — re-applies the title/logo/CTA overlay onto the existing video (no Veo credits, keeps the clip).`)) return
  reskinningIndex.value = s.slide_index
  try {
    await reskinSlide.mutateAsync({ id: id.value, slideIndex: s.slide_index })
    refetch()
  } catch (e) {
    window.alert(e?.response?.data?.error?.message || 'Re-skin failed.')
  } finally {
    reskinningIndex.value = null
  }
}

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
    <div v-else-if="willRedirectToDraft" class="py-12 text-center text-neutral-500 dark:text-neutral-400">Opening draft editor…</div>

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
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50 px-3 py-2 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:ring-amber-500/20">
            <p class="text-xs text-amber-700 dark:text-amber-300">
              {{ refetching
                ? 'Re-fetching the original slides from Instagram… (~1 min)'
                : 'The original Instagram slides were cleared — showing the generated version only.' }}
            </p>
            <button
              class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-2.5 py-1 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-50 active:scale-[0.98] disabled:opacity-60 dark:border-amber-500/40 dark:bg-neutral-800 dark:text-amber-300 dark:hover:bg-amber-900/20 motion-reduce:transition-none"
              :disabled="refetching"
              @click="doRefetchSource"
            >
              <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': refetching }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
              {{ refetching ? 'Re-fetching…' : 'Re-fetch source' }}
            </button>
          </div>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div v-for="(s, i) in generatedSlides" :key="i" class="flex items-center justify-center rounded-lg bg-neutral-100 p-1 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-700">
              <img v-if="!genState(s)" :src="s.image_url" :alt="`generated slide ${i + 1}`" class="max-h-52 w-full rounded object-contain" />
              <span v-else class="flex aspect-[4/5] w-full items-center justify-center px-1 text-center text-xs"
                    :class="genState(s) === 'failed' ? 'text-red-500 dark:text-red-400' : 'text-neutral-400 dark:text-neutral-600'">{{ genState(s) }}</span>
            </div>
          </div>
        </template>
      </div>

      <!-- video_rebrand: branded 4:5 MP4 carousel for manual download -->
      <section
        v-if="isVideoRebrand"
        class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800"
      >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
            Branded video carousel
            <span class="ml-1 font-normal text-neutral-400 dark:text-neutral-500">· {{ readyVideoSlides.length }}/{{ videoSlides.length }} ready</span>
          </h2>
          <div class="flex flex-wrap items-center gap-2">
            <button
              :disabled="regenAll.isPending.value"
              @click="doRegenerateAll"
              title="Re-render every slide (hook & CTA use Veo credits; tool slides re-skin free)"
              class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/40"
            >
              <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': regenAll.isPending.value }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
              {{ regenAll.isPending.value ? 'Queuing…' : 'Regenerate all' }}
            </button>
            <button
              v-if="readyVideoSlides.length"
              @click="downloadAll"
              class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-amber-700"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" /></svg>
              Download all ({{ readyVideoSlides.length }})
            </button>
          </div>
        </div>

        <p v-if="videoGenerating" class="mb-4 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
          <svg class="h-3.5 w-3.5 animate-spin text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
          Generating hook + CTA clips and compositing slides… this view refreshes automatically.
        </p>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <div
            v-for="s in videoSlides"
            :key="s.id"
            class="overflow-hidden rounded-lg ring-1 ring-neutral-200 dark:ring-neutral-700"
          >
            <div class="relative flex aspect-[4/5] items-center justify-center bg-neutral-100 dark:bg-neutral-900">
              <video
                v-if="!videoSlideState(s)"
                :src="s.composited_url"
                class="h-full w-full object-contain"
                controls
                playsinline
                muted
                loop
                preload="metadata"
              ></video>
              <span
                v-else
                class="px-2 text-center text-xs"
                :class="videoSlideState(s) === 'failed' ? 'text-red-500 dark:text-red-400' : 'text-neutral-400 dark:text-neutral-600'"
              >{{ videoSlideState(s) }}</span>
              <span class="absolute left-1.5 top-1.5 rounded bg-black/55 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-white">
                {{ s.slide_index }} · {{ roleLabel(s.role) }}
              </span>
            </div>
            <div class="flex divide-x divide-neutral-200 dark:divide-neutral-700">
              <button
                v-if="!videoSlideState(s)"
                @click="downloadSlide(s)"
                class="flex flex-1 items-center justify-center gap-1 bg-neutral-50 py-1.5 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:bg-neutral-900/60 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-neutral-100"
              >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" /></svg>
                Download
              </button>
              <button
                :disabled="regeneratingIndex === s.slide_index"
                @click="doRegenerateSlide(s)"
                :title="s.role === 'tool' ? 'Re-skin this slide (free, chrome only)' : 'Re-render this slide with Veo (uses credits)'"
                class="flex flex-1 items-center justify-center gap-1 py-1.5 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                :class="s.role === 'tool'
                  ? 'bg-neutral-50 text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:bg-neutral-900/60 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-neutral-100'
                  : 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/40'"
              >
                <svg class="h-3 w-3" :class="{ 'animate-spin': regeneratingIndex === s.slide_index }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
                {{ regeneratingIndex === s.slide_index ? 'Queuing…' : (s.role === 'tool' ? 'Re-skin' : 'Re-render') }}
              </button>
              <!-- Bookend-only credit-free re-skin: re-apply the title/logo/CTA overlay
                   onto the existing Veo clip without a Veo re-render. -->
              <button
                v-if="s.role !== 'tool'"
                :disabled="reskinningIndex === s.slide_index || regeneratingIndex === s.slide_index"
                @click="doReskinSlide(s)"
                title="Re-skin this slide — re-apply the title/logo/CTA overlay onto the existing video (free, no Veo credits)"
                class="flex flex-1 items-center justify-center gap-1 bg-emerald-50 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/40"
              >
                <svg class="h-3 w-3" :class="{ 'animate-spin': reskinningIndex === s.slide_index }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
                {{ reskinningIndex === s.slide_index ? 'Queuing…' : 'Re-skin' }}
              </button>
            </div>
          </div>
        </div>

        <p class="mt-4 text-xs text-neutral-400 dark:text-neutral-500">
          Download all slides, then post them as a video carousel in the Instagram app (manual publish — v1).
        </p>
      </section>

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
