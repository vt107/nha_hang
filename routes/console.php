<?php

use App\Support\Demo\DemoMode;
use Illuminate\Support\Facades\Schedule;

// Số liệu biểu đồ Horizon (/horizon)
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Dọn job lỗi cũ hơn 7 ngày
Schedule::command('queue:prune-failed --hours=168')->daily();

// Cache tag trên Redis (menu) để lại key tham chiếu cũ: dọn định kỳ theo khuyến nghị của Laravel
Schedule::command('cache:prune-stale-tags')->hourly();

// Bản demo (DEMO_MODE=true): dựng lại dữ liệu mẫu mỗi ngày để ngày giờ luôn mới.
// DEMO_RESET_AT nhận nhiều mốc (vd "04:00,11:00,17:00") để bàn đang phục vụ / bếp không bị "cũ" cả ngày.
foreach (array_filter(array_map('trim', explode(',', (string) config('demo.reset_at')))) as $time) {
    Schedule::command('demo:reset --force')
        ->dailyAt($time)
        ->when(fn () => DemoMode::enabled())
        ->withoutOverlapping();
}
