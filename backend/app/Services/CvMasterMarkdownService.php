<?php

namespace App\Services;

use App\Http\Resources\Cv\CvProjectResource;
use App\Models\Award;
use App\Models\Post;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

/**
 * CV Master Markdown rendering service.
 *
 * Composes Ali's full professional profile (identity + summary + skills
 * + projects + awards + thought leadership) into one dense markdown
 * document for LLM-consuming clients (jobhunter cv-tailor, job-score).
 *
 * Reads the same data sources as App\Http\Controllers\Api\CvExportController
 * (settings group=about, projects with translations, awards, published
 * posts) so the two endpoints stay structurally consistent.
 */
class CvMasterMarkdownService
{
    /**
     * Render the master CV as a single markdown string.
     *
     * @param  bool  $compact  When true, truncate per-project narrative
     *                         (Problem/Outcome) for tighter LLM context
     *                         windows (~5k tokens vs. ~10k default).
     * @return string  Markdown body with leading H1 header.
     */
    public function render(bool $compact = false): string
    {
        $about = Setting::where('group', 'about')->pluck('value', 'key');

        $basics = [
            'name' => $about->get('name') ?: 'Ali Sadikin',
            'title' => $about->get('title') ?: 'AI Generalist Expert',
            'summary' => $this->normalizeMarkdownText($about->get('bio') ?: ''),
            'email' => $about->get('email'),
            'phone' => $about->get('phone'),
            'city' => $about->get('city'),
            'country' => $about->get('country'),
            'location' => $about->get('location'),
            'hero_tagline' => $this->normalizeMarkdownText($about->get('hero_tagline') ?: ''),
            'availability_note' => $this->normalizeMarkdownText($about->get('availability_note') ?: ''),
            'mission' => $this->normalizeMarkdownText($about->get('mission') ?: ''),
            'approach' => $this->normalizeMarkdownText($about->get('approach') ?: ''),
            'profiles' => $this->parseSocialLinks($about->get('social_links')),
        ];

        $skillsList = $this->parseJsonArray($about->get('skills'));
        $experienceList = $this->parseJsonArray($about->get('experience'));
        $educationList = $this->parseJsonArray($about->get('education'));

        $projects = Project::with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $skillDomains = $this->aggregateSkillDomains($projects);
        $projectRows = $projects->map(fn ($p) => $this->buildProjectRow($p, $compact))->all();
        $awardRows = $this->loadAwardRows($compact);
        $thoughtRows = $this->loadThoughtLeadershipRows($compact);

        $appUrl = rtrim(config('app.url'), '/');

        return View::make('cv.master', [
            'basics' => $basics,
            'compact' => $compact,
            'skill_domains' => $skillDomains,
            'skills_list' => $skillsList,
            'experience' => $this->buildExperienceRows($experienceList, $compact),
            'education' => $this->buildEducationRows($educationList),
            'projects' => $projectRows,
            'awards' => $awardRows,
            'thought_leadership' => $thoughtRows,
            'generated_at' => now()->toDateString(),
            'self_url' => $appUrl . '/api/cv/master.md',
        ])->render();
    }

