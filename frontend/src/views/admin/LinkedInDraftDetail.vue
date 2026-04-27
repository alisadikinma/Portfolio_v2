<script setup>
import { ref, computed, watch } from 'vue'
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

// Edit mode state — seeded from draft when entering edit mode
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
  const payload = {
    content: editForm.value.content,
    link_comment: editForm.value.link_comment,
    hashtags: editForm.value.hashtags,
  }
  const result = await updateMutation.mutateAsync({ id: draftId.value, payload })
  updateWarnings.value = result?.warnings || []
  isEditing.value = false
  refetch()
}

function addHashtag() {
  const raw = newHashtagInput.value.trim()
  if (!raw) return
  const tag = raw.startsWith('#') ? raw : `#${raw}`
  if (editForm.value.hashtags.length >= 5) {
    alert('Max 5 hashtags')
    return
  }
  if (!editForm.value.hashtags.includes(tag)) {
    editForm.value.hashtags.push(tag)
  }
  newHashtagInput.value = ''
}

function removeHashtag(tag) {
  editForm.value.hashtags = editForm.value.hashtags.filter(t => t !== tag)
}

const charCount = computed(() => editForm.value.content.length)
const charCountClass = computed(() => {
  if (charCount.value < 800 || charCount.value > 1400) return 'text-red-500'
  if (charCount.value < 1100 || charCount.value > 1300) return 'text-yellow-500'
  return 'text-amber-500'
})

const hashtagCountValid = computed(() =>
  editForm.value.hashtags.length >= 3 && editForm.value.hashtags.length <= 5
)

// Actions
async function doApprove() {
  await approveMutation.mutateAsync(draftId.value)
  refetch()
}
async function doCancel() {
  if (!confirm('Cancel this draft?')) return
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
  if (!confirm('Regenerate from scratch? The current draft will be archived.')) return
  const result = await regenerateMutation.mutateAsync(draftId.value)
  const newId = result?.data?.id
  if (newId) router.push({ name: 'admin-linkedin-draft-detail', params: { id: newId } })
}

// Helpers
function statusBadgeClass(status) {
  return {
    published: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40',
    awaiting_publish: 'bg-amber-500/20 text-amber-400 border-amber-500/40',
    manual_review: 'bg-amber-500/20 text-amber-400 border-amber-500/40',
    cancelled: 'bg-neutral-500/20 text-neutral-400 border-neutral-500/40',
    failed: 'bg-red-500/20 text-red-400 border-red-500/40',
    generating: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40',
    validating: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40',
    pending_generation: 'bg-neutral-500/20 text-neutral-400 border-neutral-500/40',
  }[status] || 'bg-neutral-500/20 text-neutral-400'
}

function statusLabel(status) {
  return {
    published: 'Published',
    awaiting_publish: 'Awaiting Publish',
    manual_review: 'Manual Review',
    cancelled: 'Cancelled',
    failed: 'Failed',
    generating: 'Generating',
    validating: 'Validating',
    pending_generation: 'Pending',
  }[status] || status
}

function depthScoreClass(score) {
  if (score === null || score === undefined) return 'text-neutral-500'
  if (score >= 80) return 'text-amber-400'
  if (score >= 70) return 'text-yellow-500'
  return 'text-red-400'
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString()
}

function countdownTo(dt) {
  if (!dt) return ''
  const diff = new Date(dt).getTime() - Date.now()
  if (diff < 0) return 'publishing…'
  const mins = Math.floor(diff / 60000)
  const secs = Math.floor((diff % 60000) / 1000)
  return `in ${mins}m ${secs}s`
}

const showEdit = computed(() =>
  draft.value && ['awaiting_publish', 'manual_review'].includes(draft.value.status)
)

const carouselSlides = computed(() =>
  Array.isArray(draft.value?.carousel_slides) ? draft.value.carousel_slides : []
)

// Plugin v0.4.6+ splits per-slide text into copy_id (Indonesian, main headline)
// + copy_en (English, subtitle). Older drafts (v0.4.5 and below) only have a
// single `copy` field. These helpers prefer the new fields, fall back to copy.
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

// ============================================================================
// Carousel image generation status + retry actions
// ============================================================================

