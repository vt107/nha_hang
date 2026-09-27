<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Mã hiển thị cho người dùng: {prefix}{yymmdd}-{4 ký tự}, ví dụ S260927-7KQ2.
 */
final class Code
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function make(string $prefix): string
    {
        $suffix = '';

        for ($i = 0; $i < 4; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $prefix.now()->format('ymd').'-'.$suffix;
    }

    public static function token(): string
    {
        return Str::random(32);
    }
}
