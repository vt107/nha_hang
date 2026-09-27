<?php

use Illuminate\Support\Facades\Schedule;

// Số liệu biểu đồ Horizon (/horizon)
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Dọn job lỗi cũ hơn 7 ngày
Schedule::command('queue:prune-failed --hours=168')->daily();

// Cache tag trên Redis (menu) để lại key tham chiếu cũ: dọn định kỳ theo khuyến nghị của Laravel
Schedule::command('cache:prune-stale-tags')->hourly();
