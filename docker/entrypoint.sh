#!/bin/sh
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "AgriGuard: set APP_KEY in Render (run locally: php artisan key:generate --show)." >&2
  exit 1
fi

PORT="${PORT:-10000}"

php artisan migrate --force --no-interaction
php artisan storage:link --force --no-interaction 2>/dev/null || true

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec php artisan serve --no-reload --host=0.0.0.0 --port="$PORT"
