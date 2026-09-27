<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderSource: string implements HasLabel
{
    case Qr = 'qr';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Qr => 'Khách gọi qua QR',
            self::Staff => 'Nhân viên gọi hộ',
        };
    }
}
