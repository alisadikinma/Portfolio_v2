/**
 * Every schedule time Ali sees or types is WIB (Asia/Jakarta, UTC+7, no DST),
 * whatever zone the browser is in. The backend reads a naive string as WIB and
 * stores UTC; these helpers keep the browser from reinterpreting values.
 */
export const WIB_ZONE = 'Asia/Jakarta'
export const WIB_OFFSET = '+07:00'
const WIB_OFFSET_MS = 7 * 60 * 60 * 1000

const NAIVE_LOCAL = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/
const HAS_ZONE = /(Z|[+-]\d{2}:?\d{2})$/i

/**
 * datetime-local value ("YYYY-MM-DDTHH:mm") read as WIB -> ISO with +07:00.
 * Values that already carry an offset/Z pass through. Empty/invalid -> ''.
 */
export function fromWibInput(local) {
  if (!local || typeof local !== 'string') return ''
  const v = local.trim()
  if (HAS_ZONE.test(v)) return Number.isNaN(Date.parse(v)) ? '' : v
  if (!NAIVE_LOCAL.test(v)) return ''
  const withSeconds = v.length === 16 ? `${v}:00` : v
  const iso = `${withSeconds}${WIB_OFFSET}`
  return Number.isNaN(Date.parse(iso)) ? '' : iso
}

/** ISO instant (any zone) -> WIB wall time "YYYY-MM-DDTHH:mm" for a datetime-local input. */
export function toWibInput(iso) {
  if (!iso) return ''
  const ms = new Date(iso).getTime()
  if (Number.isNaN(ms)) return ''
  return new Date(ms + WIB_OFFSET_MS).toISOString().slice(0, 16)
}

/** Epoch ms of a datetime-local value read as WIB; NaN when invalid. */
export function wibInputToMs(local) {
  const iso = fromWibInput(local)
  return iso ? new Date(iso).getTime() : NaN
}

/** True when the WIB datetime-local value is strictly in the future. */
export function isFutureWibInput(local, now = Date.now()) {
  const ms = wibInputToMs(local)
  return !Number.isNaN(ms) && ms > now
}

/** "Tomorrow 09:00 WIB" seed for a picker, as a datetime-local value. */
export function wibTomorrowAt(hour = 9, now = Date.now()) {
  const wibDay = new Date(now + WIB_OFFSET_MS + 24 * 60 * 60 * 1000).toISOString().slice(0, 10)
  return `${wibDay}T${String(hour).padStart(2, '0')}:00`
}

/**
 * Display: "13 May 2026, 12:00 WIB". Always Asia/Jakarta; '—' when empty/invalid.
 * Pass `withYear: false` for the compact form.
 */
export function formatWib(value, { withYear = true } = {}) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  const text = new Intl.DateTimeFormat('en-GB', {
    timeZone: WIB_ZONE,
    day: 'numeric',
    month: 'short',
    ...(withYear ? { year: 'numeric' } : {}),
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(d)
  return `${text} WIB`
}
