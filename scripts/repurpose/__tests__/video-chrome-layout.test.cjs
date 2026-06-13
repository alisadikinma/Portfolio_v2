#!/usr/bin/env node
/**
 * Unit test — the redesigned tool-slide chrome (June 13, 2026):
 *   - header has NO top-left creator avatar (brand lives only in the footer now)
 *   - header renders a bilingual body: Indonesian DESC (primary) + English
 *     SUBTITLE companion
 *   - footer carries IG/TikTok/LinkedIn glyphs + @handle, a globe + site, and the
 *     gold "Geser (Swipe) →" pill
 *
 * Plain node test (no jest). Run:
 *   node scripts/repurpose/__tests__/video-chrome-layout.test.cjs
 */
'use strict';

const assert = require('assert');
const { buildHeaderHtml, buildFooterHtml } = require('../video-chrome.cjs');

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

// ---- header ----

check('header renders the tool title + Indonesian primary desc + English subtitle', () => {
  const html = buildHeaderHtml({
    title: 'Opal',
    desc: 'Bikin aplikasi AI sendiri cukup dengan mendeskripsikannya',
    subtitle: 'Build your own AI app just by describing it',
    active: 1,
    total: 9,
    number: '1',
  });
  assert.ok(html.includes('Opal'), 'title missing');
  assert.ok(html.includes('class="desc">Bikin aplikasi AI'), 'Indonesian primary desc missing');
  assert.ok(html.includes('class="sub">Build your own AI app'), 'English subtitle companion missing');
});

check('header has NO top-left avatar/logo img', () => {
  const html = buildHeaderHtml({ title: 'X', desc: 'd', subtitle: 's', active: 1, total: 3, number: '1' });
  // the only <img> in the chrome now lives in the footer; the header must have none
  assert.ok(!/<img/i.test(html), 'header still contains an <img> (top-left logo not removed)');
});

check('header stepper lights the first `active` chips', () => {
  const html = buildHeaderHtml({ title: 'X', desc: 'd', active: 2, total: 4, number: '2' });
  const on = (html.match(/chip on/g) || []).length;
  const off = (html.match(/chip off/g) || []).length;
  assert.strictEqual(on, 2, 'expected 2 lit chips');
  assert.strictEqual(off, 2, 'expected 2 unlit chips');
});

check('header omits the subtitle node when no English companion given', () => {
  const html = buildHeaderHtml({ title: 'X', desc: 'd', subtitle: '', active: 1, total: 1, number: '1' });
  assert.ok(!html.includes('class="sub"'), 'subtitle node should be absent when empty');
});

// ---- footer ----

check('footer carries IG, TikTok, LinkedIn glyphs + globe + handle + site + swipe', () => {
  const html = buildFooterHtml({ logoUri: '', handle: '@alisadikinma', site: 'alisadikinma.com' });
  const svgCount = (html.match(/<svg/g) || []).length;
  assert.strictEqual(svgCount, 4, 'expected 4 svg glyphs (IG, TT, LI, globe)');
  assert.ok(html.includes('@alisadikinma'), 'handle missing');
  assert.ok(html.includes('alisadikinma.com'), 'site missing');
  assert.ok(/Geser \(Swipe\) →/.test(html), 'swipe pill missing');
});

check('footer renders the creator avatar when a logo URI is provided', () => {
  const html = buildFooterHtml({ logoUri: 'data:image/png;base64,AAAA', handle: '@x', site: 's' });
  assert.ok(html.includes('class="favatar"'), 'avatar img missing when logo provided');
});

check('footer escapes a handle with an angle bracket', () => {
  const html = buildFooterHtml({ handle: '@a<b', site: 's' });
  assert.ok(html.includes('@a&lt;b'), 'handle not HTML-escaped');
});

if (failures > 0) {
  console.error(`\n${failures} assertion(s) failed.`);
  process.exit(1);
}
console.log('\nAll video-chrome layout assertions passed.');
