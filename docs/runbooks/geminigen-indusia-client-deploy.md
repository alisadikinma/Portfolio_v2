# Runbook — GeminiGen → indusia client (SSOT) deploy

**What this ships:** the backend stops hand-rolling GeminiGen/snapgen HTTP submits and instead
shells the `indusiagen-api-client` CLI (the single source of truth for the wire protocol) via
[`GeminiGenClientBridge`](../../backend/app/Services/GeminiGenClientBridge.php). Two feature flags
gate it per surface — **both default OFF**, so a deploy with no env change is a no-op (old PHP HTTP
path stays live). Design + reconciled doc grounding: [plan](../plans/2026-07-01-geminigen-indusia-client-ssot.md).

Rollout is **flag-flip, not code-deploy** — the code is already merged behind the flags. Enable
images first (Wave 1), watch, then video (Wave 2).

---

## 0. One-time VPS client install

The bridge runs the client as user **claudesn** (queue-worker context, same as `LINKEDIN_GEN_*`).
Do it once per VPS.

The repo is **private** — claudesn has no account-wide GitHub key (its `deploy_key` /
`jobhunter_deploy` are per-repo). Add a dedicated read-only deploy key first:

```bash
# on the VPS as claudesn:
ssh-keygen -t ed25519 -N "" -C "indusiagen-deploy-vps" -f ~/.ssh/indusiagen_deploy
cat >> ~/.ssh/config <<'EOF'

Host github-indusiagen
  HostName github.com
  User git
  IdentityFile ~/.ssh/indusiagen_deploy
  IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config ~/.ssh/indusiagen_deploy
cat ~/.ssh/indusiagen_deploy.pub    # ← add this as a READ-ONLY deploy key on the repo
# (gh from an authed machine: gh api repos/alisadikinma/indusiagen-api-client/keys -f title=vps -f key="<pubkey>" -F read_only=true)

# then clone via the alias:
cd /home/claudesn
git clone git@github-indusiagen:alisadikinma/indusiagen-api-client.git
cd indusiagen-api-client
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt      # or: .venv/bin/pip install -e .
```

**API key** — the client has NO dotenv loader, so a repo `.env` is NOT read (`.env.example`
is misleading). Use the documented config-file fallback (`resolve_api_key` reads
`GEMINIGEN_API_KEY` env → `~/.config/geminigen/config.json`). Key NEVER on argv (`ps`):

```bash
mkdir -p ~/.config/geminigen
KEY=$(grep -E '^GEMINIGEN_API_KEY=' /var/www/Portfolio_v2/backend/.env | cut -d= -f2- | tr -d '"'"'"'"'\r)
umask 077; printf '{"api_key": "%s"}\n' "$KEY" > ~/.config/geminigen/config.json
chmod 600 ~/.config/geminigen/config.json
```

Base URL defaults to snapgen inside the client (`GEMINIGEN_BASE_URL` env overrides) — no config needed.

Paths must match [`config/geminigen.php`](../../backend/config/geminigen.php) defaults
(`/home/claudesn/indusiagen-api-client` + `.venv/bin/python`) or be overridden via env below.

---

## Step-0 verification checklist (RUN BEFORE FLIPPING ANY FLAG)

All three as **claudesn**, from the repo root. Each must succeed or the matching surface will fail
silently to `manual_review` / stuck-poll. A green submit prints `submitted: uuid=<uuid> …`; an
`HTTP 402` means the **snapgen account is out of credits** (hits the old HTTP path too — top up first).

The CLI's `--aspect` is a strict enum: `1:1, 16:9, 9:16, 4:3, 3:4, 21:9, 3:2, 2:3` — **`4:5` is
rejected** (the raw API silently fell it back to 16:9; the carousel path uses `3:4`, valid).

```bash
cd /home/claudesn/indusiagen-api-client
PY=.venv/bin/python

# (1) IMAGE submit reachable — expect: `submitted: uuid=<uuid> status=<n>`
$PY -m scripts.geminigen_image image "a calm test still" --model nano-banana-pro --aspect 3:4 --no-wait

# (2) gpt-image-2 is PREMIUM-plan only + LOCAL-ref only. Confirm the account plan allows it.
#     URL refs are auto-materialized to temp files by the bridge, but the MODEL access must exist:
$PY -m scripts.geminigen_image image "typography test" --model gpt-image-2 --mode medium --no-wait
#     → 403 / "premium" in the error = plan gate. Keep linkedin_carousel_image_model on
#       nano-banana-pro until the plan is upgraded.

# (3) VIDEO submit — per-family subcommand. GROK only accepts aspect 2:3 (9:16 → HTTP 400):
$PY -m scripts.geminigen_video grok "a subtle push-in" --aspect 2:3 --mode custom --no-wait
$PY -m scripts.geminigen_video veo  "a slow orbit" --aspect 9:16 --mode frame --no-wait
```

