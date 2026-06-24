<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Testimonial;
use App\Services\Seo\SchemaGraphBuilder;
use App\Services\Seo\SeoHtmlComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Server-side SSR-enrichment for the homepage + blog surfaces so crawlers and
 * LLM/GEO bots that don't run JavaScript receive real per-page <head>, a JSON-LD
 * graph, hreflang and (for detail pages) a crawlable article body. Vue still
 * hydrates into #app on top — real users are unaffected.
 *
 * Activates only when the webserver routes the path to PHP-FPM (see
 * scripts/nginx/portfolio-8080.conf — widened in the SSR-deploy runbook).
 * Projects keep their existing OG-only injection in routes/web.php.
 *
 * Composed HTML is cached for 1h (key seo_html:*) and purged on Post save/delete
 * via purgeForPost(), wired from Post::boot(). Author identity is fixed to the
 * canonical Person entity for GEO consistency.
 */
class SpaPrerenderController extends Controller
{
    private const AUTHOR = 'Ali Sadikin Ma';
    private const LANGS = ['', 'en', 'id'];

    public function __construct(
        private SeoHtmlComposer $composer,
        private SchemaGraphBuilder $schema,
    ) {
    }

    // ---- Homepage -----------------------------------------------------------

    public function home()
    {
        if (!$this->distExists()) {
            return $this->devFallback('/');
        }

        $html = Cache::remember('seo_html:home', $this->ttl(), function () {
            $shell = $this->loadShell();

            // index.html already carries static Person + FAQPage blocks (G2/G3),
            // so we only ADD the WebSite entity + a crawlable identity summary —
            // never re-inject Person/FAQ (would duplicate the static graph).
            $body = View::make('seo.summary', [
                'heading' => 'Ali Sadikin Ma — AI Generalist Expert',
                'intro' => 'Ali Sadikin Ma is an AI Generalist who turns frontier AI models into shipped products — not slide decks. He builds and teaches AI Agents, Vibe Coding, and Generative Video. Latest writing:',
                'items' => $this->recentPostItems('', 5),
            ])->render();

            return $this->composer->compose($shell, [
                'jsonLd' => [
                    $this->schema->webSite(),
                    // Organization node carrying aggregateRating + review[] built
                    // from real, same-page-displayed testimonials (TestimonialsCarousel)
                    // — GEO review signal. Person + FAQ stay static in index.html.
                    $this->organizationRatingNode(),
                ],
                'bodyHtml' => $body,
            ]);
        });

        return $this->respond($html);
    }

    /**
     * Build the homepage Organization JSON-LD node with aggregateRating + top-5
     * review[] computed from active testimonials. Zero active testimonials → a
     * plain Organization node (no fabricated rating). One query; the home HTML is
     * cached 1h and busted on any Testimonial save/delete (Testimonial::boot()).
     */
    private function organizationRatingNode(): array
    {
        $testimonials = Testimonial::where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $count = $testimonials->count();
        if ($count < 1) {
            return $this->schema->organizationWithRating(['reviewCount' => 0], []);
        }

        $reviews = $testimonials->take(5)->map(fn (Testimonial $t) => [
            'author' => $t->client_name,
            'body' => $t->testimonial_text,
            'rating' => $t->star_rating,
        ])->all();

        return $this->schema->organizationWithRating(
            [
                'ratingValue' => round((float) $testimonials->avg('star_rating'), 1),
                'reviewCount' => $count,
            ],
            $reviews,
        );
    }

    // ---- Blog index ---------------------------------------------------------

