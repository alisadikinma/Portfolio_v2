<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\SpaPrerenderController;
use App\Models\Project;

// Redirect login route to Filament admin login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::get('/test-route', function () {
    return 'Routes are working! User: ' . (auth()->check() ? auth()->user()->email : 'Not logged in');
});

// Branded URL shortener — /r/{code} → 301 redirect to short_links.target_url
// (which already carries UTM params for GA attribution). Used by cross-post
// pipeline (LinkedIn first-comment, IG/Threads first-comment via Publer,
// TikTok caption body). See App\Services\ShortLinkService.
Route::get('/r/{code}', [ShortLinkController::class, 'redirect'])
    ->where('code', '[A-Za-z0-9]{4,16}')
    ->name('short-link.redirect');

/*
|--------------------------------------------------------------------------
| SSR-enrichment for SEO + GEO (homepage + blog surfaces)
|--------------------------------------------------------------------------
|
| Search engines and LLM/GEO crawlers (ChatGPT, Perplexity, Claude, Google
| AI) do not execute JavaScript, so the Vue SPA's runtime meta/schema/body
| injection is invisible to them — they see an empty <div id="app">. These
| routes load the built SPA shell (frontend/dist/index.html), splice in the
| per-page <head>, a JSON-LD entity graph, hreflang, and (for blog detail) a
| crawlable <article> body, then return the HTML. Vue still hydrates over #app
| for real users, who are unaffected.
|
| Handled by App\Http\Controllers\SpaPrerenderController. Projects keep their
| OG-only closure below (out of scope for this layer).
|
| For these to fire in production, the webserver must route the matching
| paths to PHP-FPM BEFORE the SPA's try_files fallback — see
| scripts/nginx/portfolio-8080.conf (widened in the SSR-deploy runbook).
|
| ROUTE ORDER MATTERS: /blog/category/{slug} is registered before
| /blog/{slug} so the {slug} wildcard never swallows "category".
*/

// Homepage — adds WebSite schema + a crawlable identity/recent-posts summary.
// (Person + FAQPage already live as static blocks in frontend/index.html.)
Route::get('/', [SpaPrerenderController::class, 'home'])->name('home');

// Blog category (locale-prefixed + bare) — register BEFORE the detail wildcard.
Route::get('/{lang}/blog/category/{slug}', [SpaPrerenderController::class, 'blogCategory'])
    ->where(['lang' => 'en|id']);
Route::get('/blog/category/{slug}', [SpaPrerenderController::class, 'blogCategory']);

// Blog index (locale-prefixed + bare).
Route::get('/{lang}/blog', [SpaPrerenderController::class, 'blogIndex'])
    ->where(['lang' => 'en|id']);
Route::get('/blog', [SpaPrerenderController::class, 'blogIndex'])->name('blog');

// Blog detail (locale-prefixed + bare).
Route::get('/{lang}/blog/{slug}', [SpaPrerenderController::class, 'blogDetail'])
    ->where(['lang' => 'en|id']);
Route::get('/blog/{slug}', [SpaPrerenderController::class, 'blogDetail']);

// ---- Static SPA named routes (sitemap route() helpers; dev redirect) -------

Route::get('/projects', function () { return redirect('http://localhost:5173/projects'); })->name('projects');
Route::get('/about', function () { return redirect('http://localhost:5173/about'); })->name('about');
Route::get('/contact', function () { return redirect('http://localhost:5173/contact'); })->name('contact');

/*
|--------------------------------------------------------------------------
| Crawler-friendly OG meta SSR for projects (OG-only — unchanged)
|--------------------------------------------------------------------------
| Project detail pages keep the lighter OG/Twitter/canonical injection. The
| full schema-graph + crawlable-body treatment is scoped to blog for now.
*/

// Resolve a stored image reference (relative path, /storage/* path, or
// absolute URL) into an absolute https URL suitable for og:image.
$resolveImage = function (?string $image, string $baseUrl): ?string {
    if (empty($image)) {
        return null;
    }
    if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
        return $image;
    }
    return $baseUrl . (str_starts_with($image, '/') ? $image : '/storage/' . $image);
};

