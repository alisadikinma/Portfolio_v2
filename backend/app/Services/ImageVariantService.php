<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Generates responsive WebP variants + base64 LQIP placeholder for a
 * source image stored on the public disk.
 *
 * Output map (returned by generate()):
 *   {
 *     '320w':  '/storage/projects/49_x-320w.webp',
 *     '640w':  '/storage/projects/49_x-640w.webp',
 *     '1024w': '/storage/projects/49_x-1024w.webp',
 *     '1920w': '/storage/projects/49_x-1920w.webp',
 *     'lqip':  'data:image/jpeg;base64,/9j/4AAQSkZJ...'
 *   }
 *
 * Frontend BaseImage component renders <picture><source srcset> from
 * width keys + uses the lqip dataURI as a blur background until the
 * real image loads. Original source file is preserved.
 */
class ImageVariantService
{
    /** Width breakpoints. Variants smaller than source width only. */
    private const WIDTHS = [320, 640, 1024, 1920];

    /** WebP quality — sweet spot between size and visual fidelity. */
    private const WEBP_QUALITY = 82;

    /** LQIP renders to ~1-2KB base64 — visible blur, near-zero payload. */
    private const LQIP_WIDTH = 24;

    private const LQIP_QUALITY = 30;

    private ImageManager $manager;

    public function __construct()
    {
        // Imagick is faster + better quality than GD when available.
        $this->manager = new ImageManager(
            extension_loaded('imagick') ? new ImagickDriver() : new GdDriver()
        );
    }

    /**
     * Generate variants for a stored image. Idempotent — skips variants
     * already present with mtime newer than source.
     *
     * @param  string  $relativePath  Path under Storage::disk('public') —
     *                                e.g., 'projects/49_x.png'. Returns
     *                                empty array if missing or unreadable.
     */
    public function generate(string $relativePath): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            Log::warning('[ImageVariant] source not found on public disk', [
                'path' => $relativePath,
            ]);
            return [];
        }

        try {
            $sourceAbs = $disk->path($relativePath);
            $image = $this->manager->read($sourceAbs);
            $sourceWidth = $image->width();

            $variants = [];
            $pathInfo = pathinfo($relativePath);
            $dir = ($pathInfo['dirname'] === '.' || $pathInfo['dirname'] === '')
                ? ''
                : $pathInfo['dirname'].'/';
            $base = $pathInfo['filename'];

            foreach (self::WIDTHS as $width) {
                if ($width >= $sourceWidth) {
                    continue; // never upscale
                }

                $variantRelative = "{$dir}{$base}-{$width}w.webp";
                $variantAbs = $disk->path($variantRelative);

                // Idempotent skip
                if (file_exists($variantAbs) && filemtime($variantAbs) >= filemtime($sourceAbs)) {
                    $variants["{$width}w"] = '/storage/'.$variantRelative;
                    continue;
                }

                // Each transform reads fresh — Intervention v3 mutates the
                // working image instance, and clone semantics aren't always
                // safe across drivers.
                $variant = $this->manager->read($sourceAbs)
                    ->scaleDown(width: $width)
                    ->toWebp(quality: self::WEBP_QUALITY);

                $disk->put($variantRelative, (string) $variant);
                $variants["{$width}w"] = '/storage/'.$variantRelative;
            }

            // LQIP: 24px-wide JPEG q30 → base64 dataURI. ~1KB on average.
            $lqip = $this->manager->read($sourceAbs)
                ->scaleDown(width: self::LQIP_WIDTH)
                ->toJpeg(quality: self::LQIP_QUALITY);

            $variants['lqip'] = 'data:image/jpeg;base64,'.base64_encode((string) $lqip);

            return $variants;
        } catch (\Throwable $e) {
            Log::error('[ImageVariant] generate failed', [
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Normalize whatever shape the model column holds into a relative
     * public-disk path. Accepts:
     *   - 'projects/49_x.png'
     *   - '/storage/projects/49_x.png'
     *   - 'https://alisadikinma.com/storage/projects/49_x.png'
     *
     * Returns null if input is empty or unparseable.
     */
    public static function normalizePath(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        // Strip protocol + host
        $path = preg_replace('#^https?://[^/]+#', '', $stored);
        // Strip leading slashes + /storage/ prefix
        $path = preg_replace('#^/?storage/#', '', $path ?? '');

        $path = ltrim($path ?? '', '/');

        return $path === '' ? null : $path;
    }
}
