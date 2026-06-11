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

// Tailwind chip tone per status group. Unknown → neutral slate (never empty).
const TONE = {
  drafted: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
  failed: 'bg-red-500/15 text-red-300 ring-red-500/30',
  received: 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
}
const INPROGRESS_TONE = 'bg-cyan-500/15 text-cyan-300 ring-cyan-500/30'

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

export function modeLabel(mode) {
  if (mode === 'blog') return '📝 Blog + Carousel'
  if (mode === 'carousel') return '🎠 Carousel'
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
