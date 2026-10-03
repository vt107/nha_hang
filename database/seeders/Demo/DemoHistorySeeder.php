<?php

namespace Database\Seeders\Demo;

use App\Enums\BankTransactionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bản demo: ~90 ngày khách đã ăn xong (phiên đóng, order, món, hóa đơn, thanh toán, giao dịch SePay, yêu cầu phục vụ)
 * cho báo cáo doanh thu / món bán chạy / hình thức thanh toán.
 *
 * Ghi hàng loạt bằng query builder (nhanh hơn đi qua service hàng nghìn lần) nhưng giữ đúng quy ước của BillingService:
 * order_items là snapshot giá + tùy chọn, hóa đơn = tổng món tính tiền - giảm giá + phí phục vụ + VAT,
 * tổng các khoản thanh toán = tổng hóa đơn, món thuộc hóa đơn qua order_items.invoice_id.
 */
class DemoHistorySeeder extends Seeder
{
    private const DAYS = 90;

    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const CANCEL_REASONS = ['Khách đổi món', 'Bếp hết nguyên liệu', 'Khách chờ lâu, hủy món', 'Gọi trùng món'];

    private const VOID_REASONS = ['Khách khiếu nại món, hoàn tiền', 'Nhập sai giảm giá', 'Khách quen, quản lý mời'];

    /** @var array<string, int> bảng => id kế tiếp (ghi id tường minh để nối khóa ngoại khi insert hàng loạt) */
    private array $nextId = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = [];

    /** @var array<string, true> */
    private array $usedCodes = [];

    /** @var array<int, string> table_session_id => mã phiên (nội dung chuyển khoản) */
    private array $sessionCodes = [];

    private int $servicePercent;

    private int $vatPercent;

    private int $bankSequence = 91_000_000;

    private CarbonImmutable $now;

    /** @var Collection<int, DiningTable> */
    private Collection $tables;

    /** @var Collection<int, MenuItem> */
    private Collection $menu;

    /** @var array<int, int> menu_item_id => trọng số bán chạy */
    private array $weights = [];

    /** @var array<string, User> */
    private array $staff = [];

    /** @var array<int, CarbonImmutable> dining_table_id => bàn bận tới lúc */
    private array $busyUntil = [];

    public function run(): void
    {
        mt_srand(20260927);

        $this->now = CarbonImmutable::now();
        $this->servicePercent = (int) Setting::get('billing.service_charge_percent', 0);
        $this->vatPercent = (int) Setting::get('billing.vat_percent', 0);

        foreach (['reservations', 'table_sessions', 'orders', 'order_items', 'invoices', 'payments', 'bank_transactions', 'service_requests'] as $table) {
            $this->nextId[$table] = (int) DB::table($table)->max('id') + 1;
            $this->rows[$table] = [];
        }

        $this->loadCatalog();

        for ($daysAgo = self::DAYS - 1; $daysAgo >= 0; $daysAgo--) {
            $this->day($this->now->startOfDay()->subDays($daysAgo), $daysAgo);
        }

        $this->strayBankTransactions();
        $this->flush();
    }

    private function loadCatalog(): void
    {
        $this->tables = DiningTable::withTrashed()->get();

        $this->menu = MenuItem::withTrashed()
            ->with(['category', 'optionGroups' => fn ($query) => $query->where('is_active', true)->with('options')])
            ->get()
            ->reject(fn (MenuItem $item) => $item->category->slug === 'mon-tet')
            ->keyBy('id');

        $popularity = [
            'Trà đá' => 10, 'Bia Sài Gòn' => 9, 'Phở bò tái nạm' => 9, 'Cơm tấm sườn bì chả' => 8, 'Bún chả Hà Nội' => 7,
            'Cà phê sữa đá' => 6, 'Gỏi cuốn tôm thịt' => 6, 'Chả giò rế' => 6, 'Nước cam ép' => 5, 'Bia Heineken' => 5,
            'Trà tắc' => 5, 'Bò lúc lắc' => 5, 'Cơm chiên hải sản' => 5, 'Rau muống xào tỏi' => 5, 'Lẩu thái hải sản' => 4,
            'Cá kho tộ' => 4, 'Gà nướng mật ong' => 4, 'Mì xào bò' => 4, 'Coca-Cola' => 4,
        ];

        foreach ($this->menu as $item) {
            $this->weights[$item->id] = $popularity[$item->name] ?? 2;
        }

        $users = User::withTrashed()->get()->keyBy('email');
        $this->staff = [
            'admin' => $users['admin@nhahang.test'],
            'manager' => $users['manager@nhahang.test'],
            'waiter' => $users['waiter@nhahang.test'],
            'mai' => $users['mai.hoang@nhahang.test'],
            'huy' => $users['huy.do@nhahang.test'],
            'nam' => $users['nam.bui@nhahang.test'],
            'tung' => $users['tung.ngo@nhahang.test'],
            'kitchen' => $users['kitchen@nhahang.test'],
            'hong' => $users['hong.vu@nhahang.test'],
        ];
    }

