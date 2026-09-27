<?php

namespace App\Services\Billing;

/**
 * Hóa đơn tạm tính của một phiên bàn.
 */
final readonly class BillSummary
{
    /**
     * @param  list<array{name: string, unit_price: int, quantity: int, amount: int}>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotal,
        public int $discount,
        public int $serviceChargePercent,
        public int $serviceCharge,
        public int $vatPercent,
        public int $vat,
        public int $total,
    ) {}
}
