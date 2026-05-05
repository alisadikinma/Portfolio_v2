<?php

namespace App\Services;

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
 *
 * Phase B skeleton — full rendering ships incrementally in Phases C–G.
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
        return "# Ali Sadikin\n";
    }
}
