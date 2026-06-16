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
};
