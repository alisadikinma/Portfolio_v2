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

    /** @var array<int,string> real source dirs created under storage/app for cleanup. */
    private array $tmpSourceDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpSourceDirs as $dir) {
            if (is_dir($dir)) {
                array_map('unlink', glob($dir . '/*') ?: []);
                @rmdir($dir);
            }
        }

        parent::tearDown();
    }

    private function manager(): ImageManager
    {
        return new ImageManager(extension_loaded('imagick') ? new ImagickDriver() : new GdDriver());
    }

    /**
     * Write real source slide images into the REAL storage/app/{relDir} location
     * — exactly where InstagramCaptureService persists them (slides_path is
     * relative to storage/app, NOT the 'local' disk = storage/app/private). The
     * old version seeded the faked 'local' disk, which mirrored the production
     * path bug and let it ship green.
     */
    private function seedSourceSlides(string $relDir): void
    {
        $dir = storage_path('app/' . $relDir);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->tmpSourceDirs[] = $dir;

        $bytes = (string) $this->manager()->create(600, 750)->fill('#4477aa')->toJpeg();
        file_put_contents($dir . '/slide-01.jpg', $bytes);
        file_put_contents($dir . '/slide-02.jpg', $bytes);
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
        Storage::fake('public');

        // No RepurposeJob → isRepurpose() false.
        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'carousel_slides' => $this->slidesWithOneProfile()]);

        $count = $this->enricherReturning(['name' => 'Ashish Vaswani', 'bbox' => [0.2, 0.2, 0.4, 0.4]])->enrich($draft->fresh());

        $this->assertSame(0, $count);
        $this->assertArrayNotHasKey('person_photo_refs', $draft->fresh()->carousel_slides[1]);
    }

    // --- Group fallback (unlabelled founders photo, e.g. "4 MIT Dropouts") ----

    /** A profile slide about 4 named founders (the Cursor case). */
    private function slidesWithGroupProfile(): array
    {
        return [
            [
                'slide_number' => 1, 'layout_hint' => 'body', 'copy_id' => 'SIAPA CURSOR? 4 MIT Dropouts', 'copy_en' => 'Who is Cursor?',
                'image_prompt' => str_repeat('profile ', 60), 'is_cover' => false, 'is_cta' => false,
                'image_status' => 'pending', 'image_url' => null,
                'needs_real_faces' => true,
                'people' => [
                    ['name' => 'Michael Truell'], ['name' => 'Sualeh Asif'],
                    ['name' => 'Arvid Lunnemark'], ['name' => 'Aman Sanger'],
                ],
                'face_layout' => 'photo_band_top',
            ],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $nameMatches  canned per-name matches (slide_path filled in)
     * @param  array<int,array{0:float,1:float,2:float,3:float}>  $groupFaces  canned group bboxes
     */
    private function groupEnricher(array $nameMatches, array $groupFaces): CarouselPersonPhotoEnricher
    {
        $locator = new class($nameMatches, $groupFaces) extends SourceFaceLocator {
            public function __construct(private array $nm, private array $gf)
            {
            }

            public function locate(array $slidePaths, array $people): array
            {
                if ($slidePaths === []) {
                    return [];
                }

                return array_map(fn ($m) => array_merge($m, ['slide_path' => $slidePaths[0]]), $this->nm);
            }

            public function locateGroup(array $slidePaths, array $people, string $topic = ''): array
            {
                if ($slidePaths === []) {
                    return [];
                }

                return array_map(fn ($b) => [
                    'name' => null, 'role' => null, 'slide_path' => $slidePaths[0], 'bbox' => $b,
                ], $this->gf);
            }
        };

        return new CarouselPersonPhotoEnricher($locator);
    }

    public function test_group_fallback_crops_all_faces_when_people_unlabelled(): void
    {
        Storage::fake('public');
        $this->seedSourceSlides('repurpose/80');

        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'carousel_slides' => $this->slidesWithGroupProfile()]);
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id, 'mode' => 'carousel', 'slides_path' => 'repurpose/80', 'status' => 'drafted']);

        // Name-matching finds NOTHING (no labels) → group locate finds all 4 faces.
        $enricher = $this->groupEnricher([], [
            [0.08, 0.30, 0.18, 0.28], [0.30, 0.30, 0.18, 0.28],
            [0.52, 0.30, 0.18, 0.28], [0.74, 0.30, 0.18, 0.28],
        ]);

        $count = $enricher->enrich($draft->fresh());

        $this->assertSame(1, $count);
        $slide = $draft->fresh()->carousel_slides[0];
        $this->assertCount(4, $slide['person_photo_refs']);
        // No name attribution on group crops.
        $this->assertNull($slide['person_photo_refs'][0]['name']);
        $this->assertSame('pending', $slide['image_status']);
        $this->assertNull($slide['image_url']);
        // Each cut-out actually written.
        foreach ($slide['person_photo_refs'] as $ref) {
            Storage::disk('public')->assertExists(str_replace(url('/storage/'), '', $ref['url']));
        }
    }

    public function test_group_fallback_not_used_when_name_matches_suffice(): void
    {
        Storage::fake('public');
        $this->seedSourceSlides('repurpose/81');

        $draft = LinkedInPost::factory()->create(['format' => 'carousel', 'carousel_slides' => $this->slidesWithGroupProfile()]);
        RepurposeJob::factory()->create(['linkedin_post_id' => $draft->id, 'mode' => 'carousel', 'slides_path' => 'repurpose/81', 'status' => 'drafted']);

        // All 4 names matched by label → group must NOT override (names preserved).
        $named = [
            ['name' => 'Michael Truell', 'role' => null, 'bbox' => [0.08, 0.3, 0.18, 0.28]],
            ['name' => 'Sualeh Asif', 'role' => null, 'bbox' => [0.30, 0.3, 0.18, 0.28]],
            ['name' => 'Arvid Lunnemark', 'role' => null, 'bbox' => [0.52, 0.3, 0.18, 0.28]],
            ['name' => 'Aman Sanger', 'role' => null, 'bbox' => [0.74, 0.3, 0.18, 0.28]],
        ];
        $enricher = $this->groupEnricher($named, [[0.0, 0.0, 0.5, 0.5]]); // group would only give 1

        $count = $enricher->enrich($draft->fresh());

        $this->assertSame(1, $count);
        $refs = $draft->fresh()->carousel_slides[0]['person_photo_refs'];
        $this->assertCount(4, $refs);
        $this->assertSame('Michael Truell', $refs[0]['name']); // name-match kept, not group
    }
}
