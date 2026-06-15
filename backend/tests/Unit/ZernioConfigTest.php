<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Phase A — Zernio config block.
 *
 * Mirrors the existing `social-cross-post.publer` block but WITHOUT the
 * media-poll keys (Zernio takes public CDN URLs directly — no upload/poll step).
 */
class ZernioConfigTest extends TestCase
{
    public function test_zernio_base_url_and_api_path(): void
    {
        $this->assertSame('https://zernio.com', config('social-cross-post.zernio.base_url'));
        $this->assertSame('/api/v1', config('social-cross-post.zernio.api_path'));
    }

    public function test_zernio_block_has_no_media_poll_keys(): void
    {
        $zernio = config('social-cross-post.zernio');

        $this->assertIsArray($zernio);
        $this->assertArrayHasKey('enabled', $zernio);
        $this->assertArrayHasKey('max_retries', $zernio);
        $this->assertArrayHasKey('http_timeout_seconds', $zernio);
        $this->assertArrayHasKey('schedule_enabled', $zernio);

        // Zernio needs no async media ingest — these Publer-only keys must be absent.
        $this->assertArrayNotHasKey('media_poll_tries', $zernio);
        $this->assertArrayNotHasKey('media_poll_interval_ms', $zernio);
    }
}
