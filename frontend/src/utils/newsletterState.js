// Pure localStorage state helpers for the newsletter funnel.
// No Vue, no axios, no framework — so this module can be unit-tested
// with node directly (see newsletterState.test.mjs).
//
// Used by:
//   - useNewsletter.js (composable)
//   - NewsletterInlineCard.vue, NewsletterFooterBar.vue, NewsletterFloatingBanner.vue
//
// Contract:
//   - isDismissed() / isSubscribed() are safe on SSR (return false if no window)
//   - Any placement that calls markSubscribed(email) or markDismissed()
//     silences all 4 touchpoints for the visitor.

export const DISMISS_TTL_DAYS = 7
export const STORAGE_DISMISSED = 'nl_dismissed_at'
export const STORAGE_SUBSCRIBED = 'nl_subscribed_email'

const DAY_MS = 24 * 60 * 60 * 1000

function hasStorage() {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
}

export function isDismissed() {
  if (!hasStorage()) return false
  const raw = window.localStorage.getItem(STORAGE_DISMISSED)
  if (!raw) return false
  const ts = Number(raw)
  if (!Number.isFinite(ts)) return false
  const ageMs = Date.now() - ts
  return ageMs >= 0 && ageMs < DISMISS_TTL_DAYS * DAY_MS
}

export function isSubscribed() {
  if (!hasStorage()) return false
  const raw = window.localStorage.getItem(STORAGE_SUBSCRIBED)
  return typeof raw === 'string' && raw.length > 0
}

export function markDismissed() {
  if (!hasStorage()) return
  window.localStorage.setItem(STORAGE_DISMISSED, String(Date.now()))
}

export function markSubscribed(email) {
  if (!hasStorage()) return
  if (typeof email !== 'string') return
  const normalized = email.trim().toLowerCase()
  if (normalized.length === 0) return
  window.localStorage.setItem(STORAGE_SUBSCRIBED, normalized)
}

export function clearNewsletterState() {
  if (!hasStorage()) return
  window.localStorage.removeItem(STORAGE_DISMISSED)
  window.localStorage.removeItem(STORAGE_SUBSCRIBED)
}
