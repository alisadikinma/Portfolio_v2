# Postiz Local-Node Cross-Post Hub — Deploy Runbook

**Feature:** Self-hosted Postiz on a local machine as the primary cross-post
publish backbone, coordinated by the Laravel VPS via a pull model. Unblocks
autonomous **IG video carousel** publishing (the only path that works — Publer
crashes on it). Publer is retained as an automatic fallback.

**Design + plan:** [docs/plans/2026-06-13-postiz-local-node-crosspost.md](../plans/2026-06-13-postiz-local-node-crosspost.md)

**Default state:** OFF. `postiz_enabled='false'` → every cross-post sibling keeps
dispatching to Publer exactly as before. Zero production impact until you flip it.

---

## 0. Architecture recap

```
VPS (alisadikinma.com)  ── pull ──▶  LOCAL PC (dumb executor)
  • slot fires → postiz_publish_jobs.ready_to_publish
  • GET  /automation/postiz/pending   (atomic claim + 10m lease)
  • POST /automation/postiz/{job}/result   (accepted → published|failed)
  • POST /automation/postiz/channels/sync   (auto platform→integration map)
  • Watchdog (postiz:reap-unclaimed, every min): claim-aware Publer fallback
                                                  (NEVER once postiz_post_id set)
LOCAL: Postiz (Docker) + Cloudflare named tunnel + Node poller (claims, uploads,
       publishes via /public/v1, callbacks).
```

The VPS owns ALL timing + anti-double-publish authority. The local node is dumb.

---

## 1. Machine sizing

- **8 GB RAM minimum (tight), 16 GB comfortable.** Real stack = postiz + 2×
  Postgres (app PG17 + Temporal PG16) + Redis + Temporal + Elasticsearch (ES7) +
  temporal-ui. Realistic idle ~3–4 GB. Disk ~5–10 GB. No video encoding locally.
- **Mini-PC Intel N100 16GB** (~$150–200, ~6W) ideal for 24/7. A regular dev PC
  works too — the Publer fallback covers any downtime for Publer-capable platforms
  (IG video carousel waits for the node to come back).
- **Slim option (optional, saves ~0.5GB):** set `ENABLE_ES=false` on the
  `temporal` service + remove `temporal-elasticsearch` + its `depends_on`.

## 2. Bring up the Postiz stack

```bash
cd infra/postiz-local
cp .env.example .env            # fill JWT secret + social keys + VPS/Postiz tokens
# Temporal needs the dynamicconfig dir from the postiz-app repo:
cp -r /path/to/postiz-app/dynamicconfig ./dynamicconfig
docker compose up -d
docker compose ps               # all services healthy/up; Temporal UI on :8080
```

Open `http://localhost:4007`, create the (single) admin account. `DISABLE_REGISTRATION=true`
locks signups after the first account.

> **`spotlight` (Sentry) is intentionally dropped** from this compose — nothing
> depends on it. **`API_LIMIT` is raised to 300** (docker default 30/hr is too low
> for an 11-call carousel + confirm polls).

## 3. Cloudflare named tunnel (OAuth callbacks)

OAuth callback is the only thing needing inbound; everything else is outbound.
A **named** tunnel gives a stable hostname (required — OAuth redirect URIs are
registered against `postiz.alisadikinma.com` exactly).

```bash
cloudflared tunnel login
cloudflared tunnel create postiz-local
cloudflared tunnel route dns postiz-local postiz.alisadikinma.com
cp cloudflared-config.example.yml ~/.cloudflared/config.yml   # edit UUID + paths
cloudflared tunnel run postiz-local      # or install as a service for 24/7
```

Verify `https://postiz.alisadikinma.com` resolves to the Postiz UI.

## 4. Connect channels (one-time per channel)

In Postiz UI → add channel → platform OAuth → callback returns to the tunnel URL.
Each platform needs its own OAuth dev-app keys (in `.env`) AND the tunnel redirect
URI registered in that dev-app:

- **Instagram** — `instagram` (needs a FB Business + linked FB Page) OR
  `instagram-standalone` (professional account, no FB Page). Pick per account setup.
- **LinkedIn** — `linkedin` (personal) and/or `linkedin-page` (company).
- **TikTok**, **Threads**, **Facebook** (Page).
- **Medium** — `medium`. ⚠️ Medium largely stopped issuing new integration tokens
  (~2023); verify the account can still mint one before relying on blog→Medium.

Token refresh runs outbound via Temporal (IG/FB/LinkedIn 60d, TikTok 24h) — the
tunnel may idle after connect; re-needed only if a refresh token itself expires.

## 5. Poller + Postiz API key

- Postiz UI → **Settings → Developers** → generate an **API key**. Put it in `.env`
  as `POSTIZ_API_KEY`.
- Mint a **VPS automation bearer token** (admin → Automation Tokens) → `.env`
  `VPS_AUTOMATION_TOKEN`. Set `VPS_API_BASE_URL=https://alisadikinma.com/api`.
- The `poller` container (in the same compose) auto-syncs the integration list to
  the VPS on startup + hourly — **no integration IDs are captured manually**; the
  VPS resolves platform→integration_id from `postiz_channels` at publish time.

