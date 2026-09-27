<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sinh nội dung VietQR (chuẩn EMVCo / NAPAS 247) để khách quét chuyển khoản bằng app ngân hàng.
 * Không gọi dịch vụ bên ngoài; nhân viên tự kiểm tra tiền về và xác nhận.
 */
final class VietQr
{
    /** BIN ngân hàng theo NAPAS. */
    public const BANKS = [
        '970436' => 'Vietcombank',
        '970415' => 'VietinBank',
        '970418' => 'BIDV',
        '970405' => 'Agribank',
        '970407' => 'Techcombank',
        '970422' => 'MB Bank',
        '970416' => 'ACB',
        '970432' => 'VPBank',
        '970423' => 'TPBank',
        '970403' => 'Sacombank',
        '970441' => 'VIB',
        '970437' => 'HDBank',
        '970443' => 'SHB',
        '970448' => 'OCB',
        '970426' => 'MSB',
        '970431' => 'Eximbank',
        '970440' => 'SeABank',
        '970449' => 'LPBank',
        '970428' => 'Nam A Bank',
    ];

    public static function payload(string $bankBin, string $accountNumber, ?int $amount = null, ?string $description = null): string
    {
        $beneficiary = self::field('00', $bankBin).self::field('01', $accountNumber);

        $merchant = self::field('00', 'A000000727')
            .self::field('01', $beneficiary)
            .self::field('02', 'QRIBFTTA');

        $payload = self::field('00', '01')
            .self::field('01', $amount ? '12' : '11')
            .self::field('38', $merchant)
            .self::field('53', '704')
            .($amount ? self::field('54', (string) $amount) : '')
            .self::field('58', 'VN');

        if ($description = self::cleanDescription($description)) {
            $payload .= self::field('62', self::field('08', $description));
        }

        $payload .= '6304';

        return $payload.self::crc16($payload);
    }

    /** Nội dung chuyển khoản: không dấu, chỉ chữ số / chữ cái / khoảng trắng, tối đa 25 ký tự. */
    public static function cleanDescription(?string $description): string
    {
        $ascii = Str::upper(Str::ascii((string) $description));

        return Str::limit(trim(preg_replace('/[^A-Z0-9 ]+/', ' ', $ascii)), 25, '');
    }

    /** CRC-16/CCITT-FALSE, trả về 4 ký tự hex in hoa. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        foreach (str_split($data) as $char) {
            $crc ^= ord($char) << 8;

            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    private static function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }
}
