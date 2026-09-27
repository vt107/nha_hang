<?php

namespace App\Support;

final class Money
{
    /** 65000 → "65.000 ₫" */
    public static function format(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' ₫';
    }
}
