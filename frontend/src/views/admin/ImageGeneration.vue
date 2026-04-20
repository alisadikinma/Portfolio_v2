<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'
import PipelineStepBar from '@/components/admin/PipelineStepBar.vue'
import ImageConfigModal from '@/components/admin/ImageConfigModal.vue'
import BaseLightbox from '@/components/base/BaseLightbox.vue'
import StockImageSearch from '@/components/admin/StockImageSearch.vue'
import EntityUploadSlot from '@/components/admin/EntityUploadSlot.vue'

const route = useRoute()
const router = useRouter()
const { getIdea, saveDraft, generateSegmentImage, retrySegment, skipSegment, regenerateImagePrompts, rewriteSegmentVd, resumeImagePipeline, downloadStockImage, isLoading } = useContentEngine()
const MAX_SEGMENT_ATTEMPTS = 3
const toast = useToast()

const idea = ref(null)
const loadError = ref(null)
const segments = ref([])
const generatingAll = ref(false)
const configSegment = ref(null) // segment being configured in modal
const variantConfirmSeg = ref(null) // segment awaiting variant-confirm modal
const variantExtraNote = ref('')
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
// ── Branded filename helpers (loaded from creator_brand settings on mount) ──
const brandSlug = ref('alisadikinma')

function slugify(str) {
  return (str || '').toString().toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    || 'image'
}

// NOTE: seg.index MUST equal the segment's 0-based position in image_prompts[] —
// the backend's ImageGenerationService::buildBrandedFilename uses the array index
// with the same label contract ('cover' for index 0, 'body-N' for inline).
// Any filtered subset must preserve seg.index; do NOT reassign indices here.
function computeBrandedFilename(seg) {
  if (!seg) return 'image.png'
  const keywordSource = idea.value?.generated_article?.prep_data?.research?.keyword
    || idea.value?.title
    || 'image'
  const keywordSlug = slugify(keywordSource)
  const label = seg.index === 0 ? 'cover' : `body-${seg.index}`
  return `${brandSlug.value}-${keywordSlug}-${label}.png`
}