    /** Số lượt khách trong ngày: theo thứ, xu hướng tăng dần, ngày lễ cao điểm, thỉnh thoảng ngày mưa vắng. */
    private function day(CarbonImmutable $date, int $daysAgo): void
    {
        $weekday = [1 => 0.8, 2 => 0.85, 3 => 0.9, 4 => 0.95, 5 => 1.15, 6 => 1.45, 0 => 1.35][$date->dayOfWeek];
        $trend = 0.8 + 0.4 * (self::DAYS - 1 - $daysAgo) / (self::DAYS - 1);
        $holiday = match ($date->format('m-d')) {
            '09-01', '09-02' => 1.8,
            '09-25', '10-20', '11-20' => 1.4,
            default => 1.0,
        };
        $rain = mt_rand(1, 100) <= 6 ? 0.6 : 1.0;
        $noise = mt_rand(88, 112) / 100;

        $count = max(6, (int) round(24 * $weekday * $trend * $holiday * $rain * $noise));

        $starts = [];
        for ($i = 0; $i < $count; $i++) {
            $slot = mt_rand(1, 100);
            $minutes = match (true) {
                $slot <= 42 => mt_rand(10 * 60 + 30, 13 * 60 + 15),
                $slot <= 52 => mt_rand(14 * 60, 17 * 60),
                default => mt_rand(17 * 60 + 30, 21 * 60 + 15),
            };
            $starts[] = $date->addMinutes($minutes);
        }

        sort($starts);
        $this->busyUntil = [];

        foreach ($starts as $openedAt) {
            $this->session($openedAt, $daysAgo);
        }
    }

