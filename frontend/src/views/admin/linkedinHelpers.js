/**
 * Shared display helpers for LinkedIn admin views.
 *
 * - STATUS_META: per-status label + accent + one-sentence operator copy
 * - REASON_LABELS: humanizes pipeline_state_log reason strings
 * - TRANSITION_LABELS: humanizes from→to pairs (used in timeline)
 * - QUEUE_KEY / FEED_KEY: sessionStorage keys for back-nav tab restore
 * - ICON: SVG path strings (24x24 viewBox, stroke="currentColor", strokeWidth=1.5)
 *
 * Kept lean — just data and tiny pure functions, no Vue runtime imports.
 */

// --- Status metadata ---------------------------------------------------------
// `tone` drives accent colors via CSS classes elsewhere. `mood`:
//   'progress' (cyan, animated) | 'pending' (neutral, idle)
//   'decision' (amber, awaits operator) | 'live' (amber, ticking down)
//   'success' (emerald, terminal good) | 'error' (red, terminal bad)
//   'archive' (neutral, terminal idle)
export const STATUS_META = {
  pending_generation: {
    label: 'Queued',
    short: 'Pending',
    mood: 'pending',
    sentence: 'Queued for generation. The plugin will pick this up on the next worker tick.',
  },
  generating: {
    label: 'Generating',
    short: 'Generating',
    mood: 'progress',
    sentence: 'The /linkedin-gen plugin is running. Typical runs finish in 30-90 seconds.',
  },
  validating: {
    label: 'Validating',
    short: 'Validating',
    mood: 'progress',
    sentence: 'Plugin returned. Backend is scoring depth + checking hard-fail rules.',
  },
  manual_review: {
    label: 'Needs review',
    short: 'Manual review',
    mood: 'decision',
    sentence: 'Validation flagged something. Review the draft, then approve, edit, or regenerate.',
  },
  awaiting_publish: {
    label: 'Awaiting publish',
    short: 'Scheduled',
    mood: 'live',
    sentence: 'Approved. Will publish automatically when the cancel window ends.',
  },
  published: {
    label: 'Published',
    short: 'Published',
    mood: 'success',
    sentence: 'Live on LinkedIn. The first comment with the blog link posts a few seconds after.',
  },
  cancelled: {
    label: 'Cancelled',
    short: 'Cancelled',
    mood: 'archive',
    sentence: 'Cancelled by admin. Regenerate to start a fresh attempt.',
  },
  failed: {
    label: 'Failed',
    short: 'Failed',
    mood: 'error',
    sentence: 'The plugin or pipeline hit a terminal error. Read the message, then regenerate.',
  },

  // ---------------------------------------------------------------------------
  // Synthetic statuses — not stored on the row; derived from
  // (status='manual_review' + format='carousel' + slide image state) so the
  // badge reflects what the operator actually needs to do, instead of a
  // misleading "MANUAL REVIEW" while images haven't even started rendering.
  // Resolved via effectiveStatusMeta() below.
  // ---------------------------------------------------------------------------
  carousel_render_pending: {
    label: 'Awaiting render',
    short: 'Awaiting render',
    mood: 'decision',
    sentence: 'Slide JSON is ready but images haven\'t been dispatched yet. Click Approve to start GeminiGen rendering.',
  },
  carousel_render_active: {
    label: 'Rendering slides',
    short: 'Rendering',
    mood: 'progress',
    sentence: 'GeminiGen is rendering the slide images. Each slide takes ~30-60s; the page polls every 5 seconds.',
  },
  carousel_render_failed: {
    label: 'Render failed',
    short: 'Render failed',
    mood: 'error',
    sentence: 'All slide image renders failed (typically GeminiGen safety refusal or rate limit). Restart from blog or retry individual slides.',
  },
  carousel_render_partial: {
    label: 'Render partial',
    short: 'Partial render',
    mood: 'decision',
    sentence: 'Some slides rendered, others failed. Retry failed slides individually before approving.',
  },
}

export function statusMeta(status) {
  return STATUS_META[status] || {
    label: status || 'Unknown',
    short: status || '—',
    mood: 'pending',
    sentence: '',
  }
}

