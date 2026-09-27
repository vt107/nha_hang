<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

/**
 * Khoảng ngày cho báo cáo, dựng từ bộ lọc trên trang Doanh thu.
 */
final readonly class DateRange
{
    public const PRESETS = [
        'today' => 'Hôm nay',
        'yesterday' => 'Hôm qua',
        'last_7_days' => '7 ngày qua',
        'last_30_days' => '30 ngày qua',
        'this_month' => 'Tháng này',
        'last_month' => 'Tháng trước',
        'custom' => 'Tùy chọn',
    ];

    public function __construct(
        public Carbon $from,
        public Carbon $to,
    ) {}

    /**
     * @param  array<string, mixed>|null  $filters  ['preset' => ..., 'from' => 'Y-m-d', 'to' => 'Y-m-d']
     */
    public static function fromFilters(?array $filters): self
    {
        $today = today();

        return match ($filters['preset'] ?? 'last_7_days') {
            'today' => new self($today->copy(), $today->copy()->endOfDay()),
            'yesterday' => new self($today->copy()->subDay(), $today->copy()->subDay()->endOfDay()),
            'last_30_days' => new self($today->copy()->subDays(29), $today->copy()->endOfDay()),
            'this_month' => new self($today->copy()->startOfMonth(), $today->copy()->endOfDay()),
            'last_month' => new self($today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()),
            'custom' => self::custom($filters['from'] ?? null, $filters['to'] ?? null),
            default => new self($today->copy()->subDays(6), $today->copy()->endOfDay()),
        };
    }

    public function isSingleDay(): bool
    {
        return $this->from->isSameDay($this->to);
    }

    public function label(): string
    {
        return $this->isSingleDay()
            ? $this->from->format('d/m/Y')
            : $this->from->format('d/m/Y').' - '.$this->to->format('d/m/Y');
    }

    private static function custom(?string $from, ?string $to): self
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : today()->subDays(6);
        $end = $to ? Carbon::parse($to)->endOfDay() : today()->endOfDay();

        return $start->lte($end) ? new self($start, $end) : new self($end->copy()->startOfDay(), $start->copy()->endOfDay());
    }
}
