import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextAction } from '../index.js';

test('nextAction: nothing in hand → poll for a job', () => {
  assert.equal(nextAction({ claimedJob: false }), 'poll');
});

test('nextAction: claimed but no manifest yet → build (capture/asr/segment/translate)', () => {
  assert.equal(nextAction({ claimedJob: true, segmentsBuilt: false }), 'build');
});

test('nextAction: manifest built but segments remain → render next segment', () => {
  assert.equal(nextAction({ claimedJob: true, segmentsBuilt: true, allSegmentsRendered: false }), 'render');
});

test('nextAction: all segments rendered, final not uploaded → compose + upload', () => {
  assert.equal(
    nextAction({ claimedJob: true, segmentsBuilt: true, allSegmentsRendered: true, finalUploaded: false }),
    'compose_upload',
  );
});

test('nextAction: everything done', () => {
  assert.equal(
    nextAction({ claimedJob: true, segmentsBuilt: true, allSegmentsRendered: true, finalUploaded: true }),
    'done',
  );
});
