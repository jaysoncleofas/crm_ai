#!/bin/sh
set -e

# Only the app container should boot caches and schema; queue/scheduler override entrypoint.
if [ "${1}" = "php-fpm" ]; then
    php artisan migrate --force --no-interaction
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
