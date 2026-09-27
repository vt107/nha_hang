<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Billing\BankTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook SePay (https://docs.sepay.vn): cấu hình URL {APP_URL}/webhooks/sepay, xác thực "API Key",
 * key đặt ở SEPAY_WEBHOOK_KEY. SePay coi là thành công khi nhận HTTP 200 + {"success": true}.
 */
class SePayWebhookController extends Controller
{
    public function __invoke(Request $request, BankTransferService $transfers): JsonResponse
    {
        $key = (string) config('services.sepay.webhook_key');

        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Webhook chưa được cấu hình'], 503);
        }

        $header = (string) $request->header('Authorization');

        if (! hash_equals('apikey '.strtolower($key), strtolower(trim($header)))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->validate([
            'id' => ['required'],
            'transferAmount' => ['required', 'integer', 'min:0'],
            'transferType' => ['required', 'in:in,out'],
            'content' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'accountNumber' => ['nullable', 'string'],
            'subAccount' => ['nullable', 'string'],
            'referenceCode' => ['nullable', 'string'],
            'transactionDate' => ['nullable', 'string'],
            'gateway' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'accumulated' => ['nullable'],
        ]);

        $transaction = $transfers->handleSePay($payload);

        return response()->json(['success' => true, 'status' => $transaction->status->value]);
    }
}
