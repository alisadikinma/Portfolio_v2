<?php

namespace Tests\Unit;

use App\Services\CarouselPersonStripRenderer;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class CarouselPersonStripRendererTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/person-strip-' . uniqid();
        @mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp . '/*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    private function jpg(string $name, int $w = 1080, int $h = 1350): string
    {
        $mgr = new ImageManager(extension_loaded('imagick') ? new ImagickDriver() : new GdDriver());
        $path = $this->tmp . '/' . $name;
        file_put_contents($path, (string) $mgr->create($w, $h)->fill('#335577')->toJpeg());

        return $path;
    }

    /** Renderer whose node call is replaced by a seam that records args + touches the out file. */
    private function renderer(bool $succeed, ?array &$capturedArgs): CarouselPersonStripRenderer
    {
        // Force local driver + a non-empty script path via config so the guard passes.
        config()->set('services.instagram_capture.driver', 'local');
        config()->set('services.instagram_capture.person_strip_script_path', '/fake/carousel-person-strip.cjs');

        return new class($succeed, $capturedArgs) extends CarouselPersonStripRenderer {
            public function __construct(private bool $ok, private ?array &$cap)
            {
                parent::__construct();
            }

            protected function runStrip(array $args): bool
            {
                $this->cap = $args;
                if ($this->ok) {
                    // Emulate the node script writing the output file.
                    $i = array_search('--out', $args, true);
                    if ($i !== false && isset($args[$i + 1])) {
                        file_put_contents($args[$i + 1], 'png');
                    }
                }

                return $this->ok;
            }
        };
    }

    public function test_it_builds_args_and_returns_true_when_output_written(): void
    {
        $base = $this->jpg('base.jpg', 1080, 1350);
        $face = $this->jpg('face.jpg', 200, 200);
        $out = $this->tmp . '/out.png';

        $args = null;
        $r = $this->renderer(true, $args);

        $ok = $r->render($base, [['path' => $face, 'name' => 'Ashish Vaswani', 'role' => 'lead author']], ['y' => 0.12, 'h' => 0.26], $out);

        $this->assertTrue($ok);
        $this->assertFileExists($out);

        // Args carry base, real dimensions, faces json, band, out.
        $this->assertContains('--base', $args);
        $wi = array_search('--width', $args, true);
        $this->assertSame('1080', $args[$wi + 1]);
        $hi = array_search('--height', $args, true);
        $this->assertSame('1350', $args[$hi + 1]);
        $fi = array_search('--faces', $args, true);
        $this->assertStringContainsString('Ashish Vaswani', $args[$fi + 1]);
    }

    public function test_it_drops_faces_with_missing_files_and_fails_when_none_remain(): void
    {
        $base = $this->jpg('base.jpg');
        $out = $this->tmp . '/out.png';

        $args = null;
        $r = $this->renderer(true, $args);

        $ok = $r->render($base, [['path' => '/nope/missing.png', 'name' => 'Ghost']], ['y' => 0.12, 'h' => 0.26], $out);

        $this->assertFalse($ok, 'no real faces → render fails (caller keeps plain slide)');
        $this->assertNull($args, 'runStrip never invoked when no usable faces');
    }

    public function test_it_returns_false_when_node_fails(): void
    {
        $base = $this->jpg('base.jpg');
        $face = $this->jpg('face.jpg', 200, 200);
        $out = $this->tmp . '/out.png';

        $args = null;
        $r = $this->renderer(false, $args);

        $ok = $r->render($base, [['path' => $face, 'name' => 'X Y']], ['y' => 0.12, 'h' => 0.26], $out);

        $this->assertFalse($ok);
        $this->assertFileDoesNotExist($out);
    }

    public function test_it_returns_false_when_base_missing(): void
    {
        $args = null;
        $r = $this->renderer(true, $args);

        $ok = $r->render('/nope/base.png', [['path' => $this->jpg('f.jpg', 200, 200)]], ['y' => 0.1, 'h' => 0.2], $this->tmp . '/o.png');

        $this->assertFalse($ok);
    }
}
