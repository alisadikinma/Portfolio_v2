<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Models\Project;
use App\Models\Post;

Route::get('/', function () {
    return view('welcome');
});

// Redirect login route to Filament admin login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Named routes used by the sitemap / route() helper. These redirect to the
// Vite dev server in local development; in production the webserver should
// short-circuit them to the SPA before they ever hit Laravel.
Route::get('/', function () { return redirect('http://localhost:5173'); })->name('home');
Route::get('/blog', function () { return redirect('http://localhost:5173/blog'); })->name('blog');
Route::get('/projects', function () { return redirect('http://localhost:5173/projects'); })->name('projects');
Route::get('/about', function () { return redirect('http://localhost:5173/about'); })->name('about');
Route::get('/contact', function () { return redirect('http://localhost:5173/contact'); })->name('contact');

Route::get('/test-route', function () {
    return 'Routes are working! User: ' . (auth()->check() ? auth()->user()->email : 'Not logged in');
});

/*
|--------------------------------------------------------------------------
| Crawler-friendly OG meta SSR for blog posts and projects
|--------------------------------------------------------------------------
|
| LinkedIn / Facebook / Twitter / WhatsApp link-preview crawlers do not
| execute JavaScript, so the SPA's runtime meta-tag injection (see
| frontend/src/composables/useMetaTags.js) is invisible to them. These
| routes load the built SPA shell from frontend/dist/index.html, splice
| the per-post / per-project OG and Twitter tags in, and return the
| resulting HTML. Vue still hydrates on top for real users.
|
| Two URL shapes are supported:
|   /blog/{slug}                — legacy bare path (backwards-compat)
|   /{lang}/blog/{slug}         — locale-prefixed (current production URLs)
| Same pair for /projects.
|
| For these routes to actually fire in production, the webserver vhost
| must route the matching paths to PHP-FPM BEFORE falling back to the
| SPA's index.html. Nginx example:
|
|   location ~ ^/(?:en|id)?/?(blog|projects)/[^/]+/?$ {
|       try_files $uri @backend;
|   }
|   location @backend { proxy_pass http://php-fpm-upstream; }
|
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

    $replacements = [
        '/<title>[^<]*<\/title>/i'                                                => '<title>' . $titleEsc . '</title>',
        '/<meta\s+name="title"\s+content="[^"]*"\s*\/?>/i'                        => '<meta name="title" content="' . $titleEsc . '">',
        '/<meta\s+name="description"\s+content="[^"]*"\s*\/?>/i'                  => '<meta name="description" content="' . $descEsc . '">',
        '/<meta\s+property="og:type"\s+content="[^"]*"\s*\/?>/i'                  => '<meta property="og:type" content="article">',
        '/<meta\s+property="og:url"\s+content="[^"]*"\s*\/?>/i'                   => '<meta property="og:url" content="' . $urlEsc . '">',
        '/<meta\s+property="og:title"\s+content="[^"]*"\s*\/?>/i'                 => '<meta property="og:title" content="' . $titleEsc . '">',
        '/<meta\s+property="og:description"\s+content="[^"]*"\s*\/?>/i'           => '<meta property="og:description" content="' . $descEsc . '">',
        '/<meta\s+property="og:locale"\s+content="[^"]*"\s*\/?>/i'                => '<meta property="og:locale" content="' . $locale . '">',
        '/<meta\s+name="twitter:url"\s+content="[^"]*"\s*\/?>/i'                  => '<meta name="twitter:url" content="' . $urlEsc . '">',
        '/<meta\s+name="twitter:title"\s+content="[^"]*"\s*\/?>/i'                => '<meta name="twitter:title" content="' . $titleEsc . '">',
        '/<meta\s+name="twitter:description"\s+content="[^"]*"\s*\/?>/i'          => '<meta name="twitter:description" content="' . $descEsc . '">',
        '/<link\s+rel="canonical"\s+href="[^"]*"\s*\/?>/i'                        => '<link rel="canonical" href="' . $urlEsc . '">',
    ];

    if ($imageEsc !== null) {
        $replacements['/<meta\s+property="og:image"\s+content="[^"]*"\s*\/?>/i']  = '<meta property="og:image" content="' . $imageEsc . '">';
        $replacements['/<meta\s+name="twitter:image"\s+content="[^"]*"\s*\/?>/i'] = '<meta name="twitter:image" content="' . $imageEsc . '">';
    }

    foreach ($replacements as $pattern => $replacement) {
        $html = preg_replace($pattern, $replacement, $html);
    }

    return $html;
};

// ---- Blog ------------------------------------------------------------------

$serveBlogOg = function (string $slug, string $lang) use ($injectOg, $resolveImage) {
    $post = Post::with(['translations', 'category'])
        ->where('slug', $slug)
        ->where('published', true)
        ->first();
    if (!$post) {
        abort(404);
    }

    // Prefer the requested language; fall back to English, then any translation,
    // then the post's own primary-language fields (title/excerpt/content live on
    // posts too as the authored source of truth).
    $translation = $post->translation($lang)
        ?? $post->translation('en')
        ?? $post->translations->first();

    $title = $translation->meta_title
        ?? $translation->og_title
        ?? $translation->title
        ?? $post->title
        ?? $slug;

    $description = $translation->meta_description
        ?? $translation->og_description
        ?? $translation->excerpt
        ?? $post->excerpt
        ?? Str::limit(strip_tags($translation->content ?? $post->content ?? ''), 160);

    $baseUrl = rtrim(config('app.url'), '/');
    $image = $resolveImage($post->og_image ?: $post->featured_image, $baseUrl);
    $url = $baseUrl . ($lang === '' ? "/blog/{$slug}" : "/{$lang}/blog/{$slug}");

    $html = $injectOg($url, $title, $description, $image, $lang ?: 'en');
    if ($html === null) {
        return redirect($url, 302);
    }

    return response($html)
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->header('Cache-Control', 'public, max-age=300');
};

Route::get('/{lang}/blog/{slug}', function (string $lang, string $slug) use ($serveBlogOg) {
    return $serveBlogOg($slug, $lang);
})->where(['lang' => 'en|id']);

Route::get('/blog/{slug}', function (string $slug) use ($serveBlogOg) {
    return $serveBlogOg($slug, '');
});

// ---- Projects --------------------------------------------------------------

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
