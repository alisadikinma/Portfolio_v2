<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\SpaPrerenderController;

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

// FAQ — dedicated server-rendered surface (GEO Pillar 2). Config-sourced static
// content (config/faq.php): crawlable <dl> + FAQPage JSON-LD for JS-blind AI
// crawlers, Vue FaqView hydrates over #app for humans. No locale variants.
Route::get('/faq', [SpaPrerenderController::class, 'faq'])->name('faq');

// ---- Static SPA named routes (sitemap route() helpers; dev redirect) -------

Route::get('/projects', function () { return redirect('http://localhost:5173/projects'); })->name('projects');
Route::get('/about', function () { return redirect('http://localhost:5173/about'); })->name('about');
Route::get('/contact', function () { return redirect('http://localhost:5173/contact'); })->name('contact');

/*
|--------------------------------------------------------------------------
| SSR-enrichment for project detail pages (full schema graph + crawlable body)
|--------------------------------------------------------------------------
| Project detail pages now get the same treatment as blog detail: a per-page
| <head>, a CreativeWork JSON-LD node + breadcrumbs, hreflang, and a crawlable
| <article> case-study body. Handled by SpaPrerenderController::projectDetail,
| cached 1h (key seo_html:project_detail:*) and purged on Project save/delete
| via SpaPrerenderController::purgeForProject() (wired from Project::boot()).
| Same nginx-routing requirement as the blog routes above.
*/
Route::get('/{lang}/projects/{slug}', [SpaPrerenderController::class, 'projectDetail'])
    ->where(['lang' => 'en|id']);
Route::get('/projects/{slug}', [SpaPrerenderController::class, 'projectDetail']);