// Load frontend/dist/index.html and rewrite the OG / Twitter / canonical
// tags inline. Returns null if the dist build isn't present (dev mode).
$injectOg = function (string $url, string $title, string $description, ?string $image, string $lang): ?string {
    $indexPath = base_path('../frontend/dist/index.html');
    if (!file_exists($indexPath)) {
        return null;
    }
    $html = @file_get_contents($indexPath);
    if ($html === false) {
        return null;
    }

    $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $descEsc = htmlspecialchars($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $urlEsc = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $imageEsc = $image ? htmlspecialchars($image, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    $locale = $lang === 'id' ? 'id_ID' : 'en_US';

    // Literal-return callbacks so values containing "$N" are never read as
    // regex backreferences (the latent bug SeoHtmlComposer also fixes).
    $replacements = [
        '/<title>[^<]*<\/title>/i'                                       => '<title>' . $titleEsc . '</title>',
        '/<meta\s+name="title"\s+content="[^"]*"\s*\/?>/i'               => '<meta name="title" content="' . $titleEsc . '">',
        '/<meta\s+name="description"\s+content="[^"]*"\s*\/?>/i'         => '<meta name="description" content="' . $descEsc . '">',
        '/<meta\s+property="og:type"\s+content="[^"]*"\s*\/?>/i'         => '<meta property="og:type" content="article">',
        '/<meta\s+property="og:url"\s+content="[^"]*"\s*\/?>/i'          => '<meta property="og:url" content="' . $urlEsc . '">',
        '/<meta\s+property="og:title"\s+content="[^"]*"\s*\/?>/i'        => '<meta property="og:title" content="' . $titleEsc . '">',
        '/<meta\s+property="og:description"\s+content="[^"]*"\s*\/?>/i'  => '<meta property="og:description" content="' . $descEsc . '">',
        '/<meta\s+property="og:locale"\s+content="[^"]*"\s*\/?>/i'       => '<meta property="og:locale" content="' . $locale . '">',
        '/<meta\s+name="twitter:url"\s+content="[^"]*"\s*\/?>/i'         => '<meta name="twitter:url" content="' . $urlEsc . '">',
        '/<meta\s+name="twitter:title"\s+content="[^"]*"\s*\/?>/i'       => '<meta name="twitter:title" content="' . $titleEsc . '">',
        '/<meta\s+name="twitter:description"\s+content="[^"]*"\s*\/?>/i' => '<meta name="twitter:description" content="' . $descEsc . '">',
        '/<link\s+rel="canonical"\s+href="[^"]*"\s*\/?>/i'               => '<link rel="canonical" href="' . $urlEsc . '">',
    ];

    if ($imageEsc !== null) {
        $replacements['/<meta\s+property="og:image"\s+content="[^"]*"\s*\/?>/i']  = '<meta property="og:image" content="' . $imageEsc . '">';
        $replacements['/<meta\s+name="twitter:image"\s+content="[^"]*"\s*\/?>/i'] = '<meta name="twitter:image" content="' . $imageEsc . '">';
    }

    foreach ($replacements as $pattern => $replacement) {
        $html = preg_replace_callback($pattern, fn () => $replacement, $html);
    }

    return $html;
};

$serveProjectOg = function (string $slug, string $lang) use ($injectOg, $resolveImage) {
    $project = Project::where('slug', $slug)->first();
    if (!$project) {
        abort(404);
    }

    $title = $project->meta_title
        ?: $project->og_title
        ?: $project->title
        ?: $slug;

    $description = $project->meta_description
        ?: $project->og_description
        ?: ($project->description
            ? Str::limit(strip_tags($project->description), 160)
            : '');

    $baseUrl = rtrim(config('app.url'), '/');
    $image = $resolveImage($project->og_image ?: $project->image, $baseUrl);
    $url = $baseUrl . ($lang === '' ? "/projects/{$slug}" : "/{$lang}/projects/{$slug}");

    $html = $injectOg($url, $title, $description, $image, $lang ?: 'en');
    if ($html === null) {
        return redirect($url, 302);
    }

    return response($html)
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->header('Cache-Control', 'public, max-age=300');
};

Route::get('/{lang}/projects/{slug}', function (string $lang, string $slug) use ($serveProjectOg) {
    return $serveProjectOg($slug, $lang);
})->where(['lang' => 'en|id']);

Route::get('/projects/{slug}', function (string $slug) use ($serveProjectOg) {
    return $serveProjectOg($slug, '');
});
