#!/bin/sh
# Chạy khi container khởi động (app / horizon / scheduler / reverb dùng chung image).
# Env lấy từ env_file của compose; cache config ngay tại container này.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY chưa được đặt. Tạo bằng: docker compose -f compose.prod.yaml run --rm app php artisan key:generate --show" >&2
    exit 1
fi

export PHP_FPM_MAX_CHILDREN="${PHP_FPM_MAX_CHILDREN:-20}"

php artisan optimize --quiet
php artisan filament:optimize --quiet

if [ "${CONTAINER_ROLE:-}" = "app" ] && [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    php artisan migrate --force --isolated
fi

exec "$@"
