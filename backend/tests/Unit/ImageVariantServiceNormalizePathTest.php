<?php

namespace Tests\Unit;

use App\Services\ImageVariantService;
use PHPUnit\Framework\TestCase;

/**
 * normalizePath() is the boundary translator between whatever shape the
 * model column stores (legacy absolute URL vs new relative path vs
 * /storage prefix) and the relative public-disk path the rest of the
 * service needs. Pure function — no DB, no filesystem.
 */
class ImageVariantServiceNormalizePathTest extends TestCase
{
    public function test_returns_null_for_null_input(): void
    {
        $this->assertNull(ImageVariantService::normalizePath(null));
    }

    public function test_returns_null_for_empty_string(): void
    {
        $this->assertNull(ImageVariantService::normalizePath(''));
    }

    public function test_passes_through_already_relative_path(): void
    {
        $this->assertSame(
            'projects/49_x.png',
            ImageVariantService::normalizePath('projects/49_x.png')
        );
    }

    public function test_strips_leading_storage_prefix(): void
    {
        $this->assertSame(
            'projects/49_x.png',
            ImageVariantService::normalizePath('/storage/projects/49_x.png')
        );
    }

    public function test_strips_storage_prefix_without_leading_slash(): void
    {
        $this->assertSame(
            'projects/49_x.png',
            ImageVariantService::normalizePath('storage/projects/49_x.png')
        );
    }

    public function test_strips_protocol_and_host_from_absolute_url(): void
    {
        $this->assertSame(
            'projects/49_x.png',
            ImageVariantService::normalizePath('https://alisadikinma.com/storage/projects/49_x.png')
        );
    }

    public function test_strips_protocol_host_from_http_url(): void
    {
        $this->assertSame(
            'uploads/branding/logo.png',
            ImageVariantService::normalizePath('http://localhost/storage/uploads/branding/logo.png')
        );
    }

    public function test_handles_arbitrary_subpath_after_storage(): void
    {
        $this->assertSame(
            'gallery/items/123/photo.jpg',
            ImageVariantService::normalizePath('https://cdn.example.com/storage/gallery/items/123/photo.jpg')
        );
    }

    public function test_returns_null_when_only_slashes(): void
    {
        $this->assertNull(ImageVariantService::normalizePath('///'));
    }

    public function test_preserves_filename_with_dashes_and_dots(): void
    {
        $this->assertSame(
            'projects/thumbnail/49_dlp-form-request-cybersecurity.png',
            ImageVariantService::normalizePath('/storage/projects/thumbnail/49_dlp-form-request-cybersecurity.png')
        );
    }
}