    /**
     * Decode a settings JSON column into an array, returning [] on any
     * decode failure or non-array result.
     */
    protected function parseJsonArray($raw): array
    {
        if (!$raw) return [];
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Render experience entries into role lines with dates, company,
     * location, and a description (full in non-compact, summarized in compact).
     */
    protected function buildExperienceRows(array $entries, bool $compact): array
    {
        return collect($entries)->map(function (array $e) use ($compact) {
            $start = (string) ($e['start_date'] ?? '');
            $end = !empty($e['current']) ? 'present' : (string) ($e['end_date'] ?? '');
            $period = trim($start . ($end !== '' ? ' – ' . $end : ''));

            $description = (string) ($e['description'] ?? '');
            $rendered = $compact
                ? $this->summarize($description, 30)
                : $this->normalizeMarkdownText($description);

            return [
                'title' => $this->normalizeMarkdownText((string) ($e['title'] ?? '')),
                'company' => $this->normalizeMarkdownText((string) ($e['company'] ?? '')),
                'company_url' => (string) ($e['company_url'] ?? '') ?: null,
                'location' => $this->normalizeMarkdownText((string) ($e['location'] ?? '')) ?: null,
                'period' => $period !== '' ? $period : null,
                'current' => !empty($e['current']),
                'description' => $rendered ?: null,
            ];
        })->all();
    }

    /**
     * Render education entries into degree + institution + period + brief note.
     */
    protected function buildEducationRows(array $entries): array
    {
        return collect($entries)->map(fn (array $e) => [
            'degree' => $this->normalizeMarkdownText((string) ($e['degree'] ?? '')),
            'institution' => $this->normalizeMarkdownText((string) ($e['institution'] ?? '')),
            'period' => (string) ($e['period'] ?? '') ?: null,
            'description' => $this->normalizeMarkdownText((string) ($e['description'] ?? '')) ?: null,
        ])->all();
    }

    /**
     * Award rows ordered by `is_featured DESC, id DESC` — mirrors the
     * exact query used by CvExportController so the JSON and markdown
     * exports surface awards in the same priority.
     *
     * @return array<int, array{year:?string,title:string,organization:?string,description:?string}>
     */
    protected function loadAwardRows(bool $compact = false): array
    {
        return Award::orderByDesc('is_featured')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Award $a) => [
                'year' => $this->extractYear($a->received_at),
                'title' => $this->normalizeMarkdownText((string) $a->title),
                'organization' => $a->organization ? $this->normalizeMarkdownText((string) $a->organization) : null,
                'description' => $a->description
                    ? $this->summarize((string) $a->description, $compact ? 30 : 150)
                    : null,
            ])
            ->all();
    }

    /**
     * Top 5 published posts ordered by published_at DESC. Each row uses
     * the EN translation when available, otherwise falls back silently
     * to the primary translation, then to the direct title/excerpt
     * columns. Public URL uses `/blog/{slug}` per frontend routing.
     *
     * @return array<int, array{title:string,url:string,date:string,excerpt:string}>
     */
    protected function loadThoughtLeadershipRows(bool $compact = false): array
    {
        $appUrl = rtrim(config('app.url'), '/');

        return Post::with(['translations', 'category'])
            ->where('published', true)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit($compact ? 5 : 15)
            ->get()
            ->map(function (Post $p) use ($appUrl, $compact) {
                $translations = $p->relationLoaded('translations')
                    ? $p->translations
                    : $p->translations()->get();
                $en = $translations->firstWhere('language', 'en');
                $primary = $translations->first();

                $title = $en?->title ?? $primary?->title ?? $p->title;
                $excerpt = $en?->excerpt ?? $primary?->excerpt ?? $p->excerpt;

                return [
                    'title' => $this->normalizeMarkdownText((string) $title),
                    'url' => $appUrl . '/blog/' . $p->slug,
                    'date' => $p->published_at?->toDateString() ?? '',
                    'excerpt' => $this->summarize((string) ($excerpt ?? ''), $compact ? 24 : 80) ?? '',
                    'category' => $p->category?->name ?? null,
                ];
            })
            ->all();
    }

    /**
     * Pull a 4-digit year off whatever shape `awards.received_at` carries
     * (DateTimeInterface, ISO string, "2024" plain year, null).
     */
    protected function extractYear($value): ?string
    {
        if (!$value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y');
        }
        if (is_string($value) && preg_match('/(\d{4})/', $value, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Compose a render-ready row for a single project.
     *
     * Reuses CvProjectResource for industry / tags / metrics / highlights
     * / relevance_hint (single-source heuristic) but overrides the title
     * + description with English-preferring translation lookup. The CV
     * Master Markdown endpoint is English-only by design — Indonesian
     * fallback is silent (no "[ID]" prefix).
     *
     * Year range: prefers explicit start_date/end_date columns, falls
     * back to completed_at year when only that column is populated.
     *
     * @return array{
     *   title:string,
     *   role:?string,
     *   year_range:?string,
     *   industry:?string,
     *   tech_stack:?string,
     *   problem:?string,
     *   outcome:?string,
     *   relevance:?string
     * }
     */
    protected function buildProjectRow(Project $project, bool $compact = false): array
    {
        $request = Request::create('/api/cv/master.md', 'GET');
        $resource = (new CvProjectResource($project))->toArray($request);

        // English-preferring translation override (resource picks Indonesian).
        $translations = $project->relationLoaded('translations')
            ? $project->translations
            : $project->translations()->get();

        $en = $translations->firstWhere('language', 'en');
        $primary = $translations->first();
        $title = $en?->title ?? $primary?->title ?? $resource['name'] ?? $project->title;
        $description = $en?->description ?? $primary?->description ?? $resource['description'] ?? $project->description;

        // Year range — prefer start/end, fallback to completed_at year.
        $start = $this->yearFromDate($project->start_date);
        $end = $this->yearFromDate($project->end_date) ?? $this->yearFromDate($project->completed_at);
        $yearRange = $this->formatYearRange($start, $end);

        // Tech stack — full list in non-compact, capped at 5 in compact.
        $tags = collect($resource['tags'] ?? []);
        $techStack = ($compact ? $tags->take(5) : $tags)->implode(', ');

        // Narrative beats (impact/problem/solution/result) — only for non-compact.
        $narrative = $compact ? null : $this->buildNarrativeBeats($project);

        // Metrics map — surface authored numbers (savings, deploy counts, KPIs).
        $metrics = $compact ? null : $this->buildMetricsLine($resource['metrics'] ?? []);

        return [
            'title' => $this->normalizeMarkdownText((string) $title),
            'role' => $project->role ? (string) $project->role : null,
            'year_range' => $yearRange,
            'industry' => $resource['industry'] ?? null,
            'tech_stack' => $techStack !== '' ? $techStack : null,
            'description' => $compact ? null : ($this->normalizeMarkdownText((string) ($description ?? '')) ?: null),
            'problem' => $compact ? null : $this->summarize($description ?? '', 200),
            'outcome' => $compact ? null : $this->summarize($project->result ?? $project->impact_statement ?? '', 150),
            'narrative' => $narrative,
            'metrics' => $metrics,
            'url' => $resource['url'] ?? null,
            'relevance' => collect($resource['relevance_hint'] ?? [])->implode(', ') ?: null,
        ];
    }

    /**
     * Compose a 4-beat narrative array (Impact / Problem / Solution / Result)
     * from the project's case-study fields. Returns null when no beat is
     * populated. Each beat is HTML-stripped and word-bounded.
     *
     * @return array<int, array{label:string, text:string}>|null
     */
    protected function buildNarrativeBeats(Project $project): ?array
    {
        $beats = [
            ['label' => 'Impact', 'raw' => $project->impact_statement],
            ['label' => 'Problem', 'raw' => $project->problem],
            ['label' => 'Solution', 'raw' => $project->solution],
            ['label' => 'Result', 'raw' => $project->result],
        ];

        $rendered = collect($beats)
            ->map(fn ($beat) => [
                'label' => $beat['label'],
                'text' => $this->summarize((string) ($beat['raw'] ?? ''), 120) ?? '',
            ])
            ->filter(fn ($beat) => $beat['text'] !== '')
            ->values()
            ->all();

        return $rendered ?: null;
    }

    /**
     * Render the metrics map as an inline "key: value · key: value" string.
     * Returns null when empty so the Blade @if guard hides the line.
     */
    protected function buildMetricsLine(array $metrics): ?string
    {
        if (empty($metrics)) {
            return null;
        }
        $parts = [];
        foreach ($metrics as $key => $value) {
            if (!is_scalar($value)) continue;
            $label = is_string($key) ? str_replace('_', ' ', $key) : null;
            $parts[] = $label ? "{$label}: {$value}" : (string) $value;
        }
        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * Extract a 4-digit year from a value that may be a Carbon, ISO
     * string, year-only string, or null.
     */
    protected function yearFromDate($value): ?int
    {
        if (!$value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return (int) $value->format('Y');
        }
        if (is_string($value)) {
            if (preg_match('/^(\d{4})/', $value, $m)) {
                return (int) $m[1];
            }
        }
        return null;
    }

    /**
     * Format a year-range pair as "2022–2024" / "2022" / "2022–present".
     * Returns null when both ends are missing.
     */
    protected function formatYearRange(?int $start, ?int $end): ?string
    {
        if ($start === null && $end === null) {
            return null;
        }
        if ($start !== null && $end !== null && $start !== $end) {
            return $start . '–' . $end;
        }
        if ($start !== null && $end === null) {
            return $start . '–present';
        }
        return (string) ($start ?? $end);
    }

    /**
     * Strip HTML, decode entities, then truncate to ~N words while
     * preserving word boundaries. Returns null when input is empty.
     */
    protected function summarize(string $raw, int $words): ?string
    {
        $clean = $this->normalizeMarkdownText($raw);
        if ($clean === '') {
            return null;
        }
        // Single-line summary: collapse all whitespace to one space.
        $clean = preg_replace('/\s+/', ' ', $clean);
        $tokens = preg_split('/\s+/', $clean) ?: [];
        if (count($tokens) <= $words) {
            return $clean;
        }
        return rtrim(implode(' ', array_slice($tokens, 0, $words)), ".,;: ") . '…';
    }

    /**
     * Join the curated config('cv.skill_domains') copy with live counts
     * of how many projects produce each domain `key` via the CvProjectResource
     * `relevance_hint` heuristic.
     *
     * Reusing CvProjectResource keeps the heuristic single-sourced — no
     * duplicate string-matching rules between the JSON export and the
     * markdown export.
     *
     * @return array<int, array{key:string,label:string,years:int,bullets:array<int,string>,count:int}>
     */
    protected function aggregateSkillDomains(Collection $projects): array
    {
        $request = Request::create('/api/cv/master.md', 'GET');
        $hintsByDomain = [];

        foreach ($projects as $project) {
            $resource = (new CvProjectResource($project))->toArray($request);
            $hints = $resource['relevance_hint'] ?? [];
            foreach ($hints as $hint) {
                $hintsByDomain[$hint] = ($hintsByDomain[$hint] ?? 0) + 1;
            }
        }

        $domains = (array) config('cv.skill_domains', []);

        return array_map(static function (array $domain) use ($hintsByDomain) {
            return [
                'key' => $domain['key'],
                'label' => $domain['label'],
                'years' => $domain['years'],
                'bullets' => $domain['bullets'],
                'count' => $hintsByDomain[$domain['key']] ?? 0,
            ];
        }, $domains);
    }

    /**
     * Strip HTML and collapse whitespace so values authored as rich text
     * (e.g., the about.bio setting authored via WYSIWYG) render as clean
     * markdown paragraphs. Preserves paragraph breaks by inserting two
     * newlines after </p> and other block-closing tags before stripping.
     */
    protected function normalizeMarkdownText(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        // Treat consecutive <br> tags as paragraph breaks (rich-text editors
        // often emit <br><br> instead of wrapping content in <p>).
        $withParaBreaks = preg_replace('#(<br\s*/?\s*>\s*){2,}#i', "\n\n", $raw);
        // Single <br> → single newline (line break inside a paragraph).
        $withParaBreaks = preg_replace('#<br\s*/?\s*>#i', "\n", $withParaBreaks);
        // Insert paragraph breaks where HTML block tags close, then strip.
        $withBreaks = preg_replace(
            '#</(p|div|h[1-6]|li|blockquote)>#i',
            "$0\n\n",
            $withParaBreaks
        );
        $stripped = strip_tags($withBreaks);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse 3+ newlines down to 2, trim trailing whitespace per line.
        $normalized = preg_replace("/\n{3,}/", "\n\n", $decoded);
        $normalized = preg_replace("/[ \t]+\n/", "\n", $normalized);

        return trim($normalized);
    }

    /**
     * Decode the Settings.social_links JSON blob into a list of profile
     * rows shaped {network, url}. Mirrors the helper on
     * CvExportController so the two endpoints render identical socials.
     *
     * Hardened against:
     *   - settings row missing entirely (returns [])
     *   - JSON parse failure (returns [])
     *   - rows without a url (filtered out)
     */
    protected function parseSocialLinks($raw): array
    {
        if (!$raw) {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return collect($decoded)
            ->map(fn ($row) => [
                'network' => $row['platform'] ?? null,
                'url' => $row['url'] ?? null,
            ])
            ->filter(fn ($row) => !empty($row['url']))
            ->values()
            ->all();
    }
}
