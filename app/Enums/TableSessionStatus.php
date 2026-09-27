<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TableSessionStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case PaymentRequested = 'payment_requested';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Đang phục vụ',
            self::PaymentRequested => 'Chờ thanh toán',
            self::Closed => 'Đã đóng',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::PaymentRequested => 'warning',
            self::Closed => 'gray',
        };
    }
}
