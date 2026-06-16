import { readFile, mkdir } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { run } from './lib/run.js';

/**
 * Parse whisper.cpp --output-json-full into flat word list with second-based
 * timestamps. whisper.cpp emits `transcription[].offsets.{from,to}` in ms when
 * run with -ml 1 --split-on-word (one token-word per segment).
 * Exported pure so it can be unit-tested without invoking the binary.
 */
export function parseWhisperJson(json) {
  const rows = Array.isArray(json?.transcription) ? json.transcription : [];
  const words = [];
  for (const r of rows) {
    const text = (r.text ?? '').trim();
    if (!text) continue;
    const from = r.offsets?.from;
    const to = r.offsets?.to;
    if (typeof from !== 'number' || typeof to !== 'number') continue;
    words.push({ word: text, start: from / 1000, end: to / 1000 });
  }
  return { words, text: words.map((w) => w.word).join(' ') };
}

/**
 * Transcribe an mp4 to English words+timestamps via whisper.cpp.
 * Extracts 16kHz mono wav with ffmpeg first (whisper.cpp requires wav).
 * `model` must point to a real ggml model (.bin) — the bundled tiny test stub
 * is NOT adequate; download e.g. ggml-base.en.bin first (see worker README).
 */
export async function transcribe(mp4Path, {
  model,
  whisperBin = 'whisper-cli',
  ffmpegBin = 'ffmpeg',
  language = 'en',
} = {}) {
  if (!model) throw new Error('asr: a real whisper ggml model path is required');
  const workDir = dirname(mp4Path);
  await mkdir(workDir, { recursive: true });
  const wav = join(workDir, 'audio.16k.wav');
  await run(ffmpegBin, ['-y', '-i', mp4Path, '-ar', '16000', '-ac', '1', '-c:a', 'pcm_s16le', wav]);

  const outBase = join(workDir, 'asr');
  await run(whisperBin, [
    '-m', model,
    '-f', wav,
    '-l', language,
    '-ml', '1',          // max segment length 1 → per-word segments
    '--split-on-word',
    '--output-json-full',
    '-of', outBase,
  ]);

  const json = JSON.parse(await readFile(`${outBase}.json`, 'utf8'));
  return parseWhisperJson(json);
}
