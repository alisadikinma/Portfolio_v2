import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { run } from './lib/run.js';

/**
 * Download a source Instagram reel to {outDir}/source.mp4 via yt-dlp (headless,
 * no login — proven path, see vault ig-video-carousel-rebrand-poc).
 * Returns the absolute mp4 path.
 */
export async function downloadReel(url, outDir, { ytDlpBin = 'yt-dlp' } = {}) {
  if (!/^https?:\/\/(www\.)?instagram\.com\//.test(url)) {
    throw new Error(`refusing to download non-Instagram URL: ${url}`);
  }
  await mkdir(outDir, { recursive: true });
  const out = join(outDir, 'source.mp4');
  await run(ytDlpBin, [
    '--no-playlist',
    '--quiet',
    '--no-warnings',
    // Prefer a single progressive mp4 so downstream ffmpeg has one clean input.
    '-f', 'best[ext=mp4]/mp4/best',
    '--merge-output-format', 'mp4',
    '-o', out,
    url,
  ]);
  return out;
}
