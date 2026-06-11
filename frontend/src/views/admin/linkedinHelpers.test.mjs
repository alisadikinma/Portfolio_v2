// Smoke tests for linkedinHelpers.js (Node --test runner).
// Pattern: pure ES module import + manual assertEqual harness.
// Run: node --test frontend/src/views/admin/linkedinHelpers.test.mjs
//
// Scope: generatingProgress(draft) — synthetic % indicator derived from
// pipeline_state_log[] timestamps + format-aware baselines.

import {
  generatingProgress,
  generatingProgressMeta,
  shouldShowLastError,
  resolveCarouselActivity,
  formatElapsed,
} from './linkedinHelpers.js'

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

console.log('--- generatingProgressMeta tests (real vs synthetic + stuck) ---')

// 8. Real progress takes precedence over synthetic
{
  const draft = {
    ...mkDraft({ status: 'generating', format: 'text', offsetMs: 30_000 }),
    progress_percentage: 60,
  }
  const meta = generatingProgressMeta(draft)
  assertEqual('real progress 60 preferred over synthetic ~50', meta?.pct, 60)
  assertEqual('source flagged as real', meta?.source, 'real')
  assertEqual('not stuck when real=60', meta?.stuck, false)
}

// 9. Stuck detection: real=5, elapsed > 5× baseline (text 60s baseline → 360s)
{
  const draft = {
    ...mkDraft({ status: 'generating', format: 'text', offsetMs: 400_000 }),
    progress_percentage: 5,
  }
  const meta = generatingProgressMeta(draft)
  assertEqual('stuck flagged when real=5 + elapsed >> baseline', meta?.stuck, true)
  assertEqual('real pct still surfaced when stuck', meta?.pct, 5)
}

// 10. No real progress → synthetic source label
{
  const meta = generatingProgressMeta(mkDraft({ status: 'generating', format: 'text', offsetMs: 30_000 }))
  assertEqual('source flagged as synthetic when no real progress', meta?.source, 'synthetic')
}

// 11. Synthetic stuck: no real progress + elapsed >> 5× baseline (text 300s+)
{
  const meta = generatingProgressMeta(mkDraft({ status: 'generating', format: 'text', offsetMs: 400_000 }))
  assertEqual('synthetic stuck when elapsed >> 5× baseline', meta?.stuck, true)
}

// 12. Out-of-scope returns null
assertEqual(
  'generatingProgressMeta returns null for published',
  generatingProgressMeta({ status: 'published', format: 'text' }),
  null,
)

// ---------------------------------------------------------------------------
// formatElapsed(ms) — "Nm SSs" or '' for null/invalid/negative.
// ---------------------------------------------------------------------------
assertEqual('formatElapsed 192000ms', formatElapsed(192_000), '3m12s')
assertEqual('formatElapsed 5000ms zero-pads seconds', formatElapsed(5_000), '0m05s')
assertEqual('formatElapsed null', formatElapsed(null), '')
assertEqual('formatElapsed negative guard', formatElapsed(-100), '')

// ---------------------------------------------------------------------------
// shouldShowLastError({ lastError, status, pipelineLog, slides,
//   regenerateActive, nowMs, staleHours }) — suppress while in flight,
//   else existing status + staleness gates.
// ---------------------------------------------------------------------------
const NOW = Date.now()
const recentLog = [{ to: 'manual_review', timestamp: new Date(NOW - 60_000).toISOString() }]
const staleLog = [{ to: 'manual_review', timestamp: new Date(NOW - 48 * 36e5).toISOString() }]

assertEqual('error suppressed when regenerateActive',
  shouldShowLastError({ lastError: 'boom', status: 'manual_review', pipelineLog: recentLog, slides: [], regenerateActive: true, nowMs: NOW }), false)
assertEqual('error suppressed when a slide is generating',
  shouldShowLastError({ lastError: 'boom', status: 'manual_review', pipelineLog: recentLog, slides: [{ image_status: 'generating' }], nowMs: NOW }), false)
assertEqual('error suppressed when a slide is reauthoring',
  shouldShowLastError({ lastError: 'boom', status: 'manual_review', pipelineLog: recentLog, slides: [{ image_status: 'reauthoring' }], nowMs: NOW }), false)
assertEqual('no error → false',
  shouldShowLastError({ lastError: null, status: 'failed', pipelineLog: recentLog, slides: [], nowMs: NOW }), false)
assertEqual('status not failed/manual_review → false',
  shouldShowLastError({ lastError: 'boom', status: 'awaiting_publish', pipelineLog: recentLog, slides: [], nowMs: NOW }), false)
assertEqual('failed + no log → true',
  shouldShowLastError({ lastError: 'boom', status: 'failed', pipelineLog: [], slides: [], nowMs: NOW }), true)
assertEqual('manual_review + recent log → true (regression)',
  shouldShowLastError({ lastError: 'boom', status: 'manual_review', pipelineLog: recentLog, slides: [{ image_status: 'done', image_url: 'x' }], nowMs: NOW, staleHours: 24 }), true)
assertEqual('stale error (>staleHours) → false',
  shouldShowLastError({ lastError: 'boom', status: 'failed', pipelineLog: staleLog, slides: [], nowMs: NOW, staleHours: 24 }), false)

// ---------------------------------------------------------------------------
// resolveCarouselActivity({ slides, regenerateActive, regenerateStartedAt, nowMs })
//   → { phase, renderDone, renderTotal, elapsedMs }
// ---------------------------------------------------------------------------
{
  const a = resolveCarouselActivity({ slides: [{ image_status: 'done', image_url: 'x' }, { image_status: 'done', image_url: 'y' }], regenerateActive: true, nowMs: NOW })
  assertEqual('regenerateActive → re_authoring phase', a.phase, 're_authoring')
}
{
  const a = resolveCarouselActivity({ slides: [{ image_status: 'reauthoring' }, { image_status: 'reauthoring' }], nowMs: NOW })
  assertEqual('reauthoring slide → re_authoring phase', a.phase, 're_authoring')
}
{
  const a = resolveCarouselActivity({ slides: [{ image_status: 'done', image_url: 'x' }, { image_status: 'generating' }, { image_status: 'pending' }], nowMs: NOW })
  assertEqual('in-flight slide → rendering phase', a.phase, 'rendering')
  assertEqual('renderDone counts done-with-url', a.renderDone, 1)
  assertEqual('renderTotal counts all slides', a.renderTotal, 3)
}
{
  const a = resolveCarouselActivity({ slides: [{ image_status: 'done', image_url: 'x' }, { image_status: 'done', image_url: 'y' }], nowMs: NOW })
  assertEqual('all done → ready phase', a.phase, 'ready')
  assertEqual('ready renderDone === renderTotal', a.renderDone, a.renderTotal)
}
{
  const a = resolveCarouselActivity({ slides: [{ image_status: 'reauthoring' }], regenerateActive: true, regenerateStartedAt: new Date(NOW - 120_000).toISOString(), nowMs: NOW })
  assertEqual('elapsedMs computed from started_at', a.elapsedMs, 120_000)
}
{
  const a = resolveCarouselActivity({ slides: [{ image_status: 'reauthoring' }], regenerateActive: true, regenerateStartedAt: null, nowMs: NOW })
  assertEqual('elapsedMs null when no started_at', a.elapsedMs, null)
}
{
  const a = resolveCarouselActivity({ slides: [], regenerateActive: false, nowMs: NOW })
  assertEqual('empty + inactive → idle phase', a.phase, 'idle')
}

console.log(`---\nTotal: ${passed} passed, ${failed} failed`)
process.exit(failed > 0 ? 1 : 0)
