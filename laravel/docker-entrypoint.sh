#!/bin/sh

# Ждем, пока база данных станет доступна (опционально, но полезно)
# sleep 5 

# Запускаем миграции
echo "Running migrations..."
php artisan migrate --force

# Запускаем оригинальную команду контейнера (которая указана в Dockerfile)
exec "$@"