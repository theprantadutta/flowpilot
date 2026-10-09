#!/bin/sh
# Prepares a FlowPilot container, then runs its command (php-fpm, a queue
# worker, the scheduler or a one-off artisan command).
set -eu

cd /var/www/html

# FPM pool size; see docker/php/fpm-pool.conf.
export FPM_MAX_CHILDREN="${FPM_MAX_CHILDREN:-16}"

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set. Generate one with: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

# The storage volume starts empty on a new server.
mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs

# Cache configuration, routes, views and events for this container's
# environment. Each container has its own cache, so this is safe to run in all
# of them at once.
php artisan optimize --no-interaction --quiet

exec "$@"
