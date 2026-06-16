# video-full-worker

MacBook-local worker for the Portfolio_v2 `video_full` repurpose mode
(talking-head IG reel → Ali's Indonesian reel). Runtime = **Node.js daemon**
(see ADR `adr-2026-06-16-video-full-worker-runtime`). Talks to the VPS only via
the bridge API (Phase E) — no DB access.

> Status: **Phase A** shipped — local pipeline core (capture → ASR → segment →
> translate → manifest). Phases B–I (asset gen, cleaning gate, bridge, Telegram,
> daemon, admin UI, Zernio publish) per the plan.

## Phase A — what exists
| Module | Does | Tool |
|---|---|---|
| `capture.js` | download source reel | yt-dlp |
| `asr.js` | English words + timestamps | whisper.cpp (`whisper-cli`) |
| `segment.js` | scene-cut detection + per-span vision classify | ffmpeg + claude CLI |
| `translate.js` | EN→ID per span | claude CLI |
| `lib/manifest.js` | merge signals → ordered segment manifest (pure) | — |
| `pipeline.js` | chains all of the above → `{ mp4, manifest }` | — |

## Prerequisites (verified present on this Mac)
`node>=20`, `npm`, `ffmpeg`, `yt-dlp`, `whisper-cli` (whisper.cpp), `claude` CLI.

## Required one-time setup: a real Whisper model
whisper.cpp ships only a **tiny test stub** (`for-tests-ggml-tiny.bin`, ~575KB) —
inadequate for real transcription. Download a real ggml model and point
`WHISPER_MODEL` at it:

```bash
mkdir -p ~/.video-full-worker/models
curl -L -o ~/.video-full-worker/models/ggml-base.en.bin \
  https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-base.en.bin
export WHISPER_MODEL=~/.video-full-worker/models/ggml-base.en.bin
```
(Use `ggml-small.en.bin` / `ggml-medium.en.bin` for higher accuracy at more compute.)

## Run the tests
```bash
cd video-full-worker
npm test          # node --test — pure-logic suite (manifest + parsers)
```

## Validate Phase A on a real reel
```bash
export WHISPER_MODEL=~/.video-full-worker/models/ggml-base.en.bin
node -e "import('./pipeline.js').then(m => m.buildManifestForReel('https://www.instagram.com/p/DZmqSoRKOQ9/').then(r => console.log(JSON.stringify(r.manifest, null, 2))))"
```
Requires: the Whisper model above, network access to Instagram, and the `claude`
CLI authenticated (used for classify + translate).

## Config (env)
See `config.js`. Phase-A keys: `WHISPER_MODEL`, `VIDEO_FULL_WORK_DIR`,
`*_BIN` overrides, `VIDEO_FULL_SCENE_THRESHOLD`,
`VIDEO_FULL_CLASSIFY_MODEL`/`VIDEO_FULL_TRANSLATE_MODEL`.
