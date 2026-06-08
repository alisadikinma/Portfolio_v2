// Smoke test for ReceiptsBento.vue (The Operator §4). File-content assertions.
// Run: node src/components/home/ReceiptsBento.test.mjs
//
// §4 = 6-tile proof bento. Lead tile (gold) = #1 Global AI Demo Day 2026.
// Live tiles (56+ products, 17 years) pull from /api/homepage/featured stats;
// the rest are locked static metrics. Keyence framing = "better & cheaper",
// NEVER "replaced Keyence" (operator correction).
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'ReceiptsBento.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('consumes useHomepageFeatured (real stats)', () =>
  assert.ok(src.includes('useHomepageFeatured'), 'must use useHomepageFeatured'))

ok('lead tile = Global AI Demo Day 2026', () =>
  assert.ok(/Global AI Demo Day 2026/.test(src), 'must feature the Demo Day #1 lead tile'))

ok('documented impact tile $318K+', () =>
  assert.ok(/\$318K\+/.test(src), 'must include $318K+ documented impact'))

ok('accuracy tile framed cheaper/better than Keyence (not "replaced")', () => {
  assert.ok(/95%/.test(src), 'must include ≥95% accuracy tile')
  assert.ok(/Keyence/i.test(src), 'must reference Keyence-class AOI for context')
  assert.ok(!/replaced\s+keyence/i.test(src), 'must NOT say "replaced Keyence"')
})

ok('renders exactly 6 tiles', () => {
  const m = src.match(/id:\s*['"]tile-/g) || []
  assert.equal(m.length, 6, `expected 6 tiles, found ${m.length}`)
})

ok('no placeholder / TODO markers', () =>
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum/i.test(src), 'no placeholder markers'))

console.log(`\n${passed} checks passed.`)
