// Smoke test for InternationalStages.vue (The Operator §5). File-content checks.
// Run: node src/components/home/InternationalStages.test.mjs
//
// §5 = international reach as a 6-card row. Content is hand-curated identity fact
// (operator corrected the list to include NextDev/Telkomsel, which funded the
// Google Startup Grind Silicon Valley trip). Facts sourced from the WHY-vault
// awards.md — must stay accurate.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'InternationalStages.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('all six stages present', () => {
  for (const s of ['Hangzhou', 'Silicon Valley', 'Fenox', 'IDBYTE', 'Bengaluru', 'NextDev'])
    assert.ok(src.includes(s), `missing stage: ${s}`)
})

ok('Alibaba / UN-UNCTAD framing (1 of 48 Asia)', () => {
  assert.ok(/UNCTAD/i.test(src), 'must reference UN-UNCTAD')
  assert.ok(/48/.test(src), 'must reference 1 of 48 Asian entrepreneurs')
})

ok('NextDev funded the Silicon Valley trip (relationship surfaced)', () =>
  assert.ok(/funded/i.test(src), 'must surface NextDev → funded SV relationship'))

ok('exactly 6 stage entries', () => {
  const m = src.match(/id:\s*['"]stage-/g) || []
  assert.equal(m.length, 6, `expected 6 stages, found ${m.length}`)
})

ok('no placeholder / TODO markers', () =>
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum/i.test(src), 'no placeholder markers'))

// Task 4: each curated stage maps to a real award gallery and shows a photo.
ok('all 6 stages carry an awardId', () => {
  const m = src.match(/awardId:\s*\d+/g) || []
  assert.equal(m.length, 6, `expected 6 awardId mappings, found ${m.length}`)
})

ok('consumes useStageGalleries + BaseGalleryModal', () => {
  assert.ok(/useStageGalleries/.test(src), 'must use useStageGalleries composable')
  assert.ok(/BaseGalleryModal/.test(src), 'must wire BaseGalleryModal for the photo grid')
})

ok('renders a cover photo with a v-if guard (text-only fallback)', () => {
  assert.ok(/<img/.test(src), 'must render a cover <img>')
  assert.ok(/cover/i.test(src), 'cover must come from the resolved gallery cover')
  assert.ok(/v-if=/.test(src), 'cover must be guarded so missing photos fall back to text-only')
})

console.log(`\n${passed} checks passed.`)
