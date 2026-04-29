<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useLinkedInDraft,
  useUpdateLinkedInDraft,
  useApproveLinkedInDraft,
  useCancelLinkedInDraft,
  usePublishLinkedInDraftNow,
  useRegenerateLinkedInDraft,
  useRegenerateAllCarouselImages,
  useRegenerateSlideImage,
  postTitle,
} from '@/composables/useLinkedInDrafts'
import {
  statusMeta,
  effectiveStatusMeta,
  MOOD_CLASSES,
  transitionSummary,
  reasonLabel,
  formatChip,
  relativeTime,
  countdownTo,
  formatDateTime,
  ICON,
} from './linkedinHelpers'

const route = useRoute()
const router = useRouter()

const draftId = computed(() => Number(route.params.id))
const { draft, isLoading, error, refetch } = useLinkedInDraft(draftId)

const updateMutation = useUpdateLinkedInDraft()
const approveMutation = useApproveLinkedInDraft()
const cancelMutation = useCancelLinkedInDraft()
const publishNowMutation = usePublishLinkedInDraftNow()
const regenerateMutation = useRegenerateLinkedInDraft()
const regenerateAllImagesMutation = useRegenerateAllCarouselImages()
const regenerateSlideMutation = useRegenerateSlideImage()

// --- Smart back navigation: read the referring origin (queue vs feed) from
// sessionStorage so "Back" returns to the right list view, with the tab
// the operator was on. Falls back to /admin/linkedin-queue.
const backTarget = ref({ name: 'admin-linkedin-queue', label: 'Queue' })
onMounted(() => {
  const origin = sessionStorage.getItem('linkedin:detail:origin')
  if (origin === 'feed') {
    backTarget.value = { name: 'admin-linkedin-posts', label: 'Posts' }
  } else {
    backTarget.value = { name: 'admin-linkedin-queue', label: 'Queue' }
  }
})

function goBack() {
  router.push({ name: backTarget.value.name })
}

// --- Edit mode -------------------------------------------------------------
const isEditing = ref(false)
const editForm = ref({ content: '', link_comment: '', hashtags: [] })
const newHashtagInput = ref('')
const activeSlideIndex = ref(0)
const updateWarnings = ref([])

watch(draft, (d) => {
  if (d && !isEditing.value) {
    editForm.value = {
      content: d.content || '',
      link_comment: d.link_comment || '',
      hashtags: Array.isArray(d.hashtags) ? [...d.hashtags] : [],
    }
  }
})

function startEdit() {
  if (!draft.value) return
  editForm.value = {
    content: draft.value.content || '',
    link_comment: draft.value.link_comment || '',
    hashtags: Array.isArray(draft.value.hashtags) ? [...draft.value.hashtags] : [],
  }
  isEditing.value = true
  updateWarnings.value = []
}

function cancelEdit() {
  isEditing.value = false
  updateWarnings.value = []
}

async function saveEdit() {
  const result = await updateMutation.mutateAsync({
    id: draftId.value,
    payload: {
      content: editForm.value.content,
      link_comment: editForm.value.link_comment,
      hashtags: editForm.value.hashtags,
    },
  })
  updateWarnings.value = result?.warnings || []
  isEditing.value = false
  refetch()
}

function addHashtag() {
  const raw = newHashtagInput.value.trim()
  if (!raw) return
  const tag = raw.startsWith('#') ? raw : `#${raw}`
  if (editForm.value.hashtags.length >= 5) return
  if (!editForm.value.hashtags.includes(tag)) {
    editForm.value.hashtags.push(tag)
  }
  newHashtagInput.value = ''
}

function removeHashtag(tag) {
  editForm.value.hashtags = editForm.value.hashtags.filter(t => t !== tag)
}

const charCount = computed(() => editForm.value.content.length)
const charCountTone = computed(() => {
  const c = charCount.value
  if (c < 800 || c > 1400) return 'text-red-400'
  if (c < 1100 || c > 1300) return 'text-yellow-400'
  return 'text-emerald-400'
})

const hashtagCountValid = computed(() =>
  editForm.value.hashtags.length >= 3 && editForm.value.hashtags.length <= 5
)

// --- Status mood + sentence + actions -------------------------------------
// effectiveStatusMeta promotes carousel manual_review with un-rendered slides
// to the synthetic "Awaiting render" / "Rendering" / etc. badge so the hero
// panel sentence + chip + rail all reflect what the operator actually needs
// to do, not the FSM-direct status.
const meta = computed(() => effectiveStatusMeta(draft.value))
const mood = computed(() => MOOD_CLASSES[meta.value.mood] || MOOD_CLASSES.pending)
const isInProgress = computed(() =>
  ['pending_generation', 'generating', 'validating'].includes(draft.value?.status)
)
const isTerminalGood = computed(() => draft.value?.status === 'published')
const isTerminalBad = computed(() => draft.value?.status === 'failed')
const showLastError = computed(() =>
  // Only relevant when current status is failed OR manual_review.
  // Hides stale errors lingering after a successful retry.
  Boolean(
    draft.value?.last_error &&
      ['failed', 'manual_review'].includes(draft.value?.status)
  )
)

// --- Live countdown ticker for awaiting_publish so the seconds tick visibly
const tick = ref(0)
let tickerInterval
onMounted(() => {
  tickerInterval = setInterval(() => { tick.value++ }, 1000)
})
onBeforeUnmount(() => {
  if (tickerInterval) clearInterval(tickerInterval)
})
const liveCountdown = computed(() => {
  // Re-read tick.value to force reactivity each second
  void tick.value
  return draft.value?.cancel_window_ends_at
    ? countdownTo(draft.value.cancel_window_ends_at)
    : ''
})

