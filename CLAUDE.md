# Nhà Hàng

Website nhà hàng **một chi nhánh**: menu QR gọi món tại bàn, màn hình nhân viên / bếp realtime, đặt bàn online, admin. Laravel 13, MySQL 8.4, Redis 7, Filament 5 (admin tại `/admin`), Livewire 4, Laravel Reverb (WebSocket), Horizon (queue).

## Môi trường

Chạy hoàn toàn bằng Docker. PHP trên máy host là 8.0 nên **không** chạy `php` / `composer` trực tiếp trên host (npm thì chạy trên host).

- `make up` / `make down`: nginx (listen 80 trong container, map ra host bằng `APP_PORT`, máy dev = 8090 vì Apache chiếm 80), php-fpm `app`, `horizon`, `scheduler`, `reverb`, MySQL (:3309 trên host), Redis (:6380 trên host)
- `make artisan c="..."`, `make composer c="..."`, `make test`, `make fresh` (migrate:fresh --seed), `make restart` (sau khi sửa job / event)
- `npm run build` / `npm run dev` cho asset Vite (Tailwind 4, Echo)
- Test chạy trên database MySQL riêng `nha_hang_test` (không dùng SQLite: có cột generated + `lockForUpdate`)
- Tài khoản seed (mật khẩu `password`): `admin@nhahang.test`, `manager@nhahang.test`, `waiter@nhahang.test`, `kitchen@nhahang.test`
- Nginx proxy `/app/*`, `/apps/*` sang Reverb (:8080 nội bộ); trình duyệt kết nối WebSocket cùng host / port với web (`VITE_REVERB_*`)

## Màn hình & route

| Ai | URL | Code |
|---|---|---|
| Khách (web) | `/`, `/dat-ban` | `public/home.blade.php`, `Livewire/Site/ReservationForm` |
| Khách tại bàn | `/t/{qr_token}` → `/menu`, `/mon-da-goi`, `/thanh-toan` | `Customer/QrEntryController`, middleware `table.session`, `Livewire/Customer/*` |
| Phục vụ | `/staff`, `/staff/tables/{id}`, `/staff/invoices/{id}/print` | `Livewire/Staff/TableBoard`, `TableDetail` |
| Bếp | `/kitchen` | `Livewire/Kitchen/Board` |
| Admin / quản lý | `/admin` (Filament), `/qr/print` | `app/Filament`, `Admin/QrPrintController` |
| Webhook ngân hàng | `POST /webhooks/sepay` (không CSRF, header `Authorization: Apikey {SEPAY_WEBHOOK_KEY}`) | `Webhook/SePayWebhookController`, `Services/Billing/BankTransferService` |
| Đăng nhập nhân viên | `/login` | `Auth/LoginController` (chuyển trang theo vai trò) |

Realtime: `TableSessionUpdated` (kênh private `staff` + public `table-session.{token}`), `KitchenBoardUpdated` (private `kitchen`), `StaffAlerted` (private `staff`). Livewire nghe bằng `#[On('echo...')]`; toast + tiếng bíp cho nhân viên / bếp qua `window.listenForAlerts()` trong `resources/js/app.js`. Màn hình nhân viên / bếp có `wire:poll.30s` dự phòng khi mất WebSocket.

## Mô hình dữ liệu

```
areas 1─n dining_tables 1─n table_sessions 1─n orders 1─n order_items n─1 menu_items n─1 categories
                               │ 1─n invoices 1─n payments n─1 bank_transactions        │ n─n option_groups 1─n options
                               │        └─1─n order_items (invoice_id: món thuộc hóa đơn nào)
                               └ 1─n service_requests
reservations (n─1 dining_tables, 1─1 table_sessions khi khách đến)
```

- Model bàn là `DiningTable` (bảng `dining_tables`), không đặt tên `Table` để khỏi đụng `Filament\Tables\Table`.
- `dining_tables` **không có cột status**: trống / có khách / chờ thanh toán suy ra từ phiên chưa đóng (`openSession`).
- `table_sessions` = một lượt khách. Mỗi bàn tối đa 1 phiên chưa đóng, DB đảm bảo bằng cột generated `open_table_id` UNIQUE. Tạo phiên phải bắt `UniqueConstraintViolationException` (2 máy quét QR cùng lúc) rồi đọc lại phiên đang mở.
- `orders` = một lần bấm "gọi món"; mọi order của phiên gộp vào **một** `invoices`.
- `order_items` là snapshot tên / giá / tùy chọn lúc gọi: `unit_price` = giá món + tiền tùy chọn, `options` JSON `[{group, name, price_delta}]`. Không đọc giá hiện tại của `menu_items` / `options` để tính tiền.
- `order_items.invoice_id` null = món còn phải thu (scope `unbilled()`). Tách bill một phần số lượng thì tách dòng order_item làm hai.

## Quy ước nghiệp vụ

