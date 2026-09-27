<?php

namespace App\Models;

use App\Enums\BankTransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tiền vào tài khoản nhận qua webhook ngân hàng (SePay).
 */
#[Fillable([
    'provider', 'provider_id', 'account_number', 'amount', 'content', 'reference_code', 'transacted_at',
    'status', 'note', 'table_session_id', 'invoice_id', 'handled_by', 'payload',
])]
class BankTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BankTransactionStatus::class,
            'amount' => 'integer',
            'transacted_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
