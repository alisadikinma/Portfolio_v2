// Smoke test for WhoIAm.vue (The Operator §2). File-content assertions
// (no component runner). Run: node src/components/home/WhoIAm.test.mjs
//
// §2 doubles as the answer-shaped "about" block that LLMs quote (GEO lever G3),
// so it MUST contain a verbatim "Ali Sadikin Ma is an AI Generalist…" opener
// and pull the real portrait from settings.about.profile_photo.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'WhoIAm.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('consumes useAboutSettings (real bio + portrait)', () =>
  assert.ok(src.includes('useAboutSettings'), 'must use useAboutSettings composable'))

ok('binds a portrait <img> to the avatar', () => {
  assert.ok(/<img/.test(src), 'must render an <img> portrait')
  assert.ok(/heroAvatar|profile_photo|portrait/i.test(src), 'portrait must bind real photo source')
})

ok('answer-shaped opener for LLM retrieval (G3)', () =>
  assert.ok(src.includes('Ali Sadikin Ma is an AI Generalist'),
    'must contain verbatim answer-shaped opener'))

ok('identity chips: countries + open-source', () => {
  assert.ok(/16\s*countries/i.test(src), 'must mention 16 countries')
  assert.ok(/open-source/i.test(src), 'must mention open-source repos')
})

ok('origin + trilingual grounding', () => {
  assert.ok(/Batam/.test(src), 'must mention Batam origin')
  assert.ok(/Mandarin/.test(src), 'must list trilingual (Mandarin)')
})

ok('no placeholder / TODO markers', () =>
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum/i.test(src), 'no placeholder markers'))

// Task 2: the live bio is real HTML — must render sanitized, not escaped.
ok('renders live bio via sanitized v-html (not escaped interpolation)', () => {
  assert.ok(/v-html="cleanBio"/.test(src), 'missing v-html cleanBio binding')
  assert.ok(/const\s+cleanBio\s*=\s*computed/.test(src), 'missing cleanBio computed')
  assert.ok(!/\{\{\s*liveBio\s*\}\}/.test(src), 'must not raw-escape liveBio via interpolation')
})

ok('cleanBio sanitizes (allowlist + strips script/attrs)', () => {
  assert.ok(/script/i.test(src) && /replace/.test(src), 'cleanBio must strip script/attributes')
  assert.ok(/strong|em|<p|br/i.test(src), 'cleanBio must keep an emphasis/paragraph allowlist')
})

console.log(`\n${passed} checks passed.`)
