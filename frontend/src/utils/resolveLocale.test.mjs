// Smoke test for resolveLocale helper.
// Run: node frontend/src/utils/resolveLocale.test.mjs
//
// Pure localStorage + Intl stubs. No DOM, no Vue.

import {
  resolveLocale,
  persistDetectedLocale,
  setManualLocale,
  isManualLocale,
  getSavedLocale,
} from './resolveLocale.js'

function assert(cond, label) {
  if (!cond) {
    console.error(`  FAIL: ${label}`)
    process.exitCode = 1
  } else {
    console.log(`  pass: ${label}`)
  }
}

function mockLocalStorage() {
  const store = new Map()
  globalThis.localStorage = {
    getItem: (k) => (store.has(k) ? store.get(k) : null),
    setItem: (k, v) => store.set(k, String(v)),
    removeItem: (k) => store.delete(k),
    clear: () => store.clear(),
  }
  return store
}

function mockTimezone(tz) {
  globalThis.Intl = {
    DateTimeFormat: function () {
      return { resolvedOptions: () => ({ timeZone: tz }) }
    },
  }
}

console.log('resolveLocale smoke test:')

// Setup
mockLocalStorage()

// 1. First visit from Jakarta → 'id'
mockTimezone('Asia/Jakarta')
assert(resolveLocale() === 'id', 'first visit from Asia/Jakarta → id')
persistDetectedLocale()
assert(getSavedLocale() === 'id', 'persistDetectedLocale writes id to storage')

// 2. Return visit — saved=id, no manual flag → still id
assert(resolveLocale() === 'id', 'return visit with saved id (no manual) → id')
assert(isManualLocale() === false, 'isManualLocale false when not user-set')

// 3. First visit from US (America/New_York) → 'en'
localStorage.clear()
mockTimezone('America/New_York')
assert(resolveLocale() === 'en', 'first visit from America/New_York → en')

// 4. First visit from Singapore → 'en' (not Indonesian TZ, even though close)
localStorage.clear()
mockTimezone('Asia/Singapore')
assert(resolveLocale() === 'en', 'first visit from Asia/Singapore → en')

// 5. Indonesian user manually switches to EN, returns → EN wins
localStorage.clear()
mockTimezone('Asia/Jakarta')
setManualLocale('en')
assert(resolveLocale() === 'en', 'manual EN override wins in Indonesia')
assert(isManualLocale() === true, 'isManualLocale true after setManualLocale')
assert(getSavedLocale() === 'en', 'saved locale is en after manual set')

// 6. Second-timezone Indonesia (WITA) → 'id'
localStorage.clear()
mockTimezone('Asia/Makassar')
assert(resolveLocale() === 'id', 'Asia/Makassar (WITA) → id')

// 7. Third-timezone Indonesia (WIT) → 'id'
localStorage.clear()
mockTimezone('Asia/Jayapura')
assert(resolveLocale() === 'id', 'Asia/Jayapura (WIT) → id')

// 8. Non-Indonesia user with saved id (auto-detected earlier, but moved) →
//    saved wins (no manual flag), no new detection until they clear storage
localStorage.clear()
mockTimezone('Asia/Jakarta')
persistDetectedLocale() // saves id
mockTimezone('America/New_York') // user's laptop TZ changed (moved)
assert(resolveLocale() === 'id', 'saved locale persists across TZ changes (no manual flag)')

// 9. Idempotent: persistDetectedLocale with existing saved is a no-op
localStorage.clear()
localStorage.setItem('locale', 'id')
persistDetectedLocale()
assert(getSavedLocale() === 'id', 'persistDetectedLocale leaves existing preference alone')

// 10. Garbage input resistance
localStorage.clear()
localStorage.setItem('locale', 'fr') // not supported
mockTimezone('Asia/Jakarta')
assert(resolveLocale() === 'id', 'unsupported saved locale falls through to detection')

console.log('done')
