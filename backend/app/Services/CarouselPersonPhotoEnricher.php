<?php

namespace App\Services;

use App\Models\LinkedInPost;
use App\Models\RepurposeJob;
use App\Support\SharedDir;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * people_spotlight fulfilment (2026-06-17) — composite REAL founder/people
 * photos onto a profile slide that the plugin flagged `needs_real_faces`.
 *
 * The plugin (the "brain") already DECIDED a slide profiles specific real
 * people and supplied their NAMES + a `face_layout` + a reserved photo band in
 * the image_prompt. This service is the DEPLOYMENT-SPECIFIC fulfilment: for an
 * IG-repurpose draft it finds those people's faces in the captured source IG
 * slides (SourceFaceLocator), crops the real photos (Intervention), and attaches
 * them as `person_photo_refs` so the post-render composite drops them into the
 * reserved band. No app-side intent detection — it acts ONLY on the plugin flag.
 *
 * Repurpose-only by nature (only repurpose drafts have captured source slides);
 * blog→carousel is a silent no-op (zero regression). Idempotent per slide
 * (`person_photos_enriched`), fully fail-safe (any miss leaves the slide
 * untouched), mirrors CarouselCoverFigureEnricher's wiring.
 *
 * @see SourceFaceLocator          (locates a named person's face bbox)
 * @see CarouselPersonStripRenderer (post-render composite into the band)
 */
class CarouselPersonPhotoEnricher
{
    private ?ImageManager $manager = null;

    /** Fraction of the bbox to pad on every side so framed cut-outs aren't tight. */
    private const PAD = 0.12;

    public function __construct(private readonly SourceFaceLocator $locator)
    {
    }

    /**
     * Resolve + attach real person photos for every `needs_real_faces` slide on
     * the draft. Returns the number of slides actually enriched (≥1 face placed).
     */
    public function enrich(LinkedInPost $draft): int
    {
        try {
            return $this->doEnrich($draft);
        } catch (\Throwable $e) {
            Log::warning('[CarouselPersonPhoto] enrich failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function doEnrich(LinkedInPost $draft): int
    {
        $slides = $draft->carousel_slides ?? [];
        if (! is_array($slides) || $slides === []) {
            return 0;
        }

        // Any slide actually asking for faces? Cheap pre-check before the (more
        // expensive) repurpose-job + source-slide resolution.
        $hasFlagged = false;
        foreach ($slides as $s) {
            if (($s['needs_real_faces'] ?? false) === true && empty($s['person_photos_enriched'])) {
                $hasFlagged = true;
                break;
            }
        }
        if (! $hasFlagged) {
            return 0;
        }

        // Repurpose gate — only repurpose drafts have captured source slides.
        if (! $draft->isRepurpose()) {
            return 0;
        }

        $sourcePaths = $this->resolveSourceSlidePaths($draft);
        if ($sourcePaths === []) {
            return 0;
        }

        $enriched = 0;
        $changed = false;

        foreach ($slides as $i => $slide) {
            if (($slide['needs_real_faces'] ?? false) !== true || ! empty($slide['person_photos_enriched'])) {
                continue;
            }

            $people = is_array($slide['people'] ?? null) ? $slide['people'] : [];
            if ($people === []) {
                $slides[$i] = $this->markResolved($slide);
                $changed = true;
                continue;
            }

            $matches = $this->locator->locate($sourcePaths, $people);

            // Group fallback — an UNLABELLED group photo (e.g. "4 MIT dropouts who
            // started Cursor") yields too few per-name matches: the source shows
            // the people together but doesn't label each face, and vision won't
            // attribute a name by appearance alone. Find the group-introduction
            // slide and crop every face (no name attribution — the real faces ARE
            // the human touch). Only when it actually finds more than name-matching.
            if (count($matches) < count($people)) {
                $headline = (string) ($slide['copy_id'] ?? $slide['copy_en'] ?? $slide['copy'] ?? '');
                $group = $this->locator->locateGroup($sourcePaths, $people, $headline);
                if (count($group) > count($matches)) {
                    $matches = $group;
                }
            }

            $refs = [];
            $faceNo = 0;
            foreach ($matches as $match) {
                $faceNo++;
                $url = $this->cropFace($match['slide_path'], $match['bbox'], $draft->id, $i, $faceNo);
                if ($url === null) {
                    continue;
                }
                $refs[] = [
                    'url' => $url,
                    'name' => $match['name'],
                    'role' => $match['role'] ?? null,
                ];
            }

            if ($refs === []) {
                // Named but no usable photo resolved → leave the plugin slide as
                // authored (its reserved band renders empty). Mark resolved so we
                // don't re-run vision every dispatch (explicit re-render clears it).
                $slides[$i] = $this->markResolved($slide);
                $changed = true;
                continue;
            }

            $slide['person_photo_refs'] = $refs;
            $slide['face_layout'] = is_string($slide['face_layout'] ?? null) && $slide['face_layout'] !== ''
                ? $slide['face_layout']
                : 'photo_band_top';
            $slide['person_photos_enriched'] = true;
            // Force a re-render so the reserved-band prompt renders fresh; the
            // post-render composite then drops the real cut-outs into the band.
            $slide['image_status'] = 'pending';
            $slide['image_url'] = null;
            $slides[$i] = $slide;
            $changed = true;
            $enriched++;

            Log::info('[CarouselPersonPhoto] attached real person photos', [
                'draft_id' => $draft->id,
                'slide_index' => $i,
                'faces' => count($refs),
            ]);
        }

        if ($changed) {
            $draft->update(['carousel_slides' => $slides]);
        }

        return $enriched;
    }

    /** Mark a flagged slide as resolved-without-photos so vision never re-runs (until a re-render clears it). */
    private function markResolved(array $slide): array
    {
        $slide['person_photos_enriched'] = true;

        return $slide;
    }

    /**
     * Resolve the captured source IG slide image paths for a repurpose draft.
     *
     * IMPORTANT: `slides_path` is relative to `storage/app` (that's where
     * InstagramCaptureService writes — `storage_path('app/'.$relDir)`), NOT the
     * 'local' disk. Under Laravel 11/12 the 'local' disk root is
     * `storage/app/private`, so `Storage::disk('local')->files($relDir)` looked in
     * `storage/app/private/repurpose/{id}` and found nothing → the enricher
     * silently no-op'd and no faces ever composited. Read the real location with
     * a native scan, mirroring VideoSlideExtractor's `storage_path('app/'.$rel)`.
     *
     * @return array<int,string> absolute filesystem paths, sorted
     */
    private function resolveSourceSlidePaths(LinkedInPost $draft): array
    {
        $job = RepurposeJob::query()
            ->where('linkedin_post_id', $draft->id)
            ->when($draft->post_id, fn ($q) => $q->orWhere('anchor_post_id', $draft->post_id))
            ->latest('id')
            ->first();

        $relDir = $job?->slides_path;
        if (! is_string($relDir) || $relDir === '') {
            return [];
        }

        $absDir = storage_path('app/' . $relDir);
        if (! is_dir($absDir)) {
            return [];
        }

        $paths = [];
        foreach (glob($absDir . '/*') ?: [] as $f) {
            if (preg_match('/slide-\d+\.(jpg|jpeg|png)$/i', $f)) {
                $paths[] = $f;
            }
        }
        sort($paths);

        return $paths;
    }

    /**
     * Crop the padded face bbox out of the source slide and store it as a unique
     * public PNG. Returns the public URL, or null on any failure.
     *
     * @param  array{0:float,1:float,2:float,3:float}  $bbox  normalized [x,y,w,h]
     */
    private function cropFace(string $srcAbs, array $bbox, int $draftId, int $slideIdx, int $faceNo): ?string
    {
        if (! is_file($srcAbs)) {
            return null;
        }

        try {
            $img = $this->manager()->read($srcAbs);
            $W = $img->width();
            $H = $img->height();

            // Pad in normalized space, then clamp to the frame.
            $x = $bbox[0] - self::PAD;
            $y = $bbox[1] - self::PAD;
            $w = $bbox[2] + 2 * self::PAD;
            $h = $bbox[3] + 2 * self::PAD;
            $x = max(0.0, $x);
            $y = max(0.0, $y);
            $w = min($w, 1.0 - $x);
            $h = min($h, 1.0 - $y);

            $pxX = (int) round($x * $W);
            $pxY = (int) round($y * $H);
            $pxW = max(1, (int) round($w * $W));
            $pxH = max(1, (int) round($h * $H));

            // Intervention v3: crop(width, height, offset_x, offset_y) from top-left.
            $img->crop($pxW, $pxH, $pxX, $pxY);
            $png = (string) $img->toPng();
            if ($png === '') {
                return null;
            }

            $rel = sprintf(
                'repurpose-faces/%d/%d/face-%02d-%s.png',
                $draftId,
                $slideIdx,
                $faceNo,
                bin2hex(random_bytes(6))
            );

            // Cross-user write guard (claudesn worker vs www-data dir perms).
            SharedDir::ensure(dirname(Storage::disk('public')->path($rel)));

            $written = Storage::disk('public')->put($rel, $png);
            if (! $written || ! Storage::disk('public')->exists($rel)) {
                Log::warning('[CarouselPersonPhoto] crop store failed', ['rel' => $rel]);

                return null;
            }

            return url('/storage/' . $rel);
        } catch (\Throwable $e) {
            Log::warning('[CarouselPersonPhoto] crop threw', [
                'src' => $srcAbs,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function manager(): ImageManager
    {
        return $this->manager ??= new ImageManager(
            extension_loaded('imagick') ? new ImagickDriver() : new GdDriver()
        );
    }
}
