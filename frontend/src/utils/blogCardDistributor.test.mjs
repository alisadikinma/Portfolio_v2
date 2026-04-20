// Smoke test for blogCardDistributor helper.
// Run: node frontend/src/utils/blogCardDistributor.test.mjs
//
// Pure logic — no Vue, no DOM. Safe to run in Node.

import { getCardVariant } from './blogCardDistributor.js'

function assert(cond, label) {
  if (!cond) {
    console.error(`  FAIL: ${label}`)
    process.exitCode = 1
  } else {
    console.log(`  pass: ${label}`)
  }
}

const plainPost = { id: 1, title: 'Sample', excerpt: 'Hello', ai_summary: null }
const quotablePost = { id: 2, title: 'Quotable', excerpt: 'Hi', ai_summary: 'A pithy quote worth pulling out of the piece.' }

console.log('blogCardDistributor smoke test:')

// Hero first, wide second — deterministic
assert(getCardVariant(0, plainPost) === 'hero', 'index 0 → hero')
assert(getCardVariant(1, plainPost) === 'wide', 'index 1 → wide')

// Tall hits at index % 5 === 3 (indices 3, 8, 13...)
assert(getCardVariant(3, plainPost) === 'tall', 'index 3 → tall')
assert(getCardVariant(8, plainPost) === 'tall', 'index 8 → tall')

// Small is the default
assert(getCardVariant(4, plainPost) === 'small', 'index 4 → small')
assert(getCardVariant(5, plainPost) === 'small', 'index 5 → small')
assert(getCardVariant(6, plainPost) === 'small', 'index 6 → small')
assert(getCardVariant(7, plainPost) === 'small', 'index 7 → small')

// Quote only when index % 7 === 2 AND ai_summary exists
// index 9: 9 % 7 === 2 → quote if ai_summary present
assert(getCardVariant(9, quotablePost) === 'quote', 'index 9 + ai_summary → quote')
assert(getCardVariant(9, plainPost) === 'small', 'index 9 without ai_summary → small (fallback)')

// Quote at index 16: 16 % 7 === 2 (no tall collision since 16 % 5 !== 3)
assert(getCardVariant(16, quotablePost) === 'quote', 'index 16 + ai_summary → quote')

// Genuine quote-vs-tall collision at index 23: 23 % 7 === 2 AND 23 % 5 === 3
// Quote is listed first in the if-chain → quote wins when ai_summary present.
assert(getCardVariant(23, quotablePost) === 'quote', 'index 23 with ai_summary → quote beats tall')
assert(getCardVariant(23, plainPost) === 'tall', 'index 23 without ai_summary → falls through to tall')

// Null / undefined post is safe — falls back to small (except hero/wide which ignore post shape)
assert(getCardVariant(4, null) === 'small', 'index 4 null post → small')
assert(getCardVariant(9, null) === 'small', 'index 9 null post (no ai_summary) → small')

// Negative / non-integer indices are treated as small (defensive)
assert(getCardVariant(-1, plainPost) === 'small', 'negative index → small')
assert(getCardVariant(2.5, plainPost) === 'small', 'non-integer index → small')

console.log('done')