const lightboxFilename = computed(() => {
  if (adHocPreview.value) {
    const name = adHocPreview.value.url.split('/').pop() || 'reference.jpg'
    return name.split('?')[0]
  }
  const s = generatedSegments.value[lightboxIndex.value]
  if (!s) return ''
  return computeBrandedFilename(s)
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
  const brands = (seg.brand_refs || []).filter(r => (r?.url || r) && !(r?.url || r).startsWith('blob:'))
  return faces.length > 0 || styles.length > 0 || brands.length > 0 || (seg.additional_notes || '').trim().length > 0
}
let saveTimeout = null
let pollInterval = null

// ── Style/Model/Ratio options (defaults: Photorealistic, nano-banana-pro, 16:9) ──
const styleOptions = ['Photorealistic', 'Cinematic', 'Portrait Cinematic', 'Minimal', 'Abstract', 'Illustration']
const modelOptions = ['nano-banana-pro', 'nano-banana-2', 'imagen-4']
const ratioOptions = ['16:9', '4:3', '1:1', '9:16']

// ── Load idea + brand slug ──
async function loadBrandSlug() {
  try {
    const { default: api } = await import('@/services/api')
    const res = await api.get('/admin/settings/creator-brand')
    if (res.data?.success && res.data.data?.creator_brand_slug) {
      brandSlug.value = res.data.data.creator_brand_slug
    }
  } catch (err) {
    console.warn('[ImageGeneration] creator_brand slug fetch failed — falling back to default', err)
  }
}

onMounted(async () => {
  const id = route.params.id
  const [result] = await Promise.all([getIdea(id), loadBrandSlug()])
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

// ── Brand manifest mapping ──
function mapManifestToSegment(manifest, img, segIndex) {
  if (!manifest || !Array.isArray(manifest)) return []
  const segLabel = img.type === 'cover' ? 'cover' : `inline-${segIndex}`
  return manifest.filter(item => {
    const usedIn = item.used_in || []
    // Match if used_in references this segment's label/index, or is global (empty)
    return usedIn.length === 0 || usedIn.some(u =>
      u === segLabel || u === `segment-${segIndex}` || u === String(segIndex)
    )
  })
}

const isAwaitingBrand = computed(() => idea.value?.status === 'awaiting_brand_images')

// Phase H: named-entity-aware cover flow
const isAwaitingManualUpload = computed(() => idea.value?.status === 'awaiting_manual_upload')

const pendingEntityManifest = computed(() => {
  const manifest = idea.value?.pending_manifest || idea.value?.generated_article?.brand_manifest
  if (!manifest || typeof manifest !== 'object') return []
  return Array.isArray(manifest.entity) ? manifest.entity : []
})

async function reloadIdeaState() {
  const id = route.params.id
  const result = await getIdea(id)
  if (result.success && result.data) {
    idea.value = result.data
    initSegments()
  }
}

async function handleEntityResolved() {
  await reloadIdeaState()
}

async function handleEntitySkipped() {
  await reloadIdeaState()
}

// Trim inline auto-caption to max ~80 chars on a word boundary, with an
// ellipsis. Strips trailing punctuation before appending so we don't get
// ",…" or ";…" glued together. Only runs on the auto-default path — once
// a user edits and saves, we keep whatever they wrote verbatim.
function shortenCaption(text, maxLen = 80) {
  const s = (text || '').trim()
  if (s.length <= maxLen) return s
  const slice = s.slice(0, maxLen - 1)
  const lastSpace = slice.lastIndexOf(' ')
  const base = lastSpace > maxLen * 0.5 ? slice.slice(0, lastSpace) : slice
  return base.replace(/[.,;:\-–—]+$/, '').trim() + '…'
}

function initSegments() {
  const article = idea.value?.generated_article
  if (!article?.image_prompts) return

  const isUsableRef = (u) => typeof u === 'string' && u.length > 0 && !u.startsWith('blob:')

  segments.value = article.image_prompts.map((img, i) => {
    // Migrate legacy flat generated_url to variations[]
    let variations = img.variations || []
    if (variations.length === 0 && img.generated_url) {
      variations = [{ url: img.generated_url, job_uuid: img.job_uuid || null, status: 'done' }]
    }

    // Normalize stuck variations
    variations = variations.map(v => {
      if (v.status === 'generating' && !v.job_uuid) return { ...v, status: 'failed' }
      return v
    })

    const selectedVar = img.selected_variation ?? 0
    const activeVar = variations[selectedVar]
    const hasAnyDone = variations.some(v => v.status === 'done' && v.url)
    const anyGenerating = variations.some(v => v.status === 'generating')

    // Segment-level status: generating if any variation is generating, done if any done,
    // failed when variations exist but all failed, else fall back to DB / pending.
    let segStatus = img.status || 'pending'
    if (anyGenerating) segStatus = 'generating'
    else if (hasAnyDone) segStatus = 'done'
    else if (variations.length > 0 && variations.every(v => v.status === 'failed')) segStatus = 'failed'
    else if (segStatus === 'generating' && !img.job_uuid) segStatus = 'failed'

    // Auto-fill caption when plugin didn't author one. Cover → article title (full);
    // inline → concept trimmed to ≤80 chars on a word boundary so figcaptions stay
    // tight and powerful. User edits win on next save via persistDraft and stick on reload.
    const autoCaption = img.type === 'cover'
      ? (article.title || idea.value?.title || '')
      : shortenCaption(img.concept || '', 80)

    return {
      ...img,
      index: i,
      visual_direction: img.visual_direction || img.prompt || '',
      visual_direction_original: img.visual_direction_original || '',
      caption: img.caption || autoCaption,
      needs_creator_face: img.needs_creator_face ?? false,
      style: img.style || 'Photorealistic',
      model: img.model || 'nano-banana-pro',
      aspect_ratio: img.aspect_ratio || '16:9',
      reference_image_url: img.reference_image_url || '',
      face_refs: (img.face_refs || []).filter(isUsableRef),
      style_refs: (img.style_refs || []).filter(isUsableRef),
      brand_refs: (img.brand_refs || []).filter(isUsableRef),
      brand_manifest_items: mapManifestToSegment(article.brand_manifest, img, i),
      additional_notes: img.additional_notes || '',
      variations,
      selected_variation: selectedVar,
      generated_url: activeVar?.url || '',
      status: segStatus,
      label: img.type === 'cover' ? 'COVER' : `BODY-${i}`,
      // Phase B: per-segment retry state. Surfaced in the UI as a small
      // "Attempt N/3" badge + a Retry/Skip button pair.
      retry_count: Number(img.retry_count || 0),
      terminal_at: img.terminal_at || null,
      failure_history: Array.isArray(img.failure_history) ? img.failure_history : [],
      // Named-entity refs carried onto the segment so the "Regenerate
      // Prompt" nudge can detect when a cover likely needs a fresh
      // Wikimedia lookup (public figure / landmark on cover but
      // entity_refs[] is empty — symptom: creator face keeps getting
      // prepended as a fallback).
      entity_refs: Array.isArray(img.entity_refs) ? img.entity_refs : [],
    }
  })
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
    // Destructure OUT webhook-managed fields so auto-save can't overwrite them with stale data.
    // Backend retains whatever the webhook/controller last set for these fields.
    const { variations, status, job_uuid, generated_url, error, ...safeExisting } = existingPrompts[i] || {}
    return {
      ...safeExisting,
      type: seg.type,
      section: seg.section,
      concept: seg.concept,
      caption: seg.caption || '',
      needs_creator_face: seg.needs_creator_face ?? false,
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
      brand_refs: seg.brand_refs || [],
      additional_notes: seg.additional_notes || '',
      selected_variation: seg.selected_variation ?? 0,
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

// Opens the variant confirmation modal. Lets user tack on a one-off
// instruction for this attempt only (won't pollute seg.additional_notes)
// and explicitly confirm before firing the API call + spending tokens.
function openVariantConfirm(segIndex) {
  const seg = segments.value[segIndex]
  if (!seg) return
  if ((seg.variations || []).length >= 3) {
    toast.error('Maximum 3 variations per segment')
    return
  }
  variantConfirmSeg.value = seg
  variantExtraNote.value = ''
}

function closeVariantConfirm() {
  variantConfirmSeg.value = null
  variantExtraNote.value = ''
}

async function confirmVariantGenerate() {
  const seg = variantConfirmSeg.value
  if (!seg) return
  const extra = variantExtraNote.value.trim()
  const savedNotes = seg.additional_notes
  // Merge one-off instruction with existing notes for this single call.
  // Restore original notes after dispatch so the extra doesn't stick.
  if (extra) {
    seg.additional_notes = savedNotes ? `${savedNotes}\n${extra}` : extra
  }
  closeVariantConfirm()
  try {
    await generateSingle(seg.index)
  } finally {
    if (extra) seg.additional_notes = savedNotes
  }
}

async function generateSingle(segIndex) {
  const seg = segments.value[segIndex]
  if (!seg) return

  // Enforce max 3 variations
  if ((seg.variations || []).length >= 3) {
    toast.error('Maximum 3 variations per segment')
    return
  }

  // Cancel any pending auto-save to prevent it from overwriting the job_uuid
  if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null }

  seg.status = 'generating'
  const prompt = getPrompt(seg)

  // Optimistic placeholder: show a spinner in the variation strip (and in the
  // main preview when no existing image is set) the moment the user clicks
  // Generate, instead of waiting for the API round-trip to come back. Without
  // this the "+" drop-down "Generate AI" click looked dead until the next
  // poll tick — user had to refresh the browser to see any loading feedback.
  if (!seg.variations) seg.variations = []
  const optimisticIdx = seg.variations.length
  seg.variations.push({ url: null, job_uuid: null, status: 'generating', pending: true })

  const result = await generateSegmentImage(idea.value.id, {
    segment_index: segIndex,
    prompt,
    style: seg.style,
    model: seg.model,
    aspect_ratio: seg.aspect_ratio,
    face_refs: seg.face_refs || [],
    style_refs: seg.style_refs || [],
    brand_refs: seg.brand_refs || [],
    additional_notes: seg.additional_notes || '',
  })

  if (result.success) {
    const uuid = result.data?.uuid
    const varIdx = result.data?.variation_index ?? optimisticIdx
    // Drop the optimistic placeholder, then write the server-reported slot.
    // Backend uses retry-in-place — on retry it may return an earlier index
    // (reusing a failed slot) rather than appending. Mirror that locally so
    // the UI matches the DB; otherwise the placeholder lingers until refresh.
    seg.variations.splice(optimisticIdx, 1)
    if (varIdx < seg.variations.length) {
      seg.variations[varIdx] = { url: null, job_uuid: uuid, status: 'generating' }
    } else {
      seg.variations.push({ url: null, job_uuid: uuid, status: 'generating' })
    }
    seg.selected_variation = varIdx
    seg.job_uuid = uuid
    toast.success(`Image variation ${varIdx + 1} generation started`)
  } else {
    // API rejected — remove the optimistic placeholder so the strip goes back
    // to its pre-click state, then surface the error.
    seg.variations.splice(optimisticIdx, 1)
    const anyGenerating = (seg.variations || []).some(v => v.status === 'generating')
    seg.status = anyGenerating ? 'generating' : (seg.variations?.length ? 'done' : 'failed')
    toast.error(result.error || `Failed to generate image ${segIndex + 1}`)
  }
}

function selectVariation(seg, varIdx) {
  if (!seg.variations?.[varIdx] || seg.variations[varIdx].status !== 'done') return
  seg.selected_variation = varIdx
  seg.generated_url = seg.variations[varIdx].url
  scheduleAutoSave()
}

// ── Phase B: Retry + Skip for failed segments ──

async function retryFailedSegment(seg) {
  if (!idea.value?.id) return
  if ((seg.retry_count || 0) >= MAX_SEGMENT_ATTEMPTS) {
    toast.error(`Retry cap reached (${MAX_SEGMENT_ATTEMPTS}). Use Skip or Regenerate Prompt instead.`)
    return
  }

  const priorStatus = seg.status
  seg.status = 'generating'
  if (!seg.variations) seg.variations = []
  const optimisticIdx = seg.variations.length
  seg.variations.push({ url: null, job_uuid: null, status: 'generating', pending: true })

  const result = await retrySegment(idea.value.id, seg.index)

  if (result.success) {
    seg.variations.splice(optimisticIdx, 1)
    seg.variations.push({ url: null, job_uuid: result.data?.uuid, status: 'generating' })
    seg.retry_count = result.data?.retry_count ?? (seg.retry_count + 1)
    seg.terminal_at = null
    seg.job_uuid = result.data?.uuid
    toast.success(`Retry ${seg.retry_count}/${MAX_SEGMENT_ATTEMPTS} dispatched for ${seg.label}`)
  } else {
    seg.variations.splice(optimisticIdx, 1)
    seg.status = priorStatus
    toast.error(result.error || `Retry failed for ${seg.label}`)
  }
}

async function skipFailedSegment(seg) {
  if (!idea.value?.id) return

  const isCover = seg.index === 0
  const force = isCover
    ? window.confirm(
        `Skip the COVER segment? The article will publish with no cover image ` +
        `and Ali's creator face will not appear on the featured position. ` +
        `This has high blast radius — confirm only if you're sure.`
      )
    : true

  if (isCover && !force) return

  const result = await skipSegment(idea.value.id, seg.index, { force })

  if (result.success) {
    seg.status = 'skipped'
    seg.terminal_at = result.data?.terminal_at || new Date().toISOString()
    seg.failure_history = [
      ...(seg.failure_history || []),
      { attempt: seg.retry_count || 0, uuid: null, reason: 'user_skip', timestamp: seg.terminal_at },
    ]
    toast.success(`${seg.label} skipped — idea can now advance when other segments resolve`)
  } else {
    toast.error(result.error || `Skip failed for ${seg.label}`)
  }
}

function canRetrySegment(seg) {
  if (seg.status !== 'failed') return false
  if ((seg.retry_count || 0) >= MAX_SEGMENT_ATTEMPTS) return false
  return true
}

function canSkipSegment(seg) {
  if (seg.status === 'done' || seg.status === 'skipped') return false
  // Block skip while a GeminiGen job is in flight — backend also enforces
  // this with 409 SEGMENT_STILL_GENERATING, but hiding the button avoids
  // confusing the operator in the first place.
  if (seg.status === 'generating') return false
  return seg.status === 'failed' || (seg.failure_history || []).length > 0
}

function failureHistoryTooltip(seg) {
  const history = seg.failure_history || []
  if (!history.length) return ''
  return history
    .map((h) => `#${h.attempt}: ${h.reason || 'unknown'} (${h.timestamp?.slice(0, 19).replace('T', ' ') || ''})`)
    .join('\n')
}

// Regenerate Prompt: runs the full plugin /article-images skill for this one
// section, which triggers fresh NER + Wikidata/Commons entity lookup. Use
// this when Retry keeps reusing cached refs that are wrong or empty — e.g.
// when an article about a public figure lands with an empty entity_refs[]
// because the original NER run missed the subject.
async function regenerateSegmentPrompt(seg) {
  if (!idea.value?.id) return

  const confirmed = window.confirm(
    `Regenerate prompt for ${seg.label}?\n\n` +
    `This re-runs the article-images skill via SSH (~30s) and refreshes entity ` +
    `references from Wikipedia / Wikimedia. Use this when the image keeps ` +
    `showing the wrong face (e.g. generic creator face instead of the actual ` +
    `public figure named in the article).\n\n` +
    `The current Visual Direction and prompt text will be overwritten.`
  )
  if (!confirmed) return

  const priorStatus = seg.status
  seg.status = 'rewriting_vd' // re-use this for "prompt authoring in progress" UX

  const result = await regenerateImagePrompts(idea.value.id, [seg.index])

  if (result.success) {
    toast.success(`${seg.label} prompt regeneration triggered — NER + Wikimedia lookup running in background. Refresh in ~30s.`)
  } else {
    seg.status = priorStatus
    toast.error(result.error || `Regenerate prompt failed for ${seg.label}`)
  }
}

// Heuristic: show a subtle "missing entity refs?" nudge when the segment's
// VD or caption mentions a proper-noun pattern but entity_refs[] is empty.
// Only surfaced on covers, since inline images aren't gated on entity refs.
function shouldNudgeEntityRefresh(seg) {
  if (seg.index !== 0) return false
  if ((seg.entity_refs || []).length > 0) return false

  const text = `${seg.visual_direction || ''} ${seg.caption || ''} ${idea.value?.title || ''}`
  // Match CEO/President/Prime Minister patterns, or Title-Case multi-word proper
  // nouns that typically indicate public figures or landmarks. This is a soft
  // signal — false positives are fine, operator decides to act or ignore.
  const hasTitle = /\b(CEO|CTO|CFO|President|Minister|Founder|Senator|Governor)\b/i.test(text)
  const hasMultiCapWord = /\b[A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?\b/.test(text)
  return hasTitle || hasMultiCapWord
}

// ── Stock image as variation ──
const stockSearchSegIndex = ref(-1) // which segment is showing stock search
const stockDownloading = ref(false)

function openStockSearch(segIndex) {
  stockSearchSegIndex.value = segIndex
}

function closeStockSearch() {
  stockSearchSegIndex.value = -1
}

async function handleStockImageSelect(imageUrl, segIndex) {
  const seg = segments.value[segIndex]
  if (!seg || (seg.variations || []).length >= 3) return

  stockDownloading.value = true
  const result = await downloadStockImage(imageUrl)
  stockDownloading.value = false

  if (!result.success || !result.data?.url) {
    toast.error(result.error || 'Failed to download stock image')
    return
  }

  const localUrl = result.data.url
  if (!seg.variations) seg.variations = []
  const varIdx = seg.variations.length
  seg.variations.push({ url: localUrl, job_uuid: null, status: 'done', source: 'stock' })
  seg.selected_variation = varIdx
  seg.generated_url = localUrl
  seg.status = 'done'
  stockSearchSegIndex.value = -1
  toast.success('Stock image added as variation')
  scheduleAutoSave()
}

// ── Poll for generation completion ──
function startPolling() {
  pollInterval = setInterval(async () => {
    // Check both segment status AND variation status. A segment can have
    // status='done' (one variation finished) while another variation is still
    // 'generating' — e.g. user clicked "+" to add a second variant. Without
    // the variation-level check, a race inside this same poll loop (stale
    // local variations mid-generateSingle) can flip seg.status to 'done'
    // based on the existing done variation, after which polling bails out
    // and the new variation stays stuck on the spinner until page refresh.
    const generating = segments.value.some(s =>
      s.status === 'generating' ||
      s.status === 'rewriting_vd' ||
      (s.variations || []).some(v => v.status === 'generating')
    )
    if (!generating) return

    const result = await getIdea(idea.value.id)
    if (!result.success) return

    idea.value = result.data
    const updatedPrompts = result.data.generated_article?.image_prompts || []
    for (let i = 0; i < updatedPrompts.length && i < segments.value.length; i++) {
      const updated = updatedPrompts[i]
      const seg = segments.value[i]
      const updatedVars = updated.variations || []

      // Sync each variation's status from backend
      for (const uv of updatedVars) {
        if (!uv.job_uuid) continue
        const localVar = (seg.variations || []).find(v => v.job_uuid === uv.job_uuid)
        if (!localVar || localVar.status !== 'generating') continue
        if (uv.status === 'done' && uv.url) {
          localVar.url = uv.url
          localVar.status = 'done'
        } else if (uv.status === 'failed') {
          localVar.status = 'failed'
        }
      }

      // Update segment-level status
      const anyGenerating = (seg.variations || []).some(v => v.status === 'generating')
      const anyDone = (seg.variations || []).some(v => v.status === 'done' && v.url)
      const vars = seg.variations || []
      if (anyGenerating) {
        seg.status = 'generating'
      } else if (anyDone) {
        seg.status = 'done'
        // Set generated_url to selected variation
        const selected = seg.variations[seg.selected_variation ?? 0]
        if (selected?.url) seg.generated_url = selected.url
        // Auto-select first done if selected is empty
        if (!seg.generated_url) {
          const firstDone = seg.variations.findIndex(v => v.status === 'done' && v.url)
          if (firstDone >= 0) {
            seg.selected_variation = firstDone
            seg.generated_url = seg.variations[firstDone].url
          }
        }
      } else if (vars.length > 0 && vars.every(v => v.status === 'failed')) {
        // All variations failed → surface failure so user can retry
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
  seg.brand_refs = options.brandRefs || []
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

// ── Resume pipeline (after brand image upload) ──
async function handleResumePipeline() {
  if (!idea.value) return
  await persistDraft()
  const result = await resumeImagePipeline(idea.value.id)
  if (result.success) {
    toast.success('Pipeline resumed — generating image prompts')
    idea.value.status = 'generating_images'
  } else {
    toast.error(result.error || 'Failed to resume pipeline')
  }
}

// ── Approve & continue ──
// Intentionally does NOT compact variations or delete files — that cleanup runs
// server-side in approveAndPublish at the final Publish step. This way, users can
// navigate back from Finalize to re-review/change their selection without losing
// the other variations.
async function handleApprove() {
  await persistDraft()
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

      <!-- Phase H: Awaiting named-entity upload banner (public figures / landmarks) -->
      <div v-if="isAwaitingManualUpload && pendingEntityManifest.length > 0" class="max-w-5xl mx-auto px-4 sm:px-6 pt-4">
        <div class="rounded-xl border border-red-300 dark:border-red-800/40 bg-red-50/50 dark:bg-red-900/10 p-4">
          <div class="mb-3">
            <p class="text-sm font-semibold text-red-800 dark:text-red-300">
              Named entity references required
            </p>
            <p class="text-xs text-red-700 dark:text-red-400 mt-0.5">
              The plugin detected specific public figures or landmarks in this article.
              Upload reference photos (or skip to use creator face fallback) before generating images.
            </p>
          </div>
          <div class="space-y-2">
            <EntityUploadSlot
              v-for="entity in pendingEntityManifest"
              :key="entity.entity_name"
              :idea-id="idea.id"
              :entity-name="entity.entity_name"
              :entity-type="entity.entity_type"
              :status="entity.status"
              :fetched-url="entity.fetched_url || null"
              :license="entity.license || null"
              :reason="entity.reason || null"
              :required="entity.required !== false"
              @resolved="handleEntityResolved"
              @skipped="handleEntitySkipped"
            />
          </div>
        </div>
      </div>

      <!-- Awaiting Brand Images Banner -->
      <div v-if="isAwaitingBrand" class="max-w-5xl mx-auto px-4 sm:px-6 pt-4">
        <div class="flex items-center justify-between gap-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            </div>
            <div>
              <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Brand images needed</p>
              <p class="text-xs text-amber-600 dark:text-amber-400">Upload required brand/product images in each segment's config, then click Resume.</p>
            </div>
          </div>
          <button @click="handleResumePipeline" :disabled="isLoading" class="px-4 py-2 text-xs font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50 flex-shrink-0">
            Resume Pipeline
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
              <!-- Retry count badge (visible after at least one failure attempt) -->
              <span
                v-if="(seg.retry_count || 0) > 0"
                :title="failureHistoryTooltip(seg)"
                class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-red-500/5 text-red-600/80 dark:text-red-400/80 border border-red-500/20"
              >
                Attempt {{ seg.retry_count }}/{{ MAX_SEGMENT_ATTEMPTS }}
              </span>

              <!-- Retry button: reuse cached prompt + refs, just re-dispatch -->
              <button
                v-if="canRetrySegment(seg)"
                @click="retryFailedSegment(seg)"
                :disabled="isLoading"
                class="px-2.5 py-1 text-[11px] font-medium rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition-colors disabled:opacity-50"
                title="Retry: reuse cached prompt + refs, new GeminiGen attempt (fast, no SSH)"
              >
                Retry
              </button>

              <!-- Regenerate Prompt button: re-run /article-images skill with
                   fresh NER + Wikimedia lookup. Use when cached entity_refs
                   are empty/wrong and Retry keeps falling back to creator
                   face. Subtle amber "!" nudge when heuristic detects likely
                   missing entity refs (public figure / landmark on cover). -->
              <button
                v-if="seg.status === 'failed' || shouldNudgeEntityRefresh(seg)"
                @click="regenerateSegmentPrompt(seg)"
                :disabled="isLoading || seg.status === 'rewriting_vd'"
                class="px-2.5 py-1 text-[11px] font-medium rounded-lg bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20 hover:bg-cyan-500/20 transition-colors disabled:opacity-50 inline-flex items-center gap-1"
                title="Regenerate Prompt: re-run /article-images via SSH — refreshes NER + Wikimedia entity lookup. Use when cached refs are wrong (e.g. missing public figure / landmark photo)"
              >
                <span v-if="shouldNudgeEntityRefresh(seg)" class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                Regen Prompt
              </button>

              <!-- Skip button: for failed or history-laden segments -->
              <button
                v-if="canSkipSegment(seg)"
                @click="skipFailedSegment(seg)"
                :disabled="isLoading"
                class="px-2.5 py-1 text-[11px] font-medium rounded-lg bg-neutral-500/10 text-neutral-600 dark:text-neutral-300 border border-neutral-400/30 hover:bg-neutral-500/20 transition-colors disabled:opacity-50"
                :title="seg.index === 0 ? 'Skip cover (high blast radius — confirms before submit)' : 'Skip: mark as skipped so idea can advance'"
              >
                Skip
              </button>

              <button @click="openConfig(seg)" class="w-7 h-7 flex items-center justify-center rounded-lg text-neutral-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors" title="Configure references & settings">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </button>
              <span :class="[
                'text-[11px] font-medium px-2.5 py-1 rounded-full',
                seg.status === 'done' ? 'bg-green-500/10 text-green-600 dark:text-green-400' :
                seg.status === 'generating' || seg.status === 'rewriting_vd' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' :
                seg.status === 'failed' ? 'bg-red-500/10 text-red-600 dark:text-red-400' :
                seg.status === 'skipped' ? 'bg-neutral-500/10 text-neutral-500 dark:text-neutral-400' :
                'bg-neutral-500/10 text-neutral-500 dark:text-neutral-400'
              ]">
                {{ seg.status === 'done' ? 'Generated' : seg.status === 'rewriting_vd' ? 'Rewriting VD...' : seg.status === 'generating' ? 'Generating...' : seg.status === 'failed' ? 'Failed' : seg.status === 'skipped' ? 'Skipped' : 'Pending' }}
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

              <!-- Caption (per-type placeholder + helper) -->
              <div>
                <label class="flex items-center justify-between mb-1.5">
                  <span class="text-[11px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Caption</span>
                  <span class="text-[10px] text-neutral-400 dark:text-neutral-500">{{ (seg.caption || '').length }} / 150</span>
                </label>
                <input
                  v-model="seg.caption"
                  @input="scheduleAutoSave"
                  type="text"
                  maxlength="150"
                  :placeholder="seg.type === 'cover'
                    ? 'Article title (or lightly SEO-paraphrased with primary keyword)'
                    : '5-12 words of supporting context (e.g. \u201cTerminal output after first successful generation\u201d)'"
                  class="w-full px-3.5 py-2 text-sm rounded-xl border border-neutral-200 dark:border-neutral-600/60 bg-neutral-50 dark:bg-neutral-900/50 text-neutral-900 dark:text-neutral-100 focus:ring-1 focus:ring-amber-500/50 focus:border-amber-500/50 placeholder-neutral-400 dark:placeholder-neutral-600 transition-colors"
                />
                <p class="mt-1 text-[10px] text-neutral-400 dark:text-neutral-500">
                  <template v-if="seg.type === 'cover'">Defaults to the article title. Shown as the figcaption under the hero image on the blog post.</template>
                  <template v-else>Short figcaption below the image. Do not repeat the article title or section heading.</template>
                </p>
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

                <template v-for="(ref, i) in (seg.brand_refs || [])" :key="'brand-' + i">
                  <img
                    v-if="(ref?.url || ref) && !(ref?.url || ref).startsWith('blob:')"
                    :src="ref?.url || ref"
                    :title="`Brand: ${ref?.filename || 'reference ' + (i + 1)} — click to preview`"
                    class="w-8 h-8 rounded object-cover border border-blue-200 dark:border-blue-700 cursor-pointer hover:ring-2 hover:ring-blue-400 transition"
                    @click="previewConfigImage(ref?.url || ref, `Brand: ${ref?.filename || 'reference ' + (i + 1)}`)"
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

            <!-- Right: Image preview + variations (2 cols) -->
            <div class="lg:col-span-2 p-5 lg:border-l border-t lg:border-t-0 border-neutral-100 dark:border-neutral-700/40 flex flex-col">
              <!-- Main preview -->
              <div :class="['relative rounded-2xl overflow-hidden bg-neutral-100 dark:bg-neutral-900/60 border border-neutral-200/60 dark:border-neutral-700/40 flex-1 min-h-[200px]', seg.aspect_ratio === '16:9' ? 'aspect-video' : seg.aspect_ratio === '1:1' ? 'aspect-square' : seg.aspect_ratio === '9:16' ? 'aspect-[9/16] max-h-80' : 'aspect-[4/3]']">
                <!-- Stock search overlay (covers preview when adding stock to existing segment) -->
                <div v-if="stockSearchSegIndex === seg.index && seg.generated_url" class="absolute inset-0 bg-white dark:bg-neutral-900 z-10 overflow-y-auto p-3">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-neutral-600 dark:text-neutral-300">Search Stock Images</span>
                    <button @click="closeStockSearch" class="w-6 h-6 rounded-full flex items-center justify-center text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                  <StockImageSearch
                    :model-value="''"
                    :orientation="seg.aspect_ratio === '9:16' ? 'portrait' : 'landscape'"
                    @select="(url) => handleStockImageSelect(url, seg.index)"
                  />
                  <div v-if="stockDownloading" class="absolute inset-0 bg-white/80 dark:bg-neutral-900/80 flex items-center justify-center z-20">
                    <div class="text-center">
                      <div class="w-8 h-8 mx-auto rounded-full border-2 border-transparent border-t-amber-500 animate-spin mb-2"></div>
                      <span class="text-xs text-neutral-500">Downloading...</span>
                    </div>
                  </div>
                </div>

                <!-- Selected image (show even while another variation is generating) -->
                <template v-if="seg.generated_url">
                  <img
                    :src="seg.generated_url"
                    :alt="seg.concept"
                    class="w-full h-full object-cover cursor-zoom-in transition-opacity hover:opacity-90"
                    @click="openLightbox(seg)"
                    title="Click to expand"
                  />
                  <!-- Selected badge -->
                  <span v-if="(seg.variations || []).length > 1" class="absolute top-2 right-2 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full bg-green-500/90 text-white shadow">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    Selected
                  </span>
                </template>

                <!-- Generating / Rewriting VD (only when no image to show yet) -->
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

                <!-- Pending (no variations yet) — show inline stock search OR action buttons -->
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center">
                  <!-- Inline stock search overlay -->
                  <div v-if="stockSearchSegIndex === seg.index" class="absolute inset-0 bg-white dark:bg-neutral-900 z-10 overflow-y-auto p-3">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-xs font-medium text-neutral-600 dark:text-neutral-300">Search Stock Images</span>
                      <button @click="closeStockSearch" class="w-6 h-6 rounded-full flex items-center justify-center text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                    <StockImageSearch
                      :model-value="''"
                      :orientation="seg.aspect_ratio === '9:16' ? 'portrait' : 'landscape'"
                      @select="(url) => handleStockImageSelect(url, seg.index)"
                    />
                    <div v-if="stockDownloading" class="absolute inset-0 bg-white/80 dark:bg-neutral-900/80 flex items-center justify-center z-20">
                      <div class="text-center">
                        <div class="w-8 h-8 mx-auto rounded-full border-2 border-transparent border-t-amber-500 animate-spin mb-2"></div>
                        <span class="text-xs text-neutral-500">Downloading...</span>
                      </div>
                    </div>
                  </div>

                  <!-- Action buttons -->
                  <template v-else>
                    <div class="w-16 h-16 rounded-2xl bg-neutral-200/50 dark:bg-neutral-800/50 flex items-center justify-center mb-4">
                      <svg class="w-7 h-7 text-neutral-300 dark:text-neutral-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                      </svg>
                    </div>
                    <div class="flex items-center gap-2">
                      <button @click="generateSingle(seg.index)" class="px-4 py-2 text-xs font-medium rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition-colors active:scale-[0.98]">
                        Generate Image
                      </button>
                      <button @click="openStockSearch(seg.index)" class="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors active:scale-[0.98]">
                        Use Stock Image
                      </button>
                    </div>
                  </template>
                </div>
              </div>

              <!-- Variation thumbnails strip (max 3 slots) -->
              <div v-if="(seg.variations || []).length > 0" class="mt-3 flex items-center gap-2">
                <!-- Existing variations -->
                <button
                  v-for="(v, vi) in (seg.variations || [])"
                  :key="'var-' + vi"
                  @click="v.status === 'done' && selectVariation(seg, vi)"
                  :class="[
                    'relative w-16 h-12 rounded-lg overflow-hidden border-2 transition-all flex-shrink-0',
                    vi === (seg.selected_variation ?? 0)
                      ? 'border-green-500 ring-1 ring-green-500/30'
                      : 'border-neutral-200 dark:border-neutral-700 hover:border-amber-400',
                    v.status === 'done' ? 'cursor-pointer' : 'cursor-default'
                  ]"
                  :title="v.status === 'done' ? `Variation ${vi + 1} — click to select` : v.status === 'generating' ? 'Generating...' : 'Failed'"
                >
                  <img v-if="v.url && v.status === 'done'" :src="v.url" class="w-full h-full object-cover" />
                  <div v-else-if="v.status === 'generating'" class="w-full h-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                    <div class="w-5 h-5 rounded-full border-2 border-transparent border-t-amber-500 animate-spin"></div>
                  </div>
                  <div v-else class="w-full h-full bg-red-500/5 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                  </div>
                  <!-- Index badge -->
                  <span :class="[
                    'absolute top-0.5 left-0.5 w-4 h-4 rounded text-[9px] font-bold flex items-center justify-center',
                    vi === (seg.selected_variation ?? 0) ? 'bg-green-500 text-white' : 'bg-black/50 text-white'
                  ]">{{ vi + 1 }}</span>
                  <!-- Stock badge -->
                  <span v-if="v.source === 'stock'" class="absolute bottom-0.5 right-0.5 px-1 py-px text-[7px] font-bold uppercase rounded bg-blue-500/80 text-white leading-none">S</span>
                </button>

                <!-- Add variation: dropdown with Generate / Stock options. Opens upward
                     to avoid being clipped by the card's bottom edge when this segment
                     card sits near the bottom of the viewport. -->
                <div
                  v-if="(seg.variations || []).length < 3 && !(seg.variations || []).some(v => v.status === 'generating')"
                  class="relative flex-shrink-0 group/add"
                >
                  <button
                    class="w-16 h-12 rounded-lg border-2 border-dashed border-neutral-300 dark:border-neutral-600 flex items-center justify-center hover:border-amber-400 hover:bg-amber-500/5 transition-colors cursor-pointer"
                    title="Add another variation"
                  >
                    <svg class="w-5 h-5 text-neutral-400 dark:text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                  </button>
                  <!-- Dropdown (opens upward) -->
                  <div class="absolute bottom-full left-0 mb-1 w-36 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 shadow-lg opacity-0 invisible group-hover/add:opacity-100 group-hover/add:visible transition-all z-50">
                    <button @click="openVariantConfirm(seg.index)" class="w-full px-3 py-2 text-left text-xs text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 rounded-t-lg flex items-center gap-2">
                      <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      Generate AI
                    </button>
                    <button @click="openStockSearch(seg.index)" class="w-full px-3 py-2 text-left text-xs text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 rounded-b-lg flex items-center gap-2">
                      <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                      Stock Image
                    </button>
                  </div>
                </div>

                <span class="text-[10px] text-neutral-400 ml-1">Click to select</span>
              </div>
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

    <!-- Variant Confirm Modal -->
    <Teleport to="body">
      <div v-if="variantConfirmSeg" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div @click="closeVariantConfirm" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md bg-white dark:bg-neutral-800 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-700">
          <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Generate new variant?</h3>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              {{ (variantConfirmSeg.variations || []).length === 0
                  ? `Create the first image for ${variantConfirmSeg.label}.`
                  : `Another attempt for ${variantConfirmSeg.label} (${(variantConfirmSeg.variations || []).length}/3 used).` }}
            </p>
          </div>
          <div class="px-5 py-4 space-y-3">
            <div>
              <label class="block text-[11px] font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wider mb-1.5">
                Additional instruction <span class="text-neutral-400 normal-case font-normal">(optional, one-off)</span>
              </label>
              <textarea
                v-model="variantExtraNote"
                rows="3"
                maxlength="300"
                class="w-full px-3 py-2 text-sm rounded-xl border border-neutral-200 dark:border-neutral-600/60 bg-neutral-50 dark:bg-neutral-900/50 text-neutral-900 dark:text-neutral-100 focus:ring-1 focus:ring-amber-500/50 focus:border-amber-500/50 placeholder-neutral-400 dark:placeholder-neutral-600 resize-none"
                placeholder="e.g. make it more dramatic, cooler palette, tighter composition..."
              ></textarea>
              <p class="text-[10px] text-neutral-400 dark:text-neutral-500 mt-1">Applies only to this attempt — won't modify the saved notes on the segment.</p>
            </div>
          </div>
          <div class="px-5 py-3 border-t border-neutral-200 dark:border-neutral-700 flex items-center justify-end gap-2">
            <button @click="closeVariantConfirm" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
              Cancel
            </button>
            <button @click="confirmVariantGenerate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              Generate
            </button>
          </div>
        </div>
      </div>
    </Teleport>

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
