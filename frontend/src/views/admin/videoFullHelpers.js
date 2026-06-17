// Pure helpers for the video_full admin UI (mirrors repurposeHelpers.js).

// Platforms a single 60s reel (video_full) can publish to via Zernio.
// YouTube is video_full-ONLY (it gets the clip as a Short); reddit/facebook
// added 2026-06-16. The publish endpoint gates each on an account being
// configured, so listing one here is safe even before it's wired in settings.
export const VIDEO_FULL_PLATFORMS = ['linkedin', 'instagram', 'tiktok', 'threads', 'reddit', 'facebook', 'youtube']

const TERMINAL = ['drafted', 'failed']

/** Whether a job is in a terminal state (stops the detail/list poll). */
export function isTerminal(status) {
  return TERMINAL.includes(status)
}

/** Operator-facing Indonesian label for a video_full job status. */
export function statusLabel(status) {
  return {
    queued_local: 'Menunggu worker',
    claimed_local: 'Worker mengambil',
    processing_local: 'Memproses',
    uploaded: 'Siap direview',
    drafted: 'Selesai',
    failed: 'Gagal',
  }[status] || status
}

/** Tailwind dot class for a per-segment status. */
export function segmentDot(status) {
  return {
    done: 'bg-emerald-500',
    processing: 'bg-cyan-500 animate-pulse',
    pending: 'bg-slate-400',
    failed: 'bg-red-500',
    dropped: 'bg-slate-600',
  }[status] || 'bg-slate-400'
}

/** Worker considered online when the last heartbeat is < 3 min old. */
export function workerOnline(heartbeatIso, now = Date.now()) {
  if (!heartbeatIso) return false
  const ts = Date.parse(heartbeatIso)
  return Number.isFinite(ts) && now - ts < 3 * 60 * 1000
}
