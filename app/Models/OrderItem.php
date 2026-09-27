<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * item_name / unit_price là snapshot lúc gọi: không đọc giá hiện tại của menu_items để tính tiền.
 */
#[Fillable([
    'order_id', 'invoice_id', 'menu_item_id', 'item_name', 'unit_price', 'options', 'quantity', 'note', 'status',
    'cancel_reason', 'cancelled_by', 'queued_at', 'cooking_at', 'ready_at', 'served_at', 'cancelled_at',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderItemStatus::class,
            'unit_price' => 'integer',
            'options' => 'array',
            'quantity' => 'integer',
            'queued_at' => 'datetime',
            'cooking_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<int, never>
     */
    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn () => $this->unit_price * $this->quantity);
    }

    /** "Trà sữa (Size L, Trân châu)" */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->options
            ? $this->item_name.' ('.collect($this->options)->pluck('name')->join(', ').')'
            : $this->item_name);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    #[Scope]
    protected function billable(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), OrderItemStatus::billable());
    }

    /** Món tính tiền chưa nằm trong hóa đơn nào (còn phải thu). */
    #[Scope]
    protected function unbilled(Builder $query): void
    {
        $query->billable()->whereNull($query->qualifyColumn('invoice_id'));
    }

    #[Scope]
    protected function onKitchenBoard(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), OrderItemStatus::kitchenBoard())->orderBy($query->qualifyColumn('queued_at'));
    }
}