    private function session(CarbonImmutable $openedAt, int $daysAgo): void
    {
        $guests = $this->pickWeighted([1 => 6, 2 => 30, 3 => 18, 4 => 22, 5 => 8, 6 => 8, 7 => 3, 8 => 3, 10 => 1, 12 => 1]);
        $duration = mt_rand(40, 75) + $guests * mt_rand(3, 7);
        $closedAt = $openedAt->addMinutes($duration);

        // Hôm nay: chỉ lấy lượt khách đã về (phiên đang mở do DemoLiveSeeder tạo).
        if ($closedAt->greaterThan($this->now->subMinutes(5))) {
            return;
        }

        $table = $this->pickTable($guests, $openedAt, $closedAt, $daysAgo);
        $waiter = $this->pickWaiter($daysAgo);
        $source = $this->pickWeighted(['qr' => 55, 'staff' => 33, 'reservation' => 12]);
        $sessionId = $this->id('table_sessions');
        $this->sessionCodes[$sessionId] = $this->code('S', $openedAt);

        $reservationId = null;
        if ($source === 'reservation') {
            $reservedAt = $openedAt->subMinutes(mt_rand(0, 10))->startOfHour()->addMinutes(intdiv($openedAt->minute, 15) * 15);
            $reservationId = $this->reservation($table, $guests, $reservedAt, $waiter);
        }

        $devices = collect(range(1, max(1, min(3, $guests - 1))))->map(fn () => (string) Str::uuid())->all();

        // Order + món
        $items = [];
        $orderCount = 1 + (mt_rand(1, 100) <= 45 ? 1 : 0) + (mt_rand(1, 100) <= 12 ? 1 : 0);
        $orderAt = $openedAt->addMinutes(mt_rand(2, 8));

        for ($k = 0; $k < $orderCount && $orderAt->lessThan($closedAt->subMinutes(15)); $k++) {
            $orderSource = $k === 0
                ? ($source === 'qr' ? OrderSource::Qr : OrderSource::Staff)
                : (mt_rand(1, 100) <= 60 ? OrderSource::Qr : OrderSource::Staff);
            $rejected = $orderSource === OrderSource::Qr && $k === 0 && mt_rand(1, 100) <= 2;
            $items = [...$items, ...$this->order($sessionId, $orderSource, $k, $orderAt, $guests, $waiter, $devices, $rejected, $daysAgo)];
            $orderAt = $orderAt->addMinutes(mt_rand(15, 40));
        }

        $billable = [];
        foreach ($items as $item) {
            // Món hủy không tính tiền, không thuộc hóa đơn nào.
            if ($item['status'] === OrderItemStatus::Cancelled->value) {
                $this->rows['order_items'][] = $item;
            } else {
                $billable[] = $item;
            }
        }

        // Thu tiền: đa số 1 hóa đơn, thỉnh thoảng tách hóa đơn theo món.
        $closedBy = $waiter->id;
        if ($billable !== []) {
            $groups = count($billable) >= 3 && mt_rand(1, 100) <= 5
                ? array_chunk($billable, (int) ceil(count($billable) / 2))
                : [$billable];

            foreach ($groups as $index => $group) {
                $isFinal = $index === array_key_last($groups);
                $paidAt = $isFinal ? $closedAt : $closedAt->subMinutes(mt_rand(5, 12));
                $closedBy = $this->invoice($sessionId, $group, $paidAt, $waiter, $isFinal && count($groups) === 1);
            }
        }

        $this->rows['table_sessions'][] = [
            'id' => $sessionId,
            'dining_table_id' => $table->id,
            'code' => $this->sessionCodes[$sessionId],
            'token' => Str::random(32),
            'status' => TableSessionStatus::Closed->value,
            'source' => match ($source) {
                'qr' => TableSessionSource::Qr->value,
                'staff' => TableSessionSource::Staff->value,
                default => TableSessionSource::Reservation->value,
            },
            'guest_count' => $guests,
            'reservation_id' => $reservationId,
            'opened_by' => $source === 'qr' ? null : $waiter->id,
            'closed_by' => $closedBy,
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'note' => null,
            'created_at' => $openedAt,
            'updated_at' => $closedAt,
        ];

        // Khách bấm "Thanh toán" / "Gọi nhân viên" trên điện thoại (đã xử lý).
        if ($source === 'qr' && mt_rand(1, 100) <= 45) {
            $this->serviceRequest($sessionId, ServiceRequestType::RequestBill, $closedAt->subMinutes(mt_rand(6, 12)), $waiter);
        }
        if (mt_rand(1, 100) <= 15) {
            $this->serviceRequest($sessionId, ServiceRequestType::CallWaiter, $openedAt->addMinutes(mt_rand(10, 30)), $waiter);
        }
    }

