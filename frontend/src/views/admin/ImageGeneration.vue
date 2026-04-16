<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'
import PipelineStepBar from '@/components/admin/PipelineStepBar.vue'
import ImageConfigModal from '@/components/admin/ImageConfigModal.vue'
import BaseLightbox from '@/components/base/BaseLightbox.vue'

const route = useRoute()
const router = useRouter()
const { getIdea, saveDraft, generateSegmentImage, rewriteSegmentVd, isLoading } = useContentEngine()
const toast = useToast()

const idea = ref(null)
const loadError = ref(null)
const segments = ref([])
const generatingAll = ref(false)
const configSegment = ref(null) // segment being configured in modal
const lightboxIndex = ref(-1)
const adHocPreview = ref(null) // { url, title } — for chip clicks (face/style refs)
const generatedSegments = computed(() => segments.value.filter(s => s.status === 'done' && s.generated_url))
const lightboxImage = computed(() => {
  if (adHocPreview.value) return adHocPreview.value.url
  return generatedSegments.value[lightboxIndex.value]?.generated_url || ''
})
const lightboxTitle = computed(() => {
  if (adHocPreview.value) return adHocPreview.value.title
  const s = generatedSegments.value[lightboxIndex.value]
  return s ? `${s.label} — ${s.concept || s.visual_direction || ''}`.trim() : ''
})
const lightboxFilename = computed(() => {
  if (adHocPreview.value) {
    const name = adHocPreview.value.url.split('/').pop() || 'reference.jpg'
    return name.split('?')[0]
  }
  const s = generatedSegments.value[lightboxIndex.value]
  if (!s) return ''
  const slug = (s.label || 'image').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  return `${slug}.jpg`
})
const lightboxOpen = computed(() => adHocPreview.value !== null || lightboxIndex.value >= 0)
const lightboxTotal = computed(() => (adHocPreview.value ? 1 : generatedSegments.value.length))
const lightboxCurrentIdx = computed(() => (adHocPreview.value ? 0 : lightboxIndex.value))

function openLightbox(seg) {
  adHocPreview.value = null
  const idx = generatedSegments.value.findIndex(s => s.index === seg.index)
  if (idx >= 0) lightboxIndex.value = idx
}
function previewConfigImage(url, title = 'Reference image') {
  if (!url || url.startsWith('blob:')) return
  adHocPreview.value = { url, title }
}
function closeLightbox() {
  lightboxIndex.value = -1
  adHocPreview.value = null
}
function lightboxPrev() {
  if (adHocPreview.value) return
  if (lightboxIndex.value > 0) lightboxIndex.value--
}
function lightboxNext() {
  if (adHocPreview.value) return
  if (lightboxIndex.value < generatedSegments.value.length - 1) lightboxIndex.value++
}
function hasSegmentConfig(seg) {
  if (!seg) return false
  const faces = (seg.face_refs || []).filter(u => u && !u.startsWith('blob:'))
  const styles = (seg.style_refs || []).filter(u => u && !u.startsWith('blob:'))
  return faces.length > 0 || styles.length > 0 || (seg.additional_notes || '').trim().length > 0
}
let saveTimeout = null
let pollInterval = null

// ── Style/Model/Ratio options (defaults: Photorealistic, nano-banana-2, 16:9) ──
const styleOptions = ['Photorealistic', 'Cinematic', 'Portrait Cinematic', 'Minimal', 'Abstract', 'Illustration']
const modelOptions = ['nano-banana-2', 'nano-banana-pro', 'imagen-4']
const ratioOptions = ['16:9', '4:3', '1:1', '9:16']

// ── Load idea ──
onMounted(async () => {
  const id = route.params.id
  const result = await getIdea(id)
  if (result.success && result.data) {
    idea.value = result.data
    initSegments()
    startPolling()
  } else {
    loadError.value = result.error || 'Failed to load content idea'
  }
})

