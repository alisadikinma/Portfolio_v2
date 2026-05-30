#!/bin/sh
set -e

cd /var/www/backend

# Install PHP deps if vendor is empty (named volume persists across restarts)
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing composer dependencies..."
  composer install --no-interaction --prefer-dist --no-progress
fi

# Ensure .env exists
if [ ! -f .env ]; then
  echo "[entrypoint] No .env found, copying .env.example"
  cp .env.example .env
fi

# Generate APP_KEY if missing
if ! grep -q "^APP_KEY=base64:" .env; then
  echo "[entrypoint] Generating APP_KEY..."
  php artisan key:generate --force
fi

# Storage symlink for public file access
php artisan storage:link 2>/dev/null || true

# Writable permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

# Clear stale caches (DB host/credentials change between environments)
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

echo "[entrypoint] Backend ready. Starting: $*"
exec "$@"
