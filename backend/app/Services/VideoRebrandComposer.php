<?php

namespace App\Services;

use App\Models\RepurposeVideoSlide;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * video_rebrand Phase D — ffmpeg composite of ONE slide: keep the center 16:9
 * demo region unchanged (cropped via the capture-time luminance band), stack the
 * brand header PNG above + footer PNG below → 1080×1350 (4:5), audio preserved.
 *
 * POC-validated filtergraph:
 *   [0:v]crop=in_w:{crop_h}:0:{crop_y},scale=1080:609[c];[1:v][c][2:v]vstack=inputs=3[v]
 * header(1080×508) + center(1080×609) + footer(1080×233) = 1080×1350.
 *
 * `buildFilter` is pure (unit-tested byte-exact). `composeSlide` execs ffmpeg
 * (ssh|local, mirroring the capture driver).
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
class VideoRebrandComposer
{
    private string $driver;
    private string $sshHost;
    private string $sshUser;
    private string $sshKey;
    private string $ffmpegPath;
    private int $timeout;

    public function __construct()
    {
        $this->driver = (string) config('services.instagram_capture.driver', 'ssh');
        $this->sshHost = (string) config('services.instagram_capture.ssh_host', 'localhost');
        $this->sshUser = (string) config('services.instagram_capture.ssh_user', 'claudesn');
        $this->sshKey = (string) config('services.instagram_capture.ssh_key', '');
        $this->ffmpegPath = (string) config('services.instagram_capture.ffmpeg_path', 'ffmpeg');
        $this->timeout = (int) config('services.instagram_capture.video_timeout', 300);
    }

    /**
     * The POC-validated filtergraph. Uses the per-slide center band detected at
     * capture (crop_y/crop_h); falls back to a proportional centered 16:9 region
     * when the band is missing (defensive — capture always sets it).
     */
    public function buildFilter(RepurposeVideoSlide $slide): string
    {
        if ($slide->crop_h && $slide->crop_y !== null) {
            $crop = "crop=in_w:{$slide->crop_h}:0:{$slide->crop_y}";
        } else {
            // 16:9 of the full width, vertically centered.
            $crop = 'crop=in_w:in_w*9/16:0:(in_h-in_w*9/16)/2';
        }

        return "[0:v]{$crop},scale=1080:609[c];[1:v][c][2:v]vstack=inputs=3[v]";
    }

    /**
     * Composite one slide into a 1080×1350 mp4. Returns the relative composited
     * path on success (and flips composited_status=done), null on failure
     * (composited_status=failed + last_error).
     */
    public function composeSlide(RepurposeVideoSlide $slide, string $headerPng, string $footerPng): ?string
    {
        $jobId = (int) $slide->repurpose_job_id;
        $sourceRel = (string) $slide->source_video_path;
        if ($sourceRel === '') {
            $slide->update(['composited_status' => 'failed', 'last_error' => 'no_source_video']);
            return null;
        }

        $sourceAbs = storage_path('app/' . $sourceRel);
        $outRel = "repurpose/{$jobId}/composited/slide_{$slide->slide_index}.mp4";
        $outAbs = storage_path('app/' . $outRel);
        @mkdir(dirname($outAbs), 0775, true);

        $slide->update(['composited_status' => 'compositing']);

        $filter = $this->buildFilter($slide);
        $cmd = [
            $this->ffmpegPath, '-y',
            '-i', $sourceAbs,
            '-i', $headerPng,
            '-i', $footerPng,
            '-filter_complex', $filter,
            '-map', '[v]',
            '-map', '0:a?',           // audio optional (some slides are silent)
            '-c:v', 'libx264', '-crf', '20',
            '-c:a', 'aac',
            '-movflags', '+faststart',
            $outAbs,
        ];

        try {
            $ok = $this->runFfmpeg($cmd);
        } catch (\Throwable $e) {
            Log::error('[VideoRebrandComposer] exec threw', ['slide' => $slide->id, 'error' => $e->getMessage()]);
            $slide->update(['composited_status' => 'failed', 'last_error' => 'compose_exception: ' . $e->getMessage()]);
            return null;
        }

        if (!$ok) {
            $slide->update(['composited_status' => 'failed', 'last_error' => 'ffmpeg_failed']);
            return null;
        }

        $slide->update(['composited_status' => 'done', 'composited_path' => $outRel, 'last_error' => null]);

        return $outRel;
    }

    /**
     * Exec ffmpeg (ssh | local). Protected for test seams; Process::fake also
     * intercepts it directly. Returns true on exit 0.
     *
     * @param array<int,string> $cmd
     */
    protected function runFfmpeg(array $cmd): bool
    {
        if ($this->driver === 'local') {
            return Process::timeout($this->timeout)->run($cmd)->successful();
        }

        $remoteParts = array_map('escapeshellarg', $cmd);
        $remoteCmd = 'bash -lc ' . escapeshellarg('source ~/.profile 2>/dev/null; ' . implode(' ', $remoteParts));

        return Process::timeout($this->timeout)->run($this->sshCommand($remoteCmd))->successful();
    }

    private function sshCommand(string $remoteCommand): string
    {
        $keyOption = $this->sshKey !== '' ? '-i ' . escapeshellarg($this->sshKey) : '';
        return trim("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$this->sshUser}@{$this->sshHost} {$remoteCommand}");
    }
}
