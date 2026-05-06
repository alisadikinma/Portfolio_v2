<?php

namespace App\Http\Resources\Cv;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CvProjectResource — JSON Resume-flavored project shape for the CV
 * Master Export endpoint consumed by jobhunter platforms.
 *
 * Schema v2.0.0 changes (May 6, 2026)
 * -----------------------------------
 * - `variant_hint`: NEW — strict 3-value enum {vibe_coding, ai_automation,
 *   ai_video}. Replaces the loose 6-value `relevance_hint` for jobhunter's
 *   strict consumer schema. Old `relevance_hint` is preserved for back-compat
 *   but consumers should migrate.
 * - `tags`: freeform taxonomy (semantic categories). Was conflated with
 *   tech_stack — now distinct.
 * - `tech_stack`: NEW — concrete tools used to build the project (Python,
 *   PyTorch, etc.). Sourced from the `technologies` JSON column. ATS
 *   keyword matchers care intensely about this; tags is for filters/search.
 * - `highlights[]`: structured shape now. Each entry is
 *   {text, metrics, tags, variant_hint} (instead of bare strings). Existing
 *   case-study fields are wrapped into the structured shape with empty
 *   metrics/tags/variant_hint until per-project data entry catches up.
 * - `metrics{}`: object shape preserved (already correct).
 *
 * Schema mapping notes
 * --------------------
 * - `industry`: surfaces `domain` (Case Study) → `category` (legacy fallback).
 * - Translations: prefer `id` primary, fall back to first available, then
 *   to direct columns (legacy single-language rows).
 *
 * variant_hint heuristic (strict 3 values only)
 * ---------------------------------------------
 *   - "video" / "veo" / "sora" / "runway" / "kling" / "viral content"
 *     → ai_video
 *   - "vibe coding" / "claude" / "cursor" / "ai coding" → vibe_coding
 *   - "ai" / "agent" / "automation" / "rpa" / "ml" / "computer vision"
 *     / "industrial" → ai_automation
 * Multiple matches allowed; no cap. Empty array when nothing matches
 * (consumer treats absence as "uncategorized" — does NOT silently
 * default to ai_automation).
 */
class CvProjectResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        // Prefer Indonesian primary language (project default), fall back to
        // first available, then to direct column (legacy single-lang rows).
        $translation = $translations->firstWhere('language', 'id')
            ?? $translations->first();

        $name = $this->restoreAcronyms($translation?->title ?? $this->title);
        $description = $translation?->description ?? $this->description;

        // Surface domain (Case Study) first, fall back to legacy category.
        $industry = $this->restoreAcronyms($this->domain ?: $this->category);

        // tags column = freeform taxonomy (semantic categories).
        // technologies column = concrete tools used to build it.
        // Pre-v2: these were merged into a single tags[] field — split now
        // because ATS scorers want tech_stack distinct from semantic tags.
        $tags = collect((array) ($this->tags ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $techStack = collect((array) ($this->technologies ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $metrics = $this->extractMetrics();
        $highlights = $this->extractHighlights();
        $relevanceHint = $this->buildRelevanceHint($name, $industry, $tags, $techStack, $metrics);
        $variantHint = $this->buildVariantHint($name, $industry, $tags, $techStack);

        // Date column candidates: start_date / end_date (Case Study era),
        // completed_at (legacy single-date rows).
        $startDate = $this->formatYearMonth($this->start_date);
        $endDate = $this->formatYearMonth($this->end_date) ?? $this->formatYearMonth($this->completed_at);

        return [
            'name' => $name,
            'description' => $description,
            'url' => $this->slug ? rtrim(config('app.url'), '/') . '/projects/' . $this->slug : null,
            'industry' => $industry,
            // Cast to object so empty serializes as `{}` (object) not `[]`
            // (list). PHP encodes empty arrays as JSON arrays — consumer
            // schema declares metrics as a dict, the list shape fails strict
            // Pydantic validation. Non-empty associative arrays cast cleanly.
            'metrics' => (object) $metrics,
            'tags' => $tags,
            'tech_stack' => $techStack,
            'highlights' => $highlights,
            'variant_hint' => $variantHint,
            'relevance_hint' => $relevanceHint,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Restore tech acronym casing in titles that were title-cased at import
     * time (e.g. "Production Ai-Powered ... Cctv" → "Production AI-Powered
     * ... CCTV"). Word-boundary regex tolerates hyphen separators.
     *
     * Permanent fix is operator data-cleanup on the projects.title column;
     * until then, this output-time normalizer keeps the API surface clean
     * for downstream LLM / ATS consumers that read the canonical project
     * name from this field.
     */
    protected function restoreAcronyms(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        static $acronyms = [
            // Software / cloud / web
            'AI', 'ML', 'NLP', 'LLM', 'RAG', 'OCR', 'API', 'SDK', 'IDE',
            'AWS', 'GCP', 'CDN', 'DNS', 'HTTP', 'HTTPS', 'JSON', 'YAML',
            'XML', 'SQL', 'NoSQL', 'PHP', 'JS', 'TS', 'NPM', 'PDF', 'CSV',
            'JWT', 'OAuth', 'SaaS', 'B2B', 'B2C', 'CMS', 'CRM', 'ERP',
            'ETL', 'KPI', 'ROI', 'UI', 'UX', 'QA', 'QC', 'IT',
            'DevOps', 'MLOps', 'RPA', 'CCTV',
            // Hardware
            'GPU', 'CPU', 'RAM', 'SSD', 'USB', 'HDMI', 'LED', 'LCD',
            // Manufacturing / industrial / IoT
            'IoT', 'PCB', 'OEE', 'MES', 'SCADA', 'PLC', 'HMI',
            'RFID', 'NFC', 'GPS', 'UAV', 'LIDAR', 'IMU',
        ];

        foreach ($acronyms as $acronym) {
            $text = preg_replace(
                '/\b' . preg_quote($acronym, '/') . '\b/i',
                $acronym,
                $text
            );
        }
        return $text;
    }

    /**
     * Pull authored metrics out of tech_stack_details JSON when present.
     * Returns an empty associative array so callers can always treat the
     * field as a map.
     */
    protected function extractMetrics(): array
    {
        $details = $this->tech_stack_details;
        if (!is_array($details)) {
            return [];
        }
        // tech_stack_details may carry a structured "metrics" sub-key OR be
        // a freeform map — accept both shapes.
        if (isset($details['metrics']) && is_array($details['metrics'])) {
            return $details['metrics'];
        }
        // Filter to scalar leaf values (numbers + strings) so we don't
        // accidentally surface nested config objects in the CV payload.
        return collect($details)
            ->filter(fn ($v) => is_scalar($v))
            ->all();
    }

    /**
     * Distill structured highlights from the Case Study narrative fields.
     *
     * Schema v2.0.0 shape: each highlight is
     *   {text: string, metrics: object|null, tags: string[], variant_hint: string[]}
     *
     * Existing data only has plain text per case-study field (impact /
     * problem / solution / result), so metrics/tags/variant_hint default to
     * empty until per-project enrichment lands. Consumer parsers can
     * already validate against the spec'd Pydantic schema — they just see
     * empty enrichment slots.
     */
    protected function extractHighlights(): array
    {
        $candidates = array_filter([
            $this->impact_statement,
            $this->problem,
            $this->solution,
            $this->result,
        ]);

        return collect($candidates)
            ->map(fn ($s) => trim(preg_replace('/\s+/', ' ', strip_tags((string) $s))))
            ->filter(fn ($s) => $s !== '')
            ->map(fn ($s) => [
                'text' => $s,
                'metrics' => null,
                'tags' => [],
                'variant_hint' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * Strict variant hint per schema v2.0.0. Returns subset of
     * {vibe_coding, ai_automation, ai_video} only — never values outside
     * that triple. Empty array when nothing matches (signal: consumer
     * should not auto-categorize this entry).
     */
    protected function buildVariantHint(?string $name, ?string $industry, array $tags, array $techStack): array
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $name,
            $industry,
            implode(' ', $tags),
            implode(' ', $techStack),
        ])), 'UTF-8');

        $hints = [];

        // ai_video — explicit AI-video tooling or output focus.
        if (preg_match('/\b(veo|sora|runway|kling|viral\s+content|video\s+generation|ai\s+video)\b/', $haystack)) {
            $hints[] = 'ai_video';
        }

        // vibe_coding — IDE-coupled "AI authoring code in tight feedback loop".
        if (str_contains($haystack, 'vibe coding')
            || str_contains($haystack, 'claude code')
            || str_contains($haystack, 'cursor')
            || str_contains($haystack, 'ai coding')) {
            $hints[] = 'vibe_coding';
        }

        // ai_automation — broadest bucket: AI agents, RPA, computer vision,
        // workflow automation, industrial QC.
        if (preg_match('/\b(ai|agent|automation|rpa|ml|computer\s+vision|industrial|qc|workflow|n8n|zapier|langchain|rag)\b/', $haystack)) {
            $hints[] = 'ai_automation';
        }

        return collect($hints)->unique()->values()->all();
    }

    /**
     * Legacy 6-value relevance_hint kept for back-compat with v1.0.0
     * consumers. Adds enterprise / manufacturing / logistics / fintech /
     * gov_tech tags on top of the 3 strict variants.
     *
     * Heuristic — see class docblock for the full rule table.
     */
    protected function buildRelevanceHint(?string $name, ?string $industry, array $tags, array $techStack, array $metrics): array
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $name,
            $industry,
            implode(' ', $tags),
            implode(' ', $techStack),
        ])), 'UTF-8');

        $hints = $this->buildVariantHint($name, $industry, $tags, $techStack);

        // Legacy ai_agents marker (consolidated into ai_automation in v2.0.0
        // variant_hint, but kept for back-compat in relevance_hint).
        if (preg_match('/\b(agent|rag|langchain)\b/', $haystack)) {
            $hints[] = 'ai_agents';
        }

        // Industry verticals
        if (str_contains($haystack, 'manufacturing')
            || str_contains($haystack, 'factory')
            || str_contains($haystack, 'isc')) {
            $hints[] = 'manufacturing';
            $hints[] = 'enterprise';
        }
        if (str_contains($haystack, 'logistics')
            || str_contains($haystack, 'supply chain')
            || str_contains($haystack, 'port')) {
            $hints[] = 'logistics';
            $hints[] = 'enterprise';
        }
        if (str_contains($haystack, 'government')) {
            $hints[] = 'gov_tech';
            $hints[] = 'enterprise';
        }
        if (str_contains($haystack, 'banking') || str_contains($haystack, 'finance')) {
            $hints[] = 'fintech';
        }

        // Enterprise tag from 6-figure+ savings in any metric value
        foreach ($metrics as $metricValue) {
            if (!is_scalar($metricValue)) continue;
            $valueStr = (string) $metricValue;
            // Match "$100,000+", "100k", "1M", "USD 250,000" patterns
            if (preg_match('/(\$|usd\s*|rp\s*)?\s*(\d{1,3}(?:[,\.]\d{3})+|\d+\s*[mk])/i', $valueStr)) {
                // Tighter check: must be at least 6 digits worth
                $digits = preg_replace('/[^0-9]/', '', $valueStr);
                $hasMagnitudeSuffix = (bool) preg_match('/[mk]\b/i', $valueStr);
                if (strlen($digits) >= 5 || $hasMagnitudeSuffix) {
                    $hints[] = 'enterprise';
                    break;
                }
            }
        }

        return collect($hints)->unique()->take(5)->values()->all();
    }

    /**
     * Format a date column as YYYY-MM. Accepts Carbon instance, ISO string,
     * or a year-only string ("2019"). Returns null when nothing is parseable.
     */
    protected function formatYearMonth($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m');
        }
        if (is_string($value)) {
            // Plain year string ("2019") → return as YYYY-01
            if (preg_match('/^\d{4}$/', $value)) {
                return $value . '-01';
            }
            try {
                return \Carbon\Carbon::parse($value)->format('Y-m');
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
}
