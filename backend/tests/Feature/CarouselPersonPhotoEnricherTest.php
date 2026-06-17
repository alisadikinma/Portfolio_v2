<?php

namespace Tests\Feature;

use App\Models\LinkedInPost;
use App\Models\RepurposeJob;
use App\Services\CarouselPersonPhotoEnricher;
use App\Services\SourceFaceLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class CarouselPersonPhotoEnricherTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): ImageManager
    {
        return new ImageManager(extension_loaded('imagick') ? new ImagickDriver() : new GdDriver());
    }

    /** Write a real source slide image into the fake local disk + return its rel dir. */
    private function seedSourceSlides(string $relDir): void
    {
        $bytes = (string) $this->manager()->create(600, 750)->fill('#4477aa')->toJpeg();
        Storage::disk('local')->put($relDir . '/slide-01.jpg', $bytes);
        Storage::disk('local')->put($relDir . '/slide-02.jpg', $bytes);
    }

    /** A profile slide flagged needs_real_faces + a plain concept slide. */
    private function slidesWithOneProfile(): array
    {
        return [
            [
                'slide_number' => 1, 'layout_hint' => 'cover', 'copy_id' => 'C', 'copy_en' => 'C',
                'image_prompt' => str_repeat('cover ', 60), 'is_cover' => true, 'is_cta' => false,
                'image_status' => 'pending', 'image_url' => null,
                'needs_real_faces' => false, 'people' => [], 'face_layout' => null,
            ],
            [
                'slide_number' => 2, 'layout_hint' => 'body', 'copy_id' => 'SIAPA ASHISH VASWANI?', 'copy_en' => 'Who is Ashish Vaswani?',
                'image_prompt' => str_repeat('profile ', 60), 'is_cover' => false, 'is_cta' => false,
                'image_status' => 'pending', 'image_url' => null,
                'needs_real_faces' => true,
                'people' => [['name' => 'Ashish Vaswani', 'role' => 'lead author']],
                'face_layout' => 'photo_band_top',
            ],
            [
                'slide_number' => 3, 'layout_hint' => 'body', 'copy_id' => 'Konsep', 'copy_en' => 'Concept',
                'image_prompt' => str_repeat('plain ', 60), 'is_cover' => false, 'is_cta' => false,
                'image_status' => 'pending', 'image_url' => null,
                'needs_real_faces' => false, 'people' => [], 'face_layout' => null,
            ],
        ];
    }

    private function enricherReturning(array $matchTemplate): CarouselPersonPhotoEnricher
    {
        $locator = new class($matchTemplate) extends SourceFaceLocator {
            public function __construct(private array $tpl)
            {
            }

            public function locate(array $slidePaths, array $people): array
            {
                if ($this->tpl === [] || $slidePaths === []) {
                    return [];
                }

                return [array_merge($this->tpl, ['slide_path' => $slidePaths[0]])];
            }
        };

        return new CarouselPersonPhotoEnricher($locator);
    }

    public function test_it_attaches_real_photo_refs_and_forces_rerender_on_profile_slide(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedSourceSlides('repurpose/77');

        $draft = LinkedInPost::factory()->create([
            'format' => 'carousel',
            'carousel_slides' => $this->slidesWithOneProfile(),
        ]);
        RepurposeJob::factory()->create([
            'linkedin_post_id' => $draft->id,
            'mode' => 'carousel',
            'slides_path' => 'repurpose/77',
            'status' => 'drafted',
        ]);

        $enricher = $this->enricherReturning([
            'name' => 'Ashish Vaswani', 'role' => 'lead author', 'bbox' => [0.25, 0.2, 0.4, 0.45],
        ]);

        $count = $enricher->enrich($draft->fresh());

        $this->assertSame(1, $count);
        $slides = $draft->fresh()->carousel_slides;

        // Profile slide (index 1) enriched.
        $this->assertTrue($slides[1]['person_photos_enriched']);
        $this->assertCount(1, $slides[1]['person_photo_refs']);
        $this->assertSame('Ashish Vaswani', $slides[1]['person_photo_refs'][0]['name']);
        $this->assertStringContainsString('/storage/repurpose-faces/', $slides[1]['person_photo_refs'][0]['url']);
        $this->assertSame('pending', $slides[1]['image_status']);
        $this->assertNull($slides[1]['image_url']);

        // The cropped cut-out file actually exists on the public disk.
        $rel = str_replace(url('/storage/'), '', $slides[1]['person_photo_refs'][0]['url']);
        Storage::disk('public')->assertExists($rel);

        // Plain slides untouched (no refs, not flagged enriched).
        $this->assertArrayNotHasKey('person_photo_refs', $slides[0]);
        $this->assertArrayNotHasKey('person_photo_refs', $slides[2]);
        $this->assertEmpty($slides[0]['person_photos_enriched'] ?? null);
    }

    public function test_it_is_idempotent_and_skips_already_enriched_slides(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedSourceSlides('repurpose/78');

        $slides = $this->slidesWithOneProfile();
        $slides[1]['person_photos_enriched'] = true; // already done

        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'carousel_slides' => $slides]);
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id, 'mode' => 'carousel', 'slides_path' => 'repurpose/78', 'status' => 'drafted']);

        $count = $this->enricherReturning(['name' => 'Ashish Vaswani', 'bbox' => [0.2, 0.2, 0.4, 0.4]])->enrich($draft->fresh());

        $this->assertSame(0, $count);
        $this->assertArrayNotHasKey('person_photo_refs', $draft->fresh()->carousel_slides[1]);
    }

    public function test_it_marks_resolved_without_rerender_when_no_face_located(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedSourceSlides('repurpose/79');

        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'carousel_slides' => $this->slidesWithOneProfile()]);
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id, 'mode' => 'carousel', 'slides_path' => 'repurpose/79', 'status' => 'drafted']);

        // Locator finds nothing.
        $count = $this->enricherReturning([])->enrich($draft->fresh());

        $this->assertSame(0, $count);
        $slides = $draft->fresh()->carousel_slides;
        $this->assertTrue($slides[1]['person_photos_enriched']); // resolved, won't re-run
        $this->assertArrayNotHasKey('person_photo_refs', $slides[1]);
        $this->assertSame('pending', $slides[1]['image_status']); // unchanged
    }

    public function test_it_is_a_noop_for_non_repurpose_drafts(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // No RepurposeJob → isRepurpose() false.
        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'carousel_slides' => $this->slidesWithOneProfile()]);

        $count = $this->enricherReturning(['name' => 'Ashish Vaswani', 'bbox' => [0.2, 0.2, 0.4, 0.4]])->enrich($draft->fresh());

        $this->assertSame(0, $count);
        $this->assertArrayNotHasKey('person_photo_refs', $draft->fresh()->carousel_slides[1]);
    }
}
