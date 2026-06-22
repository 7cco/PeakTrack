#!/bin/sh
set -e

rm -rf storage/framework/views/* storage/framework/cache/data/* bootstrap/cache/*.php 2>/dev/null || true

[ -f .env ] || cp .env.example .env

[ -d vendor ] || composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

grep -q '^APP_KEY=base64' .env || php artisan key:generate

echo "Running migrations..."
php artisan migrate --force

php artisan db:seed --class=DemoUserSeeder
# Запускаем оригинальную команду контейнера (которая указана в Dockerfile)
exec "$@"