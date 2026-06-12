# Runbook — Postiz self-host deploy (Phase 0 of IG video-carousel rebrand)

> **Scope:** stand up a self-hosted Postiz on the production VPS, connect Ali's Instagram, and prove it publishes a **video carousel** before any `PostizClient` code is written. This is the HARD GATE for [docs/plans/2026-06-12-ig-video-carousel-rebrand.md](../plans/2026-06-12-ig-video-carousel-rebrand.md) Phase 0.
>
> **Why Postiz** (not Publer): Publer cannot publish IG video carousels (live-probed 2026-06-12, all 3 type variants fail). Postiz is source-verified to do the official Meta Graph API child-status-poll carousel sequence. Background: memory `postiz-self-host-publish`, `publer-video-carousel-impossible`.
>
> **Decisions locked (2026-06-12):** subdomain `postiz.alisadikinma.com`; reuse the existing IG/FB Meta app; IG is **Professional but NOT linked to a FB Page** → use the **Instagram Standalone** connection flow (`INSTAGRAM_APP_ID/SECRET`, NOT `FACEBOOK_APP_ID/SECRET`).

---

## 0. Reality check — resource footprint (read before deploying)

The canonical Postiz compose (gitroomhq/postiz-docker-compose) runs a **heavy** stack on top of whatever the VPS already hosts (Portfolio_v2: Laravel + MySQL + queue worker + claudesn pipelines):

| Service | Image | Notes |
|---|---|---|
| postiz (all-in-one) | `ghcr.io/gitroomhq/postiz-app:latest` | frontend + backend + workers (port `4007:5000`) |
| postiz-postgres | `postgres:17-alpine` | Postiz DB |
| postiz-redis | `redis:7.2` | queues / cache |
| temporal | `temporalio/auto-setup:1.28.1` | workflow engine (v2.12+ requirement) |
| temporal-postgresql | `postgres:16` | Temporal DB |
| temporal-elasticsearch | `elasticsearch:7.17.27` | **~256–512MB RAM**, the biggest single consumer |
| temporal-ui | `temporalio/ui:2.34.0` | port `8080` (keep internal / firewalled) |

Postiz's own tested baseline is **Ubuntu 24.04, 2GB RAM, 2 vCPU — for Postiz alone.** Co-hosting with Portfolio_v2 means **budget at least +2–3GB RAM headroom**. If the VPS is tight:
- Check free RAM first: `free -h`. If < 3GB free, **do not deploy here** — use a separate small VPS for Postiz (it talks to Portfolio_v2 only over HTTPS API, so it can live anywhere).
- ES is the swing factor; there is no supported "Temporal without ES" path in the canonical compose — don't hand-strip it (drifts from canonical).

> **STOP-AND-ASK** if `free -h` shows < 3GB available — confirm with the operator whether to add RAM, use a separate VPS, or pause.

---

## 1. DNS + TLS for `postiz.alisadikinma.com`