- **Khách quét QR là mở phiên** (URL `/t/{qr_token}`, token 32 ký tự ngẫu nhiên, admin đổi được khi QR bị lộ). Khách không cần đăng nhập; phiên gắn với session trình duyệt, kênh realtime `table-session.{token}`.
- Order khách gọi qua QR duyệt theo setting `order.confirm_mode` (`OrderConfirmMode`, mặc định `first_order`: nhân viên duyệt order đầu tiên của phiên để chặn order ảo; order sau vào bếp luôn). Order nhân viên gọi hộ không cần duyệt.
- **Giỏ hàng mỗi điện thoại một giỏ**, lưu Redis theo `session token + device_id` (cookie), không lưu DB. Order ghi `device_id` để khách xem "món tôi đã gọi".
- Trạng thái món (`OrderItemStatus`): `pending → queued → cooking → ready → served`, `cancelled` được từ mọi bước trước `served`. Chuyển trạng thái chỉ qua `canTransitionTo()` và ghi cột thời điểm tương ứng (`timestampColumn()`).
- Bếp **không chia trạm**: một màn hình, 3 cột Đơn mới (`queued`) / Đang làm (`cooking`) / Hoàn thành (`ready`), làm theo từng món.
- `menu_items.is_active` = admin ẩn / hiện; `is_available` = còn / hết trong ngày (bếp bật tắt). Menu khách dùng scope `visible()`, gọi món chỉ nhận `orderable()`.
- **Size / topping**: `option_groups` dùng chung nhiều món (pivot `menu_item_option_group`), `min_select >= 1` = bắt buộc, `max_select = 1` = chọn một. Server kiểm tra + tính giá ở `OptionResolver`; giỏ hàng key theo `CartService::lineKey(món, tùy chọn)`. Bảng chọn dùng chung: trait `ConfiguresMenuOptions` + `partials/option-picker`.
- **Thanh toán** qua `BillingService::checkout(session, cashier, list<PaymentLine>, discount, selection)`:
  - `selection` null = thu toàn bộ món còn lại (bắt buộc không còn món đang làm), có giá trị = tách hóa đơn theo món. Phiên đóng khi không còn món `unbilled`.
  - Một hóa đơn nhiều `payments` (chia đều, nửa tiền mặt nửa CK); tổng các khoản phải bằng đúng tổng hóa đơn, khoản cuối để `amount` null = phần còn lại.
  - `cashier` null = hệ thống tự thu (webhook).
- **Hủy hóa đơn** `BillingService::void()`: chỉ admin / quản lý, bắt buộc lý do. `reopen` = gỡ món khỏi hóa đơn + mở lại phiên (bàn phải trống) để thu lại; không reopen = hoàn tiền / miễn phí.
- **Chuyển khoản**: nội dung CK = `TableSession::paymentCode()` (mã phiên bỏ gạch). Webhook SePay ghi `bank_transactions` (unique provider + id, gửi lại không thu 2 lần); đúng số tiền + `bank.auto_confirm` bật + không còn món đang làm thì tự `checkout`, còn lại status `matched` chờ nhân viên bấm "Dùng để thanh toán" hoặc admin gán tay bàn cho giao dịch `unmatched`.
- Tiền là VND, số nguyên. Doanh thu = tổng `invoices.total` có status `paid`, tính theo `paid_at`. Món bán chạy join qua `order_items.invoice_id` (không qua phiên, tránh đếm trùng khi bàn có nhiều hóa đơn).
- Vai trò (`UserRole`): `admin`, `manager` vào Filament; `waiter` dùng màn hình nhân viên (bàn, order, thu tiền); `kitchen` dùng màn hình bếp. Horizon chỉ cho `admin`.

## Quy ước code

- Trạng thái / loại lưu `VARCHAR` + PHP backed enum trong `app/Enums` (implement `HasLabel` / `HasColor` của Filament), không dùng MySQL `ENUM`.
- Model dùng attribute `#[Fillable]` / `#[Hidden]` / `#[Scope]` như skeleton Laravel 13.
- Logic nghiệp vụ nằm trong `app/Services` (vd `Services/Ordering`, `Services/Billing`); Filament, controller, Livewire chỉ gọi vào đó. Thao tác đụng tới tiền / phiên bàn bọc transaction + `lockForUpdate`.
- Realtime: event implement `ShouldBroadcast` trong `app/Events`, kênh khai báo ở `routes/channels.php`.
- Redis: cache (menu dùng tag, xóa khi admin sửa món / bếp báo hết món), session, queue (Horizon), giỏ hàng, rate limit.
- **Không cache object / model**: `cache.serializable_classes = false` (mặc định Laravel 13), object đọc ra thành `__PHP_Incomplete_Class`. Cache mảng thuộc tính rồi hydrate lại (xem `MenuCatalog`). Test chạy cache Redis thật (DB 14) nên sẽ bắt lỗi này.
- Scope trên model dùng `$query->qualifyColumn(...)`: các query join / hasManyThrough (`orderItems`, in QR) sẽ lỗi cột mơ hồ nếu không.
- Lỗi nghiệp vụ ném `App\Exceptions\BusinessException` (thông điệp tiếng Việt); component Livewire nhân viên / bếp gọi service qua `attempt()` (`RunsBusinessActions`) để hiện toast.
- `APP_URL` phải là domain / IP mà điện thoại khách truy cập được: link QR in ra dựng từ `APP_URL`, không theo host đang mở trang admin.
- Cấu hình vận hành trong bảng `settings`, đọc bằng `Setting::get('group.key')`. Secret chỉ để trong `.env`.
- Nhãn hiển thị bằng tiếng Việt.
