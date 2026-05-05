<?php

namespace App\Services;

use App\Http\Resources\Cv\CvProjectResource;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\Request;
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
            'profiles' => $this->parseSocialLinks($about->get('social_links')),
        ];

        $projects = Project::with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $skillDomains = $this->aggregateSkillDomains($projects);

        return View::make('cv.master', [
            'basics' => $basics,
            'compact' => $compact,
            'skill_domains' => $skillDomains,
        ])->render();
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
        // Insert paragraph breaks where HTML block tags close, then strip.
        $withBreaks = preg_replace(
            '#</(p|div|h[1-6]|li|blockquote)>#i',
            "$0\n\n",
            $raw
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
