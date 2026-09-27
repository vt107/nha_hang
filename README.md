# Nhà Hàng: Website + Menu QR + Đặt bàn

Laravel 13 · MySQL 8.4 · Redis 7 · Filament 5 · Reverb · Horizon · Nginx (listen 80)

## Chạy lần đầu

```bash
cp .env.example .env          # sửa APP_PORT / VITE_REVERB_PORT nếu cần (production: 80)
make build && make up
make composer c="install"
make artisan c="key:generate"
make fresh                    # migrate + seed dữ liệu mẫu
make artisan c="storage:link"
npm install && npm run build
```

- Web: http://localhost:8090 · Admin: http://localhost:8090/admin (`admin@nhahang.test` / `password`) · Horizon: `/horizon`
- Test: `make test`

Quy ước nghiệp vụ và code: xem [CLAUDE.md](CLAUDE.md).
