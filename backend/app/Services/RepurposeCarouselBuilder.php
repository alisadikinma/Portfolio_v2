<?php

namespace App\Services;

use App\Models\RepurposeJob;
use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * Source-mirrored repurpose carousel builder (2026-06-15).
 *
 * For IG-repurpose carousels the slide count follows the SOURCE's natural tool
 * list — one Tool/Skill/Plugin per slide — instead of the force-carousel path's
 * flat 7-slide re-narration (which crammed 2–3 tools per body slide).
 *
 * The reliable tool list is the post CAPTION's numbered list (`1) Name — desc
 * 2) ...`), NOT the captured slide frames: the IG capture routinely over-grabs
 * the creator's profile grid (e.g. 28 noisy frames, only 1 real tool slide),
 * while the caption carries the clean, complete, fact-checkable list. Per-tool
 * detail comes from the fact-checked claims; tone follows Ali's voice.
 *
 * Every slide — cover, each tool, cta — is bilingual: copy_id (Indonesian,
 * primary) + copy_en (English companion), matching the --bilingual=id,en
 * standard. Each slide is authored INDEPENDENTLY via a light CLI call (opsi A),
 * so the Sonnet output-truncation that forced the ≤7 cap cannot occur.
 *
 * @see docs/plans/2026-06-15-carousel-one-tool-per-slide.md
 */
class RepurposeCarouselBuilder
{
    use RunsRepurposeClaudeCli;

    /** Sanity ceiling — a real listicle is never dozens of slides; trim + log the absurd case. */
    public const MAX_TOOL_SLIDES = 20;

    /**
     * Parse the numbered tool list out of the source caption.
     *
     * Handles the IG listicle convention `… 1) AutoHedge — run an AI hedge fund.
     * 2) Vibe Trading — 64 finance skills. …`, tolerant of em/en/hyphen/colon
     * name–desc separators and leading prose before item 1. Stops each item at
     * the next `N)` marker (so `$24,000` / `4 agents` inside a desc never split).
     *
     * @return list<array{name: string, desc: string}>
     */
    public function parseToolList(string $caption): array
    {
        $caption = trim($caption);
        if ($caption === '') {
            return [];
        }

        // Each numbered item = "N)" then everything up to the next "N)" or EOF.
        if (!preg_match_all('/\b(\d{1,2})\)\s*(.+?)(?=\s*\b\d{1,2}\)|$)/su', $caption, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $tools = [];
        foreach ($matches as $item) {
            $body = trim((string) preg_replace('/\s+/', ' ', $item[2]));
            if ($body === '') {
                continue;
            }
            // Name = up to the first em/en-dash, hyphen or colon separator; rest = desc.
            if (preg_match('/^(.+?)\s*[—–:]\s*(.+)$/u', $body, $parts)
                || preg_match('/^(.+?)\s+-\s+(.+)$/u', $body, $parts)) {
                $name = trim($parts[1]);
                $desc = trim($parts[2]);
            } else {
                $name = $body;
                $desc = '';
            }
            if ($name === '') {
                continue;
            }
            $tools[] = ['name' => $name, 'desc' => $desc];
        }

        if (count($tools) > self::MAX_TOOL_SLIDES) {
            Log::warning('[RepurposeCarouselBuilder] tool list exceeds sanity ceiling — trimming', [
                'found' => count($tools),
                'kept' => self::MAX_TOOL_SLIDES,
            ]);
            $tools = array_slice($tools, 0, self::MAX_TOOL_SLIDES);
        }

        return $tools;
    }

    /**
     * Phase D — assemble the full carousel_slides[] from a repurpose job's
     * fact-checked source: cover (hook) + ONE slide per tool + cta. Slide count
     * follows the source tool list. Returns [] when no tool list can be parsed
     * (caller routes to the legacy path / manual review).
     *
     * Per-slide CLI failure degrades to a deterministic fallback slide (never an
     * empty slide), so one flaky author can't break the whole carousel.
     *
     * @return list<array<string,mixed>>
     */
    public function buildSlides(RepurposeJob $job): array
    {
        $extracted = (array) ($job->extracted ?? []);
        $caption = (string) ($extracted['caption'] ?? '');
        $tools = $this->parseToolList($caption);
        if ($tools === []) {
            Log::warning('[RepurposeCarouselBuilder] no tool list parsed from caption', ['job' => $job->id]);

            return [];
        }

        $rewritten = (array) ($job->rewritten ?? []);
        $topic = trim((string) ($rewritten['title'] ?? '')) ?: $this->topicFromCaption($caption);
        $claims = array_map('strval', array_filter((array) ($extracted['claims'] ?? []), 'is_scalar'));
        $total = count($tools);

        $slides = [];

        $cover = $this->authorSlide('cover', ['topic' => $topic, 'tool_count' => $total]);
        $slides[] = $this->slide(1, 'cover', $cover, ['name' => $topic], ['is_cover' => true]);

        $number = 2;
        foreach ($tools as $i => $tool) {
            $authored = $this->authorSlide('tool', [
                'name' => $tool['name'],
                'desc' => $tool['desc'],
                'fact' => $this->factFor($tool['name'], $claims),
                'position' => $i + 1,
                'total' => $total,
            ]);
            $slides[] = $this->slide($number++, 'body', $authored, $tool);
        }

        $cta = $this->authorSlide('cta', ['topic' => $topic]);
        $slides[] = $this->slide($number, 'cta', $cta, ['name' => $topic], ['is_cta' => true]);

        return $slides;
    }

    /**
     * Resolve the source RepurposeJob for a carousel draft and build its
     * source-mirrored slides. Returns [] when the draft isn't a repurpose, has
     * no source job, or no tool list can be parsed — the caller then falls back
     * to the legacy /carousel-gen path.
     *
     * @return list<array<string,mixed>>
     */
    public function buildForDraftId(int $draftId): array
    {
        $job = RepurposeJob::where('linkedin_post_id', $draftId)->latest('id')->first();

        return $job ? $this->buildSlides($job) : [];
    }

    /**
     * Build one carousel_slides[] entry. On a failed author, degrade to a
     * deterministic bilingual-ish fallback from the source text (never empty) so
     * the pipeline stays whole.
     *
     * @param array{success: bool, copy_id: string, copy_en: string, image_prompt: string, error: string|null} $authored
     * @param array{name?: string, desc?: string} $source
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function slide(int $number, string $layout, array $authored, array $source, array $extra = []): array
    {
        if (!($authored['success'] ?? false)) {
            $name = trim((string) ($source['name'] ?? ''));
            $desc = trim((string) ($source['desc'] ?? ''));
            $line = $desc !== '' ? "{$name} — {$desc}" : $name;
            Log::warning('[RepurposeCarouselBuilder] slide author failed — deterministic fallback', [
                'slide' => $number, 'layout' => $layout, 'error' => $authored['error'] ?? null,
            ]);
            $authored = [
                'copy_id' => $line,
                'copy_en' => $line,
                'image_prompt' => "Sketchnote infographic slide for \"{$name}\": {$desc}",
            ];
        }

        return array_merge([
            'slide_number' => $number,
            'layout_hint' => $layout,
            'copy_id' => $authored['copy_id'],
            'copy_en' => $authored['copy_en'],
            'image_prompt' => $authored['image_prompt'],
            'image_status' => 'pending',
            'image_url' => null,
        ], $extra);
    }

    /** First claim that names the tool — the fact-checked detail for its slide. */
    private function factFor(string $name, array $claims): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        foreach ($claims as $claim) {
            if (stripos($claim, $name) !== false) {
                return (string) $claim;
            }
        }

        return '';
    }

    /** Fallback topic when the rewrite title is missing — first sentence of the caption. */
    private function topicFromCaption(string $caption): string
    {
        $caption = trim((string) preg_replace('/\s+/', ' ', $caption));
        $firstSentence = preg_split('/(?<=[.!?])\s/', $caption)[0] ?? $caption;

        return mb_substr(trim($firstSentence), 0, 120);
    }

    /**
     * Phase B/C — author ONE bilingual slide via a light CLI call (opsi A).
     * role ∈ cover | tool | cta. Returns bilingual copy + a sketchnote image
     * prompt. Every creative/visual rule (base colour, doodle gate, brand chrome,
     * bilingual hierarchy) is deferred to the carousel-gen bundle in the system
     * prompt — this method only frames the per-slide task. Slides are authored
     * INDEPENDENTLY so no monolithic N-slide envelope can truncate.
     *
     * @param array<string,mixed> $payload role-specific data (topic / tool / fact)
     * @return array{success: bool, copy_id: string, copy_en: string, image_prompt: string, error: string|null}
     */
    public function authorSlide(string $role, array $payload): array
    {
        try {
            $res = $this->runSlideAuthor($this->buildSlidePrompt($role, $payload));
        } catch (\Throwable $e) {
            Log::warning('[RepurposeCarouselBuilder] author exec threw', ['role' => $role, 'error' => $e->getMessage()]);

            return $this->authorFailure('exec_error');
        }

        if (!($res['success'] ?? false)) {
            return $this->authorFailure((string) ($res['error'] ?? 'author_failed'));
        }

        $parsed = (array) ($res['parsed'] ?? []);
        $copyId = trim((string) ($parsed['copy_id'] ?? ''));
        $copyEn = trim((string) ($parsed['copy_en'] ?? ''));
        $imagePrompt = trim((string) ($parsed['image_prompt'] ?? ''));

        // Bilingual is non-negotiable: BOTH languages must be present, never EN-only.
        if ($copyId === '' || $copyEn === '' || $imagePrompt === '') {
            return $this->authorFailure('incomplete_slide');
        }

        return [
            'success' => true,
            'copy_id' => $copyId,
            'copy_en' => $copyEn,
            'image_prompt' => $imagePrompt,
            'error' => null,
        ];
    }

    /** @return array{success: false, copy_id: string, copy_en: string, image_prompt: string, error: string} */
    private function authorFailure(string $error): array
    {
        return ['success' => false, 'copy_id' => '', 'copy_en' => '', 'image_prompt' => '', 'error' => $error];
    }

    /**
     * Test seam — wraps the trait CLI call so tests can subclass + inject canned
     * slide JSON without a real Claude CLI.
     *
     * @return array{success: bool, parsed: array<string,mixed>|null, output: string, error: string|null, repaired: bool}
     */
    protected function runSlideAuthor(string $prompt): array
    {
        return $this->runRepurposeParsed(
            $prompt,
            'repurpose-carousel-slide',
            ['copy_id', 'copy_en', 'image_prompt'],
            (string) config('services.repurpose.model_slide_author', 'sonnet'),
            $this->refsBundle()
        );
    }

    /**
     * Shares the SAME compiled knowledge bundle as /carousel-gen (sketchnote
     * style-presets + creator-bible + bilingual hierarchy + brand chrome) so the
     * per-slide author and the carousel pipeline stay one source of truth.
     * Defaults to the carousel-gen bundle so it can NEVER be silently empty.
     */
    protected function refsBundle(): string
    {
        $refs = (string) config('services.repurpose.refs_carousel', '');

        return $refs !== '' ? $refs : (string) config('carousel-gen.refs_pipeline', '');
    }

    /**
     * Thin per-slide task framing. Visual rules live in the bundle (system
     * prompt) — do NOT restate base colour / doodle gate / chrome here (would
     * duplicate + drift). Bilingual: copy_id = Indonesian PRIMARY/main line,
     * copy_en = English companion (same meaning, shorter). 4:5 portrait.
     *
     * @param array<string,mixed> $payload
     */
    private function buildSlidePrompt(string $role, array $payload): string
    {
        $jsonShape = '{"copy_id": "...", "copy_en": "...", "image_prompt": "..."}';
        $strict = "STRICT JSON OUTPUT — parsed by a machine: output ONE compact JSON object only, no markdown "
            . "fences, no preamble, no trailing prose. Escape every double-quote inside a value as \\\". "
            . "copy_id is INDONESIAN (primary/main headline line), copy_en is the ENGLISH companion (same "
            . "meaning, shorter) — BOTH required, NEVER English-only. Return exactly: {$jsonShape}";

        $apply = "Apply the sketchnote infographic + bilingual headline + brand standards from your system "
            . "prompt (the /carousel-gen knowledge). Do not restate or invent visual rules; defer to the standard.";

        if ($role === 'tool') {
            $name = trim((string) ($payload['name'] ?? ''));
            $desc = trim((string) ($payload['desc'] ?? ''));
            $fact = trim((string) ($payload['fact'] ?? ''));
            $position = (int) ($payload['position'] ?? 0);
            $total = (int) ($payload['total'] ?? 0);

            return <<<PROMPT
You are authoring ONE body slide ({$position} of {$total}) of an educational Instagram/LinkedIn carousel. This slide teaches EXACTLY ONE tool — never mention or combine any other tool.

Tool: "{$name}"
What it does (already fact-checked — use it, do NOT contradict or invent beyond it): {$desc}
Verified detail: {$fact}

{$apply}

Author a self-contained mini-infographic for THIS one tool: copy_id/copy_en = the tool name + one-line what-it-does + the single most useful verified fact. image_prompt = the 4:5 sketchnote doodle slide that conveys what this ONE tool does (tool name + a doodle of its function + the key fact), per the standard.

{$strict}
PROMPT;
        }

        if ($role === 'cta') {
            $topic = trim((string) ($payload['topic'] ?? ''));

            return <<<PROMPT
You are authoring the final CTA slide of an educational Instagram/LinkedIn carousel about: "{$topic}".

{$apply}

copy_id/copy_en = ONE single clear command (save/follow), bilingual — not a list of asks. image_prompt = the 4:5 sketchnote CTA slide per the standard (the page number / @handle watermark / social block are added separately, so omit them).

{$strict}
PROMPT;
        }

        // cover (hook)
        $topic = trim((string) ($payload['topic'] ?? ''));
        $count = (int) ($payload['tool_count'] ?? 0);

        return <<<PROMPT
You are authoring the COVER (hook, slide 1) of an educational Instagram/LinkedIn carousel about: "{$topic}" — a list of {$count} tools revealed across the following slides.

{$apply}

CURIOSITY GAP — the cover MUST NOT reveal, enumerate, or display the specific tools (no row of tool cards/logos spelling out the list); tease and withhold so the reader swipes. copy_id/copy_en = a scroll-stopping bilingual hook headline (Indonesian dominant + English subtitle). image_prompt = the 4:5 sketchnote/Spotlight cover per the standard (page number / @handle watermark / swipe pill are added separately, so omit them).

{$strict}
PROMPT;
    }
}
