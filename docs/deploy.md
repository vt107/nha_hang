# Triển khai production

Toàn bộ hệ thống chạy bằng Docker Compose trên một máy chủ Linux: `compose.prod.yaml`.

| Dịch vụ | Việc |
|---|---|
| `nginx` | Cổng 80 (và 443 khi có SSL), trả asset tĩnh, chuyển PHP sang `app`, WebSocket `/app` sang `reverb` |
| `app` | php-fpm (Laravel) |
| `horizon` | Xử lý queue (broadcast realtime, job) |
| `scheduler` | Lịch chạy định kỳ (`routes/console.php`) |
| `reverb` | WebSocket realtime |
| `mysql`, `redis` | Dữ liệu, cache / session / queue. Không mở cổng ra ngoài |
| `certbot` | Gia hạn chứng chỉ Let's Encrypt mỗi 12 giờ |
| `backup` | Sao lưu database + ảnh upload mỗi ngày vào `./backups` |

Code, thư viện PHP và asset được đóng gói sẵn trong image (`docker/prod/Dockerfile`), không mount source vào container.

## 1. Chuẩn bị máy chủ

- Ubuntu 22.04 / 24.04, tối thiểu 2 vCPU, 2 GB RAM (khuyên dùng 4 GB), 20 GB ổ đĩa.
- Cài Docker: `curl -fsSL https://get.docker.com | sh`
- Mở cổng 80 và 443 trên firewall (`ufw allow 80,443/tcp`).
- Trỏ bản ghi DNS `A` của domain về IP máy chủ.

## 2. Cài đặt lần đầu

```bash
git clone <repo> nha_hang && cd nha_hang
cp .env.production.example .env
nano .env        # điền mọi giá trị <...>: domain, mật khẩu DB / Redis, key Reverb...
```

Tạo `APP_KEY` rồi dán vào `.env`:

```bash
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml run --rm --no-deps app php artisan key:generate --show
```

Khởi động, tạo bảng và tài khoản admin đầu tiên:

```bash
make prod-up
make prod-artisan c="migrate --force"
make prod-artisan c="db:seed --class=SettingSeeder --force"
make prod-artisan c="app:create-admin"       # nhập họ tên, email, mật khẩu
```

Muốn có dữ liệu mẫu (bàn, menu, size/topping) để thử: `make prod-artisan c="db:seed --force"`. Tài khoản mẫu có mật khẩu `password`: **đổi mật khẩu hoặc xóa trước khi dùng thật**.

Lúc này trang đã chạy ở `http://<domain>`.

## 3. Bật HTTPS

Cần `SERVER_NAME` và `LETSENCRYPT_EMAIL` trong `.env`, domain đã trỏ về máy chủ, cổng 80 mở:

```bash
make prod-ssl
```

Lệnh cấp chứng chỉ rồi khởi động lại nginx. Nginx tự chuyển sang cấu hình HTTPS khi thấy chứng chỉ (`docker/prod/nginx/40-select-site.sh`): cổng 80 chuyển hướng sang 443, có HTTP/2 + HSTS. Dịch vụ `certbot` tự gia hạn, nginx tự nạp lại chứng chỉ mỗi 6 giờ.

Sau khi có HTTPS: `APP_URL=https://<domain>`, `SESSION_SECURE_COOKIE=true`, rồi `make prod-up`.

**Chạy sau Cloudflare / load balancer** (SSL kết thúc ở proxy): bỏ qua `make prod-ssl`, nginx chạy cấu hình HTTP cổng 80. Đặt `TRUSTED_PROXIES=*` và `APP_URL=https://<domain>` để Laravel lấy đúng IP khách (rate limit) và sinh link `https`. Với Cloudflare, bật WebSockets và chọn SSL mode "Flexible" (hoặc "Full" nếu đã có chứng chỉ trên máy chủ).

## 4. Cấu hình trong trang quản trị

Đăng nhập `https://<domain>/admin`:

1. **Cài đặt**: tên nhà hàng, VAT / phí phục vụ, ngân hàng + số tài khoản nhận tiền (VietQR).
2. **Khu vực**, **Bàn & mã QR**: tạo bàn, bấm "In QR tất cả bàn" để in và dán lên bàn.
3. **Danh mục**, **Món ăn**, **Size / topping**.
4. **Nhân viên**: tạo tài khoản phục vụ (`/staff`) và bếp (`/kitchen`).

Mã QR in ra trỏ về `APP_URL`: đổi domain thì phải in lại QR.

## 5. Tự xác nhận chuyển khoản (SePay, không bắt buộc)

1. Trên my.sepay.vn → Tích hợp webhook: URL `https://<domain>/webhooks/sepay`, kiểu chứng thực **API Key**.
2. Đặt cùng key vào `.env`: `SEPAY_WEBHOOK_KEY=...`, rồi `make prod-up`.
3. Admin → Cài đặt → bật "Tự xác nhận chuyển khoản qua webhook SePay".

Giao dịch nhận được xem ở Admin → Báo cáo → Chuyển khoản.

## 6. Cập nhật phiên bản mới

```bash
./deploy.sh        # hoặc make prod-deploy
```

Script: kéo code mới, build image, sao lưu database, khởi động lại, chạy migrate, khởi động lại Horizon / Reverb / scheduler.

## 7. Sao lưu & khôi phục

- Tự động mỗi ngày lúc `BACKUP_TIME` (mặc định 03:00), giữ `BACKUP_KEEP_DAYS` ngày (mặc định 14), trong `BACKUP_DIR` (mặc định `./backups`):
  - `backups/db/nha_hang-YYYYMMDD-HHMMSS.sql.gz`: database
  - `backups/uploads/uploads-*.tar.gz`: ảnh món / danh mục
- Sao lưu ngay: `make prod-backup`
- Khôi phục database: `make prod-restore file=nha_hang-20260928-030000.sql.gz` (ghi đè dữ liệu hiện tại, xóa cache sau khi khôi phục).
- Khôi phục ảnh: `docker compose -f compose.prod.yaml run --rm --entrypoint sh -v "$PWD/backups:/backups" app -c "tar -xzf /backups/uploads/<file> -C /var/www/html/storage/app"`

Thư mục `backups` nằm trên cùng máy chủ: **nên đồng bộ thêm ra ngoài** (rclone lên Google Drive / S3, hoặc snapshot của nhà cung cấp VPS).

## 8. Vận hành

| Việc | Lệnh |
|---|---|
| Trạng thái dịch vụ | `make prod-ps` |
| Xem log | `make prod-logs` (log Laravel: `storage/logs/laravel-*.log` trong volume `storage`) |
| Lệnh artisan | `make prod-artisan c="..."` |
| Bảo trì | `make prod-artisan c="down"` / `c="up"` |
| Theo dõi queue | `https://<domain>/horizon` (chỉ tài khoản admin) |

## 9. Kiểm tra stack production trên máy dev

```bash
cp .env.production.example .env.prodtest   # điền giá trị thử, HTTP_PORT=8091, HTTPS_PORT=8443, APP_URL=http://localhost:8091
export APP_ENV_FILE=.env.prodtest
docker compose -p nha_hang_prod --env-file .env.prodtest -f compose.prod.yaml up -d --build
# ...
docker compose -p nha_hang_prod --env-file .env.prodtest -f compose.prod.yaml down -v
```