    public function blogIndex(Request $request, ?string $lang = null)
    {
        $lang = $this->normalizeLang($lang);
        if (!$this->distExists()) {
            return $this->devFallback($this->blogIndexPath($lang));
        }

        $html = Cache::remember("seo_html:blog_index:{$lang}", $this->ttl(), function () use ($lang) {
            $shell = $this->loadShell();
            $items = $this->recentPostItems($lang, 20);
            $base = $this->baseUrl();
            $url = $base . $this->blogIndexPath($lang);

            $title = 'Blog — AI Agents, Vibe Coding & Generative Video | ' . self::AUTHOR;
            $description = 'Essays and field notes on AI Agents, Vibe Coding, and Generative AI Video by Ali Sadikin Ma — what actually ships, not slideware.';

            $body = View::make('seo.summary', [
                'heading' => 'Blog',
                'intro' => $description,
                'items' => $items,
            ])->render();

            return $this->composer->compose($shell, [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'robots' => 'index, follow',
                'og' => $this->og('website', $url, $title, $description, null, $lang),
                'twitter' => $this->twitter($url, $title, $description, null),
                'hreflang' => $this->hreflang($this->blogIndexPath('en'), $this->blogIndexPath('id'), $this->blogIndexPath('')),
                'jsonLd' => array_values(array_filter([
                    $this->schema->webSite(),
                    $this->schema->itemList($items),
                    $this->schema->breadcrumbList([
                        ['name' => 'Home', 'url' => $base . '/'],
                        ['name' => 'Blog', 'url' => $url],
                    ]),
                ])),
                'bodyHtml' => $body,
            ]);
        });

        return $this->respond($html);
    }

    // ---- FAQ ----------------------------------------------------------------

    /**
     * Dedicated /faq surface (GEO Pillar 2). Mirrors blogIndex()/home(): static
     * content sourced from config/faq.php, so the composed HTML is cached under
     * a single key with NO purge wiring (config can't change at runtime; a deploy
     * + cache:clear refreshes it). Emits a FAQPage JSON-LD block + WebSite entity
     * and splices a crawlable <dl> into #app for JS-blind AI crawlers; Vue's
     * FaqView hydrates over #app for real users. Same single source feeds the
     * /api/faq endpoint the Vue view fetches — no answer copy is duplicated.
     */
    public function faq()
    {
        if (!$this->distExists()) {
            return $this->devFallback('/faq');
        }

        $html = Cache::remember('seo_html:faq', $this->ttl(), function () {
            $shell = $this->loadShell();
            $items = config('faq.items', []);

            $base = $this->baseUrl();
            $url = $base . '/faq';
            $title = 'FAQ — ' . self::AUTHOR;
            $description = 'Answers to common questions about Ali Sadikin Ma — his AI services (AI Agents, Vibe Coding, Generative Video, AI visual inspection), how he works, and how to engage him.';

            $body = View::make('seo.faq', ['items' => $items])->render();

            return $this->composer->compose($shell, [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'robots' => 'index, follow',
                'og' => $this->og('website', $url, $title, $description, null, ''),
                'twitter' => $this->twitter($url, $title, $description, null),
                'jsonLd' => array_values(array_filter([
                    $this->schema->faqPage($items),
                    $this->schema->webSite(),
                ])),
                'bodyHtml' => $body,
            ]);
        });

        return $this->respond($html);
    }

    // ---- Blog category ------------------------------------------------------

    public function blogCategory(Request $request, string $slug, ?string $lang = null)
    {
        // Route params bind positionally in URI order, so for
        // /{lang}/blog/category/{slug} the locale lands in $slug. Resolve by
        // name so both the bare and locale-prefixed routes bind correctly.
        $slug = $request->route('slug');
        $lang = $this->normalizeLang($request->route('lang'));
        if (!$this->distExists()) {
            return $this->devFallback($this->categoryPath($lang, $slug));
        }

        // Resolve outside the cache so a missing category 404s (never cached).
        $category = Category::where('slug', $slug)->first();
        if (!$category) {
            abort(404);
        }

        $html = Cache::remember("seo_html:blog_category:{$lang}:{$slug}", $this->ttl(), function () use ($lang, $slug, $category) {
            $shell = $this->loadShell();

            $posts = Post::with('translations')
                ->where('published', true)
                ->whereHas('category', fn ($q) => $q->where('slug', $slug))
                ->orderByDesc('published_at')
                ->limit(20)
                ->get();
            $items = $posts->map(fn (Post $p) => $this->postItem($p, $lang))->all();

            $base = $this->baseUrl();
            $url = $base . $this->categoryPath($lang, $slug);
            $title = $category->name . ' — Blog | ' . self::AUTHOR;
            $description = 'Articles about ' . $category->name . ' by ' . self::AUTHOR . '.';

            $body = View::make('seo.summary', [
                'heading' => $category->name,
                'intro' => $description,
                'items' => $items,
            ])->render();

            return $this->composer->compose($shell, [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'robots' => 'index, follow',
                'og' => $this->og('website', $url, $title, $description, null, $lang),
                'twitter' => $this->twitter($url, $title, $description, null),
                'hreflang' => $this->hreflang($this->categoryPath('en', $slug), $this->categoryPath('id', $slug), $this->categoryPath('', $slug)),
                'jsonLd' => array_values(array_filter([
                    $this->schema->collectionPage(['name' => $category->name, 'url' => $url], $items),
                    $this->schema->breadcrumbList([
                        ['name' => 'Home', 'url' => $base . '/'],
                        ['name' => 'Blog', 'url' => $base . $this->blogIndexPath($lang)],
                        ['name' => $category->name, 'url' => $url],
                    ]),
                ])),
                'bodyHtml' => $body,
            ]);
        });

        return $this->respond($html);
    }

