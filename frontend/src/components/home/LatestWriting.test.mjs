// Smoke test for LatestWriting.vue (The Operator §8). File-content assertions.
// Run: node src/components/home/LatestWriting.test.mjs
//
// §8 = editorial feed from LIVE /api/homepage/featured.latest_articles PLUS the
// "Content Engine meta-flex": this blog writes itself via Ali's own AI Content
// Engine (real system in this same codebase) — a building-in-public proof point.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'LatestWriting.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('consumes useHomepageFeatured (live latest_articles)', () => {
  assert.ok(src.includes('useHomepageFeatured'), 'must use useHomepageFeatured')
  assert.ok(/latest_articles/.test(src), 'must read latest_articles')
})

ok('cards deep-link to article detail', () =>
  assert.ok(/`\/blog\/|to="\/blog\//.test(src), 'must link to /blog/{slug}'))

ok('Content Engine meta-flex present', () => {
  assert.ok(/Content Engine/i.test(src), 'must name the AI Content Engine')
  assert.ok(/writes itself|self-writing|auto/i.test(src), 'must carry the meta-flex framing')
})

ok('handles loading + empty states', () => {
  assert.ok(/isLoading|loading/i.test(src), 'must reference loading state')
  assert.ok(/v-if|v-else/.test(src), 'must branch for empty/loaded')
})

ok('no placeholder / TODO markers', () =>
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum/i.test(src), 'no placeholder markers'))

console.log(`\n${passed} checks passed.`)
