# Cloudflare Proxy Setup — alisadikinma.com

Phase E of [docs/plans/2026-05-05-portfolio-perf-cache-refactor.md](../plans/2026-05-05-portfolio-perf-cache-refactor.md).

This is a one-time operator runbook. After execution, Cloudflare sits in front of the VPS, edge-caching `/storage/*` + `/uploads/*` globally and bypassing `/api/*`.

## Pre-flight

- [ ] Cloudflare free account active for `alisadikinma.com` zone
- [ ] DNS records visible in CF dashboard
- [ ] Origin VPS IP recorded (for SSL "Full strict" origin verification)
- [ ] Production HTTPS cert valid on origin (Let's Encrypt or similar)

## Steps

### 1. Toggle DNS proxy ON (orange cloud)

Cloudflare dashboard → `alisadikinma.com` → DNS → Records.

For each of these records, click the grey cloud icon to toggle to **orange cloud (Proxied)**:

- `A    @           <vps-ip>`        → Proxied
- `A    www         <vps-ip>` (or CNAME) → Proxied

Leave any wildcard subdomain records used for things you DON'T want proxied (e.g., direct SSH) as grey cloud.

**Why**: Grey cloud = DNS only (CF resolves, traffic goes direct to origin, no edge cache). Orange = traffic flows through CF edge, gets cached + minified + Brotli'd.

### 2. Verify propagation

Wait ~5 min, then verify from a non-CF DNS resolver:

```bash
dig alisadikinma.com +short @8.8.8.8
# Expected: returns Cloudflare anycast IP (104.x.x.x or 172.x.x.x range)
# NOT your VPS IP
```

If still returning origin IP: wait 5 more min, check TTL on the A record (lower TTL before toggling helps next time).

### 3. SSL/TLS configuration

Cloudflare dashboard → SSL/TLS → Overview.

- Encryption mode: **Full (strict)**
  - Validates origin cert. Requires valid HTTPS on VPS (already true — Let's Encrypt).
  - Avoid "Flexible" — that creates a security hole (CF→origin via plain HTTP).

SSL/TLS → Edge Certificates:
- Always Use HTTPS: **ON**
- Automatic HTTPS Rewrites: **ON**
- Minimum TLS Version: **TLS 1.2** (or 1.3)
- Opportunistic Encryption: **ON**

### 4. Page Rules — cache strategy

Cloudflare free tier includes 3 page rules. We need exactly 3.

**Settings → Rules → Page Rules → Create Page Rule:**

#### Rule 1 — `/storage/*` Cache Everything

- URL pattern: `alisadikinma.com/storage/*`
- Settings:
  - Cache Level: **Cache Everything**
  - Edge Cache TTL: **a month**
  - Browser Cache TTL: **a month**
- Save and Deploy. Make this priority #1 (drag to top).

#### Rule 2 — `/uploads/*` Cache Everything

- URL pattern: `alisadikinma.com/uploads/*`
- Settings:
  - Cache Level: **Cache Everything**
  - Edge Cache TTL: **a month**
  - Browser Cache TTL: **a month**
- Save and Deploy. Priority #2.

#### Rule 3 — `/api/*` Cache Bypass

- URL pattern: `alisadikinma.com/api/*`
- Settings:
  - Cache Level: **Bypass**
- Save and Deploy. Priority #3.

**Why bypass `/api/*`**: API responses are user-state-dependent (auth, language). Origin already returns proper `Cache-Control: no-cache, private` headers; let CF respect that and never cache API JSON.

### 5. Network optimizations

Cloudflare dashboard → Speed → Optimization.

- Auto Minify: HTML ✓, CSS ✓, JavaScript ✓
- Brotli: **ON**
- Early Hints: **ON** (free, helps browser preload)
- Rocket Loader: **OFF** (rewrites JS in ways that break some Vue hydration patterns — leave off)

Cloudflare dashboard → Network.

- HTTP/3 (with QUIC): **ON**
- 0-RTT Connection Resumption: **ON**
- gRPC: leave default
- WebSockets: **ON** (keep — not used now but cheap insurance)
- IPv6 Compatibility: **ON**

### 6. Caching configuration

Cloudflare dashboard → Caching → Configuration.

- Caching Level: **Standard** (respects query strings — important for Vite hashed assets)
- Browser Cache TTL: **Respect Existing Headers** (don't override origin's `Cache-Control`)
- Crawler Hints: **ON** (free SEO boost)

### 7. Verification

Wait 2-3 min after deploying rules, then verify edge cache:

```bash
# First request — should be MISS (origin → edge → client)
curl -sI https://alisadikinma.com/storage/projects/thumbnail/49_dlp-form-request-cybersecurity.png \
  | grep -iE 'cf-cache-status|cache-control'

# Expected first time:
#   cf-cache-status: MISS  (or DYNAMIC on very first ever request)

# Second request — should be HIT
curl -sI https://alisadikinma.com/storage/projects/thumbnail/49_dlp-form-request-cybersecurity.png \
  | grep -iE 'cf-cache-status|cache-control'

# Expected:
#   cf-cache-status: HIT
#   age: <seconds since first request>
```

Verify API bypass:

```bash
curl -sI https://alisadikinma.com/api/projects | grep -iE 'cf-cache-status|cache-control'

# Expected:
#   cf-cache-status: BYPASS  (or DYNAMIC)
#   cache-control: no-cache, private
```

### 8. Smoke tests

- [ ] Open https://alisadikinma.com in incognito → loads normally, no cert warnings
- [ ] DevTools → Network → reload → response headers show `cf-ray: <id>` and `server: cloudflare`
- [ ] Login still works (admin)
- [ ] Image upload still works (admin)
- [ ] No mixed content warnings

### 9. 24h follow-up checks

- [ ] CF Analytics dashboard shows traffic flowing through edge
- [ ] Origin VPS bandwidth dropped measurably (CF caches at edge — expect 60-80% reduction on bandwidth)
- [ ] No spike in 5xx errors in CF analytics
- [ ] Operator + visitor experience unchanged or faster

## Rollback

If anything breaks: DNS → toggle orange cloud back to grey on the record(s). Traffic reverts to direct origin in <5 min DNS propagation.

Worst case: page rules can be deleted with one click. SSL mode change to "Off" (no SSL) only if cert chain breaks somehow (very unlikely with Full strict + valid origin cert).

## What NOT to enable on free tier (yet)

- **Polish** (paid Pro plan) — auto WebP/AVIF conversion at edge. Plan covers this via Phase C backend variants instead.
- **Mirage** — image lazy-load injection. Conflicts with our BaseImage component (Phase D).
- **APO for WordPress** — irrelevant.
- **Argo Smart Routing** — paid, marginal latency improvement for our traffic pattern.
- **Workers** — keep available for future use (e.g., image transformation on demand) but not needed now.
