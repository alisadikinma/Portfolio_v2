<?php

namespace Tests\Unit;

use App\Services\SourceFaceLocator;
use Tests\TestCase;

/**
 * Unit tests for SourceFaceLocator — pure-location of named people's faces in
 * source slides. The Claude-CLI call is replaced by a test seam so no real
 * inference runs; we assert the parse/normalize/match/clamp logic.
 */
class SourceFaceLocatorTest extends TestCase
{
    private function locator(array $cannedParsed, bool $success = true): SourceFaceLocator
    {
        return new class($cannedParsed, $success) extends SourceFaceLocator {
            public function __construct(private array $canned, private bool $ok)
            {
            }

            protected function runFaceLocate(string $prompt): array
            {
                return [
                    'success' => $this->ok,
                    'parsed' => $this->ok ? $this->canned : null,
                    'output' => '',
                    'error' => $this->ok ? null : 'fake_failure',
                    'repaired' => false,
                ];
            }
        };
    }

    private array $paths = ['/src/slide-01.jpg', '/src/slide-02.jpg', '/src/slide-03.jpg'];

    public function test_it_maps_a_match_to_slide_path_role_and_clamped_bbox(): void
    {
        $loc = $this->locator([
            'status' => 'ok',
            'matches' => [
                ['name' => 'Ashish Vaswani', 'slide' => 2, 'bbox' => [0.1, 0.2, 0.3, 0.4]],
            ],
        ]);

        $out = $loc->locate($this->paths, [['name' => 'Ashish Vaswani', 'role' => 'lead author']]);

        $this->assertCount(1, $out);
        $this->assertSame('Ashish Vaswani', $out[0]['name']);
        $this->assertSame('lead author', $out[0]['role']);
        $this->assertSame('/src/slide-02.jpg', $out[0]['slide_path']);
        $this->assertSame([0.1, 0.2, 0.3, 0.4], $out[0]['bbox']);
    }

    public function test_it_returns_empty_when_no_people(): void
    {
        $loc = $this->locator(['status' => 'ok', 'matches' => []]);
        $this->assertSame([], $loc->locate($this->paths, []));
    }

    public function test_it_returns_empty_when_no_slide_paths(): void
    {
        $loc = $this->locator(['status' => 'ok', 'matches' => [['name' => 'X Y', 'slide' => 1, 'bbox' => [0, 0, 1, 1]]]]);
        $this->assertSame([], $loc->locate([], [['name' => 'X Y']]));
    }

    public function test_it_returns_empty_on_cli_failure(): void
    {
        $loc = $this->locator([], false);
        $this->assertSame([], $loc->locate($this->paths, [['name' => 'Ashish Vaswani']]));
    }

    public function test_it_drops_unrequested_names(): void
    {
        $loc = $this->locator([
            'status' => 'ok',
            'matches' => [
                ['name' => 'Random Stranger', 'slide' => 1, 'bbox' => [0.1, 0.1, 0.2, 0.2]],
            ],
        ]);
        $this->assertSame([], $loc->locate($this->paths, [['name' => 'Ashish Vaswani']]));
    }

    public function test_it_drops_out_of_range_slide_index(): void
    {
        $loc = $this->locator([
            'status' => 'ok',
            'matches' => [
                ['name' => 'Ashish Vaswani', 'slide' => 99, 'bbox' => [0.1, 0.1, 0.2, 0.2]],
            ],
        ]);
        $this->assertSame([], $loc->locate($this->paths, [['name' => 'Ashish Vaswani']]));
    }

    public function test_it_drops_malformed_bbox(): void
    {
        $loc = $this->locator([
            'status' => 'ok',
            'matches' => [
                ['name' => 'A One', 'slide' => 1, 'bbox' => [0.1, 0.1, 0.2]],          // wrong arity
                ['name' => 'B Two', 'slide' => 1, 'bbox' => [0.1, 0.1, 0.0, 0.4]],     // zero width
                ['name' => 'C Three', 'slide' => 1, 'bbox' => 'nope'],                  // not array
            ],
        ]);
        $out = $loc->locate($this->paths, [['name' => 'A One'], ['name' => 'B Two'], ['name' => 'C Three']]);
        $this->assertSame([], $out);
    }

    public function test_it_clamps_oversize_bbox_into_frame(): void
    {
        $loc = $this->locator([
            'status' => 'ok',
            'matches' => [
                ['name' => 'Big Box', 'slide' => 1, 'bbox' => [0.8, 0.8, 0.9, 0.9]],
            ],
        ]);
        $out = $loc->locate($this->paths, [['name' => 'Big Box']]);
        $this->assertCount(1, $out);
        // x stays, w clamped to 1-x = 0.2 (within float tolerance).
        $this->assertEqualsWithDelta(0.8, $out[0]['bbox'][0], 1e-9);
        $this->assertEqualsWithDelta(0.2, $out[0]['bbox'][2], 1e-9);
        $this->assertEqualsWithDelta(0.2, $out[0]['bbox'][3], 1e-9);
    }

