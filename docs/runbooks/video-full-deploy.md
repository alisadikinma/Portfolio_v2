# video_full (Video 60s) — Deploy & Ops Runbook

4th IG-repurpose mode: a source talking-head reel → Ali's ≈60s Indonesian reel
(Ali face/voice/ID captions). **Dual-runtime**: the VPS (Laravel) tracks the job +
Telegram trigger + admin UI + Zernio publish; the **MacBook** runs the heavy
pipeline as a Node daemon. Plan: [docs/plans/2026-06-16-video-full-rebrand.md](../plans/2026-06-16-video-full-rebrand.md).

## VPS side (auto via deploy.sh)
1. `git push` → migration `2026_06_16_000010_add_video_full_to_repurpose` runs
   (worker columns + `video_full_segments`); `TelegramSettingsSeeder` seeds
   `telegram_video_full_enabled` (default `false`, idempotent).
2. Enable the Telegram button in `/admin/about` → Telegram card (or set
   `telegram_video_full_enabled='true'`). The "🎥 Video 60s" button then appears
   on the IG-repurpose mode prompt.
3. Mint a worker token (one-time):
   ```bash
   php artisan tinker --execute="echo App\Models\User::find(1)->createToken('video-full-worker',['video-full:work'])->plainTextToken;"
   ```
   Hand this to the MacBook worker as `VIDEO_FULL_WORKER_TOKEN`.

## MacBook worker side (one-time)
See [video-full-worker/README.md](../../video-full-worker/README.md). Summary:
- Install: `yt-dlp`, `ffmpeg`, `whisper-cli` (whisper.cpp) + a real ggml model,
  Remotion (`cd video-full-worker/remotion && npm install`), RVC (+ trained Ali
  voice `.pth`) or ElevenLabs keys, `tesseract`.
- Env: `VIDEO_FULL_BRIDGE_URL`, `VIDEO_FULL_WORKER_TOKEN`, `GEMINIGEN_API_KEY`,
  `WHISPER_MODEL`, `RVC_*` (or `ELEVENLABS_*`).
- Run: `node video-full-worker/index.js` (keep alive via launchd / pm2).

## Flow
1. Send an IG reel URL to the Telegram bot → tap **🎥 Video 60s**.
2. Job parks at `queued_local`. The MacBook daemon claims it (`GET /worker/video-full/claim`),
   runs capture→ASR→segment→translate→keyframe→Veo/GROK→voice-change→compose,
   streams progress, uploads per-segment previews + the final MP4.
3. Review at `/admin/video-full/:id` — segment timeline, worker-online status,
   final-video player, per-segment **↻ Regenerate**.
4. Publish via Zernio to LinkedIn/IG/TikTok/Threads (`POST /admin/video-full/{id}/publish-zernio`).
   Requires `ZERNIO_PUBLISH_ENABLED=true` + a `zernio_{platform}_account_id` per
   target. **LinkedIn-via-Zernio**: needs `zernio_linkedin_account_id` configured;
   if Zernio has no LinkedIn account it lands in `skipped` (route LinkedIn natively
   or post manually — open item).

## Troubleshooting
- **Job stuck at `queued_local`** → worker offline. Check the daemon is running on
  the MacBook + the token/bridge URL are correct. The admin detail shows
  "Worker offline".
- **Veo audio/figure failure** → the worker fails over Veo→GROK automatically
  (vault `veo-audio-celebrity-grok-failover`); persistent failure surfaces as a
  failed segment → use ↻ Regenerate.
- **Upload stalls** → large MP4 over home internet; check the daemon log + retry
  (each segment + the final upload are independent).
- **No Whisper output** → the bundled tiny stub is inadequate; download a real
  `ggml-base.en.bin` and set `WHISPER_MODEL`.
- **Zernio `NO_FINAL_VIDEO` (422)** → the worker hasn't uploaded the final reel yet.

## Rollback
Set `telegram_video_full_enabled='false'` (button disappears; existing jobs keep
working). The mode is additive — no impact on blog/carousel/video_rebrand.
