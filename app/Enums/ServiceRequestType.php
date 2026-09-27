<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceRequestType: string implements HasLabel
{
    case CallWaiter = 'call_waiter';
    case RequestBill = 'request_bill';

    public function getLabel(): string
    {
        return match ($this) {
            self::CallWaiter => 'Gọi nhân viên',
            self::RequestBill => 'Yêu cầu thanh toán',
        };
    }
}
