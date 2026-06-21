#!/usr/bin/env node
/**
 * ig-capture.cjs — deterministic Instagram post capture for the IG → repurpose
 * carousel pipeline (Phase B of docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md).
 *
 * Headless-Chromium, best-effort, NO agent loop (per Decision D2). Pulls a public
 * post's carousel slides + caption and writes them to a private out dir, then
 * emits a single JSON line to stdout for InstagramCaptureService to parse.
 *
 * Usage:
 *   node ig-capture.cjs --url <ig-url> --out <dir> [--storage-state <path>] [--timeout <sec>]
 *
 * Output (stdout, last line, JSON):
 *   { "ok": true, "count": 3, "slides": ["slide-01.jpg", ...], "caption": "..." }
 *   { "ok": false, "count": 0, "slides": [], "caption": "", "error": "login_wall" }
 *
 * Exit code: 0 on >=1 slide captured, 1 otherwise (or on hard error). The JSON
 * is still printed on failure so the caller gets a structured reason.
 *
 * Extraction strategy (ordered, best-effort — IG markup changes often):
 *   1. JSON-LD (<script type="application/ld+json">) → caption + image[] when it
 *      carries the full carousel (>= 2 images).
 *   2. else TRAVERSE the carousel: hover the media, click "Next" through every
 *      slide, collecting only full-res (naturalWidth >= 600) IG-CDN images. This
 *      is the normal path now — IG no longer serves JSON-LD carousel images to
 *      anonymous Chromium, and a flat <img> scrape both over-grabs (avatars +
 *      srcset thumbs + the "more posts" grid) and under-captures (lazy slides).
 *   3. else og:image single cover (lone-image post).
 * Image bytes are fetched THROUGH the browser context (same cookies/session) so
 * authenticated storageState works for wall-gated carousels.
 *
 * Feasibility note (Phase I runbook): the IG login wall can block anonymous
 * capture. When 0 slides are found the caller fails the job with a clear reason
 * and the operator is asked to paste/check — never a silent success.
 */
'use strict';

const fs = require('fs');
const path = require('path');

function parseArgs(argv) {
  const args = {};
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      const key = a.slice(2);
      const val = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : 'true';
      args[key] = val;
    }
  }
  return args;
}

function emit(obj) {
  process.stdout.write(JSON.stringify(obj) + '\n');
}

function isInstagramUrl(url) {
  try {
    const u = new URL(url);
    const host = u.hostname.toLowerCase();
    return (host === 'instagram.com' || host === 'www.instagram.com')
      && /^\/(p|reel|reels|tv)\/[A-Za-z0-9_-]+\/?$/.test(u.pathname);
  } catch {
    return false;
  }
}

function uniq(arr) {
  return Array.from(new Set(arr));
}

/** Pull caption + image urls from JSON-LD blocks if present. */
async function fromJsonLd(page) {
  const blocks = await page.$$eval(
    'script[type="application/ld+json"]',
    (nodes) => nodes.map((n) => n.textContent || '')
  );
  let caption = '';
  let images = [];
  for (const raw of blocks) {
    let data;
    try { data = JSON.parse(raw); } catch { continue; }
    const items = Array.isArray(data) ? data : [data];
    for (const it of items) {
      if (!it || typeof it !== 'object') continue;
      if (!caption && typeof it.caption === 'string') caption = it.caption;
      if (!caption && typeof it.articleBody === 'string') caption = it.articleBody;
      if (!caption && typeof it.description === 'string') caption = it.description;
      const img = it.image;
      if (typeof img === 'string') images.push(img);
      else if (Array.isArray(img)) images.push(...img.map((x) => (typeof x === 'string' ? x : x && x.url)).filter(Boolean));
      else if (img && typeof img === 'object' && img.url) images.push(img.url);
    }
  }
  return { caption, images: uniq(images.filter(Boolean)) };
}

/** Fallback: og:image + og:description meta tags. */
async function fromMeta(page) {
  const grab = async (prop) => {
    try {
      return await page.$eval(`meta[property="${prop}"]`, (el) => el.getAttribute('content') || '');
    } catch { return ''; }
  };
  const caption = await grab('og:description');
  const image = await grab('og:image');
  return { caption, images: image ? [image] : [] };
}

/**
 * Authoritative carousel extraction — TRAVERSE the carousel.
 *
 * IG lazy-loads carousel slides: at load only ~2 full-res slides exist in the
 * DOM; the rest load only when the "Next" arrow (hover-gated) is clicked. A flat
 * `<img>` scrape therefore over-grabs (srcset thumbnails + the profile avatar +
 * the "more posts" suggested grid) AND under-captures (never sees the lazy
 * slides). Instead: collect only large (naturalWidth >= 600) IG-CDN images —
 * which cleanly excludes ~150px avatars and ~480px grid cards — and click
 * through every slide until the arrow disappears (or no new image appears for
 * two consecutive steps, the loop-back guard). Insertion order ≈ slide order
 * (cover first). Returns the clean, ordered, deduped full-res slide URLs.
 */
