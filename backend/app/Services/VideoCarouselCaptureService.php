<?php

namespace App\Services;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * video_rebrand Phase B — downloads a source IG VIDEO carousel's slides via
 * yt-dlp (headless, no login — POC-proven), extracts a poster frame per slide
 * (ffmpeg) + probes dimensions/audio (ffprobe), and writes one
 * repurpose_video_slides row per slide (role='tool', indexed 1..N so index 0
 * is reserved for the Veo hook prepended in Phase E).
 *
 * Mirrors InstagramCaptureService's exec/parse pattern (ssh | local). The heavy
 * lifting lives in a node wrapper (scripts/repurpose/ig-video-capture.cjs) that
 * emits a single JSON line; this service maps that into DB rows. `runCapture`
 * is protected so tests subclass it to inject canned output.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
class VideoCarouselCaptureService
{
    private string $driver;
    private string $sshHost;
    private string $sshUser;
    private string $sshKey;
    private string $nodePath;
    private string $scriptPath;
    private string $ytdlpPath;
    private string $ffmpegPath;
    private string $ffprobePath;
    private int $timeout;

    public function __construct()
    {
        $this->driver = (string) config('services.instagram_capture.driver', 'ssh');
        $this->sshHost = (string) config('services.instagram_capture.ssh_host', 'localhost');
        $this->sshUser = (string) config('services.instagram_capture.ssh_user', 'claudesn');
        $this->sshKey = (string) config('services.instagram_capture.ssh_key', '');
        $this->nodePath = (string) config('services.instagram_capture.node_path', 'node');
        $this->scriptPath = (string) config('services.instagram_capture.video_script_path', '');
        $this->ytdlpPath = (string) config('services.instagram_capture.ytdlp_path', 'yt-dlp');
        $this->ffmpegPath = (string) config('services.instagram_capture.ffmpeg_path', 'ffmpeg');
        $this->ffprobePath = (string) config('services.instagram_capture.ffprobe_path', 'ffprobe');
        $this->timeout = (int) config('services.instagram_capture.video_timeout', 300);
    }

    /**
     * Download + persist the source carousel's video slides. On success creates
     * repurpose_video_slides rows and sets $job->slides_path.
     *
     * @return array{success: bool, count: int, slides: array<int,array<string,mixed>>, error: string|null}
     */
    public function capture(RepurposeJob $job): array
    {
        $url = (string) $job->source_url;

        // SSRF re-guard — never exec yt-dlp against a non-IG host.
        if (!$this->isInstagramUrl($url)) {
            return $this->fail('invalid_url_host');
        }
        if ($this->scriptPath === '') {
            return $this->fail('video_script_not_configured');
        }

        $relDir = 'repurpose/' . $job->id;
        $outDir = storage_path('app/' . $relDir . '/video');

        try {
            $raw = $this->runCapture($url, $outDir);
        } catch (\Throwable $e) {
            Log::error('[VideoCarouselCapture] exec threw', ['job' => $job->id, 'error' => $e->getMessage()]);
            return $this->fail('exec_error: ' . $e->getMessage());
        }

        $parsed = $this->parseOutput($raw['stdout'] ?? '');
        if ($parsed === null) {
            Log::error('[VideoCarouselCapture] unparseable output', [
                'job' => $job->id,
                'exit' => $raw['exit'] ?? null,
                'stderr_head' => mb_substr((string) ($raw['stderr'] ?? ''), 0, 500),
                'stdout_head' => mb_substr((string) ($raw['stdout'] ?? ''), 0, 500),
            ]);
            return $this->fail('capture_unparseable');
        }

        $slides = array_values((array) ($parsed['slides'] ?? []));
        if (!($parsed['ok'] ?? false) || count($slides) < 1) {
            return $this->fail((string) ($parsed['error'] ?? 'no_video_items'));
        }

        // Persist one tool-slide row per downloaded video, indexed 1..N.
        $index = 1;
        foreach ($slides as $slide) {
            $file = (string) ($slide['file'] ?? '');
            $poster = (string) ($slide['poster'] ?? '');
            if ($file === '') {
                continue;
            }
            RepurposeVideoSlide::create([
                'repurpose_job_id' => $job->id,
                'slide_index' => $index,
                'role' => RepurposeVideoSlide::ROLE_TOOL,
                'source_video_path' => $relDir . '/video/' . $file,
                'poster_path' => $poster !== '' ? $relDir . '/video/' . $poster : null,
                // Deterministic center 16:9 band detected at capture (row-luminance).
                'crop_y' => isset($slide['crop_y']) ? (int) $slide['crop_y'] : null,
                'crop_h' => isset($slide['crop_h']) ? (int) $slide['crop_h'] : null,
                'composited_status' => 'pending',
            ]);
            $index++;
        }

        $job->update(['slides_path' => $relDir]);

        Log::info('[VideoCarouselCapture] captured', ['job' => $job->id, 'count' => $index - 1]);

        return [
            'success' => true,
            'count' => $index - 1,
            'slides' => $slides,
            'error' => null,
        ];
    }

    /**
     * Exec the node wrapper (ssh | local). Returns raw streams; caller parses the
     * single JSON line. Protected so tests subclass to inject canned output.
     *
     * @return array{stdout: string, stderr: string, exit: int}
     */
    protected function runCapture(string $url, string $outDir): array
    {
        $args = [
            '--url', $url,
            '--out', $outDir,
            '--timeout', (string) $this->timeout,
            '--ytdlp', $this->ytdlpPath,
            '--ffmpeg', $this->ffmpegPath,
            '--ffprobe', $this->ffprobePath,
        ];

        $procTimeout = $this->timeout + 30;

        if ($this->driver === 'local') {
            $cmd = array_merge([$this->nodePath, $this->scriptPath], $args);
            $result = Process::timeout($procTimeout)->run($cmd);
        } else {
            $remoteParts = array_map('escapeshellarg', array_merge([$this->nodePath, $this->scriptPath], $args));
            $remoteCmd = 'bash -lc ' . escapeshellarg('source ~/.profile 2>/dev/null; ' . implode(' ', $remoteParts));
            $result = Process::timeout($procTimeout)->run($this->sshCommand($remoteCmd));
        }

        return [
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
            'exit' => $result->exitCode() ?? -1,
        ];
    }

    /**
     * Parse the wrapper's last JSON line (tolerates leading log noise).
     *
     * @return array<string,mixed>|null
     */
    private function parseOutput(string $stdout): ?array
    {
        $stdout = trim($stdout);
        if ($stdout === '') {
            return null;
        }
        $lines = preg_split('/\r?\n/', $stdout) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded) && array_key_exists('ok', $decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private function isInstagramUrl(string $url): bool
    {
        return (bool) preg_match(
            '~^https?://(?:www\.)?instagram\.com/(?:p|reel|reels|tv)/[A-Za-z0-9_-]+/?~i',
            $url
        );
    }

    private function sshCommand(string $remoteCommand): string
    {
        $keyOption = $this->sshKey !== '' ? '-i ' . escapeshellarg($this->sshKey) : '';
        return trim("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$this->sshUser}@{$this->sshHost} {$remoteCommand}");
    }

    /**
     * @return array{success: false, count: int, slides: array<int,mixed>, error: string}
     */
    private function fail(string $error): array
    {
        return ['success' => false, 'count' => 0, 'slides' => [], 'error' => $error];
    }
}
