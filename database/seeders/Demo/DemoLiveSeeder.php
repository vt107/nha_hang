<?php

namespace Database\Seeders\Demo;

use App\Enums\OrderItemStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestType;
use App\Enums\TableSessionSource;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BankTransferService;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentLine;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OrderItemService;
use App\Services\Ordering\OrderService;
use App\Services\Reservations\ReservationService;
use App\Services\Tables\TableSessionService;
use App\Support\Code;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Bản demo: các bàn đang có khách ngay lúc seed (màn hình phục vụ / bếp / khách tại bàn có nội dung).
 * Đi qua service thật (mở phiên, giỏ hàng, gọi món, duyệt, bếp chuyển trạng thái, tách hóa đơn, webhook SePay)
 * với đồng hồ lùi về từng thời điểm, nên dữ liệu đúng mọi quy ước nghiệp vụ.
 */
class DemoLiveSeeder extends Seeder
{
    private CarbonImmutable $now;

    private User $waiter;

    private User $waiter2;

    private User $kitchen;

    public function __construct(
        private TableSessionService $sessions,
        private OrderService $orders,
        private OrderItemService $items,
        private CartService $cart,
        private BillingService $billing,
        private BankTransferService $transfers,
        private ReservationService $reservations,
    ) {}

    public function run(): void
    {
        $this->now = CarbonImmutable::now();
        $this->waiter = User::query()->where('email', 'waiter@nhahang.test')->firstOrFail();
        $this->waiter2 = User::query()->where('email', 'mai.hoang@nhahang.test')->firstOrFail();
        $this->kitchen = User::query()->where('email', 'kitchen@nhahang.test')->firstOrFail();

        // Không đẩy hàng trăm event realtime cũ lên Reverb / Horizon khi seed.
        $broadcasting = config('broadcasting.default');
        $queue = config('queue.default');
        config(['broadcasting.default' => 'null', 'queue.default' => 'sync']);

        try {
            $this->tableA01();
            $this->tableA02();
            $this->tableA03();
            $this->tableA05();
            $this->tableA06();
            $this->tableA08();
            $this->tableB01();
            $this->tableB02();
            $this->tableB04();
            $this->tableS01();
            $this->tableP01();
        } finally {
            Carbon::setTestNow();
            config(['broadcasting.default' => $broadcasting, 'queue.default' => $queue]);
        }
    }

    /** Khách 2 người đang ăn, mọi món đã lên. */
    private function tableA01(): void
    {
        $session = $this->openByQr('A01', 52, 2);
        $order = $this->qrOrder($session, 50, [
            ['Phở bò tái nạm', 1],
            ['Cơm tấm sườn bì chả', 1, ['Trứng ốp la']],
            ['Trà đá', 2],
            ['Cà phê sữa đá', 1, ['Lớn (L)', 'Ít ngọt']],
        ], confirmAgo: 48);
        $this->progress($order, [[46, 36, 34], [45, 33, 31], [47, 46, 45], [47, 43, 42]]);
    }

    /** Khách vừa quét QR, chưa gọi món. */
    private function tableA02(): void
    {
        $this->openByQr('A02', 3);
    }

    /** Bàn demo "Khách tại bàn" (config demo.qr_table_token): món ở đủ trạng thái, 1 món bếp hủy. */
    private function tableA03(): void
    {
        $session = $this->openByQr('A03', 38, 4);
        $device = 'demo-device-a03';

        $first = $this->qrOrder($session, 35, [
            ['Gỏi cuốn tôm thịt', 2],
            ['Chả giò rế', 1],
            ['Lẩu thái hải sản', 1, ['Cay vừa', 'Nồi lớn (4-6 người)']],
            ['Nước cam ép', 2, ['Lớn (L)']],
            ['Trà đá', 2],
        ], confirmAgo: 33, device: $device, note: 'Ra món khai vị trước giúp mình');
        $this->progress($first, [[31, 25, 24], [31, 22, 21], [6], [32, 29, 28], [33, 32, 31]]);

        $second = $this->qrOrder($session, 12, [
            ['Gà nướng mật ong', 1],
            ['Rau muống xào tỏi', 1],
            ['Bia Sài Gòn', 4],
        ], device: $device);
        $this->progress($second, [[5], [], [11, 1]]);

        $byStaff = $this->staffOrder($session, 4, [
            ['Cơm chiên hải sản', 1, ['Trứng ốp la']],
            ['Cá kho tộ', 1],
        ]);
        $this->at(2, fn () => $this->items->transition($byStaff->items->sortBy('id')->values()[1], OrderItemStatus::Cancelled, $this->kitchen, 'Hết cá basa, bếp báo đổi món khác'));
    }

