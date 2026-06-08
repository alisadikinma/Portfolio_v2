<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SPA Shell Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the built Vue SPA shell (frontend/dist/index.html) that
    | SpaPrerenderController loads and splices per-page <head> + JSON-LD + body
    | into. When the file is absent (dev, before a frontend build), the
    | controller falls back to a 302 redirect to the SPA. Overridable via env
    | so tests can point at a fixture and non-standard deploy layouts work.
    |
    */
    'spa_shell_path' => env('SEO_SPA_SHELL_PATH', base_path('../frontend/dist/index.html')),

    /*
    |--------------------------------------------------------------------------
    | SSR HTML Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long composed SSR HTML is cached (key seo_html:*). Entries are purged
    | early on Post save/delete via SpaPrerenderController::purgeForPost().
    |
    */
    'html_cache_ttl' => (int) env('SEO_HTML_CACHE_TTL', 3600),

];
