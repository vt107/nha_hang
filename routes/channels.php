<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Kênh công khai "table-session.{token}" cho khách không cần khai báo: token ngẫu nhiên 32 ký tự.

Broadcast::channel('staff', fn (User $user) => $user->is_active
    && $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Waiter));

Broadcast::channel('kitchen', fn (User $user) => $user->is_active
    && $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Kitchen));
