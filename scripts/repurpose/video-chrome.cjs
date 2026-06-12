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
 *     --number 2 --logo file:///path/logo.png --handle @alisadikinma \
 *     --site alisadikinma.com --header-out /abs/header.png --footer-out /abs/footer.png
 *
 * stdout last line: CHROME_OK  (or a thrown error + non-zero exit)
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
'use strict';

const { chromium } = require('/var/www/Portfolio_v2/node_modules/playwright');

function arg(name, def) {
  const i = process.argv.indexOf('--' + name);
  return i > -1 && process.argv[i + 1] !== undefined ? process.argv[i + 1] : def;
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

const logoTag = LOGO ? `<img class="logo" src="${LOGO}">` : '';
const footLogo = LOGO ? `<img src="${LOGO}">` : '';
const header = `<!doctype html><html><head>${base}</head><body><div class="hd"><div class="top">${logoTag}<div class="steps">${chips}</div></div><div class="mid"><div class="ey">${esc(NUMBER)}</div><h1>${esc(TITLE)}</h1><div class="desc">${esc(DESC)}</div></div></div></body></html>`;
const footer = `<!doctype html><html><head>${base}</head><body><div class="ft"><div class="fl">${footLogo}<div><div class="handle">${esc(HANDLE)}</div><div class="site">${esc(SITE)}</div></div></div><div class="pill">Geser →</div></div></body></html>`;

(async () => {
  if (!HEADER_OUT || !FOOTER_OUT) {
    console.error('missing --header-out / --footer-out');
    process.exit(1);
  }
  const b = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--single-process', '--disable-dev-shm-usage'],
  });
  const p = await b.newPage();

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
})().catch((e) => { console.error(e); process.exit(1); });
