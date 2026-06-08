// Smoke test for useExperienceGalleries — file-content checks (no DOM/jsdom).
// Run: node src/composables/useExperienceGalleries.test.mjs
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'useExperienceGalleries.js'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('exports useExperienceGalleries', () =>
  assert.ok(/export function useExperienceGalleries/.test(src), 'must export useExperienceGalleries'))

ok('fetches the /galleries/{id} endpoint (gallery_id based, not award)', () => {
  assert.ok(/\/galleries\/\$\{id\}/.test(src) || /`\/galleries\//.test(src), 'must hit /galleries/{id}')
  assert.ok(!/\/awards\//.test(src), 'career galleries are gallery_id based, not award based')
})

ok('cover prefers the gallery thumbnail, falls back to item file_url', () => {
  assert.ok(/g\.thumbnail/.test(src), 'cover must prefer the curated gallery.thumbnail')
  assert.ok(/file_url/.test(src), 'must fall back to item file_url so modal resolves')
})

ok('parallel fetch + per-id isolation (Promise.all + try/catch → null)', () => {
  assert.ok(/Promise\.all/.test(src), 'must fetch in parallel')
  assert.ok(/catch/.test(src), 'per-gallery failure must isolate to null')
})

ok('exposes coverFor + itemsFor flatteners (chapter spans multiple galleries)', () => {
  assert.ok(/coverFor/.test(src) && /itemsFor/.test(src), 'must expose coverFor + itemsFor')
})

console.log(`\n${passed} checks passed.`)
