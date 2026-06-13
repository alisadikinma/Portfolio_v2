#!/usr/bin/env node
/**
 * video_rebrand Phase B — download a source Instagram VIDEO carousel headless
 * (no login) and emit one JSON line describing each video slide.
 *
 * Consumed by App\Services\VideoCarouselCaptureService (ssh|local exec). POC
 * validated yt-dlp pulls all carousel items without cookies. We download each
 * item, keep only those with a video stream, extract a poster frame, and probe
 * dimensions/duration/audio.
 *
 *   node ig-video-capture.cjs --url <ig-url> --out <dir> \
 *        [--timeout 300] [--ytdlp yt-dlp] [--ffmpeg ffmpeg] [--ffprobe ffprobe]
 *
 * stdout (last line): {"ok":true,"count":N,"slides":[{file,poster,width,height,duration,has_audio}],"error":null}
 * On any failure: {"ok":false,"count":0,"slides":[],"error":"<reason>"}
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
const timeout = parseInt(arg('timeout', '300'), 10) * 1000;
const YTDLP = arg('ytdlp', 'yt-dlp');
const FFMPEG = arg('ffmpeg', 'ffmpeg');
const FFPROBE = arg('ffprobe', 'ffprobe');
// IG now requires auth for media download via yt-dlp's API path (anonymous gets
// metadata but the media bytes return "login required / rate-limit"). A Netscape
// cookies.txt (exported from a logged-in browser) unlocks it. Empty = anonymous.
const COOKIES = arg('cookies', '');

if (!/^https?:\/\/(www\.)?instagram\.com\/(p|reel|reels|tv)\/[A-Za-z0-9_-]+/i.test(url)) {
  emit({ ok: false, count: 0, slides: [], error: 'invalid_url_host' });
  process.exit(0);
}
if (!outDir) {
  emit({ ok: false, count: 0, slides: [], error: 'no_out_dir' });
  process.exit(0);
}

function run(bin, args, opts) {
  return execFileSync(bin, args, { timeout, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], ...(opts || {}) });
}

function evenInt(n) {
  n = Math.round(n);
  return n % 2 === 0 ? n : n - 1;
}

/**
 * Deterministic center 16:9 band detection (POC-proven row-luminance method).
 * Dumps one luminance value per row (scale width→1 averages each row), classifies
 * rows dark(<110)/light, and takes the largest contiguous dark run as the center
 * demo video. Falls back to a proportional centered 16:9 band when the detected
 * run is implausible (not ~16:9 of width).
 */
function detectBand(file, width, height, ss) {
  const expected = (width * 9) / 16; // center demo is a 16:9 region of the slide width
  const fallback = () => {
    const h = evenInt(Math.min(expected, height));
    return { crop_y: evenInt(Math.max(0, (height - h) / 2)), crop_h: h };
  };
  if (!width || !height) return fallback();
  try {
    const buf = execFileSync(
      FFMPEG,
      ['-ss', String(ss), '-i', file, '-frames:v', '1', '-vf', `scale=1:${height},format=gray`, '-f', 'rawvideo', '-'],
      { timeout, maxBuffer: 1 << 20 }
    );
    // buf[i] = luminance of row i (0..height-1)
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
    // Plausibility: the dark run should be roughly a 16:9 band (0.6×–1.4× expected).
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

try {
  fs.mkdirSync(outDir, { recursive: true });

  // Download every carousel item as slide_<playlist_index>.<ext>. yt-dlp treats
  // an IG carousel as a playlist; --ignore-errors keeps going if one item is an
  // image (no video) and can't be fetched as media.
  run(YTDLP, [
    ...(COOKIES ? ['--cookies', COOKIES] : []),
    '--no-warnings',
    '--ignore-errors',
    '--no-part',
    // Be gentle with IG so a normal run doesn't trip the rate-limiter: retry
    // transient failures with backoff, pause briefly between item requests.
    '--retries', '5',
    '--extractor-retries', '3',
    '--retry-sleep', '5',
    '--sleep-requests', '2',
    '-o', path.join(outDir, 'slide_%(playlist_index)s.%(ext)s'),
    url,
  ]);

  // Collect downloaded media, ordered by the numeric slide index in the name.
  const files = fs
    .readdirSync(outDir)
    .filter((f) => /^slide_\d+\./i.test(f) && !/\.jpg$/i.test(f))
    .sort((a, b) => {
      const na = parseInt((a.match(/slide_(\d+)/) || [])[1] || '0', 10);
      const nb = parseInt((b.match(/slide_(\d+)/) || [])[1] || '0', 10);
      return na - nb;
    });

  const slides = [];
  for (const f of files) {
    const full = path.join(outDir, f);
    const meta = probe(full);
    if (!meta) continue; // skip non-video items

    const base = f.replace(/\.[^.]+$/, '');
    const posterName = base + '.jpg';
    const posterFull = path.join(outDir, posterName);
    const ss = meta.duration > 2 ? 2 : Math.max(0, meta.duration / 2);
    try {
      run(FFMPEG, ['-y', '-ss', String(ss), '-i', full, '-frames:v', '1', '-q:v', '3', posterFull]);
    } catch (e) {
      // poster is best-effort; a slide without one still composites (vision falls back)
    }

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