    /** Order đầu tiên qua QR đang chờ nhân viên duyệt. */
    private function tableA05(): void
    {
        $session = $this->openByQr('A05', 6, 3);
        $this->qrOrder($session, 2, [
            ['Bún chả Hà Nội', 2],
            ['Chả giò rế', 1],
            ['Trà đá', 3],
        ]);
    }

    /** Món xong chờ bưng + khách bấm gọi nhân viên. */
    private function tableA06(): void
    {
        $session = $this->openByStaff('A06', 41, 4);
        $first = $this->staffOrder($session, 39, [
            ['Phở bò tái nạm', 1, ['Trứng trần']],
            ['Phở bò tái nạm', 1],
            ['Gỏi cuốn tôm thịt', 1],
            ['Coca-Cola', 2],
        ]);
        $this->progress($first, [[37, 27, 26], [37, 27, 26], [36, 30, 29], [38, 37, 36]]);

        $second = $this->staffOrder($session, 18, [
            ['Cá kho tộ', 1],
            ['Canh chua cá lóc', 1],
        ]);
        $this->progress($second, [[15, 3], [14, 3]]);

        $this->at(2, fn () => $this->sessions->requestService($session, ServiceRequestType::CallWaiter));
    }

    private function tableA08(): void
    {
        $session = $this->openByQr('A08', 25, 2);
        $order = $this->qrOrder($session, 23, [
            ['Mì xào bò', 1, ['Cay vừa']],
            ['Cà phê sữa đá', 1, ['Vừa (M)', 'Ít đá']],
            ['Khoai tây chiên', 1],
        ], confirmAgo: 22);
        $this->progress($order, [[8], [21, 19, 18], [10, 2]]);
    }

    /** Khách bấm "Thanh toán" (chờ thanh toán) + chuyển khoản báo về thiếu tiền, chờ nhân viên xử lý. */
    private function tableB01(): void
    {
        $session = $this->openByQr('B01', 85, 5);
        $first = $this->qrOrder($session, 82, [
            ['Lẩu gà lá é', 1, ['Không cay', 'Nồi lớn (4-6 người)']],
            ['Gỏi ngó sen tôm thịt', 1],
            ['Chả giò rế', 2],
            ['Bia Heineken', 6],
            ['Trà đá', 5],
        ], confirmAgo: 80);
        $this->progress($first, [[78, 62, 60], [78, 68, 67], [77, 66, 65], [79, 78, 77], [80, 79, 78]]);

        $second = $this->qrOrder($session, 40, [
            ['Chè khúc bạch', 3, ['Thạch dừa']],
            ['Bánh flan', 2],
        ]);
        $this->progress($second, [[38, 30, 28], [38, 27, 25]]);

        $this->at(4, fn () => $this->sessions->requestService($session, ServiceRequestType::RequestBill));

        $total = $this->billing->summarize($session->refresh())->total;
        $this->at(2, fn () => $this->transfers->handleSePay([
            'id' => 92_000_001,
            'gateway' => 'Vietcombank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '0000000000',
            'content' => $session->paymentCode().' chuyen tien',
            'transferType' => 'in',
            'transferAmount' => $total - 50000,
            'referenceCode' => 'FT'.now()->format('ymd').'48213977',
            'description' => 'BankAPINotify '.$session->paymentCode().' chuyen tien',
        ]));
    }

