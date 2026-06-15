#!/usr/bin/env node
/**
 * Hook title overlay — video-chrome.cjs `--mode hook` builds a transparent
 * full-canvas (1080×1350) overlay carrying the cover headline (from the original
 * IG hook) anchored bottom-third over a scrim, so it reads over the creator clip
 * without covering the upper-center face. Tests the pure buildHookTitleHtml export.
 * Run: node scripts/repurpose/__tests__/video-chrome-hook-title.test.cjs
 */
'use strict';

const assert = require('assert');
const { buildHookTitleHtml } = require('../video-chrome.cjs');

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

const html = buildHookTitleHtml('7 AI Tools Google yang Game-Changer');

check('renders the provided title text', () => {
  assert.ok(html.includes('7 AI Tools Google yang Game-Changer'), 'missing title');
});

check('is a transparent full-canvas overlay (1080x1350, no opaque page bg)', () => {
  assert.ok(html.includes('1350'), 'overlay should target the 1080x1350 canvas');
  assert.ok(/background:\s*transparent/i.test(html) || /body\{[^}]*transparent/i.test(html), 'body must be transparent for omitBackground');
});

check('has a bottom scrim for legibility over the clip', () => {
  assert.ok(/scrim/i.test(html), 'expected a scrim element');
  assert.ok(/linear-gradient/i.test(html), 'scrim should be a gradient');
});

check('escapes HTML in the title (no raw tag injection)', () => {
  const evil = buildHookTitleHtml('<script>x</script>');
  assert.ok(!evil.includes('<script>x'), 'title must be HTML-escaped');
  assert.ok(evil.includes('&lt;script&gt;'), 'expected escaped entities');
});

check('renders a brand logo chip when a logo URI is provided', () => {
  const withLogo = buildHookTitleHtml('7 Google AI Tools', 'data:image/png;base64,AAAA');
  assert.ok(/class="logo"/.test(withLogo), 'expected a logo chip');
  assert.ok(withLogo.includes('data:image/png;base64,AAAA'), 'expected the logo src inlined');
});

check('omits the logo chip when no logo provided', () => {
  assert.ok(!/class="logo"/.test(html), 'must not render a logo chip without a logo');
});

check('renders the English companion subtitle when provided (bilingual hook)', () => {
  const bi = buildHookTitleHtml('7 Tools AI Google yang Game-Changer', '', '7 Game-Changing Google AI Tools');
  assert.ok(/class="hsub"/.test(bi), 'expected a companion subtitle element');
  assert.ok(bi.includes('7 Game-Changing Google AI Tools'), 'expected the English companion text');
  // Indonesian primary stays the h1.
  assert.ok(/<h1>7 Tools AI Google yang Game-Changer<\/h1>/.test(bi), 'Indonesian title must be the h1');
});

check('omits the companion when empty or identical to the title', () => {
  assert.ok(!/class="hsub"/.test(html), 'no subtitle arg → no companion element');
  const same = buildHookTitleHtml('Same Title', '', 'Same Title');
  assert.ok(!/class="hsub"/.test(same), 'identical subtitle must be suppressed (no duplicate line)');
});

check('escapes HTML in the companion subtitle', () => {
  const evil = buildHookTitleHtml('Judul', '', '<b>x</b>');
  assert.ok(!evil.includes('<b>x</b>'), 'subtitle must be HTML-escaped');
  assert.ok(evil.includes('&lt;b&gt;x&lt;/b&gt;'), 'expected escaped companion entities');
});

if (failures > 0) {
  console.error(`\n${failures} assertion(s) failed.`);
  process.exit(1);
}
console.log('\nAll video-chrome hook title overlay assertions passed.');
