#!/usr/bin/env sh
set -eu

echo "== Ricosty production check =="

if [ ! -f .env ]; then
    echo "Missing .env. Copy .env.example and configure production values."
    exit 1
fi

php artisan about --only=environment
php artisan config:clear
php artisan route:clear
php artisan view:clear

./vendor/bin/pint --test
php artisan test

if command -v npm >/dev/null 2>&1; then
    npm run build
else
    docker compose exec -T frontend_dev npm run build
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Production check finished."
