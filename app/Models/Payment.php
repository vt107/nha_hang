<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một khoản tiền nhân viên đã xác nhận nhận được (tiền mặt hoặc chuyển khoản).
 */
#[Fillable(['invoice_id', 'method', 'amount', 'received_amount', 'reference', 'note', 'confirmed_by', 'confirmed_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'received_amount' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function changeAmount(): int
    {
        return max(0, ($this->received_amount ?? $this->amount) - $this->amount);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
