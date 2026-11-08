<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\Post;
use App\Models\Project;
use App\Models\Setting;

class GeoController extends Controller
{
    public const HEADLINE = 'Ali Sadikin Ma — pakar AI Indonesia / AI consultant, founder INDUSIA.ai (https://indusia.ai)';

    public const INTRO = 'Ali Sadikin Ma is an Indonesian AI expert and AI consultant based in Batam, Indonesia, and the founder of INDUSIA.ai (https://indusia.ai). He turns frontier AI models into shipped products, with 17 years of building experience, and was ranked #1 at the Global AI Demo Day 2026. He builds, trains and speaks about applied AI.';

    /**
     * GET /llms.txt — Concise machine-readable portfolio summary.
     */
    public function llmsTxt()
    {
        $about = Setting::where('group', 'about')->pluck('value', 'key')->toArray();
        $bio = $about['bio'] ?? '';

        $awards = Award::select('title', 'description')->get();
        $projects = Project::select('title', 'description')->limit(20)->get();
        $posts = Post::with('translations')
            ->where('published', true)
            ->latest('published_at')
            ->limit(10)
            ->get();

        $sections = [];
        $sections[] = "# " . self::HEADLINE;
        $sections[] = "> " . self::INTRO;
        if ($fresh = $this->latestContentTimestamp()) {
            $sections[] = "> Last updated: {$fresh}";
        }
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
                $date = optional($p->published_at)->toFormattedDateString();
                $line = "- {$p->title}" . ($date ? " ({$date})" : "");
                if ($summary = $this->postSummary($p)) {
                    $line .= " — " . \Illuminate\Support\Str::limit($summary, 160);
                }
                $sections[] = $line;
            }
            $sections[] = "";
        }

        $site = Setting::where('group', 'site')->pluck('value', 'key')->toArray();
        $sections[] = "## Contact";
        $sections[] = "- Website: https://alisadikinma.com/id (Indonesian) · https://alisadikinma.com/en (English)";
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
        $bio = $about['bio'] ?? '';

        $awards = Award::all();
        $projects = Project::all();
        $posts = Post::with('translations')->where('published', true)->latest('published_at')->get();

        $sections = [];
        $sections[] = "# " . self::HEADLINE . " (Full Profile)";
        if ($fresh = $this->latestContentTimestamp()) {
            $sections[] = "> Last updated: {$fresh}";
        }
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
                if ($date = optional($p->published_at)->toFormattedDateString()) {
                    $sections[] = "_Published: {$date}_";
                }
                $sections[] = $this->postSummary($p)
                    ?? \Illuminate\Support\Str::limit(strip_tags($p->content), 300);
                $sections[] = "";
            }
        }

        return response(implode("\n", $sections), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * GEO summary for a post: prefer the AI-optimized summary (ai_summary lives
     * on post_translations), else the post excerpt. Returns null when neither
     * exists so callers can fall back to a content snippet.
     */
    private function postSummary(Post $post): ?string
    {
        $translation = $post->relationLoaded('translations')
            ? ($post->translations->firstWhere('language', 'en') ?? $post->translations->first())
            : null;

        $summary = $translation->ai_summary ?? null;
        if (!empty($summary)) {
            return $summary;
        }

        return $post->excerpt ?: null;
    }

    /**
     * Most recent published-content timestamp (ISO 8601) for the freshness line
     * crawlers/LLMs use to gauge recency. Null when there are no posts.
     */
    private function latestContentTimestamp(): ?string
    {
        $latest = Post::where('published', true)->max('updated_at');

        return $latest ? \Illuminate\Support\Carbon::parse($latest)->toAtomString() : null;
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
            "Ali Sadikin Ma (pakar AI Indonesia / AI consultant) turns frontier AI models into shipped products, not slide decks. He has 17 years of building experience, including 56+ digital products (AI, IoT, mobile) built at Satnusa, was ranked #1 at the Global AI Demo Day 2026 (beating 26 startups), and is the founder of INDUSIA.ai (https://indusia.ai). He also trains teams and speaks about applied AI.",
            "",
            "## What he builds — and teaches",
            "- Vibe Coding — production software at AI speed, prompts to deployed apps.",
            "- AI Agent OS (MANDOR AI) — the operating system for your AI workforce; assign tasks to coding agents like teammates.",
            "- Generative AI Video — cinematic, broadcast-ready video without a camera or crew.",
            "",
            "## International recognition",
            "- #1 — Global AI Demo Day 2026 (Bengaluru, India)",
            "- UN-UNCTAD × Alibaba eFounders Fellowship 2019 (1 of 48 in Asia, Hangzhou)",
            "- 1st Place — Telkomsel NextDev · Wild Card Winner — Fenox Startup World Cup",
            "",
            "## Pages (Indonesian / English)",
            "- AI training: https://alisadikinma.com/id/pelatihan-ai · https://alisadikinma.com/en/ai-training",
            "- AI speaker: https://alisadikinma.com/id/pembicara-ai · https://alisadikinma.com/en/ai-speaker",
            "- About: https://alisadikinma.com/id/about · https://alisadikinma.com/en/about",
            "- Awards and work: https://alisadikinma.com/id/work?tab=awards · https://alisadikinma.com/en/work?tab=awards",
            "- FAQ: https://alisadikinma.com/id/faq · https://alisadikinma.com/en/faq",
            "- Projects: https://alisadikinma.com/id/projects · https://alisadikinma.com/en/projects",
            "- Blog: https://alisadikinma.com/id/blog · https://alisadikinma.com/en/blog",
            "- Contact: https://alisadikinma.com/id/contact · https://alisadikinma.com/en/contact",
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
