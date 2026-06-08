// Smoke test for Home.vue recompose (The Operator §D). File-content assertions.
// Run: node src/views/Home.test.mjs
//
// Asserts the 9-section spine is imported + rendered in order and the retired
// sections (SkillsReel / SkillShowcase / StatsBar / CinematicHero / ProjectsBento
// / LatestBlog / CTASection) are gone from Home.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'Home.vue'), 'utf8')

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

const SPINE = [
  'HeroOperator',
  'WhoIAm',
  'WhatISolveTabs',
  'ReceiptsBento',
  'InternationalStages',
  'SelectedWork',
  'TestimonialsCarousel',
  'LatestWriting',
  'JoinTheBuild',
]

ok('all 9 sections imported', () => {
  for (const c of SPINE)
    assert.ok(new RegExp(`import\\s+${c}\\s+from`).test(src), `missing import: ${c}`)
})

ok('9 sections rendered in correct order (in <template>)', () => {
  const tpl = src.slice(src.indexOf('<template>'), src.indexOf('</template>'))
  let cursor = -1
  for (const c of SPINE) {
    const at = tpl.indexOf(`<${c}`)
    assert.ok(at > -1, `not rendered: ${c}`)
    assert.ok(at > cursor, `out of order: ${c}`)
    cursor = at
  }
})

ok('each section gated on its snap-section wrapper', () => {
  // every <X .../> in template should be preceded by an isSectionActive v-if wrapper
  const count = (src.match(/isSectionActive\(/g) || []).length
  assert.ok(count >= SPINE.length, `expected ≥${SPINE.length} isSectionActive gates, found ${count}`)
})

ok('retired sections no longer imported', () => {
  for (const c of ['SkillsReel', 'SkillShowcase', 'StatsBar', 'CinematicHero', 'ProjectsBento', 'LatestBlog', 'CTASection'])
    assert.ok(!new RegExp(`import\\s+${c}\\s+from`).test(src), `should not import retired: ${c}`)
})

console.log(`\n${passed} checks passed.`)