const slidesWithImageStatus = computed(() => {
  const tally = { done: 0, generating: 0, pending: 0, failed: 0, total: 0 }
  for (const slide of carouselSlides.value) {
    tally.total++
    const status = slide?.image_status
    if (status === 'done') tally.done++
    else if (status === 'generating') tally.generating++
    else if (status === 'failed') tally.failed++
    else tally.pending++
  }
  return tally
})

function imageStatusBadge(status) {
  return {
    done: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40',
    generating: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40 animate-pulse',
    failed: 'bg-red-500/20 text-red-400 border-red-500/40',
    pending: 'bg-neutral-500/20 text-neutral-400 border-neutral-500/40',
  }[status] || 'bg-neutral-500/20 text-neutral-400 border-neutral-500/40'
}

function imageStatusLabel(status) {
  return {
    done: 'Ready',
    generating: 'Generating…',
    failed: 'Failed',
    pending: 'Pending',
  }[status] || 'Pending'
}

async function regenerateAllImages() {
  if (!draft.value) return
  if (!confirm(`Regenerate ALL ${carouselSlides.value.length} slide images? This will re-dispatch every slide that isn't already done.`)) return
  try {
    await regenerateAllImagesMutation.mutateAsync(draftId.value)
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Image regeneration failed')
  }
}

