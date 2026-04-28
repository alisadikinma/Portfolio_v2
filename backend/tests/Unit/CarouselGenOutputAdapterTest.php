<?php

namespace Tests\Unit;

use App\Exceptions\CarouselGenAdapterException;
use App\Services\CarouselGenOutputAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CarouselGenOutputAdapter.
 *
 * The adapter maps the /carousel-gen plugin's stdout JSON (matching
 * CarouselGenOutputSchema) onto the existing linkedin_posts.carousel_slides
 * JSON column shape. Backward-compat with draft #28+ that already exist in
 * DB is the load-bearing requirement.
 *
 * The schema is a discriminated union on `status`:
 *   - status=complete  → array of fully-shaped slide rows
 *   - status=failed    → throws CarouselGenAdapterException
 *
 * @covers \App\Services\CarouselGenOutputAdapter
 */
class CarouselGenOutputAdapterTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures';

    private function adapter(): CarouselGenOutputAdapter
    {
        return new CarouselGenOutputAdapter();
    }

    private function loadFixture(string $name): array
    {
        $path = self::FIXTURE_DIR . '/' . $name;
        $this->assertFileExists($path, "Fixture file not found: {$path}");
        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded, "Fixture is not valid JSON: {$path}");
        return $decoded;
    }

    public function test_it_maps_bilingual_status_complete_to_carousel_slides_array(): void
    {
        $input = $this->loadFixture('carousel-gen-bilingual.json');

        $result = $this->adapter()->adapt($input);

        $this->assertIsArray($result);
        $this->assertCount(9, $result, 'Expected 9 slides on the bilingual fixture');

        // Every slide must carry the 12 fields downstream consumers depend on.
        $expectedKeys = [
            'slide_number',
            'layout_hint',
            'copy_id',
            'copy_en',
            'image_prompt',
            'is_cover',
            'is_cta',
            'direct_answer_block',
            'image_status',
            'image_url',
            'image_job_uuid',
            'image_error',
        ];
        foreach ($result as $idx => $slide) {
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $slide,
                    "Slide at index {$idx} missing field `{$key}`"
                );
            }
        }

        // Spot-check narrative invariants.
        $this->assertSame(1, $result[0]['slide_number']);
        $this->assertSame('cover', $result[0]['layout_hint']);
        $this->assertTrue($result[0]['is_cover']);
        $this->assertFalse($result[0]['is_cta']);

        $this->assertSame(9, $result[8]['slide_number']);
        $this->assertSame('cta', $result[8]['layout_hint']);
        $this->assertTrue($result[8]['is_cta']);
        $this->assertFalse($result[8]['is_cover']);

        // Slide 8 (index 7) is the direct_answer slide and must carry the block.
        $this->assertSame('direct_answer', $result[7]['layout_hint']);
        $this->assertNotNull($result[7]['direct_answer_block']);
        $this->assertIsString($result[7]['direct_answer_block']);
    }

    public function test_it_maps_single_language_to_copy_id_with_copy_en_null(): void
    {
        $input = $this->loadFixture('carousel-gen-single-language.json');

        $result = $this->adapter()->adapt($input);

        $this->assertCount(9, $result);

        // The fixture's source `copy` field for slide 1 must surface as copy_id;
        // copy_en MUST be null because bilingual=false on the input envelope.
        $sourceSlides = $input['slides'];
        foreach ($result as $idx => $slide) {
            $this->assertSame(
                $sourceSlides[$idx]['copy'],
                $slide['copy_id'],
                "Slide at index {$idx}: copy_id should mirror source `copy`"
            );
            $this->assertNull(
                $slide['copy_en'],
                "Slide at index {$idx}: copy_en MUST be null in single-language mode"
            );
        }
    }

    public function test_it_preserves_image_prompt_verbatim_including_placeholder_tokens(): void
    {
        $input = $this->loadFixture('carousel-gen-bilingual.json');

        $result = $this->adapter()->adapt($input);

        // The 6 placeholder tokens that CarouselSlideEnhancer resolves at
        // dispatch time. The adapter MUST NOT mutate these.
        $tokens = [
            '{{CREATOR_FACE}}',
            '{{BRAND_LOGO}}',
            '{{HANDLE}}',
            '{{PORTFOLIO_URL}}',
            '{{PAGE_INDICATOR}}',
            '{{SWIPE_TEXT}}',
        ];

        foreach ($result as $idx => $slide) {
            $prompt = $slide['image_prompt'];
            $this->assertIsString($prompt);
            $this->assertSame(
                $input['slides'][$idx]['image_prompt'],
                $prompt,
                "Slide at index {$idx}: image_prompt must pass through verbatim"
            );
            foreach ($tokens as $token) {
                $this->assertStringContainsString(
                    $token,
                    $prompt,
                    "Slide at index {$idx}: placeholder token `{$token}` was stripped"
                );
            }
        }
    }

    public function test_it_initializes_image_status_pending_for_all_slides(): void
    {
        $input = $this->loadFixture('carousel-gen-bilingual.json');

        $result = $this->adapter()->adapt($input);

        foreach ($result as $idx => $slide) {
            $this->assertSame('pending', $slide['image_status'], "Slide {$idx} image_status should be `pending`");
            $this->assertNull($slide['image_url'], "Slide {$idx} image_url should be null");
            $this->assertNull($slide['image_job_uuid'], "Slide {$idx} image_job_uuid should be null");
            $this->assertNull($slide['image_error'], "Slide {$idx} image_error should be null");
        }
    }

    public function test_it_preserves_direct_answer_block_only_on_direct_answer_slides(): void
    {
        $input = $this->loadFixture('carousel-gen-bilingual.json');

        $result = $this->adapter()->adapt($input);

        foreach ($result as $idx => $slide) {
            if ($slide['layout_hint'] === 'direct_answer') {
                $this->assertNotNull(
                    $slide['direct_answer_block'],
                    "direct_answer slide at index {$idx} must carry direct_answer_block"
                );
                $this->assertIsString($slide['direct_answer_block']);
            } else {
                $this->assertNull(
                    $slide['direct_answer_block'],
                    "Non-direct_answer slide at index {$idx} (layout={$slide['layout_hint']}) must have direct_answer_block=null"
                );
            }
        }
    }

    public function test_it_throws_carousel_gen_adapter_exception_when_status_failed(): void
    {
        $input = $this->loadFixture('carousel-gen-failed.json');

        $this->expectException(CarouselGenAdapterException::class);
        // Fixture's error field is the substring we expect surfaced in the
        // thrown message.
        $this->expectExceptionMessageMatches('/depth gate/i');

        $this->adapter()->adapt($input);
    }

    public function test_it_throws_carousel_gen_adapter_exception_when_status_failed_and_no_error_field(): void
    {
        $input = [
            'status' => 'failed',
            'format' => 'carousel',
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $this->expectException(CarouselGenAdapterException::class);
        $this->expectExceptionMessageMatches('/carousel-gen returned status=failed/');

        $this->adapter()->adapt($input);
    }

    public function test_it_preserves_gapless_slide_numbers(): void
    {
        $input = $this->loadFixture('carousel-gen-bilingual.json');

        $result = $this->adapter()->adapt($input);

        foreach ($result as $idx => $slide) {
            $this->assertSame(
                $idx + 1,
                $slide['slide_number'],
                "Adapter must preserve gapless 1..N slide_number ordering"
            );
        }
    }
}