    public function test_it_keeps_one_match_per_person(): void
    {
        $loc = $this->locator([
            'status' => 'ok',
            'matches' => [
                ['name' => 'Dup Person', 'slide' => 1, 'bbox' => [0.1, 0.1, 0.2, 0.2]],
                ['name' => 'dup person', 'slide' => 3, 'bbox' => [0.3, 0.3, 0.2, 0.2]],
            ],
        ]);
        $out = $loc->locate($this->paths, [['name' => 'Dup Person']]);
        $this->assertCount(1, $out);
        $this->assertSame('/src/slide-01.jpg', $out[0]['slide_path']); // first kept
    }

    // --- Group fallback (unlabelled group photo) -----------------------------

    private function groupLocator(array $cannedParsed, bool $success = true): SourceFaceLocator
    {
        return new class($cannedParsed, $success) extends SourceFaceLocator {
            public function __construct(private array $canned, private bool $ok)
            {
            }

            protected function runGroupLocate(string $prompt): array
            {
                return [
                    'success' => $this->ok,
                    'parsed' => $this->ok ? $this->canned : null,
                    'output' => '',
                    'error' => $this->ok ? null : 'fake_failure',
                    'repaired' => false,
                ];
            }
        };
    }

    public function test_group_returns_all_faces_left_to_right_without_names(): void
    {
        $loc = $this->groupLocator([
            'status' => 'ok',
            'slide' => 2,
            // Deliberately out of left-to-right order.
            'faces' => [
                [0.60, 0.2, 0.15, 0.30],
                [0.10, 0.2, 0.15, 0.30],
                [0.35, 0.2, 0.15, 0.30],
            ],
        ]);

        $people = [['name' => 'Michael Truell'], ['name' => 'Sualeh Asif'], ['name' => 'Arvid Lunnemark'], ['name' => 'Aman Sanger']];
        $out = $loc->locateGroup($this->paths, $people, 'SIAPA CURSOR? 4 MIT Dropouts');

        $this->assertCount(3, $out);
        // Sorted by x ascending.
        $this->assertEqualsWithDelta(0.10, $out[0]['bbox'][0], 1e-9);
        $this->assertEqualsWithDelta(0.35, $out[1]['bbox'][0], 1e-9);
        $this->assertEqualsWithDelta(0.60, $out[2]['bbox'][0], 1e-9);
        // All point at the chosen slide, no name attribution.
        foreach ($out as $f) {
            $this->assertSame('/src/slide-02.jpg', $f['slide_path']);
            $this->assertNull($f['name']);
            $this->assertNull($f['role']);
        }
    }

    public function test_group_caps_faces_to_headcount(): void
    {
        $loc = $this->groupLocator([
            'status' => 'ok',
            'slide' => 1,
            'faces' => [
                [0.10, 0.2, 0.1, 0.2], [0.30, 0.2, 0.1, 0.2], [0.50, 0.2, 0.1, 0.2], [0.70, 0.2, 0.1, 0.2],
            ],
        ]);

        // Only 2 people requested → never return more than 2 faces.
        $out = $loc->locateGroup($this->paths, [['name' => 'One Name'], ['name' => 'Two Name']], 'topic');
        $this->assertCount(2, $out);
    }

    public function test_group_accepts_bbox_wrapper_objects(): void
    {
        $loc = $this->groupLocator([
            'status' => 'ok',
            'slide' => 1,
            'faces' => [
                ['bbox' => [0.10, 0.2, 0.2, 0.3]],
                ['bbox' => [0.40, 0.2, 0.2, 0.3]],
            ],
        ]);
        $out = $loc->locateGroup($this->paths, [['name' => 'One Name'], ['name' => 'Two Name']], 'topic');
        $this->assertCount(2, $out);
        $this->assertEqualsWithDelta(0.10, $out[0]['bbox'][0], 1e-9);
    }

    public function test_group_returns_empty_when_no_faces(): void
    {
        $loc = $this->groupLocator(['status' => 'ok', 'slide' => 1, 'faces' => []]);
        $this->assertSame([], $loc->locateGroup($this->paths, [['name' => 'One Name'], ['name' => 'Two Name']], 't'));
    }

    public function test_group_returns_empty_on_out_of_range_slide(): void
    {
        $loc = $this->groupLocator(['status' => 'ok', 'slide' => 99, 'faces' => [[0.1, 0.1, 0.2, 0.2]]]);
        $this->assertSame([], $loc->locateGroup($this->paths, [['name' => 'One Name']], 't'));
    }

    public function test_group_returns_empty_on_cli_failure(): void
    {
        $loc = $this->groupLocator([], false);
        $this->assertSame([], $loc->locateGroup($this->paths, [['name' => 'One Name']], 't'));
    }

    public function test_group_returns_empty_when_no_people(): void
    {
        $loc = $this->groupLocator(['status' => 'ok', 'slide' => 1, 'faces' => [[0.1, 0.1, 0.2, 0.2]]]);
        $this->assertSame([], $loc->locateGroup($this->paths, [], 't'));
    }
}
