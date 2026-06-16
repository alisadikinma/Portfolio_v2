import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractJson } from '../lib/run.js';
import { parseWhisperJson } from '../asr.js';
import { buildClassifyPrompt, parseClassifyResponse } from '../segment.js';
import { buildTranslatePrompt, parseTranslateResponse } from '../translate.js';

test('extractJson: tolerates preamble narration + trailing code fence', () => {
  const raw = 'Sure, here it is:\n```json\n[{"index":0,"type":"to_camera"}]\n```\nDone.';
  assert.deepEqual(extractJson(raw), [{ index: 0, type: 'to_camera' }]);
});

test('extractJson: ignores braces inside strings', () => {
  const raw = '{"id":"a } b","ok":true}';
  assert.deepEqual(extractJson(raw), { id: 'a } b', ok: true });
});

test('extractJson: throws when no JSON present', () => {
  assert.throws(() => extractJson('no json here'), /no JSON found/);
});

test('parseWhisperJson: maps ms offsets to second timestamps', () => {
  const json = {
    transcription: [
      { text: ' AI', offsets: { from: 100, to: 500 } },
      { text: ' tools', offsets: { from: 600, to: 1000 } },
      { text: '   ', offsets: { from: 1000, to: 1100 } }, // blank → skipped
    ],
  };
  const { words, text } = parseWhisperJson(json);
  assert.equal(words.length, 2);
  assert.deepEqual(words[0], { word: 'AI', start: 0.1, end: 0.5 });
  assert.equal(text, 'AI tools');
});

test('parseClassifyResponse: index-aligned, unknown type → b_roll', () => {
  const text = '[{"index":1,"type":"split_screen"},{"index":0,"type":"weird"}]';
  assert.deepEqual(parseClassifyResponse(text, 2), [{ type: 'b_roll' }, { type: 'split_screen' }]);
});

test('parseClassifyResponse: missing span → safe default', () => {
  assert.deepEqual(parseClassifyResponse('[{"index":0,"type":"to_camera"}]', 2), [
    { type: 'to_camera' }, { type: 'b_roll' },
  ]);
});

test('buildClassifyPrompt: lists every span + frame + speech hint', () => {
  const p = buildClassifyPrompt(
    [{ start: 0, end: 2, sourceTextEn: 'hi there' }, { start: 2, end: 4, sourceTextEn: '' }],
    ['/f/0.jpg', '/f/1.jpg'],
  );
  assert.match(p, /Span 0:.*\/f\/0\.jpg.*"hi there"/);
  assert.match(p, /Span 1:.*no speech/);
});

test('parseTranslateResponse: index-aligned ID strings, missing → empty', () => {
  const text = '[{"index":0,"id":"halo"},{"index":2,"id":"dunia"}]';
  assert.deepEqual(parseTranslateResponse(text, 3), ['halo', '', 'dunia']);
});

test('buildTranslatePrompt: numbers lines + marks no-speech', () => {
  const p = buildTranslatePrompt(['hello', '']);
  assert.match(p, /0: hello/);
  assert.match(p, /1: \(no speech\)/);
});
