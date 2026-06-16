<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useLinkedInDraft,
  useLinkedInDraftProgress,
  useUpdateLinkedInDraft,
  useApproveLinkedInDraft,
  useCancelLinkedInDraft,
  usePublishLinkedInDraftNow,
  useRegenerateLinkedInDraft,
  useRegenerateAllCarouselImages,
  useRerenderImagesOnly,
  useRegenerateCaption,
  useRegenerateSlideImage,
  useConflictCheck,
  useGenerateThreads,
  useRegenerateInstagram,
  useRegenerateTiktok,
  useRegenerateThreads,
  useRegenerateAllCaptions,
  usePublishCrossPost,
  useRegenerateHookVideo,
  postTitle,
} from '@/composables/useLinkedInDrafts'
import {
  usePublishRepurposeZernio,
  useUpdateRepurposeCaptions,
} from '@/composables/useRepurposeJobs'
import { useToast } from '@/composables/useToast'
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
  shouldShowLastError,
  resolveCarouselActivity,
  formatElapsed,
  ICON,
} from './linkedinHelpers'

const route = useRoute()
const router = useRouter()

const draftId = computed(() => Number(route.params.id))
const { draft, isLoading, error, refetch } = useLinkedInDraft(draftId)

// --- video_carousel (IG + Threads) management -------------------------------
// A video_carousel anchor is a display-only LinkedIn row; its real content lives
// on the linked repurpose job, surfaced by show() as draft.repurpose. This page
// is the full management surface: video preview + IG/Threads caption editing +
// Approve/Schedule (reusing the repurpose Zernio publish endpoint).
const THREADS_CAP = 500
const isVideoCarousel = computed(() => draft.value?.format === 'video_carousel')
const repurpose = computed(() => draft.value?.repurpose || null)
const repurposeJobId = computed(() => repurpose.value?.id ?? null)

const igCaptionDraft = ref('')
const threadsCaptionDraft = ref('')
// Seed the editors whenever the loaded captions change (initial load + refetch).
watch(repurpose, (r) => {
  if (!r) return
  igCaptionDraft.value = r.caption_instagram || ''
  threadsCaptionDraft.value = r.caption_threads || ''
}, { immediate: true })

const captionsDirty = computed(() =>
  !!repurpose.value &&
  (igCaptionDraft.value !== (repurpose.value.caption_instagram || '') ||
    threadsCaptionDraft.value !== (repurpose.value.caption_threads || '')))
const threadsRemaining = computed(() => THREADS_CAP - threadsCaptionDraft.value.length)

const updateCaptions = useUpdateRepurposeCaptions()
const publishZernioMut = usePublishRepurposeZernio()

async function saveVideoCaptions() {
  if (!repurposeJobId.value) return
  try {
    await updateCaptions.mutateAsync({
      id: repurposeJobId.value,
      instagram: igCaptionDraft.value,
      threads: threadsCaptionDraft.value.slice(0, THREADS_CAP),
    })
    await refetch()
    toast.success('Captions saved.')
  } catch (e) {
    toast.error(e?.response?.data?.error?.message || 'Failed to save captions.')
  }
}

const videoScheduleAt = ref('')
const VIDEO_PLATFORMS = ['instagram', 'threads']

function zernioPlatformState(platform) {
  return repurpose.value?.zernio_publish?.[platform] || null
}

async function publishVideoNow() {
  if (!repurposeJobId.value) return
  if (!confirm('Publish this video carousel to Instagram + Threads now?')) return
  try {
    if (captionsDirty.value) await saveVideoCaptions()
    await publishZernioMut.mutateAsync({ id: repurposeJobId.value, platforms: VIDEO_PLATFORMS })
    await refetch()
    toast.success('Publishing to Instagram + Threads…')
  } catch (e) {
    toast.error(e?.response?.data?.error?.message || 'Publish failed.')
  }
}

async function scheduleVideo() {
  if (!repurposeJobId.value) return
  if (!videoScheduleAt.value) { toast.error('Pick a date + time first.'); return }
  const iso = new Date(videoScheduleAt.value).toISOString()
  if (new Date(iso) <= new Date()) { toast.error('Scheduled time must be in the future.'); return }
  try {
    if (captionsDirty.value) await saveVideoCaptions()
    await publishZernioMut.mutateAsync({ id: repurposeJobId.value, platforms: VIDEO_PLATFORMS, scheduledAt: iso })
    await refetch()
    toast.success('Scheduled to Instagram + Threads.')
  } catch (e) {
    toast.error(e?.response?.data?.error?.message || 'Schedule failed.')
  }
}

function openSocialStudio() {
  if (repurposeJobId.value) router.push({ name: 'admin-repurpose-detail', params: { id: repurposeJobId.value } })
}

// --- Per-platform caption switcher --------------------------------------
// Operator toggles between LinkedIn / FB / IG / TikTok in-place — only
// caption + hashtags + status pill swap. The carousel slide imagery is
// always sourced from the LinkedIn draft (carousel images are reused
// across platforms). Cross-post siblings are loaded by the show endpoint
// as draft.facebook_post / instagram_post / tiktok_post.
const activePlatform = ref('linkedin')

const PLATFORM_META = {
  linkedin: { label: 'LinkedIn', accent: 'text-sky-400', activeBg: 'bg-sky-500/10 text-sky-200 border-sky-400/40' },
  facebook: { label: 'Facebook', accent: 'text-blue-400', activeBg: 'bg-blue-500/10 text-blue-200 border-blue-400/40' },
  instagram: { label: 'Instagram', accent: 'text-fuchsia-400', activeBg: 'bg-fuchsia-500/10 text-fuchsia-200 border-fuchsia-400/40' },
  tiktok: { label: 'TikTok', accent: 'text-rose-400', activeBg: 'bg-rose-500/10 text-rose-200 border-rose-400/40' },
  threads: { label: 'Threads', accent: 'text-neutral-300', activeBg: 'bg-neutral-200/10 text-neutral-100 border-neutral-300/40' },
}

// Platforms shown in the caption tab strip. Facebook hidden (May 10, 2026)
// — moved off Publer to direct Graph API integration, no UI surface yet.
// PLATFORM_META.facebook stays intact so server-side lookups (labels, status
// chips, draft.facebook_post deserialization) keep working.
const VISIBLE_PLATFORMS = ['linkedin', 'instagram', 'tiktok', 'threads']
const VISIBLE_PLATFORM_META = computed(() =>
  Object.fromEntries(VISIBLE_PLATFORMS.map((k) => [k, PLATFORM_META[k]]))
)

const platformPostFor = (key) => {
  if (!draft.value) return null
  if (key === 'linkedin') {
    return {
      content: draft.value.content || '',
      hashtags: Array.isArray(draft.value.hashtags) ? draft.value.hashtags : [],
      status: draft.value.status,
      external_url: draft.value.external_url || null,
      published_at: draft.value.published_at || null,
      scheduled_at: draft.value.scheduled_at || null,
    }
  }
  // Backend returns snake_case relation keys (facebook_post, instagram_post,
  // tiktok_post) by default Laravel serialization rules.
  const sibling = draft.value[`${key}_post`]
  if (!sibling) return null
  return {
    title: sibling.title || '', // TikTok REQUIRES this for photo carousel (Publer ≤90 chars)
    content: sibling.caption || '',
    hashtags: Array.isArray(sibling.hashtags) ? sibling.hashtags : [],
    status: sibling.status,
    external_url: sibling.external_url || null,
    published_at: sibling.published_at || null,
    scheduled_at: sibling.scheduled_at || null,
  }
}

const activePlatformPost = computed(() => platformPostFor(activePlatform.value))
const activePlatformExists = computed(() => activePlatformPost.value !== null)

// TikTok: plugin currently emits title as caption first line too (hook
// echo). Until plugin authors distinct title/caption, dedupe display-side
// — strip the matching first line if it equals title verbatim. Other
// platforms render caption as-is (no title field shown for them).
const captionDisplayBody = computed(() => {
  const post = activePlatformPost.value
  if (!post?.content) return ''
  if (activePlatform.value !== 'tiktok' || !post.title) return post.content
  const lines = post.content.split('\n')
  const firstNonEmpty = lines.findIndex((l) => l.trim() !== '')
  if (firstNonEmpty === -1) return post.content
  if (lines[firstNonEmpty].trim() !== post.title.trim()) return post.content
  // Drop the matching line + any leading blank line(s) right after it.
  const remaining = lines.slice(firstNonEmpty + 1)
  while (remaining.length && remaining[0].trim() === '') remaining.shift()
  return remaining.join('\n')
})

