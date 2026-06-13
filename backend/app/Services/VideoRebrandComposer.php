<?php

namespace App\Services;

use App\Models\RepurposeVideoSlide;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

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
        // Write to the PUBLIC disk + store a full public URL — same convention as
        // the Veo bookends (GeminiGenVideoService::finalizeVeoClip). The source
        // capture stays on the private disk; only the final downloadable slide is
        // published so the Social Studio `<a download>` link resolves for ALL
        // slides (tool + hook + cta), not just the bookends.
        $outRel = "repurpose/{$jobId}/composited/slide_{$slide->slide_index}.mp4";
        $outAbs = Storage::disk('public')->path($outRel);
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

        $publicUrl = url('/storage/' . $outRel);
        $slide->update(['composited_status' => 'done', 'composited_path' => $publicUrl, 'last_error' => null]);

        return $publicUrl;
    }

    /**
     * Composite the CTA ask overlay (#3) onto the finalized CTA Veo clip. Reads
     * the 1080×1350 CTA clip from the public disk (written by finalizeVeoClip),
     * overlays the transparent ask-card PNG, keeps audio, and writes a sibling
     * `_cta.mp4` on the public disk. Returns the new full public URL, or null on
     * failure (caller keeps the plain clip — the ask still ships in the caption).
     */
    public function overlayCta(RepurposeVideoSlide $slide, string $overlayPng): ?string
    {
        $jobId = (int) $slide->repurpose_job_id;
        $inRel = "repurpose/{$jobId}/composited/slide_{$slide->slide_index}.mp4";
        $inAbs = Storage::disk('public')->path($inRel);
        if (!is_file($inAbs)) {
            Log::error('[VideoRebrandComposer] CTA overlay: finalized clip missing', ['slide' => $slide->id, 'in' => $inRel]);
            return null;
        }

        $outRel = "repurpose/{$jobId}/composited/slide_{$slide->slide_index}_cta.mp4";
        $outAbs = Storage::disk('public')->path($outRel);

        $cmd = [
            $this->ffmpegPath, '-y',
            '-i', $inAbs,
            '-i', $overlayPng,
            '-filter_complex', '[0:v][1:v]overlay=0:0[v]',
            '-map', '[v]',
            '-map', '0:a?',
            '-c:v', 'libx264', '-crf', '20',
            '-c:a', 'aac',
            '-movflags', '+faststart',
            $outAbs,
        ];

        try {
            $ok = $this->runFfmpeg($cmd);
        } catch (\Throwable $e) {
            Log::error('[VideoRebrandComposer] CTA overlay exec threw', ['slide' => $slide->id, 'error' => $e->getMessage()]);
            return null;
        }

        if (!$ok) {
            Log::error('[VideoRebrandComposer] CTA overlay ffmpeg failed', ['slide' => $slide->id]);
            return null;
        }

        // Verify the overlaid output is real + non-empty BEFORE dropping the plain
        // clip — ffmpeg can exit 0 yet write a 0-byte file on a disk-full race. If
        // so, keep the plain clip and degrade (caller falls back to it; the ask
        // still ships in the caption). This guard gates a destructive delete, so
        // unlike composeSlide it is mandatory here.
        if (!is_file($outAbs) || filesize($outAbs) === 0) {
            Log::error('[VideoRebrandComposer] CTA overlay produced empty/missing output — keeping plain clip', ['slide' => $slide->id, 'out' => $outRel]);
            @unlink($outAbs);
            return null;
        }

        // The plain clip is now superseded by the overlaid _cta.mp4 and is no
        // longer referenced by composited_path — drop it so it doesn't orphan on
        // disk (the reaper only knows composited_path).
        Storage::disk('public')->delete($inRel);

        return url('/storage/' . $outRel);
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
