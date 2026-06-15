<?php

namespace Tests\Unit;

use App\Services\ZernioImageNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Pure ratio logic for the IG aspect-ratio normalizer. Image I/O (Intervention)
 * is prod-exercised; here we lock the gate + target math and the fail-open path.
 */
class ZernioImageNormalizerTest extends TestCase
{
    private ZernioImageNormalizer $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new ZernioImageNormalizer();
    }

    public function test_needs_normalization_gate(): void
    {
        $this->assertFalse($this->n->needsNormalization(1080, 1350)); // 0.8 (4:5) — in range
        $this->assertFalse($this->n->needsNormalization(1000, 1000)); // 1.0 square
        $this->assertFalse($this->n->needsNormalization(1080, 566));  // ~1.908 — just in range
        $this->assertTrue($this->n->needsNormalization(896, 1280));   // 0.7 — too tall
        $this->assertTrue($this->n->needsNormalization(1080, 500));   // 2.16 — too wide
        $this->assertFalse($this->n->needsNormalization(0, 100));     // guard
    }

    public function test_target_dimensions_bring_ratio_into_range(): void
    {
        // Too tall → widen to 4:5 (0.8)
        [$w, $h] = $this->n->targetDimensions(896, 1280);
        $this->assertSame([1024, 1280], [$w, $h]);
        $this->assertFalse($this->n->needsNormalization($w, $h));

        // Too wide → heighten to ~1.9
        [$w2, $h2] = $this->n->targetDimensions(1080, 500);
        $this->assertFalse($this->n->needsNormalization($w2, $h2));

        // In-range → unchanged
        $this->assertSame([1080, 1350], $this->n->targetDimensions(1080, 1350));
    }

    public function test_unresolvable_url_returns_original(): void
    {
        $url = 'https://example.com/not-a-storage-path.png';
        $this->assertSame($url, $this->n->normalizeForInstagram($url));
    }
}
