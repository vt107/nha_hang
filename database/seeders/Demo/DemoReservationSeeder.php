<?php

namespace Database\Seeders\Demo;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Bản demo: đặt bàn hôm nay (theo khung giờ), 2 tuần tới và các lượt hủy / không đến trong quá khứ.
 * Đặt bàn đã đến (seated) nằm ở DemoHistorySeeder (gắn với phiên bàn) và DemoLiveSeeder (bàn B02).
 */
class DemoReservationSeeder extends Seeder
{
    private const SLOTS = ['11:00', '11:30', '12:00', '12:30', '13:00', '18:00', '18:30', '19:00', '19:30', '20:00'];

    private const NOTES = ['Bàn gần cửa sổ', 'Có 2 trẻ nhỏ, cần ghế em bé', 'Sinh nhật, nhà hàng chuẩn bị nến giúp', 'Tiếp khách công ty',
        'Ăn chay 1 người', 'Đến trễ khoảng 15 phút', 'Cần chỗ đậu ô tô'];

    private CarbonImmutable $now;

    /** @var Collection<string, DiningTable> */
    private Collection $tables;

    /** @var Collection<int, User> */
    private Collection $staff;

    /** @var array<string, true> */
    private array $usedCodes = [];

    public function run(): void
    {
        mt_srand(20261001);

        $this->now = CarbonImmutable::now();
        $this->tables = DiningTable::query()->active()->get()->keyBy('code');
        $this->usedCodes = Reservation::query()->pluck('code')->flip()->map(fn () => true)->all();
        $this->staff = User::query()->whereIn('email', ['waiter@nhahang.test', 'mai.hoang@nhahang.test', 'manager@nhahang.test'])->get();

        $this->today();
        $this->upcoming();
        $this->past();
    }

    /** Hôm nay: khung giờ chưa tới thì chờ / đã xác nhận, đã qua thì khách không đến / đã hủy. */
    private function today(): void
    {
        $plan = [
            ['11:30', 6, 'B03', ReservationStatus::Confirmed],
            ['12:00', 2, null, ReservationStatus::Pending],
            ['12:30', 4, 'A07', ReservationStatus::Confirmed],
            ['18:00', 8, 'S02', ReservationStatus::Confirmed, 'Sinh nhật, nhà hàng chuẩn bị nến giúp'],
            ['18:30', 3, null, ReservationStatus::Pending],
            ['19:00', 12, 'P02', ReservationStatus::Confirmed, 'Tiệc công ty, đặt trước set lẩu'],
            ['19:00', 4, null, ReservationStatus::Cancelled, null, 'Khách gọi báo hủy'],
            ['19:30', 5, null, ReservationStatus::Pending],
            ['20:00', 2, 'A04', ReservationStatus::Confirmed],
        ];

        foreach ($plan as $row) {
            [$time, $size, $table, $status] = $row;
            $at = $this->now->setTimeFromTimeString($time);

            if ($at->lessThan($this->now->subMinutes(30)) && $status !== ReservationStatus::Cancelled) {
                $status = $status === ReservationStatus::Confirmed ? ReservationStatus::NoShow : ReservationStatus::Cancelled;
            }

            $this->reservation($at, $size, $table, $status, $row[4] ?? null, $row[5] ?? null);
        }
    }

    private function upcoming(): void
    {
        for ($day = 1; $day <= 14; $day++) {
            $date = $this->now->startOfDay()->addDays($day);
            $count = mt_rand(1, 3) + ($date->isWeekend() ? 2 : 0);

            for ($i = 0; $i < $count; $i++) {
                $size = [2, 2, 3, 4, 4, 5, 6, 8, 10][mt_rand(0, 8)];
                $status = $this->pick([ReservationStatus::Confirmed->value => 60, ReservationStatus::Pending->value => 35, ReservationStatus::Cancelled->value => 5]);
                $table = $status === ReservationStatus::Confirmed->value && mt_rand(1, 100) <= 70 ? $this->tableFor($size) : null;

                $this->reservation($date->setTimeFromTimeString(self::SLOTS[mt_rand(0, count(self::SLOTS) - 1)]), $size, $table, ReservationStatus::from($status));
            }
        }
    }

    /** Quá khứ: khách không đến / hủy (khách đã đến nằm trong lịch sử phiên bàn). */
    private function past(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $date = $this->now->startOfDay()->subDays(mt_rand(1, 89));
            $size = [2, 3, 4, 4, 6, 8][mt_rand(0, 5)];
            $status = mt_rand(1, 100) <= 45 ? ReservationStatus::NoShow : ReservationStatus::Cancelled;

            $this->reservation($date->setTimeFromTimeString(self::SLOTS[mt_rand(0, count(self::SLOTS) - 1)]), $size,
                mt_rand(0, 1) ? $this->tableFor($size) : null, $status,
                internalNote: $status === ReservationStatus::Cancelled ? collect(['Khách gọi báo hủy', 'Đổi sang ngày khác', 'Trùng lịch'])->random() : 'Gọi 2 lần không nghe máy');
        }
    }

    private function reservation(CarbonImmutable $at, int $size, ?string $tableCode, ReservationStatus $status, ?string $note = null, ?string $internalNote = null): void
    {
        [$name, $phone] = DemoPeople::customer();
        $source = $this->pick([ReservationSource::Web->value => 55, ReservationSource::Phone->value => 35, ReservationSource::Staff->value => 10]);
        $createdAt = $at->subHours(2)->min($this->now)->subMinutes(mt_rand(10, 60 * 96));
        $handled = $status !== ReservationStatus::Pending || $source !== ReservationSource::Web->value;

        Reservation::query()->forceCreate([
            'code' => $this->code($createdAt),
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => mt_rand(1, 100) <= 30 ? 'khach'.mt_rand(100, 999).'@example.com' : null,
            'party_size' => $size,
            'reserved_at' => $at,
            'duration_minutes' => 120,
            'dining_table_id' => $tableCode ? $this->tables[$tableCode]->id : null,
            'status' => $status,
            'source' => $source,
            'note' => $note ?? (mt_rand(1, 100) <= 35 ? self::NOTES[mt_rand(0, count(self::NOTES) - 1)] : null),
            'internal_note' => $internalNote,
            'handled_by' => $handled ? $this->staff->random()->id : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /** Mã như App\Support\Code::make('R') nhưng theo ngày tạo đặt bàn. */
    private function code(CarbonImmutable $createdAt): string
    {
        do {
            $code = 'R'.$createdAt->format('ymd').'-';
            for ($i = 0; $i < 4; $i++) {
                $code .= '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[mt_rand(0, 31)];
            }
        } while (isset($this->usedCodes[$code]));

        $this->usedCodes[$code] = true;

        return $code;
    }

    private function tableFor(int $size): string
    {
        return $this->tables
            ->filter(fn (DiningTable $table) => $table->capacity >= $size && $table->capacity <= max(4, $size * 2))
            ->keys()
            ->random();
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function pick(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
