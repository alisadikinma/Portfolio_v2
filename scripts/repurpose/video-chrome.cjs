#!/usr/bin/env node
/**
 * video_rebrand Phase D — render ONE tool slide's brand chrome (header + footer
 * PNGs) via Playwright HTML→PNG. Ported from the operator-approved POC
 * (~/poc-video-rebrand/render-chrome.cjs) and parametrized.
 *
 * Header (1080×508): brand logo top-left + cumulative numbered stepper top-right
 * (chips 1..total, the first `active` lit gold) + big gold number badge + tool
 * title + description. Footer (1080×233): navy gradient + gold top-glow + logo +
 * @handle + site + gold "Geser →" pill.
 *
 *   node video-chrome.cjs --title Stitch --desc "..." --active 2 --total 3 \
 *     --number 2 --logo /abs/logo.png --handle @alisadikinma \
 *     --site alisadikinma.com --header-out /abs/header.png --footer-out /abs/footer.png
 *
 * stdout last line: CHROME_OK  (or a thrown error + non-zero exit)
 *
 * Logo is inlined as a `data:image/png;base64,…` URI (NOT `file://`): Playwright
 * `page.setContent()` runs in an opaque `about:blank` origin that silently blocks
 * `file://` sub-resources, so a `file://` logo never rendered (the broken-logo
 * bug). `toDataUri` is exported for unit testing without launching Chromium.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
'use strict';

const fs = require('fs');
const path = require('path');

function arg(name, def) {
  const i = process.argv.indexOf('--' + name);
  return i > -1 && process.argv[i + 1] !== undefined ? process.argv[i + 1] : def;
}

const MIME_BY_EXT = {
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
};

/**
 * Read a local logo image and return an origin-independent
 * `data:<mime>;base64,…` URI. Accepts a plain path or a `file://` URL; the MIME
 * is derived from the file extension (the creator_brand_logo setting may store a
 * jpg/webp, not only png), defaulting to image/png. Returns '' for an empty arg
 * or an unreadable file (caller then emits no <img>).
 */