    // ---- Blog detail --------------------------------------------------------

    public function blogDetail(Request $request, string $slug, ?string $lang = null)
    {
        // Route params bind positionally in URI order, so for /{lang}/blog/{slug}
        // the locale lands in $slug. Resolve by name so both the bare and
        // locale-prefixed routes bind correctly.
        $slug = $request->route('slug');
        $lang = $this->normalizeLang($request->route('lang'));
        if (!$this->distExists()) {
            return $this->devFallback($this->blogDetailPath($lang, $slug));
        }

        // Resolve outside the cache so an unpublished/missing post 404s.
        $exists = Post::where('slug', $slug)->where('published', true)->exists();
        if (!$exists) {
            abort(404);
        }

        $html = Cache::remember("seo_html:blog_detail:{$lang}:{$slug}", $this->ttl(), function () use ($lang, $slug) {
            $shell = $this->loadShell();

            $post = Post::with(['translations', 'category'])
                ->where('slug', $slug)
                ->where('published', true)
                ->first();

            $t = $this->pickTranslation($post, $lang);

            $headline = ($t->title ?? null) ?: ($post->title ?: $slug);
            $title = ($t->meta_title ?? null) ?: ($t->og_title ?? null) ?: ($headline . ' | Blog');
            $description = ($t->meta_description ?? null)
                ?: ($t->og_description ?? null)
                ?: ($t->excerpt ?? null)
                ?: ($post->excerpt
                    ?: Str::limit(strip_tags(($t->content ?? null) ?: ($post->content ?? '')), 160));
            $keywords = $t->meta_keywords ?? null;

            $base = $this->baseUrl();
            $url = $base . $this->blogDetailPath($lang, $slug);
            $image = $this->resolveImage($post->og_image ?: $post->featured_image, $base);
            $publishedIso = optional($post->published_at)->toAtomString();
            $modifiedIso = optional($post->updated_at)->toAtomString();

            $categoryName = $post->category->name ?? null;
            $categoryUrl = $post->category
                ? $base . $this->categoryPath($lang, $post->category->slug)
                : null;

            $blogPosting = $this->schema->blogPosting([
                'headline' => $headline,
                'description' => $description,
                'url' => $url,
                'image' => $image,
                'datePublished' => $publishedIso,
                'dateModified' => $modifiedIso,
                'authorName' => self::AUTHOR,
                // Reflect the language actually served (an ID-only post at the
                // bare /blog/{slug} URL should report id, not the url default).
                'inLanguage' => ($t->language ?? null) ?: ($lang ?: 'en'),
                'keywords' => $keywords,
            ]);

            $crumbs = [
                ['name' => 'Home', 'url' => $base . '/'],
                ['name' => 'Blog', 'url' => $base . $this->blogIndexPath($lang)],
            ];
            if ($categoryName) {
                $crumbs[] = ['name' => $categoryName, 'url' => $categoryUrl];
            }
            $crumbs[] = ['name' => $headline, 'url' => $url];

            $faq = $this->schema->faqPage(($t->faq_schema ?? null) ?: ($post->faq_schema ?? null));

            $body = View::make('seo.article', [
                'title' => $headline,
                'author' => self::AUTHOR,
                'publishedIso' => $publishedIso,
                'publishedHuman' => optional($post->published_at)->format('F j, Y'),
                'modifiedIso' => $modifiedIso,
                'modifiedHuman' => optional($post->updated_at)->format('F j, Y'),
                'category' => $categoryName,
                'categoryUrl' => $categoryUrl,
                'image' => $image,
                'imageAlt' => $headline,
                'summary' => $t->ai_summary ?? null,
                'content' => ($t->content ?? null) ?: ($post->content ?? ''),
            ])->render();

            return $this->composer->compose($shell, [
                'title' => $title,
                'description' => $description,
                'keywords' => $keywords,
                'canonical' => $url,
                'robots' => 'index, follow',
                'og' => $this->og('article', $url, $title, $description, $image, $lang),
                'twitter' => $this->twitter($url, $title, $description, $image),
                'hreflang' => $this->hreflang($this->blogDetailPath('en', $slug), $this->blogDetailPath('id', $slug), $this->blogDetailPath('', $slug)),
                'jsonLd' => array_values(array_filter([$blogPosting, $this->schema->breadcrumbList($crumbs), $faq])),
                'bodyHtml' => $body,
            ]);
        });

        return $this->respond($html);
    }

