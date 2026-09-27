<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dùng: ->middleware('role:waiter,manager,admin'). Tài khoản bị khóa bị đăng xuất ngay.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Tài khoản đã bị khóa.']);
        }

        abort_unless($user->hasRole(...array_map(UserRole::from(...), $roles)), 403, 'Bạn không có quyền vào trang này.');

        return $next($request);
    }
}
