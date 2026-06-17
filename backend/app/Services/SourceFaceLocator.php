<?php

namespace App\Services;

use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * people_spotlight fulfilment (2026-06-17) — locate the face of a GIVEN named
 * person across the captured source IG slide images.
 *
 * This is PURE LOCATION, not intent detection: the plugin already decided a
 * slide needs real faces and supplied the people's NAMES (the people_spotlight
 * contract on carousel_slides). This service only answers "which source slide
 * shows <name>, and where is their face?" so CarouselPersonPhotoEnricher can
 * crop the real photo and composite it into the reserved band.
 *
 * Vision is done via the shared Claude-CLI image-input path (same pattern as
 * SlideVisionExtractor) — each source slide image path is embedded in the
 * prompt and the model returns normalized face bounding boxes. Bbox accuracy is
 * approximate; the enricher pads the box and frames the cut-out, so slack is
 * tolerated. Fail-safe: any miss returns [] and the slide is left untouched.
 *
 * @see CarouselPersonPhotoEnricher (the caller that crops + composites)
 * @see VideoHookSceneAuthor (sibling CLI-author service this mirrors)
 */
class SourceFaceLocator
{
    use RunsRepurposeClaudeCli;

    /**
     * Locate the faces of $people across the $slidePaths source images.
     *
     * @param  array<int,string>  $slidePaths  Absolute paths to source slide images, in order.
     * @param  array<int,array{name:string,role?:string}>  $people  The named people to find.
     * @return array<int,array{name:string,role:?string,slide_path:string,bbox:array{0:float,1:float,2:float,3:float}}>
     *         One best match per resolvable person (unresolvable people omitted). Empty on any miss.
     */
    public function locate(array $slidePaths, array $people): array
    {
        $slidePaths = array_values(array_filter($slidePaths, static fn ($p) => is_string($p) && $p !== ''));
        $names = [];
        foreach ($people as $person) {
            $name = trim((string) ($person['name'] ?? ''));
            if (mb_strlen($name) >= 2) {
                $names[$name] = isset($person['role']) ? trim((string) $person['role']) : null;
            }
        }
        if ($slidePaths === [] || $names === []) {
            return [];
        }

        try {
            $res = $this->runFaceLocate($this->buildPrompt($slidePaths, array_keys($names)));
        } catch (\Throwable $e) {
            Log::warning('[SourceFaceLocator] exec threw — no faces located', ['error' => $e->getMessage()]);

            return [];
        }

        if (! ($res['success'] ?? false)) {
            return [];
        }

        $parsed = (array) ($res['parsed'] ?? []);
        $rawMatches = $parsed['matches'] ?? [];
        if (! is_array($rawMatches)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($rawMatches as $m) {
            if (! is_array($m)) {
                continue;
            }
            $name = trim((string) ($m['name'] ?? ''));
            // Match (case-insensitive) against a requested name — never trust a
            // name the model invented that wasn't asked for.
            $canonical = $this->matchName($name, array_keys($names));
            if ($canonical === null || isset($seen[$canonical])) {
                continue; // unknown or already matched (keep first/best per person)
            }

            $slideIdx = $this->resolveSlideIndex($m['slide'] ?? $m['slide_index'] ?? null, count($slidePaths));
            if ($slideIdx === null) {
                continue;
            }

            $bbox = $this->clampBbox($m['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }

            $seen[$canonical] = true;
            $out[] = [
                'name' => $canonical,
                'role' => $names[$canonical] !== '' ? $names[$canonical] : null,
                'slide_path' => $slidePaths[$slideIdx],
                'bbox' => $bbox,
            ];
        }

        return $out;
    }

    /**
     * Test seam — wraps the CLI call so tests subclass + inject canned matches
     * without a real Claude CLI. Requires `status` (always truthy) rather than
     * `matches` so a legitimate empty-matches response isn't treated as a parse
     * failure + retried.
     *
     * @return array{success: bool, parsed: array<string,mixed>|null, output: string, error: string|null, repaired: bool}
     */
    protected function runFaceLocate(string $prompt): array
    {
        return $this->runRepurposeParsed(
            $prompt,
            'source-face-locate',
            ['status'],
            (string) config('services.repurpose.model_vision', 'sonnet'),
            ''
        );
    }

    /** @param array<int,string> $names */
    private function buildPrompt(array $slidePaths, array $names): string
    {
        $imageLines = '';
        foreach ($slidePaths as $i => $path) {
            $n = $i + 1;
            $imageLines .= "Slide {$n}: read the image file at this path: {$path}\n";
        }
        $nameList = implode("\n", array_map(static fn ($n) => "- {$n}", $names));

        return <<<PROMPT
You are locating the FACES of specific named people inside an Instagram carousel's source slides, so their real photos can be cropped and reused.

Source slide images:
{$imageLines}
People to find (match by the captions/labels/context visible in the slides — a slide that introduces founders/authors usually labels each face):
{$nameList}

For EACH person you can actually SEE a clear face for in a slide, return one match: which slide, and the face's bounding box as normalized fractions of that slide's full width/height.
- bbox = [x, y, w, h], each a fraction 0..1. x,y = top-left corner; w,h = width,height. Frame the HEAD AND SHOULDERS (not a tight face crop), leaving a little margin.
- Only include a person if their face is genuinely visible. OMIT anyone you cannot see. It is correct to return an empty list if none are visible.
- Do NOT invent names that were not requested.

STRICT JSON OUTPUT — parsed by a machine, not a human:
- Output ONE compact JSON object only. No markdown fences, no preamble, no trailing prose.
- "slide" is the 1-based slide number from the list above.

Return ONE JSON object with exactly this shape:
{
  "status": "ok",
  "matches": [
    { "name": "Full Name", "slide": 1, "bbox": [0.10, 0.15, 0.30, 0.40] }
  ]
}
PROMPT;
    }

    /** Case-insensitive exact match of a returned name against the requested set. */
    private function matchName(string $name, array $requested): ?string
    {
        if ($name === '') {
            return null;
        }
        foreach ($requested as $r) {
            if (mb_strtolower($r) === mb_strtolower($name)) {
                return $r;
            }
        }

        return null;
    }

    /** Resolve a 1-based slide number to a 0-based path index, or null if out of range. */
    private function resolveSlideIndex(mixed $raw, int $count): ?int
    {
        if (! is_int($raw) && ! (is_string($raw) && ctype_digit($raw))) {
            return null;
        }
        $idx = (int) $raw - 1;

        return ($idx >= 0 && $idx < $count) ? $idx : null;
    }

    /**
     * Validate + clamp a normalized [x,y,w,h] bbox. Rejects non-arrays, wrong
     * arity, non-numeric, or zero-area boxes; clamps each value into [0,1] and
     * ensures the box stays inside the frame.
     *
     * @return array{0:float,1:float,2:float,3:float}|null
     */
    private function clampBbox(mixed $raw): ?array
    {
        if (! is_array($raw) || count($raw) !== 4) {
            return null;
        }
        $vals = [];
        foreach ($raw as $v) {
            if (! is_numeric($v)) {
                return null;
            }
            $vals[] = max(0.0, min(1.0, (float) $v));
        }
        [$x, $y, $w, $h] = $vals;
        if ($w <= 0.0 || $h <= 0.0) {
            return null;
        }
        // Keep the box inside the frame.
        $w = min($w, 1.0 - $x);
        $h = min($h, 1.0 - $y);
        if ($w <= 0.0 || $h <= 0.0) {
            return null;
        }

        return [$x, $y, $w, $h];
    }
}