    // ---- Project detail -----------------------------------------------------

    public function projectDetail(Request $request, string $slug, ?string $lang = null)
    {
        // Route params bind positionally in URI order, so for
        // /{lang}/projects/{slug} the locale lands in $slug. Resolve by name so
        // both the bare and locale-prefixed routes bind correctly.
        $slug = $request->route('slug');
        $lang = $this->normalizeLang($request->route('lang'));
        if (!$this->distExists()) {
            return $this->devFallback($this->projectDetailPath($lang, $slug));
        }

        // Resolve outside the cache so an inactive/missing project 404s.
        $exists = Project::where('slug', $slug)->where('is_active', true)->exists();
        if (!$exists) {
            abort(404);
        }

        $html = Cache::remember("seo_html:project_detail:{$lang}:{$slug}", $this->ttl(), function () use ($lang, $slug) {
            $shell = $this->loadShell();

            $project = Project::with('translations')
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            $t = $this->pickProjectTranslation($project, $lang);

            $headline = ($t->title ?? null) ?: ($project->title ?: $slug);
            $title = ($t->meta_title ?? null) ?: ($project->meta_title ?? null)
                ?: ($t->og_title ?? null) ?: ($project->og_title ?? null)
                ?: ($headline . ' | Projects');
            $description = ($t->meta_description ?? null)
                ?: ($project->meta_description ?? null)
                ?: ($t->og_description ?? null)
                ?: ($project->og_description ?? null)
                ?: ($t->description ?? null)
                ?: ($project->description
                    ? Str::limit(strip_tags($project->description), 160)
                    : '');
            $keywords = $project->meta_keywords ?? null;

            $base = $this->baseUrl();
            $url = $base . $this->projectDetailPath($lang, $slug);
            $image = $this->resolveImage($project->og_image ?: $project->image, $base);
            $createdIso = optional($project->created_at)->toAtomString();
            $modifiedIso = optional($project->updated_at)->toAtomString();

            $creativeWork = $this->schema->creativeWork([
                'name' => $headline,
                'description' => $description,
                'url' => $url,
                'image' => $image,
                'dateCreated' => $createdIso,
                'dateModified' => $modifiedIso,
                // Reflect the language actually served.
                'inLanguage' => ($t->language ?? null) ?: ($lang ?: 'en'),
                'keywords' => $keywords,
                'about' => $project->domain ?: null,
            ]);

            $crumbs = [
                ['name' => 'Home', 'url' => $base . '/'],
                ['name' => 'Projects', 'url' => $base . '/projects'],
                ['name' => $headline, 'url' => $url],
            ];

            // Surface only the case-study meta fields that actually exist.
            $meta = array_filter([
                'Client' => $project->client ?? null,
                'Role' => $project->role ?? null,
                'Domain' => $project->domain ?? null,
                'Category' => $project->category ?? null,
                'Year' => optional($project->end_date ?: $project->completed_at)->format('Y'),
                'Outcome' => $project->result ?: ($project->impact_statement ?: null),
            ], fn ($v) => !empty($v));

            $body = View::make('seo.project', [
                'title' => $headline,
                'category' => $project->category ?? null,
                'image' => $image,
                'imageAlt' => $headline,
                'summary' => ($t->ai_summary ?? null) ?: ($project->ai_summary ?? null),
                'meta' => $meta,
                'content' => ($t->content ?? null) ?: ($project->content ?? ''),
            ])->render();

            return $this->composer->compose($shell, [
                'title' => $title,
                'description' => $description,
                'keywords' => $keywords,
                'canonical' => $url,
                'robots' => 'index, follow',
                'og' => $this->og('article', $url, $title, $description, $image, $lang),
                'twitter' => $this->twitter($url, $title, $description, $image),
                'hreflang' => $this->hreflang($this->projectDetailPath('en', $slug), $this->projectDetailPath('id', $slug), $this->projectDetailPath('', $slug)),
                'jsonLd' => array_values(array_filter([$creativeWork, $this->schema->breadcrumbList($crumbs)])),
                'bodyHtml' => $body,
            ]);
        });

        return $this->respond($html);
    }

