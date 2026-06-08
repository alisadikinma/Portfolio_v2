// Smoke test for InternationalStages.vue (The Operator §5 → "Track Record"). File-content checks.
// Run: node src/components/home/InternationalStages.test.mjs
//
// Reframed (Follow-up Pass 3): the section is now "Track Record" — two labeled bands:
// a 3-chapter 17-year CAREER band (Singapore MNC IT · Marlin Booking · Sat Nusapersada)
// ABOVE the existing 5 speaking/competition STAGE cards. IDBYTE dropped (weakest).
// Career facts sourced from the WHY-vault 10-Identity/experience.md — must stay accurate.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'InternationalStages.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('reframed to Track Record', () =>
  assert.ok(/Track Record/i.test(src), 'must use the "Track Record" reframe heading/eyebrow'))

ok('six stages present (IDBYTE restored)', () => {
  for (const s of ['Hangzhou', 'Silicon Valley', 'Fenox', 'Bengaluru', 'NextDev', 'IDBYTE'])
    assert.ok(src.includes(s), `missing stage: ${s}`)
})

ok('IDBYTE stage restored (Top 8 Finalist 2017)', () => {
  assert.ok(/IDBYTE 2017 Connected/.test(src), 'must include the IDBYTE 2017 Connected event')
  assert.ok(/Top 8 Finalist/.test(src), 'must show the Top 8 Finalist result')
})

ok('career band present (3 chapters, vault-verified)', () => {
  for (const c of ['Sat Nusapersada', 'Marlin', 'exSYS'])
    assert.ok(src.includes(c), `missing career chapter reference: ${c}`)
})

ok('career cards pull real experience-gallery photos (gallery_id based)', () => {
  assert.ok(/useExperienceGalleries/.test(src), 'must consume useExperienceGalleries')
  assert.ok(/galleryIds:/.test(src), 'career chapters must carry galleryIds')
  // Singapore career journey = gallery 14 (verified via /api/galleries/14)
  assert.ok(/\b14\b/.test(src), 'Singapore chapter must map to gallery 14')
})

ok('Marlin card uses the purpose-built illustration cover', () => {
  assert.ok(/coverOverride/.test(src), 'Marlin must carry a coverOverride')
  assert.ok(/marlin-booking\.jpg/.test(src), 'cover must point at the Marlin illustration')
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

// Task 4 (unchanged): each curated stage maps to a real award gallery and shows a photo.
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
