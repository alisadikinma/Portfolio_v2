# Docker — Portfolio_v2

Two local stacks. The public site is Astro (`web/`) since PF-2; the Vue app in `frontend/` is admin-only.

| Stack | Command | Use |
|---|---|---|
| **Full site + prod-data copy** (review before release) | `scripts/pf2-local.sh up` | Astro + admin + API on http://localhost:8000. See [pf2-local/README.md](pf2-local/README.md). |
| **Dev API + admin** (root `docker-compose.yml`) | `docker compose up -d --build` | MySQL + Laravel (`portfolio_backend`) + nginx :8000 + admin Vite :5173 + phpMyAdmin :8080. Starts with an EMPTY database. |

## Dev API + admin stack

```bash
docker compose up -d --build
docker compose exec backend php artisan migrate --seed   # first run only
docker compose exec backend php artisan test
docker compose down        # keep data
docker compose down -v     # wipe database volume
```

- MySQL: `root`/`root`, app user `portfolio`/`secret`, db `portfolio_v2`.
- No database dump is imported. Need real data? Use the pf2-local stack (`scripts/pf2-local.sh import`).
- Mail: set `MAIL_MAILER=log` in `backend/.env` (mail lands in `backend/storage/logs/laravel.log`).

## Files

- `../docker-compose.yml` — dev stack above
- `php/Dockerfile` — PHP 8.4-fpm image (also the `pf2-backend` image used by pf2-local and the phpunit command in PF-2 docs)
- `php/entrypoint.sh` — composer install + key:generate + storage:link on boot
- `nginx/default.conf` — serves `backend/public`, proxies PHP to `backend:9000` (dev stack only)
- `pf2-local/` — full-site review stack
