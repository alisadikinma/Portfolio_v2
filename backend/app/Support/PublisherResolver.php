<?php

namespace App\Support;

use App\Jobs\PublishViaPubler;
use App\Jobs\PublishViaZernio;
use App\Models\Setting;
use App\Services\PublerPayloadBuilder;
use App\Services\ZernioPayloadBuilder;

/**
 * Per-platform publisher router for the cross-post pipeline (2026-06-15).
 *
 * Zernio is the primary publisher for IG/TikTok/Threads; Publer remains the
 * fallback (and the only path for Facebook, which Zernio handling is out of
 * scope). The active publisher per platform is the `crosspost_publisher_{p}`
 * setting (group=zernio), defaulting to 'zernio' for the three Zernio-capable
 * platforms and 'publer' for anything else.
 *
 * Centralizes the routing so every publish site (auto fan-out, operator
 * Approve, bulk Publish-all, single re-publish) stays consistent — flipping a
 * platform back to Publer, or eventually deleting Publer, is a one-place change.
 *
 * The Publer/Zernio adapter CLASSES are untouched; this only chooses between
 * them and exposes the publisher-aware gate + published-id column the call
 * sites previously hardcoded to Publer.
 */
class PublisherResolver
{
    private const ZERNIO_PLATFORMS = ['instagram', 'tiktok', 'threads'];

    /** The active publisher for a platform: 'zernio' | 'publer'. */
    public static function for(string $platform): string
    {
        if (! in_array($platform, self::ZERNIO_PLATFORMS, true)) {
            return 'publer'; // e.g. facebook — Zernio path not provided
        }

        $value = Setting::where('group', 'zernio')
            ->where('key', "crosspost_publisher_{$platform}")
            ->value('value');

        return $value === 'publer' ? 'publer' : 'zernio';
    }

    /** True when the platform's SELECTED publisher has an account configured. */
    public static function isPlatformEnabled(string $platform): bool
    {
        return self::for($platform) === 'zernio'
            ? ZernioPayloadBuilder::isPlatformEnabled($platform)
            : PublerPayloadBuilder::isPlatformEnabled($platform);
    }

    /** The sibling column holding the published id for the selected publisher. */
    public static function publishedIdColumn(string $platform): string
    {
        return self::for($platform) === 'zernio' ? 'zernio_post_id' : 'publer_post_id';
    }

    /** Dispatch the correct publish job for the platform's selected publisher. */
    public static function dispatchPublish(string $platform, int $siblingPostId): void
    {
        if (self::for($platform) === 'zernio') {
            PublishViaZernio::dispatch($platform, $siblingPostId);
        } else {
            PublishViaPubler::dispatch($platform, $siblingPostId);
        }
    }
}
