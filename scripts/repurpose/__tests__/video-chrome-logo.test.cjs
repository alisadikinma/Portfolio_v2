#!/usr/bin/env node
/**
 * Phase A unit test — video-chrome.cjs must inline the brand logo as a
 * data:image/png;base64 URI (origin-independent) instead of a `file://` URL,
 * because Playwright `page.setContent()` runs in an opaque `about:blank` origin
 * that silently blocks `file://` sub-resources (the broken-logo bug).
 *
 * Plain node test (no jest) — mirrors the frontend *.test.mjs smoke-test
 * convention. Run: node scripts/repurpose/__tests__/video-chrome-logo.test.cjs
 * Exits 0 on pass, non-zero with a message on the first failure.
 */
'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('assert');

const { toDataUri } = require('../video-chrome.cjs');

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

// A tiny payload — toDataUri just base64-encodes the bytes (no PNG validation).
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'chrome-logo-'));
const pngPath = path.join(tmp, 'logo.png');
const bytes = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 1, 2, 3, 4]);
fs.writeFileSync(pngPath, bytes);

check('empty logo arg returns empty string', () => {
  assert.strictEqual(toDataUri(''), '');
});

check('missing file returns empty string (no throw)', () => {
  assert.strictEqual(toDataUri(path.join(tmp, 'does-not-exist.png')), '');
});

check('plain path → data:image/png;base64 URI', () => {
  const uri = toDataUri(pngPath);
  assert.ok(uri.startsWith('data:image/png;base64,'), 'expected data URI, got: ' + uri.slice(0, 40));
});

check('file:// prefix is stripped and still inlined', () => {
  const uri = toDataUri('file://' + pngPath);
  assert.ok(uri.startsWith('data:image/png;base64,'), 'expected data URI from file:// path, got: ' + uri.slice(0, 40));
});

check('base64 payload decodes back to the original bytes', () => {
  const uri = toDataUri(pngPath);
  const b64 = uri.slice('data:image/png;base64,'.length);
  assert.ok(Buffer.from(b64, 'base64').equals(bytes), 'decoded bytes differ from source');
});

check('MIME is derived from the file extension (.jpg → image/jpeg)', () => {
  const jpgPath = path.join(tmp, 'logo.jpg');
  fs.writeFileSync(jpgPath, bytes);
  assert.ok(toDataUri(jpgPath).startsWith('data:image/jpeg;base64,'), 'expected jpeg mime');
});

check('unknown extension falls back to image/png', () => {
  const odd = path.join(tmp, 'logo.bin');
  fs.writeFileSync(odd, bytes);
  assert.ok(toDataUri(odd).startsWith('data:image/png;base64,'), 'expected png fallback mime');
});

fs.rmSync(tmp, { recursive: true, force: true });

if (failures > 0) {
  console.error(`\n${failures} assertion(s) failed.`);
  process.exit(1);
}
console.log('\nAll video-chrome logo data-URI assertions passed.');
