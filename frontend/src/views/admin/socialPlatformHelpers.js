/**
 * Shared helpers for the cross-post admin views (Facebook, Instagram,
 * TikTok). Lifted + generalized from `linkedinHelpers.js` — the
 * cross-post FSM is simpler (7 states vs LinkedIn's 8), so this is a
 * trimmed mirror.
 *
 * FSM (per FacebookPostStatus, InstagramPostStatus, TiktokPostStatus —
 * all identical structure):
 *   pending_generation → generating → awaiting_review → publishing → published
 *                                ↘ failed                ↘ failed     ↘ failed
 *                                                          ↘ cancelled
 */

export const PLATFORMS = {
  facebook: {
    label: 'Facebook',
    short: 'FB',
    color: 'sky',
    accent: '#1877F2',
    routePrefix: '/admin/facebook-posts',
    detailPrefix: '/admin/facebook-drafts',
  },
  instagram: {
    label: 'Instagram',
    short: 'IG',
    color: 'fuchsia',
    accent: '#E4405F',
    routePrefix: '/admin/instagram-posts',
    detailPrefix: '/admin/instagram-drafts',
  },
  tiktok: {
    label: 'TikTok',
    short: 'TT',
    color: 'rose',
    accent: '#FE2C55',
    routePrefix: '/admin/tiktok-posts',
    detailPrefix: '/admin/tiktok-drafts',
  },
}

export const STATUS_META = {
  pending_generation: {
    label: 'PENDING',
    text: 'text-amber-700 dark:text-amber-300',
    bg: 'bg-amber-100 dark:bg-amber-900/30',
    border: 'border-amber-200 dark:border-amber-800',
    description: 'Queued for caption generation.',
    mood: 'in_progress',
  },
  generating: {
    label: 'GENERATING',
    text: 'text-cyan-700 dark:text-cyan-300',
    bg: 'bg-cyan-100 dark:bg-cyan-900/30',
    border: 'border-cyan-200 dark:border-cyan-800',
    description: 'Caption authoring in progress (~30-60s).',
    mood: 'in_progress',
  },
  awaiting_review: {
    label: 'AWAITING REVIEW',
    text: 'text-violet-700 dark:text-violet-300',
    bg: 'bg-violet-100 dark:bg-violet-900/30',
    border: 'border-violet-200 dark:border-violet-800',
    description: 'Caption ready — review and approve to publish.',
    mood: 'attention',
  },
  publishing: {
    label: 'PUBLISHING',
    text: 'text-blue-700 dark:text-blue-300',
    bg: 'bg-blue-100 dark:bg-blue-900/30',
    border: 'border-blue-200 dark:border-blue-800',
    description: 'Sending to Publer for cross-post.',
    mood: 'in_progress',
  },
  published: {
    label: 'PUBLISHED',
    text: 'text-emerald-700 dark:text-emerald-300',
    bg: 'bg-emerald-100 dark:bg-emerald-900/30',
    border: 'border-emerald-200 dark:border-emerald-800',
    description: 'Live on the platform.',
    mood: 'success',
  },
  failed: {
    label: 'FAILED',
    text: 'text-red-700 dark:text-red-300',
    bg: 'bg-red-100 dark:bg-red-900/30',
    border: 'border-red-200 dark:border-red-800',
    description: 'Caption generation or publish hit an error. Click Regenerate.',
    mood: 'error',
  },
  cancelled: {
    label: 'CANCELLED',
    text: 'text-neutral-700 dark:text-neutral-300',
    bg: 'bg-neutral-100 dark:bg-neutral-800',
    border: 'border-neutral-200 dark:border-neutral-700',
    description: 'Draft cancelled by operator.',
    mood: 'neutral',
  },
}

export function statusMeta(status) {
  return STATUS_META[status] ?? {
    label: (status || 'UNKNOWN').toUpperCase(),
    text: 'text-neutral-700 dark:text-neutral-300',
    bg: 'bg-neutral-100 dark:bg-neutral-800',
    border: 'border-neutral-200 dark:border-neutral-700',
    description: '',
    mood: 'neutral',
  }
}

const FEED_STATUSES = ['publishing', 'published', 'cancelled']
const QUEUE_STATUSES = ['pending_generation', 'generating', 'awaiting_review', 'failed']

export function isFeedStatus(status) {
  return FEED_STATUSES.includes(status)
}

export function isQueueStatus(status) {
  return QUEUE_STATUSES.includes(status)
}

/**
 * Title resolver — prefers EN translation, falls back to first available.
 * Mirrors the precedent in linkedinHelpers.js.
 */
export function resolvePostTitle(draft) {
  if (!draft?.post?.translations) return null
  const en = draft.post.translations.find((t) => t.language === 'en')
  if (en?.title) return en.title
  const first = draft.post.translations[0]
  return first?.title ?? null
}

export function relativeTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const diff = (Date.now() - d.getTime()) / 1000
  if (Number.isNaN(diff)) return ''
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`
  return d.toLocaleDateString()
}

export function formatHashtags(tags) {
  if (!Array.isArray(tags) || tags.length === 0) return ''
  return tags
    .map((t) => (t.startsWith('#') ? t : `#${t}`))
    .join(' ')
}

export function transitionSummary(entry) {
  if (!entry || typeof entry !== 'object') return ''
  const reason = (entry.reason || '').toLowerCase()
  switch (reason) {
    case 'cross_post_scan_fanout':
      return 'Detected by cross-post scanner'
    case 'plugin_dispatch_start':
      return 'Caption generation started'
    case 'generation_complete':
      return 'Caption ready'
    case 'generation_failed':
      return 'Caption generation failed'
    case 'admin_approved':
      return 'Approved by operator'
    case 'admin_cancelled':
      return 'Cancelled by operator'
    case 'admin_regenerate':
      return 'Regeneration requested'
    case 'text_format_reuse_complete':
      return 'Reused LinkedIn content (text format)'
    case 'carousel_format_complete':
      return 'Carousel caption assembled'
    case 'stale_retry_recovery':
      return 'Recovered from stale in-flight state'
    default:
      return entry.from && entry.to
        ? `${entry.from} → ${entry.to}`
        : reason || ''
  }
}