async function collectCarouselImages(page) {
  const seen = new Map(); // path (query-stripped) -> full url
  const grab = async () => {
    const urls = await page.$$eval('img', (imgs) =>
      imgs
        .filter((i) => /cdninstagram\.com|fbcdn\.net/.test(i.src || '') && i.naturalWidth >= 600)
        .map((i) => i.src)
    );
    for (const u of urls) {
      const key = u.split('?')[0];
      if (!seen.has(key)) seen.set(key, u);
    }
  };

  await grab();
  let stale = 0;
  for (let k = 0; k < 14; k++) {
    const before = seen.size;
    // The Next/Prev arrows only appear while the pointer is over the media.
    try {
      await page.mouse.move(640, 760);
      await page.waitForTimeout(200);
    } catch {}
    const next =
      (await page.$('button[aria-label="Next"]')) ||
      (await page.$('button[aria-label*="ext"]')) ||
      (await page.$('[aria-label="Next"]'));
    if (!next) break;
    try {
      await next.click({ timeout: 3000, force: true });
    } catch {
      break;
    }
    await page.waitForTimeout(1100);
    await grab();
    if (seen.size === before) {
      if (++stale >= 2) break; // looped back to the start / no more slides
    } else {
      stale = 0;
    }
  }

  return Array.from(seen.values());
}

async function main() {
  const args = parseArgs(process.argv);
  const url = args.url || '';
  const outDir = args.out || '';
  const storageState = args['storage-state'] && args['storage-state'] !== 'true' ? args['storage-state'] : '';
  const timeoutSec = parseInt(args.timeout || '120', 10);

  if (!isInstagramUrl(url)) {
    emit({ ok: false, count: 0, slides: [], caption: '', error: 'invalid_url_host' });
    process.exit(1);
  }
  if (!outDir) {
    emit({ ok: false, count: 0, slides: [], caption: '', error: 'missing_out_dir' });
    process.exit(1);
  }
  fs.mkdirSync(outDir, { recursive: true });

  let chromium;
  try {
    ({ chromium } = require('playwright'));
  } catch (e) {
    emit({ ok: false, count: 0, slides: [], caption: '', error: 'playwright_not_installed' });
    process.exit(1);
  }

  let browser;
  try {
    browser = await chromium.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--single-process', '--disable-dev-shm-usage'],
    });
    const contextOpts = {
      userAgent:
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
      viewport: { width: 1280, height: 1600 },
    };
    if (storageState && fs.existsSync(storageState)) {
      contextOpts.storageState = storageState;
    }
    const context = await browser.newContext(contextOpts);
    const page = await context.newPage();

    await page.goto(url, { waitUntil: 'networkidle', timeout: timeoutSec * 1000 });
    // Give carousel media a beat to hydrate.
    await page.waitForTimeout(2500);

    // Caption: JSON-LD first, then og:description.
    const ld = await fromJsonLd(page);
    let caption = ld.caption;
    if (!caption) {
      const meta = await fromMeta(page);
      if (meta.caption) caption = meta.caption;
    }

    // Images (authoritative → fallback):
    //   1. JSON-LD image[] when it carries the full carousel (>= 2) — clean + cheap.
    //   2. else TRAVERSE the carousel (handles IG's lazy-load; the normal path now
    //      that IG no longer serves JSON-LD carousel images anonymously).
    //   3. else og:image single cover, so a lone-image post still yields 1 slide.
    let images = ld.images.length >= 2 ? ld.images : await collectCarouselImages(page);
    if (images.length === 0) {
      images = (await fromMeta(page)).images;
    }
    images = uniq(images.filter(Boolean));

    if (images.length === 0) {
      const blocked = await page.$('input[name="username"]');
      emit({ ok: false, count: 0, slides: [], caption, error: blocked ? 'login_wall' : 'no_slides_found' });
      await browser.close();
      process.exit(1);
    }

    // Download each image THROUGH the browser session (cookies preserved).
    const slides = [];
    for (let i = 0; i < images.length; i++) {
      try {
        const resp = await context.request.get(images[i], { timeout: 30000 });
        if (!resp.ok()) continue;
        const buf = await resp.body();
        if (!buf || buf.length < 1024) continue; // skip 1x1 pixels / broken
        const name = `slide-${String(slides.length + 1).padStart(2, '0')}.jpg`;
        fs.writeFileSync(path.join(outDir, name), buf);
        slides.push(name);
      } catch {
        // skip this slide, continue
      }
    }

    if (caption) {
      fs.writeFileSync(path.join(outDir, 'caption.txt'), caption, 'utf8');
    }

    await browser.close();

    if (slides.length === 0) {
      emit({ ok: false, count: 0, slides: [], caption, error: 'download_failed' });
      process.exit(1);
    }

    emit({ ok: true, count: slides.length, slides, caption });
    process.exit(0);
  } catch (e) {
    try { if (browser) await browser.close(); } catch {}
    emit({ ok: false, count: 0, slides: [], caption: '', error: 'capture_error: ' + (e && e.message ? e.message : String(e)) });
    process.exit(1);
  }
}

main();
