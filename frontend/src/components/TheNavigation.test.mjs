// Smoke test for TheNavigation.vue (The Operator — task 5: scroll-to-section nav).
// File-content assertions (no component runner). Run: node src/components/TheNavigation.test.mjs
//
// The header menu is the homepage section spine: clicking an item smooth-scrolls
// to that section on the homepage, or routes home + hash from any other page.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'TheNavigation.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

const SECTION_IDS = [
  'who-i-am', 'what-i-solve', 'receipts',
  'international-stages', 'selected-work', 'latest-writing', 'join-the-build',
]

ok('declares the 7 homepage section anchors', () => {
  for (const id of SECTION_IDS)
    assert.ok(src.includes(`'${id}'`) || src.includes(`"${id}"`), `missing section link: ${id}`)
})

ok('has a scroll-to-section handler', () => {
  assert.ok(/goToSection|scrollToSection/.test(src), 'missing goToSection handler')
  assert.ok(/scrollIntoView/.test(src), 'on-home click must use scrollIntoView')
})

ok('route-home-then-scroll fallback (off-home)', () => {
  assert.ok(/router\.push/.test(src), 'must router.push when off homepage')
  assert.ok(/hash/.test(src), 'must pass a hash to the home route')
})

ok('respects reduced motion for scroll', () =>
  assert.ok(/prefers-reduced-motion|reducedMotion/.test(src), 'scroll must honor reduced motion'))

ok('no placeholder / TODO markers', () =>
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum/i.test(src), 'no placeholder markers'))

console.log(`\n${passed} checks passed.`)
