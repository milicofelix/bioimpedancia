#!/usr/bin/env sh
set -eu

echo "== Ricosty production check =="

run_composer() {
    if command -v composer >/dev/null 2>&1; then
        composer "$@"
    else
        docker compose exec -T app composer "$@"
    fi
}

run_npm() {
    if command -v npm >/dev/null 2>&1; then
        npm "$@"
    else
        docker compose exec -T frontend_dev npm "$@"
    fi
}

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
run_composer audit
run_npm audit
run_npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Production check finished."
