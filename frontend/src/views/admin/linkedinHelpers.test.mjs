// Smoke tests for linkedinHelpers.js (Node --test runner).
// Pattern: pure ES module import + manual assertEqual harness.
// Run: node --test frontend/src/views/admin/linkedinHelpers.test.mjs
//
// Scope: generatingProgress(draft) — synthetic % indicator derived from
// pipeline_state_log[] timestamps + format-aware baselines.

import { generatingProgress } from './linkedinHelpers.js'

let failed = 0
let passed = 0

function assertEqual(label, actual, expected) {
  // Strict equality for primitives + null
  if (actual === expected) {
    console.log(`PASS: ${label} (got ${actual})`)
    passed++
  } else {
    console.error(`FAIL: ${label} — expected ${expected}, got ${actual}`)
    failed++
  }
}

function assertInRange(label, actual, min, max) {
  if (typeof actual === 'number' && actual >= min && actual <= max) {
    console.log(`PASS: ${label} (got ${actual}, in [${min},${max}])`)
    passed++
  } else {
    console.error(`FAIL: ${label} — expected number in [${min},${max}], got ${actual}`)
    failed++
  }
}

// Helper: build a minimal draft with one pipeline_state_log entry transitioning
// into the given target state at a given offset (ms ago) from now.
function mkDraft({ status, format = 'text', offsetMs = 0, target = null }) {
  const transitionTo = target ?? status
  const ts = new Date(Date.now() - offsetMs).toISOString()
  return {
    status,
    format,
    pipeline_state_log: [
      { from: 'pending_generation', to: transitionTo, reason: 'plugin_dispatch_start', timestamp: ts },
    ],
  }
}

console.log('--- generatingProgress smoke tests ---')

// 1. Out-of-scope statuses return null
assertEqual(
  'returns null for status=manual_review',
  generatingProgress({ status: 'manual_review', format: 'text', pipeline_state_log: [] }),
  null,
)

assertEqual(
  'returns null for status=published',
  generatingProgress({ status: 'published', format: 'text', pipeline_state_log: [] }),
  null,
)

// 2. Missing/empty pipeline_state_log
assertEqual(
  'returns null when pipeline_state_log empty',
  generatingProgress({ status: 'generating', format: 'text', pipeline_state_log: [] }),
  null,
)

assertEqual(
  'returns null when pipeline_state_log missing entirely',
  generatingProgress({ status: 'generating', format: 'text' }),
  null,
)

// 3. Text format, 30s elapsed @ 60s baseline ≈ 50%
assertInRange(
  'text format, ~30s elapsed, expect ~50%',
  generatingProgress(mkDraft({ status: 'generating', format: 'text', offsetMs: 30_000 })),
  45, 55, // tolerance for clock drift between mkDraft + helper Date.now()
)

// 4. Carousel format, 180s elapsed @ 360s baseline ≈ 50%
assertInRange(
  'carousel format, ~180s elapsed, expect ~50%',
  generatingProgress(mkDraft({ status: 'generating', format: 'carousel', offsetMs: 180_000 })),
  45, 55,
)

// 5. Hard cap at 95% — text format, 90s elapsed = 150% raw → cap to 95
assertEqual(
  'text format, 90s elapsed → cap at 95%',
  generatingProgress(mkDraft({ status: 'generating', format: 'text', offsetMs: 90_000 })),
  95,
)

// 6. Validating ramps 95→99 (8s baseline). At 4s elapsed (50% through window),
// formula yields 95 + (50/25) = 97. Tolerance covers 1s clock drift.
assertInRange(
  'validating, ~4s elapsed, expect 96-98',
  generatingProgress(mkDraft({ status: 'validating', format: 'text', offsetMs: 4_000, target: 'validating' })),
  96, 98,
)

// 6b. Validating capped at 99 — long elapsed should not exceed.
assertEqual(
  'validating, 30s elapsed → cap at 99',
  generatingProgress(mkDraft({ status: 'validating', format: 'text', offsetMs: 30_000, target: 'validating' })),
  99,
)

// 7. Defensive: malformed timestamp → null (no NaN%)
assertEqual(
  'returns null when timestamp is malformed',
  generatingProgress({
    status: 'generating',
    format: 'text',
    pipeline_state_log: [{ from: 'pending_generation', to: 'generating', timestamp: 'not-a-date' }],
  }),
  null,
)

console.log(`---\nTotal: ${passed} passed, ${failed} failed`)
process.exit(failed > 0 ? 1 : 0)
