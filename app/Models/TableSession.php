<?php

namespace App\Models;

use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Một lượt khách ngồi bàn. Mọi order của phiên gộp vào 1 hóa đơn.
 */
#[Fillable([
    'dining_table_id', 'code', 'token', 'status', 'source', 'guest_count', 'reservation_id',
    'opened_by', 'closed_by', 'opened_at', 'closed_at', 'note',
])]
#[Hidden(['token'])]
class TableSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TableSessionStatus::class,
            'source' => TableSessionSource::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** Kênh realtime công khai của phiên (token ngẫu nhiên, khách không đoán được phiên khác). */
    public function broadcastChannel(): string
    {
        return 'table-session.'.$this->token;
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasManyThrough<OrderItem, Order, $this>
     */
    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Order::class);
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * Có thể nhiều hóa đơn khi tách bill theo món.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Mã đưa vào nội dung chuyển khoản, không dấu cách / gạch (ngân hàng hay bỏ ký tự đặc biệt): S260927-7KQ2 → S2609277KQ2. */
    public function paymentCode(): string
    {
        return str_replace('-', '', $this->code);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('closed_at');
    }
}
