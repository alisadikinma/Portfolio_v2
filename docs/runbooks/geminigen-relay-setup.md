# GeminiGen Relay Webhook — Operator Setup

One-time setup so the `geminigen-api-client` plugin can route its GeminiGen.ai webhook callbacks through Portfolio_v2's stateless relay endpoint.

**Endpoint:** `POST https://alisadikinma.com/api/automation/geminigen/webhook`
**Design doc:** [docs/plans/2026-05-14-geminigen-relay-webhook.md](../plans/2026-05-14-geminigen-relay-webhook.md)
**Implementation commits:** `d30a9b6c` → `bedaa385` (5 commits, May 14, 2026)

## Prerequisites

- SSH access to VPS as `claudesn`
- Admin login at `https://alisadikinma.com/admin`
- GeminiGen.ai account for this plugin (separate from the existing account that powers blog/carousel — API key starts with `geminiai-6c98...`)
- The plugin checked out at `D:\Projects\claude-plugin\geminigen-api-client` (or wherever)

## Step 1 — Download GeminiGen webhook public key

The relay verifies inbound `x-signature` headers via RSA-SHA256 against the public key tied to the plugin's GeminiGen account.

1. Log in to the GeminiGen account that owns this plugin's API key
2. Navigate to **Service Integration** settings (per [GeminiGen webhooks docs](https://docs.geminigen.ai/getting-started/webhooks))
3. Click **Public Key** download link → save as `geminigen-relay-public.pem` locally
4. Confirm the file starts with `-----BEGIN PUBLIC KEY-----` and ends with `-----END PUBLIC KEY-----`

## Step 2 — Upload public key to VPS

```bash
# From your local machine
scp geminigen-relay-public.pem claudesn@<VPS_HOST>:/home/claudesn/

# Then SSH in and lock permissions
ssh claudesn@<VPS_HOST>
chmod 640 /home/claudesn/geminigen-relay-public.pem
chown claudesn:www-data /home/claudesn/geminigen-relay-public.pem
ls -la /home/claudesn/geminigen-relay-public.pem
# Expected: -rw-r----- 1 claudesn www-data ... geminigen-relay-public.pem
```

Mode `640` + group `www-data` lets BOTH PHP-FPM (`www-data` user, HTTP path) AND the queue worker (`claudesn` user, future use) read it.

## Step 3 — Add `.env` keys + recache

Edit `/var/www/Portfolio_v2/backend/.env` (or your deploy path). Append:

```env
# GeminiGen Relay (stateless webhook for geminigen-api-client plugin)
GEMINIGEN_RELAY_PUBLIC_KEY_PATH=/home/claudesn/geminigen-relay-public.pem
GEMINIGEN_RELAY_FORWARD_TIMEOUT=15
GEMINIGEN_RELAY_FORWARD_RETRIES=2
GEMINIGEN_RELAY_FORWARD_RETRY_DELAY_MS=1000
```

Then:

```bash
cd /var/www/Portfolio_v2/backend
php artisan config:clear
php artisan config:cache
sudo systemctl restart portfolio-queue.service     # picks up new env
```

Verify:

```bash
php artisan tinker --execute='dump(config("geminigen-relay"));'
# Expected: array with all 4 keys, public_key_path = /home/claudesn/geminigen-relay-public.pem
```

## Step 4 — Mint relay token via admin UI

1. Open `https://alisadikinma.com/admin/automation/tokens`
2. Click **Create Token**
3. Select **Category:** `GeminiGen Relay`
4. **Name:** `geminigen-prod` (UI auto-prefixes with `geminigen-`; type only `prod`)
5. **Abilities:** check `geminigen:relay` (only ability available in this category)
6. Click **Create**
7. **COPY THE PLAIN-TEXT TOKEN** shown in the modal — it cannot be retrieved later (Sanctum hashes server-side). Token format: `{id}|{64-char-hex-secret}`.

The token is cached in `sessionStorage` for the current admin tab, but DO NOT rely on that — paste it into the plugin config immediately.

## Step 5 — Configure plugin

In `D:\Projects\claude-plugin\geminigen-api-client\` (or however the plugin stores config — see its README):

```
GEMINIGEN_API_KEY=geminiai-6c98ca9915736c70b4096bde15261219
GEMINIGEN_RELAY_BASE_URL=https://alisadikinma.com/api/automation/geminigen/webhook
GEMINIGEN_RELAY_TOKEN=<paste-token-from-step-4>
```

When the plugin dispatches a generation to GeminiGen, it should construct `webhook_url` as:

```
https://alisadikinma.com/api/automation/geminigen/webhook
  ?cb={base64url(caller_callback_url)}
  &token={GEMINIGEN_RELAY_TOKEN}
```

where `base64url` is standard base64 with `+` → `-`, `/` → `_`, `=` stripped.

## Step 6 — Smoke test

Verify the endpoint is wired and gates are firing. From any shell with `curl`:

```bash
TOKEN="<paste-token>"
CB=$(echo -n "https://example.com/cb" | base64 | tr '+/' '-_' | tr -d '=')

# Should return 403 INVALID_SIGNATURE (token + cb pass, but signature is bogus)
curl -i -X POST \
  "https://alisadikinma.com/api/automation/geminigen/webhook?cb=${CB}&token=${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "X-Signature: deadbeef" \
  -d '{"event":"IMAGE_GENERATION_COMPLETED","uuid":"smoke-test","data":{}}'
```

Expected response:

```
HTTP/2 403
content-type: application/json

{"success":false,"error":{"code":"INVALID_SIGNATURE","message":"RSA signature verification failed"}}
```

That 403 proves: route is reachable, token validates, `cb` decodes, public key loads, RSA verify runs (and correctly rejects bogus signature). If you see 401 or 422 instead, walk back through steps 3/4/5.

Test the missing-token gate (no `token=` query param):

```bash
curl -i -X POST \
  "https://alisadikinma.com/api/automation/geminigen/webhook?cb=${CB}" \
  -H "Content-Type: application/json" \
  -H "X-Signature: deadbeef" \
  -d '{}'
# Expect 401 MISSING_TOKEN
```

## Step 7 — Rollback / token rotation

**Rotate token** (operator suspects leak):
1. `/admin/automation/tokens` → delete the `geminigen-prod` row
2. Mint a new one
3. Update plugin config + restart whatever runs the plugin
4. In-flight GeminiGen jobs whose webhook fires AFTER rotation will return 401 → callback lost. Acceptable cost.

**Kill the integration entirely:**
- Delete the token → all subsequent webhooks return 401
- Or comment out the route in `routes/api.php` and redeploy (heavier but auditable)

## Observability

Relay logs land in `storage/logs/laravel.log` with the prefix `[GeminiGenRelay]`:

```bash
ssh claudesn@<VPS_HOST>
cd /var/www/Portfolio_v2/backend
tail -f storage/logs/laravel.log | grep GeminiGenRelay
```

Per-webhook log entry contains `event`, `uuid`, `cb_host` (not full URL — log sanitation), `forward_status`, `relayed` (bool), `duration_ms`, and `error` (null on success).

## Known operational behavior

- **Token in URL leaks to nginx access logs.** Documented trade-off — accepted because logs are VPS-private and HTTPS protects the wire.
- **Caller down → callback dropped after 2 retries.** Relay returns 200 to GeminiGen so GeminiGen doesn't retry-storm the relay endpoint over the next 3 hours. The dropped callback is in the log entry with `relayed=false` + `forward_status=5xx` or `null`.
- **GeminiGen signed asset URLs expire fast.** Caller MUST download the asset (`data.generated_image[0].image_url` or video equivalent) synchronously in its webhook handler, before queueing any downstream processing.
- **No replay protection v1.** A captured valid webhook could be re-played by an attacker against the caller endpoint. Mitigated by the caller being on the same private trust boundary as Portfolio. Add UUID-dedup table if/when attack pattern observed.
