<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Khách bấm "Gọi nhân viên" / "Thanh toán" trên trang QR.
 */
#[Fillable(['table_session_id', 'type', 'status', 'note', 'handled_by', 'handled_at'])]
class ServiceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ServiceRequestType::class,
            'status' => ServiceRequestStatus::class,
            'handled_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
