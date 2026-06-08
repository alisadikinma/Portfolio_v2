// Smoke test for useStageGalleries.js (The Operator — task 4).
// Fetches award galleries so the International Stages cards show real event photos.
// Run: node src/composables/useStageGalleries.test.mjs
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'useStageGalleries.js'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('exports useStageGalleries', () =>
  assert.ok(/export\s+function\s+useStageGalleries/.test(src), 'must export useStageGalleries'))

ok('hits the awards galleries endpoint', () =>
  assert.ok(/\/awards\//.test(src) && /galleries/.test(src), 'must call /awards/{id}/galleries'))

ok('fetches in parallel', () =>
  assert.ok(/Promise\.all/.test(src), 'must Promise.all the award fetches'))

ok('maps first gallery first item → cover', () => {
  assert.ok(/galleries\[0\]/.test(src), 'must read galleries[0]')
  assert.ok(/items\[0\]/.test(src), 'must read items[0] as cover')
})

ok('graceful per-award failure (try/catch → null)', () =>
  assert.ok(/try/.test(src) && /catch/.test(src), 'must isolate per-award failures'))

ok('no placeholder / TODO markers', () =>
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum/i.test(src), 'no placeholder markers'))

console.log(`\n${passed} checks passed.`)