    /**
     * @param  list<string>  $devices
     * @return list<array<string, mixed>> các dòng order_items (chưa insert, invoice_id gán sau)
     */
    private function order(int $sessionId, OrderSource $source, int $index, CarbonImmutable $createdAt, int $guests, User $waiter, array $devices, bool $rejected, int $daysAgo): array
    {
        $orderId = $this->id('orders');
        // Order QR đầu tiên của phiên cần nhân viên duyệt; order sau vào bếp luôn; nhân viên gọi hộ không cần duyệt.
        $confirmedAt = $source === OrderSource::Qr && $index === 0 ? $createdAt->addMinutes(mt_rand(1, 3)) : $createdAt;
        $confirmedBy = match (true) {
            $source === OrderSource::Staff, $index === 0 => $waiter->id,
            default => null,
        };

        $this->rows['orders'][] = [
            'id' => $orderId,
            'table_session_id' => $sessionId,
            'code' => $this->code('O', $createdAt),
            'source' => $source->value,
            'status' => ($rejected ? OrderStatus::Rejected : OrderStatus::Confirmed)->value,
            'device_id' => $source === OrderSource::Qr ? $devices[array_rand($devices)] : null,
            'note' => mt_rand(1, 100) <= 8 ? collect(['Không hành', 'Ít cay', 'Ra món khai vị trước', 'Có trẻ em, cho thêm chén nhỏ'])->random() : null,
            'created_by' => $source === OrderSource::Staff ? $waiter->id : null,
            'confirmed_by' => $confirmedBy,
            'confirmed_at' => $rejected ? null : $confirmedAt,
            'rejected_reason' => $rejected ? 'Khách gọi nhầm bàn' : null,
            'created_at' => $createdAt,
            'updated_at' => $confirmedAt,
        ];

        $lineCount = $index === 0 ? min(8, max(2, (int) round($guests * 0.9) + mt_rand(0, 2))) : mt_rand(1, 3);
        $picked = [];
        $items = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $item = $this->pickMenuItem($daysAgo, $picked);
            if (! $item) {
                break;
            }
            $picked[] = $item->id;

            [$options, $extra] = $this->pickOptions($item);
            $kitchen = mt_rand(1, 100) <= 70 ? $this->staff['kitchen'] : $this->staff['hong'];
            $row = [
                'id' => $this->id('order_items'),
                'order_id' => $orderId,
                'invoice_id' => null,
                'menu_item_id' => $item->id,
                'item_name' => $item->name,
                'unit_price' => $this->priceAt($item, $daysAgo) + $extra,
                'options' => $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null,
                'quantity' => $this->quantityFor($item, $guests),
                'note' => mt_rand(1, 100) <= 4 ? collect(['Không hành', 'Ít đá', 'Làm nhanh giúp', 'Không rau mùi'])->random() : null,
                'status' => OrderItemStatus::Served->value,
                'cancel_reason' => null,
                'cancelled_by' => null,
                'queued_at' => $confirmedAt,
                'cooking_at' => $confirmedAt->addMinutes(mt_rand(1, 6)),
                'ready_at' => null,
                'served_at' => null,
                'cancelled_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
            $row['ready_at'] = $row['cooking_at']->addMinutes($item->category->slug === 'do-uong' ? mt_rand(2, 5) : mt_rand(6, 18));
            $row['served_at'] = $row['ready_at']->addMinutes(mt_rand(1, 4));
            $row['updated_at'] = $row['served_at'];

            if ($rejected) {
                $row = [...$row, 'status' => OrderItemStatus::Cancelled->value, 'cancel_reason' => 'Khách gọi nhầm bàn',
                    'cancelled_by' => $waiter->id, 'queued_at' => null, 'cooking_at' => null, 'ready_at' => null, 'served_at' => null,
                    'cancelled_at' => $confirmedAt, 'updated_at' => $confirmedAt];
            } elseif (mt_rand(1, 100) <= 2) {
                $cancelledAt = $confirmedAt->addMinutes(mt_rand(2, 8));
                $row = [...$row, 'status' => OrderItemStatus::Cancelled->value, 'cancel_reason' => collect(self::CANCEL_REASONS)->random(),
                    'cancelled_by' => mt_rand(0, 1) ? $waiter->id : $kitchen->id, 'cooking_at' => null, 'ready_at' => null,
                    'served_at' => null, 'cancelled_at' => $cancelledAt, 'updated_at' => $cancelledAt];
            }

            $items[] = $row;
        }

        return $items;
    }

