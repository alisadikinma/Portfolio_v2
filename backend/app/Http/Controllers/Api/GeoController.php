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
}
