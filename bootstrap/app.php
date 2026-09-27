<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureTableSession;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'table.session' => EnsureTableSession::class,
        ]);

        // Webhook ngân hàng xác thực bằng API key, không có CSRF token.
        $middleware->preventRequestForgery(except: ['webhooks/*']);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn () => LoginController::homeFor(auth()->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
