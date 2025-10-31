<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCacheHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $response;
        }

        $path = $request->path();
        
        // Images - Cache 1 year
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i', $path)) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'HIT-Images');
        }
        
        // CSS & JS - Cache 1 month
        elseif (preg_match('/\.(css|js)$/i', $path)) {
            $response->headers->set('Cache-Control', 'public, max-age=2592000');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'HIT-Assets');
        }
        
        // Fonts - Cache 1 year
        elseif (preg_match('/\.(woff|woff2|ttf|otf|eot)$/i', $path)) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
            $response->headers->set('X-Cache-Middleware', 'HIT-Fonts');
        }
        
        // API responses - No cache
        elseif (str_starts_with($path, 'api/')) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('X-Cache-Middleware', 'NO-CACHE-API');
        }

        return $response;
    }
}
