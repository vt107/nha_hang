<?php

/*
|--------------------------------------------------------------------------
| Chế độ demo (chỉ xem)
|--------------------------------------------------------------------------
|
| Bật DEMO_MODE=true khi deploy bản giới thiệu cho khách xem:
| - Mọi lệnh ghi SQL phát sinh từ request web bị chặn (App\Support\Demo\DemoMode),
|   trừ các bảng hạ tầng trong `writable_tables` và các câu lệnh khớp `allowed_write_patterns`.
| - Form POST / PUT / PATCH / DELETE bị chặn trước khi vào controller, trừ `allowed_routes`.
| - Nút "Demo" nổi ở góc màn hình liệt kê các khu vực + tài khoản đăng nhập sẵn (`portals`).
| - `php artisan demo:reset` dựng lại database với dữ liệu mẫu (lịch chạy hằng ngày).
|
| Lệnh artisan / queue / scheduler / test (chạy CLI) không bị chặn.
|
*/

// qr_token cố định (32 ký tự) của bàn demo cho khu vực "Khách tại bàn".
$qrTableToken = 'DemoBanA03KhachTaiBanGoiMonQR026';

return [

    'enabled' => (bool) env('DEMO_MODE', false),

    'message' => 'Đây là bản demo chỉ xem: thao tác thêm / sửa / xoá đã được tắt.',

    // Giờ dựng lại dữ liệu demo mỗi ngày (múi giờ app). null = không tự reset.
    // Dự án này nhận nhiều mốc cách nhau dấu phẩy (routes/console.php), vd "04:00,11:00,17:00".
    'reset_at' => env('DEMO_RESET_AT', '04:00'),

    // Bảng hạ tầng vẫn cho ghi (phiên đăng nhập, cache, rate limit).
    'writable_tables' => [
        'sessions',
        'cache',
        'cache_locks',
    ],

    // Regex (so với câu SQL) cho phép ghi dù bảng không nằm trong writable_tables.
    'allowed_write_patterns' => [
        // "Ghi nhớ đăng nhập"
        '/^update [`"]?users[`"]? set [`"]?remember_token[`"]? = \?/i',
    ],

    // Route (tên hoặc pattern path) được phép nhận POST / PUT / PATCH / DELETE.
    // Request Livewire (livewire.update) luôn đi qua, lệnh ghi bên trong do guard SQL chặn.
    'allowed_routes' => [
        'login',
        'logout',
        'filament.admin.auth.logout',
        // Xác thực kênh realtime private (staff, kitchen) của Reverb: chỉ đọc.
        'broadcasting/auth',
    ],

    // DemoSeeder gán token này cho bàn A03 và luôn để bàn có phiên đang mở (vào /t/{token} không phải tạo phiên).
    'qr_table_token' => $qrTableToken,

    /*
     * Các khu vực hiển thị trong nút Demo.
     * url / login_url là path tương đối (qua helper url()).
     * accounts[].key là duy nhất toàn file: /login?demo=<key> sẽ điền sẵn tài khoản đó.
     */
    'portals' => [
        [
            'label' => 'Trang quản trị',
            'description' => 'Tổng quan doanh thu, báo cáo món bán chạy, thực đơn, bàn & mã QR, đặt bàn, hóa đơn, chuyển khoản, nhân viên, cài đặt.',
            'url' => '/admin',
            'login_url' => '/admin/login',
            'accounts' => [
                ['key' => 'admin', 'role' => 'Quản trị viên', 'email' => 'admin@nhahang.test', 'password' => 'password'],
            ],
        ],
        [
            'label' => 'Quản lý nhà hàng',
            'description' => 'Trang quản trị với quyền quản lý: vận hành, báo cáo, hủy hóa đơn (không vào được Cài đặt hệ thống).',
            'url' => '/admin',
            'login_url' => '/admin/login',
            'accounts' => [
                ['key' => 'manager', 'role' => 'Quản lý', 'email' => 'manager@nhahang.test', 'password' => 'password'],
            ],
        ],
        [
            'label' => 'Màn hình phục vụ',
            'description' => 'Sơ đồ bàn realtime: bàn có khách, order chờ duyệt, món chờ bưng, khách gọi thanh toán; thu tiền, tách hóa đơn, in hóa đơn.',
            'url' => '/staff',
            'login_url' => '/login',
            'accounts' => [
                ['key' => 'waiter', 'role' => 'Nhân viên phục vụ', 'email' => 'waiter@nhahang.test', 'password' => 'password'],
            ],
        ],
        [
            'label' => 'Màn hình bếp',
            'description' => 'Bảng 3 cột Đơn mới / Đang làm / Hoàn thành theo từng món, tổng số phần cần làm, báo hết món.',
            'url' => '/kitchen',
            'login_url' => '/login',
            'accounts' => [
                ['key' => 'kitchen', 'role' => 'Đầu bếp', 'email' => 'kitchen@nhahang.test', 'password' => 'password'],
            ],
        ],
        [
            'label' => 'Khách tại bàn (quét QR)',
            'description' => 'Như khách quét mã QR trên bàn A03: xem menu, chọn size / topping, giỏ hàng, món đã gọi, trang thanh toán VietQR. Không cần đăng nhập.',
            'url' => '/t/'.$qrTableToken,
            'accounts' => [],
        ],
        [
            'label' => 'Website nhà hàng',
            'description' => 'Trang chủ công khai: giới thiệu, thực đơn, liên hệ.',
            'url' => '/',
            'accounts' => [],
        ],
        [
            'label' => 'Đặt bàn online',
            'description' => 'Form đặt bàn của khách (gửi form bị tắt ở bản demo).',
            'url' => '/dat-ban',
            'accounts' => [],
        ],
    ],

];
