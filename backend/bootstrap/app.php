<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Register Livewire routes
            \Livewire\Livewire::setUpdateRoute(function ($handle) {
                return \Illuminate\Support\Facades\Route::post('livewire/update', $handle);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add CORS middleware for API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // ETag/304 for JSON GET responses — cuts revalidation cost from
        // full payload to ~50 bytes. Skips non-GET, non-2xx, and >1MB
        // payloads internally; safe to apply globally to the api group.
        $middleware->api(append: [
            \App\Http\Middleware\ApiETag::class,
        ]);

        // Add cache headers for static assets (images, CSS, JS, fonts)
        $middleware->web(append: [
            \App\Http\Middleware\SetCacheHeaders::class,
        ]);

        // Log AI bot crawls (GPTBot, ClaudeBot, PerplexityBot, …) — GA4 can
        // never see these (no JS, no referrer). Fail-open; recorded daily into
        // geo_crawler_hits. See GET /api/admin/geo/crawler-hits.
        $middleware->appendToGroup('web', \App\Http\Middleware\LogAiCrawler::class);

        // Middleware aliases for route-level use (`->middleware('set.locale.by.geoip')`).
        // `ability` / `abilities` are Sanctum's per-token capability gates —
        // used by the CV Master Export API (Phase 10) to scope the jobhunter
        // token to read-only access without granting full app session.
        $middleware->alias([
            'set.locale.by.geoip' => \App\Http\Middleware\SetLocaleByGeoIP::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        // Note: statefulApi() removed - we're using token-based auth, not cookie-based
        // This prevents CSRF 419 errors on API routes
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\InvalidStateTransitionException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_STATE_TRANSITION',
                        'message' => $e->getMessage(),
                    ],
                ], 409);
            }
        });

        // API routes must return 401 JSON (not 302 redirect to /login HTML
        // page) when Sanctum auth fails. The default Laravel behavior assumes
        // a web session and redirects — for API consumers (jobhunter,
        // automation webhooks, mobile clients) this surfaces as a confusing
        // HTML response. Override applies to anything matching `api/*` OR
        // any request that prefers JSON via Accept header.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => $e->getMessage() ?: 'Unauthenticated.',
                    ],
                ], 401);
            }
        });
    })->create();