onUnmounted(() => {
  if (saveTimeout) clearTimeout(saveTimeout)
  if (pollInterval) clearInterval(pollInterval)
})

function initSegments() {
  const article = idea.value?.generated_article
  if (!article?.image_prompts) return

  // Normalize stuck segments: if status='generating' but no job_uuid, the previous
  // queue attempt died mid-request (network, timeout, or backend exception).
  // Treat as 'failed' so user can click Retry instead of staring at a spinner.
  const normalizeStatus = (img) => {
    const s = img.status || 'pending'
    if (s === 'generating' && !img.job_uuid) return 'failed'
    return s
  }
  // Strip browser-only blob: URLs — GeminiGen can't fetch them (400 FILE_DOWNLOAD_FAILED).
  // These get saved into face_refs/style_refs when file upload failed in earlier versions.
  const isUsableRef = (u) => typeof u === 'string' && u.length > 0 && !u.startsWith('blob:')

  segments.value = article.image_prompts.map((img, i) => ({
    ...img,
    index: i,
    visual_direction: img.visual_direction || img.prompt || '',
    visual_direction_original: img.visual_direction_original || '',
    style: img.style || 'Photorealistic',
    model: img.model || 'nano-banana-2',
    aspect_ratio: img.aspect_ratio || '16:9',
    reference_image_url: img.reference_image_url || '',
    face_refs: (img.face_refs || []).filter(isUsableRef),
    style_refs: (img.style_refs || []).filter(isUsableRef),
    additional_notes: img.additional_notes || '',
    generated_url: img.generated_url || '',
    status: normalizeStatus(img),
    label: img.type === 'cover' ? 'COVER' : `BODY-${i}`,
  }))
}

function getPrompt(seg) {
  return seg.visual_direction || ''
}

// ── Auto-save draft (debounced) ──
function scheduleAutoSave() {
  if (saveTimeout) clearTimeout(saveTimeout)
  saveTimeout = setTimeout(async () => {
    await persistDraft()
  }, 2000)
}

async function persistDraft() {
  if (!idea.value) return
  const article = { ...idea.value.generated_article }
  // Merge user-editable fields only — DO NOT send generated_url, status, job_uuid.
  // Those are managed by generateSegmentImage controller + GeminiGen webhook.
  // Sending them here races with the webhook and can overwrite completed results.
  const existingPrompts = idea.value.generated_article?.image_prompts || []
  article.image_prompts = segments.value.map((seg, i) => {
    const existing = existingPrompts[i] || {}
    return {
      ...existing,  // preserve backend-managed fields (status, job_uuid, generated_url)
      type: seg.type,
      section: seg.section,
      concept: seg.concept,
      prompt: getPrompt(seg),
      visual_direction: seg.visual_direction,
      visual_direction_original: seg.visual_direction_original,
      style: seg.style,
      model: seg.model,
      aspect_ratio: seg.aspect_ratio,
      resolution: seg.resolution || '1K',
      placement: seg.placement,
      suggested_position: seg.suggested_position,
      insert_after_heading: seg.insert_after_heading,
      reference_image_url: seg.reference_image_url,
      face_refs: seg.face_refs || [],
      style_refs: seg.style_refs || [],
      additional_notes: seg.additional_notes || '',
    }
  })
  await saveDraft(idea.value.id, { generated_article: article })
}

// ── Generation ──
async function handleGenerateAll() {
  generatingAll.value = true
  const pending = segments.value.filter(s => s.status !== 'done')
  for (const seg of pending) {
    await generateSingle(seg.index)
  }
  generatingAll.value = false
}

