<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pick locale (id|en) for public read endpoints with this priority:
 *
 *   1. `lang_preference` cookie — operator-set, wins always.
 *   2. `CF-IPCountry` request header (Cloudflare).
 *   3. ip-api.com fallback (configurable, fail-open to null).
 *
 * Country `ID` → `id`, anything else → `en`. Sets the resolved choice
 * back on a 1-year cookie so subsequent requests skip the geo lookup.
 *
 * Phase 1, Homepage Redesign — see
 * docs/plans/2026-05-04-homepage-redesign-plan.md.
 */
class SetLocaleByGeoIP
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieLocale = $request->cookie('lang_preference');

        if (in_array($cookieLocale, ['id', 'en'], true)) {
            App::setLocale($cookieLocale);
            return $next($request);
        }

        $country = $request->header('CF-IPCountry');

        if (empty($country) || $country === 'XX' || $country === 'T1') {
            $country = $this->resolveCountryViaFallback($request->ip());
        }

        $locale = ($country === 'ID') ? 'id' : 'en';

        App::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        // 1-year persistence — geo lookups are expensive, cache aggressively.
        // Set on the response directly because the API middleware group
        // doesn't include AddQueuedCookiesToResponse, so Cookie::queue() is
        // silently dropped on /api/* routes.
        $expiresAt = now()->addYear()->getTimestamp();
        $response->headers->setCookie(new Cookie(
            'lang_preference',
            $locale,
            $expiresAt,
            '/',           // path
            null,          // domain (null = current host)
            null,          // secure (null = auto-detect from request)
            false,         // httpOnly — frontend Vue toggle reads/writes this
            false,         // raw
            'lax'          // sameSite
        ));

        return $response;
    }

    /**
     * Hit ip-api.com for a country code. Fail-open: any error returns null
     * so the caller falls through to default `en`. Never throws.
     */
    private function resolveCountryViaFallback(?string $ip): ?string
    {
        if (empty($ip)) {
            return null;
        }

        $provider = config('services.geoip.fallback');
        if ($provider !== 'ip-api') {
            return null;
        }

        $timeout = (int) config('services.geoip.timeout', 2);

        try {
            $response = Http::timeout($timeout)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'countryCode',
                ]);

            if (!$response->ok()) {
                return null;
            }

            $code = $response->json('countryCode');
            return is_string($code) && strlen($code) === 2 ? strtoupper($code) : null;
        } catch (\Throwable $e) {
            Log::debug('[SetLocaleByGeoIP] ip-api lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
