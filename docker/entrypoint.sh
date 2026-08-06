#!/usr/bin/env sh
set -eu

mkdir -p \
  storage/app/private \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

case "${APP_ENV:-production}" in
  staging|production)
    php artisan app:config-check
    ;;
esac

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

if [ "${APP_ENV:-production}" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec "$@"
