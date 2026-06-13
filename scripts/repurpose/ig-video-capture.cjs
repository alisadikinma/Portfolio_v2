#!/usr/bin/env node
/**
 * video_rebrand Phase B — capture a source Instagram VIDEO carousel headless
 * and emit one JSON line describing each video slide.
 *
 * Consumed by App\Services\VideoCarouselCaptureService (ssh|local exec).
 *
 * CAPTURE METHOD: Playwright (headless browser), NOT yt-dlp (June 13, 2026).
 * yt-dlp hits IG's internal API which IG rate-limits hard and flags the session
 * even WITH cookies — a known dead-end (it succeeds once on a fresh account then
 * throttles for hours). The headless browser renders the real page like a human,
 * intercepts the video CDN responses, and downloads them THROUGH the browser
 * context (cookies preserved) — the same resilient pattern the image-carousel
 * path (scripts/playwright/ig-capture.cjs) uses and which is NOT API-throttled.
 *
 * Flow: load post (+ Netscape cookies) → listen for cdninstagram video responses
 * → swipe through every carousel slide so each video hydrates → download each
 * unique video via context.request → ffmpeg poster + center 16:9 luminance
 * band-detect (unchanged from the old yt-dlp version).
 *
 *   node ig-video-capture.cjs --url <ig-url> --out <dir> \
 *        [--timeout 300] [--ffmpeg ffmpeg] [--ffprobe ffprobe] [--cookies <netscape.txt>]
 *
 * stdout (last line): {"ok":true,"count":N,"slides":[{file,poster,width,height,duration,has_audio,crop_y,crop_h}],"error":null}
 * On failure: {"ok":false,"count":0,"slides":[],"error":"<reason>"}  (login_wall | no_video_items | playwright_not_installed | capture_error: ...)
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
'use strict';

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function arg(name, def) {
  const i = process.argv.indexOf('--' + name);
  return i > -1 && process.argv[i + 1] ? process.argv[i + 1] : def;
}

function emit(obj) {
  process.stdout.write(JSON.stringify(obj) + '\n');
}

const url = arg('url', '');
const outDir = arg('out', '');
const timeoutMs = parseInt(arg('timeout', '300'), 10) * 1000;
const FFMPEG = arg('ffmpeg', 'ffmpeg');
const FFPROBE = arg('ffprobe', 'ffprobe');
// Netscape cookies.txt exported from a logged-in IG browser session. Passed to
// the Playwright context so wall-gated / full carousels are reachable. Empty =
// anonymous (public posts mostly work; private/wall → login_wall).
const COOKIES = arg('cookies', '');

if (!/^https?:\/\/(www\.)?instagram\.com\/(p|reel|reels|tv)\/[A-Za-z0-9_-]+/i.test(url)) {
  emit({ ok: false, count: 0, slides: [], error: 'invalid_url_host' });
  process.exit(0);
}
if (!outDir) {
  emit({ ok: false, count: 0, slides: [], error: 'no_out_dir' });
  process.exit(0);
}

function loadPlaywright() {
  for (const p of ['/var/www/Portfolio_v2/node_modules/playwright', 'playwright']) {
    try { return require(p); } catch (e) { /* try next */ }
  }
  return null;
}

/** Netscape cookies.txt → Playwright addCookies objects (scoped to .instagram.com). */
function parseCookies(file) {
  if (!file || !fs.existsSync(file)) return [];
  return fs
    .readFileSync(file, 'utf8')
    .split('\n')
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const p = l.split('\t');
      return { name: p[5], value: p[6], domain: '.instagram.com', path: '/', secure: true };
    })
    .filter((c) => c.name && c.value);
}

function run(bin, args, opts) {
  return execFileSync(bin, args, { timeout: timeoutMs, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], ...(opts || {}) });
}

function evenInt(n) {
  n = Math.round(n);
  return n % 2 === 0 ? n : n - 1;
}

/**
 * Deterministic center 16:9 band detection (POC-proven row-luminance method).
 * Dumps one luminance value per row, classifies rows dark(<110)/light, and takes
 * the largest contiguous dark run as the center demo video. Falls back to a
 * proportional centered 16:9 band when the detected run is implausible.
 */
function detectBand(file, width, height, ss) {
  const expected = (width * 9) / 16;
  const fallback = () => {
    const h = evenInt(Math.min(expected, height));
    return { crop_y: evenInt(Math.max(0, (height - h) / 2)), crop_h: h };
  };
  if (!width || !height) return fallback();
  try {
    const buf = execFileSync(
      FFMPEG,
      ['-ss', String(ss), '-i', file, '-frames:v', '1', '-vf', `scale=1:${height},format=gray`, '-f', 'rawvideo', '-'],
      { timeout: timeoutMs, maxBuffer: 1 << 20 }
    );
    let bestStart = -1, bestLen = 0, curStart = -1, curLen = 0;
    for (let i = 0; i < buf.length; i++) {
      if (buf[i] < 110) {
        if (curStart < 0) curStart = i;
        curLen++;
        if (curLen > bestLen) { bestLen = curLen; bestStart = curStart; }
      } else {
        curStart = -1; curLen = 0;
      }
    }
    if (bestStart < 0) return fallback();
    if (bestLen < expected * 0.6 || bestLen > expected * 1.4) return fallback();
    return { crop_y: evenInt(bestStart), crop_h: evenInt(bestLen) };
  } catch (e) {
    return fallback();
  }
}

