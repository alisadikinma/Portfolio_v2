import { readFile, writeFile } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { run } from './lib/run.js';
import { config } from './config.js';

/**
 * Convert a clip's voice to Ali's timbre WITHOUT changing timing — so Veo's lip
 * sync stays valid and the voice is consistent across all 8s clips (this is what
 * defeats Veo's per-clip voice discontinuity). Primary: RVC speech-to-speech
 * (local, offline). Fallback: ElevenLabs Voice Changer (s2s). Returns outPath.
 */
export async function changeVoice(clipPath, outPath, opts = {}) {
  const v = { ...config.voice, ...opts };
  const work = dirname(outPath);
  const srcWav = join(work, 'voice-src.wav');
  const outWav = join(work, 'voice-ali.wav');

  // 1) Extract the clip's audio (44.1k mono) — preserves timing.
  await run(config.bins.ffmpeg, ['-y', '-i', clipPath, '-vn', '-ar', '44100', '-ac', '1', srcWav]);

  // 2) Voice-change the audio (timing preserved by both engines).
  if (v.rvcCli && v.rvcModel) {
    await runRvc(srcWav, outWav, v);
  } else if (v.elevenLabsKey && v.elevenLabsVoiceId) {
    await elevenLabsConvert(srcWav, outWav, v);
  } else {
    throw new Error('voice: configure RVC (RVC_CLI+RVC_MODEL) or ElevenLabs (ELEVENLABS_API_KEY+ELEVENLABS_VOICE_ID)');
  }

  // 3) Mux the converted audio back over the original video stream.
  await run(config.bins.ffmpeg, [
    '-y', '-i', clipPath, '-i', outWav,
    '-map', '0:v:0', '-map', '1:a:0', '-c:v', 'copy', '-shortest', outPath,
  ]);
  return outPath;
}

async function runRvc(inWav, outWav, v) {
  const args = [v.rvcCli, '--input', inWav, '--output', outWav, '--model', v.rvcModel];
  if (v.rvcIndex) args.push('--index', v.rvcIndex);
  await run(v.rvcPython, args);
}

async function elevenLabsConvert(inWav, outWav, v) {
  const buf = await readFile(inWav);
  const form = new FormData();
  form.append('audio', new Blob([buf]), 'in.wav');
  form.append('model_id', 'eleven_multilingual_sts_v2');
  const res = await fetch(`https://api.elevenlabs.io/v1/speech-to-speech/${v.elevenLabsVoiceId}`, {
    method: 'POST',
    headers: { 'xi-api-key': v.elevenLabsKey },
    body: form,
  });
  if (!res.ok) throw new Error(`elevenlabs s2s ${res.status}: ${(await res.text()).slice(0, 200)}`);
  await writeFile(outWav, Buffer.from(await res.arrayBuffer()));
}
