<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị',
            self::Manager => 'Quản lý',
            self::Waiter => 'Phục vụ',
            self::Kitchen => 'Bếp',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Manager => 'warning',
            self::Waiter => 'info',
            self::Kitchen => 'success',
        };
    }

    /** Được vào trang quản trị Filament. */
    public function canAccessAdmin(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }
}
