<?php

namespace App\Services;

use App\Models\InstagramPost;
use App\Support\SharedDir;
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
     * Write a transient download to the OS temp dir (world-writable /tmp), NOT
     * storage/app/private/tmp — that dir is 0700 www-data and unreadable by the
     * claudesn queue worker that runs the rebrand poller. Durable replacement for
     * the fragile `chmod 2775 private/tmp` ops-fix. Returns the absolute path;
     * callers @unlink it in a finally block.
     */
    private function writeTempFile(string $name, string $bytes): string
    {
        $path = rtrim(sys_get_temp_dir(), '/').'/'.$name;
        file_put_contents($path, $bytes);

        return $path;
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
     * Dispatch a face-gen keyframe for a video_rebrand hook/CTA clip — a 9:16
     * photoreal portrait built from the creator face ref(s) via GeminiGen
     * /generate_image. Poll-based (NO webhook — geminigen never fires one); the
     * finished image URL feeds dispatchVeoClip() as the Veo start frame. Returns
     * the job uuid (poll key at /history/{uuid}) or null on
     * circuit-open / missing key / HTTP failure.
     *
     * $faceRefUrls accepts a single URL (CTA / legacy callers) OR an array of
     * URLs — e.g. [creatorFace, publicFigureFace] for a topic-aware hook where the
     * figure enters as reference image 2 (its NAME never appears in the prompt).
     * Each URL becomes its own file_urls multipart entry (what GeminiGen expects).
     *
     * @param string|array<int,string> $faceRefUrls
     */
    public function dispatchKeyframe(string|array $faceRefUrls, string $prompt, ?int $contextId = null): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiGenVideo] GEMINIGEN_API_KEY missing — cannot dispatch keyframe', ['ctx' => $contextId]);

            return null;
        }

        if ($this->breaker->state() === 'open') {
            Log::warning('[GeminiGenVideo] dispatchKeyframe skipped — circuit OPEN', ['ctx' => $contextId]);

            return null;
        }

        $refs = array_values(array_filter(
            is_array($faceRefUrls) ? $faceRefUrls : [$faceRefUrls],
            fn ($u) => is_string($u) && $u !== ''
        ));
        if ($refs === []) {
            Log::error('[GeminiGenVideo] dispatchKeyframe called with no usable face ref', ['ctx' => $contextId]);

            return null;
        }

        $finalPrompt = $prompt
            .'. Maintain exact facial identity, appearance, and features from the provided face reference image(s).';

        $multipart = [
            ['name' => 'prompt', 'contents' => $finalPrompt],
            ['name' => 'model', 'contents' => (string) (config('content.default_image_model') ?: 'nano-banana-pro')],
            // 9:16 is Imagen-native (1:1/16:9/9:16/4:3/3:4 only) so it survives
            // without falling back to the 16:9 default — feeds Veo cleanly.
            ['name' => 'aspect_ratio', 'contents' => '9:16'],
            ['name' => 'style', 'contents' => 'Photorealistic'],
        ];
        // GeminiGen expects each ref URL as its own multipart entry.
        foreach ($refs as $url) {
            $multipart[] = ['name' => 'file_urls', 'contents' => $url];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->asMultipart()
                ->post("{$this->baseUrl}/generate_image", $multipart);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->breaker->recordFailure(null, null, $e);
            Log::error('[GeminiGenVideo] keyframe HTTP connection exception', ['ctx' => $contextId, 'error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] keyframe HTTP unexpected exception', ['ctx' => $contextId, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            $errorCode = $response->json('error_code') ?? $response->json('code');
            $this->breaker->recordFailure($response->status(), is_string($errorCode) ? $errorCode : null);
            Log::error('[GeminiGenVideo] keyframe dispatch non-2xx', [
                'ctx' => $contextId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $this->breaker->recordSuccess();

        $uuid = $response->json('uuid');
        if (! is_string($uuid) || $uuid === '') {
            Log::error('[GeminiGenVideo] keyframe 2xx but no uuid', ['ctx' => $contextId, 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        return $uuid;
    }

    /**
     * Dispatch a Veo image-to-video job for a video_rebrand hook/CTA clip. The
     * keyframe (face-gen 9:16) drives the start frame (mode_image=frame, ref sent
     * as ref_images — verified live 2026-06-12). Returns the Veo job uuid (poll
     * key at /history/{uuid}) or null on circuit-open / missing key / HTTP failure.
     */
    public function dispatchVeoClip(string $keyframeUrl, string $prompt, string $aspect = '9:16', ?int $contextId = null): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiGenVideo] GEMINIGEN_API_KEY missing — cannot dispatch Veo', ['ctx' => $contextId]);

            return null;
        }

        if ($this->breaker->state() === 'open') {
            Log::warning('[GeminiGenVideo] dispatchVeoClip skipped — circuit OPEN', ['ctx' => $contextId]);

            return null;
        }

        $multipart = [
            ['name' => 'prompt', 'contents' => $prompt],
            ['name' => 'model', 'contents' => (string) config('services.geminigen.veo_model', 'veo-3.1-fast')],
            ['name' => 'aspect_ratio', 'contents' => $aspect],
            ['name' => 'duration', 'contents' => '6'],
            ['name' => 'resolution', 'contents' => '720p'],
            ['name' => 'mode_image', 'contents' => 'frame'],
            ['name' => 'ref_images', 'contents' => $keyframeUrl],
        ];

        try {
            $response = Http::timeout(90)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->asMultipart()
                ->post("{$this->baseUrl}/video-gen/veo", $multipart);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->breaker->recordFailure(null, null, $e);
            Log::error('[GeminiGenVideo] Veo HTTP connection exception', ['ctx' => $contextId, 'error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] Veo HTTP unexpected exception', ['ctx' => $contextId, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            $errorCode = $response->json('error_code') ?? $response->json('code');
            $this->breaker->recordFailure($response->status(), is_string($errorCode) ? $errorCode : null);
            Log::error('[GeminiGenVideo] Veo dispatch non-2xx', [
                'ctx' => $contextId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $this->breaker->recordSuccess();

        $uuid = $response->json('uuid');
        if (! is_string($uuid) || $uuid === '') {
            Log::error('[GeminiGenVideo] Veo 2xx but no uuid', ['ctx' => $contextId, 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        return $uuid;
    }

    /**
     * Dispatch a GROK (xAI grok-video) image-to-video job for a video_rebrand
     * hook/CTA clip — the FAILOVER generator when Veo can't be used: a public
     * figure is on the keyframe (Veo blocks prominent-people) OR Veo failed
     * PUBLIC_ERROR_AUDIO_FILTERED (Veo 3.x forces audio gen, no off-switch, and
     * its audio filter trips nondeterministically). GROK strips audio on download
     * and is xAI (not Google) so it clears both walls. Proven live 2026-06-14.
     *
     * IMPORTANT: GROK only accepts aspect_ratio 2:3 (9:16 → HTTP 400). The 2:3
     * output is later center-cropped to 4:5 by finalizeVeoClip (same crop math —
     * any source taller than 4:5 works). Returns the GROK job uuid (poll key at
     * /history/{uuid}) or null on circuit-open / missing key / HTTP failure.
     */
    public function dispatchGrokClip(string $keyframeUrl, string $prompt, ?int $contextId = null): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiGenVideo] GEMINIGEN_API_KEY missing — cannot dispatch GROK', ['ctx' => $contextId]);

            return null;
        }

        if ($this->breaker->state() === 'open') {
            Log::warning('[GeminiGenVideo] dispatchGrokClip skipped — circuit OPEN', ['ctx' => $contextId]);

            return null;
        }

        $multipart = [
            ['name' => 'prompt', 'contents' => $prompt],
            ['name' => 'model', 'contents' => (string) config('services.geminigen.video_model', 'grok-3')],
            // GROK rejects 9:16 (HTTP 400) — 2:3 is the only vertical it accepts;
            // cropped 2:3 → 4:5 downstream in finalizeVeoClip.
            ['name' => 'aspect_ratio', 'contents' => '2:3'],
            ['name' => 'mode', 'contents' => 'custom'],
            ['name' => 'duration', 'contents' => '6'],
            ['name' => 'resolution', 'contents' => '720p'],
            ['name' => 'file_urls', 'contents' => $keyframeUrl],
        ];

        try {
            $response = Http::timeout(90)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->asMultipart()
                ->post("{$this->baseUrl}/video-gen/grok", $multipart);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->breaker->recordFailure(null, null, $e);
            Log::error('[GeminiGenVideo] GROK clip HTTP connection exception', ['ctx' => $contextId, 'error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] GROK clip HTTP unexpected exception', ['ctx' => $contextId, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            $errorCode = $response->json('error_code') ?? $response->json('code');
            $this->breaker->recordFailure($response->status(), is_string($errorCode) ? $errorCode : null);
            Log::error('[GeminiGenVideo] GROK clip dispatch non-2xx', [
                'ctx' => $contextId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $this->breaker->recordSuccess();

        $uuid = $response->json('uuid');
        if (! is_string($uuid) || $uuid === '') {
            Log::error('[GeminiGenVideo] GROK clip 2xx but no uuid', ['ctx' => $contextId, 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        return $uuid;
    }

    /**
     * Download a finished Veo 9:16 MP4, center-crop to 4:5 + scale to 1080×1350
     * (match the carousel slides). Audio kept (Veo clips may carry it). $relOut is
     * the public-disk relative path. Returns the public MP4 URL or null on failure.
     */
    public function finalizeVeoClip(string $remoteVideoUrl, string $relOut): ?string
    {
        try {
            $resp = Http::timeout(120)->get($remoteVideoUrl);
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] Veo video download failed', ['out' => $relOut, 'error' => $e->getMessage()]);

            return null;
        }
        if (! $resp->successful()) {
            Log::error('[GeminiGenVideo] Veo video download non-2xx', ['out' => $relOut, 'status' => $resp->status()]);

            return null;
        }

        $rawPath = $this->writeTempFile('veo-raw-'.uniqid().'.mp4', $resp->body());

        $outPath = Storage::disk('public')->path($relOut);
        SharedDir::ensure(dirname($outPath));

        // 9:16 → 4:5: keep full width, center-crop height to iw*5/4, then normalize
        // to exactly 1080×1350.
        try {
            $result = Process::timeout(150)->run([
                $this->ffmpeg, '-y', '-i', $rawPath,
                '-vf', 'crop=iw:floor(iw*5/4/2)*2:0:(ih-iw*5/4)/2,scale=1080:1350',
                '-c:v', 'libx264', '-crf', '20', '-c:a', 'aac', '-movflags', '+faststart',
                $outPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('[GeminiGenVideo] Veo ffmpeg crop threw', ['out' => $relOut, 'error' => $e->getMessage()]);
            @unlink($outPath);

            return null;
        } finally {
            @unlink($rawPath);
        }

        if (! $result->successful() || ! is_file($outPath)) {
            Log::error('[GeminiGenVideo] Veo ffmpeg crop failed', ['out' => $relOut, 'error' => $result->errorOutput()]);
            @unlink($outPath);

            return null;
        }

        return url('/storage/'.$relOut);
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
        $srcPath = $this->writeTempFile("grok-src-{$igId}-".uniqid().'.png', $resp->body());

        $outRel = "linkedin-carousel/grok-frame-{$igId}.jpg";
        $outPath = Storage::disk('public')->path($outRel);
        SharedDir::ensure(dirname($outPath));

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
            @unlink($srcPath);
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

        $rawPath = $this->writeTempFile("grok-raw-{$igId}-".uniqid().'.mp4', $resp->body());

        $outRel = "linkedin-carousel/grok-hook-{$igId}.mp4";
        $outPath = Storage::disk('public')->path($outRel);
        SharedDir::ensure(dirname($outPath));

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
            @unlink($rawPath);
        }

        if (! $result->successful() || ! is_file($outPath)) {
            Log::error('[GeminiGenVideo] ffmpeg crop failed', ['ig' => $igId, 'error' => $result->errorOutput()]);
            @unlink($outPath);

            return null;
        }

        return url('/storage/'.$outRel);
    }
}
