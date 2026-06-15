<?php

namespace App\Services;

use App\Support\SharedDir;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Ensures a carousel slide image fits Instagram's carousel aspect-ratio window
 * (0.75–1.91, width/height) before it's handed to Zernio. IG rejects the whole
 * post with HTTP 400 if ANY item is out of range (production: draft 153 had a
 * slide at 0.75:1 → "Aspect ratio … is outside Instagram's allowed range").
 *
 * Out-of-range slides are letterboxed onto a compliant 4:5 (too-tall) or ~1.9:1
 * (too-wide) canvas — the original is centered, padding sampled from the slide's
 * top-left pixel so the bars blend into a solid background. The padded copy is
 * cached under storage/app/public/zernio-normalized/ and reused (idempotent).
 *
 * Fail-open: any resolution/decode/encode error returns the ORIGINAL url — the
 * worst case is the pre-existing IG rejection, never a crash or a dropped slide.
 */
class ZernioImageNormalizer
{
    /** Instagram carousel allows width/height in [0.75, 1.91]. */
    private const IG_MIN_RATIO = 0.75;

    private const IG_MAX_RATIO = 1.91;

    /** Targets safely INSIDE the window (avoid boundary rounding rejections). */
    private const TARGET_TALL_RATIO = 0.8;   // 4:5

    private const TARGET_WIDE_RATIO = 1.9;

    private const OUTPUT_DIR = 'zernio-normalized';

    private ?ImageManager $manager = null;

    /** Pure ratio gate — true when w/h falls outside IG's carousel window. */
    public function needsNormalization(int $width, int $height): bool
    {
        if ($width <= 0 || $height <= 0) {
            return false;
        }
        $ratio = $width / $height;

        return $ratio < self::IG_MIN_RATIO || $ratio > self::IG_MAX_RATIO;
    }

    /**
     * Padded canvas dimensions that bring (w,h) inside the IG window while
     * preserving the whole original (letterbox). Pure — no I/O.
     *
     * @return array{0:int,1:int} [targetWidth, targetHeight]
     */
    public function targetDimensions(int $width, int $height): array
    {
        if (! $this->needsNormalization($width, $height)) {
            return [$width, $height];
        }
        $ratio = $width / $height;

        // Too tall (ratio < min) → widen the canvas to TARGET_TALL_RATIO.
        if ($ratio < self::IG_MIN_RATIO) {
            return [(int) round($height * self::TARGET_TALL_RATIO), $height];
        }

        // Too wide (ratio > max) → heighten the canvas to TARGET_WIDE_RATIO.
        return [$width, (int) round($width / self::TARGET_WIDE_RATIO)];
    }

    /**
     * Return an app-hosted URL guaranteed to satisfy IG's ratio window. If the
     * slide is already in range (or the URL can't be resolved to a local file),
     * the original URL is returned unchanged.
     */
    public function normalizeForInstagram(string $url): string
    {
        try {
            $relative = $this->relativePathFromUrl($url);
            if ($relative === null) {
                return $url;
            }

            $disk = Storage::disk('public');
            if (! $disk->exists($relative)) {
                return $url;
            }

            $sourceAbs = $disk->path($relative);
            $size = @getimagesize($sourceAbs);
            if ($size === false) {
                return $url;
            }
            [$w, $h] = $size;
            if (! $this->needsNormalization((int) $w, (int) $h)) {
                return $url;
            }

            $outRelative = self::OUTPUT_DIR.'/'.sha1($relative).'.png';
            $outAbs = $disk->path($outRelative);
            if (file_exists($outAbs) && filemtime($outAbs) >= filemtime($sourceAbs)) {
                return url('/storage/'.$outRelative); // cached
            }

            [$tw, $th] = $this->targetDimensions((int) $w, (int) $h);
            $image = $this->manager()->read($sourceAbs);
            $bg = $image->pickColor(0, 0); // blend bars into the slide's background
            $image->resizeCanvas($tw, $th, background: $bg, position: 'center');

            // The social-crosspost queue worker runs as `claudesn`, but this dir is
            // often first created by `www-data` (php-fpm) at mode 0755 — the worker
            // (group member, no group-write) then can't write into it. Force 0775
            // so whichever user writes first, the other can too (same class as the
            // video_rebrand SharedDir fix). Production: draft 163 IG publish failed
            // with "Image 2: Image not found" because the write silently failed.
            SharedDir::ensure(dirname($outAbs));

            // Storage::put() returns FALSE on a write failure (e.g. permission
            // denied) WITHOUT throwing — so a phantom normalized URL pointing at a
            // file that was never written would slip past the catch below and reach
            // Zernio, which 404s it ("Image not found at the provided URL"). Verify
            // the write actually landed; otherwise fail-open to the original URL
            // (worst case: the pre-existing IG ratio rejection — a TRUE error).
            $ok = $disk->put($outRelative, (string) $image->toPng());
            if ($ok === false || ! $disk->exists($outRelative)) {
                Log::warning('[ZernioImageNormalizer] write failed — using original', [
                    'out' => $outRelative, 'src' => $relative, 'put_result' => $ok,
                ]);

                return $url;
            }

            Log::info('[ZernioImageNormalizer] padded slide to IG ratio', [
                'from' => "{$w}x{$h}", 'to' => "{$tw}x{$th}", 'src' => $relative,
            ]);

            return url('/storage/'.$outRelative);
        } catch (\Throwable $e) {
            Log::warning('[ZernioImageNormalizer] normalize failed — using original', [
                'url' => $url, 'error' => $e->getMessage(),
            ]);

            return $url;
        }
    }

    /** Map an app-hosted /storage/... URL to its public-disk relative path. */
    private function relativePathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }
        $marker = '/storage/';
        $pos = strpos($path, $marker);
        if ($pos === false) {
            return null;
        }

        return ltrim(substr($path, $pos + strlen($marker)), '/') ?: null;
    }

    private function manager(): ImageManager
    {
        return $this->manager ??= new ImageManager(
            extension_loaded('imagick') ? new ImagickDriver() : new GdDriver()
        );
    }
}