1. Add a DNS record for `postiz.alisadikinma.com` → VPS IP (Cloudflare). **Grey-cloud (DNS-only)** first to simplify cert issuance + avoid CF body-size/stream limits on the API; can orange-cloud later once verified (mind CF's 100MB upload cap on free plans — video slides are small, fine).
2. Issue a cert (the VPS already terminates TLS for alisadikinma.com via nginx + certbot/CF origin cert) — add `postiz.alisadikinma.com` to the cert.

---

## 2. Clone the canonical compose (DO NOT snapshot)

Per Postiz docs, the compose + Temporal `dynamicconfig/` change between releases — always pull from the repo, never copy a frozen version into Portfolio_v2.

```bash
sudo mkdir -p /opt/postiz && sudo chown $USER:$USER /opt/postiz
cd /opt/postiz
git clone https://github.com/gitroomhq/postiz-docker-compose .
```

This gives `docker-compose.yaml` + `dynamicconfig/` (Temporal mounts it).

---

## 3. Configure env (override the compose defaults)

Edit the `environment:` block of the `postiz` service in `/opt/postiz/docker-compose.yaml` (or move them to a `postiz.env` per Postiz Option B). Set **these** to our values; leave the Temporal/Postgres/Redis service blocks untouched:

```yaml
# === Required — public origin (all-in-one image serves one origin) ===
MAIN_URL: 'https://postiz.alisadikinma.com'
FRONTEND_URL: 'https://postiz.alisadikinma.com'
NEXT_PUBLIC_BACKEND_URL: 'https://postiz.alisadikinma.com/api'   # public API base = NEXT_PUBLIC_BACKEND_URL + /public/v1
BACKEND_INTERNAL_URL: 'http://localhost:3000'                    # leave as-is (in-container)
TEMPORAL_ADDRESS: 'temporal:7233'                               # leave as-is
JWT_SECRET: '<run: openssl rand -hex 32>'                       # MUST be unique; rotating logs everyone out
IS_GENERAL: 'true'                                             # open-source build routes
RUN_CRON: 'true'

# === Lock down to single operator ===
DISABLE_REGISTRATION: 'false'   # leave FALSE for the very first signup, then flip TRUE (see §6) and recreate

# === Public API throughput (compose default is 30; docs default 90) ===
API_LIMIT: 90

# === Instagram STANDALONE (IG Professional, no FB Page link) ===
INSTAGRAM_APP_ID: '<from Meta app — Instagram product>'
INSTAGRAM_APP_SECRET: '<from Meta app — Instagram product>'
# (FACEBOOK_APP_ID/SECRET left blank — we are NOT using the FB Business flow)

# Storage stays local (STORAGE_PROVIDER: 'local', UPLOAD_DIRECTORY: '/uploads') — fine for v1.
```

> Resolved public endpoints with the above:
> - Public API: `https://postiz.alisadikinma.com/api/public/v1`
> - MCP (optional): `https://postiz.alisadikinma.com/api/mcp` (verify exact path after boot; docs show `/mcp` on the backend, which is under `/api` here)
> - IG standalone OAuth redirect: `https://postiz.alisadikinma.com/integrations/social/instagram-standalone`

---

## 4. nginx reverse proxy (chunked streaming + large upload body)

Add a server block proxying `postiz.alisadikinma.com` → `127.0.0.1:4007`. Two non-defaults matter: **streaming** (MCP uses `Transfer-Encoding: chunked`) and **upload body size** (video slides via `/api/public/v1/upload`).

```nginx
server {
    server_name postiz.alisadikinma.com;
    listen 443 ssl http2;
    # ... ssl_certificate / ssl_certificate_key for postiz.alisadikinma.com ...

    client_max_body_size 200m;          # video uploads
    proxy_read_timeout 300s;            # long media processing / poll

    location / {
        proxy_pass http://127.0.0.1:4007;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_buffering off;            # required for MCP chunked streaming
    }
}
```

Reload: `sudo nginx -t && sudo systemctl reload nginx`. Keep Temporal UI (`:8080`) firewalled / not proxied publicly.

---

## 5. First boot

```bash
cd /opt/postiz
docker compose up -d
docker compose logs -f postiz      # watch for "listening" / migration success; ES + Temporal take ~1–2 min
```

Wait for the postiz healthcheck to go healthy (`start_period` 120s). Then open `https://postiz.alisadikinma.com`.

---

## 6. Create the single operator account, then lock signup

1. Register the first user via the web UI (Ali's email).
2. Edit env `DISABLE_REGISTRATION: 'true'`, then `docker compose down && docker compose up -d` (env changes need recreate). Signup page + OIDC now disabled.

---

## 7. Meta app — add Instagram product (reuse existing app)

In the existing IG/FB Meta Developer app (developers.facebook.com):

1. Add the **Instagram** product (Instagram API setup → "Set up Instagram Business Login"). This is the Standalone path — does not require the FB Business / Page link.
2. Set the OAuth Redirect URI: `https://postiz.alisadikinma.com/integrations/social/instagram-standalone`.
3. Copy **Instagram App ID** + **Instagram App Secret** → into the compose `INSTAGRAM_APP_ID/SECRET` (§3), recreate the container.
4. **App Roles → add Ali's IG handle as an "Instagram Tester"**, then accept the invite inside the IG app (Settings → Apps and websites). This lets the account publish WITHOUT full App Review while we validate.

> Full App Review for `instagram_content_publish` is only needed to go beyond tester accounts. For Ali's own single account, tester role is enough to prove + operate v1.

---

## 8. Connect Instagram in Postiz

Postiz UI → Add Channel → **Instagram (Standalone)** → authorize. Confirm the channel appears connected.

---

## 9. Mint a Public API key

Postiz UI → Settings → Developers → Public API → generate key. This is the key Portfolio_v2's future `PostizClient` will use (store later in `settings` group=postiz, encrypted). For the probe, export it locally.

---

## 10. LIVE TEST — publish a video carousel via the public API (the gate)

Use 2–3 short rebranded mp4 slides (from the existing POC output, or any 2–3 short 1080×1350 mp4s). **Schedule far-future** so nothing goes live unintentionally; delete after, OR let one publish to verify the real carousel then remove it.

```bash
export PK='<postiz api key>'
BASE='https://postiz.alisadikinma.com/api/public/v1'

# 1) upload each slide video → capture {id, path}
curl -s -X POST "$BASE/upload" -H "Authorization: $PK" -F "file=@slide1.mp4"
curl -s -X POST "$BASE/upload" -H "Authorization: $PK" -F "file=@slide2.mp4"
curl -s -X POST "$BASE/upload" -H "Authorization: $PK" -F "file=@slide3.mp4"

# 2) get the IG integration id
curl -s "$BASE/integrations" -H "Authorization: $PK"

# 3) create a far-future scheduled IG carousel post
curl -s -X POST "$BASE/posts" -H "Authorization: $PK" -H "Content-Type: application/json" -d '{
  "type": "schedule",
  "date": "2027-01-01T10:00:00.000Z",
  "shortLink": false,
  "tags": [],
  "posts": [{
    "integration": { "id": "<IG_INTEGRATION_ID>" },
    "value": [{
      "content": "video carousel probe",
      "image": [
        { "id": "<id1>", "path": "<path1>" },
        { "id": "<id2>", "path": "<path2>" },
        { "id": "<id3>", "path": "<path3>" }
      ]
    }],
    "settings": { "__type": "instagram", "post_type": "post" }
  }]
}'
```

Verify in the Postiz UI that the post is built as a carousel of 3 videos (Temporal UI `:8080` shows the workflow). To confirm IG truly accepts it, optionally change `type` to `"now"` once, watch it post a real carousel to Ali's IG, then delete from IG. Otherwise delete the scheduled post in the UI before its date.

**Record for Phase G** (paste into the plan's Phase 0 verification): exact upload response shape, the `integrations` id, and the final `/posts` envelope that worked. That becomes the `PostizClient`/`PostizPayloadBuilder` contract.

---

## 11. Rollback / teardown

```bash
cd /opt/postiz
docker compose down              # stop (keeps volumes)
docker compose down -v           # nuke incl. volumes (full reset)
```

Remove the nginx server block + reload, and the DNS record, to fully back out. No Portfolio_v2 code touched at Phase 0, so rollback is isolated.

---

## Verification checklist (Phase 0 exit)

- [ ] `free -h` confirmed ≥3GB headroom (or moved to a separate VPS)
- [ ] `https://postiz.alisadikinma.com` loads; signup locked (`DISABLE_REGISTRATION=true`)
- [ ] `GET /api/public/v1/integrations` returns Ali's IG channel
- [ ] A **video carousel** builds (3 video children → CAROUSEL container) and either posts live to IG or reaches FINISHED before `media_publish`
- [ ] Exact upload + `/posts` payload + integration id recorded in the plan for Phase G
- [ ] No unintended live post left on Ali's account
- [ ] Temporal UI (`:8080`) not publicly exposed

> On PASS → proceed to plan Phase A (migration). On FAIL (App Review blocks publish, format rejection) → STOP-AND-ASK: fall back to manual Social-Studio draft (operator posts in IG app).
