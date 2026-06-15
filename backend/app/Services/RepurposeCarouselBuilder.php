<?php

namespace App\Services;

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
}
