import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseHistory } from '../lib/geminigen.js';
import { pickProvider, classifyVideoError } from '../animate.js';

test('pickProvider: no error stays on current provider', () => {
  assert.equal(pickProvider({ current: 'veo' }), 'veo');
});

test('pickProvider: a known figure on the keyframe → GROK upfront (Veo refuses celeb faces)', () => {
  assert.equal(pickProvider({ current: 'veo', hasFigure: true }), 'grok');
});

test('pickProvider: audio_filtered + prominent_people fail over to GROK', () => {
  assert.equal(pickProvider({ current: 'veo', errorClass: 'audio_filtered' }), 'grok');
  assert.equal(pickProvider({ current: 'veo', errorClass: 'prominent_people' }), 'grok');
});

test('pickProvider: content_policy/transient retry the SAME provider (prompt degraded elsewhere)', () => {
  assert.equal(pickProvider({ current: 'veo', errorClass: 'content_policy' }), 'veo');
  assert.equal(pickProvider({ current: 'veo', errorClass: 'transient' }), 'veo');
});

test('classifyVideoError: substring match → class (mirrors backend VideoGenErrorClassifier)', () => {
  assert.equal(classifyVideoError('PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'), 'prominent_people');
  assert.equal(classifyVideoError('Audio generation failed'), 'audio_filtered');
  assert.equal(classifyVideoError('blocked by content_policy'), 'content_policy');
  assert.equal(classifyVideoError('connection reset'), 'transient');
  assert.equal(classifyVideoError(null), 'transient');
});

test('parseHistory: pending job (status<3, no error) is not terminal', () => {
  const r = parseHistory({ data: { status: 1 } });
  assert.equal(r.terminal, false);
  assert.equal(r.error, null);
});

test('parseHistory: status 3 image job exposes imageUrl, terminal, no error', () => {
  const r = parseHistory({ data: { status: 3, image_url: 'https://x/y.png' } });
  assert.equal(r.terminal, true);
  assert.equal(r.error, null);
  assert.equal(r.imageUrl, 'https://x/y.png');
});

test('parseHistory: video job reads generated_video[0].video_url', () => {
  const r = parseHistory({ data: { status: 3, generated_video: [{ video_url: 'https://x/v.mp4' }] } });
  assert.equal(r.videoUrl, 'https://x/v.mp4');
});

test('parseHistory: error_message marks terminal+error even at status<3', () => {
  const r = parseHistory({ data: { status: 2, error_message: 'PUBLIC_ERROR_AUDIO_FILTERED' } });
  assert.equal(r.terminal, true);
  assert.equal(r.error, 'PUBLIC_ERROR_AUDIO_FILTERED');
});

test('parseHistory: tolerates a flat (un-nested) body', () => {
  const r = parseHistory({ status: 3, image_url: 'https://x/z.png' });
  assert.equal(r.imageUrl, 'https://x/z.png');
  assert.equal(r.terminal, true);
});