    /** Khách đặt bàn đã đến (mở phiên từ đặt bàn), lẩu đang nấu. */
    private function tableB02(): void
    {
        [$name, $phone] = DemoPeople::customer();
        $table = $this->table('B02');
        $reservedAt = $this->now->subMinutes(35);

        $reservation = $this->at(60 * 24 * 2, fn () => Reservation::create([
            'code' => Code::make('R'),
            'customer_name' => $name,
            'customer_phone' => $phone,
            'party_size' => 8,
            'reserved_at' => $reservedAt->setTime($reservedAt->hour, intdiv($reservedAt->minute, 15) * 15),
            'duration_minutes' => 120,
            'dining_table_id' => $table->id,
            'status' => ReservationStatus::Confirmed,
            'source' => ReservationSource::Phone,
            'note' => 'Họp lớp, cần bàn dài',
            'handled_by' => $this->waiter->id,
        ]));

        $session = $this->at(28, fn () => $this->reservations->seat($reservation, $this->waiter));
        $order = $this->staffOrder($session, 26, [
            ['Lẩu bò nhúng giấm', 1, ['Nồi lớn (4-6 người)']],
            ['Lẩu thái hải sản', 1, ['Rất cay', 'Nồi lớn (4-6 người)']],
            ['Gỏi ngó sen tôm thịt', 1],
            ['Chả giò rế', 2],
            ['Bia Sài Gòn', 10],
            ['Nước dừa tươi', 3],
        ]);
        $this->progress($order, [[10], [9], [24, 15, 14], [24, 16, 14], [25, 23, 22], [25, 23, 22]]);

        $this->staffOrder($session, 3, [['Bò lúc lắc', 2]]);
    }

    /** Đã tách thu một hóa đơn (2 người trả trước bằng chuyển khoản), phần còn lại chưa thu. */
    private function tableB04(): void
    {
        $session = $this->openByStaff('B04', 100, 6);
        $order = $this->staffOrder($session, 97, [
            ['Phở bò tái nạm', 2],
            ['Bún chả Hà Nội', 2],
            ['Cơm tấm sườn bì chả', 2],
            ['Trà đá', 6],
            ['Cà phê sữa đá', 2, ['Vừa (M)']],
        ]);
        $this->progress($order, [[95, 82, 80], [95, 80, 79], [94, 84, 83], [96, 95, 94], [60, 57, 55]]);

        $items = $order->items()->orderBy('id')->get();
        $this->at(10, fn () => $this->billing->checkout($session, $this->waiter, [PaymentLine::transfer(reference: 'FT'.now()->format('ymd').'77120458')],
            selection: [$items[0]->id => 2, $items[3]->id => 2]));
    }

    /** Bàn đông vừa gọi: món chờ quá 15 phút (bếp cảnh báo đỏ). */
    private function tableS01(): void
    {
        $session = $this->openByQr('S01', 18, 7);
        $order = $this->qrOrder($session, 16, [
            ['Lẩu thái hải sản', 1, ['Cay vừa', 'Nồi lớn (4-6 người)']],
            ['Gà nướng mật ong', 1],
            ['Tôm sú nướng muối ớt', 1],
            ['Mực chiên nước mắm', 1],
            ['Rau muống xào tỏi', 2],
            ['Bia Sài Gòn', 6],
        ], confirmAgo: 15, confirmBy: $this->waiter2);
        $this->progress($order, [[6], [], [], [4], [], [14, 12, 11]]);
    }

    /** Phòng VIP tiệc sinh nhật 10 khách. */
    private function tableP01(): void
    {
        $session = $this->openByStaff('P01', 55, 10);
        $session->update(['note' => 'Tiệc sinh nhật, khách mang bánh kem']);

        $first = $this->staffOrder($session, 52, [
            ['Gỏi cuốn tôm thịt', 3],
            ['Nem chua rán', 2],
            ['Gà nướng mật ong', 2],
            ['Bò lúc lắc', 2],
            ['Lẩu thái hải sản', 2, ['Không cay', 'Nồi lớn (4-6 người)']],
            ['Bia Heineken', 12],
            ['Coca-Cola', 4],
        ], by: $this->waiter2);
        $this->progress($first, [[50, 42, 40], [50, 41, 39], [48, 30, 28], [7], [20, 1], [51, 50, 49], [51, 50, 49]]);

        $this->staffOrder($session, 8, [
            ['Chè thái', 10, ['Thạch dừa']],
            ['Trái cây thập cẩm', 2],
        ], by: $this->waiter2);
    }