async function generateSingle(segIndex) {
  const seg = segments.value[segIndex]
  if (!seg) return

  // Cancel any pending auto-save to prevent it from overwriting the job_uuid
  // that generateSegmentImage is about to set in the backend
  if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null }

  seg.status = 'generating'
  seg.generated_url = ''  // clear stale image so polling doesn't short-circuit
  seg.job_uuid = null
  const prompt = getPrompt(seg)

  const result = await generateSegmentImage(idea.value.id, {
    segment_index: segIndex,
    prompt,
    style: seg.style,
    model: seg.model,
    aspect_ratio: seg.aspect_ratio,
    face_refs: seg.face_refs || [],
    style_refs: seg.style_refs || [],
    additional_notes: seg.additional_notes || '',
  })

  if (result.success) {
    seg.job_uuid = result.data?.uuid
    toast.success(`Image ${segIndex + 1} generation started`)
  } else {
    seg.status = 'failed'
    toast.error(result.error || `Failed to generate image ${segIndex + 1}`)
  }
}

// ── Poll for generation completion ──
function startPolling() {
  pollInterval = setInterval(async () => {
    const generating = segments.value.some(s => s.status === 'generating')
    if (!generating) return

    const result = await getIdea(idea.value.id)
    if (!result.success) return

    idea.value = result.data
    const updatedPrompts = result.data.generated_article?.image_prompts || []
    for (let i = 0; i < updatedPrompts.length && i < segments.value.length; i++) {
      const updated = updatedPrompts[i]
      const seg = segments.value[i]
      if (seg.status !== 'generating') continue
      // Match by job_uuid to ensure we're seeing the CURRENT job's result, not a stale one
      const uuidMatch = seg.job_uuid && updated.job_uuid === seg.job_uuid
      if (uuidMatch && updated.generated_url && updated.status === 'done') {
        seg.generated_url = updated.generated_url
        seg.status = 'done'
      }
      if (uuidMatch && updated.status === 'failed') {
        seg.status = 'failed'
      }
    }
  }, 5000)
}

// ── Computed ──
const doneCount = computed(() => segments.value.filter(s => s.status === 'done').length)
const totalCount = computed(() => segments.value.length)
const allDone = computed(() => doneCount.value === totalCount.value && totalCount.value > 0)
const articleTitle = computed(() => {
  const a = idea.value?.generated_article
  return a?.en?.title || a?.title || idea.value?.title || ''
})

// ── Config modal apply ──
function openConfig(seg) {
  configSegment.value = seg
}

async function handleConfigApply(options) {
  const seg = configSegment.value
  if (!seg) return

  const prevFaceRefs = (seg.face_refs || []).slice()
  const newFaceRefs = (options.faceRefs || []).filter(u => u && !u.startsWith('blob:'))

  seg.face_refs = options.faceRefs
  seg.style_refs = options.styleRefs
  seg.additional_notes = options.additionalNotes
  seg.model = options.model
  seg.style = options.style
  configSegment.value = null

  // If a face ref is present AND it changed since last generation, rewrite VD
  // to match the reference's actual appearance BEFORE generating. This avoids
  // demographic contradiction (e.g. VD says "young woman" but ref is an older
  // man — GeminiGen follows text over the reference image when they conflict).
  const faceRefsChanged =
    newFaceRefs.length !== prevFaceRefs.length ||
    newFaceRefs.some((u, i) => u !== prevFaceRefs[i])
  const needsVdRewrite =
    newFaceRefs.length > 0 && (faceRefsChanged || !seg.visual_direction_original)

  if (needsVdRewrite) {
    const prevStatus = seg.status
    seg.status = 'rewriting_vd'
    toast.success('Rewriting visual direction for face reference...')
    const rewriteResult = await rewriteSegmentVd(idea.value.id, seg.index, newFaceRefs[0])
    if (rewriteResult.success && rewriteResult.data?.new_vd) {
      if (!seg.visual_direction_original) {
        seg.visual_direction_original = rewriteResult.data.original_vd
      }
      seg.visual_direction = rewriteResult.data.new_vd
    } else {
      seg.status = prevStatus
      toast.error(rewriteResult.error || 'Visual direction rewrite failed — generating with original VD')
    }
  }

  scheduleAutoSave()
  toast.success('Configuration applied')
}

