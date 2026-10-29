#!/bin/sh
set -eu

# Dev container with a freshly cloned, bind-mounted source: install dependencies once.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

# Compose starts the app only once postgres is healthy; migrations are idempotent.
php artisan migrate --force --no-interaction --quiet

exec "$@"
