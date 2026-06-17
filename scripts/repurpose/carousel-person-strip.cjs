#!/usr/bin/env node
/**
 * carousel-person-strip.cjs — people_spotlight composite (2026-06-17).
 *
 * Composites REAL cropped founder/people face cut-outs into the reserved photo
 * band of an already-rendered carousel slide PNG, with hand-drawn "pinned
 * polaroid" frames + role captions. The plugin (people_spotlight) reserved the
 * band in the image_prompt; CarouselPersonPhotoEnricher cropped the real photos;
 * this step drops them in.
 *
 * One-shot composite: the base slide is the full-bleed background and the faces
 * are framed in the band — a single screenshot yields the final PNG (no PHP-side
 * compositing). Both the base and each face are inlined as `data:` URIs because
 * Playwright `page.setContent()` runs in an opaque origin that blocks `file://`
 * (the same gotcha video-chrome.cjs documents). `buildStripHtml` + `toDataUri`
 * are exported for unit tests without launching Chromium.
 *
 * Usage:
 *   node carousel-person-strip.cjs --base /abs/slide.png --width 1080 --height 1350 \
 *        --faces '[{"path":"/abs/face-01.png","name":"Ashish Vaswani","role":"lead author"}]' \
 *        --band-y 0.12 --band-h 0.26 --out /abs/out.png
 */
'use strict';

const fs = require('fs');

function arg(name, def) {
  const i = process.argv.indexOf('--' + name);
  return i > -1 && process.argv[i + 1] !== undefined ? process.argv[i + 1] : def;
}

/** Read a local image file → data: URI. Returns '' on any failure. */
function toDataUri(p) {
  try {
    if (!p || !fs.existsSync(p)) return '';
    const buf = fs.readFileSync(p);
    const ext = (p.split('.').pop() || '').toLowerCase();
    const mime = ext === 'jpg' || ext === 'jpeg' ? 'image/jpeg' : ext === 'webp' ? 'image/webp' : 'image/png';
    return `data:${mime};base64,${buf.toString('base64')}`;
  } catch (_e) {
    return '';
  }
}

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * Build the full-page HTML: base slide as background + a row of framed face
 * cut-outs in the reserved band.
 *
 * @param {{baseUri:string,width:number,height:number,faces:Array<{uri:string,name?:string,role?:string}>,band:{y:number,h:number}}} o
 */
function buildStripHtml(o = {}) {
  const width = Number(o.width) || 1080;
  const height = Number(o.height) || 1350;
  const faces = Array.isArray(o.faces) ? o.faces.filter((f) => f && f.uri) : [];
  const band = o.band || {};
  const bandY = Math.max(0, Math.min(1, Number(band.y) || 0.12));
  const bandH = Math.max(0.05, Math.min(0.6, Number(band.h) || 0.26));

  const topPx = Math.round(bandY * height);
  const bandPx = Math.round(bandH * height);
  // Slight alternating tilt for the pinned-polaroid feel.
  const tilts = [-4, 3, -3, 4, -2, 2];

  const cards = faces
    .map((f, i) => {
      const caption = escapeHtml(f.role || f.name || '');
      const tilt = tilts[i % tilts.length];
      return `
      <figure class="pf" style="transform: rotate(${tilt}deg);">
        <div class="pf-photo"><img src="${f.uri}" alt=""/></div>
        ${caption ? `<figcaption class="pf-cap">${caption}</figcaption>` : ''}
      </figure>`;
    })
    .join('');

  return `<!DOCTYPE html><html><head><meta charset="utf-8"/>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  html,body { width:${width}px; height:${height}px; overflow:hidden; }
  .base { position:absolute; inset:0; width:${width}px; height:${height}px; object-fit:cover; }
  .band {
    position:absolute; left:0; right:0; top:${topPx}px; height:${bandPx}px;
    display:flex; align-items:center; justify-content:center; gap:${Math.round(width * 0.03)}px;
    padding:0 ${Math.round(width * 0.06)}px;
  }
  .pf { display:flex; flex-direction:column; align-items:center; gap:8px; }
  .pf-photo {
    background:#fffdf7; padding:8px 8px 14px; border-radius:6px;
    box-shadow:0 10px 22px rgba(0,0,0,0.40); border:1px solid rgba(0,0,0,0.08);
  }
  .pf-photo img {
    display:block; width:${Math.round(bandPx * 0.62)}px; height:${Math.round(bandPx * 0.62)}px;
    object-fit:cover; border-radius:3px;
  }
  .pf-cap {
    font-family:'Caveat','Comic Sans MS',cursive,sans-serif; font-size:${Math.round(bandPx * 0.10)}px;
    color:#fffdf7; font-weight:700; text-shadow:0 2px 6px rgba(0,0,0,0.6);
    max-width:${Math.round(width * 0.22)}px; text-align:center; line-height:1.1;
  }
</style></head>
<body>
  ${o.baseUri ? `<img class="base" src="${o.baseUri}" alt=""/>` : ''}
  <div class="band">${cards}</div>
</body></html>`;
}

async function main() {
  const BASE = arg('base', '');
  const WIDTH = parseInt(arg('width', '1080'), 10);
  const HEIGHT = parseInt(arg('height', '1350'), 10);
  const OUT = arg('out', '');
  const BAND_Y = parseFloat(arg('band-y', '0.12'));
  const BAND_H = parseFloat(arg('band-h', '0.26'));
  let faces = [];
  try {
    faces = JSON.parse(arg('faces', '[]'));
  } catch (_e) {
    faces = [];
  }

  if (!BASE || !OUT) {
    console.error('carousel-person-strip: --base and --out are required');
    process.exit(2);
  }

  const faceUris = (Array.isArray(faces) ? faces : [])
    .map((f) => ({ uri: toDataUri(f.path), name: f.name, role: f.role }))
    .filter((f) => f.uri);

  const html = buildStripHtml({
    baseUri: toDataUri(BASE),
    width: WIDTH,
    height: HEIGHT,
    faces: faceUris,
    band: { y: BAND_Y, h: BAND_H },
  });

  const { chromium } = require('/var/www/Portfolio_v2/node_modules/playwright');
  const b = await chromium.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  try {
    const p = await b.newPage({ viewport: { width: WIDTH, height: HEIGHT }, deviceScaleFactor: 1 });
    await p.setContent(html, { waitUntil: 'networkidle' });
    await p.screenshot({ path: OUT });
  } finally {
    await b.close();
  }
}

if (require.main === module) {
  main().catch((e) => {
    console.error(e && e.stack ? e.stack : String(e));
    process.exit(1);
  });
}

module.exports = { toDataUri, buildStripHtml, escapeHtml };
