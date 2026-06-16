import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildSegmentManifest } from '../lib/manifest.js';

// sceneCuts include 0 (start) and the video end → consecutive pairs are spans.
// 3 spans: [0,4.2] to_camera, [4.2,9.8] b_roll, [9.8,15] split_screen
const fixture = {
  sceneCuts: [0, 4.2, 9.8, 15],
  words: [
    { word: 'AI', start: 0.1, end: 0.5 },
    { word: 'tools', start: 0.6, end: 1.0 },
    { word: 'save', start: 1.1, end: 1.5 },     // midpoint 1.3 → span 0
    { word: 'hours', start: 5.0, end: 5.4 },     // midpoint 5.2 → span 1
    { word: 'every', start: 6.0, end: 6.4 },     // span 1
    { word: 'day', start: 10.2, end: 10.6 },     // midpoint 10.4 → span 2
  ],
  classifications: [
    { type: 'to_camera' },
    { type: 'b_roll' },
    { type: 'split_screen' },
  ],
  translations: ['Tools AI menghemat waktu', 'berjam-jam tiap hari', undefined], // span 2 untranslated
};

test('buildSegmentManifest derives one segment per scene span, in order', () => {
  const m = buildSegmentManifest(fixture);
  assert.equal(m.length, 3);
  assert.deepEqual(m.map((s) => s.index), [0, 1, 2]);
});

test('each segment has start/end/duration from the scene boundaries', () => {
  const [s0, s1, s2] = buildSegmentManifest(fixture);
  assert.deepEqual([s0.start, s0.end], [0, 4.2]);
  assert.equal(Number(s0.duration.toFixed(1)), 4.2);
  assert.deepEqual([s2.start, s2.end], [9.8, 15]);
  assert.equal(Number(s2.duration.toFixed(1)), 5.2);
});

test('words are assigned to a span by their midpoint', () => {
  const [s0, s1, s2] = buildSegmentManifest(fixture);
  assert.deepEqual(s0.words.map((w) => w.word), ['AI', 'tools', 'save']);
  assert.deepEqual(s1.words.map((w) => w.word), ['hours', 'every']);
  assert.deepEqual(s2.words.map((w) => w.word), ['day']);
});

test('sourceTextEn joins the span words; textId comes from translations', () => {
  const [s0, s1] = buildSegmentManifest(fixture);
  assert.equal(s0.sourceTextEn, 'AI tools save');
  assert.equal(s0.textId, 'Tools AI menghemat waktu');
  assert.equal(s1.sourceTextEn, 'hours every');
});

test('missing translation degrades to empty string, never undefined', () => {
  const m = buildSegmentManifest(fixture);
  assert.equal(m[2].textId, '');
});

test('classification type is carried onto each segment', () => {
  const m = buildSegmentManifest(fixture);
  assert.deepEqual(m.map((s) => s.type), ['to_camera', 'b_roll', 'split_screen']);
});

test('a span with no words yields empty sourceTextEn and words[]', () => {
  const m = buildSegmentManifest({
    sceneCuts: [0, 2, 4],
    words: [{ word: 'hi', start: 0.5, end: 0.8 }], // only span 0
    classifications: [{ type: 'to_camera' }, { type: 'b_roll' }],
    translations: ['halo', 'kosong'],
  });
  assert.equal(m[1].sourceTextEn, '');
  assert.deepEqual(m[1].words, []);
});
