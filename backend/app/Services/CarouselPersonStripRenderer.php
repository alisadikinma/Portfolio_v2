<?php

namespace App\Services;

use App\Support\SharedDir;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * people_spotlight composite (2026-06-17) — drops real cropped face cut-outs
 * into the reserved photo band of an already-rendered carousel slide PNG by
 * exec'ing scripts/repurpose/carousel-person-strip.cjs (Playwright HTML→PNG).
 *
 * Thin node-invoker (mirrors VideoChromeRenderer): the base slide becomes the
 * background, the faces are framed pinned-polaroids in the band, one screenshot
 * = the final composited PNG. Returns the absolute output path, or null on any
 * failure so the caller keeps the plain rendered slide (graceful degrade).
 *
 * @see CarouselPersonPhotoEnricher (sets person_photo_refs this consumes)
 * @see scripts/repurpose/carousel-person-strip.cjs
 */
class CarouselPersonStripRenderer
{
    private string $driver;
    private string $sshHost;
    private string $sshUser;
    private string $sshKey;
    private string $nodePath;
    private string $scriptPath;
    private int $timeout;
    private ?ImageManager $manager = null;

    public function __construct()
    {
        $this->driver = (string) config('services.instagram_capture.driver', 'ssh');
        $this->sshHost = (string) config('services.instagram_capture.ssh_host', 'localhost');
        $this->sshUser = (string) config('services.instagram_capture.ssh_user', 'claudesn');
        $this->sshKey = (string) config('services.instagram_capture.ssh_key', '');
        $this->nodePath = (string) config('services.instagram_capture.node_path', 'node');
        $this->scriptPath = (string) config('services.instagram_capture.person_strip_script_path', '');
        $this->timeout = 120;
    }

    /**
     * Composite the face cut-outs into the slide's reserved band.
     *
     * @param  string  $baseAbs   Absolute path to the rendered slide PNG.
     * @param  array<int,array{path:string,name?:string,role?:string}>  $faces  Local cut-out paths + labels.
     * @param  array{y:float,h:float}  $band  Normalized band geometry.
     * @param  string  $outAbs    Absolute output path for the composited PNG.
     * @return bool  True when the output file was produced.
     */
    public function render(string $baseAbs, array $faces, array $band, string $outAbs): bool
    {
        if ($this->scriptPath === '') {
            Log::error('[CarouselPersonStrip] person_strip_script_path not configured');

            return false;
        }
        if (! is_file($baseAbs)) {
            Log::warning('[CarouselPersonStrip] base slide missing', ['base' => $baseAbs]);

            return false;
        }

        // Only faces whose local file actually exists reach the composite.
        $clean = [];
        foreach ($faces as $f) {
            $path = (string) ($f['path'] ?? '');
            if ($path !== '' && is_file($path)) {
                $clean[] = [
                    'path' => $path,
                    'name' => (string) ($f['name'] ?? ''),
                    'role' => (string) ($f['role'] ?? ''),
                ];
            }
        }
        if ($clean === []) {
            return false;
        }

        [$w, $h] = $this->dimensions($baseAbs);

        SharedDir::ensure(dirname($outAbs));

        $args = [
            '--base', $baseAbs,
            '--width', (string) $w,
            '--height', (string) $h,
            '--faces', json_encode(array_values($clean), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            '--band-y', (string) ($band['y'] ?? 0.12),
            '--band-h', (string) ($band['h'] ?? 0.26),
            '--out', $outAbs,
        ];

        try {
            $ok = $this->runStrip($args);
        } catch (\Throwable $e) {
            Log::error('[CarouselPersonStrip] exec threw', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $ok || ! is_file($outAbs)) {
            Log::error('[CarouselPersonStrip] render failed', ['out' => $outAbs]);

            return false;
        }

        return true;
    }

    /** @param array<int,string> $args */
    protected function runStrip(array $args): bool
    {
        if ($this->driver === 'local') {
            return Process::timeout($this->timeout)
                ->run(array_merge([$this->nodePath, $this->scriptPath], $args))
                ->successful();
        }
        $remoteParts = array_map('escapeshellarg', array_merge([$this->nodePath, $this->scriptPath], $args));
        $remoteCmd = 'bash -lc ' . escapeshellarg('source ~/.profile 2>/dev/null; ' . implode(' ', $remoteParts));

        return Process::timeout($this->timeout)->run($this->sshCommand($remoteCmd))->successful();
    }

    /** @return array{0:int,1:int} [width, height] — falls back to 4:5 1080x1350 on read failure. */
    private function dimensions(string $baseAbs): array
    {
        try {
            $img = $this->manager()->read($baseAbs);

            return [$img->width(), $img->height()];
        } catch (\Throwable $e) {
            return [1080, 1350];
        }
    }

    private function manager(): ImageManager
    {
        return $this->manager ??= new ImageManager(
            extension_loaded('imagick') ? new ImagickDriver() : new GdDriver()
        );
    }

    private function sshCommand(string $remoteCommand): string
    {
        $keyOption = $this->sshKey !== '' ? '-i ' . escapeshellarg($this->sshKey) : '';

        return trim("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$this->sshUser}@{$this->sshHost} {$remoteCommand}");
    }
}
