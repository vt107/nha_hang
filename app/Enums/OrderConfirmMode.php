<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Setting "order.confirm_mode": order khách gọi qua QR có cần nhân viên duyệt không. */
enum OrderConfirmMode: string implements HasLabel
{
    case FirstOrder = 'first_order';
    case Always = 'always';
    case Never = 'never';

    public function getLabel(): string
    {
        return match ($this) {
            self::FirstOrder => 'Chỉ duyệt order đầu tiên của phiên',
            self::Always => 'Duyệt mọi order',
            self::Never => 'Không cần duyệt, vào bếp ngay',
        };
    }
}
