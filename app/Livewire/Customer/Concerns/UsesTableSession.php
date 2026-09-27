<?php

namespace App\Livewire\Customer\Concerns;

use App\Enums\ServiceRequestType;
use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureTableSession;
use App\Models\TableSession;
use App\Services\Tables\TableSessionService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Dùng cho component của khách: phiên bàn lấy từ session trình duyệt (middleware table.session đã kiểm tra),
 * device_id lấy từ cookie. Hai giá trị khóa (#[Locked]) để client không sửa được.
 */
trait UsesTableSession
{
    #[Locked]
    public string $sessionToken = '';

    #[Locked]
    public string $deviceId = '';

    public function mountUsesTableSession(): void
    {
        $this->sessionToken = (string) session('table_session_token');
        $this->deviceId = (string) request()->cookie(EnsureTableSession::DEVICE_COOKIE);
    }

    #[Computed]
    public function tableSession(): TableSession
    {
        return TableSession::query()->with('diningTable')->where('token', $this->sessionToken)->firstOrFail();
    }

    /** Phiên đã đóng (đã thanh toán) thì đưa về trang menu, middleware sẽ hiện lời cảm ơn. */
    protected function ensureSessionOpen(): bool
    {
        if ($this->tableSession->isOpen()) {
            return true;
        }

        $this->redirectRoute('customer.menu');

        return false;
    }

    /** Chặn spam: mỗi phiên + IP tối đa 12 thao tác / phút. */
    protected function throttle(): bool
    {
        $key = 'customer:'.$this->sessionToken.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 12)) {
            $this->toast('Bạn thao tác quá nhanh, vui lòng thử lại sau ít phút.', 'warning');

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    public function callWaiter(TableSessionService $sessions): void
    {
        $this->requestService($sessions, ServiceRequestType::CallWaiter, 'Đã gọi nhân viên, vui lòng chờ trong giây lát.');
    }

    public function requestBill(TableSessionService $sessions): void
    {
        $this->requestService($sessions, ServiceRequestType::RequestBill, 'Đã báo nhân viên, nhân viên sẽ tới thanh toán cho bàn.');
    }

    private function requestService(TableSessionService $sessions, ServiceRequestType $type, string $message): void
    {
        if (! $this->ensureSessionOpen() || ! $this->throttle()) {
            return;
        }

        try {
            $sessions->requestService($this->tableSession, $type);
            $this->toast($message, 'success');
        } catch (BusinessException $e) {
            $this->toast($e->getMessage(), 'error');
        }

        unset($this->tableSession);
    }

    protected function toast(string $message, string $type = 'info'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
