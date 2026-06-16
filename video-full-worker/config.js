import { homedir } from 'node:os';
import { join } from 'node:path';

/**
 * Central worker config, env-overridable. Phase A keys only; Phase B (geminigen,
 * RVC, ElevenLabs) and Phase E/G (bridge URL, token) extend this.
 */
export const config = {
  workRoot: process.env.VIDEO_FULL_WORK_DIR || join(homedir(), '.video-full-worker', 'jobs'),
  bins: {
    ytDlp: process.env.YT_DLP_BIN || 'yt-dlp',
    ffmpeg: process.env.FFMPEG_BIN || 'ffmpeg',
    ffprobe: process.env.FFPROBE_BIN || 'ffprobe',
    whisper: process.env.WHISPER_BIN || 'whisper-cli',
    claude: process.env.CLAUDE_BIN || 'claude',
  },
  // Must point at a REAL ggml model (the bundled tiny test stub is inadequate).
  // e.g. ~/.video-full-worker/models/ggml-base.en.bin — see README.
  whisperModel: process.env.WHISPER_MODEL || '',
  models: {
    classify: process.env.VIDEO_FULL_CLASSIFY_MODEL || 'sonnet',
    translate: process.env.VIDEO_FULL_TRANSLATE_MODEL || 'sonnet',
  },
  sceneThreshold: Number.isFinite(Number(process.env.VIDEO_FULL_SCENE_THRESHOLD))
    ? Number(process.env.VIDEO_FULL_SCENE_THRESHOLD)
    : 0.3,
  // GeminiGen HTTP API (same contract as backend GeminiGenVideoService).
  geminigen: {
    apiKey: process.env.GEMINIGEN_API_KEY || '',
    imageModel: process.env.GEMINIGEN_IMAGE_MODEL || 'nano-banana-pro',
    veoModel: process.env.GEMINIGEN_VEO_MODEL || 'veo-3.1-fast',
    grokModel: process.env.GEMINIGEN_VIDEO_MODEL || 'grok-3',
  },
  // Ali's creator face reference — MUST be an HTTPS URL (nano-banana-pro 400s on
  // local-path refs; see vault indusia-image-gen-face-ref-gotcha).
  aliFaceUrl: process.env.VIDEO_FULL_ALI_FACE_URL
    || 'https://alisadikinma.com/uploads/about/1776545803_creator-face.png',
  // Voice change: RVC (local, speech-to-speech) primary, ElevenLabs fallback.
  voice: {
    rvcPython: process.env.RVC_PYTHON || 'python3',
    rvcCli: process.env.RVC_CLI || '', // path to RVC infer script; empty → fallback
    rvcModel: process.env.RVC_MODEL || '', // trained Ali voice model (.pth)
    rvcIndex: process.env.RVC_INDEX || '',
    elevenLabsKey: process.env.ELEVENLABS_API_KEY || '',
    elevenLabsVoiceId: process.env.ELEVENLABS_VOICE_ID || '',
  },
};
