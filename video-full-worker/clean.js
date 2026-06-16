import { join, dirname } from 'node:path';
import { mkdir } from 'node:fs/promises';
import { run, extractJson } from './lib/run.js';

/**
 * Pure: union the detected subtitle text boxes into a single opaque cover rect
 * (with padding), clamped to the frame. The Indonesian caption bar is rendered
 * over this rect to hide the source's burned-in English subtitle (decided:
 * OVERLAY/timpa, no inpaint). Returns null when no subtitle was detected.
 */
export function subtitleCoverBox(boxes, { videoW, videoH, padding = 12 } = {}) {
  if (!boxes || boxes.length === 0) return null;
  let minX = Infinity; let minY = Infinity; let maxX = -Infinity; let maxY = -Infinity;
  for (const b of boxes) {
    minX = Math.min(minX, b.x);
    minY = Math.min(minY, b.y);
    maxX = Math.max(maxX, b.x + b.w);
    maxY = Math.max(maxY, b.y + b.h);
  }
  const x = Math.max(0, minX - padding);
  const y = Math.max(0, minY - padding);
  return {
    x,
    y,
    w: Math.min(videoW - x, maxX - minX + padding * 2),
    h: Math.min(videoH - y, maxY - minY + padding * 2),
  };
}

/** Pure: normalize the vision-gate JSON for one frame into a safe shape. */
export function parseGateResponse(text) {
  const j = extractJson(text);
  const boxes = (arr) => (Array.isArray(arr) ? arr.filter((b) =>
    ['x', 'y', 'w', 'h'].every((k) => typeof b?.[k] === 'number')) : []);
  return {
    hasCreatorFace: j?.has_creator_face === true,
    brandBoxes: boxes(j?.brand_boxes),
    subtitleBoxes: boxes(j?.subtitle_boxes),
  };
}

/** Pure: build the cleaning-gate vision prompt for one b-roll frame. */
export function buildGatePrompt(framePath) {
  return [
    `Inspect this single video frame: ${framePath}`,
    'Report, as ONLY a JSON object (no prose, no fences):',
    '- has_creator_face: true if a recognizable human presenter face is shown',
    '  (the ORIGINAL creator, e.g. Vaibhav Sisinty — any prominent talking-head face).',
    '- brand_boxes: array of {x,y,w,h} pixel rects around any creator handle',
    '  (e.g. "@vaibhavsisinty"), channel logo, or personal watermark.',
    '- subtitle_boxes: array of {x,y,w,h} pixel rects around any burned-in caption/subtitle text.',
    'Use pixel coordinates from the top-left. Empty arrays if none.',
  ].join('\n');
}

/**
 * Run the cleaning gate on one b-roll span: sample a frame, ask the claude CLI
 * (Read-enabled vision) to detect the original creator's face, brand marks, and
 * burned-in subtitle boxes. Returns the parsed gate result. Real integration.
 */
export async function gateBrollFrame(mp4Path, atSeconds, {
  claudeBin = 'claude', model = 'sonnet', workDir = dirname(mp4Path), ffmpegBin = 'ffmpeg',
} = {}) {
  await mkdir(workDir, { recursive: true });
  const frame = join(workDir, `gate-${Math.round(atSeconds * 1000)}.jpg`);
  await run(ffmpegBin, ['-y', '-ss', String(atSeconds), '-i', mp4Path, '-frames:v', '1', '-q:v', '3', frame]);
  const { stdout } = await run(claudeBin, ['-p', buildGatePrompt(frame), '--model', model, '--allowedTools', 'Read']);
  return parseGateResponse(stdout);
}
