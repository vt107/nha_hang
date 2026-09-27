<?php

namespace App\Providers\Filament;

use App\Support\Site;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Thương hiệu lấy từ Cài đặt (closure: chỉ đọc DB khi render, không phải lúc build image / boot).
            ->brandName(fn () => app(Site::class)->name())
            ->brandLogo(fn () => app(Site::class)->logoUrl())
            ->brandLogoHeight('2.5rem')
            ->favicon(fn () => app(Site::class)->faviconUrl())
            ->colors(fn () => [
                'primary' => app(Site::class)->hasCustomColor() ? Color::hex(app(Site::class)->color()) : Color::Amber,
            ])
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Vận hành'),
                NavigationGroup::make('Thực đơn'),
                NavigationGroup::make('Bàn'),
                NavigationGroup::make('Báo cáo'),
                NavigationGroup::make('Hệ thống'),
            ])
            ->navigationItems([
                NavigationItem::make('Màn hình phục vụ')
                    ->url('/staff', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->group('Vận hành')
                    ->sort(1),
                NavigationItem::make('Màn hình bếp')
                    ->url('/kitchen', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedFire)
                    ->group('Vận hành')
                    ->sort(2),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
