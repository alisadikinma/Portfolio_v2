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

    public function test_it_passes_through_people_spotlight_contract_fields(): void
    {
        // A profile slide carrying the plugin's people_spotlight contract +
        // a legacy slide carrying none of the fields. The adapter must surface
        // the contract verbatim on the flagged slide and apply safe defaults
        // (needs_real_faces=false, people=[], face_layout=null) on the rest.
        $input = [
            'status' => 'complete',
            'format' => 'carousel',
            'total_slides' => 5,
            'aspect_ratio' => '4:5',
            'bilingual' => true,
            'narrative' => '5act',
            'slides' => [
                [
                    'slide_number' => 1,
                    'layout_hint' => 'cover',
                    'copy_id' => 'Cover ID',
                    'copy_en' => 'Cover EN',
                    'image_prompt' => str_repeat('cover prompt ', 30),
                    'is_cover' => true,
                    'is_cta' => false,
                ],
                [
                    'slide_number' => 2,
                    'layout_hint' => 'body',
                    'copy_id' => 'SIAPA ASHISH VASWANI?',
                    'copy_en' => 'Who is Ashish Vaswani?',
                    'image_prompt' => str_repeat('profile prompt ', 30),
                    'is_cover' => false,
                    'is_cta' => false,
                    'needs_real_faces' => true,
                    'people' => [['name' => 'Ashish Vaswani', 'role' => 'lead author']],
                    'face_layout' => 'photo_band_top',
                ],
                [
                    'slide_number' => 3,
                    'layout_hint' => 'body',
                    'copy_id' => 'Konsep biasa',
                    'copy_en' => 'Plain concept',
                    'image_prompt' => str_repeat('plain body prompt ', 20),
                    'is_cover' => false,
                    'is_cta' => false,
                ],
                [
                    'slide_number' => 4,
                    'layout_hint' => 'body',
                    'copy_id' => 'Body 4',
                    'copy_en' => 'Body 4',
                    'image_prompt' => str_repeat('body4 prompt ', 30),
                    'is_cover' => false,
                    'is_cta' => false,
                ],
                [
                    'slide_number' => 5,
                    'layout_hint' => 'cta',
                    'copy_id' => 'CTA ID',
                    'copy_en' => 'CTA EN',
                    'image_prompt' => str_repeat('cta prompt ', 30),
                    'is_cover' => false,
                    'is_cta' => true,
                ],
            ],
            'generated_at' => '2026-06-17T10:00:00Z',
        ];

        $result = $this->adapter()->adapt($input);

        // Flagged profile slide (index 1) carries the contract verbatim.
        $this->assertTrue($result[1]['needs_real_faces']);
        $this->assertSame([['name' => 'Ashish Vaswani', 'role' => 'lead author']], $result[1]['people']);
        $this->assertSame('photo_band_top', $result[1]['face_layout']);

        // Legacy slides default safely — never null/absent surprises downstream.
        foreach ([0, 2, 3, 4] as $idx) {
            $this->assertArrayHasKey('needs_real_faces', $result[$idx]);
            $this->assertFalse($result[$idx]['needs_real_faces'], "Slide {$idx} should default needs_real_faces=false");
            $this->assertSame([], $result[$idx]['people'], "Slide {$idx} should default people=[]");
            $this->assertNull($result[$idx]['face_layout'], "Slide {$idx} should default face_layout=null");
        }
    }

    public function test_it_drops_malformed_people_entries_and_caps_to_six(): void
    {
        // Defense-in-depth: people entries without a usable name are dropped;
        // role coerced to string|null; capped at 6 (mirrors schema .max(6)).
        $people = [];
        for ($i = 1; $i <= 8; $i++) {
            $people[] = ['name' => "Person {$i}", 'role' => "role {$i}"];
        }
        $people[] = ['role' => 'no name — dropped'];
        $people[] = ['name' => 'A']; // too short — dropped

        $input = [
            'status' => 'complete',
            'format' => 'carousel',
            'total_slides' => 5,
            'aspect_ratio' => '4:5',
            'bilingual' => true,
            'narrative' => '5act',
            'slides' => [
                ['slide_number' => 1, 'layout_hint' => 'cover', 'copy_id' => 'a', 'copy_en' => 'a', 'image_prompt' => str_repeat('p ', 200), 'is_cover' => true, 'is_cta' => false],
                ['slide_number' => 2, 'layout_hint' => 'body', 'copy_id' => 'b', 'copy_en' => 'b', 'image_prompt' => str_repeat('p ', 200), 'is_cover' => false, 'is_cta' => false, 'needs_real_faces' => true, 'people' => $people, 'face_layout' => 'photo_band_top'],
                ['slide_number' => 3, 'layout_hint' => 'body', 'copy_id' => 'c', 'copy_en' => 'c', 'image_prompt' => str_repeat('p ', 200), 'is_cover' => false, 'is_cta' => false],
                ['slide_number' => 4, 'layout_hint' => 'body', 'copy_id' => 'd', 'copy_en' => 'd', 'image_prompt' => str_repeat('p ', 200), 'is_cover' => false, 'is_cta' => false],
                ['slide_number' => 5, 'layout_hint' => 'cta', 'copy_id' => 'e', 'copy_en' => 'e', 'image_prompt' => str_repeat('p ', 200), 'is_cover' => false, 'is_cta' => true],
            ],
            'generated_at' => '2026-06-17T10:00:00Z',
        ];

        $result = $this->adapter()->adapt($input);

        $this->assertCount(6, $result[1]['people'], 'people must be capped at 6');
        foreach ($result[1]['people'] as $person) {
            $this->assertArrayHasKey('name', $person);
            $this->assertGreaterThanOrEqual(2, mb_strlen($person['name']));
        }
    }

    public function test_it_throws_when_status_complete_with_empty_slides_array(): void
    {
        // Defense-in-depth: plugin's Zod schema enforces slides.min(5), but
        // an empty array bypassing validation should still fail loudly at
        // the adapter rather than silently returning [].
        $input = [
            'status' => 'complete',
            'format' => 'carousel',
            'total_slides' => 0,
            'aspect_ratio' => '4:5',
            'bilingual' => true,
            'narrative' => '5act',
            'slides' => [],
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $this->expectException(CarouselGenAdapterException::class);
        $this->expectExceptionMessageMatches('/empty slides array/');

        $this->adapter()->adapt($input);
    }
}
