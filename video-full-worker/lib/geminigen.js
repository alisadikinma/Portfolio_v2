import { config } from '../config.js';

const BASE = 'https://api.geminigen.ai/uapi/v1';

/**
 * Pure: normalize a /history/{uuid} poll body into a uniform terminal state.
 * Mirrors the backend GeminiGenVideoService/PollRebrandAssets contract:
 *   status 3 = terminal; error_code/error_message present = failed;
 *   image → image_url|media_url|generated_image[0].image_url;
 *   video → generated_video[0].video_url|media_url.
 */
export function parseHistory(body) {
  const data = (body && body.data) || body || {};
  const status = Number(data.status ?? 0);
  const errCode = String(data.error_code ?? (body && body.error_code) ?? '');
  const errMsg = String(data.error_message ?? (body && body.error_message) ?? '');
  const error = errCode || errMsg ? errMsg || errCode : null;
  return {
    terminal: status === 3 || error !== null,
    error,
    imageUrl: data.image_url ?? data.media_url ?? data.generated_image?.[0]?.image_url ?? null,
    videoUrl: data.generated_video?.[0]?.video_url ?? data.media_url ?? null,
  };
}

function apiKey() {
  const k = config.geminigen?.apiKey;
  if (!k) throw new Error('geminigen: GEMINIGEN_API_KEY not set');
  return k;
}

async function postMultipart(path, fields) {
  const form = new FormData();
  for (const [name, value] of fields) form.append(name, value);
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'x-api-key': apiKey() },
    body: form,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`geminigen ${path} ${res.status}: ${JSON.stringify(json).slice(0, 300)}`);
  const uuid = json?.uuid;
  if (typeof uuid !== 'string' || !uuid) throw new Error(`geminigen ${path}: 2xx but no uuid`);
  return uuid;
}

/** Dispatch a 9:16 image (keyframe). refUrls = HTTPS reference image URLs (Ali face). */
export function dispatchImage(prompt, refUrls = [], { model = config.geminigen?.imageModel || 'nano-banana-pro', aspect = '9:16' } = {}) {
  const fields = [['prompt', prompt], ['model', model], ['aspect_ratio', aspect]];
  for (const u of refUrls) fields.push(['file_urls', u]);
  return postMultipart('/generate_image', fields);
}

/** Dispatch a Veo image-to-video clip from a keyframe URL (ref_images). */
export function dispatchVeo(keyframeUrl, prompt, { aspect = '9:16', model = config.geminigen?.veoModel || 'veo-3.1-fast' } = {}) {
  return postMultipart('/video-gen/veo', [
    ['prompt', prompt], ['model', model], ['aspect_ratio', aspect], ['ref_images', keyframeUrl],
  ]);
}

/** Dispatch a GROK image-to-video clip. GROK only accepts 2:3 (9:16 → HTTP 400). */
export function dispatchGrok(keyframeUrl, prompt, { model = config.geminigen?.grokModel || 'grok-3' } = {}) {
  return postMultipart('/video-gen/grok', [
    ['prompt', prompt], ['model', model], ['aspect_ratio', '2:3'], ['file_urls', keyframeUrl],
  ]);
}

/** Poll /history/{uuid} once → parsed terminal state. */
export async function pollHistory(uuid) {
  const res = await fetch(`${BASE}/history/${uuid}`, { headers: { 'x-api-key': apiKey() } });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`geminigen history ${res.status}`);
  return parseHistory(json);
}

/** Poll a uuid until terminal (or timeout). Returns the parsed terminal state. */
export async function waitForJob(uuid, { intervalMs = 6000, timeoutMs = 600000, sleep = defaultSleep } = {}) {
  const deadline = Date.now() + timeoutMs;
  for (;;) {
    const state = await pollHistory(uuid);
    if (state.terminal) return state;
    if (Date.now() > deadline) throw new Error(`geminigen job ${uuid} timed out`);
    await sleep(intervalMs);
  }
}

function defaultSleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}
