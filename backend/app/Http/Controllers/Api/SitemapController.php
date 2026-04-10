<?php

namespace App\Http\Controllers\Api;

use App\Models\Post;
use App\Models\Project;
use App\Models\Category;
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
        return $baseUrl . $path;
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

    /**
     * Generate XML sitemap
     * Response: application/xml
     */
    public function index()
    {
        $urls = [
            // Static pages with highest priority
            [
                'loc' => $this->frontendUrl('/'),
                'lastmod' => Carbon::now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => $this->frontendUrl('/blog'),
                'lastmod' => Post::published()->latest('published_at')->first()?->published_at?->toAtomString() ?? Carbon::now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => $this->frontendUrl('/projects'),
                'lastmod' => Project::latest('updated_at')->first()?->updated_at?->toAtomString() ?? Carbon::now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            [
                'loc' => $this->frontendUrl('/about'),
                'lastmod' => Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
            [
                'loc' => $this->frontendUrl('/contact'),
                'lastmod' => Carbon::now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ],
        ];

        // Add published blog posts
        $posts = Post::published()
            ->select('slug', 'published_at', 'updated_at')
            ->orderByDesc('published_at')
            ->get();

        foreach ($posts as $post) {
            $urls[] = [
                'loc' => $this->frontendUrl("/blog/{$post->slug}"),
                'lastmod' => $post->updated_at->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ];
        }

        // Add projects
        $projects = Project::select('slug', 'updated_at')->get();

        foreach ($projects as $project) {
            $urls[] = [
                'loc' => $this->frontendUrl("/projects/{$project->slug}"),
                'lastmod' => $project->updated_at->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        // Add categories
        $categories = Category::select('slug', 'updated_at')
            ->whereHas('posts', function ($q) {
                $q->published();
            })
            ->get();

        foreach ($categories as $category) {
            $urls[] = [
                'loc' => $this->frontendUrl("/blog/category/{$category->slug}"),
                'lastmod' => $category->updated_at->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        return response()->view('sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Generate sitemap index (for large sites with multiple sitemaps)
     */
    public function sitemapIndex()
    {
        $sitemaps = [
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
     * Posts-only sitemap
     */
    public function posts()
    {
        $posts = Post::published()
            ->with('translations:id,post_id,language,slug')
            ->select('id', 'slug', 'published_at', 'updated_at')
            ->orderByDesc('published_at')
            ->paginate(50000); // Google limit: 50,000 URLs per sitemap

        $frontendBase = rtrim(config('app.frontend_url', 'https://alisadikinma.com'), '/');

        $urls = [];
        foreach ($posts->items() as $post) {
            $alternates = collect(['en' => $post->slug]);

            if ($post->relationLoaded('translations')) {
                foreach ($post->translations as $translation) {
                    $slug = !empty($translation->slug) ? $translation->slug : $post->slug;
                    $alternates->put($translation->language, $slug);
                }
            }

            $enSlug = $alternates->get('en', $post->slug);
            $urls[] = [
                'loc' => "{$frontendBase}/en/blog/{$enSlug}",
                'lastmod' => $post->updated_at->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.8',
                'alternates' => $alternates->count() > 1 ? $alternates->all() : [],
                'x_default' => "{$frontendBase}/en/blog/{$enSlug}",
                'frontend_base' => $frontendBase,
            ];
        }

        return response()->view('sitemap-posts', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Projects-only sitemap
     */
    public function projects()
    {
        $projects = Project::select('slug', 'updated_at')
            ->paginate(50000);

        $urls = array_map(function ($project) {
            return [
                'loc' => $this->frontendUrl("/projects/{$project->slug}"),
                'lastmod' => $project->updated_at->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }, $projects->items());

        return response()->view('sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