Note on poll: the client's `check <uuid>` subcommand is an **unimplemented stub** in the deployed
version, so the bridge is SUBMIT-only. Completion polling stays in the backend crons
(`blog:process-images`, `linkedin:poll-hook-videos`, `repurpose:poll-rebrand-assets`) as a plain
`GET {base_url}/history/{uuid}` — model-agnostic, no drift, already on `config('geminigen.base_url')`.

---

## 1. Env (Laravel `.env` on the VPS)

```env
# Base URL — vendor rebranded api.geminigen.ai → api.snapgen.ai (already the default).
GEMINIGEN_BASE_URL=https://api.snapgen.ai/uapi/v1

# Bridge transport. 'local' = direct Process::run of the venv python as the queue user
# (correct for the queue-dispatched image/video paths — claudesn). Use 'ssh' only if a
# submit runs in www-data HTTP context (none currently do).
GEMINIGEN_CLIENT_DRIVER=local
GEMINIGEN_CLIENT_PATH=/home/claudesn/indusiagen-api-client/.venv/bin/python
GEMINIGEN_CLIENT_REPO=/home/claudesn/indusiagen-api-client
GEMINIGEN_CLIENT_TIMEOUT=60
# ssh-driver only:
# GEMINIGEN_CLIENT_SSH_HOST=localhost
# GEMINIGEN_CLIENT_SSH_USER=claudesn
# GEMINIGEN_CLIENT_SSH_KEY=/home/claudesn/.ssh/id_ed25519

# Rollout flags — DEFAULT OFF. Flip one wave at a time.
GEMINIGEN_USE_INDUSIA_IMAGES=false
GEMINIGEN_USE_INDUSIA_VIDEO=false
```

After any `.env` edit:

```bash
cd /var/www/Portfolio_v2/backend
php artisan config:cache
sudo systemctl restart portfolio-queue.service   # worker picks up new config
```

---

## 2. Rollout — Wave 1 (images)

1. Step-0 checks (1) + (2) green.
2. `GEMINIGEN_USE_INDUSIA_IMAGES=true` → `config:cache` → restart queue.
3. Trigger one blog article image gen (or a carousel "Regenerate All Images") from admin.
4. Confirm in `laravel.log`: bridge `submitted: uuid=…` line, `image_generation_jobs` row created
   `status=generating`, then the poll cron flips it `done` (webhook never fires — poll is the sole
   completion path, per project memory).
5. **gpt-image-2 carousel knob** — optional, Premium-plan only. Set the operator setting
   `linkedin_carousel_image_model=gpt-image-2` (`/admin/settings`, LinkedIn group) ONLY after
   step-0 (2) confirmed the plan allows it. Default stays `nano-banana-pro`.

**Rollback:** `GEMINIGEN_USE_INDUSIA_IMAGES=false` → `config:cache` → restart. Instant revert to the
PHP HTTP submit; no data migration.

---

## 3. Rollout — Wave 2 (video) — only after Wave 1 is prod-green

1. Step-0 check (3) green (both grok 2:3 + veo frame).
2. `GEMINIGEN_USE_INDUSIA_VIDEO=true` → `config:cache` → restart queue.
3. Trigger an IG carousel GROK hook video (`regenerate-hook-video`) + a `video_rebrand` Veo clip.
4. Confirm bridge submit lines + `repurpose_video_slides` / hook rows advancing via the poll crons.

**Rollback:** `GEMINIGEN_USE_INDUSIA_VIDEO=false` → `config:cache` → restart.

---

## Gotchas

- **GROK aspect** — the video CLI's grok family accepts **`2:3` only**; `9:16` → HTTP 400. The bridge
  passes whatever aspect the dispatcher sets, so `dispatchGrokClip`/`dispatchHookVideo` must send `2:3`
  (they do). Veo takes the real aspect (`9:16` etc.).
- **gpt-image-2 refs** — model rejects URL refs. The bridge auto-downloads each `http…` ref to a temp
  file and cleans it up. No action needed, but a ref host that 404s → that ref is silently dropped
  (fail-soft), not a submit failure.
- **API key** — never appears on the CLI argv (would be visible in `ps`). The client reads it from its
  own `/home/claudesn/indusiagen-api-client/.env`. If submits 401, fix THAT file, not Laravel `.env`.
- **Wrong driver** — if a future submit path runs in www-data HTTP context, `local` driver will fail
  (www-data can't read the claudesn venv). Switch that surface to `ssh` + the claudesn key (mode 600),
  same two-context rule as `ARTICLE_GEN_*` vs `LINKEDIN_GEN_*`.
