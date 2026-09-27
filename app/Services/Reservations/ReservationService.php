<?php

namespace App\Services\Reservations;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\TableSessionSource;
use App\Events\StaffAlerted;
use App\Exceptions\BusinessException;
use App\Models\Reservation;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Tables\TableSessionService;
use App\Support\Code;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(private TableSessionService $sessions) {}

    /**
     * @param  array{customer_name: string, customer_phone: string, customer_email?: ?string, party_size: int, reserved_at: mixed, note?: ?string}  $data
     */
    public function createFromWeb(array $data): Reservation
    {
        $reservation = Reservation::create([
            ...$data,
            'code' => Code::make('R'),
            'status' => ReservationStatus::Pending,
            'source' => ReservationSource::Web,
        ]);

        event(new StaffAlerted("Đặt bàn mới: {$reservation->customer_name}, {$reservation->party_size} người lúc {$reservation->reserved_at->format('H:i d/m')}"));

        return $reservation;
    }

    public function updateStatus(Reservation $reservation, ReservationStatus $status, User $by): Reservation
    {
        $reservation->update(['status' => $status, 'handled_by' => $by->id]);

        return $reservation;
    }

    /** Khách đặt bàn đã đến: mở phiên trên bàn đã gán. */
    public function seat(Reservation $reservation, User $by): TableSession
    {
        if (! in_array($reservation->status, ReservationStatus::upcoming(), true)) {
            throw new BusinessException('Đặt bàn này không còn hiệu lực.');
        }

        if (! $reservation->diningTable) {
            throw new BusinessException('Hãy gán bàn cho đặt bàn trước khi xếp khách vào.');
        }

        if ($reservation->diningTable->openSession()->exists()) {
            throw new BusinessException("{$reservation->diningTable->displayName()} đang có khách.");
        }

        return DB::transaction(function () use ($reservation, $by) {
            $session = $this->sessions->openForTable(
                $reservation->diningTable,
                TableSessionSource::Reservation,
                $by,
                $reservation,
                $reservation->party_size,
            );

            $reservation->update(['status' => ReservationStatus::Seated, 'handled_by' => $by->id]);

            return $session;
        });
    }
}
