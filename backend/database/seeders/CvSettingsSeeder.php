<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * CV Master Export Settings (May 2026 — schema_version 2.0.0)
 *
 * Seeds the four JSON keys consumed by /api/cv/export:
 *   - summary_variants: 3 hand-crafted opening paragraphs (vibe_coding /
 *     ai_automation / ai_video). jobhunter scores each scraped role
 *     against all three and picks the strongest match.
 *   - work_experience: employment history, separate from projects[]. Keeps
 *     ATS years-of-experience math honest (without this, scorers double
 *     count by treating each project as a job).
 *   - skills_matrix: categorized skills object (languages / frameworks /
 *     ai_tools / cloud / databases / infrastructure / domain). ATS keyword
 *     matchers read this shape directly.
 *   - education: degrees + courses. Empty list `[]` is valid (renders
 *     nothing on the consumer side).
 *
 * Idempotent via firstOrCreate — operator-edited values survive deploys.
 * Defaults below are sourced from the May 2026 enhancement spec; operator
 * is expected to update via /admin/about (CV Master Export card) when the
 * generic seeded values diverge from reality.
 */
class CvSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'summary_variants',
                'value' => json_encode([
                    'vibe_coding' => 'Full-stack vibe coder shipping production AI apps from prompt → deploy in days. INDUSIA.ai stack: Next.js 15 + FastAPI + Claude Sonnet 4.6. Demo Day Champion #1 — Outskill AI Generalist Fellowship. Batam, working globally.',
                    'ai_automation' => 'AI Visual Inspection live on production lines at Evident Scientific (Olympus successor) and Novanta (NASDAQ:NOVT). Replacing $24K Keyence rigs with $19,950 deploys. 17 years enterprise → solo AI studio. Specialty: industrial CV + workflow automation that survives factory-floor reality.',
                    'ai_video' => 'AI video generation pipeline operator — VEO 3, Sora, Runway, Kling. Built 7-beat narrative-arc engine with 4-stage validation (script → image → video → reference consistency). Champion of Outskill Demo Day with Sparkfluence: AI-Powered Platform for Viral Content Creation.',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'key' => 'work_experience',
                'value' => json_encode([
                    [
                        'company' => 'INDUSIA.ai',
                        'position' => 'Founder / Solo AI Engineer',
                        'start_date' => '2024-01',
                        'end_date' => null,
                        'location' => 'Batam, Indonesia (remote-global)',
                        'summary' => 'AI Visual Inspection deployments for industrial QC. Customers: Evident Scientific, Novanta.',
                        'highlights' => [
                            [
                                'text' => 'Replaced $24K Keyence rigs with $19,950 INDUSIA deploys at Evident Scientific (Olympus industrial successor)',
                                'metrics' => ['cost_saved_usd' => 4050, 'deployments' => 1],
                                'tags' => ['industrial', 'computer_vision', 'qc'],
                                'variant_hint' => ['ai_automation'],
                            ],
                        ],
                        'tech_stack' => ['Python', 'PyTorch', 'ONNX', 'FastAPI', 'Docker'],
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'key' => 'skills_matrix',
                'value' => json_encode([
                    'languages' => ['Python', 'TypeScript', 'JavaScript', 'PHP', 'SQL'],
                    'frameworks' => ['FastAPI', 'Next.js 15', 'React 19', 'Laravel', 'Vue 3', 'PyTorch'],
                    'ai_tools' => ['Claude Sonnet 4.6', 'GPT-4', 'VEO 3', 'Sora', 'Runway', 'Kling', 'n8n', 'LangChain', 'Cursor'],
                    'cloud' => ['AWS', 'GCP', 'DigitalOcean', 'Hostinger VPS', 'Cloudflare'],
                    'databases' => ['PostgreSQL', 'MySQL', 'Redis', 'SQLite', 'ChromaDB'],
                    'infrastructure' => ['Docker', 'Docker Compose', 'GitHub Actions', 'Traefik', 'Alembic'],
                    'domain' => ['Computer Vision', 'Industrial QC', 'RAG', 'AI Agents', 'Vibe Coding', 'Workflow Automation'],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            [
                'key' => 'education',
                // Empty list is valid — consumer renders nothing rather than erroring.
                'value' => json_encode([], JSON_UNESCAPED_SLASHES),
            ],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(
                ['key' => $row['key'], 'group' => 'cv'],
                ['value' => $row['value'], 'type' => 'json']
            );
        }

        if ($this->command) {
            $this->command->info('✅ CV Master Export settings seeded successfully!');
        }
    }
}
