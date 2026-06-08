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

ok('every tab has a cta with label + to', () => {
  for (const t of tabs) {
    assert.ok(t.cta && t.cta.label && t.cta.to, `tab ${t.id} missing cta`)
  }
})

console.log(`\n${passed} checks passed.`)
