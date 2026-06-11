import test from 'node:test'
import assert from 'node:assert/strict'
import { isTerminal, inferFailedStep, statusTone, STATUS_LABEL, rightPaneMode } from './repurposeHelpers.js'

test('isTerminal flags only drafted/failed', () => {
  assert.equal(isTerminal('drafted'), true)
  assert.equal(isTerminal('failed'), true)
  assert.equal(isTerminal('researching'), false)
  assert.equal(isTerminal('received'), false)
})

test('inferFailedStep reads the last failed-from in the log', () => {
  assert.equal(
    inferFailedStep([
      { from: 'extracted', to: 'researching' },
      { from: 'researching', to: 'failed' },
    ]),
    'researching',
  )
  assert.equal(inferFailedStep([]), null)
  assert.equal(inferFailedStep(null), null)
  assert.equal(inferFailedStep([{ from: 'received', to: 'capturing' }]), null)
})

test('statusTone returns a non-empty class for known and unknown statuses', () => {
  assert.ok(statusTone('failed').length > 0)
  assert.ok(statusTone('drafted').length > 0)
  assert.ok(statusTone('researching').length > 0)
  assert.ok(statusTone('totally-unknown').length > 0)
})

test('rightPaneMode routes carousel(with draft)/carousel(processing)/blog', () => {
  // carousel + linkedin_post_id → the generated draft is ready to show
  assert.equal(rightPaneMode({ mode: 'carousel', linkedin_post_id: 99 }), 'generated')
  // carousel still processing (no draft yet) → show pipeline progress
  assert.equal(rightPaneMode({ mode: 'carousel', linkedin_post_id: null }), 'in_progress')
  assert.equal(rightPaneMode({ mode: 'carousel' }), 'in_progress')
  // blog mode produces a ContentIdea, not a draft → Content Engine handoff
  assert.equal(rightPaneMode({ mode: 'blog', linkedin_post_id: null }), 'blog')
  // null/unknown job → safe default
  assert.equal(rightPaneMode(null), 'in_progress')
})

test('STATUS_LABEL covers every FSM state', () => {
  for (const s of [
    'received', 'capturing', 'captured', 'extracting', 'extracted',
    'researching', 'researched', 'rewriting', 'rewritten', 'finalizing',
    'drafted', 'failed',
  ]) {
    assert.equal(typeof STATUS_LABEL[s], 'string')
  }
})
