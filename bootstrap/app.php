<?php

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
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'table.session' => \App\Http\Middleware\EnsureTableSession::class,
        ]);

        // Webhook ngân hàng xác thực bằng API key, không có CSRF token.
        $middleware->preventRequestForgery(except: ['webhooks/*']);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn () => \App\Http\Controllers\Auth\LoginController::homeFor(auth()->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
