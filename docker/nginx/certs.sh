#!/bin/sh
set -e

# Путь к директории с SSL сертификатами (относительно расположения скрипта)
CERT_DIR="$(dirname "$0")/ssl"
mkdir -p "$CERT_DIR"

# Проверяем существование обоих сертификатов
if [ -f "$CERT_DIR/laravel.crt" ] && [ -f "$CERT_DIR/fastapi.crt" ]; then
  echo "Сертификаты уже существуют, пропускаю генерацию"
  exit 0
fi

echo "Генерация SSL сертификатов..."

# Генерация сертификата для Laravel (track)
echo "Создание сертификата для Laravel (localhost)..."
openssl req -x509 -nodes -newkey rsa:2048 \
  -keyout "$CERT_DIR/laravel.key" \
  -out    "$CERT_DIR/laravel.crt" \
  -days 825 \
  -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,DNS:www.localhost"

# Генерация сертификата для FastAPI (track-api)
echo "Создание сертификата для FastAPI (api.localhost)..."
openssl req -x509 -nodes -newkey rsa:2048 \
  -keyout "$CERT_DIR/fastapi.key" \
  -out    "$CERT_DIR/fastapi.crt" \
  -days 825 \
  -subj "/CN=api.localhost" \
  -addext "subjectAltName=DNS:api.localhost,DNS:localhost"

echo "Готово! Сертификаты созданы в директории: $CERT_DIR"
echo ""
echo "Созданные файлы:"
ls -la "$CERT_DIR"/*.crt "$CERT_DIR"/*.key 2>/dev/null || true