Confirm the poller is alive:
```bash
docker compose logs -f poller     # "[poller] started — worker=local-1, ..."
```
Then on the VPS, confirm channels synced:
```bash
php artisan tinker --execute="App\Models\PostizChannel::all(['platform','handle','postiz_integration_id','enabled'])"
```

## 6. VPS settings

`postiz` settings group is seeded by `deploy.sh` (idempotent). Defaults:

| key | default | meaning |
|---|---|---|
| `postiz_enabled` | `false` | master switch (OFF = all Publer) |
| `postiz_lease_minutes` | `10` | claim lease window |
| `postiz_fallback_deadline_minutes` | `6` | watchdog deadline before Publer fallback |
| `postiz_api_base_url` | null | informational (poller pulls; not strictly required) |
| `postiz_worker_alert_minutes` | `20` | IG-video/Medium stuck-alert threshold |
| `postiz_medium_enabled` | `false` | blog→Medium cross-post toggle |

The watchdog cron `postiz:reap-unclaimed` is registered in `/admin/settings →
Scheduler` (every minute). It no-ops unless a job is past its deadline.

---

## 7. Phase J — Phased LinkedIn cutover

D3 (Postiz replaces Publer for ALL platforms) is **phased, not rip-and-replace**.
The VPS LinkedIn direct-OAuth publish path stays live throughout.

1. **IG first.** Connect IG in Postiz, flip `postiz_enabled=true`. This proves the
   loop on the platform Publer can't do (video carousel). Watch one real publish
   end-to-end: `postiz_publish_jobs` → `claimed` → `accepted` → `published` with a
   real `permalink`.
2. **Add LinkedIn in parallel.** Connect LinkedIn in Postiz. Cross-post LinkedIn
   siblings now route through Postiz too (the dispatcher branches on the mapped
   channel). Run **parallel for N posts**, compare permalinks + rendered quality
   against the legacy VPS direct-OAuth path.
3. **Retire VPS LinkedIn direct-OAuth** publish **only after** Postiz proves stable
   for LinkedIn. Keep the OAuth tokens until then.
4. **Rollback at any point** = set `postiz_enabled=false`. Within one slot tick
   (≤1 min) every sibling reverts to the Publer dispatch path. For Medium, also
   flip `postiz_medium_enabled=false`.

**Verification checklist:**
- [ ] Postiz stack healthy (`docker compose ps`; Temporal UI reachable on :8080).
- [ ] Tunnel resolves `https://postiz.alisadikinma.com` (named, stable).
- [ ] ≥1 channel connected; token refresh confirmed; `postiz_channels` synced on VPS.
- [ ] IG video carousel published end-to-end via Postiz (real permalink).
- [ ] Parallel-run LinkedIn comparison documented before retiring direct-OAuth.
- [ ] Rollback lever (`postiz_enabled=false`) reverts to Publer within one slot tick.

---

## 8. Anti-double-publish (how the lease protects you)

The race: VPS marks `ready` at 18:00; local publishes 18:01 (IG video child-poll
~4 min); watchdog deadline 18:05. Without the lease the watchdog would fire Publer
**mid-publish** → double post.

- `GET /pending` **atomically claims** (lockForUpdate) + sets a 10m lease. Claimed
  jobs aren't returned again; the watchdog skips active leases.
- The poller callbacks `accepted` (with `postiz_post_id`) the instant Postiz takes
  the job → the watchdog **permanently hands off** (never fires Publer past
  `accepted`, even on a later Temporal ERROR — that routes to a WARNING log for
  manual handling, never an auto-republish).
- The watchdog auto-fires Publer **only for jobs the poller NEVER claimed**
  (`status=ready_to_publish`, `claimed_at IS NULL` — i.e. the PC was offline at the
  slot, so Postiz definitively never saw it).
- **PC offline** → never claimed → Publer fallback at deadline ✓
- **PC slow** → active lease → watchdog waits ✓
- **PC crash AFTER Postiz accepted, BEFORE the accepted-callback** (the dangerous
  window) → the job is `claimed`, lease-expired, `postiz_post_id` still NULL. Since
  the post **may already be live on Postiz**, the watchdog does NOT auto-Publer —
  it parks the job as **`needs_review`** (not claimable, so the poller won't re-take
  it either) and alerts the operator to confirm + finish manually. A slow-but-alive
  poller still self-heals (a late `accepted`/`published` callback recovers it). This
  trades a little availability on the rare crash for a hard no-double-post guarantee.

## 9. Troubleshooting

| Symptom | Check |
|---|---|
| Jobs stuck `ready_to_publish`, never claimed | poller alive? `docker compose logs poller`. VPS token valid? |
| `postiz_channels` empty on VPS | poller integration-sync failing — check `VPS_*` env + token scope |
| OAuth callback fails | tunnel down, or redirect URI mismatch vs `POSTIZ_PUBLIC_URL` |
| Double post | should be impossible — verify lease + `postiz_post_id` hand-off; report if seen |
| IG video carousel stuck | only Postiz can publish it — no Publer fallback by design; bring the node online; WARNING log fires past `postiz_worker_alert_minutes` |
| Everything broke | `postiz_enabled=false` → instant Publer revert |
