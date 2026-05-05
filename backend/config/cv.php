<?php

/*
|--------------------------------------------------------------------------
| CV Master Markdown Skill Domains
|--------------------------------------------------------------------------
|
| Hand-curated skill domains rendered into the Skills Matrix section of
| GET /api/cv/master.md. Each domain joins with a project count derived
| from CvProjectResource::relevance_hint heuristic at render time.
|
| `key` matches a value emitted by the relevance_hint heuristic
| (ai_automation, vibe_coding, ai_agents, manufacturing, fintech,
| logistics, gov_tech, enterprise) so project counts aggregate correctly.
|
| `years` is hand-set since Ali's career history isn't fully encoded in
| the projects table. Update annually.
|
| `bullets` are short phrases (1 line each) — keep dense to control
| token budget on the LLM-consuming end (jobhunter cv-tailor, job-score).
|
*/

return [

    'skill_domains' => [

        [
            'key' => 'ai_automation',
            'label' => 'AI Automation',
            'years' => 7,
            'bullets' => [
                'LLM orchestration, RAG pipelines, prompt engineering',
                'n8n / Zapier / Make multi-step workflows',
                'Custom skills + MCP server integrations',
            ],
        ],

        [
            'key' => 'vibe_coding',
            'label' => 'Vibe Coding',
            'years' => 3,
            'bullets' => [
                'Claude Code / Cursor / Aider — pair-programming with LLMs',
                'Spec-driven development: brief → plan → execute → verify',
                'AI-augmented refactoring + test generation',
            ],
        ],

        [
            'key' => 'ai_agents',
            'label' => 'AI Agents',
            'years' => 2,
            'bullets' => [
                'Multi-agent orchestration with role-based prompting',
                'Tool-use design + safety guardrails',
                'Claude Agent SDK, OpenAI Assistants, LangGraph',
            ],
        ],

        [
            'key' => 'manufacturing',
            'label' => 'Industrial Automation & Manufacturing',
            'years' => 12,
            'bullets' => [
                'PLC programming, SCADA, HMI design',
                'Computer vision QA pipelines + edge inference',
                'Palm oil mill (PKS) automation, sorting, inspection',
            ],
        ],

        [
            'key' => 'enterprise',
            'label' => 'Enterprise Software',
            'years' => 15,
            'bullets' => [
                'Laravel + Vue full-stack delivery',
                'API design, queue infrastructure, deploy automation',
                'Banking, government, logistics domain experience',
            ],
        ],

    ],

];
