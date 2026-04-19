// Smoke test for newsletter localStorage state helpers.
// Run: node frontend/src/utils/newsletterState.test.mjs
//
// Pure — no Vue, no axios, no framework. localStorage is mocked at module scope.

// ── Mock localStorage + window before importing ──
const store = new Map()
globalThis.localStorage = {
  getItem: (key) => (store.has(key) ? store.get(key) : null),
  setItem: (key, value) => { store.set(key, String(value)) },
  removeItem: (key) => { store.delete(key) },
  clear: () => { store.clear() },
}
globalThis.window = globalThis

const {
  isDismissed,
  isSubscribed,
  markDismissed,
  markSubscribed,
  clearNewsletterState,
  DISMISS_TTL_DAYS,
} = await import('./newsletterState.js')

function assert(cond, label) {
  if (!cond) {
    console.error(`  FAIL: ${label}`)
    process.exitCode = 1
  } else {
    console.log(`  pass: ${label}`)
  }
}

console.log('newsletterState smoke test:')

// 1. Exports present
assert(typeof isDismissed === 'function', 'isDismissed is exported as a function')
assert(typeof isSubscribed === 'function', 'isSubscribed is exported as a function')
assert(typeof markDismissed === 'function', 'markDismissed is exported as a function')
assert(typeof markSubscribed === 'function', 'markSubscribed is exported as a function')
assert(typeof clearNewsletterState === 'function', 'clearNewsletterState is exported')
assert(DISMISS_TTL_DAYS === 7, 'DISMISS_TTL_DAYS constant equals 7')

// 2. Fresh state returns false for both flags
clearNewsletterState()
assert(isDismissed() === false, 'fresh state: isDismissed() returns false')
assert(isSubscribed() === false, 'fresh state: isSubscribed() returns false')

// 3. markDismissed() sets dismissal, isDismissed() returns true
clearNewsletterState()
markDismissed()
assert(isDismissed() === true, 'after markDismissed(): isDismissed() returns true')

// 4. Expired dismissal (8 days old) returns false
clearNewsletterState()
const eightDaysAgoMs = Date.now() - (8 * 24 * 60 * 60 * 1000)
globalThis.localStorage.setItem('nl_dismissed_at', String(eightDaysAgoMs))
assert(isDismissed() === false, 'expired dismissal (8 days old): isDismissed() returns false')

// 5. Fresh dismissal (1 day old) still active
clearNewsletterState()
const oneDayAgoMs = Date.now() - (1 * 24 * 60 * 60 * 1000)
globalThis.localStorage.setItem('nl_dismissed_at', String(oneDayAgoMs))
assert(isDismissed() === true, 'fresh dismissal (1 day old): isDismissed() returns true')

// 6. markSubscribed(email) persists state
clearNewsletterState()
markSubscribed('hello@example.com')
assert(isSubscribed() === true, 'after markSubscribed(email): isSubscribed() returns true')
assert(
  globalThis.localStorage.getItem('nl_subscribed_email') === 'hello@example.com',
  'markSubscribed stores the email (lowercased)',
)

// 7. markSubscribed trims + lowercases the email
clearNewsletterState()
markSubscribed('  MixedCase@Example.COM  ')
assert(
  globalThis.localStorage.getItem('nl_subscribed_email') === 'mixedcase@example.com',
  'markSubscribed trims whitespace + lowercases',
)

// 8. Empty string to markSubscribed is a no-op
clearNewsletterState()
markSubscribed('')
assert(isSubscribed() === false, 'markSubscribed("") does not mark as subscribed')

// 9. Null / undefined to markSubscribed is a no-op
clearNewsletterState()
markSubscribed(null)
markSubscribed(undefined)
assert(isSubscribed() === false, 'markSubscribed(null|undefined) does not mark as subscribed')

// 10. Malformed timestamp does not crash, returns false
clearNewsletterState()
globalThis.localStorage.setItem('nl_dismissed_at', 'not-a-number')
assert(isDismissed() === false, 'malformed timestamp: isDismissed() returns false (no crash)')

// 11. clearNewsletterState() resets both flags
markDismissed()
markSubscribed('x@y.z')
assert(isDismissed() === true && isSubscribed() === true, 'before clear: both flags set')
clearNewsletterState()
assert(isDismissed() === false && isSubscribed() === false, 'after clearNewsletterState(): both flags cleared')

console.log('Done.')