// Template ref for the caption tab nav so the top "Fanned out" chips
// can scroll the operator down to the swapped caption after activating.
const captionTabsRef = ref(null)
function activatePlatformAndScroll(key) {
  activePlatform.value = key
  nextTick(() => {
    captionTabsRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
}

// --- Cross-post platform status visibility -------------------------------
// Each Fanned-out chip shows a status pill so the operator can see at a
// glance whether each platform succeeded WITHOUT navigating away. Status
// values come from the per-platform sibling FSM enums:
//   pending → orange (queued, not yet authored)
//   generating / publishing → cyan (in progress)
//   awaiting_review → amber (needs operator decision)
//   published → emerald (live)
//   failed / cancelled → red / neutral (terminal failure / aborted)
const PLATFORM_STATUS_META = {
  pending: { label: 'PENDING', dot: 'bg-orange-400', text: 'text-orange-300' },
  generating: { label: 'AUTHORING', dot: 'bg-cyan-400 animate-pulse', text: 'text-cyan-300' },
  validating: { label: 'VALIDATING', dot: 'bg-cyan-400 animate-pulse', text: 'text-cyan-300' },
  awaiting_review: { label: 'NEEDS REVIEW', dot: 'bg-amber-400', text: 'text-amber-300' },
  awaiting_manual_publish: { label: 'NEEDS REVIEW', dot: 'bg-amber-400', text: 'text-amber-300' },
  publishing: { label: 'PUBLISHING', dot: 'bg-cyan-400 animate-pulse', text: 'text-cyan-300' },
  published_externally: { label: 'PUBLISHED', dot: 'bg-emerald-400', text: 'text-emerald-300' },
  published: { label: 'PUBLISHED', dot: 'bg-emerald-400', text: 'text-emerald-300' },
  failed: { label: 'FAILED', dot: 'bg-red-400', text: 'text-red-300' },
  cancelled: { label: 'CANCELLED', dot: 'bg-neutral-500', text: 'text-neutral-400' },
}

function platformStatusMeta(key) {
  const sibling = draft.value?.[`${key}_post`]
  if (!sibling) return { label: 'NOT YET', dot: 'bg-neutral-700', text: 'text-neutral-500' }
  const status = sibling.status || 'pending'
  return PLATFORM_STATUS_META[status] || { label: status.toUpperCase(), dot: 'bg-neutral-500', text: 'text-neutral-400' }
}

// Aggregate health line shown above the chips: "3/4 published".
const sosmedHealth = computed(() => {
  if (!draft.value) return null
  // Facebook hidden from cross-post pipeline (May 10, 2026) — moved to direct
  // Graph API integration (mirror LinkedIn pattern), no longer published via
  // Publer. FB backend code (FacebookPost model + service + scanner branch)
  // intact for future revival.
  const platforms = ['linkedin']
  if (draft.value.format === 'text') platforms.push('threads')
  if (draft.value.format === 'carousel') platforms.push('instagram', 'tiktok', 'threads')

  let published = 0, inProgress = 0, failed = 0, notYet = 0
  for (const p of platforms) {
    const status = p === 'linkedin'
      ? draft.value.status
      : draft.value[`${p}_post`]?.status

    if (!status) { notYet++; continue }
    if (status === 'published' || status === 'published_externally') published++
    else if (status === 'failed') failed++
    else if (status === 'cancelled') {/* skip */}
    else inProgress++
  }
  return { total: platforms.length, published, inProgress, failed, notYet }
})

const updateMutation = useUpdateLinkedInDraft()
const approveMutation = useApproveLinkedInDraft()
const cancelMutation = useCancelLinkedInDraft()
const publishNowMutation = usePublishLinkedInDraftNow()
const generateThreadsMutation = useGenerateThreads()
const regenerateInstagramMutation = useRegenerateInstagram()
const regenerateTiktokMutation = useRegenerateTiktok()
const regenerateThreadsMutation = useRegenerateThreads()
const regenerateAllCaptionsMutation = useRegenerateAllCaptions()
const regenerateHookVideoMutation = useRegenerateHookVideo()

// --- IG hook video (GROK mixed carousel) --------------------------------
// The IG hook slide ships in two forms: the rendered carousel slide 1 image
// AND a GROK image-to-video animation of it. Operator previews both via an
// Image|Video toggle and can re-trigger the GROK render.
const hookTab = ref('image')
const hookVideo = computed(() => {
  const ig = draft.value?.instagram_post
  return {
    status: ig?.hook_video_status || null,
    url: ig?.hook_video_url || null,
    error: ig?.hook_video_error || null,
  }
})
// Slide 1 of an IG-capable carousel ships a GROK image-to-video animation
// alongside the static slide image. The Image|Video toggle lives ON the slide
// viewer (only on slide 1), decoupled from the caption-platform tab below —
// the clip is IG-specific but the shared viewer is where the operator sees it.
const hasHookVideo = computed(
  () => draft.value?.format === 'carousel' && !!draft.value?.instagram_post
)
const isHookSlide = computed(() => activeSlideIndex.value === 0 && hasHookVideo.value)
const showHookVideoFrame = computed(() => isHookSlide.value && hookTab.value === 'video')
async function doRegenerateHookVideo() {
  if (!confirm('Regenerate the Instagram hook video? GROK renders a fresh ~6s clip (takes a few minutes).')) return
  try {
    await regenerateHookVideoMutation.mutateAsync(draftId.value)
    hookTab.value = 'video'
    toast.success('Hook video regeneration started')
    refetch()
  } catch (err) {
    toast.error(err?.response?.data?.error?.message || 'Hook video regen failed')
  }
}

const regenerateMutation = useRegenerateLinkedInDraft()
const toast = useToast()

// Per-platform retry — only surfaced on a FAILED cross-post chip. Re-publishes
// that one sibling via the publisher-aware endpoint (Zernio) without touching
// the others or regenerating its caption.
const publishCrossPostMutation = usePublishCrossPost()
const publishingPlatform = ref(null)
function platformStatus(p) {
  return draft.value?.[`${p}_post`]?.status || null
}
async function doRetryPlatform(platform) {
  if (publishingPlatform.value) return
  publishingPlatform.value = platform
  try {
    const res = await publishCrossPostMutation.mutateAsync({ id: draftId.value, platform })
    toast.success(res?.message || `Retrying ${platform}…`)
    refetch()
  } catch (e) {
    toast.error(e?.response?.data?.error?.message || `Retry ${platform} failed.`)
  } finally {
    publishingPlatform.value = null
  }
}

// "Open on <platform>" external links — one per cross-post sibling that actually
// published and carries a live URL (IG/TikTok/Threads via Zernio). Mirrors the
// "Open on LinkedIn" affordance so the operator can jump straight to each post.
const OPEN_LINK_CLASS = {
  instagram: 'bg-gradient-to-r from-fuchsia-600 to-pink-600 hover:from-fuchsia-500 hover:to-pink-500',
  tiktok: 'bg-neutral-900 hover:bg-black border border-neutral-700',
  threads: 'bg-neutral-700 hover:bg-neutral-600',
}
const publishedExternalLinks = computed(() => {
  if (!draft.value) return []
  const platforms = draft.value.format === 'carousel' ? ['instagram', 'tiktok', 'threads'] : ['threads']
  return platforms
    .map((p) => ({
      platform: p,
      label: PLATFORM_META[p]?.label || p,
      url: draft.value?.[`${p}_post`]?.external_url || null,
      status: draft.value?.[`${p}_post`]?.status || null,
    }))
    .filter((x) => x.status === 'published' && x.url)
})

// Inline result panel for the unified "Regenerate ALL captions" button.
// Auto-clears after 12s so it doesn't linger forever, but stays long
// enough for operator to read per-platform outcomes.
const regenAllResults = ref(null) // { linkedin: {outcome,...}, instagram: {...}, ... }
const regenAllStartedAt = ref(null)
let regenAllClearTimer = null
function clearRegenAllResults() {
  regenAllResults.value = null
  regenAllStartedAt.value = null
  if (regenAllClearTimer) {
    clearTimeout(regenAllClearTimer)
    regenAllClearTimer = null
  }
}
const regenerateAllImagesMutation = useRegenerateAllCarouselImages()
const rerenderImagesOnlyMutation = useRerenderImagesOnly()
const regenerateCaptionMutation = useRegenerateCaption()
const regenerateSlideMutation = useRegenerateSlideImage()

// --- Smart back navigation: read the referring origin from sessionStorage so
// "Back" returns to the right list view. 'feed' came from the calendar →
// Content Calendar; 'studio' (and any default) came from the merged menu →
// Social Studio.
const backTarget = ref({ name: 'admin-social-studio', label: 'Social Studio' })
onMounted(() => {
  const origin = sessionStorage.getItem('linkedin:detail:origin')
  if (origin === 'feed') {
    backTarget.value = { name: 'admin-sosmed-posts', label: 'Content Calendar' }
  } else {
    backTarget.value = { name: 'admin-social-studio', label: 'Social Studio' }
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

// --- Streaming progress panel ---------------------------------------------
// Mirrors ContentEngine's pipelinePhases pattern. Two variants:
//   text     → 3 phases (Brief 0-60% · Validate 60-95% · Done 95-100%)
//   carousel → 4 phases (Brief 0-25% · Carousel Gen 25-55% · Validate 55-65% · Render 65-100%)
//
// Step `name` matches the values emitted by LinkedInProgressEmitter from
// the backend services. `pct` is the threshold that marks a step as "done"
// once progress_percentage crosses it.
const PHASES_TEXT = [
  {
    name: 'Brief',
    skill: '/linkedin-gen',
    model: 'Sonnet',
    pctRange: '0–60%',
    minPct: 0,
    maxPct: 60,
    steps: [
      { name: 'plugin_dispatch', label: 'SSH Dispatch', pct: 5 },
      { name: 'orchestrator_parsed', label: 'Plugin Done', pct: 50 },
      { name: 'gates_evaluated', label: 'Validated', pct: 60 },
    ],
  },
  {
    name: 'Validate',
    skill: 'depth-score',
    model: 'Backend',
    pctRange: '60–95%',
    minPct: 60,
    maxPct: 95,
    steps: [
      { name: 'gates_evaluated', label: 'Depth Score', pct: 75 },
      { name: 'fsm_advanced', label: 'FSM Advanced', pct: 95 },
    ],
  },
  {
    name: 'Done',
    skill: 'finalize',
    model: '–',
    pctRange: '95–100%',
    minPct: 95,
    maxPct: 100,
    steps: [
      { name: 'completed', label: 'Ready', pct: 100 },
    ],
  },
]

const PHASES_CAROUSEL = [
  {
    name: 'Brief',
    skill: '/linkedin-gen',
    model: 'Sonnet',
    pctRange: '0–25%',
    minPct: 0,
    maxPct: 25,
    steps: [
      { name: 'plugin_dispatch', label: 'SSH Dispatch', pct: 5 },
      { name: 'orchestrator_parsed', label: 'Route Carousel', pct: 25 },
    ],
  },
  {
    name: 'Carousel Gen',
    skill: '/carousel-gen',
    model: 'Sonnet',
    pctRange: '25–55%',
    minPct: 25,
    maxPct: 55,
    steps: [
      { name: 'carousel_gen_dispatch', label: 'Dispatch', pct: 30 },
      { name: 'slides_assembled', label: 'Slides Assembled', pct: 55 },
    ],
  },
  {
    name: 'Validate',
    skill: 'structural',
    model: 'Backend',
    pctRange: '55–65%',
    minPct: 55,
    maxPct: 65,
    steps: [
      { name: 'structural_check', label: 'Structural', pct: 60 },
      { name: 'fsm_advanced', label: 'FSM Advanced', pct: 65 },
    ],
  },
  {
    name: 'Render',
    skill: 'GeminiGen',
    model: '–',
    pctRange: '65–100%',
    minPct: 65,
    maxPct: 100,
    steps: [
      { name: 'render_dispatching', label: 'Dispatching', pct: 68 },
      { name: 'render_progress', label: 'Slides', pct: 90 },
      { name: 'render_complete', label: 'Done', pct: 100 },
    ],
  },
]

const { progress: liveProgress } = useLinkedInDraftProgress(
  draftId,
  { enabled: computed(() => !!draftId.value) }
)

const progressPhases = computed(() => {
  return draft.value?.format === 'carousel' ? PHASES_CAROUSEL : PHASES_TEXT
})

// Show the BRIEF→VALIDATE phase tracker only when the orchestrator
// pipeline is actually running. Image-rendering-in-flight (FSM at
// manual_review/awaiting_publish with slides still pending) is excluded
// — the slide thumbnail strip and "Approval gated" banner already
// surface render progress, and lighting up BRIEF/SSH Dispatch when no
// SSH/Sonnet work is happening was the source of the "stuck at SSH
// Dispatch" misread.
const showProgressPanel = computed(() => {
  if (!draft.value) return false
  if (isInProgress.value) return true
  if (liveProgress.value?.process_alive) return true
  return false
})

const progressPct = computed(() => {
  const p = liveProgress.value?.progress_percentage
  if (typeof p === 'number') return Math.max(0, Math.min(100, p))
  return 0
})

const progressLog = computed(() => liveProgress.value?.progress_log || [])
const currentStepName = computed(() => liveProgress.value?.current_step || 'initializing')
const isProgressFailed = computed(() => currentStepName.value === 'failed')

function formatStepName(stepName) {
  if (!stepName) return ''
  return String(stepName)
    .split('_')
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
}

function formatLogTime(ts) {
  if (!ts) return ''
  try {
    const d = new Date(ts)
    const pad = (n) => String(n).padStart(2, '0')
    return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
  } catch (_e) { return '' }
}

function phaseStatus(phase) {
  const pct = progressPct.value
  // First phase + pct=0 + no step signal = nothing started yet. Without
  // this guard, BRIEF always reads "active" with an amber border the
  // moment the panel mounts (because 0 >= phase.minPct(0) trivially
  // matches), which historically misled operators into thinking SSH
  // Dispatch was stuck when in fact no work had been kicked off.
  if (pct === 0 && phase.minPct === 0) {
    const step = currentStepName.value
    if (!step || step === 'initializing') return 'pending'
  }
  if (isProgressFailed.value && pct >= phase.minPct && pct < phase.maxPct) return 'failed'
  if (pct >= phase.maxPct) return 'done'
  if (pct >= phase.minPct) return 'active'
  return 'pending'
}

function phaseCardClass(phase) {
  const s = phaseStatus(phase)
  if (s === 'failed') return 'border-red-500/40 bg-red-500/[0.04]'
  if (s === 'done') return 'border-emerald-500/30 bg-emerald-500/[0.04]'
  if (s === 'active') return 'border-amber-500/40 bg-amber-500/[0.05] shadow-[0_0_24px_-12px_rgba(245,158,11,0.4)]'
  return 'border-neutral-700 bg-neutral-900/40'
}

function phaseHeaderColor(phase) {
  const s = phaseStatus(phase)
  if (s === 'failed') return 'text-red-300'
  if (s === 'done') return 'text-emerald-300'
  if (s === 'active') return 'text-amber-300'
  return 'text-neutral-500'
}

function phaseModelBadge(phase) {
  const s = phaseStatus(phase)
  if (s === 'active') return 'bg-amber-500/20 text-amber-300'
  if (s === 'done') return 'bg-emerald-500/15 text-emerald-300'
  if (s === 'failed') return 'bg-red-500/15 text-red-300'
  return 'bg-neutral-800 text-neutral-500'
}

function phaseStatusLabel(phase) {
  const s = phaseStatus(phase)
  if (s === 'failed') return 'failed'
  if (s === 'done') return '✓ done'
  if (s === 'active') return 'active'
  return 'wait'
}

function stepIsDone(step) {
  return progressPct.value >= step.pct
}

function stepIsActive(step) {
  // Active if percentage is between previous step (or 0) and this step's pct.
  // Simple rule: active when current_step name matches OR pct is in range.
  if (currentStepName.value === step.name) return true
  return false
}

function stepChipClass(step) {
  if (stepIsDone(step)) return 'bg-emerald-500/15 text-emerald-300'
  if (stepIsActive(step)) return 'bg-amber-500/20 text-amber-200'
  return 'bg-neutral-800 text-neutral-500'
}

// Auto-scroll log viewer to bottom when new entries arrive.
const logContainer = ref(null)
watch(progressLog, async (entries) => {
  if (!logContainer.value || !entries.length) return
  await nextTick()
  logContainer.value.scrollTop = logContainer.value.scrollHeight
}, { deep: true })

const isTerminalGood = computed(() => draft.value?.status === 'published')
const isTerminalBad = computed(() => draft.value?.status === 'failed')

const STALE_ERROR_HOURS = 24
// Delegated to the pure shouldShowLastError() helper (unit-tested). Adds an
// in-flight guard on top of the existing status + staleness gates: while a
// /carousel-gen re-author OR slide render is in progress, the stale last_error
// is suppressed so the operator never sees the "red error + nothing happening"
// contradiction (the draft-149 incident). Error stays in the DB for debugging.
const showLastError = computed(() =>
  shouldShowLastError({
    lastError: draft.value?.last_error,
    status: draft.value?.status,
    pipelineLog: draft.value?.pipeline_state_log,
    slides: carouselSlides.value,
    regenerateActive: draft.value?.regenerate_activity?.active,
    nowMs: Date.now(),
    staleHours: STALE_ERROR_HOURS,
  })
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

// P5 (May 12) — count how many times this draft was postponed by the
// atomic orchestrator because cross-post siblings weren't ready at slot
// fire. Surfaces as amber chip near the live countdown.
const postponeCount = computed(() => {
  const log = Array.isArray(draft.value?.pipeline_state_log)
    ? draft.value.pipeline_state_log
    : []
  return log.filter(e => e?.reason === 'slot_postponed_siblings_not_ready').length
})

// Format the slot scheduled_at as "13 May · 05:00 WIB" — post-May-12 it's
// the actual publish time, not now() + cancel window.
const scheduledSlotLabel = computed(() => {
  const iso = draft.value?.scheduled_at
  if (!iso) return ''
  const d = new Date(iso)
  const opts = {
    timeZone: 'Asia/Jakarta',
    day: '2-digit', month: 'short',
    hour: '2-digit', minute: '2-digit', hour12: false,
  }
  try {
    return new Intl.DateTimeFormat('en-GB', opts).format(d).replace(',', ' ·') + ' WIB'
  } catch {
    return iso
  }
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

// --- Conflict warning (soft, ±30 min window) -------------------------------
// Per design doc §4.1: as the operator types a new datetime, debounce-check
// against other live drafts. Reschedule button stays enabled (soft warning,
// not hard block — announcement + Q&A back-to-back is legitimate).
const conflictMutation = useConflictCheck()
const conflictData = ref(null)
let conflictDebounceTimer = null

watch(scheduleAt, (newVal) => {
  // Reset whenever the input clears or the modal closes
  if (!newVal) {
    conflictData.value = null
    if (conflictDebounceTimer) clearTimeout(conflictDebounceTimer)
    return
  }
  if (conflictDebounceTimer) clearTimeout(conflictDebounceTimer)
  conflictDebounceTimer = setTimeout(async () => {
    try {
      const local = new Date(newVal)
      if (Number.isNaN(local.getTime())) {
        conflictData.value = null
        return
      }
      const result = await conflictMutation.mutateAsync({
        draftId: draftId.value,
        at: local.toISOString(),
      })
      conflictData.value = result
    } catch (e) {
      // Soft-failure: warning is informational, network blip shouldn't
      // block the operator. Log to console for debugging.
      console.warn('[LinkedInDraft] conflict check failed', e?.message)
      conflictData.value = null
    }
  }, 300)
})

onBeforeUnmount(() => {
  if (conflictDebounceTimer) clearTimeout(conflictDebounceTimer)
})

/**
 * Apply a suggested hour from the conflict-warning suggestion chips.
 * Replaces the time portion of the existing scheduleAt while preserving
 * the date. `hour` arrives as "HH:00" string from the backend.
 */
function applySuggestedHour(hour) {
  if (!scheduleAt.value || !hour) return
  // scheduleAt format is "YYYY-MM-DDTHH:mm" (datetime-local browser format)
  const [datePart] = scheduleAt.value.split('T')
  if (!datePart) return
  // hour is "HH:00" — trim and pad just in case
  const [h, m = '00'] = hour.split(':')
  const hh = String(h).padStart(2, '0')
  const mm = String(m).padStart(2, '0')
  scheduleAt.value = `${datePart}T${hh}:${mm}`
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
  const platforms = draft.value?.format === 'carousel'
    ? 'LinkedIn + Instagram + TikTok + Threads'
    : 'LinkedIn + Threads'
  const confirmMsg =
    `Publish now to ${platforms}?\n\n` +
    `LinkedIn fires now (~30-60s).\n` +
    `Cross-posts publish via Publer (~1-3 min after).\n` +
    `Skips the cancel window. Cannot rollback once Publer schedules.`
  if (!confirm(confirmMsg)) return
  try {
    await publishNowMutation.mutateAsync(draftId.value)
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Publish failed')
  }
}

// doPublishAll removed May 10 — "Publish now" now does cross-platform
// fan-out automatically via backend publishNow controller change.
async function doRegenerate() {
  if (!confirm('Restart from the blog post?\n\nThis draft will be archived and a brand-new one built from scratch (new draft ID, fresh caption + slides). Runtime ~5-7 min.')) return
  const result = await regenerateMutation.mutateAsync(draftId.value)
  const newId = result?.data?.id
  if (newId) router.push({ name: 'admin-sosmed-draft-detail', params: { id: newId } })
}

// One-off Threads cross-post sibling creation for LinkedIn drafts that
// pre-date the /threads-gen plugin (May 10, 2026 ship). Surfaces as button
// inside the Threads tab section when threads_post is null. Bulk equivalent
// is `php artisan linkedin:backfill-threads`.
async function doGenerateThreads() {
  if (!confirm('Generate Threads variant for this LinkedIn draft?\n\nA new Threads draft will be created and queued for caption authoring (~30s).')) return
  try {
    await generateThreadsMutation.mutateAsync(draftId.value)
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Generate Threads failed')
  }
}

// Per-platform caption regen — IG/TT/Threads cross-post siblings. Resets the
// sibling's caption + hashtags + link_comment, dispatches Generate*Post job
// (~30s). FSM-gated server-side (409 if mid-pipeline). LinkedIn carousel uses
// doRegenerateLinkedInCaption (sync ~1s) instead since slides stay untouched.
const platformRegenMap = {
  instagram: { mutation: regenerateInstagramMutation, label: 'Instagram' },
  tiktok: { mutation: regenerateTiktokMutation, label: 'TikTok' },
  threads: { mutation: regenerateThreadsMutation, label: 'Threads' },
}
async function doRegeneratePlatform(platform) {
  const cfg = platformRegenMap[platform]
  if (!cfg) return
  if (!confirm(`Regenerate ${cfg.label} caption?\n\nThe existing caption + hashtags will be discarded and re-authored from scratch (~30s).`)) return
  try {
    await cfg.mutation.mutateAsync(draftId.value)
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || `${cfg.label} regen failed`)
  }
}

// LinkedIn carousel caption-only refresh — sync ~1s, slides untouched.
// (Text-format LinkedIn must use the full Regenerate button since post body
// IS the caption — needs /linkedin-gen plugin.)
async function doRegenerateLinkedInCaption() {
  if (!confirm('Re-synth LinkedIn caption + hashtags from existing slides?\n\nSlide images stay untouched. Sync, ~1 second.')) return
  try {
    await regenerateCaptionMutation.mutateAsync(draftId.value)
    refetch()
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Caption regen failed')
  }
}

// Unified fan-out — regen caption across all 4 platforms in one click.
// Backend returns per-platform outcomes; we render an inline result panel
// showing colored badges per platform + fire a toast summary so operator
// sees something happen IMMEDIATELY (no silent disabled-button limbo).
async function doRegenerateAllCaptions() {
  const platformsList = ['LinkedIn', 'Instagram', 'TikTok', 'Threads']
  const lines = [
    'Regenerate captions across all platforms?',
    '',
    `Will refresh: ${platformsList.join(', ')}`,
    '',
    'LinkedIn carousel = sync ~1s · LinkedIn text = ~30-90s',
    'Instagram / TikTok / Threads = ~30s each (queued)',
    '',
    'Slide images stay untouched.',
  ].join('\n')
  if (!confirm(lines)) return

  clearRegenAllResults()
  regenAllStartedAt.value = Date.now()
  toast.success('Caption fan-out started — refreshing all platforms…', 4000)

  try {
    const resp = await regenerateAllCaptionsMutation.mutateAsync(draftId.value)
    regenAllResults.value = resp?.data || null

    // Per-platform tally for toast summary
    const counts = { refreshed: 0, dispatched: 0, in_progress: 0, missing: 0, failed: 0 }
    for (const r of Object.values(resp?.data || {})) {
      counts[r.outcome] = (counts[r.outcome] || 0) + 1
    }
    const summary = Object.entries(counts).filter(([, n]) => n > 0).map(([k, n]) => `${n} ${k}`).join(' · ')
    toast.success(`Caption regen complete — ${summary}`, 6000)
    refetch()

    // Auto-clear panel after 12s (operator's eye has moved on by then)
    regenAllClearTimer = setTimeout(() => {
      clearRegenAllResults()
    }, 12000)
  } catch (err) {
    const msg = err?.response?.data?.error?.message || err?.message || 'Caption fan-out failed'
    toast.error(`Regen failed: ${msg}`, 8000)
    regenAllResults.value = null
  }
}

onBeforeUnmount(() => {
  if (regenAllClearTimer) clearTimeout(regenAllClearTimer)
})

// Read the link_comment field from the active platform's draft. LinkedIn
// stores it on the draft itself; cross-posts (IG/TikTok/Threads) store it on
// their own draft row exposed via draft.<platform>_post relation.
function getActivePlatformLinkComment() {
  if (!draft.value) return null
  if (activePlatform.value === 'linkedin') return draft.value.link_comment || null
  const sibling = draft.value[`${activePlatform.value}_post`]
  return sibling?.link_comment || null
}

// --- Carousel slides + image actions --------------------------------------
const carouselSlides = computed(() =>
  Array.isArray(draft.value?.carousel_slides) ? draft.value.carousel_slides : []
)

// Live carousel work phase for the status hero / approval-gated banner.
// `void tick.value` makes the elapsed timer re-evaluate every second; the
// regenerate_activity block (active + started_at) comes from show().
const liveActivity = computed(() => {
  void tick.value
  return resolveCarouselActivity({
    slides: carouselSlides.value,
    regenerateActive: draft.value?.regenerate_activity?.active,
    regenerateStartedAt: draft.value?.regenerate_activity?.started_at,
    nowMs: Date.now(),
  })
})

// Carousels can only be approved / scheduled / published when EVERY slide has
// finished rendering. Approving with pending slides starts the cancel_window
// timer immediately while GeminiGen is still generating images — the
// `linkedin:process-scheduled` cron then fires publishCarousel which fails
// the per-slide validation gate ("validate every slide has image_status=done")
// and the operator only sees the failure 15+ minutes later. Block the action
// at the UI layer so operator-intent matches operator-effect.
const slidesReadyForPublish = computed(() => {
  if (!draft.value) return false
  if (draft.value.format !== 'carousel') return true
  const slides = carouselSlides.value
  if (slides.length === 0) return false
  return slides.every(s => s?.image_status === 'done' && !!s?.image_url)
})

const slidesPendingMessage = computed(() => {
  if (slidesReadyForPublish.value) return ''
  const slides = carouselSlides.value
  if (slides.length === 0) return 'No slides on this draft yet'
  // Re-authoring: /carousel-gen is rewriting the storyline — no render started.
  // liveActivity.elapsedMs ticks every second (tick-driven) so the operator
  // sees it's actually working, not stalled.
  const reauthoring = slides.filter(s => s?.image_status === 'reauthoring').length
  if (reauthoring > 0 || liveActivity.value.phase === 're_authoring') {
    const el = formatElapsed(liveActivity.value.elapsedMs)
    return `Re-authoring storyline with /carousel-gen${el ? ` · ${el} elapsed` : ''} · ~3-7 min — building slides now`
  }
  const done = slides.filter(s => s?.image_status === 'done' && s?.image_url).length
  const generating = slides.filter(s => s?.image_status === 'generating').length
  const failed = slides.filter(s => s?.image_status === 'failed').length
  if (failed > 0) {
    return `Rendering ${done} of ${slides.length} done · ${failed} failed (auto-retries every ~5 min, or hit "Re-render image")`
  }
  return `Rendering slide images — ${done} of ${slides.length} done${generating > 0 ? ` · ${generating} in flight` : ''}`
})

// Cross-post caption readiness (June 12, 2026). Authoritative flag computed
// server-side in show() (caption_readiness) — it knows which Publer platforms
// are configured (the FE can't). Blocks Approve / Schedule / Publish when any
// expected IG/TikTok/Threads caption is missing, empty, or still generating,
// so the operator can't push a carousel whose sibling captions haven't landed.
const captionsReady = computed(() => draft.value?.caption_readiness?.ready ?? true)

const CAPTION_BLOCKER_LABELS = {
  instagram_caption_missing: 'Instagram caption not generated yet',
  tiktok_caption_missing: 'TikTok caption not generated yet',
  threads_caption_missing: 'Threads caption not generated yet',
  instagram_caption_empty: 'Instagram caption is empty',
  tiktok_caption_empty: 'TikTok caption is empty',
  threads_caption_empty: 'Threads caption is empty',
  instagram_pending_generation: 'Instagram caption queued (generating soon)',
  tiktok_pending_generation: 'TikTok caption queued (generating soon)',
  threads_pending_generation: 'Threads caption queued (generating soon)',
  instagram_generating: 'Instagram caption still authoring',
  tiktok_generating: 'TikTok caption still authoring',
  threads_generating: 'Threads caption still authoring',
  instagram_failed: 'Instagram caption failed — auto-retries every ~10 min',
  tiktok_failed: 'TikTok caption failed — auto-retries every ~10 min',
  threads_failed: 'Threads caption failed — auto-retries every ~10 min',
}
const captionsPendingMessage = computed(() => {
  if (captionsReady.value) return ''
  const blockers = draft.value?.caption_readiness?.blockers ?? []
  if (blockers.length === 0) return 'Cross-post captions not ready yet'
  return blockers.map(b => CAPTION_BLOCKER_LABELS[b] || b).join(' · ')
})

// Single gate for the 3 publish actions: slides rendered AND captions ready.
const readyForApproval = computed(() => slidesReadyForPublish.value && captionsReady.value)
const approvalPendingMessage = computed(() => {
  if (readyForApproval.value) return ''
  // Slides first (rendering is the longer pole), then captions.
  if (!slidesReadyForPublish.value) return slidesPendingMessage.value
  return captionsPendingMessage.value
})

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
  const msg = `Regenerate all ${count} slide images via /carousel-gen plugin?\n\n` +
    `This re-runs the plugin to produce FRESH visual concepts (absurdist hooks, surreal metaphors per the visual-hook gate) — not just a re-render of the existing prompts. Existing slide images are discarded.\n\n` +
    `Caption + hashtags + draft ID are preserved (use "Regenerate caption" separately if those need refreshing).\n\n` +
    `Total runtime: ~5-7 min (2-3 min plugin authoring + 3-4 min image rendering).`
  if (!confirm(msg)) return
  try {
    const res = await regenerateAllImagesMutation.mutateAsync(draftId.value)
    refetch()
    alert(res?.message || `Queued for ${count} slides — slides will update live via webhooks as each renders.`)
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Image regeneration failed')
  }
}

async function rerenderImagesOnly() {
  if (!draft.value) return
  const count = carouselSlides.value.length
  const msg = `Re-render only — skip /carousel-gen and use the existing slide prompts?\n\n` +
    `Use this when /carousel-gen keeps failing (Sonnet truncation) but the slides JSON is already good. ` +
    `Visual concepts stay the same; GeminiGen just re-runs against the prompts already in the DB.\n\n` +
    `Runtime: ~2-3 min (image rendering only — no plugin re-authoring).`
  if (!confirm(msg)) return
  try {
    const res = await rerenderImagesOnlyMutation.mutateAsync(draftId.value)
    refetch()
    alert(res?.message || `Re-rendering ${count} slides — slides will update live via webhooks.`)
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Image rerender failed')
  }
}

async function regenerateCaption() {
  if (!draft.value) return
  const msg = `Regenerate caption + hashtags from current slides?\n\n` +
    `Slide images, draft ID, and FSM state are preserved. The 7-block caption synthesizer (hook → subtitle → setup → pull-quote → insight bullets → engagement question → link CTA) re-runs against the current slide content.\n\n` +
    `Runtime: ~1 second (pure backend synthesis — no Claude/SSH).`
  if (!confirm(msg)) return
  try {
    const res = await regenerateCaptionMutation.mutateAsync(draftId.value)
    refetch()
    alert(res?.message || 'Caption + hashtags regenerated.')
  } catch (err) {
    alert(err?.response?.data?.error?.message || 'Caption regeneration failed')
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

// Depth Score is a 0-100 rubric scoped to format=text (hook, dwell, hashtag mix,
// AI-slop, link-in-comment compliance). For carousel, the /linkedin-validate
// branch is z.unknown() post plugin v0.5.0 — depth signal is not meaningful.
// Legacy carousel rows from plugin v0.4.x were stamped with stub `100` values;
// hide those instead of surfacing a misleading "perfect score".
const showDepthScore = computed(() =>
  draft.value
    && draft.value.format !== 'carousel'
    && draft.value.depth_score !== null
    && draft.value.depth_score !== undefined
)

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
         VIDEO CAROUSEL (IG + Threads) — display-only LinkedIn anchor whose real
         content lives on the linked repurpose job (draft.repurpose).
         ==================================================================== -->
    <template v-else-if="isVideoCarousel">
      <section class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-6 space-y-2">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="rounded bg-fuchsia-500/15 px-2 py-0.5 text-[10px] font-mono uppercase tracking-wider text-fuchsia-300">🎬 Video carousel · IG · Threads</span>
              <span class="rounded-full px-2.5 py-0.5 text-[11px] font-mono uppercase tracking-wide" :class="mood.chip">{{ meta.label }}</span>
            </div>
            <h1 class="mt-2 text-lg font-semibold text-neutral-100">{{ postTitle(draft) }}</h1>
            <p class="text-xs text-neutral-500">Published to Instagram + Threads via Zernio. LinkedIn has no video-carousel format, so this never posts to LinkedIn.</p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg border border-neutral-700 px-3 py-2 text-xs text-neutral-300 hover:bg-neutral-800/60"
            @click="openSocialStudio"
          >↪ Open in Social Studio (re-render clips)</button>
        </div>
      </section>

      <div v-if="!repurpose" class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-6 text-sm text-amber-200">
        No linked repurpose job found for this anchor — manage it from Social Studio.
      </div>

      <template v-else>
        <!-- Video preview grid -->
        <section class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-6">
          <h2 class="mb-3 text-sm font-semibold text-neutral-200">Clips <span class="text-neutral-500">· {{ repurpose.composited_videos.length }}</span></h2>
          <div v-if="repurpose.composited_videos.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <video
              v-for="(url, i) in repurpose.composited_videos"
              :key="i"
              :src="url"
              controls
              preload="metadata"
              class="aspect-[4/5] w-full rounded-lg border border-neutral-800 bg-black object-cover"
            />
          </div>
          <p v-else class="text-xs text-neutral-500">No composited clips yet — render them in Social Studio.</p>
        </section>

        <!-- Per-platform caption editors -->
        <section class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-neutral-200">Captions</h2>
            <button
              type="button"
              :disabled="!captionsDirty || updateCaptions.isPending.value"
              class="rounded-lg bg-emerald-600/90 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-600 disabled:opacity-40"
              @click="saveVideoCaptions"
            >{{ updateCaptions.isPending.value ? 'Saving…' : 'Save captions' }}</button>
          </div>

          <div>
            <label class="mb-1 flex items-center gap-2 text-xs font-medium text-fuchsia-300">Instagram caption</label>
            <textarea
              v-model="igCaptionDraft"
              rows="5"
              class="w-full rounded-lg border border-neutral-700 bg-neutral-900/60 px-3 py-2 text-sm text-neutral-100 focus:border-fuchsia-400/60 focus:outline-none"
              placeholder="Instagram caption…"
            />
            <div class="mt-1 text-right text-[11px] text-neutral-500">{{ igCaptionDraft.length }} chars</div>
          </div>

          <div>
            <label class="mb-1 flex items-center gap-2 text-xs font-medium text-neutral-300">Threads caption</label>
            <textarea
              v-model="threadsCaptionDraft"
              rows="4"
              :maxlength="THREADS_CAP"
              class="w-full rounded-lg border border-neutral-700 bg-neutral-900/60 px-3 py-2 text-sm text-neutral-100 focus:border-neutral-400/60 focus:outline-none"
              placeholder="Threads caption…"
            />
            <div class="mt-1 text-right text-[11px]" :class="threadsRemaining < 0 ? 'text-red-400' : 'text-neutral-500'">
              {{ threadsRemaining }} / {{ THREADS_CAP }} left
            </div>
          </div>
        </section>

        <!-- Publish / Schedule + per-platform status -->
        <section class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-6 space-y-4">
          <h2 class="text-sm font-semibold text-neutral-200">Publish</h2>

          <div class="flex flex-wrap gap-3">
            <div
              v-for="p in VIDEO_PLATFORMS"
              :key="p"
              class="flex items-center gap-2 rounded-lg border border-neutral-800 px-3 py-2 text-xs"
            >
              <span class="font-mono uppercase tracking-wide" :class="p === 'instagram' ? 'text-fuchsia-300' : 'text-neutral-300'">{{ p }}</span>
              <span v-if="zernioPlatformState(p)" class="rounded-full bg-neutral-800 px-2 py-0.5 text-[10px] uppercase text-neutral-300">{{ zernioPlatformState(p).status }}</span>
              <span v-else class="text-neutral-600">not sent</span>
              <a
                v-if="zernioPlatformState(p)?.url"
                :href="zernioPlatformState(p).url"
                target="_blank"
                rel="noopener"
                class="text-cyan-400 hover:underline"
              >open ↗</a>
            </div>
          </div>

          <div class="flex flex-wrap items-end gap-3 pt-1">
            <button
              type="button"
              :disabled="publishZernioMut.isPending.value || !repurpose.composited_videos.length"
              class="rounded-lg bg-emerald-600/90 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-600 disabled:opacity-40"
              @click="publishVideoNow"
            >✓ Approve &amp; Publish now (IG + Threads)</button>

            <div class="flex items-end gap-2">
              <div>
                <label class="mb-1 block text-[11px] text-neutral-500">Schedule for</label>
                <input
                  v-model="videoScheduleAt"
                  type="datetime-local"
                  class="rounded-lg border border-neutral-700 bg-neutral-900/60 px-3 py-2 text-sm text-neutral-100 focus:border-cyan-400/60 focus:outline-none"
                />
              </div>
              <button
                type="button"
                :disabled="publishZernioMut.isPending.value || !videoScheduleAt || !repurpose.composited_videos.length"
                class="rounded-lg border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 text-sm font-medium text-cyan-200 hover:bg-cyan-500/20 disabled:opacity-40"
                @click="scheduleVideo"
              >🗓 Schedule</button>
            </div>
          </div>
        </section>
      </template>
    </template>

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
              <span v-if="showDepthScore" class="text-[10px] font-mono uppercase tracking-[0.18em] text-neutral-500">
                Depth <span class="font-bold ml-0.5" :class="depthTone(draft.depth_score)">{{ draft.depth_score }}</span>
              </span>
            </div>

            <!-- Cross-post fan-out reminder (Q3.3 minimal badge cluster).
                 Clicking a chip activates the matching tab in the caption
                 section below + scrolls there — instead of router-link
                 navigating away (which used to dump operators into the
                 Facebook/Instagram/TikTok Queue List page, breaking the
                 "stay in detail" mental model). Gated on awaiting_publish
                 / published FSM states because the scanner cron only fans
                 out after approval. Carousel → FB+IG+TT; text → FB only. -->
            <div
              v-if="(draft.format === 'carousel' || draft.format === 'text')
                && (draft.status === 'awaiting_publish' || draft.status === 'published')"
              class="space-y-1.5"
            >
              <!-- Aggregate health line — at-a-glance "X of N published"
                   so the operator doesn't have to mentally aggregate the
                   per-chip statuses below. -->
              <div v-if="sosmedHealth" class="flex flex-wrap items-center gap-2 text-[11px] font-mono">
                <span class="uppercase tracking-[0.14em] text-neutral-500">Sosmed health:</span>
                <span :class="sosmedHealth.published === sosmedHealth.total ? 'text-emerald-300' : 'text-neutral-300'">
                  {{ sosmedHealth.published }}/{{ sosmedHealth.total }} published
                </span>
                <span v-if="sosmedHealth.inProgress > 0" class="text-cyan-300">
                  · {{ sosmedHealth.inProgress }} in progress
                </span>
                <span v-if="sosmedHealth.failed > 0" class="text-red-300">
                  · {{ sosmedHealth.failed }} failed
                </span>
                <span v-if="sosmedHealth.notYet > 0" class="text-neutral-500">
                  · {{ sosmedHealth.notYet }} not yet
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                <span class="font-mono uppercase tracking-[0.14em] text-neutral-500 mr-1">
                  Fanned out:
                </span>
                <!--
                  Facebook chip hidden (May 10, 2026) — FB moved off Publer
                  pipeline to direct Graph API integration. Backend code intact
                  (PLATFORM_META.facebook + activatePlatformAndScroll('facebook')
                  + draft.facebook_post still resolved server-side) for future
                  revival when direct FB integration ships.
                -->
                <template v-if="draft.format === 'carousel'">
                  <button
                    type="button"
                    @click="activatePlatformAndScroll('instagram')"
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border border-fuchsia-400/30 bg-fuchsia-500/5 text-fuchsia-300 hover:bg-fuchsia-500/15 hover:text-fuchsia-200 transition cursor-pointer"
                    title="Show Instagram caption below"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-fuchsia-400" />
                    Instagram
                    <span class="font-mono text-[9px] uppercase tracking-[0.1em] flex items-center gap-1 pl-1.5 ml-1 border-l border-fuchsia-400/20">
                      <span :class="['w-1 h-1 rounded-full', platformStatusMeta('instagram').dot]" />
                      <span :class="platformStatusMeta('instagram').text">{{ platformStatusMeta('instagram').label }}</span>
                    </span>
                  </button>
                  <button
                    v-if="platformStatus('instagram') === 'failed'"
                    type="button"
                    :disabled="!!publishingPlatform"
                    @click="doRetryPlatform('instagram')"
                    title="Retry publishing Instagram"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border border-amber-400/30 bg-amber-500/5 text-amber-300 hover:bg-amber-500/15 transition disabled:opacity-50"
                  >
                    <svg class="h-3 w-3" :class="{ 'animate-spin': publishingPlatform === 'instagram' }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
                    {{ publishingPlatform === 'instagram' ? '…' : 'Retry' }}
                  </button>
                  <button
                    type="button"
                    @click="activatePlatformAndScroll('tiktok')"
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border border-rose-400/30 bg-rose-500/5 text-rose-300 hover:bg-rose-500/15 hover:text-rose-200 transition cursor-pointer"
                    title="Show TikTok caption below"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400" />
                    TikTok
                    <span class="font-mono text-[9px] uppercase tracking-[0.1em] flex items-center gap-1 pl-1.5 ml-1 border-l border-rose-400/20">
                      <span :class="['w-1 h-1 rounded-full', platformStatusMeta('tiktok').dot]" />
                      <span :class="platformStatusMeta('tiktok').text">{{ platformStatusMeta('tiktok').label }}</span>
                    </span>
                  </button>
                  <button
                    v-if="platformStatus('tiktok') === 'failed'"
                    type="button"
                    :disabled="!!publishingPlatform"
                    @click="doRetryPlatform('tiktok')"
                    title="Retry publishing TikTok"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border border-amber-400/30 bg-amber-500/5 text-amber-300 hover:bg-amber-500/15 transition disabled:opacity-50"
                  >
                    <svg class="h-3 w-3" :class="{ 'animate-spin': publishingPlatform === 'tiktok' }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
                    {{ publishingPlatform === 'tiktok' ? '…' : 'Retry' }}
                  </button>
                </template>
                <!-- Threads — fanned out for both text and carousel formats -->
                <button
                  type="button"
                  @click="activatePlatformAndScroll('threads')"
                  class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border border-neutral-300/30 bg-neutral-200/5 text-neutral-300 hover:bg-neutral-200/15 hover:text-neutral-100 transition cursor-pointer"
                  title="Show Threads caption below"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-neutral-300" />
                  Threads
                  <span class="font-mono text-[9px] uppercase tracking-[0.1em] flex items-center gap-1 pl-1.5 ml-1 border-l border-neutral-300/20">
                    <span :class="['w-1 h-1 rounded-full', platformStatusMeta('threads').dot]" />
                    <span :class="platformStatusMeta('threads').text">{{ platformStatusMeta('threads').label }}</span>
                  </span>
                </button>
                <button
                  v-if="platformStatus('threads') === 'failed'"
                  type="button"
                  :disabled="!!publishingPlatform"
                  @click="doRetryPlatform('threads')"
                  title="Retry publishing Threads"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border border-amber-400/30 bg-amber-500/5 text-amber-300 hover:bg-amber-500/15 transition disabled:opacity-50"
                >
                  <svg class="h-3 w-3" :class="{ 'animate-spin': publishingPlatform === 'threads' }" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 14a7 7 0 0 0 12.4 2M18.5 10A7 7 0 0 0 6.1 8" /></svg>
                  {{ publishingPlatform === 'threads' ? '…' : 'Retry' }}
                </button>
                <span class="ml-1 text-neutral-600 hidden sm:inline">
                  · click to view caption
                </span>
              </div>

            </div>

            <!-- Operator-facing sentence (suppressed when live progress panel is active) -->
            <p
              v-if="!showProgressPanel"
              class="text-base text-neutral-300 leading-relaxed max-w-2xl"
            >
              {{ meta.sentence }}
            </p>

            <!-- Live progress panel — mirrors ContentEngine pattern: top-level
                 bar + phase cards + terminal-style log viewer. Renders when
                 the pipeline is in-flight (FSM state) OR carousel slides
                 are mid-render. Polls every 3s via useLinkedInDraftProgress. -->
            <div v-if="showProgressPanel" class="space-y-4 max-w-4xl">
              <!-- Top bar -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <span class="text-sm font-medium text-neutral-300">{{ formatStepName(currentStepName) }}</span>
                  <span class="text-sm font-mono text-amber-300">{{ progressPct }}%</span>
                </div>
                <div class="w-full h-2.5 rounded-full bg-neutral-800/60 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-700 ease-out"
                    :class="isProgressFailed ? 'bg-red-500' : 'bg-gradient-to-r from-amber-500 to-amber-400'"
                    :style="{ width: progressPct + '%' }"
                  />
                </div>
              </div>

              <!-- Phase cards: 3 (text) or 4 (carousel) -->
              <div
                class="grid gap-2.5"
                :class="progressPhases.length === 4 ? 'grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3'"
              >
                <div
                  v-for="phase in progressPhases"
                  :key="phase.name"
                  class="rounded-lg border px-3 py-2.5 transition-all min-w-0"
                  :class="phaseCardClass(phase)"
                >
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="text-[11px] font-bold uppercase tracking-wide truncate" :class="phaseHeaderColor(phase)">
                        {{ phase.name }}
                      </span>
                      <span class="text-[9px] font-mono px-1.5 py-0.5 rounded shrink-0" :class="phaseModelBadge(phase)">
                        {{ phase.model }}
                      </span>
                    </div>
                    <span class="text-[10px] font-mono font-medium shrink-0" :class="phaseHeaderColor(phase)">
                      {{ phaseStatusLabel(phase) }}
                    </span>
                  </div>
                  <div class="flex flex-wrap gap-1 mb-2">
                    <span
                      v-for="step in phase.steps"
                      :key="step.name"
                      class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium"
                      :class="stepChipClass(step)"
                    >
                      <svg
                        v-if="stepIsDone(step)"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                        class="w-2.5 h-2.5"
                      >
                        <path d="M4.5 12.75l6 6 9-13.5" />
                      </svg>
                      <svg
                        v-else-if="stepIsActive(step)"
                        viewBox="0 0 24 24" fill="none" class="animate-spin w-2.5 h-2.5"
                      >
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                        <path d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" fill="currentColor" class="opacity-75"/>
                      </svg>
                      {{ step.label }}
                    </span>
                  </div>
                  <div class="text-[9px] font-mono text-neutral-500 truncate">
                    {{ phase.skill }} · {{ phase.pctRange }}
                  </div>
                </div>
              </div>

              <!-- Log viewer (terminal-style). Collapses when log is empty. -->
              <div class="bg-neutral-950 rounded-lg overflow-hidden border border-neutral-800/80">
                <div class="px-3 py-2 bg-neutral-900/80 border-b border-neutral-800 flex items-center justify-between">
                  <span class="text-[11px] font-mono text-neutral-400">Generation Log</span>
                  <span class="text-[10px] font-mono text-neutral-500">{{ progressLog.length }} entries</span>
                </div>
                <div
                  ref="logContainer"
                  class="overflow-y-auto p-3 space-y-1 font-mono text-xs"
                  style="max-height: 240px; scroll-behavior: smooth;"
                >
                  <div v-if="!progressLog.length" class="text-neutral-500 py-3 text-center text-[11px]">
                    Waiting for log entries…
                  </div>
                  <div
                    v-for="(entry, i) in progressLog"
                    :key="i"
                    class="flex gap-2"
                    :class="entry.step === 'failed' ? 'text-red-400' : 'text-neutral-300'"
                  >
                    <span class="text-neutral-600 shrink-0">{{ formatLogTime(entry.timestamp) }}</span>
                    <span
                      class="shrink-0"
                      :class="entry.step === 'failed' ? 'text-red-400' : entry.step === 'completed' || entry.step === 'render_complete' ? 'text-emerald-400' : 'text-amber-400'"
                    >[{{ entry.step }}]</span>
                    <span class="break-words min-w-0">{{ entry.message }}</span>
                  </div>
                </div>
              </div>

              <!-- Footer: pulsing dot + status text -->
              <div class="flex items-center gap-2 text-xs text-neutral-500">
                <span
                  class="w-2 h-2 rounded-full"
                  :class="
                    progressPct >= 100 ? 'bg-emerald-500'
                    : isProgressFailed ? 'bg-red-500'
                    : 'bg-emerald-500 animate-pulse'
                  "
                />
                <span :class="progressPct >= 100 ? 'text-emerald-400 font-medium' : ''">
                  {{
                    progressPct >= 100 ? 'Completed'
                    : isProgressFailed ? 'Process failed'
                    : 'Process running'
                  }}
                </span>
              </div>
            </div>

            <!-- Live countdown + slot label for awaiting_publish -->
            <div v-if="draft.status === 'awaiting_publish' && draft.cancel_window_ends_at" class="flex flex-wrap items-center gap-2 text-sm">
              <div class="inline-flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-amber-400">
                  <path :d="ICON.clock" />
                </svg>
                <span class="text-neutral-400">Scheduled for</span>
                <span class="font-mono text-neutral-100 text-sm">{{ scheduledSlotLabel }}</span>
                <span class="text-neutral-500">·</span>
                <span class="text-neutral-400">in</span>
                <span class="font-mono font-bold text-amber-400 text-base">{{ liveCountdown }}</span>
              </div>
              <!-- P5: postpone chip when carousel siblings forced delays -->
              <span
                v-if="postponeCount > 0"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-mono"
                :title="`Atomic orchestrator postponed this draft ${postponeCount} time(s) waiting for cross-post siblings to be ready`"
              >
                ↻ postponed {{ postponeCount }}×
              </span>
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

            <!-- Slides-not-ready hint — surfaced when carousel publish actions
                 are disabled so operator understands WHY the green button is
                 dim. Sits above the chip warnings so it's the first thing read. -->
            <div
              v-if="['manual_review', 'awaiting_publish'].includes(draft.status) && draft.format === 'carousel' && !readyForApproval"
              class="mt-1 flex items-start gap-3 rounded-lg border border-cyan-500/25 bg-cyan-500/[0.04] px-4 py-3"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-cyan-400 shrink-0 mt-0.5">
                <path :d="ICON.clock" />
              </svg>
              <div class="min-w-0 flex-1">
                <p class="text-[11px] uppercase tracking-[0.14em] text-cyan-400 font-medium mb-0.5">Approval gated</p>
                <p class="text-sm text-cyan-100/90">{{ approvalPendingMessage }}</p>
                <!-- Live progress bar: indeterminate while re-authoring, determinate while rendering. -->
                <div v-if="liveActivity.phase === 're_authoring'" class="mt-2 h-1 w-full overflow-hidden rounded-full bg-cyan-500/15">
                  <div class="h-full w-1/3 rounded-full bg-cyan-400/70 animate-pulse motion-reduce:animate-none"></div>
                </div>
                <div v-else-if="liveActivity.phase === 'rendering' && liveActivity.renderTotal > 0" class="mt-2 h-1 w-full overflow-hidden rounded-full bg-cyan-500/15">
                  <div
                    class="h-full rounded-full bg-cyan-400 transition-[width] duration-500"
                    :style="{ width: Math.round((liveActivity.renderDone / liveActivity.renderTotal) * 100) + '%' }"
                  ></div>
                </div>
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
            <!-- Manual review: approve (in cancel window) is primary.
                 Disabled when carousel slides aren't all rendered yet —
                 publishing with pending slides hits the per-slide validation
                 gate in publishCarousel and fails after the cancel window.  -->
            <button
              v-if="draft.status === 'manual_review'"
              @click="doApprove"
              :disabled="approveMutation.isPending.value || !readyForApproval"
              :title="readyForApproval ? '' : approvalPendingMessage"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-500 text-emerald-950 hover:bg-emerald-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-emerald-500 shadow-[0_8px_24px_-12px_rgba(16,185,129,0.5)]"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.check" />
              </svg>
              {{ approveMutation.isPending.value ? 'Approving…' : 'Approve' }}
            </button>

            <!-- Schedule for later (manual_review + awaiting_publish).
                 Same readiness gate as Approve — scheduling a not-yet-rendered
                 carousel for the future would also publish with blank slides. -->
            <button
              v-if="['manual_review', 'awaiting_publish'].includes(draft.status)"
              @click="openScheduler"
              :disabled="!readyForApproval"
              :title="readyForApproval ? '' : approvalPendingMessage"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-amber-500/40 text-amber-300 hover:bg-amber-500/10 active:scale-[0.98] text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.clock" />
              </svg>
              {{ draft.status === 'awaiting_publish' ? 'Reschedule' : 'Schedule for later' }}
            </button>

            <!-- Awaiting publish: SINGLE Publish now button. Backend now
                 fans out to LinkedIn + IG/TikTok/Threads via Publer
                 automatically (May 10 unified — old "Publish to all
                 platforms" emerald button removed since publishNow is
                 already cross-platform). -->
            <button
              v-if="draft.status === 'awaiting_publish'"
              @click="doPublishNow"
              :disabled="publishNowMutation.isPending.value || !readyForApproval"
              :title="readyForApproval
                ? 'Publish LinkedIn + cross-post to Instagram/TikTok/Threads via Publer'
                : approvalPendingMessage"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500 text-amber-950 hover:bg-amber-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-amber-500 shadow-[0_8px_24px_-12px_rgba(212,168,67,0.5)]"
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

            <!-- Published cross-posts: one "Open on <platform>" link per sibling
                 that actually went live (IG/TikTok/Threads via Zernio). -->
            <a
              v-for="link in publishedExternalLinks"
              :key="link.platform"
              :href="link.url"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-white active:scale-[0.98] text-sm font-semibold transition"
              :class="OPEN_LINK_CLASS[link.platform]"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="ICON.externalLink" />
              </svg>
              Open on {{ link.label }}
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
                :disabled="approveMutation.isPending.value || !readyForApproval"
                :title="readyForApproval
                  ? (conflictData?.has_conflict ? 'Soft warning — proceed if intentional.' : '')
                  : approvalPendingMessage"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500 text-amber-950 hover:bg-amber-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-amber-500"
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

          <!-- Soft-warning conflict block. Shown only when the debounced
               check returns has_conflict=true. Reschedule button remains
               enabled — operator decides whether back-to-back is intentional. -->
          <div
            v-if="conflictData?.has_conflict"
            class="mt-3 max-w-3xl rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
          >
            <div class="flex items-start gap-2">
              <span class="text-amber-300 mt-0.5">⚠</span>
              <div class="flex-1">
                <p class="text-amber-100">
                  <strong>Conflict:</strong>
                  '{{ conflictData.conflicts[0].post_title }}' is scheduled at
                  {{ formatDateTime(conflictData.conflicts[0].scheduled_at) }}
                  ({{ conflictData.conflicts[0].minutes_apart }} min apart).
                </p>
                <p class="mt-1 text-amber-200/80 text-xs">
                  LinkedIn distributes reach poorly when same-author posts ship within 30 minutes.
                </p>
                <div
                  v-if="conflictData.suggested_alternatives?.length"
                  class="mt-2 flex flex-wrap items-center gap-1.5"
                >
                  <span class="text-xs text-amber-200/70">Suggested alternatives:</span>
                  <button
                    v-for="hour in conflictData.suggested_alternatives"
                    :key="hour"
                    type="button"
                    @click="applySuggestedHour(hour)"
                    class="rounded-md border border-amber-400/40 bg-amber-500/10 px-2 py-0.5 text-xs text-amber-100 hover:bg-amber-500/20 transition-colors"
                  >
                    {{ hour }}
                  </button>
                </div>
              </div>
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
                <!-- Slide counter + per-slide retry. Prev/next arrows moved
                     onto the image frame itself (overlay, gallery pattern). -->
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                  <div class="flex items-center gap-3 flex-wrap">
                    <p class="text-xs font-mono uppercase tracking-[0.14em] text-neutral-400">
                      Slide <span class="text-neutral-100 font-bold">{{ activeSlideIndex + 1 }}</span> / {{ carouselSlides.length }}
                    </p>
                    <!-- Hook slide (IG carousel): Image|Video preview toggle, inline
                         on the viewer so it sits in the SAME section as the slide. -->
                    <div v-if="isHookSlide" class="inline-flex items-center gap-2">
                      <div class="inline-flex rounded-lg border border-neutral-700 overflow-hidden text-[10px] font-mono uppercase tracking-[0.1em]">
                        <button
                          type="button"
                          @click="hookTab = 'image'"
                          :class="hookTab === 'image' ? 'bg-fuchsia-500/20 text-fuchsia-200' : 'text-neutral-400 hover:text-neutral-200'"
                          class="px-2.5 py-1 transition"
                        >Image</button>
                        <button
                          type="button"
                          @click="hookTab = 'video'"
                          :class="hookTab === 'video' ? 'bg-fuchsia-500/20 text-fuchsia-200' : 'text-neutral-400 hover:text-neutral-200'"
                          class="px-2.5 py-1 transition border-l border-neutral-700"
                        >Video</button>
                      </div>
                      <span
                        v-if="hookTab === 'video' && hookVideo.status"
                        class="text-[9px] font-mono uppercase tracking-[0.1em] px-2 py-0.5 rounded-full border"
                        :class="{
                          'border-emerald-500/40 bg-emerald-500/10 text-emerald-300': hookVideo.status === 'done',
                          'border-cyan-500/40 bg-cyan-500/10 text-cyan-300 animate-pulse': hookVideo.status === 'generating' || hookVideo.status === 'pending',
                          'border-rose-500/40 bg-rose-500/10 text-rose-300': hookVideo.status === 'failed',
                        }"
                      >{{ hookVideo.status }}</span>
                    </div>
                  </div>
                  <!-- Context action: regenerate the GROK clip while previewing the
                       hook video; otherwise re-render the static slide image. -->
                  <button
                    v-if="showHookVideoFrame"
                    @click="doRegenerateHookVideo"
                    :disabled="regenerateHookVideoMutation.isPending.value || hookVideo.status === 'generating' || hookVideo.status === 'pending'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md bg-fuchsia-500/10 ring-1 ring-fuchsia-500/30 text-fuchsia-300 hover:bg-fuchsia-500/20 disabled:opacity-40 transition"
                    title="Re-render the IG hook animation via GROK (~few minutes)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                      <path :d="ICON.refresh" />
                    </svg>
                    {{ regenerateHookVideoMutation.isPending.value ? 'Starting…' : 'Regenerate video' }}
                  </button>
                  <button
                    v-else
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
                </div>

                <!-- Slide frame (3:4) with overlaid prev/next gallery arrows -->
                <div class="relative mx-auto" style="max-width: min(100%, 60vh);">
                <div
                  class="rounded-xl border border-neutral-800/80 overflow-hidden bg-gradient-to-br from-neutral-950 to-neutral-900 relative"
                  style="aspect-ratio: 3 / 4; max-height: 80vh;"
                >
                  <!-- Status pill -->
                  <span
                    v-if="!showHookVideoFrame && carouselSlides[activeSlideIndex]?.image_status"
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

                  <!-- Hook-video status pill (replaces the image pill while
                       previewing the GROK clip on slide 1). -->
                  <span
                    v-if="showHookVideoFrame && hookVideo.status"
                    class="absolute top-3 right-3 z-10 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-mono uppercase tracking-[0.14em]"
                    :class="{
                      'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30': hookVideo.status === 'done',
                      'bg-cyan-500/15 text-cyan-300 ring-1 ring-cyan-500/30': hookVideo.status === 'generating' || hookVideo.status === 'pending',
                      'bg-rose-500/15 text-rose-300 ring-1 ring-rose-500/30': hookVideo.status === 'failed',
                    }"
                  >
                    <span
                      v-if="hookVideo.status === 'generating' || hookVideo.status === 'pending'"
                      class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"
                    />
                    {{ hookVideo.status }}
                  </span>

                  <!-- Static slide image (every slide) vs GROK hook video (slide 1, Video tab). -->
                  <template v-if="!showHookVideoFrame">
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
                    <p class="text-red-300 text-[10px] uppercase tracking-[0.18em] font-mono mb-2">
                      Render failed
                      <span v-if="carouselSlides[activeSlideIndex]?.last_classified_error_class" class="ml-2 inline-block px-1.5 py-0.5 rounded bg-red-950/60 text-red-200 text-[9px] tracking-normal">
                        {{ carouselSlides[activeSlideIndex].last_classified_error_class }}
                      </span>
                      <span v-if="(carouselSlides[activeSlideIndex]?.image_rewrite_tier ?? 0) > 0" class="ml-1 inline-block px-1.5 py-0.5 rounded text-[9px] tracking-normal" :class="{
                        'bg-amber-950/60 text-amber-200': carouselSlides[activeSlideIndex].image_rewrite_tier === 1,
                        'bg-red-950/60 text-red-200': carouselSlides[activeSlideIndex].image_rewrite_tier === 2,
                      }">
                        Tier {{ carouselSlides[activeSlideIndex].image_rewrite_tier }}
                      </span>
                    </p>
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
                  </template>

                  <!-- GROK hook video states (slide 1, Video tab). -->
                  <template v-else>
                    <video
                      v-if="hookVideo.status === 'done' && hookVideo.url"
                      :src="hookVideo.url"
                      controls
                      loop
                      muted
                      playsinline
                      class="absolute inset-0 w-full h-full object-contain bg-black"
                    ></video>
                    <div
                      v-else-if="hookVideo.status === 'generating' || hookVideo.status === 'pending'"
                      class="absolute inset-0 flex flex-col items-center justify-center text-center p-6"
                    >
                      <span class="inline-block h-10 w-10 rounded-full border-2 border-cyan-400/30 border-t-cyan-400 animate-spin mb-3"></span>
                      <p class="text-cyan-300 text-[10px] uppercase tracking-[0.18em] font-mono">Rendering with GROK…</p>
                      <p class="text-neutral-400 text-xs mt-1">~a few minutes · poll-driven</p>
                    </div>
                    <div
                      v-else-if="hookVideo.status === 'failed'"
                      class="absolute inset-0 flex flex-col items-center justify-center text-center p-6"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-rose-400 mb-2">
                        <path :d="ICON.alertTriangle" />
                      </svg>
                      <p class="text-rose-300 text-[10px] uppercase tracking-[0.18em] font-mono mb-2">Hook video failed</p>
                      <p v-if="hookVideo.error" class="text-rose-200 text-xs mb-4 max-w-md font-mono break-words">{{ hookVideo.error }}</p>
                      <button
                        @click="doRegenerateHookVideo"
                        :disabled="regenerateHookVideoMutation.isPending.value"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-fuchsia-500 text-fuchsia-950 hover:bg-fuchsia-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50"
                      >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                          <path :d="ICON.refresh" />
                        </svg>
                        Retry hook video
                      </button>
                    </div>
                    <div
                      v-else
                      class="absolute inset-0 flex flex-col items-center justify-center text-center p-6"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-fuchsia-400/70 mb-3">
                        <path :d="ICON.image" />
                      </svg>
                      <p class="text-neutral-300 text-sm mb-4 max-w-xs">No hook video yet — render a GROK animation of this slide for the Instagram carousel.</p>
                      <button
                        @click="doRegenerateHookVideo"
                        :disabled="regenerateHookVideoMutation.isPending.value"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-fuchsia-500 text-fuchsia-950 hover:bg-fuchsia-400 active:scale-[0.98] text-sm font-semibold transition disabled:opacity-50"
                      >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                          <path :d="ICON.refresh" />
                        </svg>
                        Generate hook video
                      </button>
                    </div>
                  </template>
                </div>
                  <!-- Gallery-style prev/next arrows overlaid on the frame.
                       Placed in the relative wrapper above the frame so they
                       can sit at the vertical center of the slide regardless
                       of frame height. -->
                  <button
                    @click="prevSlide"
                    :disabled="activeSlideIndex === 0"
                    class="absolute left-2 top-1/2 -translate-y-1/2 z-20 inline-flex items-center justify-center w-9 h-9 rounded-full bg-neutral-900/80 backdrop-blur-md ring-1 ring-neutral-700 hover:bg-neutral-800 hover:ring-amber-500/50 disabled:opacity-0 disabled:pointer-events-none transition-all shadow-lg"
                    aria-label="Previous slide"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-200">
                      <path :d="ICON.chevronLeft" />
                    </svg>
                  </button>
                  <button
                    @click="nextSlide"
                    :disabled="activeSlideIndex === carouselSlides.length - 1"
                    class="absolute right-2 top-1/2 -translate-y-1/2 z-20 inline-flex items-center justify-center w-9 h-9 rounded-full bg-neutral-900/80 backdrop-blur-md ring-1 ring-neutral-700 hover:bg-neutral-800 hover:ring-amber-500/50 disabled:opacity-0 disabled:pointer-events-none transition-all shadow-lg"
                    aria-label="Next slide"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-200">
                      <path :d="ICON.chevronRight" />
                    </svg>
                  </button>
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

              <!-- Per-platform caption switcher — in-place tabs that swap
                   ONLY the caption + hashtags + status. The carousel slide
                   images above are shared across every platform (rendered
                   once via /carousel-gen, reused for FB/IG/TikTok). Click
                   does NOT navigate; it toggles the active platform copy
                   below. -->
              <div ref="captionTabsRef" class="pt-3 border-t border-neutral-800/60 space-y-3">
                <!-- Unified "Regenerate ALL captions" — single action, fans out
                     across LinkedIn + IG + TT + Threads. Per-platform outcomes
                     surface in the result panel below + a toast notification.
                     Always visible regardless of active tab so operator can
                     trigger fan-out from any platform context. -->
                <div class="flex items-center justify-between gap-3">
                  <span class="text-[10px] font-mono uppercase tracking-[0.14em] text-neutral-500">
                    Per-platform captions
                  </span>

                  <button
                    type="button"
                    @click="doRegenerateAllCaptions"
                    :disabled="regenerateAllCaptionsMutation.isPending.value"
                    class="inline-flex items-center gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-xs font-mono uppercase tracking-[0.12em] text-amber-200 hover:bg-amber-500/20 hover:text-amber-100 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm shrink-0"
                    title="Refresh caption + hashtags + link_comment across LinkedIn / Instagram / TikTok / Threads in one click"
                  >
                    <svg
                      v-if="regenerateAllCaptionsMutation.isPending.value"
                      viewBox="0 0 24 24"
                      class="h-3.5 w-3.5 animate-spin"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <circle cx="12" cy="12" r="9" stroke-opacity="0.25" />
                      <path d="M21 12a9 9 0 0 0-9-9" stroke-linecap="round" />
                    </svg>
                    <svg v-else viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="23 4 23 10 17 10" />
                      <polyline points="1 20 1 14 7 14" />
                      <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                    </svg>
                    <span v-if="regenerateAllCaptionsMutation.isPending.value">Refreshing all platforms…</span>
                    <span v-else>Regenerate all captions</span>
                  </button>
                </div>

                <!-- Inline result panel — shows per-platform outcomes after
                     the unified fan-out completes. Auto-clears after 12s. -->
                <div
                  v-if="regenAllResults || regenerateAllCaptionsMutation.isPending.value"
                  class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3"
                >
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-[10px] font-mono uppercase tracking-[0.14em] text-amber-300">
                      <span v-if="regenerateAllCaptionsMutation.isPending.value" class="inline-block h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse" />
                      <span v-else class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400" />
                      <span v-if="regenerateAllCaptionsMutation.isPending.value">Caption fan-out in progress…</span>
                      <span v-else>Caption fan-out complete</span>
                    </div>
                    <button
                      v-if="regenAllResults"
                      type="button"
                      @click="clearRegenAllResults"
                      class="text-[10px] font-mono uppercase tracking-[0.12em] text-neutral-500 hover:text-neutral-300 transition"
                    >
                      dismiss ✕
                    </button>
                  </div>

                  <!-- Per-platform badge grid -->
                  <div v-if="regenAllResults" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div
                      v-for="(result, platform) in regenAllResults"
                      :key="platform"
                      :class="[
                        'rounded-md border px-2 py-1.5 text-[11px] flex flex-col gap-0.5',
                        result.outcome === 'refreshed' && 'border-emerald-400/40 bg-emerald-500/5 text-emerald-200',
                        result.outcome === 'dispatched' && 'border-cyan-400/40 bg-cyan-500/5 text-cyan-200',
                        result.outcome === 'in_progress' && 'border-amber-400/40 bg-amber-500/5 text-amber-200',
                        result.outcome === 'missing' && 'border-neutral-700/60 bg-neutral-900/40 text-neutral-500',
                        result.outcome === 'failed' && 'border-red-400/40 bg-red-500/5 text-red-200',
                      ]"
                    >
                      <div class="flex items-center justify-between">
                        <span class="font-medium uppercase tracking-wider text-[10px]">{{ platform }}</span>
                        <span class="font-mono text-[14px] leading-none">
                          <span v-if="result.outcome === 'refreshed'">✓</span>
                          <span v-else-if="result.outcome === 'dispatched'">→</span>
                          <span v-else-if="result.outcome === 'in_progress'">⟳</span>
                          <span v-else-if="result.outcome === 'missing'">—</span>
                          <span v-else-if="result.outcome === 'failed'">✕</span>
                          <span v-else>?</span>
                        </span>
                      </div>
                      <span class="text-[10px] opacity-80">{{ result.message || result.outcome }}</span>
                    </div>
                  </div>

                  <!-- Pending state placeholder grid -->
                  <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div
                      v-for="p in ['linkedin', 'instagram', 'tiktok', 'threads']"
                      :key="p"
                      class="rounded-md border border-neutral-700/40 bg-neutral-900/40 px-2 py-1.5 text-[11px] flex items-center justify-between text-neutral-500"
                    >
                      <span class="font-medium uppercase tracking-wider text-[10px]">{{ p }}</span>
                      <span class="inline-block h-3 w-3 border border-neutral-600 border-t-amber-400 rounded-full animate-spin" />
                    </div>
                  </div>
                </div>

                <nav
                  class="inline-flex flex-wrap gap-1 rounded-lg border border-neutral-700/60 bg-neutral-950/40 p-1"
                  role="tablist"
                  aria-label="Switch caption per platform"
                >
                  <button
                    v-for="(meta, key) in VISIBLE_PLATFORM_META"
                    :key="key"
                    type="button"
                    role="tab"
                    :aria-selected="activePlatform === key ? 'true' : 'false'"
                    @click="activePlatform = key"
                    :class="[
                      'inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium uppercase tracking-wider transition-colors',
                      activePlatform === key
                        ? `${meta.activeBg} shadow-sm`
                        : 'border-transparent text-neutral-400 hover:bg-neutral-900/60 hover:text-neutral-200',
                    ]"
                  >
                    <span :class="activePlatform === key ? '' : meta.accent">{{ meta.label }}</span>
                    <span
                      v-if="key !== 'linkedin' && !platformPostFor(key)"
                      class="text-[9px] font-mono opacity-70"
                      title="Cross-post variant not generated yet"
                    >
                      —
                    </span>
                  </button>
                </nav>
              </div>

              <!-- Caption renders for the active platform. When a cross-post
                   sibling doesn't exist yet (e.g. FB draft hasn't fanned out),
                   show an explanatory empty state instead of LinkedIn copy. -->
              <template v-if="activePlatformExists">
                <!-- Status pill for non-LinkedIn platforms (LinkedIn already
                     has its own status hero panel up top). -->
                <div
                  v-if="activePlatform !== 'linkedin'"
                  class="text-[10px] font-mono uppercase tracking-[0.14em] text-neutral-500"
                >
                  {{ PLATFORM_META[activePlatform].label }} · {{ activePlatformPost.status || 'pending' }}
                </div>

                <!-- Title — REQUIRED by Publer for TikTok photo carousel
                     (≤90 chars per Publer API spec). LinkedIn/IG/Threads
                     don't need it as a separate field — caption hook
                     covers the same role. -->
                <div
                  v-if="activePlatform === 'tiktok' && activePlatformPost.title"
                  class="rounded-md border border-neutral-800/60 bg-neutral-950/40 px-3 py-2"
                >
                  <p class="text-[10px] font-mono uppercase tracking-[0.14em] text-neutral-500 mb-1">
                    Title
                    <span class="ml-1 text-neutral-600">· {{ activePlatformPost.title.length }}/90</span>
                    <span
                      v-if="activePlatformPost.title.length > 90"
                      class="ml-2 text-red-400"
                    >Publer hard cap exceeded</span>
                  </p>
                  <p class="text-[15px] text-neutral-100 font-medium leading-snug">{{ activePlatformPost.title }}</p>
                </div>

                <!-- Caption body — for TikTok, strip duplicate first line
                     when it exactly matches the title (plugin currently
                     echoes title as caption hook; until plugin v0.5.0
                     fixes this, dedupe display-side). -->
                <div v-if="captionDisplayBody && captionDisplayBody.trim() !== ''" class="whitespace-pre-wrap text-neutral-200 leading-relaxed text-[15px]">
                  {{ captionDisplayBody }}
                </div>
                <div v-else class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-200">
                  <strong class="text-amber-300">No caption.</strong> This {{ PLATFORM_META[activePlatform].label }} variant will publish without a body — typically 50% lower reach.
                </div>

                <div v-if="activePlatformPost.hashtags.length > 0" class="flex flex-wrap gap-x-2 gap-y-1">
                  <span v-for="tag in activePlatformPost.hashtags" :key="tag" class="text-cyan-400 text-sm">{{ tag }}</span>
                </div>

                <!-- Per-platform caption regen buttons. LinkedIn carousel uses
                     the sync caption-only path (~1s, slides untouched).
                     Instagram/TikTok/Threads use the full Generate*Post job
                     dispatch (~30s — no slides on those platforms). LinkedIn
                     text format intentionally omitted: caption IS the post body
                     so it requires the main Regenerate button (full pipeline).
                     Server enforces FSM gating (409 if mid-pipeline). -->
                <div class="flex items-center gap-2 pt-2 border-t border-neutral-800/60">
                  <button
                    v-if="activePlatform === 'linkedin' && draft.format === 'carousel'"
                    type="button"
                    @click="doRegenerateLinkedInCaption"
                    :disabled="regenerateCaptionMutation.isPending.value"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-[0.12em] rounded-md border border-cyan-500/40 bg-cyan-500/10 text-cyan-300 hover:bg-cyan-500/20 hover:text-cyan-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Re-synth caption + hashtags + link from existing slides (slides untouched, ~1s sync)"
                  >
                    <span v-if="regenerateCaptionMutation.isPending.value">⟳ Refreshing…</span>
                    <span v-else>↻ Regenerate caption · sync</span>
                  </button>

                  <button
                    v-if="['instagram', 'tiktok', 'threads'].includes(activePlatform)"
                    type="button"
                    @click="doRegeneratePlatform(activePlatform)"
                    :disabled="platformRegenMap[activePlatform].mutation.isPending.value"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-[0.12em] rounded-md border border-cyan-500/40 bg-cyan-500/10 text-cyan-300 hover:bg-cyan-500/20 hover:text-cyan-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    :title="`Reset ${PLATFORM_META[activePlatform].label} caption + hashtags + dispatch fresh job (~30s)`"
                  >
                    <span v-if="platformRegenMap[activePlatform].mutation.isPending.value">⟳ Dispatching…</span>
                    <span v-else>↻ Regenerate {{ PLATFORM_META[activePlatform].label }} caption</span>
                  </button>

                  <span class="text-[10px] font-mono uppercase tracking-[0.12em] text-neutral-500 ml-auto">
                    <template v-if="activePlatform === 'linkedin' && draft.format === 'text'">
                      Use top-right Regenerate for text — full pipeline
                    </template>
                  </span>
                </div>
              </template>
              <div
                v-else
                class="rounded-lg border border-neutral-800 bg-neutral-900/40 px-4 py-3 text-sm text-neutral-400"
              >
                <p class="text-neutral-300 font-medium mb-1">{{ PLATFORM_META[activePlatform].label }} variant not generated yet.</p>
                <p class="text-xs mb-3">The cross-post fan-out for this platform hasn't run. Slides above will be reused once it does.</p>

                <!-- One-off "Generate Threads" button — useful for LinkedIn drafts
                     that pre-date the /threads-gen plugin (May 10, 2026). Only
                     surfaces on the Threads tab to avoid clutter on FB/IG/TT
                     where there's no equivalent backfill flow yet. -->
                <button
                  v-if="activePlatform === 'threads'"
                  type="button"
                  @click="doGenerateThreads"
                  :disabled="generateThreadsMutation.isPending.value"
                  class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-mono uppercase tracking-[0.12em] rounded-md border border-purple-500/40 bg-purple-500/10 text-purple-300 hover:bg-purple-500/20 hover:text-purple-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span v-if="generateThreadsMutation.isPending.value">⟳ Generating…</span>
                  <span v-else>🧵 Generate Threads now</span>
                </button>
              </div>

              <!-- First-comment bubble — surfaces the branded short URL that
                   will be auto-posted as first comment on platforms that
                   support it (LinkedIn native, IG/Threads via Publer).
                   TikTok intentionally excluded — Publer API has no
                   first-comment support for TikTok, URL lives directly in
                   the caption body so the bubble would just duplicate. -->
              <div
                v-if="['linkedin', 'instagram', 'threads'].includes(activePlatform) && getActivePlatformLinkComment()"
                class="mt-2 p-3 rounded-lg border border-cyan-500/20 bg-cyan-500/5"
              >
                <p class="text-[10px] text-cyan-400 font-mono uppercase tracking-[0.14em] mb-1">
                  First comment (auto-posted +1 min)
                </p>
                <p class="text-sm text-neutral-300 break-all">{{ getActivePlatformLinkComment() }}</p>
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

            <div v-if="showDepthScore">
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
                v-if="draft.format === 'carousel' && carouselSlides.length > 0 && !['pending_generation', 'generating', 'validating'].includes(draft.status)"
                @click="regenerateCaption"
                :disabled="regenerateCaptionMutation.isPending.value"
                title="Re-synth caption + hashtags from current slides (~1s, slide images preserved)"
                class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-sm font-medium text-neutral-200 ring-1 ring-neutral-800 transition group disabled:opacity-50"
              >
                <span class="inline-flex items-center gap-2">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-400 group-hover:text-emerald-400 transition-colors">
                    <path :d="ICON.pencil" />
                  </svg>
                  {{ regenerateCaptionMutation.isPending.value ? 'Synthesizing…' : 'Regenerate caption' }}
                </span>
              </button>

              <button
                v-if="draft.format === 'carousel' && carouselSlides.length > 0"
                @click="regenerateAllImages"
                :disabled="regenerateAllImagesMutation.isPending.value"
                title="Regenerate all slide images via /carousel-gen plugin (~5-7 min, keeps draft ID + caption)"
                class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-sm font-medium text-neutral-200 ring-1 ring-neutral-800 transition group disabled:opacity-50"
              >
                <span class="inline-flex items-center gap-2">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-400 group-hover:text-amber-400 transition-colors">
                    <path :d="ICON.image" />
                  </svg>
                  {{ regenerateAllImagesMutation.isPending.value ? 'Dispatching…' : 'Regenerate All Images' }}
                </span>
              </button>

              <button
                v-if="draft.format === 'carousel' && carouselSlides.length > 0"
                @click="rerenderImagesOnly"
                :disabled="rerenderImagesOnlyMutation.isPending.value"
                title="Re-render PNGs from existing slide prompts. Skips /carousel-gen entirely (~2-3 min). Use when /carousel-gen keeps failing but slides JSON is good."
                class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-sm font-medium text-neutral-200 ring-1 ring-neutral-800 transition group disabled:opacity-50"
              >
                <span class="inline-flex items-center gap-2">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-neutral-400 group-hover:text-cyan-400 transition-colors">
                    <path :d="ICON.refresh" />
                  </svg>
                  {{ rerenderImagesOnlyMutation.isPending.value ? 'Dispatching…' : 'Re-render Images Only' }}
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
