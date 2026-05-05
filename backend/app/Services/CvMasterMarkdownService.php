<?php

namespace App\Services;

use App\Models\Setting;
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
            'summary' => $about->get('bio') ?: '',
            'email' => $about->get('email'),
            'phone' => $about->get('phone'),
            'city' => $about->get('city'),
            'country' => $about->get('country'),
            'profiles' => $this->parseSocialLinks($about->get('social_links')),
        ];

        return View::make('cv.master', [
            'basics' => $basics,
            'compact' => $compact,
        ])->render();
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