    private function openByQr(string $code, int $minutesAgo, ?int $guests = null): TableSession
    {
        $table = $this->table($code);
        $session = $this->at($minutesAgo, fn () => $this->sessions->openForTable($table, TableSessionSource::Qr));

        if ($guests) {
            $session->update(['guest_count' => $guests]);
        }

        return $session;
    }

    private function openByStaff(string $code, int $minutesAgo, int $guests): TableSession
    {
        return $this->at($minutesAgo, fn () => $this->sessions->openForTable($this->table($code), TableSessionSource::Staff, $this->waiter, guestCount: $guests));
    }

    /**
     * Khách gọi qua giỏ hàng trên điện thoại. Order đầu tiên của phiên chờ duyệt ($confirmAgo = lúc nhân viên duyệt).
     *
     * @param  list<array{0: string, 1: int, 2?: list<string>}>  $lines  [tên món, số phần, [tên tùy chọn]]
     */
    private function qrOrder(TableSession $session, int $minutesAgo, array $lines, ?int $confirmAgo = null, ?string $device = null, ?string $note = null, ?User $confirmBy = null): Order
    {
        $device ??= (string) Str::uuid();

        $order = $this->at($minutesAgo, function () use ($session, $lines, $device, $note) {
            foreach ($lines as $line) {
                [$item, $optionIds] = $this->menuLine($line);
                $this->cart->add($session, $device, $item->id, $line[1], $optionIds);
            }

            return $this->orders->placeFromCart($session, $device, $note);
        });

        if ($confirmAgo !== null) {
            $this->at($confirmAgo, fn () => $this->orders->confirm($order, $confirmBy ?? $this->waiter));
        }

        return $order->refresh();
    }

    /**
     * @param  list<array{0: string, 1: int, 2?: list<string>}>  $lines
     */
    private function staffOrder(TableSession $session, int $minutesAgo, array $lines, ?User $by = null): Order
    {
        $payload = array_map(function (array $line) {
            [$item, $optionIds] = $this->menuLine($line);

            return ['menu_item_id' => $item->id, 'option_ids' => $optionIds, 'quantity' => $line[1]];
        }, $lines);

        return $this->at($minutesAgo, fn () => $this->orders->placeByStaff($session, $by ?? $this->waiter, $payload))->load('items');
    }

    /**
     * Bếp / phục vụ chuyển trạng thái từng món: mỗi phần tử = [phút trước lúc bắt đầu nấu, xong, đã phục vụ] (bỏ trống = dừng ở bước trước).
     *
     * @param  list<list<int>>  $plan
     */
    private function progress(Order $order, array $plan): void
    {
        $items = $order->items()->orderBy('id')->get();
        $steps = [OrderItemStatus::Cooking, OrderItemStatus::Ready, OrderItemStatus::Served];

        foreach ($plan as $index => $times) {
            foreach ($times as $step => $minutesAgo) {
                $to = $steps[$step];
                $by = $to === OrderItemStatus::Served ? $this->waiter : $this->kitchen;
                $this->at($minutesAgo, fn () => $this->items->transition($items[$index]->refresh(), $to, $by));
            }
        }
    }

    /**
     * @param  array{0: string, 1: int, 2?: list<string>}  $line
     * @return array{0: MenuItem, 1: list<int>}
     */
    private function menuLine(array $line): array
    {
        $item = MenuItem::query()->where('name', $line[0])->with('optionGroups.options')->firstOrFail();
        $optionIds = $item->optionGroups->flatMap->options->whereIn('name', $line[2] ?? [])->pluck('id')->values()->all();

        return [$item, $optionIds];
    }

    private function table(string $code): DiningTable
    {
        return DiningTable::query()->where('code', $code)->firstOrFail();
    }

    /**
     * Chạy $callback với đồng hồ lùi về $minutesAgo phút trước lúc seed.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(int $minutesAgo, callable $callback): mixed
    {
        Carbon::setTestNow($this->now->subMinutes($minutesAgo));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