/**
 * Inspect a carousel draft's slide image lifecycle. Returns one of:
 *   'ready'      — every slide has image_status='done' + image_url
 *   'pending'    — no slide has even started rendering (image_status='pending'
 *                   or null on every slide)
 *   'generating' — at least one slide is in flight (image_status='generating')
 *   'partial'    — mix of done + failed (no in-flight)
 *   'failed'     — all slides failed
 *
 * Returns 'ready' for non-carousel drafts (synthetic status doesn't apply).
 */
export function inspectCarouselRenderState(draft) {
  if (!draft || draft.format !== 'carousel') return 'ready'
  const slides = Array.isArray(draft.carousel_slides) ? draft.carousel_slides : []
  if (slides.length === 0) return 'pending'

  let done = 0
  let inFlight = 0
  let failed = 0
  for (const slide of slides) {
    const s = slide?.image_status
    if (s === 'done' && slide?.image_url) done++
    else if (s === 'generating') inFlight++
    else if (s === 'failed') failed++
    // 'pending' or null falls through — counted as not-yet-started below
  }

  if (done === slides.length) return 'ready'
  if (inFlight > 0) return 'generating'
  if (failed === slides.length) return 'failed'
  if (done > 0 || failed > 0) return 'partial'
  return 'pending'
}

/**
 * Resolve the BADGE metadata for a draft, taking carousel image rendering
 * state into account. Use this in queue / posts / detail badge renders
 * instead of statusMeta(draft.status) directly — otherwise carousels in
 * `manual_review` state with un-rendered slides show a misleading "MANUAL
 * REVIEW" badge when the operator hasn't actually been asked to review
 * anything yet.
 */
export function effectiveStatusMeta(draft) {
  if (!draft) return statusMeta(null)

  // Only carousel + manual_review needs the synthetic override. Other states
  // (generating/validating/failed/awaiting_publish/published/cancelled) are
  // unambiguous — keep the FSM-direct meta.
  if (draft.format === 'carousel' && draft.status === 'manual_review') {
    const renderState = inspectCarouselRenderState(draft)
    switch (renderState) {
      case 'pending':    return STATUS_META.carousel_render_pending
      case 'generating': return STATUS_META.carousel_render_active
      case 'failed':     return STATUS_META.carousel_render_failed
      case 'partial':    return STATUS_META.carousel_render_partial
      // 'ready' falls through — slides are rendered, operator legitimately
      // needs to manual-review the now-visible carousel.
    }
  }

  return statusMeta(draft.status)
}

// Tailwind class fragments per mood — kept short, applied via :class binding.
export const MOOD_CLASSES = {
  progress: {
    chip: 'bg-cyan-500/15 text-cyan-400 ring-1 ring-cyan-500/30',
    dot: 'bg-cyan-400 animate-pulse',
    rail: 'from-cyan-500/40 via-cyan-500/10 to-transparent',
  },
  pending: {
    chip: 'bg-indigo-500/15 text-indigo-300 ring-1 ring-indigo-500/30',
    dot: 'bg-indigo-400',
    rail: 'from-indigo-500/30 via-indigo-500/5 to-transparent',
  },
  decision: {
    chip: 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/40',
    dot: 'bg-amber-400 animate-pulse',
    rail: 'from-amber-500/40 via-amber-500/10 to-transparent',
  },
  live: {
    chip: 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/40',
    dot: 'bg-amber-400 animate-pulse',
    rail: 'from-amber-500/40 via-amber-500/10 to-transparent',
  },
  success: {
    chip: 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30',
    dot: 'bg-emerald-400',
    rail: 'from-emerald-500/30 via-emerald-500/5 to-transparent',
  },
  error: {
    chip: 'bg-red-500/15 text-red-300 ring-1 ring-red-500/30',
    dot: 'bg-red-400',
    rail: 'from-red-500/40 via-red-500/10 to-transparent',
  },
  archive: {
    chip: 'bg-neutral-500/15 text-neutral-400 ring-1 ring-neutral-500/30',
    dot: 'bg-neutral-500',
    rail: 'from-neutral-500/20 via-neutral-500/5 to-transparent',
  },
}

