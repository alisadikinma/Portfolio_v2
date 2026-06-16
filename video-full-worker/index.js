import { join } from 'node:path';
import { mkdir } from 'node:fs/promises';
import { config } from './config.js';
import { Bridge } from './lib/bridge.js';
import { buildManifestForReel } from './pipeline.js';
import { planSegmentRender, cutSpan, vstackClips, concatClips, captionTrackFromManifest, overlayCaptions } from './compose.js';
import { generateKeyframe } from './keyframe.js';
import { animateClip } from './animate.js';
import { changeVoice } from './voice.js';

/**
 * Pure: the daemon's state machine. Decides the next step from how far a claimed
 * job has progressed. Kept pure so the loop logic is unit-tested.
 */
export function nextAction(state) {
  if (!state.claimedJob) return 'poll';
  if (!state.segmentsBuilt) return 'build';
  if (!state.allSegmentsRendered) return 'render';
  if (!state.finalUploaded) return 'compose_upload';
  return 'done';
}

/** Render a single segment to a normalized 9:16 clip per its strategy. */
async function renderSegment(seg, ctx) {
  const { srcMp4, workDir } = ctx;
  const out = join(workDir, `seg-${String(seg.index).padStart(2, '0')}.mp4`);
  const plan = planSegmentRender(seg);
  switch (plan.strategy) {
    case 'veo_talking': {
      const kf = await generateKeyframe(talkingPrompt(seg), join(workDir, `kf-${seg.index}.png`));
      const { clipPath } = await animateClip(kf.url, talkingPrompt(seg), { outPath: out });
      return changeVoice(clipPath, out.replace('.mp4', '.voiced.mp4'));
    }
    case 'reuse_source':
      return cutSpan(srcMp4, seg.start, seg.end, out);
    case 'remotion_recreate':
      // Recreate explanation b-roll via Remotion (own look). Caption overlay step
      // composites the ID text; the recreate body is rendered from the template.
      return cutSpan(srcMp4, seg.start, seg.end, out); // base bed; templates composited at overlay
    case 'vstack_broll_top_ali_bottom': {
      const top = await cutSpan(srcMp4, seg.start, seg.end, join(workDir, `top-${seg.index}.mp4`));
      const kf = await generateKeyframe(talkingPrompt(seg), join(workDir, `kf-${seg.index}.png`));
      const { clipPath } = await animateClip(kf.url, talkingPrompt(seg), { outPath: join(workDir, `bot-${seg.index}.mp4`) });
      const voiced = await changeVoice(clipPath, join(workDir, `bot-${seg.index}.voiced.mp4`));
      return vstackClips(top, voiced, out);
    }
    case 'drop':
    default:
      return null; // dropped segment contributes nothing
  }
}

function talkingPrompt(seg) {
  return `Indonesian creator Ali speaking to camera, natural delivery: "${seg.textId || seg.sourceTextEn}". `
    + 'Vertical 9:16, soft key light, shallow depth of field, authentic hand gestures.';
}

/** Process one claimed job end-to-end and upload the final reel. */
export async function processJob(job, bridge = new Bridge()) {
  const workDir = join(config.workRoot, String(job.id));
  await mkdir(workDir, { recursive: true });

  const { mp4, manifest } = await buildManifestForReel(job.source_url, {
    jobId: job.id, workRoot: config.workRoot,
    onProgress: (step, pct) => bridge.progress(job.id, pct, step).catch(() => {}),
  });
  await bridge.putSegments(job.id, manifest.map((s) => ({
    segment_index: s.index, type: s.type, start_sec: s.start, end_sec: s.end,
    source_text_en: s.sourceTextEn, text_id: s.textId, strategy: planSegmentRender(s).strategy,
  })));

  const clips = [];
  for (const seg of manifest) {
    await bridge.progress(job.id, 50 + Math.round((seg.index / manifest.length) * 40), 'render').catch(() => {});
    const clip = await renderSegment(seg, { srcMp4: mp4, workDir });
    if (clip) {
      clips.push(clip);
      await bridge.uploadSegmentPreview(job.id, seg.index, clip, 'done').catch(() => {});
    } else {
      await bridge.uploadSegmentPreview(job.id, seg.index, null, 'dropped').catch(() => {});
    }
  }

  const assembled = join(workDir, 'assembled.mp4');
  await concatClips(clips, assembled, { workDir });
  const final = join(workDir, 'final.mp4');
  await overlayCaptions(assembled, captionTrackFromManifest(manifest), final, { workDir });

  await bridge.progress(job.id, 95, 'upload').catch(() => {});
  await bridge.uploadFinal(job.id, final);
  return final;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Long-poll loop: claim → process → repeat. Crash-safe (each job independent). */
export async function run({ bridge = new Bridge(), once = false } = {}) {
  for (;;) {
    let job = null;
    try {
      job = await bridge.claim();
    } catch (e) {
      console.error('[video-full-worker] claim failed:', e.message);
    }
    if (job) {
      try {
        const out = await processJob(job, bridge);
        console.log(`[video-full-worker] job ${job.id} done → ${out}`);
      } catch (e) {
        console.error(`[video-full-worker] job ${job.id} failed:`, e.message);
        // Surface the crash on the VPS so the operator sees a Failed job (retryable)
        // rather than a row stuck at processing_local until the heartbeat goes stale.
        await bridge.fail(job.id, e.message).catch(() => {});
      }
    }
    if (once) return;
    await sleep(job ? 1000 : config.bridge.pollIntervalMs);
  }
}

// Entry point when run directly (node index.js).
if (import.meta.url === `file://${process.argv[1]}`) {
  run().catch((e) => {
    console.error('[video-full-worker] fatal:', e);
    process.exit(1);
  });
}
