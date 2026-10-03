<?php

namespace Database\Seeders\Demo;

/**
 * Tên / số điện thoại khách hư cấu cho dữ liệu demo (số 09xx 000 xxx không phải thuê bao thật).
 */
final class DemoPeople
{
    private const FAMILY = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý'];

    private const MIDDLE = ['Văn', 'Thị', 'Minh', 'Ngọc', 'Thanh', 'Hoàng', 'Quốc', 'Thu', 'Gia', 'Bảo', 'Đức', 'Hải'];

    private const GIVEN = ['An', 'Bình', 'Châu', 'Dũng', 'Giang', 'Hà', 'Hạnh', 'Hiếu', 'Hùng', 'Khoa', 'Lan', 'Linh', 'Long', 'Mai', 'Nam',
        'Ngân', 'Nhung', 'Phong', 'Phúc', 'Quang', 'Quyên', 'Sơn', 'Tâm', 'Thảo', 'Trang', 'Trung', 'Tuấn', 'Uyên', 'Vy', 'Yến'];

    /**
     * @return array{0: string, 1: string} [họ tên, số điện thoại]
     */
    public static function customer(): array
    {
        $name = self::FAMILY[mt_rand(0, count(self::FAMILY) - 1)].' '
            .self::MIDDLE[mt_rand(0, count(self::MIDDLE) - 1)].' '
            .self::GIVEN[mt_rand(0, count(self::GIVEN) - 1)];

        $phone = '09'.mt_rand(0, 9).'0000'.str_pad((string) mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

        return [$name, $phone];
    }
}
