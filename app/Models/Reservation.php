<?php

namespace App\Models;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'code', 'customer_name', 'customer_phone', 'customer_email', 'party_size', 'reserved_at',
    'duration_minutes', 'dining_table_id', 'status', 'source', 'note', 'internal_note', 'handled_by',
])]
class Reservation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
            'reserved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return HasOne<TableSession, $this>
     */
    public function tableSession(): HasOne
    {
        return $this->hasOne(TableSession::class);
    }

    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), ReservationStatus::upcoming());
    }
}
