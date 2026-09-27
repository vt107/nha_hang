<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Bàn ăn. Không có cột status: trống / có khách / chờ thanh toán suy ra từ phiên đang mở (openSession).
 */
#[Fillable(['area_id', 'code', 'name', 'capacity', 'sort_order', 'is_active'])]
#[Hidden(['qr_token'])]
class DiningTable extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $table) {
            $table->qr_token ??= static::newQrToken();
        });
    }

    public static function newQrToken(): string
    {
        return Str::random(32);
    }

    /** Đổi mã QR (khi QR cũ bị lộ); QR đã in trước đó hết hiệu lực. */
    public function regenerateQrToken(): void
    {
        $this->forceFill(['qr_token' => static::newQrToken()])->save();
    }

    /** Luôn dựng từ APP_URL (không theo host đang truy cập) để QR in ra dùng được trên điện thoại khách. */
    public function qrUrl(): string
    {
        return rtrim(config('app.url'), '/').'/t/'.$this->qr_token;
    }

    public function displayName(): string
    {
        return $this->name ?: 'Bàn '.$this->code;
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return HasMany<TableSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /**
     * Phiên chưa đóng (DB đảm bảo tối đa 1).
     *
     * @return HasOne<TableSession, $this>
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(TableSession::class)->whereNull('closed_at');
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true);
    }
}
