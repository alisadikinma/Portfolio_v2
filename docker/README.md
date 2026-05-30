# Docker Local Stack — Portfolio_v2

Full local stack: MySQL 8 + Laravel (PHP-FPM) + Nginx + Vue (Vite) + phpMyAdmin.
The dump `portfolio_v2_20260530.sql` is auto-imported into MySQL on first boot.

## Services & URLs

| Service     | URL                          | Notes                                   |
|-------------|------------------------------|-----------------------------------------|
| Frontend    | http://localhost:5173        | Vue 3 + Vite dev server (HMR)           |
| Backend API | http://localhost:8000/api    | Laravel 12 via Nginx + PHP-FPM          |
| phpMyAdmin  | http://localhost:8080        | server `mysql`, user `root` / `root`    |
| MySQL       | localhost:3306               | db `portfolio_v2`                        |

## Credentials

- MySQL root: `root` / `root`
- MySQL app user: `portfolio` / `secret`
- DB name: `portfolio_v2`

## Usage

```bash
# Start everything (build on first run)
docker compose up -d --build

# Status / logs
docker compose ps
docker compose logs -f backend
docker compose logs -f frontend

# Stop (keeps data)
docker compose down

# Stop + WIPE database/volumes (re-import SQL on next up)
docker compose down -v

# Run artisan inside the backend container
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan tinker
```

## Important: re-importing the SQL

The dump is imported **only on first boot**, when the `mysql_data` volume is empty.
To re-import (e.g. after replacing the `.sql` file):

```bash
docker compose down -v        # drops the mysql_data volume
docker compose up -d          # fresh import
```

## Config files

- `docker-compose.yml` — service definitions (root)
- `docker/php/Dockerfile` — PHP 8.2-fpm image (pdo_mysql, gd, intl, zip, bcmath, exif…)
- `docker/php/entrypoint.sh` — composer install + key:generate + storage:link on boot
- `docker/nginx/default.conf` — serves `backend/public`, proxies PHP to `backend:9000`
- `backend/.env` — points DB to the `mysql` service
- `frontend/.env.development.local` — points the browser API base to `http://localhost:8000/api`

## Notes

- No `php artisan migrate` is run — the schema+data come entirely from the imported dump.
  If you later need to apply newer Laravel migrations, run
  `docker compose exec backend php artisan migrate` manually.
- Mail uses the `log` driver (no real SMTP) — emails land in `backend/storage/logs/laravel.log`.
- Sessions/cache/queue use the `database` driver (those tables exist in the dump).
