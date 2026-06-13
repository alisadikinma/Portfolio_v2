<?php

namespace Tests\Feature;

use App\Services\VideoCarouselCaptureService;
use Tests\TestCase;

/**
 * IG now requires auth for yt-dlp media download (anonymous → "login required /
 * rate-limit"). The capture service passes a Netscape cookies.txt to the cjs via
 * `--cookies` when `services.instagram_capture.ytdlp_cookies_path` is set, and
 * omits the flag entirely when it isn't (anonymous fallback).
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase B
 */
class VideoCaptureCookiesArgTest extends TestCase
{
    /** Expose the protected arg builder. Construct fresh — the service reads
     *  the cookies path in its constructor, so config must be set first. */
    private function buildArgs(): array
    {
        $svc = new VideoCarouselCaptureService();
        $ref = new \ReflectionMethod($svc, 'buildCaptureArgs');
        $ref->setAccessible(true);

        return $ref->invoke($svc, 'https://instagram.com/p/X', '/tmp/out');
    }

    public function test_appends_cookies_flag_when_configured(): void
    {
        config()->set('services.instagram_capture.ytdlp_cookies_path', '/home/claudesn/ig-cookies.txt');

        $args = $this->buildArgs();

        $i = array_search('--cookies', $args, true);
        $this->assertNotFalse($i, '--cookies flag missing');
        $this->assertSame('/home/claudesn/ig-cookies.txt', $args[$i + 1]);
    }

    public function test_omits_cookies_flag_when_not_configured(): void
    {
        config()->set('services.instagram_capture.ytdlp_cookies_path', '');

        $this->assertNotContains('--cookies', $this->buildArgs());
    }
}
