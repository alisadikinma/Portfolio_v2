<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useRepurposeJob, useRetryRepurposeJob, fetchSlideObjectUrl } from '@/composables/useRepurposeJobs'
import { statusTone, statusLabel, modeLabel, relativeTime, inferFailedStep } from './repurposeHelpers.js'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))

const { job, isLoading, refetch } = useRepurposeJob(id)
const retry = useRetryRepurposeJob()

const slideUrls = ref([])
let loadedForCount = -1

// Lazy-load authed slide thumbnails once slide_count is known.
watch(
  () => job.value?.slide_count,
  async (count) => {
    if (!count || count === loadedForCount) return
    loadedForCount = count
    revokeSlides()
    const urls = []
    for (let i = 0; i < count; i++) {
      try {
        urls.push(await fetchSlideObjectUrl(id.value, i))
      } catch {
        urls.push(null)
      }
    }
    slideUrls.value = urls
  },
)

function revokeSlides() {
  slideUrls.value.forEach(u => u && URL.revokeObjectURL(u))
  slideUrls.value = []
}
onBeforeUnmount(revokeSlides)

const verdicts = computed(() => job.value?.research?.verdicts || [])
const claims = computed(() => job.value?.extracted?.claims || [])
const correctedCount = computed(() => job.value?.research?.corrected_count ?? 0)
const isFailed = computed(() => job.value?.status === 'failed')

function claimText(c) {
  return typeof c === 'string' ? c : (c?.claim || JSON.stringify(c))
}
async function doRetry() {
  await retry.mutateAsync(id.value)
  refetch()
}
function verdictTone(status) {
  if (status === 'wrong' || status === 'outdated') return 'text-amber-300'
  if (status === 'correct') return 'text-emerald-300'
  return 'text-slate-400'
}
</script>

