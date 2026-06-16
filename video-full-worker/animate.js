import { readFile, writeFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { dispatchVeo, dispatchGrok, waitForJob } from './lib/geminigen.js';
import { run } from './lib/run.js';

const PROMINENT = ['public_error_prominent_people_upload', 'public_error_prominent_people', 'prominent people', 'prominent person'];
const AUDIO = ['public_error_audio_filtered', 'audio_filtered', 'audio generation failed'];
const POLICY = ['content_policy', 'safety filter', 'unsafe content', 'sexual content', 'violat'];

/** Pure: classify a GeminiGen error string (mirror of backend VideoGenErrorClassifier). */
export function classifyVideoError(reason) {
  if (!reason) return 'transient';
  const s = String(reason).toLowerCase();
  if (PROMINENT.some((p) => s.includes(p))) return 'prominent_people';
  if (AUDIO.some((p) => s.includes(p))) return 'audio_filtered';
  if (POLICY.some((p) => s.includes(p))) return 'content_policy';
  return 'transient';
}

/**
 * Pure: decide which provider to use next.
 * Veo is DEFAULT (quality); GROK is failover only — a known figure on the
 * keyframe (Veo refuses celeb faces) or an audio_filtered trip (Veo always
 * generates audio) routes to GROK. content_policy/transient retry the same
 * provider with a degraded prompt. (vault: veo-audio-celebrity-grok-failover)
 */
export function pickProvider({ current = 'veo', errorClass = null, hasFigure = false } = {}) {
  if (hasFigure) return 'grok';
  if (!errorClass) return current;
  if (errorClass === 'prominent_people' || errorClass === 'audio_filtered') return 'grok';
  return current;
}

/**
 * Download a clip URL, then crop to 9:16 (GROK renders 2:3 → center-crop to 9:16;
 * Veo already 9:16). Returns the local mp4 path. Real ffmpeg, no placeholder.
 */
async function fetchAndNormalize(url, outPath, provider, ffmpegBin) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`clip download ${res.status}`);
  const raw = `${outPath}.raw.mp4`;
  await writeFile(raw, Buffer.from(await res.arrayBuffer()));
  if (provider === 'grok') {
    // 2:3 → 9:16 center crop (keep full height, crop width).
    await run(ffmpegBin, ['-y', '-i', raw, '-vf', "crop='ih*9/16':ih", '-c:a', 'copy', outPath]);
  } else {
    await run(ffmpegBin, ['-y', '-i', raw, '-c', 'copy', outPath]);
  }
  return outPath;
}

/**
 * Animate one keyframe into a talking clip with provider failover.
 * Tries the chosen provider; on a figure/audio failure, fails over to GROK and
 * retries. Returns { provider, clipPath, veoUrl }. Caller degrades the prompt
 * between retries for content_policy/transient classes.
 */
export async function animateClip(keyframeUrl, prompt, {
  hasFigure = false, outPath, ffmpegBin = 'ffmpeg', maxAttempts = 3, jobOpts = {},
} = {}) {
  let provider = pickProvider({ current: 'veo', hasFigure });
  let lastErr = null;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      const uuid = provider === 'grok'
        ? await dispatchGrok(keyframeUrl, prompt)
        : await dispatchVeo(keyframeUrl, prompt);
      const state = await waitForJob(uuid, jobOpts);
      if (state.error) throw new Error(state.error);
      if (!state.videoUrl) throw new Error('terminal job with no video url');
      const clipPath = await fetchAndNormalize(state.videoUrl, outPath, provider, ffmpegBin);
      return { provider, clipPath, veoUrl: state.videoUrl };
    } catch (e) {
      lastErr = e;
      const cls = classifyVideoError(e.message);
      const next = pickProvider({ current: provider, errorClass: cls });
      provider = next;
    }
  }
  throw new Error(`animateClip exhausted ${maxAttempts} attempts: ${lastErr?.message}`);
}
