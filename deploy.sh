#!/usr/bin/env bash
# A11 · Деплой на боевой сервер BrainyCP. Запуск на сервере из папки проекта:
#   cd /home/tsoy/sites/banqofbank && bash deploy.sh
# Первый запуск: после него открыть сайт в браузере → мастер установки.
set -euo pipefail
cd "$(dirname "$0")"

# A19 · В BrainyCP php может не быть в PATH: ищем PHP 8.3/8.4 в типичных местах панели.
if [ -z "${PHP_BIN:-}" ]; then
    for p in php /usr/bin/php83/bin/php /usr/bin/php84/bin/php /opt/php83/bin/php; do
        if command -v "$p" >/dev/null 2>&1; then PHP_BIN="$p"; break; fi
    done
fi
PHP_BIN="${PHP_BIN:?не найден PHP — задай PHP_BIN=/путь/к/php}"
COMPOSER_BIN="${COMPOSER_BIN:-$PHP_BIN $(command -v composer || echo /usr/local/bin/composer)}"
# A19 · Кеш config/route/view хранит абсолютные пути. Если PHP-FPM сайта работает в chroot
# (BrainyCP: chroot = /home/tsoy, сайт видит /sites/...), кеш из консоли ломает сайт.
# Поэтому по умолчанию кеши только очищаются; ARTISAN_CACHE=1 — включить кеширование на сервере без chroot.
ARTISAN_CACHE="${ARTISAN_CACHE:-0}"

echo "→ git pull"
git pull --ff-only

echo "→ composer install"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

echo "→ права на запись"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache public/uploads
chmod -R ug+rwX storage bootstrap/cache public/uploads
[ -f .env ] && chmod ug+rw .env

if [ -f storage/app/installed.json ]; then
    echo "→ миграции и кеши"
    "$PHP_BIN" artisan migrate --force
    if [ "$ARTISAN_CACHE" = "1" ]; then
        "$PHP_BIN" artisan config:cache
        "$PHP_BIN" artisan route:cache
        "$PHP_BIN" artisan view:cache
    else
        "$PHP_BIN" artisan optimize:clear
    fi
else
    echo "→ сайт ещё не установлен: открой его в браузере, запустится мастер установки"
fi

echo "✓ готово"
