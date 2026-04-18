# GitHub Actions — Deploy to VPS

## Overview

`deploy.yml` runs on every push to `main` (and on manual dispatch from the Actions tab). It SSHs into the production VPS and runs `scripts/deploy.sh`, which:

1. `git fetch + reset --hard origin/main`
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force`
4. `php artisan db:seed --class=CreatorBrandSettingsSeeder --force` (idempotent)
5. Laravel cache clear + recache (config, route, view)
6. `npm ci + npm run build` in `frontend/`
7. Fix `storage/` + `bootstrap/cache/` permissions for `www-data`
8. `php artisan queue:restart`
9. Post-deploy health check via `curl https://alisadikinma.com/api/health`

## Required GitHub Secrets

Go to **Settings → Secrets and variables → Actions → New repository secret** and add:

| Secret | Example value | Purpose |
|---|---|---|
| `VPS_SSH_HOST` | `alisadikinma.com` or `31.97.188.145` | VPS hostname or IP |
| `VPS_SSH_USER` | `www-data` or `deploy` | SSH user with project-directory write access |
| `VPS_SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----\n...` | Private key (full contents) — matches an `authorized_keys` entry on the VPS |
| `VPS_SSH_PORT` | `22` (optional) | Override if SSH runs on a non-standard port |
| `VPS_PROJECT_PATH` | `/var/www/alisadikinma.com` | Absolute path to the project root on the VPS |

### Generating the SSH key

On your local machine:
```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/portfolio_v2_deploy -N ""
cat ~/.ssh/portfolio_v2_deploy.pub    # add this line to VPS ~/.ssh/authorized_keys
cat ~/.ssh/portfolio_v2_deploy        # paste full contents into VPS_SSH_KEY secret
```

On the VPS:
```bash
# as the deploy user (e.g. www-data or a dedicated deploy account)
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "<paste public key here>" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

## First-run checklist

- [ ] All 5 secrets added in GitHub repo settings
- [ ] SSH user has write access to `VPS_PROJECT_PATH`
- [ ] SSH user can run `composer`, `php`, `npm` (check `which composer npm node`)
- [ ] SSH user has passwordless `sudo` for `chown` (optional — script falls back to non-sudo chown)
- [ ] Webhook reachability: push a small doc-only commit to `main` and watch the Actions tab

## Manual trigger

From the GitHub web UI:
1. Repo → **Actions** tab
2. Select **Deploy to VPS** workflow in the left sidebar
3. Click **Run workflow** dropdown → select `main` → **Run workflow**

## Troubleshooting

**`Permission denied (publickey)`** — `VPS_SSH_KEY` doesn't match any entry in VPS `authorized_keys`. Re-check both files.

**`host key verification failed`** — The `ssh-keyscan` step in the workflow failed. Check `VPS_SSH_HOST` matches a reachable host from GitHub's runner.

**`composer: command not found`** — SSH user's `$PATH` doesn't include composer. Either set up a login shell for the user (`usermod -s /bin/bash <user>`) or call composer by absolute path in `deploy.sh`.

**`php artisan migrate: SQLSTATE access denied`** — The `.env` on the VPS may differ from expectations; verify DB credentials match the production MySQL config.

**Post-deploy 500 errors** — Most common after a deploy is a stale `config:cache`. Re-run the workflow, or SSH in and run `cd <project> && cd backend && php artisan config:clear && php artisan config:cache`.

## Concurrency

`concurrency: deploy-production` prevents two deploys from running simultaneously. `cancel-in-progress: false` means a push during an in-flight deploy queues the new one — it doesn't interrupt the active deploy mid-migration.
