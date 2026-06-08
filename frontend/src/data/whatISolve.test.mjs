// Smoke test for whatISolve disciplines data (The Operator redesign).
// Run: node src/data/whatISolve.test.mjs
import assert from 'node:assert/strict'
import { tabs } from './whatISolve.js'

let passed = 0
const ok = (name, fn) => { fn(); passed++; console.log(`  ✓ ${name}`) }

ok('exactly 3 disciplines', () => {
  assert.equal(tabs.length, 3, `expected 3 tabs, got ${tabs.length}`)
})

ok('first tab is vibe-coding', () => {
  assert.equal(tabs[0].id, 'vibe-coding')
})

ok("tab id 'ai-agent-os' exists (MANDOR AI)", () => {
  const t = tabs.find(t => t.id === 'ai-agent-os')
  assert.ok(t, "expected tab id 'ai-agent-os' to exist")
  assert.equal(t.product, 'MANDOR AI')
  assert.equal(t.badge, 'Introducing')
})

ok('no AI Automation tab (dropped per 3-discipline design)', () => {
  assert.ok(!tabs.find(t => t.id === 'ai-automation'), 'ai-automation should be removed')
})

ok('generative video discipline present', () => {
  const t = tabs.find(t => /video/i.test(t.label))
  assert.ok(t, 'expected a Generative Video discipline')
})

ok('ai-agent-os tab shows a branded MANDOR board image (not the OpenClaw video)', () => {
  const t = tabs.find(t => t.id === 'ai-agent-os')
  assert.ok(t.imageSrc, "ai-agent-os tab must carry an imageSrc")
  assert.ok(/mandor-board/.test(t.imageSrc), `imageSrc must point at the MANDOR board asset, got ${t.imageSrc}`)
})

ok('every tab cta scrolls to the newsletter waitlist (no dead routes)', () => {
  for (const t of tabs) {
    assert.ok(t.cta && t.cta.label, `tab ${t.id} missing cta label`)
    assert.equal(t.cta.anchor, 'join-the-build', `tab ${t.id} cta must scroll to #join-the-build`)
  }
  const json = JSON.stringify(tabs)
  assert.ok(!json.includes('/courses'), 'no dead /courses route in cta data')
  assert.ok(!json.includes('/projects/mandor-ai'), 'no dead /projects/mandor-ai route in cta data')
})

console.log(`\n${passed} checks passed.`)
