<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Seated = 'seated';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Seated => 'Đã đến',
            self::Cancelled => 'Đã hủy',
            self::NoShow => 'Không đến',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Seated => 'success',
            self::Cancelled => 'gray',
            self::NoShow => 'danger',
        };
    }

    /**
     * Đặt bàn còn hiệu lực, giữ bàn.
     *
     * @return list<self>
     */
    public static function upcoming(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
