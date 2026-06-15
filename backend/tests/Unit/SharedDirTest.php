<?php

namespace Tests\Unit;

use App\Support\SharedDir;
use PHPUnit\Framework\TestCase;

/**
 * SharedDir::ensure must leave the directory group-writable (0775) regardless of
 * the process umask. The repurpose video pipeline writes across two users
 * (www-data initial render → claudesn queue-worker re-skin); a umask-downgraded
 * 0755 dir is exactly what broke job #26's hook re-skin with ffmpeg
 * "Permission denied".
 */
class SharedDirTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/shareddir-'.uniqid();
    }

    protected function tearDown(): void
    {
        @exec('rm -rf '.escapeshellarg($this->base));
        parent::tearDown();
    }

    public function test_creates_dir_group_writable_under_strict_umask(): void
    {
        $old = umask(0022); // strict umask that would downgrade mkdir(0775) → 0755
        try {
            $dir = $this->base.'/a/b/composited';
            SharedDir::ensure($dir);

            $this->assertDirectoryExists($dir);
            // Leaf dir (where the slide mp4 is written) must keep the group-write bit.
            $this->assertSame('0775', substr(sprintf('%o', fileperms($dir)), -4));
        } finally {
            umask($old);
        }
    }

    public function test_idempotent_and_forces_mode_on_existing_dir(): void
    {
        $dir = $this->base.'/existing';
        @mkdir($dir, 0755, true); // pre-existing 0755 (the broken state)
        @chmod($dir, 0755);

        SharedDir::ensure($dir);

        clearstatcache(true, $dir);
        $this->assertSame('0775', substr(sprintf('%o', fileperms($dir)), -4));
    }
}
