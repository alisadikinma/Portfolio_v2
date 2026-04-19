// Smoke test. Run: node frontend/src/utils/extractFaqFromHtml.test.mjs

import { extractFaqFromHtml } from './extractFaqFromHtml.js'

function assert(cond, label) {
  if (!cond) {
    console.error(`  FAIL: ${label}`)
    process.exitCode = 1
  } else {
    console.log(`  pass: ${label}`)
  }
}

console.log('extractFaqFromHtml smoke test:')

// 1. Empty / non-string → []
assert(Array.isArray(extractFaqFromHtml('')) && extractFaqFromHtml('').length === 0, 'empty returns []')
assert(extractFaqFromHtml(null).length === 0, 'null returns []')

// 2. HTML without FAQ heading → []
const noFaq = '<h2>Intro</h2><p>Body</p>'
assert(extractFaqFromHtml(noFaq).length === 0, 'no FAQ heading returns []')

// 3. Pattern B (bold <p><strong>Q</strong></p> + <p>A</p>) — the older
// pipeline's output, matches the real production post that regressed.
const boldStyle =
  '<h2>Main</h2><p>body</p>' +
  '<h2>FAQ: Pertanyaan yang Sering Ditanya</h2>' +
  '<p><strong>Apakah Gemini 3 bisa diakses secara gratis?</strong></p>' +
  '<p>Ya. Gemini 3 tersedia dengan akses gratis terbatas di ai.google.dev.</p>' +
  '<p><strong>Apa perbedaan antara 3 dan 3.1 Pro?</strong></p>' +
  '<p>Gemini 3 adalah model base. 3.1 Pro menambahkan real-time grounding.</p>'
const boldItems = extractFaqFromHtml(boldStyle)
assert(boldItems.length === 2, 'bold-style: 2 items extracted')
assert(
  boldItems[0].question === 'Apakah Gemini 3 bisa diakses secara gratis?',
  'bold-style: Q1 text correct'
)
assert(
  boldItems[0].answer.includes('Ya. Gemini 3 tersedia'),
  'bold-style: A1 text correct'
)
assert(
  boldItems[1].question === 'Apa perbedaan antara 3 dan 3.1 Pro?',
  'bold-style: Q2 text correct'
)

// 4. Pattern A (h3 + p) — newer plugin output
const headingStyle =
  '<h2>FAQ</h2>' +
  '<h3>What is X?</h3>' +
  '<p>X is <em>a thing</em>.</p>' +
  '<h3>How do I Y?</h3>' +
  '<p>By Z.</p>'
const headingItems = extractFaqFromHtml(headingStyle)
assert(headingItems.length === 2, 'h3-style: 2 items extracted')
assert(headingItems[0].question === 'What is X?', 'h3-style: Q1 correct')
assert(
  headingItems[0].answer.includes('<em>a thing</em>'),
  'h3-style: A1 preserves inline HTML'
)

// 5. FAQ mid-article — stops at next h2
const midFaq =
  '<h2>FAQ</h2>' +
  '<p><strong>Q1?</strong></p><p>A1</p>' +
  '<p><strong>Q2?</strong></p><p>A2</p>' +
  '<h2>Conclusion</h2>' +
  '<p><strong>Not an FAQ answer</strong></p>' +
  '<p>This should not be picked up</p>'
const midItems = extractFaqFromHtml(midFaq)
assert(midItems.length === 2, 'mid-article FAQ: stops at next h2 (2 items only)')

// 6. HR or other non-p/h3/h4 block between Q and A is OK
const withHr =
  '<h2>FAQ</h2>' +
  '<p><strong>Q?</strong></p>' +
  '<hr>' +
  '<p>Answer after hr</p>'
const hrItems = extractFaqFromHtml(withHr)
assert(
  hrItems.length === 1 && hrItems[0].answer.includes('Answer after hr'),
  'non-p/h3/h4 elements skipped between Q and A'
)

// 7. Question without answer — don't emit incomplete entry
const orphan = '<h2>FAQ</h2><p><strong>Q1?</strong></p><h2>Next Section</h2>'
assert(
  extractFaqFromHtml(orphan).length === 0,
  'orphan question without following <p> not emitted'
)

if (process.exitCode === 1) {
  console.log('\nFAILED')
} else {
  console.log('\nall assertions passed')
}