// --- Schedule picker ------------------------------------------------------
// `isScheduling` toggles the inline datetime-local input. `scheduleAt` is
// the datetime-local value (browser-formatted "YYYY-MM-DDTHH:mm" — no
// timezone offset; we send it as-is and let Carbon parse in local server tz).
const isScheduling = ref(false)
const scheduleAt = ref('')

function openScheduler() {
  // Default to current cancel_window_ends_at if already scheduled, else
  // suggest "tomorrow at 9am" — peak engagement hour for B2B LinkedIn.
  const existing = draft.value?.cancel_window_ends_at
  const seed = existing ? new Date(existing) : (() => {
    const d = new Date()
    d.setDate(d.getDate() + 1)
    d.setHours(9, 0, 0, 0)
    return d
  })()
  // Format as YYYY-MM-DDTHH:mm in local time
  const pad = (n) => String(n).padStart(2, '0')
  scheduleAt.value =
    `${seed.getFullYear()}-${pad(seed.getMonth() + 1)}-${pad(seed.getDate())}T${pad(seed.getHours())}:${pad(seed.getMinutes())}`
  isScheduling.value = true
}

function closeScheduler() {
  isScheduling.value = false
  scheduleAt.value = ''
}

async function submitSchedule() {
  if (!scheduleAt.value) return
  // Convert datetime-local (no tz) to ISO with local-tz offset so backend
  // parses to the expected wall-clock time.
  const local = new Date(scheduleAt.value)
  if (Number.isNaN(local.getTime())) {
    alert('Invalid date/time')
    return
  }
  if (local.getTime() <= Date.now()) {
    alert('Schedule time must be in the future')
    return
  }
  try {
    await approveMutation.mutateAsync({
      id: draftId.value,
      publishAt: local.toISOString(),
    })
    closeScheduler()
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Schedule failed')
  }
}

// --- Action handlers -------------------------------------------------------
async function doApprove() {
  await approveMutation.mutateAsync(draftId.value)
  refetch()
}
async function doCancel() {
  if (!confirm('Cancel this draft? You can regenerate later.')) return
  await cancelMutation.mutateAsync(draftId.value)
  refetch()
}
async function doPublishNow() {
  if (!confirm('Publish to LinkedIn now, skipping the cancel window?')) return
  try {
    await publishNowMutation.mutateAsync(draftId.value)
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Publish failed')
  }
}
async function doRegenerate() {
  if (!confirm('Restart from the blog post?\n\nThis draft will be archived and a brand-new one built from scratch (new draft ID, fresh caption + slides). Runtime ~5-7 min.')) return
  const result = await regenerateMutation.mutateAsync(draftId.value)
  const newId = result?.data?.id
  if (newId) router.push({ name: 'admin-linkedin-draft-detail', params: { id: newId } })
}

// --- Carousel slides + image actions --------------------------------------
const carouselSlides = computed(() =>
  Array.isArray(draft.value?.carousel_slides) ? draft.value.carousel_slides : []
)

function slideCopyId(slide) {
  if (!slide) return ''
  return slide.copy_id || slide.copy || ''
}
function slideCopyEn(slide) {
  if (!slide) return ''
  return slide.copy_en || slide.copy || ''
}

function prevSlide() {
  if (activeSlideIndex.value > 0) activeSlideIndex.value--
}
function nextSlide() {
  if (activeSlideIndex.value < carouselSlides.value.length - 1) activeSlideIndex.value++
}

const slideTally = computed(() => {
  const t = { done: 0, generating: 0, pending: 0, failed: 0, total: 0 }
  for (const s of carouselSlides.value) {
    t.total++
    const st = s?.image_status
    if (st === 'done') t.done++
    else if (st === 'generating') t.generating++
    else if (st === 'failed') t.failed++
    else t.pending++
  }
  return t
})

async function regenerateAllImages() {
  if (!draft.value) return
  const count = carouselSlides.value.length
  const msg = `Re-author all ${count} slides via /carousel-gen plugin?\n\n` +
    `This produces FRESH visual concepts (absurdist hooks, surreal metaphors per the visual-hook gate) — not just a re-render of the existing prompts. ` +
    `Existing slide images are discarded.\n\n` +
    `Caption + hashtags + draft ID are preserved.\n\n` +
    `Total runtime: ~5-7 min (2-3 min plugin authoring + 3-4 min image rendering).`
  if (!confirm(msg)) return
  try {
    const res = await regenerateAllImagesMutation.mutateAsync(draftId.value)
    refetch()
    alert(res?.message || `Re-authoring queued for ${count} slides — slides will update live via webhooks as each renders.`)
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Image regeneration failed')
  }
}

async function regenerateSingleSlide(slideIndex) {
  if (!confirm(`Re-render slide ${slideIndex + 1}? It will replace the existing image.`)) return
  try {
    await regenerateSlideMutation.mutateAsync({ id: draftId.value, slideIndex })
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Slide retry failed')
  }
}

// --- Validation surfacing --------------------------------------------------
const hasValidationContent = computed(() => {
  const v = draft.value?.validation_log
  return Boolean(v && (v.failures?.length || v.suggestions?.length))
})

// --- Depth score color
function depthTone(score) {
  if (score === null || score === undefined) return 'text-neutral-500'
  if (score >= 80) return 'text-emerald-400'
  if (score >= 70) return 'text-amber-400'
  return 'text-red-400'
}

