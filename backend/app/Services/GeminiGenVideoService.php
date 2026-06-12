<?php

namespace App\Services;

use App\Models\InstagramPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * GROK hook-video dispatch (June 12, 2026) — Instagram mixed carousel.
 *
 * Turns an IG draft's hook slide (slide 1) into a short GROK image-to-video
 * animation: the creator continues their pose + the floating topic icons drift.
 * IG (unlike LinkedIn) can mix video+image in one carousel, so this video ships
 * as carousel item 1 with the rest staying image slides.
 *
 * Locked v7 recipe (Phase 0 spike, verified live on prod VPS):
 *   - model=grok-3 (→ model_name=grok-video), aspect_ratio=2:3, mode=custom,
 *     duration=6, resolution=720p, image-to-video via file_urls=<padded JPG>.
 *   - Frame MUST be a flattened JPG (GROK rejects PNG alpha) and pre-padded
 *     4:5→2:3 with brand-blue fill so GROK has no empty margin to hallucinate
 *     into (the actual cause of the v6 phantom-object artifacts).
 *   - Static no-new-objects prompt (NO LLM step) — generic + frame-respecting.
 *   - Output is cropped 2:3→4:5 + audio stripped on download to match slides.
 *   - Delivery is POLL-only (GeminiGen never fires webhooks) — the dispatch
 *     returns a job uuid the poller hits at /uapi/v1/history/{uuid}.
 *
 * Circuit-open short-circuits (skip, never throw) — same convention as
 * LinkedInCarouselImageService.
 */
class GeminiGenVideoService
{
    private string $apiKey;

    private string $baseUrl = 'https://api.geminigen.ai/uapi/v1';

    private string $ffmpeg;

    /**
     * Locked v7 prompt: ONE comma-separated sentence, ZERO semicolons (the API
     * truncates input_text at the first ';'), no hardcoded prop ("coffee"), and
     * the hard "animate only what exists, add nothing new" constraint that —
     * combined with the pre-padded 2:3 frame — kills the invented-object artifacts.
     */
    public const HOOK_VIDEO_PROMPT = 'The creator continues the exact relaxed pose and action already shown in the source image, '
        .'animate only the elements that already exist and introduce no new object icon or element, '
        .'both hands and anything being held stay exactly as in the frame with no duplicated or extra hand, '
        .'the floating side UI icons drift and bob gently with subtle parallax, '
        .'the camera stays completely static with no zoom or pan, '
        .'the mouth stays closed and the person is not speaking, '
        .'photorealistic with natural micro-motion and no morphing';

    public function __construct(
        private readonly GeminiGenCircuitBreaker $breaker
    ) {
        $this->apiKey = (string) config('services.geminigen.api_key', '');
        $this->ffmpeg = (string) config('services.geminigen.ffmpeg_path', 'ffmpeg');
    }

