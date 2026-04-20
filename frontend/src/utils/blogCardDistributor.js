// Deterministic card-variant picker for the blog social-feed layout.
// Pure function — no Vue, no DOM. Used by BlogFeedDistributor.vue and
// verified by blogCardDistributor.test.mjs.
//
// Variants: hero | wide | tall | small | quote
// Layout contract (from 2026-04-19 plan):
//   index 0                       → hero  (featured, full-width cinematic)
//   index 1                       → wide  (editorial 2-col)
//   index % 7 === 2 && ai_summary → quote (pull-quote card)
//   index % 5 === 3               → tall  (9:16 portrait)
//   else                          → small (default 3-col tile)
//
// Precedence note: quote and tall collide at indices 23, 58, 93…
// (LCM(5,7)×k − 12). At those indices quote wins when `ai_summary` exists,
// else falls through to tall. Covered by the index-23 test cases.

export function getCardVariant(index, post) {
  if (!Number.isInteger(index) || index < 0) return 'small'
  if (index === 0) return 'hero'
  if (index === 1) return 'wide'
  if (index % 7 === 2 && post && typeof post.ai_summary === 'string' && post.ai_summary.trim().length > 0) {
    return 'quote'
  }
  if (index % 5 === 3) return 'tall'
  return 'small'
}
