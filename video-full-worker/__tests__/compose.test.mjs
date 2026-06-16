import { test } from 'node:test';
import assert from 'node:assert/strict';
import { subtitleCoverBox, parseGateResponse } from '../clean.js';
import { captionTrackFromManifest, planSegmentRender } from '../compose.js';

test('subtitleCoverBox: null when no subtitle boxes detected', () => {
  assert.equal(subtitleCoverBox([], { videoW: 1080, videoH: 1920 }), null);
});

test('subtitleCoverBox: union of boxes + padding, clamped to frame', () => {
  const box = subtitleCoverBox(
    [{ x: 100, y: 1500, w: 400, h: 80 }, { x: 200, y: 1600, w: 500, h: 80 }],
    { videoW: 1080, videoH: 1920, padding: 10 },
  );
  assert.equal(box.x, 90);          // 100 - 10
  assert.equal(box.y, 1490);        // 1500 - 10
  assert.equal(box.w, 620);         // (700-100) + 20
  assert.equal(box.h, 200);         // (1680-1500) + 20
});

test('subtitleCoverBox: clamps negative origin to 0', () => {
  const box = subtitleCoverBox([{ x: 5, y: 5, w: 100, h: 20 }], { videoW: 1080, videoH: 1920, padding: 12 });
  assert.equal(box.x, 0);
  assert.equal(box.y, 0);
});

test('parseGateResponse: normalizes vision gate JSON, defaults safe', () => {
  const r = parseGateResponse('{"has_creator_face":true,"brand_boxes":[{"x":1,"y":2,"w":3,"h":4}],"subtitle_boxes":[]}');
  assert.equal(r.hasCreatorFace, true);
  assert.deepEqual(r.brandBoxes, [{ x: 1, y: 2, w: 3, h: 4 }]);
  assert.deepEqual(r.subtitleBoxes, []);
});

test('parseGateResponse: missing keys → safe falsy defaults', () => {
  const r = parseGateResponse('{}');
  assert.equal(r.hasCreatorFace, false);
  assert.deepEqual(r.brandBoxes, []);
  assert.deepEqual(r.subtitleBoxes, []);
});

test('captionTrackFromManifest: one cue per non-empty ID segment, timed', () => {
  const cues = captionTrackFromManifest([
    { start: 0, end: 2, textId: 'Halo dunia' },
    { start: 2, end: 4, textId: '   ' },     // blank → skipped
    { start: 4, end: 6, textId: 'Tools AI' },
  ]);
  assert.deepEqual(cues, [
    { start: 0, end: 2, text: 'Halo dunia' },
    { start: 4, end: 6, text: 'Tools AI' },
  ]);
});

test('planSegmentRender: to_camera → veo, b_roll(clean) → reuse, split_screen → vstack', () => {
  assert.equal(planSegmentRender({ type: 'to_camera' }).strategy, 'veo_talking');
  assert.equal(planSegmentRender({ type: 'b_roll', animatable: false, clean: true }).strategy, 'reuse_source');
  assert.equal(planSegmentRender({ type: 'b_roll', animatable: true }).strategy, 'remotion_recreate');
  assert.equal(planSegmentRender({ type: 'split_screen' }).strategy, 'vstack_broll_top_ali_bottom');
});

test('planSegmentRender: dirty + uncroppable + not animatable b_roll → drop', () => {
  assert.equal(planSegmentRender({ type: 'b_roll', clean: false, croppable: false, animatable: false }).strategy, 'drop');
});
