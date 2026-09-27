<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;

/**
 * Một khoản khách trả cho hóa đơn. amount = null: phần còn lại của hóa đơn (chỉ dòng cuối).
 */
final readonly class PaymentLine
{
    public function __construct(
        public PaymentMethod $method,
        public ?int $amount = null,
        public ?int $received = null,
        public ?string $reference = null,
        public ?int $bankTransactionId = null,
    ) {}

    public static function cash(?int $amount = null, ?int $received = null): self
    {
        return new self(PaymentMethod::Cash, $amount, $received);
    }

    public static function transfer(?int $amount = null, ?string $reference = null, ?int $bankTransactionId = null): self
    {
        return new self(PaymentMethod::BankTransfer, $amount, reference: $reference, bankTransactionId: $bankTransactionId);
    }
}
