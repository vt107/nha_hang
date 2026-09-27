<?php

namespace App\Services\Billing;

use App\Enums\BankTransactionStatus;
use App\Events\StaffAlerted;
use App\Events\TableSessionUpdated;
use App\Exceptions\BusinessException;
use App\Models\BankTransaction;
use App\Models\Setting;
use App\Models\TableSession;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Tiền chuyển khoản báo về qua webhook: tìm phiên bàn theo mã trong nội dung CK (TableSession::paymentCode()),
 * đủ tiền + setting bank.auto_confirm bật thì tự thu tiền và đóng bàn; còn lại chờ nhân viên xử lý.
 */
class BankTransferService
{
    public function __construct(private BillingService $billing) {}

    /**
     * @param  array<string, mixed>  $payload  Dữ liệu webhook SePay
     */
    public function handleSePay(array $payload): BankTransaction
    {
        try {
            // Ghi nhận trước (unique provider + id) để webhook gửi lại không thu tiền 2 lần.
            $transaction = BankTransaction::create([
                'provider' => 'sepay',
                'provider_id' => (string) $payload['id'],
                'account_number' => $payload['accountNumber'] ?? null,
                'amount' => (int) $payload['transferAmount'],
                'content' => $payload['content'] ?? $payload['description'] ?? null,
                'reference_code' => $payload['referenceCode'] ?? null,
                'transacted_at' => isset($payload['transactionDate']) ? Carbon::parse($payload['transactionDate']) : now(),
                'status' => BankTransactionStatus::Unmatched,
                'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return BankTransaction::query()->where('provider', 'sepay')->where('provider_id', (string) $payload['id'])->firstOrFail();
        }

        if (($payload['transferType'] ?? 'in') !== 'in') {
            return $this->mark($transaction, BankTransactionStatus::Ignored, 'Tiền ra');
        }

        $account = (string) Setting::get('bank.account_number');

        if (filled($account) && filled($transaction->account_number) && $transaction->account_number !== $account
            && ($payload['subAccount'] ?? null) !== $account) {
            return $this->mark($transaction, BankTransactionStatus::Ignored, 'Không phải tài khoản nhận tiền của nhà hàng');
        }

        return $this->apply($transaction);
    }

    /** Khớp giao dịch với phiên bàn và thử tự thu tiền (cũng dùng khi admin gán tay giao dịch cho bàn). */
    public function apply(BankTransaction $transaction, ?TableSession $session = null): BankTransaction
    {
        $session ??= $this->matchSession($transaction->content);
        $amount = Money::format($transaction->amount);

        if (! $session) {
            event(new StaffAlerted("Nhận chuyển khoản {$amount} không khớp bàn nào (nội dung: {$transaction->content})"));

            return $this->mark($transaction, BankTransactionStatus::Unmatched, 'Không tìm thấy mã bàn trong nội dung chuyển khoản');
        }

        $transaction->update(['table_session_id' => $session->id]);
        $table = $session->diningTable->displayName();

        if (! $session->isOpen()) {
            event(new StaffAlerted("Nhận chuyển khoản {$amount} cho {$table} nhưng bàn đã đóng, cần kiểm tra"));

            return $this->mark($transaction, BankTransactionStatus::Matched, 'Phiên bàn đã đóng');
        }

        $total = $this->billing->summarize($session)->total;

        $reason = match (true) {
            ! Setting::get('bank.auto_confirm', true) => 'Tự xác nhận đang tắt, nhân viên xác nhận',
            $transaction->amount < $total => 'Thiếu '.Money::format($total - $transaction->amount),
            $transaction->amount > $total => 'Dư '.Money::format($transaction->amount - $total),
            default => null,
        };

        if ($reason === null) {
            try {
                $this->billing->checkout($session, null, [
                    PaymentLine::transfer($total, $transaction->reference_code, $transaction->id),
                ], note: 'Tự xác nhận chuyển khoản qua SePay');

                return $transaction->refresh();
            } catch (BusinessException $e) {
                $reason = $e->getMessage();
            }
        }

        $this->mark($transaction, BankTransactionStatus::Matched, $reason);
        event(new TableSessionUpdated($session, "{$table}: nhận chuyển khoản {$amount}, cần xác nhận ({$reason})"));

        return $transaction;
    }

    /**
     * Mã phiên trong nội dung CK. Ngân hàng hay bỏ dấu cách / gạch ngang và thêm chữ vào trước sau,
     * nên bỏ hết ký tự không phải chữ số rồi tìm mọi chuỗi dạng S + 6 số + 4 ký tự.
     */
    public function matchSession(?string $content): ?TableSession
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $content));

        preg_match_all('/(?=S(\d{6})([A-Z0-9]{4}))/', $normalized, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if ($session = TableSession::query()->firstWhere('code', "S{$match[1]}-{$match[2]}")) {
                return $session;
            }
        }

        return null;
    }

    private function mark(BankTransaction $transaction, BankTransactionStatus $status, ?string $note): BankTransaction
    {
        $transaction->update(['status' => $status, 'note' => $note]);

        return $transaction;
    }
}