async function regenerateSingleSlide(slideIndex) {
  if (!confirm(`Re-render slide ${slideIndex + 1}? Existing image will be replaced when GeminiGen finishes (~30s).`)) return
  try {
    await regenerateSlideMutation.mutateAsync({ id: draftId.value, slideIndex })
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Slide retry failed')
  }
}
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Back + Loading/Error -->
    <div class="mb-4">
      <button
        @click="router.back()"
        class="text-sm text-neutral-500 hover:text-amber-600 flex items-center gap-1"
      >
        ← Back
      </button>
    </div>

    <div v-if="isLoading" class="py-12 text-center text-neutral-500">Loading draft…</div>
    <div v-else-if="error" class="py-12 text-center">
      <p class="text-red-500">Failed to load draft</p>
      <p class="text-xs text-neutral-500 mt-1">{{ error.message || 'Unknown error' }}</p>
    </div>
    <div v-else-if="!draft" class="py-12 text-center text-neutral-500">
      Draft not found.
    </div>

    <!-- Main layout -->
    <div v-else class="grid gap-6 lg:grid-cols-[1fr_360px]">
      <!-- Left column: content preview / editor -->
      <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-wrap items-center gap-3">
          <span
            class="px-3 py-1 rounded-full text-xs font-medium uppercase tracking-wider border"
            :class="statusBadgeClass(draft.status)"
          >
            {{ statusLabel(draft.status) }}
          </span>
          <span class="text-xs font-mono text-neutral-500 uppercase tracking-wider">
            {{ draft.format }}
          </span>
          <span v-if="draft.carousel_slides" class="text-xs text-neutral-500">
            · {{ carouselSlides.length }} slides
          </span>
        </div>

        <!-- Update warnings (from PUT 200 response) -->
        <div
          v-if="updateWarnings.length > 0"
          class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        >
          <p class="font-semibold text-amber-700 dark:text-amber-400 mb-1">Heads up:</p>
          <ul class="list-disc list-inside text-amber-700 dark:text-amber-400 space-y-0.5">
            <li v-for="w in updateWarnings" :key="w">{{ w }}</li>
          </ul>
        </div>

        <!-- CONTENT PREVIEW (read mode) -->
        <div
          v-if="!isEditing"
          class="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900"
        >
          <!-- Text format -->
          <div v-if="draft.format === 'text'" class="p-5 space-y-4">
            <div class="flex items-start gap-3 pb-3 border-b border-neutral-200 dark:border-neutral-700">
              <div class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-500 to-cyan-500 flex items-center justify-center text-white font-bold">
                AS
              </div>
              <div class="flex-1">
                <p class="font-semibold text-neutral-900 dark:text-neutral-100">
                  Ali Sadikin Ma
                </p>
                <p class="text-xs text-neutral-500">AI Generalist Expert · now</p>
              </div>
              <svg class="w-6 h-6 text-[#0077B5]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
              </svg>
            </div>

            <div class="whitespace-pre-wrap text-neutral-800 dark:text-neutral-200 leading-relaxed">
              {{ draft.content }}
            </div>

            <div v-if="Array.isArray(draft.hashtags) && draft.hashtags.length > 0" class="flex flex-wrap gap-2">
              <span
                v-for="tag in draft.hashtags"
                :key="tag"
                class="text-[#0077B5] dark:text-cyan-400 text-sm hover:underline"
              >
                {{ tag }}
              </span>
            </div>

            <!-- First-comment bubble -->
            <div
              v-if="draft.link_comment"
              class="mt-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border-l-4 border-[#0077B5]"
            >
              <p class="text-[11px] text-[#0077B5] dark:text-cyan-400 font-semibold uppercase tracking-wider mb-1">
                First comment
              </p>
              <p class="text-sm text-neutral-700 dark:text-neutral-300">
                {{ draft.link_comment }}
              </p>
            </div>
          </div>

          <!-- Carousel format -->
          <div v-else-if="draft.format === 'carousel'" class="p-5 space-y-4">
            <div v-if="carouselSlides.length === 0" class="text-center py-8 text-neutral-500">
              No slides generated yet.
            </div>
            <div v-else>
              <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <p class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                  Slide {{ activeSlideIndex + 1 }} / {{ carouselSlides.length }}
                </p>
                <div class="flex gap-2">
                  <!-- Per-slide regen — always available so operators can re-render
                       any slide that doesn't meet expectations, not just failed ones -->
                  <button
                    @click="regenerateSingleSlide(activeSlideIndex)"
                    :disabled="regenerateSlideMutation.isPending.value"
                    class="px-3 py-1 text-xs rounded bg-amber-500/10 border border-amber-500/40 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 disabled:opacity-30"
                    title="Re-render this slide (replaces existing image when GeminiGen finishes)"
                  >
                    {{ regenerateSlideMutation.isPending.value ? '↻ Queueing…' : '↻ Regen this slide' }}
                  </button>
                  <button
                    @click="prevSlide"
                    :disabled="activeSlideIndex === 0"
                    class="px-3 py-1 text-xs rounded bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-30"
                  >
                    ← Prev
                  </button>
                  <button
                    @click="nextSlide"
                    :disabled="activeSlideIndex === carouselSlides.length - 1"
                    class="px-3 py-1 text-xs rounded bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-30"
                  >
                    Next →
                  </button>
                </div>
              </div>

              <!-- Image-only 3:4 frame — slide PNG fills the entire container.
                   Native render is 1080x1440 (3:4 portrait — Imagen-native ratio
                   chosen for cross-platform reuse: full-bleed on LinkedIn,
                   center-crops cleanly to 4:5 on Instagram Feed, centers with
                   minor letterbox on TikTok). Frame ratio matches the image
                   ratio so there is no letterboxing in this admin viewer. -->
              <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden bg-gradient-to-br from-neutral-900 to-neutral-700 mx-auto relative"
                   style="aspect-ratio: 3 / 4; max-height: 80vh; max-width: min(100%, 60vh);">
                <!-- Status pill overlay (top-right) -->
                <span
                  v-if="carouselSlides[activeSlideIndex]?.image_status"
                  class="absolute top-3 right-3 z-10 px-2.5 py-1 rounded-full text-[11px] font-medium uppercase tracking-wider border"
                  :class="imageStatusBadge(carouselSlides[activeSlideIndex].image_status)"
                >
                  {{ imageStatusLabel(carouselSlides[activeSlideIndex].image_status) }}
                </span>

                <!-- Rendered slide image (when done) — full bleed, fills the 4:5 frame -->
                <img
                  v-if="carouselSlides[activeSlideIndex]?.image_url"
                  :src="carouselSlides[activeSlideIndex].image_url"
                  :alt="`Slide ${activeSlideIndex + 1}`"
                  class="absolute inset-0 w-full h-full object-cover"
                >

                <!-- Generating placeholder (spinner + copy text preview) -->
                <div v-else-if="carouselSlides[activeSlideIndex]?.image_status === 'generating'"
                     class="absolute inset-0 flex flex-col items-center justify-center text-center p-6">
                  <svg class="animate-spin w-12 h-12 text-cyan-400 mb-3" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                  </svg>
                  <p class="text-cyan-300 text-xs uppercase tracking-wider mb-2">Rendering with GeminiGen…</p>
                  <!-- Indonesian main headline -->
                  <p class="text-white text-base font-bold uppercase tracking-tight max-w-md mb-1">
                    {{ slideCopyId(carouselSlides[activeSlideIndex]) }}
                  </p>
                  <!-- English subtitle (golden, smaller) -->
                  <p class="text-amber-400 text-xs italic max-w-md">
                    {{ slideCopyEn(carouselSlides[activeSlideIndex]) }}
                  </p>
                </div>

                <!-- Failed placeholder (with retry button) -->
                <div v-else-if="carouselSlides[activeSlideIndex]?.image_status === 'failed'"
                     class="absolute inset-0 flex flex-col items-center justify-center text-center p-6">
                  <p class="text-red-400 text-xs uppercase tracking-wider mb-2">Render failed</p>
                  <p class="text-red-200 text-xs mb-3 max-w-md">
                    {{ carouselSlides[activeSlideIndex]?.image_error || 'Unknown error from GeminiGen' }}
                  </p>
                  <button
                    @click="regenerateSingleSlide(activeSlideIndex)"
                    :disabled="regenerateSlideMutation.isPending.value"
                    class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 disabled:opacity-50"
                  >
                    ↻ Retry this slide
                  </button>
                </div>

                <!-- Pending / no-status placeholder (copy text preview, bilingual) -->
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-center text-neutral-400 p-6">
                  <p class="text-xs uppercase tracking-wider mb-2">
                    {{ carouselSlides[activeSlideIndex]?.layout_hint || 'Image placeholder' }}
                  </p>
                  <!-- Indonesian main headline (white, bold, uppercase) -->
                  <p class="text-white text-lg font-bold uppercase tracking-tight max-w-md mb-2">
                    {{ slideCopyId(carouselSlides[activeSlideIndex]) }}
                  </p>
                  <!-- English subtitle (golden, smaller, italic) -->
                  <p class="text-amber-400 text-sm italic max-w-md">
                    {{ slideCopyEn(carouselSlides[activeSlideIndex]) }}
                  </p>
                </div>
              </div>

              <!-- Slide metadata + bilingual copy preview (sits BELOW the 3:4 frame so the image dominates).
                   max-width matches the slide viewer's 60vh so the metadata bar aligns under the image.
                   Shows both copy_id (Indonesian, headline) + copy_en (English, subtitle) per v0.4.6 schema. -->
              <div class="mt-3 mx-auto rounded-lg bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 p-3 space-y-2"
                   style="max-width: min(100%, 60vh);">
                <p class="text-[10px] uppercase tracking-wider text-neutral-500">
                  {{ carouselSlides[activeSlideIndex]?.layout_hint || 'body' }}
                </p>
                <!-- ID label + Indonesian copy -->
                <div>
                  <span class="inline-block text-[9px] font-mono uppercase tracking-wider text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded bg-emerald-500/10 mr-2">ID</span>
                  <span class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 leading-relaxed">
                    {{ slideCopyId(carouselSlides[activeSlideIndex]) || '—' }}
                  </span>
                </div>
                <!-- EN label + English copy -->
                <div>
                  <span class="inline-block text-[9px] font-mono uppercase tracking-wider text-cyan-600 dark:text-cyan-400 px-1.5 py-0.5 rounded bg-cyan-500/10 mr-2">EN</span>
                  <span class="text-sm text-neutral-700 dark:text-neutral-300 italic leading-relaxed">
                    {{ slideCopyEn(carouselSlides[activeSlideIndex]) || '—' }}
                  </span>
                </div>
              </div>

              <!-- Image generation summary line -->
              <div
                v-if="slidesWithImageStatus.total > 0"
                class="mt-3 flex items-center justify-between text-xs text-neutral-500"
              >
                <div class="flex flex-wrap gap-x-3 gap-y-1 font-mono">
                  <span class="text-emerald-500">✓ {{ slidesWithImageStatus.done }}</span>
                  <span v-if="slidesWithImageStatus.generating > 0" class="text-cyan-500 animate-pulse">
                    ↻ {{ slidesWithImageStatus.generating }} generating
                  </span>
                  <span v-if="slidesWithImageStatus.pending > 0" class="text-neutral-500">
                    · {{ slidesWithImageStatus.pending }} pending
                  </span>
                  <span v-if="slidesWithImageStatus.failed > 0" class="text-red-500">
                    ✕ {{ slidesWithImageStatus.failed }} failed
                  </span>
                  <span class="text-neutral-400">/ {{ slidesWithImageStatus.total }} total</span>
                </div>
              </div>

              <!-- Thumbnail strip with per-slide image status indicator dots -->
              <div class="mt-3 flex gap-2 overflow-x-auto pb-2">
                <button
                  v-for="(slide, i) in carouselSlides"
                  :key="i"
                  @click="activeSlideIndex = i"
                  :class="[
                    'flex-shrink-0 w-14 h-14 rounded-md border-2 text-[10px] font-mono flex items-center justify-center transition-all relative overflow-hidden',
                    i === activeSlideIndex
                      ? 'border-amber-500 bg-amber-500/20 text-amber-600'
                      : 'border-neutral-300 dark:border-neutral-700 text-neutral-500 hover:border-amber-500/50',
                  ]"
                >
                  <!-- Thumbnail image when ready -->
                  <img
                    v-if="slide?.image_url"
                    :src="slide.image_url"
                    :alt="`Slide ${i + 1}`"
                    class="absolute inset-0 w-full h-full object-cover opacity-60"
                  >
                  <!-- Status dot -->
                  <span
                    v-if="slide?.image_status"
                    :class="[
                      'absolute top-0 right-0 w-2 h-2 rounded-full m-0.5',
                      slide.image_status === 'done' && 'bg-emerald-500',
                      slide.image_status === 'generating' && 'bg-cyan-500 animate-pulse',
                      slide.image_status === 'failed' && 'bg-red-500',
                      slide.image_status === 'pending' && 'bg-neutral-400',
                    ]"
                  />
                  <!-- Slide number on top of any thumbnail backdrop -->
                  <span class="relative z-10 font-bold text-shadow drop-shadow-md">
                    {{ i + 1 }}
                  </span>
                </button>
              </div>
            </div>

            <!-- Hashtags + link_comment below carousel -->
            <div v-if="Array.isArray(draft.hashtags) && draft.hashtags.length > 0" class="flex flex-wrap gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-700">
              <span
                v-for="tag in draft.hashtags"
                :key="tag"
                class="text-[#0077B5] dark:text-cyan-400 text-sm"
              >
                {{ tag }}
              </span>
            </div>
          </div>
        </div>

        <!-- EDIT MODE -->
        <div
          v-else
          class="rounded-xl border border-amber-500/50 bg-white dark:bg-neutral-900 p-5 space-y-4"
        >
          <p class="text-xs font-mono uppercase tracking-wider text-amber-600">Editing draft</p>

          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Content
              <span class="ml-2 text-xs font-normal" :class="charCountClass">
                {{ charCount }} chars · sweet spot 1100-1300
              </span>
            </label>
            <textarea
              v-model="editForm.content"
              rows="12"
              class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-amber-500 font-mono text-sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Link comment
              <span class="text-xs font-normal text-neutral-500">— posted as first comment after publish</span>
            </label>
            <input
              v-model="editForm.link_comment"
              type="text"
              placeholder="Full article: https://alisadikinma.com/blog/..."
              class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-amber-500"
            >
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Hashtags
              <span class="text-xs font-normal" :class="hashtagCountValid ? 'text-neutral-500' : 'text-red-500'">
                {{ editForm.hashtags.length }}/5 (min 3)
              </span>
            </label>
            <div class="flex flex-wrap gap-2 mb-2">
              <span
                v-for="tag in editForm.hashtags"
                :key="tag"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-500/20 text-amber-600 dark:text-amber-400 text-sm"
              >
                {{ tag }}
                <button
                  @click="removeHashtag(tag)"
                  class="hover:text-red-500"
                  aria-label="Remove hashtag"
                >×</button>
              </span>
            </div>
            <div class="flex gap-2">
              <input
                v-model="newHashtagInput"
                @keydown.enter.prevent="addHashtag"
                type="text"
                placeholder="#NewHashtag (enter to add)"
                class="flex-1 px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-sm focus:outline-none focus:border-amber-500"
              >
              <button
                @click="addHashtag"
                class="px-3 py-2 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400 hover:bg-amber-500/30 text-sm"
              >
                Add
              </button>
            </div>
          </div>

          <div class="flex gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-700">
            <button
              @click="saveEdit"
              :disabled="updateMutation.isPending.value || !hashtagCountValid"
              class="px-4 py-2 rounded-lg bg-amber-500 text-white font-medium hover:bg-amber-600 disabled:opacity-50"
            >
              {{ updateMutation.isPending.value ? 'Saving…' : 'Save' }}
            </button>
            <button
              @click="cancelEdit"
              class="px-4 py-2 rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
            >
              Cancel
            </button>
          </div>
        </div>

        <!-- Validation panel -->
        <div
          v-if="!isEditing && draft.validation_log && (draft.validation_log.failures?.length || draft.validation_log.suggestions?.length)"
          class="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5"
        >
          <h3 class="text-sm font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-3">
            Validation
          </h3>

          <div v-if="draft.validation_log.failures?.length" class="space-y-2 mb-4">
            <p class="text-xs font-medium text-red-500 uppercase tracking-wider">Failures</p>
            <div
              v-for="(f, i) in draft.validation_log.failures"
              :key="i"
              class="flex gap-3 p-2 rounded bg-red-500/10 border border-red-500/30"
            >
              <span class="font-mono text-xs text-red-500 whitespace-nowrap">{{ f.deduction }} pts</span>
              <div>
                <p class="text-sm text-neutral-800 dark:text-neutral-200">{{ f.message }}</p>
                <p class="text-xs text-neutral-500 font-mono">rule: {{ f.rule }}</p>
              </div>
            </div>
          </div>

          <div v-if="draft.validation_log.suggestions?.length">
            <p class="text-xs font-medium text-amber-500 uppercase tracking-wider mb-2">Suggestions</p>
            <ul class="text-sm space-y-1 text-neutral-700 dark:text-neutral-300 list-disc list-inside">
              <li v-for="(s, i) in draft.validation_log.suggestions" :key="i">{{ s }}</li>
            </ul>
          </div>
        </div>

        <!-- Last error (for failed status) -->
        <div
          v-if="!isEditing && draft.last_error"
          class="rounded-xl border border-red-500/40 bg-red-500/10 p-4"
        >
          <p class="text-xs font-medium text-red-500 uppercase tracking-wider mb-1">Last error</p>
          <p class="text-sm text-red-700 dark:text-red-400 font-mono break-words">
            {{ draft.last_error }}
          </p>
        </div>
      </div>

      <!-- Right column: metadata + timeline + actions -->
      <div class="space-y-4">
        <!-- Metadata -->
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4 space-y-3 text-sm">
          <div class="pb-3 border-b border-neutral-200 dark:border-neutral-700">
            <p class="text-xs uppercase tracking-wider text-neutral-500 mb-1">From blog</p>
            <p class="font-medium text-neutral-800 dark:text-neutral-200">
              {{ postTitle(draft) }}
            </p>
          </div>

          <div v-if="draft.depth_score !== null && draft.depth_score !== undefined">
            <p class="text-xs uppercase tracking-wider text-neutral-500">Depth score</p>
            <p class="text-3xl font-mono font-bold" :class="depthScoreClass(draft.depth_score)">
              {{ draft.depth_score }}
              <span class="text-sm text-neutral-500 font-normal">/ 100</span>
            </p>
          </div>

          <div v-if="draft.scheduled_at">
            <p class="text-xs uppercase tracking-wider text-neutral-500">Scheduled</p>
            <p class="text-neutral-800 dark:text-neutral-200">{{ formatDateTime(draft.scheduled_at) }}</p>
          </div>

          <div v-if="draft.cancel_window_ends_at && draft.status === 'awaiting_publish'">
            <p class="text-xs uppercase tracking-wider text-neutral-500">Publishes</p>
            <p class="text-amber-500 font-mono font-semibold">
              {{ countdownTo(draft.cancel_window_ends_at) }}
            </p>
            <p class="text-xs text-neutral-500">{{ formatDateTime(draft.cancel_window_ends_at) }}</p>
          </div>

          <div v-if="draft.published_at">
            <p class="text-xs uppercase tracking-wider text-neutral-500">Published</p>
            <p class="text-neutral-800 dark:text-neutral-200">{{ formatDateTime(draft.published_at) }}</p>
            <a
              v-if="draft.linkedin_post_url"
              :href="draft.linkedin_post_url"
              target="_blank"
              rel="noopener"
              class="text-xs text-amber-600 hover:underline break-all"
            >
              {{ draft.linkedin_post_url }} ↗
            </a>
          </div>

          <div v-if="draft.retry_count > 0">
            <p class="text-xs uppercase tracking-wider text-neutral-500">Retries</p>
            <p class="font-mono text-neutral-800 dark:text-neutral-200">{{ draft.retry_count }} / 3</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4">
          <p class="text-xs uppercase tracking-wider text-neutral-500 mb-3">Actions</p>
          <div class="flex flex-col gap-2">
            <button
              v-if="showEdit && !isEditing"
              @click="startEdit"
              class="w-full px-3 py-2 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-sm font-medium text-neutral-800 dark:text-neutral-200"
            >
              ✎ Edit content
            </button>

            <button
              v-if="draft.status === 'manual_review'"
              @click="doApprove"
              :disabled="approveMutation.isPending.value"
              class="w-full px-3 py-2 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 text-sm font-medium disabled:opacity-50"
            >
              {{ approveMutation.isPending.value ? 'Approving…' : '✓ Approve' }}
            </button>

            <button
              v-if="draft.status === 'awaiting_publish'"
              @click="doPublishNow"
              :disabled="publishNowMutation.isPending.value"
              class="w-full px-3 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600 text-sm font-medium disabled:opacity-50"
            >
              {{ publishNowMutation.isPending.value ? 'Publishing…' : '🚀 Publish now' }}
            </button>

            <a
              v-if="draft.status === 'published' && draft.linkedin_post_url"
              :href="draft.linkedin_post_url"
              target="_blank"
              rel="noopener"
              class="w-full px-3 py-2 rounded-lg bg-[#0077B5] text-white hover:bg-[#005f8e] text-sm font-medium text-center"
            >
              Open on LinkedIn ↗
            </a>

            <button
              v-if="['manual_review', 'failed', 'cancelled', 'published'].includes(draft.status)"
              @click="doRegenerate"
              :disabled="regenerateMutation.isPending.value"
              class="w-full px-3 py-2 rounded-lg border border-cyan-500 text-cyan-600 dark:text-cyan-400 hover:bg-cyan-500/10 text-sm font-medium disabled:opacity-50"
            >
              {{ regenerateMutation.isPending.value ? 'Queueing…' : '↻ Regenerate copy' }}
            </button>

            <!-- Carousel-only: regenerate all slide images (re-dispatches GeminiGen for every non-done slide) -->
            <button
              v-if="draft.format === 'carousel' && carouselSlides.length > 0"
              @click="regenerateAllImages"
              :disabled="regenerateAllImagesMutation.isPending.value"
              class="w-full px-3 py-2 rounded-lg border border-amber-500 text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 text-sm font-medium disabled:opacity-50"
            >
              {{ regenerateAllImagesMutation.isPending.value ? 'Dispatching…' : '🖼 Regenerate all images' }}
            </button>

            <button
              v-if="!['cancelled', 'published'].includes(draft.status)"
              @click="doCancel"
              :disabled="cancelMutation.isPending.value"
              class="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-sm font-medium disabled:opacity-50"
            >
              ✕ Cancel
            </button>
          </div>
        </div>

        <!-- Timeline -->
        <div
          v-if="Array.isArray(draft.pipeline_state_log) && draft.pipeline_state_log.length > 0"
          class="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4"
        >
          <p class="text-xs uppercase tracking-wider text-neutral-500 mb-3">Timeline</p>
          <div class="space-y-2.5">
            <div
              v-for="(entry, i) in draft.pipeline_state_log.slice().reverse()"
              :key="i"
              class="text-xs border-l-2 border-neutral-300 dark:border-neutral-700 pl-3 py-0.5"
            >
              <p class="font-mono text-neutral-600 dark:text-neutral-400">
                <span class="text-neutral-500">{{ entry.from }}</span>
                →
                <span class="text-neutral-800 dark:text-neutral-200 font-semibold">{{ entry.to }}</span>
              </p>
              <p v-if="entry.reason" class="text-[11px] text-amber-600 dark:text-amber-400 mt-0.5">
                {{ entry.reason }}
              </p>
              <p class="text-[10px] text-neutral-500 font-mono mt-0.5">
                {{ formatDateTime(entry.timestamp) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
