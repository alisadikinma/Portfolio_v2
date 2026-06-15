<?php

namespace Tests\Unit;

use App\Services\GeminiGenVideoService;
use PHPUnit\Framework\TestCase;

/**
 * GROK hook keyframe + output video must get a UNIQUE /storage path per render.
 *
 * Root cause of the "Regenerate video uses the old asset" bug (draft 165): both
 * grok-frame-{ig}.jpg and grok-hook-{ig}.mp4 used DETERMINISTIC filenames, and
 * Cloudflare serves /storage with `cache-control: immutable, max-age=30d`. So a
 * regenerate overwrote origin bytes but the unchanged URL kept serving the
 * previously-cached (stale) frame to GROK / video to the admin. Unique filenames
 * force a cache MISS every render (the slide renderer already does this via -vN).
 */
class GeminiGenVideoAssetPathTest extends TestCase
{
    public function test_asset_path_is_unique_per_call(): void
    {
        $a = GeminiGenVideoService::carouselAssetPath('grok-frame', 32, 'jpg');
        $b = GeminiGenVideoService::carouselAssetPath('grok-frame', 32, 'jpg');

        $this->assertNotSame($a, $b, 'Each render must produce a fresh URL to dodge the immutable CDN cache');
    }

    public function test_asset_path_shape(): void
    {
        $frame = GeminiGenVideoService::carouselAssetPath('grok-frame', 32, 'jpg');
        $video = GeminiGenVideoService::carouselAssetPath('grok-hook', 7, 'mp4');

        $this->assertMatchesRegularExpression('#^linkedin-carousel/grok-frame-32-[0-9a-f]{8,}\.jpg$#', $frame);
        $this->assertMatchesRegularExpression('#^linkedin-carousel/grok-hook-7-[0-9a-f]{8,}\.mp4$#', $video);
    }
}
