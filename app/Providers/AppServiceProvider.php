<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Request cập nhật của Livewire (/livewire/update) cũng phải qua kiểm tra vai trò như route gốc.
        Livewire::addPersistentMiddleware([EnsureUserHasRole::class]);

        Gate::define('access-admin', fn (User $user) => $user->is_active && $user->role->canAccessAdmin());

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // Khách gọi món / gọi nhân viên: chặn spam theo phiên bàn + IP.
        RateLimiter::for('customer-actions', fn (Request $request) => Limit::perMinute(12)
            ->by($request->session()->get('table_session_token', '').'|'.$request->ip()));

        RateLimiter::for('reservations', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
    }
}