<template>
  <div class="px-4 py-6 sm:px-6 lg:px-8">
    <button
      class="mb-4 text-sm text-slate-400 transition hover:text-slate-200"
      @click="router.push({ name: 'admin-repurpose' })"
    >
      ← Back to Repurpose
    </button>

    <div v-if="isLoading" class="py-12 text-center text-slate-500">Loading…</div>

    <div v-else-if="!job" class="py-12 text-center text-slate-500">Job not found.</div>

    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="rounded-xl bg-slate-900/50 p-5 ring-1 ring-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <span
              class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ring-1"
              :class="statusTone(job.status)"
            >
              {{ statusLabel(job.status) }}
            </span>
            <span class="text-slate-300">{{ modeLabel(job.mode) }}</span>
            <span class="text-xs text-slate-500">#{{ job.id }} · {{ relativeTime(job.updated_at) }}</span>
          </div>
          <button
            v-if="isFailed"
            class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-cyan-500 active:scale-[0.98] disabled:opacity-60"
            :disabled="retry.isPending.value"
            @click="doRetry"
          >
            <span v-if="retry.isPending.value">Retrying…</span>
            <span v-else>↻ Retry {{ inferFailedStep(job.pipeline_state_log) || 'capture' }} step</span>
          </button>
        </div>
        <a
          :href="job.source_url" target="_blank" rel="noopener"
          class="mt-3 block truncate text-sm text-cyan-400 hover:underline"
        >{{ job.source_url }}</a>
        <p v-if="job.angle" class="mt-1 text-sm text-slate-400">Angle: {{ job.angle }}</p>
        <p v-if="job.last_error" class="mt-3 rounded-lg bg-red-500/10 p-3 text-sm text-red-300 ring-1 ring-red-500/20">
          {{ job.last_error }}
        </p>
        <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
          <span v-if="job.content_idea_id">Content idea #{{ job.content_idea_id }}</span>
          <router-link
            v-if="job.linkedin_post_id"
            :to="{ name: 'admin-sosmed-draft-detail', params: { id: job.linkedin_post_id } }"
            class="text-cyan-400 hover:underline"
          >LinkedIn draft #{{ job.linkedin_post_id }} →</router-link>
          <span v-if="job.anchor_post_id">Anchor post #{{ job.anchor_post_id }}</span>
        </div>
      </div>

      <!-- Slides -->
      <div v-if="job.slide_count" class="rounded-xl bg-slate-900/50 p-5 ring-1 ring-slate-800">
        <h2 class="mb-3 text-sm font-semibold text-slate-200">Captured slides ({{ job.slide_count }})</h2>
        <div class="flex gap-3 overflow-x-auto pb-2">
          <div
            v-for="(url, i) in slideUrls"
            :key="i"
            class="h-40 w-32 flex-shrink-0 overflow-hidden rounded-lg bg-slate-800 ring-1 ring-slate-700"
          >
            <img v-if="url" :src="url" :alt="`slide ${i + 1}`" class="h-full w-full object-cover" />
            <div v-else class="flex h-full items-center justify-center text-xs text-slate-500">slide {{ i + 1 }}</div>
          </div>
        </div>
      </div>

      <!-- Extracted claims -->
      <div v-if="claims.length" class="rounded-xl bg-slate-900/50 p-5 ring-1 ring-slate-800">
        <h2 class="mb-3 text-sm font-semibold text-slate-200">Extracted claims ({{ claims.length }})</h2>
        <ul class="space-y-1.5 text-sm text-slate-300">
          <li v-for="(c, i) in claims" :key="i" class="flex gap-2">
            <span class="text-slate-600">{{ i + 1 }}.</span><span>{{ claimText(c) }}</span>
          </li>
        </ul>
        <p v-if="job.extracted?.caption" class="mt-3 text-xs text-slate-500">
          Caption: {{ job.extracted.caption }}
        </p>
      </div>

      <!-- Fact-check verdicts -->
      <div v-if="verdicts.length" class="rounded-xl bg-slate-900/50 p-5 ring-1 ring-slate-800">
        <h2 class="mb-3 text-sm font-semibold text-slate-200">
          Fact-check · {{ correctedCount }} corrected
        </h2>
        <ul class="space-y-3 text-sm">
          <li v-for="(v, i) in verdicts" :key="i" class="border-l-2 border-slate-700 pl-3">
            <p class="text-slate-300">{{ v.claim }}</p>
            <p :class="verdictTone(v.status)" class="text-xs uppercase tracking-wide">{{ v.status }}</p>
            <p v-if="v.corrected && v.corrected !== v.claim" class="mt-0.5 text-slate-400">→ {{ v.corrected }}</p>
            <div v-if="Array.isArray(v.sources) && v.sources.length" class="mt-1 flex flex-wrap gap-2">
              <a
                v-for="(s, si) in v.sources" :key="si" :href="s" target="_blank" rel="noopener"
                class="text-xs text-cyan-400 hover:underline"
              >source {{ si + 1 }}</a>
            </div>
          </li>
        </ul>
      </div>

      <!-- Rewrite preview -->
      <div v-if="job.rewritten" class="rounded-xl bg-slate-900/50 p-5 ring-1 ring-slate-800">
        <h2 class="mb-3 text-sm font-semibold text-slate-200">Rewritten draft</h2>
        <h3 class="text-lg font-semibold text-slate-100">{{ job.rewritten.title }}</h3>
        <p v-if="job.rewritten.excerpt" class="mt-1 text-sm text-slate-400">{{ job.rewritten.excerpt }}</p>
        <!-- Admin-only preview of our own pipeline output. -->
        <div
          v-if="job.rewritten.body"
          class="prose prose-invert mt-3 max-h-72 overflow-y-auto rounded-lg bg-slate-950/40 p-3 text-sm text-slate-300"
          v-html="job.rewritten.body"
        ></div>
      </div>

      <!-- Timeline -->
      <div class="rounded-xl bg-slate-900/50 p-5 ring-1 ring-slate-800">
        <h2 class="mb-3 text-sm font-semibold text-slate-200">Pipeline timeline</h2>
        <ol class="space-y-2 text-xs">
          <li v-for="(e, i) in (job.pipeline_state_log || [])" :key="i" class="flex items-center gap-2">
            <span class="text-slate-500">{{ relativeTime(e.timestamp) }}</span>
            <span class="text-slate-400">{{ e.from }} → </span>
            <span :class="e.to === 'failed' ? 'text-red-300' : 'text-slate-200'">{{ e.to }}</span>
            <span class="text-slate-600">· {{ e.reason }}</span>
          </li>
        </ol>
      </div>
    </div>
  </div>
</template>
