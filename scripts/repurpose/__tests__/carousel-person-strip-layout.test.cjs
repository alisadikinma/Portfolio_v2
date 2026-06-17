#!/usr/bin/env node
/**
 * Unit test — carousel-person-strip composite layout (people_spotlight).
 * Plain node test (no jest). Run:
 *   node scripts/repurpose/__tests__/carousel-person-strip-layout.test.cjs
 */
'use strict';

const assert = require('assert');
const { buildStripHtml, escapeHtml } = require('../carousel-person-strip.cjs');

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

check('renders the base slide as a full-bleed background', () => {
  const html = buildStripHtml({ baseUri: 'data:image/png;base64,AAAA', width: 1080, height: 1350, faces: [], band: {} });
  assert.ok(html.includes('class="base"'), 'has base img');
  assert.ok(html.includes('data:image/png;base64,AAAA'), 'base uri embedded');
  assert.ok(html.includes('1080px') && html.includes('1350px'), 'sized to slide');
});

check('renders one framed card + caption per face', () => {
  const html = buildStripHtml({
    baseUri: 'data:image/png;base64,B',
    width: 1080,
    height: 1350,
    faces: [
      { uri: 'data:image/png;base64,F1', name: 'Ashish Vaswani', role: 'lead author' },
      { uri: 'data:image/png;base64,F2', name: 'Michael Truell' },
    ],
    band: { y: 0.12, h: 0.26 },
  });
  assert.strictEqual((html.match(/class="pf"/g) || []).length, 2, 'two face cards');
  assert.ok(html.includes('data:image/png;base64,F1') && html.includes('data:image/png;base64,F2'), 'both face uris');
  // Caption prefers role, falls back to name.
  assert.ok(html.includes('lead author'), 'role caption');
  assert.ok(html.includes('Michael Truell'), 'name caption fallback');
});

check('positions the band from the normalized band geometry', () => {
  const html = buildStripHtml({ baseUri: 'd', width: 1000, height: 1000, faces: [{ uri: 'x' }], band: { y: 0.2, h: 0.3 } });
  assert.ok(html.includes('top:200px'), 'band top = y*height');
  assert.ok(html.includes('height:300px'), 'band height = h*height');
});

check('skips faces without a uri', () => {
  const html = buildStripHtml({ baseUri: 'd', width: 1080, height: 1350, faces: [{ name: 'No URI' }, { uri: 'data:,ok' }], band: {} });
  assert.strictEqual((html.match(/class="pf"/g) || []).length, 1, 'only the face with a uri renders');
});

check('escapeHtml neutralizes caption markup', () => {
  assert.strictEqual(escapeHtml('<b>"x"</b>'), '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');
  const html = buildStripHtml({ baseUri: 'd', width: 1080, height: 1350, faces: [{ uri: 'u', role: '<script>z' }], band: {} });
  assert.ok(!html.includes('<script>z'), 'caption is escaped');
});

if (failures > 0) {
  console.error(`\n${failures} test(s) failed`);
  process.exit(1);
}
console.log('\nall carousel-person-strip layout tests passed');
