<?php

namespace App\Http\Controllers\Api;

use App\Models\Award;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    /**
     * Frontend base URL — uses FRONTEND_URL env var in production.
     */
    protected function frontendUrl($path = '')
    {
        $baseUrl = config('app.frontend_url', 'http://localhost:5173');

        return $baseUrl.$path;
    }

    /**
     * API base URL — always uses https in production.
     */
    protected function apiUrl($path = '')
    {
        $base = url($path);
        if (app()->isProduction()) {
            $base = str_replace('http://', 'https://', $base);
        }

        return $base;
    }

    private const LANGS = ['id', 'en'];

    /** Pages whose first path segment differs per language: [id, en]. */
    private const SEGMENT_PAIRS = [
        'training' => ['pelatihan-ai', 'ai-training'],
        'speaker' => ['pembicara-ai', 'ai-speaker'],
    ];

    private function siteBase(): string
    {
        return rtrim((string) config('app.frontend_url', 'https://alisadikinma.com'), '/');
    }

    /**
     * One <url> per language version; every entry lists all given alternates plus
     * x-default (English when present, else the first language that exists).
     *
     * @param  array<string,string>  $paths  lang => path (no host), only languages that exist
     * @param  array<string,?string>  $lastmod  lang => ISO date or null (null = omitted, never faked)
     * @return array<int,array<string,mixed>>
     */
    private function localizedEntries(array $paths, string $changefreq, string $priority, array $lastmod = []): array
    {
        if ($paths === []) {
            return [];
        }
        $base = $this->siteBase();
        $alternates = [];
        foreach (self::LANGS as $lang) {
            if (isset($paths[$lang])) {
                $alternates[$lang] = $base.$paths[$lang];
            }
        }
        $alternates['x-default'] = $alternates['en'] ?? reset($alternates);

        $entries = [];
        foreach (self::LANGS as $lang) {
            if (! isset($paths[$lang])) {
                continue;
            }
            $entries[] = [
                'loc' => $base.$paths[$lang],
                'alternates' => $alternates,
                'lastmod' => $lastmod[$lang] ?? null,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        }

        return $entries;
    }

    /** Same path in both languages. */
    private function bothLangs(string $suffix): array
    {
        return ['id' => "/id{$suffix}", 'en' => "/en{$suffix}"];
    }

    private function render(array $urls)
    {
        return response()->view('sitemap-hreflang', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Pages sitemap: static pages, training/speaker, blog categories, awards.
     * Response: application/xml
     */
    public function index()
    {
        $urls = [];
        $add = function (array $paths, string $freq, string $prio, array $lastmod = []) use (&$urls) {
            array_push($urls, ...$this->localizedEntries($paths, $freq, $prio, $lastmod));
        };

        $add($this->bothLangs(''), 'weekly', '1.0');
        $add($this->bothLangs('/blog'), 'daily', '0.9');
        $add($this->bothLangs('/projects'), 'weekly', '0.9');
        $add($this->bothLangs('/work'), 'weekly', '0.8');
        $add($this->bothLangs('/about'), 'monthly', '0.8');
        $add($this->bothLangs('/faq'), 'monthly', '0.7');
        $add($this->bothLangs('/contact'), 'monthly', '0.7');
        foreach (self::SEGMENT_PAIRS as [$idSeg, $enSeg]) {
            $add(['id' => "/id/{$idSeg}", 'en' => "/en/{$enSeg}"], 'monthly', '0.9');
        }

        // Categories: a language is listed only if a published post in it exists.
        $categoryLangs = [];
        $slugById = Category::query()->pluck('slug', 'id');
        $posts = Post::published()->with('translations:id,post_id,language')->get(['id', 'category_id']);
        foreach ($posts as $post) {
            $slug = $slugById[$post->category_id] ?? null;
            if (! $slug) {
                continue;
            }
            foreach ($post->translations as $t) {
                if (in_array($t->language, self::LANGS, true)) {
                    $categoryLangs[$slug][$t->language] = "/{$t->language}/blog/category/".rawurlencode($slug);
                }
            }
        }
        ksort($categoryLangs);
        foreach ($categoryLangs as $paths) {
            $add($paths, 'weekly', '0.6');
        }

        $awardSlugs = Award::query()->whereNotNull('slug')->where('slug', '!=', '')->orderBy('id')->pluck('slug');
        foreach ($awardSlugs as $slug) {
            $enc = rawurlencode($slug);
            $add(['id' => "/id/penghargaan/{$enc}", 'en' => "/en/awards/{$enc}"], 'monthly', '0.6');
        }

        return $this->render($urls);
    }

    /**
     * Sitemap index: pages, posts, projects.
     */
    public function sitemapIndex()
    {
        $sitemaps = [
            [
                'loc' => $this->apiUrl('/api/sitemap.xml'),
                'lastmod' => Post::published()->latest('published_at')->first()?->published_at?->toAtomString() ?? Carbon::now()->toAtomString(),
            ],
            [
                'loc' => $this->apiUrl('/api/sitemap-posts.xml'),
                'lastmod' => Post::published()->latest('published_at')->first()?->published_at?->toAtomString() ?? Carbon::now()->toAtomString(),
            ],
            [
                'loc' => $this->apiUrl('/api/sitemap-projects.xml'),
                'lastmod' => Project::latest('updated_at')->first()?->updated_at?->toAtomString() ?? Carbon::now()->toAtomString(),
            ],
        ];

        return response()->view('sitemap-index', ['sitemaps' => $sitemaps], 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Posts sitemap: each post only in the languages it exists in (a missing
     * language 302-redirects on the site, so it is never listed).
     */
    public function posts()
    {
        $posts = Post::published()
            ->with('translations:id,post_id,language,slug,updated_at')
            ->select('id', 'slug', 'published_at', 'content_reviewed_at')
            ->orderByDesc('published_at')
            ->limit(50000) // Google limit: 50,000 URLs per sitemap
            ->get();

        $urls = [];
        foreach ($posts as $post) {
            $paths = [];
            $lastmod = [];
            foreach ($post->translations as $t) {
                if (! in_array($t->language, self::LANGS, true) || isset($paths[$t->language])) {
                    continue;
                }
                $slug = ! empty($t->slug) ? $t->slug : $post->slug;
                $paths[$t->language] = "/{$t->language}/blog/".rawurlencode($slug);
                $lastmod[$t->language] = ($post->contentUpdatedAt($t->language) ?? $post->published_at)?->toAtomString();
            }
            array_push($urls, ...$this->localizedEntries($paths, 'monthly', '0.8', $lastmod));
        }

        return $this->render($urls);
    }

    /**
     * Projects sitemap: published + active projects, both languages.
     */
    public function projects()
    {
        $projects = Project::query()
            ->where('published', true)
            ->where('is_active', true)
            ->select('slug', 'updated_at')
            ->limit(50000)
            ->get();

        $urls = [];
        foreach ($projects as $project) {
            if (empty($project->slug)) {
                continue;
            }
            $enc = rawurlencode($project->slug);
            $at = $project->updated_at?->toAtomString();
            array_push($urls, ...$this->localizedEntries(
                $this->bothLangs("/projects/{$enc}"),
                'monthly',
                '0.7',
                ['id' => $at, 'en' => $at],
            ));
        }

        return $this->render($urls);
    }
}