    /**
     * Một hóa đơn đã thu: tổng tính như BillingService::summarizeItems(), các khoản thanh toán cộng đúng bằng tổng.
     *
     * @param  list<array<string, mixed>>  $items
     * @return int|null người đóng bàn (null = hệ thống tự thu qua SePay)
     */
    private function invoice(int $sessionId, array $items, CarbonImmutable $paidAt, User $waiter, bool $canAutoConfirm): ?int
    {
        $subtotal = array_sum(array_map(fn (array $item) => $item['unit_price'] * $item['quantity'], $items));
        $discount = 0;
        if (mt_rand(1, 100) <= 12) {
            $discount = min($subtotal, (int) collect([10000, 20000, 50000, (int) (round($subtotal * 0.1 / 1000) * 1000)])->random());
        }
        $serviceCharge = (int) round(($subtotal - $discount) * $this->servicePercent / 100);
        $vat = (int) round(($subtotal - $discount + $serviceCharge) * $this->vatPercent / 100);
        $total = $subtotal - $discount + $serviceCharge + $vat;

        $roll = mt_rand(1, 100);
        $auto = $canAutoConfirm && $roll > 68 && $roll <= 90;
        $cashier = $auto ? null : $waiter;
        $invoiceId = $this->id('invoices');

        $invoice = [
            'id' => $invoiceId,
            'table_session_id' => $sessionId,
            'code' => $this->code('HD', $paidAt),
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'service_charge_amount' => $serviceCharge,
            'vat_amount' => $vat,
            'total' => $total,
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => $paidAt,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
            'cashier_id' => $cashier?->id,
            'note' => $auto ? 'Tự xác nhận chuyển khoản qua SePay' : null,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ];

        // Hóa đơn bị hủy (quản lý / admin, có lý do): không reopen = hoàn tiền / miễn phí, món vẫn thuộc hóa đơn hủy.
        $voidedAt = $paidAt->addMinutes(mt_rand(10, 90));
        if (! $auto && mt_rand(1, 1000) <= 7 && $voidedAt->lessThan($this->now)) {
            $invoice = [...$invoice, 'status' => InvoiceStatus::Void->value, 'voided_at' => $voidedAt,
                'voided_by' => (mt_rand(0, 1) ? $this->staff['manager'] : $this->staff['admin'])->id,
                'void_reason' => collect(self::VOID_REASONS)->random(), 'updated_at' => $voidedAt];
        }

        $this->rows['invoices'][] = $invoice;

        foreach ($items as $item) {
            $this->rows['order_items'][] = [...$item, 'invoice_id' => $invoiceId];
        }

        match (true) {
            $roll <= 50 => $this->cashPayment($invoiceId, $total, $paidAt, $waiter),
            $roll <= 68 => $this->transferPayment($invoiceId, $sessionId, $total, $paidAt, $waiter, mt_rand(1, 3) === 1),
            $auto => $this->autoTransferPayment($invoiceId, $sessionId, $total, $paidAt),
            $roll <= 97 => $this->mixedPayment($invoiceId, $sessionId, $total, $paidAt, $waiter),
            default => $this->splitEvenly($invoiceId, $total, $paidAt, $waiter),
        };

        return $cashier?->id;
    }

    private function cashPayment(int $invoiceId, int $amount, CarbonImmutable $at, User $cashier): void
    {
        $step = collect([1000, 10000, 50000, 100000])->random();
        $received = (int) (ceil($amount / $step) * $step);

        $this->payment($invoiceId, PaymentMethod::Cash, $amount, $at, $cashier, received: $received);
    }

    private function transferPayment(int $invoiceId, int $sessionId, int $amount, CarbonImmutable $at, User $cashier, bool $fromWebhook): void
    {
        // Tiền đã báo về qua SePay nhưng không tự thu được (còn món chưa phục vụ): nhân viên bấm "Dùng để thanh toán".
        $transactionId = $fromWebhook
            ? $this->bankTransaction($sessionId, $invoiceId, $amount, $at->subMinutes(mt_rand(2, 6)), BankTransactionStatus::Applied,
                'Còn 1 món chưa phục vụ. Hãy đánh dấu đã phục vụ hoặc hủy trước khi thanh toán.', $cashier->id)
            : null;

        $this->payment($invoiceId, PaymentMethod::BankTransfer, $amount, $at, $cashier, reference: $this->bankReference($at), bankTransactionId: $transactionId);
    }

    private function autoTransferPayment(int $invoiceId, int $sessionId, int $amount, CarbonImmutable $at): void
    {
        $transactionId = $this->bankTransaction($sessionId, $invoiceId, $amount, $at, BankTransactionStatus::Applied);
        $reference = end($this->rows['bank_transactions'])['reference_code'];

        $this->payment($invoiceId, PaymentMethod::BankTransfer, $amount, $at, null, reference: $reference, bankTransactionId: $transactionId);
    }

    private function mixedPayment(int $invoiceId, int $sessionId, int $total, CarbonImmutable $at, User $cashier): void
    {
        $cash = (int) (round($total / 2 / 1000) * 1000);

        $this->payment($invoiceId, PaymentMethod::Cash, $cash, $at, $cashier, received: (int) (ceil($cash / 50000) * 50000));
        $this->payment($invoiceId, PaymentMethod::BankTransfer, $total - $cash, $at, $cashier, reference: $this->bankReference($at));
    }

