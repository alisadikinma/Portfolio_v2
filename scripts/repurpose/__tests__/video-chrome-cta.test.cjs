#!/usr/bin/env node
/**
 * Phase D unit test (#3 CTA ask) — video-chrome.cjs must build a CTA overlay
 * card with a real Follow / Save / Comment ask, and MUST NOT promise a
 * comment→DM auto-delivery (no auto-DM infra). The card is composited over the
 * CTA Veo clip so the ask is visible in-feed regardless of caption truncation.
 *
 * Tests the pure buildCtaOverlayHtml(handle) export (no Chromium launch).
 * Run: node scripts/repurpose/__tests__/video-chrome-cta.test.cjs
 */
'use strict';

const assert = require('assert');
const { buildCtaOverlayHtml } = require('../video-chrome.cjs');

let failures = 0;
function check(name, fn) {
  try {
    fn();
    console.log('  ok  - ' + name);
  } catch (e) {
    failures++;
    console.error('  FAIL- ' + name + '\n        ' + e.message);
  }
}

const html = buildCtaOverlayHtml('@alisadikinma');

check('renders the Follow ask with the handle', () => {
  assert.ok(/Follow/i.test(html), 'missing Follow');
  assert.ok(html.includes('@alisadikinma'), 'missing handle');
});

check('renders Save + Comment asks', () => {
  assert.ok(/Save/i.test(html), 'missing Save ask');
  assert.ok(/Comment/i.test(html), 'missing Comment ask');
});

check('does NOT promise a comment→DM auto-delivery', () => {
  assert.ok(!/\bDM\b/i.test(html), 'must not mention DM');
  assert.ok(!/send (?:you|it)/i.test(html), 'must not promise to send anything');
  assert.ok(!/inbox/i.test(html), 'must not mention inbox');
});

check('is a transparent full-canvas overlay (1350 tall, no opaque page bg)', () => {
  assert.ok(html.includes('1350'), 'overlay should target the 1080x1350 canvas');
  assert.ok(/background:\s*transparent/i.test(html) || /body\{[^}]*transparent/i.test(html), 'body must be transparent for omitBackground');
});

if (failures > 0) {
  console.error(`\n${failures} assertion(s) failed.`);
  process.exit(1);
}
console.log('\nAll video-chrome CTA overlay assertions passed.');
