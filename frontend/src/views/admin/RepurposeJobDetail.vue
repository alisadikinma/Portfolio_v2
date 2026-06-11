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
 * 2-pane Source ↔ Generated workspace for an IG-repurpose job.
 *   LEFT  (Source · Original IG): captured slides are PRIVATE → blob-fetched via
 *         fetchSlideObjectUrl; original caption + extracted claims + fact-check.
 *   RIGHT (Generated · Your version): per rightPaneMode —
 *         'generated' → reuse the linked LinkedInPost draft (PUBLIC
 *                       carousel_slides[].image_url + caption) + the SAME
 *                       approve/cancel/regenerate/publish mutations the draft
 *                       detail uses (no duplicated publish logic).
 *         'in_progress' → pipeline timeline + current step.
 *         'blog' → rewrite preview + Content Engine handoff.
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

const verdicts = computed(() => job.value?.research?.verdicts || [])
const claims = computed(() => job.value?.extracted?.claims || [])
const correctedCount = computed(() => job.value?.research?.corrected_count ?? 0)
const isFailed = computed(() => job.value?.status === 'failed')
const headerTitle = computed(() =>
  job.value?.title || job.value?.rewritten?.title || `Repurpose #${job.value?.id ?? ''}`,
)

function claimText(c) { return typeof c === 'string' ? c : (c?.claim || JSON.stringify(c)) }
function verdictTone(status) {
  if (status === 'wrong' || status === 'outdated') return 'text-amber-300'
  if (status === 'correct') return 'text-emerald-300'
  return 'text-neutral-400'
}
async function doRetry() { await retry.mutateAsync(id.value); refetch() }
function goBack() { router.push({ name: 'admin-social-studio' }) }
</script>

