<?php

namespace App\Livewire\Customer;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Livewire\Customer\Concerns\UsesTableSession;
use App\Models\Setting;
use App\Services\Billing\BillingService;
use App\Services\Billing\BillSummary;
use App\Support\QrImage;
use App\Support\VietQr;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tạm tính + VietQR để khách chuyển khoản. Nhân viên xác nhận tiền về rồi đóng bàn (trang tự chuyển sang lời cảm ơn).
 */
#[Title('Thanh toán')]
class BillPage extends Component
{
    use UsesTableSession;

    #[Computed]
    public function summary(): BillSummary
    {
        return app(BillingService::class)->summarize($this->tableSession);
    }

    #[Computed]
    public function billRequested(): bool
    {
        return $this->tableSession->serviceRequests()
            ->where('type', ServiceRequestType::RequestBill)
            ->where('status', ServiceRequestStatus::Pending)
            ->exists();
    }

    /**
     * @return array{image: string, bank: string, account: string, name: string, description: string}|null
     */
    #[Computed]
    public function transfer(): ?array
    {
        $bin = (string) Setting::get('bank.bin');
        $account = (string) Setting::get('bank.account_number');

        if ($this->summary->total === 0 || blank($bin) || blank($account)) {
            return null;
        }

        $description = VietQr::cleanDescription($this->tableSession->code);

        return [
            'image' => QrImage::dataUri(VietQr::payload($bin, $account, $this->summary->total, $description)),
            'bank' => VietQr::BANKS[$bin] ?? $bin,
            'account' => $account,
            'name' => (string) Setting::get('bank.account_name'),
            'description' => $description,
        ];
    }

    #[On('echo:table-session.{sessionToken},.session.updated')]
    public function refreshFromBroadcast(): void
    {
        unset($this->tableSession, $this->summary, $this->billRequested, $this->transfer);
        $this->ensureSessionOpen();
    }

    public function render(): View
    {
        return view('livewire.customer.bill-page');
    }
}
