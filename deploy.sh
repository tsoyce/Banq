#!/usr/bin/env bash
# A11 · Деплой на боевой сервер BrainyCP. Запуск на сервере из папки проекта:
#   cd /home/tsoy/sites/banqofbank && bash deploy.sh
# Первый запуск: после него открыть сайт в браузере → мастер установки.
set -euo pipefail
cd "$(dirname "$0")"

PHP_BIN="${PHP_BIN:-php}"          # в BrainyCP бывает /opt/php83/bin/php
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

echo "→ git pull"
git pull --ff-only

echo "→ composer install"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "→ права на запись"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache public/uploads
chmod -R ug+rwX storage bootstrap/cache public/uploads
[ -f .env ] && chmod ug+rw .env

if [ -f storage/app/installed.json ]; then
    echo "→ миграции и кеши"
    "$PHP_BIN" artisan migrate --force
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache
else
    echo "→ сайт ещё не установлен: открой его в браузере, запустится мастер установки"
fi

echo "✓ готово"
