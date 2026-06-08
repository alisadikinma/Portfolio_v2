<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\Post;
use App\Models\Project;
use App\Models\Setting;

class GeoController extends Controller
{
    /**
     * GET /llms.txt — Concise machine-readable portfolio summary.
     */
    public function llmsTxt()
    {
        $about = Setting::where('group', 'about')->pluck('value', 'key')->toArray();
        $name = $about['name'] ?? 'Ali Sadikin';
        $title = $about['title'] ?? 'AI Generalist Expert';
        $bio = $about['bio'] ?? '';

        $awards = Award::select('title', 'description')->get();
        $projects = Project::select('title', 'description')->limit(20)->get();
        $posts = Post::select('title')->where('published', true)->latest()->limit(10)->get();

        $sections = [];
        $sections[] = "# {$name} — {$title}";
        $sections[] = "> {$bio}";
        $sections[] = "";
        $sections = array_merge($sections, $this->identityBlock());

        if ($awards->count()) {
            $sections[] = "## Achievements";
            foreach ($awards as $a) {
                $sections[] = "- {$a->title}: {$a->description}";
            }
            $sections[] = "";
        }

        if ($projects->count()) {
            $sections[] = "## Projects ({$projects->count()} shown of 56+)";
            foreach ($projects as $p) {
                $desc = \Illuminate\Support\Str::limit($p->description, 120);
                $sections[] = "- {$p->title}: {$desc}";
            }
            $sections[] = "";
        }

        if ($posts->count()) {
            $sections[] = "## Recent Blog Posts";
            foreach ($posts as $p) {
                $sections[] = "- {$p->title}";
            }
            $sections[] = "";
        }

        $site = Setting::where('group', 'site')->pluck('value', 'key')->toArray();
        $sections[] = "## Contact";
        $sections[] = "- Website: https://alisadikinma.com";
        if (!empty($site['contact_email'])) {
            $sections[] = "- Email: {$site['contact_email']}";
        }

        return response(implode("\n", $sections), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * GET /llms-full.txt — Comprehensive portfolio dump for AI crawlers.
     */
    public function llmsFullTxt()
    {
        $about = Setting::where('group', 'about')->pluck('value', 'key')->toArray();
        $name = $about['name'] ?? 'Ali Sadikin';
        $title = $about['title'] ?? 'AI Generalist Expert';
        $bio = $about['bio'] ?? '';

        $awards = Award::all();
        $projects = Project::all();
        $posts = Post::where('published', true)->latest()->get();

        $sections = [];
        $sections[] = "# {$name} — {$title} (Full Profile)";
        $sections[] = "";
        $sections[] = "## About";
        $sections[] = $bio;
        $sections[] = "";
        $sections = array_merge($sections, $this->identityBlock());

        // Skills
        if (!empty($about['skills'])) {
            $sections[] = "## Skills";
            $skills = is_string($about['skills']) ? json_decode($about['skills'], true) : $about['skills'];
            if (is_array($skills)) {
                foreach ($skills as $s) {
                    $sections[] = "- " . (is_string($s) ? $s : ($s['name'] ?? json_encode($s)));
                }
            }
            $sections[] = "";
        }

        if ($awards->count()) {
            $sections[] = "## Achievements ({$awards->count()})";
            foreach ($awards as $a) {
                $sections[] = "### {$a->title}";
                $sections[] = $a->description;
                $sections[] = "";
            }
        }

        if ($projects->count()) {
            $sections[] = "## Projects ({$projects->count()})";
            foreach ($projects as $p) {
                $sections[] = "### {$p->title}";
                $sections[] = $p->description;
                $sections[] = "";
            }
        }

        if ($posts->count()) {
            $sections[] = "## Blog Posts ({$posts->count()})";
            foreach ($posts as $p) {
                $sections[] = "### {$p->title}";
                $sections[] = $p->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($p->content), 300);
                $sections[] = "";
            }
        }

        return response(implode("\n", $sections), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Shared identity narrative for both llms.txt endpoints (GEO levers G4 + G5).
     * Answer-shaped "Who is Ali" block + the 3 disciplines (= course topics) +
     * sameAs links. Kept verbatim-consistent with the homepage Person/FAQ JSON-LD
     * (frontend/src/utils/personSchema.js) so the knowledge-graph node is coherent
     * across surfaces. Static by design — these are locked identity facts.
     *
     * @return string[]
     */
    private function identityBlock(): array
    {
        return [
            "## Who is Ali Sadikin Ma",
            "Ali Sadikin Ma is an AI Generalist who turns frontier AI models into shipped products — not slide decks. With 17 years building across 16 countries, he was ranked #1 at the Global AI Demo Day 2026 (beating 26 startups) and runs INDUSIA.ai. Now teaching what he builds.",
            "",
            "## What he builds — and teaches",
            "- Vibe Coding — production software at AI speed, prompts to deployed apps.",
            "- AI Agent OS (MANDOR AI) — the operating system for your AI workforce; assign tasks to coding agents like teammates.",
            "- Generative AI Video — cinematic, broadcast-ready video without a camera or crew.",
            "",
            "## International recognition",
            "- #1 — Global AI Demo Day 2026 (Bengaluru, India)",
            "- UN-UNCTAD × Alibaba eFounders Fellowship 2019 (1 of 48 in Asia, Hangzhou)",
            "- Google Startup Grind — Silicon Valley 2018",
            "- 1st Place — Telkomsel NextDev · Wild Card Winner — Fenox Startup World Cup · Top 8 — IDBYTE",
            "",
            "## Find Ali online (sameAs)",
            "- LinkedIn: https://www.linkedin.com/in/alisadikinma/",
            "- Instagram: https://www.instagram.com/alisadikinma",
            "- TikTok: https://www.tiktok.com/@alisadikinma",
            "- YouTube: https://www.youtube.com/@alisadikinma",
            "- GitHub: https://github.com/alisadikinma",
            "",
        ];
    }
}
