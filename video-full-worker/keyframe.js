import { writeFile } from 'node:fs/promises';
import { dispatchImage, waitForJob } from './lib/geminigen.js';
import { config } from './config.js';

/**
 * Generate a 9:16 Ali keyframe still from a prompt + face reference(s).
 * refUrls default to Ali's creator face HTTPS URL (nano-banana-pro requires an
 * HTTPS ref, never a local path — vault indusia-image-gen-face-ref-gotcha).
 * Downloads the rendered still to outPath. Returns { path, url }.
 */
export async function generateKeyframe(prompt, outPath, { refUrls, jobOpts } = {}) {
  const refs = (refUrls && refUrls.length) ? refUrls : [config.aliFaceUrl];
  const uuid = await dispatchImage(prompt, refs);
  const state = await waitForJob(uuid, jobOpts);
  if (state.error) throw new Error(`keyframe failed: ${state.error}`);
  if (!state.imageUrl) throw new Error('keyframe terminal with no image url');
  const res = await fetch(state.imageUrl);
  if (!res.ok) throw new Error(`keyframe download ${res.status}`);
  await writeFile(outPath, Buffer.from(await res.arrayBuffer()));
  return { path: outPath, url: state.imageUrl };
}
