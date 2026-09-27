<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationSource: string implements HasLabel
{
    case Web = 'web';
    case Phone = 'phone';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Website',
            self::Phone => 'Gọi điện',
            self::Staff => 'Nhân viên tạo',
        };
    }
}
