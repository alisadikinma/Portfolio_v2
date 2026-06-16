import { mkdir } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { run, extractJson } from './lib/run.js';

const VALID_TYPES = ['to_camera', 'b_roll', 'split_screen'];

/** Probe video duration (seconds) via ffprobe. */
export async function getDuration(mp4Path, { ffprobeBin = 'ffprobe' } = {}) {
  const { stdout } = await run(ffprobeBin, [
    '-v', 'error', '-show_entries', 'format=duration',
    '-of', 'default=noprint_wrappers=1:nokey=1', mp4Path,
  ]);
  return parseFloat(stdout.trim());
}

/**
 * Detect scene-cut timestamps via ffmpeg scene score, returning a sorted cut
 * array INCLUDING 0 and the video end (the shape buildSegmentManifest expects).
 */
export async function detectScenes(mp4Path, { threshold = 0.3, ffmpegBin = 'ffmpeg', ffprobeBin = 'ffprobe' } = {}) {
  const { stderr } = await run(ffmpegBin, [
    '-i', mp4Path,
    '-filter:v', `select='gt(scene,${threshold})',showinfo`,
    '-an', '-f', 'null', '-',
  ], { allowNonZero: true });

  const cuts = [];
  for (const m of stderr.matchAll(/pts_time:([0-9.]+)/g)) {
    cuts.push(parseFloat(m[1]));
  }
  const duration = await getDuration(mp4Path, { ffprobeBin });
  const all = [0, ...cuts.filter((t) => t > 0.05 && t < duration - 0.05), duration];
  return [...new Set(all)].sort((a, b) => a - b);
}

/** Extract one representative frame at the midpoint of each span. */
export async function sampleFrames(mp4Path, spans, outDir, { ffmpegBin = 'ffmpeg' } = {}) {
  await mkdir(outDir, { recursive: true });
  const paths = [];
  for (let i = 0; i < spans.length; i++) {
    const mid = (spans[i].start + spans[i].end) / 2;
    const out = join(outDir, `span-${String(i).padStart(2, '0')}.jpg`);
    await run(ffmpegBin, ['-y', '-ss', String(mid), '-i', mp4Path, '-frames:v', '1', '-q:v', '3', out]);
    paths.push(out);
  }
  return paths;
}

/** Pure: build the vision-classify prompt (testable without the LLM). */
export function buildClassifyPrompt(spans, framePaths) {
  const lines = spans.map((s, i) =>
    `Span ${i}: time ${s.start.toFixed(1)}-${s.end.toFixed(1)}s, frame ${framePaths[i]}` +
    (s.sourceTextEn ? `, speech: "${s.sourceTextEn}"` : ', no speech'));
  return [
    'You classify each span of a talking-head Instagram reel by reading its frame image.',
    'Categories:',
    '- to_camera: the creator is speaking to camera, face fills the frame',
    '- b_roll: footage/screen-recording/graphic with NO creator face filling the frame',
    '- split_screen: frame split (e.g. b-roll top, creator face bottom)',
    '',
    'Spans:',
    ...lines,
    '',
    `Read each frame image and reply with ONLY a JSON array of ${spans.length} objects,`,
    'index-aligned to the spans, each {"index":N,"type":"to_camera|b_roll|split_screen"}.',
    'No prose, no code fences.',
  ].join('\n');
}

/** Pure: validate + normalize the LLM classify response into index-aligned types. */
export function parseClassifyResponse(text, spanCount) {
  const arr = extractJson(text);
  if (!Array.isArray(arr)) throw new Error('classify: expected a JSON array');
  const byIndex = new Map(arr.map((o) => [o.index, o.type]));
  const out = [];
  for (let i = 0; i < spanCount; i++) {
    const t = byIndex.get(i);
    out.push({ type: VALID_TYPES.includes(t) ? t : 'b_roll' }); // unknown → safe default
  }
  return out;
}

/**
 * Classify every span by vision. Real integration: samples a frame per span and
 * asks the claude CLI (Read-enabled) to read the frames and return JSON.
 */
export async function classifySpans(mp4Path, spans, {
  claudeBin = 'claude', model = 'sonnet', workDir = dirname(mp4Path),
} = {}) {
  const frames = await sampleFrames(mp4Path, spans, join(workDir, 'frames'));
  const prompt = buildClassifyPrompt(spans, frames);
  const { stdout } = await run(claudeBin, [
    '-p', prompt,
    '--model', model,
    '--allowedTools', 'Read',
  ]);
  return parseClassifyResponse(stdout, spans.length);
}
