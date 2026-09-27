<?php

namespace App\Http\Controllers\Customer;

use App\Enums\TableSessionSource;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\Tables\TableSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Khách quét QR trên bàn (/t/{qr_token}): mở / vào phiên đang mở của bàn, ghi token phiên vào session trình duyệt.
 */
class QrEntryController extends Controller
{
    public function __invoke(Request $request, string $token, TableSessionService $sessions): RedirectResponse|Response
    {
        $table = DiningTable::query()->where('qr_token', $token)->first();

        if (! $table) {
            return response()->view('customer.message', [
                'title' => 'Mã QR không hợp lệ',
                'message' => 'Mã QR này đã hết hiệu lực. Vui lòng quét mã đang dán trên bàn hoặc gọi nhân viên.',
            ], 404);
        }

        try {
            $session = $sessions->openForTable($table, TableSessionSource::Qr);
        } catch (BusinessException $e) {
            return response()->view('customer.message', ['title' => $table->displayName(), 'message' => $e->getMessage()], 423);
        }

        $request->session()->put('table_session_token', $session->token);

        return redirect()->route('customer.menu');
    }
}
