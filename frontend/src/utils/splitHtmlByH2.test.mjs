// Smoke test for splitHtmlByH2 helper.
// Run: node frontend/src/utils/splitHtmlByH2.test.mjs
//
// Pure regex implementation — no DOMParser, Node-safe.

import { splitHtmlByH2 } from './splitHtmlByH2.js'

function assert(cond, label) {
  if (!cond) {
    console.error(`  FAIL: ${label}`)
    process.exitCode = 1
  } else {
    console.log(`  pass: ${label}`)
  }
}

console.log('splitHtmlByH2 smoke test:')

// 1. Empty / null / undefined → []
assert(Array.isArray(splitHtmlByH2('')) && splitHtmlByH2('').length === 0, 'empty string → []')
assert(Array.isArray(splitHtmlByH2(null)) && splitHtmlByH2(null).length === 0, 'null → []')
assert(Array.isArray(splitHtmlByH2(undefined)) && splitHtmlByH2(undefined).length === 0, 'undefined → []')

// 2. HTML with no H2 → single chunk
const plain = '<p>No headings here.</p>'
let r = splitHtmlByH2(plain)
assert(r.length === 1 && r[0].type === 'chunk' && r[0].html === plain, 'no-H2 HTML → single chunk')

// 3. One H2 in the middle → chunk-before + h2 + chunk-after
const oneH2 = '<p>Intro.</p><h2>First Section</h2><p>Body of first.</p>'
r = splitHtmlByH2(oneH2)
assert(r.length === 3, 'one H2 → 3 elements')
assert(r[0].type === 'chunk' && r[0].html === '<p>Intro.</p>', 'one H2: chunk-before')
assert(r[1].type === 'h2' && r[1].text === 'First Section', 'one H2: h2 text extracted')
assert(r[1].id === 'first-section', 'one H2: id slug-cased from text')
assert(r[2].type === 'chunk' && r[2].html === '<p>Body of first.</p>', 'one H2: chunk-after')

// 4. Three H2s → 7 elements (chunk + h2 + chunk + h2 + chunk + h2 + chunk)
const threeH2 = '<p>A</p><h2>One</h2><p>B</p><h2>Two</h2><p>C</p><h2>Three</h2><p>D</p>'
r = splitHtmlByH2(threeH2)
assert(r.length === 7, 'three H2s → 7 elements')
assert(r[1].id === 'one' && r[3].id === 'two' && r[5].id === 'three', 'three H2s: ids')

// 5. Leading H2 → empty chunk-before omitted (no empty leading chunk noise)
const leadingH2 = '<h2>Lead</h2><p>Body.</p>'
r = splitHtmlByH2(leadingH2)
assert(r.length === 2, 'leading H2 → 2 elements (no empty chunk prefix)')
assert(r[0].type === 'h2' && r[1].type === 'chunk', 'leading H2: first is h2, second is chunk')

// 6. Trailing H2 → empty chunk-after omitted
const trailingH2 = '<p>Body.</p><h2>Trail</h2>'
r = splitHtmlByH2(trailingH2)
assert(r.length === 2, 'trailing H2 → 2 elements (no empty chunk suffix)')
assert(r[0].type === 'chunk' && r[1].type === 'h2', 'trailing H2: chunk then h2')

// 7. H2 with attributes (id, class) — text still extracted cleanly
const attrH2 = '<p>x</p><h2 id="custom" class="foo">Styled Heading</h2><p>y</p>'
r = splitHtmlByH2(attrH2)
assert(r[1].type === 'h2' && r[1].text === 'Styled Heading', 'H2 with attrs: text extracted')

// 8. H2 with inline markup (strong, em) — text stripped of tags
const markupH2 = '<h2>Tools <strong>We</strong> Use</h2><p>z</p>'
r = splitHtmlByH2(markupH2)
assert(r[0].type === 'h2' && r[0].text === 'Tools We Use', 'H2 with inline markup: text stripped')
assert(r[0].id === 'tools-we-use', 'H2 with inline markup: id from stripped text')

// 9. Duplicate H2 titles — ids get numeric suffix for uniqueness (-2, -3…)
const dupeH2 = '<h2>Same</h2><p>a</p><h2>Same</h2><p>b</p><h2>Same</h2>'
r = splitHtmlByH2(dupeH2)
const ids = r.filter(x => x.type === 'h2').map(x => x.id)
assert(ids[0] === 'same' && ids[1] === 'same-2' && ids[2] === 'same-3', 'duplicate H2s get -2, -3 suffix')

// 10. Unicode / symbols in H2 text — slug strips non-alphanum
const unicodeH2 = '<h2>AI &amp; ML: A Primer (2026)</h2>'
r = splitHtmlByH2(unicodeH2)
assert(r[0].type === 'h2' && r[0].id.match(/^[a-z0-9-]+$/), 'unicode H2: slug is alphanum+dashes only')

console.log('done')
