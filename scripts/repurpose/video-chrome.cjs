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
// English companion line under the Indonesian-primary DESC (bilingual chrome).
const SUBTITLE = arg('subtitle', '');
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

const F = `@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;600&display=swap');`;

// Inline social/web SVG glyphs (origin-independent — no external requests, unlike
// the old file:// logo that silently failed under page.setContent). White glyphs
// read on the navy footer; the globe is gold to pair with the site URL.
const IG_SVG = `<svg viewBox="0 0 24 24" width="46" height="46" fill="none" stroke="#fff" stroke-width="2"><rect x="2.5" y="2.5" width="19" height="19" rx="5.5"/><circle cx="12" cy="12" r="4.6"/><circle cx="17.4" cy="6.6" r="1.3" fill="#fff" stroke="none"/></svg>`;
const TT_SVG = `<svg viewBox="0 0 24 24" width="46" height="46" fill="#fff"><path d="M16.5 3c.4 2.2 1.8 3.7 3.9 3.9v2.7c-1.3.1-2.6-.3-3.8-1.1v5.9c0 3.4-2.7 5.8-6 5.3-2.9-.4-4.6-3.1-3.9-6 .5-2.3 2.8-3.9 5.2-3.6v2.8c-.5-.1-.9-.1-1.4 0-1 .3-1.6 1.2-1.4 2.3.2 1 1.2 1.7 2.3 1.5 1-.2 1.6-1.1 1.6-2.2V3h3.5z"/></svg>`;
const LI_SVG = `<svg viewBox="0 0 24 24" width="46" height="46" fill="#fff"><path d="M4.98 3.5a2 2 0 100 4 2 2 0 000-4zM3.2 9h3.6v12H3.2zM9.2 9h3.5v1.7h.1c.5-.9 1.8-1.9 3.7-1.9 3.9 0 4.7 2.5 4.7 5.8V21h-3.7v-5.2c0-1.3 0-2.9-1.8-2.9s-2 1.4-2 2.8V21H9.2z"/></svg>`;
const WEB_SVG = `<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#F7B733" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.7 2.6 15.3 0 18M12 3c-2.6 2.7-2.6 15.3 0 18"/></svg>`;

const STYLE = `<style>${F}*{margin:0;padding:0;box-sizing:border-box;font-family:'Space Grotesk','Inter',sans-serif;color:#fff}
.hd{width:1080px;height:508px;background:linear-gradient(135deg,#1466C4,#0a3a7a 55%,#04498f);position:relative;overflow:hidden;padding:52px 60px;display:flex;flex-direction:column}
.hd::after{content:'';position:absolute;right:-120px;top:-120px;width:380px;height:380px;background:radial-gradient(circle,rgba(245,166,35,.28),transparent 70%)}
.top{display:flex;justify-content:flex-end;align-items:center;z-index:2}
.steps{display:flex;gap:16px}
.chip{width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:31px}
.chip.on{background:linear-gradient(135deg,#F7B733,#E8920A);color:#06203f;box-shadow:0 0 22px rgba(245,166,35,.65),inset 0 0 0 2px rgba(255,255,255,.25)}
.chip.off{background:rgba(255,255,255,.07);color:rgba(255,255,255,.38);box-shadow:inset 0 0 0 2px rgba(255,255,255,.16)}
.mid{margin-top:auto;z-index:2}
.ey{display:inline-flex;align-items:center;justify-content:center;width:100px;height:100px;border-radius:26px;background:linear-gradient(135deg,#F7B733,#E8920A);color:#06203f;font-size:60px;font-weight:700;box-shadow:0 0 26px rgba(245,166,35,.55),inset 0 0 0 2px rgba(255,255,255,.28)}
h1{font-size:88px;font-weight:700;line-height:1;margin:8px 0 14px}
.desc{font-size:33px;font-weight:600;line-height:1.3;color:#fff;max-width:940px}
.sub{font-size:26px;font-weight:400;line-height:1.3;color:#F7B733;max-width:940px;margin-top:8px}
.ft{width:1080px;height:233px;background:linear-gradient(135deg,#04305f,#0a3a7a);display:flex;align-items:center;justify-content:space-between;padding:0 56px;border-top:2px solid rgba(245,166,35,.55);box-shadow:inset 0 3px 34px rgba(245,166,35,.2)}
.fl{display:flex;align-items:center;gap:24px}
.favatar{height:88px;width:88px;border-radius:20px;object-fit:cover}
.fcol{display:flex;flex-direction:column;gap:10px}
.frow{display:flex;align-items:center;gap:16px}
.handle{font-size:34px;font-weight:700;margin-left:4px}
.site{font-size:26px;font-weight:600;color:#F7B733}
.pill{background:linear-gradient(135deg,#F7B733,#E8920A);color:#06203f;font-weight:700;font-size:30px;padding:18px 34px;border-radius:999px;box-shadow:0 0 24px rgba(245,166,35,.55);white-space:nowrap}</style>`;

/**
 * Header (1080×508): cumulative numbered stepper top-RIGHT (the creator avatar
 * was removed from the top-left — the brand now lives only in the footer), a big
 * gold number badge, the tool TITLE (proper noun, unchanged), the Indonesian
 * DESC (primary, white) and the English SUBTITLE companion (gold). Pure →
 * unit-testable without Chromium.
 */
function buildHeaderHtml({ title = '', desc = '', subtitle = '', active = 1, total = 1, number = '1' } = {}) {
  const chips = Array.from(
    { length: total },
    (_, i) => `<div class="chip ${i < active ? 'on' : 'off'}">${i + 1}</div>`
  ).join('');
  const subTag = subtitle ? `<div class="sub">${esc(subtitle)}</div>` : '';
  const descTag = desc ? `<div class="desc">${esc(desc)}</div>` : '';
  return `<!doctype html><html><head>${STYLE}</head><body><div class="hd"><div class="top"><div class="steps">${chips}</div></div><div class="mid"><div class="ey">${esc(number)}</div><h1>${esc(title)}</h1>${descTag}${subTag}</div></div></body></html>`;
}

/**
 * Footer (1080×233): creator avatar + two brand rows — IG/TikTok/LinkedIn glyphs
 * with @handle, then a globe with the site — plus the gold "Geser →" swipe pill.
 * Pure → unit-testable without Chromium.
 */
function buildFooterHtml({ logoUri = '', handle = '@alisadikinma', site = 'alisadikinma.com' } = {}) {
  const avatar = logoUri ? `<img class="favatar" src="${logoUri}">` : '';
  return `<!doctype html><html><head>${STYLE}</head><body><div class="ft"><div class="fl">${avatar}<div class="fcol"><div class="frow">${IG_SVG}${TT_SVG}${LI_SVG}<span class="handle">${esc(handle)}</span></div><div class="frow">${WEB_SVG}<span class="site">${esc(site)}</span></div></div></div><div class="pill">Geser (Swipe) →</div></div></body></html>`;
}

const header = buildHeaderHtml({ title: TITLE, desc: DESC, subtitle: SUBTITLE, active: ACTIVE, total: TOTAL, number: NUMBER });
const footer = buildFooterHtml({ logoUri: toDataUri(LOGO), handle: HANDLE, site: SITE });

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

module.exports = { toDataUri, buildCtaOverlayHtml, buildHeaderHtml, buildFooterHtml };

if (require.main === module) {
  render().catch((e) => { console.error(e); process.exit(1); });
}
