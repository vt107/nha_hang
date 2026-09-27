<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceRequestStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Done => 'Đã xử lý',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Done => 'success',
        };
    }
}
