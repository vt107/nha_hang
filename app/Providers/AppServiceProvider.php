<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use App\Support\Demo\DemoMode;
use App\Support\Site;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Site::class);
    }

    public function boot(): void
    {
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // SSL kết thúc ở Cloudflare / proxy: vẫn sinh link https cho QR, redirect, asset.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Request cập nhật của Livewire (/livewire/update) cũng phải qua kiểm tra vai trò như route gốc.
        Livewire::addPersistentMiddleware([EnsureUserHasRole::class]);

        // Cấu hình giao diện / SEO cho view của app (không gắn vào view của Filament / vendor).
        View::composer(
            ['layouts::*', 'layouts.*', 'components.*', 'partials.*', 'public.*', 'customer.*', 'staff.*', 'admin.*', 'auth.*', 'errors.*', 'livewire.*'],
            fn ($view) => $view->with('site', $this->app->make(Site::class)),
        );

        Gate::define('access-admin', fn (User $user) => $user->is_active && $user->role->canAccessAdmin());

        Event::listen(Login::class, function (Login $event) {
            // Bản demo chỉ xem: không ghi lần đăng nhập cuối (lệnh ghi bị guard SQL chặn sẽ làm đăng nhập thất bại).
            if ($event->user instanceof User && ! DemoMode::guarding()) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // Khách gọi món / gọi nhân viên: chặn spam theo phiên bàn + IP.
        RateLimiter::for('customer-actions', fn (Request $request) => Limit::perMinute(12)
            ->by($request->session()->get('table_session_token', '').'|'.$request->ip()));

        RateLimiter::for('reservations', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
    }
}