// --- Pipeline log humanization ----------------------------------------------
export const REASON_LABELS = {
  cron_scan_detected_new_post: 'Detected in blog scan',
  plugin_dispatch_start: 'Plugin dispatch started',
  plugin_validate_start: 'Validation started',
  plugin_passed_gate: 'Validation gate passed',
  plugin_failed_gate: 'Validation gate failed',
  generation_error: 'Generation error',
  admin_approve: 'Approved by admin',
  admin_cancel: 'Cancelled by admin',
  admin_regenerate: 'Regenerate triggered',
  admin_publish_now: 'Published manually',
  kill_switch_demotion: 'Auto-publish disabled — held for review',
  publish_succeeded: 'Published to LinkedIn',
  publish_failed: 'Publish failed',
  scan: 'Blog scan',
}

export function reasonLabel(reason) {
  if (!reason) return ''
  return REASON_LABELS[reason] || reason.replace(/_/g, ' ')
}

// One-line summary of a from→to transition for the timeline.
export function transitionSummary(entry) {
  if (!entry) return ''
  const { from, to, reason } = entry
  // Prefer a humanized reason when present
  if (reason && REASON_LABELS[reason]) return REASON_LABELS[reason]
  // Otherwise compose "{From short} → {To short}"
  const fromLabel = STATUS_META[from]?.short || from || 'start'
  const toLabel = STATUS_META[to]?.short || to || '—'
  return `${fromLabel} → ${toLabel}`
}

// --- Back-navigation memory --------------------------------------------------
// We persist the active queue/feed tab in sessionStorage so the detail page's
// back button returns the operator to the same tab they came from. Survives
// hard refresh within the session, cleared on tab close.
export const QUEUE_TAB_KEY = 'linkedin:queue:tab'
export const FEED_TAB_KEY = 'linkedin:feed:tab'

// --- Format display ----------------------------------------------------------
export function formatChip(format) {
  return format === 'carousel' ? 'CAROUSEL' : 'TEXT'
}

// --- Time helpers ------------------------------------------------------------
export function relativeTime(datetime) {
  if (!datetime) return ''
  const diff = Date.now() - new Date(datetime).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

export function countdownTo(datetime) {
  if (!datetime) return ''
  const diff = new Date(datetime).getTime() - Date.now()
  if (diff < 0) return 'publishing now'
  const mins = Math.floor(diff / 60000)
  const secs = Math.floor((diff % 60000) / 1000)
  if (mins === 0) return `${secs}s`
  return `${mins}m ${secs}s`
}

export function formatDateTime(dt) {
  if (!dt) return '—'
  const d = new Date(dt)
  // "Apr 28 · 03:08" — short, scannable, no year (irrelevant within session)
  const month = d.toLocaleString('en-US', { month: 'short' })
  const day = d.getDate()
  const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  return `${month} ${day} · ${time}`
}

// --- SVG icon paths ----------------------------------------------------------
// 24x24 viewBox, designed for stroke="currentColor" strokeWidth=1.5 fill="none".
// Pulled from Phosphor-style line set, manually traced for license-free use.
export const ICON = {
  arrowLeft: 'M19 12H5M12 19l-7-7 7-7',
  arrowUpRight: 'M7 17L17 7M7 7h10v10',
  check: 'M5 13l4 4L19 7',
  x: 'M6 18L18 6M6 6l12 12',
  refresh: 'M3 12a9 9 0 0 1 15.5-6.3L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.5 6.3L3 16M3 21v-5h5',
  pencil: 'M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z',
  send: 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z',
  image: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM8.5 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM21 15l-5-5L5 21',
  externalLink: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3',
  alertCircle: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM12 8v4M12 16h.01',
  alertTriangle: 'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01',
  clock: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM12 6v6l4 2',
  inbox: 'M22 12h-6l-2 3h-4l-2-3H2M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z',
  loader: 'M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35',
  chevronLeft: 'M15 18l-6-6 6-6',
  chevronRight: 'M9 18l6-6-6-6',
  linkedin: 'M4 4h4v16H4V4zm2 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4zM10 8h4v2c.5-1 2-2.5 4-2.5 4 0 6 2.5 6 6v6.5h-4v-6c0-2-1-3-2.5-3s-3 1.5-3 3v6h-4V8z',
  sparkle: 'M12 2l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6zM18 13l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z',
}
