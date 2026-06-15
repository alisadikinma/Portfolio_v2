<?php

namespace Tests\Feature;

use App\Services\ZernioImageNormalizer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Regression for the draft-163 IG failure: the normalizer wrote the padded PNG
 * with Storage::put() but NEVER checked its return value. Storage::put() returns
 * FALSE (no exception) on a permission-denied write — so the social-crosspost
 * worker (claudesn) writing into a www-data-owned 0755 dir got a silent failure,
 * and a normalized URL pointing at a file that was never written reached Zernio,
 * which 404'd it ("Instagram Image 2: Image not found at the provided URL").
 *
 * The guard now verifies the write landed; otherwise it fails-open to the
 * original URL (worst case = the TRUE pre-existing IG ratio rejection, never a
 * phantom 404).
 */
class ZernioImageNormalizerWriteGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** A valid out-of-range (too-tall, 0.6) PNG written to the public disk. */
    private function seedOutOfRangeSlide(string $relative): void
    {
        $img = imagecreatetruecolor(60, 100); // 0.6 ratio → needs normalization
        imagefill($img, 0, 0, imagecolorallocate($img, 10, 20, 30));
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        Storage::disk('public')->put($relative, $bytes);
    }

    public function test_successful_write_returns_normalized_url_and_persists_file(): void
    {
        Storage::fake('public');
        $relative = 'linkedin-carousel/li-test-slide-02.png';
        $this->seedOutOfRangeSlide($relative);

        $out = (new ZernioImageNormalizer())
            ->normalizeForInstagram(url('/storage/'.$relative));

        $this->assertStringContainsString('/storage/zernio-normalized/', $out);
        // The returned URL MUST point at a file that actually exists on disk.
        $outRelative = 'zernio-normalized/'.sha1($relative).'.png';
        $this->assertTrue(Storage::disk('public')->exists($outRelative));
    }

    public function test_write_failure_fails_open_to_original_url(): void
    {
        $relative = 'linkedin-carousel/li-test-slide-02.png';
        $original = url('/storage/'.$relative);

        // A real out-of-range PNG on the actual public disk so getimagesize works.
        $this->seedOutOfRangeSlide($relative);
        $realPath = Storage::disk('public')->path($relative);

        // Disk whose put() returns false (the permission-denied shape) and whose
        // out-file never exists — exactly the silent-failure production hit.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->with($relative)->andReturnTrue();
        $disk->shouldReceive('path')->with($relative)->andReturn($realPath);
        $disk->shouldReceive('path')->andReturnUsing(fn ($p) => '/tmp/'.$p);
        $disk->shouldReceive('put')->andReturnFalse();
        $disk->shouldReceive('exists')->andReturnFalse(); // out-file check
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $out = (new ZernioImageNormalizer())->normalizeForInstagram($original);

        // No phantom zernio-normalized URL — fail-open to the original.
        $this->assertSame($original, $out);

        @unlink($realPath); // Storage facade is mocked now — clean the real file directly.
    }
}
