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
 * throttles for hours). The headless browser renders the real page like a human
 * and reads the progressive video URLs straight out of the page's embedded JSON
 * — the same resilient pattern the image-carousel path uses, NOT API-throttled.
 *
 * Flow: load post (+ Netscape cookies) → read `video_versions[].url` (highest-
 * bitrate progressive MP4 per slide) from the rendered data-sjs JSON → download
 * each via context.request → ffmpeg poster + center 16:9 luminance band-detect.
 *
 * Do NOT intercept the playing <video>: IG streams carousel videos as MPEG-DASH
 * media segments (tiny unplayable chunks); only the embedded progressive_url
 * gives a complete file with audio. (Diagnosed + verified live on VPS Jun 13.)
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

/** Unescape the backslash-escaped URLs embedded in IG's data-sjs JSON blocks. */
function unescapeUrl(s) {
  return s.replace(/\\\//g, '/').replace(/\\u0026/g, '&').replace(/\\u003D/gi, '=');
}

/**
 * Extract one FULL-PROGRESSIVE video URL per carousel slide from the rendered
 * page HTML. IG embeds a `"video_versions":[{type,width,height,url}, ...]` array
 * per video item in its server-rendered data-sjs JSON; the FIRST entry is the
 * highest-quality progressive MP4. This is deterministic (N arrays = N video
 * slides) and yields complete files WITH audio.
 *
 * Why not intercept the playing <video>? IG streams carousel videos as MPEG-DASH
 * media segments (many tiny `video/mp4` 200 responses); re-downloading a single
 * intercepted segment gives an unplayable ~20-130KB chunk (duration 0, no audio).
 * Only a fully-buffered slide ever surfaces as one progressive file. The embedded
 * progressive_url is the reliable source. (Verified live on VPS, June 13 2026.)
 */
function extractProgressiveUrls(html) {
  const urls = [];
  let i = 0;
  while ((i = html.indexOf('"video_versions":[', i)) >= 0) {
    const seg = html.slice(i, i + 4000);
    const m = seg.match(/"url":"(https:[^"]+?\.mp4[^"]*?)"/);
    if (m) urls.push(unescapeUrl(m[1]));
    i += 18;
  }
  return [...new Set(urls)];
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

    await page.goto(url, { waitUntil: 'load', timeout: Math.min(60000, timeoutMs) });
    await page.waitForTimeout(6000);

    // Primary source: progressive video_versions URLs in the rendered data-sjs JSON.
    let urls = extractProgressiveUrls(await page.content());

    // Defensive: a lazy-hydrated carousel may surface extra items only after a
    // swipe. If the first read looks short, swipe through and union the results.
    if (urls.length < 2) {
      for (let i = 0; i < 15; i++) {
        const next = await page.$('button[aria-label="Next"], [aria-label="Next"]');
        if (!next) break;
        try { await next.click({ timeout: 3000 }); } catch (e) { break; }
        await page.waitForTimeout(1800);
      }
      urls = [...new Set([...urls, ...extractProgressiveUrls(await page.content())])];
    }

    if (urls.length === 0) {
      const title = (await page.title()) || '';
      const hasLogin = (await page.$('input[name="username"]')) !== null;
      await browser.close();
      return { error: (hasLogin || /log\s?in/i.test(title)) ? 'login_wall' : 'no_video_items' };
    }

    // Download each full progressive video via the browser context (cookies kept).
    // URLs are in document order = carousel slide order → slide_1..slide_N.
    const files = [];
    for (let i = 0; i < urls.length; i++) {
      try {
        const resp = await ctx.request.get(urls[i], { timeout: 90000, headers: { Referer: 'https://www.instagram.com/' } });
        if (!resp.ok()) continue;
        const buf = await resp.body();
        if (!buf || buf.length < 50000) continue; // full slide videos are >0.5MB; skip stragglers
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
