<?php

namespace App\Http\Resources\Cv;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CvProjectResource — JSON Resume-flavored project shape for the CV
 * Master Export endpoint consumed by jobhunter platforms.
 *
 * Schema mapping notes
 * --------------------
 * The `projects` table stores `title` + `description` as direct columns
 * (legacy single-language path). i18n via project_translations is optional
 * and frequently empty for older records — when present, the primary-language
 * translation (id) wins, falling back to whatever locale exists.
 *
 * `industry` does not exist as a column. We surface `domain` first
 * (Case Study additions) and fall back to `category` for legacy rows.
 *
 * `metrics` is parsed from `tech_stack_details` (JSON) when present —
 * authored figures live there. Otherwise empty object so callers can
 * always read it as a map.
 *
 * `highlights` collects the strongest narrative beats from the case-study
 * fields when populated: impact_statement, problem, solution, result.
 *
 * `relevance_hint` heuristic
 * --------------------------
 * 1-3 hints derived from title / tags / category / domain. Lowercase scan:
 *   - "AI" / "agent" / "automation" / "RPA" / "ML" → ai_automation, ai_agents
 *   - "vibe coding" / "claude" / "cursor" / "ai coding" → vibe_coding
 *   - "manufacturing" / "factory" / "ISC" → enterprise, manufacturing
 *   - "logistics" / "supply chain" / "port" → logistics, enterprise
 *   - "government" → gov_tech, enterprise
 *   - "banking" / "finance" → fintech
 * Plus: any 6-figure savings figure in metrics → enterprise
 *
 * Hints are deduped, capped at 3 entries.
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

        $name = $translation?->title ?? $this->title;
        $description = $translation?->description ?? $this->description;

        // Surface domain (Case Study) first, fall back to legacy category.
        $industry = $this->domain ?: $this->category;

        // tags column is JSON-cast to array; technologies similarly.
        $tags = collect((array) ($this->tags ?? []))
            ->merge((array) ($this->technologies ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $metrics = $this->extractMetrics();
        $highlights = $this->extractHighlights();
        $relevanceHint = $this->buildRelevanceHint($name, $industry, $tags, $metrics);

        // Date column candidates: start_date / end_date (Case Study era),
        // completed_at (legacy single-date rows).
        $startDate = $this->formatYearMonth($this->start_date);
        $endDate = $this->formatYearMonth($this->end_date) ?? $this->formatYearMonth($this->completed_at);

        return [
            'name' => $name,
            'description' => $description,
            'url' => $this->slug ? rtrim(config('app.url'), '/') . '/projects/' . $this->slug : null,
            'industry' => $industry,
            'metrics' => $metrics,
            'tags' => $tags,
            'highlights' => $highlights,
            'relevance_hint' => $relevanceHint,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
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
     * Distill a short list of bullet-style highlights from the Case Study
     * narrative fields. Strips HTML tags + collapses whitespace so the
     * downstream consumer can render them as plain bullets.
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
            ->values()
            ->all();
    }

    /**
     * Heuristic — see class docblock for the full rule table.
     */
    protected function buildRelevanceHint(?string $name, ?string $industry, array $tags, array $metrics): array
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $name,
            $industry,
            implode(' ', $tags),
        ])), 'UTF-8');

        $hints = [];

        // AI / automation cluster
        if (preg_match('/\b(ai|agent|automation|rpa|ml)\b/', $haystack)) {
            $hints[] = 'ai_automation';
            $hints[] = 'ai_agents';
        }
        if (str_contains($haystack, 'vibe coding')
            || str_contains($haystack, 'claude')
            || str_contains($haystack, 'cursor')
            || str_contains($haystack, 'ai coding')) {
            $hints[] = 'vibe_coding';
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

        return collect($hints)->unique()->take(3)->values()->all();
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
