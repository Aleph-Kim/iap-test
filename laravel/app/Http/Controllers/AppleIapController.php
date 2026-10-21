<?php

namespace App\Http\Controllers;

use App\Models\ApplePurchase;
use App\Support\AppleJwsVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class AppleIapController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jws' => ['required', 'string'],
        ]);

        try {
            $transaction = (new AppleJwsVerifier(config('services.apple_iap.root_cert')))->verify($validated['jws']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($transaction['bundleId'] !== config('services.apple_iap.bundle_id')
            || $transaction['environment'] !== config('services.apple_iap.environment')) {
            return response()->json(['message' => 'Invalid app or environment'], 422);
        }

        if (isset($transaction['revocationDate'])) {
            return response()->json(['message' => 'Transaction revoked'], 422);
        }

        // transaction_id unique로 같은 거래의 중복 지급 방지
        $purchase = ApplePurchase::firstOrCreate(
            ['transaction_id' => $transaction['transactionId']],
            [
                'user_id' => $request->user()->id,
                'product_id' => $transaction['productId'],
                'environment' => $transaction['environment'],
                'purchased_at' => Carbon::createFromTimestampMs($transaction['purchaseDate']),
            ],
        );

        if ($purchase->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Transaction belongs to another user'], 409);
        }

        if ($purchase->wasRecentlyCreated) {
            // product_id별 지급 처리 위치 (코인 적립, 기능 해제 등 미구현)
        }

        return response()->json([
            'transactionId' => $purchase->transaction_id,
            'productId' => $purchase->product_id,
        ]);
    }
}
