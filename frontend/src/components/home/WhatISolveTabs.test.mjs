// Smoke test for WhatISolveTabs.vue (The Operator redesign, Phase B).
// Asserts the template renders product + Introducing badge, the 3-discipline
// heading, and does NOT double-append an arrow to CTA labels (data already
// includes "→"). Run: node src/components/home/WhatISolveTabs.test.mjs
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'WhatISolveTabs.vue'), 'utf8')

let passed = 0
const ok = (name, fn) => { fn(); passed++; console.log(`  ✓ ${name}`) }

ok('renders active.product (MANDOR AI)', () => {
  assert.ok(src.includes('active.product'), 'template must reference active.product')
})

ok('renders active.badge (Introducing)', () => {
  assert.ok(src.includes('active.badge'), 'template must reference active.badge')
})

ok('heading reflects 3 disciplines', () => {
  assert.ok(/Three disciplines/i.test(src), "heading should say 'Three disciplines'")
})

ok('no double-arrow on CTA (data label already has →)', () => {
  assert.ok(!src.includes('{{ active.cta.label }} →'), 'remove trailing → after cta.label')
})

// Follow-up pass 3: CTA scrolls to the newsletter waitlist (no dead RouterLink).
ok('CTA scrolls to a section anchor (waitlist)', () => {
  assert.ok(src.includes('scrollIntoView'), 'CTA must scroll to a section anchor')
  assert.ok(src.includes('cta.anchor'), 'CTA must read active.cta.anchor')
})

console.log(`\n${passed} checks passed.`)
