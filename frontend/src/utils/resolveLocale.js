// Locale resolution with geo auto-detection.
//
// Rules:
//   1. If the visitor previously clicked the Language Switcher (we stamp
//      `locale_manual=true` when they do), their saved `locale` always
//      wins — even if their browser is in Indonesia. Respects user intent.
//   2. Otherwise, detect from the browser's resolved timezone. Indonesia
//      spans three zones (Asia/Jakarta WIB, Asia/Makassar WITA,
//      Asia/Jayapura WIT). Any match → 'id'.
//   3. Fallback → 'en'.
//
// Timezone detection is synchronous + free (Intl API, no GeoIP roundtrip).
// Used by i18n init + router root redirect so the first-paint locale
// matches the visitor's region without forcing them through a picker.

const INDONESIA_TIMEZONES = new Set([
  'Asia/Jakarta',
  'Asia/Pontianak', // also WIB
  'Asia/Makassar',
  'Asia/Jayapura',
])

export function isManualLocale() {
  if (typeof localStorage === 'undefined') return false
  return localStorage.getItem('locale_manual') === 'true'
}

export function getSavedLocale() {
  if (typeof localStorage === 'undefined') return null
  const raw = localStorage.getItem('locale')
  return raw === 'id' || raw === 'en' ? raw : null
}

function detectFromBrowser() {
  if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat !== 'function') {
    return 'en'
  }
  try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''
    if (INDONESIA_TIMEZONES.has(tz)) return 'id'
  } catch {
    // resolvedOptions can throw on very old browsers — fall through.
  }
  return 'en'
}

/**
 * Returns the locale the app should use right now.
 * Does NOT write to localStorage — pair with persistDetectedLocale()
 * when you want to cache the first-visit detection.
 */
export function resolveLocale() {
  if (isManualLocale()) {
    const saved = getSavedLocale()
    if (saved) return saved
  }
  const saved = getSavedLocale()
  if (saved) return saved
  return detectFromBrowser()
}

/**
 * Persist the auto-detected locale so subsequent visits skip the
 * detection step. Called once on app boot. Never sets locale_manual —
 * that's reserved for explicit user clicks via the LanguageSwitcher.
 */
export function persistDetectedLocale() {
  if (typeof localStorage === 'undefined') return
  if (localStorage.getItem('locale')) return // already have a preference, leave alone
  const detected = detectFromBrowser()
  localStorage.setItem('locale', detected)
}

/**
 * Called by LanguageSwitcher when the user explicitly clicks a flag.
 * Stamps locale_manual=true so the auto-detection branch is skipped
 * on future visits, respecting the user's explicit choice.
 */
export function setManualLocale(lang) {
  if (typeof localStorage === 'undefined') return
  if (lang !== 'id' && lang !== 'en') return
  localStorage.setItem('locale', lang)
  localStorage.setItem('locale_manual', 'true')
}
