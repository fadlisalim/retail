<?php

namespace App\Http\Controllers;

use App\Services\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single entry point for gateway callbacks. It trusts NOTHING from the request
 * until PaymentManager verifies the HMAC signature; replay/duplicate protection
 * and status handling all live in the manager (spec §18/§30).
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentManager $payments): JsonResponse
    {
        $signature = $request->header('X-Signature', $request->header('X-Callback-Token', ''));

        $result = $payments->handleWebhook(
            providerCode: $provider,
            payload: $request->all(),
            rawBody: $request->getContent(),
            signature: (string) $signature,
            ip: $request->ip(),
        );

        $httpStatus = match ($result['status']) {
            'processed', 'duplicate' => 200,
            'invalid_signature' => 401,
            'unknown_provider' => 404,
            default => 202,
        };

        return response()->json($result, $httpStatus);
    }
}