function probe(file) {
  try {
    const out = run(FFPROBE, ['-v', 'quiet', '-print_format', 'json', '-show_streams', '-show_format', file]);
    const j = JSON.parse(out);
    const streams = j.streams || [];
    const v = streams.find((s) => s.codec_type === 'video');
    const a = streams.find((s) => s.codec_type === 'audio');
    if (!v) return null; // not a video slide (image-only item) — skip
    return {
      width: v.width || 0,
      height: v.height || 0,
      duration: parseFloat((j.format && j.format.duration) || v.duration || '0') || 0,
      has_audio: !!a,
    };
  } catch (e) {
    return null;
  }
}

/** Stable key for a signed IG video URL: the long media-id segments in the path
 *  (query params + CDN host vary per request, the /o1/v/.../<id> path is stable). */
function videoKey(u) {
  const noQ = u.split('?')[0];
  const segs = (noQ.match(/\/[A-Za-z0-9_-]{16,}/g) || []).join('');
  return segs || noQ;
}

function isVideoResponse(u, ct) {
  if (ct && ct.toLowerCase().includes('video/')) return true;
  return /cdninstagram\.com\/(o1\/)?v\//i.test(u) || /\.mp4(\?|$)/i.test(u);
}

async function capturePlaywright() {
  const pw = loadPlaywright();
  if (!pw) return { error: 'playwright_not_installed' };

  const browser = await pw.chromium.launch({ headless: true });
  try {
    const ctx = await browser.newContext({
      userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    });
    const cks = parseCookies(COOKIES);
    if (cks.length) await ctx.addCookies(cks);
    const page = await ctx.newPage();

    // Collect cdninstagram video response URLs in first-seen (≈ carousel) order.
    const seen = new Map(); // key -> url
    page.on('response', (resp) => {
      try {
        const u = resp.url();
        const ct = resp.headers()['content-type'] || '';
        if (isVideoResponse(u, ct)) {
          const k = videoKey(u);
          if (!seen.has(k)) seen.set(k, u);
        }
      } catch (e) { /* ignore */ }
    });

    await page.goto(url, { waitUntil: 'load', timeout: Math.min(60000, timeoutMs) });
    await page.waitForTimeout(5000);

    const title = (await page.title()) || '';
    const hasLogin = (await page.$('input[name="username"]')) !== null;
    if (seen.size === 0 && (hasLogin || /log\s?in/i.test(title))) {
      await browser.close();
      return { error: 'login_wall' };
    }

    // Swipe through the carousel so every slide's video hydrates → response fires.
    for (let i = 0; i < 20; i++) {
      const next = await page.$('button[aria-label="Next"], [aria-label="Next"]');
      if (!next) break;
      try { await next.click({ timeout: 3000 }); } catch (e) { break; }
      await page.waitForTimeout(2500);
    }
    await page.waitForTimeout(1500);

    // Download each unique video via the browser context (cookies/session kept).
    const files = [];
    const urls = [...seen.values()];
    for (let i = 0; i < urls.length; i++) {
      try {
        const resp = await ctx.request.get(urls[i], { timeout: 60000, headers: { Referer: 'https://www.instagram.com/' } });
        if (!resp.ok()) continue;
        const buf = await resp.body();
        if (!buf || buf.length < 10000) continue; // skip tiny/non-video payloads
        const name = 'slide_' + (files.length + 1) + '.mp4';
        fs.writeFileSync(path.join(outDir, name), buf);
        files.push(name);
      } catch (e) { /* skip this url */ }
    }

    await browser.close();
    return { error: null, files };
  } catch (e) {
    try { await browser.close(); } catch (_) { /* ignore */ }
    return { error: 'capture_error: ' + (e && e.message ? e.message : String(e)) };
  }
}

(async () => {
  try {
    fs.mkdirSync(outDir, { recursive: true });

    const cap = await capturePlaywright();
    if (cap.error) {
      emit({ ok: false, count: 0, slides: [], error: cap.error });
      process.exit(0);
    }

    const files = (cap.files || []).sort((a, b) => {
      const na = parseInt((a.match(/slide_(\d+)/) || [])[1] || '0', 10);
      const nb = parseInt((b.match(/slide_(\d+)/) || [])[1] || '0', 10);
      return na - nb;
    });

    const slides = [];
    for (const f of files) {
      const full = path.join(outDir, f);
      const meta = probe(full);
      if (!meta) continue; // skip non-video / corrupt items

      const base = f.replace(/\.[^.]+$/, '');
      const posterName = base + '.jpg';
      const posterFull = path.join(outDir, posterName);
      const ss = meta.duration > 2 ? 2 : Math.max(0, meta.duration / 2);
      try {
        run(FFMPEG, ['-y', '-ss', String(ss), '-i', full, '-frames:v', '1', '-q:v', '3', posterFull]);
      } catch (e) { /* poster best-effort */ }

      const band = detectBand(full, meta.width, meta.height, ss);

      slides.push({
        file: f,
        poster: fs.existsSync(posterFull) ? posterName : '',
        width: meta.width,
        height: meta.height,
        duration: meta.duration,
        has_audio: meta.has_audio,
        crop_y: band.crop_y,
        crop_h: band.crop_h,
      });
    }

    if (slides.length < 1) {
      emit({ ok: false, count: 0, slides: [], error: 'no_video_items' });
      process.exit(0);
    }

    emit({ ok: true, count: slides.length, slides, error: null });
  } catch (e) {
    emit({ ok: false, count: 0, slides: [], error: 'capture_error: ' + (e && e.message ? e.message : String(e)) });
  }
})();