<template>
  <div class="px-4 py-6 sm:px-6 lg:px-8">
    <button class="mb-4 text-sm text-neutral-400 transition hover:text-neutral-200 motion-reduce:transition-none" @click="goBack">
      ← Back to Social Studio
    </button>

    <div v-if="isLoading" class="py-12 text-center text-neutral-500">Loading…</div>
    <div v-else-if="!job" class="py-12 text-center text-neutral-500">Job not found.</div>

    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="rounded-xl bg-neutral-900/50 p-5 ring-1 ring-neutral-800">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold text-neutral-100">{{ headerTitle }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-neutral-500">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium ring-1" :class="statusTone(job.status)">
                {{ statusLabel(job.status) }}
              </span>
              <span class="text-neutral-400">{{ modeLabel(job.mode) }}</span>
              <span>#{{ job.id }} · {{ relativeTime(job.updated_at) }}</span>
            </div>
          </div>
          <button
            v-if="isFailed"
            class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-cyan-500 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
            :disabled="retry.isPending.value"
            @click="doRetry"
          >
            {{ retry.isPending.value ? 'Retrying…' : `↻ Retry ${inferFailedStep(job.pipeline_state_log) || 'capture'} step` }}
          </button>
        </div>
        <p v-if="job.last_error" class="mt-3 rounded-lg bg-red-500/10 p-3 text-sm text-red-300 ring-1 ring-red-500/20">
          {{ job.last_error }}
        </p>
      </div>

      <!-- 2-pane workspace -->
      <div class="grid gap-5 lg:grid-cols-2">
        <!-- LEFT · Source -->
        <section class="rounded-xl bg-neutral-900/50 p-5 ring-1 ring-neutral-800">
          <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-neutral-500">Source · Original IG</h2>
          <a :href="job.source_url" target="_blank" rel="noopener" class="block truncate text-sm text-cyan-400 hover:underline">
            {{ job.source_url }}
          </a>
          <p v-if="job.angle" class="mt-1 text-sm text-neutral-400">Angle: {{ job.angle }}</p>

          <!-- captured slides (private blobs) -->
          <div v-if="job.slide_count" class="mt-4">
            <p class="mb-2 text-xs text-neutral-500">Captured slides ({{ job.slide_count }})</p>
            <div class="flex gap-3 overflow-x-auto pb-2">
              <div v-for="(url, i) in slideUrls" :key="i" class="h-40 w-32 flex-shrink-0 overflow-hidden rounded-lg bg-neutral-800 ring-1 ring-neutral-700">
                <img v-if="url" :src="url" :alt="`slide ${i + 1}`" class="h-full w-full object-cover" />
                <div v-else class="flex h-full items-center justify-center text-xs text-neutral-500">slide {{ i + 1 }}</div>
              </div>
            </div>
          </div>

          <p v-if="job.extracted?.caption" class="mt-4 text-xs text-neutral-400">
            <span class="font-medium text-neutral-500">Original caption:</span> {{ job.extracted.caption }}
          </p>

          <!-- claims -->
          <div v-if="claims.length" class="mt-4">
            <p class="mb-2 text-xs text-neutral-500">Extracted claims ({{ claims.length }})</p>
            <ul class="space-y-1.5 text-sm text-neutral-300">
              <li v-for="(c, i) in claims" :key="i" class="flex gap-2">
                <span class="text-neutral-600">{{ i + 1 }}.</span><span>{{ claimText(c) }}</span>
              </li>
            </ul>
          </div>

          <!-- fact-check -->
          <div v-if="verdicts.length" class="mt-4">
            <p class="mb-2 text-xs text-neutral-500">Fact-check · {{ correctedCount }} corrected</p>
            <ul class="space-y-3 text-sm">
              <li v-for="(v, i) in verdicts" :key="i" class="border-l-2 border-neutral-700 pl-3">
                <p class="text-neutral-300">{{ v.claim }}</p>
                <p :class="verdictTone(v.status)" class="text-xs uppercase tracking-wide">{{ v.status }}</p>
                <p v-if="v.corrected && v.corrected !== v.claim" class="mt-0.5 text-neutral-400">→ {{ v.corrected }}</p>
                <div v-if="Array.isArray(v.sources) && v.sources.length" class="mt-1 flex flex-wrap gap-2">
                  <a v-for="(s, si) in v.sources" :key="si" :href="s" target="_blank" rel="noopener" class="text-xs text-cyan-400 hover:underline">
                    source {{ si + 1 }}
                  </a>
                </div>
              </li>
            </ul>
          </div>
        </section>

        <!-- RIGHT · Generated -->
        <section class="rounded-xl bg-neutral-900/50 p-5 ring-1 ring-neutral-800">
          <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-neutral-500">Generated · Your version</h2>

          <!-- generated: linked draft slides + caption + actions -->
          <template v-if="paneMode === 'generated' && draft">
            <div class="mb-3 flex items-center gap-2">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1" :class="statusTone(draftStatus)">
                {{ statusLabel(draftStatus) }}
              </span>
              <span class="text-xs text-neutral-500">LinkedIn draft #{{ linkedinPostId }}</span>
            </div>

            <div v-if="generatedSlides.length" class="flex gap-3 overflow-x-auto pb-2">
              <div v-for="(s, i) in generatedSlides" :key="i" class="h-40 w-32 flex-shrink-0 overflow-hidden rounded-lg bg-neutral-800 ring-1 ring-neutral-700">
                <img v-if="s.image_status === 'done' && s.image_url" :src="s.image_url" :alt="`generated slide ${i + 1}`" class="h-full w-full object-cover" />
                <div v-else class="flex h-full items-center justify-center px-1 text-center text-[10px] text-neutral-500">
                  {{ s.image_status === 'failed' ? 'failed' : 'rendering…' }}
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-neutral-500">Slides not rendered yet.</p>

            <p v-if="draft.content" class="mt-3 whitespace-pre-line rounded-lg bg-neutral-950/40 p-3 text-sm text-neutral-300">
              {{ draft.content }}
            </p>

            <!-- actions — reuse the draft mutations on linkedin_post_id -->
            <div class="mt-4 flex flex-wrap gap-2">
              <button
                v-if="draftStatus === 'manual_review'"
                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-500 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
                :disabled="anyDraftActionPending" @click="doApprove"
              >{{ approve.isPending.value ? 'Approving…' : 'Approve & schedule' }}</button>
              <button
                v-if="draftStatus === 'awaiting_publish'"
                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-500 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
                :disabled="anyDraftActionPending" @click="doPublishNow"
              >{{ publishNow.isPending.value ? 'Publishing…' : 'Publish now' }}</button>
              <a
                v-if="draftStatus === 'published' && draft.linkedin_post_url"
                :href="draft.linkedin_post_url" target="_blank" rel="noopener"
                class="rounded-lg bg-[#0077B5] px-3 py-1.5 text-sm font-medium text-white transition hover:brightness-110 motion-reduce:transition-none"
              >Open on LinkedIn ↗</a>
              <button
                class="rounded-lg bg-neutral-800 px-3 py-1.5 text-sm text-neutral-200 ring-1 ring-neutral-700 transition hover:bg-neutral-700 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
                :disabled="anyDraftActionPending" @click="doRegenImages"
              >{{ regenImages.isPending.value ? 'Regenerating…' : 'Regenerate images' }}</button>
              <button
                v-if="draftStatus && !['published', 'cancelled'].includes(draftStatus)"
                class="rounded-lg bg-neutral-800 px-3 py-1.5 text-sm text-red-300 ring-1 ring-red-500/30 transition hover:bg-red-500/10 active:scale-[0.98] disabled:opacity-60 motion-reduce:transition-none"
                :disabled="anyDraftActionPending" @click="doCancel"
              >{{ cancel.isPending.value ? 'Cancelling…' : 'Cancel' }}</button>
              <router-link
                :to="{ name: 'admin-sosmed-draft-detail', params: { id: linkedinPostId } }"
                class="rounded-lg px-3 py-1.5 text-sm text-cyan-400 ring-1 ring-neutral-800 transition hover:text-cyan-300 motion-reduce:transition-none"
              >Open full editor →</router-link>
            </div>
          </template>

          <!-- in_progress: pipeline still running, no draft yet -->
          <template v-else-if="paneMode === 'in_progress'">
            <p class="text-sm text-neutral-400">
              Pipeline running — current step:
              <span class="font-medium text-cyan-300">{{ statusLabel(job.status) }}</span>
            </p>
            <ol class="mt-4 space-y-2 text-xs">
              <li v-for="(e, i) in (job.pipeline_state_log || [])" :key="i" class="flex items-center gap-2">
                <span class="text-neutral-500">{{ relativeTime(e.timestamp) }}</span>
                <span class="text-neutral-400">{{ e.from }} →</span>
                <span :class="e.to === 'failed' ? 'text-red-300' : 'text-neutral-200'">{{ e.to }}</span>
                <span class="text-neutral-600">· {{ e.reason }}</span>
              </li>
            </ol>
          </template>

          <!-- blog: produces a ContentIdea, not a draft -->
          <template v-else>
            <h3 v-if="job.rewritten?.title" class="text-lg font-semibold text-neutral-100">{{ job.rewritten.title }}</h3>
            <p v-if="job.rewritten?.excerpt" class="mt-1 text-sm text-neutral-400">{{ job.rewritten.excerpt }}</p>
            <!-- Admin-only preview of our own pipeline output. -->
            <div
              v-if="job.rewritten?.body"
              class="prose prose-invert mt-3 max-h-72 overflow-y-auto rounded-lg bg-neutral-950/40 p-3 text-sm text-neutral-300"
              v-html="job.rewritten.body"
            ></div>
            <router-link
              v-if="job.content_idea_id"
              :to="{ name: 'admin-content-engine' }"
              class="mt-4 inline-block text-sm text-cyan-400 hover:underline"
            >Open in Content Engine (idea #{{ job.content_idea_id }}) →</router-link>
          </template>
        </section>
      </div>

      <!-- Pipeline timeline (full) -->
      <div class="rounded-xl bg-neutral-900/50 p-5 ring-1 ring-neutral-800">
        <h2 class="mb-3 text-sm font-semibold text-neutral-200">Pipeline timeline</h2>
        <ol class="space-y-2 text-xs">
          <li v-for="(e, i) in (job.pipeline_state_log || [])" :key="i" class="flex items-center gap-2">
            <span class="text-neutral-500">{{ relativeTime(e.timestamp) }}</span>
            <span class="text-neutral-400">{{ e.from }} →</span>
            <span :class="e.to === 'failed' ? 'text-red-300' : 'text-neutral-200'">{{ e.to }}</span>
            <span class="text-neutral-600">· {{ e.reason }}</span>
          </li>
        </ol>
      </div>
    </div>
  </div>
</template>
