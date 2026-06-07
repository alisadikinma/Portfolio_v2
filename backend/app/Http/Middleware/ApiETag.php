<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds an ETag to JSON GET responses and short-circuits to 304 Not Modified
 * when the client's If-None-Match matches.
 *
 * Cuts API revalidation cost from "full payload re-download" to ~50 bytes,
 * letting browsers cache JSON aggressively without going stale. Skips:
 *   - non-GET requests (POST/PUT/DELETE bypass)
 *   - non-2xx responses (don't cache errors)
 *   - responses larger than MAX_HASH_BYTES (avoid CPU on huge payloads)
 *   - streamed/file responses (ETag handled elsewhere)
 */
class ApiETag
{
    private const MAX_HASH_BYTES = 1_048_576; // 1 MB

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')) {
            return $response;
        }

        // The SPA admin client (axios) treats 304 as an error — its default
        // validateStatus accepts only 200-299. A revalidated admin GET would
        // therefore surface as a failed/empty response (e.g. Content Engine
        // rendering "0 ideas" on every reload: first load 200, next load
        // If-None-Match -> 304 -> axios throws -> ideas list stays empty).
        // ETag/304 saves ~nothing on these per-user authenticated payloads,
        // so skip them entirely. Public GETs (posts/categories/llms.txt) and
        // the tool-consumed CV export endpoints keep their ETag revalidation.
        if ($request->is('api/admin/*') || $request->is('admin/*')) {
            return $response;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        // BinaryFileResponse / StreamedResponse don't expose a content string;
        // skip rather than buffer them into memory.
        if (! method_exists($response, 'getContent')) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '' || strlen($content) > self::MAX_HASH_BYTES) {
            return $response;
        }

        // Weak ETag (W/"...") because:
        //   1. Apache mod_deflate strips strong ETag on gzipped responses,
        //      breaking the whole point of attaching ETag at all.
        //   2. md5(content) reflects payload equivalence, not byte-for-byte
        //      identity (which is what a strong ETag promises). Weak is the
        //      semantically honest choice here.
        $etag = 'W/"'.md5($content).'"';
        $response->headers->set('ETag', $etag);

        // Vary on Origin so per-CORS-origin caches don't bleed.
        $existingVary = $response->headers->get('Vary');
        if ($existingVary === null || stripos($existingVary, 'Origin') === false) {
            $response->headers->set('Vary', trim(($existingVary ? $existingVary.', ' : '').'Origin'));
        }

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch !== null && $this->etagMatches($ifNoneMatch, $etag)) {
            $response->setContent('');
            $response->setStatusCode(304);
            // Strip Content-Length / Content-Type per RFC 7232 §4.1
            $response->headers->remove('Content-Length');
        }

        return $response;
    }

    /**
     * Match per RFC 7232 §3.2 — supports comma-separated If-None-Match list
     * and the wildcard "*". Weak comparison: strip W/ prefix from BOTH sides
     * before comparing (RFC 7232 §2.3.2 weak comparison function).
     */
    private function etagMatches(string $ifNoneMatch, string $currentEtag): bool
    {
        $normalizedCurrent = preg_replace('/^W\//', '', $currentEtag);
        $candidates = array_map('trim', explode(',', $ifNoneMatch));
        foreach ($candidates as $candidate) {
            if ($candidate === '*') {
                return true;
            }
            $normalizedCandidate = preg_replace('/^W\//', '', $candidate);
            if ($normalizedCandidate === $normalizedCurrent) {
                return true;
            }
        }
        return false;
    }
}