function toDataUri(logoArg) {
  const p = String(logoArg || '').replace(/^file:\/\//, '');
  if (!p) return '';
  try {
    const buf = fs.readFileSync(p);
    const mime = MIME_BY_EXT[path.extname(p).toLowerCase()] || 'image/png';
    return `data:${mime};base64,` + buf.toString('base64');
  } catch (e) {
    return '';
  }
}

const TITLE = arg('title', '');
const DESC = arg('desc', '');
const ACTIVE = parseInt(arg('active', '1'), 10);
const TOTAL = parseInt(arg('total', '1'), 10);
const NUMBER = arg('number', String(ACTIVE));
const LOGO = arg('logo', '');
const HANDLE = arg('handle', '@alisadikinma');
const SITE = arg('site', 'alisadikinma.com');
const HEADER_OUT = arg('header-out', '');
const FOOTER_OUT = arg('footer-out', '');
// CTA overlay mode (#3): `--mode cta --overlay-out /abs.png` renders a single
// transparent-bg ask card (composited over the CTA Veo clip via ffmpeg).
const MODE = arg('mode', '');
const OVERLAY_OUT = arg('overlay-out', '');

function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

const chips = Array.from(
  { length: TOTAL },
  (_, i) => `<div class="chip ${i < ACTIVE ? 'on' : 'off'}">${i + 1}</div>`
).join('');

const F = `@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;600&display=swap');`;
const base = `<style>${F}*{margin:0;padding:0;box-sizing:border-box;font-family:'Space Grotesk','Inter',sans-serif;color:#fff}
.hd{width:1080px;height:508px;background:linear-gradient(135deg,#1466C4,#0a3a7a 55%,#04498f);position:relative;overflow:hidden;padding:56px 60px;display:flex;flex-direction:column}
.hd::after{content:'';position:absolute;right:-120px;top:-120px;width:380px;height:380px;background:radial-gradient(circle,rgba(245,166,35,.28),transparent 70%)}
.top{display:flex;justify-content:space-between;align-items:center;z-index:2}
.logo{height:58px}
.steps{display:flex;gap:16px}
.chip{width:66px;height:66px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:32px}
.chip.on{background:linear-gradient(135deg,#F7B733,#E8920A);color:#06203f;box-shadow:0 0 22px rgba(245,166,35,.65),inset 0 0 0 2px rgba(255,255,255,.25)}
.chip.off{background:rgba(255,255,255,.07);color:rgba(255,255,255,.38);box-shadow:inset 0 0 0 2px rgba(255,255,255,.16)}
.mid{margin-top:auto;z-index:2}
.ey{display:inline-flex;align-items:center;justify-content:center;width:104px;height:104px;border-radius:26px;background:linear-gradient(135deg,#F7B733,#E8920A);color:#06203f;font-size:62px;font-weight:700;box-shadow:0 0 26px rgba(245,166,35,.55),inset 0 0 0 2px rgba(255,255,255,.28)}
h1{font-size:92px;font-weight:700;line-height:1;margin:10px 0 18px}
.desc{font-size:30px;line-height:1.35;color:rgba(255,255,255,.85);max-width:900px}
.ft{width:1080px;height:233px;background:linear-gradient(135deg,#04305f,#0a3a7a);display:flex;align-items:center;justify-content:space-between;padding:0 60px;border-top:2px solid rgba(245,166,35,.55);box-shadow:inset 0 3px 34px rgba(245,166,35,.2)}
.fl{display:flex;align-items:center;gap:20px}.fl img{height:54px}
.handle{font-size:32px;font-weight:700}.site{font-size:22px;color:#F7B733;margin-top:2px}
.pill{background:linear-gradient(135deg,#F7B733,#E8920A);color:#06203f;font-weight:700;font-size:30px;padding:18px 34px;border-radius:999px;box-shadow:0 0 24px rgba(245,166,35,.55)}</style>`;

const LOGO_URI = toDataUri(LOGO);
const logoTag = LOGO_URI ? `<img class="logo" src="${LOGO_URI}">` : '';
const footLogo = LOGO_URI ? `<img src="${LOGO_URI}">` : '';
const header = `<!doctype html><html><head>${base}</head><body><div class="hd"><div class="top">${logoTag}<div class="steps">${chips}</div></div><div class="mid"><div class="ey">${esc(NUMBER)}</div><h1>${esc(TITLE)}</h1><div class="desc">${esc(DESC)}</div></div></div></body></html>`;
const footer = `<!doctype html><html><head>${base}</head><body><div class="ft"><div class="fl">${footLogo}<div><div class="handle">${esc(HANDLE)}</div><div class="site">${esc(SITE)}</div></div></div><div class="pill">Geser →</div></div></body></html>`;

/**
 * CTA ask overlay (#3) — a transparent full-canvas (1080×1350) page with a
 * navy/gold ask card anchored in the bottom third (above the mobile dead zone).
 * Composited over the CTA Veo clip so Follow/Save/Comment is visible in-feed.
 * Deliberately NO comment→DM promise (no auto-DM infra). Pure → unit-testable.
 */
function buildCtaOverlayHtml(handle) {
  const h = esc(handle || '@alisadikinma');
  return `<!doctype html><html><head><style>${F}*{margin:0;padding:0;box-sizing:border-box;font-family:'Space Grotesk','Inter',sans-serif}
html,body{width:1080px;height:1350px;background:transparent}
.wrap{width:1080px;height:1350px;display:flex;align-items:flex-end;justify-content:center;padding:0 60px 170px}
.card{width:100%;background:linear-gradient(135deg,rgba(4,48,95,.95),rgba(10,58,122,.95));border:2px solid rgba(245,166,35,.6);border-radius:34px;padding:46px 54px;box-shadow:0 18px 60px rgba(0,0,0,.45),inset 0 3px 34px rgba(245,166,35,.18);color:#fff}
.cta-h{font-size:42px;font-weight:700;color:#F7B733;margin-bottom:26px;letter-spacing:.5px}
.row{display:flex;align-items:center;gap:20px;font-size:34px;font-weight:600;margin:16px 0;color:#fff}
.ic{display:inline-flex;align-items:center;justify-content:center;width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#F7B733,#E8920A);color:#06203f;font-size:32px;font-weight:700;flex:none}
.hl{color:#F7B733;font-weight:700}</style></head><body><div class="wrap"><div class="card">
<div class="cta-h">Found this useful?</div>
<div class="row"><span class="ic">+</span><span>Follow <span class="hl">${h}</span> for more AI tools</span></div>
<div class="row"><span class="ic">&#9662;</span><span>Save this so you don't lose it</span></div>
<div class="row"><span class="ic">&#10022;</span><span>Comment <span class="hl">&quot;AI&quot;</span> if it helped</span></div>
</div></div></body></html>`;
}

async function render() {
  if (MODE !== 'cta' && (!HEADER_OUT || !FOOTER_OUT)) {
    console.error('missing --header-out / --footer-out');
    process.exit(1);
  }
  if (MODE === 'cta' && !OVERLAY_OUT) {
    console.error('missing --overlay-out');
    process.exit(1);
  }
  // Lazy require so the module can be required as a library (tests) without
  // Playwright installed at the VPS path.
  const { chromium } = require('/var/www/Portfolio_v2/node_modules/playwright');
  const b = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--single-process', '--disable-dev-shm-usage'],
  });
  const p = await b.newPage();

  if (MODE === 'cta') {
    await p.setViewportSize({ width: 1080, height: 1350 });
    await p.setContent(buildCtaOverlayHtml(HANDLE), { waitUntil: 'networkidle' });
    try { await p.evaluate(() => document.fonts.ready); } catch (e) {}
    await p.waitForTimeout(400);
    // omitBackground keeps the PNG transparent → ffmpeg overlay shows only the card.
    await p.screenshot({ path: OVERLAY_OUT, omitBackground: true });
    await b.close();
    console.log('CHROME_OK');
    return;
  }

  await p.setViewportSize({ width: 1080, height: 508 });
  await p.setContent(header, { waitUntil: 'networkidle' });
  try { await p.evaluate(() => document.fonts.ready); } catch (e) {}
  await p.waitForTimeout(500);
  await p.screenshot({ path: HEADER_OUT });

  await p.setViewportSize({ width: 1080, height: 233 });
  await p.setContent(footer, { waitUntil: 'networkidle' });
  try { await p.evaluate(() => document.fonts.ready); } catch (e) {}
  await p.waitForTimeout(400);
  await p.screenshot({ path: FOOTER_OUT });

  await b.close();
  console.log('CHROME_OK');
}

module.exports = { toDataUri, buildCtaOverlayHtml };

if (require.main === module) {
  render().catch((e) => { console.error(e); process.exit(1); });
}
