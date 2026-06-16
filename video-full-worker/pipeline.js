import { join } from 'node:path';
import { downloadReel } from './capture.js';
import { transcribe } from './asr.js';
import { detectScenes, classifySpans } from './segment.js';
import { translateSpans } from './translate.js';
import { buildSegmentManifest } from './lib/manifest.js';
import { config } from './config.js';

/**
 * Phase A end-to-end: source reel → ordered segment manifest.
 * Real integrations throughout (yt-dlp, whisper.cpp, ffmpeg, claude CLI).
 *
 * Two manifest passes: the first (no classify/translate) gives the spans with
 * timing + per-span English text needed to drive classify+translate; the second
 * assembles the final manifest with those results merged in.
 */
export async function buildManifestForReel(url, opts = {}) {
  const jobId = opts.jobId || `local-${Date.now()}`;
  const workDir = join(opts.workRoot || config.workRoot, String(jobId));
  const whisperModel = opts.whisperModel || config.whisperModel;
  const progress = opts.onProgress || (() => {});

  progress('capture', 5);
  const mp4 = await downloadReel(url, workDir, { ytDlpBin: config.bins.ytDlp });

  progress('asr', 25);
  const { words } = await transcribe(mp4, {
    model: whisperModel, whisperBin: config.bins.whisper, ffmpegBin: config.bins.ffmpeg,
  });

  progress('segment', 45);
  const sceneCuts = await detectScenes(mp4, {
    threshold: config.sceneThreshold, ffmpegBin: config.bins.ffmpeg, ffprobeBin: config.bins.ffprobe,
  });
  const spans = buildSegmentManifest({ sceneCuts, words });

  progress('classify', 60);
  const classifications = await classifySpans(mp4, spans, {
    claudeBin: config.bins.claude, model: config.models.classify, workDir,
  });

  progress('translate', 80);
  const translations = await translateSpans(spans.map((s) => s.sourceTextEn), {
    claudeBin: config.bins.claude, model: config.models.translate,
  });

  progress('manifest', 100);
  const manifest = buildSegmentManifest({ sceneCuts, words, classifications, translations });
  return { jobId, workDir, mp4, manifest };
}