    /**
     * Dispatch a GROK image-to-video job for the IG hook. Returns the GROK job
     * uuid (poll key) or null on circuit-open / missing key / HTTP failure.
     */
    public function dispatchHookVideo(InstagramPost $ig, string $frameUrl): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiGenVideo] GEMINIGEN_API_KEY missing — cannot dispatch', ['ig' => $ig->id]);

            return null;
        }

        // Circuit breaker gate — during a GeminiGen outage, skip dispatch and
        // leave the IG draft's hook_video_status untouched so the reaper retries
        // once the breaker closes (mirrors the image-dispatch behavior).
        if ($this->breaker->state() === 'open') {
            Log::warning('[GeminiGenVideo] dispatchHookVideo skipped — circuit OPEN', ['ig' => $ig->id]);

            return null;
        }

        $multipart = [
            ['name' => 'prompt', 'contents' => self::HOOK_VIDEO_PROMPT],
            ['name' => 'model', 'contents' => (string) config('services.geminigen.video_model', 'grok-3')],
            ['name' => 'aspect_ratio', 'contents' => '2:3'],
            ['name' => 'mode', 'contents' => 'custom'],
            ['name' => 'duration', 'contents' => '6'],
            ['name' => 'resolution', 'contents' => '720p'],
            ['name' => 'file_urls', 'contents' => $frameUrl],
        ];

        try {
            // 90s — this POST uploads the padded JPG frame (binary multipart)
            // plus GROK's enqueue time, not just a JSON body.
            $response = Http::timeout(90)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->asMultipart()
                ->post("{$this->baseUrl}/video-gen/grok", $multipart);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->breaker->recordFailure(null, null, $e);
            Log::error('[GeminiGenVideo] HTTP connection exception', ['ig' => $ig->id, 'error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] HTTP unexpected exception', ['ig' => $ig->id, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            $errorCode = $response->json('error_code') ?? $response->json('code');
            $this->breaker->recordFailure($response->status(), is_string($errorCode) ? $errorCode : null);
            Log::error('[GeminiGenVideo] GROK dispatch non-2xx', [
                'ig' => $ig->id,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $this->breaker->recordSuccess();

        $uuid = $response->json('uuid');
        if (! is_string($uuid) || $uuid === '') {
            Log::error('[GeminiGenVideo] GROK 2xx but no uuid', ['ig' => $ig->id, 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        return $uuid;
    }

    /**
     * Download the hook slide PNG, flatten alpha (GROK rejects PNG transparency)
     * and pre-pad 4:5/3:4 → 2:3 with brand-blue (#0F59B6) fill so GROK has no
     * empty margin to outpaint. Returns the public JPG URL to pass as file_urls,
     * or null on download/ffmpeg failure.
     */
    public function prepareFrame(string $slideImageUrl, int $igId): ?string
    {
        try {
            $resp = Http::timeout(20)->get($slideImageUrl);
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] frame download failed', ['ig' => $igId, 'url' => $slideImageUrl, 'error' => $e->getMessage()]);

            return null;
        }
        if (! $resp->successful()) {
            Log::error('[GeminiGenVideo] frame download non-2xx', ['ig' => $igId, 'status' => $resp->status()]);

            return null;
        }

        // uniqid suffix so a manual retry racing the reaper for the same IG id
        // can't overwrite the other's source PNG mid-ffmpeg.
        $srcRel = "tmp/grok-src-{$igId}-".uniqid().'.png';
        Storage::disk('local')->put($srcRel, $resp->body());
        $srcPath = Storage::disk('local')->path($srcRel);

        $outRel = "linkedin-carousel/grok-frame-{$igId}.jpg";
        $outPath = Storage::disk('public')->path($outRel);
        @mkdir(dirname($outPath), 0775, true);

        // Flatten to rgb24 (drop alpha) + pad to 2:3 with brand-blue bars.
        // try/catch returns null on a ffmpeg timeout/throw; finally guarantees
        // the temp source PNG is removed on every path.
        try {
            $result = Process::timeout(60)->run([
                $this->ffmpeg, '-y', '-i', $srcPath,
                '-vf', 'format=rgb24,pad=iw:ceil(iw*3/2/2)*2:(ow-iw)/2:(oh-ih)/2:color=0x0F59B6',
                '-frames:v', '1', $outPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] ffmpeg pad threw', ['ig' => $igId, 'error' => $e->getMessage()]);
            @unlink($outPath);

            return null;
        } finally {
            Storage::disk('local')->delete($srcRel);
        }

        if (! $result->successful() || ! is_file($outPath)) {
            Log::error('[GeminiGenVideo] ffmpeg pad failed', ['ig' => $igId, 'error' => $result->errorOutput()]);
            @unlink($outPath);

            return null;
        }

        return url('/storage/'.$outRel);
    }

    /**
     * Download the finished GROK MP4, crop 2:3 → 4:5 (match the image slides)
     * and strip audio. Returns the public MP4 URL or null on failure.
     */
    public function finalizeHookVideo(string $remoteVideoUrl, int $igId): ?string
    {
        try {
            $resp = Http::timeout(90)->get($remoteVideoUrl);
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] video download failed', ['ig' => $igId, 'error' => $e->getMessage()]);

            return null;
        }
        if (! $resp->successful()) {
            Log::error('[GeminiGenVideo] video download non-2xx', ['ig' => $igId, 'status' => $resp->status()]);

            return null;
        }

        $rawRel = "tmp/grok-raw-{$igId}-".uniqid().'.mp4';
        Storage::disk('local')->put($rawRel, $resp->body());
        $rawPath = Storage::disk('local')->path($rawRel);

        $outRel = "linkedin-carousel/grok-hook-{$igId}.mp4";
        $outPath = Storage::disk('public')->path($outRel);
        @mkdir(dirname($outPath), 0775, true);

        // Center-crop 2:3 → 4:5 (even dims) + drop the audio track.
        // try/catch returns null on timeout/throw; finally removes the temp raw.
        try {
            $result = Process::timeout(120)->run([
                $this->ffmpeg, '-y', '-i', $rawPath,
                '-vf', 'crop=iw:floor(iw*5/4/2)*2',
                '-an', $outPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] ffmpeg crop threw', ['ig' => $igId, 'error' => $e->getMessage()]);
            @unlink($outPath);

            return null;
        } finally {
            Storage::disk('local')->delete($rawRel);
        }

        if (! $result->successful() || ! is_file($outPath)) {
            Log::error('[GeminiGenVideo] ffmpeg crop failed', ['ig' => $igId, 'error' => $result->errorOutput()]);
            @unlink($outPath);

            return null;
        }

        return url('/storage/'.$outRel);
    }
}
