<?php

namespace App\Services\Tables;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Events\TableSessionUpdated;
use App\Exceptions\BusinessException;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Code;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TableSessionService
{
    /**
     * Lấy phiên đang mở của bàn, chưa có thì mở mới. An toàn khi nhiều máy quét QR cùng lúc:
     * DB chỉ cho 1 phiên chưa đóng mỗi bàn (open_table_id UNIQUE), máy chậm hơn sẽ đọc lại phiên vừa tạo.
     */
    public function openForTable(
        DiningTable $table,
        TableSessionSource $source,
        ?User $openedBy = null,
        ?Reservation $reservation = null,
        ?int $guestCount = null,
    ): TableSession {
        if (! $table->is_active) {
            throw new BusinessException('Bàn này đang tạm ngưng phục vụ.');
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($existing = $table->openSession()->first()) {
                return $existing;
            }

            try {
                $session = TableSession::create([
                    'dining_table_id' => $table->id,
                    'code' => Code::make('S'),
                    'token' => Code::token(),
                    'status' => TableSessionStatus::Open,
                    'source' => $source,
                    'guest_count' => $guestCount,
                    'reservation_id' => $reservation?->id,
                    'opened_by' => $openedBy?->id,
                    'opened_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            event(new TableSessionUpdated(
                $session,
                $source === TableSessionSource::Qr ? "{$table->displayName()} có khách mới (quét QR)" : null,
            ));

            return $session;
        }

        throw new BusinessException('Không mở được bàn, vui lòng thử lại.');
    }

    /** Chuyển khách sang bàn khác (bàn đích phải trống). */
    public function transfer(TableSession $session, DiningTable $to): TableSession
    {
        $from = $session->diningTable;

        try {
            DB::transaction(function () use ($session, $to) {
                $session = TableSession::query()->lockForUpdate()->findOrFail($session->id);

                if (! $session->isOpen()) {
                    throw new BusinessException('Phiên bàn đã đóng.');
                }

                $session->update(['dining_table_id' => $to->id]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException("{$to->displayName()} đang có khách.");
        }

        $session->refresh();
        event(new TableSessionUpdated($session, "Chuyển khách từ {$from->displayName()} sang {$to->displayName()}"));

        return $session;
    }

    /**
     * Đóng phiên không thu thêm tiền: khách quét nhầm / bỏ về, hoặc mọi món đã nằm trong các hóa đơn tách.
     * Order chờ duyệt bị từ chối luôn.
     */
    public function closeWithoutPayment(TableSession $session, User $by): void
    {
        DB::transaction(function () use ($session, $by) {
            $session = TableSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new BusinessException('Phiên bàn đã đóng.');
            }

            if ($session->orderItems()->unbilled()->exists()) {
                throw new BusinessException('Bàn còn món chưa thanh toán, hãy thu tiền hoặc hủy món trước.');
            }

            $session->orders()->where('status', OrderStatus::Pending)->each(function ($order) use ($by) {
                $order->update(['status' => OrderStatus::Rejected, 'rejected_reason' => 'Đóng bàn']);
                $order->items()->update([
                    'status' => OrderItemStatus::Cancelled,
                    'cancel_reason' => 'Đóng bàn',
                    'cancelled_by' => $by->id,
                    'cancelled_at' => now(),
                ]);
            });

            $this->markClosed($session, $by);
        });

        event(new TableSessionUpdated($session->refresh()));
    }

    /** Đánh dấu phiên đã đóng và xử lý xong các yêu cầu còn treo. Gọi bên trong transaction. */
    public function markClosed(TableSession $session, ?User $by): void
    {
        $session->update([
            'status' => TableSessionStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $by?->id,
        ]);

        $session->serviceRequests()->where('status', ServiceRequestStatus::Pending)->update([
            'status' => ServiceRequestStatus::Done,
            'handled_by' => $by?->id,
            'handled_at' => now(),
        ]);
    }

    /** Khách bấm "Gọi nhân viên" / "Thanh toán". Bấm nhiều lần không tạo trùng yêu cầu. */
    public function requestService(TableSession $session, ServiceRequestType $type): ServiceRequest
    {
        if (! $session->isOpen()) {
            throw new BusinessException('Phiên bàn đã đóng.');
        }

        $request = $session->serviceRequests()->firstOrCreate(
            ['type' => $type, 'status' => ServiceRequestStatus::Pending],
        );

        if ($type === ServiceRequestType::RequestBill && $session->status === TableSessionStatus::Open) {
            $session->update(['status' => TableSessionStatus::PaymentRequested]);
        }

        if ($request->wasRecentlyCreated) {
            event(new TableSessionUpdated($session, "{$session->diningTable->displayName()}: {$type->getLabel()}"));
        }

        return $request;
    }

    public function resolveServiceRequest(ServiceRequest $request, User $by): void
    {
        $request->update([
            'status' => ServiceRequestStatus::Done,
            'handled_by' => $by->id,
            'handled_at' => now(),
        ]);

        event(new TableSessionUpdated($request->tableSession));
    }
}
