<?php

namespace App\Events;

use App\Models\TableSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Có thay đổi trên một phiên bàn (order mới, món đổi trạng thái, thanh toán...).
 * Màn hình nhân viên và trang của khách nghe event này để tải lại dữ liệu; $alert (nếu có) hiện thành thông báo cho nhân viên.
 */
class TableSessionUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TableSession $session,
        public ?string $alert = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('staff'),
            new Channel($this->session->broadcastChannel()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session' => $this->session->code,
            'table' => $this->session->diningTable->code,
            'status' => $this->session->status->value,
            'alert' => $this->alert,
        ];
    }
}
