/**
 * Pure presentation helpers for the IG-repurpose admin panel. Framework-free so
 * they can be unit-tested with `node --test` (see repurposeHelpers.test.mjs) and
 * shared by the composable + List/Detail views.
 *
 * FSM mirror of App\Enums\RepurposeJobStatus (12 states).
 */

export const TERMINAL_STATUSES = ['drafted', 'failed']

export const STATUS_LABEL = {
  received: 'Awaiting mode',
  capturing: 'Capturing slides',
  captured: 'Slides captured',
  extracting: 'Reading slides',
  extracted: 'Text extracted',
  researching: 'Fact-checking',
  researched: 'Facts verified',
  rewriting: 'Rewriting',
  rewritten: 'Rewritten',
  finalizing: 'Finalizing draft',
  drafted: 'Draft ready',
  failed: 'Failed',
}

// Light/dark chip tone per status group, matching the Content Engine status
// pills (bg-{c}-100 text-{c}-800 dark:…). Unknown → neutral (never empty).
const TONE = {
  drafted: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
  failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  received: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
}
const INPROGRESS_TONE = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'

export function isTerminal(status) {
  return TERMINAL_STATUSES.includes(status)
}

export function statusTone(status) {
  if (TONE[status]) return TONE[status]
  if (status && status !== 'drafted' && status !== 'failed' && status !== 'received') {
    return INPROGRESS_TONE
  }
  return TONE.received
}

export function statusLabel(status) {
  return STATUS_LABEL[status] || status || 'Unknown'
}

/**
 * The step a job failed at = the `from` of the last pipeline_state_log entry
 * whose `to` was 'failed'. Returns null when the job never failed.
 */
export function inferFailedStep(log) {
  if (!Array.isArray(log)) return null
  for (let i = log.length - 1; i >= 0; i--) {
    if (log[i] && log[i].to === 'failed') {
      return log[i].from || null
    }
  }
  return null
}

/**
 * Which view the right ("Generated") pane of the 2-pane detail should render:
 *   'generated'   — carousel job with a linked LinkedInPost draft → show the
 *                   rendered slides + caption + publish actions.
 *   'in_progress' — carousel job still processing (no draft yet) → show the
 *                   pipeline timeline / current step.
 *   'blog'        — blog-mode job → produces a ContentIdea (not a draft); show
 *                   the rewrite preview + a Content Engine handoff link.
 */
export function rightPaneMode(job) {
  if (job?.mode === 'blog') return 'blog'
  if (job?.mode === 'carousel' && job?.linkedin_post_id) return 'generated'
  return 'in_progress'
}

export function modeLabel(mode) {
  if (mode === 'blog') return 'Blog + Carousel'
  if (mode === 'carousel') return 'Carousel'
  return '—'
}

export function relativeTime(iso) {
  if (!iso) return ''
  const then = new Date(iso).getTime()
  if (Number.isNaN(then)) return ''
  const diff = Math.max(0, Date.now() - then)
  const s = Math.floor(diff / 1000)
  if (s < 60) return `${s}s ago`
  const m = Math.floor(s / 60)
  if (m < 60) return `${m}m ago`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h ago`
  return `${Math.floor(h / 24)}d ago`
}