    private function splitEvenly(int $invoiceId, int $total, CarbonImmutable $at, User $cashier): void
    {
        $people = mt_rand(2, 4);
        $share = intdiv($total, $people);

        for ($i = 0; $i < $people; $i++) {
            $amount = $share + ($i === 0 ? $total - $share * $people : 0);
            $this->payment($invoiceId, PaymentMethod::Cash, $amount, $at, $cashier, received: $amount);
        }
    }

    private function payment(int $invoiceId, PaymentMethod $method, int $amount, CarbonImmutable $at, ?User $cashier, ?int $received = null, ?string $reference = null, ?int $bankTransactionId = null): void
    {
        $this->rows['payments'][] = [
            'id' => $this->id('payments'),
            'invoice_id' => $invoiceId,
            'method' => $method->value,
            'amount' => $amount,
            'received_amount' => $method === PaymentMethod::Cash ? $received ?? $amount : null,
            'reference' => $reference,
            'bank_transaction_id' => $bankTransactionId,
            'note' => null,
            'confirmed_by' => $cashier?->id,
            'confirmed_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    private function bankTransaction(?int $sessionId, ?int $invoiceId, int $amount, CarbonImmutable $at, BankTransactionStatus $status, ?string $note = null, ?int $handledBy = null, ?string $content = null, string $type = 'in', ?string $account = null): int
    {
        $id = $this->id('bank_transactions');
        $providerId = (string) $this->bankSequence++;
        $reference = $this->bankReference($at);
        $account ??= (string) Setting::get('bank.account_number');

        if ($content === null) {
            $paymentCode = str_replace('-', '', $this->sessionCodes[$sessionId]);
            $content = collect([
                "{$paymentCode} chuyen tien",
                "MBVCB.{$providerId}.{$paymentCode}.CT tu 0071000123456",
                "NGUYEN VAN AN chuyen tien {$paymentCode}",
                "{$paymentCode}",
            ])->random();
        }

        $this->rows['bank_transactions'][] = [
            'id' => $id,
            'provider' => 'sepay',
            'provider_id' => $providerId,
            'account_number' => $account,
            'amount' => $amount,
            'content' => $content,
            'reference_code' => $reference,
            'transacted_at' => $at,
            'status' => $status->value,
            'note' => $note,
            'table_session_id' => $sessionId,
            'invoice_id' => $invoiceId,
            'handled_by' => $handledBy,
            'payload' => json_encode([
                'id' => (int) $providerId,
                'gateway' => 'Vietcombank',
                'transactionDate' => $at->format('Y-m-d H:i:s'),
                'accountNumber' => $account,
                'subAccount' => null,
                'code' => null,
                'content' => $content,
                'transferType' => $type,
                'transferAmount' => $amount,
                'accumulated' => mt_rand(20_000_000, 90_000_000),
                'referenceCode' => $reference,
                'description' => 'BankAPINotify '.$content,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $at,
            'updated_at' => $at,
        ];

        return $id;
    }

    /** Giao dịch không khớp bàn / bỏ qua / khớp phiên đã đóng, để màn hình Chuyển khoản có đủ trạng thái. */
    private function strayBankTransactions(): void
    {
        $closedSession = end($this->rows['table_sessions']);

        $stray = [
            [0, 350000, 'CHUYEN TIEN AN TRUA', BankTransactionStatus::Unmatched, 'Không tìm thấy mã bàn trong nội dung chuyển khoản', 'in'],
            [1, 1250000, 'TRAN THI BICH chuyen khoan', BankTransactionStatus::Unmatched, 'Không tìm thấy mã bàn trong nội dung chuyển khoản', 'in'],
            [3, 480000, 'thanh toan ban B2', BankTransactionStatus::Unmatched, 'Không tìm thấy mã bàn trong nội dung chuyển khoản', 'in'],
            [9, 210000, 'IBFT chuyen tien', BankTransactionStatus::Unmatched, 'Không tìm thấy mã bàn trong nội dung chuyển khoản', 'in'],
            [2, 5000000, 'Chi tien nhap hang hai san', BankTransactionStatus::Ignored, 'Tiền ra', 'out'],
            [6, 3200000, 'Thanh toan tien dien thang 9', BankTransactionStatus::Ignored, 'Tiền ra', 'out'],
            [12, 150000, 'Hoan tien coc', BankTransactionStatus::Ignored, 'Không phải tài khoản nhận tiền của nhà hàng', 'in', '1903555555555'],
            [21, 2500000, 'Rut tien mat', BankTransactionStatus::Ignored, 'Tiền ra', 'out'],
        ];

        foreach ($stray as $row) {
            [$daysAgo, $amount, $content, $status, $note, $type] = $row;
            $at = $this->now->subDays($daysAgo)->setTime(mt_rand(9, 21), mt_rand(0, 59));
            $at = $at->greaterThan($this->now) ? $this->now->subMinutes(40) : $at;
            $this->bankTransaction(null, null, $amount, $at, $status, $note, content: $content, type: $type, account: $row[6] ?? null);
        }

        // Tiền về sau khi bàn đã đóng: chờ nhân viên kiểm tra.
        if ($closedSession) {
            $at = $closedSession['closed_at']->addMinutes(7);
            $this->bankTransaction($closedSession['id'], null, 120000, $at->greaterThan($this->now) ? $this->now->subMinute() : $at,
                BankTransactionStatus::Matched, 'Phiên bàn đã đóng');
        }
    }

    private function reservation(DiningTable $table, int $guests, CarbonImmutable $reservedAt, User $waiter): int
    {
        $id = $this->id('reservations');
        $createdAt = $reservedAt->subDays(mt_rand(0, 6))->subHours(mt_rand(1, 10));
        [$name, $phone] = DemoPeople::customer();

        $this->rows['reservations'][] = [
            'id' => $id,
            'code' => $this->code('R', $createdAt),
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => null,
            'party_size' => $guests,
            'reserved_at' => $reservedAt,
            'duration_minutes' => 120,
            'dining_table_id' => $table->id,
            'status' => ReservationStatus::Seated->value,
            'source' => collect([ReservationSource::Web, ReservationSource::Phone, ReservationSource::Staff])->random()->value,
            'note' => mt_rand(1, 100) <= 30 ? collect(['Bàn gần cửa sổ', 'Có 1 ghế trẻ em', 'Sinh nhật bạn', 'Khách VIP công ty'])->random() : null,
            'internal_note' => null,
            'handled_by' => $waiter->id,
            'created_at' => $createdAt,
            'updated_at' => $reservedAt,
        ];

        return $id;
    }

    private function serviceRequest(int $sessionId, ServiceRequestType $type, CarbonImmutable $at, User $waiter): void
    {
        $handledAt = $at->addMinutes(mt_rand(1, 4));

        $this->rows['service_requests'][] = [
            'id' => $this->id('service_requests'),
            'table_session_id' => $sessionId,
            'type' => $type->value,
            'status' => ServiceRequestStatus::Done->value,
            'note' => null,
            'handled_by' => $waiter->id,
            'handled_at' => $handledAt,
            'created_at' => $at,
            'updated_at' => $handledAt,
        ];
    }

    private function pickTable(int $guests, CarbonImmutable $from, CarbonImmutable $to, int $daysAgo): DiningTable
    {
        $candidates = $this->tables->filter(fn (DiningTable $table) => ($table->is_active && ! $table->trashed())
            // Bàn S04 tạm ngưng / A09 đã xóa vẫn có khách trong quá khứ.
            || ($table->code === 'S04' && $daysAgo > 20)
            || ($table->code === 'A09' && $daysAgo > 45));

        $fits = $candidates->filter(fn (DiningTable $table) => $table->capacity >= $guests
            && $table->capacity <= max(4, $guests * 2)
            && (! isset($this->busyUntil[$table->id]) || $this->busyUntil[$table->id]->lessThan($from)));

        $table = ($fits->isNotEmpty() ? $fits : $candidates->filter(fn (DiningTable $table) => $table->capacity >= $guests))->random();
        $this->busyUntil[$table->id] = $to->addMinutes(5);

        return $table;
    }

    private function pickWaiter(int $daysAgo): User
    {
        $pool = ['waiter' => 5, 'mai' => 4, 'huy' => 3, 'manager' => 1];
        if ($daysAgo > 25) {
            $pool['nam'] = 3;
        }
        if ($daysAgo > 40) {
            $pool['tung'] = 3;
        }

        return $this->staff[$this->pickWeighted($pool)];
    }

    /**
     * @param  list<int>  $exclude
     */
    private function pickMenuItem(int $daysAgo, array $exclude): ?MenuItem
    {
        $weights = [];
        foreach ($this->menu as $id => $item) {
            if (in_array($id, $exclude, true)) {
                continue;
            }
            // Món đã ngừng bán / tạm ẩn chỉ có trong các ngày trước đó.
            if (($item->trashed() && $daysAgo <= 30) || (! $item->is_active && $daysAgo <= 20)) {
                continue;
            }
            // Món đang tạm hết hôm nay vẫn bán được những ngày trước.
            if (! $item->is_available && $daysAgo === 0) {
                continue;
            }
            $weights[$id] = $this->weights[$id];
        }

        return $weights ? $this->menu[$this->pickWeighted($weights)] : null;
    }

    /**
     * @return array{0: list<array{group: string, name: string, price_delta: int}>, 1: int}
     */
    private function pickOptions(MenuItem $item): array
    {
        $snapshot = [];

        foreach ($item->optionGroups as $group) {
            $available = $group->options->where('is_available', true)->values();
            if ($available->isEmpty()) {
                continue;
            }

            if ($group->min_select > 0) {
                $chosen = mt_rand(1, 100) <= 70
                    ? $available->where('is_default', true)->take($group->max_select)
                    : collect([$available->random()]);
                $chosen = $chosen->isEmpty() ? collect([$available->first()]) : $chosen;
            } elseif (mt_rand(1, 100) <= 25) {
                $chosen = $available->random(min($available->count(), mt_rand(1, $group->max_select)));
                $chosen = $chosen instanceof Collection ? $chosen : collect([$chosen]);
            } else {
                continue;
            }

            foreach ($chosen as $option) {
                $snapshot[] = ['group' => $group->name, 'name' => $option->name, 'price_delta' => $option->price_delta];
            }
        }

        return [$snapshot, array_sum(array_column($snapshot, 'price_delta'))];
    }

    private function quantityFor(MenuItem $item, int $guests): int
    {
        return match ($item->category->slug) {
            'do-uong' => $item->name === 'Trà đá' ? $guests : mt_rand(1, max(1, $guests)),
            'lau' => 1,
            'mon-chinh' => mt_rand(1, max(1, (int) ceil($guests / 2))),
            default => $guests >= 4 && mt_rand(0, 1) ? 2 : 1,
        };
    }

    /** Giá snapshot theo thời điểm: phở tăng giá cách đây 45 ngày (hóa đơn cũ giữ giá cũ). */
    private function priceAt(MenuItem $item, int $daysAgo): int
    {
        return $item->name === 'Phở bò tái nạm' && $daysAgo > 45 ? 60000 : $item->price;
    }

    private function bankReference(CarbonImmutable $at): string
    {
        return 'FT'.$at->format('ymd').mt_rand(100000, 999999).mt_rand(10, 99);
    }

    /** Mã giống App\Support\Code::make() nhưng theo ngày phát sinh (dữ liệu quá khứ). */
    private function code(string $prefix, CarbonImmutable $at): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= self::CODE_ALPHABET[mt_rand(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = $prefix.$at->format('ymd').'-'.$suffix;
        } while (isset($this->usedCodes[$code]));

        return $this->usedCodes[$code] = $code;
    }

    private function id(string $table): int
    {
        return $this->nextId[$table]++;
    }

    /**
     * @template T of array-key
     *
     * @param  array<T, int>  $weights
     * @return T
     */
    private function pickWeighted(array $weights): int|string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    private function flush(): void
    {
        // Thứ tự theo khóa ngoại.
        foreach (['reservations', 'table_sessions', 'orders', 'invoices', 'order_items', 'bank_transactions', 'payments', 'service_requests'] as $table) {
            foreach (array_chunk($this->rows[$table], 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }
}
