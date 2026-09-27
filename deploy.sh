#!/usr/bin/env bash
# Cập nhật bản mới trên máy chủ production: ./deploy.sh
set -euo pipefail

cd "$(dirname "$0")"
COMPOSE="docker compose -f compose.prod.yaml"

echo "==> Lấy code mới"
git pull --ff-only

echo "==> Build image"
$COMPOSE build --pull

echo "==> Sao lưu database trước khi migrate"
$COMPOSE run --rm backup once || echo "(Bỏ qua: chưa có database để sao lưu)"

echo "==> Khởi động lại dịch vụ"
$COMPOSE up -d --remove-orphans

echo "==> Migrate database"
$COMPOSE exec -T app php artisan migrate --force

echo "==> Khởi động lại worker để nhận code mới"
$COMPOSE exec -T horizon php artisan horizon:terminate || true
$COMPOSE restart reverb scheduler

docker image prune -f >/dev/null
$COMPOSE ps
echo "==> Xong"
