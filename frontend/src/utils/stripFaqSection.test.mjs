// Smoke test for stripFaqSection helper.
// Run: node frontend/src/utils/stripFaqSection.test.mjs
//
// Pure-regex implementation — no DOMParser, no jsdom, no deps.

import { stripFaqSection } from './stripFaqSection.js'

function assert(cond, label) {
  if (!cond) {
    console.error(`  FAIL: ${label}`)
    process.exitCode = 1
  } else {
    console.log(`  pass: ${label}`)
  }
}

console.log('stripFaqSection smoke test:')

// 1. Empty / non-string input returns unchanged
assert(stripFaqSection('') === '', 'empty string returned unchanged')
assert(stripFaqSection(null) === null, 'null returned unchanged')
assert(stripFaqSection(undefined) === undefined, 'undefined returned unchanged')

// 2. Non-FAQ HTML returned unchanged
const plain = '<h2>Introduction</h2><p>Body</p><h2>Conclusion</h2><p>Ending</p>'
assert(stripFaqSection(plain) === plain, 'non-FAQ HTML returned unchanged')

// 3. FAQ at end of article — strips h2 + siblings to end
const endFaq =
  '<h2>Main Section</h2>' +
  '<p>Body paragraph</p>' +
  '<h2>FAQ: Pertanyaan yang Sering Ditanya</h2>' +
  '<h3>Apakah X bisa Y?</h3>' +
  '<p>Ya, X bisa Y.</p>' +
  '<h3>Bagaimana cara Z?</h3>' +
  '<p>Dengan melakukan A, B, C.</p>'
const endStripped = stripFaqSection(endFaq)
assert(
  endStripped === '<h2>Main Section</h2><p>Body paragraph</p>',
  'end-of-article FAQ stripped to end'
)

// 4. FAQ mid-article — strips h2 + siblings until next h2
const midFaq =
  '<h2>Main Section</h2>' +
  '<p>Body 1</p>' +
  '<h2>FAQ</h2>' +
  '<h3>Q1?</h3>' +
  '<p>A1</p>' +
  '<h3>Q2?</h3>' +
  '<p>A2</p>' +
  '<h2>Conclusion</h2>' +
  '<p>Ending</p>'
const midStripped = stripFaqSection(midFaq)
assert(
  midStripped ===
    '<h2>Main Section</h2><p>Body 1</p><h2>Conclusion</h2><p>Ending</p>',
  'mid-article FAQ stripped, next section preserved'
)

// 5. "Frequently Asked" English heading variant
const enFaq =
  '<h2>Overview</h2><p>Intro</p>' +
  '<h2>Frequently Asked Questions</h2>' +
  '<h3>Q?</h3><p>A</p>'
assert(
  stripFaqSection(enFaq) === '<h2>Overview</h2><p>Intro</p>',
  'English "Frequently Asked" heading recognized'
)

// 6. FAQ h2 with attributes (id, class) still matched
const attrFaq =
  '<h2>Body</h2><p>text</p>' +
  '<h2 id="faq-section" class="mt-8">FAQ</h2>' +
  '<h3>Q?</h3><p>A</p>'
assert(
  stripFaqSection(attrFaq) === '<h2>Body</h2><p>text</p>',
  'FAQ h2 with id + class attributes stripped'
)

// 7. Case-insensitive matching ("faq" lowercase inside heading)
const lcFaq = '<h2>Body</h2><p>x</p><h2>faq section</h2><p>answer</p>'
assert(
  stripFaqSection(lcFaq) === '<h2>Body</h2><p>x</p>',
  'lowercase "faq" heading matched'
)

if (process.exitCode === 1) {
  console.log('\nFAILED')
} else {
  console.log('\nall assertions passed')
}
