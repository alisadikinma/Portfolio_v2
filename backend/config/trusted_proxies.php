<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Production path: Cloudflare -> Traefik (docker bridge) -> nginx :8080 -> php-fpm.
| Without these, $request->ip() is Traefik's container IP for every visitor,
| which makes every throttle: limiter global and logs wrong IPs.
|
| Applied in bootstrap/app.php via $middleware->trustProxies(). Laravel walks
| X-Forwarded-For from the right and returns the first UNtrusted hop.
|
| Cloudflare ranges: https://www.cloudflare.com/ips-v4 and
| https://www.cloudflare.com/ips-v6 (checked 2026-11-08). Re-check
| occasionally; Cloudflare rarely changes them.
|
| Override everything with TRUSTED_PROXIES (comma-separated CIDRs, or "*").
*/
$override = env('TRUSTED_PROXIES');

return [
    'proxies' => is_string($override) && trim($override) !== ''
        ? array_values(array_filter(array_map('trim', explode(',', $override))))
        : [
            // Loopback
            '127.0.0.1',
            '::1',
            // Docker bridge networks (Traefik container)
            '172.16.0.0/12',
            // Cloudflare IPv4
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            // Cloudflare IPv6
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ],
];
