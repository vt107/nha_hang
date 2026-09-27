<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BankTransactionStatus: string implements HasColor, HasLabel
{
    /** Đã tự động ghi nhận thanh toán, đóng bàn. */
    case Applied = 'applied';
    /** Khớp bàn nhưng chưa tự ghi nhận được (thiếu tiền, còn món chưa phục vụ...): chờ nhân viên. */
    case Matched = 'matched';
    /** Không tìm thấy bàn theo nội dung chuyển khoản. */
    case Unmatched = 'unmatched';
    /** Giao dịch tiền ra hoặc không liên quan. */
    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Applied => 'Đã ghi nhận',
            self::Matched => 'Chờ nhân viên',
            self::Unmatched => 'Không khớp bàn',
            self::Ignored => 'Bỏ qua',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Applied => 'success',
            self::Matched => 'warning',
            self::Unmatched => 'danger',
            self::Ignored => 'gray',
        };
    }
}
