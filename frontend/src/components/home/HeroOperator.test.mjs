// Smoke test for HeroOperator.vue (The Operator §1). File-content assertions
// (no component runner). Run: node src/components/home/HeroOperator.test.mjs
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'HeroOperator.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('consumes useHomepageFeatured (real stats)', () =>
  assert.ok(src.includes('useHomepageFeatured'), 'must use useHomepageFeatured'))

ok('has a <video> hero element', () =>
  assert.ok(/<video/.test(src), 'must render a video element'))

ok('respects reduced motion', () =>
  assert.ok(/reduced/i.test(src), 'must handle prefers-reduced-motion'))

ok('renders ALI SADIKIN MA wordmark', () =>
  assert.ok(src.includes('ALI SADIKIN MA'), 'must render the wordmark'))

ok('has all 3 hero CTAs', () => {
  for (const c of ['Follow the build', 'Learn AI with me', 'Read the blog'])
    assert.ok(src.includes(c), `missing CTA: ${c}`)
})

ok('manifesto uses business-outcomes framing (no "factories" count)', () => {
  assert.ok(src.includes('real business outcomes'), 'manifesto must say real business outcomes')
  assert.ok(!/16 countries/.test(src), 'must not contain ambiguous "16 countries"')
})

console.log(`\n${passed} checks passed.`)
