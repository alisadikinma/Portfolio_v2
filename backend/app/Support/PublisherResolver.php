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
 * Zernio is the primary publisher for IG/TikTok/Threads/Reddit/Facebook/YouTube
 * (Facebook migrated off Publer on 2026-06-16). The active publisher per platform
 * is the `crosspost_publisher_{p}` setting (group=zernio): 'zernio' (default for a
 * Zernio-capable platform), 'publer' (fallback), or 'off' (hard-disabled — never
 * publishes; used to default Reddit off until its deploy live-probe passes).
 * Anything outside the Zernio-capable set falls back to 'publer'.
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
    private const ZERNIO_PLATFORMS = ['instagram', 'tiktok', 'threads', 'reddit', 'facebook', 'youtube'];

    /** The active publisher for a platform: 'zernio' | 'publer' | 'off'. */
    public static function for(string $platform): string
    {
        if (! in_array($platform, self::ZERNIO_PLATFORMS, true)) {
            return 'publer'; // e.g. pinterest — no Zernio path
        }

        $value = Setting::where('group', 'zernio')
            ->where('key', "crosspost_publisher_{$platform}")
            ->value('value');

        return match ($value) {
            'publer' => 'publer',
            'off' => 'off', // hard-disabled — never publishes (e.g. Reddit pre-probe)
            default => 'zernio',
        };
    }

    /** True when the platform's SELECTED publisher has an account configured. */
    public static function isPlatformEnabled(string $platform): bool
    {
        return match (self::for($platform)) {
            'zernio' => ZernioPayloadBuilder::isPlatformEnabled($platform),
            'publer' => PublerPayloadBuilder::isPlatformEnabled($platform),
            default => false, // 'off' — disabled regardless of account config
        };
    }

    /** The sibling column holding the published id for the selected publisher. */
    public static function publishedIdColumn(string $platform): string
    {
        // 'off' never dispatches, so its column is never read — default to zernio's.
        return self::for($platform) === 'publer' ? 'publer_post_id' : 'zernio_post_id';
    }

    /** Dispatch the correct publish job for the platform's selected publisher. */
    public static function dispatchPublish(string $platform, int $siblingPostId): void
    {
        match (self::for($platform)) {
            'zernio' => PublishViaZernio::dispatch($platform, $siblingPostId),
            'publer' => PublishViaPubler::dispatch($platform, $siblingPostId),
            default => null, // 'off' — no-op, never publishes
        };
    }
}
