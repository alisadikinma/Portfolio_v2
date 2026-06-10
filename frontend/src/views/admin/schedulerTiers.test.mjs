// Phase A — admin-settings-simplify plan (2026-06-09).
// Pure classifier for the Scheduler tab: operator-tunable rows show by default,
// self-healing internals + locked placeholders collapse into accordions.
//
// Run: node src/views/admin/schedulerTiers.test.mjs

import assert from 'node:assert'
import { classifyTier, OPERATOR_SIGNATURES } from './schedulerTiers.js'

let passed = 0
const check = (label, fn) => { fn(); passed++; console.log(`  ✓ ${label}`) }

check('operator signature → operator', () => {
  assert.strictEqual(classifyTier('social-cross-post:scan', false), 'operator')
  assert.strictEqual(classifyTier('newsletter:send-weekly', false), 'operator')
  assert.strictEqual(classifyTier('content:auto-pipeline', false), 'operator')
})

check('self-healing internal → system', () => {
  assert.strictEqual(classifyTier('linkedin:reap-stuck', false), 'system')
  assert.strictEqual(classifyTier('geminigen:circuit-probe', false), 'system')
  assert.strictEqual(classifyTier('linkedin:purge-low-virality', false), 'system')
})

check('placeholder flag wins regardless of signature', () => {
  assert.strictEqual(classifyTier('placeholder:tiktok-publish', true), 'placeholder')
  // even an operator-named signature is placeholder when flagged
  assert.strictEqual(classifyTier('social-cross-post:scan', true), 'placeholder')
})

check('unknown signature defaults to system (safe — hidden, not lost)', () => {
  assert.strictEqual(classifyTier('some:future-command', false), 'system')
})

check('OPERATOR_SIGNATURES is the documented ~7-row allowlist', () => {
  assert.ok(OPERATOR_SIGNATURES instanceof Set)
  assert.strictEqual(OPERATOR_SIGNATURES.size, 7)
  // Phase C (2026-06-10): linkedin:scan-blog cron retired — publishing now
  // auto-creates drafts (event-driven), so it's no longer operator-tunable.
  assert.ok(!OPERATOR_SIGNATURES.has('linkedin:scan-blog'))
  assert.ok(OPERATOR_SIGNATURES.has('linkedin:auto-schedule'))
})

console.log(`\n${passed} checks passed`)