// ── Approve & continue ──
async function handleApprove() {
  await persistDraft()
  // Update status to images_ready
  const article = { ...idea.value.generated_article }
  article.image_prompts = segments.value.map(seg => ({
    ...seg,
    prompt: getPrompt(seg),
  }))
  await saveDraft(idea.value.id, { generated_article: article })

  router.push(`/admin/content-engine/${idea.value.id}/finalize`)
}
</script>

<template>
  <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
    <!-- Loading -->
    <div v-if="isLoading && !idea" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <svg class="animate-spin h-8 w-8 mx-auto text-amber-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Loading image generation...</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="flex items-center justify-center min-h-screen">
      <div class="text-center max-w-md">
        <p class="text-red-600 dark:text-red-400 font-medium mb-2">Failed to load</p>
        <p class="text-sm text-neutral-500">{{ loadError }}</p>
      </div>
    </div>

    <!-- Main -->
    <template v-else-if="idea">
      <!-- Step Bar -->
      <PipelineStepBar :current-step="2" :idea-id="route.params.id" :idea-status="idea.status" />

      <!-- Title Bar -->
      <div class="sticky top-0 z-40 bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
          <button @click="router.push(`/admin/content-engine/${idea.id}/preview`)" class="inline-flex items-center gap-1.5 text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Article
          </button>
          <h1 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 truncate max-w-md hidden sm:block">{{ articleTitle }}</h1>
          <button @click="handleGenerateAll" :disabled="generatingAll || allDone" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">
            <svg v-if="!generatingAll" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            {{ generatingAll ? 'Generating...' : allDone ? 'All Generated' : 'Generate All' }}
          </button>
        </div>
      </div>

      <!-- Segments -->
      <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-4">
        <div
          v-for="seg in segments"
          :key="seg.index"
          class="bg-white dark:bg-neutral-800 rounded-2xl border border-neutral-200/80 dark:border-neutral-700/60 overflow-hidden"
        >
          <!-- Segment header -->
          <div class="px-5 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-bold">{{ seg.index + 1 }}</span>
              <div>
                <span class="text-xs font-bold uppercase tracking-wider text-neutral-900 dark:text-neutral-100">{{ seg.label }}</span>
                <p v-if="seg.concept" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-1">{{ seg.concept }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button @click="openConfig(seg)" class="w-7 h-7 flex items-center justify-center rounded-lg text-neutral-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors" title="Configure references & settings">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </button>
              <span :class="[
                'text-[11px] font-medium px-2.5 py-1 rounded-full',
                seg.status === 'done' ? 'bg-green-500/10 text-green-600 dark:text-green-400' :
                seg.status === 'generating' || seg.status === 'rewriting_vd' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' :
                seg.status === 'failed' ? 'bg-red-500/10 text-red-600 dark:text-red-400' :
                'bg-neutral-500/10 text-neutral-500 dark:text-neutral-400'
              ]">
                {{ seg.status === 'done' ? 'Generated' : seg.status === 'rewriting_vd' ? 'Rewriting VD...' : seg.status === 'generating' ? 'Generating...' : seg.status === 'failed' ? 'Failed' : 'Pending' }}
              </span>
            </div>
          </div>

          <div class="border-t border-neutral-100 dark:border-neutral-700/40"></div>

          <!-- Segment body: 2-column grid -->
          <div class="grid grid-cols-1 lg:grid-cols-5 gap-0">
            <!-- Left: Prompt fields (3 cols) -->
            <div class="lg:col-span-3 p-5 space-y-4">
              <!-- Visual Direction -->
              <div>
                <label class="block text-[11px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider mb-1.5">Visual Direction</label>
                <textarea v-model="seg.visual_direction" @input="scheduleAutoSave" rows="4" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-neutral-200 dark:border-neutral-600/60 bg-neutral-50 dark:bg-neutral-900/50 text-neutral-900 dark:text-neutral-100 focus:ring-1 focus:ring-amber-500/50 focus:border-amber-500/50 placeholder-neutral-400 dark:placeholder-neutral-600 resize-none transition-colors" placeholder="Describe the scene, lighting, camera angle, color palette, mood...&#10;e.g. Cinematic wide shot of a futuristic workspace, warm golden light, dark moody atmosphere, cyan accent lighting"></textarea>
              </div>

              <!-- Config pills row -->
              <div class="flex flex-wrap items-center gap-2 pt-1">
                <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-900/50 border border-neutral-200/60 dark:border-neutral-700/40">
                  <span class="text-[10px] text-neutral-400 uppercase tracking-wider">Style</span>
                  <select v-model="seg.style" @change="scheduleAutoSave" class="bg-transparent text-xs font-medium text-neutral-700 dark:text-neutral-300 border-0 p-0 pr-5 focus:ring-0 cursor-pointer">
                    <option v-for="s in styleOptions" :key="s" :value="s">{{ s }}</option>
                  </select>
                </div>
                <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-900/50 border border-neutral-200/60 dark:border-neutral-700/40">
                  <span class="text-[10px] text-neutral-400 uppercase tracking-wider">Model</span>
                  <select v-model="seg.model" @change="scheduleAutoSave" class="bg-transparent text-xs font-medium text-neutral-700 dark:text-neutral-300 border-0 p-0 pr-5 focus:ring-0 cursor-pointer">
                    <option v-for="m in modelOptions" :key="m" :value="m">{{ m }}</option>
                  </select>
                </div>
                <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-900/50 border border-neutral-200/60 dark:border-neutral-700/40">
                  <span class="text-[10px] text-neutral-400 uppercase tracking-wider">Ratio</span>
                  <select v-model="seg.aspect_ratio" @change="scheduleAutoSave" class="bg-transparent text-xs font-medium text-neutral-700 dark:text-neutral-300 border-0 p-0 pr-5 focus:ring-0 cursor-pointer">
                    <option v-for="r in ratioOptions" :key="r" :value="r">{{ r }}</option>
                  </select>
                </div>
              </div>

              <!-- Applied-config chips (face refs, style refs, notes) -->
              <div
                v-if="hasSegmentConfig(seg)"
                class="flex flex-wrap items-center gap-2 pt-3 mt-1 border-t border-neutral-100 dark:border-neutral-700/40"
              >
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider">Config</span>

                <template v-for="(url, i) in (seg.face_refs || [])" :key="'face-' + i">
                  <img
                    v-if="url && !url.startsWith('blob:')"
                    :src="url"
                    :title="`Face reference ${i + 1} — click to preview`"
                    class="w-6 h-6 rounded-full object-cover border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:ring-2 hover:ring-amber-400 transition"
                    @click="previewConfigImage(url, `Face reference ${i + 1}`)"
                  />
                </template>

                <template v-for="(url, i) in (seg.style_refs || [])" :key="'style-' + i">
                  <img
                    v-if="url && !url.startsWith('blob:')"
                    :src="url"
                    :title="`Style reference ${i + 1} — click to preview`"
                    class="w-10 h-7 rounded object-cover border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:ring-2 hover:ring-amber-400 transition"
                    @click="previewConfigImage(url, `Style reference ${i + 1}`)"
                  />
                </template>

                <span
                  v-if="(seg.additional_notes || '').trim()"
                  :title="seg.additional_notes"
                  class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] rounded-full bg-neutral-100 dark:bg-neutral-900/50 text-neutral-600 dark:text-neutral-400 border border-neutral-200/60 dark:border-neutral-700/40 cursor-help"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-7-8h8a2 2 0 012 2v10a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                  Notes
                </span>

                <span
                  v-if="seg.visual_direction_original"
                  title="Visual Direction was auto-rewritten to match the face reference"
                  class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                >
                  VD rewritten
                </span>
              </div>

            </div>

            <!-- Right: Image preview (2 cols) -->
            <div class="lg:col-span-2 p-5 lg:border-l border-t lg:border-t-0 border-neutral-100 dark:border-neutral-700/40 flex flex-col">
              <div :class="['relative rounded-2xl overflow-hidden bg-neutral-100 dark:bg-neutral-900/60 border border-neutral-200/60 dark:border-neutral-700/40 flex-1 min-h-[200px]', seg.aspect_ratio === '16:9' ? 'aspect-video' : seg.aspect_ratio === '1:1' ? 'aspect-square' : seg.aspect_ratio === '9:16' ? 'aspect-[9/16] max-h-80' : 'aspect-[4/3]']">
                <!-- Generated image -->
                <img
                  v-if="seg.generated_url && seg.status === 'done'"
                  :src="seg.generated_url"
                  :alt="seg.concept"
                  class="w-full h-full object-cover cursor-zoom-in transition-opacity hover:opacity-90"
                  @click="openLightbox(seg)"
                  title="Click to expand"
                />

                <!-- Generating / Rewriting VD -->
                <div v-else-if="seg.status === 'generating' || seg.status === 'rewriting_vd'" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
                  <div class="relative">
                    <div class="w-12 h-12 rounded-full border-2 border-amber-500/20"></div>
                    <div class="absolute inset-0 w-12 h-12 rounded-full border-2 border-transparent border-t-amber-500 animate-spin"></div>
                  </div>
                  <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ seg.status === 'rewriting_vd' ? 'Rewriting visual direction...' : 'Generating image...' }}</span>
                </div>

                <!-- Failed -->
                <div v-else-if="seg.status === 'failed'" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                  </div>
                  <span class="text-xs text-red-400">Failed to generate</span>
                  <button @click="generateSingle(seg.index)" class="text-xs text-amber-500 hover:text-amber-400 font-medium">Retry</button>
                </div>

                <!-- Pending -->
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-4">
                  <div class="w-16 h-16 rounded-2xl bg-neutral-200/50 dark:bg-neutral-800/50 flex items-center justify-center">
                    <svg class="w-7 h-7 text-neutral-300 dark:text-neutral-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                    </svg>
                  </div>
                  <button @click="generateSingle(seg.index)" class="px-4 py-2 text-xs font-medium rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition-colors active:scale-[0.98]">
                    Generate Image
                  </button>
                </div>
              </div>

              <!-- Regenerate (shown only when image exists) -->
              <button
                v-if="seg.status === 'done'"
                @click="generateSingle(seg.index)"
                class="mt-3 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium rounded-xl border border-neutral-200 dark:border-neutral-700/60 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors active:scale-[0.98]"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                Regenerate
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="sticky bottom-0 z-30 bg-white dark:bg-neutral-800 border-t border-neutral-200 dark:border-neutral-700 shadow-[0_-2px_10px_rgba(0,0,0,0.05)]">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
          <span class="text-sm text-neutral-500 dark:text-neutral-400">
            {{ doneCount }}/{{ totalCount }} images generated
          </span>
          <button @click="handleApprove" :disabled="!allDone" class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">
            Approve Images &amp; Continue
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </template>

    <!-- Config Modal -->
    <ImageConfigModal
      :visible="!!configSegment"
      :segment="configSegment"
      @apply="handleConfigApply"
      @close="configSegment = null"
    />

    <!-- Image Lightbox -->
    <BaseLightbox
      :show="lightboxOpen"
      :current-image="lightboxImage"
      :current-title="lightboxTitle"
      :current-index="lightboxCurrentIdx"
      :total-items="lightboxTotal"
      :download-filename="lightboxFilename"
      @close="closeLightbox"
      @prev="lightboxPrev"
      @next="lightboxNext"
    />
  </div>
</template>