// --- Show thumbnail upload caption gate (Q3 / fix from review)
// Caption only fires when the post is actually heading toward publish AND no
// asset URN exists yet. Stays silent during generation/validation/published/etc.
const showThumbnailUploadCaption = computed(() =>
  Boolean(
    draft.value?.format === 'text' &&
      draft.value?.post?.featured_image &&
      !draft.value?.thumbnail_asset_urn &&
      ['manual_review', 'awaiting_publish'].includes(draft.value?.status)
  )
)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Back rail + source breadcrumb -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <button
        @click="goBack"
        class="group inline-flex items-center gap-2 text-sm text-neutral-400 hover:text-amber-400 transition-colors"
      >
        <svg viewBox="0 0 24 24" fill="none" :stroke="'currentColor'" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 transition-transform group-hover:-translate-x-0.5">
          <path :d="ICON.arrowLeft" />
        </svg>
        <span>Back to {{ backTarget.label }}</span>
      </button>
      <div v-if="draft" class="flex items-center gap-2 text-xs text-neutral-500 truncate max-w-md">
        <svg viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-[#0077B5] shrink-0">
          <path :d="ICON.linkedin" />
        </svg>
        <span class="truncate">From: {{ postTitle(draft) }}</span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="space-y-4">
      <div class="h-32 rounded-2xl bg-neutral-900/40 animate-pulse" />
      <div class="grid lg:grid-cols-[1fr_360px] gap-6">
        <div class="h-96 rounded-2xl bg-neutral-900/40 animate-pulse" />
        <div class="space-y-3">
          <div class="h-24 rounded-2xl bg-neutral-900/40 animate-pulse" />
          <div class="h-32 rounded-2xl bg-neutral-900/40 animate-pulse" />
          <div class="h-48 rounded-2xl bg-neutral-900/40 animate-pulse" />
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/5 p-8 text-center">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 text-red-400 mx-auto mb-3">
        <path :d="ICON.alertCircle" />
      </svg>
      <p class="text-red-300 font-medium">Failed to load draft</p>
      <p class="text-xs text-neutral-500 mt-2 font-mono">{{ error.message || 'Unknown error' }}</p>
    </div>

    <!-- Not found -->
    <div v-else-if="!draft" class="rounded-2xl border border-neutral-800 p-8 text-center text-neutral-500">
      Draft not found.
    </div>

    <!-- ====================================================================
         Main layout
         ==================================================================== -->
    <template v-else>
      <!-- ============== STATUS HERO PANEL ============== -->
      <section
        class="relative overflow-hidden rounded-2xl border border-neutral-800/80 bg-neutral-950/40"
        :class="['ring-1', mood.chip.split(' ').filter(c => c.startsWith('ring-')).join(' ')]"
      >
        <!-- Mood gradient rail (left side, ~3px) -->
        <div
          class="absolute left-0 top-0 bottom-0 w-[3px] bg-gradient-to-b"
          :class="mood.rail"
        />

        <div class="relative p-6 sm:p-7 grid gap-5 lg:grid-cols-[1fr_auto] lg:items-start">
          <div class="space-y-3">
            <!-- Chip row -->
            <div class="flex flex-wrap items-center gap-2.5">
              <span
                class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-mono font-medium uppercase tracking-[0.12em]"
                :class="mood.chip"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="mood.dot" />
                {{ meta.label }}
              </span>
              <span class="text-[10px] font-mono uppercase tracking-[0.18em] text-neutral-500">
                {{ formatChip(draft.format) }}
                <template v-if="draft.format === 'carousel'"> · {{ carouselSlides.length }} slides</template>
              </span>
              <span v-if="draft.depth_score !== null && draft.depth_score !== undefined" class="text-[10px] font-mono uppercase tracking-[0.18em] text-neutral-500">
                Depth <span class="font-bold ml-0.5" :class="depthTone(draft.depth_score)">{{ draft.depth_score }}</span>
              </span>
            </div>

            <!-- Operator-facing sentence -->
            <p class="text-base text-neutral-300 leading-relaxed max-w-2xl">
              {{ meta.sentence }}
            </p>

            <!-- Live countdown for awaiting_publish -->
            <div v-if="draft.status === 'awaiting_publish' && draft.cancel_window_ends_at" class="inline-flex items-center gap-2 text-sm">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-amber-400">
                <path :d="ICON.clock" />
              </svg>
              <span class="text-neutral-400">Publishes in</span>
              <span class="font-mono font-bold text-amber-400 text-base">{{ liveCountdown }}</span>
            </div>

            <!-- Last error (only when status terminal-bad or stuck-in-review) -->
            <div
              v-if="showLastError"
              class="mt-1 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/5 px-4 py-3"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-red-400 shrink-0 mt-0.5">
                <path :d="ICON.alertTriangle" />
              </svg>
              <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-[0.14em] text-red-400 font-medium mb-0.5">Last error</p>
                <p class="text-sm text-red-200 font-mono break-words">{{ draft.last_error }}</p>
              </div>
            </div>

            <!-- Update warnings (after edit-save) -->
            <div
              v-if="updateWarnings.length > 0"
              class="mt-1 rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm"
            >
              <p class="text-[11px] uppercase tracking-[0.14em] text-amber-400 font-medium mb-1">Heads up</p>
              <ul class="text-amber-200 space-y-0.5 list-disc list-inside">
                <li v-for="w in updateWarnings" :key="w">{{ w }}</li>
              </ul>
            </div>
          </div>

          <!-- Primary action cluster (top-right on lg+, full-width below on mobile) -->
          <div class="flex flex-wrap gap-2 lg:justify-end lg:min-w-[200px]">
            <!-- Manual review: approve (in cancel window) is primary -->
            <button
              v-if="draft.status === 'manual_review'"
              @click="doApprove"
              :disabled="approveMutation.isPending.value"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-500 text-emerald-950 hover:bg-emerald-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50 disabled:cursor-wait shadow-[0_8px_24px_-12px_rgba(16,185,129,0.5)]"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.check" />
              </svg>
              {{ approveMutation.isPending.value ? 'Approving…' : 'Approve' }}
            </button>

            <!-- Schedule for later (manual_review + awaiting_publish) -->
            <button
              v-if="['manual_review', 'awaiting_publish'].includes(draft.status)"
              @click="openScheduler"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-amber-500/40 text-amber-300 hover:bg-amber-500/10 active:scale-[0.98] text-sm font-medium transition"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.clock" />
              </svg>
              {{ draft.status === 'awaiting_publish' ? 'Reschedule' : 'Schedule for later' }}
            </button>

            <!-- Awaiting publish: publish-now is primary -->
            <button
              v-if="draft.status === 'awaiting_publish'"
              @click="doPublishNow"
              :disabled="publishNowMutation.isPending.value"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500 text-amber-950 hover:bg-amber-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50 disabled:cursor-wait shadow-[0_8px_24px_-12px_rgba(212,168,67,0.5)]"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.send" />
              </svg>
              {{ publishNowMutation.isPending.value ? 'Publishing…' : 'Publish now' }}
            </button>

            <!-- Published: external link is primary -->
            <a
              v-if="isTerminalGood && draft.linkedin_post_url"
              :href="draft.linkedin_post_url"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#0077B5] text-white hover:bg-[#005f8e] active:scale-[0.98] text-sm font-semibold transition"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.externalLink" />
              </svg>
              Open on LinkedIn
            </a>

            <!-- Failed/cancelled: restart-from-blog is primary -->
            <button
              v-if="['failed', 'cancelled'].includes(draft.status)"
              @click="doRegenerate"
              :disabled="regenerateMutation.isPending.value"
              title="Discard this draft and rebuild from the blog post (creates new draft, ~5-7 min)"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-cyan-500 text-cyan-950 hover:bg-cyan-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50 disabled:cursor-wait"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.refresh" />
              </svg>
              {{ regenerateMutation.isPending.value ? 'Queueing…' : 'Restart from blog' }}
            </button>

            <!-- In-progress states: cancel is the only meaningful action -->
            <button
              v-if="isInProgress"
              @click="doCancel"
              :disabled="cancelMutation.isPending.value"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-neutral-700 text-neutral-300 hover:border-red-500/50 hover:text-red-400 active:scale-[0.98] text-sm font-medium transition disabled:opacity-50"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.x" />
              </svg>
              Cancel run
            </button>
          </div>
        </div>

        <!-- Inline scheduler — opens when operator clicks Schedule/Reschedule.
             Native datetime-local input keeps deps zero. The form submits to
             approveMutation with publishAt; backend Carbon parses local-tz ISO. -->
        <div
          v-if="isScheduling"
          class="relative px-6 sm:px-7 py-5 border-t border-amber-500/20 bg-amber-500/[0.04]"
        >
          <div class="flex flex-wrap items-end gap-3 max-w-3xl">
            <div class="flex-1 min-w-[260px]">
              <label class="block text-[11px] font-mono uppercase tracking-[0.14em] text-amber-300 mb-1.5">
                Publish at (your local time)
              </label>
              <input
                v-model="scheduleAt"
                type="datetime-local"
                class="w-full px-3.5 py-2.5 rounded-lg border border-amber-500/30 bg-neutral-900/60 text-neutral-100 focus:outline-none focus:border-amber-500/60 focus:ring-1 focus:ring-amber-500/40 text-sm font-mono"
              >
              <p class="text-[11px] text-neutral-500 mt-1.5">
                Pick any future moment. Pro tip: Tuesday-Thursday, 9-11am local for B2B reach.
              </p>
            </div>
            <div class="flex gap-2">
              <button
                @click="submitSchedule"
                :disabled="approveMutation.isPending.value"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500 text-amber-950 hover:bg-amber-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path :d="ICON.check" />
                </svg>
                {{ approveMutation.isPending.value ? 'Saving…' : (draft.status === 'awaiting_publish' ? 'Reschedule' : 'Approve & schedule') }}
              </button>
              <button
                @click="closeScheduler"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-neutral-700 text-neutral-300 hover:bg-neutral-800 text-sm font-medium transition"
              >
                Discard
              </button>
            </div>
          </div>
        </div>

        <!-- Loading shimmer at the bottom for in-progress states -->
        <div v-if="isInProgress" class="h-[2px] w-full overflow-hidden bg-neutral-800/50">
          <div
            class="h-full w-1/3 bg-gradient-to-r from-transparent via-cyan-400 to-transparent animate-[slide_2s_ease-in-out_infinite]"
          />
        </div>
      </section>

      <!-- ============== Body grid ============== -->
      <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <!-- LEFT: preview / editor / validation -->
        <div class="space-y-5">
          <!-- LinkedIn-style preview (read mode) -->
          <article
            v-if="!isEditing"
            class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 overflow-hidden"
          >
            <!-- TEXT format -->
            <div v-if="draft.format === 'text'" class="px-6 py-5 space-y-4">
              <header class="flex items-start gap-3 pb-4 border-b border-neutral-800/60">
                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-amber-500 to-cyan-500 flex items-center justify-center text-neutral-950 font-bold text-sm">
                  AS
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-neutral-100 text-sm">Ali Sadikin Ma</p>
                  <p class="text-xs text-neutral-500">AI Generalist Expert · now · <span class="font-mono">PUBLIC</span></p>
                </div>
                <svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-[#0077B5] shrink-0">
                  <path :d="ICON.linkedin" />
                </svg>
              </header>

              <!-- 16:9 thumbnail (rendered first, mirroring operator-preferred
                   read order: visual first, then supporting copy, then tags). -->
              <figure
                v-if="draft.post?.featured_image"
                class="-mx-6 border-y border-neutral-800/60 overflow-hidden bg-neutral-900"
              >
                <img
                  :src="draft.post.featured_image"
                  :alt="draft.post.translations?.[0]?.title || 'Blog thumbnail'"
                  class="w-full h-auto block"
                  style="aspect-ratio: 16 / 9; object-fit: cover;"
                  loading="lazy"
                >
                <figcaption
                  v-if="showThumbnailUploadCaption"
                  class="px-6 py-2 text-[10px] font-mono uppercase tracking-[0.14em] text-amber-400/80 bg-amber-500/[0.04] border-t border-amber-500/15"
                >
                  Will upload to LinkedIn on publish
                </figcaption>
              </figure>

              <div class="whitespace-pre-wrap text-neutral-200 leading-relaxed text-[15px]">
                {{ draft.content || '—' }}
              </div>

              <div v-if="Array.isArray(draft.hashtags) && draft.hashtags.length > 0" class="flex flex-wrap gap-x-2 gap-y-1">
                <span
                  v-for="tag in draft.hashtags"
                  :key="tag"
                  class="text-cyan-400 text-sm hover:underline cursor-default"
                >
                  {{ tag }}
                </span>
              </div>

              <div
                v-if="draft.link_comment"
                class="mt-4 p-3 rounded-lg border border-cyan-500/20 bg-cyan-500/5"
              >
                <p class="text-[10px] text-cyan-400 font-mono uppercase tracking-[0.14em] mb-1">First comment</p>
                <p class="text-sm text-neutral-300">{{ draft.link_comment }}</p>
              </div>
            </div>

            <!-- CAROUSEL format -->
            <div v-else-if="draft.format === 'carousel'" class="px-6 py-5 space-y-4">
              <!-- LinkedIn-style author header (mirrors text mockup so operator
                   sees what the post looks like in feed). -->
              <header class="flex items-start gap-3 pb-4 border-b border-neutral-800/60">
                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-amber-500 to-cyan-500 flex items-center justify-center text-neutral-950 font-bold text-sm">
                  AS
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-neutral-100 text-sm">Ali Sadikin Ma</p>
                  <p class="text-xs text-neutral-500">AI Generalist Expert · now · <span class="font-mono">PUBLIC</span></p>
                </div>
                <svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-[#0077B5] shrink-0">
                  <path :d="ICON.linkedin" />
                </svg>
              </header>

              <div v-if="carouselSlides.length === 0" class="text-center py-12">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 text-neutral-600 mx-auto mb-2">
                  <path :d="ICON.image" />
                </svg>
                <p class="text-sm text-neutral-500">No slides authored yet.</p>
              </div>
              <div v-else>
                <!-- Slide nav -->
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                  <p class="text-xs font-mono uppercase tracking-[0.14em] text-neutral-400">
                    Slide <span class="text-neutral-100 font-bold">{{ activeSlideIndex + 1 }}</span> / {{ carouselSlides.length }}
                  </p>
                  <div class="flex gap-1.5">
                    <button
                      @click="regenerateSingleSlide(activeSlideIndex)"
                      :disabled="regenerateSlideMutation.isPending.value"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md bg-amber-500/10 ring-1 ring-amber-500/30 text-amber-300 hover:bg-amber-500/20 disabled:opacity-30 transition"
                      title="Re-render only this slide's image (~30-60s, same prompt)"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path :d="ICON.refresh" />
                      </svg>
                      Re-render image
                    </button>
                    <button
                      @click="prevSlide"
                      :disabled="activeSlideIndex === 0"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-neutral-800 hover:bg-neutral-700 disabled:opacity-30 transition"
                      aria-label="Previous slide"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-neutral-300">
                        <path :d="ICON.chevronLeft" />
                      </svg>
                    </button>
                    <button
                      @click="nextSlide"
                      :disabled="activeSlideIndex === carouselSlides.length - 1"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-neutral-800 hover:bg-neutral-700 disabled:opacity-30 transition"
                      aria-label="Next slide"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-neutral-300">
                        <path :d="ICON.chevronRight" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Slide frame (3:4) -->
                <div
                  class="rounded-xl border border-neutral-800/80 overflow-hidden bg-gradient-to-br from-neutral-950 to-neutral-900 mx-auto relative"
                  style="aspect-ratio: 3 / 4; max-height: 80vh; max-width: min(100%, 60vh);"
                >
                  <!-- Status pill -->
                  <span
                    v-if="carouselSlides[activeSlideIndex]?.image_status"
                    class="absolute top-3 right-3 z-10 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-mono uppercase tracking-[0.14em]"
                    :class="{
                      'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30': carouselSlides[activeSlideIndex].image_status === 'done',
                      'bg-cyan-500/15 text-cyan-300 ring-1 ring-cyan-500/30': carouselSlides[activeSlideIndex].image_status === 'generating',
                      'bg-red-500/15 text-red-300 ring-1 ring-red-500/30': carouselSlides[activeSlideIndex].image_status === 'failed',
                      'bg-neutral-500/15 text-neutral-400 ring-1 ring-neutral-500/30': carouselSlides[activeSlideIndex].image_status === 'pending',
                    }"
                  >
                    <span
                      v-if="carouselSlides[activeSlideIndex].image_status === 'generating'"
                      class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"
                    />
                    {{ {
                      done: 'Ready',
                      generating: 'Rendering',
                      failed: 'Failed',
                      pending: 'Pending',
                    }[carouselSlides[activeSlideIndex].image_status] || 'Pending' }}
                  </span>

                  <img
                    v-if="carouselSlides[activeSlideIndex]?.image_url"
                    :src="carouselSlides[activeSlideIndex].image_url"
                    :alt="`Slide ${activeSlideIndex + 1}`"
                    class="absolute inset-0 w-full h-full object-cover"
                  >

                  <div
                    v-else-if="carouselSlides[activeSlideIndex]?.image_status === 'generating'"
                    class="absolute inset-0 flex flex-col items-center justify-center text-center p-6"
                  >
                    <svg class="animate-spin w-10 h-10 text-cyan-400 mb-3" viewBox="0 0 24 24" fill="none">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                    </svg>
                    <p class="text-cyan-300 text-[10px] uppercase tracking-[0.18em] font-mono mb-3">Rendering with GeminiGen</p>
                    <p class="text-white text-base font-bold uppercase tracking-tight max-w-md mb-1 leading-tight">{{ slideCopyId(carouselSlides[activeSlideIndex]) }}</p>
                    <p class="text-amber-400 text-xs italic max-w-md">{{ slideCopyEn(carouselSlides[activeSlideIndex]) }}</p>
                  </div>

                  <div
                    v-else-if="carouselSlides[activeSlideIndex]?.image_status === 'failed'"
                    class="absolute inset-0 flex flex-col items-center justify-center text-center p-6"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-red-400 mb-2">
                      <path :d="ICON.alertTriangle" />
                    </svg>
                    <p class="text-red-300 text-[10px] uppercase tracking-[0.18em] font-mono mb-2">Render failed</p>
                    <p class="text-red-200 text-xs mb-4 max-w-md font-mono">{{ carouselSlides[activeSlideIndex]?.image_error || 'Unknown error' }}</p>
                    <button
                      @click="regenerateSingleSlide(activeSlideIndex)"
                      :disabled="regenerateSlideMutation.isPending.value"
                      class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-amber-500 text-amber-950 hover:bg-amber-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                        <path :d="ICON.refresh" />
                      </svg>
                      Retry this slide
                    </button>
                  </div>

                  <div
                    v-else
                    class="absolute inset-0 flex flex-col items-center justify-center text-center text-neutral-400 p-6"
                  >
                    <p class="text-white text-2xl font-bold uppercase tracking-tight max-w-md mb-2 leading-tight">{{ slideCopyId(carouselSlides[activeSlideIndex]) }}</p>
                    <p class="text-white/80 text-xs max-w-md">{{ slideCopyEn(carouselSlides[activeSlideIndex]) }}</p>
                  </div>
                </div>

                <!-- Image generation tally -->
                <div v-if="slideTally.total > 0" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] font-mono">
                  <span class="text-emerald-400">
                    <span class="text-neutral-500">Ready</span> {{ slideTally.done }}
                  </span>
                  <span v-if="slideTally.generating > 0" class="text-cyan-400">
                    <span class="text-neutral-500">Rendering</span> {{ slideTally.generating }}
                  </span>
                  <span v-if="slideTally.pending > 0" class="text-neutral-400">
                    <span class="text-neutral-500">Pending</span> {{ slideTally.pending }}
                  </span>
                  <span v-if="slideTally.failed > 0" class="text-red-400">
                    <span class="text-neutral-500">Failed</span> {{ slideTally.failed }}
                  </span>
                  <span class="text-neutral-500 ml-auto">{{ slideTally.total }} total</span>
                </div>

                <!-- Thumbnail strip -->
                <div class="mt-3 flex gap-2 overflow-x-auto pb-2">
                  <button
                    v-for="(slide, i) in carouselSlides"
                    :key="i"
                    @click="activeSlideIndex = i"
                    :class="[
                      'shrink-0 w-12 h-12 rounded-md text-[10px] font-mono flex items-center justify-center transition-all relative overflow-hidden ring-1',
                      i === activeSlideIndex
                        ? 'ring-amber-400 bg-amber-500/15'
                        : 'ring-neutral-800 hover:ring-neutral-600',
                    ]"
                  >
                    <img
                      v-if="slide?.image_url"
                      :src="slide.image_url"
                      :alt="`Slide ${i + 1}`"
                      class="absolute inset-0 w-full h-full object-cover opacity-60"
                    >
                    <span
                      v-if="slide?.image_status"
                      :class="[
                        'absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full',
                        slide.image_status === 'done' && 'bg-emerald-400',
                        slide.image_status === 'generating' && 'bg-cyan-400 animate-pulse',
                        slide.image_status === 'failed' && 'bg-red-400',
                        slide.image_status === 'pending' && 'bg-neutral-500',
                      ]"
                    />
                    <span class="relative z-10 font-bold text-white drop-shadow-md">{{ i + 1 }}</span>
                  </button>
                </div>
              </div>

              <!-- Post body caption — sits between slides and hashtags, mirroring
                   the operator-preferred read order: see the visual first, then
                   the supporting copy, then tags. Empty fallback warns operator
                   that the carousel will publish without a body. -->
              <div v-if="draft.content && draft.content.trim() !== ''" class="whitespace-pre-wrap text-neutral-200 leading-relaxed text-[15px] pt-3 border-t border-neutral-800/60">
                {{ draft.content }}
              </div>
              <div v-else class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-200">
                <strong class="text-amber-300">No caption.</strong> The carousel will publish without a body — typically 50% lower reach. Click "Edit content" to add one.
              </div>

              <div v-if="Array.isArray(draft.hashtags) && draft.hashtags.length > 0" class="flex flex-wrap gap-x-2 gap-y-1">
                <span v-for="tag in draft.hashtags" :key="tag" class="text-cyan-400 text-sm">{{ tag }}</span>
              </div>

              <!-- First-comment bubble (link to original blog post). LinkedIn
                   posts this 30s after the main post via PostLinkedInFirstComment
                   job — avoids the 60% reach penalty from in-body URLs. -->
              <div
                v-if="draft.link_comment"
                class="mt-2 p-3 rounded-lg border border-cyan-500/20 bg-cyan-500/5"
              >
                <p class="text-[10px] text-cyan-400 font-mono uppercase tracking-[0.14em] mb-1">First comment (auto-posted +30s)</p>
                <p class="text-sm text-neutral-300 break-all">{{ draft.link_comment }}</p>
              </div>
            </div>
          </article>

          <!-- ============== EDIT MODE ============== -->
          <article
            v-else
            class="rounded-2xl border border-amber-500/40 bg-neutral-950/40 p-6 space-y-5"
          >
            <header class="flex items-center justify-between flex-wrap gap-2">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-amber-400">Editing draft</p>
              <p class="text-xs text-neutral-500">Saving does not re-validate (validation gate stays as it was)</p>
            </header>

            <div>
              <label class="flex items-center justify-between text-sm font-medium text-neutral-300 mb-1.5">
                <span>Body</span>
                <span class="text-[11px] font-mono" :class="charCountTone">
                  {{ charCount }} / sweet spot 1100-1300
                </span>
              </label>
              <textarea
                v-model="editForm.content"
                rows="14"
                class="w-full px-3.5 py-3 rounded-lg border border-neutral-800 bg-neutral-900/50 text-neutral-100 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30 font-mono text-sm leading-relaxed"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-300 mb-1.5">
                Link comment
                <span class="ml-2 text-[11px] font-normal text-neutral-500">posted as first comment after publish</span>
              </label>
              <input
                v-model="editForm.link_comment"
                type="text"
                placeholder="Full article: https://alisadikinma.com/blog/..."
                class="w-full px-3.5 py-2.5 rounded-lg border border-neutral-800 bg-neutral-900/50 text-neutral-100 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30 text-sm"
              >
            </div>

            <div>
              <label class="flex items-center justify-between text-sm font-medium text-neutral-300 mb-1.5">
                <span>Hashtags</span>
                <span class="text-[11px] font-mono" :class="hashtagCountValid ? 'text-emerald-400' : 'text-red-400'">
                  {{ editForm.hashtags.length }} / 3-5
                </span>
              </label>
              <div class="flex flex-wrap gap-2 mb-2">
                <span
                  v-for="tag in editForm.hashtags"
                  :key="tag"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/15 ring-1 ring-amber-500/30 text-amber-300 text-sm"
                >
                  {{ tag }}
                  <button @click="removeHashtag(tag)" class="hover:text-red-400 leading-none ml-0.5" aria-label="Remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                      <path :d="ICON.x" />
                    </svg>
                  </button>
                </span>
              </div>
              <div class="flex gap-2">
                <input
                  v-model="newHashtagInput"
                  @keydown.enter.prevent="addHashtag"
                  type="text"
                  placeholder="#NewTag (Enter to add)"
                  class="flex-1 px-3.5 py-2 rounded-lg border border-neutral-800 bg-neutral-900/50 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30"
                >
                <button
                  @click="addHashtag"
                  class="px-3.5 py-2 rounded-lg bg-amber-500/15 text-amber-300 hover:bg-amber-500/25 text-sm font-medium ring-1 ring-amber-500/30"
                >
                  Add
                </button>
              </div>
            </div>

            <div class="flex gap-2 pt-4 border-t border-neutral-800">
              <button
                @click="saveEdit"
                :disabled="updateMutation.isPending.value || !hashtagCountValid"
                class="px-4 py-2.5 rounded-lg bg-amber-500 text-amber-950 font-semibold hover:bg-amber-400 active:scale-[0.98] disabled:opacity-50 transition text-sm"
              >
                {{ updateMutation.isPending.value ? 'Saving…' : 'Save changes' }}
              </button>
              <button
                @click="cancelEdit"
                class="px-4 py-2.5 rounded-lg border border-neutral-700 text-neutral-300 hover:bg-neutral-800 text-sm font-medium"
              >
                Discard
              </button>
            </div>
          </article>

          <!-- Validation panel (failures + suggestions) -->
          <article
            v-if="!isEditing && hasValidationContent"
            class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-6"
          >
            <h3 class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-400 mb-4">Validation</h3>
            <div v-if="draft.validation_log.failures?.length" class="space-y-2 mb-5">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-red-400">Failures</p>
              <div
                v-for="(f, i) in draft.validation_log.failures"
                :key="i"
                class="flex gap-3 px-3 py-2.5 rounded-lg bg-red-500/5 ring-1 ring-red-500/25"
              >
                <span class="font-mono text-[11px] text-red-400 whitespace-nowrap pt-0.5">−{{ f.deduction }}pts</span>
                <div class="min-w-0">
                  <p class="text-sm text-neutral-200">{{ f.message }}</p>
                  <p class="text-[11px] text-neutral-500 font-mono mt-0.5">{{ f.rule }}</p>
                </div>
              </div>
            </div>
            <div v-if="draft.validation_log.suggestions?.length">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-amber-400 mb-2">Suggestions</p>
              <ul class="text-sm space-y-1 text-neutral-300 list-disc list-outside ml-5 marker:text-amber-400/60">
                <li v-for="(s, i) in draft.validation_log.suggestions" :key="i">{{ s }}</li>
              </ul>
            </div>
          </article>
        </div>

        <!-- RIGHT: details + secondary actions + timeline -->
        <aside class="space-y-4">
          <!-- Details -->
          <section class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-5 space-y-4">
            <h3 class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-400">Details</h3>

            <div v-if="draft.depth_score !== null && draft.depth_score !== undefined">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-500 mb-1">Depth score</p>
              <p class="text-3xl font-bold tracking-tight" :class="depthTone(draft.depth_score)">
                {{ draft.depth_score }}<span class="text-sm text-neutral-600 font-normal ml-1">/ 100</span>
              </p>
            </div>

            <div v-if="draft.scheduled_at" class="text-sm">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-500 mb-0.5">Approved at</p>
              <p class="text-neutral-200 font-mono">{{ formatDateTime(draft.scheduled_at) }}</p>
            </div>

            <div v-if="draft.cancel_window_ends_at && draft.status === 'awaiting_publish'" class="text-sm">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-500 mb-0.5">Publish at</p>
              <p class="text-neutral-200 font-mono">{{ formatDateTime(draft.cancel_window_ends_at) }}</p>
            </div>

            <div v-if="draft.published_at" class="text-sm">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-500 mb-0.5">Published</p>
              <p class="text-neutral-200 font-mono">{{ formatDateTime(draft.published_at) }}</p>
              <a
                v-if="draft.linkedin_post_url"
                :href="draft.linkedin_post_url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 mt-1 text-xs text-amber-400 hover:underline break-all"
              >
                View post
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                  <path :d="ICON.arrowUpRight" />
                </svg>
              </a>
            </div>

            <div v-if="draft.retry_count > 0" class="text-sm">
              <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-500 mb-0.5">Retries</p>
              <p class="font-mono text-neutral-200">{{ draft.retry_count }} of 3</p>
            </div>
          </section>

          <!-- Secondary actions -->
          <section class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-5">
            <h3 class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-400 mb-3">Actions</h3>
            <div class="flex flex-col gap-2">
              <button
                v-if="['awaiting_publish', 'manual_review'].includes(draft.status) && !isEditing"
                @click="startEdit"
                class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-sm font-medium text-neutral-200 ring-1 ring-neutral-800 transition group"
              >
                <span class="inline-flex items-center gap-2">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-400 group-hover:text-amber-400 transition-colors">
                    <path :d="ICON.pencil" />
                  </svg>
                  Edit content
                </span>
              </button>

              <button
                v-if="['manual_review', 'failed', 'cancelled', 'published'].includes(draft.status)"
                @click="doRegenerate"
                :disabled="regenerateMutation.isPending.value"
                title="Discard this draft and rebuild from the blog post (creates new draft, ~5-7 min)"
                class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-sm font-medium text-neutral-200 ring-1 ring-neutral-800 transition group disabled:opacity-50"
              >
                <span class="inline-flex items-center gap-2">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-400 group-hover:text-cyan-400 transition-colors">
                    <path :d="ICON.refresh" />
                  </svg>
                  {{ regenerateMutation.isPending.value ? 'Queueing…' : 'Restart from blog' }}
                </span>
              </button>

              <button
                v-if="draft.format === 'carousel' && carouselSlides.length > 0"
                @click="regenerateAllImages"
                :disabled="regenerateAllImagesMutation.isPending.value"
                title="Re-author all slide copy + images via /carousel-gen (~5-7 min, keeps draft ID)"
                class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-sm font-medium text-neutral-200 ring-1 ring-neutral-800 transition group disabled:opacity-50"
              >
                <span class="inline-flex items-center gap-2">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-400 group-hover:text-amber-400 transition-colors">
                    <path :d="ICON.image" />
                  </svg>
                  {{ regenerateAllImagesMutation.isPending.value ? 'Dispatching…' : 'Re-author all slides' }}
                </span>
              </button>

              <button
                v-if="!['cancelled', 'published'].includes(draft.status) && !isInProgress"
                @click="doCancel"
                :disabled="cancelMutation.isPending.value"
                class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-900 hover:bg-red-500/10 text-sm font-medium text-neutral-300 hover:text-red-400 ring-1 ring-neutral-800 hover:ring-red-500/30 transition group disabled:opacity-50"
              >
                <span class="inline-flex items-center gap-2">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path :d="ICON.x" />
                  </svg>
                  Cancel draft
                </span>
              </button>
            </div>
          </section>

          <!-- Humanized timeline -->
          <section
            v-if="Array.isArray(draft.pipeline_state_log) && draft.pipeline_state_log.length > 0"
            class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-5"
          >
            <h3 class="text-[11px] font-mono uppercase tracking-[0.14em] text-neutral-400 mb-4">Timeline</h3>
            <ol class="relative space-y-4 before:absolute before:left-[5px] before:top-1.5 before:bottom-1.5 before:w-px before:bg-neutral-800">
              <li
                v-for="(entry, i) in [...draft.pipeline_state_log].reverse()"
                :key="i"
                class="relative pl-6"
              >
                <span
                  class="absolute left-0 top-1 w-[11px] h-[11px] rounded-full ring-2 ring-neutral-950"
                  :class="MOOD_CLASSES[statusMeta(entry.to).mood]?.dot || 'bg-neutral-500'"
                />
                <p class="text-sm text-neutral-200 leading-snug">{{ transitionSummary(entry) }}</p>
                <p v-if="entry.reason && !['cron_scan_detected_new_post','plugin_dispatch_start','plugin_validate_start','plugin_passed_gate','admin_approve','admin_cancel','kill_switch_demotion'].includes(entry.reason)" class="text-[11px] text-neutral-500 mt-0.5">
                  {{ reasonLabel(entry.reason) }}
                </p>
                <p class="text-[10px] font-mono text-neutral-600 mt-0.5">{{ formatDateTime(entry.timestamp) }}</p>
              </li>
            </ol>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<style scoped>
@keyframes slide {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(400%); }
}
</style>
