import { writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { run } from './lib/run.js';
import { config } from './config.js';

/** Pure: caption cues (timed Indonesian text) from the segment manifest. */
export function captionTrackFromManifest(manifest) {
  return manifest
    .filter((s) => s.textId && s.textId.trim())
    .map((s) => ({ start: s.start, end: s.end, text: s.textId.trim() }));
}

/**
 * Pure: decide how each segment's visual is produced.
 *   to_camera   → veo_talking (Phase B regen of Ali)
 *   split_screen→ vstack (clean source b-roll top + Ali Veo strip bottom)
 *   b_roll      → remotion_recreate (animatable) | reuse_source (clean / croppable) | drop
 */
export function planSegmentRender(seg) {
  if (seg.type === 'to_camera') return { strategy: 'veo_talking' };
  if (seg.type === 'split_screen') return { strategy: 'vstack_broll_top_ali_bottom' };
  if (seg.animatable) return { strategy: 'remotion_recreate' };
  if (seg.clean) return { strategy: 'reuse_source' };
  if (seg.croppable) return { strategy: 'reuse_source', crop: true };
  return { strategy: 'drop' };
}

const W = 1080;
const H = 1920;

/** Cut a [start,end] span from the source reel, normalized to 9:16 1080×1920. */
export async function cutSpan(srcMp4, start, end, outPath, { ffmpegBin = config.bins.ffmpeg } = {}) {
  await run(ffmpegBin, [
    '-y', '-ss', String(start), '-to', String(end), '-i', srcMp4,
    '-vf', `scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H}`,
    '-c:a', 'aac', outPath,
  ]);
  return outPath;
}

/** Stack a top (b-roll) and bottom (Ali strip) clip into one 9:16 frame. */
export async function vstackClips(topClip, bottomClip, outPath, { ffmpegBin = config.bins.ffmpeg } = {}) {
  await run(ffmpegBin, [
    '-y', '-i', topClip, '-i', bottomClip,
    '-filter_complex',
    `[0:v]scale=${W}:${H / 2}:force_original_aspect_ratio=increase,crop=${W}:${H / 2}[t];` +
    `[1:v]scale=${W}:${H / 2}:force_original_aspect_ratio=increase,crop=${W}:${H / 2}[b];` +
    '[t][b]vstack=inputs=2[v]',
    '-map', '[v]', '-map', '1:a?', '-c:a', 'aac', outPath,
  ]);
  return outPath;
}

/** Concat prepared per-segment clips (re-encode for safe concat across sources). */
export async function concatClips(clipPaths, outPath, { ffmpegBin = config.bins.ffmpeg, workDir } = {}) {
  const listFile = join(workDir, 'concat.txt');
  await writeFile(listFile, clipPaths.map((p) => `file '${p.replace(/'/g, "'\\''")}'`).join('\n'));
  await run(ffmpegBin, [
    '-y', '-f', 'concat', '-safe', '0', '-i', listFile,
    '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', outPath,
  ]);
  return outPath;
}

/**
 * Render the timed Indonesian caption track as a transparent Remotion overlay and
 * composite it over the assembled video. The Remotion project lives in ./remotion.
 */
export async function overlayCaptions(videoPath, cues, outPath, { workDir } = {}) {
  const propsFile = join(workDir, 'captions.props.json');
  await writeFile(propsFile, JSON.stringify({ cues, durationInSeconds: cuesDuration(cues) }));
  const overlay = join(workDir, 'captions.mov');
  // Transparent caption overlay (ProRes 4444 to carry alpha).
  await run('npx', [
    'remotion', 'render', 'Captions', overlay,
    '--props', propsFile, '--codec', 'prores', '--prores-profile', '4444',
  ], { cwd: new URL('./remotion', import.meta.url).pathname });
  await run(config.bins.ffmpeg, [
    '-y', '-i', videoPath, '-i', overlay,
    '-filter_complex', '[0:v][1:v]overlay=0:0[v]',
    '-map', '[v]', '-map', '0:a?', '-c:a', 'copy', outPath,
  ]);
  return outPath;
}

function cuesDuration(cues) {
  return cues.reduce((m, c) => Math.max(m, c.end), 0);
}