    // ---- Cache purge (called from Post::boot saved/deleted) -----------------

    /**
     * Forget every SSR HTML cache entry a Post change could invalidate: its own
     * detail variants, all blog-index variants, the homepage, and (if linked)
     * its category variants. Cheap explicit forgets — the database cache driver
     * has no tag support.
     */
    /**
     * Forget just the homepage SSR HTML cache. Wired from Testimonial::boot()
     * saved/deleted so a rating/review change refreshes the Organization graph
     * before the 1h TTL lapses. The home cache is a single key (not per-lang).
     */
    public static function purgeHome(): void
    {
        Cache::forget('seo_html:home');
    }

    public static function purgeForPost(Post $post): void
    {
        Cache::forget('seo_html:home');

        // Cover the current slug AND a renamed-from slug. getOriginal() returns
        // the pre-save value; on a non-slug change it equals the current slug,
        // and array_unique dedupes the duplicate away.
        $slugs = array_unique(array_filter([
            $post->slug,
            $post->getOriginal('slug'),
        ]));

        foreach (self::LANGS as $lang) {
            Cache::forget("seo_html:blog_index:{$lang}");
            foreach ($slugs as $slug) {
                Cache::forget("seo_html:blog_detail:{$lang}:{$slug}");
            }
        }

        // Purge the current category AND the category the post moved out of, so
        // both category listings drop/add the post immediately.
        $categorySlugs = array_unique(array_filter([
            $post->category->slug ?? null,
            self::categorySlugForId($post->getOriginal('category_id')),
        ]));
        foreach ($categorySlugs as $slug) {
            self::purgeCategorySlug($slug);
        }
    }

    /**
     * Forget every SSR HTML cache entry a Project change could invalidate: its
     * own detail variants under the current AND renamed-from slug, per lang.
     * Projects aren't on the SSR home/index body, so no home/index purge.
     * Wired from Project::boot() saved/deleted.
     */
    public static function purgeForProject(Project $project): void
    {
        $slugs = array_unique(array_filter([
            $project->slug,
            $project->getOriginal('slug'),
        ]));

        foreach (self::LANGS as $lang) {
            foreach ($slugs as $slug) {
                Cache::forget("seo_html:project_detail:{$lang}:{$slug}");
            }
        }
    }

    /**
     * Purge home + blog-index + the category page + every published-post detail
     * page in the category (their breadcrumb embeds the category name). Wired
     * from Category::boot() saved/deleted so a rename/delete refreshes promptly.
     */
    public static function purgeForCategory(Category $category): void
    {
        Cache::forget('seo_html:home');
        foreach (self::LANGS as $lang) {
            Cache::forget("seo_html:blog_index:{$lang}");
        }

        $slugs = array_unique(array_filter([
            $category->slug,
            $category->getOriginal('slug'),
        ]));
        foreach ($slugs as $slug) {
            self::purgeCategorySlug($slug);
        }

        foreach ($category->posts()->where('published', true)->pluck('slug') as $postSlug) {
            foreach (self::LANGS as $lang) {
                Cache::forget("seo_html:blog_detail:{$lang}:{$postSlug}");
            }
        }
    }

    private static function purgeCategorySlug(?string $slug): void
    {
        if (!$slug) {
            return;
        }
        foreach (self::LANGS as $lang) {
            Cache::forget("seo_html:blog_category:{$lang}:{$slug}");
        }
    }

    private static function categorySlugForId($id): ?string
    {
        return empty($id) ? null : optional(Category::find($id))->slug;
    }

