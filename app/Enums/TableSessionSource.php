<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TableSessionSource: string implements HasLabel
{
    case Qr = 'qr';
    case Staff = 'staff';
    case Reservation = 'reservation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Qr => 'Khách quét QR',
            self::Staff => 'Nhân viên mở',
            self::Reservation => 'Từ đặt bàn',
        };
    }
}
