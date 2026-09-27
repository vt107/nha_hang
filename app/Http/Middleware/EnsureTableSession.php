<?php

namespace App\Http\Middleware;

use App\Models\TableSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trang của khách chỉ vào được sau khi quét QR và phiên bàn còn mở. Gắn cookie device_id (giỏ hàng theo điện thoại).
 */
class EnsureTableSession
{
    public const DEVICE_COOKIE = 'device_id';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->session()->get('table_session_token');
        $session = $token ? TableSession::query()->where('token', $token)->first() : null;

        if (! $session) {
            return response()->view('customer.message', [
                'title' => 'Vui lòng quét mã QR',
                'message' => 'Hãy quét mã QR dán trên bàn để xem menu và gọi món.',
            ], 403);
        }

        if (! $session->isOpen()) {
            $request->session()->forget('table_session_token');

            return response()->view('customer.message', [
                'title' => 'Cảm ơn quý khách!',
                'message' => 'Bàn đã thanh toán xong. Hẹn gặp lại quý khách. Nếu muốn gọi thêm, hãy quét lại mã QR trên bàn.',
                'icon' => '🙏',
            ]);
        }

        if (! $request->cookie(self::DEVICE_COOKIE)) {
            $deviceId = (string) Str::uuid();
            $request->cookies->set(self::DEVICE_COOKIE, $deviceId);
            Cookie::queue(self::DEVICE_COOKIE, $deviceId, 60 * 24 * 365);
        }

        return $next($request);
    }
}