    /**
     * Resolve the best translation from the ALREADY-EAGER-LOADED collection
     * (firstWhere is zero-query) instead of Post::translation() which re-queries
     * the DB and would defeat the with('translations') eager load. Fallback:
     * requested lang → en → first available.
     */
    private function pickTranslation(Post $post, string $lang)
    {
        return $post->translations->firstWhere('language', $lang ?: 'en')
            ?? $post->translations->firstWhere('language', 'en')
            ?? $post->translations->first();
    }

    /**
     * Resolve the best Project translation from the ALREADY-EAGER-LOADED
     * collection (firstWhere is zero-query). Project translations are also keyed
     * by `language`. Fallback: requested lang → en → first available → null.
     */
    private function pickProjectTranslation(Project $project, string $lang)
    {
        return $project->translations->firstWhere('language', $lang ?: 'en')
            ?? $project->translations->firstWhere('language', 'en')
            ?? $project->translations->first();
    }

    // ---- Helpers ------------------------------------------------------------

    private function distPath(): string
    {
        return config('seo.spa_shell_path') ?: base_path('../frontend/dist/index.html');
    }

    private function ttl(): int
    {
        return (int) config('seo.html_cache_ttl', 3600);
    }

    private function distExists(): bool
    {
        return file_exists($this->distPath());
    }

    private function loadShell(): string
    {
        $html = @file_get_contents($this->distPath());

        return $html === false ? '' : $html;
    }

    private function baseUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }

    /** Dev (no dist build): preserve the old redirect-to-SPA behavior. */
    private function devFallback(string $path)
    {
        return redirect($this->baseUrl() . $path, 302);
    }

    private function respond(string $html)
    {
        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    private function normalizeLang(?string $lang): string
    {
        return in_array($lang, ['en', 'id'], true) ? $lang : '';
    }

    private function blogIndexPath(string $lang): string
    {
        return $lang === '' ? '/blog' : "/{$lang}/blog";
    }

    private function blogDetailPath(string $lang, string $slug): string
    {
        return $lang === '' ? "/blog/{$slug}" : "/{$lang}/blog/{$slug}";
    }

    private function categoryPath(string $lang, string $slug): string
    {
        return $lang === '' ? "/blog/category/{$slug}" : "/{$lang}/blog/category/{$slug}";
    }

    private function projectDetailPath(string $lang, string $slug): string
    {
        return $lang === '' ? "/projects/{$slug}" : "/{$lang}/projects/{$slug}";
    }

    private function hreflang(string $enPath, string $idPath, string $defaultPath): array
    {
        $base = $this->baseUrl();

        return [
            'en' => $base . $enPath,
            'id' => $base . $idPath,
            'x-default' => $base . $defaultPath,
        ];
    }

    private function og(string $type, string $url, string $title, string $description, ?string $image, string $lang): array
    {
        $og = [
            'type' => $type,
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'locale' => $lang === 'id' ? 'id_ID' : 'en_US',
        ];
        if ($image) {
            $og['image'] = $image;
        }

        return $og;
    }

    private function twitter(string $url, string $title, string $description, ?string $image): array
    {
        $tw = [
            'url' => $url,
            'title' => $title,
            'description' => $description,
        ];
        if ($image) {
            $tw['image'] = $image;
        }

        return $tw;
    }

    private function resolveImage(?string $image, string $baseUrl): ?string
    {
        if (empty($image)) {
            return null;
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return $baseUrl . (str_starts_with($image, '/') ? $image : '/storage/' . $image);
    }

    /** @return array<int, array{title:string,name:string,excerpt:string,url:string}> */
    private function recentPostItems(string $lang, int $limit): array
    {
        return Post::with('translations')
            ->where('published', true)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Post $p) => $this->postItem($p, $lang))
            ->all();
    }

    /** @return array{title:string,name:string,excerpt:string,url:string} */
    private function postItem(Post $post, string $lang): array
    {
        $t = $this->pickTranslation($post, $lang);

        $title = ($t->title ?? null) ?: ($post->title ?: $post->slug);
        $excerpt = ($t->excerpt ?? null)
            ?: ($post->excerpt
                ?: Str::limit(strip_tags(($t->ai_summary ?? null) ?: ($t->content ?? '')), 140));

        return [
            'title' => $title,
            'name' => $title,
            'excerpt' => $excerpt,
            'url' => $this->baseUrl() . $this->blogDetailPath($lang, $post->slug),
        ];
    }
}
